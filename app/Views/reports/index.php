<?php
$pct = fn($v) => $v === null ? '-' : number_format($v * 100, 0) . '%';
$q = fn(array $over = []) => http_build_query(array_filter(array_merge(['by' => $f['by'], 'semester' => $f['semester'], 'subject_id' => $f['subject_id'] ?: '', 'class_id' => $f['class_id'] ?: ''], $over), fn($v) => $v !== '' && $v !== null));
$maxAvg = 10.0;
$termText = implode(', ', array_map(fn($n, $m) => $n . ' từ tháng ' . $m, array_keys($terms), $terms));
?>
<div class="page-head">
    <div>
        <h1>Báo cáo tổng quan</h1>
        <p>Kết quả thi toàn hệ thống, gom theo môn học, lớp hoặc học kỳ</p>
    </div>
    <div class="header-actions">
        <?php if ($xlsxOk): ?><a class="btn" href="<?= url('/admin/reports/export?' . $q(['format' => 'xlsx'])) ?>">Xuất Excel (.xlsx)</a><?php endif; ?>
        <a class="btn <?= $xlsxOk ? 'btn-outline' : '' ?>" href="<?= url('/admin/reports/export?' . $q(['format' => 'csv'])) ?>">Xuất CSV</a>
        <a class="btn btn-outline" href="<?= url('/dashboard') ?>">&laquo; Trang chủ</a>
    </div>
</div>

