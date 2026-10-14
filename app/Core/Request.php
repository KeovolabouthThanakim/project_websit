<?php
namespace App\Core;

class Request
{
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /** Đường dẫn đã bỏ thư mục gốc: /nau-quiz/login -> /login */
    public function path(): string
    {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base = App::$baseUrl;
        if ($base !== '' && stripos($path, $base) === 0) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . trim($path, '/');
        return $path;
    }

    /** Toàn bộ dữ liệu POST */
    public function all(): array
    {
        return $_POST;
    }

    /** Lấy dữ liệu từ POST, nếu không có thì từ GET */
    public function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }
}
