-- Nâng cấp: thêm chức năng lớp học. KHÔNG xóa dữ liệu hiện có - chạy được trên CSDL đang dùng.
SET NAMES utf8mb4;

USE nau_quiz;

-- Lớp học: mỗi lớp có thể có 1 giáo viên chủ nhiệm (do quản trị viên phân công)
CREATE TABLE IF NOT EXISTS classes (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  code       VARCHAR(30)  NOT NULL UNIQUE,              -- mã lớp, vd CNTT-K60
  name       VARCHAR(150) NOT NULL,
  cohort     VARCHAR(50)  NULL,                         -- khóa / niên khóa, vd K60
  teacher_id INT          NULL,                         -- giáo viên chủ nhiệm
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_classes_teacher (teacher_id),
  CONSTRAINT fk_classes_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Sinh viên thuộc lớp. UNIQUE(user_id): mỗi sinh viên chỉ thuộc 1 lớp.
-- Không cho xóa lớp còn sinh viên (RESTRICT); xóa tài khoản sinh viên thì tự rời lớp (CASCADE).
CREATE TABLE IF NOT EXISTS class_students (
  class_id   INT      NOT NULL,
  user_id    INT      NOT NULL,
  added_by   INT      NULL,                             -- người thêm (quản trị viên / giáo viên)
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (class_id, user_id),
  UNIQUE KEY uq_class_students_user (user_id),
  CONSTRAINT fk_cs_class    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cs_user     FOREIGN KEY (user_id)  REFERENCES users(id)   ON DELETE CASCADE,
  CONSTRAINT fk_cs_added_by FOREIGN KEY (added_by) REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB;

-- Dữ liệu mẫu (an toàn khi chạy lại): 2 lớp, lớp CNTT-K60 do gv001 chủ nhiệm, có sv001 và sv002
INSERT IGNORE INTO classes (code, name, cohort, teacher_id)
  SELECT 'CNTT-K60', 'Công nghệ thông tin K60', 'K60', id FROM users WHERE username = 'gv001';
INSERT IGNORE INTO classes (code, name, cohort) VALUES ('KT-K61', 'Kế toán K61', 'K61');

INSERT IGNORE INTO class_students (class_id, user_id, added_by)
  SELECT c.id, u.id, (SELECT id FROM users WHERE username = 'gv001')
  FROM classes c JOIN users u ON u.username IN ('sv001', 'sv002')
  WHERE c.code = 'CNTT-K60';