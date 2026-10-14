<?php
form_errors();   // đọc lỗi 1 lần
$has = fn(string $f): string => isset(form_errors()[$f]) ? ' has-error' : '';
$email = old('email', (string) ($user['email'] ?? ''));
?>
<div class="page-head">
    <div>
        <h1>Hồ sơ của tôi</h1>
        <p>Xem thông tin tài khoản, đổi email và đổi mật khẩu</p>
    </div>
</div>

<?php require ROOT . '/app/Views/dashboard/_profile.php'; ?>

<section class="panel">
    <h2 class="panel-title">Email</h2>
    <div class="panel-body">
        <form method="post" action="<?= url('/profile') ?>" autocomplete="off">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="form-row<?= $has('email') ?>">
                    <label for="email">Địa chỉ email</label>
                    <input type="email" id="email" name="email" value="<?= e($email) ?>" maxlength="120">
                    <?= field_error('email') ?>
                    <span class="hint">Dùng để nhận liên kết đặt lại mật khẩu và thông báo. Để trống nếu không muốn khai báo.</span>
                </div>
            </div>
            <button class="btn" type="submit">Lưu email</button>
        </form>
    </div>
</section>

<section class="panel">
    <h2 class="panel-title">Đổi mật khẩu</h2>
    <div class="panel-body">
        <form method="post" action="<?= url('/profile/password') ?>" autocomplete="off">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="form-row<?= $has('current_password') ?>">
                    <label for="current_password">Mật khẩu hiện tại <span class="req">*</span></label>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                    <?= field_error('current_password') ?>
                </div>
                <div class="form-row<?= $has('password') ?>">
                    <label for="password">Mật khẩu mới <span class="req">*</span></label>
                    <input type="password" id="password" name="password" autocomplete="new-password" required>
                    <?= field_error('password') ?>
                    <span class="hint">Tối thiểu 8 ký tự, khác mật khẩu hiện tại.</span>
                </div>
                <div class="form-row<?= $has('password_confirm') ?>">
                    <label for="password_confirm">Nhập lại mật khẩu mới <span class="req">*</span></label>
                    <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
                    <?= field_error('password_confirm') ?>
                </div>
            </div>
            <button class="btn" type="submit">Đổi mật khẩu</button>
        </form>
    </div>
</section>
