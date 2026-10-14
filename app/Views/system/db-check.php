<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kiểm tra kết nối CSDL</title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <style>
        .check { display:flex; gap:12px; padding:12px 0; border-bottom:1px solid var(--border); }
        .check:last-child { border-bottom:0; }
        .mark { font-weight:700; width:24px; }
        .ok .mark { color:#16a34a; } .fail .mark { color:#dc2626; }
        .detail { color:#991b1b; font-size:.9rem; margin-top:4px; word-break:break-word; }
    </style>
</head>
<body>
<main class="container">
    <h1>Kiểm tra kết nối cơ sở dữ liệu</h1>
    <div class="card">
        <?php foreach ($checks as $c): ?>
            <div class="check <?= $c['ok'] ? 'ok' : 'fail' ?>">
                <span class="mark"><?= $c['ok'] ? '✔' : '✘' ?></span>
                <div>
                    <div><?= e($c['name']) ?></div>
                    <?php if ($c['detail'] !== ''): ?><div class="detail"><?= e($c['detail']) ?></div><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="muted">Cấu hình đang dùng (config/config.php): host=<?= e($cfg['host']) ?>, port=<?= e((string) $cfg['port']) ?>,
        database=<?= e($cfg['name']) ?>, user=<?= e($cfg['user']) ?>, password=<?= $cfg['password'] === '' ? '(trống)' : '(đã đặt)' ?></p>
    <p><a href="<?= url('/') ?>">← Về trang chủ</a></p>
</main>
</body>
</html>
