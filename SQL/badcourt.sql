-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: mysql
-- Generation Time: Feb 24, 2026 at 04:34 AM
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
  `customer_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `customer_phone` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `start_datetime` datetime NOT NULL,
  `duration_hours` int NOT NULL,
  `price_per_hour` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('booked','cancelled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'booked',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `court_id`, `customer_name`, `customer_phone`, `start_datetime`, `duration_hours`, `price_per_hour`, `discount_amount`, `total_amount`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(15, 11, 'ปิ่นบุญญา', '0899999999', '2025-11-18 09:00:00', 2, 100.00, 12.00, 188.00, 'cancelled', 5, '2025-11-18 16:44:55', '2025-11-18 17:14:07'),
(16, 11, 'Kritsakorn', '0840831515111111111111111', '2025-11-19 23:00:00', 6, 100.00, 800.00, 0.00, 'booked', 5, '2025-11-19 07:21:46', NULL),
(18, 20, 'เอ้', '0819160099', '2025-11-28 16:15:00', 2, 250.00, 0.00, 500.00, 'booked', 5, '2025-11-28 04:19:29', NULL),
(19, 20, 'มิวสิก', '0872545487', '2025-11-28 12:59:00', 3, 0.00, 0.00, 0.00, 'booked', 5, '2025-11-28 04:20:32', NULL),
(20, 18, 'ตั้ม', '0213131321', '2025-11-28 16:59:00', 2, 200.00, 0.00, 400.00, 'booked', 5, '2025-11-28 04:27:17', NULL),
(21, 11, 'เอ้', '0819160099', '2026-01-09 16:00:00', 1, 100.00, 50.00, 50.00, 'booked', 5, '2026-01-09 07:07:52', NULL),
(22, 11, 'เอ้', '0819160099', '2026-01-09 14:00:00', 1, 100.00, 0.00, 100.00, 'booked', 5, '2026-01-09 07:08:17', NULL),
(23, 11, 'เอ้', '0873646987', '2026-02-18 16:14:00', 2, 100.00, 0.00, 200.00, 'booked', 5, '2026-02-18 08:30:22', NULL),
(24, 19, 'เอ้', '0873646987', '2026-02-18 16:14:00', 2, 250.00, 100.00, 400.00, 'booked', 5, '2026-02-18 08:30:45', NULL),
(25, 19, 'เอ้', '0875132132', '2026-02-18 18:15:00', 2, 250.00, 0.00, 500.00, 'booked', 5, '2026-02-18 08:31:33', NULL),
(26, 19, 'เจน', '2343252345', '2026-02-18 21:00:00', 2, 0.00, 15.00, 0.00, 'booked', 5, '2026-02-18 11:21:51', NULL),
(27, 28, 'แยย', '2343252345', '2026-02-18 16:15:00', 1, 250.00, 0.00, 250.00, 'booked', 5, '2026-02-18 11:25:09', NULL),
(28, 28, 'พชิ', '0622173495', '2026-02-23 11:00:00', 3, 0.00, 200.00, 0.00, 'cancelled', 5, '2026-02-23 08:35:19', '2026-02-23 08:39:23'),
(29, 11, 'พี่เฟรม', '0999999999', '2026-03-03 20:00:00', 2, 250.00, 0.00, 500.00, 'booked', 5, '2026-02-23 10:04:57', '2026-02-23 10:08:34'),
(30, 11, 'เจน', '0999999999', '2026-02-23 17:30:00', 2, 100.00, 0.00, 200.00, 'booked', 5, '2026-02-23 10:27:24', NULL),
(31, 27, 'เจ๊', '0999999999', '2026-02-23 19:00:00', 2, 150.00, 100.00, 200.00, 'booked', 5, '2026-02-23 10:29:51', NULL),
(32, 20, 'พี่เฟรม', '0999999999', '2026-02-23 21:00:00', 2, 0.00, 0.00, 0.00, 'booked', 5, '2026-02-23 10:31:36', NULL),
(33, 28, 'เอ็ม', '0999999999', '2026-02-23 16:00:00', 1, 250.00, 0.00, 250.00, 'booked', 5, '2026-02-23 10:38:50', NULL),
(34, 28, 'แนน', '0999999999', '2026-02-23 19:00:00', 2, 250.00, 0.00, 500.00, 'booked', 5, '2026-02-23 10:39:35', NULL),
(35, 28, 'เจน', '', '2026-02-23 17:15:00', 1, 250.00, 0.00, 250.00, 'booked', 5, '2026-02-23 10:40:40', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `booking_logs`
--

CREATE TABLE `booking_logs` (
  `id` int NOT NULL,
  `booking_id` int DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `actor_id` int DEFAULT NULL,
  `details` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courts`
