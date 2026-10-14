<section class="login-box">
    <div class="login-head">
        <h1>Đăng nhập</h1>
        <p>Dành cho quản trị viên, giảng viên và sinh viên.</p>
    </div>
    <form method="post" action="<?= url('/login') ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <label for="username">Tên đăng nhập</label>
            <input type="text" id="username" name="username" value="<?= e(old('username')) ?>" required autofocus autocapitalize="off" spellcheck="false" autocomplete="<?= sec('block_autofill', true) ? 'off' : 'username' ?>">
            <span class="hint">Giảng viên, sinh viên đăng nhập bằng mã số của mình.</span>
        </div>
        <div class="form-row">
            <div class="label-row">
                <label for="password">Mật khẩu</label>
                <a class="forgot-link" href="<?= url('/forgot-password') ?>">Quên mật khẩu?</a>
            </div>
            <input type="password" id="password" name="password" required autocomplete="<?= sec('block_autofill', true) ? 'new-password' : 'current-password' ?>">
        </div>
        <button class="btn btn-block" type="submit">Đăng nhập</button>
    </form>
    <p class="login-foot">Quản trị viên quên mật khẩu có thể đặt lại qua email. Giảng viên, sinh viên vui lòng liên hệ quản trị viên. <a href="<?= url('/help') ?>">Hướng dẫn sử dụng</a></p>
</section>
