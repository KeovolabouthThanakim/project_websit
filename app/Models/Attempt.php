<?php
namespace App\Models;

use App\Core\App;
use App\Core\Model;

/**
 * Lượt làm bài của sinh viên: bảng "exam_attempts", đề riêng của từng sinh viên "exam_attempt_questions",
 * đáp án đã chọn "exam_answers".
 *
 * Quy tắc (xem README):
 *  - Mỗi sinh viên chỉ làm mỗi kỳ thi 1 lần.
 *  - Giờ hết hạn (deadline_at) do server tính; hết giờ thì tự nộp và chấm bằng đáp án đã tự lưu.
 *  - Câu 1 đáp án đúng: đúng = 1 điểm, sai = 0. Câu nhiều đáp án đúng: chấm từng phần (có trừ điểm chọn sai) hoặc
 *    "đúng hết mới có điểm" tùy config exam.partial_credit.
 *  - Điểm bài thi = tổng điểm các câu / số câu x 10.
 */
class Attempt extends Model
{
    protected static string $table = 'exam_attempts';
    protected static array $fillable = ['exam_id', 'user_id', 'started_at', 'deadline_at', 'submitted_at', 'status', 'auto_submitted', 'earned', 'total_questions', 'score'];

    public const SCALE = 10;

    private static function in(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    /** Giây ân hạn khi nhận bài nộp muộn do độ trễ mạng (config exam.submit_grace_seconds) */
    public static function grace(): int
    {
        return (int) ((App::config('exam') ?? [])['submit_grace_seconds'] ?? 10);
    }

    public static function partialCredit(): bool
    {
        return (bool) ((App::config('exam') ?? [])['partial_credit'] ?? true);
    }

    // ----- Chấm điểm (hàm thuần, dễ kiểm thử) -----

    /**
     * Điểm một câu (0 - 1).
     * $correct, $selected: mảng id đáp án. Câu nhiều đáp án, chấm từng phần:
     *   max(0, (số đáp án đúng đã chọn - số đáp án sai đã chọn) / số đáp án đúng)
     */
    public static function credit(array $correct, array $selected, bool $partial): float
    {
        $correct = array_values(array_unique(array_map('intval', $correct)));
        $selected = array_values(array_unique(array_map('intval', $selected)));
        if (!$correct) {
            return 0.0;
        }
        $right = count(array_intersect($selected, $correct));
        $wrong = count($selected) - $right;

        if (count($correct) === 1 || !$partial) {
            return ($right === count($correct) && $wrong === 0) ? 1.0 : 0.0;
        }
        return max(0.0, ($right - $wrong) / count($correct));
    }

    public static function scoreOf(float $earned, int $total): float
    {
        return $total > 0 ? round($earned / $total * self::SCALE, 2) : 0.0;
    }

    // ----- Truy vấn -----

    public static function forExamUser(int $examId, int $userId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM exam_attempts WHERE exam_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$examId, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function countForExam(int $examId): int
    {
        $stmt = self::db()->prepare('SELECT COUNT(*) FROM exam_attempts WHERE exam_id = ?');
        $stmt->execute([$examId]);
        return (int) $stmt->fetchColumn();
    }

    /** Một lượt làm bài kèm thông tin kỳ thi - chỉ của chính sinh viên $userId */
    public static function detailOwned(int $id, int $userId): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT a.*, e.title, e.description, e.duration_minutes, e.start_at, e.end_at, e.status AS exam_status, e.shuffle,
                    s.code AS subject_code, s.name AS subject_name
             FROM exam_attempts a JOIN exams e ON e.id = a.exam_id JOIN subjects s ON s.id = e.subject_id
             WHERE a.id = ? AND a.user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    /** Kỳ thi mà lớp $classId được thi, đã công bố (hoặc đã đóng nhưng sinh viên đã làm) */
    public static function examForClass(int $examId, int $classId, int $userId): ?array
    {
        $stmt = self::db()->prepare(
            "SELECT e.*, s.code AS subject_code, s.name AS subject_name,
                    (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) AS question_count
             FROM exams e
             JOIN subjects s ON s.id = e.subject_id
             JOIN exam_classes ec ON ec.exam_id = e.id AND ec.class_id = ?
             WHERE e.id = ? AND (e.status = 'published'
                   OR (e.status = 'closed' AND EXISTS (SELECT 1 FROM exam_attempts a WHERE a.exam_id = e.id AND a.user_id = ?)))
             LIMIT 1"
        );
        $stmt->execute([$classId, $examId, $userId]);
        return $stmt->fetch() ?: null;
    }

    /** Danh sách kỳ thi của sinh viên kèm kết quả (mới nhất trước) */
    public static function listForStudent(int $userId, int $classId): array
    {
        $stmt = self::db()->prepare(
            "SELECT e.id, e.title, e.duration_minutes, e.start_at, e.end_at, e.status,
                    s.code AS subject_code, s.name AS subject_name,
                    (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) AS question_count,
                    a.id AS attempt_id, a.status AS attempt_status, a.deadline_at, a.score, a.submitted_at,
                    a.started_at, a.earned, a.total_questions, a.auto_submitted
             FROM exams e
             JOIN subjects s ON s.id = e.subject_id
             JOIN exam_classes ec ON ec.exam_id = e.id AND ec.class_id = ?
             LEFT JOIN exam_attempts a ON a.exam_id = e.id AND a.user_id = ?
             WHERE e.status = 'published' OR (e.status = 'closed' AND a.id IS NOT NULL)
             ORDER BY e.start_at DESC, e.id DESC"
        );
        $stmt->execute([$classId, $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Những sinh viên đang làm bài NGAY BÂY GIỜ (chưa nộp, chưa quá hạn), hết hạn sớm nhất trước.
     * Mỗi dòng: username, full_name, class_code, exam_id, exam_title, subject_code, started_at, deadline_at, answered, total_questions.
     * Gọi finalizeExpired() trước để bài quá hạn không còn bị tính là đang làm.
     */
    public static function inProgressNow(int $limit = 50): array
    {
        $limit = max(1, $limit);
        $stmt = self::db()->prepare(
            "SELECT u.username, " . User::codeCol() . ", u.full_name, e.id AS exam_id, e.title AS exam_title, s.code AS subject_code,
                    a.started_at, a.deadline_at, a.total_questions,
                    (SELECT c.code FROM class_students cs JOIN classes c ON c.id = cs.class_id WHERE cs.user_id = u.id LIMIT 1) AS class_code,
                    (SELECT COUNT(DISTINCT x.question_id) FROM exam_answers x WHERE x.attempt_id = a.id) AS answered
             FROM exam_attempts a
             JOIN users u ON u.id = a.user_id
             JOIN exams e ON e.id = a.exam_id
             JOIN subjects s ON s.id = e.subject_id
             WHERE a.status = 'in_progress' AND a.deadline_at >= ?
             ORDER BY a.deadline_at, u.username LIMIT $limit"
        );
        $stmt->execute([date('Y-m-d H:i:s')]);
        return $stmt->fetchAll();
    }

    public static function countInProgressNow(): int
    {
        $stmt = self::db()->prepare("SELECT COUNT(*) FROM exam_attempts WHERE status = 'in_progress' AND deadline_at >= ?");
        $stmt->execute([date('Y-m-d H:i:s')]);
        return (int) $stmt->fetchColumn();
    }

    // ----- Bắt đầu làm bài -----

    /**
     * Tạo lượt làm bài + chốt thứ tự câu hỏi / đáp án cho sinh viên. Trả về id lượt làm bài.
     * $extraMinutes: số phút giáo viên cộng thêm riêng cho sinh viên này (xem ExamGrant).
     * Ném PDOException (mã 23000) nếu sinh viên đã có lượt làm bài của kỳ thi này.
     */
    public static function start(array $exam, int $userId, int $extraMinutes = 0): int
    {
        $pdo = self::db();
        $examId = (int) $exam['id'];

        $stmt = $pdo->prepare('SELECT question_id FROM exam_questions WHERE exam_id = ? ORDER BY position, question_id');
        $stmt->execute([$examId]);
        $questionIds = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
        if ($exam['shuffle']) {
            shuffle($questionIds);
        }

        $optionsByQuestion = [];
        if ($questionIds) {
            $stmt = $pdo->prepare('SELECT id, question_id FROM question_options WHERE question_id IN (' . self::in($questionIds) . ') ORDER BY position, id');
            $stmt->execute($questionIds);
            foreach ($stmt->fetchAll() as $o) {
                $optionsByQuestion[(int) $o['question_id']][] = (int) $o['id'];
            }
        }

        $now = time();
        $pdo->beginTransaction();
        try {
            $attemptId = self::create([
                'exam_id'         => $examId,
                'user_id'         => $userId,
                'started_at'      => date('Y-m-d H:i:s', $now),
                'deadline_at'     => date('Y-m-d H:i:s', $now + ((int) $exam['duration_minutes'] + max(0, $extraMinutes)) * 60),
                'status'          => 'in_progress',
                'total_questions' => count($questionIds),
            ]);
            $ins = $pdo->prepare('INSERT INTO exam_attempt_questions (attempt_id, question_id, position, option_order) VALUES (?, ?, ?, ?)');
            foreach ($questionIds as $pos => $qid) {
                $opts = $optionsByQuestion[$qid] ?? [];
                if ($exam['shuffle']) {
                    shuffle($opts);
                }
                $ins->execute([$attemptId, $qid, $pos + 1, implode(',', $opts)]);
            }
            $pdo->commit();
            return $attemptId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ----- Đề của sinh viên -----

    public static function secondsLeft(array $attempt): int
    {
        return max(0, strtotime($attempt['deadline_at']) - time());
    }

    /** Đã quá hạn (hết giờ + ân hạn)? Từ lúc này không nhận thêm đáp án nữa, bài sẽ được tự nộp */
    public static function isExpired(array $attempt): bool
    {
        return time() > strtotime($attempt['deadline_at']) + self::grace();
    }

    /**
     * Bài làm theo đúng thứ tự riêng của sinh viên.
     * $withKey = false (khi đang làm bài): KHÔNG đưa is_correct / explanation ra khỏi Model.
     * Mỗi câu: id, content, image, multiple, options[id, content, selected, (is_correct)], earned, (explanation)
     */
    public static function paper(int $attemptId, bool $withKey): array
    {
        $pdo = self::db();
        $stmt = $pdo->prepare(
            'SELECT aq.question_id, aq.option_order, aq.earned, q.content, q.image, q.explanation
             FROM exam_attempt_questions aq JOIN questions q ON q.id = aq.question_id
             WHERE aq.attempt_id = ? ORDER BY aq.position'
        );
        $stmt->execute([$attemptId]);
        $rows = $stmt->fetchAll();
        if (!$rows) {
            return [];
        }
        $qids = array_map(fn($r) => (int) $r['question_id'], $rows);

        $stmt = $pdo->prepare('SELECT id, question_id, content, is_correct FROM question_options WHERE question_id IN (' . self::in($qids) . ') ORDER BY position, id');
        $stmt->execute($qids);
        $opts = [];
        foreach ($stmt->fetchAll() as $o) {
            $opts[(int) $o['question_id']][(int) $o['id']] = $o;
        }

        $stmt = $pdo->prepare('SELECT question_id, option_id FROM exam_answers WHERE attempt_id = ?');
        $stmt->execute([$attemptId]);
        $picked = [];
        foreach ($stmt->fetchAll() as $a) {
            $picked[(int) $a['question_id']][(int) $a['option_id']] = true;
        }

        $paper = [];
        foreach ($rows as $r) {
            $qid = (int) $r['question_id'];
            $all = $opts[$qid] ?? [];
            // Theo thứ tự đã chốt; đáp án mới thêm sau khi bắt đầu (nếu có) xếp cuối
            $order = array_filter(array_map('intval', explode(',', (string) $r['option_order'])));
            $ordered = [];
            foreach ($order as $oid) {
                if (isset($all[$oid])) {
                    $ordered[$oid] = $all[$oid];
                }
            }
            $ordered += $all;

            $multiple = count(array_filter($all, fn($o) => $o['is_correct'])) > 1;
            $q = ['id' => $qid, 'content' => $r['content'], 'image' => $r['image'], 'multiple' => $multiple, 'earned' => $r['earned'] === null ? null : (float) $r['earned'], 'options' => []];
            foreach ($ordered as $oid => $o) {
                $opt = ['id' => $oid, 'content' => $o['content'], 'selected' => isset($picked[$qid][$oid])];
                if ($withKey) {
                    $opt['is_correct'] = (bool) $o['is_correct'];
                }
                $q['options'][] = $opt;
            }
            if ($withKey) {
                $q['explanation'] = $r['explanation'];
            }
            $paper[] = $q;
        }
        return $paper;
    }

    /** Câu hỏi này (và ảnh) có thuộc lượt làm bài không - dùng để cho sinh viên xem ảnh minh họa */
    public static function hasImage(int $attemptId, string $file): bool
    {
        $stmt = self::db()->prepare(
            'SELECT 1 FROM exam_attempt_questions aq JOIN questions q ON q.id = aq.question_id WHERE aq.attempt_id = ? AND q.image = ? LIMIT 1'
        );
        $stmt->execute([$attemptId, $file]);
        return (bool) $stmt->fetchColumn();
    }

    // ----- Lưu đáp án -----

    /** Lưu lựa chọn của một câu (thay thế lựa chọn cũ). Trả về false nếu câu hỏi / đáp án không thuộc bài làm */
    public static function saveAnswer(int $attemptId, int $questionId, array $optionIds): bool
    {
        $pdo = self::db();
        $stmt = $pdo->prepare('SELECT 1 FROM exam_attempt_questions WHERE attempt_id = ? AND question_id = ?');
        $stmt->execute([$attemptId, $questionId]);
        if (!$stmt->fetchColumn()) {
            return false;
        }

        $optionIds = array_values(array_unique(array_map('intval', $optionIds)));
        if ($optionIds) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM question_options WHERE question_id = ? AND id IN (' . self::in($optionIds) . ')');
            $stmt->execute([$questionId, ...$optionIds]);
            if ((int) $stmt->fetchColumn() !== count($optionIds)) {
                return false;
            }
            // Câu 1 đáp án đúng chỉ nhận 1 lựa chọn
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM question_options WHERE question_id = ? AND is_correct = 1');
            $stmt->execute([$questionId]);
            if ((int) $stmt->fetchColumn() <= 1) {
                $optionIds = array_slice($optionIds, 0, 1);
            }
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM exam_answers WHERE attempt_id = ? AND question_id = ?')->execute([$attemptId, $questionId]);
            $ins = $pdo->prepare('INSERT INTO exam_answers (attempt_id, question_id, option_id) VALUES (?, ?, ?)');
            foreach ($optionIds as $oid) {
                $ins->execute([$attemptId, $questionId, $oid]);
            }
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Lưu toàn bộ đáp án gửi kèm lúc nộp bài: [question_id => [option_id, ...]] (câu vắng mặt = bỏ trống) */
    public static function saveAllAnswers(int $attemptId, array $answers): void
    {
        $stmt = self::db()->prepare('SELECT question_id FROM exam_attempt_questions WHERE attempt_id = ?');
        $stmt->execute([$attemptId]);
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $qid) {
            self::saveAnswer($attemptId, (int) $qid, (array) ($answers[(int) $qid] ?? []));
        }
    }

    // ----- Nộp bài + chấm điểm -----

    /**
     * Chấm và đóng bài làm (chạy được nhiều lần an toàn - lần sau không làm gì).
     * $submittedAt: thời điểm tính là đã nộp (khi hết giờ là đúng giờ hết hạn, không phải lúc hệ thống chấm).
     * Trả về true nếu lần gọi này đã chấm bài.
     */
    public static function grade(int $attemptId, bool $auto, ?int $submittedAt = null): bool
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT status, deadline_at FROM exam_attempts WHERE id = ? FOR UPDATE');
            $stmt->execute([$attemptId]);
            $row = $stmt->fetch();
            if (!$row || $row['status'] !== 'in_progress') {
                $pdo->rollBack();
                return false;
            }
            $submittedAt = min($submittedAt ?? time(), strtotime($row['deadline_at']));

            $stmt = $pdo->prepare('SELECT question_id FROM exam_attempt_questions WHERE attempt_id = ?');
            $stmt->execute([$attemptId]);
            $qids = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

            $correct = [];
            if ($qids) {
                $stmt = $pdo->prepare('SELECT question_id, id FROM question_options WHERE is_correct = 1 AND question_id IN (' . self::in($qids) . ')');
                $stmt->execute($qids);
                foreach ($stmt->fetchAll() as $o) {
                    $correct[(int) $o['question_id']][] = (int) $o['id'];
                }
            }
            $stmt = $pdo->prepare('SELECT question_id, option_id FROM exam_answers WHERE attempt_id = ?');
            $stmt->execute([$attemptId]);
            $picked = [];
            foreach ($stmt->fetchAll() as $a) {
                $picked[(int) $a['question_id']][] = (int) $a['option_id'];
            }

            $partial = self::partialCredit();
            $upd = $pdo->prepare('UPDATE exam_attempt_questions SET earned = ? WHERE attempt_id = ? AND question_id = ?');
            $earned = 0.0;
            foreach ($qids as $qid) {
                $c = self::credit($correct[$qid] ?? [], $picked[$qid] ?? [], $partial);
                $earned += $c;
                $upd->execute([round($c, 4), $attemptId, $qid]);
            }

            $pdo->prepare(
                "UPDATE exam_attempts SET status = 'submitted', submitted_at = ?, auto_submitted = ?, earned = ?, total_questions = ?, score = ? WHERE id = ?"
            )->execute([date('Y-m-d H:i:s', $submittedAt), $auto ? 1 : 0, round($earned, 3), count($qids), self::scoreOf($earned, count($qids)), $attemptId]);

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Tự nộp + chấm các bài đã hết giờ mà sinh viên chưa nộp (đóng tab, mất mạng...).
     * Gọi trước khi hiển thị danh sách / kết quả. $examId / $userId: giới hạn phạm vi (null = tất cả).
     */
    public static function finalizeExpired(?int $examId = null, ?int $userId = null): int
    {
        $where = ["status = 'in_progress'", 'deadline_at < ?'];
        $params = [date('Y-m-d H:i:s', time() - self::grace())];
        if ($examId !== null) {
            $where[] = 'exam_id = ?';
            $params[] = $examId;
        }
        if ($userId !== null) {
            $where[] = 'user_id = ?';
            $params[] = $userId;
        }
        $stmt = self::db()->prepare('SELECT id, deadline_at FROM exam_attempts WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);

        $n = 0;
        foreach ($stmt->fetchAll() as $a) {
            if (self::grade((int) $a['id'], true, strtotime($a['deadline_at']))) {
                $n++;
            }
        }
        return $n;
    }
}
