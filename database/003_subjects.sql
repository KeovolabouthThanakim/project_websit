-- Nâng cấp: thêm chức năng môn học. KHÔNG xóa dữ liệu hiện có - chạy được trên CSDL đang dùng.
-- Yêu cầu đã có bảng users (nếu chưa có lớp học cũng không sao, 2 chức năng độc lập).
SET NAMES utf8mb4;

USE nau_quiz;

-- Môn học. is_active = FALSE nghĩa là ngừng sử dụng (ẩn khỏi các màn hình chọn môn sau này, không xóa dữ liệu)
CREATE TABLE IF NOT EXISTS subjects (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  code        VARCHAR(30)  NOT NULL UNIQUE,             -- mã môn, vd INF101
  name        VARCHAR(150) NOT NULL,
  credits     TINYINT UNSIGNED NULL,                    -- số tín chỉ
  description TEXT NULL,
  is_active   BOOLEAN      NOT NULL DEFAULT TRUE,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Giáo viên phụ trách môn (nhiều - nhiều). Sau này giáo viên chỉ soạn câu hỏi / đề thi cho môn mình phụ trách.
CREATE TABLE IF NOT EXISTS subject_teachers (
  subject_id INT      NOT NULL,
  user_id    INT      NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (subject_id, user_id),
  INDEX idx_subject_teachers_user (user_id),
  CONSTRAINT fk_st_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  CONSTRAINT fk_st_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

-- Dữ liệu mẫu (an toàn khi chạy lại): 3 môn, INF101 do gv001 phụ trách, PHY101 đang ngừng sử dụng
INSERT IGNORE INTO subjects (code, name, credits, description, is_active) VALUES
  ('INF101', 'Tin học đại cương', 3, 'Kiến thức cơ bản về máy tính, hệ điều hành và ứng dụng văn phòng.', 1),
  ('MAT101', 'Giải tích 1', 3, NULL, 1),
  ('PHY101', 'Vật lý đại cương', 2, NULL, 0);

INSERT IGNORE INTO subject_teachers (subject_id, user_id)
  SELECT s.id, u.id FROM subjects s JOIN users u ON u.username = 'gv001' WHERE s.code = 'INF101';