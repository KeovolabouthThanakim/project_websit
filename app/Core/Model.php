<?php
namespace App\Core;

use PDO;

/**
 * Model gốc: các thao tác CRUD cơ bản. Model con chỉ cần khai báo
 * $table và $fillable (danh sách cột được phép ghi).
 * Dữ liệu trả về là mảng kết hợp (array).
 */
abstract class Model
{
    protected static string $table = '';
    protected static array $fillable = [];

    protected static function db(): PDO
    {
        return Database::connection();
    }

    /** Chỉ cho phép tên cột dạng chữ thường + gạch dưới (chống SQL injection qua tên cột) */
    private static function column(string $name): string
    {
        if (!preg_match('/^[a-z_]+$/', $name)) {
            throw new \InvalidArgumentException("Tên cột không hợp lệ: $name");
        }
        return $name;
    }

    public static function find(int $id): ?array
    {
        return self::findBy('id', $id);
    }

    public static function findBy(string $column, $value): ?array
    {
        $sql = 'SELECT * FROM ' . static::$table . ' WHERE ' . self::column($column) . ' = ? LIMIT 1';
        $stmt = static::db()->prepare($sql);
        $stmt->execute([$value]);
        return $stmt->fetch() ?: null;
    }

    public static function all(string $orderBy = 'id'): array
    {
        $sql = 'SELECT * FROM ' . static::$table . ' ORDER BY ' . self::column($orderBy);
        return static::db()->query($sql)->fetchAll();
    }

    public static function create(array $data): int
    {
        $data = array_intersect_key($data, array_flip(static::$fillable));
        $cols = array_map([self::class, 'column'], array_keys($data));
        $sql = 'INSERT INTO ' . static::$table . ' (' . implode(', ', $cols) . ') VALUES ('
             . implode(', ', array_fill(0, count($cols), '?')) . ')';
        static::db()->prepare($sql)->execute(array_values($data));
        return (int) static::db()->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $data = array_intersect_key($data, array_flip(static::$fillable));
        $sets = array_map(fn($c) => self::column($c) . ' = ?', array_keys($data));
        $sql = 'UPDATE ' . static::$table . ' SET ' . implode(', ', $sets) . ' WHERE id = ?';
        return static::db()->prepare($sql)->execute([...array_values($data), $id]);
    }

    public static function delete(int $id): bool
    {
        return static::db()->prepare('DELETE FROM ' . static::$table . ' WHERE id = ?')->execute([$id]);
    }
}
