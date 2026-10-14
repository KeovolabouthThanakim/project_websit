<?php
use App\Models\Exam;
use App\Models\Result;
use App\Models\StudentStats;

$st = $stats;
$cdTs = function ($x) {
    return $x['prio'] === 0 ? $x['deadline_at'] : ($x['prio'] === 1 ? $x['end_at'] : $x['start_at']);
};
?>
<div class="page-head">
    <div>
        <h1>Trang chủ sinh viên</h1>
        <p>Xin chào, <?= e($user['full_name']) ?></p>
    </div>
</div>

<?php if (empty($class)): ?>
<section class="panel"><div class="panel-body"><p class="muted" style="margin:0">Bạn chưa được xếp vào lớp nào nên chưa có môn học và kỳ thi. Vui lòng liên hệ giáo viên hoặc quản trị viên.</p></div></section>
<?php else: ?>

<?php if (!empty($hero)): ?>
    <?php $running = $hero['prio'] === 0; ?>
    <div class="hero<?= $running ? ' urgent' : '' ?>" role="status">
        <div>
            <div class="hero-kicker"><?= $running ? 'Bạn đang làm dở một bài thi' : 'Có kỳ thi đang mở cho lớp bạn' ?></div>
            <div class="hero-title"><?= e($hero['title']) ?> <span class="muted">· <?= e($hero['subject_code']) ?></span></div>
            <div class="hero-sub">
                <?php if ($running): ?>
                    Thời gian còn lại: <span class="cd cd-big" data-to="<?= (int) strtotime($hero['deadline_at']) ?>">--:--</span>
                <?php else: ?>
                    Cổng thi đóng sau <span class="cd" data-to="<?= (int) strtotime($hero['end_at']) ?>">--:--</span> · làm bài <?= (int) $hero['duration_minutes'] ?> phút · <?= (int) $hero['question_count'] ?> câu
                <?php endif; ?>
            </div>
        </div>
        <a class="btn" href="<?= url($running ? '/attempts/' . (int) $hero['attempt_id'] : '/my-exams/' . (int) $hero['id']) ?>"><?= $running ? 'Tiếp tục làm bài' : 'Vào thi' ?></a>
    </div>
<?php endif; ?>

<div class="stats">
    <div class="stat"><span class="stat-num"><?= $st['avg'] !== null ? e(fmt_score(round($st['avg'], 2))) : '-' ?></span><span class="stat-label">Điểm trung bình</span></div>
    <div class="stat"><span class="stat-num"><?= (int) $st['submitted'] ?></span><span class="stat-label">Bài đã nộp<?= $st['submitted'] ? ' (đạt ' . (int) $st['passed'] . ')' : '' ?></span></div>
    <div class="stat<?= $st['todo'] > 0 ? ' warn' : '' ?>"><span class="stat-num"><?= (int) $st['todo'] ?></span><span class="stat-label">Cần làm ngay</span></div>
    <div class="stat"><span class="stat-num"><?= (int) $st['upcoming'] ?></span><span class="stat-label">Kỳ thi sắp diễn ra</span></div>
    <div class="stat<?= ($compare && $compare['diff'] < 0) ? ' warn' : '' ?>" title="So sánh trên các kỳ thi đã kết thúc và đã có đủ bài nộp trong lớp">
        <span class="stat-num"><?= $compare ? e(StudentStats::signed($compare['diff'])) : '-' ?></span>
        <span class="stat-label"><?= $compare ? 'So với TB lớp (' . (int) $compare['exams'] . ' kỳ thi)' : 'So với TB lớp' ?></span>
    </div>
</div>

<div class="two-col">
    <section class="panel">
        <h2 class="panel-title">Tiến bộ qua các kỳ thi</h2>
        <div class="panel-body">
            <?php if (!empty($chart)): ?>
                <div class="dc-legend">
                    <span><i class="dc-key"></i> Điểm của bạn</span>
                    <?php if ($compare): ?><span><i class="dc-key avg"></i> TB lớp</span><?php endif; ?>
                    <span><i class="dc-key pass"></i> Mức đạt (<?= e(fmt_score(Result::passScore())) ?>)</span>
                </div>
                <div class="dc-wrap"><?= StudentStats::chartSvg($chart) ?></div>
                <?php if (!$compare): ?><p class="hint" style="margin:10px 0 0">Điểm trung bình lớp sẽ hiện khi kỳ thi kết thúc và có từ <?= (int) StudentStats::MIN_PEERS ?> bài nộp.</p><?php endif; ?>
            <?php else: ?>
                <p class="muted" style="margin:0">Chưa có bài thi nào được nộp. Biểu đồ sẽ xuất hiện sau bài thi đầu tiên của bạn.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Kỳ thi cần chú ý</h2>
        <div class="panel-body">
            <?php if (!empty($attention)): ?>
            <ul class="plain">
                <?php foreach ($attention as $x): ?>
                    <li>
                        <span><a href="<?= url($x['prio'] === 0 ? '/attempts/' . (int) $x['attempt_id'] : '/my-exams/' . (int) $x['id']) ?>"><strong><?= e($x['title']) ?></strong></a><br>
                            <span class="muted" style="font-size:.82rem"><?= e($x['subject_code']) ?> ·
                                <?= $x['prio'] === 0 ? 'còn' : ($x['prio'] === 1 ? 'đóng cổng sau' : 'mở sau') ?>
                                <span class="cd" data-to="<?= (int) strtotime($cdTs($x)) ?>">--:--</span></span></span>
                        <?php if ($x['prio'] === 0): ?><span class="badge badge-lv-medium">Đang làm dở</span>
                        <?php else: ?><span class="badge badge-st-<?= e($x['state']) ?>"><?= e(Exam::STATES[$x['state']]) ?></span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
                <p class="muted" style="margin:0">Hiện không có kỳ thi nào đang mở hoặc sắp diễn ra.</p>
            <?php endif; ?>
            <p style="margin:14px 0 0"><a href="<?= url('/my-exams') ?>">Xem tất cả kỳ thi &raquo;</a> · <a href="<?= url('/my-schedule') ?>">Lịch thi &raquo;</a></p>
        </div>
    </section>
