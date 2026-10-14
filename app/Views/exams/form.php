<?php
$isEdit = $mode === 'edit';
form_errors();
$has = fn(string $f): string => isset(form_errors()[$f]) ? ' has-error' : '';

// Giờ mặc định cho đề mới: ngày mai 08:00 - 10:00
$defStart = date('Y-m-d\TH:i', strtotime('tomorrow 08:00'));
$defEnd = date('Y-m-d\TH:i', strtotime('tomorrow 10:00'));
$toInput = fn(?string $v): string => $v ? date('Y-m-d\TH:i', strtotime($v)) : '';

$title    = old('title', $isEdit ? $exam['title'] : '');
$desc     = old('description', $isEdit ? (string) $exam['description'] : '');
$duration = old('duration_minutes', $isEdit ? (string) $exam['duration_minutes'] : '30');
$startAt  = old('start_at', $isEdit ? $toInput($exam['start_at']) : $defStart);
$endAt    = old('end_at', $isEdit ? $toInput($exam['end_at']) : $defEnd);
$shuffle  = old('shuffle', $isEdit ? ($exam['shuffle'] ? '1' : '0') : '1') === '1';
$picked   = array_map('intval', array_filter(explode(',', old('class_ids', implode(',', $selected))), 'strlen'));
$action   = $isEdit ? url('/exams/' . (int) $exam['id']) : url('/exams');
$backUrl  = url($isEdit ? '/exams/' . (int) $exam['id'] : '/exams');
?>
<div class="page-head">
    <div>
        <h1><?= $isEdit ? 'Sửa đề thi' : 'Tạo đề thi' ?></h1>
        <p><?= $isEdit ? e($exam['title']) : 'Bước 2: nhập thông tin, sau đó chọn câu hỏi cho đề' ?></p>
    </div>
    <a class="btn btn-outline" href="<?= $backUrl ?>">&laquo; Quay lại</a>
</div>

<form method="post" action="<?= $action ?>" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="subject_id" value="<?= (int) $subject['id'] ?>">

    <section class="panel">
        <h2 class="panel-title">Thông tin đề thi</h2>
        <div class="panel-body">
            <div class="form-row">
                <label>Môn học</label>
                <input type="text" value="<?= e($subject['code'] . ' - ' . $subject['name']) ?>" disabled>
                <?php if ($isEdit): ?><span class="hint">Không đổi được môn học của đề thi đã tạo.</span><?php endif; ?>
            </div>
            <div class="form-row<?= $has('title') ?>">
                <label for="title">Tên đề thi <span class="req">*</span></label>
                <input type="text" id="title" name="title" value="<?= e($title) ?>" maxlength="200" required placeholder="Ví dụ: Kiểm tra giữa kỳ - Tin học đại cương">
                <?= field_error('title') ?>
            </div>
            <div class="form-grid">
                <div class="form-row<?= $has('start_at') ?>">
                    <label for="start_at">Mở cổng thi lúc <span class="req">*</span></label>
                    <input type="datetime-local" id="start_at" name="start_at" value="<?= e($startAt) ?>" required>
                    <?= field_error('start_at') ?>
                </div>
                <div class="form-row<?= $has('end_at') ?>">
                    <label for="end_at">Đóng cổng thi lúc <span class="req">*</span></label>
                    <input type="datetime-local" id="end_at" name="end_at" value="<?= e($endAt) ?>" required>
                    <?= field_error('end_at') ?>
                </div>
                <div class="form-row<?= $has('duration_minutes') ?>">
                    <label for="duration_minutes">Thời gian làm bài (phút) <span class="req">*</span></label>
                    <input type="number" id="duration_minutes" name="duration_minutes" value="<?= e($duration) ?>" min="1" max="300" required>
                    <?= field_error('duration_minutes') ?>
                    <span class="hint">Thời gian mỗi sinh viên có sau khi bấm vào thi. Sinh viên vào thi được trong khoảng từ lúc mở đến lúc đóng cổng.</span>
                </div>
                <div class="form-row">
                    <label>Tùy chọn</label>
                    <label class="check-row"><input type="checkbox" name="shuffle" value="1" <?= $shuffle ? 'checked' : '' ?>> Đảo thứ tự câu hỏi và đáp án cho từng sinh viên</label>
                </div>
            </div>
            <div class="form-row<?= $has('description') ?>">
                <label for="description">Mô tả / dặn dò</label>
                <textarea id="description" name="description" rows="3" maxlength="1000"><?= e($desc) ?></textarea>
                <?= field_error('description') ?>
            </div>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Lớp được thi <span class="req">*</span></h2>
        <div class="panel-body<?= $has('class_ids') ?>">
            <?php if ($classes): ?>
                <div class="checklist">
                    <?php foreach ($classes as $c): ?>
                        <label class="check-row">
                            <input type="checkbox" name="class_ids[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $picked, true) ? 'checked' : '' ?>>
                            <span><strong><?= e($c['code']) ?></strong> - <?= e($c['name']) ?> <span class="muted">(<?= (int) $c['student_count'] ?> sinh viên)</span></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?= field_error('class_ids') ?>
                <span class="hint">Chỉ hiện các lớp đang học môn này<?= ''?>. Muốn thêm lớp, quản trị viên gán môn cho lớp ở mục Lớp học hoặc Môn học.</span>
            <?php else: ?>
                <p class="muted" style="margin:0">Môn này chưa được gán cho lớp nào (hoặc các lớp đều do giáo viên khác giảng dạy) nên chưa thể tạo đề thi. Quản trị viên gán môn cho lớp ở mục Lớp học hoặc Môn học.</p>
                <?= field_error('class_ids') ?>
            <?php endif; ?>
        </div>
    </section>

    <div class="form-actions">
        <button class="btn" type="submit"><?= $isEdit ? 'Lưu thay đổi' : 'Tạo đề thi' ?></button>
        <a class="btn btn-outline" href="<?= $backUrl ?>">Hủy</a>
    </div>
</form>
