<?php
namespace App\Controllers;

use App\Models\Audit;
use App\Core\App;
use App\Core\Controller;
use App\Core\Mailer;
use App\Core\Response;
use App\Core\Session;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Notification;
use App\Models\Question;
use App\Models\Subject;

/**
 * Kỳ thi / đề thi.
 *  - Quản trị viên: mọi kỳ thi
 *  - Giáo viên: chỉ kỳ thi của các môn mình được phân công (kiểm tra trong canManage())
 * Quy trình: tạo kỳ thi (bản nháp) -> chọn câu hỏi -> công bố. Chỉ bản nháp mới sửa được câu hỏi và xóa được.
 */
class ExamController extends Controller
{
    private const PER_PAGE = 10;
    private const STATES = ['draft', 'upcoming', 'open', 'ended', 'closed'];
    private const DT_FORMAT = 'Y-m-d\TH:i';

    private function isAdmin(): bool
    {
        return $this->user()['role'] === 'admin';
    }

    /** null = không giới hạn (admin); ngược lại là id giáo viên */
    private function scope(): ?int
    {
        return $this->isAdmin() ? null : (int) $this->user()['id'];
    }

    private function canManage(int $subjectId): bool
    {
        return $this->isAdmin() || Subject::isAssigned($subjectId, (int) $this->user()['id']);
    }

    // ------------------------------------------------------------------
    // Danh sách / chi tiết
    // ------------------------------------------------------------------
    public function index(): void
    {
        $f = $this->filters();
        $page = (int) $this->request->input('page', 1);

        $this->view('exams/index', [
            'title'    => 'Đề thi',
            'result'   => Exam::search($f, $page, self::PER_PAGE, $this->scope()),
            'f'        => $f,
            'subjects' => Question::subjectChoices($this->scope(), false),
            'perPage'  => self::PER_PAGE,
            'isAdmin'  => $this->isAdmin(),
        ]);
    }

    public function show(string $id): void
    {
        $exam = $this->findAuthorized($id);
        $examId = (int) $exam['id'];
        $isDraft = $exam['status'] === 'draft';

        $this->view('exams/show', [
            'title'     => $exam['title'],
            'exam'      => $exam,
            'state'     => Exam::state($exam),
            'classes'   => Exam::classesOf($examId),
            'questions' => Exam::questionsOf($examId),
            'attempts'  => Attempt::countForExam($examId),
            'problems'  => $isDraft ? Exam::publishProblems($exam) : [],
            'available' => $isDraft ? Exam::availableQuestions((int) $exam['subject_id'], $examId, (string) $this->request->input('level', '')) : [],
            'counts'    => $isDraft ? Exam::availableCounts((int) $exam['subject_id'], $examId) : [],
            'level'     => $isDraft ? (string) $this->request->input('level', '') : '',
        ]);
    }

    // ------------------------------------------------------------------
    // Tạo / sửa: bước 1 chọn môn, bước 2 nhập thông tin
    // ------------------------------------------------------------------
    public function create(): void
    {
        $subjectId = (int) $this->request->input('subject_id', 0);
        $subject = $subjectId > 0 ? Subject::find($subjectId) : null;

        if (!$subject || !$subject['is_active'] || !$this->canManage($subjectId)) {
            $this->view('exams/pick', [
                'title'    => 'Tạo đề thi',
                'subjects' => Question::subjectChoices($this->scope(), true),
                'invalid'  => $subjectId > 0,
            ]);
            return;
        }

        $this->view('exams/form', [
            'title' => 'Tạo đề thi', 'mode' => 'create', 'exam' => null, 'subject' => $subject,
            'classes' => Exam::classChoices($subjectId, $this->scope()), 'selected' => [],
        ]);
    }

