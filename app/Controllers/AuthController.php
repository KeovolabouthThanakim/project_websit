<?php
namespace App\Controllers;

use App\Models\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Core\Throttle;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->view('auth/login', ['title' => 'Đăng nhập'], 'layouts/auth');
    }

    public function login(): void
    {
        $username = trim((string) $this->request->input('username', ''));
        $password = (string) $this->request->input('password', '');

        if ($username === '' || $password === '') {
            Session::flash('error', 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.');
            Session::flash('old_username', $username);
            $this->redirect('/login');
        }

        // Chống dò mật khẩu: sai quá nhiều lần thì tạm khóa, kể cả khi lần sau nhập đúng
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '?');
        $keyUser = 'login-user:' . strtolower($username) . '|' . $ip;
        $keyIp = 'login-ip:' . $ip;
        $window = max(1, (int) sec('login_window_minutes', 15)) * 60;
        $wait = max(
            Throttle::retryAfter($keyUser, max(1, (int) sec('login_max_attempts', 5)), $window),
            Throttle::retryAfter($keyIp, max(1, (int) sec('login_ip_max_attempts', 30)), $window)
        );
        if ($wait > 0) {
            Audit::log('auth.locked', 'Tên đăng nhập: ' . str_cut($username, 50), ['username' => str_cut($username, 50)]);
            Session::flash('error', 'Bạn đã nhập sai quá nhiều lần. Vui lòng thử lại sau ' . (int) ceil($wait / 60) . ' phút.');
            Session::flash('old_username', $username);
            $this->redirect('/login');
        }

        $user = Auth::attempt($username, $password);

        if ($user === null) {
            Throttle::hit($keyUser, $window);
            Throttle::hit($keyIp, $window);
            Audit::log('auth.login_failed', 'Tên đăng nhập: ' . str_cut($username, 50), ['username' => str_cut($username, 50)]);
            // Cùng 1 thông báo cho mọi trường hợp sai, tránh lộ tài khoản nào tồn tại
            Session::flash('error', 'Tên đăng nhập hoặc mật khẩu không đúng.');
            Session::flash('old_username', $username);
            $this->redirect('/login');
        }

        Throttle::clear($keyUser);
        Auth::login($user);
        Audit::log('auth.login', '', $user);
        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        Auth::logout();
        Session::flash('success', 'Bạn đã đăng xuất.');
        $this->redirect('/login');
    }
}
