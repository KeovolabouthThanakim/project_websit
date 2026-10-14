# Hệ thống Quản lý và Tổ chức Thi Trắc nghiệm Trực tuyến - Đại học Nghệ An
PHP thuần + MVC tự viết (không dùng framework) + MySQL. Yêu cầu PHP >= 8.0.

## Cài đặt trên XAMPP
1. Chép thư mục `nau-quiz` vào `C:\xampp\htdocs\`
2. Bật **Apache** và **MySQL** trong XAMPP
3. Vào phpMyAdmin > tab **Nhập (Import)**: nhập `database/schema.sql`, sau đó nhập `database/seed.sql`
   (cài mới hoàn toàn; `schema.sql` xóa và tạo lại các bảng)
4. Nếu cần cấu hình riêng (ví dụ mật khẩu MySQL hoặc SMTP), sao chép `config/config.php` thành `config/config.local.php` rồi chỉnh sửa file mới này. Git sẽ không tải file cấu hình local lên.
5. Mở http://localhost/nau-quiz/

**Đã có dữ liệu người dùng và chỉ muốn thêm chức năng lớp học?** Chỉ nhập `database/002_classes.sql`
(không xóa dữ liệu hiện có, chạy lại nhiều lần vẫn an toàn). Không nhập `schema.sql`.

**Đã có lớp học và môn học, chỉ muốn thêm chức năng gán môn học cho lớp?** Nhập thêm `database/004_class_subjects.sql`
(cần đã có `002_classes.sql` và `003_subjects.sql`; không xóa dữ liệu, chạy lại nhiều lần vẫn an toàn).

**Đã có môn học, chỉ muốn thêm chức năng ngân hàng câu hỏi?** Nhập thêm `database/005_questions.sql`
(cần đã có `003_subjects.sql`; không xóa dữ liệu, chạy lại nhiều lần vẫn an toàn; có kèm 3 câu hỏi mẫu cho môn INF101).

**Đã có dữ liệu và chỉ muốn thêm chức năng quên mật khẩu qua email?** Nhập thêm `database/006_password_resets.sql`
(cần đã có bảng `users`; không xóa dữ liệu, chạy lại nhiều lần vẫn an toàn). Xem mục "Quên mật khẩu qua email" bên dưới để cấu hình gửi email.

**Đã có đề thi, chỉ muốn thêm chức năng làm bài và chấm điểm?** Nhập thêm `database/008_attempts.sql`
(cần đã có `007_exams.sql`; không xóa dữ liệu, chạy lại nhiều lần vẫn an toàn).

**Muốn cho thi lại / thi muộn / thêm thời gian từng sinh viên?** Nhập thêm `database/010_exam_grants.sql` (cần đã có `008_attempts.sql`). Chưa nhập thì các trang khác vẫn chạy, chỉ là chưa dùng được chức năng này.

**Muốn dùng thông báo trong hệ thống, cài đặt trên giao diện và nhật ký hoạt động?** Nhập thêm 3 file (mỗi file độc lập, không xóa dữ liệu, chạy lại nhiều lần vẫn an toàn; chưa nhập file nào thì chức năng tương ứng tự tắt, các trang khác vẫn chạy):
`database/011_notifications.sql` (chuông thông báo cho sinh viên), `database/012_settings.sql` (trang **Cài đặt**) và `database/013_audit_logs.sql` (trang **Nhật ký**). Trang **Sao lưu** và **Hồ sơ** không cần thêm bảng.

**Muốn lưu ngày sinh cho người dùng?** Nhập thêm `database/014_users_date_of_birth.sql` (thêm cột `date_of_birth` vào bảng `users`; không xóa dữ liệu, chạy lại nhiều lần vẫn an toàn; cài mới bằng `schema.sql` thì đã có sẵn cột). Chưa nhập thì hệ thống vẫn chạy bình thường, chỉ là ẩn ô ngày sinh. Sau đó nhập ngày sinh ở **Quản lý người dùng > Thêm / Sửa**, hoặc thêm cột **Ngày sinh** (dd/mm/yyyy) vào file Excel / CSV khi nhập hàng loạt.

**Muốn ghi nhật ký rời trang thi (chuyển tab, rời cửa sổ, sao chép nội dung)?** Nhập thêm `database/009_exam_events.sql`
(cần đã có `008_attempts.sql`; không xóa dữ liệu, chạy lại nhiều lần vẫn an toàn). Chưa nhập thì hệ thống vẫn chạy bình thường, chỉ là không ghi / không hiện nhật ký. Các trang mới của sinh viên (lịch thi, lịch sử kết quả) và các bản in **không cần** thêm bảng nào.

Nếu không vào được, mở http://localhost/nau-quiz/db-check để kiểm tra kết nối CSDL từng bước.

## Chức năng hiện có
- Đăng nhập / đăng xuất, phân quyền theo vai trò (quản trị viên, giảng viên, sinh viên)
- Trang chủ riêng cho từng vai trò (quản trị: thống kê tài khoản, tài khoản mới tạo)
- **Quản lý người dùng** (`/admin/users`, chỉ quản trị viên): tìm kiếm, lọc theo vai trò, phân trang,
  thêm, sửa, khóa / mở khóa, đặt lại mật khẩu, xóa
  - Không thể tự khóa, tự xóa hoặc tự đổi vai trò của chính mình
  - Tài khoản bị khóa không đăng nhập được; nếu đang đăng nhập sẽ bị đăng xuất ở lần truy cập kế tiếp
  - Xóa người dùng đã có dữ liệu liên quan sẽ bị chặn, nên dùng chức năng khóa

- **Quản lý lớp học** (`/classes`):
  - **Quản trị viên:** thêm / sửa / xóa lớp, phân công giáo viên chủ nhiệm (GVCN), thêm / bớt sinh viên ở mọi lớp
  - **Giáo viên:** chỉ thấy các lớp mình được phân công chủ nhiệm và được thêm / bớt sinh viên trong các lớp đó
    - Thêm sinh viên bằng 2 cách: tick chọn từ danh sách sinh viên chưa có lớp, hoặc dán danh sách mã sinh viên (mỗi mã một dòng)
    - Hệ thống báo rõ mã nào không tìm thấy, không phải sinh viên, đã có trong lớp hoặc đang thuộc lớp khác
  - Mỗi sinh viên chỉ thuộc 1 lớp. Không xóa được lớp còn sinh viên
  - Đổi vai trò một tài khoản sẽ tự dọn dữ liệu lớp (sinh viên -> rời lớp, giáo viên -> bỏ phân công chủ nhiệm)
  - Sinh viên xem được lớp và GVCN của mình ở trang chủ

- **Môn học của lớp** (mỗi lớp học những môn nào, ai giảng dạy):
  - **Quản trị viên:** gán / bỏ môn cho lớp ngay ở trang chi tiết **lớp** (`/classes/{id}`) hoặc trang chi tiết **môn** (`/subjects/{id}`), chọn kèm giáo viên giảng dạy (có thể để trống)
  - Danh sách môn học có cột **Lớp học**, danh sách lớp có cột **Số môn học**, nên nhìn là biết môn nào của lớp nào
  - **Giáo viên:** được chọn giảng dạy một môn sẽ tự thấy môn đó trong "Môn học của tôi"; trang môn học liệt kê các lớp đang học
  - **Sinh viên:** thấy danh sách môn học của lớp mình (kèm giáo viên giảng dạy) ở trang chủ
  - Chỉ gán được môn đang sử dụng; mỗi môn chỉ gán một lần cho một lớp. Xóa lớp hoặc môn thì các liên kết này tự mất

- **Ngân hàng câu hỏi** (`/questions`):
  - **Quản trị viên:** xem / thêm / sửa / ẩn / xóa câu hỏi của mọi môn
  - **Giáo viên:** chỉ làm việc với câu hỏi của các môn mình được phân công (phụ trách hoặc giảng dạy ở một lớp)
  - Câu hỏi trắc nghiệm 2-6 đáp án; một đáp án đúng = câu một lựa chọn, nhiều đáp án đúng = câu nhiều lựa chọn; có độ khó, giải thích đáp án và ảnh minh họa (JPG/PNG/GIF/WEBP, tối đa 2 MB, lưu trong `uploads/questions/`)
  - Tìm kiếm theo nội dung, lọc theo môn / độ khó / trạng thái, phân trang
  - **Ẩn** câu hỏi để không đưa vào đề thi mới nhưng vẫn giữ dữ liệu. Môn học đã có câu hỏi thì không xóa được, hãy chọn "Ngừng sử dụng"
  - Trang chi tiết môn học có nút vào thẳng ngân hàng câu hỏi của môn đó

## Quên mật khẩu qua email
- Ở trang đăng nhập bấm **Quên mật khẩu?** (`/forgot-password`), nhập tên đăng nhập hoặc email -> hệ thống gửi liên kết đặt lại mật khẩu tới email của tài khoản.
- Mặc định chỉ **quản trị viên** dùng được (giảng viên, sinh viên nhờ quản trị viên đặt lại). Muốn mở cho vai trò khác: sửa `password_reset.roles` trong `config/config.php`, ví dụ `['admin', 'teacher']`.
- Liên kết hiệu lực 30 phút (`password_reset.ttl_minutes`), chỉ dùng được một lần; yêu cầu mới sẽ hủy liên kết cũ; mỗi tài khoản chỉ được yêu cầu 1 lần/phút.
- Hệ thống chỉ lưu mã băm SHA-256 của mã đặt lại, và luôn báo "đã gửi" dù tài khoản có tồn tại hay không (không lộ tài khoản nào có trong hệ thống).
- Tài khoản phải có **email** thì mới nhận được liên kết (khai báo ở trang Quản lý người dùng).

**Cấu hình gửi email** (`mail` trong `config/config.local.php`):
| `driver` | Dùng khi | Ghi chú |
|---|---|---|
| `log` (mặc định) | Chạy thử trên XAMPP | **Không gửi email thật.** Nội dung được ghi vào `storage/mail/*.txt`, mở file lấy liên kết để thử |
| `smtp` | Chạy thật | Điền `host`, `port`, `encryption`, `username`, `password` trong `config.local.php` |
| `mail` | Hosting hỗ trợ hàm `mail()` | Không cần cấu hình thêm |

Gmail: `host` = `smtp.gmail.com`, `port` = 587, `encryption` = `tls`, `username` = địa chỉ Gmail, `password` = **Mật khẩu ứng dụng** (bật xác minh 2 bước rồi tạo tại myaccount.google.com/apppasswords), `from_email` = chính địa chỉ Gmail đó. Không lưu mật khẩu thật trong `config.php` hoặc commit vào Git.
Nếu XAMPP báo lỗi chứng chỉ SSL khi chạy thử, tạm đặt `verify_ssl` = `false` (không nên dùng khi chạy thật).
Khi đưa lên server thật, đặt `app_url` (vd `https://thi.nau.edu.vn`) để liên kết trong email luôn đúng địa chỉ.

- **Đề thi / kỳ thi** (menu **Đề thi**, quản trị viên và giáo viên):
  - **Tạo đề thi:** chọn môn, nhập tên, giờ mở - đóng cổng thi, thời gian làm bài (phút), chọn các lớp được thi
    (chỉ hiện lớp đang học môn đó; giáo viên chỉ chọn được lớp mình giảng dạy hoặc lớp chưa phân công giáo viên)
  - **Chọn câu hỏi:** tick chọn thủ công (có lọc độ khó) hoặc bốc ngẫu nhiên theo số câu dễ / trung bình / khó từ ngân hàng câu hỏi của môn
  - **Trạng thái:** Bản nháp -> Công bố -> (Sắp diễn ra / Đang mở / Đã kết thúc theo giờ) -> Đóng. Có thể mở lại hoặc chuyển về bản nháp
  - Chỉ công bố được khi đề có câu hỏi, có lớp thi, không có câu bị ẩn và giờ đóng chưa qua
  - Chỉ sửa câu hỏi và xóa được khi còn là **bản nháp**; lớp đang có đề thi thì không bỏ được môn khỏi lớp
  - Cần nhập `database/007_exams.sql` (sau 002 đến 005; không xóa dữ liệu, chạy lại nhiều lần vẫn an toàn)

- **Làm bài thi** (menu **Kỳ thi của tôi**, sinh viên):
  - Sinh viên chỉ thấy kỳ thi đã **công bố** cho **lớp của mình**; trang chủ nhắc kỳ thi đang mở / sắp diễn ra / đang làm dở
  - **Vào thi** khi cổng thi đang mở. Đồng hồ (`thời gian làm bài`) do **server** tính từ lúc bấm *Bắt đầu*, **không dừng** khi đóng trình duyệt; vào lại trang để làm tiếp. Giờ đóng cổng chỉ chặn việc *vào* thi, ai đã vào thì vẫn đủ thời gian làm bài
  - Trang làm bài: các câu hỏi trên một trang cuộn liền, **bảng điều khiển cố định bên phải** gồm số câu đã làm, đồng hồ đếm ngược (đỏ khi còn dưới 5 phút), lưới số câu để nhảy nhanh (xanh = đã làm, tím = cần kiểm tra lại), nút **Nộp bài**; nút **Lát kiểm tra lại** ở mỗi câu để tự đánh dấu (chỉ lưu trong trình duyệt, không ảnh hưởng điểm); **tự lưu** mỗi khi chọn (mất mạng thì tự thử lại). Trên điện thoại bảng này thu gọn ở đầu trang Hết giờ hệ thống **tự nộp**; sinh viên không quay lại thì bài vẫn được chấm khi có người mở danh sách kỳ thi (hoặc trang kết quả)
  - **Mỗi sinh viên chỉ làm mỗi kỳ thi 1 lần** (ràng buộc UNIQUE trong CSDL nên bấm hai lần cũng chỉ có một bài); giáo viên có thể cấp quyền thi lại / thi muộn riêng cho từng người (xem bên dưới)
  - **Xáo trộn:** nếu đề bật "Đảo câu hỏi", thứ tự câu và đáp án được xáo riêng cho từng sinh viên rồi **chốt lại** lúc bắt đầu (tải lại trang vẫn đúng thứ tự cũ)
  - **Chấm điểm** ngay khi nộp, thang 10 = tổng điểm các câu / số câu x 10:
    - Câu 1 đáp án đúng: đúng = 1 điểm, sai hoặc bỏ trống = 0
    - Câu nhiều đáp án đúng: mặc định **chấm từng phần** = `(số đáp án đúng đã chọn - số đáp án sai đã chọn) / số đáp án đúng`, thấp nhất 0 (chọn bừa tất cả không được điểm). Đổi `exam.partial_credit` thành `false` trong `config/config.php` để yêu cầu phải chọn đúng và đủ mới có điểm
  - **Kết quả:** điểm hiện ngay sau khi nộp. **Đáp án đúng + giải thích chỉ hiện sau khi kỳ thi kết thúc** (hết giờ đóng cổng hoặc giáo viên đóng đề) để người nộp sớm không đưa đáp án cho bạn đang thi
  - Kỳ thi đã có bài làm thì **không chuyển về bản nháp / xóa được** (giáo viên dùng "Đóng đề thi"); tài khoản đã có bài làm không xóa được, hãy khóa
  - Cần nhập `database/008_attempts.sql` (sau 007)

- **Kết quả & thống kê** (nút **Kết quả** ở danh sách đề thi / **Kết quả & thống kê** ở trang chi tiết đề; quản trị viên và giáo viên có quyền với môn đó, đề đã công bố trở lên):
  - **Bảng điểm cả lớp:** liệt kê mọi sinh viên của các lớp được thi, kể cả người **chưa vào thi** hoặc **đang làm**; hiện điểm, số câu đúng, thời gian làm, đánh dấu bài tự nộp do hết giờ. Lọc theo lớp, sắp xếp theo lớp / tên / điểm
  - **Thống kê:** số bài đã nộp, điểm trung bình, cao nhất / thấp nhất, trung vị, tỷ lệ đạt (từ 5 điểm, đổi ở `Result::PASS`), **biểu đồ phân bố điểm** (10 khoảng), điểm trung bình theo lớp
  - **Phân tích từng câu:** tỷ lệ làm đúng của từng câu; câu có điểm trung bình dưới 40% (từ 5 bài nộp trở lên) được đánh dấu "Cần xem lại" để phát hiện câu quá khó hoặc đáp án nhập sai
  - **Xuất CSV** (đúng theo lớp đang lọc, có BOM nên Excel đọc đúng tiếng Việt; điểm dùng dấu chấm thập phân, ô bắt đầu bằng `= + - @` được chặn công thức)
  - **Xem bài làm của từng sinh viên:** đáp án đã chọn, đáp án đúng, giải thích
  - Khi mở trang, bài hết giờ mà chưa nộp được tự nộp và chấm trước khi thống kê. Không cần nhập thêm file SQL

- **Trang chủ giảng viên:** thẻ thống kê nhanh + các bảng **Đang diễn ra** (tiến độ đã nộp / đang làm / chưa vào thi, nút **Theo dõi trực tiếp**), **Sắp diễn ra**, **Bản nháp chưa công bố** (kèm điều còn thiếu để công bố) và **Vừa kết thúc** (14 ngày, số sinh viên vắng, điểm TB). Bài trắc nghiệm được chấm tự động nên không có mục "chờ chấm". Không cần nhập thêm SQL.

- **Cho thi lại / thi muộn / thêm thời gian cho từng sinh viên** (ở **Kết quả** của đề thi, cột cuối của bảng điểm; giáo viên có môn đó và quản trị viên):
  - **Cộng thêm thời gian** cho bài *đang làm* (rớt mạng, sự cố máy): cộng vào hạn nộp hiện tại, đáp án đã chọn được giữ nguyên.
  - **Cho thi lại**: bài hiện tại (đã nộp hoặc đang làm dở) bị **xóa** cùng đáp án và nhật ký rời trang; điểm cũ, người cấp và lý do được lưu vào lịch sử. Sinh viên làm lại từ đầu (đề xáo lại), có thể kèm thời gian cộng thêm.
  - **Cho vào thi (thi muộn / thi bù)** cho sinh viên chưa có bài: vào được dù cổng thi chưa mở hoặc đã đóng, trong số giờ đã chọn. **Thu hồi** được khi sinh viên chưa dùng.
  - Đề thi phải đang ở trạng thái *đã công bố* (đề đã đóng thì mở lại trước). Ràng buộc "mỗi sinh viên 1 bài / kỳ thi" vẫn giữ nguyên nên mọi thống kê hiện có không đổi. Lưu ý: sau khi kỳ thi kết thúc, sinh viên khác có thể đã xem đáp án đúng.
  - Cần nhập `database/010_exam_grants.sql` (sau 008; không có lệnh `USE` nên nhập vào CSDL đang chọn trong phpMyAdmin; chạy lại nhiều lần vẫn an toàn).

- **Nhập câu hỏi từ Excel / Word** (nút **Nhập từ Excel / Word** ở Ngân hàng câu hỏi): chọn môn, tải file `.xlsx`, `.csv`, `.docx`, `.txt` hoặc dán nội dung -> **xem trước** (câu hợp lệ / lỗi / trùng, chưa ghi gì) -> xác nhận. Tối đa 300 câu / 2 MB mỗi lần; câu trùng nội dung với ngân hàng hoặc trong cùng file bị bỏ qua; mọi câu được kiểm tra theo đúng luật của form thêm câu hỏi và được nhập trong một giao dịch. Có file Excel mẫu và văn bản mẫu để tải. Đọc `.xlsx` / `.docx` cần extension `zip` của PHP (thường bật sẵn); không có thì dùng CSV (UTF-8) hoặc dán nội dung. Không cần nhập thêm SQL.
  - Excel / CSV: `Câu hỏi | A | B | C | D | E | F | Đáp án đúng | Độ khó | Giải thích` (có dòng tiêu đề thì cột xếp thứ tự tùy ý). Đáp án đúng ghi chữ cái, nhiều đáp án: `A, C`.
  - Word / văn bản: `Câu 1: ...`, các dòng `A.` `B.` ..., đáp án đúng bằng dòng `Đáp án: B` hoặc dấu `*` trước đáp án. Hãy gõ tay nhãn A, B, C, không dùng danh sách tự đánh số của Word.

- **Sao chép đề thi** (nút ở trang chi tiết đề thi): tạo **bản nháp** mới cùng môn, mô tả, thời lượng, lớp (chỉ lớp bạn được phép chọn) và các câu hỏi đang hiện; không chép kết quả. Giờ thi tự dời tiến theo bội số 7 ngày tới tương lai - nhớ kiểm tra lại ở **Sửa thông tin** trước khi công bố.

- **Thống kê môn học qua nhiều kỳ thi** (`/subjects/{id}/analytics`; nút **Thống kê** ở trang chủ giảng viên, **Thống kê các kỳ thi** ở trang môn học, **Thống kê cả môn** ở trang Kết quả): biểu đồ điểm trung bình qua các kỳ, bảng từng kỳ (số bài, TB, trung vị, cao / thấp, tỷ lệ đạt), **câu hay làm sai nhất** gộp mọi kỳ thi (từ 5 lượt làm trở lên) kèm đáp án sai được chọn nhiều nhất. Lọc theo lớp. Không cần nhập thêm SQL.

- **Nhập người dùng từ Excel / CSV** (quản trị viên, `/admin/users/import`): tạo hàng loạt tài khoản sinh viên / giảng viên và **xếp lớp ngay lúc nhập** (cột `Mã lớp`; tùy chọn tự tạo lớp chưa có). Cột: `Tên đăng nhập | Họ và tên | Email | Mật khẩu | Vai trò | Mã lớp` (có dòng tiêu đề thì cột xếp tùy ý). Xem trước từng dòng (lỗi / trùng được nêu rõ) rồi mới xác nhận; toàn bộ được ghi trong một giao dịch. Mật khẩu để trống thì hệ thống tự sinh (10 ký tự) và hiện **một lần** ở trang kết quả kèm nút tải CSV để phát cho người dùng (giữ tạm trong session đến khi tải CSV, không lưu dạng rõ trong CSDL). Chỉ tạo mới - tên đăng nhập / email đã có bị báo lỗi, không ghi đè; không tạo được quản trị viên qua đường này. Tối đa 300 dòng / 2 MB; không cần nhập thêm SQL.

- **Tổng quan thi cử trên trang chủ quản trị:** kỳ thi đang diễn ra (tiến độ nộp, nút Theo dõi trực tiếp), danh sách sinh viên **đang làm bài ngay bây giờ** (đã làm bao nhiêu câu, còn bao lâu), kỳ thi sắp diễn ra và số bản nháp.

- **Báo cáo tổng quan** (quản trị viên, mục **Báo cáo** ở menu, `/admin/reports`): gom kết quả thi theo **môn học / lớp / học kỳ**, lọc chéo theo học kỳ - môn - lớp, kèm số kỳ thi, tỷ lệ tham gia, điểm trung bình, tỷ lệ đạt và danh sách từng kỳ thi; có **Xuất Excel (.xlsx)** (file Excel thật: số là số, có định dạng %, cố định dòng tiêu đề) và **Xuất CSV** dự phòng. Điểm TB và tỷ lệ đạt tính trên toàn bộ bài nộp của nhóm. Chỉ tính bài đã nộp của sinh viên hiện thuộc lớp được thi.
  - **Học kỳ không lưu trong CSDL** mà suy ra từ ngày mở cổng thi (mặc định: HK1 từ tháng 8, HK2 từ tháng 2, HK hè từ tháng 6; tháng 1 thuộc HK1 của năm học trước). Muốn đổi, thêm vào `config/config.php`: `'semesters' => ['HK1' => 9, 'HK2' => 2, 'HK hè' => 6],` (tên => tháng bắt đầu; mục đầu tiên là học kỳ mở đầu năm học).

## Hồ sơ, hướng dẫn, thông báo, cài đặt, nhật ký, sao lưu
- **Hồ sơ** (`/profile`, bấm vào tên ở menu): mọi vai trò tự đổi email và đổi mật khẩu (phải nhập mật khẩu hiện tại; sai nhiều lần sẽ tạm khóa).
- **Trang giới thiệu** (`/`, khi chưa đăng nhập) và **Hướng dẫn sử dụng** (`/help`, công khai): hướng dẫn theo vai trò, câu hỏi thường gặp, quy chế thi và thông tin liên hệ (hai mục sau do quản trị viên nhập ở Cài đặt).
- **Thông báo** (`/notifications`): khi công bố kỳ thi, hoặc khi giảng viên cho thi lại / vào thi muộn / cộng thêm giờ, sinh viên có thông báo trong hệ thống (chấm đỏ trên menu). Email báo kỳ thi mới vẫn gửi như trước.
- **Cài đặt** (`/admin/settings`, quản trị viên): tên trường, logo (PNG), điểm đạt, học kỳ, chấm từng phần, quy chế, liên hệ. Giá trị lưu trong CSDL và **đè lên** `config.php`. Mật khẩu SMTP, kết nối CSDL, bảo mật vẫn đặt trong `config/config.local.php`.
- **Nhật ký** (`/admin/audit`): ghi đăng nhập (kể cả sai / bị khóa), thao tác người dùng, kỳ thi, cấp quyền, cài đặt, sao lưu; lọc theo nhóm, ngày, từ khóa. Tự xóa dòng cũ hơn 365 ngày (đổi bằng `audit.retention_days` trong config).
- **Sao lưu** (`/admin/backup`, quản trị viên): tải file `.sql` toàn bộ dữ liệu (không cần mysqldump), lưu bản trên máy chủ (`storage/backups`), tải ảnh câu hỏi dạng ZIP. **Khôi phục** ghi đè dữ liệu hiện tại nên phải gõ chữ xác nhận + nhập lại mật khẩu; hệ thống chỉ nhận file do chính nó tạo ra, tự sao lưu bản hiện tại trước, và tự trả lại nếu khôi phục lỗi giữa chừng. File sao lưu chứa dữ liệu cá nhân, hãy cất kỹ.

## Đổi logo trường
Chép file logo vào `public/assets/img/logo.png` (nên là ảnh vuông, nền trong suốt). Nếu có file này,
hệ thống tự dùng thay cho ô chữ "NAU" ở đầu trang.

## Tài khoản mẫu
| Tên đăng nhập | Mật khẩu   | Vai trò  |
|---------------|------------|----------|
| admin         | admin123   | Quản trị |
| gv001         | teacher123 | Giảng viên |
| sv001, sv002  | student123 | Sinh viên |

Đổi mật khẩu và đặt `debug => false` trong `config/config.php` khi đưa lên môi trường thật.

## Cấu trúc & luồng xử lý
```
public/index.php  ->  App::run()  ->  Router  ->  Middleware  ->  Controller  ->  Model / View
```
| Thư mục | Vai trò |
|---|---|
| `public/` | Thư mục duy nhất truy cập được từ web: `index.php`, css/js/ảnh |
| `routes/web.php` | Khai báo đường dẫn -> Controller, kèm middleware |
| `app/Core/` | Lõi MVC: App, Router, Middleware, Request, Response, Controller, Model, View, Session, Database, Auth |
| `app/Controllers/` | Xử lý request, gọi Model, trả View |
| `app/Models/` | Làm việc với bảng dữ liệu (PDO, prepared statement) |
| `app/Views/` | Giao diện HTML (`layouts/main.php` là khung chung) |
| `config/` | Cấu hình |
| `database/` | `schema.sql`, `seed.sql` |
| `uploads/` | Ảnh câu hỏi (sinh viên xem qua `/attempts/{id}/images/...`, chỉ ảnh của bài mình) |
| `storage/mail/` | Email thử nghiệm khi `mail.driver = log` (không truy cập được từ web) |

## Thêm một chức năng mới (ví dụ danh sách môn học)
1. **Bảng**: thêm vào `database/schema.sql`
2. **Model**: `app/Models/Subject.php`
   ```php
   class Subject extends \App\Core\Model {
       protected static string $table = 'subjects';
       protected static array $fillable = ['code', 'name'];
   }
   ```
3. **Controller**: `app/Controllers/SubjectController.php` với phương thức `index()` gọi `Subject::all()` rồi `$this->view('subjects/index', [...])`
4. **View**: `app/Views/subjects/index.php`
5. **Route**: `routes/web.php`
   ```php
   $router->get('/teacher/subjects', [SubjectController::class, 'index'], ['auth', 'role:teacher,admin']);
   ```

## Bảo mật đã có sẵn
- Mật khẩu băm bằng `password_hash` / `password_verify`
- CSRF token tự động kiểm tra cho mọi request POST (dùng `<?= csrf_field() ?>` trong form)
- Prepared statement chống SQL injection; hàm `e()` chống XSS
- Đổi session id sau khi đăng nhập/đăng xuất, cookie `HttpOnly` + `SameSite=Lax`
- Thư mục `app/`, `config/`, `database/` không truy cập trực tiếp được từ trình duyệt

## Lộ trình tiếp theo
~~Tạo đề thi~~ (đã xong) -> ~~Làm bài + chấm điểm~~ (đã xong) -> ~~Kết quả, thống kê cho giáo viên~~ (đã xong). Ý tưởng tiếp theo: thống kê điểm theo nhiều kỳ thi / theo môn, cho phép giáo viên cấp lượt thi lại, nhập điểm ngoài hệ thống

Lưu ý: sửa nội dung / đáp án đúng của một câu hỏi **sau khi** đã có sinh viên làm bài sẽ không đổi điểm đã chấm, nhưng trang xem đáp án của sinh viên hiển thị theo đáp án hiện tại của câu hỏi.
## Nếu web chạy chậm sau khi đưa lên tên miền
- Bản này đã: nén + cache file tĩnh (`public/.htaccess`, cần Apache bật `mod_deflate`, `mod_headers`), gắn `?v=` vào css để cache lâu mà vẫn cập nhật, tải font Google không chặn việc hiển thị trang, và nhả khóa session ở endpoint tự lưu đáp án để các request của cùng một sinh viên không xếp hàng.
- Kiểm tra phía máy chủ: bật **OPcache** (`opcache.enable=1`), dùng **PHP 8.1+**, `db.host` nên là `127.0.0.1` (hoặc `localhost`) khi MySQL cùng máy, không dùng tên miền; gói hosting quá yếu (ít CPU/RAM) sẽ chậm khi cả lớp cùng đăng nhập vì mã hóa mật khẩu (bcrypt) tốn CPU.
- Nếu dùng Nginx thay vì Apache, `.htaccess` không có tác dụng: cần bật `gzip on;` và `expires` cho `/assets/` trong cấu hình Nginx.
- Đo thử: mở DevTools (F12) -> Network. Thời gian "Waiting for server response" (TTFB) lớn nghĩa là máy chủ/PHP/MySQL chậm; TTFB nhỏ nhưng tải lâu nghĩa là mạng hoặc file lớn.

## Giáo viên xem lớp
- Trang **Lớp của tôi** hiện đủ các lớp giáo viên chủ nhiệm, giảng dạy một môn, hoặc học môn mình phụ trách; có thẻ thống kê **tổng số lớp** và **tổng số sinh viên** (toàn bộ, không phụ thuộc ô tìm kiếm hay trang).
- Lớp chủ nhiệm: xem + thêm/bớt sinh viên. Lớp chỉ giảng dạy: **chỉ xem** danh sách và sĩ số (không có nút thêm/bớt, và máy chủ cũng từ chối nếu gửi trực tiếp).
- Quản trị viên vẫn toàn quyền.

## Lịch thi, lịch sử kết quả và in bảng điểm
**Sinh viên** (menu: *Lịch thi*, *Kết quả của tôi*):
- **Lịch thi** (`/my-schedule`): lịch tháng (chuyển tháng bằng nút « »), mỗi kỳ thi hiện ở ngày mở cổng, cổng mở nhiều ngày thì các ngày sau hiện nhạt; banner kỳ thi cần chú ý nhất; danh sách "Việc cần làm" có **đếm ngược** (còn bao lâu đến lúc mở cổng / đóng cổng / hết giờ làm bài); trạng thái từng kỳ thi gồm đang làm dở, đang mở, sắp diễn ra, đã nộp, **đã bỏ lỡ**. Đếm ngược dùng giờ máy chủ nên không bị lệch khi đồng hồ máy sinh viên sai; về 0 thì trang tự tải lại.
- **Lịch sử kết quả** (`/my-results`): mọi bài đã nộp, **nhóm theo môn**; mỗi môn có điểm trung bình, cao nhất, gần nhất, số bài đạt, biểu đồ xu hướng và bảng chi tiết (kèm ▲▼ so với bài thi trước cùng môn); lọc theo môn bằng `?subject=MÃ_MÔN`. Điểm trung bình lớp áp dụng đúng quy tắc cũ: chỉ hiện khi kỳ thi đã kết thúc và có từ 3 bài nộp.

**In / lưu PDF** (dùng chức năng in của trình duyệt, chọn máy in *"Lưu dạng PDF"*; không cần thư viện PDF nào):
- Sinh viên: **phiếu kết quả một bài thi** (`/attempts/{id}/print`, chỉ có điểm và thông tin bài thi, *không có đáp án*) và **bảng điểm cá nhân** (`/my-results/print`, lọc được theo môn).
- Giáo viên / quản trị viên: **bảng điểm lớp** (`/exams/{id}/results/print`, theo lớp đang lọc, có chỗ ký tên). Quyền giống trang kết quả.
- Điểm trung bình trên bảng điểm cá nhân là trung bình cộng các bài thi, **không phải điểm học phần**.

## Nhật ký hành vi trong lúc thi
- Khi sinh viên đang làm bài, trình duyệt báo về máy chủ các hành vi: **chuyển tab / thu nhỏ trình duyệt**, **rời khỏi cửa sổ thi** (mất tiêu điểm quá 1,5 giây), **sao chép nội dung**, **bấm chuột phải**. Mọi mốc giờ và thời gian vắng mặt do **máy chủ** tính, không tin giờ của trình duyệt. Tải lại trang thi không bị tính là rời trang.
- Sinh viên được **thông báo trước** (ở trang chi tiết kỳ thi và ngay trên trang làm bài) và thấy cảnh báo khi quay lại. Hệ thống **không chặn** thao tác nào và **không tự trừ điểm**.
- Giáo viên xem ở: trang **Theo dõi trực tiếp** (nhãn ⚠ dưới trạng thái, thẻ thống kê và tab *Có cảnh báo*), trang **Kết quả** (nhãn ⚠ trong bảng điểm), trang **xem bài làm từng sinh viên** (nhật ký chi tiết theo thời gian) và file **CSV** (3 cột thêm). Từ 3 lần rời trang trở lên được tô đỏ (`AttemptEvent::WARN_LEAVES`).
- Tắt hoàn toàn: `exam.track_events => false` trong `config.php`.
- **Giới hạn cần biết:** dữ liệu do trình duyệt của sinh viên báo về nên chỉ là **dấu hiệu tham khảo**. Có thể là mất mạng, thông báo hệ thống hoặc thao tác vô ý; ngược lại sinh viên có thể né tránh (tắt JavaScript, dùng điện thoại / thiết bị thứ hai). Không nên dùng làm căn cứ duy nhất để kết luận gian lận. Muốn giám sát chặt hơn cần phần mềm khóa trình duyệt hoặc giám thị phòng thi.

## Theo dõi phòng thi trực tiếp
- Vào từ nút **Theo dõi** (danh sách đề thi) hoặc **Theo dõi trực tiếp** (chi tiết đề / trang kết quả). Quyền giống trang kết quả: quản trị viên mọi đề, giáo viên chỉ đề của môn mình; đề nháp chưa có.
- Hiện số **đang làm bài** (đã vào phòng thi, chưa nộp), **chưa vào thi**, **đã nộp** và tổng số; bảng chi tiết từng sinh viên gồm tiến độ (số câu đã chọn đáp án), thời gian còn lại đếm ngược, giờ vào, giờ nộp, điểm. Lọc theo lớp, theo nhóm trạng thái, tìm theo mã / tên.
- Trang tự cập nhật mỗi 10 giây (dừng khi chuyển tab trình duyệt, có nút Tạm dừng). Bài hết giờ được tự nộp khi trang cập nhật.
- Lưu ý: "đang làm bài" dựa trên trạng thái bài làm, chưa phát hiện được sinh viên đã đóng trình duyệt nhưng chưa nộp (đồng hồ vẫn chạy cho đến hết giờ).

## Bảo mật
Đã có sẵn: mật khẩu băm bcrypt; CSRF token cho mọi form POST; câu truy vấn dùng prepared statements; phân quyền theo vai trò + theo môn / lớp; cookie phiên `HttpOnly` + `SameSite`; thông báo đăng nhập sai không lộ tài khoản có tồn tại hay không; mã đặt lại mật khẩu chỉ lưu dạng băm, hết hạn sau 30 phút.

Bổ sung ở bản này:
- **Chống dò mật khẩu:** sai 5 lần trong 15 phút (theo tên đăng nhập + IP; hoặc 30 lần theo IP) thì tạm khóa đăng nhập. Chỉnh trong `config.php` -> `security`. Dữ liệu đếm lưu ở `storage/throttle/` (cần ghi được).
- **Trình duyệt tự điền mật khẩu:** form đăng nhập đã yêu cầu trình duyệt không tự điền (`security.block_autofill`). Trình duyệt có quyền bỏ qua; trên máy dùng chung (phòng máy) nên tắt "Lưu mật khẩu" / dùng hồ sơ khách và nhớ bấm Đăng xuất.
- **Phiên làm việc:** tự đăng xuất sau 120 phút không hoạt động; chặn session id do bên ngoài đặt; cookie có cờ `Secure` khi chạy HTTPS (kể cả sau proxy).
- **Header bảo mật:** `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, và HSTS khi dùng HTTPS.
- **Chặn truy cập trực tiếp** vào `app/`, `config/`, `database/`, `storage/`, `uploads/`... bằng `.htaccess` (Apache). Nginx cần tự chặn các thư mục này trong cấu hình.
- **Cảnh báo cho quản trị viên** ở trang chủ quản trị khi chạy trên tên miền thật mà còn: bật debug, dùng MySQL root không mật khẩu, chưa dùng HTTPS, chưa đặt `app_url`, hoặc còn mật khẩu mặc định của tài khoản mẫu.

Việc cần làm khi đưa lên tên miền thật: đổi mật khẩu 4 tài khoản mẫu; `debug => false`; tạo user MySQL riêng có mật khẩu; bật HTTPS; đặt `app_url`; để `mail.driver` là `smtp` hoặc `mail` (chế độ `log` ghi liên kết đặt lại mật khẩu ra file trong `storage/mail/`); sao lưu CSDL định kỳ.
