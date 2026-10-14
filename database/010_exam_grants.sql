-- Nâng cấp: cấp quyền thi lại / thi muộn / thêm thời gian cho từng sinh viên.
-- KHÔNG xóa dữ liệu hiện có - chạy lại nhiều lần vẫn an toàn. Yêu cầu đã nhập 008_attempts.sql.
-- (Không có lệnh USE: nhập vào đúng CSDL đang chọn trong phpMyAdmin, dùng được cả trên hosting.)
SET NAMES utf8mb4;

-- Quyền riêng của một sinh viên với một kỳ thi: được vào thi dù cổng thi đã đóng (đến valid_until)
-- và/hoặc được cộng thêm extra_minutes vào thời gian làm bài. Mỗi sinh viên / kỳ thi tối đa 1 quyền đang có.
-- used_at: lúc sinh viên đã dùng quyền này để bắt đầu làm bài (NULL = chưa dùng).
CREATE TABLE IF NOT EXISTS exam_grants (
  id            INT PRIMARY KEY AUTO_INCREMENT,
  exam_id       INT          NOT NULL,
  user_id       INT          NOT NULL,
  extra_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  valid_until   DATETIME     NOT NULL,
  note          VARCHAR(255) NULL,
  granted_by    INT          NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  used_at       DATETIME     NULL,
  UNIQUE KEY uq_grant_exam_user (exam_id, user_id),
  INDEX idx_grant_user (user_id),
  CONSTRAINT fk_grant_exam FOREIGN KEY (exam_id)    REFERENCES exams(id) ON DELETE CASCADE,
  CONSTRAINT fk_grant_user FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_grant_by   FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Lịch sử các bài làm đã bị hủy để cho thi lại: giữ lại điểm cũ, người cấp quyền và lý do.
-- (Bài làm cũ - đáp án, nhật ký rời trang - bị xóa để sinh viên làm lại từ đầu; UNIQUE (exam_id, user_id) của
-- exam_attempts vẫn giữ nguyên nên mọi thống kê hiện có không bị ảnh hưởng.)
CREATE TABLE IF NOT EXISTS exam_attempt_history (
  id              INT PRIMARY KEY AUTO_INCREMENT,
  exam_id         INT          NOT NULL,
  user_id         INT          NOT NULL,
  started_at      DATETIME     NULL,
  submitted_at    DATETIME     NULL,
  status          ENUM('in_progress','submitted') NOT NULL,
  auto_submitted  BOOLEAN      NOT NULL DEFAULT FALSE,
  score           DECIMAL(4,2) NULL,
  total_questions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  note            VARCHAR(255) NULL,
  archived_by     INT          NULL,
  archived_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_hist_exam_user (exam_id, user_id),
  CONSTRAINT fk_hist_exam FOREIGN KEY (exam_id)     REFERENCES exams(id) ON DELETE CASCADE,
  CONSTRAINT fk_hist_user FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_hist_by   FOREIGN KEY (archived_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
