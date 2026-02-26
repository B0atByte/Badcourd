-- ============================================================
-- Promotions System Migration
-- BARGAIN_SPORT — badcourt database
-- ============================================================
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 1. สร้างตาราง promotions
CREATE TABLE IF NOT EXISTS promotions (
  id               INT            PRIMARY KEY AUTO_INCREMENT,
  code             VARCHAR(30)    NOT NULL UNIQUE COMMENT 'รหัสโปรโมชั่น เช่น STAFF15',
  name             VARCHAR(100)   NOT NULL COMMENT 'ชื่อโปรโมชั่น เช่น พนักงาน 15%',
  discount_percent DECIMAL(5,2)   NOT NULL COMMENT 'ส่วนลด % เช่น 15.00',
  start_date       DATE           NOT NULL,
  end_date         DATE           NOT NULL,
  is_active        TINYINT(1)     NOT NULL DEFAULT 1,
  description      TEXT           NULL,
  created_by       INT            NULL,
  created_at       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP      NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_code   (code),
  INDEX idx_active (is_active),
  INDEX idx_dates  (start_date, end_date),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. เพิ่มคอลัมน์ใน bookings เพื่อติดตามโปรโมชั่น
ALTER TABLE bookings
  ADD COLUMN promotion_id               INT          NULL AFTER member_id,
  ADD COLUMN promotion_discount_percent DECIMAL(5,2) NULL AFTER promotion_id,
  ADD INDEX  idx_promotion_id           (promotion_id),
  ADD CONSTRAINT fk_bookings_promotion
    FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE SET NULL ON UPDATE CASCADE;

-- 3. ข้อมูลตัวอย่าง
INSERT IGNORE INTO promotions (code, name, discount_percent, start_date, end_date, description, created_by)
VALUES
  ('STAFF15',   'พนักงาน 15%',      15.00, '2026-01-01', '2026-12-31', 'ส่วนลดพนักงานประจำปี 2026', 5),
  ('HOLIDAY20', 'วันหยุดพิเศษ 20%', 20.00, '2026-04-13', '2026-04-15', 'โปรโมชั่น Songkran 2026',   5);
