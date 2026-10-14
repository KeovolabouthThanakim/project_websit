<?php
$items = $result['items'];
$page = $result['page'];
$pages = $result['pages'];
$pageUrl = function (int $p) use ($q): string {
    $params = array_filter(['q' => $q, 'page' => $p > 1 ? $p : ''], fn($v) => $v !== '');
    return url('/classes') . ($params ? '?' . http_build_query($params) : '');
};
?>
<div class="page-head">
    <div>
        <h1><?= $isAdmin ? 'Quản lý lớp học' : 'Lớp của tôi' ?></h1>
        <p><?= $isAdmin ? 'Danh sách lớp học và giáo viên chủ nhiệm' : 'Các lớp bạn chủ nhiệm hoặc giảng dạy' ?></p>
    </div>
    <?php if ($isAdmin): ?><a class="btn" href="<?= url('/classes/create') ?>">+ Thêm lớp học</a><?php endif; ?>
</div>

<div class="stats">
    <div class="stat"><span class="stat-num"><?= (int) $totals['classes'] ?></span><span class="stat-label"><?= $isAdmin ? 'Tổng số lớp' : 'Số lớp của bạn' ?></span></div>
    <div class="stat"><span class="stat-num"><?= (int) $totals['students'] ?></span><span class="stat-label">Tổng số sinh viên (tất cả các lớp<?= $isAdmin ? '' : ' của bạn' ?>)</span></div>
</div>

<form class="filter-bar" method="get" action="<?= url('/classes') ?>">
    <div class="grow">
        <label for="q">Tìm kiếm</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Mã lớp, tên lớp hoặc khóa">
    </div>
    <button class="btn" type="submit">Tìm kiếm</button>
    <?php if ($q !== ''): ?><a class="btn btn-outline" href="<?= url('/classes') ?>">Xóa bộ lọc</a><?php endif; ?>
</form>

<p class="muted">Tìm thấy <strong><?= (int) $result['total'] ?></strong> lớp học.</p>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr><th>STT</th><th>Mã lớp</th><th>Tên lớp</th><th>Khóa</th><th>Giáo viên chủ nhiệm</th><th>Sĩ số</th><th>Số môn học</th><th>Thao tác</th></tr>
        </thead>
        <tbody>
        <?php foreach ($items as $i => $c): ?>
            <tr>
                <td><?= ($page - 1) * $perPage + $i + 1 ?></td>
                <td><a href="<?= url('/classes/' . (int) $c['id']) ?>"><strong><?= e($c['code']) ?></strong></a></td>
                <td><?= e($c['name']) ?></td>
                <td><?= e($c['cohort']) ?></td>
                <td><?= $c['teacher_name'] ? e($c['teacher_name']) : '<span class="muted">Chưa phân công</span>' ?><?= (!$isAdmin && !$c['is_homeroom']) ? ' <span class="badge badge-st-draft" title="Bạn giảng dạy ở lớp này, chỉ được xem">Giảng dạy</span>' : '' ?></td>
                <td><?= (int) $c['student_count'] ?></td>
                <td><?= (int) $c['subject_count'] ?></td>
                <td class="actions">
                    <a class="btn btn-sm" href="<?= url('/classes/' . (int) $c['id']) ?>"><?= ($isAdmin || $c['is_homeroom']) ? 'Xem / Quản lý SV' : 'Xem sinh viên' ?></a>
                    <?php if ($isAdmin): ?>
                        <a class="btn btn-sm btn-outline" href="<?= url('/classes/' . (int) $c['id'] . '/edit') ?>">Sửa</a>
                        <form method="post" action="<?= url('/classes/' . (int) $c['id'] . '/delete') ?>"
                              data-confirm="Bạn có chắc muốn xóa lớp &quot;<?= e($c['code']) ?>&quot;?">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-danger" type="submit">Xóa</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?>
            <tr><td colspan="8" class="empty"><?= $isAdmin ? 'Chưa có lớp học nào phù hợp.' : 'Bạn chưa được phân công chủ nhiệm hay giảng dạy lớp nào. Vui lòng liên hệ quản trị viên.' ?></td></tr>
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