    public function store(): void
    {
        $subjectId = (int) $this->request->input('subject_id', 0);
        $subject = $subjectId > 0 ? Subject::find($subjectId) : null;
        if (!$subject || !$subject['is_active'] || !$this->canManage($subjectId)) {
            Session::flash('error', 'Vui lòng chọn một môn học hợp lệ.');
            $this->redirect('/exams/create');
        }

        [$data, $classIds, $errors] = $this->validate($this->request->all(), $subject, null);
        if ($errors) {
            $this->backWithErrors('/exams/create?subject_id=' . $subjectId, $errors, $data, $classIds);
        }

        $data['subject_id'] = $subjectId;
        $data['created_by'] = (int) $this->user()['id'];
        $data['status'] = 'draft';
        $id = Exam::saveWithClasses(null, $data, $classIds);

        Session::flash('success', 'Đã tạo đề thi "' . $data['title'] . '". Hãy chọn câu hỏi cho đề rồi công bố.');
        $this->redirect('/exams/' . $id);
    }

    public function edit(string $id): void
    {
        $exam = $this->findAuthorized($id);
        $this->view('exams/form', [
            'title' => 'Sửa đề thi', 'mode' => 'edit', 'exam' => $exam,
            'subject' => ['id' => $exam['subject_id'], 'code' => $exam['subject_code'], 'name' => $exam['subject_name']],
            'classes' => Exam::classChoices((int) $exam['subject_id'], $this->scope(), (int) $exam['id']),
            'selected' => array_map(fn($c) => (int) $c['id'], Exam::classesOf((int) $exam['id'])),
        ]);
    }

    public function update(string $id): void
    {
        $exam = $this->findAuthorized($id);
        $subject = ['id' => $exam['subject_id']];

        [$data, $classIds, $errors] = $this->validate($this->request->all(), $subject, (int) $exam['id']);
        if ($errors) {
            $this->backWithErrors('/exams/' . $exam['id'] . '/edit', $errors, $data, $classIds);
        }

        Exam::saveWithClasses((int) $exam['id'], $data, $classIds);
        Session::flash('success', 'Đã cập nhật đề thi.');
        $this->redirect('/exams/' . $exam['id']);
    }

    // ------------------------------------------------------------------
    // Công bố / đóng / chuyển về nháp
    // ------------------------------------------------------------------
    public function status(string $id): void
    {
        $exam = $this->findAuthorized($id);
        $examId = (int) $exam['id'];
        $action = (string) $this->request->input('action', '');
        $from = $exam['status'];

        if ($action === 'publish' && $from === 'draft') {
            $problems = Exam::publishProblems($exam);
            if ($problems) {
                Session::flash('error', 'Chưa thể công bố: ' . implode(' ', $problems));
                $this->redirect('/exams/' . $examId);
            }
            $to = 'published';
            $msg = 'Đã công bố đề thi. Sinh viên của các lớp được chọn sẽ thấy đề thi.';
        } elseif ($action === 'close' && $from === 'published') {
            $to = 'closed';
            $msg = 'Đã đóng đề thi.';
        } elseif ($action === 'reopen' && $from === 'closed') {
            $to = 'published';
            $msg = 'Đã mở lại đề thi.';
        } elseif ($action === 'unpublish' && ($from === 'published' || $from === 'closed')) {
            $n = Attempt::countForExam($examId);
            if ($n > 0) {
                Session::flash('error', "Đã có $n bài làm của sinh viên nên không thể chuyển về bản nháp (sẽ mất bài làm). Bạn có thể đóng đề thi thay thế.");
                $this->redirect('/exams/' . $examId);
            }
            $to = 'draft';
            $msg = 'Đã chuyển đề thi về bản nháp.';
        } else {
            Session::flash('error', 'Thao tác không phù hợp với trạng thái hiện tại của đề thi.');
            $this->redirect('/exams/' . $examId);
        }

        Exam::update($examId, ['status' => $to]);
        Audit::log('exam.' . $action, '"' . $exam['title'] . '" (#' . $examId . ')');
        if ($action === 'publish') {
            Notification::push(
                Exam::studentIds($examId),
                'Kỳ thi mới: ' . $exam['title'],
                $exam['subject_code'] . ' - ' . $exam['subject_name'] . '. Mở cổng: ' . fmt_date($exam['start_at']) . ' đến ' . fmt_date($exam['end_at'])
                    . '. Thời gian làm bài ' . (int) $exam['duration_minutes'] . ' phút.',
                '/my-exams/' . $examId
            );
            $msg = trim($msg . ' ' . $this->notifyStudents($exam));
        }
        Session::flash('success', $msg);
        $this->redirect('/exams/' . $examId);
    }

