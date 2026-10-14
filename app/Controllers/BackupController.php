<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Backup;
use App\Core\Controller;
use App\Core\Session;
use App\Core\Throttle;
use App\Models\Audit;
use App\Models\User;

/**
 * Sao lưu / khôi phục dữ liệu (chỉ quản trị viên).
 * Khôi phục GHI ĐÈ toàn bộ dữ liệu hiện tại nên cần: gõ chữ xác nhận + nhập lại mật khẩu, và luôn tự sao lưu bản hiện tại trước.
 */
class BackupController extends Controller
{
    private const CONFIRM_WORD = 'KHÔI PHỤC';

    public function index(): void
    {
        $uploads = ROOT . '/uploads';
        $hasFiles = false;
        if (is_dir($uploads)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($uploads, \FilesystemIterator::SKIP_DOTS)) as $f) {
                if ($f->isFile() && $f->getBasename()[0] !== '.') { $hasFiles = true; break; }
            }
        }
        $this->view('admin/backup', [
            'title'     => 'Sao lưu và khôi phục',
            'saved'     => Backup::listSaved(),
            'zipOk'     => class_exists(\ZipArchive::class) && $hasFiles,
            'maxUpload' => ini_get('upload_max_filesize'),
            'word'      => self::CONFIRM_WORD,
        ]);
    }

    /** Tải bản sao lưu về máy */
    public function download(): void
    {
        $name = 'nau-quiz-' . date('Ymd-His') . '.sql';
        Audit::log('backup.download', $name);
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: no-store');
        while (ob_get_level() > 0) { ob_end_clean(); }
        @set_time_limit(0);
        Backup::dump(function (string $s) { echo $s; flush(); });
        exit;
    }

    /** Lưu một bản trên máy chủ (storage/backups) */
    public function save(): void
    {
        try {
            $name = Backup::saveToServer('backup');
            Audit::log('backup.save', $name);
            Session::flash('success', 'Đã lưu bản sao lưu trên máy chủ: ' . $name);
        } catch (\Throwable $e) {
            error_log((string) $e);
            Session::flash('error', 'Không lưu được bản sao lưu: ' . $e->getMessage());
        }
        $this->redirect('/admin/backup');
    }

    /** Tải ảnh câu hỏi (thư mục uploads/) dạng ZIP */
    public function downloadFiles(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            Session::flash('error', 'Máy chủ chưa bật extension zip của PHP.');
            $this->redirect('/admin/backup');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'nauzip');
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            Session::flash('error', 'Không tạo được file ZIP.');
            $this->redirect('/admin/backup');
        }
        $base = realpath(ROOT . '/uploads');
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && $f->getBasename()[0] !== '.') {
                $zip->addFile($f->getPathname(), 'uploads/' . ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($base))), '/'));
            }
        }
        $zip->close();
        Audit::log('backup.download', 'uploads.zip');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="nau-quiz-uploads-' . date('Ymd-His') . '.zip"');
        header('Content-Length: ' . filesize($tmp));
        while (ob_get_level() > 0) { ob_end_clean(); }
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    /** Tải một bản đã lưu trên máy chủ */
    public function downloadSaved(string $name): void
    {
        $p = Backup::savedPath($name);
        if (!$p) {
            \App\Core\Response::abort(404, 'Không tìm thấy bản sao lưu.');
        }
        Audit::log('backup.download', $name);
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($p));
        header('Cache-Control: no-store');
        readfile($p);
        exit;
    }

    public function deleteSaved(string $name): void
    {
        $p = Backup::savedPath($name);
        if ($p && @unlink($p)) {
            Audit::log('backup.delete', $name);
            Session::flash('success', 'Đã xóa bản sao lưu ' . $name . '.');
        } else {
            Session::flash('error', 'Không xóa được bản sao lưu.');
        }
        $this->redirect('/admin/backup');
    }

    /** Khôi phục từ file tải lên hoặc từ bản đã lưu trên máy chủ */
    public function restore(): void
    {
        $me = $this->user();
        $in = $this->request->all();

        // 1) Xác nhận: gõ đúng chữ + nhập lại mật khẩu (có giới hạn số lần sai)
        $key = 'restore-pw:' . (int) $me['id'];
        if (Throttle::retryAfter($key, 5, 900) > 0) {
            $this->fail('Bạn đã nhập sai mật khẩu quá nhiều lần. Vui lòng thử lại sau ít phút.');
        }
        if (trim((string) ($in['confirm'] ?? '')) !== self::CONFIRM_WORD) {
            $this->fail('Hãy gõ đúng chữ ' . self::CONFIRM_WORD . ' để xác nhận.');
        }
        $row = User::find((int) $me['id']);
        if (!$row || !password_verify((string) ($in['password'] ?? ''), (string) $row['password_hash'])) {
            Throttle::hit($key, 900);
            $this->fail('Mật khẩu không đúng.');
        }
        Throttle::clear($key);

        // 2) Chọn nguồn file
        $source = '';
        $path = null;
        $saved = (string) ($in['saved'] ?? '');
        if ($saved !== '') {
            $path = Backup::savedPath($saved);
            $source = $saved;
            if (!$path) {
                $this->fail('Không tìm thấy bản sao lưu đã chọn.');
            }
        } else {
            $f = $_FILES['file'] ?? null;
            if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                $this->fail('Hãy chọn file .sql cần khôi phục.');
            }
            if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
                $this->fail('File lớn hơn giới hạn tải lên của máy chủ (' . ini_get('upload_max_filesize') . '). Hãy dùng chức năng Import của phpMyAdmin, hoặc chép file vào thư mục storage/backups rồi chọn trong danh sách.');
            }
            if ($f['error'] !== UPLOAD_ERR_OK) {
                $this->fail('Tải file lên không thành công (mã lỗi ' . (int) $f['error'] . ').');
            }
            $path = $f['tmp_name'];
            $source = 'file tải lên ' . str_cut(basename((string) $f['name']), 80);
        }

        // 3) Kiểm tra TOÀN BỘ file trước khi động vào dữ liệu
        $err = Backup::validate($path);
        if ($err !== null) {
            $this->fail('File không dùng được: ' . $err);
        }

        // 4) Tự sao lưu dữ liệu hiện tại để quay lại được
        try {
            $before = Backup::saveToServer('truoc-khoi-phuc');
        } catch (\Throwable $e) {
            $this->fail('Chưa khôi phục vì không tự sao lưu được dữ liệu hiện tại: ' . $e->getMessage());
        }

        // 5) Khôi phục; lỗi giữa chừng thì tự trả về bản vừa sao lưu
        try {
            $n = Backup::restore($path);
        } catch (\Throwable $e) {
            error_log('Khôi phục thất bại: ' . $e->getMessage());
            $msg = 'Khôi phục thất bại giữa chừng (' . $e->getMessage() . ').';
            try {
                Backup::restore(Backup::dir() . '/' . $before);
                $msg .= ' Hệ thống đã tự trả dữ liệu về như trước khi khôi phục.';
            } catch (\Throwable $e2) {
                $msg .= ' Không tự trả lại được, hãy khôi phục bản ' . $before . ' trong danh sách bản lưu trên máy chủ.';
            }
            $this->fail($msg);
        }

        Audit::log('backup.restore', 'Từ ' . $source . " ($n câu lệnh); bản trước đó: $before", $me);
        // Dữ liệu người dùng đã đổi theo bản sao lưu nên bắt đăng nhập lại
        Auth::logout();
        Session::flash('success', "Đã khôi phục dữ liệu ($n câu lệnh). Bản trước khi khôi phục được lưu là $before. Vui lòng đăng nhập lại.");
        $this->redirect('/login');
    }

    private function fail(string $message): void
    {
        Session::flash('error', $message);
        $this->redirect('/admin/backup');
    }
}
