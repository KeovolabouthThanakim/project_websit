<?php
$picked = $preselect;
if ($picked === 0 && count($subjects) === 1) {
    $picked = (int) $subjects[0]['id'];
}
?>
<div class="page-head">
    <div>
        <h1>Nhập câu hỏi từ file</h1>
        <p>Thêm nhiều câu hỏi cùng lúc từ Excel, Word hoặc văn bản. Bạn sẽ được xem trước, chưa có gì bị ghi cho đến khi xác nhận.</p>
    </div>
    <a class="btn btn-outline" href="<?= url('/questions') ?>">&laquo; Ngân hàng câu hỏi</a>
</div>

<?php if (!$subjects): ?>
    <section class="panel"><div class="panel-body"><p style="margin:0">Bạn chưa được phân công môn học nào đang sử dụng nên chưa thể nhập câu hỏi.</p></div></section>
<?php else: ?>
<form method="post" action="<?= url('/questions/import/preview') ?>" enctype="multipart/form-data" autocomplete="off">
    <?= csrf_field() ?>
    <section class="panel">
        <h2 class="panel-title">1. Chọn môn và nguồn câu hỏi</h2>
        <div class="panel-body">
            <div class="form-row">
                <label for="subject_id">Môn học <span class="req">*</span></label>
                <select id="subject_id" name="subject_id" required>
                    <option value="">- Chọn môn học -</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= $picked === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['code'] . ' - ' . $s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label for="file">File Excel (.xlsx), CSV, Word (.docx) hoặc văn bản (.txt)</label>
                <input type="file" id="file" name="file" accept=".xlsx,.csv,.docx,.txt">
                <span class="hint">Tối đa <?= (int) $maxMb ?> MB và <?= (int) $maxQ ?> câu mỗi lần.<?= $zipOk ? '' : ' Máy chủ chưa bật extension zip nên chưa đọc được .xlsx / .docx - hãy dùng CSV hoặc dán nội dung.' ?></span>
            </div>
            <div class="form-row">
                <label for="text">Hoặc dán trực tiếp nội dung (copy từ Word)</label>
                <textarea id="text" name="text" rows="8" placeholder="Câu 1: ...&#10;A. ...&#10;*B. ...&#10;Đáp án: B"></textarea>
                <span class="hint">Nếu chọn file thì ô này được bỏ qua.</span>
            </div>
            <button class="btn" type="submit">Đọc file và xem trước</button>
        </div>
    </section>
</form>
<?php endif; ?>

<div class="two-col">
    <section class="panel">
        <h2 class="panel-title">Mẫu Excel / CSV</h2>
        <div class="panel-body">
            <p style="margin-top:0">Mỗi câu hỏi một dòng, các cột theo thứ tự:</p>
            <p><strong>Câu hỏi</strong> | <strong>A</strong> | <strong>B</strong> | <strong>C</strong> | <strong>D</strong> | E | F | <strong>Đáp án đúng</strong> | Độ khó | Giải thích</p>
            <ul class="rules">
                <li>2 đến 6 đáp án (cột A - F); ô đáp án không dùng thì để trống ở cuối.</li>
                <li><strong>Đáp án đúng</strong>: chữ cái, ví dụ <code>B</code>. Câu nhiều đáp án đúng: <code>A, C</code>.</li>
                <li><strong>Độ khó</strong>: Dễ, Trung bình hoặc Khó (để trống = Trung bình).</li>
                <li>Dòng đầu là tiêu đề thì được tự nhận (cột có thể xếp thứ tự khác); không có tiêu đề thì theo thứ tự trên.</li>
                <li>CSV phải lưu dạng <strong>UTF-8</strong> (Excel: "CSV UTF-8 (được phân tách bằng dấu phẩy)") để không mất dấu tiếng Việt.</li>
            </ul>
            <a class="btn btn-sm btn-outline" href="<?= url('/questions/import/template/xlsx') ?>">Tải file Excel mẫu</a>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Mẫu Word / văn bản</h2>
        <div class="panel-body">
            <code class="code-sample"><?= e($sample) ?></code>
            <ul class="rules" style="margin-top:12px">
                <li>Đáp án đúng: ghi dòng <code>Đáp án: B</code> hoặc đặt dấu <code>*</code> ngay trước đáp án đúng.</li>
                <li>Gõ nhãn <code>A.</code> <code>B.</code> <code>C.</code> bằng tay, <strong>không</strong> dùng danh sách tự đánh số của Word (Word không lưu nhãn đó trong file).</li>
                <li>Giải thích và độ khó không bắt buộc. Nội dung câu hỏi được xuống dòng thoải mái.</li>
            </ul>
            <a class="btn btn-sm btn-outline" href="<?= url('/questions/import/template/txt') ?>">Tải file văn bản mẫu</a>
        </div>
    </section>
</div>
