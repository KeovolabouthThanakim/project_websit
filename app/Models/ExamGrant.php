<?php
namespace App\Models;

use App\Core\Model;

/**
 * Quyền riêng của từng sinh viên với một kỳ thi (do giáo viên / quản trị viên cấp):
 *  - Thi muộn / thi bù: vào thi dù cổng thi chưa mở hoặc đã đóng, đến hạn valid_until.
 *  - Thi lại: bài đã làm (đã nộp hoặc đang làm dở) được lưu vào lịch sử rồi xóa, sinh viên làm lại từ đầu.
 *  - Cộng thêm thời gian: cho bài đang làm (rớt mạng...) hoặc cho lượt thi sắp tới.
 *
 * Mỗi sinh viên vẫn chỉ có tối đa 1 bài làm / kỳ thi tại một thời điểm (UNIQUE exam_id + user_id giữ nguyên),
 * nên các trang thống kê hiện có không phải sửa. Chưa nhập database/010_exam_grants.sql thì ready() = false
 * và mọi hàm đọc trả về rỗng.
 */
class ExamGrant extends Model
{
    protected static string $table = 'exam_grants';
    protected static array $fillable = ['exam_id', 'user_id', 'extra_minutes', 'valid_until', 'note', 'granted_by', 'used_at'];

    public const MAX_EXTRA_MINUTES = 240;
    public const MAX_VALID_HOURS = 168;      // 7 ngày
    public const DEFAULT_VALID_HOURS = 24;

    private static ?bool $ready = null;

    /** Đã nhập 010_exam_grants.sql chưa */
    public static function ready(): bool
    {
        if (self::$ready === null) {
            try {
                self::db()->query('SELECT 1 FROM exam_grants LIMIT 1');
                self::db()->query('SELECT 1 FROM exam_attempt_history LIMIT 1');
                self::$ready = true;
            } catch (\PDOException $e) {
                self::$ready = false;
            }
        }
        return self::$ready;
    }

    // ----- Đọc -----

    /** Quyền còn hiệu lực và chưa dùng của sinh viên với kỳ thi; null nếu không có */
    public static function activeFor(int $examId, int $userId): ?array
    {
        if (!self::ready()) {
            return null;
        }
        $stmt = self::db()->prepare(
            'SELECT * FROM exam_grants WHERE exam_id = ? AND user_id = ? AND used_at IS NULL AND valid_until > ? LIMIT 1'
        );
        $stmt->execute([$examId, $userId, date('Y-m-d H:i:s')]);
        return $stmt->fetch() ?: null;
    }

    /** [user_id => quyền còn hiệu lực chưa dùng] của cả kỳ thi */
    public static function activeForExam(int $examId): array
    {
        if (!self::ready()) {
            return [];
        }
        $stmt = self::db()->prepare('SELECT * FROM exam_grants WHERE exam_id = ? AND used_at IS NULL AND valid_until > ?');
        $stmt->execute([$examId, date('Y-m-d H:i:s')]);
        $out = [];
        foreach ($stmt->fetchAll() as $g) {
            $out[(int) $g['user_id']] = $g;
        }
        return $out;
    }

    /** [user_id => số lần đã cho thi lại] của cả kỳ thi */
    public static function historyCounts(int $examId): array
    {
        if (!self::ready()) {
            return [];
        }
        $stmt = self::db()->prepare('SELECT user_id, COUNT(*) FROM exam_attempt_history WHERE exam_id = ? GROUP BY user_id');
        $stmt->execute([$examId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_KEY_PAIR));
    }

    /** Các bài làm đã bị hủy để cho thi lại của một sinh viên (mới nhất trước) */
    public static function history(int $examId, int $userId): array
    {
        if (!self::ready()) {
            return [];
        }
        $stmt = self::db()->prepare(
            'SELECT h.*, u.full_name AS by_name FROM exam_attempt_history h LEFT JOIN users u ON u.id = h.archived_by
             WHERE h.exam_id = ? AND h.user_id = ? ORDER BY h.archived_at DESC, h.id DESC'
        );
        $stmt->execute([$examId, $userId]);
        return $stmt->fetchAll();
    }

    /** Sinh viên có thuộc một lớp được thi của kỳ thi, hoặc đã có bài làm kỳ thi này không (để chặn cấp quyền cho người lạ) */
    public static function studentInExam(int $examId, int $userId): ?array
    {
        $stmt = self::db()->prepare(
            "SELECT u.id, u.username, " . \App\Models\User::codeCol() . ", u.full_name, u.is_active,
                    (SELECT c.code FROM class_students cs JOIN classes c ON c.id = cs.class_id WHERE cs.user_id = u.id LIMIT 1) AS class_code
             FROM users u
             WHERE u.id = ? AND u.role = 'student' AND (
                 EXISTS (SELECT 1 FROM class_students cs JOIN exam_classes ec ON ec.class_id = cs.class_id
                         WHERE cs.user_id = u.id AND ec.exam_id = ?)
                 OR EXISTS (SELECT 1 FROM exam_attempts a WHERE a.exam_id = ? AND a.user_id = u.id))
             LIMIT 1"
        );
        $stmt->execute([$userId, $examId, $examId]);
        return $stmt->fetch() ?: null;
    }