    // ------------------------------------------------------------------
    // Câu hỏi của đề (chỉ khi còn là bản nháp)
    // ------------------------------------------------------------------
    public function addQuestions(string $id): void
    {
        $exam = $this->findDraft($id);
        $examId = (int) $exam['id'];

        $ids = [];
        foreach ((array) $this->request->input('question_ids', []) as $v) {
            if (ctype_digit((string) $v)) {
                $ids[(int) $v] = (int) $v;
            }
        }
        $valid = Exam::validNewQuestionIds((int) $exam['subject_id'], $examId, array_values($ids));
        if (!$valid) {
            Session::flash('error', 'Vui lòng tick chọn ít nhất một câu hỏi để thêm.');
            $this->redirect('/exams/' . $examId . '#them-cau-hoi');
        }

        $added = Exam::addQuestions($examId, $valid);
        Session::flash('success', "Đã thêm $added câu hỏi vào đề.");
        $this->redirect('/exams/' . $examId);
    }

    public function randomQuestions(string $id): void
    {
        $exam = $this->findDraft($id);
        $examId = (int) $exam['id'];
        $subjectId = (int) $exam['subject_id'];

        $picked = [];
        $short = [];
        foreach (Question::DIFFICULTIES as $key => $label) {
            $n = (string) $this->request->input('n_' . $key, '0');
            $n = ctype_digit($n) ? min((int) $n, 200) : 0;
            if ($n < 1) {
                continue;
            }
            $ids = Exam::randomQuestionIds($subjectId, $examId, $key, $n);
            if (count($ids) < $n) {
                $short[] = $label . ': cần ' . $n . ', chỉ có ' . count($ids);
            }
            array_push($picked, ...$ids);
        }

        if (!$picked && !$short) {
            Session::flash('error', 'Vui lòng nhập số câu cần bốc ở ít nhất một mức độ khó.');
            $this->redirect('/exams/' . $examId . '#them-cau-hoi');
        }

        $added = Exam::addQuestions($examId, $picked);
        if ($added > 0) {
            Session::flash('success', "Đã bốc ngẫu nhiên và thêm $added câu hỏi vào đề.");
        }
        if ($short) {
            Session::flash('error', 'Ngân hàng câu hỏi không đủ câu (' . implode('; ', $short) . '). Đã thêm tất cả câu còn lại ở mức đó.');
        }
        $this->redirect('/exams/' . $examId);
    }

    public function removeQuestion(string $id, string $qid): void
    {
        $exam = $this->findDraft($id);
        if (ctype_digit($qid) && Exam::removeQuestion((int) $exam['id'], (int) $qid)) {
            Session::flash('success', 'Đã bỏ câu hỏi khỏi đề.');
        } else {
            Session::flash('error', 'Câu hỏi này không có trong đề.');
        }
        $this->redirect('/exams/' . $exam['id']);
    }

    public function clearQuestions(string $id): void
    {
        $exam = $this->findDraft($id);
        Exam::clearQuestions((int) $exam['id']);
        Session::flash('success', 'Đã bỏ tất cả câu hỏi khỏi đề.');
        $this->redirect('/exams/' . $exam['id']);
    }

