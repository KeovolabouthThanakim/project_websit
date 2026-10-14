<?php
namespace App\Models;

use App\Core\Model;

/**
 * Kết quả & thống kê của một kỳ thi (dành cho giáo viên / quản trị viên). Chỉ đọc dữ liệu, không ghi.
 *  - roster(): bảng điểm - mọi sinh viên của các lớp được thi, kể cả người chưa vào thi
 *  - summary(): điểm trung bình, cao / thấp nhất, trung vị, tỷ lệ đạt, phân bố điểm (hàm thuần, dễ kiểm thử)
 *  - questionStats(): tỷ lệ làm đúng từng câu để biết câu nào quá khó / cần xem lại
 */
class Result extends Model
{
    protected static string $table = 'exam_attempts';

    /** Điểm đạt (thang 10) */
    /** Điểm đạt mặc định; đổi được trong Quản trị viên > Cài đặt (dùng passScore()) */
    public const PASS = 5.0;

    public static function passScore(): float
    {
        $v = setting('pass_score', '');
        return is_numeric($v) && (float) $v >= 0 && (float) $v <= 10 ? (float) $v : self::PASS;
    }

    /** Câu có tỷ lệ đúng dưới ngưỡng này được đánh dấu "cần xem lại" */
    public const HARD_RATE = 0.4;

    // ----- Điểm trung bình lớp (cho trang chủ sinh viên) -----

