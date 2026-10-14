<?php
namespace App\Core;

class Response
{
    public static function redirect(string $path): void
    {
        header('Location: ' . url($path));
        exit;
    }

    /** Trả JSON (dùng cho các lời gọi nền như tự lưu đáp án) */
    public static function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function abort(int $code, string $message = ''): void
    {
        $titles = [
            403 => 'Bạn không có quyền truy cập',
            404 => 'Không tìm thấy trang',
            405 => 'Phương thức không được hỗ trợ',
            419 => 'Phiên làm việc hết hạn',
            500 => 'Lỗi máy chủ',
        ];
        http_response_code($code);
        View::render('errors/error', [
            'title'   => $titles[$code] ?? 'Lỗi',
            'code'    => $code,
            'message' => $message,
        ], 'layouts/auth');
        exit;
    }
}
