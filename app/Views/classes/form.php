<?php
$isEdit = $mode === 'edit';
form_errors();
$has = fn(string $f): string => isset(form_errors()[$f]) ? ' has-error' : '';

$code    = old('code', $isEdit ? $class['code'] : '');
$name    = old('name', $isEdit ? $class['name'] : '');
$cohort  = old('cohort', $isEdit ? (string) $class['cohort'] : '');
$teacher = old('teacher_id', $isEdit ? (string) $class['teacher_id'] : '');
$action  = $isEdit ? url('/classes/' . (int) $class['id']) : url('/classes');
?>
<div class="page-head">
    <div>
        <h1><?= $isEdit ? 'Sửa lớp học' : 'Thêm lớp học mới' ?></h1>
        <p><?= $isEdit ? 'Lớp: ' . e($class['code']) : 'Nhập thông tin lớp học' ?></p>
    </div>
    <a class="btn btn-outline" href="<?= url($isEdit ? '/classes/' . (int) $class['id'] : '/classes') ?>">&laquo; Quay lại</a>
</div>

<section class="panel">
    <h2 class="panel-title">Thông tin lớp học</h2>
    <div class="panel-body">
        <form method="post" action="<?= $action ?>" autocomplete="off">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="form-row<?= $has('code') ?>">
                    <label for="code">Mã lớp <span class="req">*</span></label>
                    <input type="text" id="code" name="code" value="<?= e($code) ?>" maxlength="30" required>
                    <?= field_error('code') ?>
                    <span class="hint">Ví dụ: CNTT-K60. Chỉ dùng chữ không dấu, số và . _ -</span>
                </div>
                <div class="form-row<?= $has('name') ?>">
                    <label for="name">Tên lớp <span class="req">*</span></label>
                    <input type="text" id="name" name="name" value="<?= e($name) ?>" maxlength="150" required>
                    <?= field_error('name') ?>
                </div>
                <div class="form-row<?= $has('cohort') ?>">
                    <label for="cohort">Khóa</label>
                    <input type="text" id="cohort" name="cohort" value="<?= e($cohort) ?>" maxlength="50">
                    <?= field_error('cohort') ?>
                </div>
                <div class="form-row<?= $has('teacher_id') ?>">
                    <label for="teacher_id">Giáo viên chủ nhiệm</label>
                    <select id="teacher_id" name="teacher_id">
                        <option value="">- Chưa phân công -</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" <?= $teacher === (string) $t['id'] ? 'selected' : '' ?>>
                                <?= e($t['full_name']) ?> (<?= e($t['username']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error('teacher_id') ?>
                    <span class="hint">Giáo viên chủ nhiệm được thêm / bớt sinh viên trong lớp này.</span>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn" type="submit"><?= $isEdit ? 'Lưu thay đổi' : 'Thêm lớp học' ?></button>
                <a class="btn btn-outline" href="<?= url($isEdit ? '/classes/' . (int) $class['id'] : '/classes') ?>">Hủy</a>
            </div>
        </form>
    </div>
</section>