<?php
namespace App\Controllers;

use App\Models\Audit;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\ClassSubject;
use App\Models\Classroom;

/**
 * Quản lý lớp học.
 *  - Quản trị viên: toàn quyền (tạo/sửa/xóa lớp, phân công giáo viên chủ nhiệm, thêm/bớt sinh viên mọi lớp)
 *  - Giáo viên: xem sĩ số + danh sách sinh viên của lớp mình chủ nhiệm, lớp mình giảng dạy và lớp học môn mình phụ trách;
 *    chỉ thêm/bớt sinh viên ở lớp mình chủ nhiệm
 */
class ClassController extends Controller
{
    private const PER_PAGE = 10;
    private const MAX_CODES = 500;

    private function isAdmin(): bool
    {
        return $this->user()['role'] === 'admin';
    }

    // ------------------------------------------------------------------
    // Danh sách
    // ------------------------------------------------------------------
    public function index(): void
    {
        $q = str_cut(trim((string) $this->request->input('q', '')), 100);
        $page = (int) $this->request->input('page', 1);
        $teacherId = $this->isAdmin() ? null : (int) $this->user()['id'];

        $this->view('classes/index', [
            'title'   => $this->isAdmin() ? 'Quản lý lớp học' : 'Lớp của tôi',
            'result'  => Classroom::search($q, $page, self::PER_PAGE, $teacherId),
            'totals'  => Classroom::totals($teacherId),
            'q'       => $q,
            'perPage' => self::PER_PAGE,
            'isAdmin' => $this->isAdmin(),
        ]);
    }

    // ------------------------------------------------------------------
    // Chi tiết lớp + quản lý sinh viên
    // ------------------------------------------------------------------
    public function show(string $id): void
    {
        $class = $this->findAndAuthorize($id, false); // giáo viên giảng dạy cũng được xem
        $canManage = $this->canManageClass($class);

        $this->view('classes/show', [
            'title'      => 'Lớp ' . $class['code'],
            'class'      => $class,
            'students'   => Classroom::students((int) $class['id']),
            'subjects'   => ClassSubject::forClass((int) $class['id']),
            'subjectChoices' => $this->isAdmin() ? ClassSubject::subjectsNotIn((int) $class['id']) : [],
            'teacherChoices' => $this->isAdmin() ? Classroom::teachers() : [],
            'unassigned' => $canManage ? Classroom::unassignedStudents() : [],
            'isAdmin'    => $this->isAdmin(),
            'canManage'  => $canManage,
        ]);
    }

    public function addStudents(string $id): void
    {
        $class = $this->findAndAuthorize($id);
        $classId = (int) $class['id'];
        $in = $this->request->all();

        // 1) Gom ứng viên: sinh viên được tick + mã sinh viên dán vào ô văn bản
        $ids = [];
        foreach ((array) ($in['student_ids'] ?? []) as $v) {
            if (ctype_digit((string) $v)) {
                $ids[(int) $v] = true;
            }
        }
        $codes = array_values(array_unique(array_filter(
            preg_split('/[\s,;]+/', trim((string) ($in['codes'] ?? ''))) ?: [],
            fn($c) => $c !== ''
        )));

        if (!$ids && !$codes) {
            Session::flash('error', 'Vui lòng chọn sinh viên hoặc nhập mã sinh viên cần thêm.');
            $this->redirect('/classes/' . $classId);
        }
        if (count($codes) > self::MAX_CODES) {
            Session::flash('error', 'Mỗi lần chỉ nhập tối đa ' . self::MAX_CODES . ' mã sinh viên.');
            $this->redirect('/classes/' . $classId);
        }

        // 2) Tra cứu tài khoản
        $candidates = [];   // user_id => row
        foreach (Classroom::usersByIds(array_keys($ids)) as $u) {
            $candidates[(int) $u['id']] = $u;
        }
        $found = [];
        foreach (Classroom::usersByUsernames($codes) as $u) {
            $candidates[(int) $u['id']] = $u;
            $found[strtolower($u['username'])] = true;
            if (!empty($u['student_code'])) {
                $found[strtolower($u['student_code'])] = true;
            }
        }
        $notFound = array_values(array_filter($codes, fn($c) => !isset($found[strtolower($c)])));

        // 3) Phân loại
        $membership = Classroom::membershipMap(array_keys($candidates));
        $toAdd = [];
        $notStudent = $already = $elsewhere = [];
        foreach ($candidates as $uid => $u) {
            if ($u['role'] !== 'student') {
                $notStudent[] = $u['username'];
            } elseif (isset($membership[$uid])) {
                if ((int) $membership[$uid]['class_id'] === $classId) {
                    $already[] = $u['username'];
                } else {
                    $elsewhere[] = $u['username'] . ' (lớp ' . $membership[$uid]['code'] . ')';
                }
            } else {
                $toAdd[] = $uid;
            }
        }

        // 4) Thêm và báo kết quả
        $added = $toAdd ? Classroom::addStudents($classId, $toAdd, (int) $this->user()['id']) : 0;

        if ($added > 0) {
            Session::flash('success', "Đã thêm $added sinh viên vào lớp {$class['code']}.");
        }
        $problems = [];
        if ($notFound)   { $problems[] = 'không tìm thấy tài khoản: ' . $this->names($notFound); }
        if ($notStudent) { $problems[] = 'không phải sinh viên: ' . $this->names($notStudent); }
        if ($elsewhere)  { $problems[] = 'đã thuộc lớp khác (cần xóa khỏi lớp cũ trước): ' . $this->names($elsewhere); }
        if ($already)    { $problems[] = 'đã có trong lớp này: ' . $this->names($already); }
        if ($problems) {
            Session::flash('error', ($added > 0 ? 'Một số tài khoản chưa được thêm - ' : 'Không thêm được - ') . implode('; ', $problems) . '.');
        }
        $this->redirect('/classes/' . $classId);
    }

