<?php
$isEdit = $mode === 'edit';
form_errors();
$has = fn(string $f): string => isset(form_errors()[$f]) ? ' has-error' : '';

$code        = old('code', $isEdit ? $subject['code'] : '');
$name        = old('name', $isEdit ? $subject['name'] : '');
$credits     = old('credits', $isEdit ? (string) $subject['credits'] : '');
$description = old('description', $isEdit ? (string) $subject['description'] : '');
$active      = old('is_active', $isEdit ? ($subject['is_active'] ? '1' : '0') : '1') === '1';
$selected    = array_map('intval', array_filter(explode(',', old('teacher_ids', implode(',', $assigned))), 'strlen'));
$action      = $isEdit ? url('/subjects/' . (int) $subject['id']) : url('/subjects');
$backUrl     = url($isEdit ? '/subjects/' . (int) $subject['id'] : '/subjects');
?>
<div class="page-head">
    <div>
        <h1><?= $isEdit ? 'Sửa môn học' : 'Thêm môn học mới' ?></h1>
        <p><?= $isEdit ? 'Môn: ' . e($subject['code']) : 'Nhập thông tin môn học' ?></p>
    </div>
    <a class="btn btn-outline" href="<?= $backUrl ?>">&laquo; Quay lại</a>
</div>

<form method="post" action="<?= $action ?>" autocomplete="off">
    <?= csrf_field() ?>
    <section class="panel">
        <h2 class="panel-title">Thông tin môn học</h2>
        <div class="panel-body">
            <div class="form-grid">
                <div class="form-row<?= $has('code') ?>">
                    <label for="code">Mã môn học <span class="req">*</span></label>
                    <input type="text" id="code" name="code" value="<?= e($code) ?>" maxlength="30" required>
                    <?= field_error('code') ?>
                    <span class="hint">Ví dụ: INF101. Chỉ dùng chữ không dấu, số và . _ -</span>
                </div>
                <div class="form-row<?= $has('name') ?>">
                    <label for="name">Tên môn học <span class="req">*</span></label>
                    <input type="text" id="name" name="name" value="<?= e($name) ?>" maxlength="150" required>
                    <?= field_error('name') ?>
                </div>
                <div class="form-row<?= $has('credits') ?>">
                    <label for="credits">Số tín chỉ</label>
                    <input type="text" id="credits" name="credits" value="<?= e($credits) ?>" inputmode="numeric" maxlength="2">
                    <?= field_error('credits') ?>
                    <span class="hint">Số nguyên từ 1 đến 20, có thể để trống.</span>
                </div>
            </div>

            <div class="form-row<?= $has('description') ?>">
                <label for="description">Mô tả</label>
                <textarea id="description" name="description" rows="3" maxlength="1000"><?= e($description) ?></textarea>
                <?= field_error('description') ?>
            </div>

            <div class="form-row">
                <label class="check-row">
                    <input type="checkbox" name="is_active" value="1" <?= $active ? 'checked' : '' ?>>
                    Đang sử dụng (bỏ chọn để ngừng sử dụng môn học này)
                </label>
            </div>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Giáo viên phụ trách</h2>
        <div class="panel-body">
            <div class="form-row<?= $has('teacher_ids') ?>">
                <?= field_error('teacher_ids') ?>
                <?php if ($teachers): ?>
                    <div class="checklist">
                        <?php foreach ($teachers as $t): ?>
                            <label class="check-row">
                                <input type="checkbox" name="teacher_ids[]" value="<?= (int) $t['id'] ?>" <?= in_array((int) $t['id'], $selected, true) ? 'checked' : '' ?>>
                                <span><?= e($t['full_name']) ?> (<?= e($t['username']) ?>)<?= $t['is_active'] ? '' : ' <span class="badge badge-off">Đã khóa</span>' ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <span class="hint">Giáo viên được chọn sẽ soạn câu hỏi và đề thi cho môn này (ở các bước phát triển sau).</span>
                <?php else: ?>
                    <p class="muted">Chưa có giáo viên nào trong hệ thống. Hãy thêm tài khoản giáo viên ở mục Quản lý người dùng.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <button class="btn" type="submit"><?= $isEdit ? 'Lưu thay đổi' : 'Thêm môn học' ?></button>
        <a class="btn btn-outline" href="<?= $backUrl ?>">Hủy</a>
    </div>
</form>