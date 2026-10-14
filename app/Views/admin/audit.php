<?php
$items = $result['items'];
$page = $result['page'];
$pages = $result['pages'];
$pageUrl = function (int $p) use ($filters): string {
    $params = array_filter($filters + ['page' => $p > 1 ? $p : ''], fn($v) => $v !== '');
    return url('/admin/audit') . ($params ? '?' . http_build_query($params) : '');
};
$filtered = $filters['q'] !== '' || $filters['group'] !== '' || $filters['from'] !== '' || $filters['to'] !== '';
?>
<div class="page-head">
    <div>
        <h1>Nhật ký hoạt động</h1>
        <p>Ai đã làm gì và lúc nào: đăng nhập, quản lý người dùng, kỳ thi, cấp quyền, cài đặt</p>
    </div>
</div>

<?php if (!$ready): ?>
    <div class="panel"><div class="panel-body">Chưa bật nhật ký. Hãy nhập file <code>database/013_audit_logs.sql</code> vào cơ sở dữ liệu rồi tải lại trang.</div></div>
<?php else: ?>
<form class="filter-bar" method="get" action="<?= url('/admin/audit') ?>">
    <div class="grow">
        <label for="q">Tìm kiếm</label>
        <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Tên đăng nhập, nội dung hoặc địa chỉ IP">
    </div>
    <div>
        <label for="group">Nhóm</label>
        <select id="group" name="group">
            <option value="">Tất cả</option>
            <?php foreach (App\Models\Audit::GROUPS as $k => $label): ?>
                <option value="<?= e($k) ?>" <?= $filters['group'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="from">Từ ngày</label>
        <input type="date" id="from" name="from" value="<?= e($filters['from']) ?>">
    </div>
    <div>
        <label for="to">Đến ngày</label>
        <input type="date" id="to" name="to" value="<?= e($filters['to']) ?>">
    </div>
    <button class="btn" type="submit">Lọc</button>
    <?php if ($filtered): ?><a class="btn btn-outline" href="<?= url('/admin/audit') ?>">Xóa bộ lọc</a><?php endif; ?>
</form>

<p class="muted">Có <strong><?= (int) $result['total'] ?></strong> dòng. Nhật ký cũ hơn 365 ngày được tự dọn.</p>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr><th>Thời gian</th><th>Người thực hiện</th><th>Hành động</th><th>Chi tiết</th><th>IP</th></tr>
        </thead>
        <tbody>
        <?php foreach ($items as $r): ?>
            <tr>
                <td><?= e(fmt_date($r['created_at'])) ?></td>
                <td>
                    <?php if ((string) $r['username'] !== ''): ?>
                        <?= e($r['username']) ?>
                        <?php if ($r['role']): ?><span class="badge badge-<?= e($r['role']) ?>"><?= e(role_label($r['role'])) ?></span><?php endif; ?>
                    <?php else: ?><span class="muted">-</span><?php endif; ?>
                </td>
                <td><?= e(App\Models\Audit::label($r['action'])) ?></td>
                <td><?= e($r['detail']) ?></td>
                <td><?= e($r['ip']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?>
            <tr><td colspan="5" class="empty">Chưa có dòng nhật ký nào phù hợp.</td></tr>
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
<?php endif; ?>
