<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\Attempt;
use App\Models\AttemptEvent;
use App\Models\Exam;
use App\Models\ExamGrant;
use App\Models\Result;
use App\Models\Subject;

/**
 * Kết quả & thống kê kỳ thi cho giáo viên / quản trị viên:
 * bảng điểm cả lớp, phân bố điểm, phân tích từng câu, xuất CSV, xem bài làm của từng sinh viên.
 * Quyền giống ExamController: quản trị viên mọi kỳ thi; giáo viên chỉ kỳ thi của môn mình được phân công.
 */
class ExamResultController extends Controller
{
    private const SORTS = ['class', 'name', 'score_desc', 'score_asc'];

    private function canManage(int $subjectId): bool
    {
        return $this->user()['role'] === 'admin' || Subject::isAssigned($subjectId, (int) $this->user()['id']);
    }

    // ------------------------------------------------------------------
    // Bảng điểm + thống kê
    // ------------------------------------------------------------------
    public function index(string $id): void
    {
        $exam = $this->findExam($id);
        $examId = (int) $exam['id'];
        Attempt::finalizeExpired($examId); // bài hết giờ mà chưa nộp thì nộp + chấm trước khi thống kê

        $classes = Exam::classesOf($examId);
        $classId = $this->classFilter($classes);
        $sort = (string) $this->request->input('sort', 'class');
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'class';

        $rows = Result::roster($examId, $classId);
        $summary = Result::summary($rows);

        $this->view('exams/results', [
            'title'     => 'Kết quả - ' . $exam['title'],
            'exam'      => $exam,
            'state'     => Exam::state($exam),
            'classes'   => $classes,
            'classId'   => $classId,
            'sort'      => $sort,
            'rows'      => self::sortRows($rows, $sort),
            'summary'   => $summary,
            'byClass'   => $classId === 0 ? Result::byClass($rows) : [],
            'questions' => Result::questionStats($examId, $classId),
            'passScore' => Result::passScore(),
            'hardRate'  => Result::HARD_RATE,
            'events'    => AttemptEvent::summaryForExam($examId),
            'grants'    => ExamGrant::activeForExam($examId),   // [user_id => quyền đang chờ]
            'retakes'   => ExamGrant::historyCounts($examId),   // [user_id => số lần đã cho thi lại]
            'canGrant'  => ExamGrant::ready() && $exam['status'] === 'published',
        ]);
    }

    // ------------------------------------------------------------------
    // Theo dõi phòng thi trực tiếp: ai đang làm bài, ai chưa vào thi, ai đã nộp (trang tự làm mới)
    // ------------------------------------------------------------------
    public function monitor(string $id): void
    {
        $exam = $this->findExam($id);
        $this->view('exams/monitor', [
            'title'   => 'Theo dõi phòng thi - ' . $exam['title'],
            'exam'    => $exam,
            'state'   => Exam::state($exam),
            'classes' => Exam::classesOf((int) $exam['id']),
            'classId' => $this->classFilter(Exam::classesOf((int) $exam['id'])),
        ]);
    }

    /** Dữ liệu JSON cho trang theo dõi (được gọi định kỳ) */
    public function monitorData(string $id): void
    {
        $exam = $this->findExam($id);
        $examId = (int) $exam['id'];
        Attempt::finalizeExpired($examId); // bài hết giờ thì tự nộp để số liệu đúng

        $classId = $this->classFilter(Exam::classesOf($examId));
        $order = ['working' => 0, 'absent' => 1, 'done' => 2];
        $rows = Result::roster($examId, $classId, true);
        usort($rows, fn($a, $b) => [$order[Result::rowState($a)], (string) $a['class_code'], $a['username']]
            <=> [$order[Result::rowState($b)], (string) $b['class_code'], $b['username']]);

        $counts = ['total' => count($rows), 'absent' => 0, 'working' => 0, 'done' => 0, 'flagged' => 0];
        $events = AttemptEvent::summaryForExam($examId);
        $out = [];
        $now = time();
        foreach ($rows as $r) {
            $st = Result::rowState($r);
            $counts[$st]++;
            $ev = $events[(int) $r['attempt_id']] ?? ['leaves' => 0, 'others' => 0, 'away' => 0];
            $level = AttemptEvent::level($ev['leaves'], $ev['others']);
            if ($level !== 'none') {
                $counts['flagged']++;
            }
            $out[] = [
                'username'  => sv_code($r),
                'full_name' => $r['full_name'],
                'class'     => $r['class_code'] ?? '',
                'state'     => $st,
                'answered'  => $st === 'absent' ? 0 : (int) $r['answered'],
                'questions' => $st === 'absent' ? 0 : (int) $r['total_questions'],
                'left'      => $st === 'working' ? max(0, strtotime($r['deadline_at']) - $now) : null,
                'started'   => $r['started_at'] ? date('H:i', strtotime($r['started_at'])) : '',
                'submitted' => $st === 'done' ? date('H:i', strtotime($r['submitted_at'])) : '',
                'score'     => $st === 'done' ? fmt_score($r['score']) : '',
                'auto'      => $st === 'done' && $r['auto_submitted'],
                'leaves'    => $ev['leaves'],
                'others'    => $ev['others'],
                'away'      => $ev['away'],
                'level'     => $level,
            ];
        }
        Response::json([
            'ok'     => true,
            'time'   => date('H:i:s', $now),
            'state'  => Exam::state($exam),
            'counts' => $counts,
            'rows'   => $out,
        ]);
    }

