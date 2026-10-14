<?php
namespace App\Controllers;

use App\Models\Audit;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\Classroom;
use App\Models\QuestionImport;
use App\Models\UserImport;

/**
 * Quản trị viên: nhập hàng loạt tài khoản sinh viên / giảng viên từ Excel (.xlsx) hoặc CSV, gán lớp ngay lúc nhập.
 * Ba bước: tải file -> XEM TRƯỚC (chưa ghi gì) -> xác nhận -> trang kết quả (mật khẩu tự sinh chỉ hiện ở đây, tải CSV để phát cho người dùng).
 * Mật khẩu tự sinh được giữ tạm trong session đến khi tải CSV hoặc bắt đầu lần nhập mới; không ghi vào CSDL dạng rõ.
 */
class UserImportController extends Controller
{
    private const PREVIEW = 'user_import';
    private const RESULT = 'user_import_result';

    public function form(): void
    {
        Session::forget(self::PREVIEW);
        $this->view('users/import', ['title' => 'Nhập người dùng từ file', 'max' => UserImport::MAX_ROWS, 'zipOk' => class_exists(\ZipArchive::class)]);
    }

    public function preview(): void
    {
        $f = $_FILES['file'] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $this->fail('Hãy chọn file Excel (.xlsx) hoặc CSV.');
        }
        $err = (int) $f['error'];
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || (int) $f['size'] > QuestionImport::MAX_FILE_BYTES) {
            $this->fail('File quá lớn (tối đa ' . (QuestionImport::MAX_FILE_BYTES / 1048576) . ' MB).');
        }
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
            $this->fail('Tải file lên không thành công. Vui lòng thử lại.');
        }
        $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'csv'], true)) {
            $this->fail('Chỉ nhận file .xlsx hoặc .csv. (Excel cũ .xls hãy lưu lại thành .xlsx.)');
        }
        if ($ext === 'xlsx' && file_get_contents((string) $f['tmp_name'], false, null, 0, 2) !== 'PK') {
            $this->fail('File không đúng định dạng .xlsx.');
        }

        try {
            $rows = $ext === 'xlsx' ? QuestionImport::readXlsx((string) $f['tmp_name']) : QuestionImport::readCsv((string) $f['tmp_name']);
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $raw = UserImport::fromRows($rows);
        if (!$raw) {
            $this->fail('Không tìm thấy dòng dữ liệu nào trong file.');
        }
        if (count($raw) > UserImport::MAX_ROWS) {
            $this->fail('Mỗi lần chỉ nhập tối đa ' . UserImport::MAX_ROWS . ' tài khoản (file có ' . count($raw) . ' dòng). Hãy chia nhỏ file.');
        }

        $create = !empty($this->request->input('create_classes'));
        $classCodes = array_column($raw, 'class');
        $checked = UserImport::check($raw, UserImport::existingUsernames(), UserImport::existingEmails(), Classroom::idsByCodes($classCodes), $create, UserImport::existingStudentCodes());

        Session::set(self::PREVIEW, [
            'rows'   => $checked,
            'create' => $create,
            'source' => str_cut((string) preg_replace('/[^\p{L}\p{N} ._\-()]/u', '', basename((string) $f['name'])), 80),
        ]);
        $this->redirect('/admin/users/import/preview');
    }

    public function showPreview(): void
    {
        $d = Session::get(self::PREVIEW);
        if (!is_array($d) || empty($d['rows'])) {
            Session::flash('error', 'Phiên nhập đã hết hạn hoặc chưa có dữ liệu. Vui lòng tải file lại.');
            $this->redirect('/admin/users/import');
        }
        $count = ['ok' => 0, 'error' => 0];
        $newClasses = [];
        foreach ($d['rows'] as $r) {
            $count[$r['status']]++;
            if ($r['status'] === 'ok' && $r['new_class']) {
                $newClasses[strtolower($r['class'])] = $r['class'];
            }
        }
        $this->view('users/import_preview', [
            'title' => 'Xem trước khi nhập', 'rows' => $d['rows'], 'count' => $count, 'source' => $d['source'],
            'newClasses' => array_values($newClasses), 'create' => $d['create'],
        ]);
    }

    public function confirm(): void
    {
        $d = Session::get(self::PREVIEW);
        if (!is_array($d) || empty($d['rows'])) {
            Session::flash('error', 'Phiên nhập đã hết hạn hoặc đã được nhập rồi. Vui lòng tải file lại.');
            $this->redirect('/admin/users/import');
        }
        $ok = array_values(array_filter($d['rows'], fn($r) => $r['status'] === 'ok'));
        if (!$ok) {
            Session::forget(self::PREVIEW);
            Session::flash('error', 'Không có dòng hợp lệ nào để nhập.');
            $this->redirect('/admin/users/import');
        }

        try {
            $result = UserImport::run($ok, (bool) $d['create'], (int) $this->user()['id']);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {   // trùng tên đăng nhập / email / lớp do người khác vừa tạo -> không ghi gì cả
                Session::forget(self::PREVIEW);
                Session::flash('error', 'Dữ liệu vừa thay đổi (có tên đăng nhập, mã sinh viên, email hoặc lớp bị trùng) nên chưa nhập tài khoản nào. Vui lòng tải file lại để xem trước.');
                $this->redirect('/admin/users/import');
            }
            throw $e;
        }
        Session::forget(self::PREVIEW);
        Audit::log('user.import', 'Nhập ' . count($ok) . ' dòng hợp lệ từ file' . (!empty($d['create']) ? ' (có tạo tài khoản mới)' : ''));
        Session::set(self::RESULT, $result);
        $this->redirect('/admin/users/import/result');
    }

    public function result(): void
    {
        $r = Session::get(self::RESULT);
        if (!is_array($r) || !$r) {
            Session::flash('error', 'Không còn kết quả nhập nào để hiển thị (mật khẩu tự sinh chỉ giữ đến khi bạn tải CSV).');
            $this->redirect('/admin/users');
        }
        $this->view('users/import_result', ['title' => 'Kết quả nhập người dùng', 'rows' => $r]);
    }

    /** Tải CSV danh sách tài khoản + mật khẩu tự sinh, rồi XÓA khỏi session */
    public function download(): void
    {
        $r = Session::get(self::RESULT);
        if (!is_array($r) || !$r) {
            Response::abort(404, 'Không còn kết quả nhập nào để tải.');
        }
        Session::forget(self::RESULT);
        $out = "\xEF\xBB\xBF" . implode(',', ['Tên đăng nhập', 'Mã sinh viên', 'Họ và tên', 'Vai trò', 'Lớp', 'Mật khẩu ban đầu']) . "\r\n";
        foreach ($r as $x) {
            $cells = [$x['username'], $x['student_code'] ?? '', $x['full_name'], role_label($x['role']), $x['class'], $x['generated'] ? $x['password'] : '(do bạn đặt trong file)'];
            $out .= implode(',', array_map(fn($c) => '"' . str_replace('"', '""', (string) ExamResultController::csvCell($c)) . '"', $cells)) . "\r\n";
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="tai-khoan-moi-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store');
        header('Content-Length: ' . strlen($out));
        echo $out;
        exit;
    }

    public function template(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            Response::abort(500, 'Máy chủ chưa bật extension zip của PHP nên không tạo được file Excel mẫu.');
        }
        $body = QuestionImport::buildXlsx(UserImport::sampleRows(), 'Nguoi dung');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="mau-nhap-nguoi-dung.xlsx"');
        header('Content-Length: ' . strlen($body));
        echo $body;
        exit;
    }

    private function fail(string $message): void
    {
        Session::flash('error', $message);
        $this->redirect('/admin/users/import');
    }
}