    /**
     * Điểm trung bình của lớp $classId ở từng kỳ thi trong $examIds (chỉ tính bài đã nộp của sinh viên đang thuộc lớp đó).
     * Trả về [exam_id => ['avg' => float, 'n' => số bài đã nộp]]. Kỳ thi chưa ai nộp thì không có phần tử.
     * Chỉ trả số liệu gộp, không lộ điểm của từng bạn.
     */
    public static function classAverages(array $examIds, int $classId): array
    {
        $examIds = array_values(array_unique(array_map('intval', $examIds)));
        if (!$examIds || $classId <= 0) {
            return [];
        }
        $in = implode(',', array_fill(0, count($examIds), '?'));
        $stmt = self::db()->prepare(
            "SELECT a.exam_id, AVG(a.score) AS avg_score, COUNT(*) AS n
             FROM exam_attempts a
             JOIN class_students cs ON cs.user_id = a.user_id AND cs.class_id = ?
             WHERE a.exam_id IN ($in) AND a.status = 'submitted' AND a.score IS NOT NULL
             GROUP BY a.exam_id"
        );
        $stmt->execute(array_merge([$classId], $examIds));
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[(int) $r['exam_id']] = ['avg' => (float) $r['avg_score'], 'n' => (int) $r['n']];
        }
        return $out;
    }

    // ----- Bảng điểm -----

    /**
     * Mỗi dòng: user_id, username, full_name, class_id, class_code, attempt_id, status, score, earned,
     * total_questions, full_correct, started_at, submitted_at, auto_submitted.
     * attempt_id = null: chưa vào thi. Sinh viên đã làm bài nhưng sau đó rời lớp vẫn được giữ lại (class_code = null).
     */
    public static function roster(int $examId, int $classId = 0, bool $live = false): array
    {
        $cols = 'u.id AS user_id, u.username, ' . User::codeCol() . ', u.full_name,
                 a.id AS attempt_id, a.status, a.score, a.earned, a.total_questions, a.started_at, a.submitted_at, a.auto_submitted,
                 (SELECT COUNT(*) FROM exam_attempt_questions q WHERE q.attempt_id = a.id AND q.earned >= 1) AS full_correct';
        if ($live) {
            // Theo dõi trực tiếp: hạn nộp + số câu đã trả lời
            $cols .= ', a.deadline_at, (SELECT COUNT(DISTINCT x.question_id) FROM exam_answers x WHERE x.attempt_id = a.id) AS answered';
        }

        $params = [$examId];
        $classSql = '';
        if ($classId > 0) {
            $classSql = ' AND c.id = ?';
            $params[] = $classId;
        }
        $stmt = self::db()->prepare(
            "SELECT $cols, c.id AS class_id, c.code AS class_code
             FROM exam_classes ec
             JOIN classes c ON c.id = ec.class_id
             JOIN class_students cs ON cs.class_id = c.id
             JOIN users u ON u.id = cs.user_id
             LEFT JOIN exam_attempts a ON a.exam_id = ec.exam_id AND a.user_id = u.id
             WHERE ec.exam_id = ? $classSql
             ORDER BY c.code, u.username"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Bài làm của người không còn trong lớp nào được thi (đã chuyển / rời lớp)
        if ($classId === 0) {
            $stmt = self::db()->prepare(
                "SELECT $cols, NULL AS class_id, NULL AS class_code
                 FROM exam_attempts a JOIN users u ON u.id = a.user_id
                 WHERE a.exam_id = ? AND NOT EXISTS (
                     SELECT 1 FROM class_students cs JOIN exam_classes ec ON ec.class_id = cs.class_id
                     WHERE cs.user_id = a.user_id AND ec.exam_id = a.exam_id)
                 ORDER BY u.username"
            );
            $stmt->execute([$examId]);
            array_push($rows, ...$stmt->fetchAll());
        }
        return $rows;
    }

    /** Trạng thái hiển thị của một dòng bảng điểm: absent | working | done */
    public static function rowState(array $row): string
    {
        if ($row['attempt_id'] === null) {
            return 'absent';
        }
        return $row['status'] === 'submitted' ? 'done' : 'working';
    }

    // ----- Thống kê (hàm thuần) -----

    /** Số giây làm bài, null nếu chưa nộp */
    public static function secondsSpent(array $row): ?int
    {
        if ($row['attempt_id'] === null || $row['status'] !== 'submitted' || !$row['submitted_at']) {
            return null;
        }
        return max(0, strtotime($row['submitted_at']) - strtotime($row['started_at']));
    }

    /**
     * Tổng hợp từ bảng điểm: tổng số, đã nộp, đang làm, chưa thi, điểm TB / cao / thấp / trung vị,
     * số bài và tỷ lệ đạt, phân bố 10 khoảng điểm [0,1) ... [9,10] (điểm 10 nằm ở khoảng cuối).
     */
    public static function summary(array $rows): array
    {
        $scores = [];
        $absent = $working = 0;
        foreach ($rows as $r) {
            switch (self::rowState($r)) {
                case 'absent':
                    $absent++;
                    break;
                case 'working':
                    $working++;
                    break;
                default:
                    $scores[] = (float) $r['score'];
            }
        }
        sort($scores);
        $n = count($scores);

        $median = null;
        if ($n > 0) {
            $median = $n % 2 ? $scores[intdiv($n, 2)] : ($scores[$n / 2 - 1] + $scores[$n / 2]) / 2;
        }

        $buckets = array_fill(0, 10, 0);
        $passed = 0;
        foreach ($scores as $s) {
            $buckets[min(9, max(0, (int) floor($s)))]++;
            if ($s >= self::passScore()) {
                $passed++;
            }
        }

        return [
            'total'     => count($rows),
            'submitted' => $n,
            'working'   => $working,
            'absent'    => $absent,
            'avg'       => $n ? round(array_sum($scores) / $n, 2) : null,
            'max'       => $n ? max($scores) : null,
            'min'       => $n ? min($scores) : null,
            'median'    => $median === null ? null : round($median, 2),
            'passed'    => $passed,
            'pass_rate' => $n ? $passed / $n : null,
            'buckets'   => $buckets,
        ];
    }

    /** Điểm trung bình / số bài đã nộp theo từng lớp: [class_code => ['students', 'submitted', 'avg']] */
    public static function byClass(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $key = $r['class_code'] ?? '(đã rời lớp)';
            $out[$key] ??= ['students' => 0, 'submitted' => 0, 'sum' => 0.0];
            $out[$key]['students']++;
            if (self::rowState($r) === 'done') {
                $out[$key]['submitted']++;
                $out[$key]['sum'] += (float) $r['score'];
            }
        }
        foreach ($out as &$o) {
            $o['avg'] = $o['submitted'] ? round($o['sum'] / $o['submitted'], 2) : null;
            unset($o['sum']);
        }
        return $out;
    }

    // ----- Phân tích từng câu -----

    /**
     * Mỗi câu của đề: id, content, difficulty, answered (số bài đã nộp có câu này),
     * full_count (số bài đúng hoàn toàn), avg_earned (0 - 1, null nếu chưa có bài nộp).
     * Chỉ tính bài đã nộp; $classId > 0 thì chỉ tính sinh viên lớp đó.
     */
    public static function questionStats(int $examId, int $classId = 0): array
    {
        $classSql = $classId > 0 ? ' AND a.user_id IN (SELECT user_id FROM class_students WHERE class_id = ?)' : '';
        $stmt = self::db()->prepare(
            "SELECT q.id, q.content, q.difficulty, eq.position,
                    COUNT(aq.attempt_id) AS answered,
                    COALESCE(SUM(aq.earned >= 1), 0) AS full_count,
                    AVG(aq.earned) AS avg_earned
             FROM exam_questions eq
             JOIN questions q ON q.id = eq.question_id
             LEFT JOIN exam_attempt_questions aq ON aq.question_id = q.id
                  AND aq.attempt_id IN (SELECT a.id FROM exam_attempts a WHERE a.exam_id = ? AND a.status = 'submitted' $classSql)
             WHERE eq.exam_id = ?
             GROUP BY q.id, q.content, q.difficulty, eq.position
             ORDER BY eq.position, q.id"
        );
        // Thứ tự tham số trong SQL: exam (trong truy vấn con), [class], exam (WHERE cuối)
        $ordered = [$examId];
        if ($classId > 0) {
            $ordered[] = $classId;
        }
        $ordered[] = $examId;
        $stmt->execute($ordered);
        return $stmt->fetchAll();
    }

    // ----- Chi tiết một bài làm (giáo viên xem) -----

    /** Một bài làm của kỳ thi $examId, kèm thông tin sinh viên; null nếu không thuộc kỳ thi này */
    public static function attemptOfExam(int $attemptId, int $examId): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT a.*, u.username, ' . User::codeCol() . ', u.full_name,
                    (SELECT c.code FROM class_students cs JOIN classes c ON c.id = cs.class_id WHERE cs.user_id = u.id LIMIT 1) AS class_code
             FROM exam_attempts a JOIN users u ON u.id = a.user_id
             WHERE a.id = ? AND a.exam_id = ? LIMIT 1'
        );
        $stmt->execute([$attemptId, $examId]);
        return $stmt->fetch() ?: null;
    }
}
