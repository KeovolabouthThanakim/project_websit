<?php
namespace App\Core;

class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = self::isHttps();
        $idle = max(5, (int) sec('session_idle_minutes', 120)) * 60;
        ini_set('session.use_strict_mode', '1');   // không nhận session id do client tự đặt
        ini_set('session.use_only_cookies', '1');  // không nhận session id qua URL
        ini_set('session.gc_maxlifetime', (string) $idle);
        session_name('NAUQUIZSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Quá thời gian không hoạt động thì đăng xuất (lưu đáp án khi đang thi cũng tính là hoạt động)
        if (isset($_SESSION['_seen']) && time() - (int) $_SESSION['_seen'] > $idle) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['_seen'] = time();
    }

    /** Đang chạy qua HTTPS (kể cả khi đứng sau proxy / Cloudflare báo X-Forwarded-Proto) */
    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Đổi session id (chống session fixation) - gọi sau khi đăng nhập/đăng xuất */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    // ----- Flash: dữ liệu chỉ tồn tại đến lần đọc kế tiếp -----
    public static function flash(string $key, $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function pullFlash(string $key, $default = null)
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    // ----- CSRF -----
    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token) && hash_equals(self::csrfToken(), $token);
    }
}
