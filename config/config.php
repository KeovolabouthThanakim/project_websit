<?php
// Cấu hình ứng dụng - mặc định cho XAMPP/WAMP (root, không mật khẩu)
return [
    'app_name' => 'NAU Quiz',
    'timezone' => 'Asia/Ho_Chi_Minh',

    // null = tự nhận diện. Chỉ đặt tay khi tự nhận diện sai, ví dụ '/nau-quiz'
    'base_url' => null,

    // true khi phát triển (hiện lỗi chi tiết). PHẢI để false khi đưa lên server thật
    'debug' => false,

    // Địa chỉ gốc dùng trong liên kết gửi qua email, vd 'https://thi.nau.edu.vn/nau-quiz'.
    // null = tự lấy từ trình duyệt (chỉ nên dùng khi chạy thử trên máy cá nhân)
    'app_url' => 'https://nau.kesug.com',

    // Quên mật khẩu: vai trò được phép dùng và thời hạn của liên kết (phút)
    'password_reset' => [
        'roles'       => ['admin'],
        'ttl_minutes' => 30,
    ],

    // Bảo mật
    'security' => [
        // true = form đăng nhập yêu cầu trình duyệt KHÔNG tự điền tên đăng nhập / mật khẩu đã lưu (hợp với phòng máy dùng chung).
        // Lưu ý: trình duyệt có quyền bỏ qua yêu cầu này; cách chắc nhất là tắt "lưu mật khẩu" trên máy dùng chung.
        'block_autofill'        => true,
        // Chống dò mật khẩu: sai quá số lần cho phép trong khoảng thời gian thì tạm khóa đăng nhập
        'login_max_attempts'    => 5,    // mỗi cặp (tên đăng nhập + địa chỉ IP)
        'login_ip_max_attempts' => 30,   // mỗi địa chỉ IP (mọi tên đăng nhập)
        'login_window_minutes'  => 15,
        // Tự đăng xuất khi không hoạt động quá số phút này (đang thi mà còn lưu đáp án thì vẫn tính là hoạt động)
        'session_idle_minutes'  => 120,
    ],

    // Làm bài thi
    'exam' => [
        // Câu có NHIỀU đáp án đúng: true = chấm từng phần (mỗi đáp án đúng được chọn +, mỗi đáp án sai bị chọn -, thấp nhất 0 điểm);
        //                           false = phải chọn đúng và đủ mới có điểm. Câu 1 đáp án đúng luôn là đúng/sai.
        'partial_credit' => true,
        // Số giây ân hạn khi nhận bài nộp / lưu đáp án sau giờ hết hạn (bù độ trễ mạng)
        'submit_grace_seconds' => 10,
        // Ghi nhật ký hành vi khi sinh viên đang làm bài (chuyển tab, rời cửa sổ, sao chép nội dung) để giáo viên tham khảo.
        // Cần nhập database/009_exam_events.sql. Sinh viên được thông báo rõ trên trang thi. Đặt false để tắt hoàn toàn.
        'track_events' => true,
    ],


    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3307,
        'name'     => 'nau_quiz',
        'user'     => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],
];