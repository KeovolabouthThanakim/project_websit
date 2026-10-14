<?php
$items = $result['items'];
$page = $result['page'];
$pages = $result['pages'];

$ctx = '<input type="hidden" name="back_q" value="' . e($q) . '">'
     . '<input type="hidden" name="back_status" value="' . e($status) . '">'
     . '<input type="hidden" name="back_page" value="' . (int) $page . '">';

$pageUrl = function (int $p) use ($q, $status): string {
    $params = array_filter(['q' => $q, 'status' => $status, 'page' => $p > 1 ? $p : ''], fn($v) => $v !== '');
    return url('/subjects') . ($params ? '?' . http_build_query($params) : '');
};
?>
<div class="page-head">
    <div>
        <h1><?= $isAdmin ? 'Quản lý môn học' : 'Môn học của tôi' ?></h1>
        <p><?= $isAdmin ? 'Danh sách môn học và giáo viên phụ trách' : 'Các môn học bạn được phân công phụ trách' ?></p>
    </div>
    <?php if ($isAdmin): ?><a class="btn" href="<?= url('/subjects/create') ?>">+ Thêm môn học</a><?php endif; ?>
</div>

<form class="filter-bar" method="get" action="<?= url('/subjects') ?>">
    <div class="grow">
        <label for="q">Tìm kiếm</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Mã môn hoặc tên môn học">
    </div>
    <?php if ($isAdmin): ?>
    <div>
        <label for="status">Trạng thái</label>
        <select id="status" name="status">
            <option value="">Tất cả</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Đang sử dụng</option>
            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Ngừng sử dụng</option>
        </select>
    </div>
    <?php endif; ?>
    <button class="btn" type="submit">Tìm kiếm</button>
    <?php if ($q !== '' || $status !== ''): ?><a class="btn btn-outline" href="<?= url('/subjects') ?>">Xóa bộ lọc</a><?php endif; ?>
</form>

<p class="muted">Tìm thấy <strong><?= (int) $result['total'] ?></strong> môn học.</p>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr><th>STT</th><th>Mã môn</th><th>Tên môn học</th><th>Tín chỉ</th><th>Lớp học</th><th>Giáo viên phụ trách</th><th>Trạng thái</th><th>Thao tác</th></tr>
        </thead>
        <tbody>
        <?php foreach ($items as $i => $s): ?>
            <tr>
                <td><?= ($page - 1) * $perPage + $i + 1 ?></td>
                <td><a href="<?= url('/subjects/' . (int) $s['id']) ?>"><strong><?= e($s['code']) ?></strong></a></td>
                <td><?= e($s['name']) ?></td>
                <td><?= $s['credits'] !== null ? (int) $s['credits'] : '' ?></td>
                <td><?php if ($s['class_codes']): foreach (explode(', ', $s['class_codes']) as $cc): ?><span class="tag"><?= e($cc) ?></span><?php endforeach; else: ?><span class="muted">Chưa gán lớp</span><?php endif; ?></td>
                <td><?= $s['teacher_names'] ? e($s['teacher_names']) : '<span class="muted">Chưa phân công</span>' ?></td>
                <td><span class="badge <?= $s['is_active'] ? 'badge-on' : 'badge-off' ?>"><?= $s['is_active'] ? 'Đang sử dụng' : 'Ngừng sử dụng' ?></span></td>
                <td class="actions">
                    <a class="btn btn-sm" href="<?= url('/subjects/' . (int) $s['id']) ?>">Xem</a>
                    <?php if ($isAdmin): ?>
                        <a class="btn btn-sm btn-outline" href="<?= url('/subjects/' . (int) $s['id'] . '/edit') ?>">Sửa</a>
                        <form method="post" action="<?= url('/subjects/' . (int) $s['id'] . '/toggle') ?>">
                            <?= csrf_field() ?><?= $ctx ?>
                            <button class="btn btn-sm btn-outline" type="submit"><?= $s['is_active'] ? 'Ngừng sử dụng' : 'Kích hoạt' ?></button>
                        </form>
                        <form method="post" action="<?= url('/subjects/' . (int) $s['id'] . '/delete') ?>"
                              data-confirm="Bạn có chắc muốn xóa môn học &quot;<?= e($s['code']) ?>&quot;? Thao tác này không thể hoàn tác.">
                            <?= csrf_field() ?><?= $ctx ?>
                            <button class="btn btn-sm btn-danger" type="submit">Xóa</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?>
            <tr><td colspan="8" class="empty"><?= $isAdmin ? 'Không có môn học nào phù hợp.' : 'Bạn chưa được phân công môn học nào. Vui lòng liên hệ quản trị viên.' ?></td></tr>
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