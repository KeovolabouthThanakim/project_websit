<?php
namespace App\Core;

use App\Models\User;

class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    /** Hash giả để thời gian xử lý như nhau dù tài khoản có tồn tại hay không */
    private const DUMMY_HASH = '$2y$10$ST//9vm6giGMPvVKnRYQc.NEEppJYYjUF0nRjGwZeFQdgDkUE3VQC';

    /** Kiểm tra tên đăng nhập + mật khẩu. Trả về user nếu đúng, null nếu sai */
    public static function attempt(string $username, string $password): ?array
    {
        $user = User::findByUsername($username);
        $valid = password_verify($password, $user['password_hash'] ?? self::DUMMY_HASH);

        if (!$user || !$valid || !$user['is_active']) {
            return null;
        }
        return $user;
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        self::$loaded = false;
    }

    public static function logout(): void
    {
        Session::forget('user_id');
        Session::regenerate();
        self::$user = null;
        self::$loaded = false;
    }

    /** Người dùng đang đăng nhập (không chứa password_hash) hoặc null */
    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = Session::get('user_id');
            $user = $id ? User::find((int) $id) : null;

            if ($user && $user['is_active']) {
                unset($user['password_hash']);
                self::$user = $user;
            } else {
                self::$user = null;
            }
        }
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }
}
