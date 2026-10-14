<?php
namespace App\Controllers;

use App\Models\Audit;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\User;

class UserController extends Controller
{
    private const PER_PAGE = 10;
    private const MIN_PASSWORD = 8;

    // ------------------------------------------------------------------
    // Danh sách
    // ------------------------------------------------------------------
    public function index(): void
    {
        $q = str_cut(trim((string) $this->request->input('q', '')), 100);
        $role = (string) $this->request->input('role', '');
        $page = (int) $this->request->input('page', 1);

        $result = User::search($q, $role, $page, self::PER_PAGE);

        $this->view('users/index', [
            'title'   => 'Quản lý người dùng',
            'result'  => $result,
            'perPage' => self::PER_PAGE,
            'filters' => ['q' => $q, 'role' => in_array($role, User::ROLES, true) ? $role : ''],
            'me'      => $this->user(),
        ]);
    }

    // ------------------------------------------------------------------
    // Thêm mới
    // ------------------------------------------------------------------
    public function create(): void
    {
        $this->view('users/form', ['title' => 'Thêm người dùng', 'mode' => 'create', 'target' => null, 'isSelf' => false]);
    }

    public function store(): void
    {
        $in = $this->request->all();
        [$data, $errors] = $this->validateProfile($in, null);

        $password = (string) ($in['password'] ?? '');
        $errors += $this->validatePassword($password, (string) ($in['password_confirm'] ?? ''));

        if ($errors) {
            $this->backWithErrors('/admin/users/create', $errors, $data);
        }

        $newId = User::create($data + ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        Audit::log('user.create', $data['username'] . ' (' . role_label($data['role']) . ', #' . $newId . ')');
        Session::flash('success', 'Đã thêm người dùng "' . $data['username'] . '".');
        $this->redirect('/admin/users');
    }

    // ------------------------------------------------------------------
    // Sửa
    // ------------------------------------------------------------------
    public function edit(string $id): void
    {
        $target = $this->findOrFail($id);
        unset($target['password_hash']);

        $this->view('users/form', [
            'title'  => 'Sửa người dùng',
            'mode'   => 'edit',
            'target' => $target,
            'isSelf' => $this->isSelf($target),
        ]);
    }

    public function update(string $id): void
    {
        $target = $this->findOrFail($id);
        $in = $this->request->all();

        // Không cho tự đổi vai trò hoặc tự khóa chính mình (tránh mất quyền quản trị)
        $self = $this->isSelf($target);
        if ($self) {
            $in['role'] = $target['role'];
            $in['is_active'] = '1';
        }

        [$data, $errors] = $this->validateProfile($in, (int) $target['id']);

        if ($errors) {
            $this->backWithErrors('/admin/users/' . $target['id'] . '/edit', $errors, $data);
        }

        User::update((int) $target['id'], $data);
        Audit::log('user.update', $data['username'] . ' (#' . (int) $target['id'] . ')' . ($target['role'] !== $data['role'] ? ': vai trò ' . role_label($target['role']) . ' -> ' . role_label($data['role']) : ''));

        // Đổi vai trò: không còn là sinh viên -> rời lớp; không còn là giáo viên -> bỏ phân công chủ nhiệm và môn học
        if ($target['role'] === 'student' && $data['role'] !== 'student') {
            Classroom::forgetStudent((int) $target['id']);
        }
        if ($target['role'] === 'teacher' && $data['role'] !== 'teacher') {
            Classroom::clearTeacher((int) $target['id']);
            Subject::forgetTeacher((int) $target['id']);
            \App\Models\ClassSubject::forgetTeacher((int) $target['id']);
        }

        Session::flash('success', 'Đã cập nhật thông tin người dùng "' . $data['username'] . '".');
        $this->redirect($this->listUrl());
    }

    public function resetPassword(string $id): void
    {
        $target = $this->findOrFail($id);
        $in = $this->request->all();

        $errors = $this->validatePassword((string) ($in['password'] ?? ''), (string) ($in['password_confirm'] ?? ''));
        if ($errors) {
            $this->backWithErrors('/admin/users/' . $target['id'] . '/edit', $errors, []);
        }

        User::update((int) $target['id'], ['password_hash' => password_hash((string) $in['password'], PASSWORD_DEFAULT)]);
        Audit::log('user.password', $target['username'] . ' (#' . (int) $target['id'] . ')');
        Session::flash('success', 'Đã đặt lại mật khẩu cho "' . $target['username'] . '".');
        $this->redirect('/admin/users/' . $target['id'] . '/edit');
    }

    // ------------------------------------------------------------------
    // Khóa / mở khóa, xóa
    // ------------------------------------------------------------------
    public function toggle(string $id): void
    {
        $target = $this->findOrFail($id);

        if ($this->isSelf($target)) {
            Session::flash('error', 'Bạn không thể khóa tài khoản của chính mình.');
            $this->redirect($this->listUrl());
        }

        $newState = $target['is_active'] ? 0 : 1;
        User::update((int) $target['id'], ['is_active' => $newState]);
        Audit::log($newState ? 'user.unlock' : 'user.lock', $target['username'] . ' (#' . (int) $target['id'] . ')');
        Session::flash('success', ($newState ? 'Đã mở khóa' : 'Đã khóa') . ' tài khoản "' . $target['username'] . '".');
        $this->redirect($this->listUrl());
    }

    public function destroy(string $id): void
    {
        $target = $this->findOrFail($id);

        if ($this->isSelf($target)) {
            Session::flash('error', 'Bạn không thể xóa tài khoản của chính mình.');
            $this->redirect($this->listUrl());
        }

        try {
            User::delete((int) $target['id']);
            Audit::log('user.delete', $target['username'] . ' (' . role_label($target['role']) . ', #' . (int) $target['id'] . ')');
            Session::flash('success', 'Đã xóa người dùng "' . $target['username'] . '".');
        } catch (\PDOException $e) {
            // Vi phạm khóa ngoại: tài khoản đã có dữ liệu liên quan (bài thi, đề thi...)
            if ($e->getCode() === '23000') {
                Session::flash('error', 'Không thể xóa "' . $target['username'] . '" vì đã có dữ liệu liên quan. Hãy khóa tài khoản thay vì xóa.');
            } else {
                throw $e;
            }
        }
        $this->redirect($this->listUrl());
    }

    // ------------------------------------------------------------------
    // Hàm phụ
    // ------------------------------------------------------------------
    private function findOrFail(string $id): array
    {
        $user = ctype_digit($id) ? User::find((int) $id) : null;
        if (!$user) {
            Response::abort(404, 'Không tìm thấy người dùng.');
        }
        return $user;
    }

    private function isSelf(array $target): bool
    {
        return (int) $target['id'] === (int) $this->user()['id'];
    }

    /** Quay lại danh sách, giữ nguyên bộ lọc/trang đang xem (các trường back_q, back_role, back_page) */
    private function listUrl(): string
    {
        $role = (string) $this->request->input('back_role', '');
        $page = (int) $this->request->input('back_page', 1);

        $params = array_filter([
            'q'    => str_cut(trim((string) $this->request->input('back_q', '')), 100),
            'role' => in_array($role, User::ROLES, true) ? $role : '',
            'page' => $page > 1 ? $page : '',
        ], fn($v) => $v !== '');

        return '/admin/users' . ($params ? '?' . http_build_query($params) : '');
    }

    /** Lưu lỗi + dữ liệu đã nhập vào flash rồi quay lại form */
    private function backWithErrors(string $path, array $errors, array $data): void
    {
        Session::flash('errors', $errors);
        Session::flash('error', 'Vui lòng kiểm tra lại các thông tin bị đánh dấu lỗi.');
        foreach (['username', 'student_code', 'full_name', 'email', 'date_of_birth', 'role'] as $field) {
            if (isset($data[$field])) {
                Session::flash('old_' . $field, (string) $data[$field]);
            }
        }
        if (array_key_exists('is_active', $data)) {
            Session::flash('old_is_active', $data['is_active'] ? '1' : '0');
        }
        $this->redirect($path);
    }

    /** Kiểm tra họ tên, tên đăng nhập, email, vai trò. Trả về [dữ liệu sạch, lỗi] */
    private function validateProfile(array $in, ?int $id): array
    {
        $errors = [];
        $username = trim((string) ($in['username'] ?? ''));
        $fullName = trim((string) ($in['full_name'] ?? ''));
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        $role = (string) ($in['role'] ?? '');
        $dobRaw = trim((string) ($in['date_of_birth'] ?? ''));
        $code = trim((string) ($in['student_code'] ?? ''));

        if ($username === '') {
            $errors['username'] = 'Vui lòng nhập tên đăng nhập.';
        } elseif (str_len($username) < 3 || str_len($username) > 50) {
            $errors['username'] = 'Tên đăng nhập phải từ 3 đến 50 ký tự.';
        } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            $errors['username'] = 'Chỉ dùng chữ không dấu, số và các ký tự . _ -';
        } elseif (User::taken('username', $username, $id)) {
            $errors['username'] = 'Tên đăng nhập này đã có người sử dụng.';
        }

        // Mã sinh viên: bắt buộc với sinh viên, không dùng cho vai trò khác
        if (User::hasStudentCode()) {
            if ($role !== 'student') {
                $code = '';
            } elseif ($code === '') {
                $errors['student_code'] = 'Vui lòng nhập mã sinh viên.';
            } elseif (str_len($code) < 3 || str_len($code) > 30) {
                $errors['student_code'] = 'Mã sinh viên phải từ 3 đến 30 ký tự.';
            } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $code)) {
                $errors['student_code'] = 'Mã sinh viên chỉ dùng chữ không dấu, số và các ký tự . _ -';
            } elseif (User::taken('student_code', $code, $id)) {
                $errors['student_code'] = 'Mã sinh viên này đã có người sử dụng.';
            }
        }

        if ($fullName === '') {
            $errors['full_name'] = 'Vui lòng nhập họ và tên.';
        } elseif (str_len($fullName) > 100) {
            $errors['full_name'] = 'Họ và tên tối đa 100 ký tự.';
        }

        if ($email !== '') {
            if (str_len($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Địa chỉ email không hợp lệ.';
            } elseif (User::taken('email', $email, $id)) {
                $errors['email'] = 'Email này đã có người sử dụng.';
            }
        }

        if (!in_array($role, User::ROLES, true)) {
            $errors['role'] = 'Vui lòng chọn vai trò.';
        }

        // Ngày sinh: không bắt buộc; nếu có thì phải là ngày có thật và không ở tương lai
        $dob = null;
        if ($dobRaw !== '') {
            $dob = parse_dob($dobRaw);
            if ($dob === null) {
                $errors['date_of_birth'] = 'Ngày sinh không hợp lệ (định dạng ngày/tháng/năm, không ở tương lai).';
            }
        }

        $data = [
            'username'  => $username,
            'full_name' => $fullName,
            'email'     => $email === '' ? null : $email,   // NULL để nhiều tài khoản được để trống email
            'role'      => $role,
            'is_active' => !empty($in['is_active']) ? 1 : 0,
        ];
        if (User::hasStudentCode()) {                       // chưa chạy 015_users_student_code.sql thì bỏ qua
            $data['student_code'] = $code === '' ? null : $code;   // NULL để nhiều tài khoản không phải sinh viên không bị trùng
        }
        if (User::hasDob()) {                               // chưa chạy 014_users_date_of_birth.sql thì bỏ qua, không gây lỗi SQL
            $data['date_of_birth'] = $dob;
        }
        // Giữ lại chuỗi người dùng đã nhập để hiện lại trong form khi có lỗi
        if (isset($errors['date_of_birth'])) {
            $data['date_of_birth'] = $dobRaw;
        }
        if (isset($errors['student_code'])) {
            $data['student_code'] = $code;
        }
        return [$data, $errors];
    }

    private function validatePassword(string $password, string $confirm): array
    {
        $errors = [];
        if (str_len($password) < self::MIN_PASSWORD) {
            $errors['password'] = 'Mật khẩu phải có ít nhất ' . self::MIN_PASSWORD . ' ký tự.';
        } elseif (strlen($password) > 72) {
            $errors['password'] = 'Mật khẩu tối đa 72 ký tự.';
        } elseif ($password !== $confirm) {
            $errors['password_confirm'] = 'Mật khẩu nhập lại không khớp.';
        }
        return $errors;
    }
}