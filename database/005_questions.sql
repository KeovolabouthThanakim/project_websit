-- Nâng cấp: ngân hàng câu hỏi trắc nghiệm. KHÔNG xóa dữ liệu hiện có - chạy lại nhiều lần vẫn an toàn.
-- Yêu cầu đã nhập 003_subjects.sql (bảng subjects).
SET NAMES utf8mb4;

USE nau_quiz;

-- Câu hỏi thuộc một môn học. Môn đã có câu hỏi thì không xóa được (RESTRICT) - chỉ "Ngừng sử dụng".
-- is_active = FALSE nghĩa là câu hỏi đang ẩn: không được đưa vào đề thi mới, nhưng vẫn giữ dữ liệu.
CREATE TABLE IF NOT EXISTS questions (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  subject_id  INT          NOT NULL,
  created_by  INT          NULL,                             -- người soạn câu hỏi
  content     TEXT         NOT NULL,                         -- nội dung câu hỏi
  image       VARCHAR(80)  NULL,                             -- tên file ảnh trong uploads/questions/
  explanation TEXT         NULL,                             -- giải thích đáp án (hiện sau khi làm bài)
  difficulty  ENUM('easy','medium','hard') NOT NULL DEFAULT 'medium',
  is_active   BOOLEAN      NOT NULL DEFAULT TRUE,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_questions_subject (subject_id),
  INDEX idx_questions_creator (created_by),
  CONSTRAINT fk_q_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT,
  CONSTRAINT fk_q_creator FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- Các đáp án của câu hỏi (2-6 đáp án). Một đáp án đúng = câu một lựa chọn; nhiều đáp án đúng = câu nhiều lựa chọn.
CREATE TABLE IF NOT EXISTS question_options (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  question_id INT          NOT NULL,
  content     VARCHAR(500) NOT NULL,
  is_correct  BOOLEAN      NOT NULL DEFAULT FALSE,
  position    TINYINT UNSIGNED NOT NULL DEFAULT 0,           -- thứ tự hiển thị: 0 = A, 1 = B...
  INDEX idx_qo_question (question_id),
  CONSTRAINT fk_qo_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Dữ liệu mẫu (chỉ thêm khi bảng câu hỏi còn trống): 3 câu cho môn INF101
INSERT INTO questions (subject_id, created_by, content, explanation, difficulty)
  SELECT s.id, (SELECT id FROM users WHERE username = 'gv001'),
         'Thiết bị nào sau đây là thiết bị nhập (input) của máy tính?', 'Bàn phím dùng để đưa dữ liệu vào máy tính; màn hình và máy in là thiết bị xuất.', 'easy'
  FROM subjects s WHERE s.code = 'INF101' AND NOT EXISTS (SELECT 1 FROM questions);
INSERT INTO question_options (question_id, content, is_correct, position)
  SELECT q.id, o.content, o.is_correct, o.position FROM questions q
  JOIN (SELECT 'Màn hình' AS content, 0 AS is_correct, 0 AS position UNION ALL SELECT 'Bàn phím', 1, 1
        UNION ALL SELECT 'Máy in', 0, 2 UNION ALL SELECT 'Loa', 0, 3) o
  WHERE q.content LIKE 'Thiết bị nào sau đây là thiết bị nhập%'
    AND NOT EXISTS (SELECT 1 FROM question_options x WHERE x.question_id = q.id);

INSERT INTO questions (subject_id, created_by, content, explanation, difficulty)
  SELECT s.id, (SELECT id FROM users WHERE username = 'gv001'),
         'Phần mềm nào sau đây là hệ điều hành? (chọn tất cả đáp án đúng)', 'Windows và Linux là hệ điều hành; Word là phần mềm ứng dụng, Chrome là trình duyệt.', 'medium'
  FROM subjects s WHERE s.code = 'INF101'
    AND NOT EXISTS (SELECT 1 FROM questions WHERE content LIKE 'Phần mềm nào sau đây là hệ điều hành%');
INSERT INTO question_options (question_id, content, is_correct, position)
  SELECT q.id, o.content, o.is_correct, o.position FROM questions q
  JOIN (SELECT 'Windows' AS content, 1 AS is_correct, 0 AS position UNION ALL SELECT 'Microsoft Word', 0, 1
        UNION ALL SELECT 'Linux', 1, 2 UNION ALL SELECT 'Google Chrome', 0, 3) o
  WHERE q.content LIKE 'Phần mềm nào sau đây là hệ điều hành%'
    AND NOT EXISTS (SELECT 1 FROM question_options x WHERE x.question_id = q.id);

INSERT INTO questions (subject_id, created_by, content, explanation, difficulty)
  SELECT s.id, (SELECT id FROM users WHERE username = 'gv001'),
         '1 KB bằng bao nhiêu byte?', 'Theo cách tính nhị phân thông dụng, 1 KB = 2^10 = 1024 byte.', 'easy'
  FROM subjects s WHERE s.code = 'INF101'
    AND NOT EXISTS (SELECT 1 FROM questions WHERE content LIKE '1 KB bằng bao nhiêu byte%');
INSERT INTO question_options (question_id, content, is_correct, position)
  SELECT q.id, o.content, o.is_correct, o.position FROM questions q
  JOIN (SELECT '100 byte' AS content, 0 AS is_correct, 0 AS position UNION ALL SELECT '1000 byte', 0, 1
        UNION ALL SELECT '1024 byte', 1, 2 UNION ALL SELECT '2048 byte', 0, 3) o
  WHERE q.content LIKE '1 KB bằng bao nhiêu byte%'
    AND NOT EXISTS (SELECT 1 FROM question_options x WHERE x.question_id = q.id);
