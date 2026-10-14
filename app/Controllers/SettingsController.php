<?php
namespace App\Controllers;

use App\Models\Audit;
use App\Core\App;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Setting;

/** Cài đặt hệ thống (chỉ quản trị viên). Mật khẩu SMTP và kết nối CSDL vẫn nằm ở config/config.local.php, không lưu trong CSDL. */
class SettingsController extends Controller
{
    private const LOGO_MAX_BYTES = 1048576;   // 1 MB
    private const LOGO_MAX_SIDE  = 1024;      // px

    private static function logoPath(): string
    {
        return ROOT . '/public/assets/img/logo.png';
    }

    public function show(): void
    {
        $terms = \App\Models\Report::terms();
        $this->view('admin/settings', [
            'title'    => 'Cài đặt hệ thống',
            'ready'    => Setting::ready(),
            'values'   => [
                'school_name'    => (string) setting('school_name', ''),
                'contact_text'   => (string) setting('contact_text', ''),
                'exam_rules'     => (string) setting('exam_rules', ''),
                'pass_score'     => (string) \App\Models\Result::passScore(),
                'partial_credit' => \App\Models\Attempt::partialCredit(),
                'semesters'      => Setting::termsToString($terms),
            ],
            'hasLogo'  => is_file(self::logoPath()),
            'logoOk'   => is_writable(dirname(self::logoPath())),
        ]);
    }

    public function update(): void
    {
        if (!Setting::ready()) {
            Session::flash('error', 'Chưa nhập database/012_settings.sql nên chưa lưu được cài đặt.');
            $this->redirect('/admin/settings');
        }
        $in = $this->request->all();
        $errors = [];

        $school = trim((string) ($in['school_name'] ?? ''));
        if (str_len($school) > 120) {
            $errors['school_name'] = 'Tên trường tối đa 120 ký tự.';
        }
        $contact = trim((string) ($in['contact_text'] ?? ''));
        if (str_len($contact) > 500) {
            $errors['contact_text'] = 'Thông tin liên hệ tối đa 500 ký tự.';
        }
        $rules = trim((string) ($in['exam_rules'] ?? ''));
        if (str_len($rules) > 4000) {
            $errors['exam_rules'] = 'Quy chế thi tối đa 4000 ký tự.';
        }

        $pass = str_replace(',', '.', trim((string) ($in['pass_score'] ?? '')));
        if (!is_numeric($pass) || (float) $pass < 0 || (float) $pass > 10) {
            $errors['pass_score'] = 'Điểm đạt phải là số từ 0 đến 10.';
        }

        $semRaw = trim((string) ($in['semesters'] ?? ''));
        $terms = Setting::parseTerms($semRaw);
        if ($terms === null) {
            $errors['semesters'] = 'Học kỳ theo dạng "Tên:tháng bắt đầu", cách nhau bằng dấu phẩy, ví dụ HK1:8, HK2:2. Tháng 1-12, không trùng, tối đa 6 học kỳ.';
        }

        $logoMsg = $this->handleLogo($errors);

        if ($errors) {
            Session::flash('errors', $errors);
            Session::flash('error', 'Chưa lưu được, vui lòng kiểm tra các mục bị đánh dấu lỗi.');
            foreach (['school_name', 'contact_text', 'exam_rules', 'pass_score', 'semesters'] as $f) {
                Session::flash('old_' . $f, (string) ($in[$f] ?? ''));
            }
            Session::flash('old_partial_credit', !empty($in['partial_credit']) ? '1' : '0');
            $this->redirect('/admin/settings');
        }

        Setting::setMany([
            'school_name'    => $school,
            'contact_text'   => $contact,
            'exam_rules'     => $rules,
            'pass_score'     => (string) (float) $pass,
            'partial_credit' => !empty($in['partial_credit']) ? '1' : '0',
            'semesters'      => Setting::termsToString($terms),
        ]);

        Audit::log('settings.update', 'Điểm đạt ' . (float) $pass . '; chấm từng phần: ' . (!empty($in['partial_credit']) ? 'bật' : 'tắt') . '; học kỳ: ' . Setting::termsToString($terms) . ($logoMsg !== '' ? '; ' . $logoMsg : ''));
        Session::flash('success', trim('Đã lưu cài đặt. ' . $logoMsg));
        $this->redirect('/admin/settings');
    }

    /** Xử lý tải lên / xóa logo. Trả về câu thông báo (rỗng nếu không đổi); lỗi ghi vào $errors['logo'] */
    private function handleLogo(array &$errors): string
    {
        $path = self::logoPath();
        if (!empty($this->request->input('remove_logo')) && is_file($path)) {
            @unlink($path);
            return 'Đã xóa logo.';
        }
        $f = $_FILES['logo'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return '';
        }
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors['logo'] = 'Tải logo lên không thành công (mã lỗi ' . (int) $f['error'] . ').';
            return '';
        }
        if ($f['size'] > self::LOGO_MAX_BYTES) {
            $errors['logo'] = 'Logo tối đa 1 MB.';
            return '';
        }
        $info = @getimagesize($f['tmp_name']);
        if (!$info || $info[2] !== IMAGETYPE_PNG) {
            $errors['logo'] = 'Logo phải là ảnh PNG.';
            return '';
        }
        if ($info[0] > self::LOGO_MAX_SIDE || $info[1] > self::LOGO_MAX_SIDE) {
            $errors['logo'] = 'Logo tối đa ' . self::LOGO_MAX_SIDE . 'x' . self::LOGO_MAX_SIDE . ' điểm ảnh.';
            return '';
        }
        if (!is_writable(dirname($path)) || !@move_uploaded_file($f['tmp_name'], $path)) {
            $errors['logo'] = 'Không ghi được logo. Hãy cấp quyền ghi cho thư mục public/assets/img.';
            return '';
        }
        return 'Đã cập nhật logo.';
    }
}
