<?php
namespace App\Models;

use App\Core\Model;

/**
 * Thống kê một môn học qua NHIỀU kỳ thi (chỉ đọc), nối tiếp trang "Kết quả" của từng kỳ thi:
 *  - exams(): điểm trung bình / trung vị / tỷ lệ đạt của từng kỳ thi, theo thời gian (để vẽ xu hướng)
 *  - hardQuestions(): các câu hay bị làm sai nhất trên mọi kỳ thi của môn, kèm đáp án sai được chọn nhiều nhất
 * Chỉ tính bài ĐÃ NỘP của kỳ thi đã công bố / đã đóng. Bài đã bị hủy để cho thi lại không còn trong bảng nên không bị tính 2 lần.
 * $classId > 0: chỉ tính sinh viên đang thuộc lớp đó.
 */
class Analytics extends Model
{
    protected static string $table = 'exam_attempts';

    /** Câu phải có ít nhất ngần này lượt làm mới đưa vào danh sách "hay sai" (tránh kết luận từ mẫu quá nhỏ) */
    public const MIN_SAMPLE = 5;
    public const TOP_QUESTIONS = 20;

    /** Lớp đang học môn này (để lọc) */
    public static function classChoices(int $subjectId): array
    {
        $stmt = self::db()->prepare(
            'SELECT c.id, c.code, c.name FROM class_subjects cs JOIN classes c ON c.id = cs.class_id WHERE cs.subject_id = ? ORDER BY c.code'
        );
        $stmt->execute([$subjectId]);
        return $stmt->fetchAll();
    }

    private static function classSql(int $classId): string
    {
        return $classId > 0 ? ' AND a.user_id IN (SELECT user_id FROM class_students WHERE class_id = ?)' : '';
    }

    /**
     * Mỗi kỳ thi (cũ -> mới, theo giờ mở cổng): id, title, start_at, status, students (số sinh viên được thi),
     * submitted, avg, median, min, max, pass_rate (0-1 hoặc null nếu chưa ai nộp).
     * Kỳ thi chưa có bài nộp vẫn được liệt kê (avg = null) để thấy rõ kỳ nào chưa có dữ liệu.
     */
    public static function exams(int $subjectId, int $classId = 0): array
    {
        $db = self::db();
        $stmt = $db->prepare(
            "SELECT e.id, e.title, e.start_at, e.end_at, e.status,
                    (SELECT COUNT(DISTINCT cs.user_id) FROM exam_classes ec JOIN class_students cs ON cs.class_id = ec.class_id
                      WHERE ec.exam_id = e.id" . ($classId > 0 ? ' AND ec.class_id = ?' : '') . ") AS students
             FROM exams e WHERE e.subject_id = ? AND e.status <> 'draft' ORDER BY e.start_at, e.id"
        );
        $stmt->execute($classId > 0 ? [$classId, $subjectId] : [$subjectId]);
        $exams = [];
        foreach ($stmt->fetchAll() as $e) {
            $e['scores'] = [];
            $exams[(int) $e['id']] = $e;
        }
        if (!$exams) {
            return [];
        }

        $in = implode(',', array_fill(0, count($exams), '?'));
        $stmt = $db->prepare(
            "SELECT a.exam_id, a.score FROM exam_attempts a
             WHERE a.exam_id IN ($in) AND a.status = 'submitted' AND a.score IS NOT NULL" . self::classSql($classId)
        );
        $stmt->execute(array_merge(array_keys($exams), $classId > 0 ? [$classId] : []));
        foreach ($stmt->fetchAll() as $r) {
            $exams[(int) $r['exam_id']]['scores'][] = (float) $r['score'];
        }

        $out = [];
        foreach ($exams as $e) {
            $sc = $e['scores'];
            unset($e['scores']);
            $s = self::describe($sc);
            $out[] = $e + ['submitted' => $s['n']] + $s;
        }
        return $out;
    }

