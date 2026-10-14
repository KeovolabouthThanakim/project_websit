<?php /* Trường dùng chung của form cấp quyền: thời hạn quyền, phút cộng thêm, ghi chú */ ?>
<div class="form-grid">
    <div class="form-row">
        <label for="valid_hours">Quyền có hiệu lực (giờ) <span class="req">*</span></label>
        <input type="number" id="valid_hours" name="valid_hours" min="1" max="<?= (int) $maxHours ?>" value="<?= (int) $defHours ?>" required>
        <span class="hint">Sinh viên phải bấm vào thi trong khoảng này.</span>
    </div>
    <div class="form-row">
        <label for="extra_minutes">Cộng thêm thời gian (phút)</label>
        <input type="number" id="extra_minutes" name="extra_minutes" min="0" max="<?= (int) $maxExtra ?>" value="0">
        <span class="hint">Cộng vào <?= (int) $exam['duration_minutes'] ?> phút làm bài (để 0 nếu không cần).</span>
    </div>
</div>
<div class="form-row">
    <label for="note">Lý do / ghi chú</label>
    <input type="text" id="note" name="note" maxlength="255" placeholder="Ví dụ: rớt mạng lúc 9h15, có xác nhận của giám thị">
</div>
