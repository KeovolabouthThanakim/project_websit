<section class="landing-hero">
    <h1><?= e(system_name()) ?></h1>
    <p><?= e(school_name()) ?>. Làm bài thi, chấm điểm tự động và xem kết quả ngay trên trình duyệt, kể cả điện thoại.</p>
    <div class="hero-actions">
        <a class="btn" href="<?= url('/login') ?>">Đăng nhập</a>
        <a class="btn btn-outline" href="<?= url('/help') ?>">Hướng dẫn sử dụng</a>
    </div>
</section>

<div class="cards3">
    <article class="panel feature">
        <h2>Sinh viên</h2>
        <p>Xem lịch thi của lớp, vào thi khi cổng thi mở, bài làm được tự lưu và tự nộp khi hết giờ. Điểm hiện ngay sau khi nộp.</p>
    </article>
    <article class="panel feature">
        <h2>Giảng viên</h2>
        <p>Xây dựng ngân hàng câu hỏi, tạo đề, theo dõi sinh viên đang làm bài và xem bảng điểm, thống kê từng câu.</p>
    </article>
    <article class="panel feature">
        <h2>Quản trị viên</h2>
        <p>Quản lý tài khoản, lớp, môn học, xem báo cáo tổng quan và nhật ký hoạt động của hệ thống.</p>
    </article>
</div>

<p class="muted center">Chưa có tài khoản? Tên đăng nhập là mã sinh viên / mã giảng viên do nhà trường cấp. Cần hỗ trợ, xem <a href="<?= url('/help') ?>">hướng dẫn sử dụng</a>.</p>