    // ------------------------------------------------------------------
    // Sao chép đề thi (ra một bản nháp mới, dùng cho kỳ thi lặp lại / lớp khác / học kỳ sau)
    // ------------------------------------------------------------------
    public function copy(string $id): void
    {
        $exam = $this->findAuthorized($id);
        $subject = Subject::find((int) $exam['subject_id']);
        if (!$subject || !$subject['is_active']) {
            Session::flash('error', 'Môn học đang ngừng sử dụng nên không sao chép được đề thi.');
            $this->redirect('/exams/' . (int) $exam['id']);
        }

        $r = Exam::duplicate($exam, (int) $this->user()['id'], $this->scope());
        $notes = [];
        if ($r['skipped'] > 0) {
            $notes[] = $r['skipped'] . ' câu hỏi đã bị ẩn nên không được chép';
        }
        if ($r['classes'] < count(Exam::classesOf((int) $exam['id']))) {
            $notes[] = 'một số lớp không thuộc quyền của bạn nên không được chép';
        }
        Audit::log('exam.copy', '"' . $exam['title'] . '" (#' . (int) $exam['id'] . ') -> bản nháp #' . (int) $r['id']);
        Session::flash('success', 'Đã sao chép thành bản nháp mới. Hãy kiểm tra thời gian thi và lớp được thi rồi công bố.' . ($notes ? ' Lưu ý: ' . implode('; ', $notes) . '.' : ''));
        $this->redirect('/exams/' . $r['id']);
    }

    // ------------------------------------------------------------------
    // Xóa (chỉ bản nháp)
    // ------------------------------------------------------------------
    public function destroy(string $id): void
    {
        $exam = $this->findAuthorized($id);
        if ($exam['status'] !== 'draft') {
            Session::flash('error', 'Chỉ xóa được đề thi đang ở trạng thái bản nháp. Hãy chuyển về bản nháp trước.');
            $this->redirect('/exams/' . $exam['id']);
        }

        Exam::delete((int) $exam['id']);
        Audit::log('exam.delete', '"' . $exam['title'] . '" (#' . (int) $exam['id'] . ')');
        Session::flash('success', 'Đã xóa đề thi "' . $exam['title'] . '".');
        $this->redirect($this->listUrl());
    }

    // ------------------------------------------------------------------
    // Hàm phụ
    // ------------------------------------------------------------------
    /** Gửi email báo kỳ thi mới cho sinh viên các lớp được thi. Trả về câu thông báo cho người công bố. */
    private function notifyStudents(array $exam): string
    {
        $cfg = App::config('notify', []);
        if (array_key_exists('exam_published', $cfg) && !$cfg['exam_published']) {
            return '';
        }
        $emails = Exam::studentEmails((int) $exam['id']);
        if (!$emails) {
            return 'Chưa gửi email vì chưa có sinh viên nào có địa chỉ email.';
        }

        @set_time_limit(120);
        [$subject, $html, $text] = $this->examMail($exam, $this->absoluteUrl('/my-exams/' . (int) $exam['id']));
        $sent = Mailer::sendBulk($emails, $subject, $html, $text);
        if ($sent < 1) {
            error_log('Gửi email thông báo kỳ thi thất bại: ' . Mailer::lastError());
            return 'Không gửi được email thông báo' . (App::config('debug') ? ': ' . Mailer::lastError() : '.');
        }
        $note = "Đã gửi email thông báo tới $sent/" . count($emails) . ' sinh viên.';
        if ((App::config('mail', [])['driver'] ?? 'log') === 'log') {
            $note .= ' (chế độ thử: nội dung ghi vào storage/mail, chưa gửi thật)';
        }
        return $note;
    }

