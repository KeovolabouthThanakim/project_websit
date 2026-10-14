-- Hệ thống Quản lý và Tổ chức Thi Trắc nghiệm Trực tuyến - Đại học Nghệ An
-- Bảng: users, classes, class_students

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS nau_quiz
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE nau_quiz;

DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS class_students;
DROP TABLE IF EXISTS classes;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id            INT PRIMARY KEY AUTO_INCREMENT,
  username      VARCHAR(50)  NOT NULL UNIQUE,          -- tên đăng nhập
  student_code  VARCHAR(30)  NULL UNIQUE,              -- mã sinh viên (chỉ dùng cho vai trò sinh viên)
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(120) UNIQUE,
  date_of_birth DATE         NULL,                         -- ngày sinh (không bắt buộc)
  role          ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
  is_active     BOOLEAN      NOT NULL DEFAULT TRUE,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role (role)
) ENGINE=InnoDB;

-- Lớp học: mỗi lớp có thể có 1 giáo viên chủ nhiệm (do quản trị viên phân công)
CREATE TABLE classes (
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
CREATE TABLE class_students (
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

-- Mã đặt lại mật khẩu gửi qua email (chức năng quên mật khẩu). Chỉ lưu mã băm SHA-256.
CREATE TABLE password_resets (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  user_id    INT         NOT NULL,
  token_hash CHAR(64)    NOT NULL UNIQUE,
  expires_at DATETIME    NOT NULL,
  created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pr_user (user_id),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
