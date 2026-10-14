-- Nâng cấp: đề thi / kỳ thi. KHÔNG xóa dữ liệu hiện có - chạy lại nhiều lần vẫn an toàn.
-- Yêu cầu đã nhập 002_classes.sql, 003_subjects.sql, 004_class_subjects.sql và 005_questions.sql.
SET NAMES utf8mb4;

USE nau_quiz;

-- Một kỳ thi = một đề thi của một môn, dành cho một hay nhiều lớp đang học môn đó.
-- status: draft = bản nháp (đang soạn), published = đã công bố cho sinh viên, closed = giáo viên đã đóng.
-- Công bố rồi thì "Sắp diễn ra / Đang mở / Đã kết thúc" được tính theo start_at, end_at.
-- Môn học đã có kỳ thi thì không xóa được (RESTRICT).
CREATE TABLE IF NOT EXISTS exams (
  id               INT PRIMARY KEY AUTO_INCREMENT,
  subject_id       INT          NOT NULL,
  created_by       INT          NULL,
  title            VARCHAR(200) NOT NULL,
  description      TEXT         NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL,              -- thời gian làm bài của mỗi sinh viên
  start_at         DATETIME     NOT NULL,                    -- mở cổng vào thi
  end_at           DATETIME     NOT NULL,                    -- đóng cổng vào thi
  shuffle          BOOLEAN      NOT NULL DEFAULT TRUE,       -- đảo thứ tự câu hỏi và đáp án cho từng sinh viên
  status           ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_exams_subject (subject_id),
  INDEX idx_exams_status (status),
  INDEX idx_exams_creator (created_by),
  CONSTRAINT fk_exams_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT,
  CONSTRAINT fk_exams_creator FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- Lớp được thi. Lớp đã có kỳ thi thì không xóa được (RESTRICT).
CREATE TABLE IF NOT EXISTS exam_classes (
  exam_id  INT NOT NULL,
  class_id INT NOT NULL,
  PRIMARY KEY (exam_id, class_id),
  INDEX idx_ec_class (class_id),
  CONSTRAINT fk_ec_exam  FOREIGN KEY (exam_id)  REFERENCES exams(id)   ON DELETE CASCADE,
  CONSTRAINT fk_ec_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Câu hỏi của đề thi. Câu hỏi đã nằm trong đề thi thì không xóa được (RESTRICT) - chỉ "Ẩn".
CREATE TABLE IF NOT EXISTS exam_questions (
  exam_id     INT NOT NULL,
  question_id INT NOT NULL,
  position    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (exam_id, question_id),
  INDEX idx_eq_question (question_id),
  CONSTRAINT fk_eq_exam     FOREIGN KEY (exam_id)     REFERENCES exams(id)     ON DELETE CASCADE,
  CONSTRAINT fk_eq_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Dữ liệu mẫu (chỉ thêm khi chưa có kỳ thi nào): 1 bản nháp cho INF101, lớp CNTT-K60
INSERT INTO exams (subject_id, created_by, title, description, duration_minutes, start_at, end_at, status)
  SELECT s.id, (SELECT id FROM users WHERE username = 'gv001'), 'Kiểm tra giữa kỳ - Tin học đại cương',
         'Bài kiểm tra trắc nghiệm giữa kỳ.', 30,
         DATE_ADD(CURDATE(), INTERVAL 7 DAY) + INTERVAL 8 HOUR, DATE_ADD(CURDATE(), INTERVAL 7 DAY) + INTERVAL 10 HOUR, 'draft'
  FROM subjects s WHERE s.code = 'INF101' AND NOT EXISTS (SELECT 1 FROM exams);

INSERT IGNORE INTO exam_classes (exam_id, class_id)
  SELECT e.id, c.id FROM exams e JOIN classes c ON c.code = 'CNTT-K60' WHERE e.title = 'Kiểm tra giữa kỳ - Tin học đại cương';

INSERT IGNORE INTO exam_questions (exam_id, question_id, position)
  SELECT e.id, q.id, q.id FROM exams e JOIN questions q ON q.subject_id = e.subject_id AND q.is_active = 1
  WHERE e.title = 'Kiểm tra giữa kỳ - Tin học đại cương';
