<?php
namespace App\Core;

class App
{
    public static array $config = [];
    public static string $baseUrl = '';

    public static function run(): void
    {
        $config = require ROOT . '/config/config.php';
        $localConfigPath = ROOT . '/config/config.local.php';
        if (is_file($localConfigPath)) {
            $localConfig = require $localConfigPath;
            if (!is_array($localConfig)) {
                throw new \RuntimeException('config/config.local.php must return an array.');
            }
            $config = array_replace_recursive($config, $localConfig);
        }
        self::$config = $config;

        ini_set('display_errors', self::config('debug') ? '1' : '0');
        error_reporting(E_ALL);
        date_default_timezone_set(self::config('timezone', 'UTC'));

        // Header bảo mật cho mọi phản hồi do PHP tạo ra
        header('X-Frame-Options: SAMEORIGIN');                 // chống nhúng trang vào iframe của web khác (clickjacking)
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        if (Session::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000');  // bắt trình duyệt luôn dùng HTTPS cho tên miền này
        }

        self::$baseUrl = rtrim((string) (self::config('base_url') ?? self::detectBaseUrl()), '/');

        \App\Models\Setting::overlay();   // cài đặt trong CSDL đè lên config.php (nếu đã nhập 012_settings.sql)

        Session::start();

        $request = new Request();
        $router = new Router();
        require ROOT . '/routes/web.php';

        try {
            $router->dispatch($request);
        } catch (\Throwable $e) {
            error_log((string) $e);
            Response::abort(500, self::config('debug') ? $e->getMessage() : 'Đã xảy ra lỗi máy chủ.');
        }
    }

    public static function config(string $key, $default = null)
    {
        return self::$config[$key] ?? $default;
    }

    /**
     * Tự nhận diện thư mục gốc của site (vd "/nau-quiz") bằng cách so sánh
     * đường dẫn thật trên đĩa với DOCUMENT_ROOT và URL đang được gọi.
     */
    private static function detectBaseUrl(): string
    {
        $docRoot = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $docRoot = rtrim($docRoot, '/');
        $reqPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        $dirs = [
            str_replace('\\', '/', (string) realpath(ROOT . '/public')),
            str_replace('\\', '/', (string) realpath(ROOT)),
        ];

        foreach ($dirs as $dir) {
            if ($docRoot === '' || stripos($dir, $docRoot) !== 0) {
                continue;
            }
            $candidate = substr($dir, strlen($docRoot));
            if ($candidate === '') {
                return '';
            }
            $len = strlen($candidate);
            if (stripos($reqPath, $candidate) === 0 && (strlen($reqPath) === $len || $reqPath[$len] === '/')) {
                return $candidate;
            }
        }
        return '';
    }
}
