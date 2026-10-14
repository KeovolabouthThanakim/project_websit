-- Nâng cấp: làm bài thi + chấm điểm. KHÔNG xóa dữ liệu hiện có - chạy lại nhiều lần vẫn an toàn.
-- Yêu cầu đã nhập 007_exams.sql.
SET NAMES utf8mb4;

USE nau_quiz;

-- Một lượt làm bài = một sinh viên làm một kỳ thi. Mỗi sinh viên chỉ được làm MỖI KỲ THI 1 LẦN (UNIQUE exam_id + user_id).
-- deadline_at = giờ bắt đầu + thời gian làm bài, do SERVER tính; giờ đóng cổng (exams.end_at) chỉ chặn việc vào thi.
-- score: thang điểm 10. earned: tổng điểm các câu (mỗi câu tối đa 1). total_questions: số câu của đề tại lúc bắt đầu.
-- auto_submitted = TRUE: hết giờ nên hệ thống tự nộp.
-- Kỳ thi đã có bài làm thì không xóa được (RESTRICT); tài khoản đã có bài làm không xóa được - chỉ khóa.
CREATE TABLE IF NOT EXISTS exam_attempts (
  id              INT PRIMARY KEY AUTO_INCREMENT,
  exam_id         INT          NOT NULL,
  user_id         INT          NOT NULL,
  started_at      DATETIME     NOT NULL,
  deadline_at     DATETIME     NOT NULL,
  submitted_at    DATETIME     NULL,
  status          ENUM('in_progress','submitted') NOT NULL DEFAULT 'in_progress',
  auto_submitted  BOOLEAN      NOT NULL DEFAULT FALSE,
  earned          DECIMAL(7,3) NULL,
  total_questions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  score           DECIMAL(4,2) NULL,
  UNIQUE KEY uq_attempt_exam_user (exam_id, user_id),
  INDEX idx_attempt_user (user_id),
  INDEX idx_attempt_status_deadline (status, deadline_at),
  CONSTRAINT fk_attempt_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE RESTRICT,
  CONSTRAINT fk_attempt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Đề của riêng từng sinh viên: thứ tự câu hỏi và thứ tự đáp án (đã xáo nếu exams.shuffle = TRUE) được CHỐT lúc bắt đầu
-- nên tải lại trang vẫn thấy đúng thứ tự cũ. option_order: danh sách id đáp án cách nhau dấu phẩy.
-- earned: điểm của câu (0 - 1), điền khi chấm.
CREATE TABLE IF NOT EXISTS exam_attempt_questions (
  attempt_id   INT          NOT NULL,
  question_id  INT          NOT NULL,
  position     SMALLINT UNSIGNED NOT NULL,
  option_order VARCHAR(100) NOT NULL,
  earned       DECIMAL(5,4) NULL,
  PRIMARY KEY (attempt_id, question_id),
  INDEX idx_aq_question (question_id),
  CONSTRAINT fk_aq_attempt  FOREIGN KEY (attempt_id)  REFERENCES exam_attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_aq_question FOREIGN KEY (question_id) REFERENCES questions(id)     ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Đáp án sinh viên đã chọn (câu nhiều đáp án có nhiều dòng). Tự lưu mỗi khi sinh viên đổi lựa chọn.
CREATE TABLE IF NOT EXISTS exam_answers (
  attempt_id  INT NOT NULL,
  question_id INT NOT NULL,
  option_id   INT NOT NULL,
  PRIMARY KEY (attempt_id, question_id, option_id),
  INDEX idx_ans_option (option_id),
  CONSTRAINT fk_ans_attempt  FOREIGN KEY (attempt_id)  REFERENCES exam_attempts(id)  ON DELETE CASCADE,
  CONSTRAINT fk_ans_option   FOREIGN KEY (option_id)   REFERENCES question_options(id) ON DELETE CASCADE
) ENGINE=InnoDB;
