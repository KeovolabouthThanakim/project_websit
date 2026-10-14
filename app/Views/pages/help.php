<?php
// Mở sẵn phần của vai trò hiện tại; chưa đăng nhập thì mở phần dành cho sinh viên
$open = fn(string $r): string => ($role === $r || ($role === '' && $r === 'student')) ? ' open' : '';
?>
<div class="page-head">
    <div>
        <h1>Hướng dẫn sử dụng</h1>
        <p>Cách làm bài thi, tạo đề và quản lý hệ thống thi trắc nghiệm</p>
    </div>
    <?php if ($role === ''): ?><a class="btn btn-outline" href="<?= url('/login') ?>">Đăng nhập</a><?php endif; ?>
</div>

<section class="panel help">
    <h2 class="panel-title">Dành cho sinh viên</h2>
    <div class="panel-body">
        <details<?= $open('student') ?>>
            <summary>Cách vào thi và làm bài</summary>
            <ol>
                <li>Đăng nhập bằng mã sinh viên, vào mục <strong>Kỳ thi của tôi</strong>. Chỉ thấy các kỳ thi đã được công bố cho <strong>lớp của bạn</strong>.</li>
                <li>Khi cổng thi mở, bấm <strong>Vào thi</strong> rồi <strong>Bắt đầu</strong>. Đồng hồ đếm ngược do máy chủ tính từ lúc bạn bấm Bắt đầu.</li>
                <li>Chọn đáp án cho từng câu. Bài được <strong>tự lưu</strong> mỗi khi bạn chọn; mất mạng thì hệ thống tự thử lại. Nút <strong>Cần kiểm tra lại</strong> chỉ để bạn tự đánh dấu, không ảnh hưởng điểm.</li>
                <li>Bấm <strong>Nộp bài</strong> khi xong. Hết giờ hệ thống tự nộp bài bạn đang làm.</li>
            </ol>
        </details>
        <details>
            <summary>Lưu ý quan trọng khi thi</summary>
            <ul>
                <li>Mỗi kỳ thi chỉ làm <strong>một lần</strong>. Nếu gặp sự cố, báo giảng viên để được cộng thêm thời gian hoặc cho thi lại.</li>
                <li>Đồng hồ <strong>không dừng</strong> khi bạn đóng trình duyệt hoặc mất mạng. Vào lại trang để làm tiếp, đáp án đã chọn vẫn còn.</li>
                <li>Giờ đóng cổng chỉ chặn việc <em>vào</em> thi. Nếu đã vào, bạn vẫn đủ thời gian làm bài.</li>
                <li>Một số kỳ thi ghi lại việc chuyển tab, rời cửa sổ hoặc sao chép nội dung để giảng viên tham khảo. Trang thi sẽ thông báo nếu có.</li>
                <li>Với câu có nhiều đáp án đúng, chọn thiếu hoặc chọn thừa có thể bị trừ điểm. Chọn bừa tất cả sẽ không được điểm.</li>
            </ul>
        </details>
        <details>
            <summary>Xem điểm và đáp án</summary>
            <p>Điểm hiện ngay sau khi nộp (thang 10). <strong>Đáp án đúng và giải thích chỉ hiện sau khi kỳ thi kết thúc</strong> để người nộp sớm không đưa đáp án cho bạn đang thi. Toàn bộ kết quả nằm ở <strong>Kết quả của tôi</strong>, có nút in.</p>
        </details>
    </div>
</section>

