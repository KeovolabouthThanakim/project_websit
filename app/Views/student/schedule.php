<?php
use App\Models\Exam;

$kindLabel = [
    'running'  => ['badge-lv-medium', 'Đang làm dở'],
    'open'     => ['badge-st-open', 'Đang mở'],
    'upcoming' => ['badge-st-upcoming', 'Sắp diễn ra'],
    'done'     => ['badge-on', 'Đã nộp'],
    'missed'   => ['badge-off', 'Đã bỏ lỡ'],
];
$timeRange = function (array $x): string {
    return date('H:i', $x['start_ts']) . ' - ' . (date('d/m', $x['end_ts']) === date('d/m', $x['start_ts']) ? date('H:i', $x['end_ts']) : date('H:i d/m', $x['end_ts']));
};
$link = function (array $x): string {
    return url($x['kind'] === 'running' ? '/attempts/' . (int) $x['attempt_id'] : '/my-exams/' . (int) $x['id']);
};
$dow = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];

// Danh sách kỳ thi trong tháng (mỗi kỳ thi một dòng, kể cả kỳ thi kéo dài sang nhiều ngày)
$monthList = [];
foreach ($weeks as $w) {
    foreach ($w as $cell) {
        foreach ($cell['items'] ?? [] as $it) {
            $monthList[(int) $it['x']['id']] = $it['x'];
        }
    }
}
uasort($monthList, fn($a, $b) => [$a['start_ts'], $a['id']] <=> [$b['start_ts'], $b['id']]);

$hero = $agenda[0] ?? null;
?>
<div class="page-head">
    <div>
        <h1>Lịch thi</h1>
        <p>Các kỳ thi của lớp bạn: kỳ thi sắp tới, đang mở và đã diễn ra.</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-outline" href="<?= url('/my-exams') ?>">Kỳ thi của tôi</a>
        <a class="btn btn-outline" href="<?= url('/my-results') ?>">Kết quả của tôi</a>
    </div>
</div>

<?php if (!$hasClass): ?>
    <section class="panel"><div class="panel-body"><p class="muted" style="margin:0">Bạn chưa được xếp vào lớp nào nên chưa có lịch thi. Vui lòng liên hệ giáo viên hoặc quản trị viên.</p></div></section>
<?php else: ?>

<?php if ($hero): ?>
    <?php $urgent = $hero['kind'] === 'running'; ?>
    <div class="hero<?= $urgent ? ' urgent' : '' ?>" role="status">
        <div>
            <div class="hero-kicker"><?= $hero['kind'] === 'running' ? 'Bạn đang làm dở một bài thi' : ($hero['kind'] === 'open' ? 'Kỳ thi đang mở cho lớp bạn' : 'Kỳ thi sắp tới') ?></div>
            <div class="hero-title"><?= e($hero['title']) ?> <span class="muted">· <?= e($hero['subject_code']) ?></span></div>
            <div class="hero-sub">
                <?php if ($hero['kind'] === 'running'): ?>
                    Thời gian còn lại: <span class="cd cd-big" data-to="<?= (int) strtotime((string) $hero['deadline_at']) ?>">--:--</span>
                <?php elseif ($hero['kind'] === 'open'): ?>
                    Cổng thi đóng sau <span class="cd cd-big" data-to="<?= (int) $hero['end_ts'] ?>">--:--</span> · làm bài <?= (int) $hero['duration_minutes'] ?> phút · <?= (int) $hero['question_count'] ?> câu
                <?php else: ?>
                    Mở cổng sau <span class="cd cd-big" data-to="<?= (int) $hero['start_ts'] ?>">--:--</span> · <?= e(fmt_day($hero['start_ts'])) ?>, <?= e($timeRange($hero)) ?> · làm bài <?= (int) $hero['duration_minutes'] ?> phút
                <?php endif; ?>
            </div>
        </div>
        <a class="btn" href="<?= $link($hero) ?>"><?= $hero['kind'] === 'running' ? 'Tiếp tục làm bài' : ($hero['kind'] === 'open' ? 'Vào thi' : 'Chi tiết') ?></a>
    </div>
<?php endif; ?>

<div class="stats">
    <div class="stat<?= $counts['running'] + $counts['open'] > 0 ? ' warn' : '' ?>"><span class="stat-num"><?= (int) ($counts['running'] + $counts['open']) ?></span><span class="stat-label">Cần làm ngay (đang mở / làm dở)</span></div>
    <div class="stat"><span class="stat-num"><?= (int) $counts['upcoming'] ?></span><span class="stat-label">Kỳ thi sắp diễn ra</span></div>
    <div class="stat"><span class="stat-num"><?= (int) $counts['missed'] ?></span><span class="stat-label">Đã bỏ lỡ (không làm bài)</span></div>
</div>

