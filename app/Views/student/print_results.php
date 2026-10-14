<?php
// Bảng điểm cá nhân để in / lưu PDF (các bài đã nộp, nhóm theo môn)
$all = [];
foreach ($groups as $g) {
    foreach ($g['items'] as $x) {
        $all[] = $x;
    }
}
$n = 0;
?>
<header class="p-head">
    <div class="p-org"><strong><?= e(school_name()) ?></strong><span><?= e(system_name()) ?></span></div>
    <div class="p-meta">Ngày in: <?= e(date('d/m/Y H:i')) ?></div>
</header>

<h1 class="p-title">Bảng điểm cá nhân</h1>
<p class="p-sub"><?= $subject !== '' ? 'Môn ' . e($subject . ' - ' . ($subjects[$subject] ?? '')) : 'Tất cả các môn' ?></p>

<dl class="p-info">
    <dt>Họ và tên</dt><dd><?= e($user['full_name']) ?></dd>
    <dt>Mã sinh viên</dt><dd><?= e(sv_code($user)) ?></dd>
    <dt>Lớp</dt><dd><?= $class ? e($class['code'] . ' - ' . $class['name']) : '-' ?></dd>
    <dt>Thang điểm</dt><dd>10 &middot; đạt từ <?= e(fmt_score($pass)) ?></dd>
</dl>

<?php if (!$groups): ?>
    <p class="muted">Chưa có bài thi nào được nộp.</p>
<?php else: ?>
<table class="p-table">
    <thead><tr><th style="width:9mm">STT</th><th>Kỳ thi</th><th style="width:34mm">Nộp lúc</th><th style="width:16mm">Điểm</th><th style="width:22mm">Kết quả</th></tr></thead>
    <tbody>
    <?php foreach ($groups as $g): ?>
        <tr class="sum"><td colspan="5"><?= e($g['code'] . ' - ' . $g['name']) ?></td></tr>
        <?php foreach ($g['items'] as $x): ?>
            <tr>
                <td class="c"><?= ++$n ?></td>
                <td><?= e($x['title']) ?></td>
                <td class="c"><?= e(fmt_date($x['submitted_at'])) ?></td>
                <td class="c"><strong><?= e(fmt_score($x['score'])) ?></strong></td>
                <td class="c"><?= $x['score'] >= $pass ? 'Đạt' : '<span class="low">Chưa đạt</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        <tr>
            <td colspan="3" class="r">Điểm trung bình môn <?= e($g['code']) ?> (<?= (int) $g['count'] ?> bài)</td>
            <td class="c"><strong><?= e(fmt_score(round($g['avg'], 2))) ?></strong></td>
            <td class="c"><?= (int) $g['passed'] ?>/<?= (int) $g['count'] ?> đạt</td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<p class="p-sum">
    <span>Số bài đã nộp: <strong><?= (int) $stats['count'] ?></strong></span>
    <span>Điểm trung bình các bài thi: <strong><?= e(fmt_score(round($stats['avg'], 2))) ?></strong></span>
    <span>Cao nhất: <strong><?= e(fmt_score($stats['best'])) ?></strong></span>
    <span>Bài đạt: <strong><?= (int) $stats['passed'] ?>/<?= (int) $stats['count'] ?></strong></span>
</p>
<?php endif; ?>

<p class="p-foot">Bảng điểm in từ <?= e(system_name()) ?> lúc <?= e(date('H:i d/m/Y')) ?>, gồm các bài thi trắc nghiệm trực tuyến đã nộp. Điểm trung bình ở đây là trung bình cộng các bài thi, không phải điểm học phần. Mọi thắc mắc vui lòng liên hệ giảng viên phụ trách môn học.</p>
