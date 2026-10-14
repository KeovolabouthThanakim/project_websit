<?php
namespace App\Core;

use PDO;

/**
 * Sao lưu / khôi phục cơ sở dữ liệu bằng PHP thuần (không cần mysqldump).
 *
 * Định dạng file .sql: mỗi câu lệnh nằm trên MỘT dòng và kết thúc bằng ";" nên khôi phục được
 * cả bằng chức năng Import của phpMyAdmin. Chỉ chấp nhận các loại câu lệnh do chính hệ thống này tạo ra.
 */
class Backup
{
    public const MARKER = '-- NAU-QUIZ-BACKUP v1';
    private const BATCH = 100;

    public static function dir(): string
    {
        $dir = ROOT . '/storage/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        // Chặn truy cập trực tiếp qua web (nếu máy chủ không rewrite hết vào public/)
        $ht = $dir . '/.htaccess';
        if (is_dir($dir) && !is_file($ht)) {
            @file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\n  Order deny,allow\n  Deny from all\n</IfModule>\n");
        }
        return $dir;
    }

    /** Ghi bản sao lưu ra $write(string). Trả về số bảng đã sao lưu */
    public static function dump(callable $write): int
    {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $db = (string) App::config('db')['name'];

        $write(self::MARKER . ' | ' . date('Y-m-d H:i:s') . " | database: $db\n");
        $write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");

        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $t) {
            $q = '`' . str_replace('`', '``', (string) $t) . '`';
            $create = $pdo->query("SHOW CREATE TABLE $q")->fetch(PDO::FETCH_NUM)[1];
            $create = trim((string) preg_replace('/\s*\R\s*/', ' ', $create));
            $write("DROP TABLE IF EXISTS $q;\n$create;\n");

            $rows = $pdo->query("SELECT * FROM $q");
            $cols = null;
            $batch = [];
            foreach ($rows as $row) {
                if ($cols === null) {
                    $cols = '(' . implode(', ', array_map(fn($c) => '`' . str_replace('`', '``', $c) . '`', array_keys($row))) . ')';
                }
                $vals = [];
                foreach ($row as $v) {
                    $vals[] = $v === null ? 'NULL' : (is_int($v) ? (string) $v : $pdo->quote((string) $v));
                }
                $batch[] = '(' . implode(',', $vals) . ')';
                if (count($batch) >= self::BATCH) {
                    $write("INSERT INTO $q $cols VALUES " . implode(',', $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                $write("INSERT INTO $q $cols VALUES " . implode(',', $batch) . ";\n");
            }
            $rows->closeCursor();
        }
        $write("SET FOREIGN_KEY_CHECKS=1;\n");
        return count($tables);
    }

    /** Lưu một bản sao lưu vào storage/backups. Trả về tên file */
    public static function saveToServer(string $prefix = 'backup'): string
    {
        $name = $prefix . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2)) . '.sql';
        $path = self::dir() . '/' . $name;
        $fh = @fopen($path, 'wb');
        if (!$fh) {
            throw new \RuntimeException('Không ghi được vào thư mục storage/backups. Hãy cấp quyền ghi cho thư mục này.');
        }
        try {
            self::dump(function (string $s) use ($fh) { fwrite($fh, $s); });
        } catch (\Throwable $e) {
            fclose($fh);
            @unlink($path);
            throw $e;
        }
        fclose($fh);
        return $name;
    }

    /** Kiểm tra toàn bộ file trước khi chạy. Trả về null nếu hợp lệ, ngược lại là câu báo lỗi */
    public static function validate(string $path): ?string
    {
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            return 'Không đọc được file.';
        }
        $first = fgets($fh);
        if ($first === false || strpos($first, self::MARKER) !== 0) {
            fclose($fh);
            return 'Đây không phải file sao lưu do hệ thống này tạo ra.';
        }
        $n = 0;
        $tables = 0;
        $line = 1;
        while (($l = fgets($fh)) !== false) {
            $line++;
            $l = rtrim($l, "\r\n");
            if ($l === '' || strpos($l, '--') === 0) {
                continue;
            }
            if (substr($l, -1) !== ';' || !preg_match('/^(SET NAMES utf8mb4;|SET FOREIGN_KEY_CHECKS=[01];|DROP TABLE IF EXISTS `[^`]+`;|CREATE TABLE `|INSERT INTO `)/', $l)) {
                fclose($fh);
                return "Dòng $line có câu lệnh không được phép hoặc bị cắt dở.";
            }
            if (strpos($l, 'CREATE TABLE `') === 0) {
                $tables++;
            }
            $n++;
        }
        fclose($fh);
        if ($tables < 1) {
            return 'File không chứa bảng dữ liệu nào.';
        }
        return $n > 0 ? null : 'File rỗng.';
    }

    /** Chạy file sao lưu (đã validate). Trả về số câu lệnh đã chạy. Ném exception nếu lỗi */
    public static function restore(string $path): int
    {
        @set_time_limit(0);
        $pdo = Database::connect();
        $fh = fopen($path, 'rb');
        if (!$fh) {
            throw new \RuntimeException('Không đọc được file.');
        }
        $n = 0;
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            while (($l = fgets($fh)) !== false) {
                $l = rtrim($l, "\r\n");
                if ($l === '' || strpos($l, '--') === 0) {
                    continue;
                }
                $pdo->exec($l);
                $n++;
            }
        } finally {
            fclose($fh);
            try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (\Throwable $e) { /* bỏ qua */ }
        }
        return $n;
    }

    /** Các bản sao lưu đang lưu trên máy chủ, mới nhất trước: [['name','size','time']] */
    public static function listSaved(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/*.sql') ?: [] as $f) {
            $out[] = ['name' => basename($f), 'size' => (int) filesize($f), 'time' => (int) filemtime($f)];
        }
        usort($out, fn($a, $b) => $b['time'] <=> $a['time']);
        return $out;
    }

    /** Tên file hợp lệ trong storage/backups (chống ../) */
    public static function savedPath(string $name): ?string
    {
        if (!preg_match('/^[A-Za-z0-9_-]+\.sql$/', $name)) {
            return null;
        }
        $p = self::dir() . '/' . $name;
        return is_file($p) ? $p : null;
    }
}
