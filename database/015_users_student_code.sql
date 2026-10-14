-- Thêm cột MÃ SINH VIÊN (student_code) cho bảng users. Dành cho CSDL đã có dữ liệu (cài mới bằng schema.sql thì đã có sẵn cột này).
-- Chạy lại nhiều lần vẫn an toàn, không xóa dữ liệu.
-- Sinh viên cũ chưa có mã: tự điền bằng tên đăng nhập (trước đây tên đăng nhập chính là mã SV); sau đó quản trị viên có thể sửa lại.
-- (Nếu CSDL của bạn không tên nau_quiz thì sửa dòng USE bên dưới cho đúng tên.)
SET NAMES utf8mb4;
USE nau_quiz;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'student_code'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE users ADD COLUMN student_code VARCHAR(30) NULL AFTER username, ADD UNIQUE KEY uq_users_student_code (student_code)',
  'SELECT ''Cot student_code da ton tai'' AS thong_bao');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE users SET student_code = LEFT(username, 30)
WHERE role = 'student' AND (student_code IS NULL OR student_code = '');
