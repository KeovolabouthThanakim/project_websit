<div class="page-head">
    <div>
        <h1>Tạo đề thi</h1>
        <p>Bước 1: chọn môn học của đề thi</p>
    </div>
    <a class="btn btn-outline" href="<?= url('/exams') ?>">&laquo; Danh sách đề thi</a>
</div>

<section class="panel">
    <h2 class="panel-title">Chọn môn học</h2>
    <div class="panel-body">
        <?php if ($subjects): ?>
            <?php if ($invalid): ?><p class="field-error" style="margin:0 0 14px">Môn học không hợp lệ hoặc bạn không được phân công môn này.</p><?php endif; ?>
            <form method="get" action="<?= url('/exams/create') ?>" style="max-width:520px">
                <div class="form-row">
                    <label for="subject_id">Môn học <span class="req">*</span></label>
                    <select id="subject_id" name="subject_id" required>
                        <option value="">-- Chọn môn học --</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= e($s['code'] . ' - ' . $s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="hint">Đề thi chỉ dành cho các lớp đang học môn này và chỉ lấy câu hỏi của môn này.</span>
                </div>
                <button class="btn" type="submit">Tiếp tục</button>
            </form>
        <?php else: ?>
            <p class="muted" style="margin:0">Bạn chưa có môn học nào để tạo đề thi. Môn học phải đang sử dụng và được phân công cho bạn (quản trị viên thực hiện ở mục Môn học).</p>
        <?php endif; ?>
    </div>
</section>
