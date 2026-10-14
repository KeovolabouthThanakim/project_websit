<?php
$items = $result['items'];
$page = $result['page'];
$pages = $result['pages'];
$hasFilter = $f['q'] !== '' || $f['subject_id'] > 0 || $f['difficulty'] !== '' || $f['status'] !== '';

$ctx = '<input type="hidden" name="back_q" value="' . e($f['q']) . '">'
     . '<input type="hidden" name="back_subject_id" value="' . (int) $f['subject_id'] . '">'
     . '<input type="hidden" name="back_difficulty" value="' . e($f['difficulty']) . '">'
     . '<input type="hidden" name="back_status" value="' . e($f['status']) . '">'
     . '<input type="hidden" name="back_page" value="' . (int) $page . '">';

$pageUrl = function (int $p) use ($f): string {
    $params = array_filter($f + ['page' => $p > 1 ? $p : ''], fn($v) => $v !== '' && $v !== 0);
    return url('/questions') . ($params ? '?' . http_build_query($params) : '');
};
$addUrl = url('/questions/create' . ($f['subject_id'] > 0 ? '?subject_id=' . (int) $f['subject_id'] : ''));
?>
<div class="page-head">
    <div>
        <h1>Ngân hàng câu hỏi</h1>
        <p><?= $isAdmin ? 'Câu hỏi trắc nghiệm của tất cả các môn học' : 'Câu hỏi của các môn bạn được phân công phụ trách' ?></p>
    </div>
    <?php if ($subjects): ?>
    <div class="header-actions">
        <a class="btn btn-outline" href="<?= url('/questions/import' . ($f['subject_id'] > 0 ? '?subject_id=' . (int) $f['subject_id'] : '')) ?>">Nhập từ Excel / Word</a>
        <a class="btn" href="<?= $addUrl ?>">+ Thêm câu hỏi</a>
    </div>
    <?php endif; ?>
</div>

<form class="filter-bar" method="get" action="<?= url('/questions') ?>">
    <div class="grow">
        <label for="q">Tìm kiếm</label>
        <input type="search" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Từ khóa trong nội dung câu hỏi">
    </div>
    <div>
        <label for="subject_id">Môn học</label>
        <select id="subject_id" name="subject_id">
            <option value="">Tất cả</option>
            <?php foreach ($subjects as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= $f['subject_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['code'] . ' - ' . $s['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="difficulty">Độ khó</label>
        <select id="difficulty" name="difficulty">
            <option value="">Tất cả</option>
            <?php foreach (\App\Models\Question::DIFFICULTIES as $k => $label): ?>
                <option value="<?= e($k) ?>" <?= $f['difficulty'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="status">Trạng thái</label>
        <select id="status" name="status">
            <option value="">Tất cả</option>
            <option value="active" <?= $f['status'] === 'active' ? 'selected' : '' ?>>Đang hiện</option>
            <option value="inactive" <?= $f['status'] === 'inactive' ? 'selected' : '' ?>>Đã ẩn</option>
        </select>
    </div>
    <button class="btn" type="submit">Tìm kiếm</button>
    <?php if ($hasFilter): ?><a class="btn btn-outline" href="<?= url('/questions') ?>">Xóa bộ lọc</a><?php endif; ?>
</form>

<p class="muted">Tìm thấy <strong><?= (int) $result['total'] ?></strong> câu hỏi.</p>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr><th>STT</th><th>Nội dung câu hỏi</th><th>Môn học</th><th>Độ khó</th><th>Loại</th><th>Người soạn</th><th>Trạng thái</th><th>Thao tác</th></tr>
        </thead>
        <tbody>
        <?php foreach ($items as $i => $q): ?>
            <?php $text = trim(preg_replace('/\s+/u', ' ', $q['content'])); ?>
            <tr>
                <td><?= ($page - 1) * $perPage + $i + 1 ?></td>
                <td class="q-cell">
                    <a href="<?= url('/questions/' . (int) $q['id']) ?>"><?= e(str_cut($text, 130)) ?><?= str_len($text) > 130 ? '…' : '' ?></a>
                    <?php if ($q['image']): ?><span class="tag">Có ảnh</span><?php endif; ?>
                </td>
                <td><span class="tag" title="<?= e($q['subject_name']) ?>"><?= e($q['subject_code']) ?></span></td>
                <td><span class="badge badge-lv-<?= e($q['difficulty']) ?>"><?= e(\App\Models\Question::DIFFICULTIES[$q['difficulty']] ?? $q['difficulty']) ?></span></td>
                <td><?= (int) $q['correct_count'] > 1 ? 'Nhiều đáp án đúng' : 'Một đáp án đúng' ?> <span class="muted">(<?= (int) $q['option_count'] ?> đáp án)</span></td>
                <td><?= $q['author_name'] ? e($q['author_name']) : '<span class="muted">-</span>' ?></td>
                <td><span class="badge <?= $q['is_active'] ? 'badge-on' : 'badge-off' ?>"><?= $q['is_active'] ? 'Đang hiện' : 'Đã ẩn' ?></span></td>
                <td class="actions">
                    <a class="btn btn-sm" href="<?= url('/questions/' . (int) $q['id']) ?>">Xem</a>
                    <a class="btn btn-sm btn-outline" href="<?= url('/questions/' . (int) $q['id'] . '/edit') ?>">Sửa</a>
                    <form method="post" action="<?= url('/questions/' . (int) $q['id'] . '/toggle') ?>">
                        <?= csrf_field() ?><?= $ctx ?>
                        <button class="btn btn-sm btn-outline" type="submit"><?= $q['is_active'] ? 'Ẩn' : 'Hiện' ?></button>
                    </form>
                    <form method="post" action="<?= url('/questions/' . (int) $q['id'] . '/delete') ?>"
                          data-confirm="Bạn có chắc muốn xóa câu hỏi #<?= (int) $q['id'] ?>? Thao tác này không thể hoàn tác.">
                        <?= csrf_field() ?><?= $ctx ?>
                        <button class="btn btn-sm btn-danger" type="submit">Xóa</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?>
            <tr><td colspan="8" class="empty">
                <?php if ($hasFilter): ?>Không có câu hỏi nào phù hợp với bộ lọc.
                <?php elseif (!$subjects): ?>Bạn chưa được phân công môn học nào nên chưa thể soạn câu hỏi. Vui lòng liên hệ quản trị viên.
                <?php else: ?>Ngân hàng câu hỏi còn trống. Hãy bấm "+ Thêm câu hỏi" để bắt đầu.<?php endif; ?>
            </td></tr>
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
