-- ตารางสมาชิก
CREATE TABLE IF NOT EXISTS members (
  id INT PRIMARY KEY AUTO_INCREMENT,
  phone VARCHAR(10) UNIQUE NOT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100),
  points INT DEFAULT 0,
  total_bookings INT DEFAULT 0,
  total_spent DECIMAL(10,2) DEFAULT 0,
  member_level ENUM('Bronze','Silver','Gold','Platinum') DEFAULT 'Bronze',
  joined_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  last_booking_date DATETIME,
  birth_date DATE,
  status ENUM('active','inactive') DEFAULT 'active',
  notes TEXT,
  INDEX idx_phone (phone),
  INDEX idx_level (member_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตารางประวัติการใช้แต้ม
CREATE TABLE IF NOT EXISTS point_transactions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  member_id INT NOT NULL,
  booking_id INT,
  points INT NOT NULL,
  type ENUM('earn','redeem','adjust') NOT NULL,
  description VARCHAR(255),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  created_by INT,
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  INDEX idx_member (member_id),
  INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- เพิ่มคอลัมน์ member_id ในตาราง bookings
ALTER TABLE bookings ADD COLUMN member_id INT AFTER customer_phone;
ALTER TABLE bookings ADD FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL;
ALTER TABLE bookings ADD INDEX idx_member_id (member_id);

-- ข้อมูลตัวอย่างสมาชิก (สำหรับทดสอบ)
INSERT INTO members (phone, name, email, points, total_bookings, total_spent, member_level) VALUES
('0812345678', 'สมชาย ใจดี', 'somchai@email.com', 150, 12, 3500.00, 'Silver'),
('0898765432', 'สมหญิง รักสนุก', 'somying@email.com', 500, 35, 12000.00, 'Gold'),
('0823456789', 'ประยุทธ์ มั่นคง', NULL, 50, 5, 1200.00, 'Bronze');
