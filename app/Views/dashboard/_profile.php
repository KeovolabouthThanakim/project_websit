<section class="panel">
    <h2 class="panel-title">Thông tin tài khoản</h2>
    <div class="panel-body">
        <dl class="info">
            <dt>Họ và tên</dt><dd><?= e($user['full_name']) ?></dd>
            <dt>Tên đăng nhập</dt><dd><?= e($user['username']) ?></dd>
            <dt>Ngày sinh</dt><dd><?= e(fmt_dob($user['date_of_birth'] ?? null) ?: 'Chưa cập nhật') ?></dd>
            <dt>Email</dt><dd><?= e($user['email'] ?: 'Chưa cập nhật') ?></dd>
            <dt>Vai trò</dt><dd><?= e(role_label($user['role'])) ?></dd>
            <?php if ($user['role'] === 'student'): ?>
                <dt>Mã sinh viên</dt><dd><?= e(sv_code($user)) ?></dd>
                <dt>Lớp</dt><dd><?= !empty($class) ? e($class['code'] . ' - ' . $class['name']) : 'Chưa được xếp lớp' ?></dd>
                <?php if (!empty($class)): ?>
                    <dt>GV chủ nhiệm</dt><dd><?= $class['teacher_name'] ? e($class['teacher_name']) : 'Chưa phân công' ?></dd>
                <?php endif; ?>
            <?php endif; ?>
        </dl>
    </div>
</section>