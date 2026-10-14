<section class="login-box error-box">
    <div class="code"><?= (int) $code ?></div>
    <h2><?= e($title) ?></h2>
    <?php if ($message !== ''): ?><p class="muted"><?= e($message) ?></p><?php endif; ?>
    <p><a class="btn" href="<?= url('/') ?>">Về trang chủ</a></p>
</section>
