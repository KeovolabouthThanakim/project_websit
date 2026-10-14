<?php
use App\Models\Exam;
use App\Models\Question;

$pct = fn($v) => $v === null ? '-' : number_format($v * 100, 0) . '%';
$base = '/subjects/' . (int) $subject['id'] . '/analytics';

// Biểu đồ xu hướng điểm trung bình (SVG thuần, thang 0 - 10)
$W = 640; $H = 220; $L = 34; $R = 14; $T = 14; $B = 46;
$n = count($withData);
$xAt = fn(int $i) => $n > 1 ? $L + ($W - $L - $R) * $i / ($n - 1) : ($L + $W - $R) / 2;
$yAt = fn(float $v) => $T + ($H - $T - $B) * (1 - $v / 10);
$points = [];
foreach ($withData as $i => $e) {
    $points[] = round($xAt($i), 1) . ',' . round($yAt((float) $e['avg']), 1);
}
?>
<div class="page-head">
    <div>
        <h1>Thống kê môn <?= e($subject['code']) ?></h1>
        <p><?= e($subject['name']) ?> · qua nhiều kỳ thi</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-outline" href="<?= url('/exams?subject_id=' . (int) $subject['id']) ?>">Các đề thi của môn</a>
        <a class="btn btn-outline" href="<?= url('/subjects/' . (int) $subject['id']) ?>">&laquo; Môn học</a>
    </div>
</div>

