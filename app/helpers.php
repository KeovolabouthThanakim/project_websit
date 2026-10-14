<?php
// Hàm tiện ích dùng trong View

use App\Core\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;

/** Escape HTML - luôn dùng khi in dữ liệu ra View để chống XSS */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Tạo URL nội bộ, tự thêm thư mục gốc (vd: /nau-quiz) */
function url(string $path = ''): string
{
    return App::$baseUrl . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = ROOT . '/public/assets/' . $path;
    $v = is_file($file) ? '?v=' . filemtime($file) : '';
    return url('assets/' . $path) . $v;
}

/** Input ẩn chứa CSRF token - bắt buộc có trong mọi form POST */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Session::csrfToken()) . '">';
}

/**
 * Giá trị người dùng vừa nhập (sau khi form bị trả về do lỗi).
 * Nếu không có thì dùng giá trị mặc định. Chỉ đọc được 1 lần cho mỗi trường.
 */
function old(string $key, string $default = ''): string
{
    $value = Session::pullFlash('old_' . $key, null);
    return $value === null ? $default : (string) $value;
}

/** Danh sách lỗi validate của form (dạng tên_trường => thông báo), đọc 1 lần */
function form_errors(): array
{
    static $errors = null;
    if ($errors === null) {
        $errors = Session::pullFlash('errors', []);
    }
    return $errors;
}

function field_error(string $field): string
{
    $message = form_errors()[$field] ?? '';
    return $message === '' ? '' : '<span class="field-error">' . e($message) . '</span>';
}

function auth_user(): ?array
{
    return Auth::user();
}

function role_label(string $role): string
{
    return ['admin' => 'Quản trị viên', 'teacher' => 'Giảng viên', 'student' => 'Sinh viên'][$role] ?? $role;
}

/** Trả về 'active' nếu đường dẫn hiện tại khớp (dùng tô sáng menu) */
function nav_active(string $path, bool $prefix = false): string
{
    $current = (new Request())->path();
    $match = $prefix
        ? ($current === $path || strpos($current, rtrim($path, '/') . '/') === 0)
        : $current === $path;
    return $match ? 'active' : '';
}

/** 2026-10-01 09:30:00 -> 01/10/2026 09:30 */
function fmt_date(?string $datetime): string
{
    $t = $datetime ? strtotime($datetime) : false;
    return $t ? date('d/m/Y H:i', $t) : '';
}

/** Mã sinh viên để hiển thị: dùng student_code, chưa có (CSDL cũ / chưa nhập) thì dùng tên đăng nhập */
function sv_code(array $u): string
{
    $c = trim((string) ($u['student_code'] ?? ''));
    return $c !== '' ? $c : (string) ($u['username'] ?? '');
}

/** 2001-05-30 -> 30/05/2001 (chỉ ngày, không giờ); rỗng / sai định dạng -> '' */
function fmt_dob(?string $date): string
{
    $date = trim((string) $date);
    if ($date === '' || $date === '0000-00-00') {
        return '';
    }
    $t = strtotime($date);
    return $t ? date('d/m/Y', $t) : '';
}

/**
 * Ngày sinh người dùng nhập / lấy từ Excel -> 'Y-m-d' để lưu CSDL. Rỗng hoặc không hợp lệ -> null.
 * Nhận: 30/05/2001, 30-5-2001, 30.05.2001, 2001-05-30 và số ngày dạng Excel (vd 37041 khi ô Excel là kiểu ngày
 * nhưng đọc ra số). Từ chối ngày không có thật (31/02), ngày ở tương lai và năm trước 1900.
 */
function parse_dob(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $y = $m = $d = 0;
    if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})(?:[ T].*)?$/', $value, $x)) {          // 2001-05-30
        [$y, $m, $d] = [(int) $x[1], (int) $x[2], (int) $x[3]];
    } elseif (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})(?:[ T].*)?$/', $value, $x)) {   // 30/05/2001
        [$d, $m, $y] = [(int) $x[1], (int) $x[2], (int) $x[3]];
    } elseif (preg_match('/^\d{5}(?:\.\d+)?$/', $value)) {                                         // số ngày của Excel (gốc 30/12/1899)
        $dt = (new DateTimeImmutable('1899-12-30'))->modify('+' . (int) $value . ' days');
        [$y, $m, $d] = [(int) $dt->format('Y'), (int) $dt->format('n'), (int) $dt->format('j')];
    } else {
        return null;
    }
    if ($y < 1900 || !checkdate($m, $d, $y)) {
        return null;
    }
    $iso = sprintf('%04d-%02d-%02d', $y, $m, $d);
    return $iso > date('Y-m-d') ? null : $iso;
}

