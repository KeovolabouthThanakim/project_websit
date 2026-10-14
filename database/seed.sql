SET NAMES utf8mb4;

USE nau_quiz;

-- Mật khẩu mẫu (băm bằng bcrypt):
--   admin   / admin123
--   gv001   / teacher123
--   sv001, sv002 / student123
-- ĐỔI MẬT KHẨU NGAY khi đưa lên môi trường thật.

INSERT INTO users (username, student_code, password_hash, full_name, email, role) VALUES
('admin', NULL, '$2b$10$cPEjG4gqX1VB0a8MQDh5wuWHzczW5wptfXKuNepjYHyhpXZkdbrZW', 'Quản trị viên',   'admin@nau.edu.vn', 'admin'),
('gv001', NULL, '$2b$10$/PtbyPElpoGC1b63DH25qO59kBrC1poCcCfjgaXmy/I.7gaJ7psPm', 'Nguyễn Văn Giảng', 'gv001@nau.edu.vn', 'teacher'),
('sv001', 'sv001', '$2b$10$OGEkGD9nduH5PFu6WjDrV.NFXruJQqR5B2BmAeOlCjgVxn0ah1QuW', 'Trần Thị Học',     'sv001@nau.edu.vn', 'student'),
('sv002', 'sv002', '$2b$10$OGEkGD9nduH5PFu6WjDrV.NFXruJQqR5B2BmAeOlCjgVxn0ah1QuW', 'Lê Văn Sinh',      'sv002@nau.edu.vn', 'student');

-- Dữ liệu mẫu (an toàn khi chạy lại): 2 lớp, lớp CNTT-K60 do gv001 chủ nhiệm, có sv001 và sv002
INSERT IGNORE INTO classes (code, name, cohort, teacher_id)
  SELECT 'CNTT-K60', 'Công nghệ thông tin K60', 'K60', id FROM users WHERE username = 'gv001';
INSERT IGNORE INTO classes (code, name, cohort) VALUES ('KT-K61', 'Kế toán K61', 'K61');

INSERT IGNORE INTO class_students (class_id, user_id, added_by)
  SELECT c.id, u.id, (SELECT id FROM users WHERE username = 'gv001')
  FROM classes c JOIN users u ON u.username IN ('sv001', 'sv002')
  WHERE c.code = 'CNTT-K60';