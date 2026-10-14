-- Chức năng quên mật khẩu: lưu mã đặt lại mật khẩu gửi qua email
-- Chạy lại nhiều lần vẫn an toàn, không xóa dữ liệu. Cần đã có bảng users.
SET NAMES utf8mb4;
USE nau_quiz;

CREATE TABLE IF NOT EXISTS password_resets (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  user_id    INT         NOT NULL,
  token_hash CHAR(64)    NOT NULL UNIQUE,               -- SHA-256 của mã gửi qua email (không lưu mã gốc)
  expires_at DATETIME    NOT NULL,
  created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pr_user (user_id),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