    // ----- Ghi -----

    /** Cấp (hoặc cấp lại) quyền vào thi cho sinh viên. $hours: quyền có hiệu lực bao nhiêu giờ kể từ bây giờ */
    public static function give(int $examId, int $userId, int $extraMinutes, int $hours, ?string $note, int $by): void
    {
        $extraMinutes = max(0, min(self::MAX_EXTRA_MINUTES, $extraMinutes));
        $hours = max(1, min(self::MAX_VALID_HOURS, $hours));
        self::db()->prepare(
            'INSERT INTO exam_grants (exam_id, user_id, extra_minutes, valid_until, note, granted_by, created_at, used_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NULL)
             ON DUPLICATE KEY UPDATE extra_minutes = VALUES(extra_minutes), valid_until = VALUES(valid_until), note = VALUES(note),
                                     granted_by = VALUES(granted_by), created_at = VALUES(created_at), used_at = NULL'
        )->execute([$examId, $userId, $extraMinutes, date('Y-m-d H:i:s', time() + $hours * 3600), $note, $by, date('Y-m-d H:i:s')]);
    }

    /** Thu hồi quyền chưa dùng. Trả về true nếu có quyền bị thu hồi */
    public static function revoke(int $examId, int $userId): bool
    {
        $stmt = self::db()->prepare('DELETE FROM exam_grants WHERE exam_id = ? AND user_id = ? AND used_at IS NULL');
        $stmt->execute([$examId, $userId]);
        return $stmt->rowCount() > 0;
    }

    /** Đánh dấu sinh viên đã dùng quyền để bắt đầu làm bài */
    public static function markUsed(int $examId, int $userId): void
    {
        if (self::ready()) {
            self::db()->prepare('UPDATE exam_grants SET used_at = ? WHERE exam_id = ? AND user_id = ? AND used_at IS NULL')
                ->execute([date('Y-m-d H:i:s'), $examId, $userId]);
        }
    }

    /**
     * Cộng thêm $minutes phút cho bài đang làm. Tính từ mốc muộn hơn giữa "hạn hiện tại" và "bây giờ" nên vẫn có tác dụng
     * khi sinh viên đã lố giờ vài giây. Trả về hạn mới (Y-m-d H:i:s), hoặc null nếu bài không còn ở trạng thái đang làm.
     */
    public static function extend(int $attemptId, int $minutes): ?string
    {
        $minutes = max(1, min(self::MAX_EXTRA_MINUTES, $minutes));
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT deadline_at FROM exam_attempts WHERE id = ? AND status = 'in_progress' FOR UPDATE");
            $stmt->execute([$attemptId]);
            $row = $stmt->fetch();
            if (!$row) {
                $pdo->rollBack();
                return null;
            }
            $new = date('Y-m-d H:i:s', max(strtotime($row['deadline_at']), time()) + $minutes * 60);
            $pdo->prepare('UPDATE exam_attempts SET deadline_at = ? WHERE id = ?')->execute([$new, $attemptId]);
            $pdo->commit();
            return $new;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cho thi lại: lưu bài làm hiện tại vào lịch sử, xóa bài đó (kéo theo đáp án, đề riêng, nhật ký rời trang),
     * rồi cấp quyền vào thi. Tất cả trong 1 transaction. Trả về false nếu sinh viên không còn bài làm nào.
     */
    public static function retake(int $examId, int $userId, int $extraMinutes, int $hours, ?string $note, int $by): bool
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM exam_attempts WHERE exam_id = ? AND user_id = ? FOR UPDATE');
            $stmt->execute([$examId, $userId]);
            $a = $stmt->fetch();
            if (!$a) {
                $pdo->rollBack();
                return false;
            }
            $pdo->prepare(
                'INSERT INTO exam_attempt_history (exam_id, user_id, started_at, submitted_at, status, auto_submitted, score, total_questions, note, archived_by, archived_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$examId, $userId, $a['started_at'], $a['submitted_at'], $a['status'], $a['auto_submitted'], $a['score'],
                        $a['total_questions'], $note, $by, date('Y-m-d H:i:s')]);
            $pdo->prepare('DELETE FROM exam_attempts WHERE id = ?')->execute([$a['id']]);
            self::give($examId, $userId, $extraMinutes, $hours, $note, $by);
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
