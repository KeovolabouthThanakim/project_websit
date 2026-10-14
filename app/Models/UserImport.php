<?php
namespace App\Models;

use App\Core\Database;

/**
 * Nhập hàng loạt tài khoản (sinh viên / giảng viên) từ Excel / CSV / văn bản dán, gán lớp cho sinh viên ngay lúc nhập.
 * Dùng lại bộ đọc file của QuestionImport. Chỉ TẠO tài khoản mới (tên đăng nhập đã có -> báo lỗi, không ghi đè);
 * không tạo được quản trị viên qua đường này để tránh cấp quyền cao nhầm.
 *
 * Cột: Tên đăng nhập | Mã sinh viên (sinh viên để trống = lấy theo tên đăng nhập) | Họ và tên | Email | Mật khẩu | Vai trò | Mã lớp | Ngày sinh (không bắt buộc, nhận dd/mm/yyyy hoặc yyyy-mm-dd)
 *  - Mật khẩu để trống: hệ thống tự sinh mật khẩu ngẫu nhiên (hiện MỘT LẦN ở trang kết quả để phát cho người dùng).
 *  - Vai trò để trống: sinh viên. Mã lớp chỉ dùng cho sinh viên.
 * Phần phân tích / kiểm tra là hàm thuần (không đụng CSDL) trừ khi cần đối chiếu dữ liệu đã có (truyền vào qua tham số).
 */
class UserImport
{
    public const MAX_ROWS = 300;
    public const MIN_PASSWORD = 8;
    public const ROLES = ['student', 'teacher'];
    private const PW_ALPHABET = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';  // bỏ ký tự dễ nhầm: l I 1 O 0

    /** Dòng thô từ bảng -> [['line', 'username', 'student_code', 'full_name', 'email', 'password', 'role', 'class', 'dob']] */
    public static function fromRows(array $rows): array
    {
        $rows = array_values(array_filter($rows, fn($r) => trim(implode('', array_map('strval', $r))) !== ''));
        if (!$rows) {
            return [];
        }
        $map = self::headerMap($rows[0]);
        $start = 1;
        if ($map !== null) {
            array_shift($rows);
            $start = 2;
        } else {
            $map = ['username' => 0, 'full_name' => 1, 'email' => 2, 'password' => 3, 'role' => 4, 'class' => 5, 'dob' => 6, 'student_code' => 7];
        }
        $out = [];
        foreach ($rows as $i => $r) {
            $cell = fn($k) => isset($map[$k]) ? trim((string) ($r[$map[$k]] ?? '')) : '';
            $out[] = ['line' => $start + $i] + array_combine(
                ['username', 'student_code', 'full_name', 'email', 'password', 'role', 'class', 'dob'],
                [$cell('username'), $cell('student_code'), $cell('full_name'), $cell('email'), $cell('password'), $cell('role'), $cell('class'), $cell('dob')]
            );
        }
        return $out;
    }

    private static function headerMap(array $header): ?array
    {
        $map = [];
        foreach ($header as $i => $h) {
            $f = QuestionImport::fold((string) $h);
            if (preg_match('/^(ma sv|ma sinh vien|ma so sinh vien|ma so sv|student code|student id|student_code|mssv)$/', $f)) {
                $map['student_code'] ??= $i;
            } elseif (preg_match('/^(ten dang nhap|ma dang nhap|username|user|ma gv|ma giang vien|ma so|ma)$/', $f)) {
                $map['username'] ??= $i;
            } elseif (preg_match('/^(ho va ten|ho ten|ho & ten|ten day du|full name|fullname|name|ten)$/', $f)) {
                $map['full_name'] ??= $i;
            } elseif ($f === 'email' || $f === 'e-mail' || $f === 'thu dien tu') {
                $map['email'] ??= $i;
            } elseif (preg_match('/^(mat khau|password|pass)$/', $f)) {
                $map['password'] ??= $i;
            } elseif (preg_match('/^(vai tro|role|loai tai khoan)$/', $f)) {
                $map['role'] ??= $i;
            } elseif (preg_match('/^(lop|ma lop|lop hoc|class|class code)$/', $f)) {
                $map['class'] ??= $i;
            } elseif (preg_match('#^(ngay sinh|ngay thang nam sinh|ngay/thang/nam sinh|nam sinh|ngay sinh \(dd/mm/yyyy\)|dob|date of birth|birthday|birth date|birthdate)$#', $f)) {
                $map['dob'] ??= $i;
            }
        }
        if (!isset($map['username']) && isset($map['student_code'])) {
            $map['username'] = $map['student_code'];   // file cũ chỉ có cột "Mã sinh viên" -> dùng làm tên đăng nhập
        }
        return isset($map['username'], $map['full_name']) ? $map : null;
    }

