<?php
use App\Models\Question;

$isEdit = $mode === 'edit';
form_errors();
$has = fn(string $f): string => isset(form_errors()[$f]) ? ' has-error' : '';

$subjectId   = (int) old('subject_id', $isEdit ? (string) $q['subject_id'] : (string) $preselect);
$content     = old('content', $isEdit ? $q['content'] : '');
$explanation = old('explanation', $isEdit ? (string) $q['explanation'] : '');
$difficulty  = old('difficulty', $isEdit ? $q['difficulty'] : 'medium');
$active      = old('is_active', $isEdit ? ($q['is_active'] ? '1' : '0') : '1') === '1';

// Danh sách đáp án: ưu tiên dữ liệu vừa nhập (khi form bị trả về do lỗi), sau đó đến dữ liệu đã lưu, cuối cùng là 4 dòng trống
$rows = json_decode(old('options', '[]'), true) ?: [];
if (!$rows) {
    $rows = array_map(fn($o) => ['content' => $o['content'], 'is_correct' => (int) $o['is_correct']], $options);
}
while (count($rows) < 4) {
    $rows[] = ['content' => '', 'is_correct' => 0];
}

$action  = $isEdit ? url('/questions/' . (int) $q['id']) : url('/questions');
$backUrl = url($isEdit ? '/questions/' . (int) $q['id'] : '/questions');
?>
<div class="page-head">
    <div>
        <h1><?= $isEdit ? 'Sửa câu hỏi' : 'Thêm câu hỏi mới' ?></h1>
        <p><?= $isEdit ? 'Câu hỏi #' . (int) $q['id'] : 'Soạn câu hỏi trắc nghiệm cho một môn học' ?></p>
    </div>
    <a class="btn btn-outline" href="<?= $backUrl ?>">&laquo; Quay lại</a>
</div>

<?php if (!$subjects): ?>
    <section class="panel"><div class="panel-body"><p class="muted" style="margin:0">Bạn chưa có môn học nào để soạn câu hỏi. Môn học phải đang sử dụng và được phân công cho bạn (quản trị viên thực hiện ở mục Môn học).</p></div></section>
