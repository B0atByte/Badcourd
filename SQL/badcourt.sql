-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: mysql
-- Generation Time: Feb 27, 2026 at 09:02 AM
-- Server version: 8.0.44
-- PHP Version: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `badcourt`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int NOT NULL,
  `court_id` int NOT NULL,
  `customer_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `customer_phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `member_id` int DEFAULT NULL,
  `promotion_id` int DEFAULT NULL,
  `promotion_discount_percent` decimal(5,2) DEFAULT NULL,
  `payment_slip_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `start_datetime` datetime NOT NULL,
  `duration_hours` int NOT NULL,
  `price_per_hour` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('booked','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'booked',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `court_id`, `customer_name`, `customer_phone`, `member_id`, `promotion_id`, `promotion_discount_percent`, `payment_slip_path`, `start_datetime`, `duration_hours`, `price_per_hour`, `discount_amount`, `total_amount`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(15, 11, 'ปิ่นบุญญา', '0899999999', NULL, NULL, NULL, NULL, '2025-11-18 09:00:00', 2, 100.00, 12.00, 188.00, 'cancelled', 5, '2025-11-18 16:44:55', '2025-11-18 17:14:07'),
(16, 11, 'Kritsakorn', '0840831515111111111111111', NULL, NULL, NULL, NULL, '2025-11-19 23:00:00', 6, 100.00, 800.00, 0.00, 'booked', 5, '2025-11-19 07:21:46', NULL),
(18, 20, 'เอ้', '0819160099', NULL, NULL, NULL, NULL, '2025-11-28 16:15:00', 2, 250.00, 0.00, 500.00, 'booked', 5, '2025-11-28 04:19:29', NULL),
(19, 20, 'มิวสิก', '0872545487', NULL, NULL, NULL, NULL, '2025-11-28 12:59:00', 3, 0.00, 0.00, 0.00, 'booked', 5, '2025-11-28 04:20:32', NULL),
(20, 18, 'ตั้ม', '0213131321', NULL, NULL, NULL, NULL, '2025-11-28 16:59:00', 2, 200.00, 0.00, 400.00, 'booked', 5, '2025-11-28 04:27:17', NULL),
(21, 11, 'เอ้', '0819160099', NULL, NULL, NULL, NULL, '2026-01-09 16:00:00', 1, 100.00, 50.00, 50.00, 'booked', 5, '2026-01-09 07:07:52', NULL),
(22, 11, 'เอ้', '0819160099', NULL, NULL, NULL, NULL, '2026-01-09 14:00:00', 1, 100.00, 0.00, 100.00, 'booked', 5, '2026-01-09 07:08:17', NULL),
(23, 11, 'เอ้', '0873646987', NULL, NULL, NULL, NULL, '2026-02-18 16:14:00', 2, 100.00, 0.00, 200.00, 'booked', 5, '2026-02-18 08:30:22', NULL),
(24, 19, 'เอ้', '0873646987', NULL, NULL, NULL, NULL, '2026-02-18 16:14:00', 2, 250.00, 100.00, 400.00, 'booked', 5, '2026-02-18 08:30:45', NULL),
(25, 19, 'เอ้', '0875132132', NULL, NULL, NULL, NULL, '2026-02-18 18:15:00', 2, 250.00, 0.00, 500.00, 'booked', 5, '2026-02-18 08:31:33', NULL),
(26, 19, 'เจน', '2343252345', NULL, NULL, NULL, NULL, '2026-02-18 21:00:00', 2, 0.00, 15.00, 0.00, 'booked', 5, '2026-02-18 11:21:51', NULL),
(27, 28, 'แยย', '2343252345', NULL, NULL, NULL, NULL, '2026-02-18 16:15:00', 1, 250.00, 0.00, 250.00, 'booked', 5, '2026-02-18 11:25:09', NULL),
(28, 28, 'พชิ', '0622173495', NULL, NULL, NULL, NULL, '2026-02-23 11:00:00', 3, 0.00, 200.00, 0.00, 'cancelled', 5, '2026-02-23 08:35:19', '2026-02-23 08:39:23'),
(29, 11, 'พี่เฟรม', '0999999999', NULL, NULL, NULL, NULL, '2026-03-03 20:00:00', 2, 250.00, 0.00, 500.00, 'booked', 5, '2026-02-23 10:04:57', '2026-02-23 10:08:34'),
(30, 11, 'เจน', '0999999999', NULL, NULL, NULL, NULL, '2026-02-23 17:30:00', 2, 100.00, 0.00, 200.00, 'booked', 5, '2026-02-23 10:27:24', NULL),
(31, 27, 'เจ๊', '0999999999', NULL, NULL, NULL, NULL, '2026-02-23 19:00:00', 2, 150.00, 100.00, 200.00, 'booked', 5, '2026-02-23 10:29:51', NULL),
(32, 20, 'พี่เฟรม', '0999999999', NULL, NULL, NULL, NULL, '2026-02-23 21:00:00', 2, 0.00, 0.00, 0.00, 'booked', 5, '2026-02-23 10:31:36', NULL),
(33, 28, 'เอ็ม', '0999999999', NULL, NULL, NULL, NULL, '2026-02-23 16:00:00', 1, 250.00, 0.00, 250.00, 'booked', 5, '2026-02-23 10:38:50', NULL),
(34, 28, 'แนน', '0999999999', NULL, NULL, NULL, NULL, '2026-02-23 19:00:00', 2, 250.00, 0.00, 500.00, 'booked', 5, '2026-02-23 10:39:35', NULL),
(35, 28, 'เจน', '', NULL, NULL, NULL, NULL, '2026-02-23 17:15:00', 1, 250.00, 0.00, 250.00, 'booked', 5, '2026-02-23 10:40:40', NULL),
(36, 11, 'มาดีใจสู้', '0898765432', 2, NULL, NULL, NULL, '2026-02-26 16:00:00', 1, 100.00, 10.00, 90.00, 'booked', 5, '2026-02-26 04:12:46', NULL),
(37, 19, 'นายดี มีชัย', '0639216822', 4, NULL, NULL, 'uploads/slips/slip_37_699fceea291ff.png', '2026-02-26 18:00:00', 1, 250.00, 37.00, 213.00, 'booked', 5, '2026-02-26 04:41:14', NULL),
(38, 20, 'พัฒนพงษ์ กิ่งจันทร์', '0639216822', 4, 3, 5.00, NULL, '2026-02-26 17:00:00', 1, 250.00, 12.00, 238.00, 'booked', 5, '2026-02-26 06:57:42', NULL),
(39, 11, 'พัฒนพงษ์', '0639216822', 4, 3, 5.00, NULL, '2026-02-26 17:00:00', 1, 100.00, 5.00, 95.00, 'cancelled', 5, '2026-02-26 06:58:53', '2026-02-26 06:59:40'),
(40, 11, 'พัฒนพงษ์ กิ่งจันทร์', '0639216822', 4, 3, 5.00, NULL, '2026-02-26 18:00:00', 2, 100.00, 10.00, 190.00, 'booked', 5, '2026-02-26 07:21:30', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `booking_logs`
--

CREATE TABLE `booking_logs` (
  `id` int NOT NULL,
  `booking_id` int DEFAULT NULL,
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `actor_id` int DEFAULT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courts`
--

