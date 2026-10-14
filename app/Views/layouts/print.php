<?php
// Khung trang in: nền trắng, không có menu. Bấm "In / Lưu PDF" rồi chọn máy in "Lưu dạng PDF" (Save as PDF) để có file PDF.
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($title ?? 'In') ?> - <?= e(system_name()) ?></title>
    <link rel="stylesheet" href="<?= asset('css/print.css') ?>">
</head>
<body>
<div class="noprint toolbar">
    <button type="button" class="tb-btn" id="print-btn">In / Lưu PDF</button>
    <a class="tb-link" href="javascript:history.back()">&laquo; Quay lại</a>
    <span class="tb-hint">Trong hộp thoại in, chọn máy in <strong>"Lưu dạng PDF"</strong> (Save as PDF) để tải file PDF. Nên tắt tùy chọn "Đầu trang và chân trang".</span>
</div>
<main class="sheet">
    <?= $content ?>
</main>
<script>
document.getElementById('print-btn').addEventListener('click', function () { window.print(); });
</script>
</body>
</html>
