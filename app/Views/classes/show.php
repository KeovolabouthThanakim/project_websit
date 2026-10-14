<div class="page-head">
    <div>
        <h1>Lớp <?= e($class['code']) ?></h1>
        <p><?= e($class['name']) ?></p>
    </div>
    <div>
        <?php if ($isAdmin): ?><a class="btn btn-outline" href="<?= url('/classes/' . (int) $class['id'] . '/edit') ?>">Sửa thông tin lớp</a><?php endif; ?>
        <a class="btn btn-outline" href="<?= url('/classes') ?>">&laquo; Danh sách lớp</a>
    </div>
</div>

<div class="two-col">
    <div>
    <section class="panel">
        <h2 class="panel-title">Môn học của lớp (<?= count($subjects) ?>)</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Mã môn</th><th>Tên môn học</th><th>Tín chỉ</th><th>Giáo viên giảng dạy</th><?php if ($isAdmin): ?><th></th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($subjects as $s): ?>
                    <tr>
                        <td><?php if ($isAdmin): ?><a href="<?= url('/subjects/' . (int) $s['id']) ?>"><strong><?= e($s['code']) ?></strong></a><?php else: ?><strong><?= e($s['code']) ?></strong><?php endif; ?></td>
                        <td><?= e($s['name']) ?><?= $s['is_active'] ? '' : ' <span class="badge badge-off">Ngừng sử dụng</span>' ?></td>
                        <td><?= $s['credits'] !== null ? (int) $s['credits'] : '' ?></td>
                        <td><?= $s['teacher_name'] ? e($s['teacher_name']) : '<span class="muted">Chưa phân công</span>' ?></td>
                        <?php if ($isAdmin): ?>
                        <td class="actions">
                            <form method="post" action="<?= url('/classes/' . (int) $class['id'] . '/subjects/' . (int) $s['id'] . '/remove') ?>"
                                  data-confirm="Bỏ môn &quot;<?= e($s['code']) ?>&quot; khỏi lớp này?">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-danger" type="submit">Bỏ khỏi lớp</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$subjects): ?><tr><td colspan="<?= $isAdmin ? 5 : 4 ?>" class="empty">Lớp chưa được gán môn học nào.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Danh sách sinh viên (<?= count($students) ?>)</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>STT</th><th>Mã SV</th><th>Họ và tên</th><th>Email</th><th>Ngày vào lớp</th><?php if ($canManage): ?><th>Thao tác</th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($students as $i => $s): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e(sv_code($s)) ?></td>
                        <td><?= e($s['full_name']) ?><?= $s['is_active'] ? '' : ' <span class="badge badge-off">Đã khóa</span>' ?></td>
                        <td><?= e($s['email']) ?></td>
                        <td><?= e(fmt_date($s['joined_at'])) ?></td>
                        <?php if ($canManage): ?><td class="actions">
                            <form method="post" action="<?= url('/classes/' . (int) $class['id'] . '/students/' . (int) $s['id'] . '/remove') ?>"
                                  data-confirm="Đưa &quot;<?= e(sv_code($s)) ?>&quot; ra khỏi lớp?">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-danger" type="submit">Đưa ra khỏi lớp</button>
                            </form>
                        </td><?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$students): ?><tr><td colspan="<?= $canManage ? 6 : 5 ?>" class="empty">Lớp chưa có sinh viên nào.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    </div>

    <div>
        <section class="panel">
            <h2 class="panel-title">Thông tin lớp</h2>
            <div class="panel-body">
                <dl class="info">
                    <dt>Mã lớp</dt><dd><?= e($class['code']) ?></dd>
                    <dt>Tên lớp</dt><dd><?= e($class['name']) ?></dd>
                    <dt>Khóa</dt><dd><?= e($class['cohort'] ?: '-') ?></dd>
                    <dt>GV chủ nhiệm</dt><dd><?= $class['teacher_name'] ? e($class['teacher_name']) : 'Chưa phân công' ?></dd>
                    <dt>Sĩ số</dt><dd><?= count($students) ?></dd>
                    <dt>Số môn học</dt><dd><?= count($subjects) ?></dd>
                </dl>
            </div>
        </section>

        <?php if ($isAdmin): ?>
        <section class="panel">
            <h2 class="panel-title">Thêm môn học vào lớp</h2>
            <div class="panel-body">
                <?php if ($subjectChoices): ?>
                <form method="post" action="<?= url('/classes/' . (int) $class['id'] . '/subjects') ?>">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <label for="subject_id">Môn học</label>
                        <select id="subject_id" name="subject_id" required>
                            <option value="">-- Chọn môn học --</option>
                            <?php foreach ($subjectChoices as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"><?= e($s['code'] . ' - ' . $s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label for="subject_teacher">Giáo viên giảng dạy</label>
                        <select id="subject_teacher" name="teacher_id">
                            <option value="">-- Chưa phân công --</option>
                            <?php foreach ($teacherChoices as $t): ?>
                                <option value="<?= (int) $t['id'] ?>"><?= e($t['full_name'] . ' (' . $t['username'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="hint">Giáo viên được chọn sẽ thấy môn này trong mục "Môn học của tôi".</span>
                    </div>
                    <button class="btn" type="submit">Thêm môn vào lớp</button>
                </form>
                <?php else: ?>
                    <p class="muted">Không còn môn học nào (đang sử dụng) để thêm.</p>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($canManage): ?>
        <section class="panel">
            <h2 class="panel-title">Thêm sinh viên vào lớp</h2>
            <div class="panel-body">
                <form method="post" action="<?= url('/classes/' . (int) $class['id'] . '/students') ?>">
                    <?= csrf_field() ?>

                    <div class="form-row">
                        <label for="filter">Chọn từ sinh viên chưa có lớp (<?= count($unassigned) ?>)</label>
                        <?php if ($unassigned): ?>
                            <input type="search" id="filter" placeholder="Lọc theo mã hoặc tên..." style="margin-bottom:6px">
                            <div class="checklist" id="checklist">
                                <?php foreach ($unassigned as $u): ?>
                                    <label class="check-row" data-text="<?= e(strtolower(sv_code($u) . ' ' . $u['username'] . ' ' . $u['full_name'])) ?>">
                                        <input type="checkbox" name="student_ids[]" value="<?= (int) $u['id'] ?>">
                                        <span><?= e(sv_code($u)) ?> - <?= e($u['full_name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="muted">Không còn sinh viên nào chưa có lớp.</p>
                        <?php endif; ?>
                    </div>

                    <div class="form-row">
                        <label for="codes">Hoặc nhập danh sách mã sinh viên</label>
                        <textarea id="codes" name="codes" rows="4" placeholder="sv001&#10;sv002&#10;sv003"></textarea>
                        <span class="hint">Mỗi mã một dòng (hoặc cách nhau bởi dấu phẩy). Tối đa 500 mã.</span>
                    </div>

                    <button class="btn" type="submit">Thêm vào lớp</button>
                </form>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>

<script>
// Lọc nhanh danh sách sinh viên chưa có lớp (không bắt buộc - trang vẫn dùng được khi tắt JavaScript)
(function () {
    var box = document.getElementById('filter');
    if (!box) return;
    box.addEventListener('input', function () {
        var kw = box.value.trim().toLowerCase();
        document.querySelectorAll('#checklist label').forEach(function (el) {
            el.style.display = el.getAttribute('data-text').indexOf(kw) === -1 ? 'none' : '';
        });
    });
})();
</script>