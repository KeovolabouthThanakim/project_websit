<div class="page-head">
    <div>
        <h1>Xem trước khi nhập</h1>
        <p>Nguồn: <?= e($source) ?></p>
    </div>
    <a class="btn btn-outline" href="<?= url('/admin/users/import') ?>">&laquo; Chọn file khác</a>
</div>

<div class="stats">
    <div class="stat"><span class="stat-num"><?= $count['ok'] ?></span><span class="stat-label">Dòng hợp lệ, sẽ được tạo</span></div>
    <div class="stat <?= $count['error'] > 0 ? 'warn' : '' ?>"><span class="stat-num"><?= $count['error'] ?></span><span class="stat-label">Dòng có lỗi, sẽ bị bỏ qua</span></div>
    <div class="stat"><span class="stat-num"><?= count($newClasses) ?></span><span class="stat-label">Lớp mới sẽ được tạo</span></div>
</div>

<?php if ($count['ok'] > 0): ?>
<section class="panel"><div class="panel-body">
    <form method="post" action="<?= url('/admin/users/import/confirm') ?>" data-confirm="Tạo <?= (int) $count['ok'] ?> tài khoản<?= $newClasses ? ' và ' . count($newClasses) . ' lớp mới' : '' ?>?">
        <?= csrf_field() ?>
        <button class="btn" type="submit">Tạo <?= (int) $count['ok'] ?> tài khoản</button>
        <?php if ($count['error'] > 0): ?><span class="muted" style="margin-left:10px">Các dòng lỗi không được tạo; sửa file rồi tải lại nếu muốn nhập thêm.</span><?php endif; ?>
    </form>
    <?php if ($newClasses): ?><p class="muted" style="margin:12px 0 0">Lớp sẽ được tạo mới: <?php foreach ($newClasses as $c): ?><span class="tag"><?= e($c) ?></span><?php endforeach; ?></p><?php endif; ?>
</div></section>
<?php else: ?>
<section class="panel"><div class="panel-body"><p style="margin:0">Không có dòng hợp lệ nào để nhập. Hãy sửa các lỗi bên dưới rồi tải file lại.</p></div></section>
<?php endif; ?>

<section class="panel">
    <h2 class="panel-title">Chi tiết (<?= count($rows) ?> dòng)</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Dòng</th><th>Tên đăng nhập</th><th>Mã SV</th><th>Họ và tên</th><th>Ngày sinh</th><th>Email</th><th>Vai trò</th><th>Lớp</th><th>Mật khẩu</th><th>Kết quả</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr class="<?= $r['status'] === 'error' ? 'import-bad' : '' ?>">
                    <td data-label="Dòng"><?= (int) $r['line'] ?></td>
                    <td data-label="Tên đăng nhập"><strong><?= e($r['username']) ?></strong></td>
                    <td data-label="Mã SV"><?= e($r['student_code'] ?? '') ?></td>
                    <td data-label="Họ và tên"><?= e($r['full_name']) ?></td>
                    <td data-label="Ngày sinh"><?= ($r['dob'] ?? '') !== '' ? e(fmt_dob($r['dob'])) : '<span class="muted">-</span>' ?></td>
                    <td data-label="Email"><?= e($r['email']) ?></td>
                    <td data-label="Vai trò"><span class="badge badge-<?= e($r['role']) ?>"><?= e(role_label($r['role'])) ?></span></td>
                    <td data-label="Lớp"><?= $r['class'] !== '' ? '<span class="tag">' . e($r['class']) . '</span>' . ($r['new_class'] ? ' <span class="muted">(mới)</span>' : '') : '<span class="muted">-</span>' ?></td>
                    <td data-label="Mật khẩu"><?= $r['password'] !== '' ? 'Do bạn đặt' : '<span class="muted">Tự sinh</span>' ?></td>
                    <td data-label="Kết quả">
                        <?php if ($r['status'] === 'ok'): ?><span class="badge badge-on">Hợp lệ</span><?php else: ?><span class="badge badge-off">Có lỗi</span><?php endif; ?>
                        <?php foreach ($r['errors'] as $err): ?><div class="text-warn" style="font-size:.85rem;margin-top:4px"><?= e($err) ?></div><?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
