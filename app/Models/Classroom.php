<?php
namespace App\Models;

use App\Core\Model;

/**
 * Lớp học (bảng "classes"). Đặt tên Classroom vì "class" là từ khóa của PHP.
 * Thành viên nằm ở bảng class_students (mỗi sinh viên chỉ thuộc 1 lớp).
 */
class Classroom extends Model
{
    protected static string $table = 'classes';
    protected static array $fillable = ['code', 'name', 'cohort', 'teacher_id'];

    private static function in(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    public static function countAll(): int
    {
        return (int) self::db()->query('SELECT COUNT(*) FROM classes')->fetchColumn();
    }

    /**
     * Điều kiện SQL: lớp $alias mà giáo viên được XEM - lớp mình chủ nhiệm, lớp mình giảng dạy một môn,
     * hoặc lớp có học một môn mình phụ trách. Cần 3 tham số (cùng id giáo viên).
     */
    private static function visibleTo(string $alias = 'c'): string
    {
        return "($alias.teacher_id = ?
                 OR EXISTS (SELECT 1 FROM class_subjects v WHERE v.class_id = $alias.id
                            AND (v.teacher_id = ? OR EXISTS (SELECT 1 FROM subject_teachers st WHERE st.subject_id = v.subject_id AND st.user_id = ?))))";
    }

