<?php
use App\Models\Question;

$letters = ['A', 'B', 'C', 'D', 'E', 'F'];
$label = ['ok' => ['badge-on', 'Hợp lệ'], 'dup' => ['badge-st-upcoming', 'Trùng - bỏ qua'], 'error' => ['badge-off', 'Có lỗi']];
?>
<div class="page-head">
    <div>
        <h1>Xem trước khi nhập</h1>
        <p>Môn <?= e($subject['code']) ?> - <?= e($subject['name']) ?> · nguồn: <?= e($source) ?></p>
    </div>
    <a class="btn btn-outline" href="<?= url('/questions/import?subject_id=' . (int) $subject['id']) ?>">&laquo; Chọn file khác</a>
</div>

<div class="stats">
    <div class="stat"><span class="stat-num"><?= $count['ok'] ?></span><span class="stat-label">Câu hợp lệ, sẽ được nhập</span></div>
    <div class="stat <?= $count['error'] > 0 ? 'warn' : '' ?>"><span class="stat-num"><?= $count['error'] ?></span><span class="stat-label">Câu có lỗi, sẽ bị bỏ qua</span></div>
    <div class="stat"><span class="stat-num"><?= $count['dup'] ?></span><span class="stat-label">Câu trùng, sẽ bị bỏ qua</span></div>
</div>

<?php if ($count['ok'] > 0): ?>
<section class="panel">
    <div class="panel-body">
        <form method="post" action="<?= url('/questions/import/confirm') ?>" data-confirm="Nhập <?= (int) $count['ok'] ?> câu hỏi hợp lệ vào ngân hàng câu hỏi môn <?= e($subject['code']) ?>?">
            <?= csrf_field() ?>
            <button class="btn" type="submit">Nhập <?= (int) $count['ok'] ?> câu hợp lệ</button>
            <?php if ($count['error'] + $count['dup'] > 0): ?><span class="muted" style="margin-left:10px">Các câu lỗi / trùng không được nhập; sửa file rồi tải lại nếu muốn nhập thêm.</span><?php endif; ?>
        </form>
    </div>
</section>
<?php else: ?>
<section class="panel"><div class="panel-body"><p style="margin:0">Không có câu hỏi hợp lệ nào để nhập. Hãy sửa các lỗi bên dưới rồi tải file lại.</p></div></section>
<?php endif; ?>

<section class="panel">
    <h2 class="panel-title">Chi tiết (<?= count($rows) ?> câu)</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Dòng / câu</th><th>Nội dung và đáp án</th><th>Độ khó</th><th>Kết quả</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr class="<?= $r['status'] === 'error' ? 'import-bad' : '' ?>">
                    <td data-label="Dòng"><?= (int) $r['line'] ?></td>
                    <td class="q-cell" data-label="Nội dung">
                        <strong><?= e(str_cut($r['content'], 300)) ?><?= str_len($r['content']) > 300 ? '…' : '' ?></strong>
                        <?php foreach ($r['options'] as $i => $o): ?>
                            <div class="<?= $o['is_correct'] ? 'import-ok' : 'muted' ?>"><?= e($letters[$i] ?? '?') ?>. <?= e(str_cut($o['content'], 200)) ?><?= $o['is_correct'] ? ' ✓' : '' ?></div>
                        <?php endforeach; ?>
                        <?php if ($r['explanation']): ?><div class="muted" style="font-size:.85rem">Giải thích: <?= e(str_cut($r['explanation'], 200)) ?></div><?php endif; ?>
                    </td>
                    <td data-label="Độ khó"><?= $r['difficulty'] ? '<span class="badge badge-lv-' . e($r['difficulty']) . '">' . e(Question::DIFFICULTIES[$r['difficulty']] ?? '') . '</span>' : '-' ?></td>
                    <td data-label="Kết quả">
                        <span class="badge <?= $label[$r['status']][0] ?>"><?= $label[$r['status']][1] ?></span>
                        <?php foreach ($r['errors'] as $err): ?><div class="text-warn" style="font-size:.85rem;margin-top:4px"><?= e($err) ?></div><?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
