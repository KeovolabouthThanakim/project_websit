-- Thông báo trong hệ thống (chuông thông báo): kỳ thi mới, được thi lại / vào thi muộn / cộng thêm giờ.
-- Chạy lại nhiều lần vẫn an toàn, không xóa dữ liệu. Cần đã có bảng users.
-- (Không có lệnh USE: nhập vào đúng CSDL đang chọn trong phpMyAdmin.)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  user_id    INT          NOT NULL,
  title      VARCHAR(160) NOT NULL,
  body       VARCHAR(500) NULL,
  url        VARCHAR(255) NULL,                 -- đường dẫn nội bộ, vd /my-exams/5
  is_read    BOOLEAN      NOT NULL DEFAULT FALSE,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notif_user (user_id, is_read, id),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
