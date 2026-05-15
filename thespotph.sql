-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 15, 2026 at 08:58 AM
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
-- Database: `thespotph`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `token` varchar(64) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `event_date` date NOT NULL,
  `package` varchar(100) NOT NULL,
  `message` text DEFAULT NULL,
  `status` enum('pending','accepted','declined') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_user_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `token`, `name`, `email`, `phone`, `event_date`, `package`, `message`, `status`, `created_at`, `assigned_user_id`) VALUES
(7, '962b2bd0a53d4b145a3250829bf5b478', 'John Percival Aguilar', 'aguilarjohnpercival@gmail.com', '09959621333', '2027-04-08', 'Premium Package', 'bday\n', 'accepted', '2026-05-11 15:24:49', '2,3');

-- --------------------------------------------------------

--
-- Table structure for table `etl_log`
--

CREATE TABLE `etl_log` (
  `log_id` int(11) NOT NULL,
  `run_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('SUCCESS','FAILED') NOT NULL,
  `rows_extracted` int(11) DEFAULT 0,
  `rows_loaded` int(11) DEFAULT 0,
  `duration_sec` decimal(6,2) DEFAULT 0.00,
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('admin','staff') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `role`, `created_at`) VALUES
(1, 'admin@thespotph.com', '482c811da5d5b4bc6d497ffa98491e38', NULL, 'admin', '2026-05-13 14:45:08'),
(2, 'aguilarjohnpercival@gmail.com', 'persibaldog', 'John Percival Z. Aguilar', 'staff', '2026-05-13 15:06:21'),
(3, 'shanini@gmail.com', 'shanigga', 'Shaznay Anne Alaon Ojeda', 'staff', '2026-05-13 16:05:32');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`);

--
-- Indexes for table `etl_log`
--
ALTER TABLE `etl_log`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `etl_log`
--
ALTER TABLE `etl_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
