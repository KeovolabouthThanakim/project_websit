<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Analytics;
use App\Models\Result;
use App\Models\Subject;

/**
 * Thống kê một môn học qua nhiều kỳ thi: xu hướng điểm trung bình và các câu hay làm sai.
 * Quyền: quản trị viên mọi môn; giáo viên chỉ môn mình được phân công.
 */
class AnalyticsController extends Controller
{
    public function subject(string $id): void
    {
        $subject = ctype_digit($id) ? Subject::find((int) $id) : null;
        if (!$subject) {
            Response::abort(404, 'Không tìm thấy môn học.');
        }
        $subjectId = (int) $subject['id'];
        if ($this->user()['role'] !== 'admin' && !Subject::isAssigned($subjectId, (int) $this->user()['id'])) {
            Response::abort(403, 'Bạn không được phân công phụ trách môn học này.');
        }

        $classes = Analytics::classChoices($subjectId);
        $classId = (int) $this->request->input('class_id', 0);
        if (!in_array($classId, array_map(fn($c) => (int) $c['id'], $classes), true)) {
            $classId = 0;
        }

        $exams = Analytics::exams($subjectId, $classId);
        $withData = array_values(array_filter($exams, fn($e) => $e['submitted'] > 0));
        $all = [];
        $sum = 0.0;
        foreach ($withData as $e) {
            $sum += $e['avg'] * $e['submitted'];
            $all[] = $e['submitted'];
        }

        $this->view('analytics/subject', [
            'title'     => 'Thống kê môn ' . $subject['code'],
            'subject'   => $subject,
            'classes'   => $classes,
            'classId'   => $classId,
            'exams'     => $exams,
            'withData'  => $withData,
            'overall'   => ['exams' => count($withData), 'attempts' => array_sum($all), 'avg' => array_sum($all) > 0 ? round($sum / array_sum($all), 2) : null],
            'questions' => Analytics::hardQuestions($subjectId, $classId),
            'pass'      => Result::passScore(),
            'minSample' => Analytics::MIN_SAMPLE,
        ]);
    }
}
