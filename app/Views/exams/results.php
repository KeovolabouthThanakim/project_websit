<?php
use App\Models\AttemptEvent;
use App\Models\Exam;
use App\Models\Question;

$examId = (int) $exam['id'];
$base = '/exams/' . $examId . '/results';
$maxBucket = max(1, ...$summary['buckets']);
$stateLabel = ['absent' => ['badge-off', 'Chưa vào thi'], 'working' => ['badge-st-upcoming', 'Đang làm'], 'done' => ['badge-on', 'Đã nộp']];
$sortLink = fn(string $key, string $label) => '<a href="' . url($base . '?' . http_build_query(array_filter(['class_id' => $classId ?: '', 'sort' => $key !== 'class' ? $key : '']))) . '"' . ($sort === $key ? ' class="sort-on"' : '') . '>' . e($label) . '</a>';
$pct = fn($v) => $v === null ? '-' : number_format($v * 100, 0) . '%';
?>
<div class="page-head">
    <div>
        <h1>Kết quả: <?= e($exam['title']) ?> <span class="badge badge-st-<?= e($state) ?>"><?= e(Exam::STATES[$state]) ?></span></h1>
        <p>Môn <?= e($exam['subject_code']) ?> - <?= e($exam['subject_name']) ?></p>
    </div>
    <div class="header-actions">
        <a class="btn btn-outline" href="<?= url('/exams/' . $examId . '/monitor' . ($classId ? '?class_id=' . $classId : '')) ?>">Theo dõi trực tiếp</a>
        <a class="btn" href="<?= url($base . '/export' . ($classId ? '?class_id=' . $classId : '')) ?>">Xuất CSV</a>
        <a class="btn btn-outline" href="<?= url($base . '/print' . ($classId ? '?class_id=' . $classId : '')) ?>" target="_blank" rel="noopener">In / Lưu PDF</a>
        <a class="btn btn-outline" href="<?= url('/subjects/' . (int) $exam['subject_id'] . '/analytics') ?>">Thống kê cả môn</a>
        <a class="btn btn-outline" href="<?= url('/exams/' . $examId) ?>">&laquo; Chi tiết đề thi</a>
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
    <?php if ($sort !== 'class'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
    <noscript><button class="btn btn-outline btn-sm" type="submit">Lọc</button></noscript>
</form>
<?php endif; ?>

<div class="stats">
    <div class="stat"><span class="stat-num"><?= $summary['submitted'] ?> / <?= $summary['total'] ?></span><span class="stat-label">Sinh viên đã nộp bài</span></div>
    <div class="stat"><span class="stat-num"><?= e(fmt_score($summary['avg'])) ?></span><span class="stat-label">Điểm trung bình</span></div>
    <div class="stat"><span class="stat-num"><?= e(fmt_score($summary['max'])) ?> / <?= e(fmt_score($summary['min'])) ?></span><span class="stat-label">Cao nhất / thấp nhất</span></div>
    <div class="stat"><span class="stat-num"><?= e(fmt_score($summary['median'])) ?></span><span class="stat-label">Điểm trung vị</span></div>
    <div class="stat"><span class="stat-num"><?= $pct($summary['pass_rate']) ?></span><span class="stat-label">Tỷ lệ đạt (từ <?= e(fmt_score($passScore)) ?> điểm)</span></div>
    <div class="stat <?= $summary['absent'] > 0 ? 'warn' : '' ?>"><span class="stat-num"><?= $summary['absent'] ?><?= $summary['working'] ? ' + ' . $summary['working'] : '' ?></span><span class="stat-label">Chưa vào thi<?= $summary['working'] ? ' + đang làm' : '' ?></span></div>
</div>

<?php if ($summary['submitted'] > 0): ?>
<section class="panel">
    <h2 class="panel-title">Phân bố điểm</h2>
    <div class="panel-body">
        <div class="hist" role="img" aria-label="Biểu đồ phân bố điểm">
            <?php foreach ($summary['buckets'] as $i => $n): ?>
                <div class="hist-col" title="<?= $n ?> sinh viên đạt <?= $i ?> - <?= $i + 1 ?> điểm">
                    <span class="hist-n"><?= $n ?></span>
                    <span class="hist-bar <?= $i + 1 <= $passScore ? 'low' : '' ?>" style="height:<?= $n > 0 ? max(4, round($n / $maxBucket * 100)) : 0 ?>%"></span>
                    <span class="hist-x"><?= $i ?>-<?= $i + 1 ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="muted" style="margin:10px 0 0;font-size:.82rem">Mỗi cột là số sinh viên có điểm từ x đến dưới x+1 (điểm 10 tính vào cột 9-10). Cột đỏ nhạt là vùng dưới điểm đạt.</p>
    </div>
</section>
<?php endif; ?>

<?php if (count($byClass) > 1): ?>
<section class="panel">
    <h2 class="panel-title">Theo lớp</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Lớp</th><th>Sinh viên</th><th>Đã nộp</th><th>Điểm trung bình</th></tr></thead>
            <tbody>
            <?php foreach ($byClass as $code => $b): ?>
                <tr>
                    <td data-label="Lớp"><strong><?= e($code) ?></strong></td>
                    <td data-label="Sinh viên"><?= (int) $b['students'] ?></td>
                    <td data-label="Đã nộp"><?= (int) $b['submitted'] ?></td>
                    <td data-label="Điểm TB"><?= e(fmt_score($b['avg'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <h2 class="panel-title">Bảng điểm (<?= count($rows) ?>)</h2>
    <div class="panel-body" style="padding-bottom:0">
        <span class="muted">Sắp xếp:</span>
        <span class="sort-links"><?= $sortLink('class', 'Lớp / mã SV') ?> · <?= $sortLink('name', 'Tên') ?> · <?= $sortLink('score_desc', 'Điểm cao → thấp') ?> · <?= $sortLink('score_asc', 'Điểm thấp → cao') ?></span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>STT</th><th>Mã SV</th><th>Họ và tên</th><th>Lớp</th><th>Trạng thái</th><th>Điểm</th><th>Đúng / tổng</th><th>Thời gian làm</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $r): ?>
                <?php
                $st = \App\Models\Result::rowState($r); $spent = \App\Models\Result::secondsSpent($r);
                $ev = $events[(int) $r['attempt_id']] ?? null; $lv = $ev ? AttemptEvent::level($ev['leaves'], $ev['others']) : 'none';
                ?>
                <tr>
                    <td data-label="STT"><?= $i + 1 ?></td>
                    <td data-label="Mã SV"><?= e(sv_code($r)) ?></td>
                    <td data-label="Họ tên"><strong><?= e($r['full_name']) ?></strong></td>
                    <td data-label="Lớp"><?= $r['class_code'] ? '<span class="tag">' . e($r['class_code']) . '</span>' : '<span class="muted">đã rời lớp</span>' ?></td>
                    <td data-label="Trạng thái"><span class="badge <?= $stateLabel[$st][0] ?>"><?= $stateLabel[$st][1] ?></span><?= ($st === 'done' && $r['auto_submitted']) ? ' <span class="badge badge-off" title="Hết giờ, hệ thống tự nộp">Tự nộp</span>' : '' ?><?php if (isset($grants[(int) $r['user_id']])): ?><br><span class="badge badge-st-open" title="Được vào thi đến <?= e(fmt_date($grants[(int) $r['user_id']]['valid_until'])) ?>">Được cấp quyền thi</span><?php endif; ?><?php if (!empty($retakes[(int) $r['user_id']])): ?> <span class="badge badge-off" title="Đã hủy bài cũ và cho thi lại">Thi lại <?= (int) $retakes[(int) $r['user_id']] ?> lần</span><?php endif; ?><?php if ($lv !== 'none'): ?><br><span class="badge <?= $lv === 'warn' ? 'badge-off' : 'badge-st-upcoming' ?>" title="Rời khỏi trang thi <?= (int) $ev['leaves'] ?> lần, tổng <?= e(fmt_duration((int) $ev['away'])) ?> · sao chép / chuột phải <?= (int) $ev['others'] ?> lần. Chỉ là dấu hiệu tham khảo."><?= $ev['leaves'] > 0 ? '&#9888; Rời trang ' . (int) $ev['leaves'] . ' lần · ' . e(fmt_duration((int) $ev['away'])) : '&#9888; ' . (int) $ev['others'] . ' thao tác' ?></span><?php endif; ?></td>
                    <td data-label="Điểm"><?= $st === 'done' ? '<strong>' . e(fmt_score($r['score'])) . '</strong>' : '-' ?></td>
                    <td data-label="Đúng / tổng"><?= $st === 'done' ? (int) $r['full_correct'] . ' / ' . (int) $r['total_questions'] : '-' ?></td>
                    <td data-label="Thời gian"><?= $spent === null ? '-' : e(fmt_duration($spent)) ?></td>
                    <td class="actions"><?php if ($st === 'done'): ?><a class="btn btn-sm btn-outline" href="<?= url($base . '/' . (int) $r['attempt_id']) ?>">Xem bài</a><?php endif; ?><?php if ($canGrant): ?> <a class="btn btn-sm btn-outline" href="<?= url('/exams/' . $examId . '/grants/' . (int) $r['user_id']) ?>" title="Cộng thêm thời gian, cho thi lại hoặc thi muộn"><?= $st === 'absent' ? 'Cho vào thi' : ($st === 'working' ? '+ Thời gian' : 'Thi lại') ?></a><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="9" class="empty">Chưa có sinh viên nào trong các lớp được thi.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2 class="panel-title">Phân tích từng câu hỏi</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Câu</th><th>Nội dung</th><th>Độ khó</th><th>Đúng hoàn toàn</th><th>Điểm TB / câu</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($questions as $i => $q): ?>
                <?php
                $text = trim(preg_replace('/\s+/u', ' ', $q['content']));
                $answered = (int) $q['answered'];
                $avg = $q['avg_earned'] === null ? null : (float) $q['avg_earned'];
                ?>
                <tr>
                    <td data-label="Câu"><?= $i + 1 ?></td>
                    <td class="q-cell" data-label="Nội dung"><a href="<?= url('/questions/' . (int) $q['id']) ?>"><?= e(str_cut($text, 100)) ?><?= str_len($text) > 100 ? '…' : '' ?></a></td>
                    <td data-label="Độ khó"><span class="badge badge-lv-<?= e($q['difficulty']) ?>"><?= e(Question::DIFFICULTIES[$q['difficulty']] ?? '') ?></span></td>
                    <td data-label="Đúng hoàn toàn"><?= $answered ? (int) $q['full_count'] . ' / ' . $answered . ' (' . $pct($q['full_count'] / $answered) . ')' : '-' ?></td>
                    <td data-label="Điểm TB"><?= $avg === null ? '-' : e(fmt_score($avg)) ?></td>
                    <td data-label=""><?php if ($avg !== null && $answered >= 5 && $avg < $hardRate): ?><span class="badge badge-lv-hard" title="Dưới <?= (int) ($hardRate * 100) ?>% điểm tối đa">Cần xem lại</span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="panel-body" style="border-top:1px solid var(--line)">
        <p class="muted" style="margin:0;font-size:.85rem">Điểm TB / câu tính trên thang 0 - 1. Câu được đánh dấu "Cần xem lại" khi có từ 5 bài nộp trở lên mà điểm trung bình dưới <?= (int) ($hardRate * 100) ?>%: có thể câu quá khó, đáp án đúng bị nhập sai hoặc nội dung gây hiểu nhầm. Chỉ tính các bài đã nộp.</p>
    </div>
</section>
