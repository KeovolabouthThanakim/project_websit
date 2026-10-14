<?php
$size = function (int $b): string {
    return $b >= 1048576 ? number_format($b / 1048576, 1, ',', '') . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
};
?>
<div class="page-head">
    <div>
        <h1>Sao lưu và khôi phục</h1>
        <p>Tải bản sao lưu cơ sở dữ liệu về máy, hoặc khôi phục lại từ một bản đã lưu</p>
    </div>
</div>

<section class="panel">
    <h2 class="panel-title">Sao lưu</h2>
    <div class="panel-body">
        <p class="muted">Bản sao lưu gồm toàn bộ tài khoản (đã mã hóa mật khẩu), lớp, môn, câu hỏi, đề thi, bài làm và nhật ký. Nên sao lưu trước mỗi kỳ thi lớn và cất file ở nơi an toàn, vì file chứa dữ liệu cá nhân.</p>
        <div class="header-actions">
            <form method="post" action="<?= url('/admin/backup/download') ?>">
                <?= csrf_field() ?>
                <button class="btn" type="submit">Tải bản sao lưu (.sql)</button>
            </form>
            <form method="post" action="<?= url('/admin/backup/save') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline" type="submit">Lưu một bản trên máy chủ</button>
            </form>
            <?php if ($zipOk): ?>
            <form method="post" action="<?= url('/admin/backup/files') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline" type="submit">Tải ảnh câu hỏi (.zip)</button>
            </form>
            <?php endif; ?>
        </div>
        <p class="muted" style="margin-top:12px">Ảnh minh họa câu hỏi nằm ở thư mục <code>uploads/</code>, không nằm trong file .sql. Nếu có dùng ảnh, hãy tải thêm file .zip.</p>
    </div>
</section>

<?php if ($saved): ?>
<section class="panel">
    <h2 class="panel-title">Bản sao lưu trên máy chủ (storage/backups)</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Tên file</th><th>Thời gian</th><th>Dung lượng</th><th>Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($saved as $s): ?>
                <tr>
                    <td><?= e($s['name']) ?></td>
                    <td><?= e(date('d/m/Y H:i', $s['time'])) ?></td>
                    <td><?= e($size($s['size'])) ?></td>
                    <td class="actions">
                        <a class="btn btn-sm btn-outline" href="<?= url('/admin/backup/files/' . rawurlencode($s['name'])) ?>">Tải về</a>
                        <form method="post" action="<?= url('/admin/backup/files/' . rawurlencode($s['name']) . '/delete') ?>" data-confirm="Xóa bản sao lưu &quot;<?= e($s['name']) ?>&quot;?">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-danger" type="submit">Xóa</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <h2 class="panel-title">Khôi phục</h2>
    <div class="panel-body">
        <div class="toast toast-error" role="alert" style="margin-bottom:16px"><div class="toast-body"><strong>Ghi đè toàn bộ dữ liệu hiện tại</strong><span>Mọi thay đổi sau thời điểm của bản sao lưu sẽ mất. Hệ thống tự lưu bản hiện tại (tên bắt đầu bằng <code>truoc-khoi-phuc</code>) để bạn quay lại được, và sẽ yêu cầu đăng nhập lại sau khi xong. Không khôi phục khi đang có kỳ thi diễn ra.</span></div></div>
        <form method="post" action="<?= url('/admin/backup/restore') ?>" enctype="multipart/form-data" autocomplete="off">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="form-row">
                    <label for="file">File sao lưu (.sql)</label>
                    <input type="file" id="file" name="file" accept=".sql">
                    <span class="hint">Giới hạn tải lên của máy chủ: <?= e((string) $maxUpload) ?>. File lớn hơn: dùng Import của phpMyAdmin, hoặc chép file vào <code>storage/backups</code> rồi chọn ở ô bên cạnh.</span>
                </div>
                <?php if ($saved): ?>
                <div class="form-row">
                    <label for="saved">Hoặc chọn bản trên máy chủ</label>
                    <select id="saved" name="saved">
                        <option value="">- Dùng file tải lên -</option>
                        <?php foreach ($saved as $s): ?>
                            <option value="<?= e($s['name']) ?>"><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="form-row">
                    <label for="confirm">Gõ <strong><?= e($word) ?></strong> để xác nhận</label>
                    <input type="text" id="confirm" name="confirm" required autocomplete="off">
                </div>
                <div class="form-row">
                    <label for="password">Mật khẩu của bạn</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
            </div>
            <button class="btn btn-danger" type="submit">Khôi phục dữ liệu</button>
        </form>
    </div>
</section>
