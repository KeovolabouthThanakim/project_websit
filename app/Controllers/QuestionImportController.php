<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\Question;
use App\Models\QuestionImport;
use App\Models\Subject;

/**
 * Nhập hàng loạt câu hỏi vào ngân hàng câu hỏi từ Excel / CSV / Word / văn bản dán trực tiếp.
 * Ba bước: chọn môn + tải file -> XEM TRƯỚC (chỉ đọc, chưa ghi gì) -> xác nhận mới ghi.
 * Quyền giống QuestionController: quản trị viên mọi môn; giáo viên chỉ môn mình được phân công.
 * Chỉ câu hợp lệ và chưa có trong ngân hàng mới được nhập; câu lỗi / câu trùng được liệt kê rõ lý do.
 */
class QuestionImportController extends Controller
{
    private const SESSION_KEY = 'question_import';

    private function isAdmin(): bool
    {
        return $this->user()['role'] === 'admin';
    }

    private function scope(): ?int
    {
        return $this->isAdmin() ? null : (int) $this->user()['id'];
    }

    private function canManage(int $subjectId): bool
    {
        return $this->isAdmin() || Subject::isAssigned($subjectId, (int) $this->user()['id']);
    }

    // ------------------------------------------------------------------
    // Bước 1: chọn môn, tải file / dán nội dung
    // ------------------------------------------------------------------
    public function form(): void
    {
        Session::forget(self::SESSION_KEY);
        $this->view('questions/import', [
            'title'     => 'Nhập câu hỏi từ file',
            'subjects'  => Question::subjectChoices($this->scope(), true),
            'preselect' => (int) $this->request->input('subject_id', 0),
            'sample'    => QuestionImport::SAMPLE_TEXT,
            'maxMb'     => QuestionImport::MAX_FILE_BYTES / 1048576,
            'maxQ'      => QuestionImport::MAX_QUESTIONS,
            'zipOk'     => class_exists(\ZipArchive::class),
        ]);
    }

    // ------------------------------------------------------------------
    // Bước 2: đọc file, kiểm tra từng câu, lưu tạm trong session rồi chuyển sang trang xem trước
    // ------------------------------------------------------------------
    public function preview(): void
    {
        $subjectRaw = (string) $this->request->input('subject_id', '');
        $subject = ctype_digit($subjectRaw) ? Subject::find((int) $subjectRaw) : null;
        if (!$subject || !$subject['is_active'] || !$this->canManage((int) $subject['id'])) {
            $this->fail('Vui lòng chọn một môn học hợp lệ mà bạn được phân công phụ trách.', $subjectRaw);
        }
        $subjectId = (int) $subject['id'];

        [$raw, $error] = $this->readSource();
        if ($error !== '') {
            $this->fail($error, (string) $subjectId);
        }
        if (!$raw) {
            $this->fail('Không tìm thấy câu hỏi nào trong nội dung đã gửi. Hãy kiểm tra lại định dạng theo mẫu bên dưới.', (string) $subjectId);
        }
        if (count($raw) > QuestionImport::MAX_QUESTIONS) {
            $this->fail('Mỗi lần chỉ nhập tối đa ' . QuestionImport::MAX_QUESTIONS . ' câu (file có ' . count($raw) . ' câu). Hãy chia nhỏ file.', (string) $subjectId);
        }

        $existing = Question::contentKeys($subjectId);
        $inFile = [];
        $rows = [];
        foreach ($raw as $r) {
            [$clean, $errors] = QuestionImport::check($r);
            $status = 'error';
            if ($clean !== null) {
                $key = Question::contentKey($clean['content']);
                if (isset($existing[$key])) {
                    $status = 'dup';
                    $errors = ['Đã có câu hỏi này trong ngân hàng câu hỏi của môn.'];
                } elseif (isset($inFile[$key])) {
                    $status = 'dup';
                    $errors = ['Trùng với câu ' . $inFile[$key] . ' trong cùng file.'];
                } else {
                    $status = 'ok';
                    $inFile[$key] = $r['line'];
                }
            }
            $rows[] = [
                'line'        => $r['line'],
                'status'      => $status,
                'errors'      => $errors,
                'content'     => $clean['content'] ?? trim((string) $r['content']),
                'options'     => $clean ? array_map(fn($o) => ['content' => $o['content'], 'is_correct' => (bool) $o['is_correct']], $clean['options'])
                                        : array_map(fn($o) => ['content' => trim((string) $o['text']), 'is_correct' => !empty($o['mark'])], $r['options']),
                'difficulty'  => $clean['difficulty'] ?? null,
                'explanation' => $clean['explanation'] ?? null,
            ];
        }

        Session::set(self::SESSION_KEY, ['subject_id' => $subjectId, 'rows' => $rows, 'source' => $this->sourceName()]);
        $this->redirect('/questions/import/preview');
    }

    public function showPreview(): void
    {
        $data = Session::get(self::SESSION_KEY);
        if (!is_array($data) || empty($data['rows'])) {
            Session::flash('error', 'Phiên nhập đã hết hạn hoặc chưa có dữ liệu. Vui lòng tải file lại.');
            $this->redirect('/questions/import');
        }
        $subject = Subject::find((int) $data['subject_id']);
        if (!$subject || !$this->canManage((int) $subject['id'])) {
            Response::abort(403, 'Bạn không được phân công phụ trách môn học này.');
        }

        $count = ['ok' => 0, 'dup' => 0, 'error' => 0];
        foreach ($data['rows'] as $r) {
            $count[$r['status']]++;
        }
        $this->view('questions/import_preview', [
            'title'   => 'Xem trước khi nhập',
            'subject' => $subject,
            'rows'    => $data['rows'],
            'count'   => $count,
            'source'  => (string) ($data['source'] ?? ''),
        ]);
    }

