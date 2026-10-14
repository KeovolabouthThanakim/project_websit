-- Cài đặt hệ thống chỉnh được trên giao diện (Quản trị viên > Cài đặt): tên trường, quy chế, điểm đạt, học kỳ...
-- Chạy lại nhiều lần vẫn an toàn, không xóa dữ liệu.
-- (Không có lệnh USE: nhập vào đúng CSDL đang chọn trong phpMyAdmin.)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  k          VARCHAR(60) PRIMARY KEY,
  v          TEXT        NULL,
  updated_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
