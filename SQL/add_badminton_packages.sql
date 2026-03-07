-- Migration: เพิ่มระบบแพ็กเกจคอร์ตแบดมินตัน
-- Created: 2026-03-07

-- ========================================
-- 1. สร้างตาราง badminton_package_types
-- ========================================
CREATE TABLE IF NOT EXISTS `badminton_package_types` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ชื่อแพ็กเกจ เช่น "10 ชม. + แถม 2"',
  `hours_total` int NOT NULL COMMENT 'จำนวนชั่วโมงหลัก',
  `bonus_hours` int NOT NULL DEFAULT 0 COMMENT 'ชั่วโมงโบนัส',
  `price` decimal(10,2) NOT NULL COMMENT 'ราคาแพ็กเกจ',
  `validity_days` int DEFAULT NULL COMMENT 'อายุแพ็กเกจ (วัน), NULL = ไม่จำกัด',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 2. สร้างตาราง member_badminton_packages
-- ========================================
CREATE TABLE IF NOT EXISTS `member_badminton_packages` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `member_id` int DEFAULT NULL COMMENT 'FK → members.id (nullable สำหรับลูกค้าที่ไม่ได้เป็นสมาชิก)',
  `customer_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `badminton_package_type_id` int NOT NULL COMMENT 'FK → badminton_package_types',
  `hours_total` int NOT NULL COMMENT 'ชั่วโมงรวม (hours_total + bonus_hours)',
  `hours_used` int NOT NULL DEFAULT 0 COMMENT 'ชั่วโมงที่ใช้แล้ว',
  `purchase_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL COMMENT 'วันหมดอายุ',
  `status` enum('active','expired','exhausted') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL COMMENT 'ผู้สร้างแพ็กเกจ (admin user id)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_phone` (`customer_phone`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_package_type` (`badminton_package_type_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_badminton_pkg_type` FOREIGN KEY (`badminton_package_type_id`)
    REFERENCES `badminton_package_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_badminton_member` FOREIGN KEY (`member_id`)
    REFERENCES `members` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 3. เพิ่มคอลัมน์ใน bookings table
-- ========================================
ALTER TABLE `bookings`
ADD COLUMN `member_badminton_package_id` int DEFAULT NULL COMMENT 'FK → member_badminton_packages' AFTER `member_id`,
ADD COLUMN `used_package_hours` int DEFAULT NULL COMMENT 'จำนวนชั่วโมงที่ใช้จากแพ็กเกจ' AFTER `member_badminton_package_id`;

ALTER TABLE `bookings`
ADD KEY `idx_badminton_pkg` (`member_badminton_package_id`);

ALTER TABLE `bookings`
ADD CONSTRAINT `fk_badminton_booking_pkg`
  FOREIGN KEY (`member_badminton_package_id`)
  REFERENCES `member_badminton_packages` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ========================================
-- 4. Insert ข้อมูลตัวอย่าง (Optional)
-- ========================================
INSERT INTO `badminton_package_types` (`name`, `hours_total`, `bonus_hours`, `price`, `validity_days`, `is_active`) VALUES
('5 ชั่วโมง + แถม 1', 5, 1, 800.00, 90, 1),
('10 ชั่วโมง + แถม 2', 10, 2, 1500.00, 120, 1),
('15 ชั่วโมง + แถม 3', 15, 3, 2100.00, 150, 1);