CREATE TABLE `courts` (
  `id` int NOT NULL,
  `court_no` int NOT NULL,
  `vip_room_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'ชื่อห้อง VIP',
  `status` enum('Available','Booked','In Use','Maintenance') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Available',
  `is_vip` tinyint(1) NOT NULL DEFAULT '0',
  `court_type` enum('normal','vip') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'normal' COMMENT 'ประเภทคอร์ต: normal=ปกติ, vip=VIP',
  `vip_price` decimal(10,2) DEFAULT NULL COMMENT 'ราคาพิเศษสำหรับคอร์ต VIP (บาท/ชั่วโมง)',
  `normal_price` decimal(10,2) DEFAULT NULL COMMENT 'ราคาคงที่สำหรับคอร์ตปกติ (optional)',
  `pricing_group_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courts`
--

INSERT INTO `courts` (`id`, `court_no`, `vip_room_name`, `status`, `is_vip`, `court_type`, `vip_price`, `normal_price`, `pricing_group_id`) VALUES
(11, -3, 'ห้อง VIP 1', 'Available', 1, 'vip', 100.00, NULL, NULL),
(18, -4, 'ห้อง VIP 2', 'Available', 1, 'vip', 100.00, NULL, NULL),
(19, 2, NULL, 'Available', 0, 'normal', NULL, NULL, 1),
(20, 3, NULL, 'Available', 0, 'normal', NULL, 100.00, NULL),
(21, 4, NULL, 'Available', 0, 'normal', NULL, 100.00, NULL),
(22, 5, NULL, 'Available', 0, 'normal', NULL, 100.00, NULL),
(23, 6, NULL, 'Available', 0, 'normal', NULL, 100.00, NULL),
(24, 7, NULL, 'Available', 0, 'normal', NULL, 100.00, NULL),
(25, 8, NULL, 'Available', 0, 'normal', NULL, 100.00, NULL),
(26, 9, NULL, 'Available', 0, 'normal', NULL, 100.00, NULL),
(27, -5, 'ห้อง VIP 3', 'Available', 1, 'vip', NULL, NULL, 1),
(28, 1, NULL, 'Available', 0, 'normal', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int NOT NULL,
  `phone` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `points` int DEFAULT '0',
  `total_bookings` int DEFAULT '0',
  `total_spent` decimal(10,2) DEFAULT '0.00',
  `member_level` enum('Bronze','Silver','Gold','Platinum') COLLATE utf8mb4_unicode_ci DEFAULT 'Bronze',
  `joined_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_booking_date` datetime DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `notes` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `phone`, `name`, `email`, `points`, `total_bookings`, `total_spent`, `member_level`, `joined_date`, `last_booking_date`, `birth_date`, `status`, `notes`) VALUES
(1, '0812345678', 'สมชาย ใจดี', 'somchai@email.com', 150, 12, 3500.00, 'Silver', '2026-02-26 02:45:01', NULL, NULL, 'active', NULL),
(2, '0898765432', 'สมหญิง รักสนุก', 'somying@email.com', 500, 36, 12090.00, 'Gold', '2026-02-26 02:45:01', '2026-02-26 04:12:46', NULL, 'active', NULL),
(3, '0823456789', 'ประยุทธ์ มั่นคง', NULL, 110, 5, 1200.00, 'Bronze', '2026-02-26 02:45:01', NULL, NULL, 'active', NULL),
(4, '0639216822', 'นายดี มีชัย', NULL, 5, 4, 736.00, 'Bronze', '2026-02-26 04:41:14', '2026-02-26 07:21:30', NULL, 'active', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `point_transactions`
--

CREATE TABLE `point_transactions` (
  `id` int NOT NULL,
  `member_id` int NOT NULL,
  `booking_id` int DEFAULT NULL,
  `points` int NOT NULL,
  `type` enum('earn','redeem','adjust') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `point_transactions`
--

INSERT INTO `point_transactions` (`id`, `member_id`, `booking_id`, `points`, `type`, `description`, `created_at`, `created_by`) VALUES
(1, 3, NULL, 20, 'adjust', '20', '2026-02-26 02:49:03', 5),
(2, 3, NULL, 20, 'adjust', '20', '2026-02-26 02:49:31', 5),
(3, 3, NULL, 20, 'adjust', '20', '2026-02-26 02:49:35', 5),
(4, 3, NULL, 20, 'adjust', '20', '2026-02-26 02:49:42', 5),
(5, 3, NULL, 20, 'adjust', '20', '2026-02-26 02:49:52', 5),
(6, 4, 37, 2, 'earn', 'รับแต้มจากการจอง (฿213)', '2026-02-26 04:41:14', 5),
(7, 4, 38, 2, 'earn', 'รับแต้มจากการจอง (฿238)', '2026-02-26 06:57:42', 5),
(8, 4, 40, 1, 'earn', 'รับแต้มจากการจอง (฿190)', '2026-02-26 07:21:30', 5);

-- --------------------------------------------------------

--
-- Table structure for table `pricing_groups`
--

CREATE TABLE `pricing_groups` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pricing_groups`
--

INSERT INTO `pricing_groups` (`id`, `name`, `created_at`) VALUES
(1, 'กลุ่มราคาช่วงเช้า', '2026-02-27 07:00:51'),
(3, 'กลุ่มราคาช่วงกลาง', '2026-02-27 07:02:58');

-- --------------------------------------------------------

--
-- Table structure for table `pricing_rules`
--

CREATE TABLE `pricing_rules` (
  `id` int NOT NULL,
  `group_id` int DEFAULT NULL,
  `day_type` enum('weekday','weekend') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `price_per_hour` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pricing_rules`
--

INSERT INTO `pricing_rules` (`id`, `group_id`, `day_type`, `start_time`, `end_time`, `price_per_hour`) VALUES
(21, 1, 'weekday', '09:00:00', '10:00:00', 200.00),
(22, 1, 'weekday', '10:00:00', '12:00:00', 250.00),
(23, 3, 'weekday', '08:00:00', '12:00:00', 400.00),
(24, 1, 'weekend', '08:00:00', '12:00:00', 400.00),
(26, 3, 'weekend', '08:00:00', '12:00:00', 400.00),
(28, NULL, 'weekend', '08:00:00', '12:00:00', 150.00),
(29, NULL, 'weekday', '08:00:00', '12:00:00', 150.00),
(30, NULL, 'weekday', '08:00:00', '12:00:00', 400.00);

-- --------------------------------------------------------

--
-- Table structure for table `promotions`
--

CREATE TABLE `promotions` (
  `id` int NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'à¸£à¸«à¸±à¸ªà¹‚à¸›à¸£à¹‚à¸¡à¸Šà¸±à¹ˆà¸™ à¹€à¸Šà¹ˆà¸™ STAFF15',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'à¸Šà¸·à¹ˆà¸­à¹‚à¸›à¸£à¹‚à¸¡à¸Šà¸±à¹ˆà¸™ à¹€à¸Šà¹ˆà¸™ à¸žà¸™à¸±à¸à¸‡à¸²à¸™ 15%',
  `discount_percent` decimal(5,2) NOT NULL COMMENT 'à¸ªà¹ˆà¸§à¸™à¸¥à¸” % à¹€à¸Šà¹ˆà¸™ 15.00',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `promotions`
--

INSERT INTO `promotions` (`id`, `code`, `name`, `discount_percent`, `start_date`, `end_date`, `is_active`, `description`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'STAFF15', 'พนักงาน 15%', 15.00, '2026-01-01', '2026-12-31', 1, 'ส่วนลดพนักงานประจำปี 2026', 5, '2026-02-26 04:16:40', '2026-02-26 04:26:41'),
(2, 'HOLIDAY20', 'วันหยุดพิเศษ 20%', 20.00, '2026-04-13', '2026-04-15', 1, 'โปรโมชั่น Songkran 2026', 5, '2026-02-26 04:16:40', '2026-02-26 04:26:41'),
(3, '11948', 'โบ็ท', 5.00, '2026-02-26', '2026-02-27', 1, '', 5, '2026-02-26 06:56:31', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('admin','user') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'user',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `active`, `created_at`) VALUES
(5, 'admin', '$2y$10$b6VpLbYoLcvnyyh4q0IxLuqA2wEXC9gnRpsYm8UxRzVzRiwWV.Ada', 'admin', 1, '2025-10-26 10:21:18'),
(6, 'user', '$2y$10$PqgmcioIWeduQN/qtuMT.eYhbCFpoPsJFNLSiYxVbQANlYCFITmfa', 'user', 1, '2025-10-26 10:41:19');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bookings_court` (`court_id`),
  ADD KEY `fk_bookings_user` (`created_by`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_promotion_id` (`promotion_id`);

--
-- Indexes for table `booking_logs`
--
ALTER TABLE `booking_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `actor_id` (`actor_id`);

--
-- Indexes for table `courts`
--
ALTER TABLE `courts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_court_type` (`court_type`),
  ADD KEY `fk_court_pg` (`pricing_group_id`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD KEY `idx_phone` (`phone`),
  ADD KEY `idx_level` (`member_level`);

--
-- Indexes for table `point_transactions`
--
ALTER TABLE `point_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `idx_member` (`member_id`),
  ADD KEY `idx_type` (`type`);

--
-- Indexes for table `pricing_groups`
--
ALTER TABLE `pricing_groups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pricing_rules`
--
ALTER TABLE `pricing_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pr_group` (`group_id`);

--
-- Indexes for table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_dates` (`start_date`,`end_date`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `booking_logs`
--
ALTER TABLE `booking_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courts`
--
ALTER TABLE `courts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `point_transactions`
--
ALTER TABLE `point_transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `pricing_groups`
--
ALTER TABLE `pricing_groups`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pricing_rules`
--
ALTER TABLE `pricing_rules`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bookings_court` FOREIGN KEY (`court_id`) REFERENCES `courts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bookings_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bookings_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `booking_logs`
--
ALTER TABLE `booking_logs`
  ADD CONSTRAINT `booking_logs_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `booking_logs_ibfk_2` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `courts`
--
ALTER TABLE `courts`
  ADD CONSTRAINT `fk_court_pg` FOREIGN KEY (`pricing_group_id`) REFERENCES `pricing_groups` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `point_transactions`
--
ALTER TABLE `point_transactions`
  ADD CONSTRAINT `point_transactions_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `point_transactions_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pricing_rules`
--
ALTER TABLE `pricing_rules`
  ADD CONSTRAINT `fk_pr_group` FOREIGN KEY (`group_id`) REFERENCES `pricing_groups` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `promotions`
--
ALTER TABLE `promotions`
  ADD CONSTRAINT `promotions_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
