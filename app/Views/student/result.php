<?php
$letters = ['A', 'B', 'C', 'D', 'E', 'F'];
$total = (int) $attempt['total_questions'];
$spent = max(0, strtotime($attempt['submitted_at']) - strtotime($attempt['started_at']));
$score = (float) $attempt['score'];
$grade = $score >= 8 ? 'good' : ($score >= 5 ? 'ok' : 'low');
?>
<div class="page-head">
    <div>
        <h1>Kết quả: <?= e($attempt['title']) ?></h1>
        <p>Môn <?= e($attempt['subject_code']) ?> - <?= e($attempt['subject_name']) ?></p>
    </div>
    <div class="header-actions">
        <a class="btn btn-outline" href="<?= url('/attempts/' . (int) $attempt['id'] . '/print') ?>" target="_blank" rel="noopener">In phiếu / Lưu PDF</a>
        <a class="btn btn-outline" href="<?= url('/my-results') ?>">Tất cả kết quả</a>
        <a class="btn btn-outline" href="<?= url('/my-exams') ?>">&laquo; Kỳ thi của tôi</a>
    </div>
</div>

<section class="panel">
    <div class="panel-body score-wrap">
        <div class="score-big score-<?= $grade ?>"><span><?= e(fmt_score($attempt['score'])) ?></span><small>/ 10 điểm</small></div>
        <dl class="info score-info">
            <dt>Số câu</dt><dd><?= $total ?> câu</dd>
            <dt>Làm đúng hoàn toàn</dt><dd><?= (int) $full ?> câu</dd>
            <?php if ($partialOn): ?><dt>Đúng một phần</dt><dd><?= (int) $partial ?> câu</dd><?php endif; ?>
            <dt>Tổng điểm các câu</dt><dd><?= e(fmt_score($attempt['earned'])) ?> / <?= $total ?></dd>
            <dt>Thời gian làm bài</dt><dd><?= e(fmt_duration($spent)) ?> / <?= (int) $attempt['duration_minutes'] ?> phút</dd>
            <dt>Nộp lúc</dt><dd><?= e(fmt_date($attempt['submitted_at'])) ?><?= $attempt['auto_submitted'] ? ' <span class="badge badge-off">Hết giờ, tự nộp</span>' : '' ?></dd>
        </dl>
    </div>
</section>

<?php if (!$ended): ?>
<section class="panel">
    <div class="panel-body">
        <strong>Đáp án đúng và giải thích sẽ hiện sau khi kỳ thi kết thúc</strong>
        <p class="muted" style="margin:6px 0 0">Kỳ thi còn mở đến <?= e(fmt_date($attempt['end_at'])) ?> nên các bạn khác có thể đang làm bài. Bạn quay lại trang này sau thời điểm đó để xem chi tiết từng câu.</p>
    </div>
</section>
<?php else: ?>
<h2 style="margin:28px 0 14px">Chi tiết từng câu</h2>
<?php foreach ($paper as $n => $q): ?>
    <?php
    $earned = $q['earned'] ?? 0.0;
    $verdict = $earned >= 1.0 ? ['badge-on', 'Đúng'] : ($earned > 0 ? ['badge-lv-medium', 'Đúng một phần'] : ['badge-off', 'Sai / bỏ trống']);
    ?>
    <section class="panel">
        <h2 class="panel-title">Câu <?= $n + 1 ?> <span class="badge <?= $verdict[0] ?>"><?= $verdict[1] ?></span>
            <?php if ($q['multiple']): ?><span class="badge badge-st-draft">Nhiều đáp án</span><?php endif; ?>
            <span class="muted" style="float:right;font-weight:500;font-size:.85rem"><?= e(fmt_score($earned)) ?> điểm</span></h2>
        <div class="panel-body">
            <p class="q-content"><?= e($q['content']) ?></p>
            <?php if ($q['image']): ?>
                <img class="q-image" src="<?= url('/attempts/' . (int) $attempt['id'] . '/images/' . e($q['image'])) ?>" alt="Ảnh minh họa câu hỏi">
            <?php endif; ?>
            <ol class="opt-list">
                <?php foreach ($q['options'] as $i => $o): ?>
                    <?php
                    $cls = $o['is_correct'] ? 'correct' : ($o['selected'] ? 'wrong' : '');
                    ?>
                    <li class="<?= $cls ?>">
                        <span class="opt-letter"><?= $letters[$i] ?? $i + 1 ?></span>
                        <span class="opt-text"><?= e($o['content']) ?></span>
                        <?php if ($o['selected']): ?><span class="badge badge-st-draft">Bạn chọn</span><?php endif; ?>
                        <?php if ($o['is_correct']): ?><span class="badge badge-on">Đáp án đúng</span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php if (!empty($q['explanation'])): ?>
                <h3 class="sub-title">Giải thích</h3>
                <p class="q-content" style="margin-bottom:0"><?= e($q['explanation']) ?></p>
            <?php endif; ?>
        </div>
    </section>
<?php endforeach; ?>
<?php endif; ?>
