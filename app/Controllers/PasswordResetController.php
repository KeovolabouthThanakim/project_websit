<?php
namespace App\Controllers;

use App\Models\Audit;
use App\Core\App;
use App\Core\Controller;
use App\Core\Mailer;
use App\Core\Session;
use App\Models\PasswordReset;
use App\Models\User;

/** Quên mật khẩu: nhập tên đăng nhập/email -> nhận liên kết qua email -> đặt mật khẩu mới */
class PasswordResetController extends Controller
{
    private const MIN_PASSWORD = 8;

    private function settings(): array
    {
        $c = App::config('password_reset', []);
        return [
            'roles' => $c['roles'] ?? ['admin'],
            'ttl'   => max(5, (int) ($c['ttl_minutes'] ?? 30)),
        ];
    }

    public function showForgot(): void
    {
        $s = $this->settings();
        $this->view('auth/forgot', [
            'title'     => 'Quên mật khẩu',
            'sent'      => (bool) Session::pullFlash('reset_sent', false),
            'ttl'       => $s['ttl'],
            'adminOnly' => $s['roles'] === ['admin'],
        ], 'layouts/auth');
    }

    public function sendLink(): void
    {
        $s = $this->settings();
        $identifier = trim((string) $this->request->input('identifier', ''));

        if ($identifier === '') {
            Session::flash('error', 'Vui lòng nhập tên đăng nhập hoặc email.');
            $this->redirect('/forgot-password');
        }

        $user = str_contains($identifier, '@') ? User::findBy('email', $identifier) : User::findByUsername($identifier);

        $eligible = $user
            && $user['is_active']
            && in_array($user['role'], $s['roles'], true)
            && !empty($user['email'])
            && !PasswordReset::requestedRecently((int) $user['id']);

        if ($eligible) {
            $token = PasswordReset::issue((int) $user['id'], $s['ttl']);
            $link = $this->absoluteUrl('/reset-password?token=' . $token);
            [$subject, $html, $text] = $this->mailContent($user['full_name'], $link, $s['ttl']);

            if (!Mailer::send($user['email'], $subject, $html, $text)) {
                error_log('Gửi email đặt lại mật khẩu thất bại: ' . Mailer::lastError());
                if (App::config('debug')) {
                    Session::flash('error', 'Không gửi được email: ' . Mailer::lastError());
                    $this->redirect('/forgot-password');
                }
            }
        }

        // Luôn trả lời giống nhau để không lộ tài khoản nào tồn tại
        Session::flash('reset_sent', true);
        $this->redirect('/forgot-password');
    }

    public function showReset(): void
    {
        $token = (string) $this->request->input('token', '');
        if (!PasswordReset::findValid($token)) {
            $this->invalidLink();
        }
        $this->view('auth/reset', ['title' => 'Đặt lại mật khẩu', 'token' => $token, 'min' => self::MIN_PASSWORD], 'layouts/auth');
    }

    public function reset(): void
    {
        $token = (string) $this->request->input('token', '');
        $row = PasswordReset::findValid($token);
        if (!$row) {
            $this->invalidLink();
        }
        $user = User::find((int) $row['user_id']);
        if (!$user || !$user['is_active']) {
            $this->invalidLink();
        }

        $password = (string) $this->request->input('password', '');
        $confirm = (string) $this->request->input('password_confirm', '');
        $errors = [];
        if (str_len($password) < self::MIN_PASSWORD) {
            $errors['password'] = 'Mật khẩu phải có ít nhất ' . self::MIN_PASSWORD . ' ký tự.';
        } elseif (strlen($password) > 72) {
            $errors['password'] = 'Mật khẩu tối đa 72 ký tự.';
        } elseif ($password !== $confirm) {
            $errors['password_confirm'] = 'Mật khẩu nhập lại không khớp.';
        }
        if ($errors) {
            Session::flash('errors', $errors);
            $this->redirect('/reset-password?token=' . $token);
        }

        User::update((int) $user['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        PasswordReset::deleteForUser((int) $user['id']);   // mã chỉ dùng được một lần
        Audit::log('auth.password_reset', '', $user);

        Session::flash('success', 'Đặt lại mật khẩu thành công. Hãy đăng nhập bằng mật khẩu mới.');
        $this->redirect('/login');
    }

    private function invalidLink(): void
    {
        Session::flash('error', 'Liên kết không hợp lệ hoặc đã hết hạn. Vui lòng yêu cầu lại.');
        $this->redirect('/forgot-password');
    }

    /** Liên kết đầy đủ để đưa vào email */
    private function absoluteUrl(string $path): string
    {
        $base = App::config('app_url');
        if (!$base) {
            $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            if (!preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $host)) {
                $host = 'localhost';
            }
            $base = ($https ? 'https://' : 'http://') . $host . App::$baseUrl;
        }
        return rtrim((string) $base, '/') . $path;
    }

    /** @return array{0:string,1:string,2:string} [tiêu đề, HTML, văn bản thuần] */
    private function mailContent(string $name, string $link, int $ttl): array
    {
        $subject = 'Đặt lại mật khẩu - ' . system_name();
        $text = "Xin chào $name,\n\n"
              . "Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.\n"
              . "Mở liên kết sau để đặt mật khẩu mới (có hiệu lực $ttl phút, chỉ dùng được một lần):\n\n$link\n\n"
              . "Nếu bạn không yêu cầu, hãy bỏ qua email này - mật khẩu hiện tại vẫn giữ nguyên.\n";
        $n = e($name);
        $l = e($link);
        $school = e(school_short());
        $system = e(system_name());
        $html = <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:0 auto;color:#1d1517">
  <h2 style="margin:0 0 12px">Đặt lại mật khẩu</h2>
  <p>Xin chào <strong>$n</strong>,</p>
  <p>Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn trên $system ($school).</p>
  <p style="margin:24px 0"><a href="$l" style="background:#c8202f;color:#fff;text-decoration:none;padding:12px 24px;border-radius:10px;font-weight:600;display:inline-block">Đặt mật khẩu mới</a></p>
  <p style="font-size:14px;color:#7a6f73">Liên kết có hiệu lực <strong>$ttl phút</strong> và chỉ dùng được một lần. Nếu nút không bấm được, hãy sao chép địa chỉ sau vào trình duyệt:<br><span style="word-break:break-all">$l</span></p>
  <p style="font-size:14px;color:#7a6f73">Nếu bạn không yêu cầu, hãy bỏ qua email này - mật khẩu hiện tại vẫn giữ nguyên.</p>
</div>
HTML;
        return [$subject, $html, $text];
    }
}