    /** Giáo viên có quyền xem lớp này không (chủ nhiệm / giảng dạy / phụ trách môn của lớp) */
    public static function canView(int $classId, int $teacherId): bool
    {
        $stmt = self::db()->prepare('SELECT 1 FROM classes c WHERE c.id = ? AND ' . self::visibleTo() . ' LIMIT 1');
        $stmt->execute([$classId, $teacherId, $teacherId, $teacherId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Số lớp và tổng số sinh viên (toàn bộ, không phụ thuộc bộ lọc / trang).
     * $teacherId != null: chỉ tính các lớp giáo viên đó được xem.
     */
    public static function totals(?int $teacherId = null): array
    {
        $sql = 'SELECT COUNT(DISTINCT c.id) AS classes, COUNT(DISTINCT cs.user_id) AS students
                FROM classes c LEFT JOIN class_students cs ON cs.class_id = c.id';
        $params = [];
        if ($teacherId !== null) {
            $sql .= ' WHERE ' . self::visibleTo();
            $params = [$teacherId, $teacherId, $teacherId];
        }
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        $r = $stmt->fetch();
        return ['classes' => (int) $r['classes'], 'students' => (int) $r['students']];
    }

    /** Tìm kiếm + phân trang. $teacherId != null: chỉ lấy các lớp giáo viên đó được xem (is_homeroom = 1 nếu là chủ nhiệm) */
    public static function search(string $q, int $page, int $perPage = 10, ?int $teacherId = null): array
    {
        $where = [];
        $params = [];
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $where[] = '(c.code LIKE ? OR c.name LIKE ? OR c.cohort LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        if ($teacherId !== null) {
            $where[] = self::visibleTo();
            array_push($params, $teacherId, $teacherId, $teacherId);
        }
        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = self::db()->prepare("SELECT COUNT(*) FROM classes c $sqlWhere");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = self::db()->prepare(
            "SELECT c.id, c.code, c.name, c.cohort, c.teacher_id, t.full_name AS teacher_name,
                    " . ($teacherId !== null ? '(c.teacher_id = ' . (int) $teacherId . ')' : '1') . " AS is_homeroom,
                    (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id = c.id) AS student_count,
                    (SELECT COUNT(*) FROM class_subjects x WHERE x.class_id = c.id) AS subject_count
             FROM classes c LEFT JOIN users t ON t.id = c.teacher_id
             $sqlWhere ORDER BY c.code LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** Một lớp kèm tên giáo viên chủ nhiệm */
    public static function withTeacher(int $id): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT c.*, t.full_name AS teacher_name, t.username AS teacher_username
             FROM classes c LEFT JOIN users t ON t.id = c.teacher_id WHERE c.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function codeTaken(string $code, ?int $exceptId = null): bool
    {
        $stmt = self::db()->prepare('SELECT 1 FROM classes WHERE code = ? AND id <> ? LIMIT 1');
        $stmt->execute([$code, $exceptId ?? 0]);
        return (bool) $stmt->fetchColumn();
    }

    /** Giáo viên đang hoạt động, dùng cho ô chọn giáo viên chủ nhiệm */
    public static function teachers(): array
    {
        return self::db()->query(
            "SELECT id, username, full_name FROM users WHERE role = 'teacher' AND is_active = 1 ORDER BY full_name"
        )->fetchAll();
    }

    public static function isActiveTeacher(int $userId): bool
    {
        $stmt = self::db()->prepare("SELECT 1 FROM users WHERE id = ? AND role = 'teacher' AND is_active = 1");
        $stmt->execute([$userId]);
        return (bool) $stmt->fetchColumn();
    }

    // ----- Sinh viên trong lớp -----

    public static function students(int $classId): array
    {
        $stmt = self::db()->prepare(
            'SELECT u.id, u.username, ' . User::codeCol() . ', u.full_name, u.email, u.is_active, cs.created_at AS joined_at
             FROM class_students cs JOIN users u ON u.id = cs.user_id
             WHERE cs.class_id = ? ORDER BY u.username'
        );
        $stmt->execute([$classId]);
        return $stmt->fetchAll();
    }

    public static function studentCount(int $classId): int
    {
        $stmt = self::db()->prepare('SELECT COUNT(*) FROM class_students WHERE class_id = ?');
        $stmt->execute([$classId]);
        return (int) $stmt->fetchColumn();
    }

    /** Sinh viên (đang hoạt động) chưa thuộc lớp nào - dùng cho danh sách chọn */
    public static function unassignedStudents(int $limit = 500): array
    {
        $limit = max(1, $limit);
        return self::db()->query(
            "SELECT u.id, u.username, " . User::codeCol() . ", u.full_name FROM users u
             WHERE u.role = 'student' AND u.is_active = 1
               AND NOT EXISTS (SELECT 1 FROM class_students cs WHERE cs.user_id = u.id)
             ORDER BY u.username LIMIT $limit"
        )->fetchAll();
    }

    /** Lớp của một sinh viên (kèm giáo viên chủ nhiệm) hoặc null */
    public static function ofStudent(int $userId): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT c.id, c.code, c.name, t.full_name AS teacher_name
             FROM class_students cs
             JOIN classes c ON c.id = cs.class_id
             LEFT JOIN users t ON t.id = c.teacher_id
             WHERE cs.user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    /** [user_id => ['class_id' => .., 'code' => ..]] cho nhiều sinh viên cùng lúc */
    public static function membershipMap(array $userIds): array
    {
        if (!$userIds) {
            return [];
        }
        $stmt = self::db()->prepare(
            'SELECT cs.user_id, c.id AS class_id, c.code FROM class_students cs
             JOIN classes c ON c.id = cs.class_id WHERE cs.user_id IN (' . self::in($userIds) . ')'
        );
        $stmt->execute(array_values($userIds));
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['user_id']] = $row;
        }
        return $map;
    }

    public static function usersByIds(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $stmt = self::db()->prepare('SELECT id, username, full_name, role FROM users WHERE id IN (' . self::in($ids) . ')');
        $stmt->execute(array_values($ids));
        return $stmt->fetchAll();
    }

    public static function usersByUsernames(array $names): array
    {
        if (!$names) {
            return [];
        }
        // Khớp theo tên đăng nhập hoặc mã sinh viên
        if (User::hasStudentCode()) {
            $stmt = self::db()->prepare('SELECT id, username, student_code, full_name, role FROM users WHERE username IN (' . self::in($names) . ') OR student_code IN (' . self::in($names) . ')');
            $stmt->execute(array_merge(array_values($names), array_values($names)));
            return $stmt->fetchAll();
        }
        $stmt = self::db()->prepare('SELECT id, username, full_name, role FROM users WHERE username IN (' . self::in($names) . ')');
        $stmt->execute(array_values($names));
        return $stmt->fetchAll();
    }

    /** [mã lớp viết thường => id] cho các mã lớp truyền vào (không phân biệt hoa thường) */
    public static function idsByCodes(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(array_map('strval', $codes), 'strlen')));
        if (!$codes) {
            return [];
        }
        $stmt = self::db()->prepare('SELECT id, code FROM classes WHERE code IN (' . self::in($codes) . ')');
        $stmt->execute($codes);
        $map = [];
        foreach ($stmt->fetchAll() as $r) {
            $map[strtolower($r['code'])] = (int) $r['id'];
        }
        return $map;
    }

    /** Thêm nhiều sinh viên vào lớp trong 1 transaction. Trả về số người thực sự được thêm */
    public static function addStudents(int $classId, array $userIds, ?int $addedBy): int
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT IGNORE INTO class_students (class_id, user_id, added_by) VALUES (?, ?, ?)');
            $added = 0;
            foreach ($userIds as $uid) {
                $stmt->execute([$classId, $uid, $addedBy]);
                $added += $stmt->rowCount();
            }
            $pdo->commit();
            return $added;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function removeStudent(int $classId, int $userId): bool
    {
        $stmt = self::db()->prepare('DELETE FROM class_students WHERE class_id = ? AND user_id = ?');
        $stmt->execute([$classId, $userId]);
        return $stmt->rowCount() > 0;
    }

    /** Khi một tài khoản không còn là sinh viên: cho rời lớp */
    public static function forgetStudent(int $userId): void
    {
        self::db()->prepare('DELETE FROM class_students WHERE user_id = ?')->execute([$userId]);
    }

    /** Khi một tài khoản không còn là giáo viên: bỏ phân công chủ nhiệm */
    public static function clearTeacher(int $userId): void
    {
        self::db()->prepare('UPDATE classes SET teacher_id = NULL WHERE teacher_id = ?')->execute([$userId]);
    }
}