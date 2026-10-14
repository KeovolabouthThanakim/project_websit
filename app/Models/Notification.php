<?php
namespace App\Models;

use App\Core\Model;

/**
 * Thông báo trong hệ thống. Mọi hàm đều an toàn khi chưa nhập 011_notifications.sql:
 * không có bảng thì coi như không có thông báo (không làm hỏng chức năng chính).
 */
class Notification extends Model
{
    protected static string $table = 'notifications';
    protected static array $fillable = ['user_id', 'title', 'body', 'url'];

    private static ?bool $ready = null;

    public static function ready(): bool
    {
        if (self::$ready === null) {
            try {
                self::db()->query('SELECT 1 FROM notifications LIMIT 1');
                self::$ready = true;
            } catch (\Throwable $e) {
                self::$ready = false;
            }
        }
        return self::$ready;
    }

    /** Gửi 1 thông báo cho nhiều người. Trả về số người đã nhận */
    public static function push(array $userIds, string $title, ?string $body = null, ?string $url = null): int
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds || !self::ready()) {
            return 0;
        }
        $title = str_cut($title, 160);
        $body = $body === null ? null : str_cut($body, 500);
        try {
            $stmt = self::db()->prepare('INSERT INTO notifications (user_id, title, body, url) VALUES (?, ?, ?, ?)');
            self::db()->beginTransaction();
            foreach ($userIds as $id) {
                $stmt->execute([$id, $title, $body, $url]);
            }
            self::db()->commit();
            return count($userIds);
        } catch (\Throwable $e) {
            if (self::db()->inTransaction()) {
                self::db()->rollBack();
            }
            error_log('Notification::push: ' . $e->getMessage());
            return 0;
        }
    }

    public static function unreadCount(int $userId): int
    {
        if (!self::ready()) {
            return 0;
        }
        $stmt = self::db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    /** Danh sách có phân trang, mới nhất trước */
    public static function paginate(int $userId, int $page, int $perPage = 20): array
    {
        if (!self::ready()) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        }
        $stmt = self::db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
        $stmt->execute([$userId]);
        $total = (int) $stmt->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;
        $stmt = self::db()->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT $perPage OFFSET $offset");
        $stmt->execute([$userId]);
        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** Lấy thông báo của chính người dùng (không đọc được của người khác) */
    public static function ofUser(int $id, int $userId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM notifications WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function markRead(int $id, int $userId): void
    {
        self::db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
    }

    public static function markAllRead(int $userId): void
    {
        if (self::ready()) {
            self::db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([$userId]);
        }
    }

    /** Xóa thông báo đã đọc quá $days ngày (gọi thủ công khi cần dọn) */
    public static function purgeOld(int $days = 90): void
    {
        if (self::ready()) {
            self::db()->prepare('DELETE FROM notifications WHERE is_read = 1 AND created_at < (NOW() - INTERVAL ? DAY)')->execute([$days]);
        }
    }
}