<div class="two-col">
    <section class="panel">
        <div class="cal-head">
            <a class="btn btn-sm btn-outline" href="<?= url('/my-schedule?month=' . $prev) ?>" aria-label="Tháng trước">&laquo;</a>
            <h2><?= e($label) ?></h2>
            <a class="btn btn-sm btn-outline" href="<?= url('/my-schedule?month=' . $next) ?>" aria-label="Tháng sau">&raquo;</a>
            <?php if (!$isCurrent): ?><a class="btn btn-sm" href="<?= url('/my-schedule') ?>">Hôm nay</a><?php endif; ?>
        </div>
        <div class="cal-scroll">
            <table class="cal" aria-label="Lịch thi <?= e($label) ?>">
                <thead><tr><?php foreach ($dow as $d): ?><th scope="col"><?= $d ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach ($weeks as $w): ?>
                    <tr>
                    <?php foreach ($w as $cell): ?>
                        <?php if ($cell === null): ?>
                            <td class="cal-empty"></td>
                        <?php else: ?>
                            <td class="<?= $cell['today'] ? 'cal-today' : '' ?>">
                                <span class="cal-day"><?= (int) $cell['day'] ?></span>
                                <?php foreach (array_slice($cell['items'], 0, 3) as $it): ?>
                                    <?php $x = $it['x']; ?>
                                    <a class="cal-chip cal-<?= e($x['kind']) ?><?= $it['cont'] ? ' cal-cont' : '' ?>" href="<?= $link($x) ?>"
                                       title="<?= e($x['title'] . ' - ' . $x['subject_code'] . ' · ' . $timeRange($x) . ' (' . $kindLabel[$x['kind']][1] . ')') ?>">
                                        <span class="cal-t"><?= $it['cont'] ? '&hellip; ' : '' ?><?= e($x['subject_code']) ?></span>
                                    </a>
                                <?php endforeach; ?>
                                <?php if (count($cell['items']) > 3): ?><span class="cal-more">+<?= count($cell['items']) - 3 ?></span><?php endif; ?>
                            </td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="cal-legend">
            <span><i class="cal-key cal-open"></i> Đang mở</span>
            <span><i class="cal-key cal-upcoming"></i> Sắp diễn ra</span>
            <span><i class="cal-key cal-done"></i> Đã nộp</span>
            <span><i class="cal-key cal-missed"></i> Đã bỏ lỡ</span>
            <span><i class="cal-key cal-running"></i> Đang làm dở</span>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Việc cần làm</h2>
        <div class="panel-body">
            <?php if ($agenda): ?>
            <ul class="plain agenda">
                <?php foreach ($agenda as $x): ?>
                    <?php
                    $to = $x['kind'] === 'running' ? strtotime((string) $x['deadline_at']) : ($x['kind'] === 'open' ? $x['end_ts'] : $x['start_ts']);
                    $when = $x['kind'] === 'running' ? 'còn' : ($x['kind'] === 'open' ? 'đóng cổng sau' : 'mở sau');
                    ?>
                    <li>
                        <span>
                            <a href="<?= $link($x) ?>"><strong><?= e($x['title']) ?></strong></a><br>
                            <span class="muted" style="font-size:.82rem"><?= e($x['subject_code']) ?> · <?= e(fmt_day($x['start_ts'])) ?>, <?= e($timeRange($x)) ?> · <?= (int) $x['duration_minutes'] ?> phút<br>
                                <?= $when ?> <span class="cd" data-to="<?= (int) $to ?>">--:--</span></span>
                        </span>
                        <span class="badge <?= $kindLabel[$x['kind']][0] ?>"><?= e($kindLabel[$x['kind']][1]) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
                <p class="muted" style="margin:0">Hiện không có kỳ thi nào đang mở hoặc sắp diễn ra.</p>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="panel">
    <h2 class="panel-title">Danh sách kỳ thi - <?= e($label) ?> (<?= count($monthList) ?>)</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Cổng thi mở</th><th>Kỳ thi</th><th>Trạng thái</th><th>Điểm</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($monthList as $x): ?>
                <tr>
                    <td data-label="Cổng thi mở" style="white-space:nowrap"><?= e(fmt_day($x['start_ts'])) ?><div class="muted" style="font-size:.82rem"><?= e($timeRange($x)) ?></div></td>
                    <td data-label="Kỳ thi" class="q-cell" style="min-width:170px"><a href="<?= $link($x) ?>"><strong><?= e($x['title']) ?></strong></a><div class="muted" style="font-size:.82rem"><?= e($x['subject_code'] . ' - ' . $x['subject_name']) ?> · <?= (int) $x['duration_minutes'] ?> phút</div></td>
                    <td data-label="Trạng thái"><span class="badge <?= $kindLabel[$x['kind']][0] ?>"><?= e($kindLabel[$x['kind']][1]) ?></span></td>
                    <td data-label="Điểm"><?= $x['kind'] === 'done' ? '<strong>' . e(fmt_score($x['score'])) . '</strong>' : '<span class="muted">-</span>' ?></td>
                    <td class="actions" style="white-space:nowrap">
                        <?php if ($x['kind'] === 'done'): ?><a class="btn btn-sm btn-outline" href="<?= url('/attempts/' . (int) $x['attempt_id'] . '/result') ?>">Kết quả</a>
                        <?php elseif ($x['kind'] === 'running'): ?><a class="btn btn-sm" href="<?= $link($x) ?>">Tiếp tục</a>
                        <?php elseif ($x['kind'] === 'open'): ?><a class="btn btn-sm" href="<?= $link($x) ?>">Vào thi</a>
                        <?php else: ?><a class="btn btn-sm btn-outline" href="<?= $link($x) ?>">Chi tiết</a><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$monthList): ?><tr><td colspan="5" class="empty">Không có kỳ thi nào trong tháng này.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<p class="hint">Kỳ thi hiện ở ngày mở cổng (ô đậm); nếu cổng thi mở nhiều ngày thì các ngày sau đó hiện nhạt đến ngày đóng cổng. Giờ hiển thị là giờ máy chủ.</p>

<script>window.NAU_NOW = <?= (int) $serverNow ?>;</script>
<script src="<?= asset('js/countdown.js') ?>"></script>
<?php endif; ?>
