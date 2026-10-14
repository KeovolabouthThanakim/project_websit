<?php
$warnings = security_warnings();
if ($weak = default_password_accounts()) {
    array_unshift($warnings, 'Các tài khoản sau vẫn dùng mật khẩu mặc định đã công khai trong tài liệu: ' . implode(', ', $weak) . '. Hãy đổi mật khẩu (hoặc khóa tài khoản mẫu) ngay.');
}
$live = $exams['live'];
$when = fn(string $dt) => date('d/m', strtotime($dt)) . ' ' . date('H:i', strtotime($dt));
$short = fn(string $t, int $n = 55) => str_len($t) > $n ? str_cut($t, $n) . '…' : $t;
$me = auth_user();
$hour = (int) date('G');
$hello = $hour < 11 ? 'Chào buổi sáng' : ($hour < 14 ? 'Chào buổi trưa' : ($hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối'));
$pct = fn(int $n) => $total > 0 ? round($n * 100 / $total, 1) : 0;
$roles = [['Quản trị viên', (int) $counts['admin'], 'r-admin'], ['Giảng viên', (int) $counts['teacher'], 'r-teacher'], ['Sinh viên', (int) $counts['student'], 'r-student']];
?>
<section class="dash-hero">
    <div class="dash-hero-main">
        <p class="dash-date"><?= e(date('d/m/Y')) ?></p>
        <h1><?= e($hello) ?>, <?= e($me['full_name']) ?></h1>
        <p class="dash-status">
            <span class="live-dot<?= $live ? '' : ' off' ?>" aria-hidden="true"></span>
            <?php if ($live): ?>
                <strong><?= count($live) ?></strong> kỳ thi đang diễn ra, <strong><?= (int) $workingCount ?></strong> sinh viên đang làm bài
            <?php else: ?>
                Hiện chưa có kỳ thi nào đang diễn ra
            <?php endif; ?>
        </p>
    </div>
    <div class="dash-actions">
        <a class="dash-act primary" href="<?= url('/exams/create') ?>">Tạo đề thi</a>
        <a class="dash-act" href="<?= url('/questions/create') ?>">Thêm câu hỏi</a>
        <a class="dash-act" href="<?= url('/admin/users/create') ?>">Thêm người dùng</a>
    </div>
</section>

<?php if ($warnings): ?>
<div class="dash-warn" role="alert">
    <span class="dash-warn-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.9L2.4 17.5A2 2 0 0 0 4.1 20.5h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg></span>
    <div class="dash-warn-body">
        <strong>Cần xử lý về bảo mật</strong>
        <ul><?php foreach ($warnings as $w): ?><li><?= e($w) ?></li><?php endforeach; ?></ul>
    </div>
    <a class="btn btn-sm" href="<?= url('/admin/users') ?>">Quản lý tài khoản</a>
</div>
<?php endif; ?>

<section class="dash-strip" aria-label="Hoạt động thi">
    <div class="dash-m<?= $live ? ' hot' : '' ?>"><span class="n"><?= count($live) ?></span><span class="l">Đang diễn ra</span></div>
    <div class="dash-m"><span class="n"><?= (int) $workingCount ?></span><span class="l">Đang làm bài</span></div>
    <div class="dash-m"><span class="n"><?= count($exams['upcoming']) ?></span><span class="l">Sắp diễn ra</span></div>
    <div class="dash-m"><span class="n"><?= count($exams['drafts']) ?></span><span class="l">Đề còn là bản nháp</span></div>
</section>

<div class="dash-grid">
    <section class="panel dash-people">
        <h2 class="panel-title">Tài khoản</h2>
        <div class="dash-people-body">
            <p class="dash-total"><strong><?= (int) $total ?></strong> tài khoản<?php if ((int) $inactive > 0): ?> <a class="tag-warn" href="<?= url('/admin/users') ?>"><?= (int) $inactive ?> bị khóa</a><?php endif; ?></p>
            <div class="role-bar" role="img" aria-label="Cơ cấu tài khoản theo vai trò">
                <?php foreach ($roles as [$lb, $n, $cl]): if ($n > 0): ?><span class="<?= $cl ?>" style="width:<?= $pct($n) ?>%"></span><?php endif; endforeach; ?>
            </div>
            <ul class="role-legend">
                <?php foreach ($roles as [$lb, $n, $cl]): ?><li><i class="<?= $cl ?>"></i><?= e($lb) ?><b><?= $n ?></b></li><?php endforeach; ?>
            </ul>
        </div>
    </section>
    <section class="panel dash-data">
        <h2 class="panel-title">Dữ liệu đào tạo</h2>
        <a href="<?= url('/classes') ?>"><span>Lớp học</span><b><?= (int) $classCount ?></b></a>
        <a href="<?= url('/subjects') ?>"><span>Môn học</span><b><?= (int) $subjectCount ?></b></a>
        <a href="<?= url('/questions') ?>"><span>Câu hỏi</span><b><?= (int) $questionCount ?></b></a>
    </section>
</div>

<section class="panel">
    <h2 class="panel-title">Kỳ thi đang diễn ra (<?= count($live) ?>)</h2>
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
                    <td data-label="Tiến độ"><strong><?= (int) $e['submitted'] ?></strong>/<?= (int) $e['students'] ?> đã nộp · <?= (int) $e['working'] ?> đang làm · <span class="<?= $absent > 0 ? 'text-warn' : 'muted' ?>"><?= $absent ?> chưa vào thi</span></td>
                    <td class="actions">
                        <a class="btn btn-sm" href="<?= url('/exams/' . (int) $e['id'] . '/monitor') ?>">Theo dõi trực tiếp</a>
                        <a class="btn btn-sm btn-outline" href="<?= url('/exams/' . (int) $e['id'] . '/results') ?>">Kết quả</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$live): ?><tr><td colspan="5" class="empty">Hiện không có kỳ thi nào đang diễn ra.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="two-col">
    <section class="panel">
        <h2 class="panel-title">Đang làm bài ngay bây giờ (<?= (int) $workingCount ?>)</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Sinh viên</th><th>Kỳ thi</th><th>Đã làm</th><th>Còn lại</th></tr></thead>
                <tbody>
                <?php foreach ($workingNow as $w): ?>
                    <?php $left = max(0, strtotime($w['deadline_at']) - time()); ?>
                    <tr>
                        <td data-label="Sinh viên"><strong><?= e($w['full_name']) ?></strong><br><span class="muted"><?= e(sv_code($w)) ?><?= $w['class_code'] ? ' · ' . e($w['class_code']) : '' ?></span></td>
                        <td data-label="Kỳ thi"><a href="<?= url('/exams/' . (int) $w['exam_id'] . '/monitor') ?>"><?= e($short($w['exam_title'], 38)) ?></a><br><span class="muted"><?= e($w['subject_code']) ?></span></td>
                        <td data-label="Đã làm"><?= (int) $w['answered'] ?>/<?= (int) $w['total_questions'] ?></td>
                        <td data-label="Còn lại"><?= e(fmt_duration($left)) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$workingNow): ?><tr><td colspan="4" class="empty">Không có sinh viên nào đang làm bài.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($workingCount > count($workingNow)): ?><div class="panel-body" style="border-top:1px solid var(--line)"><p class="muted" style="margin:0;font-size:.85rem">Hiện <?= count($workingNow) ?> người sắp hết giờ nhất; xem đầy đủ ở trang theo dõi của từng kỳ thi.</p></div><?php endif; ?>
    </section>

    <section class="panel">
        <h2 class="panel-title">Sắp diễn ra (<?= count($exams['upcoming']) ?>)</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Kỳ thi</th><th>Mở cổng</th><th></th></tr></thead>
                <tbody>
                <?php foreach (array_slice($exams['upcoming'], 0, 6) as $e): ?>
                    <tr>
                        <td data-label="Kỳ thi"><a href="<?= url('/exams/' . (int) $e['id']) ?>"><strong><?= e($short($e['title'], 40)) ?></strong></a><br><span class="muted"><?= e($e['subject_code']) ?> · <?= e((string) $e['class_codes']) ?> · <?= (int) $e['students'] ?> sinh viên</span></td>
                        <td data-label="Mở cổng"><?= e($when($e['start_at'])) ?></td>
                        <td class="actions"><a class="btn btn-sm btn-outline" href="<?= url('/exams/' . (int) $e['id']) ?>">Xem</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$exams['upcoming']): ?><tr><td colspan="3" class="empty">Không có kỳ thi nào sắp diễn ra.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="two-col">
    <section class="panel">
        <h2 class="panel-title">Tài khoản mới tạo gần đây</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Tên đăng nhập</th><th>Họ và tên</th><th>Vai trò</th><th>Ngày tạo</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $u): ?>
                    <tr>
                        <td><?= e($u['username']) ?></td>
                        <td><?= e($u['full_name']) ?></td>
                        <td><span class="badge badge-<?= e($u['role']) ?>"><?= e(role_label($u['role'])) ?></span></td>
                        <td><?= e(fmt_date($u['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recent): ?><tr><td colspan="4" class="empty">Chưa có tài khoản nào.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Thao tác nhanh</h2>
        <div class="panel-body">
            <ul class="plain links">
                <li><a href="<?= url('/admin/users/create') ?>">Thêm người dùng mới</a></li>
                <li><a href="<?= url('/admin/users/import') ?>">Nhập người dùng từ Excel / CSV</a></li>
                <li><a href="<?= url('/admin/reports') ?>">Báo cáo tổng quan kết quả thi</a></li>
                <li><a href="<?= url('/admin/users') ?>">Danh sách người dùng</a></li>
                <li><a href="<?= url('/classes/create') ?>">Thêm lớp học mới</a></li>
                <li><a href="<?= url('/classes') ?>">Danh sách lớp học</a></li>
                <li><a href="<?= url('/subjects/create') ?>">Thêm môn học mới</a></li>
                <li><a href="<?= url('/subjects') ?>">Danh sách môn học</a></li>
                <li><a href="<?= url('/questions/create') ?>">Thêm câu hỏi mới</a></li>
                <li><a href="<?= url('/questions') ?>">Ngân hàng câu hỏi</a></li>
                <li><a href="<?= url('/exams') ?>">Đề thi</a></li>
                <li><a href="<?= url('/admin/users?role=teacher') ?>">Danh sách giảng viên</a></li>
                <li><a href="<?= url('/admin/users?role=student') ?>">Danh sách sinh viên</a></li>
            </ul>
        </div>
    </section>
</div>