-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 10, 2025 at 07:22 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

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
  `id` int(11) NOT NULL,
  `court_id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(30) DEFAULT NULL,
  `start_datetime` datetime NOT NULL,
  `duration_hours` int(11) NOT NULL,
  `price_per_hour` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('booked','cancelled') NOT NULL DEFAULT 'booked',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `court_id`, `customer_name`, `customer_phone`, `start_datetime`, `duration_hours`, `price_per_hour`, `discount_amount`, `total_amount`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(13, 1, 'นางสาวสุดา สวยมาก', '', '2025-11-09 17:00:00', 2, 0.00, 0.00, 0.00, 'cancelled', 5, '2025-11-09 16:35:18', '2025-11-10 17:53:21'),
(14, 1, 'สมชาย ใจดี', '0899999999', '2025-11-10 16:00:00', 1, 250.00, 12.00, 238.00, 'booked', 5, '2025-11-10 18:01:01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `booking_logs`
--

CREATE TABLE `booking_logs` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courts`
--

CREATE TABLE `courts` (
  `id` int(11) NOT NULL,
  `court_no` int(11) NOT NULL,
  `status` enum('Available','Booked','In Use','Maintenance') DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courts`
--

INSERT INTO `courts` (`id`, `court_no`, `status`) VALUES
(1, 1, 'Available');

-- --------------------------------------------------------

--
-- Table structure for table `pricing_rules`
--

CREATE TABLE `pricing_rules` (
  `id` int(11) NOT NULL,
  `day_type` enum('weekday','weekend') NOT NULL,
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
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
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
  ADD UNIQUE KEY `uq_court_no` (`court_no`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `booking_logs`
--
ALTER TABLE `booking_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courts`
--
ALTER TABLE `courts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `pricing_rules`
--
ALTER TABLE `pricing_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_bookings_court` FOREIGN KEY (`court_id`) REFERENCES `courts` (`id`),
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
