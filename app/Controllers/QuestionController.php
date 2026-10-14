<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\Question;
use App\Models\Subject;

/**
 * Ngân hàng câu hỏi.
 *  - Quản trị viên: xem / thêm / sửa / ẩn / xóa câu hỏi của mọi môn
 *  - Giáo viên: chỉ làm việc với câu hỏi của các môn mình được phân công (kiểm tra trong canManage())
 */
class QuestionController extends Controller
{
    private const PER_PAGE = 10;
    private const STATUSES = ['active', 'inactive'];
    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;
    private const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    private function isAdmin(): bool
    {
        return $this->user()['role'] === 'admin';
    }

    /** null = không giới hạn (admin); ngược lại là id giáo viên để lọc theo môn được phân công */
    private function scope(): ?int
    {
        return $this->isAdmin() ? null : (int) $this->user()['id'];
    }

    private function canManage(int $subjectId): bool
    {
        return $this->isAdmin() || Subject::isAssigned($subjectId, (int) $this->user()['id']);
    }

    // ------------------------------------------------------------------
    // Danh sách / chi tiết
    // ------------------------------------------------------------------
    public function index(): void
    {
        $filters = $this->filters();
        $page = (int) $this->request->input('page', 1);

        $this->view('questions/index', [
            'title'    => 'Ngân hàng câu hỏi',
            'result'   => Question::search($filters, $page, self::PER_PAGE, $this->scope()),
            'f'        => $filters,
            'subjects' => Question::subjectChoices($this->scope(), false),
            'perPage'  => self::PER_PAGE,
            'isAdmin'  => $this->isAdmin(),
        ]);
    }

    public function show(string $id): void
    {
        $q = $this->findAuthorized($id);
        $this->view('questions/show', [
            'title'    => 'Câu hỏi #' . $q['id'],
            'q'        => $q,
            'options'  => Question::options((int) $q['id']),
        ]);
    }

    // ------------------------------------------------------------------
    // Thêm / sửa
    // ------------------------------------------------------------------
    public function create(): void
    {
        $subjects = Question::subjectChoices($this->scope(), true);
        $preselect = (int) $this->request->input('subject_id', 0);

        $this->view('questions/form', [
            'title' => 'Thêm câu hỏi', 'mode' => 'create', 'q' => null, 'options' => [],
            'subjects' => $subjects, 'preselect' => $preselect,
        ]);
    }

    public function store(): void
    {
        [$data, $options, $errors, $image] = $this->validate(null);
        if ($errors) {
            $this->backWithErrors('/questions/create', $errors, $data, $options);
        }

        $data['created_by'] = (int) $this->user()['id'];
        $data['image'] = $image['new'] !== null ? $this->storeImage($image['new']) : null;
        try {
            $id = Question::saveWithOptions(null, $data, $options);
        } catch (\Throwable $e) {
            $this->deleteImage($data['image']);
            throw $e;
        }

        Session::flash('success', 'Đã thêm câu hỏi vào ngân hàng.');
        $this->redirect('/questions/' . $id);
    }

    public function edit(string $id): void
    {
        $q = $this->findAuthorized($id);
        $this->view('questions/form', [
            'title' => 'Sửa câu hỏi', 'mode' => 'edit', 'q' => $q,
            'options'  => Question::options((int) $q['id']),
            'subjects' => Question::subjectChoices($this->scope(), true, (int) $q['subject_id']),
            'preselect' => 0,
        ]);
    }

    public function update(string $id): void
    {
        $q = $this->findAuthorized($id);
        [$data, $options, $errors, $image] = $this->validate($q);
        if ($errors) {
            $this->backWithErrors('/questions/' . $q['id'] . '/edit', $errors, $data, $options);
        }

        $oldImage = $q['image'];
        $newImage = $image['new'] !== null ? $this->storeImage($image['new']) : null;
        $data['image'] = $newImage ?? ($image['remove'] ? null : $oldImage);
        try {
            Question::saveWithOptions((int) $q['id'], $data, $options);
        } catch (\Throwable $e) {
            $this->deleteImage($newImage);
            throw $e;
        }
        if ($data['image'] !== $oldImage) {
            $this->deleteImage($oldImage);
        }

        Session::flash('success', 'Đã cập nhật câu hỏi.');
        $this->redirect('/questions/' . $q['id']);
    }

    // ------------------------------------------------------------------
    // Ẩn / hiện, xóa
    // ------------------------------------------------------------------
    public function toggle(string $id): void
    {
        $q = $this->findAuthorized($id);
        $new = $q['is_active'] ? 0 : 1;
        Question::update((int) $q['id'], ['is_active' => $new]);

        Session::flash('success', ($new ? 'Đã hiện lại' : 'Đã ẩn') . ' câu hỏi #' . $q['id'] . '.');
        $this->redirect($this->listUrl());
    }