--

CREATE TABLE `courts` (
  `id` int NOT NULL,
  `court_no` int NOT NULL,
  `vip_room_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'ชื่อห้อง VIP',
  `status` enum('Available','Booked','In Use','Maintenance') COLLATE utf8mb4_general_ci DEFAULT 'Available',
  `is_vip` tinyint(1) NOT NULL DEFAULT '0',
  `court_type` enum('normal','vip') COLLATE utf8mb4_general_ci DEFAULT 'normal' COMMENT 'ประเภทคอร์ต: normal=ปกติ, vip=VIP',
  `vip_price` decimal(10,2) DEFAULT NULL COMMENT 'ราคาพิเศษสำหรับคอร์ต VIP (บาท/ชั่วโมง)',
  `normal_price` decimal(10,2) DEFAULT NULL COMMENT 'ราคาคงที่สำหรับคอร์ตปกติ (optional)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courts`
--

INSERT INTO `courts` (`id`, `court_no`, `vip_room_name`, `status`, `is_vip`, `court_type`, `vip_price`, `normal_price`) VALUES
(11, -3, 'ห้อง VIP 1', 'Available', 1, 'vip', 100.00, NULL),
(18, -4, 'ห้อง VIP 2', 'Available', 1, 'vip', 100.00, NULL),
(19, 2, NULL, 'Available', 0, 'normal', NULL, NULL),
(20, 3, NULL, 'Available', 0, 'normal', NULL, NULL),
(21, 4, NULL, 'Available', 0, 'normal', NULL, NULL),
(22, 5, NULL, 'Available', 0, 'normal', NULL, NULL),
(23, 6, NULL, 'Available', 0, 'normal', NULL, NULL),
(24, 7, NULL, 'Available', 0, 'normal', NULL, NULL),
(25, 8, NULL, 'Available', 0, 'normal', NULL, NULL),
(26, 9, NULL, 'Available', 0, 'normal', NULL, NULL),
(27, -5, 'ห้อง VIP 3', 'Available', 1, 'vip', 150.00, NULL),
(28, 1, NULL, 'Available', 0, 'normal', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `pricing_rules`
--

CREATE TABLE `pricing_rules` (
  `id` int NOT NULL,
  `day_type` enum('weekday','weekend') COLLATE utf8mb4_general_ci NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `price_per_hour` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pricing_rules`
--

INSERT INTO `pricing_rules` (`id`, `day_type`, `start_time`, `end_time`, `price_per_hour`) VALUES
(10, 'weekend', '08:00:00', '12:00:00', 200.00),
(13, 'weekday', '16:00:00', '21:00:00', 250.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('admin','user') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'user',
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
  ADD KEY `fk_bookings_user` (`created_by`);

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
  ADD KEY `idx_court_type` (`court_type`);

--
-- Indexes for table `pricing_rules`
--
ALTER TABLE `pricing_rules`
  ADD PRIMARY KEY (`id`);

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

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
-- AUTO_INCREMENT for table `pricing_rules`
--
ALTER TABLE `pricing_rules`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

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
  ADD CONSTRAINT `fk_bookings_court` FOREIGN KEY (`court_id`) REFERENCES `courts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bookings_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `booking_logs`
--
ALTER TABLE `booking_logs`
  ADD CONSTRAINT `booking_logs_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `booking_logs_ibfk_2` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
