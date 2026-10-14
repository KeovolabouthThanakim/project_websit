<?php
namespace App\Models;

use App\Core\Model;

/**
 * Kỳ thi / đề thi: bảng "exams", lớp được thi "exam_classes", câu hỏi của đề "exam_questions".
 * Admin quản lý mọi kỳ thi; giáo viên chỉ quản lý kỳ thi của các môn mình được phân công.
 */
class Exam extends Model
{
    protected static string $table = 'exams';
    protected static array $fillable = ['subject_id', 'created_by', 'title', 'description', 'duration_minutes', 'start_at', 'end_at', 'shuffle', 'status'];

    /** Trạng thái hiển thị (đã tính theo thời gian) */
    public const STATES = [
        'draft'    => 'Bản nháp',
        'upcoming' => 'Sắp diễn ra',
        'open'     => 'Đang mở',
        'ended'    => 'Đã kết thúc',
        'closed'   => 'Đã đóng',
    ];

    public const MIN_DURATION = 1;
    public const MAX_DURATION = 300;

    private static function in(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    /** Điều kiện SQL: môn học $alias mà giáo viên được phân công (phụ trách hoặc giảng dạy ở một lớp) */
    private static function teacherScope(string $alias): string
    {
        return "(EXISTS (SELECT 1 FROM subject_teachers x WHERE x.subject_id = $alias.id AND x.user_id = ?)
                 OR EXISTS (SELECT 1 FROM class_subjects y WHERE y.subject_id = $alias.id AND y.teacher_id = ?))";
    }

    /** Trạng thái hiển thị của một kỳ thi: draft | upcoming | open | ended | closed */
    public static function state(array $e): string
    {
        if ($e['status'] === 'draft' || $e['status'] === 'closed') {
            return $e['status'];
        }
        $now = time();
        if ($now < strtotime($e['start_at'])) {
            return 'upcoming';
        }
        return $now <= strtotime($e['end_at']) ? 'open' : 'ended';
    }

    // ----- Danh sách -----

