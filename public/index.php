<?php
// Front controller: mọi request đi qua file này
declare(strict_types=1);

// Khi chạy bằng "php -S", trả file tĩnh (css, js, ảnh) trực tiếp
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file) && $file !== __FILE__) {
        return false;
    }
}

define('ROOT', dirname(__DIR__));
require ROOT . '/app/bootstrap.php';

App\Core\App::run();