    /** "SV", "Sinh viên", "student" -> student; "GV", "Giảng viên", "giáo viên", "teacher" -> teacher; rỗng -> student; khác -> null */
    public static function role(string $v): ?string
    {
        $f = QuestionImport::fold($v);
        if ($f === '' || in_array($f, ['sv', 'sinh vien', 'student', 'hoc sinh'], true)) {
            return 'student';
        }
        if (in_array($f, ['gv', 'giang vien', 'giao vien', 'teacher'], true)) {
            return 'teacher';
        }
        return null;
    }

    /**
     * Kiểm tra từng dòng (cùng luật với form thêm người dùng) + đối chiếu dữ liệu đã có.
     * $existingUsers: [username viết thường => true]; $existingEmails: [email => true]; $classIds: [mã lớp viết thường => id];
     * $createClasses: true = lớp chưa có sẽ được tạo mới (không báo lỗi).
     * Trả về mỗi dòng: line, status ('ok'|'error'), errors[], username, full_name, email, role, class (mã lớp đã chuẩn hóa hoặc ''),
     * password (mật khẩu người nhập hoặc ''), new_class (true nếu lớp sẽ được tạo).
     */
    public static function check(array $raw, array $existingUsers, array $existingEmails, array $classIds, bool $createClasses, array $existingCodes = []): array
    {
        $out = [];
        $seenUser = [];
        $seenEmail = [];
        $seenCode = [];
        foreach ($raw as $r) {
            $errors = [];
            $username = $r['username'];
            $fullName = $r['full_name'];
            $email = strtolower($r['email']);
            $pass = $r['password'];
            $role = self::role($r['role']);
            $classRaw = $r['class'];
            $dobRaw = $r['dob'] ?? '';
            $code = trim((string) ($r['student_code'] ?? ''));

            if ($username === '') {
                $errors[] = 'Thiếu tên đăng nhập.';
            } elseif (str_len($username) < 3 || str_len($username) > 50) {
                $errors[] = 'Tên đăng nhập phải từ 3 đến 50 ký tự.';
            } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
                $errors[] = 'Tên đăng nhập chỉ dùng chữ không dấu, số và . _ -';
            } else {
                $k = strtolower($username);
                if (isset($existingUsers[$k])) {
                    $errors[] = 'Tên đăng nhập đã có trong hệ thống.';
                } elseif (isset($seenUser[$k])) {
                    $errors[] = 'Trùng tên đăng nhập với dòng ' . $seenUser[$k] . ' trong file.';
                } else {
                    $seenUser[$k] = $r['line'];
                }
            }

            // Mã sinh viên: để trống thì lấy theo tên đăng nhập (file mẫu cũ chỉ có 1 cột mã); giảng viên không có mã SV
            if ($role !== 'student') {
                $code = '';
            } else {
                if ($code === '') {
                    $code = $username;
                }
                if ($code === '' || $username === '') {
                    // lỗi thiếu tên đăng nhập đã được báo ở trên
                } elseif (str_len($code) < 3 || str_len($code) > 30) {
                    $errors[] = 'Mã sinh viên phải từ 3 đến 30 ký tự.';
                } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $code)) {
                    $errors[] = 'Mã sinh viên chỉ dùng chữ không dấu, số và . _ -';
                } else {
                    $ck = strtolower($code);
                    if (isset($existingCodes[$ck])) {
                        $errors[] = 'Mã sinh viên đã có trong hệ thống.';
                    } elseif (isset($seenCode[$ck])) {
                        $errors[] = 'Trùng mã sinh viên với dòng ' . $seenCode[$ck] . ' trong file.';
                    } else {
                        $seenCode[$ck] = $r['line'];
                    }
                }
            }

            if ($fullName === '') {
                $errors[] = 'Thiếu họ và tên.';
            } elseif (str_len($fullName) > 100) {
                $errors[] = 'Họ và tên tối đa 100 ký tự.';
            }

            if ($email !== '') {
                if (str_len($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Email không hợp lệ.';
                } elseif (isset($existingEmails[$email])) {
                    $errors[] = 'Email đã có người sử dụng.';
                } elseif (isset($seenEmail[$email])) {
                    $errors[] = 'Trùng email với dòng ' . $seenEmail[$email] . ' trong file.';
                } else {
                    $seenEmail[$email] = $r['line'];
                }
            }

            if ($pass !== '') {
                if (str_len($pass) < self::MIN_PASSWORD) {
                    $errors[] = 'Mật khẩu phải có ít nhất ' . self::MIN_PASSWORD . ' ký tự (hoặc để trống để hệ thống tự sinh).';
                } elseif (strlen($pass) > 72) {
                    $errors[] = 'Mật khẩu tối đa 72 ký tự.';
                }
            }

            if ($role === null) {
                $errors[] = 'Vai trò "' . $r['role'] . '" không hợp lệ (dùng: Sinh viên hoặc Giảng viên).';
            }

            $dob = '';
            if ($dobRaw !== '') {
                $iso = parse_dob($dobRaw);
                if ($iso === null) {
                    $errors[] = 'Ngày sinh "' . $dobRaw . '" không hợp lệ (dùng dd/mm/yyyy, ví dụ 30/05/2003; không được ở tương lai).';
                } else {
                    $dob = $iso;
                }
            }

            $class = '';
            $newClass = false;
            if ($classRaw !== '') {
                if ($role === 'teacher') {
                    $errors[] = 'Mã lớp chỉ dùng cho sinh viên.';
                } elseif (!preg_match('/^[A-Za-z0-9._ -]{1,30}$/', $classRaw)) {
                    $errors[] = 'Mã lớp "' . $classRaw . '" không hợp lệ (tối đa 30 ký tự, chữ không dấu / số / . _ -).';
                } elseif (isset($classIds[strtolower($classRaw)])) {
                    $class = $classRaw;
                } elseif ($createClasses) {
                    $class = $classRaw;
                    $newClass = true;
                } else {
                    $errors[] = 'Không có lớp mã "' . $classRaw . '" (tích "Tự tạo lớp chưa có" nếu muốn tạo mới).';
                }
            }

            $out[] = [
                'line' => $r['line'], 'status' => $errors ? 'error' : 'ok', 'errors' => $errors,
                'username' => $username, 'student_code' => $code, 'full_name' => $fullName, 'email' => $email,
                'role' => $role ?? 'student', 'class' => $class, 'password' => $pass, 'new_class' => $newClass, 'dob' => $dob,
            ];
        }
        return $out;
    }

    public static function generatePassword(int $length = 10): string
    {
        $s = '';
        $max = strlen(self::PW_ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $s .= self::PW_ALPHABET[random_int(0, $max)];
        }
        return $s;
    }

    /** [username viết thường => true] của mọi tài khoản - để kiểm tra trùng khi xem trước */
    public static function existingUsernames(): array
    {
        $out = [];
        foreach (Database::connection()->query('SELECT username FROM users')->fetchAll(\PDO::FETCH_COLUMN) as $u) {
            $out[strtolower((string) $u)] = true;
        }
        return $out;
    }

    /** [mã sinh viên viết thường => true]; rỗng nếu CSDL chưa có cột student_code */
    public static function existingStudentCodes(): array
    {
        $out = [];
        if (!User::hasStudentCode()) {
            return $out;
        }
        foreach (Database::connection()->query("SELECT student_code FROM users WHERE student_code IS NOT NULL AND student_code <> ''")->fetchAll(\PDO::FETCH_COLUMN) as $c) {
            $out[strtolower((string) $c)] = true;
        }
        return $out;
    }

    public static function existingEmails(): array
    {
        $out = [];
        foreach (Database::connection()->query("SELECT email FROM users WHERE email IS NOT NULL AND email <> ''")->fetchAll(\PDO::FETCH_COLUMN) as $e) {
            $out[strtolower((string) $e)] = true;
        }
        return $out;
    }

    /**
     * Tạo tài khoản (+ lớp mới nếu được phép + gán lớp) cho các dòng hợp lệ, TẤT CẢ trong 1 giao dịch.
     * Trả về [['username', 'full_name', 'role', 'class', 'password' (chỉ khi do hệ thống sinh), 'generated' => bool]].
     * Ném PDOException (23000) nếu có tên đăng nhập / email bị người khác tạo trùng trong lúc xem trước -> không ghi gì cả.
     */
    public static function run(array $rows, bool $createClasses, int $adminId): array
    {
        $pdo = Database::connection();
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);   // băm mật khẩu mỗi người mất ~50-100ms
        }
        $classIds = Classroom::idsByCodes(array_column($rows, 'class'));
        $result = [];
        $pdo->beginTransaction();
        try {
            $withDob = User::hasDob();   // chưa chạy 014_users_date_of_birth.sql thì bỏ qua cột ngày sinh
            $withCode = User::hasStudentCode();   // chưa chạy 015_users_student_code.sql thì bỏ qua cột mã sinh viên
            $insU = $pdo->prepare('INSERT INTO users (username, ' . ($withCode ? 'student_code, ' : '') . 'password_hash, full_name, email, '
                . ($withDob ? 'date_of_birth, ' : '') . 'role, is_active) VALUES (?, ' . ($withCode ? '?, ' : '') . '?, ?, ?, ' . ($withDob ? '?, ' : '') . '?, 1)');
            $insC = $pdo->prepare('INSERT INTO classes (code, name, cohort, teacher_id) VALUES (?, ?, NULL, NULL)');
            $insS = $pdo->prepare('INSERT INTO class_students (class_id, user_id, added_by) VALUES (?, ?, ?)');
            foreach ($rows as $r) {
                $generated = $r['password'] === '';
                $plain = $generated ? self::generatePassword() : $r['password'];
                $params = [$r['username']];
                if ($withCode) {
                    $params[] = ($r['student_code'] ?? '') === '' ? null : $r['student_code'];
                }
                array_push($params, password_hash($plain, PASSWORD_DEFAULT), $r['full_name'], $r['email'] === '' ? null : $r['email']);
                if ($withDob) {
                    $params[] = ($r['dob'] ?? '') === '' ? null : $r['dob'];
                }
                $params[] = $r['role'];
                $insU->execute($params);
                $uid = (int) $pdo->lastInsertId();

                if ($r['role'] === 'student' && $r['class'] !== '') {
                    $key = strtolower($r['class']);
                    if (!isset($classIds[$key])) {
                        if (!$createClasses) {
                            throw new \RuntimeException('Lớp ' . $r['class'] . ' không tồn tại.');
                        }
                        $insC->execute([$r['class'], $r['class']]);
                        $classIds[$key] = (int) $pdo->lastInsertId();
                    }
                    $insS->execute([$classIds[$key], $uid, $adminId]);
                }
                $result[] = ['username' => $r['username'], 'student_code' => $r['student_code'] ?? '', 'full_name' => $r['full_name'], 'role' => $r['role'], 'class' => $r['class'],
                             'password' => $generated ? $plain : '', 'generated' => $generated];
            }
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function sampleRows(): array
    {
        return [
            ['Tên đăng nhập', 'Mã sinh viên', 'Họ và tên', 'Email', 'Mật khẩu', 'Vai trò', 'Mã lớp', 'Ngày sinh'],
            ['sv2026001', '2026001', 'Nguyễn Văn An', 'an.nv@example.com', '', 'Sinh viên', 'CNTT-K62', '15/08/2004'],
            ['sv2026002', '2026002', 'Trần Thị Bình', '', '', 'Sinh viên', 'CNTT-K62', '02/12/2004'],
            ['gv2026001', '', 'Lê Văn Cường', 'cuong.lv@example.com', '', 'Giảng viên', '', '20/03/1985'],
        ];
    }
}