    /** $f: q (tiêu đề), subject_id, state (draft|upcoming|open|ended|closed). $teacherId != null: chỉ môn của giáo viên đó */
    public static function search(array $f, int $page, int $perPage = 10, ?int $teacherId = null): array
    {
        $where = [];
        $params = [];
        $now = date('Y-m-d H:i:s');

        if (($f['q'] ?? '') !== '') {
            $where[] = 'e.title LIKE ?';
            $params[] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['q']) . '%';
        }
        if (($f['subject_id'] ?? 0) > 0) {
            $where[] = 'e.subject_id = ?';
            $params[] = (int) $f['subject_id'];
        }
        switch ($f['state'] ?? '') {
            case 'draft':
            case 'closed':
                $where[] = 'e.status = ?';
                $params[] = $f['state'];
                break;
            case 'upcoming':
                $where[] = "e.status = 'published' AND e.start_at > ?";
                $params[] = $now;
                break;
            case 'open':
                $where[] = "e.status = 'published' AND e.start_at <= ? AND e.end_at >= ?";
                array_push($params, $now, $now);
                break;
            case 'ended':
                $where[] = "e.status = 'published' AND e.end_at < ?";
                $params[] = $now;
                break;
        }
        if ($teacherId !== null) {
            $where[] = self::teacherScope('s');
            array_push($params, $teacherId, $teacherId);
        }
        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = self::db()->prepare("SELECT COUNT(*) FROM exams e JOIN subjects s ON s.id = e.subject_id $sqlWhere");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = self::db()->prepare(
            "SELECT e.id, e.title, e.duration_minutes, e.start_at, e.end_at, e.status, e.created_at,
                    s.code AS subject_code, s.name AS subject_name, u.full_name AS author_name,
                    (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) AS question_count,
                    (SELECT GROUP_CONCAT(c.code ORDER BY c.code SEPARATOR ', ')
                       FROM exam_classes ec JOIN classes c ON c.id = ec.class_id WHERE ec.exam_id = e.id) AS class_codes
             FROM exams e
             JOIN subjects s ON s.id = e.subject_id
             LEFT JOIN users u ON u.id = e.created_by
             $sqlWhere ORDER BY e.start_at DESC, e.id DESC LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** Một kỳ thi kèm thông tin môn học và người tạo */
    public static function detail(int $id): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT e.*, s.code AS subject_code, s.name AS subject_name, s.is_active AS subject_active, u.full_name AS author_name
             FROM exams e JOIN subjects s ON s.id = e.subject_id LEFT JOIN users u ON u.id = e.created_by
             WHERE e.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Email của sinh viên đang hoạt động thuộc các lớp được thi (bỏ qua người chưa có email) */
    public static function studentEmails(int $examId): array
    {
        $stmt = self::db()->prepare(
            "SELECT DISTINCT u.email
             FROM exam_classes ec
             JOIN class_students cs ON cs.class_id = ec.class_id
             JOIN users u ON u.id = cs.user_id
             WHERE ec.exam_id = ? AND u.role = 'student' AND u.is_active = 1 AND u.email IS NOT NULL AND u.email <> ''"
        );
        $stmt->execute([$examId]);
        return array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** Id của sinh viên đang hoạt động thuộc các lớp được thi (để gửi thông báo trong hệ thống) */
    public static function studentIds(int $examId): array
    {
        $stmt = self::db()->prepare(
            "SELECT DISTINCT u.id
             FROM exam_classes ec
             JOIN class_students cs ON cs.class_id = ec.class_id
             JOIN users u ON u.id = cs.user_id
             WHERE ec.exam_id = ? AND u.role = 'student' AND u.is_active = 1"
        );
        $stmt->execute([$examId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    // ----- Bảng điều khiển giáo viên -----

    /**
     * Các kỳ thi cần chú ý trên trang chủ giáo viên, đã chia nhóm:
     *  live     - đang mở cổng thi, hoặc còn sinh viên đang làm bài (dù cổng đã đóng)
     *  upcoming - đã công bố, chưa tới giờ mở cổng
     *  drafts   - bản nháp (kèm số điều còn thiếu để công bố)
     *  recent   - đã kết thúc / đã đóng trong $recentDays ngày qua, để xem kết quả
     * Mỗi dòng gồm thông tin kỳ thi + question_count, class_codes, students (tổng sinh viên được thi),
     * submitted, working, avg_score, state. $teacherId = null: mọi môn (quản trị viên).
     */
    public static function dashboard(?int $teacherId, int $recentDays = 14, int $limit = 100): array
    {
        $params = [];
        $scope = '';
        if ($teacherId !== null) {
            $scope = ' AND ' . self::teacherScope('s');
            array_push($params, $teacherId, $teacherId);
        }
        $now = date('Y-m-d H:i:s');
        $since = date('Y-m-d H:i:s', time() - $recentDays * 86400);

        $stmt = self::db()->prepare(
            "SELECT e.id, e.title, e.duration_minutes, e.start_at, e.end_at, e.status, e.subject_id,
                    s.code AS subject_code, s.name AS subject_name, s.is_active AS subject_active,
                    (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) AS question_count,
                    (SELECT COUNT(*) FROM exam_classes ec2 WHERE ec2.exam_id = e.id) AS class_count,
                    (SELECT GROUP_CONCAT(c.code ORDER BY c.code SEPARATOR ', ')
                       FROM exam_classes ec JOIN classes c ON c.id = ec.class_id WHERE ec.exam_id = e.id) AS class_codes,
                    (SELECT COUNT(DISTINCT cs.user_id) FROM exam_classes ec3 JOIN class_students cs ON cs.class_id = ec3.class_id
                       WHERE ec3.exam_id = e.id) AS students,
                    (SELECT COUNT(*) FROM exam_attempts a WHERE a.exam_id = e.id AND a.status = 'submitted') AS submitted,
                    (SELECT COUNT(*) FROM exam_attempts a WHERE a.exam_id = e.id AND a.status = 'in_progress') AS working,
                    (SELECT AVG(a.score) FROM exam_attempts a WHERE a.exam_id = e.id AND a.status = 'submitted') AS avg_score
             FROM exams e JOIN subjects s ON s.id = e.subject_id
             WHERE (e.status = 'draft'
                    OR e.end_at >= ?
                    OR EXISTS (SELECT 1 FROM exam_attempts a WHERE a.exam_id = e.id AND a.status = 'in_progress'))
                   $scope
             ORDER BY e.start_at DESC, e.id DESC LIMIT " . max(1, $limit)
        );
        array_unshift($params, $since);
        $stmt->execute($params);

        $out = ['live' => [], 'upcoming' => [], 'drafts' => [], 'recent' => []];
        foreach ($stmt->fetchAll() as $e) {
            $state = self::state($e);
            $e['state'] = $state;
            if ($state === 'draft') {
                $e['missing'] = self::draftMissing($e);
                $out['drafts'][] = $e;
            } elseif ($state === 'open' || ((int) $e['working'] > 0 && $e['status'] === 'published')) {
                $out['live'][] = $e;
            } elseif ($state === 'upcoming') {
                $out['upcoming'][] = $e;
            } elseif (strtotime($e['end_at']) >= strtotime($since) || $state === 'closed') {
                $out['recent'][] = $e;
            }
        }
        // Sắp diễn ra: gần nhất lên đầu; đang mở: cổng đóng sớm nhất lên đầu
        usort($out['upcoming'], fn($a, $b) => strcmp($a['start_at'], $b['start_at']));
        usort($out['live'], fn($a, $b) => strcmp($a['end_at'], $b['end_at']));
        usort($out['recent'], fn($a, $b) => strcmp($b['end_at'], $a['end_at']));
        return $out;
    }

    /** Những điều bản nháp còn thiếu để công bố, tính từ số liệu đã có sẵn trong dòng dashboard (không truy vấn thêm) */
    private static function draftMissing(array $e): array
    {
        $m = [];
        if ((int) $e['question_count'] < 1) {
            $m[] = 'chưa có câu hỏi';
        }
        if ((int) $e['class_count'] < 1) {
            $m[] = 'chưa chọn lớp';
        }
        if (strtotime($e['end_at']) <= time()) {
            $m[] = 'thời gian thi đã qua';
        }
        return $m;
    }

    /**
     * Sao chép đề thi thành một BẢN NHÁP mới: cùng môn, mô tả, thời lượng, tùy chọn đảo câu, danh sách lớp (chỉ giữ lớp người
     * sao chép được phép chọn) và danh sách câu hỏi (chỉ câu đang hiện). Không chép bài làm / kết quả.
     * Giờ thi được dời tiến theo bội số của 7 ngày cho tới khi cổng thi mở trong tương lai (cùng thứ, cùng giờ), vì đề thi
     * thường lặp lại hằng tuần; người dùng sửa lại ở trang Sửa thông tin nếu cần.
     * Trả về ['id' => id đề mới, 'skipped' => số câu bị ẩn không chép, 'classes' => số lớp giữ lại].
     */
    public static function duplicate(array $exam, int $createdBy, ?int $teacherId): array
    {
        $examId = (int) $exam['id'];
        $start = strtotime($exam['start_at']);
        $end = strtotime($exam['end_at']);
        $week = 7 * 86400;
        $shift = $start > time() ? 0 : (int) ceil((time() - $start + 1) / $week) * $week;

        $classIds = array_map(fn($c) => (int) $c['id'], self::classesOf($examId));
        $classIds = self::validClassIds((int) $exam['subject_id'], $classIds, $teacherId, null);

        $title = $exam['title'];
        $suffix = ' (bản sao)';
        if (str_len($title) + str_len($suffix) > 200) {
            $title = str_cut($title, 200 - str_len($suffix));
        }

        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $newId = self::create([
                'subject_id'       => (int) $exam['subject_id'],
                'created_by'       => $createdBy,
                'title'            => $title . $suffix,
                'description'      => $exam['description'],
                'duration_minutes' => (int) $exam['duration_minutes'],
                'start_at'         => date('Y-m-d H:i:s', $start + $shift),
                'end_at'           => date('Y-m-d H:i:s', $end + $shift),
                'shuffle'          => $exam['shuffle'] ? 1 : 0,
                'status'           => 'draft',
            ]);
            $ins = $pdo->prepare('INSERT IGNORE INTO exam_classes (exam_id, class_id) VALUES (?, ?)');
            foreach ($classIds as $cid) {
                $ins->execute([$newId, $cid]);
            }
            $stmt = $pdo->prepare(
                'INSERT INTO exam_questions (exam_id, question_id, position)
                 SELECT ?, eq.question_id, eq.position FROM exam_questions eq JOIN questions q ON q.id = eq.question_id
                 WHERE eq.exam_id = ? AND q.is_active = 1'
            );
            $stmt->execute([$newId, $examId]);
            $copied = $stmt->rowCount();
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return ['id' => $newId, 'skipped' => self::questionCount($examId) - $copied, 'classes' => count($classIds)];
    }

    // ----- Lớp được thi -----

    public static function classesOf(int $examId): array
    {
        $stmt = self::db()->prepare(
            'SELECT c.id, c.code, c.name, c.cohort,
                    (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id = c.id) AS student_count
             FROM exam_classes ec JOIN classes c ON c.id = ec.class_id
             WHERE ec.exam_id = ? ORDER BY c.code'
        );
        $stmt->execute([$examId]);
        return $stmt->fetchAll();
    }

    /**
     * Lớp có thể chọn cho kỳ thi của một môn: các lớp đang học môn đó.
     * Giáo viên chỉ chọn được lớp mình giảng dạy hoặc lớp chưa phân công giáo viên.
     * $keepExamId: luôn giữ các lớp đã chọn trước đó (kể cả khi phân công đã thay đổi).
     */
    public static function classChoices(int $subjectId, ?int $teacherId, ?int $keepExamId = null): array
    {
        $params = [$subjectId];
        $teacherSql = '';
        if ($teacherId !== null) {
            $teacherSql = ' AND (cs.teacher_id = ? OR cs.teacher_id IS NULL OR c.id IN (SELECT class_id FROM exam_classes WHERE exam_id = ?))';
            array_push($params, $teacherId, $keepExamId ?? 0);
        }
        $stmt = self::db()->prepare(
            "SELECT c.id, c.code, c.name, c.cohort,
                    (SELECT COUNT(*) FROM class_students x WHERE x.class_id = c.id) AS student_count
             FROM class_subjects cs JOIN classes c ON c.id = cs.class_id
             WHERE cs.subject_id = ? $teacherSql ORDER BY c.code"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Lớp đã có kỳ thi của môn này (để không cho bỏ môn khỏi lớp khi còn kỳ thi) */
    public static function existsForClassSubject(int $classId, int $subjectId): bool
    {
        $stmt = self::db()->prepare(
            'SELECT 1 FROM exam_classes ec JOIN exams e ON e.id = ec.exam_id WHERE ec.class_id = ? AND e.subject_id = ? LIMIT 1'
        );
        $stmt->execute([$classId, $subjectId]);
        return (bool) $stmt->fetchColumn();
    }

    /** Lưu kỳ thi + danh sách lớp trong 1 transaction. Trả về id kỳ thi */
    public static function saveWithClasses(?int $id, array $data, array $classIds): int
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            if ($id === null) {
                $id = self::create($data);
            } else {
                self::update($id, $data);
            }
            if ($classIds) {
                $pdo->prepare('DELETE FROM exam_classes WHERE exam_id = ? AND class_id NOT IN (' . self::in($classIds) . ')')
                    ->execute([$id, ...array_values($classIds)]);
            } else {
                $pdo->prepare('DELETE FROM exam_classes WHERE exam_id = ?')->execute([$id]);
            }
            $ins = $pdo->prepare('INSERT IGNORE INTO exam_classes (exam_id, class_id) VALUES (?, ?)');
            foreach ($classIds as $cid) {
                $ins->execute([$id, $cid]);
            }
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Trong danh sách id truyền vào, chỉ giữ lại các lớp được phép chọn cho môn này */
    public static function validClassIds(int $subjectId, array $ids, ?int $teacherId, ?int $examId): array
    {
        $allowed = array_map(fn($c) => (int) $c['id'], self::classChoices($subjectId, $teacherId, $examId));
        return array_values(array_intersect($ids, $allowed));
    }

    // ----- Câu hỏi của đề -----

    public static function questionsOf(int $examId): array
    {
        $stmt = self::db()->prepare(
            'SELECT q.id, q.content, q.image, q.difficulty, q.is_active, eq.position,
                    (SELECT COUNT(*) FROM question_options o WHERE o.question_id = q.id) AS option_count,
                    (SELECT COUNT(*) FROM question_options o WHERE o.question_id = q.id AND o.is_correct = 1) AS correct_count
             FROM exam_questions eq JOIN questions q ON q.id = eq.question_id
             WHERE eq.exam_id = ? ORDER BY eq.position, q.id'
        );
        $stmt->execute([$examId]);
        return $stmt->fetchAll();
    }

    public static function questionCount(int $examId): int
    {
        $stmt = self::db()->prepare('SELECT COUNT(*) FROM exam_questions WHERE exam_id = ?');
        $stmt->execute([$examId]);
        return (int) $stmt->fetchColumn();
    }

    /** Câu hỏi đang hiện của môn mà đề chưa có - dùng cho danh sách chọn. $difficulty rỗng = mọi độ khó */
    public static function availableQuestions(int $subjectId, int $examId, string $difficulty = '', int $limit = 300): array
    {
        $limit = max(1, $limit);
        $params = [$subjectId, $examId];
        $diffSql = '';
        if (isset(Question::DIFFICULTIES[$difficulty])) {
            $diffSql = ' AND q.difficulty = ?';
            $params[] = $difficulty;
        }
        $stmt = self::db()->prepare(
            "SELECT q.id, q.content, q.difficulty,
                    (SELECT COUNT(*) FROM question_options o WHERE o.question_id = q.id) AS option_count,
                    (SELECT COUNT(*) FROM question_options o WHERE o.question_id = q.id AND o.is_correct = 1) AS correct_count
             FROM questions q
             WHERE q.subject_id = ? AND q.is_active = 1
               AND NOT EXISTS (SELECT 1 FROM exam_questions eq WHERE eq.exam_id = ? AND eq.question_id = q.id)
               $diffSql ORDER BY q.id DESC LIMIT $limit"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** ['easy' => n, 'medium' => n, 'hard' => n]: số câu hỏi còn có thể thêm theo độ khó */
    public static function availableCounts(int $subjectId, int $examId): array
    {
        $stmt = self::db()->prepare(
            'SELECT q.difficulty, COUNT(*) FROM questions q
             WHERE q.subject_id = ? AND q.is_active = 1
               AND NOT EXISTS (SELECT 1 FROM exam_questions eq WHERE eq.exam_id = ? AND eq.question_id = q.id)
             GROUP BY q.difficulty'
        );
        $stmt->execute([$subjectId, $examId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        $out = [];
        foreach (array_keys(Question::DIFFICULTIES) as $k) {
            $out[$k] = (int) ($rows[$k] ?? 0);
        }
        return $out;
    }

    /** Trong danh sách id, chỉ giữ câu hỏi đang hiện của đúng môn và chưa có trong đề */
    public static function validNewQuestionIds(int $subjectId, int $examId, array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $stmt = self::db()->prepare(
            'SELECT q.id FROM questions q
             WHERE q.subject_id = ? AND q.is_active = 1 AND q.id IN (' . self::in($ids) . ')
               AND NOT EXISTS (SELECT 1 FROM exam_questions eq WHERE eq.exam_id = ? AND eq.question_id = q.id)'
        );
        $stmt->execute([$subjectId, ...array_values($ids), $examId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** Bốc ngẫu nhiên $n câu hỏi (đang hiện, chưa có trong đề) của một độ khó. Trả về mảng id */
    public static function randomQuestionIds(int $subjectId, int $examId, string $difficulty, int $n): array
    {
        if ($n < 1 || !isset(Question::DIFFICULTIES[$difficulty])) {
            return [];
        }
        $stmt = self::db()->prepare(
            "SELECT q.id FROM questions q
             WHERE q.subject_id = ? AND q.is_active = 1 AND q.difficulty = ?
               AND NOT EXISTS (SELECT 1 FROM exam_questions eq WHERE eq.exam_id = ? AND eq.question_id = q.id)
             ORDER BY RAND() LIMIT " . (int) $n
        );
        $stmt->execute([$subjectId, $difficulty, $examId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** Thêm câu hỏi vào cuối đề. Trả về số câu đã thêm */
    public static function addQuestions(int $examId, array $questionIds): int
    {
        if (!$questionIds) {
            return 0;
        }
        $pdo = self::db();
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) FROM exam_questions WHERE exam_id = ?');
        $stmt->execute([$examId]);
        $pos = (int) $stmt->fetchColumn();

        $ins = $pdo->prepare('INSERT IGNORE INTO exam_questions (exam_id, question_id, position) VALUES (?, ?, ?)');
        $added = 0;
        $pdo->beginTransaction();
        try {
            foreach ($questionIds as $qid) {
                $ins->execute([$examId, $qid, ++$pos]);
                $added += $ins->rowCount();
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $added;
    }

    public static function removeQuestion(int $examId, int $questionId): bool
    {
        $stmt = self::db()->prepare('DELETE FROM exam_questions WHERE exam_id = ? AND question_id = ?');
        $stmt->execute([$examId, $questionId]);
        return $stmt->rowCount() > 0;
    }

    public static function clearQuestions(int $examId): void
    {
        self::db()->prepare('DELETE FROM exam_questions WHERE exam_id = ?')->execute([$examId]);
    }

    // ----- Công bố -----

    /** Những điều còn thiếu để công bố được kỳ thi (mảng rỗng = đủ điều kiện) */
    public static function publishProblems(array $exam): array
    {
        $problems = [];
        $id = (int) $exam['id'];
        if (!$exam['subject_active']) {
            $problems[] = 'Môn học đang ngừng sử dụng.';
        }
        $count = self::questionCount($id);
        if ($count < 1) {
            $problems[] = 'Đề thi chưa có câu hỏi nào.';
        } else {
            $stmt = self::db()->prepare(
                'SELECT COUNT(*) FROM exam_questions eq JOIN questions q ON q.id = eq.question_id
                 WHERE eq.exam_id = ? AND (q.is_active = 0)'
            );
            $stmt->execute([$id]);
            if ((int) $stmt->fetchColumn() > 0) {
                $problems[] = 'Có câu hỏi đã bị ẩn trong đề - hãy bỏ câu đó khỏi đề.';
            }
        }
        if (!self::classesOf($id)) {
            $problems[] = 'Chưa chọn lớp nào được thi.';
        }
        if (strtotime($exam['end_at']) <= time()) {
            $problems[] = 'Thời điểm đóng cổng thi đã qua - hãy sửa lại thời gian thi.';
        }
        return $problems;
    }
}