    /** Gán một môn học cho lớp (chỉ quản trị viên - đã chặn ở routes) */
    public function addSubject(string $id): void
    {
        $class = $this->findAndAuthorize($id);
        $classId = (int) $class['id'];
        $subjectId = (string) $this->request->input('subject_id', '');
        $teacher = trim((string) $this->request->input('teacher_id', ''));

        $subject = ctype_digit($subjectId) ? \App\Models\Subject::find((int) $subjectId) : null;
        if (!$subject || !$subject['is_active']) {
            Session::flash('error', 'Vui lòng chọn một môn học đang sử dụng.');
            $this->redirect('/classes/' . $classId);
        }
        if (ClassSubject::exists($classId, (int) $subject['id'])) {
            Session::flash('error', 'Lớp ' . $class['code'] . ' đã có môn ' . $subject['code'] . '.');
            $this->redirect('/classes/' . $classId);
        }
        $teacherId = null;
        if ($teacher !== '') {
            if (!ctype_digit($teacher) || !Classroom::isActiveTeacher((int) $teacher)) {
                Session::flash('error', 'Giáo viên giảng dạy không hợp lệ.');
                $this->redirect('/classes/' . $classId);
            }
            $teacherId = (int) $teacher;
        }

        ClassSubject::assign($classId, (int) $subject['id'], $teacherId);
        Session::flash('success', 'Đã thêm môn ' . $subject['code'] . ' vào lớp ' . $class['code'] . '.');
        $this->redirect('/classes/' . $classId);
    }

    /** Bỏ một môn khỏi lớp (chỉ quản trị viên) */
    public function removeSubject(string $id, string $sid): void
    {
        $class = $this->findAndAuthorize($id);
        if (ctype_digit($sid) && \App\Models\Exam::existsForClassSubject((int) $class['id'], (int) $sid)) {
            Session::flash('error', 'Không thể bỏ môn khỏi lớp ' . $class['code'] . ' vì lớp đang có đề thi của môn này.');
            $this->redirect('/classes/' . $class['id']);
        }
        if (ctype_digit($sid) && ClassSubject::unassign((int) $class['id'], (int) $sid)) {
            Session::flash('success', 'Đã bỏ môn học khỏi lớp ' . $class['code'] . '.');
        } else {
            Session::flash('error', 'Môn học này không thuộc lớp.');
        }
        $this->redirect('/classes/' . $class['id']);
    }

    public function removeStudent(string $id, string $uid): void
    {
        $class = $this->findAndAuthorize($id);

        if (ctype_digit($uid) && Classroom::removeStudent((int) $class['id'], (int) $uid)) {
            Session::flash('success', 'Đã đưa sinh viên ra khỏi lớp ' . $class['code'] . '.');
        } else {
            Session::flash('error', 'Sinh viên này không thuộc lớp.');
        }
        $this->redirect('/classes/' . $class['id']);
    }

    // ------------------------------------------------------------------
    // Thêm / sửa / xóa lớp (chỉ quản trị viên - đã chặn ở routes)
    // ------------------------------------------------------------------
    public function create(): void
    {
        $this->view('classes/form', [
            'title' => 'Thêm lớp học', 'mode' => 'create', 'class' => null, 'teachers' => Classroom::teachers(),
        ]);
    }

    public function store(): void
    {
        [$data, $errors] = $this->validate($this->request->all(), null);
        if ($errors) {
            $this->backWithErrors('/classes/create', $errors, $data);
        }

        $newId = Classroom::create($data);
        Session::flash('success', 'Đã thêm lớp "' . $data['code'] . '".');
        $this->redirect('/classes/' . $newId);
    }

    public function edit(string $id): void
    {
        $class = $this->findAndAuthorize($id);
        $this->view('classes/form', [
            'title' => 'Sửa lớp học', 'mode' => 'edit', 'class' => $class, 'teachers' => Classroom::teachers(),
        ]);
    }

