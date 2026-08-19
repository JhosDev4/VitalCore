-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 16, 2026 at 07:43 PM
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
-- Database: `vitalcore_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `height_readings`
--

CREATE TABLE `height_readings` (
  `id` int(11) NOT NULL,
  `height` decimal(6,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `height_readings`
--

INSERT INTO `height_readings` (`id`, `height`, `created_at`, `user_id`) VALUES
(20, 57.00, '2026-08-05 14:22:21', 0),
(21, 56.30, '2026-08-05 14:36:40', 0),
(22, 55.90, '2026-08-05 14:36:57', 0),
(23, 223.00, '2026-08-05 14:37:15', 0),
(24, 56.10, '2026-08-05 14:41:51', 0),
(25, 56.50, '2026-08-05 14:42:09', 0),
(26, 56.60, '2026-08-05 14:42:27', 0),
(27, 56.40, '2026-08-05 14:43:56', 0),
(28, 56.80, '2026-08-05 14:55:45', 0),
(29, 56.70, '2026-08-05 15:38:37', 0),
(30, 56.80, '2026-08-05 15:39:56', 0),
(31, 56.80, '2026-08-05 16:00:01', 0),
(32, 57.00, '2026-08-05 16:02:36', 0),
(33, 57.00, '2026-08-05 16:03:10', 0),
(34, 57.00, '2026-08-05 16:03:44', 0),
(35, 57.00, '2026-08-05 16:04:29', 0),
(36, 56.30, '2026-08-05 16:13:41', 0),
(37, 57.00, '2026-08-05 16:14:13', 0),
(38, 56.50, '2026-08-05 16:14:46', 0),
(39, 56.30, '2026-08-05 16:15:28', 0),
(40, 57.00, '2026-08-05 16:22:52', 0),
(41, 56.90, '2026-08-05 16:23:25', 0),
(42, 57.00, '2026-08-05 16:23:57', 0),
(43, 57.00, '2026-08-05 16:24:39', 0),
(44, 198.80, '2026-08-05 16:38:34', 0),
(45, 57.00, '2026-08-05 16:39:06', 0),
(46, 57.00, '2026-08-05 16:39:39', 0),
(47, 103.10, '2026-08-05 16:40:23', 0),
(48, 84.00, '2026-08-09 14:19:09', 0),
(49, 83.90, '2026-08-09 14:19:30', 0),
(50, 91.90, '2026-08-09 14:19:49', 0),
(51, 84.00, '2026-08-09 14:20:17', 0),
(52, 84.00, '2026-08-09 14:28:55', 0),
(53, 84.00, '2026-08-09 14:29:14', 0),
(54, 84.00, '2026-08-09 14:29:33', 0),
(55, 84.00, '2026-08-09 14:30:03', 0),
(56, 228.00, '2026-08-09 14:32:04', 0),
(57, 84.00, '2026-08-09 14:33:26', 0),
(58, 83.00, '2026-08-09 14:33:45', 0),
(59, 83.20, '2026-08-09 14:34:07', 0),
(60, 84.00, '2026-08-09 14:34:49', 0);

-- --------------------------------------------------------

--
-- Table structure for table `max30102_readings`
--

CREATE TABLE `max30102_readings` (
  `id` int(11) NOT NULL,
  `heart_rate` int(11) NOT NULL,
  `spo2` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `max30102_readings`
--

INSERT INTO `max30102_readings` (`id`, `heart_rate`, `spo2`, `created_at`) VALUES
(1, 78, 98, '2026-07-26 21:17:53'),
(2, 78, 98, '2026-08-04 13:59:29'),
(3, 78, 98, '2026-08-04 14:53:20'),
(4, 78, 98, '2026-08-04 14:53:50'),
(5, 78, 98, '2026-08-04 14:57:20'),
(6, 78, 98, '2026-08-04 14:58:15'),
(7, 78, 98, '2026-08-04 15:25:39'),
(8, 78, 98, '2026-08-04 15:34:52'),
(9, 78, 98, '2026-08-04 15:37:31'),
(10, 78, 98, '2026-08-04 15:37:37'),
(11, 78, 98, '2026-08-04 15:38:07'),
(12, 78, 98, '2026-08-04 15:38:08'),
(13, 78, 98, '2026-08-04 15:40:06'),
(14, 78, 98, '2026-08-04 15:40:07'),
(15, 78, 98, '2026-08-04 15:40:15'),
(16, 78, 98, '2026-08-04 15:53:19'),
(17, 78, 98, '2026-08-04 15:53:20'),
(18, 78, 98, '2026-08-04 15:56:58'),
(19, 78, 98, '2026-08-04 16:02:42'),
(20, 78, 98, '2026-08-04 16:56:43'),
(21, 78, 98, '2026-08-04 17:16:10'),
(22, 78, 98, '2026-08-04 17:45:59'),
(23, 78, 98, '2026-08-04 18:43:22'),
(24, 78, 98, '2026-08-04 19:38:33'),
(25, 68, 93, '2026-08-04 19:51:22'),
(26, 78, 95, '2026-08-04 19:51:50'),
(27, 0, 0, '2026-08-04 19:52:25'),
(28, 77, 93, '2026-08-04 19:53:14'),
(29, 82, 93, '2026-08-04 19:54:14'),
(30, 96, 70, '2026-08-04 20:14:28'),
(31, 65, 91, '2026-08-04 20:15:29'),
(32, 72, 85, '2026-08-05 14:33:13'),
(33, 73, 85, '2026-08-05 15:32:29'),
(34, 86, 74, '2026-08-05 15:51:45'),
(35, 88, 91, '2026-08-05 16:00:16'),
(36, 68, 92, '2026-08-05 16:02:52'),
(37, 61, 91, '2026-08-05 16:03:26'),
(38, 72, 92, '2026-08-05 16:04:00'),
(39, 72, 92, '2026-08-05 16:04:45'),
(40, 80, 86, '2026-08-05 16:13:57'),
(41, 86, 70, '2026-08-05 16:14:29'),
(42, 84, 70, '2026-08-05 16:15:01'),
(43, 71, 86, '2026-08-05 16:15:44'),
(44, 84, 70, '2026-08-05 16:23:07'),
(45, 71, 70, '2026-08-05 16:23:40'),
(46, 83, 80, '2026-08-05 16:24:12'),
(47, 90, 70, '2026-08-05 16:24:55'),
(48, 80, 74, '2026-08-05 16:38:49'),
(49, 75, 70, '2026-08-05 16:39:22'),
(50, 73, 70, '2026-08-05 16:39:55'),
(51, 72, 91, '2026-08-05 16:40:38'),
(52, 88, 92, '2026-08-09 14:32:19'),
(53, 83, 80, '2026-08-09 14:34:22');

-- --------------------------------------------------------

--
-- Table structure for table `measurements`
--

CREATE TABLE `measurements` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `temperature` decimal(5,2) DEFAULT NULL,
  `weight` decimal(6,2) DEFAULT NULL,
  `height` decimal(6,2) DEFAULT NULL,
  `bmi` decimal(5,2) DEFAULT NULL,
  `heart_rate` int(11) DEFAULT NULL,
  `spo2` int(11) DEFAULT NULL,
  `systolic` int(11) DEFAULT NULL,
  `diastolic` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `service_type` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `measurements`
--

INSERT INTO `measurements` (`id`, `user_id`, `temperature`, `weight`, `height`, `bmi`, `heart_rate`, `spo2`, `systolic`, `diastolic`, `created_at`, `service_type`) VALUES
(6, 1, 27.75, 0.57, 86.00, 0.77, NULL, NULL, NULL, NULL, '2026-07-14 18:46:05', NULL),
(70, 1, 30.67, -1174.35, 84.00, 0.00, 83, 80, NULL, NULL, '2026-08-15 17:37:11', NULL),
(79, 1, 30.67, -1174.35, 84.00, 0.00, 83, 80, NULL, NULL, '2026-08-15 20:12:48', 'immunization'),
(80, 51, 30.67, -1174.35, 84.00, 0.00, 83, 80, NULL, NULL, '2026-08-15 20:19:22', 'immunization'),
(81, 51, 30.67, -1174.35, 84.00, 0.00, 83, 80, NULL, NULL, '2026-08-15 20:29:48', 'vital'),
(82, 56, 30.67, -1174.35, 84.00, 0.00, 83, 80, NULL, NULL, '2026-08-15 20:35:32', 'immunization'),
(83, 51, 30.67, -1174.35, 84.00, 0.00, 83, 80, NULL, NULL, '2026-08-16 16:16:29', 'immunization'),
(84, 55, 30.67, -1174.35, 84.00, 0.00, 83, 80, NULL, NULL, '2026-08-16 16:32:40', 'vital'),
(85, 54, 30.67, -1174.35, 84.00, 0.00, 83, 80, NULL, NULL, '2026-08-16 16:47:11', 'prenatal'),
(86, 54, 30.67, -1174.35, 84.00, 0.00, 83, 80, NULL, NULL, '2026-08-16 16:51:50', 'family'),
(87, 52, 30.67, -1174.35, 84.00, 0.00, 83, 80, NULL, NULL, '2026-08-16 17:07:54', 'vital');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `doctor` varchar(100) DEFAULT NULL,
  `disease` varchar(100) DEFAULT NULL,
  `room_no` varchar(20) DEFAULT NULL,
  `date_admit` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_services`
