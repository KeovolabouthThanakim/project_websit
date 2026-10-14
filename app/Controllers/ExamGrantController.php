<?php
namespace App\Controllers;

use App\Models\Audit;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\ExamGrant;
use App\Models\Notification;
use App\Models\Subject;

/**
 * Cấp quyền riêng cho từng sinh viên trong một kỳ thi (giáo viên có môn đó hoặc quản trị viên):
 *  - extend: cộng thêm phút cho bài ĐANG LÀM (rớt mạng, sự cố máy...)
 *  - retake: hủy bài đã làm (đã nộp hoặc đang làm dở) để sinh viên thi lại; điểm cũ được lưu vào lịch sử
 *  - allow:  cho sinh viên CHƯA CÓ BÀI vào thi muộn / thi bù dù cổng thi đã đóng
 *  - revoke: thu hồi quyền đã cấp mà sinh viên chưa dùng
 * Kỳ thi phải đang ở trạng thái "đã công bố" (đề đã đóng thì phải mở lại trước), vì sinh viên chỉ thấy đề đã công bố.
 */
class ExamGrantController extends Controller
{
    private function canManage(int $subjectId): bool
    {
        return $this->user()['role'] === 'admin' || Subject::isAssigned($subjectId, (int) $this->user()['id']);
    }

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
            Session::flash('error', 'Đề thi còn là bản nháp nên chưa có sinh viên nào làm bài.');
            $this->redirect('/exams/' . (int) $exam['id']);
        }
        return $exam;
    }

    private function findStudent(array $exam, string $uid): array
    {
        $student = ctype_digit($uid) ? ExamGrant::studentInExam((int) $exam['id'], (int) $uid) : null;
        if (!$student) {
            Response::abort(404, 'Sinh viên này không thuộc các lớp được thi của kỳ thi.');
        }
        return $student;
    }

    // ------------------------------------------------------------------
    public function show(string $id, string $uid): void
    {
        $exam = $this->findExam($id);
        $examId = (int) $exam['id'];
        $student = $this->findStudent($exam, $uid);
        $userId = (int) $student['id'];
        Attempt::finalizeExpired($examId, $userId); // bài hết giờ mà chưa nộp thì nộp trước để trạng thái đúng

        $this->view('exams/grant', [
            'title'    => 'Cấp quyền thi - ' . $student['full_name'],
            'exam'     => $exam,
            'state'    => Exam::state($exam),
            'student'  => $student,
            'attempt'  => Attempt::forExamUser($examId, $userId),
            'grant'    => ExamGrant::activeFor($examId, $userId),
            'history'  => ExamGrant::history($examId, $userId),
            'ready'    => ExamGrant::ready(),
            'canGrant' => $exam['status'] === 'published',
            'maxExtra' => ExamGrant::MAX_EXTRA_MINUTES,
            'maxHours' => ExamGrant::MAX_VALID_HOURS,
            'defHours' => ExamGrant::DEFAULT_VALID_HOURS,
        ]);
    }

    public function update(string $id, string $uid): void
    {
        $exam = $this->findExam($id);
        $examId = (int) $exam['id'];
        $student = $this->findStudent($exam, $uid);
        $userId = (int) $student['id'];
        $back = '/exams/' . $examId . '/grants/' . $userId;

        if (!ExamGrant::ready()) {
            Session::flash('error', 'Chưa nhập database/010_exam_grants.sql nên chưa dùng được chức năng này.');
            $this->redirect($back);
        }
        if ($exam['status'] !== 'published') {
            Session::flash('error', 'Đề thi đã đóng. Hãy mở lại (công bố) đề thi trước khi cấp quyền cho sinh viên.');
            $this->redirect($back);
        }
        if (!$student['is_active']) {
            Session::flash('error', 'Tài khoản sinh viên này đang bị khóa.');
            $this->redirect($back);
        }

        Attempt::finalizeExpired($examId, $userId);
        $attempt = Attempt::forExamUser($examId, $userId);
        $action = (string) $this->request->input('action', '');
        $by = (int) $this->user()['id'];
        $note = str_cut(trim((string) $this->request->input('note', '')), 255);
        $note = $note === '' ? null : $note;
        $extra = $this->intIn('extra_minutes', 0, ExamGrant::MAX_EXTRA_MINUTES);
        $hours = $this->intIn('valid_hours', 1, ExamGrant::MAX_VALID_HOURS, ExamGrant::DEFAULT_VALID_HOURS);
        $who = $student['full_name'] . ' (' . $student['username'] . ')';

        switch ($action) {
            case 'extend':
                $minutes = $this->intIn('minutes', 1, ExamGrant::MAX_EXTRA_MINUTES, 0);
                if (!$attempt || $attempt['status'] !== 'in_progress') {
                    Session::flash('error', 'Sinh viên này không còn bài đang làm để cộng thêm thời gian. Hãy dùng "Cho thi lại" nếu cần.');
                } elseif ($minutes < 1) {
                    Session::flash('error', 'Hãy nhập số phút cộng thêm (1 - ' . ExamGrant::MAX_EXTRA_MINUTES . ').');
                } else {
                    $new = ExamGrant::extend((int) $attempt['id'], $minutes);
                    Session::flash('success', $new
                        ? 'Đã cộng thêm ' . $minutes . ' phút cho ' . $who . '. Hạn nộp mới: ' . fmt_date($new) . '.'
                        : 'Bài làm đã được nộp trước khi cộng thời gian.');
                    if ($new) {
                        Audit::log('grant.extend', $who . ': +' . $minutes . ' phút, kỳ thi "' . $exam['title'] . '" (#' . $examId . ')');
                        $this->tell($userId, $exam, 'Được cộng thêm ' . $minutes . ' phút: ' . $exam['title'], 'Giảng viên đã cộng thêm ' . $minutes . ' phút cho bài thi của bạn. Hạn nộp mới: ' . fmt_date($new) . '.');
                    }
                }
                break;

            case 'retake':
                if (!$attempt) {
                    Session::flash('error', 'Sinh viên này chưa có bài làm nào để hủy. Hãy dùng "Cho vào thi".');
                } elseif (!ExamGrant::retake($examId, $userId, $extra, $hours, $note, $by)) {
                    Session::flash('error', 'Không tìm thấy bài làm để hủy.');
                } else {
                    Session::flash('success', 'Đã hủy bài cũ của ' . $who . ' (điểm cũ được lưu vào lịch sử). Sinh viên có thể thi lại trong ' . $hours . ' giờ tới.');
                    Audit::log('grant.retake', $who . ': kỳ thi "' . $exam['title'] . '" (#' . $examId . ')' . ($note ? ', lý do: ' . $note : ''));
                    $this->tell($userId, $exam, 'Được thi lại: ' . $exam['title'], 'Bài cũ đã được hủy. Bạn có thể vào thi lại trong ' . $hours . ' giờ tới' . ($extra > 0 ? ', được cộng thêm ' . $extra . ' phút' : '') . '.');
                }
                break;

            case 'allow':
                if ($attempt) {
                    Session::flash('error', 'Sinh viên này đã có bài làm. Hãy dùng "Cho thi lại" hoặc "Cộng thêm thời gian".');
                } else {
                    ExamGrant::give($examId, $userId, $extra, $hours, $note, $by);
                    Session::flash('success', 'Đã cho ' . $who . ' vào thi trong ' . $hours . ' giờ tới' . ($extra > 0 ? ', cộng thêm ' . $extra . ' phút' : '') . '.');
                    Audit::log('grant.allow', $who . ': kỳ thi "' . $exam['title'] . '" (#' . $examId . ')' . ($note ? ', lý do: ' . $note : ''));
                    $this->tell($userId, $exam, 'Được vào thi: ' . $exam['title'], 'Bạn được vào thi trong ' . $hours . ' giờ tới' . ($extra > 0 ? ', được cộng thêm ' . $extra . ' phút' : '') . '.');
                }
                break;

            case 'revoke':
                if (ExamGrant::revoke($examId, $userId)) {
                    Audit::log('grant.revoke', $who . ': kỳ thi "' . $exam['title'] . '" (#' . $examId . ')');
                    Session::flash('success', 'Đã thu hồi quyền vào thi của ' . $who . '.');
                } else {
                    Session::flash('error', 'Không có quyền nào đang chờ để thu hồi.');
                }
                break;

            default:
                Session::flash('error', 'Thao tác không hợp lệ.');
        }
        $this->redirect($back);
    }

    /** Thông báo trong hệ thống cho sinh viên được cấp quyền */
    private function tell(int $userId, array $exam, string $title, string $body): void
    {
        Notification::push([$userId], $title, $body, '/my-exams/' . (int) $exam['id']);
    }

    /** Số nguyên từ form, kẹp trong [min, max]; thiếu / sai thì dùng $default */
    private function intIn(string $key, int $min, int $max, int $default = 0): int
    {
        $v = (string) $this->request->input($key, '');
        return ctype_digit($v) ? max($min, min($max, (int) $v)) : $default;
    }
}