    /** @return array{0:string,1:string,2:string} [tiêu đề, HTML, văn bản thuần] */
    private function examMail(array $exam, string $link): array
    {
        $title = (string) $exam['title'];
        $subject = 'Thông báo kỳ thi: ' . $title;
        $when = fmt_date($exam['start_at']) . ' - ' . fmt_date($exam['end_at']);
        $minutes = (int) $exam['duration_minutes'];
        $subj = $exam['subject_code'] . ' - ' . $exam['subject_name'];
        $desc = trim((string) ($exam['description'] ?? ''));

        $text = "Xin chào các bạn sinh viên,\n\nCó kỳ thi mới dành cho lớp của bạn:\n\n"
              . "Kỳ thi: $title\nMôn học: $subj\nThời gian mở cổng: $when\nThời gian làm bài: $minutes phút\n"
              . ($desc !== '' ? "Ghi chú: $desc\n" : '')
              . "\nĐăng nhập và vào kỳ thi tại: $link\n\nHãy vào đúng giờ và chuẩn bị đường truyền ổn định.\n";

        $t = e($title);
        $s = e($subj);
        $w = e($when);
        $l = e($link);
        $d = $desc !== '' ? '<p style="margin:12px 0 0;color:#3d3438">' . nl2br(e($desc)) . '</p>' : '';
        $html = <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:0 auto;color:#1d1517">
  <h2 style="margin:0 0 12px">Thông báo kỳ thi</h2>
  <p>Xin chào các bạn sinh viên, có kỳ thi mới dành cho lớp của bạn:</p>
  <div style="border:1px solid #ece6e1;border-left:4px solid #c8202f;border-radius:10px;padding:14px 16px;background:#faf8f6">
    <div style="font-size:18px;font-weight:700">$t</div>
    <div style="margin-top:6px;color:#3d3438">Môn học: $s</div>
    <div style="color:#3d3438">Mở cổng: <strong>$w</strong></div>
    <div style="color:#3d3438">Thời gian làm bài: <strong>$minutes phút</strong></div>
    $d
  </div>
  <p style="margin:24px 0"><a href="$l" style="background:#c8202f;color:#fff;text-decoration:none;padding:12px 24px;border-radius:10px;font-weight:600;display:inline-block">Vào xem kỳ thi</a></p>
  <p style="font-size:14px;color:#7a6f73">Hãy vào đúng giờ và chuẩn bị đường truyền ổn định. Nếu nút không bấm được, sao chép địa chỉ sau vào trình duyệt:<br><span style="word-break:break-all">$l</span></p>
</div>
HTML;
        return [$subject, $html, $text];
    }

