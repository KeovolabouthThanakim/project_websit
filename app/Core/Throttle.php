<?php
namespace App\Core;

/**
 * Giới hạn số lần thử (chống dò mật khẩu), lưu bằng file trong storage/throttle/ nên không cần thêm bảng CSDL.
 * Mỗi "khóa" (vd tên đăng nhập + IP) có danh sách thời điểm thất bại; chỉ đếm các lần trong cửa sổ thời gian.
 * Nếu không ghi được file thì bỏ qua (không chặn người dùng) và ghi log.
 */
class Throttle
{
    private static function dir(): string
    {
        $dir = ROOT . '/storage/throttle';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            error_log('Throttle: không tạo được ' . $dir);
        }
        return $dir;
    }

    private static function file(string $key): string
    {
        return self::dir() . '/' . sha1($key) . '.json';
    }

    /** @return int[] các thời điểm thất bại còn trong cửa sổ $window giây */
    private static function read(string $key, int $window): array
    {
        $raw = @file_get_contents(self::file($key));
        $times = $raw ? json_decode($raw, true) : [];
        $from = time() - $window;
        return is_array($times) ? array_values(array_filter($times, fn($t) => is_int($t) && $t > $from)) : [];
    }

    /** Ghi nhận một lần thất bại */
    public static function hit(string $key, int $window): void
    {
        $times = self::read($key, $window);
        $times[] = time();
        if (@file_put_contents(self::file($key), json_encode($times), LOCK_EX) === false) {
            error_log('Throttle: không ghi được file giới hạn đăng nhập');
        }
        if (random_int(1, 50) === 1) {
            self::cleanup();
        }
    }

    /** Số giây phải chờ nữa mới được thử tiếp (0 = được phép) */
    public static function retryAfter(string $key, int $limit, int $window): int
    {
        $times = self::read($key, $window);
        if (count($times) < $limit) {
            return 0;
        }
        sort($times);
        // Được thử lại khi lần thất bại cũ nhất trong nhóm "vượt ngưỡng" rơi khỏi cửa sổ
        $oldestBlocking = $times[count($times) - $limit];
        return max(1, $oldestBlocking + $window - time());
    }

    public static function clear(string $key): void
    {
        @unlink(self::file($key));
    }

    /** Xóa file cũ hơn 1 ngày */
    private static function cleanup(): void
    {
        foreach (glob(self::dir() . '/*.json') ?: [] as $f) {
            if (@filemtime($f) < time() - 86400) {
                @unlink($f);
            }
        }
    }
}
