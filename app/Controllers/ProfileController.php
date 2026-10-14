<?php
namespace App\Controllers;

use App\Models\Audit;
use App\Core\Controller;
use App\Core\Session;
use App\Core\Throttle;
use App\Models\Classroom;
use App\Models\User;

/** Hồ sơ cá nhân: mọi vai trò tự xem thông tin, đổi email và đổi mật khẩu của chính mình */
class ProfileController extends Controller
{
    private const MIN_PASSWORD = 8;
    private const MAX_WRONG    = 5;      // sai mật khẩu hiện tại quá số lần này thì tạm khóa
    private const WINDOW       = 900;    // trong 15 phút

    public function show(): void
    {
        $user = $this->user();
        $this->view('profile/index', [
            'title' => 'Hồ sơ của tôi',
            'user'  => $user,
            'class' => $user['role'] === 'student' ? Classroom::ofStudent((int) $user['id']) : null,
        ]);
    }

    public function updateEmail(): void
    {
        $me = $this->user();
        $email = strtolower(trim((string) $this->request->input('email', '')));

        $error = '';
        if ($email !== '') {
            if (str_len($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Địa chỉ email không hợp lệ.';
            } elseif (User::taken('email', $email, (int) $me['id'])) {
                $error = 'Email này đã có người sử dụng.';
            }
        }
        if ($error !== '') {
            Session::flash('errors', ['email' => $error]);
            Session::flash('old_email', $email);
            Session::flash('error', 'Vui lòng kiểm tra lại email.');
            $this->redirect('/profile');
        }

        User::update((int) $me['id'], ['email' => $email === '' ? null : $email]);
        Audit::log('profile.email', $email === '' ? 'Xóa email' : 'Đổi email thành ' . $email);
        Session::flash('success', $email === '' ? 'Đã xóa email khỏi tài khoản.' : 'Đã cập nhật email.');
        $this->redirect('/profile');
    }

    public function updatePassword(): void
    {
        $me = $this->user();
        $current = (string) $this->request->input('current_password', '');
        $new     = (string) $this->request->input('password', '');
        $confirm = (string) $this->request->input('password_confirm', '');

        // Chống dò mật khẩu hiện tại (kẻ ngồi nhờ máy đang đăng nhập)
        $key = 'profile-pw:' . (int) $me['id'];
        $wait = Throttle::retryAfter($key, self::MAX_WRONG, self::WINDOW);
        if ($wait > 0) {
            Session::flash('error', 'Bạn đã nhập sai mật khẩu hiện tại quá nhiều lần. Vui lòng thử lại sau ' . (int) ceil($wait / 60) . ' phút.');
            $this->redirect('/profile');
        }

        $row = User::find((int) $me['id']);
        $errors = [];
        if ($current === '' || !$row || !password_verify($current, (string) $row['password_hash'])) {
            $errors['current_password'] = 'Mật khẩu hiện tại không đúng.';
            Throttle::hit($key, self::WINDOW);
        }
        if (str_len($new) < self::MIN_PASSWORD) {
            $errors['password'] = 'Mật khẩu mới phải có ít nhất ' . self::MIN_PASSWORD . ' ký tự.';
        } elseif (strlen($new) > 72) {
            $errors['password'] = 'Mật khẩu mới tối đa 72 ký tự.';
        } elseif ($new === $current) {
            $errors['password'] = 'Mật khẩu mới phải khác mật khẩu hiện tại.';
        } elseif ($new !== $confirm) {
            $errors['password_confirm'] = 'Mật khẩu nhập lại không khớp.';
        }

        if ($errors) {
            Session::flash('errors', $errors);
            Session::flash('error', 'Chưa đổi được mật khẩu, vui lòng kiểm tra các ô bị đánh dấu lỗi.');
            $this->redirect('/profile');
        }

        User::update((int) $me['id'], ['password_hash' => password_hash($new, PASSWORD_DEFAULT)]);
        Throttle::clear($key);
        Audit::log('profile.password');
        Session::regenerate();
        Session::flash('success', 'Đã đổi mật khẩu. Lần đăng nhập sau hãy dùng mật khẩu mới.');
        $this->redirect('/profile');
    }
}
