<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Attempt;
use App\Models\ClassSubject;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Result;
use App\Models\StudentStats;
use App\Models\Subject;
use App\Models\User;

class DashboardController extends Controller
{
    /** Hiển thị trang chủ phù hợp với vai trò người đăng nhập */
    public function index(): void
    {
        $user = $this->user();
        $data = ['title' => 'Trang chủ', 'user' => $user];

        if ($user['role'] === 'admin') {
            $data['counts'] = User::countByRole();
            $data['total'] = array_sum($data['counts']);
            $data['inactive'] = User::countInactive();
            $data['recent'] = User::recent(5);
            $data['classCount'] = Classroom::countAll();
            $data['subjectCount'] = Subject::countAll();
            $data['questionCount'] = Question::countAll();
            // Tổng quan thi cử toàn hệ thống: bài hết giờ mà chưa nộp thì tự nộp + chấm trước để số liệu đúng
            Attempt::finalizeExpired();
            $data['exams'] = Exam::dashboard(null, 14, 100);
            $data['workingNow'] = Attempt::inProgressNow(50);
            $data['workingCount'] = Attempt::countInProgressNow();
        } elseif ($user['role'] === 'teacher') {
            $data['classes'] = Classroom::search('', 1, 50, (int) $user['id'])['items'];
            $data['subjects'] = Subject::search('', '', 1, 50, (int) $user['id'])['items'];
            // Bài hết giờ mà chưa nộp thì tự nộp + chấm trước, để số liệu "đang làm / đã nộp" đúng
            Attempt::finalizeExpired();
            // live (đang thi), upcoming (sắp diễn ra), drafts (bản nháp), recent (vừa kết thúc, xem kết quả)
            $data['exams'] = Exam::dashboard((int) $user['id']);
        } else {
            $data['class'] = Classroom::ofStudent((int) $user['id']);
            $data['subjects'] = $data['class'] ? ClassSubject::forClass((int) $data['class']['id']) : [];
            // Bài hết giờ mà chưa nộp thì tự nộp + chấm trước khi thống kê
            Attempt::finalizeExpired(null, (int) $user['id']);
            $all = $data['class'] ? Attempt::listForStudent((int) $user['id'], (int) $data['class']['id']) : [];
            $doneIds = [];
            foreach ($all as $x) {
                if ($x['attempt_status'] === 'submitted') {
                    $doneIds[] = (int) $x['id'];
                }
            }
            $classAvg = $data['class'] ? Result::classAverages($doneIds, (int) $data['class']['id']) : [];
            // hero, attention (kỳ thi cần chú ý), stats, recent, chart, compare, bySubject, serverNow
            $data += StudentStats::build($all, $classAvg, $data['subjects']);
        }

        $this->view('dashboard/' . $user['role'], $data);
    }
}