<form method="get" action="<?= url('/admin/reports') ?>" class="filter-bar">
    <div>
        <label for="by">Nhóm theo</label>
        <select id="by" name="by" onchange="this.form.submit()">
            <?php foreach (\App\Models\Report::BY as $k => $label): ?><option value="<?= e($k) ?>" <?= $f['by'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="semester">Học kỳ</label>
        <select id="semester" name="semester" onchange="this.form.submit()">
            <option value="">Tất cả</option>
            <?php foreach ($options['semesters'] as $k => $label): ?><option value="<?= e($k) ?>" <?= $f['semester'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="subject_id">Môn học</label>
        <select id="subject_id" name="subject_id" onchange="this.form.submit()">
            <option value="">Tất cả</option>
            <?php foreach ($options['subjects'] as $k => $label): ?><option value="<?= (int) $k ?>" <?= $f['subject_id'] === (int) $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="class_id">Lớp</label>
        <select id="class_id" name="class_id" onchange="this.form.submit()">
            <option value="">Tất cả</option>
            <?php foreach ($options['classes'] as $k => $label): ?><option value="<?= (int) $k ?>" <?= $f['class_id'] === (int) $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
    </div>
    <noscript><button class="btn btn-sm" type="submit">Lọc</button></noscript>
    <?php if ($f['semester'] !== '' || $f['subject_id'] || $f['class_id']): ?><a class="btn btn-outline" href="<?= url('/admin/reports?by=' . e($f['by'])) ?>">Xóa bộ lọc</a><?php endif; ?>
</form>

<div class="stats">
    <div class="stat"><span class="stat-num"><?= (int) $total['exams'] ?></span><span class="stat-label">Kỳ thi</span></div>
    <div class="stat"><span class="stat-num"><?= (int) $total['submitted'] ?> / <?= (int) $total['expected'] ?></span><span class="stat-label">Bài đã nộp / lượt được thi</span></div>
    <div class="stat"><span class="stat-num"><?= $pct($total['participation']) ?></span><span class="stat-label">Tỷ lệ tham gia</span></div>
    <div class="stat"><span class="stat-num"><?= e(fmt_score($total['avg'])) ?></span><span class="stat-label">Điểm trung bình</span></div>
    <div class="stat"><span class="stat-num"><?= $pct($total['pass_rate']) ?></span><span class="stat-label">Tỷ lệ đạt (từ <?= e(fmt_score($pass)) ?>)</span></div>
</div>

<section class="panel">
    <h2 class="panel-title">Theo <?= e(mb_strtolower(\App\Models\Report::BY[$f['by']], 'UTF-8')) ?> (<?= count($groups) ?>)</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th><?= e(\App\Models\Report::BY[$f['by']]) ?></th><th>Số kỳ thi</th><th>Đã nộp / được thi</th><th>Tham gia</th><th>Điểm TB</th><th style="min-width:120px"></th><th>Tỷ lệ đạt</th></tr></thead>
            <tbody>
            <?php foreach ($groups as $g): ?>
                <tr>
                    <td data-label="<?= e(\App\Models\Report::BY[$f['by']]) ?>"><strong><?= e($g['label']) ?></strong><?= $g['sub'] !== '' ? '<br><span class="muted">' . e($g['sub']) . '</span>' : '' ?></td>
                    <td data-label="Số kỳ thi"><?= (int) $g['exams'] ?></td>
                    <td data-label="Đã nộp"><?= (int) $g['submitted'] ?> / <?= (int) $g['expected'] ?></td>
                    <td data-label="Tham gia"><?= $pct($g['participation']) ?></td>
                    <td data-label="Điểm TB"><strong><?= e(fmt_score($g['avg'])) ?></strong></td>
                    <td><?php if ($g['avg'] !== null): ?><div style="background:var(--line);border-radius:6px;height:8px;overflow:hidden"><div style="height:100%;width:<?= round($g['avg'] / $maxAvg * 100) ?>%;background:<?= $g['avg'] >= $pass ? 'var(--red)' : 'var(--gold)' ?>"></div></div><?php endif; ?></td>
                    <td data-label="Tỷ lệ đạt"><?= $pct($g['pass_rate']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$groups): ?><tr><td colspan="7" class="empty">Chưa có kỳ thi nào (đã công bố) khớp bộ lọc.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="panel-body" style="border-top:1px solid var(--line)"><p class="muted" style="margin:0;font-size:.85rem">"Được thi" là tổng sĩ số các lớp được thi, tính theo từng kỳ thi. Chỉ tính bài đã nộp của sinh viên hiện thuộc lớp được thi; bản nháp không có trong báo cáo. Điểm TB và tỷ lệ đạt tính trên toàn bộ bài nộp của nhóm (không phải trung bình của các trung bình). Học kỳ suy ra từ ngày mở cổng thi: <?= e($termText) ?> (đổi bằng khóa <code>semesters</code> trong config.php).</p></div>
</section>

<section class="panel">
    <h2 class="panel-title">Các kỳ thi (<?= (int) $examCount ?><?= $examCount > count($exams) ? ', hiện ' . count($exams) . ' kỳ mới nhất' : '' ?>)</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Kỳ thi</th><th>Học kỳ</th><th>Lớp</th><th>Đã nộp / được thi</th><th>Điểm TB</th><th>Tỷ lệ đạt</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($exams as $e): ?>
                <tr>
                    <td data-label="Kỳ thi"><strong><?= e($e['title']) ?></strong><br><span class="muted"><?= e($e['subject_code']) ?> · <?= e(date('d/m/Y', strtotime($e['start_at']))) ?></span></td>
                    <td data-label="Học kỳ"><?= e($e['semester_label']) ?></td>
                    <td data-label="Lớp"><?php foreach ($e['classes'] as $c): ?><span class="tag"><?= e($c) ?></span><?php endforeach; ?></td>
                    <td data-label="Đã nộp"><?= (int) $e['submitted'] ?> / <?= (int) $e['expected'] ?></td>
                    <td data-label="Điểm TB"><?= e(fmt_score($e['avg'])) ?></td>
                    <td data-label="Tỷ lệ đạt"><?= $pct($e['pass_rate']) ?></td>
                    <td class="actions"><a class="btn btn-sm btn-outline" href="<?= url('/exams/' . (int) $e['id'] . '/results') ?>">Kết quả</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$exams): ?><tr><td colspan="7" class="empty">Không có kỳ thi nào.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
