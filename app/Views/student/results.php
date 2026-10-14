<?php
use App\Models\StudentStats;

$st = $stats;
$qs = $subject !== '' ? '?subject=' . rawurlencode($subject) : '';
$hasAvg = function (array $pts): bool {
    foreach ($pts as $p) {
        if ($p['avg'] !== null) {
            return true;
        }
    }
    return false;
};
// ▲ tăng / ▼ giảm / = bằng điểm bài trước cùng môn
$trend = function (?float $d): string {
    if ($d === null) {
        return '<span class="muted">-</span>';
    }
    if (abs($d) < 0.005) {
        return '<span class="trend">= 0</span>';
    }
    return '<span class="trend ' . ($d > 0 ? 'up' : 'down') . '">' . ($d > 0 ? '&#9650; ' : '&#9660; ') . e(StudentStats::signed($d)) . '</span>';
};
?>
<div class="page-head">
    <div>
        <h1>Kết quả của tôi</h1>
        <p>Toàn bộ bài thi đã nộp, nhóm theo môn học, kèm xu hướng điểm.</p>
    </div>
    <div class="header-actions">
        <?php if ($st['count']): ?><a class="btn btn-outline" href="<?= url('/my-results/print' . $qs) ?>" target="_blank" rel="noopener">In / Lưu PDF</a><?php endif; ?>
        <a class="btn btn-outline" href="<?= url('/my-exams') ?>">Kỳ thi của tôi</a>
    </div>
</div>

<?php if (!$hasClass): ?>
    <section class="panel"><div class="panel-body"><p class="muted" style="margin:0">Bạn chưa được xếp vào lớp nào nên chưa có kỳ thi. Vui lòng liên hệ giáo viên hoặc quản trị viên.</p></div></section>
<?php elseif (!$subjects): ?>
    <section class="panel"><div class="panel-body"><p class="muted" style="margin:0">Bạn chưa nộp bài thi nào. Kết quả sẽ xuất hiện ở đây sau bài thi đầu tiên. <a href="<?= url('/my-schedule') ?>">Xem lịch thi &raquo;</a></p></div></section>
<?php else: ?>

<form class="filter-bar" method="get" action="<?= url('/my-results') ?>">
    <div>
        <label for="subject">Môn học</label>
        <select id="subject" name="subject" onchange="this.form.submit()">
            <option value="">Tất cả các môn (<?= count($subjects) ?>)</option>
            <?php foreach ($subjects as $code => $name): ?>
                <option value="<?= e($code) ?>" <?= $subject === $code ? 'selected' : '' ?>><?= e($code . ' - ' . $name) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn-outline" type="submit">Lọc</button>
</form>

<div class="stats">
    <div class="stat"><span class="stat-num"><?= $st['avg'] !== null ? e(fmt_score(round($st['avg'], 2))) : '-' ?></span><span class="stat-label">Điểm trung bình</span></div>
    <div class="stat"><span class="stat-num"><?= (int) $st['count'] ?></span><span class="stat-label">Bài đã nộp</span></div>
    <div class="stat"><span class="stat-num"><?= $st['best'] !== null ? e(fmt_score($st['best'])) : '-' ?></span><span class="stat-label">Điểm cao nhất</span></div>
    <div class="stat"><span class="stat-num"><?= (int) $st['passed'] ?>/<?= (int) $st['count'] ?></span><span class="stat-label">Bài đạt (từ <?= e(fmt_score($pass)) ?> điểm)</span></div>
</div>

<?php if ($subject === '' && count($groups) > 1 && count($chart) > 1): ?>
<section class="panel">
    <h2 class="panel-title">Tiến bộ qua các kỳ thi (tất cả các môn)</h2>
    <div class="panel-body">
        <div class="dc-legend">
            <span><i class="dc-key"></i> Điểm của bạn</span>
            <?php if ($hasAvg($chart)): ?><span><i class="dc-key avg"></i> TB lớp</span><?php endif; ?>
            <span><i class="dc-key pass"></i> Mức đạt (<?= e(fmt_score($pass)) ?>)</span>
        </div>
        <div class="dc-wrap"><?= StudentStats::chartSvg($chart) ?></div>
        <?php if (count($chart) >= StudentStats::HISTORY_CHART): ?><p class="hint" style="margin:10px 0 0">Biểu đồ hiển thị <?= (int) StudentStats::HISTORY_CHART ?> bài gần nhất.</p><?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php foreach ($groups as $g): ?>
