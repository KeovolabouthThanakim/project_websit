<?php
namespace App\Models;

use App\Core\Model;

/**
 * Môn học (bảng "subjects") và giáo viên phụ trách (bảng "subject_teachers").
 */
class Subject extends Model
{
    protected static string $table = 'subjects';
    protected static array $fillable = ['code', 'name', 'credits', 'description', 'is_active'];

    private static function in(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    public static function countAll(): int
    {
        return (int) self::db()->query('SELECT COUNT(*) FROM subjects')->fetchColumn();
    }

    /**
     * Tìm kiếm + lọc trạng thái + phân trang.
     * $status: '' (tất cả) | 'active' | 'inactive'.
     * $teacherId != null: chỉ lấy các môn mà giáo viên đó được phân công.
     */
    public static function search(string $q, string $status, int $page, int $perPage = 10, ?int $teacherId = null): array
    {
        $where = [];
        $params = [];
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $where[] = '(s.code LIKE ? OR s.name LIKE ?)';
            array_push($params, $like, $like);
        }
        if ($status === 'active') {
            $where[] = 's.is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 's.is_active = 0';
        }
        if ($teacherId !== null) {
            $where[] = '(EXISTS (SELECT 1 FROM subject_teachers x WHERE x.subject_id = s.id AND x.user_id = ?)
                         OR EXISTS (SELECT 1 FROM class_subjects y WHERE y.subject_id = s.id AND y.teacher_id = ?))';
            array_push($params, $teacherId, $teacherId);
        }
        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = self::db()->prepare("SELECT COUNT(*) FROM subjects s $sqlWhere");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = self::db()->prepare(
            "SELECT s.id, s.code, s.name, s.credits, s.is_active,
                    (SELECT COUNT(*) FROM subject_teachers st WHERE st.subject_id = s.id) AS teacher_count,
                    (SELECT GROUP_CONCAT(u.full_name ORDER BY u.full_name SEPARATOR ', ')
                       FROM subject_teachers st JOIN users u ON u.id = st.user_id
                      WHERE st.subject_id = s.id) AS teacher_names,
                    (SELECT COUNT(*) FROM class_subjects cs WHERE cs.subject_id = s.id) AS class_count,
                    (SELECT GROUP_CONCAT(c.code ORDER BY c.code SEPARATOR ', ')
                       FROM class_subjects cs JOIN classes c ON c.id = cs.class_id
                      WHERE cs.subject_id = s.id) AS class_codes
             FROM subjects s $sqlWhere ORDER BY s.code LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public static function codeTaken(string $code, ?int $exceptId = null): bool
    {
        $stmt = self::db()->prepare('SELECT 1 FROM subjects WHERE code = ? AND id <> ? LIMIT 1');
        $stmt->execute([$code, $exceptId ?? 0]);
        return (bool) $stmt->fetchColumn();
    }

    // ----- Giáo viên phụ trách -----

    public static function teachersOf(int $subjectId): array
    {
        $stmt = self::db()->prepare(
            'SELECT u.id, u.username, u.full_name, u.email, u.is_active
             FROM subject_teachers st JOIN users u ON u.id = st.user_id
             WHERE st.subject_id = ? ORDER BY u.full_name'
        );
        $stmt->execute([$subjectId]);
        return $stmt->fetchAll();
    }

    public static function isAssigned(int $subjectId, int $userId): bool
    {
        $stmt = self::db()->prepare(
            'SELECT 1 FROM subject_teachers WHERE subject_id = ? AND user_id = ?
             UNION SELECT 1 FROM class_subjects WHERE subject_id = ? AND teacher_id = ?'
        );
        $stmt->execute([$subjectId, $userId, $subjectId, $userId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Giáo viên để hiện trong form: mọi giáo viên đang hoạt động
     * + giáo viên đã được phân công cho môn này (kể cả khi đã bị khóa, để quản trị viên còn thấy).
     */
    public static function teacherChoices(?int $subjectId): array
    {
        $stmt = self::db()->prepare(
            "SELECT id, username, full_name, is_active FROM users
             WHERE role = 'teacher'
               AND (is_active = 1 OR id IN (SELECT user_id FROM subject_teachers WHERE subject_id = ?))
             ORDER BY full_name"
        );
        $stmt->execute([$subjectId ?? 0]);
        return $stmt->fetchAll();
    }

    /** Trong danh sách id truyền vào, chỉ giữ lại những id thật sự là giáo viên */
    public static function validTeacherIds(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $stmt = self::db()->prepare("SELECT id FROM users WHERE role = 'teacher' AND id IN (" . self::in($ids) . ')');
        $stmt->execute(array_values($ids));
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** Lưu môn học + danh sách giáo viên phụ trách trong 1 transaction. Trả về id môn học */
    public static function saveWithTeachers(?int $id, array $data, array $teacherIds): int
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            if ($id === null) {
                $id = self::create($data);
            } else {
                self::update($id, $data);
            }
            self::syncTeachers($id, $teacherIds);
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function syncTeachers(int $subjectId, array $teacherIds): void
    {
        $pdo = self::db();
        if ($teacherIds) {
            $stmt = $pdo->prepare('DELETE FROM subject_teachers WHERE subject_id = ? AND user_id NOT IN (' . self::in($teacherIds) . ')');
            $stmt->execute([$subjectId, ...array_values($teacherIds)]);
        } else {
            $pdo->prepare('DELETE FROM subject_teachers WHERE subject_id = ?')->execute([$subjectId]);
        }
        $ins = $pdo->prepare('INSERT IGNORE INTO subject_teachers (subject_id, user_id) VALUES (?, ?)');
        foreach ($teacherIds as $uid) {
            $ins->execute([$subjectId, $uid]);
        }
    }

    /** Khi một tài khoản không còn là giáo viên: bỏ mọi phân công môn học */
    public static function forgetTeacher(int $userId): void
    {
        self::db()->prepare('DELETE FROM subject_teachers WHERE user_id = ?')->execute([$userId]);
    }
}