    /** Thống kê mô tả một danh sách điểm (hàm thuần): n, avg, median, min, max, pass_rate */
    public static function describe(array $scores): array
    {
        $n = count($scores);
        if ($n === 0) {
            return ['n' => 0, 'avg' => null, 'median' => null, 'min' => null, 'max' => null, 'pass_rate' => null];
        }
        sort($scores);
        $median = $n % 2 ? $scores[intdiv($n, 2)] : ($scores[$n / 2 - 1] + $scores[$n / 2]) / 2;
        $passed = count(array_filter($scores, fn($s) => $s >= Result::passScore()));
        return [
            'n'         => $n,
            'avg'       => round(array_sum($scores) / $n, 2),
            'median'    => round($median, 2),
            'min'       => min($scores),
            'max'       => max($scores),
            'pass_rate' => $passed / $n,
        ];
    }

    /**
     * Các câu hay bị làm sai nhất của môn (điểm trung bình / câu thấp nhất trước), trên mọi kỳ thi.
     * Mỗi dòng: id, content, difficulty, is_active, attempts (lượt làm), exams (số kỳ thi đã dùng câu này),
     * full_count (đúng hoàn toàn), avg_earned (0 - 1), wrong_option (đáp án sai được chọn nhiều nhất: content, count) hoặc null.
     */
    public static function hardQuestions(int $subjectId, int $classId = 0, int $limit = self::TOP_QUESTIONS): array
    {
        $db = self::db();
        $params = [$subjectId];
        if ($classId > 0) {
            $params[] = $classId;
        }
        $params[] = self::MIN_SAMPLE;
        $stmt = $db->prepare(
            "SELECT q.id, q.content, q.difficulty, q.is_active,
                    COUNT(*) AS attempts, COUNT(DISTINCT a.exam_id) AS exams,
                    SUM(aq.earned >= 1) AS full_count, AVG(aq.earned) AS avg_earned
             FROM exam_attempt_questions aq
             JOIN exam_attempts a ON a.id = aq.attempt_id AND a.status = 'submitted'
             JOIN exams e ON e.id = a.exam_id AND e.subject_id = ?
             JOIN questions q ON q.id = aq.question_id
             WHERE aq.earned IS NOT NULL" . self::classSql($classId) . "
             GROUP BY q.id, q.content, q.difficulty, q.is_active
             HAVING COUNT(*) >= ?
             ORDER BY avg_earned ASC, attempts DESC, q.id
             LIMIT " . max(1, $limit)
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        if (!$rows) {
            return [];
        }

        // Đáp án SAI được chọn nhiều nhất của từng câu trong danh sách
        $ids = array_map(fn($r) => (int) $r['id'], $rows);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare(
            "SELECT ea.question_id, o.content, COUNT(*) AS n
             FROM exam_answers ea
             JOIN question_options o ON o.id = ea.option_id AND o.is_correct = 0
             JOIN exam_attempts a ON a.id = ea.attempt_id AND a.status = 'submitted'
             JOIN exams e ON e.id = a.exam_id AND e.subject_id = ?
             WHERE ea.question_id IN ($in)" . self::classSql($classId) . "
             GROUP BY ea.question_id, o.id, o.content"
        );
        // Thứ tự tham số trong SQL: subject (JOIN exams), câu hỏi (IN), [lớp]
        $stmt->execute(array_merge([$subjectId], $ids, $classId > 0 ? [$classId] : []));
        $wrong = [];
        foreach ($stmt->fetchAll() as $w) {
            $q = (int) $w['question_id'];
            if (!isset($wrong[$q]) || (int) $w['n'] > $wrong[$q]['count']) {
                $wrong[$q] = ['content' => $w['content'], 'count' => (int) $w['n']];
            }
        }
        foreach ($rows as &$r) {
            $r['wrong_option'] = $wrong[(int) $r['id']] ?? null;
        }
        return $rows;
    }
}
