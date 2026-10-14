<?php
use App\Models\Exam;

$live = $exams['live'];
$working = array_sum(array_map(fn($e) => (int) $e['working'], $live));
$monitorUrl = count($live) === 1 ? url('/exams/' . (int) $live[0]['id'] . '/monitor') : '#live';
$when = fn(string $dt) => date('d/m', strtotime($dt)) . ' ' . date('H:i', strtotime($dt));
$short = fn(string $t, int $n = 60) => str_len($t) > $n ? str_cut($t, $n) . '…' : $t;
$pct = fn($n, $d) => $d > 0 ? round($n / $d * 100) : 0;
?>
<div class="page-head">
    <div>
        <h1>Trang chủ giảng viên</h1>
        <p>Xin chào, <?= e($user['full_name']) ?></p>
    </div>
    <div class="header-actions">
        <?php if ($live): ?><a class="btn" href="<?= $monitorUrl ?>">Theo dõi trực tiếp<?= count($live) > 1 ? ' (' . count($live) . ' kỳ thi)' : '' ?></a><?php endif; ?>
        <a class="btn btn-outline" href="<?= url('/exams/create') ?>">+ Tạo đề thi</a>
    </div>
</div>

<div class="stats">
    <div class="stat <?= $live ? 'warn' : '' ?>"><span class="stat-num"><?= count($live) ?></span><span class="stat-label">Kỳ thi đang diễn ra</span></div>
    <div class="stat"><span class="stat-num"><?= $working ?></span><span class="stat-label">Sinh viên đang làm bài</span></div>
    <div class="stat"><span class="stat-num"><?= count($exams['upcoming']) ?></span><span class="stat-label">Sắp diễn ra</span></div>
    <div class="stat"><span class="stat-num"><?= count($exams['drafts']) ?></span><span class="stat-label">Bản nháp chưa công bố</span></div>
    <div class="stat"><span class="stat-num"><?= count($exams['recent']) ?></span><span class="stat-label">Vừa kết thúc (14 ngày)</span></div>
</div>

<?php if ($live): ?>
<section class="panel" id="live">
    <h2 class="panel-title">Đang diễn ra</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Kỳ thi</th><th>Lớp</th><th>Cổng thi đóng</th><th>Tiến độ</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($live as $e): ?>
                <?php $absent = max(0, (int) $e['students'] - (int) $e['submitted'] - (int) $e['working']); ?>
                <tr>
                    <td data-label="Kỳ thi"><a href="<?= url('/exams/' . (int) $e['id']) ?>"><strong><?= e($short($e['title'])) ?></strong></a><br><span class="muted"><?= e($e['subject_code']) ?> · <?= (int) $e['duration_minutes'] ?> phút</span></td>
                    <td data-label="Lớp"><?php foreach (array_filter(explode(', ', (string) $e['class_codes'])) as $cc): ?><span class="tag"><?= e($cc) ?></span><?php endforeach; ?></td>
                    <td data-label="Cổng đóng"><?= e($when($e['end_at'])) ?><?= $e['state'] !== 'open' ? '<br><span class="badge badge-st-ended">Cổng đã đóng, còn người đang làm</span>' : '' ?></td>
                    <td data-label="Tiến độ">
                        <strong><?= (int) $e['submitted'] ?></strong>/<?= (int) $e['students'] ?> đã nộp · <?= (int) $e['working'] ?> đang làm · <span class="<?= $absent > 0 ? 'text-warn' : 'muted' ?>"><?= $absent ?> chưa vào thi</span>
                    </td>
                    <td class="actions">
                        <a class="btn btn-sm" href="<?= url('/exams/' . (int) $e['id'] . '/monitor') ?>">Theo dõi trực tiếp</a>
                        <a class="btn btn-sm btn-outline" href="<?= url('/exams/' . (int) $e['id'] . '/results') ?>">Kết quả</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<div class="two-col">
    <section class="panel">
        <h2 class="panel-title">Sắp diễn ra (<?= count($exams['upcoming']) ?>)</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Kỳ thi</th><th>Mở cổng</th><th></th></tr></thead>
                <tbody>
                <?php foreach (array_slice($exams['upcoming'], 0, 5) as $e): ?>
                    <tr>
                        <td data-label="Kỳ thi"><a href="<?= url('/exams/' . (int) $e['id']) ?>"><strong><?= e($short($e['title'], 45)) ?></strong></a><br><span class="muted"><?= e($e['subject_code']) ?> · <?= e((string) $e['class_codes']) ?> · <?= (int) $e['question_count'] ?> câu</span></td>
                        <td data-label="Mở cổng"><?= e($when($e['start_at'])) ?></td>
                        <td class="actions"><a class="btn btn-sm btn-outline" href="<?= url('/exams/' . (int) $e['id']) ?>">Xem</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$exams['upcoming']): ?><tr><td colspan="3" class="empty">Không có kỳ thi nào sắp diễn ra.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Bản nháp chưa công bố (<?= count($exams['drafts']) ?>)</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Đề thi</th><th>Còn thiếu</th><th></th></tr></thead>
                <tbody>
                <?php foreach (array_slice($exams['drafts'], 0, 5) as $e): ?>
                    <tr>
                        <td data-label="Đề thi"><a href="<?= url('/exams/' . (int) $e['id']) ?>"><strong><?= e($short($e['title'], 45)) ?></strong></a><br><span class="muted"><?= e($e['subject_code']) ?> · <?= (int) $e['question_count'] ?> câu</span></td>
                        <td data-label="Còn thiếu"><?= $e['missing'] ? '<span class="text-warn">' . e(implode(', ', $e['missing'])) . '</span>' : '<span class="badge badge-on">Sẵn sàng công bố</span>' ?></td>
                        <td class="actions"><a class="btn btn-sm btn-outline" href="<?= url('/exams/' . (int) $e['id']) ?>">Soạn tiếp</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$exams['drafts']): ?><tr><td colspan="3" class="empty">Không có bản nháp nào.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<section class="panel">
    <h2 class="panel-title">Vừa kết thúc - xem kết quả (<?= count($exams['recent']) ?>)</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Kỳ thi</th><th>Kết thúc</th><th>Đã nộp</th><th>Điểm TB</th><th></th></tr></thead>
            <tbody>
            <?php foreach (array_slice($exams['recent'], 0, 8) as $e): ?>
                <?php $absent = max(0, (int) $e['students'] - (int) $e['submitted'] - (int) $e['working']); ?>
                <tr>
                    <td data-label="Kỳ thi"><a href="<?= url('/exams/' . (int) $e['id'] . '/results') ?>"><strong><?= e($short($e['title'])) ?></strong></a> <span class="badge badge-st-<?= e($e['state']) ?>"><?= e(Exam::STATES[$e['state']]) ?></span><br><span class="muted"><?= e($e['subject_code']) ?> · <?= e((string) $e['class_codes']) ?></span></td>
                    <td data-label="Kết thúc"><?= e($when($e['end_at'])) ?></td>
                    <td data-label="Đã nộp"><strong><?= (int) $e['submitted'] ?></strong>/<?= (int) $e['students'] ?><?= $absent > 0 ? ' <span class="text-warn" title="Chưa vào thi - có thể cần cho thi muộn / thi bù">(' . $absent . ' vắng)</span>' : '' ?></td>
                    <td data-label="Điểm TB"><?= $e['avg_score'] === null ? '-' : e(fmt_score($e['avg_score'])) ?></td>
                    <td class="actions"><a class="btn btn-sm btn-outline" href="<?= url('/exams/' . (int) $e['id'] . '/results') ?>">Kết quả</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$exams['recent']): ?><tr><td colspan="5" class="empty">Chưa có kỳ thi nào kết thúc trong 14 ngày qua.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="panel-body" style="border-top:1px solid var(--line)"><p class="muted" style="margin:0;font-size:.85rem">Bài trắc nghiệm được chấm tự động ngay khi nộp hoặc hết giờ, nên không có bài "chờ chấm". Cột "vắng" là sinh viên chưa vào thi; mở Kết quả để cho thi muộn / thi bù.</p></div>
