-- Thêm cột NGÀY SINH cho bảng users. Dành cho CSDL đã có dữ liệu (cài mới bằng schema.sql thì đã có sẵn cột này).
-- Chạy lại nhiều lần vẫn an toàn, không xóa dữ liệu. Tài khoản cũ có ngày sinh trống (NULL) cho đến khi được cập nhật.
-- (Không có lệnh USE: nhập vào đúng CSDL đang chọn trong phpMyAdmin.)
SET NAMES utf8mb4;

SET @has_dob := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'date_of_birth'
);
SET @sql := IF(@has_dob = 0,
  'ALTER TABLE users ADD COLUMN date_of_birth DATE NULL AFTER email',
  'SELECT ''Cot date_of_birth da ton tai'' AS thong_bao');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
