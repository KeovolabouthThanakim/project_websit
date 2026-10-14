<?php
namespace App\Core;

/**
 * Gửi email không cần thư viện ngoài.
 * Driver (config 'mail.driver'): 'log' ghi file vào storage/mail/, 'smtp' gửi qua SMTP, 'mail' dùng hàm mail().
 */
class Mailer
{
    private static string $error = '';
    private static int $accepted = 0;

    public static function lastError(): string
    {
        return self::$error;
    }

    public static function send(string $to, string $subject, string $html, string $text): bool
    {
        self::$error = '';
        $cfg = App::config('mail', []);
        $to = self::clean($to);
        $from = self::clean((string) ($cfg['from_email'] ?? 'no-reply@localhost'));
        $fromName = self::fromName($cfg);

        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            self::$error = 'Địa chỉ email người gửi hoặc người nhận không hợp lệ.';
            return false;
        }

        $driver = $cfg['driver'] ?? 'log';
        if ($driver === 'log') {
            return self::writeLog($to, $subject, $text);
        }

        $boundary = 'b' . bin2hex(random_bytes(12));
        $headers = [
            'Date: ' . date('r'),
            'From: ' . self::encode($fromName) . " <$from>",
            "To: <$to>",
            'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . (explode('@', $from)[1] ?? 'localhost') . '>',
            'MIME-Version: 1.0',
            "Content-Type: multipart/alternative; boundary=\"$boundary\"",
        ];
        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($text))
              . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($html))
              . "--$boundary--\r\n";

        if ($driver === 'smtp') {
            $message = implode("\r\n", $headers) . "\r\nSubject: " . self::encode($subject) . "\r\n\r\n" . $body;
            return self::viaSmtp($cfg, $from, [$to], $message);
        }

        // driver 'mail': hàm mail() tự thêm To và Subject
        $headers = array_values(array_filter($headers, fn($h) => !str_starts_with($h, 'To:')));
        $ok = @mail($to, self::encode($subject), $body, implode("\r\n", $headers));
        if (!$ok) {
            self::$error = 'Hàm mail() của PHP không gửi được. Hãy dùng driver "smtp".';
        }
        return $ok;
    }

    /**
     * Gửi cùng một thư cho nhiều người; người nhận không thấy địa chỉ của nhau (mỗi thư tối đa 50 người, dùng 1 kết nối SMTP).
     * @param string[] $emails
     * @return int số địa chỉ được nhận thành công
     */
    /** Tên người gửi: lấy từ cấu hình; để trống hoặc còn tên cũ thì dùng tên hệ thống hiện tại */
    private static function fromName(array $cfg): string
    {
        $n = trim((string) ($cfg['from_name'] ?? ''));
        return self::clean(($n === '' || $n === 'Hệ thống thi trắc nghiệm NAU') ? system_name() : $n);
    }

    public static function sendBulk(array $emails, string $subject, string $html, string $text): int
    {
        self::$error = '';
        $cfg = App::config('mail', []);
        $from = self::clean((string) ($cfg['from_email'] ?? 'no-reply@localhost'));
        $fromName = self::fromName($cfg);

        $list = [];
        foreach ($emails as $m) {
            $m = self::clean((string) $m);
            if (filter_var($m, FILTER_VALIDATE_EMAIL)) {
                $list[strtolower($m)] = $m;
            }
        }
        $list = array_values($list);
        if (!$list || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            self::$error = 'Không có địa chỉ người nhận hợp lệ hoặc địa chỉ người gửi không hợp lệ.';
            return 0;
        }

        $driver = $cfg['driver'] ?? 'log';
        if ($driver === 'log') {
            return self::writeLog(implode(', ', $list), $subject, $text) ? count($list) : 0;
        }
        if ($driver !== 'smtp') {
            $n = 0;
            foreach ($list as $m) {
                if (self::send($m, $subject, $html, $text)) {
                    $n++;
                }
            }
            return $n;
        }

        $sent = 0;
        foreach (array_chunk($list, 50) as $chunk) {
            $boundary = 'b' . bin2hex(random_bytes(12));
            $headers = [
                'Date: ' . date('r'),
                'From: ' . self::encode($fromName) . " <$from>",
                'To: undisclosed-recipients:;',
                'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . (explode('@', $from)[1] ?? 'localhost') . '>',
                'MIME-Version: 1.0',
                "Content-Type: multipart/alternative; boundary=\"$boundary\"",
            ];
            $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                  . chunk_split(base64_encode($text))
                  . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                  . chunk_split(base64_encode($html))
                  . "--$boundary--\r\n";
            $message = implode("\r\n", $headers) . "\r\nSubject: " . self::encode($subject) . "\r\n\r\n" . $body;

            self::$accepted = 0;
            if (!self::viaSmtp($cfg, $from, $chunk, $message)) {
                break;   // lỗi kết nối / đăng nhập: dừng, không thử lại từng nhóm
            }
            $sent += self::$accepted;
        }
        return $sent;
    }

    /** Chống chèn header: bỏ ký tự xuống dòng */
    private static function clean(string $value): string
    {
        return trim(str_replace(["\r", "\n", '<', '>'], '', $value));
    }

    private static function encode(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function writeLog(string $to, string $subject, string $text): bool
    {
        $dir = ROOT . '/storage/mail';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            self::$error = 'Không tạo được thư mục storage/mail.';
            return false;
        }
        $file = $dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.txt';
        $ok = @file_put_contents($file, "To: $to\nSubject: $subject\n\n$text\n") !== false;
        if (!$ok) {
            self::$error = 'Không ghi được file vào storage/mail.';
        }
        return $ok;
    }

    /** @param string[] $to */
    private static function viaSmtp(array $c, string $from, array $to, string $message): bool
    {
        $host = (string) ($c['host'] ?? '');
        $port = (int) ($c['port'] ?? 587);
        $enc = $c['encryption'] ?? 'tls';
        if ($host === '') {
            self::$error = 'Chưa cấu hình mail.host trong config/config.php.';
            return false;
        }

        $verify = (bool) ($c['verify_ssl'] ?? true);
        $ctx = stream_context_create(['ssl' => ['verify_peer' => $verify, 'verify_peer_name' => $verify, 'allow_self_signed' => !$verify]]);
        $fp = @stream_socket_client(($enc === 'ssl' ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            self::$error = "Không kết nối được tới máy chủ SMTP $host:$port ($errstr).";
            return false;
        }
        stream_set_timeout($fp, 10);

        try {
            self::expect($fp, [220]);
            self::command($fp, 'EHLO localhost', [250]);
            if ($enc === 'tls') {
                self::command($fp, 'STARTTLS', [220]);
                if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('Không bật được mã hóa TLS (nếu đang dùng XAMPP, thử đặt mail.verify_ssl = false khi chạy thử).');
                }
                self::command($fp, 'EHLO localhost', [250]);
            }
            if (($c['username'] ?? '') !== '') {
                self::command($fp, 'AUTH LOGIN', [334]);
                self::command($fp, base64_encode((string) $c['username']), [334]);
                self::command($fp, base64_encode((string) ($c['password'] ?? '')), [235]);
            }
            self::command($fp, "MAIL FROM:<$from>", [250]);
            $accepted = 0;
            $rcptError = null;
            foreach ($to as $rcpt) {
                try {
                    self::command($fp, "RCPT TO:<$rcpt>", [250, 251]);
                    $accepted++;
                } catch (\RuntimeException $e) {
                    $rcptError = $e->getMessage();   // bỏ qua địa chỉ bị từ chối, vẫn gửi cho người còn lại
                }
            }
            self::$accepted = $accepted;
            if ($accepted === 0) {
                throw new \RuntimeException($rcptError ?? 'Không có người nhận hợp lệ.');
            }
            self::command($fp, 'DATA', [354]);
            $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $message));
            fwrite($fp, str_replace("\n", "\r\n", $data) . "\r\n.\r\n");
            self::expect($fp, [250]);
            @fwrite($fp, "QUIT\r\n");
            return true;
        } catch (\RuntimeException $e) {
            self::$error = $e->getMessage();
            return false;
        } finally {
            fclose($fp);
        }
    }

    private static function command($fp, string $line, array $ok): void
    {
        fwrite($fp, $line . "\r\n");
        self::expect($fp, $ok);
    }

    /** Đọc phản hồi SMTP (có thể nhiều dòng) và kiểm tra mã trả về */
    private static function expect($fp, array $ok): void
    {
        $reply = '';
        while (($line = fgets($fp, 515)) !== false) {
            $reply .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($reply, 0, 3);
        if (!in_array($code, $ok, true)) {
            throw new \RuntimeException('SMTP từ chối: ' . trim($reply !== '' ? $reply : 'không có phản hồi (hết thời gian chờ)'));
        }
    }
}