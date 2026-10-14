<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\Attempt;
use App\Models\AttemptEvent;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamGrant;
use App\Models\Result;
use App\Models\StudentStats;

/**
 * Sinh viên: xem kỳ thi của lớp mình, làm bài, nộp bài và xem kết quả.
 *  - Chỉ thấy kỳ thi đã công bố cho lớp của mình; mỗi kỳ thi làm 1 lần (trừ khi giáo viên cấp quyền thi lại).
 *  - Đồng hồ do server tính (deadline_at). Hết giờ thì tự nộp bằng đáp án đã tự lưu.
 *  - Đáp án đúng và giải thích chỉ hiện SAU KHI kỳ thi kết thúc (hết giờ đóng cổng hoặc giáo viên đóng đề),
 *    để sinh viên làm xong sớm không đưa đáp án cho bạn đang thi.
 */
class StudentExamController extends Controller
{
    private const IMAGE_TYPES = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];

    private function uid(): int
    {
        return (int) $this->user()['id'];
    }

    private function classId(): ?int
    {
        $class = Classroom::ofStudent($this->uid());
        return $class ? (int) $class['id'] : null;
    }

    // ------------------------------------------------------------------
    // Danh sách / chi tiết kỳ thi
    // ------------------------------------------------------------------
    public function index(): void
    {
        Attempt::finalizeExpired(null, $this->uid());
        $classId = $this->classId();

        $this->view('student/index', [
            'title'    => 'Kỳ thi của tôi',
            'hasClass' => $classId !== null,
            'items'    => $classId !== null ? Attempt::listForStudent($this->uid(), $classId) : [],
        ]);
    }

    public function show(string $id): void
    {
        $exam = $this->findExam($id);
        Attempt::finalizeExpired((int) $exam['id'], $this->uid());

        $this->view('student/show', [
            'title'   => $exam['title'],
            'exam'    => $exam,
            'state'   => Exam::state($exam),
            'attempt' => Attempt::forExamUser((int) $exam['id'], $this->uid()),
            'grant'   => ExamGrant::activeFor((int) $exam['id'], $this->uid()),
        ]);
    }

    // ------------------------------------------------------------------
    // Bắt đầu làm bài
    // ------------------------------------------------------------------
    public function start(string $id): void
    {
        $exam = $this->findExam($id);
        $examId = (int) $exam['id'];
        $existing = Attempt::forExamUser($examId, $this->uid());

        if ($existing) {
            $this->redirect($this->attemptUrl($existing));
        }
        // Giáo viên có thể cấp riêng cho một sinh viên quyền thi muộn / thi lại (ExamGrant): vào được dù cổng thi đã đóng
        $grant = ExamGrant::activeFor($examId, $this->uid());
        if (Exam::state($exam) !== 'open' && !$grant) {
            Session::flash('error', 'Kỳ thi hiện không mở. Bạn chỉ vào thi được trong khoảng thời gian cổng thi mở.');
            $this->redirect('/my-exams/' . $examId);
        }
        if ((int) $exam['question_count'] < 1) {
            Session::flash('error', 'Đề thi chưa có câu hỏi. Vui lòng liên hệ giáo viên.');
            $this->redirect('/my-exams/' . $examId);
        }

        try {
            $attemptId = Attempt::start($exam, $this->uid(), $grant ? (int) $grant['extra_minutes'] : 0);
            if ($grant) {
                ExamGrant::markUsed($examId, $this->uid());
            }
        } catch (\PDOException $e) {
            // Bấm "Bắt đầu" hai lần cùng lúc: lượt thứ hai vi phạm UNIQUE -> đưa về lượt đã có
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $dup = Attempt::forExamUser($examId, $this->uid());
            if (!$dup) {
                throw $e;
            }
            $this->redirect($this->attemptUrl($dup));
        }
        $this->redirect('/attempts/' . $attemptId);
    }

    // ------------------------------------------------------------------
    // Làm bài
    // ------------------------------------------------------------------
    public function take(string $id): void
    {
        $attempt = $this->ownAttempt($id);
        $attempt = $this->settleIfExpired($attempt);
        if ($attempt['status'] === 'submitted') {
            $this->redirect('/attempts/' . $attempt['id'] . '/result');
        }

        $paper = Attempt::paper((int) $attempt['id'], false);
        $answered = count(array_filter($paper, fn($q) => (bool) array_filter($q['options'], fn($o) => $o['selected'])));

        // Vào lại / tải lại trang thi nghĩa là sinh viên đã quay lại: đóng các lần rời trang còn mở từ trang cũ
        AttemptEvent::close((int) $attempt['id']);

        $this->view('student/take', [
            'title'       => $attempt['title'],
            'attempt'     => $attempt,
            'paper'       => $paper,
            'answered'    => $answered,
            'secondsLeft' => Attempt::secondsLeft($attempt),
            'track'       => AttemptEvent::enabled(),
            'leaves'      => AttemptEvent::leaveCount((int) $attempt['id']),
        ]);
    }

    /** Tự lưu đáp án của một câu (gọi nền bằng fetch, trả JSON) */
    public function answer(string $id): void
    {
        $uid = $this->uid();
        // Endpoint tự lưu bị gọi dồn dập: nhả khóa session ngay (CSRF đã kiểm tra ở Router) để các request
        // của cùng một sinh viên không phải xếp hàng chờ nhau
        session_write_close();
        $attempt = ctype_digit($id) ? Attempt::detailOwned((int) $id, $uid) : null;
        if (!$attempt) {
            Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        if ($attempt['status'] !== 'in_progress' || Attempt::isExpired($attempt)) {
            Response::json(['ok' => false, 'finished' => true], 409);
        }

        $qid = (string) $this->request->input('question_id', '');
        $options = [];
        foreach ((array) $this->request->input('option_ids', []) as $v) {
            if (ctype_digit((string) $v)) {
                $options[] = (int) $v;
            }
        }
        if (!ctype_digit($qid) || !Attempt::saveAnswer((int) $attempt['id'], (int) $qid, $options)) {
            Response::json(['ok' => false, 'error' => 'invalid'], 422);
        }
        Response::json(['ok' => true, 'seconds_left' => Attempt::secondsLeft($attempt)]);
    }

    /**
     * Trình duyệt báo hành vi khác thường (rời tab, rời cửa sổ, sao chép...) hoặc báo "đã quay lại" (type = return).
     * Chỉ ghi khi đang làm bài và còn trong hạn; mọi mốc thời gian do máy chủ ghi. Chỉ là dấu hiệu tham khảo cho giáo viên.
     */
    public function event(string $id): void
    {
        $uid = $this->uid();
        session_write_close(); // gọi nền liên tục: không giữ khóa session (CSRF đã kiểm tra ở Router)
        $attempt = ctype_digit($id) ? Attempt::detailOwned((int) $id, $uid) : null;
        if (!$attempt) {
            Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        if ($attempt['status'] !== 'in_progress' || Attempt::isExpired($attempt)) {
            Response::json(['ok' => false, 'finished' => true], 409);
        }

        $attemptId = (int) $attempt['id'];
        $type = $this->text('type');
        if ($type === 'return') {
            AttemptEvent::close($attemptId);
        } elseif (!AttemptEvent::record($attemptId, $type) && !isset(AttemptEvent::TYPES[$type])) {
            Response::json(['ok' => false, 'error' => 'invalid'], 422);
        }
        Response::json(['ok' => true, 'leaves' => AttemptEvent::leaveCount($attemptId)]);
    }

    public function submit(string $id): void
    {
        $attempt = $this->ownAttempt($id);

        if ($attempt['status'] === 'in_progress') {
            $expired = Attempt::isExpired($attempt);
            // Form nộp bài mang theo toàn bộ lựa chọn trên màn hình: lưu lại lần cuối cho chắc (trừ khi đã quá hạn)
            if (!$expired && (string) $this->request->input('answers_sent', '') === '1') {
                Attempt::saveAllAnswers((int) $attempt['id'], (array) $this->request->input('a', []));
            }
            $auto = $expired || (string) $this->request->input('auto', '') === '1';
            Attempt::grade((int) $attempt['id'], $auto);
            Session::flash('success', $auto ? 'Hết giờ làm bài. Hệ thống đã tự nộp bài và chấm điểm.' : 'Đã nộp bài và chấm điểm.');
        }
        $this->redirect('/attempts/' . $attempt['id'] . '/result');
    }

    // ------------------------------------------------------------------
    // Kết quả
    // ------------------------------------------------------------------
    public function result(string $id): void
    {
        $attempt = $this->ownAttempt($id);
        $attempt = $this->settleIfExpired($attempt);
        if ($attempt['status'] !== 'submitted') {
            $this->redirect('/attempts/' . $attempt['id']);
        }

        // Kỳ thi kết thúc: hết giờ đóng cổng hoặc giáo viên đã đóng đề
        $ended = $attempt['exam_status'] === 'closed' || time() > strtotime($attempt['end_at']);
        $paper = $ended ? Attempt::paper((int) $attempt['id'], true) : [];

        $full = $partial = 0;
        foreach (Attempt::paper((int) $attempt['id'], false) as $q) {
            if ($q['earned'] !== null && $q['earned'] >= 1.0) {
                $full++;
            } elseif ($q['earned'] !== null && $q['earned'] > 0) {
                $partial++;
            }
        }

        $this->view('student/result', [
            'title'    => 'Kết quả - ' . $attempt['title'],
            'attempt'  => $attempt,
            'ended'    => $ended,
            'paper'    => $paper,
            'full'     => $full,
            'partial'  => $partial,
            'partialOn' => Attempt::partialCredit(),
        ]);
    }

    // ------------------------------------------------------------------
    // Lịch thi
    // ------------------------------------------------------------------
    public function schedule(): void
    {
        Attempt::finalizeExpired(null, $this->uid());
        $classId = $this->classId();
        $all = $classId !== null ? Attempt::listForStudent($this->uid(), $classId) : [];
        $month = StudentStats::monthParam($this->text('month'));

        $this->view('student/schedule', ['title' => 'Lịch thi', 'hasClass' => $classId !== null] + StudentStats::schedule($all, $month));
    }

    // ------------------------------------------------------------------
    // Lịch sử kết quả (toàn bộ bài đã nộp, nhóm theo môn) và bản in
    // ------------------------------------------------------------------
    public function results(): void
    {
        $this->view('student/results', ['title' => 'Kết quả của tôi'] + $this->historyData($this->text('subject')));
    }

    /** Bảng điểm cá nhân để in / lưu PDF (trình duyệt: In -> Lưu dạng PDF) */
    public function resultsPrint(): void
    {
        $this->view('student/print_results', [
            'title'  => 'Bảng điểm cá nhân',
            'user'   => $this->user(),
        ] + $this->historyData($this->text('subject')), 'layouts/print');
    }

    /** Phiếu kết quả của một bài thi để in / lưu PDF. Chỉ gồm điểm và thông tin bài thi, KHÔNG có đáp án (đáp án chỉ hiện sau khi kỳ thi kết thúc) */
    public function printAttempt(string $id): void
    {
        $attempt = $this->settleIfExpired($this->ownAttempt($id));
        if ($attempt['status'] !== 'submitted') {
            $this->redirect('/attempts/' . $attempt['id']);
        }
        $full = 0;
        foreach (Attempt::paper((int) $attempt['id'], false) as $q) {
            if ($q['earned'] !== null && $q['earned'] >= 1.0) {
                $full++;
            }
        }
        $this->view('student/print_attempt', [
            'title'   => 'Phiếu kết quả - ' . $attempt['title'],
            'attempt' => $attempt,
            'user'    => $this->user(),
            'class'   => Classroom::ofStudent($this->uid()),
            'full'    => $full,
            'pass'    => Result::passScore(),
        ], 'layouts/print');
    }

    // ------------------------------------------------------------------
    // Ảnh minh họa (thư mục uploads/ không truy cập trực tiếp được; sinh viên chỉ xem ảnh của bài mình)
    // ------------------------------------------------------------------
    public function image(string $id, string $file): void
    {
        $attempt = $this->ownAttempt($id);
        session_write_close(); // trang có nhiều ảnh tải song song: không giữ khóa session
        $ok = preg_match('/^[a-f0-9]{32}\.(jpg|png|gif|webp)$/', $file, $m) && Attempt::hasImage((int) $attempt['id'], $file);
        $path = ROOT . '/uploads/questions/' . $file;
        if (!$ok || !is_file($path)) {
            Response::abort(404, 'Không tìm thấy ảnh.');
        }

        header('Content-Type: ' . self::IMAGE_TYPES[$m[1]]);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    // ------------------------------------------------------------------
    // Hàm phụ
    // ------------------------------------------------------------------
    /** Kỳ thi mà lớp của sinh viên được thi (và đã công bố); không thì 404 */
    private function findExam(string $id): array
    {
        $classId = $this->classId();
        $exam = ($classId !== null && ctype_digit($id)) ? Attempt::examForClass((int) $id, $classId, $this->uid()) : null;
        if (!$exam) {
            Response::abort(404, 'Không tìm thấy kỳ thi.');
        }
        return $exam;
    }

    /** Tham số chuỗi từ query / form (mảng hoặc thiếu -> chuỗi rỗng) */
    private function text(string $key): string
    {
        $v = $this->request->input($key, '');
        return is_string($v) ? trim($v) : '';
    }

    /** Dữ liệu lịch sử kết quả: StudentStats::history() + lớp của sinh viên */
    private function historyData(string $subject): array
    {
        Attempt::finalizeExpired(null, $this->uid());
        $class = Classroom::ofStudent($this->uid());
        $all = $class ? Attempt::listForStudent($this->uid(), (int) $class['id']) : [];
        $doneIds = [];
        foreach ($all as $x) {
            if ($x['attempt_status'] === 'submitted') {
                $doneIds[] = (int) $x['id'];
            }
        }
        $classAvg = $class ? Result::classAverages($doneIds, (int) $class['id']) : [];
        return StudentStats::history($all, $classAvg, $subject) + ['class' => $class, 'hasClass' => $class !== null, 'pass' => Result::passScore()];
    }

    private function ownAttempt(string $id): array
    {
        $attempt = ctype_digit($id) ? Attempt::detailOwned((int) $id, $this->uid()) : null;
        if (!$attempt) {
            Response::abort(404, 'Không tìm thấy bài làm.');
        }
        return $attempt;
    }

    /** Bài đã quá hạn mà chưa nộp thì nộp + chấm ngay, trả về bản ghi mới */
    private function settleIfExpired(array $attempt): array
    {
        if ($attempt['status'] === 'in_progress' && Attempt::isExpired($attempt)) {
            Attempt::grade((int) $attempt['id'], true, strtotime($attempt['deadline_at']));
            return Attempt::detailOwned((int) $attempt['id'], $this->uid()) ?? $attempt;
        }
        return $attempt;
    }

    private function attemptUrl(array $attempt): string
    {
        return $attempt['status'] === 'submitted' ? '/attempts/' . $attempt['id'] . '/result' : '/attempts/' . $attempt['id'];
    }
}