--

CREATE TABLE `patient_services` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `service_type` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_services`
--

INSERT INTO `patient_services` (`id`, `user_id`, `service_type`, `created_at`) VALUES
(1, 1, 'Vital Screening', '2026-08-16 02:19:41'),
(2, 55, 'vital', '2026-08-16 02:54:17'),
(3, 54, 'prenatal', '2026-08-16 03:17:46'),
(4, 54, 'immunization', '2026-08-16 03:20:43'),
(5, 54, 'family', '2026-08-16 03:34:17'),
(6, 1, 'immunization', '2026-08-16 04:12:48'),
(7, 51, 'immunization', '2026-08-16 04:19:22'),
(8, 51, 'vital', '2026-08-16 04:29:48'),
(9, 56, 'immunization', '2026-08-16 04:35:32'),
(10, 51, 'immunization', '2026-08-17 00:16:29'),
(11, 55, 'vital', '2026-08-17 00:32:40'),
(12, 54, 'prenatal', '2026-08-17 00:47:11'),
(13, 54, 'family', '2026-08-17 00:51:50'),
(14, 52, 'vital', '2026-08-17 01:07:54');

-- --------------------------------------------------------

--
-- Table structure for table `sensor_readings`
--

CREATE TABLE `sensor_readings` (
  `id` int(11) NOT NULL,
  `temperature` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sensor_readings`
