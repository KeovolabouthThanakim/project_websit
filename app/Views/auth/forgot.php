<section class="login-box">
<?php if ($sent): ?>
    <div class="sent-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg></div>
    <div class="login-head">
        <h1>Kiểm tra email của bạn</h1>
        <p>Nếu tài khoản tồn tại và đã có email, hệ thống đã gửi liên kết đặt lại mật khẩu. Liên kết có hiệu lực <?= (int) $ttl ?> phút.</p>
    </div>
    <p class="muted">Không thấy email? Hãy kiểm tra thư mục thư rác, hoặc đợi 1 phút rồi yêu cầu lại.</p>
    <a class="btn btn-outline btn-block" href="<?= url('/login') ?>">Về trang đăng nhập</a>
<?php else: ?>
    <div class="login-head">
        <h1>Quên mật khẩu</h1>
        <p>Nhập tên đăng nhập hoặc email, chúng tôi sẽ gửi liên kết đặt lại mật khẩu qua email của tài khoản.</p>
    </div>
    <form method="post" action="<?= url('/forgot-password') ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <label for="identifier">Tên đăng nhập hoặc email</label>
            <input type="text" id="identifier" name="identifier" value="<?= e(old('identifier')) ?>" required autofocus autocomplete="username">
            <?php if ($adminOnly): ?><span class="hint">Chức năng này dành cho tài khoản quản trị viên.</span><?php endif; ?>
        </div>
        <button class="btn btn-block" type="submit">Gửi liên kết đặt lại</button>
    </form>
    <p class="login-foot"><a href="<?= url('/login') ?>">&larr; Quay lại đăng nhập</a><?= $adminOnly ? '<br>Giảng viên, sinh viên quên mật khẩu vui lòng liên hệ quản trị viên.' : '' ?></p>
<?php endif; ?>
</section>
