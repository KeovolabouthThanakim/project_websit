<?php
namespace App\Models;

use App\Core\App;
use App\Core\Database;

/**
 * Cài đặt lưu trong bảng settings (cặp khóa - giá trị). Chưa nhập 012_settings.sql thì mọi hàm trả về giá trị mặc định,
 * hệ thống chạy như cũ với config.php.
 */
class Setting
{
    /** Các khóa được phép ghi */
    public const KEYS = ['school_name', 'contact_text', 'exam_rules', 'pass_score', 'partial_credit', 'semesters'];

    private static ?array $cache = null;
    private static ?bool $ready = null;

    public static function ready(): bool
    {
        self::all();
        return self::$ready === true;
    }

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                $rows = Database::connection()->query('SELECT k, v FROM settings')->fetchAll(\PDO::FETCH_KEY_PAIR);
                self::$cache = is_array($rows) ? $rows : [];
                self::$ready = true;
            } catch (\Throwable $e) {
                self::$ready = false;
            }
        }
        return self::$cache;
    }

    public static function get(string $key, $default = '')
    {
        $v = self::all()[$key] ?? null;
        return $v === null ? $default : $v;
    }

    /** Ghi nhiều khóa một lần (chỉ khóa nằm trong KEYS) */
    public static function setMany(array $values): void
    {
        $stmt = Database::connection()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
        foreach ($values as $k => $v) {
            if (in_array($k, self::KEYS, true)) {
                $stmt->execute([$k, (string) $v]);
            }
        }
        self::$cache = null;
        self::$ready = null;
    }

    /** Đưa cài đặt trong CSDL đè lên config.php (chấm điểm từng phần, học kỳ). Gọi 1 lần khi khởi động */
    public static function overlay(): void
    {
        $v = self::all();
        if (isset($v['partial_credit']) && $v['partial_credit'] !== '') {
            $exam = (array) (App::$config['exam'] ?? []);
            $exam['partial_credit'] = $v['partial_credit'] === '1';
            App::$config['exam'] = $exam;
        }
        if (!empty($v['semesters']) && ($t = self::parseTerms($v['semesters']))) {
            App::$config['semesters'] = $t;
        }
    }

    /** "HK1:8, HK2:2, HK hè:6" -> ['HK1' => 8, ...]; sai định dạng trả về null */
    public static function parseTerms(string $raw): ?array
    {
        $out = [];
        foreach (preg_split('/[\r\n,;]+/', $raw) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (!preg_match('/^(.{1,20}?)\s*[:=]\s*(\d{1,2})$/u', $part, $m)) {
                return null;
            }
            $name = trim($m[1]);
            $month = (int) $m[2];
            if ($name === '' || $month < 1 || $month > 12 || isset($out[$name]) || in_array($month, $out, true)) {
                return null;
            }
            $out[$name] = $month;
        }
        return $out && count($out) <= 6 ? $out : null;
    }

    public static function termsToString(array $terms): string
    {
        $parts = [];
        foreach ($terms as $name => $month) {
            $parts[] = $name . ':' . $month;
        }
        return implode(', ', $parts);
    }
}