    // ------------------------------------------------------------------
    // Bước 3: ghi vào ngân hàng câu hỏi
    // ------------------------------------------------------------------
    public function confirm(): void
    {
        $data = Session::get(self::SESSION_KEY);
        if (!is_array($data) || empty($data['rows'])) {
            Session::flash('error', 'Phiên nhập đã hết hạn hoặc đã được nhập rồi. Vui lòng tải file lại.');
            $this->redirect('/questions/import');
        }
        $subjectId = (int) $data['subject_id'];
        $subject = Subject::find($subjectId);
        if (!$subject || !$subject['is_active'] || !$this->canManage($subjectId)) {
            Response::abort(403, 'Bạn không được phân công phụ trách môn học này.');
        }

        // Nhập lại bước kiểm tra trùng ngay lúc ghi (có thể ai đó vừa thêm câu giống trong lúc xem trước)
        $existing = Question::contentKeys($subjectId);
        $items = [];
        foreach ($data['rows'] as $r) {
            if ($r['status'] !== 'ok' || isset($existing[Question::contentKey($r['content'])])) {
                continue;
            }
            $items[] = ['content' => $r['content'], 'difficulty' => $r['difficulty'], 'explanation' => $r['explanation'], 'options' => $r['options']];
        }
        if (!$items) {
            Session::forget(self::SESSION_KEY);
            Session::flash('error', 'Không có câu hỏi hợp lệ nào để nhập.');
            $this->redirect('/questions/import?subject_id=' . $subjectId);
        }

        $n = Question::importMany($items, $subjectId, (int) $this->user()['id']);
        Session::forget(self::SESSION_KEY);
        Session::flash('success', 'Đã nhập ' . $n . ' câu hỏi vào môn ' . $subject['code'] . '.');
        $this->redirect('/questions?subject_id=' . $subjectId);
    }

    // ------------------------------------------------------------------
    // File mẫu
    // ------------------------------------------------------------------
    public function template(string $type): void
    {
        if ($type === 'xlsx') {
            if (!class_exists(\ZipArchive::class)) {
                Response::abort(500, 'Máy chủ chưa bật extension zip của PHP nên không tạo được file Excel mẫu.');
            }
            $body = QuestionImport::templateXlsx();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="mau-nhap-cau-hoi.xlsx"');
        } elseif ($type === 'txt') {
            $body = "\xEF\xBB\xBF" . QuestionImport::SAMPLE_TEXT;
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="mau-nhap-cau-hoi.txt"');
        } else {
            Response::abort(404, 'Không có file mẫu này.');
        }
        header('Content-Length: ' . strlen($body));
        header('X-Content-Type-Options: nosniff');
        echo $body;
        exit;
    }

    // ------------------------------------------------------------------
    // Hàm phụ
    // ------------------------------------------------------------------

    private string $sourceLabel = 'nội dung dán';

    private function sourceName(): string
    {
        return $this->sourceLabel;
    }

    /**
     * Lấy nội dung từ file tải lên (ưu tiên) hoặc ô dán. Trả về [câu thô, lỗi].
     * Chỉ tin đuôi file đã whitelist + kiểm tra chữ ký "PK" của zip (.xlsx / .docx); không tin MIME do trình duyệt gửi.
     */
    private function readSource(): array
    {
        $f = $_FILES['file'] ?? null;
        $hasFile = is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($hasFile) {
            $err = (int) $f['error'];
            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || (int) $f['size'] > QuestionImport::MAX_FILE_BYTES) {
                return [[], 'File quá lớn (tối đa ' . (QuestionImport::MAX_FILE_BYTES / 1048576) . ' MB).'];
            }
            if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
                return [[], 'Tải file lên không thành công. Vui lòng thử lại.'];
            }
            $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, QuestionImport::EXTENSIONS, true)) {
                return [[], 'Chỉ nhận file ' . implode(', ', array_map(fn($e) => '.' . $e, QuestionImport::EXTENSIONS)) . '. (File Word cũ .doc / Excel cũ .xls hãy lưu lại thành .docx / .xlsx.)'];
            }
            if (in_array($ext, ['xlsx', 'docx'], true) && file_get_contents((string) $f['tmp_name'], false, null, 0, 2) !== 'PK') {
                return [[], 'File không đúng định dạng .' . $ext . '.'];
            }
            $this->sourceLabel = str_cut(preg_replace('/[^\p{L}\p{N} ._\-()]/u', '', basename((string) $f['name'])), 80);
            return QuestionImport::parseFile((string) $f['tmp_name'], $ext);
        }

        $text = trim((string) $this->request->input('text', ''));
        if ($text === '') {
            return [[], 'Hãy chọn file hoặc dán nội dung câu hỏi vào ô bên dưới.'];
        }
        if (strlen($text) > QuestionImport::MAX_FILE_BYTES) {
            return [[], 'Nội dung dán quá dài.'];
        }
        return [QuestionImport::parseText($text), ''];
    }

    private function fail(string $message, string $subjectId): void
    {
        Session::flash('error', $message);
        $this->redirect('/questions/import' . (ctype_digit($subjectId) && (int) $subjectId > 0 ? '?subject_id=' . (int) $subjectId : ''));
    }
}