/** Timestamp -> "Thứ Ba, 06/10/2026" */
function fmt_day(int $ts): string
{
    $names = ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
    return $names[(int) date('w', $ts)] . ', ' . date('d/m/Y', $ts);
}

/** Đếm số ký tự (đúng với tiếng Việt có dấu), không bắt buộc phải có extension mbstring */
function str_len(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : (int) preg_match_all('/./us', $value);
}

/** Cắt chuỗi tối đa $length ký tự */
function str_cut(string $value, int $length): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $length, 'UTF-8');
    }
    return preg_match('/^.{0,' . $length . '}/us', $value, $m) ? $m[0] : '';
}

/** Điểm thang 10 kiểu Việt Nam: 7.5 -> "7,5", 10 -> "10", null -> "-" */
function fmt_score($score): string
{
    if ($score === null || $score === '') {
        return '-';
    }
    return rtrim(rtrim(number_format((float) $score, 2, ',', ''), '0'), ',');
}

/** 3725 -> "1:02:05", 125 -> "2:05" */
function fmt_duration(int $seconds): string
{
    $seconds = max(0, $seconds);
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
}

/** Cấu hình bảo mật (config.php -> 'security'); thiếu khóa nào thì dùng giá trị mặc định */
function sec(string $key, $default = null)
{
    $c = App::config('security', []);
    return is_array($c) && array_key_exists($key, $c) ? $c[$key] : $default;
}

/** Tài khoản mẫu còn dùng mật khẩu mặc định đã công khai trong README (chỉ kiểm tra khi quản trị viên mở trang chủ) */
function default_password_accounts(): array
{
    $known = ['admin' => 'admin123', 'gv001' => 'teacher123', 'sv001' => 'student123', 'sv002' => 'student123'];
    $found = [];
    foreach ($known as $username => $password) {
        $u = \App\Models\User::findByUsername($username);
        if ($u && password_verify($password, (string) $u['password_hash'])) {
            $found[] = $username;
        }
    }
    return $found;
}

/**
 * Cảnh báo cấu hình chưa an toàn khi chạy trên máy chủ thật (không phải localhost).
 * Hiện cho quản trị viên ở trang chủ quản trị.
 */
function security_warnings(): array
{
    $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    if (in_array($host, ['localhost', '127.0.0.1', '[::1]', ''], true)) {
        return [];
    }
    $w = [];
    if (App::config('debug')) {
        $w[] = 'Đang bật chế độ debug (config.php: debug = true). Lỗi chi tiết có thể lộ thông tin hệ thống - hãy đặt debug = false.';
    }
    $db = (array) App::config('db', []);
    if (($db['user'] ?? '') === 'root' && (string) ($db['password'] ?? '') === '') {
        $w[] = 'Cơ sở dữ liệu đang dùng tài khoản root không mật khẩu. Hãy tạo user MySQL riêng cho ứng dụng, có mật khẩu mạnh, chỉ cấp quyền trên CSDL nau_quiz.';
    }
    if (!Session::isHttps()) {
        $w[] = 'Trang đang chạy bằng HTTP, mật khẩu đi qua mạng không được mã hóa. Hãy bật HTTPS (chứng chỉ SSL) cho tên miền.';
    }
    if (App::config('app_url') === null) {
        $w[] = 'Chưa đặt app_url trong config.php: liên kết đặt lại mật khẩu gửi qua email đang lấy từ tên miền của người truy cập, có thể bị giả mạo. Hãy đặt app_url = địa chỉ chính thức của web.';
    }
    return $w;
}

/** Giá trị cài đặt trong CSDL (Quản trị viên > Cài đặt); chưa có thì dùng $default */
function setting(string $key, $default = '')
{
    return \App\Models\Setting::get($key, $default);
}

/**
 * Tên hệ thống hiển thị ở menu, trang đăng nhập, bản in và email.
 * Đổi tên: sửa 'app_name' trong config/config.php (giá trị cũ 'NAU Quiz' vẫn được hiểu là tên mặc định mới này).
 */
function system_name(): string
{
    $n = trim((string) \App\Core\App::config('app_name', ''));
    return ($n === '' || $n === 'NAU Quiz') ? 'Hệ thống Quản lý và Tổ chức Thi Trắc nghiệm Trực tuyến' : $n;
}

/** Tên đầy đủ của trường, vd "Trường Đại học Nghệ An" */
function school_name(): string
{
    $n = trim((string) setting('school_name', ''));
    return $n !== '' ? $n : 'Trường Đại học Nghệ An';
}

/** Tên ngắn dùng ở thanh menu: bỏ chữ "Trường " ở đầu, vd "Đại học Nghệ An" */
function school_short(): string
{
    $full = school_name();
    $short = trim((string) preg_replace('/^Trường\s+/u', '', $full));
    return $short !== '' ? $short : $full;
}
