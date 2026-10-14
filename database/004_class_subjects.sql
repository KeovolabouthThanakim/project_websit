-- Nâng cấp: gán môn học cho lớp học (môn nào dạy cho lớp nào, do giáo viên nào giảng dạy).
-- KHÔNG xóa dữ liệu hiện có - chạy lại nhiều lần vẫn an toàn.
-- Yêu cầu đã nhập 002_classes.sql và 003_subjects.sql.
SET NAMES utf8mb4;

USE nau_quiz;

-- Một dòng = một môn được dạy cho một lớp. teacher_id là giáo viên giảng dạy môn đó ở lớp đó (có thể để trống).
CREATE TABLE IF NOT EXISTS class_subjects (
  class_id   INT      NOT NULL,
  subject_id INT      NOT NULL,
  teacher_id INT      NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (class_id, subject_id),
  INDEX idx_cs_subject (subject_id),
  INDEX idx_cs_teacher (teacher_id),
  CONSTRAINT fk_csub_class   FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE,
  CONSTRAINT fk_csub_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  CONSTRAINT fk_csub_teacher FOREIGN KEY (teacher_id) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- Dữ liệu mẫu (an toàn khi chạy lại): lớp CNTT-K60 học INF101 (gv001 dạy) và MAT101
INSERT IGNORE INTO class_subjects (class_id, subject_id, teacher_id)
  SELECT c.id, s.id, (SELECT id FROM users WHERE username = 'gv001')
  FROM classes c JOIN subjects s ON s.code = 'INF101' WHERE c.code = 'CNTT-K60';
INSERT IGNORE INTO class_subjects (class_id, subject_id)
  SELECT c.id, s.id FROM classes c JOIN subjects s ON s.code = 'MAT101' WHERE c.code = 'CNTT-K60';
