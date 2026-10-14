<?php
$q = $filters['q'];
$role = $filters['role'];
$items = $result['items'];
$page = $result['page'];
$pages = $result['pages'];

// Các input ẩn để giữ nguyên bộ lọc/trang sau khi thao tác khóa, xóa
$ctx = '<input type="hidden" name="back_q" value="' . e($q) . '">'
     . '<input type="hidden" name="back_role" value="' . e($role) . '">'
     . '<input type="hidden" name="back_page" value="' . (int) $page . '">';

$pageUrl = function (int $p) use ($q, $role): string {
    $params = array_filter(['q' => $q, 'role' => $role, 'page' => $p > 1 ? $p : ''], fn($v) => $v !== '');
    return url('/admin/users') . ($params ? '?' . http_build_query($params) : '');
};
?>
<div class="page-head">
    <div>
        <h1>Quản lý người dùng</h1>
        <p>Danh sách tài khoản quản trị viên, giảng viên và sinh viên</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-outline" href="<?= url('/admin/users/import') ?>">Nhập từ Excel / CSV</a>
        <a class="btn" href="<?= url('/admin/users/create') ?>">+ Thêm người dùng</a>
    </div>
</div>

<form class="filter-bar" method="get" action="<?= url('/admin/users') ?>">
    <div class="grow">
        <label for="q">Tìm kiếm</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Tên đăng nhập, mã SV, họ tên hoặc email">
    </div>
    <div>
        <label for="role">Vai trò</label>
        <select id="role" name="role">
            <option value="">Tất cả</option>
            <?php foreach (App\Models\User::ROLES as $r): ?>
                <option value="<?= e($r) ?>" <?= $role === $r ? 'selected' : '' ?>><?= e(role_label($r)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn" type="submit">Tìm kiếm</button>
    <?php if ($q !== '' || $role !== ''): ?>
        <a class="btn btn-outline" href="<?= url('/admin/users') ?>">Xóa bộ lọc</a>
    <?php endif; ?>
</form>

<p class="muted">Tìm thấy <strong><?= (int) $result['total'] ?></strong> tài khoản.</p>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>STT</th><th>Tên đăng nhập</th><th>Mã SV</th><th>Họ và tên</th><th>Ngày sinh</th><th>Email</th>
                <th>Vai trò</th><th>Lớp</th><th>Trạng thái</th><th>Ngày tạo</th><th>Thao tác</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $i => $u): ?>
            <?php $isSelf = (int) $u['id'] === (int) $me['id']; ?>
            <tr>
                <td><?= ($page - 1) * $perPage + $i + 1 ?></td>
                <td><?= e($u['username']) ?><?= $isSelf ? ' <span class="muted">(bạn)</span>' : '' ?></td>
                <td><?= e($u['student_code'] ?? '') ?><?= $u['role'] === 'student' && trim((string) ($u['student_code'] ?? '')) === '' && App\Models\User::hasStudentCode() ? '<span class="badge badge-off">Chưa có</span>' : '' ?></td>
                <td><?= e($u['full_name']) ?></td>
                <td><?= e(fmt_dob($u['date_of_birth'] ?? null)) ?></td>
                <td><?= e($u['email']) ?></td>
                <td><span class="badge badge-<?= e($u['role']) ?>"><?= e(role_label($u['role'])) ?></span></td>
                <td><?= e($u['class_code']) ?></td>
                <td><span class="badge <?= $u['is_active'] ? 'badge-on' : 'badge-off' ?>"><?= $u['is_active'] ? 'Hoạt động' : 'Đã khóa' ?></span></td>
                <td><?= e(fmt_dob($u['created_at'])) ?></td>
                <td class="actions">
                    <a class="btn btn-sm btn-outline" href="<?= url('/admin/users/' . (int) $u['id'] . '/edit') ?>">Sửa</a>
                    <?php if (!$isSelf): ?>
                        <form method="post" action="<?= url('/admin/users/' . (int) $u['id'] . '/toggle') ?>">
                            <?= csrf_field() ?><?= $ctx ?>
                            <button class="btn btn-sm btn-outline" type="submit"><?= $u['is_active'] ? 'Khóa' : 'Mở khóa' ?></button>
                        </form>
                        <form method="post" action="<?= url('/admin/users/' . (int) $u['id'] . '/delete') ?>"
                              data-confirm="Bạn có chắc muốn xóa người dùng &quot;<?= e($u['username']) ?>&quot;? Thao tác này không thể hoàn tác.">
                            <?= csrf_field() ?><?= $ctx ?>
                            <button class="btn btn-sm btn-danger" type="submit">Xóa</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?>
            <tr><td colspan="11" class="empty">Không có tài khoản nào phù hợp.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
    <div class="pagination">
        <span class="muted">Trang <?= (int) $page ?> / <?= (int) $pages ?></span>
        <div class="pages">
            <?php if ($page > 1): ?><a href="<?= $pageUrl($page - 1) ?>">&laquo; Trước</a><?php endif; ?>
            <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
                <?php if ($p === $page): ?><span class="cur"><?= $p ?></span>
                <?php else: ?><a href="<?= $pageUrl($p) ?>"><?= $p ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $pages): ?><a href="<?= $pageUrl($page + 1) ?>">Sau &raquo;</a><?php endif; ?>
        </div>
    </div>
<?php endif; ?>