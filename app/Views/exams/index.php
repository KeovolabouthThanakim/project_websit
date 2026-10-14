<?php
use App\Models\Exam;

$items = $result['items'];
$page = $result['page'];
$pages = $result['pages'];
$hasFilter = $f['q'] !== '' || $f['subject_id'] > 0 || $f['state'] !== '';

$ctx = '<input type="hidden" name="back_q" value="' . e($f['q']) . '">'
     . '<input type="hidden" name="back_subject_id" value="' . (int) $f['subject_id'] . '">'
     . '<input type="hidden" name="back_state" value="' . e($f['state']) . '">'
     . '<input type="hidden" name="back_page" value="' . (int) $page . '">';

$pageUrl = function (int $p) use ($f): string {
    $params = array_filter($f + ['page' => $p > 1 ? $p : ''], fn($v) => $v !== '' && $v !== 0);
    return url('/exams') . ($params ? '?' . http_build_query($params) : '');
};
?>
<div class="page-head">
    <div>
        <h1>Đề thi</h1>
        <p><?= $isAdmin ? 'Đề thi của tất cả các môn học' : 'Đề thi của các môn bạn được phân công' ?></p>
    </div>
    <?php if ($subjects): ?><a class="btn" href="<?= url('/exams/create' . ($f['subject_id'] > 0 ? '?subject_id=' . (int) $f['subject_id'] : '')) ?>">+ Tạo đề thi</a><?php endif; ?>
</div>

<form class="filter-bar" method="get" action="<?= url('/exams') ?>">
    <div class="grow">
        <label for="q">Tìm kiếm</label>
        <input type="search" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Tên đề thi">
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
        <label for="state">Trạng thái</label>
        <select id="state" name="state">
            <option value="">Tất cả</option>
            <?php foreach (Exam::STATES as $k => $label): ?>
                <option value="<?= e($k) ?>" <?= $f['state'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn" type="submit">Tìm kiếm</button>
    <?php if ($hasFilter): ?><a class="btn btn-outline" href="<?= url('/exams') ?>">Xóa bộ lọc</a><?php endif; ?>
</form>

<p class="muted">Tìm thấy <strong><?= (int) $result['total'] ?></strong> đề thi.</p>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr><th>STT</th><th>Tên đề thi</th><th>Môn học</th><th>Lớp thi</th><th>Thời gian thi</th><th>Số câu</th><th>Trạng thái</th><th>Thao tác</th></tr>
        </thead>
        <tbody>
        <?php foreach ($items as $i => $x): ?>
            <?php $st = Exam::state($x); ?>
            <tr>
                <td><?= ($page - 1) * $perPage + $i + 1 ?></td>
                <td class="q-cell">
                    <a href="<?= url('/exams/' . (int) $x['id']) ?>"><strong><?= e($x['title']) ?></strong></a>
                    <div class="muted" style="font-size:.82rem"><?= (int) $x['duration_minutes'] ?> phút<?= $x['author_name'] ? ' · ' . e($x['author_name']) : '' ?></div>
                </td>
                <td><span class="tag" title="<?= e($x['subject_name']) ?>"><?= e($x['subject_code']) ?></span></td>
                <td><?php if ($x['class_codes']): foreach (explode(', ', $x['class_codes']) as $cc): ?><span class="tag"><?= e($cc) ?></span><?php endforeach; else: ?><span class="muted">Chưa chọn lớp</span><?php endif; ?></td>
                <td>
                    <?= e(fmt_date($x['start_at'])) ?><br>
                    <span class="muted">đến <?= e(fmt_date($x['end_at'])) ?></span>
                </td>
                <td><?= (int) $x['question_count'] ?></td>
                <td><span class="badge badge-st-<?= e($st) ?>"><?= e(Exam::STATES[$st]) ?></span></td>
                <td class="actions">
                    <a class="btn btn-sm" href="<?= url('/exams/' . (int) $x['id']) ?>">Xem</a>
                    <?php if ($x['status'] !== 'draft'): ?><a class="btn btn-sm btn-outline" href="<?= url('/exams/' . (int) $x['id'] . '/monitor') ?>">Theo dõi</a> <a class="btn btn-sm btn-outline" href="<?= url('/exams/' . (int) $x['id'] . '/results') ?>">Kết quả</a><?php endif; ?>
                    <a class="btn btn-sm btn-outline" href="<?= url('/exams/' . (int) $x['id'] . '/edit') ?>">Sửa</a>
                    <?php if ($x['status'] === 'draft'): ?>
                    <form method="post" action="<?= url('/exams/' . (int) $x['id'] . '/delete') ?>"
                          data-confirm="Bạn có chắc muốn xóa đề thi &quot;<?= e($x['title']) ?>&quot;? Thao tác này không thể hoàn tác.">
                        <?= csrf_field() ?><?= $ctx ?>
                        <button class="btn btn-sm btn-danger" type="submit">Xóa</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?>
            <tr><td colspan="8" class="empty">
                <?php if ($hasFilter): ?>Không có đề thi nào phù hợp với bộ lọc.
                <?php elseif (!$subjects): ?>Bạn chưa được phân công môn học nào nên chưa thể tạo đề thi. Vui lòng liên hệ quản trị viên.
                <?php else: ?>Chưa có đề thi nào. Hãy bấm "+ Tạo đề thi" để bắt đầu.<?php endif; ?>
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