--

INSERT INTO `sensor_readings` (`id`, `temperature`, `created_at`, `user_id`) VALUES
(1, 28.19, '2026-07-11 16:36:22', 0),
(2, 27.99, '2026-07-11 17:06:17', 0),
(3, 27.93, '2026-07-11 17:35:06', 0),
(4, 27.53, '2026-07-11 17:38:23', 0),
(5, 28.99, '2026-07-11 17:39:46', 0),
(6, 27.75, '2026-07-11 17:52:44', 0),
(7, 30.87, '2026-07-15 13:59:37', 0),
(8, 29.97, '2026-07-16 05:25:13', 0),
(9, 26.37, '2026-07-17 19:16:32', 0),
(10, 26.21, '2026-07-17 19:51:20', 0),
(11, 25.99, '2026-07-17 19:51:47', 0),
(12, 26.09, '2026-07-17 20:10:16', 0),
(13, 25.99, '2026-07-17 20:19:39', 0),
(14, 26.21, '2026-07-17 20:49:46', 0),
(15, 25.97, '2026-07-17 21:03:35', 0),
(16, 25.87, '2026-07-17 21:05:32', 0),
(17, 25.81, '2026-07-17 21:05:51', 0),
(18, 25.87, '2026-07-17 21:06:10', 0),
(19, 27.81, '2026-07-26 13:53:39', 0),
(20, 34.23, '2026-07-26 13:54:32', 0),
(21, 31.35, '2026-08-05 14:26:26', 0),
(22, 30.71, '2026-08-05 14:26:36', 0),
(23, 30.77, '2026-08-05 14:26:46', 0),
(24, 35.91, '2026-08-05 14:26:56', 0),
(25, 35.91, '2026-08-05 14:27:06', 0),
(26, 30.77, '2026-08-05 14:36:30', 0),
(27, 30.71, '2026-08-05 14:36:47', 0),
(28, 31.01, '2026-08-05 14:37:04', 0),
(29, 30.85, '2026-08-05 14:41:41', 0),
(30, 30.53, '2026-08-05 14:41:59', 0),
(31, 30.81, '2026-08-05 14:42:17', 0),
(32, 30.67, '2026-08-05 14:43:46', 0),
(33, 30.85, '2026-08-05 14:55:35', 0),
(34, 30.73, '2026-08-05 15:38:27', 0),
(35, 30.73, '2026-08-05 15:39:46', 0),
(36, 30.65, '2026-08-05 15:59:50', 0),
(37, 30.81, '2026-08-05 16:02:24', 0),
(38, 30.63, '2026-08-05 16:03:00', 0),
(39, 30.59, '2026-08-05 16:03:34', 0),
(40, 30.53, '2026-08-05 16:04:18', 0),
(41, 30.71, '2026-08-05 16:13:32', 0),
(42, 31.05, '2026-08-05 16:14:04', 0),
(43, 33.47, '2026-08-05 16:14:36', 0),
(44, 35.39, '2026-08-05 16:15:19', 0),
(45, 30.51, '2026-08-05 16:22:42', 0),
(46, 30.33, '2026-08-05 16:23:15', 0),
(47, 30.37, '2026-08-05 16:23:48', 0),
(48, 30.73, '2026-08-05 16:24:30', 0),
(49, 31.25, '2026-08-05 16:38:25', 0),
(50, 31.07, '2026-08-05 16:38:57', 0),
(51, 35.35, '2026-08-05 16:39:29', 0),
(52, 30.63, '2026-08-05 16:40:12', 0),
(53, 30.71, '2026-08-09 14:18:59', 0),
(54, 30.99, '2026-08-09 14:19:20', 0),
(55, 31.21, '2026-08-09 14:19:39', 0),
(56, 31.11, '2026-08-09 14:20:08', 0),
(57, 30.79, '2026-08-09 14:28:45', 0),
(58, 30.79, '2026-08-09 14:29:04', 0),
(59, 34.83, '2026-08-09 14:29:24', 0),
(60, 30.59, '2026-08-09 14:29:53', 0),
(61, 30.59, '2026-08-09 14:31:54', 0),
(62, 30.59, '2026-08-09 14:33:17', 0),
(63, 30.71, '2026-08-09 14:33:36', 0),
(64, 35.35, '2026-08-09 14:33:58', 0),
(65, 30.67, '2026-08-09 14:34:40', 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `city_municipality` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `code_number` varchar(6) NOT NULL,
  `role` enum('admin','patient') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `contact_number` varchar(20) DEFAULT NULL,
  `service_type` varchar(50) NOT NULL,
  `patient_number` int(11) NOT NULL DEFAULT 0,
  `blood_type` varchar(5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `last_name`, `first_name`, `middle_name`, `suffix`, `age`, `birth_date`, `gender`, `address`, `barangay`, `city_municipality`, `province`, `code_number`, `role`, `created_at`, `contact_number`, `service_type`, `patient_number`, `blood_type`) VALUES
(1, 'System Administrator', NULL, NULL, NULL, NULL, 0, NULL, 'Male', 'System', NULL, NULL, NULL, '123456', 'admin', '2026-07-11 16:21:46', NULL, '', 0, NULL),
(51, 'jhoshua concepcion laurito', 'laurito', 'jhoshua', 'concepcion', '', 21, '2008-12-01', 'Male', 'Simangan, isabel, leyte', 'Simangan', 'isabel', 'leyte', '509394', 'patient', '2026-08-14 14:10:59', '09273111611', 'Vital Screening', 17, 'O+'),
(52, 'jhonreil concepcion Laurito', 'Laurito', 'jhonreil', 'concepcion', '', 19, '2007-04-13', 'Male', 'Simangan, isabel, leyte', 'Simangan', 'isabel', 'leyte', '989188', 'patient', '2026-08-14 15:28:32', '09273111611', 'Vital Screening', 18, 'AB+'),
(53, 'jhasmine concepcion laurito', 'laurito', 'jhasmine', 'concepcion', '', 22, '2004-01-01', 'Female', 'simangan, isabel, leyte', 'simangan', 'isabel', 'leyte', '175578', 'patient', '2026-08-14 15:55:57', '09273111611', 'Family Planning', 19, 'AB+'),
(54, 'kristal  jade almoroto', 'almoroto', 'kristal ', 'jade', '', 23, '2002-12-07', 'Female', 'Balagtas, Matag-ob, leyte', 'Balagtas', 'Matag-ob', 'leyte', '771151', 'patient', '2026-08-14 19:04:26', '09273111611', 'Prenatal Check-up', 20, 'B+'),
(55, 'andrew laurito salar', 'salar', 'andrew', 'laurito', '', 23, '2003-12-07', 'Male', 'Matlang, isabel, leyte', 'Matlang', 'isabel', 'leyte', '821780', 'patient', '2026-08-15 18:50:11', '09273111611', '', 21, 'B+'),
(56, 'john concepcion verna', 'verna', 'john', 'concepcion', '', 21, '2003-12-07', 'Male', 'Sto,Highway, isabel, leyte', 'Sto,Highway', 'isabel', 'leyte', '968235', 'patient', '2026-08-15 20:32:57', '09273111611', '', 22, 'AB+'),
(57, 'mary joy concepcion almereno', 'almereno', 'mary joy', 'concepcion', '', 22, '2003-12-07', 'Female', 'anislag, isabel, leyte', 'anislag', 'isabel', 'leyte', '109400', 'patient', '2026-08-16 17:22:48', '09273111611', '', 23, 'AB+');

-- --------------------------------------------------------

--
-- Table structure for table `weight_readings`
--

CREATE TABLE `weight_readings` (
  `id` int(11) NOT NULL,
  `weight` decimal(6,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `weight_readings`
--

INSERT INTO `weight_readings` (`id`, `weight`, `created_at`, `user_id`) VALUES
(1, 0.00, '2026-07-11 16:36:41', 0),
(2, -1.69, '2026-07-11 17:06:36', 0),
(3, -0.57, '2026-07-11 17:38:44', 0),
(4, 0.00, '2026-07-11 17:40:05', 0),
(5, 0.57, '2026-07-11 17:53:06', 0),
(6, 0.00, '2026-07-15 13:59:55', 0),
(7, -240.89, '2026-07-16 05:25:21', 0),
(8, -294.23, '2026-07-17 19:02:57', 0),
(9, 1068.39, '2026-07-17 19:03:00', 0),
(10, 328.98, '2026-07-17 19:03:02', 0),
(11, -971.54, '2026-07-17 19:51:30', 0),
(12, 350.91, '2026-07-17 19:51:57', 0),
(13, -1188.97, '2026-07-17 20:10:24', 0),
(14, 1427.41, '2026-07-17 20:19:47', 0),
(15, 1724.95, '2026-07-17 20:49:55', 0),
(16, 1767.63, '2026-07-17 21:03:43', 0),
(17, -1477.17, '2026-07-17 21:05:43', 0),
(18, 305.20, '2026-07-17 21:06:01', 0),
(19, -226.34, '2026-07-17 21:06:20', 0),
(20, 1185.64, '2026-07-26 13:53:47', 0),
(21, -370.74, '2026-07-26 13:54:41', 0),
(22, -719.71, '2026-07-26 14:05:35', 0),
(23, 857.36, '2026-07-26 14:11:35', 0),
(24, 316.52, '2026-08-04 14:30:01', 0),
(25, -446.72, '2026-08-04 14:36:04', 0),
(26, 1404.35, '2026-08-04 14:38:50', 0),
(27, 1839.14, '2026-08-04 14:43:05', 0),
(28, -299.40, '2026-08-04 14:46:14', 0),
(29, -1235.97, '2026-08-04 14:48:12', 0),
(30, 128.30, '2026-08-04 14:51:17', 0),
(31, -829.67, '2026-08-04 14:52:54', 0),
(32, 658.61, '2026-08-04 15:00:26', 0),
(33, -764.78, '2026-08-04 15:08:25', 0),
(34, 1882.84, '2026-08-04 15:10:30', 0),
(35, 666.29, '2026-08-04 15:13:05', 0),
(36, 1027.48, '2026-08-04 15:16:59', 0),
(37, -108.80, '2026-08-04 15:17:57', 0),
(38, -625.86, '2026-08-04 15:22:25', 0),
(39, 45.89, '2026-08-04 15:26:32', 0),
(40, -697.46, '2026-08-04 15:27:38', 0),
(41, -300.75, '2026-08-04 15:28:58', 0),
(42, 393.56, '2026-08-04 15:37:29', 0),
(43, 1389.29, '2026-08-04 15:55:10', 0),
(44, 276.63, '2026-08-04 16:14:59', 0),
(45, 101.82, '2026-08-04 17:21:33', 0),
(46, 889.43, '2026-08-04 17:46:18', 0),
(47, 98.89, '2026-08-04 17:52:45', 0),
(48, 497.63, '2026-08-05 14:20:36', 0),
(49, -1089.82, '2026-08-05 14:20:39', 0),
(50, -1406.63, '2026-08-05 14:20:41', 0),
(51, -1305.67, '2026-08-05 14:20:44', 0),
(52, -1933.15, '2026-08-05 14:20:47', 0),
(53, -706.36, '2026-08-05 14:20:49', 0),
(54, 179.66, '2026-08-05 14:20:52', 0),
(55, -1012.01, '2026-08-05 14:20:55', 0),
(56, -198.24, '2026-08-05 14:20:57', 0),
(57, -1471.92, '2026-08-05 14:21:00', 0),
(58, -1486.82, '2026-08-05 14:21:03', 0),
(59, -1132.67, '2026-08-05 14:21:05', 0),
(60, -2373.63, '2026-08-05 14:21:08', 0),
(61, -1729.22, '2026-08-05 14:21:11', 0),
(62, -64.50, '2026-08-05 14:21:13', 0),
(63, 1484.36, '2026-08-05 14:21:16', 0),
(64, -1244.21, '2026-08-05 14:21:19', 0),
(65, -289.47, '2026-08-05 14:21:22', 0),
(66, -880.36, '2026-08-05 14:21:25', 0),
(67, -702.82, '2026-08-05 14:21:27', 0),
(68, -891.22, '2026-08-05 14:21:31', 0),
(69, -1479.51, '2026-08-05 14:21:34', 0),
(70, 1573.67, '2026-08-05 14:36:39', 0),
(71, -1208.30, '2026-08-05 14:36:56', 0),
(72, 18.46, '2026-08-05 14:37:14', 0),
(73, -200.64, '2026-08-05 14:41:50', 0),
(74, -151.84, '2026-08-05 14:42:08', 0),
(75, -237.92, '2026-08-05 14:42:26', 0),
(76, -916.46, '2026-08-05 14:43:55', 0),
(77, 223.66, '2026-08-05 14:55:44', 0),
(78, 1533.09, '2026-08-05 15:38:36', 0),
(79, 13.75, '2026-08-05 15:39:55', 0),
(80, -629.71, '2026-08-05 16:00:00', 0),
(81, -365.30, '2026-08-05 16:02:35', 0),
(82, -861.85, '2026-08-05 16:03:09', 0),
(83, -798.63, '2026-08-05 16:03:43', 0),
(84, 657.44, '2026-08-05 16:04:28', 0),
(85, -1255.16, '2026-08-05 16:13:40', 0),
(86, 112.98, '2026-08-05 16:14:13', 0),
(87, 995.51, '2026-08-05 16:14:45', 0),
(88, 1477.71, '2026-08-05 16:15:28', 0),
(89, -478.65, '2026-08-05 16:22:51', 0),
(90, 213.20, '2026-08-05 16:23:24', 0),
(91, -930.67, '2026-08-05 16:23:56', 0),
(92, -862.70, '2026-08-05 16:24:38', 0),
(93, 841.09, '2026-08-05 16:38:33', 0),
(94, -807.96, '2026-08-05 16:39:06', 0),
(95, 2011.74, '2026-08-05 16:39:39', 0),
(96, -8.67, '2026-08-05 16:40:22', 0),
(97, -338.02, '2026-08-09 14:19:08', 0),
(98, 104.85, '2026-08-09 14:19:29', 0),
(99, 163.10, '2026-08-09 14:19:48', 0),
(100, -270.76, '2026-08-09 14:20:17', 0),
(101, 1065.64, '2026-08-09 14:28:54', 0),
(102, 153.34, '2026-08-09 14:29:13', 0),
(103, 632.74, '2026-08-09 14:29:32', 0),
(104, -139.67, '2026-08-09 14:30:02', 0),
(105, -1788.44, '2026-08-09 14:32:03', 0),
(106, 1136.40, '2026-08-09 14:33:25', 0),
(107, -390.19, '2026-08-09 14:33:44', 0),
(108, -232.23, '2026-08-09 14:34:06', 0),
(109, -1174.35, '2026-08-09 14:34:48', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `height_readings`
--
ALTER TABLE `height_readings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `max30102_readings`
--
ALTER TABLE `max30102_readings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `measurements`
--
ALTER TABLE `measurements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `patient_services`
--
ALTER TABLE `patient_services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sensor_readings`
--
ALTER TABLE `sensor_readings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_number` (`code_number`);

--
-- Indexes for table `weight_readings`
--
ALTER TABLE `weight_readings`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `height_readings`
--
ALTER TABLE `height_readings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `max30102_readings`
--
ALTER TABLE `max30102_readings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `measurements`
--
ALTER TABLE `measurements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_services`
--
ALTER TABLE `patient_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `sensor_readings`
--
ALTER TABLE `sensor_readings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `weight_readings`
--
ALTER TABLE `weight_readings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `measurements`
--
ALTER TABLE `measurements`
  ADD CONSTRAINT `measurements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