</div>

<div class="two-col">
    <section class="panel">
        <h2 class="panel-title">Kết quả gần đây <a href="<?= url('/my-results') ?>" style="float:right;font-size:.82rem;font-weight:500">Toàn bộ lịch sử &raquo;</a></h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Kỳ thi</th><th>Điểm</th><th>TB lớp</th><th>Nộp lúc</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recent as $x): ?>
                    <tr>
                        <td data-label="Kỳ thi" class="q-cell"><strong><?= e($x['title']) ?></strong><div class="muted" style="font-size:.82rem"><?= e($x['subject_code']) ?></div></td>
                        <td data-label="Điểm"><span class="badge <?= $x['score'] >= Result::passScore() ? 'badge-on' : 'badge-off' ?>"><?= e(fmt_score($x['score'])) ?></span></td>
                        <td data-label="TB lớp"><?= $x['class_avg'] !== null ? e(fmt_score(round($x['class_avg'], 2))) : '<span class="muted">-</span>' ?></td>
                        <td data-label="Nộp lúc" style="white-space:nowrap"><?= e(fmt_date($x['submitted_at'])) ?></td>
                        <td class="actions" style="white-space:nowrap"><a class="btn btn-sm btn-outline" href="<?= url('/attempts/' . (int) $x['attempt_id'] . '/result') ?>">Xem kết quả</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recent): ?><tr><td colspan="5" class="empty">Bạn chưa nộp bài thi nào.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php require __DIR__ . '/_profile.php'; ?>
</div>

<section class="panel">
    <h2 class="panel-title">Môn học của lớp <?= e($class['code']) ?> (<?= count($subjects) ?>)</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Mã môn</th><th>Tên môn học</th><th>Tín chỉ</th><th>Giáo viên giảng dạy</th><th>Đã thi</th><th>Điểm TB</th></tr></thead>
            <tbody>
            <?php foreach ($subjects as $s): ?>
                <?php $b = $bySubject[$s['code']] ?? null; ?>
                <tr>
                    <td data-label="Mã môn"><strong><?= e($s['code']) ?></strong></td>
                    <td data-label="Tên môn học"><?= e($s['name']) ?></td>
                    <td data-label="Tín chỉ"><?= $s['credits'] !== null ? (int) $s['credits'] : '' ?></td>
                    <td data-label="Giáo viên"><?= $s['teacher_name'] ? e($s['teacher_name']) : '<span class="muted">Chưa phân công</span>' ?></td>
                    <td data-label="Đã thi"><?= $b ? (int) $b['done'] . '/' . (int) $b['total'] : '<span class="muted">-</span>' ?></td>
                    <td data-label="Điểm TB">
                        <?php if ($b && $b['avg'] !== null): ?>
                            <div class="prog"><span class="prog-track"><span class="prog-bar<?= $b['avg'] < Result::passScore() ? ' low' : '' ?>" style="width:<?= (int) round($b['avg'] * 10) ?>%"></span></span><span class="prog-text"><?= e(fmt_score(round($b['avg'], 2))) ?></span></div>
                        <?php else: ?><span class="muted">-</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$subjects): ?><tr><td colspan="6" class="empty">Lớp của bạn chưa được gán môn học nào.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
(function () {
    var offset = <?= (int) $serverNow ?> * 1000 - Date.now();   // lệch giờ giữa máy chủ và máy sinh viên
    var nodes = document.querySelectorAll('.cd[data-to]');
    if (!nodes.length) return;
    var reloaded = false;
    function p2(x) { return (x < 10 ? '0' : '') + x; }
    function fmt(s) {
        if (s >= 86400) return Math.floor(s / 86400) + ' ngày ' + Math.floor((s % 86400) / 3600) + ' giờ';
        var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), x = s % 60;
        return h > 0 ? h + ':' + p2(m) + ':' + p2(x) : m + ':' + p2(x);
    }
    function tick() {
        var now = Date.now() + offset, done = false;
        for (var i = 0; i < nodes.length; i++) {
            var left = Math.ceil(parseInt(nodes[i].getAttribute('data-to'), 10) - now / 1000);
            if (left <= 0) { nodes[i].textContent = '0:00'; done = true; continue; }
            nodes[i].textContent = fmt(left);
            nodes[i].classList.toggle('cd-low', left < 300);
        }
        if (done && !reloaded) {
            reloaded = true;
            // Tải lại để máy chủ cập nhật trạng thái (tự nộp bài / mở cổng thi); tối đa 1 lần mỗi 15 giây
            try {
                var last = parseInt(sessionStorage.getItem('nauDashReload') || '0', 10);
                if (Date.now() - last < 15000) return;
                sessionStorage.setItem('nauDashReload', String(Date.now()));
            } catch (e) {}
            setTimeout(function () { location.reload(); }, 1500);
        }
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
<?php endif; ?>
