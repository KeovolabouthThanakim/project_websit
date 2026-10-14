<?php $gen = count(array_filter($rows, fn($r) => $r['generated'])); ?>
<div class="page-head">
    <div>
        <h1>Đã tạo <?= count($rows) ?> tài khoản</h1>
        <p>Danh sách tài khoản vừa tạo<?= $gen ? ' và mật khẩu tự sinh' : '' ?></p>
    </div>
    <div class="header-actions">
        <a class="btn" href="<?= url('/admin/users/import/result.csv') ?>">Tải CSV tài khoản<?= $gen ? ' + mật khẩu' : '' ?></a>
        <a class="btn btn-outline" href="<?= url('/admin/users?role=student') ?>">Danh sách sinh viên</a>
    </div>
</div>

<?php if ($gen): ?>
<div class="alert alert-error" role="alert" style="margin-bottom:24px">
    <strong>Mật khẩu chỉ hiện ở trang này.</strong> Hãy tải CSV ngay để phát cho người dùng; sau khi tải (hoặc khi bạn rời đi và bắt đầu lần nhập mới) hệ thống không còn giữ mật khẩu rõ. Nếu mất, dùng "Đặt lại mật khẩu" ở trang sửa người dùng. Khuyến nghị bảo người dùng đổi mật khẩu sau lần đăng nhập đầu.
</div>
<?php endif; ?>

<section class="panel">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Tên đăng nhập</th><th>Mã SV</th><th>Họ và tên</th><th>Vai trò</th><th>Lớp</th><th>Mật khẩu ban đầu</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td data-label="Tên đăng nhập"><strong><?= e($r['username']) ?></strong></td>
                    <td data-label="Mã SV"><?= e($r['student_code'] ?? '') ?></td>
                    <td data-label="Họ và tên"><?= e($r['full_name']) ?></td>
                    <td data-label="Vai trò"><?= e(role_label($r['role'])) ?></td>
                    <td data-label="Lớp"><?= $r['class'] !== '' ? '<span class="tag">' . e($r['class']) . '</span>' : '<span class="muted">-</span>' ?></td>
                    <td data-label="Mật khẩu"><?= $r['generated'] ? '<code>' . e($r['password']) . '</code>' : '<span class="muted">do bạn đặt trong file</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
