<?php
form_errors();
$has = fn(string $f): string => isset(form_errors()[$f]) ? ' has-error' : '';
$v = fn(string $k) => old($k, (string) $values[$k]);
$partial = old('partial_credit', $values['partial_credit'] ? '1' : '0') === '1';
?>
<div class="page-head">
    <div>
        <h1>Cài đặt hệ thống</h1>
        <p>Thông tin trường, cách chấm điểm, học kỳ và nội dung trang hướng dẫn</p>
    </div>
</div>

<?php if (!$ready): ?>
    <div class="panel"><div class="panel-body">Chưa bật cài đặt trên giao diện. Hãy nhập file <code>database/012_settings.sql</code> vào cơ sở dữ liệu rồi tải lại trang này. Trong lúc đó hệ thống vẫn dùng giá trị trong <code>config/config.php</code>.</div></div>
<?php endif; ?>

<form method="post" action="<?= url('/admin/settings') ?>" enctype="multipart/form-data" autocomplete="off">
    <?= csrf_field() ?>

    <section class="panel">
        <h2 class="panel-title">Thông tin trường</h2>
        <div class="panel-body">
            <div class="form-grid">
                <div class="form-row<?= $has('school_name') ?>">
                    <label for="school_name">Tên trường</label>
                    <input type="text" id="school_name" name="school_name" value="<?= e($v('school_name')) ?>" maxlength="120" placeholder="Trường Đại học Nghệ An">
                    <?= field_error('school_name') ?>
                    <span class="hint">Hiện ở đầu trang, chân trang, bảng điểm in và email. Để trống để dùng tên mặc định.</span>
                </div>
                <div class="form-row<?= $has('logo') ?>">
                    <label for="logo">Logo (PNG)</label>
                    <?php if ($hasLogo): ?><img class="settings-logo" src="<?= asset('img/logo.png') ?>" alt="Logo hiện tại"><?php endif; ?>
                    <input type="file" id="logo" name="logo" accept="image/png" <?= $logoOk ? '' : 'disabled' ?>>
                    <?= field_error('logo') ?>
                    <span class="hint"><?= $logoOk ? 'PNG vuông, tối đa 1 MB và 1024x1024 điểm ảnh.' : 'Thư mục public/assets/img chưa cho phép ghi nên chưa đổi được logo.' ?></span>
                    <?php if ($hasLogo): ?><label class="check-row"><input type="checkbox" name="remove_logo" value="1"> Xóa logo hiện tại</label><?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Chấm điểm và học kỳ</h2>
        <div class="panel-body">
            <div class="form-grid">
                <div class="form-row<?= $has('pass_score') ?>">
                    <label for="pass_score">Điểm đạt (thang 10)</label>
                    <input type="text" id="pass_score" name="pass_score" value="<?= e($v('pass_score')) ?>" inputmode="decimal" required>
                    <?= field_error('pass_score') ?>
                    <span class="hint">Dùng tính tỷ lệ đạt trong thống kê và báo cáo. Đổi giá trị này tính lại mọi thống kê cũ, không đổi điểm đã chấm.</span>
                </div>
                <div class="form-row<?= $has('semesters') ?>">
                    <label for="semesters">Học kỳ (tên:tháng bắt đầu)</label>
                    <input type="text" id="semesters" name="semesters" value="<?= e($v('semesters')) ?>" required>
                    <?= field_error('semesters') ?>
                    <span class="hint">Ví dụ <code>HK1:8, HK2:2, HK hè:6</code>. Năm học bắt đầu ở tháng của học kỳ đầu tiên. Dùng để gom báo cáo theo học kỳ.</span>
                </div>
            </div>
            <div class="form-row">
                <label class="check-row">
                    <input type="checkbox" name="partial_credit" value="1" <?= $partial ? 'checked' : '' ?>>
                    Chấm từng phần cho câu nhiều đáp án đúng (bỏ chọn: phải chọn đúng và đủ mới có điểm)
                </label>
                <span class="hint">Áp dụng cho bài nộp từ sau khi lưu. Câu một đáp án đúng luôn chấm đúng / sai.</span>
            </div>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Nội dung trang hướng dẫn</h2>
        <div class="panel-body">
            <div class="form-row<?= $has('exam_rules') ?>">
                <label for="exam_rules">Quy chế thi</label>
                <textarea id="exam_rules" name="exam_rules" rows="7" maxlength="4000"><?= e($v('exam_rules')) ?></textarea>
                <?= field_error('exam_rules') ?>
                <span class="hint">Mỗi dòng là một đoạn. Hiện trong trang Hướng dẫn sử dụng. Để trống nếu chưa có.</span>
            </div>
            <div class="form-row<?= $has('contact_text') ?>">
                <label for="contact_text">Thông tin liên hệ hỗ trợ</label>
                <textarea id="contact_text" name="contact_text" rows="3" maxlength="500"><?= e($v('contact_text')) ?></textarea>
                <?= field_error('contact_text') ?>
                <span class="hint">Email, số điện thoại, phòng làm việc của người quản trị.</span>
            </div>
        </div>
    </section>

    <p class="muted">Cấu hình email (SMTP), kết nối cơ sở dữ liệu và bảo mật vẫn đặt trong <code>config/config.local.php</code> vì chứa mật khẩu.</p>

    <div class="form-actions">
        <button class="btn" type="submit" <?= $ready ? '' : 'disabled' ?>>Lưu cài đặt</button>
    </div>
</form>