<?php if (count($classes) > 1): ?>
<form method="get" action="<?= url($base) ?>" class="filter-bar">
    <label for="class_id">Lớp</label>
    <select id="class_id" name="class_id" onchange="this.form.submit()">
        <option value="">Tất cả các lớp</option>
        <?php foreach ($classes as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= $classId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['code']) ?> - <?= e($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-outline btn-sm" type="submit">Lọc</button></noscript>
</form>
<?php endif; ?>

<div class="stats">
    <div class="stat"><span class="stat-num"><?= (int) $overall['exams'] ?></span><span class="stat-label">Kỳ thi đã có bài nộp</span></div>
    <div class="stat"><span class="stat-num"><?= (int) $overall['attempts'] ?></span><span class="stat-label">Bài đã nộp (tổng)</span></div>
    <div class="stat"><span class="stat-num"><?= e(fmt_score($overall['avg'])) ?></span><span class="stat-label">Điểm TB chung (theo số bài)</span></div>
</div>

<?php if (!$withData): ?>
<section class="panel"><div class="panel-body"><p style="margin:0">Môn này chưa có kỳ thi nào có bài nộp<?= $classId ? ' của lớp đã chọn' : '' ?>, nên chưa có gì để thống kê.</p></div></section>
<?php else: ?>

<section class="panel">
    <h2 class="panel-title">Điểm trung bình qua các kỳ thi</h2>
    <div class="panel-body">
        <svg class="trend" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Biểu đồ điểm trung bình theo từng kỳ thi">
            <?php foreach ([0, 2.5, 5, 7.5, 10] as $g): ?>
                <line class="t-grid" x1="<?= $L ?>" x2="<?= $W - $R ?>" y1="<?= round($yAt($g), 1) ?>" y2="<?= round($yAt($g), 1) ?>"/>
                <text x="<?= $L - 6 ?>" y="<?= round($yAt($g) + 4, 1) ?>" text-anchor="end"><?= fmt_score($g) ?></text>
            <?php endforeach; ?>
            <line class="t-pass" x1="<?= $L ?>" x2="<?= $W - $R ?>" y1="<?= round($yAt($pass), 1) ?>" y2="<?= round($yAt($pass), 1) ?>"/>
            <?php if ($n > 1): ?><polyline class="t-line" points="<?= implode(' ', $points) ?>"/><?php endif; ?>
            <?php foreach ($withData as $i => $e): ?>
                <circle class="t-dot" cx="<?= round($xAt($i), 1) ?>" cy="<?= round($yAt((float) $e['avg']), 1) ?>" r="5"><title><?= e($e['title'] . ': ' . fmt_score($e['avg']) . ' điểm (' . $e['submitted'] . ' bài)') ?></title></circle>
                <text x="<?= round($xAt($i), 1) ?>" y="<?= round($yAt((float) $e['avg']) - 11, 1) ?>" text-anchor="middle"><?= e(fmt_score($e['avg'])) ?></text>
                <text x="<?= round($xAt($i), 1) ?>" y="<?= $H - 22 ?>" text-anchor="middle"><?= e(date('d/m', strtotime($e['start_at']))) ?></text>
                <text x="<?= round($xAt($i), 1) ?>" y="<?= $H - 8 ?>" text-anchor="middle"><?= e(date('Y', strtotime($e['start_at']))) ?></text>
            <?php endforeach; ?>
        </svg>
        <p class="muted" style="margin:6px 0 0;font-size:.82rem">Mỗi chấm là một kỳ thi (theo ngày mở cổng). Đường nét đứt vàng là điểm đạt (<?= e(fmt_score($pass)) ?>). Rê chuột vào chấm để xem tên kỳ thi.</p>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <h2 class="panel-title">Các kỳ thi của môn (<?= count($exams) ?>)</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Kỳ thi</th><th>Ngày thi</th><th>Đã nộp</th><th>Điểm TB</th><th>Trung vị</th><th>Cao / thấp</th><th>Tỷ lệ đạt</th><th></th></tr></thead>
            <tbody>
            <?php foreach (array_reverse($exams) as $e): ?>
                <tr>
                    <td data-label="Kỳ thi"><strong><?= e($e['title']) ?></strong> <span class="badge badge-st-<?= e($e['status'] === 'closed' ? 'closed' : Exam::state($e)) ?>"><?= e(Exam::STATES[$e['status'] === 'closed' ? 'closed' : Exam::state($e)]) ?></span></td>
                    <td data-label="Ngày thi"><?= e(date('d/m/Y', strtotime($e['start_at']))) ?></td>
                    <td data-label="Đã nộp"><?= (int) $e['submitted'] ?> / <?= (int) $e['students'] ?></td>
                    <td data-label="Điểm TB"><strong><?= e(fmt_score($e['avg'])) ?></strong></td>
                    <td data-label="Trung vị"><?= e(fmt_score($e['median'])) ?></td>
                    <td data-label="Cao / thấp"><?= $e['max'] === null ? '-' : e(fmt_score($e['max']) . ' / ' . fmt_score($e['min'])) ?></td>
                    <td data-label="Tỷ lệ đạt"><?= $pct($e['pass_rate']) ?></td>
                    <td class="actions"><a class="btn btn-sm btn-outline" href="<?= url('/exams/' . (int) $e['id'] . '/results' . ($classId ? '?class_id=' . $classId : '')) ?>">Kết quả</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$exams): ?><tr><td colspan="8" class="empty">Môn này chưa có kỳ thi nào được công bố.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2 class="panel-title">Câu hỏi hay làm sai nhất</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>#</th><th>Nội dung</th><th>Độ khó</th><th>Số lượt làm</th><th>Đúng hoàn toàn</th><th>Điểm TB / câu</th><th>Đáp án sai hay chọn nhất</th></tr></thead>
            <tbody>
            <?php foreach ($questions as $i => $q): ?>
                <?php $text = trim(preg_replace('/\s+/u', ' ', $q['content'])); $att = (int) $q['attempts']; ?>
                <tr>
                    <td data-label="#"><?= $i + 1 ?></td>
                    <td class="q-cell" data-label="Nội dung"><a href="<?= url('/questions/' . (int) $q['id']) ?>"><?= e(str_cut($text, 110)) ?><?= str_len($text) > 110 ? '…' : '' ?></a><?= $q['is_active'] ? '' : ' <span class="badge badge-off">Đã ẩn</span>' ?><br><span class="muted"><?= (int) $q['exams'] ?> kỳ thi</span></td>
                    <td data-label="Độ khó"><span class="badge badge-lv-<?= e($q['difficulty']) ?>"><?= e(Question::DIFFICULTIES[$q['difficulty']] ?? '') ?></span></td>
                    <td data-label="Số lượt"><?= $att ?></td>
                    <td data-label="Đúng hoàn toàn"><?= (int) $q['full_count'] ?> (<?= $pct($att ? $q['full_count'] / $att : null) ?>)</td>
                    <td data-label="Điểm TB"><strong><?= e(fmt_score((float) $q['avg_earned'])) ?></strong></td>
                    <td data-label="Đáp án sai"><?= $q['wrong_option'] ? e(str_cut($q['wrong_option']['content'], 70)) . ' <span class="muted">(' . (int) $q['wrong_option']['count'] . ' lượt)</span>' : '<span class="muted">-</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$questions): ?><tr><td colspan="7" class="empty">Chưa có câu hỏi nào đủ <?= (int) $minSample ?> lượt làm để thống kê.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="panel-body" style="border-top:1px solid var(--line)">
        <p class="muted" style="margin:0;font-size:.85rem">Gộp mọi lần câu hỏi xuất hiện trong các kỳ thi của môn; chỉ liệt kê câu có từ <?= (int) $minSample ?> lượt làm trở lên (tối đa <?= \App\Models\Analytics::TOP_QUESTIONS ?> câu). Điểm TB / câu thang 0 - 1: càng thấp càng nhiều người sai. Nếu một đáp án sai được chọn rất nhiều, hãy xem lại đáp án đúng hoặc cách diễn đạt của câu hỏi.</p>
    </div>
</section>
