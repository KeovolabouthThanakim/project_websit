<div class="page-head">
    <div>
        <h1>Nhập người dùng từ file</h1>
        <p>Tạo hàng loạt tài khoản sinh viên / giảng viên và xếp lớp cho sinh viên ngay lúc nhập. Bạn được xem trước, chưa có gì bị ghi cho đến khi xác nhận.</p>
    </div>
    <a class="btn btn-outline" href="<?= url('/admin/users') ?>">&laquo; Quản lý người dùng</a>
</div>

<form method="post" action="<?= url('/admin/users/import/preview') ?>" enctype="multipart/form-data" autocomplete="off">
    <?= csrf_field() ?>
    <section class="panel">
        <h2 class="panel-title">Tải file</h2>
        <div class="panel-body">
            <div class="form-row">
                <label for="file">File Excel (.xlsx) hoặc CSV <span class="req">*</span></label>
                <input type="file" id="file" name="file" accept=".xlsx,.csv" required>
                <span class="hint">Tối đa <?= (int) $max ?> tài khoản mỗi lần. CSV phải lưu dạng UTF-8.<?= $zipOk ? '' : ' Máy chủ chưa bật extension zip nên chưa đọc được .xlsx - hãy dùng CSV.' ?></span>
            </div>
            <div class="form-row">
                <label class="check-row"><input type="checkbox" name="create_classes" value="1"> Tự tạo lớp học nếu mã lớp trong file chưa có (tên lớp lấy theo mã, sửa lại sau ở trang Lớp học)</label>
            </div>
            <button class="btn" type="submit">Đọc file và xem trước</button>
        </div>
    </section>
</form>

<section class="panel">
    <h2 class="panel-title">Định dạng file</h2>
    <div class="panel-body">
        <p style="margin-top:0">Mỗi tài khoản một dòng, các cột: <strong>Tên đăng nhập</strong> | Mã sinh viên | <strong>Họ và tên</strong> | Email | Mật khẩu | Vai trò | Mã lớp | Ngày sinh</p>
        <ul class="rules">
            <li>Dòng đầu là tiêu đề thì cột được nhận theo tên (thứ tự tùy ý); không có tiêu đề thì theo thứ tự trên.</li>
            <li><strong>Vai trò</strong>: Sinh viên hoặc Giảng viên (để trống = Sinh viên). Quản trị viên phải tạo thủ công.</li>
            <li><strong>Mã lớp</strong>: chỉ dùng cho sinh viên, mỗi sinh viên một lớp.</li>
            <li><strong>Ngày sinh</strong> (không bắt buộc): ghi dạng <code>dd/mm/yyyy</code> (vd 15/08/2004) hoặc <code>yyyy-mm-dd</code>; ô kiểu Ngày của Excel cũng đọc được. Ngày không có thật hoặc ở tương lai bị báo lỗi.<?= \App\Models\User::hasDob() ? '' : ' <strong class="text-warn">Chưa nhập database/014_users_date_of_birth.sql nên ngày sinh chưa được lưu.</strong>' ?></li>
            <li><strong>Mật khẩu</strong>: để trống thì hệ thống tự sinh mật khẩu ngẫu nhiên (10 ký tự) và hiện <em>một lần</em> ở trang kết quả, kèm nút tải CSV để phát cho người dùng. Nếu tự đặt thì từ <?= \App\Models\UserImport::MIN_PASSWORD ?> ký tự.</li>
            <li>Chỉ <strong>tạo mới</strong>: tên đăng nhập hoặc email đã có trong hệ thống bị báo lỗi, không ghi đè tài khoản cũ.</li>
        </ul>
        <a class="btn btn-sm btn-outline" href="<?= url('/admin/users/import/template') ?>">Tải file Excel mẫu</a>
    </div>
</section>