<section class="panel help">
    <h2 class="panel-title">Dành cho giảng viên</h2>
    <div class="panel-body">
        <details<?= $open('teacher') ?>>
            <summary>Tạo và công bố đề thi</summary>
            <ol>
                <li>Thêm câu hỏi vào <strong>Ngân hàng câu hỏi</strong> của môn mình phụ trách. Có thể nhập hàng loạt từ Excel / Word.</li>
                <li>Vào <strong>Đề thi</strong> &rarr; tạo đề: chọn môn, giờ mở và đóng cổng, thời gian làm bài, các lớp được thi.</li>
                <li>Chọn câu hỏi thủ công hoặc bốc ngẫu nhiên theo số câu dễ / trung bình / khó.</li>
                <li>Bấm <strong>Công bố</strong>. Chỉ công bố được khi đề có câu hỏi, có lớp thi và giờ đóng chưa qua. Có thể <strong>sao chép</strong> đề cũ thành bản nháp mới.</li>
            </ol>
        </details>
        <details>
            <summary>Theo dõi, chấm và xử lý sự cố</summary>
            <ul>
                <li><strong>Theo dõi trực tiếp</strong>: xem ai đã nộp, ai đang làm, ai chưa vào thi.</li>
                <li><strong>Kết quả</strong>: bảng điểm cả lớp, thống kê từng câu, xuất CSV, in bảng điểm.</li>
                <li><strong>Cộng thêm thời gian</strong>, <strong>cho thi lại</strong> hoặc <strong>cho vào thi muộn</strong> từng sinh viên ở cột cuối của bảng điểm.</li>
            </ul>
        </details>
    </div>
</section>

<section class="panel help">
    <h2 class="panel-title">Dành cho quản trị viên</h2>
    <div class="panel-body">
        <details<?= $open('admin') ?>>
            <summary>Các việc chính</summary>
            <ul>
                <li><strong>Người dùng</strong>: thêm, khóa, đặt lại mật khẩu, nhập hàng loạt từ Excel / CSV và xếp lớp ngay khi nhập.</li>
                <li><strong>Lớp học, Môn học</strong>: gán môn cho lớp, phân công giảng viên.</li>
                <li><strong>Báo cáo</strong>: gom kết quả theo môn, lớp, học kỳ và xuất CSV.</li>
                <li>Nên đổi mật khẩu các tài khoản mẫu và đặt <code>debug = false</code> trước khi dùng thật.</li>
            </ul>
        </details>
    </div>
</section>

<section class="panel help">
    <h2 class="panel-title">Câu hỏi thường gặp</h2>
    <div class="panel-body">
        <details>
            <summary>Tôi quên mật khẩu</summary>
            <p>Quản trị viên dùng <a href="<?= url('/forgot-password') ?>">Quên mật khẩu</a> để nhận liên kết đặt lại qua email. Giảng viên và sinh viên liên hệ quản trị viên để được đặt lại, sau đó đăng nhập và đổi mật khẩu ở trang <strong>Hồ sơ</strong> (bấm vào tên của bạn ở menu).</p>
        </details>
        <details>
            <summary>Tôi không thấy kỳ thi nào</summary>
            <p>Kiểm tra bạn đã được xếp vào lớp chưa (trang chủ có ghi lớp của bạn), và giảng viên đã <strong>công bố</strong> đề thi cho lớp đó chưa.</p>
        </details>
        <details>
            <summary>Đang thi thì mất mạng hoặc máy hỏng</summary>
            <p>Đăng nhập lại (bằng máy khác nếu cần) và vào đúng kỳ thi để làm tiếp. Nếu mất nhiều thời gian, báo giảng viên để được cộng thêm thời gian.</p>
        </details>
        <details>
            <summary>Tôi nộp bài rồi nhưng không thấy đáp án đúng</summary>
            <p>Đáp án chỉ mở sau khi kỳ thi kết thúc (hết giờ đóng cổng hoặc giảng viên đóng đề).</p>
        </details>
    </div>
</section>

<?php $rules = trim((string) setting('exam_rules', '')); $contact = trim((string) setting('contact_text', '')); ?>
<?php if ($rules !== ''): ?>
<section class="panel help">
    <h2 class="panel-title">Quy chế thi</h2>
    <div class="panel-body"><p><?= nl2br(e($rules)) ?></p></div>
</section>
<?php endif; ?>

<?php if ($contact !== ''): ?>
<section class="panel help">
    <h2 class="panel-title">Liên hệ hỗ trợ</h2>
    <div class="panel-body"><p><?= nl2br(e($contact)) ?></p></div>
</section>
<?php endif; ?>