    public function destroy(string $id): void
    {
        $q = $this->findAuthorized($id);

        try {
            Question::delete((int) $q['id']);
            $this->deleteImage($q['image']);
            Session::flash('success', 'Đã xóa câu hỏi #' . $q['id'] . '.');
        } catch (\PDOException $e) {
            // Vi phạm khóa ngoại: câu hỏi đã nằm trong đề thi / bài làm
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            Session::flash('error', 'Không thể xóa câu hỏi #' . $q['id'] . ' vì đã được dùng trong đề thi. Hãy chọn "Ẩn" thay vì xóa.');
        }
        $this->redirect($this->listUrl());
    }

    // ------------------------------------------------------------------
    // Ảnh câu hỏi (thư mục uploads/ nằm ngoài public/ nên phải đọc qua controller)
    // ------------------------------------------------------------------
    public function image(string $file): void
    {
        $q = preg_match('/^[a-f0-9]{32}\.(jpg|png|gif|webp)$/', $file) ? Question::findBy('image', $file) : null;
        $path = $q ? $this->imageDir() . '/' . $file : '';
        if (!$q || !is_file($path)) {
            Response::abort(404, 'Không tìm thấy ảnh.');
        }
        if (!$this->canManage((int) $q['subject_id'])) {
            Response::abort(403);
        }

        $mime = array_search(pathinfo($file, PATHINFO_EXTENSION), self::IMAGE_TYPES, true);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    // ------------------------------------------------------------------
    // Hàm phụ
    // ------------------------------------------------------------------
    private function findAuthorized(string $id): array
    {
        $q = ctype_digit($id) ? Question::detail((int) $id) : null;
        if (!$q) {
            Response::abort(404, 'Không tìm thấy câu hỏi.');
        }
        if (!$this->canManage((int) $q['subject_id'])) {
            Response::abort(403, 'Bạn không được phân công phụ trách môn học của câu hỏi này.');
        }
        return $q;
    }

    private function filters(): array
    {
        $status = (string) $this->request->input('status', '');
        $difficulty = (string) $this->request->input('difficulty', '');
        $subjectId = (string) $this->request->input('subject_id', '');
        return [
            'q'          => str_cut(trim((string) $this->request->input('q', '')), 100),
            'subject_id' => ctype_digit($subjectId) ? (int) $subjectId : 0,
            'difficulty' => isset(Question::DIFFICULTIES[$difficulty]) ? $difficulty : '',
            'status'     => in_array($status, self::STATUSES, true) ? $status : '',
        ];
    }

    /** Quay lại danh sách, giữ bộ lọc / trang đang xem (trường back_*) */
    private function listUrl(): string
    {
        $page = (int) $this->request->input('back_page', 1);
        $params = array_filter([
            'q'          => str_cut(trim((string) $this->request->input('back_q', '')), 100),
            'subject_id' => ctype_digit((string) $this->request->input('back_subject_id', '')) ? (string) (int) $this->request->input('back_subject_id') : '',
            'difficulty' => isset(Question::DIFFICULTIES[(string) $this->request->input('back_difficulty', '')]) ? (string) $this->request->input('back_difficulty') : '',
            'status'     => in_array((string) $this->request->input('back_status', ''), self::STATUSES, true) ? (string) $this->request->input('back_status') : '',
            'page'       => $page > 1 ? $page : '',
        ], fn($v) => $v !== '' && $v !== '0');

        return '/questions' . ($params ? '?' . http_build_query($params) : '');
    }

    private function backWithErrors(string $path, array $errors, array $data, array $options): void
    {
        Session::flash('errors', $errors);
        Session::flash('error', 'Vui lòng kiểm tra lại các thông tin bị đánh dấu lỗi.');
        foreach (['subject_id', 'content', 'explanation', 'difficulty'] as $f) {
            Session::flash('old_' . $f, (string) ($data[$f] ?? ''));
        }
        Session::flash('old_is_active', $data['is_active'] ? '1' : '0');
        Session::flash('old_options', json_encode($options, JSON_UNESCAPED_UNICODE));
        $this->redirect($path);
    }

    /**
     * @param array|null $existing câu hỏi đang sửa (null khi thêm mới)
     * @return array [dữ liệu câu hỏi, danh sách đáp án, lỗi, ['new' => file ảnh mới|null, 'remove' => bool]]
     */
    private function validate(?array $existing): array
    {
        $in = $this->request->all();
        $errors = [];

        // Môn học: phải tồn tại, được phép soạn câu hỏi, và đang sử dụng (trừ khi giữ nguyên môn cũ)
        $subjectRaw = trim((string) ($in['subject_id'] ?? ''));
        $subject = ctype_digit($subjectRaw) ? Subject::find((int) $subjectRaw) : null;
        if (!$subject) {
            $errors['subject_id'] = 'Vui lòng chọn môn học.';
        } elseif (!$this->canManage((int) $subject['id'])) {
            $errors['subject_id'] = 'Bạn không được phân công phụ trách môn học này.';
        } elseif (!$subject['is_active'] && (!$existing || (int) $existing['subject_id'] !== (int) $subject['id'])) {
            $errors['subject_id'] = 'Môn học đang ngừng sử dụng.';
        }

        $content = trim((string) ($in['content'] ?? ''));
        if ($content === '') {
            $errors['content'] = 'Vui lòng nhập nội dung câu hỏi.';
        } elseif (str_len($content) > 2000) {
            $errors['content'] = 'Nội dung câu hỏi tối đa 2000 ký tự.';
        }

        $explanation = trim((string) ($in['explanation'] ?? ''));
        if (str_len($explanation) > 2000) {
            $errors['explanation'] = 'Giải thích tối đa 2000 ký tự.';
        }

        $difficulty = (string) ($in['difficulty'] ?? '');
        if (!isset(Question::DIFFICULTIES[$difficulty])) {
            $errors['difficulty'] = 'Độ khó không hợp lệ.';
        }

        // Đáp án: options[i] là nội dung, correct[] chứa chỉ số các dòng được tick. Bỏ qua dòng để trống.
        $correctRows = array_map('strval', (array) ($in['correct'] ?? []));
        $options = [];
        $seen = [];
        $tickedEmpty = false;
        foreach (array_values((array) ($in['options'] ?? [])) as $i => $text) {
            $text = trim((string) $text);
            $ticked = in_array((string) $i, $correctRows, true);
            if ($text === '') {
                $tickedEmpty = $tickedEmpty || $ticked;
                continue;
            }
            if (str_len($text) > 500) {
                $errors['options'] = 'Mỗi đáp án tối đa 500 ký tự.';
            }
            $key = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
            if (isset($seen[$key])) {
                $errors['options'] = 'Các đáp án không được trùng nhau.';
            }
            $seen[$key] = true;
            $options[] = ['content' => $text, 'is_correct' => $ticked ? 1 : 0];
        }
        $correctCount = array_sum(array_column($options, 'is_correct'));
        if (!isset($errors['options'])) {
            if (count($options) < Question::MIN_OPTIONS) {
                $errors['options'] = 'Cần ít nhất ' . Question::MIN_OPTIONS . ' đáp án.';
            } elseif (count($options) > Question::MAX_OPTIONS) {
                $errors['options'] = 'Tối đa ' . Question::MAX_OPTIONS . ' đáp án.';
            } elseif ($tickedEmpty) {
                $errors['options'] = 'Đáp án được đánh dấu đúng không được để trống.';
            } elseif ($correctCount < 1) {
                $errors['options'] = 'Vui lòng chọn ít nhất một đáp án đúng.';
            } elseif ($correctCount === count($options)) {
                $errors['options'] = 'Không thể để tất cả đáp án đều đúng.';
            }
        }

        $image = ['new' => null, 'remove' => !empty($in['remove_image'])];
        $imageError = $this->checkImage($image['new']);
        if ($imageError !== '') {
            $errors['image'] = $imageError;
        }

        $data = [
            'subject_id'  => $subject ? (int) $subject['id'] : 0,
            'content'     => $content,
            'explanation' => $explanation === '' ? null : $explanation,
            'difficulty'  => $difficulty,
            'is_active'   => !empty($in['is_active']) ? 1 : 0,
        ];
        return [$data, $options, $errors, $image];
    }

    /** Kiểm tra file ảnh tải lên. Trả về thông báo lỗi ('' nếu ổn hoặc không có file) và gán $file */
    private function checkImage(?array &$file): string
    {
        $f = $_FILES['image'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return '';
        }
        if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
            return 'Ảnh quá lớn (tối đa 2 MB).';
        }
        if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            return 'Không tải được ảnh lên, vui lòng thử lại.';
        }
        if ($f['size'] > self::MAX_IMAGE_BYTES) {
            return 'Ảnh quá lớn (tối đa 2 MB).';
        }
        $info = @getimagesize($f['tmp_name']);
        if (!$info || !isset(self::IMAGE_TYPES[$info['mime']])) {
            return 'Chỉ chấp nhận ảnh JPG, PNG, GIF hoặc WEBP.';
        }
        $file = ['tmp' => $f['tmp_name'], 'ext' => self::IMAGE_TYPES[$info['mime']]];
        return '';
    }

    private function imageDir(): string
    {
        return ROOT . '/uploads/questions';
    }

    /** Lưu ảnh với tên ngẫu nhiên, trả về tên file */
    private function storeImage(array $file): string
    {
        $dir = $this->imageDir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Không tạo được thư mục uploads/questions.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . $file['ext'];
        if (!move_uploaded_file($file['tmp'], $dir . '/' . $name)) {
            throw new \RuntimeException('Không lưu được ảnh vào thư mục uploads/questions.');
        }
        return $name;
    }

    private function deleteImage(?string $name): void
    {
        if ($name && preg_match('/^[a-f0-9]{32}\.(jpg|png|gif|webp)$/', $name)) {
            @unlink($this->imageDir() . '/' . $name);
        }
    }
}
