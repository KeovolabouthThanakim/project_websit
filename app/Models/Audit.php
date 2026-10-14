<?php
namespace App\Models;

use App\Core\App;
use App\Core\Auth;
use App\Core\Model;

/**
 * Nhật ký hoạt động. Audit::log() không bao giờ làm hỏng thao tác chính: chưa nhập 013_audit_logs.sql
 * hoặc ghi lỗi thì chỉ ghi vào error_log.
 */
class Audit extends Model
{
    protected static string $table = 'audit_logs';

    private static ?bool $ready = null;

    /** Nhóm lọc (tiền tố trước dấu chấm của action) */
    public const GROUPS = [
        'auth'     => 'Đăng nhập, mật khẩu',
        'profile'  => 'Hồ sơ cá nhân',
        'user'     => 'Người dùng',
        'exam'     => 'Kỳ thi',
        'grant'    => 'Cấp quyền thi',
        'class'    => 'Lớp học',
        'subject'  => 'Môn học',
        'settings' => 'Cài đặt',
        'backup'   => 'Sao lưu',
    ];

    public const LABELS = [
        'auth.login'          => 'Đăng nhập',
        'auth.login_failed'   => 'Đăng nhập sai',
        'auth.locked'         => 'Bị tạm khóa đăng nhập',
        'auth.password_reset' => 'Đặt lại mật khẩu qua email',
        'profile.password'    => 'Tự đổi mật khẩu',
        'profile.email'       => 'Tự đổi email',
        'user.create'         => 'Thêm người dùng',
        'user.update'         => 'Sửa người dùng',
        'user.password'       => 'Đặt lại mật khẩu người dùng',
        'user.lock'           => 'Khóa tài khoản',
        'user.unlock'         => 'Mở khóa tài khoản',
        'user.delete'         => 'Xóa người dùng',
        'user.import'         => 'Nhập người dùng từ file',
        'exam.publish'        => 'Công bố đề thi',
        'exam.close'          => 'Đóng đề thi',
        'exam.reopen'         => 'Mở lại đề thi',
        'exam.unpublish'      => 'Chuyển đề thi về nháp',
        'exam.copy'           => 'Sao chép đề thi',
        'exam.delete'         => 'Xóa đề thi',
        'grant.extend'        => 'Cộng thêm thời gian',
        'grant.retake'        => 'Cho thi lại',
        'grant.allow'         => 'Cho vào thi muộn',
        'grant.revoke'        => 'Thu hồi quyền vào thi',
        'class.delete'        => 'Xóa lớp',
        'subject.delete'      => 'Xóa môn học',
        'settings.update'     => 'Đổi cài đặt hệ thống',
        'backup.download'     => 'Tải bản sao lưu',
        'backup.save'         => 'Lưu bản sao lưu trên máy chủ',
        'backup.delete'       => 'Xóa bản sao lưu',
        'backup.restore'      => 'Khôi phục dữ liệu',
    ];

    public static function ready(): bool
    {
        if (self::$ready === null) {
            try {
                self::db()->query('SELECT 1 FROM audit_logs LIMIT 1');
                self::$ready = true;
            } catch (\Throwable $e) {
                self::$ready = false;
            }
        }
        return self::$ready;
    }

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }

    /**
     * Ghi một dòng nhật ký. $actor mặc định là người đang đăng nhập; truyền mảng
     * ['id'=>?, 'username'=>?, 'role'=>?] khi chưa đăng nhập (vd đăng nhập sai).
     */
    public static function log(string $action, string $detail = '', ?array $actor = null): void
    {
        try {
            if (!self::ready()) {
                return;
            }
            $actor = $actor ?? Auth::user() ?? [];
            $stmt = self::db()->prepare('INSERT INTO audit_logs (user_id, username, role, action, detail, ip) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                isset($actor['id']) ? (int) $actor['id'] : null,
                isset($actor['username']) ? str_cut((string) $actor['username'], 50) : null,
                isset($actor['role']) ? str_cut((string) $actor['role'], 10) : null,
                str_cut($action, 40),
                $detail === '' ? null : str_cut($detail, 500),
                str_cut((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 45) ?: null,
            ]);
            if (random_int(1, 500) === 1) {
                self::purge();
            }
        } catch (\Throwable $e) {
            error_log('Audit::log: ' . $e->getMessage());
        }
    }

    /** Xóa nhật ký cũ hơn audit.retention_days (mặc định 365 ngày) */
    public static function purge(): void
    {
        $days = max(30, (int) ((App::config('audit', []) ?? [])['retention_days'] ?? 365));
        self::db()->prepare('DELETE FROM audit_logs WHERE created_at < (NOW() - INTERVAL ? DAY)')->execute([$days]);
    }

    /** Tìm kiếm + lọc + phân trang. $f: q, group, from (Y-m-d), to (Y-m-d) */
    public static function search(array $f, int $page, int $perPage = 30): array
    {
        if (!self::ready()) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        }
        $where = [];
        $params = [];
        if (($f['q'] ?? '') !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['q']) . '%';
            $where[] = '(username LIKE ? OR detail LIKE ? OR ip LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        if (($f['group'] ?? '') !== '' && isset(self::GROUPS[$f['group']])) {
            $where[] = 'action LIKE ?';
            $params[] = $f['group'] . '.%';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['from'] ?? ''))) {
            $where[] = 'created_at >= ?';
            $params[] = $f['from'] . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['to'] ?? ''))) {
            $where[] = 'created_at < DATE_ADD(?, INTERVAL 1 DAY)';
            $params[] = $f['to'];
        }
        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = self::db()->prepare("SELECT COUNT(*) FROM audit_logs $sqlWhere");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = self::db()->prepare("SELECT * FROM audit_logs $sqlWhere ORDER BY id DESC LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);
        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }
}
