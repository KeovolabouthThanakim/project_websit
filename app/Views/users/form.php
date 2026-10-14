<?php
$isEdit = $mode === 'edit';
form_errors();   // đọc lỗi 1 lần
$has = fn(string $f): string => isset(form_errors()[$f]) ? ' has-error' : '';

$username = old('username', $isEdit ? $target['username'] : '');
$code     = old('student_code', $isEdit ? (string) ($target['student_code'] ?? '') : '');
$fullName = old('full_name', $isEdit ? $target['full_name'] : '');
$email    = old('email', $isEdit ? (string) $target['email'] : '');
$dob      = old('date_of_birth', $isEdit ? (string) ($target['date_of_birth'] ?? '') : '');
$role     = old('role', $isEdit ? $target['role'] : 'student');
$active   = old('is_active', $isEdit ? ($target['is_active'] ? '1' : '0') : '1') === '1';
$action   = $isEdit ? url('/admin/users/' . (int) $target['id']) : url('/admin/users');
?>
<div class="page-head">
    <div>
        <h1><?= $isEdit ? 'Sửa thông tin người dùng' : 'Thêm người dùng mới' ?></h1>
        <p><?= $isEdit ? 'Tài khoản: ' . e($target['username']) : 'Nhập đầy đủ thông tin tài khoản' ?></p>
    </div>
    <a class="btn btn-outline" href="<?= url('/admin/users') ?>">&laquo; Quay lại danh sách</a>
</div>

<section class="panel">
    <h2 class="panel-title">Thông tin tài khoản</h2>
    <div class="panel-body">
        <form method="post" action="<?= $action ?>" autocomplete="off">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="form-row<?= $has('username') ?>">
                    <label for="username">Tên đăng nhập <span class="req">*</span></label>
                    <input type="text" id="username" name="username" value="<?= e($username) ?>" maxlength="50" required>
                    <?= field_error('username') ?>
                    <span class="hint">Dùng để đăng nhập (vd mã giảng viên). Chỉ dùng chữ không dấu, số và . _ -</span>
                </div>
                <?php if (App\Models\User::hasStudentCode()): ?>
                <div class="form-row<?= $has('student_code') ?>" id="student_code_row">
                    <label for="student_code">Mã sinh viên <span class="req">*</span></label>
                    <input type="text" id="student_code" name="student_code" value="<?= e($code) ?>" maxlength="30">
                    <?= field_error('student_code') ?>
                    <span class="hint">Chỉ dùng cho vai trò Sinh viên. Chữ không dấu, số và . _ -</span>
                </div>
                <?php endif; ?>
                <div class="form-row<?= $has('full_name') ?>">
                    <label for="full_name">Họ và tên <span class="req">*</span></label>
                    <input type="text" id="full_name" name="full_name" value="<?= e($fullName) ?>" maxlength="100" required>
                    <?= field_error('full_name') ?>
                </div>
                <div class="form-row<?= $has('email') ?>">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= e($email) ?>" maxlength="120">
                    <?= field_error('email') ?>
                </div>
                <?php if (App\Models\User::hasDob()): ?>
                <div class="form-row<?= $has('date_of_birth') ?>">
                    <label for="date_of_birth">Ngày sinh</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" value="<?= e($dob) ?>" min="1900-01-01" max="<?= date('Y-m-d') ?>">
                    <?= field_error('date_of_birth') ?>
                    <span class="hint">Không bắt buộc. Chọn ngày/tháng/năm sinh.</span>
                </div>
                <?php endif; ?>
                <div class="form-row<?= $has('role') ?>">
                    <label for="role">Vai trò <span class="req">*</span></label>
                    <select id="role" name="role" <?= $isSelf ? 'disabled' : '' ?>>
                        <?php foreach (App\Models\User::ROLES as $r): ?>
                            <option value="<?= e($r) ?>" <?= $role === $r ? 'selected' : '' ?>><?= e(role_label($r)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error('role') ?>
                    <?php if ($isSelf): ?><span class="hint">Bạn không thể đổi vai trò của chính mình.</span><?php endif; ?>
                </div>
                <div class="form-row">
                    <label for="is_active">Trạng thái</label>
                    <label class="status-check" for="is_active">
                        <input type="checkbox" id="is_active" name="is_active" value="1" <?= $active ? 'checked' : '' ?> <?= $isSelf ? 'disabled' : '' ?>>
                        Tài khoản đang hoạt động
                    </label>
                    <span class="hint">Bỏ chọn để khóa: người dùng sẽ không đăng nhập được.</span>
                </div>

                <?php if (!$isEdit): ?>
                    <div class="form-row<?= $has('password') ?>">
                        <label for="password">Mật khẩu <span class="req">*</span></label>
                        <input type="password" id="password" name="password" autocomplete="new-password" required>
                        <?= field_error('password') ?>
                        <span class="hint">Tối thiểu 8 ký tự.</span>
                    </div>
                    <div class="form-row<?= $has('password_confirm') ?>">
                        <label for="password_confirm">Nhập lại mật khẩu <span class="req">*</span></label>
                        <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
                        <?= field_error('password_confirm') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button class="btn" type="submit"><?= $isEdit ? 'Lưu thay đổi' : 'Thêm người dùng' ?></button>
                <a class="btn btn-outline" href="<?= url('/admin/users') ?>">Hủy</a>
            </div>
        </form>
    </div>
</section>

<?php if ($isEdit): ?>
<section class="panel">
    <h2 class="panel-title">Đặt lại mật khẩu</h2>
    <div class="panel-body">
        <form method="post" action="<?= url('/admin/users/' . (int) $target['id'] . '/password') ?>" autocomplete="off">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="form-row<?= $has('password') ?>">
                    <label for="new_password">Mật khẩu mới</label>
                    <input type="password" id="new_password" name="password" autocomplete="new-password" required>
                    <?= field_error('password') ?>
                    <span class="hint">Tối thiểu 8 ký tự.</span>
                </div>
                <div class="form-row<?= $has('password_confirm') ?>">
                    <label for="new_password_confirm">Nhập lại mật khẩu mới</label>
                    <input type="password" id="new_password_confirm" name="password_confirm" autocomplete="new-password" required>
                    <?= field_error('password_confirm') ?>
                </div>
            </div>
            <button class="btn btn-outline" type="submit">Đặt lại mật khẩu</button>
        </form>
    </div>
</section>
<?php endif; ?>

<?php if (App\Models\User::hasStudentCode()): ?>
<script>
(function () {
    var role = document.getElementById('role');
    var row = document.getElementById('student_code_row');
    var input = document.getElementById('student_code');
    if (!role || !row || !input) { return; }
    function sync() {
        var isStudent = role.value === 'student';
        row.style.display = isStudent ? '' : 'none';
        input.required = isStudent;
    }
    role.addEventListener('change', sync);
    sync();
})();
</script>
<?php endif; ?>
