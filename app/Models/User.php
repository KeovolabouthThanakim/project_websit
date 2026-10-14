<?php
namespace App\Models;

use App\Core\Model;

class User extends Model
{
    public const ROLES = ['admin', 'teacher', 'student'];

    protected static string $table = 'users';
    protected static array $fillable = [
        'username', 'student_code', 'password_hash', 'full_name', 'email', 'date_of_birth', 'role', 'is_active',
    ];

    /**
     * CSDL đã có cột ngày sinh chưa (database/014_users_date_of_birth.sql)? Kết quả nhớ trong 1 request.
     * Chưa chạy file 014 thì các trang vẫn chạy bình thường, chỉ là ẩn ô ngày sinh thay vì báo lỗi SQL.
     */
    public static function hasDob(): bool
    {
        static $has = null;
        if ($has === null) {
            try {
                $has = (bool) self::db()->query("SHOW COLUMNS FROM users LIKE 'date_of_birth'")->fetch();
            } catch (\PDOException $e) {
                $has = false;
            }
        }
        return $has;
    }

    /**
     * CSDL đã có cột mã sinh viên chưa (database/015_users_student_code.sql)? Kết quả nhớ trong 1 request.
     * Chưa chạy file 015 thì các trang vẫn chạy bình thường, chỉ là ẩn ô mã sinh viên thay vì báo lỗi SQL.
     */
    public static function hasStudentCode(): bool
    {
        static $has = null;
        if ($has === null) {
            try {
                $has = (bool) self::db()->query("SHOW COLUMNS FROM users LIKE 'student_code'")->fetch();
            } catch (\PDOException $e) {
                $has = false;
            }
        }
        return $has;
    }

    /** Cột mã sinh viên để đưa vào SELECT (CSDL chưa có cột thì trả NULL AS student_code, không báo lỗi SQL) */
    public static function codeCol(string $alias = 'u'): string
    {
        return self::hasStudentCode() ? $alias . '.student_code' : 'NULL AS student_code';
    }

    public static function findByUsername(string $username): ?array
    {
        return self::findBy('username', $username);
    }

    /** Đếm số người dùng theo vai trò: ['admin' => 1, 'teacher' => 1, 'student' => 2] */
    public static function countByRole(): array
    {
        $rows = self::db()->query('SELECT role, COUNT(*) AS total FROM users GROUP BY role')->fetchAll();
        $counts = ['admin' => 0, 'teacher' => 0, 'student' => 0];
        foreach ($rows as $row) {
            $counts[$row['role']] = (int) $row['total'];
        }
        return $counts;
    }

    public static function countInactive(): int
    {
        return (int) self::db()->query('SELECT COUNT(*) FROM users WHERE is_active = 0')->fetchColumn();
    }

    /** Những tài khoản tạo gần đây nhất (không lấy password_hash) */
    public static function recent(int $limit = 5): array
    {
        $limit = max(1, $limit);
        return self::db()->query(
            "SELECT id, username, full_name, role, created_at FROM users ORDER BY id DESC LIMIT $limit"
        )->fetchAll();
    }

    /** Tìm kiếm + lọc + phân trang. Không trả về password_hash */
    public static function search(string $q, string $role, int $page, int $perPage = 10): array
    {
        $where = [];
        $params = [];

        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            if (self::hasStudentCode()) {
                $where[] = '(u.username LIKE ? OR u.student_code LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
                array_push($params, $like, $like, $like, $like);
            } else {
                $where[] = '(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
                array_push($params, $like, $like, $like);
            }
        }
        if (in_array($role, self::ROLES, true)) {
            $where[] = 'u.role = ?';
            $params[] = $role;
        }
        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = self::db()->prepare("SELECT COUNT(*) FROM users u $sqlWhere");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = self::db()->prepare(
            "SELECT u.id, u.username, " . (self::hasStudentCode() ? 'u.student_code' : 'NULL AS student_code') . ", u.full_name, u.email, " . (self::hasDob() ? 'u.date_of_birth' : 'NULL AS date_of_birth') . ", u.role, u.is_active, u.created_at, c.code AS class_code
             FROM users u
             LEFT JOIN class_students cs ON cs.user_id = u.id
             LEFT JOIN classes c ON c.id = cs.class_id
             $sqlWhere ORDER BY u.id LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** Kiểm tra username/email đã có người dùng khác sử dụng chưa */
    public static function taken(string $column, string $value, ?int $exceptId = null): bool
    {
        if (!in_array($column, ['username', 'email', 'student_code'], true)) {
            throw new \InvalidArgumentException('Cột không hợp lệ');
        }
        $stmt = self::db()->prepare("SELECT 1 FROM users WHERE $column = ? AND id <> ? LIMIT 1");
        $stmt->execute([$value, $exceptId ?? 0]);
        return (bool) $stmt->fetchColumn();
    }
}