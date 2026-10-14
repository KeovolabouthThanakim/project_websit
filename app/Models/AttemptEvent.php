<?php
namespace App\Models;

use App\Core\App;
use App\Core\Model;

/**
 * Nhật ký hành vi trong lúc thi ("exam_events"): sinh viên chuyển tab, rời cửa sổ, sao chép nội dung...
 *
 * Quan trọng: dữ liệu do TRÌNH DUYỆT của sinh viên báo về nên có thể bị chặn hoặc làm giả (tắt JavaScript,
 * dùng thiết bị thứ hai...). Đây chỉ là dấu hiệu để giáo viên tham khảo, KHÔNG dùng để tự động trừ điểm hay kết luận gian lận.
 * Mọi mốc thời gian do máy chủ ghi, không tin giờ của trình duyệt.
 *
 * Chưa nhập database/009_exam_events.sql thì ready() = false và mọi hàm đọc trả về rỗng, hàm ghi không làm gì,
 * để không làm hỏng trang thi.
 */
class AttemptEvent extends Model
{
    protected static string $table = 'exam_events';
    protected static array $fillable = ['attempt_id', 'type', 'created_at', 'returned_at'];

    public const TYPES = [
        'hidden'      => 'Chuyển tab / thu nhỏ trình duyệt',
        'blur'        => 'Rời khỏi cửa sổ làm bài',
        'copy'        => 'Sao chép nội dung trang',
        'contextmenu' => 'Bấm chuột phải',
    ];
    /** Các loại tính là "rời khỏi trang thi" (có thời gian vắng mặt) */
    public const LEAVE_TYPES = ['hidden', 'blur'];
    /** Từ ngần này lần rời trang trở lên thì cảnh báo mức cao */
    public const WARN_LEAVES = 3;
    /** Giới hạn số dòng nhật ký của một bài làm (chống bị gửi dồn dập làm đầy CSDL) */
    public const MAX_PER_ATTEMPT = 300;
    /** Hai sự kiện cùng loại cách nhau dưới ngần này giây thì chỉ ghi một */
    private const DEDUPE_SECONDS = 2;

    private static ?bool $ready = null;

    /** Bảng exam_events đã có chưa (đã nhập 009_exam_events.sql chưa) */
    public static function ready(): bool
    {
        if (self::$ready === null) {
            try {
                self::db()->query('SELECT 1 FROM exam_events LIMIT 1');
                self::$ready = true;
            } catch (\PDOException $e) {
                self::$ready = false;
            }
        }
        return self::$ready;
    }

    /** Có đang ghi nhật ký không: bật trong config (exam.track_events, mặc định bật) và đã có bảng */
    public static function enabled(): bool
    {
        return (bool) ((App::config('exam') ?? [])['track_events'] ?? true) && self::ready();
    }

    // ----- Ghi -----