    public function update(string $id): void
    {
        $class = $this->findAndAuthorize($id);
        [$data, $errors] = $this->validate($this->request->all(), (int) $class['id']);
        if ($errors) {
            $this->backWithErrors('/classes/' . $class['id'] . '/edit', $errors, $data);
        }

        Classroom::update((int) $class['id'], $data);
        Session::flash('success', 'Đã cập nhật lớp "' . $data['code'] . '".');
        $this->redirect('/classes/' . $class['id']);
    }

    public function destroy(string $id): void
    {
        $class = $this->findAndAuthorize($id);
        $count = Classroom::studentCount((int) $class['id']);

        if ($count > 0) {
            Session::flash('error', "Lớp \"{$class['code']}\" còn $count sinh viên. Hãy đưa các sinh viên ra khỏi lớp trước khi xóa.");
            $this->redirect('/classes/' . $class['id']);
        }

        try {
            Classroom::delete((int) $class['id']);
            Audit::log('class.delete', $class['code'] . ' (#' . (int) $class['id'] . ')');
            Session::flash('success', 'Đã xóa lớp "' . $class['code'] . '".');
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            Session::flash('error', 'Không thể xóa lớp "' . $class['code'] . '" vì đã có dữ liệu liên quan.');
        }
        $this->redirect('/classes');
    }

    // ------------------------------------------------------------------
    // Hàm phụ
    // ------------------------------------------------------------------

    /** Tìm lớp; giáo viên chỉ được vào lớp mình chủ nhiệm */
    /**
     * $manage = true (mặc định, cho mọi thao tác sửa): chỉ quản trị viên hoặc giáo viên chủ nhiệm.
     * $manage = false (chỉ xem): thêm giáo viên giảng dạy / phụ trách môn của lớp.
     */
    private function findAndAuthorize(string $id, bool $manage = true): array
    {
        $class = ctype_digit($id) ? Classroom::withTeacher((int) $id) : null;
        if (!$class) {
            Response::abort(404, 'Không tìm thấy lớp học.');
        }
        if (!$this->canManageClass($class)) {
            if ($manage || !Classroom::canView((int) $class['id'], (int) $this->user()['id'])) {
                Response::abort(403, $manage ? 'Bạn không phải giáo viên chủ nhiệm của lớp này.' : 'Bạn không được phân công chủ nhiệm hoặc giảng dạy ở lớp này.');
            }
        }
        return $class;
    }

    /** Được thêm/bớt sinh viên: quản trị viên hoặc giáo viên chủ nhiệm lớp */
    private function canManageClass(array $class): bool
    {
        return $this->isAdmin() || (int) $class['teacher_id'] === (int) $this->user()['id'];
    }

    /** Rút gọn danh sách dài khi hiển thị thông báo */
    private function names(array $items, int $max = 8): string
    {
        $shown = array_slice($items, 0, $max);
        return implode(', ', $shown) . (count($items) > $max ? ' và ' . (count($items) - $max) . ' mục khác' : '');
    }

    private function backWithErrors(string $path, array $errors, array $data): void
    {
        Session::flash('errors', $errors);
        Session::flash('error', 'Vui lòng kiểm tra lại các thông tin bị đánh dấu lỗi.');
        foreach (['code', 'name', 'cohort', 'teacher_id'] as $f) {
            if (array_key_exists($f, $data)) {
                Session::flash('old_' . $f, (string) $data[$f]);
            }
        }
        $this->redirect($path);
    }

    private function validate(array $in, ?int $id): array
    {
        $errors = [];
        $code = trim((string) ($in['code'] ?? ''));
        $name = trim((string) ($in['name'] ?? ''));
        $cohort = trim((string) ($in['cohort'] ?? ''));
        $teacher = trim((string) ($in['teacher_id'] ?? ''));

        if ($code === '') {
            $errors['code'] = 'Vui lòng nhập mã lớp.';
        } elseif (str_len($code) < 2 || str_len($code) > 30) {
            $errors['code'] = 'Mã lớp phải từ 2 đến 30 ký tự.';
        } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $code)) {
            $errors['code'] = 'Chỉ dùng chữ không dấu, số và các ký tự . _ -';
        } elseif (Classroom::codeTaken($code, $id)) {
            $errors['code'] = 'Mã lớp này đã tồn tại.';
        }

        if ($name === '') {
            $errors['name'] = 'Vui lòng nhập tên lớp.';
        } elseif (str_len($name) > 150) {
            $errors['name'] = 'Tên lớp tối đa 150 ký tự.';
        }

        if (str_len($cohort) > 50) {
            $errors['cohort'] = 'Khóa tối đa 50 ký tự.';
        }

        $teacherId = null;
        if ($teacher !== '') {
            if (!ctype_digit($teacher) || !Classroom::isActiveTeacher((int) $teacher)) {
                $errors['teacher_id'] = 'Giáo viên chủ nhiệm không hợp lệ.';
            } else {
                $teacherId = (int) $teacher;
            }
        }

        return [[
            'code'       => $code,
            'name'       => $name,
            'cohort'     => $cohort === '' ? null : $cohort,
            'teacher_id' => $teacherId,
        ], $errors];
    }
}