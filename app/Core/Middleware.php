<?php
namespace App\Core;

class Middleware
{
    /** Chạy lần lượt các middleware: 'auth', 'guest', 'role:admin,teacher' */
    public static function run(array $names): void
    {
        foreach ($names as $name) {
            [$type, $arg] = array_pad(explode(':', $name, 2), 2, '');

            switch ($type) {
                case 'auth':
                    if (!Auth::check()) {
                        Session::flash('error', 'Vui lòng đăng nhập để tiếp tục.');
                        Response::redirect('/login');
                    }
                    break;

                case 'guest':
                    if (Auth::check()) {
                        Response::redirect('/dashboard');
                    }
                    break;

                case 'role':
                    $user = Auth::user();
                    if (!$user || !in_array($user['role'], explode(',', $arg), true)) {
                        Response::abort(403);
                    }
                    break;

                default:
                    throw new \RuntimeException("Middleware không tồn tại: $name");
            }
        }
    }
}
