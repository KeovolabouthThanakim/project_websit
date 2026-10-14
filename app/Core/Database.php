<?php
namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    private static function options(): array
    {
        return [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ];
    }

    /** Tạo kết nối. $withDb = false để kết nối tới MySQL mà chưa chọn database (dùng khi chẩn đoán) */
    public static function connect(bool $withDb = true): PDO
    {
        $c = App::config('db');
        $dsn = "mysql:host={$c['host']};port={$c['port']};charset={$c['charset']}";
        if ($withDb) {
            $dsn .= ";dbname={$c['name']}";
        }
        return new PDO($dsn, $c['user'], $c['password'], self::options());
    }

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            try {
                self::$pdo = self::connect();
            } catch (PDOException $e) {
                throw new \RuntimeException(self::explain($e) . ' [' . $e->getMessage() . ']', 0, $e);
            }
        }
        return self::$pdo;
    }

    /** Giải thích lỗi kết nối bằng tiếng Việt kèm cách khắc phục */
    public static function explain(PDOException $e): string
    {
        $msg = $e->getMessage();
        $db = App::config('db');

        if (stripos($msg, 'could not find driver') !== false) {
            return 'PHP chưa bật extension pdo_mysql. Mở php.ini, bỏ dấu ; trước dòng extension=pdo_mysql rồi khởi động lại Apache.';
        }

        $code = preg_match('/\[(\d{4})\]/', $msg, $m) ? (int) $m[1] : 0;
        switch ($code) {
            case 1045:
                return 'Sai tên đăng nhập hoặc mật khẩu MySQL. Kiểm tra "user" và "password" trong config/config.php.';
            case 1049:
                return "Chưa có database \"{$db['name']}\". Hãy nhập file database/schema.sql trong phpMyAdmin.";
            case 2002:
            case 2006:
                return "Không kết nối được tới MySQL tại {$db['host']}:{$db['port']}. Kiểm tra MySQL đã bấm Start trong XAMPP chưa, và port có đúng không (mặc định 3306).";
            default:
                return 'Không kết nối được cơ sở dữ liệu.';
        }
    }
}
