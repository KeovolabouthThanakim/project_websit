<?php
namespace App\Models;

use App\Core\Model;

/**
 * Môn học được dạy cho lớp nào (bảng "class_subjects").
 * Mỗi dòng: lớp + môn + giáo viên giảng dạy (có thể trống).
 */
class ClassSubject extends Model
{
    protected static string $table = 'class_subjects';

    /** Các môn của một lớp, kèm giáo viên giảng dạy */
    public static function forClass(int $classId): array
    {
        $stmt = self::db()->prepare(
            'SELECT s.id, s.code, s.name, s.credits, s.is_active, cs.teacher_id, t.full_name AS teacher_name
             FROM class_subjects cs
             JOIN subjects s ON s.id = cs.subject_id
             LEFT JOIN users t ON t.id = cs.teacher_id
             WHERE cs.class_id = ? ORDER BY s.code'
        );
        $stmt->execute([$classId]);
        return $stmt->fetchAll();
    }

    /** Các lớp học một môn, kèm giáo viên giảng dạy và sĩ số */
    public static function forSubject(int $subjectId): array
    {
        $stmt = self::db()->prepare(
            'SELECT c.id, c.code, c.name, c.cohort, c.teacher_id AS homeroom_id, cs.teacher_id, t.full_name AS teacher_name,
                    (SELECT COUNT(*) FROM class_students x WHERE x.class_id = c.id) AS student_count
             FROM class_subjects cs
             JOIN classes c ON c.id = cs.class_id
             LEFT JOIN users t ON t.id = cs.teacher_id
             WHERE cs.subject_id = ? ORDER BY c.code'
        );
        $stmt->execute([$subjectId]);
        return $stmt->fetchAll();
    }

    /** Môn đang sử dụng mà lớp chưa có - dùng cho ô chọn */
    public static function subjectsNotIn(int $classId): array
    {
        $stmt = self::db()->prepare(
            'SELECT id, code, name FROM subjects s
             WHERE s.is_active = 1 AND NOT EXISTS (SELECT 1 FROM class_subjects cs WHERE cs.subject_id = s.id AND cs.class_id = ?)
             ORDER BY s.code'
        );
        $stmt->execute([$classId]);
        return $stmt->fetchAll();
    }

    /** Lớp chưa học môn này - dùng cho ô chọn */
    public static function classesNotIn(int $subjectId): array
    {
        $stmt = self::db()->prepare(
            'SELECT id, code, name FROM classes c
             WHERE NOT EXISTS (SELECT 1 FROM class_subjects cs WHERE cs.class_id = c.id AND cs.subject_id = ?)
             ORDER BY c.code'
        );
        $stmt->execute([$subjectId]);
        return $stmt->fetchAll();
    }

    public static function exists(int $classId, int $subjectId): bool
    {
        $stmt = self::db()->prepare('SELECT 1 FROM class_subjects WHERE class_id = ? AND subject_id = ?');
        $stmt->execute([$classId, $subjectId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Gán môn cho lớp (hoặc đổi giáo viên nếu đã gán). Giáo viên được chọn sẽ tự được tính là
     * giáo viên phụ trách môn đó để thấy môn trong mục "Môn học của tôi".
     */
    public static function assign(int $classId, int $subjectId, ?int $teacherId): void
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO class_subjects (class_id, subject_id, teacher_id) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id)'
            )->execute([$classId, $subjectId, $teacherId]);
            if ($teacherId !== null) {
                $pdo->prepare('INSERT IGNORE INTO subject_teachers (subject_id, user_id) VALUES (?, ?)')
                    ->execute([$subjectId, $teacherId]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function unassign(int $classId, int $subjectId): bool
    {
        $stmt = self::db()->prepare('DELETE FROM class_subjects WHERE class_id = ? AND subject_id = ?');
        $stmt->execute([$classId, $subjectId]);
        return $stmt->rowCount() > 0;
    }

    /** Khi một tài khoản không còn là giáo viên: bỏ tên khỏi các lớp-môn */
    public static function forgetTeacher(int $userId): void
    {
        self::db()->prepare('UPDATE class_subjects SET teacher_id = NULL WHERE teacher_id = ?')->execute([$userId]);
    }
}
