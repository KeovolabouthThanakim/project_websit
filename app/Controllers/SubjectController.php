<?php
namespace App\Controllers;

use App\Models\Audit;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\ClassSubject;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Subject;

/**
 * Quản lý môn học.
 *  - Quản trị viên: thêm / sửa / ngừng sử dụng / xóa môn, phân công giáo viên phụ trách
 *  - Giáo viên: chỉ xem các môn mình được phân công (quyền kiểm tra trong show())
 */
class SubjectController extends Controller
{
    private const PER_PAGE = 10;
    private const STATUSES = ['active', 'inactive'];

    private function isAdmin(): bool
    {
        return $this->user()['role'] === 'admin';
    }

    // ------------------------------------------------------------------
    // Danh sách / chi tiết
    // ------------------------------------------------------------------
    public function index(): void
    {
        $q = str_cut(trim((string) $this->request->input('q', '')), 100);
        $status = (string) $this->request->input('status', '');
        $status = in_array($status, self::STATUSES, true) && $this->isAdmin() ? $status : '';
        $page = (int) $this->request->input('page', 1);
        $teacherId = $this->isAdmin() ? null : (int) $this->user()['id'];

        $this->view('subjects/index', [
            'title'   => $this->isAdmin() ? 'Quản lý môn học' : 'Môn học của tôi',
            'result'  => Subject::search($q, $status, $page, self::PER_PAGE, $teacherId),
            'q'       => $q,
            'status'  => $status,
            'perPage' => self::PER_PAGE,
            'isAdmin' => $this->isAdmin(),
        ]);
    }

    public function show(string $id): void
    {
        $subject = $this->findOrFail($id);

        if (!$this->isAdmin() && !Subject::isAssigned((int) $subject['id'], (int) $this->user()['id'])) {
            Response::abort(403, 'Bạn không được phân công phụ trách môn học này.');
        }

        $this->view('subjects/show', [
            'title'    => 'Môn ' . $subject['code'],
            'subject'  => $subject,
            'teachers' => Subject::teachersOf((int) $subject['id']),
            'classes'  => ClassSubject::forSubject((int) $subject['id']),
            'classChoices'   => $this->isAdmin() ? ClassSubject::classesNotIn((int) $subject['id']) : [],
            'teacherChoices' => $this->isAdmin() ? Classroom::teachers() : [],
            'isAdmin'  => $this->isAdmin(),
            'questionCount' => Question::countBySubject((int) $subject['id']),
        ]);
    }

    /** Gán môn học này cho một lớp (chỉ quản trị viên - đã chặn ở routes) */
    public function addClass(string $id): void
    {
        $subject = $this->findOrFail($id);
        $subjectId = (int) $subject['id'];
        $classId = (string) $this->request->input('class_id', '');
        $teacher = trim((string) $this->request->input('teacher_id', ''));

        $class = ctype_digit($classId) ? Classroom::find((int) $classId) : null;
        if (!$class) {
            Session::flash('error', 'Vui lòng chọn lớp học.');
            $this->redirect('/subjects/' . $subjectId);
        }
        if (!$subject['is_active']) {
            Session::flash('error', 'Môn học đang ngừng sử dụng, hãy kích hoạt lại trước khi gán cho lớp.');
            $this->redirect('/subjects/' . $subjectId);
        }
        if (ClassSubject::exists((int) $class['id'], $subjectId)) {
            Session::flash('error', 'Lớp ' . $class['code'] . ' đã có môn này.');
            $this->redirect('/subjects/' . $subjectId);
        }
        $teacherId = null;
        if ($teacher !== '') {
            if (!ctype_digit($teacher) || !Classroom::isActiveTeacher((int) $teacher)) {
                Session::flash('error', 'Giáo viên giảng dạy không hợp lệ.');
                $this->redirect('/subjects/' . $subjectId);
            }
            $teacherId = (int) $teacher;
        }

        ClassSubject::assign((int) $class['id'], $subjectId, $teacherId);
        Session::flash('success', 'Đã gán môn ' . $subject['code'] . ' cho lớp ' . $class['code'] . '.');
        $this->redirect('/subjects/' . $subjectId);
    }

