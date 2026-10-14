-- Nâng cấp: nhật ký hành vi trong lúc thi (chuyển tab, rời cửa sổ, sao chép nội dung...).
-- KHÔNG xóa dữ liệu hiện có - chạy lại nhiều lần vẫn an toàn. Yêu cầu đã nhập 008_attempts.sql.
-- Chưa nhập file này thì hệ thống vẫn chạy bình thường, chỉ là không ghi / không hiện nhật ký.
SET NAMES utf8mb4;

USE nau_quiz;

-- Mỗi dòng là một lần trình duyệt của sinh viên báo về một hành vi khác thường trong lúc làm bài.
--   hidden      = chuyển sang tab / ứng dụng khác hoặc thu nhỏ trình duyệt
--   blur        = cửa sổ làm bài mất tiêu điểm (bấm sang cửa sổ khác) nhưng trang vẫn hiển thị
--   copy        = sao chép nội dung trang
--   contextmenu = bấm chuột phải
-- created_at: lúc rời trang (giờ SERVER ghi nhận). returned_at: lúc quay lại, do SERVER ghi nhận khi trình duyệt báo
-- "đã quay lại"; NULL = chưa quay lại (hoặc đã đóng trình duyệt). Thời gian vắng mặt = returned_at - created_at.
-- Xóa bài làm thì nhật ký tự mất theo (CASCADE).
-- Lưu ý: đây là dữ liệu do trình duyệt báo về nên chỉ là DẤU HIỆU THAM KHẢO, không phải bằng chứng gian lận.
CREATE TABLE IF NOT EXISTS exam_events (
  id          BIGINT PRIMARY KEY AUTO_INCREMENT,
  attempt_id  INT NOT NULL,
  type        ENUM('hidden','blur','copy','contextmenu') NOT NULL,
  created_at  DATETIME NOT NULL,
  returned_at DATETIME NULL,
  INDEX idx_event_attempt (attempt_id, created_at),
  CONSTRAINT fk_event_attempt FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE
) ENGINE=InnoDB;