    /** Ghi một sự kiện. Trả về false nếu bị bỏ qua (loại không hợp lệ, trùng lặp, quá giới hạn, chưa bật). */
    public static function record(int $attemptId, string $type): bool
    {
        if (!isset(self::TYPES[$type]) || !self::enabled()) {
            return false;
        }
        $pdo = self::db();
        $now = time();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM exam_events WHERE attempt_id = ?');
        $stmt->execute([$attemptId]);
        if ((int) $stmt->fetchColumn() >= self::MAX_PER_ATTEMPT) {
            return false;
        }

        $stmt = $pdo->prepare('SELECT type, created_at FROM exam_events WHERE attempt_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$attemptId]);
        $last = $stmt->fetch();
        if ($last && $last['type'] === $type && $now - strtotime($last['created_at']) < self::DEDUPE_SECONDS) {
            return false;
        }

        // Có sự kiện rời trang mới nghĩa là sinh viên đã quay lại ở giữa nhưng thông báo "đã quay lại" bị thất lạc
        if (in_array($type, self::LEAVE_TYPES, true)) {
            self::close($attemptId, $now);
        }
        self::create(['attempt_id' => $attemptId, 'type' => $type, 'created_at' => date('Y-m-d H:i:s', $now)]);
        return true;
    }

    /** Sinh viên đã quay lại trang thi: đóng các lần rời trang đang mở */
    public static function close(int $attemptId, ?int $at = null): void
    {
        if (!self::ready()) {
            return;
        }
        self::db()->prepare(
            "UPDATE exam_events SET returned_at = ? WHERE attempt_id = ? AND returned_at IS NULL AND type IN ('hidden','blur')"
        )->execute([date('Y-m-d H:i:s', $at ?? time()), $attemptId]);
    }

    // ----- Đọc -----

    public static function leaveCount(int $attemptId): int
    {
        if (!self::ready()) {
            return 0;
        }
        $stmt = self::db()->prepare("SELECT COUNT(*) FROM exam_events WHERE attempt_id = ? AND type IN ('hidden','blur')");
        $stmt->execute([$attemptId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Tổng hợp theo từng bài làm của một kỳ thi: [attempt_id => ['leaves' => số lần rời trang,
     * 'others' => số sự kiện khác (sao chép, chuột phải), 'away' => tổng số giây vắng mặt]].
     * Lần rời trang chưa quay lại được tính đến lúc nộp bài (hoặc đến bây giờ nếu đang làm bài).
     */
    public static function summaryForExam(int $examId): array
    {
        if (!self::ready()) {
            return [];
        }
        $stmt = self::db()->prepare(
            "SELECT ev.attempt_id,
                    SUM(ev.type IN ('hidden','blur')) AS leaves,
                    SUM(ev.type NOT IN ('hidden','blur')) AS others,
                    SUM(CASE WHEN ev.type IN ('hidden','blur')
                             THEN GREATEST(0, TIMESTAMPDIFF(SECOND, ev.created_at, COALESCE(ev.returned_at, a.submitted_at, ?)))
                             ELSE 0 END) AS away
             FROM exam_events ev JOIN exam_attempts a ON a.id = ev.attempt_id
             WHERE a.exam_id = ?
             GROUP BY ev.attempt_id"
        );
        $stmt->execute([date('Y-m-d H:i:s'), $examId]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[(int) $r['attempt_id']] = ['leaves' => (int) $r['leaves'], 'others' => (int) $r['others'], 'away' => (int) $r['away']];
        }
        return $out;
    }

    /**
     * Nhật ký chi tiết của một bài làm, cũ -> mới. Mỗi dòng: type, label, at, away (giây, null nếu không áp dụng),
     * open (rời trang mà chưa quay lại trước khi nộp / đến hiện tại).
     * $endAt: mốc kết thúc để tính cho lần rời trang chưa quay lại (thường là giờ nộp bài).
     */
    public static function forAttempt(int $attemptId, ?string $endAt = null): array
    {
        if (!self::ready()) {
            return [];
        }
        $stmt = self::db()->prepare('SELECT type, created_at, returned_at FROM exam_events WHERE attempt_id = ? ORDER BY id');
        $stmt->execute([$attemptId]);
        $end = $endAt ? strtotime($endAt) : time();

        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $isLeave = in_array($r['type'], self::LEAVE_TYPES, true);
            $open = $isLeave && $r['returned_at'] === null;
            $away = null;
            if ($isLeave) {
                $to = $r['returned_at'] !== null ? strtotime($r['returned_at']) : $end;
                $away = max(0, $to - strtotime($r['created_at']));
            }
            $out[] = ['type' => $r['type'], 'label' => self::TYPES[$r['type']] ?? $r['type'], 'at' => $r['created_at'], 'away' => $away, 'open' => $open];
        }
        return $out;
    }

    // ----- Hàm thuần -----

    /** Mức cảnh báo theo số lần rời trang: none | note (1-2 lần) | warn (từ WARN_LEAVES lần) */
    public static function level(int $leaves, int $others = 0): string
    {
        if ($leaves >= self::WARN_LEAVES) {
            return 'warn';
        }
        return ($leaves > 0 || $others > 0) ? 'note' : 'none';
    }
}
