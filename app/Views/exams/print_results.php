<?php
// Bảng điểm lớp để in / lưu PDF
$stateText = ['absent' => 'Chưa thi', 'working' => 'Đang làm bài', 'done' => ''];
$codes = implode(', ', array_column($classes, 'code'));
?>
<header class="p-head">
    <div class="p-org"><strong><?= e(school_name()) ?></strong><span><?= e(system_name()) ?></span></div>
    <div class="p-meta">Ngày in: <?= e(date('d/m/Y H:i')) ?></div>
</header>

<h1 class="p-title">Bảng điểm kỳ thi</h1>
<p class="p-sub"><?= e($exam['title']) ?></p>

<dl class="p-info">
    <dt>Môn học</dt><dd><?= e($exam['subject_code'] . ' - ' . $exam['subject_name']) ?></dd>
    <dt>Lớp</dt><dd><?= e($codes !== '' ? $codes : '-') ?></dd>
    <dt>Cổng thi mở</dt><dd><?= e(fmt_date($exam['start_at'])) ?> &ndash; <?= e(fmt_date($exam['end_at'])) ?></dd>
    <dt>Thời gian làm bài</dt><dd><?= (int) $exam['duration_minutes'] ?> phút &middot; thang điểm 10, đạt từ <?= e(fmt_score($pass)) ?></dd>
</dl>

<table class="p-table">
    <thead><tr><th style="width:9mm">STT</th><th style="width:26mm">Mã SV</th><th>Họ và tên</th><th style="width:22mm">Lớp</th><th style="width:16mm">Điểm</th><th style="width:20mm">Đúng / tổng</th><th style="width:20mm">Thời gian</th><th style="width:24mm">Ghi chú</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $r): ?>
        <?php
        $st = \App\Models\Result::rowState($r);
        $spent = \App\Models\Result::secondsSpent($r);
        $note = $stateText[$st];
        if ($st === 'done' && $r['auto_submitted']) {
            $note = 'Tự nộp (hết giờ)';
        }
        ?>
        <tr>
            <td class="c"><?= $i + 1 ?></td>
            <td><?= e(sv_code($r)) ?></td>
            <td><?= e($r['full_name']) ?></td>
            <td class="c"><?= e((string) ($r['class_code'] ?? '')) ?></td>
            <td class="c"><?= $st === 'done' ? '<strong>' . e(fmt_score($r['score'])) . '</strong>' : '' ?></td>
            <td class="c"><?= $st === 'done' ? (int) $r['full_correct'] . ' / ' . (int) $r['total_questions'] : '' ?></td>
            <td class="c"><?= $spent === null ? '' : e(fmt_duration($spent)) ?></td>
            <td><?= e($note) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" class="c muted">Chưa có sinh viên nào trong các lớp được thi.</td></tr><?php endif; ?>
    </tbody>
</table>

<p class="p-sum">
    <span>Tổng số sinh viên: <strong><?= (int) $summary['total'] ?></strong></span>
    <span>Đã nộp: <strong><?= (int) $summary['submitted'] ?></strong></span>
    <span>Chưa thi: <strong><?= (int) $summary['absent'] + (int) $summary['working'] ?></strong></span>
    <?php if ($summary['submitted']): ?>
    <span>Điểm TB: <strong><?= e(fmt_score($summary['avg'])) ?></strong></span>
    <span>Cao nhất: <strong><?= e(fmt_score($summary['max'])) ?></strong></span>
    <span>Thấp nhất: <strong><?= e(fmt_score($summary['min'])) ?></strong></span>
    <span>Đạt: <strong><?= (int) $summary['passed'] ?>/<?= (int) $summary['submitted'] ?></strong> (<?= e(number_format($summary['pass_rate'] * 100, 0)) ?>%)</span>
    <?php endif; ?>
</p>

<div class="p-sign">
    <div>
        <em>Ngày ..... tháng ..... năm .........</em>
        <strong>Giảng viên</strong>
        <em>(Ký và ghi rõ họ tên)</em>
        <div class="space"></div>
        <strong><?= e($user['full_name'] ?? '') ?></strong>
    </div>
</div>

<p class="p-foot">Bảng điểm được in từ <?= e(system_name()) ?> lúc <?= e(date('H:i d/m/Y')) ?> bởi <?= e($user['full_name'] ?? '') ?>. Dữ liệu có giá trị tại thời điểm in.</p>
