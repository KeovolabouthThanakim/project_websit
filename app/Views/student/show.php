<?php
use App\Models\Exam;

$examId = (int) $exam['id'];
$done = $attempt && $attempt['status'] === 'submitted';
$running = $attempt && $attempt['status'] === 'in_progress';
?>
<div class="page-head">
    <div>
        <h1><?= e($exam['title']) ?> <span class="badge badge-st-<?= e($state) ?>"><?= e(Exam::STATES[$state]) ?></span></h1>
        <p>Môn <?= e($exam['subject_code']) ?> - <?= e($exam['subject_name']) ?></p>
    </div>
    <div class="header-actions">
        <a class="btn btn-outline" href="<?= url('/my-exams') ?>">&laquo; Kỳ thi của tôi</a>
    </div>
</div>

<div class="two-col">
    <section class="panel">
        <h2 class="panel-title">Thông tin kỳ thi</h2>
        <div class="panel-body">
            <?php if ($exam['description']): ?><p class="q-content"><?= e($exam['description']) ?></p><?php endif; ?>
            <dl class="info">
                <dt>Số câu hỏi</dt><dd><?= (int) $exam['question_count'] ?> câu trắc nghiệm</dd>
                <dt>Thời gian làm bài</dt><dd><?= (int) $exam['duration_minutes'] ?> phút (tính từ lúc bạn bấm bắt đầu)</dd>
                <dt>Cổng thi mở</dt><dd><?= e(fmt_date($exam['start_at'])) ?></dd>
                <dt>Cổng thi đóng</dt><dd><?= e(fmt_date($exam['end_at'])) ?></dd>
                <dt>Số lần làm bài</dt><dd>1 lần</dd>
            </dl>

            <h3 class="sub-title">Lưu ý</h3>
            <ul class="rules">
                <li>Đồng hồ chạy ngay khi bạn bấm <strong>Bắt đầu làm bài</strong> và <strong>không dừng</strong> kể cả khi bạn đóng trình duyệt. Muốn làm tiếp, hãy quay lại trang này.</li>
                <li>Đáp án được tự động lưu mỗi khi bạn chọn. Hết giờ hệ thống tự nộp bài.</li>
                <li>Câu có nhiều đáp án đúng sẽ ghi rõ "Chọn nhiều đáp án". <?= \App\Models\Attempt::partialCredit() ? 'Chọn đúng một phần được điểm một phần, nhưng chọn sai sẽ bị trừ điểm của câu đó (thấp nhất 0 điểm).' : 'Phải chọn đúng và đủ các đáp án mới được điểm.' ?></li>
                <li>Thứ tự câu hỏi và đáp án <?= $exam['shuffle'] ? 'được xáo trộn riêng cho từng sinh viên.' : 'giống nhau cho mọi sinh viên.' ?></li>
                <?php if (\App\Models\AttemptEvent::enabled()): ?>
                <li>Trong lúc làm bài, hệ thống <strong>ghi nhận việc rời khỏi trang thi</strong> (chuyển tab, thu nhỏ trình duyệt, sao chép nội dung) và giáo viên có thể xem nhật ký này. Hãy ở lại trang thi cho đến khi nộp bài.</li>
                <?php endif; ?>
                <li>Điểm tính theo thang 10 và có ngay sau khi nộp. Đáp án đúng và giải thích hiện sau khi kỳ thi kết thúc.</li>
            </ul>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Bài làm của bạn</h2>
        <div class="panel-body">
            <?php if ($done): ?>
                <p class="muted" style="margin-top:0">Bạn đã nộp bài lúc <?= e(fmt_date($attempt['submitted_at'])) ?>.</p>
                <div class="score-mini"><?= e(fmt_score($attempt['score'])) ?><small>/10</small></div>
                <a class="btn btn-block" href="<?= url('/attempts/' . (int) $attempt['id'] . '/result') ?>">Xem kết quả</a>
            <?php elseif ($running): ?>
                <p style="margin-top:0">Bạn đang làm bài này. Đồng hồ vẫn đang chạy.</p>
                <a class="btn btn-block" href="<?= url('/attempts/' . (int) $attempt['id']) ?>">Tiếp tục làm bài</a>
            <?php elseif ($state === 'open' || $grant): ?>
                <?php $minutes = (int) $exam['duration_minutes'] + ($grant ? (int) $grant['extra_minutes'] : 0); ?>
                <?php if ($grant): ?>
                    <p class="muted" style="margin-top:0">Giáo viên đã cấp riêng cho bạn quyền vào thi<?= $state === 'open' ? '' : ' (dù cổng thi chưa mở hoặc đã đóng)' ?>, có hiệu lực đến <strong><?= e(fmt_date($grant['valid_until'])) ?></strong>.</p>
                <?php endif; ?>
                <p style="margin-top:0"><?= $grant ? '' : 'Cổng thi đang mở. ' ?>Bạn có <strong><?= $minutes ?> phút</strong> kể từ lúc bắt đầu<?= $grant && (int) $grant['extra_minutes'] > 0 ? ' (đã gồm ' . (int) $grant['extra_minutes'] . ' phút cộng thêm)' : '' ?>.</p>
                <form method="post" action="<?= url('/my-exams/' . $examId . '/start') ?>" data-confirm="Bắt đầu làm bài ngay bây giờ? Đồng hồ <?= $minutes ?> phút sẽ chạy và không thể dừng lại.">
                    <?= csrf_field() ?>
                    <button class="btn btn-block" type="submit">Bắt đầu làm bài</button>
                </form>
            <?php elseif ($state === 'upcoming'): ?>
                <p class="muted" style="margin:0">Cổng thi chưa mở. Bạn vào thi được từ <strong><?= e(fmt_date($exam['start_at'])) ?></strong>.</p>
            <?php else: ?>
                <p class="muted" style="margin:0">Kỳ thi đã kết thúc và bạn không có bài làm nào.</p>
            <?php endif; ?>
        </div>
    </section>
</div>