    /** Bỏ môn học này khỏi một lớp (chỉ quản trị viên) */
    public function removeClass(string $id, string $cid): void
    {
        $subject = $this->findOrFail($id);
        if (ctype_digit($cid) && \App\Models\Exam::existsForClassSubject((int) $cid, (int) $subject['id'])) {
            Session::flash('error', 'Không thể bỏ môn khỏi lớp này vì lớp đang có đề thi của môn này.');
            $this->redirect('/subjects/' . $subject['id']);
        }
        if (ctype_digit($cid) && ClassSubject::unassign((int) $cid, (int) $subject['id'])) {
            Session::flash('success', 'Đã bỏ môn ' . $subject['code'] . ' khỏi lớp.');
        } else {
            Session::flash('error', 'Lớp này không học môn này.');
        }
        $this->redirect('/subjects/' . $subject['id']);
    }

    // ------------------------------------------------------------------
    // Thêm / sửa (chỉ quản trị viên - đã chặn ở routes)
    // ------------------------------------------------------------------
    public function create(): void
    {
        $this->view('subjects/form', [
            'title' => 'Thêm môn học', 'mode' => 'create', 'subject' => null,
            'teachers' => Subject::teacherChoices(null), 'assigned' => [],
        ]);
    }

    public function store(): void
    {
        [$data, $teacherIds, $errors] = $this->validate($this->request->all(), null);
        if ($errors) {
            $this->backWithErrors('/subjects/create', $errors, $data, $teacherIds);
        }

        $id = Subject::saveWithTeachers(null, $data, $teacherIds);
        Session::flash('success', 'Đã thêm môn học "' . $data['code'] . '".');
        $this->redirect('/subjects/' . $id);
    }

    public function edit(string $id): void
    {
        $subject = $this->findOrFail($id);
        $this->view('subjects/form', [
            'title' => 'Sửa môn học', 'mode' => 'edit', 'subject' => $subject,
            'teachers' => Subject::teacherChoices((int) $subject['id']),
            'assigned' => array_map(fn($t) => (int) $t['id'], Subject::teachersOf((int) $subject['id'])),
        ]);
    }

    public function update(string $id): void
    {
        $subject = $this->findOrFail($id);
        [$data, $teacherIds, $errors] = $this->validate($this->request->all(), (int) $subject['id']);
        if ($errors) {
            $this->backWithErrors('/subjects/' . $subject['id'] . '/edit', $errors, $data, $teacherIds);
        }

        Subject::saveWithTeachers((int) $subject['id'], $data, $teacherIds);
        Session::flash('success', 'Đã cập nhật môn học "' . $data['code'] . '".');
        $this->redirect('/subjects/' . $subject['id']);
    }

    // ------------------------------------------------------------------
    // Ngừng sử dụng / kích hoạt lại, xóa
    // ------------------------------------------------------------------
    public function toggle(string $id): void
    {
        $subject = $this->findOrFail($id);
        $new = $subject['is_active'] ? 0 : 1;
        Subject::update((int) $subject['id'], ['is_active' => $new]);

        Session::flash('success', ($new ? 'Đã kích hoạt lại' : 'Đã ngừng sử dụng') . ' môn học "' . $subject['code'] . '".');
        $this->redirect($this->listUrl());
    }

    public function destroy(string $id): void
    {
        $subject = $this->findOrFail($id);

        try {
            Subject::delete((int) $subject['id']);
            Audit::log('subject.delete', $subject['code'] . ' (#' . (int) $subject['id'] . ')');
            Session::flash('success', 'Đã xóa môn học "' . $subject['code'] . '".');
        } catch (\PDOException $e) {
            // Vi phạm khóa ngoại: môn đã có câu hỏi / đề thi...
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            Session::flash('error', 'Không thể xóa môn "' . $subject['code'] . '" vì đã có dữ liệu liên quan (câu hỏi, đề thi...). Hãy chọn "Ngừng sử dụng" thay vì xóa.');
        }
        $this->redirect($this->listUrl());
    }