    /** Liên kết đầy đủ để đưa vào email */
    private function absoluteUrl(string $path): string
    {
        $base = App::config('app_url');
        if (!$base) {
            $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            if (!preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $host)) {
                $host = 'localhost';
            }
            $base = ($https ? 'https://' : 'http://') . $host . App::$baseUrl;
        }
        return rtrim((string) $base, '/') . $path;
    }

    private function findAuthorized(string $id): array
    {
        $exam = ctype_digit($id) ? Exam::detail((int) $id) : null;
        if (!$exam) {
            Response::abort(404, 'Không tìm thấy đề thi.');
        }
        if (!$this->canManage((int) $exam['subject_id'])) {
            Response::abort(403, 'Bạn không được phân công phụ trách môn học của đề thi này.');
        }
        return $exam;
    }

    /** Như findAuthorized nhưng chỉ chấp nhận bản nháp */
    private function findDraft(string $id): array
    {
        $exam = $this->findAuthorized($id);
        if ($exam['status'] !== 'draft') {
            Session::flash('error', 'Chỉ sửa được câu hỏi khi đề thi đang là bản nháp. Hãy chuyển về bản nháp trước.');
            $this->redirect('/exams/' . $exam['id']);
        }
        return $exam;
    }

    private function filters(): array
    {
        $state = (string) $this->request->input('state', '');
        return [
            'q'          => str_cut(trim((string) $this->request->input('q', '')), 100),
            'subject_id' => max(0, (int) $this->request->input('subject_id', 0)),
            'state'      => in_array($state, self::STATES, true) ? $state : '',
        ];
    }

    /** Quay lại danh sách, giữ bộ lọc (trường back_*) */
    private function listUrl(): string
    {
        $state = (string) $this->request->input('back_state', '');
        $params = array_filter([
            'q'          => str_cut(trim((string) $this->request->input('back_q', '')), 100),
            'subject_id' => max(0, (int) $this->request->input('back_subject_id', 0)),
            'state'      => in_array($state, self::STATES, true) ? $state : '',
            'page'       => (int) $this->request->input('back_page', 1) > 1 ? (int) $this->request->input('back_page', 1) : '',
        ], fn($v) => $v !== '' && $v !== 0);

        return '/exams' . ($params ? '?' . http_build_query($params) : '');
    }

    private function backWithErrors(string $path, array $errors, array $data, array $classIds): void
    {
        Session::flash('errors', $errors);
        Session::flash('error', 'Vui lòng kiểm tra lại các thông tin bị đánh dấu lỗi.');
        foreach (['title', 'description', 'duration_minutes', 'start_at', 'end_at'] as $f) {
            Session::flash('old_' . $f, (string) ($data[$f] ?? ''));
        }
        Session::flash('old_shuffle', !empty($data['shuffle']) ? '1' : '0');
        Session::flash('old_class_ids', implode(',', $classIds));
        $this->redirect($path);
    }

    /** Chuyển "2026-10-05T08:30" (ô datetime-local) thành "2026-10-05 08:30:00"; sai định dạng trả về null */
    private function parseDateTime(string $value): ?string
    {
        $dt = \DateTime::createFromFormat(self::DT_FORMAT, $value);
        return ($dt && $dt->format(self::DT_FORMAT) === $value) ? $dt->format('Y-m-d H:i:s') : null;
    }

    /** @return array [dữ liệu kỳ thi, danh sách id lớp, lỗi] */
    private function validate(array $in, array $subject, ?int $examId): array
    {
        $errors = [];
        $title = trim((string) ($in['title'] ?? ''));
        $description = trim((string) ($in['description'] ?? ''));
        $duration = trim((string) ($in['duration_minutes'] ?? ''));
        $startRaw = trim((string) ($in['start_at'] ?? ''));
        $endRaw = trim((string) ($in['end_at'] ?? ''));

        if ($title === '') {
            $errors['title'] = 'Vui lòng nhập tên đề thi.';
        } elseif (str_len($title) > 200) {
            $errors['title'] = 'Tên đề thi tối đa 200 ký tự.';
        }
        if (str_len($description) > 1000) {
            $errors['description'] = 'Mô tả tối đa 1000 ký tự.';
        }

        $durationValue = null;
        if (!ctype_digit($duration) || (int) $duration < Exam::MIN_DURATION || (int) $duration > Exam::MAX_DURATION) {
            $errors['duration_minutes'] = 'Thời gian làm bài phải từ ' . Exam::MIN_DURATION . ' đến ' . Exam::MAX_DURATION . ' phút.';
        } else {
            $durationValue = (int) $duration;
        }

        $start = $this->parseDateTime($startRaw);
        $end = $this->parseDateTime($endRaw);
        if ($start === null) {
            $errors['start_at'] = 'Vui lòng chọn thời điểm mở cổng thi.';
        }
        if ($end === null) {
            $errors['end_at'] = 'Vui lòng chọn thời điểm đóng cổng thi.';
        }
        if ($start !== null && $end !== null) {
            if (strtotime($end) <= strtotime($start)) {
                $errors['end_at'] = 'Thời điểm đóng phải sau thời điểm mở.';
            } elseif ($durationValue !== null && (strtotime($end) - strtotime($start)) < $durationValue * 60) {
                $errors['end_at'] = 'Khoảng từ lúc mở đến lúc đóng phải dài hơn thời gian làm bài (' . $durationValue . ' phút).';
            }
        }

        $classIds = [];
        foreach ((array) ($in['class_ids'] ?? []) as $v) {
            if (ctype_digit((string) $v)) {
                $classIds[(int) $v] = (int) $v;
            }
        }
        $classIds = array_values($classIds);
        if (!$classIds) {
            $errors['class_ids'] = 'Vui lòng chọn ít nhất một lớp được thi.';
        } elseif (count(Exam::validClassIds((int) $subject['id'], $classIds, $this->scope(), $examId)) !== count($classIds)) {
            $errors['class_ids'] = 'Có lớp không học môn này hoặc bạn không được phép chọn.';
        }

        $data = [
            'title'            => $title,
            'description'      => $description === '' ? null : $description,
            'duration_minutes' => $durationValue,
            'start_at'         => $start,
            'end_at'           => $end,
            'shuffle'          => !empty($in['shuffle']) ? 1 : 0,
        ];
        // Khi form lỗi, trả lại đúng chuỗi người dùng đã nhập
        if ($errors) {
            $data['duration_minutes'] = $duration;
            $data['start_at'] = $startRaw;
            $data['end_at'] = $endRaw;
        }
        return [$data, $classIds, $errors];
    }
}