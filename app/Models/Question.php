<?php
namespace App\Models;

use App\Core\Model;

/**
 * Ngân hàng câu hỏi: bảng "questions" và các đáp án "question_options".
 * Admin quản lý mọi câu hỏi; giáo viên chỉ quản lý câu hỏi của các môn mình được phân công.
 */
class Question extends Model
{
    protected static string $table = 'questions';
    protected static array $fillable = ['subject_id', 'created_by', 'content', 'image', 'explanation', 'difficulty', 'is_active'];

    public const DIFFICULTIES = ['easy' => 'Dễ', 'medium' => 'Trung bình', 'hard' => 'Khó'];
    public const MIN_OPTIONS = 2;
    public const MAX_OPTIONS = 6;

    /** Điều kiện SQL: môn học $alias mà giáo viên được phân công (phụ trách hoặc giảng dạy ở một lớp) */
    private static function teacherScope(string $alias): string
    {
        return "(EXISTS (SELECT 1 FROM subject_teachers x WHERE x.subject_id = $alias.id AND x.user_id = ?)
                 OR EXISTS (SELECT 1 FROM class_subjects y WHERE y.subject_id = $alias.id AND y.teacher_id = ?))";
    }

    public static function countAll(): int
    {
        return (int) self::db()->query('SELECT COUNT(*) FROM questions')->fetchColumn();
    }

    /** Số câu hỏi của một môn */
    public static function countBySubject(int $subjectId): int
    {
        $stmt = self::db()->prepare('SELECT COUNT(*) FROM questions WHERE subject_id = ?');
        $stmt->execute([$subjectId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Tìm kiếm + lọc + phân trang.
     * $f: q (từ khóa), subject_id, difficulty, status ('active'|'inactive'). $teacherId != null: chỉ môn của giáo viên đó.
     */
    public static function search(array $f, int $page, int $perPage = 10, ?int $teacherId = null): array
    {
        $where = [];
        $params = [];
        if (($f['q'] ?? '') !== '') {
            $where[] = 'q.content LIKE ?';
            $params[] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['q']) . '%';
        }
        if (($f['subject_id'] ?? 0) > 0) {
            $where[] = 'q.subject_id = ?';
            $params[] = (int) $f['subject_id'];
        }
        if (isset(self::DIFFICULTIES[$f['difficulty'] ?? ''])) {
            $where[] = 'q.difficulty = ?';
            $params[] = $f['difficulty'];
        }
        if (($f['status'] ?? '') === 'active') {
            $where[] = 'q.is_active = 1';
        } elseif (($f['status'] ?? '') === 'inactive') {
            $where[] = 'q.is_active = 0';
        }
        if ($teacherId !== null) {
            $where[] = self::teacherScope('s');
            array_push($params, $teacherId, $teacherId);
        }
        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = self::db()->prepare("SELECT COUNT(*) FROM questions q JOIN subjects s ON s.id = q.subject_id $sqlWhere");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = self::db()->prepare(
            "SELECT q.id, q.content, q.image, q.difficulty, q.is_active, q.created_at,
                    s.code AS subject_code, s.name AS subject_name, u.full_name AS author_name,
                    (SELECT COUNT(*) FROM question_options o WHERE o.question_id = q.id) AS option_count,
                    (SELECT COUNT(*) FROM question_options o WHERE o.question_id = q.id AND o.is_correct = 1) AS correct_count
             FROM questions q
             JOIN subjects s ON s.id = q.subject_id
             LEFT JOIN users u ON u.id = q.created_by
             $sqlWhere ORDER BY q.id DESC LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** Một câu hỏi kèm thông tin môn học và người soạn */
    public static function detail(int $id): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT q.*, s.code AS subject_code, s.name AS subject_name, s.is_active AS subject_active, u.full_name AS author_name
             FROM questions q JOIN subjects s ON s.id = q.subject_id LEFT JOIN users u ON u.id = q.created_by
             WHERE q.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function options(int $questionId): array
    {
        $stmt = self::db()->prepare('SELECT id, content, is_correct, position FROM question_options WHERE question_id = ? ORDER BY position, id');
        $stmt->execute([$questionId]);
        return $stmt->fetchAll();
    }

    /**
     * Môn học để chọn trong bộ lọc / form. $onlyActive: chỉ môn đang sử dụng (form thêm mới),
     * nhưng luôn giữ lại $keepId (môn hiện tại của câu hỏi đang sửa).
     */
    public static function subjectChoices(?int $teacherId, bool $onlyActive, ?int $keepId = null): array
    {
        $where = [];
        $params = [];
        if ($onlyActive) {
            $where[] = '(s.is_active = 1 OR s.id = ?)';
            $params[] = $keepId ?? 0;
        }
        if ($teacherId !== null) {
            $where[] = self::teacherScope('s');
            array_push($params, $teacherId, $teacherId);
        }
        $stmt = self::db()->prepare('SELECT s.id, s.code, s.name, s.is_active FROM subjects s ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY s.code');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Khóa so sánh nội dung câu hỏi: chữ thường, gộp khoảng trắng (để phát hiện câu trùng khi nhập hàng loạt) */
    public static function contentKey(string $content): string
    {
        $c = function_exists('mb_strtolower') ? mb_strtolower($content, 'UTF-8') : strtolower($content);
        return trim((string) preg_replace('/\s+/u', ' ', $c));
    }

    /** [khóa nội dung => true] của mọi câu hỏi trong môn - dùng để bỏ qua câu trùng khi nhập hàng loạt */
    public static function contentKeys(int $subjectId): array
    {
        $stmt = self::db()->prepare('SELECT content FROM questions WHERE subject_id = ?');
        $stmt->execute([$subjectId]);
        $keys = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $c) {
            $keys[self::contentKey((string) $c)] = true;
        }
        return $keys;
    }

    /**
     * Thêm nhiều câu hỏi cùng lúc trong 1 transaction (hoặc nhập hết, hoặc không câu nào).
     * $items: [['content', 'difficulty', 'explanation', 'options' => [['content', 'is_correct']]], ...]. Trả về số câu đã thêm.
     */
    public static function importMany(array $items, int $subjectId, int $userId): int
    {
        $pdo = self::db();
        $insQ = $pdo->prepare('INSERT INTO questions (subject_id, created_by, content, explanation, difficulty, is_active) VALUES (?, ?, ?, ?, ?, 1)');
        $insO = $pdo->prepare('INSERT INTO question_options (question_id, content, is_correct, position) VALUES (?, ?, ?, ?)');
        $pdo->beginTransaction();
        try {
            foreach ($items as $it) {
                $insQ->execute([$subjectId, $userId, $it['content'], $it['explanation'], $it['difficulty']]);
                $qid = (int) $pdo->lastInsertId();
                foreach ($it['options'] as $pos => $o) {
                    $insO->execute([$qid, $o['content'], $o['is_correct'] ? 1 : 0, $pos]);
                }
            }
            $pdo->commit();
            return count($items);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Lưu câu hỏi + đáp án trong 1 transaction. $options: [['content' => ..., 'is_correct' => 0|1], ...] theo thứ tự A, B, C...
     * Đáp án cũ được cập nhật tại chỗ (giữ nguyên id) để sau này không làm mất liên kết với bài làm.
     */
    public static function saveWithOptions(?int $id, array $data, array $options): int
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            if ($id === null) {
                $id = self::create($data);
            } else {
                self::update($id, $data);
            }

            $stmt = $pdo->prepare('SELECT id FROM question_options WHERE question_id = ? ORDER BY position, id');
            $stmt->execute([$id]);
            $existing = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

            $upd = $pdo->prepare('UPDATE question_options SET content = ?, is_correct = ?, position = ? WHERE id = ? AND question_id = ?');
            $ins = $pdo->prepare('INSERT INTO question_options (question_id, content, is_correct, position) VALUES (?, ?, ?, ?)');
            foreach ($options as $pos => $o) {
                if (isset($existing[$pos])) {
                    $upd->execute([$o['content'], $o['is_correct'], $pos, $existing[$pos], $id]);
                } else {
                    $ins->execute([$id, $o['content'], $o['is_correct'], $pos]);
                }
            }
            $extra = array_slice($existing, count($options));
            if ($extra) {
                $pdo->prepare('DELETE FROM question_options WHERE question_id = ? AND id IN (' . implode(',', array_fill(0, count($extra), '?')) . ')')
                    ->execute([$id, ...$extra]);
            }

            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