<section class="panel">
    <h2 class="panel-title"><?= e($g['code']) ?> - <?= e($g['name']) ?> <span class="muted" style="font-weight:500">· <?= (int) $g['count'] ?> bài</span></h2>

    <div class="hist-sum">
        <div><span class="hist-sum-num"><?= e(fmt_score(round($g['avg'], 2))) ?></span><span class="hist-sum-label">Điểm trung bình</span></div>
        <div><span class="hist-sum-num"><?= e(fmt_score($g['best'])) ?></span><span class="hist-sum-label">Cao nhất</span></div>
        <div><span class="hist-sum-num"><?= e(fmt_score($g['last'])) ?></span><span class="hist-sum-label">Gần nhất <?= $g['delta'] !== null ? $trend($g['delta']) : '' ?></span></div>
        <div><span class="hist-sum-num"><?= (int) $g['passed'] ?>/<?= (int) $g['count'] ?></span><span class="hist-sum-label">Bài đạt</span></div>
    </div>

    <?php if ($g['count'] >= 2): ?>
    <div class="panel-body" style="border-top:1px solid var(--line)">
        <div class="dc-legend">
            <span><i class="dc-key"></i> Điểm của bạn</span>
            <?php if ($hasAvg($g['chart'])): ?><span><i class="dc-key avg"></i> TB lớp</span><?php endif; ?>
            <span><i class="dc-key pass"></i> Mức đạt (<?= e(fmt_score($pass)) ?>)</span>
        </div>
        <div class="dc-wrap"><?= StudentStats::chartSvg($g['chart']) ?></div>
    </div>
    <?php endif; ?>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Kỳ thi</th><th>Nộp lúc</th><th>Điểm</th><th>Thời gian làm</th><th>TB lớp</th><th>So với bài trước</th><th></th></tr></thead>
            <tbody>
            <?php foreach (array_reverse($g['items']) as $x): ?>
                <tr>
                    <td data-label="Kỳ thi" class="q-cell" style="min-width:150px"><strong><?= e($x['title']) ?></strong></td>
                    <td data-label="Nộp lúc" style="white-space:nowrap"><?= e(fmt_date($x['submitted_at'])) ?><?= $x['auto_submitted'] ? '<br><span class="badge badge-off" title="Hết giờ, hệ thống tự nộp">Tự nộp</span>' : '' ?></td>
                    <td data-label="Điểm"><span class="badge <?= $x['score'] >= $pass ? 'badge-on' : 'badge-off' ?>"><?= e(fmt_score($x['score'])) ?></span></td>
                    <td data-label="Thời gian làm" style="white-space:nowrap"><?= $x['spent'] !== null ? e(fmt_duration((int) $x['spent'])) . ' / ' . (int) $x['duration_minutes'] . ' phút' : '<span class="muted">-</span>' ?></td>
                    <td data-label="TB lớp"><?= $x['class_avg'] !== null ? e(fmt_score(round($x['class_avg'], 2))) : '<span class="muted">-</span>' ?></td>
                    <td data-label="So với bài trước"><?= $trend($x['delta']) ?></td>
                    <td class="actions" style="white-space:nowrap">
                        <a class="btn btn-sm btn-outline" href="<?= url('/attempts/' . (int) $x['attempt_id'] . '/result') ?>">Xem</a>
                        <a class="btn btn-sm btn-outline" href="<?= url('/attempts/' . (int) $x['attempt_id'] . '/print') ?>" target="_blank" rel="noopener" title="In phiếu kết quả / Lưu PDF">In</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endforeach; ?>

<p class="hint">Điểm trung bình lớp chỉ hiện sau khi kỳ thi kết thúc và có từ <?= (int) StudentStats::MIN_PEERS ?> bài nộp. "So với bài trước" là chênh lệch điểm so với bài thi liền trước của cùng môn.</p>
<?php endif; ?>
