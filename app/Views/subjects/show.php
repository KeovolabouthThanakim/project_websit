<div class="page-head">
    <div>
        <h1>Môn <?= e($subject['code']) ?></h1>
        <p><?= e($subject['name']) ?></p>
    </div>
    <div class="header-actions">
        <a class="btn" href="<?= url('/questions?subject_id=' . (int) $subject['id']) ?>">Ngân hàng câu hỏi (<?= (int) $questionCount ?>)</a>
        <a class="btn btn-outline" href="<?= url('/subjects/' . (int) $subject['id'] . '/analytics') ?>">Thống kê các kỳ thi</a>
        <?php if ($isAdmin): ?><a class="btn btn-outline" href="<?= url('/subjects/' . (int) $subject['id'] . '/edit') ?>">Sửa môn học</a><?php endif; ?>
        <a class="btn btn-outline" href="<?= url('/subjects') ?>">&laquo; Danh sách môn học</a>
    </div>
</div>

<div class="two-col">
    <section class="panel">
        <h2 class="panel-title">Thông tin môn học</h2>
        <div class="panel-body">
            <dl class="info">
                <dt>Mã môn học</dt><dd><?= e($subject['code']) ?></dd>
                <dt>Tên môn học</dt><dd><?= e($subject['name']) ?></dd>
                <dt>Số tín chỉ</dt><dd><?= $subject['credits'] !== null ? (int) $subject['credits'] : '-' ?></dd>
                <dt>Trạng thái</dt>
                <dd><span class="badge <?= $subject['is_active'] ? 'badge-on' : 'badge-off' ?>"><?= $subject['is_active'] ? 'Đang sử dụng' : 'Ngừng sử dụng' ?></span></dd>
                <dt>Ngày tạo</dt><dd><?= e(fmt_date($subject['created_at'])) ?></dd>
                <dt>Mô tả</dt><dd style="font-weight:400"><?= $subject['description'] ? nl2br(e($subject['description'])) : '-' ?></dd>
            </dl>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Giáo viên phụ trách (<?= count($teachers) ?>)</h2>
        <div class="panel-body">
            <?php if ($teachers): ?>
                <ul class="plain">
                    <?php foreach ($teachers as $t): ?>
                        <li>
                            <span><strong><?= e($t['full_name']) ?></strong><br><span class="muted"><?= e($t['username']) ?></span></span>
                            <?php if (!$t['is_active']): ?><span class="badge badge-off">Đã khóa</span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="muted">Chưa phân công giáo viên nào.</p>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="panel">
    <h2 class="panel-title">Lớp học đang học môn này (<?= count($classes) ?>)</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Mã lớp</th><th>Tên lớp</th><th>Khóa</th><th>Sĩ số</th><th>Giáo viên giảng dạy</th><?php if ($isAdmin): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php $me = (int) (auth_user()['id'] ?? 0); ?>
            <?php foreach ($classes as $c): ?>
                <tr>
                    <td>
                        <?php if ($isAdmin || (int) $c['homeroom_id'] === $me): ?>
                            <a href="<?= url('/classes/' . (int) $c['id']) ?>"><strong><?= e($c['code']) ?></strong></a>
                        <?php else: ?><strong><?= e($c['code']) ?></strong><?php endif; ?>
                    </td>
                    <td><?= e($c['name']) ?></td>
                    <td><?= e($c['cohort']) ?></td>
                    <td><?= (int) $c['student_count'] ?></td>
                    <td><?= $c['teacher_name'] ? e($c['teacher_name']) : '<span class="muted">Chưa phân công</span>' ?></td>
                    <?php if ($isAdmin): ?>
                    <td class="actions">
                        <form method="post" action="<?= url('/subjects/' . (int) $subject['id'] . '/classes/' . (int) $c['id'] . '/remove') ?>"
                              data-confirm="Bỏ môn này khỏi lớp &quot;<?= e($c['code']) ?>&quot;?">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-danger" type="submit">Bỏ khỏi lớp</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$classes): ?><tr><td colspan="<?= $isAdmin ? 6 : 5 ?>" class="empty">Môn học này chưa được gán cho lớp nào.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($isAdmin): ?>
    <div class="panel-body" style="border-top:1px solid var(--line)">
        <?php if ($classChoices): ?>
        <form class="filter-bar" style="border:0;padding:0;margin:0" method="post" action="<?= url('/subjects/' . (int) $subject['id'] . '/classes') ?>">
            <?= csrf_field() ?>
            <div class="grow">
                <label for="class_id">Gán cho lớp</label>
                <select id="class_id" name="class_id" required>
                    <option value="">-- Chọn lớp --</option>
                    <?php foreach ($classChoices as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= e($c['code'] . ' - ' . $c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grow">
                <label for="class_teacher">Giáo viên giảng dạy</label>
                <select id="class_teacher" name="teacher_id">
                    <option value="">-- Chưa phân công --</option>
                    <?php foreach ($teacherChoices as $t): ?>
                        <option value="<?= (int) $t['id'] ?>"><?= e($t['full_name'] . ' (' . $t['username'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn" type="submit">Gán cho lớp</button>
        </form>
        <?php else: ?>
            <p class="muted" style="margin:0">Tất cả các lớp đều đã có môn này.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</section>