</section>

<section class="panel">
    <h2 class="panel-title">Lớp của tôi (chủ nhiệm và giảng dạy)</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Mã lớp</th><th>Tên lớp</th><th>Khóa</th><th>Sĩ số</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($classes as $c): ?>
                <tr>
                    <td><strong><?= e($c['code']) ?></strong></td>
                    <td><?= e($c['name']) ?></td>
                    <td><?= e($c['cohort']) ?></td>
                    <td><?= (int) $c['student_count'] ?></td>
                    <td class="actions"><a class="btn btn-sm" href="<?= url('/classes/' . (int) $c['id']) ?>"><?= $c['is_homeroom'] ? 'Quản lý sinh viên' : 'Xem sinh viên' ?></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$classes): ?><tr><td colspan="5" class="empty">Bạn chưa được phân công chủ nhiệm hay giảng dạy lớp nào.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2 class="panel-title">Môn học phụ trách</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Mã môn</th><th>Tên môn học</th><th>Tín chỉ</th><th>Lớp học</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($subjects as $s): ?>
                <tr>
                    <td><strong><?= e($s['code']) ?></strong></td>
                    <td><?= e($s['name']) ?></td>
                    <td><?= $s['credits'] !== null ? (int) $s['credits'] : '' ?></td>
                    <td><?php if ($s['class_codes']): foreach (explode(', ', $s['class_codes']) as $cc): ?><span class="tag"><?= e($cc) ?></span><?php endforeach; else: ?><span class="muted">Chưa gán lớp</span><?php endif; ?></td>
                    <td><span class="badge <?= $s['is_active'] ? 'badge-on' : 'badge-off' ?>"><?= $s['is_active'] ? 'Đang sử dụng' : 'Ngừng sử dụng' ?></span></td>
                    <td class="actions"><a class="btn btn-sm btn-outline" href="<?= url('/subjects/' . (int) $s['id']) ?>">Xem</a> <a class="btn btn-sm btn-outline" href="<?= url('/subjects/' . (int) $s['id'] . '/analytics') ?>">Thống kê</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$subjects): ?><tr><td colspan="6" class="empty">Bạn chưa được phân công môn học nào.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="two-col">
    <section class="panel">
        <h2 class="panel-title">Chức năng</h2>
        <div class="panel-body">
            <ul class="plain">
                <li><a href="<?= url('/questions') ?>"><strong>Ngân hàng câu hỏi</strong></a><span><a class="btn btn-sm btn-outline" href="<?= url('/questions/import') ?>">Nhập từ Excel / Word</a> <a class="btn btn-sm" href="<?= url('/questions/create') ?>">+ Thêm câu hỏi</a></span></li>
                <li><a href="<?= url('/exams') ?>"><strong>Đề thi</strong></a><a class="btn btn-sm" href="<?= url('/exams/create') ?>">+ Tạo đề thi</a></li>
                <li><a href="<?= url('/exams') ?>"><strong>Kết quả và thống kê</strong></a><a class="btn btn-sm btn-outline" href="<?= url('/exams') ?>">Chọn kỳ thi</a></li>
            </ul>
        </div>
    </section>
    <?php require __DIR__ . '/_profile.php'; ?>
</div>