    // ------------------------------------------------------------------
    // Hàm phụ
    // ------------------------------------------------------------------
    private function findOrFail(string $id): array
    {
        $subject = ctype_digit($id) ? Subject::find((int) $id) : null;
        if (!$subject) {
            Response::abort(404, 'Không tìm thấy môn học.');
        }
        return $subject;
    }

    /** Quay lại danh sách, giữ bộ lọc/trang đang xem (trường back_q, back_status, back_page) */
    private function listUrl(): string
    {
        $status = (string) $this->request->input('back_status', '');
        $page = (int) $this->request->input('back_page', 1);
        $params = array_filter([
            'q'      => str_cut(trim((string) $this->request->input('back_q', '')), 100),
            'status' => in_array($status, self::STATUSES, true) ? $status : '',
            'page'   => $page > 1 ? $page : '',
        ], fn($v) => $v !== '');

        return '/subjects' . ($params ? '?' . http_build_query($params) : '');
    }

    private function backWithErrors(string $path, array $errors, array $data, array $teacherIds): void
    {
        Session::flash('errors', $errors);
        Session::flash('error', 'Vui lòng kiểm tra lại các thông tin bị đánh dấu lỗi.');
        foreach (['code', 'name', 'credits', 'description'] as $f) {
            Session::flash('old_' . $f, (string) ($data[$f] ?? ''));
        }
        Session::flash('old_is_active', $data['is_active'] ? '1' : '0');
        Session::flash('old_teacher_ids', implode(',', $teacherIds));
        $this->redirect($path);
    }

    /** @return array [dữ liệu môn học, danh sách id giáo viên, lỗi] */
    private function validate(array $in, ?int $id): array
    {
        $errors = [];
        $code = trim((string) ($in['code'] ?? ''));
        $name = trim((string) ($in['name'] ?? ''));
        $credits = trim((string) ($in['credits'] ?? ''));
        $description = trim((string) ($in['description'] ?? ''));

        if ($code === '') {
            $errors['code'] = 'Vui lòng nhập mã môn học.';
        } elseif (str_len($code) < 2 || str_len($code) > 30) {
            $errors['code'] = 'Mã môn học phải từ 2 đến 30 ký tự.';
        } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $code)) {
            $errors['code'] = 'Chỉ dùng chữ không dấu, số và các ký tự . _ -';
        } elseif (Subject::codeTaken($code, $id)) {
            $errors['code'] = 'Mã môn học này đã tồn tại.';
        }

        if ($name === '') {
            $errors['name'] = 'Vui lòng nhập tên môn học.';
        } elseif (str_len($name) > 150) {
            $errors['name'] = 'Tên môn học tối đa 150 ký tự.';
        }

        $creditsValue = null;
        if ($credits !== '') {
            if (!ctype_digit($credits) || (int) $credits < 1 || (int) $credits > 20) {
                $errors['credits'] = 'Số tín chỉ phải là số nguyên từ 1 đến 20.';
            } else {
                $creditsValue = (int) $credits;
            }
        }

        if (str_len($description) > 1000) {
            $errors['description'] = 'Mô tả tối đa 1000 ký tự.';
        }

        // Giáo viên phụ trách: chỉ nhận id của tài khoản có vai trò giáo viên
        $teacherIds = [];
        foreach ((array) ($in['teacher_ids'] ?? []) as $v) {
            if (ctype_digit((string) $v)) {
                $teacherIds[(int) $v] = (int) $v;
            }
        }
        $teacherIds = array_values($teacherIds);
        if (count(Subject::validTeacherIds($teacherIds)) !== count($teacherIds)) {
            $errors['teacher_ids'] = 'Danh sách giáo viên phụ trách không hợp lệ.';
        }

        $data = [
            'code'        => $code,
            'name'        => $name,
            'credits'     => $creditsValue,
            'description' => $description === '' ? null : $description,
            'is_active'   => !empty($in['is_active']) ? 1 : 0,
        ];
        return [$data, $teacherIds, $errors];
    }
}