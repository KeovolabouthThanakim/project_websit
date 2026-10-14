<?php
// Phiếu kết quả một bài thi để in / lưu PDF. Không có đáp án (đáp án chỉ hiện trên trang kết quả sau khi kỳ thi kết thúc).
$total = (int) $attempt['total_questions'];
$spent = max(0, strtotime($attempt['submitted_at']) - strtotime($attempt['started_at']));
$score = (float) $attempt['score'];
?>
<header class="p-head">
    <div class="p-org"><strong><?= e(school_name()) ?></strong><span><?= e(system_name()) ?></span></div>
    <div class="p-meta">Ngày in: <?= e(date('d/m/Y H:i')) ?></div>
</header>

<h1 class="p-title">Phiếu kết quả bài thi</h1>
<p class="p-sub"><?= e($attempt['title']) ?></p>

<dl class="p-info">
    <dt>Họ và tên</dt><dd><?= e($user['full_name']) ?></dd>
    <dt>Mã sinh viên</dt><dd><?= e(sv_code($user)) ?></dd>
    <dt>Lớp</dt><dd><?= $class ? e($class['code'] . ' - ' . $class['name']) : '-' ?></dd>
    <dt>Môn học</dt><dd><?= e($attempt['subject_code'] . ' - ' . $attempt['subject_name']) ?></dd>
    <dt>Bắt đầu làm bài</dt><dd><?= e(fmt_date($attempt['started_at'])) ?></dd>
    <dt>Nộp bài lúc</dt><dd><?= e(fmt_date($attempt['submitted_at'])) ?><?= $attempt['auto_submitted'] ? ' (hết giờ, hệ thống tự nộp)' : '' ?></dd>
    <dt>Thời gian làm bài</dt><dd><?= e(fmt_duration($spent)) ?> / <?= (int) $attempt['duration_minutes'] ?> phút</dd>
    <dt>Số câu</dt><dd><?= $total ?> câu &middot; làm đúng hoàn toàn <?= (int) $full ?> câu &middot; tổng điểm các câu <?= e(fmt_score($attempt['earned'])) ?> / <?= $total ?></dd>
</dl>

<div class="p-score">
    <strong><?= e(fmt_score($attempt['score'])) ?></strong>
    <span>/ 10 điểm</span>
    <span class="p-result"><?= $score >= $pass ? 'Đạt' : 'Chưa đạt' ?></span>
</div>

<p class="p-foot">Phiếu được in từ <?= e(system_name()) ?> lúc <?= e(date('H:i d/m/Y')) ?>. Mọi thắc mắc về điểm vui lòng liên hệ giảng viên phụ trách môn học.</p>
