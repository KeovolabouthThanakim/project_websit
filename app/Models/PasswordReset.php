<?php
namespace App\Models;

use App\Core\Model;

/** Mã đặt lại mật khẩu. Chỉ lưu SHA-256 của mã; mã gốc chỉ nằm trong email. */
class PasswordReset extends Model
{
    protected static string $table = 'password_resets';
    protected static array $fillable = ['user_id', 'token_hash', 'expires_at'];

    private const FORMAT = 'Y-m-d H:i:s';

    /** Tạo mã mới cho người dùng (mã cũ của họ bị hủy). Trả về mã gốc để đưa vào email */
    public static function issue(int $userId, int $ttlMinutes): string
    {
        // Dọn mã hết hạn của mọi người và mã cũ của người này
        static::db()->prepare('DELETE FROM password_resets WHERE expires_at < ? OR user_id = ?')
            ->execute([date(self::FORMAT), $userId]);

        $token = bin2hex(random_bytes(32));
        self::create([
            'user_id'    => $userId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => date(self::FORMAT, time() + $ttlMinutes * 60),
        ]);
        return $token;
    }

    /** Trả về dòng mã còn hiệu lực, hoặc null */
    public static function findValid(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $stmt = static::db()->prepare('SELECT * FROM password_resets WHERE token_hash = ? AND expires_at > ? LIMIT 1');
        $stmt->execute([hash('sha256', $token), date(self::FORMAT)]);
        return $stmt->fetch() ?: null;
    }

    /** Người này vừa yêu cầu trong $seconds giây gần đây? (chống gửi email liên tục) */
    public static function requestedRecently(int $userId, int $seconds = 60): bool
    {
        $stmt = static::db()->prepare('SELECT 1 FROM password_resets WHERE user_id = ? AND created_at > ? LIMIT 1');
        $stmt->execute([$userId, date(self::FORMAT, time() - $seconds)]);
        return (bool) $stmt->fetchColumn();
    }

    public static function deleteForUser(int $userId): void
    {
        static::db()->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);
    }
}
