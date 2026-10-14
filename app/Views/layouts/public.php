<?php
use App\Core\Session;

$success = Session::pullFlash('success');
$error = Session::pullFlash('error');
$hasLogo = is_file(ROOT . '/public/assets/img/logo.png');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? '') !== '' ? $title . ' - ' : '') ?><?= e(system_name()) ?> - <?= e(school_short()) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap"></noscript>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body class="auth public">
<main class="auth-page">
    <div class="auth-box wide">
        <a class="auth-brand" href="<?= url('/') ?>">
            <?php if ($hasLogo): ?>
                <img class="crest-img" src="<?= asset('img/logo.png') ?>" alt="Logo">
            <?php else: ?>
                <span class="crest">NAU</span>
            <?php endif; ?>
            <span class="auth-brand-text">
                <span class="uni"><?= e(school_name()) ?></span>
                <span class="sys"><?= e(system_name()) ?></span>
            </span>
        </a>
        <?php if ($success): ?><div class="toast toast-success" role="status"><span class="toast-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span><div class="toast-body"><strong>Thành công</strong><span><?= e($success) ?></span></div></div><?php endif; ?>
        <?php if ($error): ?><div class="toast toast-error" role="alert"><span class="toast-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M12 6v7.5M12 18h.01"/></svg></span><div class="toast-body"><strong>Có lỗi xảy ra</strong><span><?= e($error) ?></span></div></div><?php endif; ?>
        <?= $content ?>
    </div>
    <footer class="auth-foot"><a href="<?= url('/help') ?>">Hướng dẫn sử dụng</a> &middot; &copy; <?= date('Y') ?> <?= e(school_name()) ?></footer>
</main>
</body>
</html>