<?php else: ?>
<form method="post" action="<?= $action ?>" enctype="multipart/form-data" autocomplete="off" id="q-form">
    <?= csrf_field() ?>
    <section class="panel">
        <h2 class="panel-title">Nội dung câu hỏi</h2>
        <div class="panel-body">
            <div class="form-grid">
                <div class="form-row<?= $has('subject_id') ?>">
                    <label for="subject_id">Môn học <span class="req">*</span></label>
                    <select id="subject_id" name="subject_id" required>
                        <option value="">-- Chọn môn học --</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= $subjectId === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['code'] . ' - ' . $s['name']) ?><?= $s['is_active'] ? '' : ' (ngừng sử dụng)' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error('subject_id') ?>
                </div>
                <div class="form-row<?= $has('difficulty') ?>">
                    <label for="difficulty">Độ khó <span class="req">*</span></label>
                    <select id="difficulty" name="difficulty">
                        <?php foreach (Question::DIFFICULTIES as $k => $label): ?>
                            <option value="<?= e($k) ?>" <?= $difficulty === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error('difficulty') ?>
                </div>
            </div>

            <div class="form-row<?= $has('content') ?>">
                <label for="content">Nội dung câu hỏi <span class="req">*</span></label>
                <textarea id="content" name="content" rows="4" maxlength="2000" required><?= e($content) ?></textarea>
                <?= field_error('content') ?>
            </div>

            <div class="form-row<?= $has('image') ?>">
                <label for="image">Ảnh minh họa</label>
                <?php if ($isEdit && $q['image']): ?>
                    <div class="current-image">
                        <img class="q-image" src="<?= url('/question-images/' . e($q['image'])) ?>" alt="Ảnh hiện tại">
                        <label class="check-row"><input type="checkbox" name="remove_image" value="1"> Xóa ảnh hiện tại</label>
                    </div>
                <?php endif; ?>
                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                <?= field_error('image') ?>
                <span class="hint">Không bắt buộc. JPG, PNG, GIF hoặc WEBP, tối đa 2 MB<?= $isEdit && $q['image'] ? '; chọn ảnh mới sẽ thay ảnh hiện tại' : '' ?>.</span>
            </div>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Các đáp án</h2>
        <div class="panel-body">
            <div class="form-row<?= $has('options') ?>" style="margin-bottom:0">
                <?= field_error('options') ?>
                <div id="option-list">
                    <?php foreach ($rows as $i => $r): ?>
                        <div class="option-row">
                            <span class="opt-letter"><?= chr(65 + $i) ?></span>
                            <input type="text" name="options[]" value="<?= e($r['content']) ?>" maxlength="500" placeholder="Nội dung đáp án">
                            <label class="check-row"><input type="checkbox" name="correct[]" value="<?= $i ?>" <?= $r['is_correct'] ? 'checked' : '' ?>> Đúng</label>
                            <button type="button" class="btn btn-sm btn-danger js-remove" title="Xóa đáp án này">Xóa</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:10px">
                    <button type="button" class="btn btn-sm btn-outline" id="add-option">+ Thêm đáp án</button>
                </div>
                <span class="hint">Từ <?= Question::MIN_OPTIONS ?> đến <?= Question::MAX_OPTIONS ?> đáp án; dòng để trống sẽ được bỏ qua. Tick một đáp án đúng cho câu một lựa chọn, tick nhiều đáp án cho câu nhiều lựa chọn.</span>
            </div>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Khác</h2>
        <div class="panel-body">
            <div class="form-row<?= $has('explanation') ?>">
                <label for="explanation">Giải thích đáp án</label>
                <textarea id="explanation" name="explanation" rows="3" maxlength="2000"><?= e($explanation) ?></textarea>
                <?= field_error('explanation') ?>
                <span class="hint">Không bắt buộc. Có thể hiển thị cho sinh viên sau khi làm bài.</span>
            </div>
            <div class="form-row" style="margin-bottom:0">
                <label class="check-row">
                    <input type="checkbox" name="is_active" value="1" <?= $active ? 'checked' : '' ?>>
                    Đang hiện (bỏ chọn để ẩn câu hỏi khỏi các đề thi mới)
                </label>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <button class="btn" type="submit"><?= $isEdit ? 'Lưu thay đổi' : 'Thêm câu hỏi' ?></button>
        <a class="btn btn-outline" href="<?= $backUrl ?>">Hủy</a>
    </div>
</form>

<script>
(function () {
    var list = document.getElementById('option-list');
    var addBtn = document.getElementById('add-option');
    var MIN = <?= Question::MIN_OPTIONS ?>, MAX = <?= Question::MAX_OPTIONS ?>;

    // Đánh lại chữ cái A, B, C... và chỉ số của ô tick sau mỗi lần thêm / xóa dòng
    function renumber() {
        var rows = list.querySelectorAll('.option-row');
        rows.forEach(function (row, i) {
            row.querySelector('.opt-letter').textContent = String.fromCharCode(65 + i);
            row.querySelector('input[type=checkbox]').value = i;
            row.querySelector('.js-remove').disabled = rows.length <= MIN;
        });
        addBtn.disabled = rows.length >= MAX;
    }

    addBtn.addEventListener('click', function () {
        var rows = list.querySelectorAll('.option-row');
        if (rows.length >= MAX) return;
        var copy = rows[rows.length - 1].cloneNode(true);
        copy.querySelector('input[type=text]').value = '';
        copy.querySelector('input[type=checkbox]').checked = false;
        list.appendChild(copy);
        renumber();
        copy.querySelector('input[type=text]').focus();
    });

    list.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.js-remove');
        if (!btn || list.querySelectorAll('.option-row').length <= MIN) return;
        btn.closest('.option-row').remove();
        renumber();
    });

    renumber();
})();
</script>
<?php endif; ?>