    // ------------------------------------------------------------------
    // Xuất CSV (mở được bằng Excel / Google Sheets)
    // ------------------------------------------------------------------
    public function export(string $id): void
    {
        $exam = $this->findExam($id);
        $examId = (int) $exam['id'];
        Attempt::finalizeExpired($examId);

        $classId = $this->classFilter(Exam::classesOf($examId));
        $rows = self::sortRows(Result::roster($examId, $classId), 'class');
        $events = AttemptEvent::summaryForExam($examId);

        $labels = ['absent' => 'Chưa vào thi', 'working' => 'Đang làm bài', 'done' => 'Đã nộp'];
        $filename = 'ket-qua-' . preg_replace('/[^a-z0-9]+/i', '-', $exam['subject_code']) . '-de-' . $examId . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM để Excel đọc đúng tiếng Việt
        fputcsv($out, ['STT', 'Mã sinh viên', 'Họ và tên', 'Lớp', 'Trạng thái', 'Điểm (thang 10)', 'Số câu đúng hoàn toàn', 'Tổng số câu', 'Bắt đầu', 'Nộp lúc', 'Thời gian làm (phút:giây)', 'Tự nộp do hết giờ', 'Số lần rời trang thi', 'Tổng thời gian rời trang (phút:giây)', 'Sao chép / chuột phải']);
        foreach ($rows as $i => $r) {
            $state = Result::rowState($r);
            $spent = Result::secondsSpent($r);
            fputcsv($out, array_map([self::class, 'csvCell'], [
                $i + 1,
                sv_code($r),
                $r['full_name'],
                $r['class_code'] ?? '',
                $labels[$state],
                $state === 'done' ? number_format((float) $r['score'], 2, '.', '') : '',
                $state === 'done' ? (int) $r['full_correct'] : '',
                $state === 'done' ? (int) $r['total_questions'] : '',
                $r['started_at'] ? fmt_date($r['started_at']) : '',
                $state === 'done' ? fmt_date($r['submitted_at']) : '',
                $spent === null ? '' : fmt_duration($spent),
                $state === 'done' ? ($r['auto_submitted'] ? 'Có' : 'Không') : '',
                $r['attempt_id'] === null ? '' : ($events[(int) $r['attempt_id']]['leaves'] ?? 0),
                $r['attempt_id'] === null ? '' : fmt_duration($events[(int) $r['attempt_id']]['away'] ?? 0),
                $r['attempt_id'] === null ? '' : ($events[(int) $r['attempt_id']]['others'] ?? 0),
            ]));
        }
        fclose($out);
        exit;
    }

