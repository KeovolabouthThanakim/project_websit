-- Nhật ký hoạt động: ai làm gì, lúc nào, từ địa chỉ IP nào (đăng nhập, người dùng, kỳ thi, cấp quyền, cài đặt...).
-- Chạy lại nhiều lần vẫn an toàn, không xóa dữ liệu.
-- (Không có lệnh USE: nhập vào đúng CSDL đang chọn trong phpMyAdmin.)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
  id         BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id    INT          NULL,                    -- NULL khi chưa đăng nhập hoặc tài khoản đã bị xóa
  username   VARCHAR(50)  NULL,                    -- chép lại tên đăng nhập để vẫn đọc được khi tài khoản bị xóa
  role       VARCHAR(10)  NULL,
  action     VARCHAR(40)  NOT NULL,                -- vd exam.publish, user.delete
  detail     VARCHAR(500) NULL,
  ip         VARCHAR(45)  NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_time (created_at),
  INDEX idx_audit_action (action, created_at),
  INDEX idx_audit_user (user_id, created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
