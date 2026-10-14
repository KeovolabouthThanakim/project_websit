<section class="login-box">
    <div class="login-head">
        <h1>Đặt mật khẩu mới</h1>
        <p>Mật khẩu có ít nhất <?= (int) $min ?> ký tự.</p>
    </div>
    <form method="post" action="<?= url('/reset-password') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="form-row<?= isset(form_errors()['password']) ? ' has-error' : '' ?>">
            <label for="password">Mật khẩu mới</label>
            <input type="password" id="password" name="password" required autofocus autocomplete="new-password">
            <?= field_error('password') ?>
        </div>
        <div class="form-row<?= isset(form_errors()['password_confirm']) ? ' has-error' : '' ?>">
            <label for="password_confirm">Nhập lại mật khẩu mới</label>
            <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
            <?= field_error('password_confirm') ?>
        </div>
        <button class="btn btn-block" type="submit">Lưu mật khẩu mới</button>
    </form>
</section>