    // ------------------------------------------------------------------
    // Xem bài làm của một sinh viên (kèm đáp án đúng + giải thích)
    // ------------------------------------------------------------------
    public function attempt(string $id, string $aid): void
    {
        $exam = $this->findExam($id);
        $examId = (int) $exam['id'];
        Attempt::finalizeExpired($examId);

        $attempt = ctype_digit($aid) ? Result::attemptOfExam((int) $aid, $examId) : null;
        if (!$attempt) {
            Response::abort(404, 'Không tìm thấy bài làm của kỳ thi này.');
        }
        if ($attempt['status'] !== 'submitted') {
            Session::flash('error', 'Sinh viên này đang làm bài, chưa có kết quả để xem.');
            $this->redirect('/exams/' . $examId . '/results');
        }

        $this->view('exams/result_attempt', [
            'title'   => $attempt['full_name'] . ' - ' . $exam['title'],
            'exam'    => $exam,
            'attempt' => $attempt,
            'paper'   => Attempt::paper((int) $attempt['id'], true),
            'events'  => AttemptEvent::forAttempt((int) $attempt['id'], $attempt['submitted_at']),
            'tracked' => AttemptEvent::ready(),
        ]);
    }

    // ------------------------------------------------------------------
    // Bảng điểm để in / lưu PDF (trình duyệt: In -> Lưu dạng PDF). Theo lớp đang lọc.
    // ------------------------------------------------------------------
    public function printSheet(string $id): void
    {
        $exam = $this->findExam($id);
        $examId = (int) $exam['id'];
        Attempt::finalizeExpired($examId);

        $classes = Exam::classesOf($examId);
        $classId = $this->classFilter($classes);
        $rows = self::sortRows(Result::roster($examId, $classId), 'class');

        $this->view('exams/print_results', [
            'title'   => 'Bảng điểm - ' . $exam['title'],
            'exam'    => $exam,
            'classes' => $classId ? array_values(array_filter($classes, fn($c) => (int) $c['id'] === $classId)) : $classes,
            'rows'    => $rows,
            'summary' => Result::summary($rows),
            'pass'    => Result::passScore(),
            'user'    => $this->user(),
        ], 'layouts/print');
    }

    // ------------------------------------------------------------------
    // Hàm phụ
    // ------------------------------------------------------------------
    private function findExam(string $id): array
    {
        $exam = ctype_digit($id) ? Exam::detail((int) $id) : null;
        if (!$exam) {
            Response::abort(404, 'Không tìm thấy đề thi.');
        }
        if (!$this->canManage((int) $exam['subject_id'])) {
            Response::abort(403, 'Bạn không được phân công phụ trách môn học của đề thi này.');
        }
        if ($exam['status'] === 'draft') {
            Session::flash('error', 'Đề thi còn là bản nháp nên chưa có kết quả.');
            $this->redirect('/exams/' . (int) $exam['id']);
        }
        return $exam;
    }

    /** Lớp đang lọc (0 = tất cả); chỉ nhận lớp thuộc kỳ thi */
    private function classFilter(array $classes): int
    {
        $cid = (int) $this->request->input('class_id', 0);
        foreach ($classes as $c) {
            if ((int) $c['id'] === $cid) {
                return $cid;
            }
        }
        return 0;
    }

    /** Sắp xếp bảng điểm. Người chưa nộp luôn xếp cuối khi sắp theo điểm */
    private static function sortRows(array $rows, string $sort): array
    {
        $key = fn(array $r) => Result::rowState($r) === 'done' ? (float) $r['score'] : null;
        usort($rows, function (array $a, array $b) use ($sort, $key) {
            if ($sort === 'name') {
                return strcmp(self::nameKey($a), self::nameKey($b));
            }
            if ($sort === 'score_desc' || $sort === 'score_asc') {
                [$x, $y] = [$key($a), $key($b)];
                if ($x === null || $y === null) {
                    return ($x === null) <=> ($y === null);
                }
                return $sort === 'score_desc' ? $y <=> $x : $x <=> $y;
            }
            return strcmp((string) $a['class_code'] . "\0" . $a['username'], (string) $b['class_code'] . "\0" . $b['username']);
        });
        return $rows;
    }

    /** Khóa sắp xếp theo tên (người Việt xếp theo tên gọi - từ cuối của họ tên) */
    private static function nameKey(array $r): string
    {
        $parts = preg_split('/\s+/u', trim($r['full_name']));
        $last = (string) end($parts);
        $lower = function_exists('mb_strtolower') ? mb_strtolower($last, 'UTF-8') : strtolower($last);
        return $lower . "\0" . $r['full_name'];
    }

    /** Chặn "CSV injection": ô bắt đầu bằng = + - @ sẽ bị Excel hiểu là công thức */
    public static function csvCell($value)
    {
        return (is_string($value) && $value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) ? "'" . $value : $value;
    }
}
