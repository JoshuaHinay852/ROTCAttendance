-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Feb 08, 2026 at 09:19 PM
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
-- Database: `rotc_attendance1`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin') DEFAULT 'admin',
  `account_status` enum('pending','approved','denied') DEFAULT 'pending',
  `status_reason` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `email`, `password`, `full_name`, `role`, `account_status`, `status_reason`, `profile_image`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'wswdw', 'hinayjoshua2004@gmail.com', '$2y$10$neJJlT4CE1mKjHrC/x1VduW8sGFY/nTW6C.zRm765.4M7mvLmd2aK', 'sxmsx', 'admin', 'approved', '', NULL, NULL, '2026-01-31 13:52:03', '2026-02-01 13:06:38'),
(2, 'Hinay', 'joshua@gmail.com', '$2y$10$h26N3yH.TnKhOo3ImcSD7.adjNysA8YUoV9n9jQf0FVTDRhqqvv62', 'Joshua Hinay', 'admin', 'approved', '', NULL, '2026-02-02 10:29:20', '2026-01-31 13:52:58', '2026-02-02 03:06:07'),
(18, 'Luspoc', 'luspoc@gmail.com', '$2y$10$1FD.nNsKYJvMOncm729As.ErWwoJbGgU5dS2xjPjr9i9zcIHT7LG6', 'Luspoc Glenn', 'admin', 'approved', NULL, NULL, '2026-02-08 19:50:50', '2026-02-01 16:26:29', '2026-02-08 11:50:50'),
(23, 'admin', 'admin@rotc.system', 'Hinay123', 'Super Admin', 'admin', 'approved', '', NULL, NULL, '2026-02-02 13:05:40', '2026-02-02 15:53:16'),
(26, 'fbgbgb', 'gbgbt@gmail.com', '$2y$10$JFL6UJZKxLCGu83Tn./7QeTau0KrzwHAuvS5FAmVVvDklyECB6r8.', 'Joshua', 'admin', 'approved', '', NULL, NULL, '2026-02-02 15:42:29', '2026-02-02 15:53:12'),
(31, 'Luspocs', 'luspocs@gmail.com', '$2y$10$PhmauGj84fz51znVkJv5Jul0EX7ONg8nihJ/lRThoawkHWyOMFG6C', 'Sotil Hinay', 'admin', 'approved', '', NULL, NULL, '2026-02-08 04:57:07', '2026-02-08 05:41:05');

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_logs`
--

CREATE TABLE `admin_activity_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_activity_logs`
--

INSERT INTO `admin_activity_logs` (`id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 2, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 02:29:20'),
(2, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 02:31:59'),
(3, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 02:34:17'),
(4, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 02:35:59'),
(5, 18, 'cadet_deleted', 'Deleted cadet: dede (ID: 31)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 02:37:14'),
(6, 18, 'cadet_deleted', 'Deleted cadet: dede (ID: 35)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 02:37:39'),
(7, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 03:00:34'),
(8, 18, 'mp_deleted', 'Deleted MP: mike.johnson03 (ID: 56)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 03:06:27'),
(9, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 09:33:20'),
(10, 18, 'mp_deleted', 'Deleted MP: amanda.taylor10 (ID: 63)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 09:41:44'),
(11, 18, 'cadet_deleted', 'Deleted cadet: maria.reyes (ID: 2)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 09:41:53'),
(12, 18, 'admin_deleted', 'Deleted admin: Luspocs (ID: 19)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 10:00:01'),
(13, 18, 'admin_deleted', 'Deleted admin: fbgbgb (ID: 17)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 10:02:40'),
(14, 18, 'admin_deleted', 'Deleted admin: wswdw (ID: 1)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 10:02:48'),
(15, 18, 'admin_deleted', 'Deleted admin: Hinay (ID: 2)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 10:03:04'),
(16, 18, 'admin_deleted', 'Deleted admin: admin (ID: 11)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 10:03:09'),
(17, 18, 'cadet_deleted', 'Deleted cadet: x c  dsdde (ID: 34)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 10:30:39'),
(18, 18, 'mp_deleted', 'Deleted MP: emily.davis06 (ID: 59)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 10:30:43'),
(19, 18, 'admin_deleted', 'Deleted admin: wswdw (ID: 1)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 10:30:49'),
(20, 18, 'admin_permanently_deleted', 'Permanently deleted admin: wswdw', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 10:37:05'),
(21, 18, 'admin_restored', 'Restored admin: admin (pending approval)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 10:37:12'),
(22, 18, 'admin_restored', 'Restored admin: fbgbgb (pending approval)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 10:37:19'),
(23, 18, 'admin_restored', 'Restored admin: Luspocs (pending approval)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 10:37:19'),
(24, 18, 'cadet_restored', 'Restored cadet: x c  dsdde', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 10:39:01'),
(25, 18, 'mp_deleted', 'Deleted MP: lisa.wilson08 (ID: 61)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 11:20:31'),
(26, 18, 'cadet_deleted', 'Deleted cadet: x c  dsdde (ID: 37)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 11:20:42'),
(27, 18, 'cadet_restored', 'Restored cadet: x c  dsdde', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 11:20:49'),
(28, 18, 'admin_deleted', 'Deleted admin: fbgbgb (ID: 21)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 12:02:05'),
(29, 18, 'mp_deleted', 'Deleted MP: john.doe01 (ID: 54)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 12:02:12'),
(30, 18, 'cadet_deleted', 'Deleted cadet: maria.reyes (ID: 36)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 12:02:16'),
(31, 18, 'cadet_deleted', 'Deleted cadet: x c  dsdde (ID: 38)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 13:05:12'),
(32, 18, 'mp_deleted', 'Deleted MP: robert.moore09 (ID: 62)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 13:05:20'),
(33, 18, 'admin_deleted', 'Deleted admin: admin (ID: 20)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 13:05:30'),
(34, 18, 'admin_restored', 'Restored admin: admin (pending approval)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 13:05:40'),
(35, 18, 'admin_restored', 'Restored admin: fbgbgb (pending approval)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 13:05:46'),
(36, 18, 'cadet_restored', 'Restored cadet: x c  dsdde', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 13:06:01'),
(37, 18, 'cadet_restored', 'Restored cadet: maria.reyes', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 13:06:04'),
(38, 18, 'mp_restored', 'Restored MP: robert.moore09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 14:56:12'),
(39, 18, 'cadet_deleted', 'Deleted cadet: maria.reyes (ID: 40)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 14:57:08'),
(40, 18, 'mp_deleted', 'Deleted MP: robert.moore09 (ID: 64)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 14:57:15'),
(41, 18, 'mp_deleted', 'Deleted MP: alex.brown05 (ID: 58)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 14:57:20'),
(42, 18, 'cadet_deleted', 'Deleted cadet: cdcd (ID: 12)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 14:57:25'),
(43, 18, 'cadet_deleted', 'Deleted cadet: x c  dsdde (ID: 39)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:05:59'),
(44, 18, 'mp_deleted', 'Deleted MP: jane.smith02 (ID: 55)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:06:07'),
(45, 18, 'mp_restored', 'Restored MP: jane.smith02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:06:21'),
(46, 18, 'cadet_restored', 'Restored cadet: x c  dsdde', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:06:21'),
(47, 18, 'cadet_restored', 'Restored cadet: cdcd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:06:21'),
(48, 18, 'mp_restored', 'Restored MP: alex.brown05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:06:21'),
(49, 18, 'mp_restored', 'Restored MP: robert.moore09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:06:21'),
(50, 18, 'cadet_restored', 'Restored cadet: maria.reyes', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:06:21'),
(51, 18, 'mp_restored', 'Restored MP: john.doe01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:06:21'),
(52, 18, 'mp_restored', 'Restored MP: lisa.wilson08', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:06:21'),
(53, 18, 'mp_restored', 'Restored MP: emily.davis06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:06:21'),
(54, 18, 'mp_restored', 'Restored MP: amanda.taylor10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:06:21'),
(55, 18, 'cadet_deleted', 'Deleted cadet: x c  dsdde (ID: 41)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:17:01'),
(56, 18, 'mp_deleted', 'Deleted MP: robert.moore09 (ID: 67)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:17:12'),
(57, 18, 'mp_restored', 'Restored MP: robert.moore09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:17:32'),
(58, 18, 'cadet_restored', 'Restored cadet: x c  dsdde', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:17:32'),
(59, 18, 'admin_deleted', 'Archived admin: fbgbgb (ID: 24)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:41:58'),
(60, 18, 'admin_restored', 'Restored admin: fbgbgb (ID: 24)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:42:06'),
(61, 18, 'admin_permanently_deleted', 'Permanently deleted admin: Hinay', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:42:13'),
(62, 18, 'admin_permanently_deleted', 'Permanently deleted admin: wswdw', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:42:13'),
(63, 18, 'admin_deleted', 'Archived admin: fbgbgb (ID: 25)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:42:21'),
(64, 18, 'admin_restored', 'Restored admin: fbgbgb (ID: 25)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:42:29'),
(65, 18, 'mp_deleted', 'Deleted MP: robert.moore09 (ID: 72)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:42:45'),
(66, 18, 'cadet_deleted', 'Deleted cadet: x c  dsdde (ID: 44)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:42:54'),
(67, 18, 'mp_deleted', 'Deleted MP: emily.davis06 (ID: 70)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:43:04'),
(70, 18, 'admin_deleted', 'Archived admin: Luspocs (ID: 22)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:43:43'),
(71, 18, 'admin_restored', 'Restored admin: Luspocs (ID: 22)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:43:50'),
(72, 18, 'mp_restored', 'Restored MP: emily.davis06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:43:50'),
(73, 18, 'cadet_restored', 'Restored cadet: x c  dsdde', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:43:50'),
(74, 18, 'mp_restored', 'Restored MP: robert.moore09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 15:43:50'),
(75, 18, 'cadet_deleted', 'Archived cadet: x c  dsdde (ID: 45)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 16:27:38'),
(76, 18, 'cadet_restored', 'Restored cadet: x c  dsdde - Status: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 16:27:50'),
(77, 18, 'mp_deleted', 'Archived MP: emily.davis06 (ID: 73)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 16:28:38'),
(78, 18, 'admin_deleted', 'Archived admin: Luspocs (ID: 27)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 16:28:46'),
(79, 18, 'admin_restored', 'Restored admin: Luspocs (ID: 27) - Status: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 16:28:58'),
(80, 18, 'mp_restored', 'Restored MP: emily.davis06 - Status: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 16:28:58'),
(81, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 16:30:52'),
(82, 18, 'cadet_deleted', 'Archived cadet: x c  dsdde (ID: 46)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 16:46:42'),
(83, 18, 'cadet_restored', 'Restored cadet: x c  dsdde - Status: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 16:47:18'),
(84, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 17:51:11'),
(85, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 18:21:28'),
(86, 18, 'cadet_deleted', 'Archived cadet: x c  dsdde (ID: 47)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 18:27:51'),
(87, 18, 'mp_deleted', 'Archived MP: emily.davis06 (ID: 75)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 18:27:56'),
(88, 18, 'admin_deleted', 'Archived admin: Luspocs (ID: 28)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 18:28:01'),
(89, 18, 'admin_restored', 'Restored admin: Luspocs (ID: 28) - Status: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 18:28:10'),
(90, 18, 'mp_restored', 'Restored MP: emily.davis06 - Status: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 18:28:10'),
(91, 18, 'cadet_restored', 'Restored cadet: x c  dsdde - Status: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-02 18:28:10'),
(92, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-03 00:19:02'),
(93, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-03 01:13:59'),
(94, 18, 'mp_deleted', 'Archived MP: fvfvfvfv (ID: 77)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-03 01:38:48'),
(95, 18, 'mp_permanently_deleted', 'Permanently deleted MP: fvfvfvfv', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-03 01:38:56'),
(96, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 00:21:24'),
(97, 18, 'cadet_deleted', 'Archived cadet: x c  dsdde (ID: 48)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 00:21:49'),
(98, 18, 'mp_deleted', 'Archived MP: emily.davis06 (ID: 76)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 00:21:57'),
(99, 18, 'admin_deleted', 'Archived admin: Luspocs (ID: 29)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 00:22:05'),
(100, 18, 'admin_restored', 'Restored admin: Luspocs (ID: 29) - Status: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 00:22:28'),
(101, 18, 'mp_restored', 'Restored MP: emily.davis06 - Status: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 00:22:28'),
(102, 18, 'cadet_restored', 'Restored cadet: x c  dsdde - Status: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 00:22:28'),
(103, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 04:41:40'),
(104, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 06:36:43'),
(105, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 07:11:38'),
(106, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 08:22:20'),
(107, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 08:52:38'),
(108, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 11:18:40'),
(109, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 11:40:04'),
(110, 18, 'event_created', 'Event: dcdc', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 11:40:40'),
(111, 18, 'event_deleted', 'Event ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 11:51:11'),
(112, 18, 'event_created', 'Event: dcdsvd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 11:52:07'),
(113, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 12:11:41'),
(114, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 12:59:35'),
(115, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 13:32:38'),
(116, 18, 'event_deleted', 'Event ID: 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 13:56:35'),
(117, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 14:02:58'),
(118, 18, 'event_created', 'Event: sxsx', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 14:31:04'),
(119, 18, 'qr_regenerated', 'Event ID: 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 14:31:15'),
(120, 18, 'qr_regenerated', 'Event ID: 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 14:31:20'),
(121, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 14:45:22'),
(122, 18, 'event_deleted', 'Event ID: 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 14:45:34'),
(123, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 15:57:15'),
(124, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 23:33:52'),
(125, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-07 00:12:59'),
(126, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-07 00:43:59'),
(127, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-07 02:03:14'),
(128, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-07 03:04:57'),
(129, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-07 04:51:42'),
(130, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-07 06:09:30'),
(131, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-07 21:22:17'),
(132, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 00:08:50'),
(133, 18, 'mp_deleted', 'Archived MP: emily.davis06 (ID: 78)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 00:10:40'),
(134, 18, 'mp_restored', 'Restored MP: emily.davis06 - Status: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 00:10:47'),
(135, 18, 'mp_deleted', 'Archived MP: emily.davis06 (ID: 79)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 01:09:21'),
(136, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 01:32:22'),
(137, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 02:15:09'),
(138, 18, 'cadet_deleted', 'Archived cadet: x c  dsdde (ID: 49)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 02:15:18'),
(139, 18, 'cadet_restored', 'Restored cadet: x c  dsdde - Status: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 02:15:28'),
(140, 18, 'mp_restored', 'Restored MP: emily.davis06 - Status: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 02:15:28'),
(141, 18, 'mp_deleted', 'Archived MP: emily.davis06 (ID: 81)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 04:15:07'),
(142, 18, 'mp_deleted', 'Archived MP: efrfr (ID: 80)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 04:30:42'),
(143, 18, 'mp_deleted', 'Archived MP: amanda.taylor10 (ID: 71)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 04:30:46'),
(144, 18, 'admin_deleted', 'Archived admin: Luspocs (ID: 30)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 04:56:43'),
(145, 18, 'admin_restored', 'Restored admin: Luspocs (ID: 30) - Status: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 04:57:07'),
(146, 18, 'mp_restored', 'Restored MP: amanda.taylor10 - Status: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 04:57:07'),
(147, 18, 'mp_restored', 'Restored MP: efrfr - Status: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 04:57:07'),
(148, 18, 'mp_restored', 'Restored MP: emily.davis06 - Status: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 04:57:07'),
(149, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 05:40:09'),
(150, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 07:06:16'),
(151, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 08:15:56'),
(152, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 08:47:24'),
(153, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 10:15:03'),
(154, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 10:45:16'),
(155, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 11:19:19'),
(156, 18, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 11:50:50'),
(157, 18, 'cadet_deleted', 'Archived cadet: x c  dsdde (ID: 50)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 11:55:47'),
(158, 18, 'cadet_restored', 'Restored cadet: x c  dsdde - Status: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-08 11:56:11');

-- --------------------------------------------------------

--
-- Table structure for table `admin_archives`
--

CREATE TABLE `admin_archives` (
  `id` int(11) NOT NULL,
  `original_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin') DEFAULT 'admin',
  `account_status` enum('pending','approved','denied') DEFAULT 'pending',
  `profile_image` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `deleted_by` int(11) NOT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_status_logs`
--

CREATE TABLE `admin_status_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `previous_status` enum('pending','approved','denied') DEFAULT NULL,
  `new_status` enum('pending','approved','denied') DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `changed_by_admin_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_status_logs`
--

INSERT INTO `admin_status_logs` (`id`, `admin_id`, `previous_status`, `new_status`, `reason`, `changed_by_admin_id`, `created_at`) VALUES
(2, 1, 'approved', 'approved', '', 2, '2026-02-01 13:06:38'),
(14, 2, 'pending', 'pending', '', 18, '2026-02-02 02:32:39'),
(15, 2, 'approved', 'approved', '', 18, '2026-02-02 03:06:07'),
(18, 26, 'approved', 'approved', '', 18, '2026-02-02 15:53:12'),
(19, 23, 'approved', 'approved', '', 18, '2026-02-02 15:53:16'),
(25, 31, 'approved', 'approved', '', 18, '2026-02-08 05:41:05');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `announcement_date` date NOT NULL,
  `announcement_time` time NOT NULL,
  `end_time` time NOT NULL,
  `location` varchar(255) NOT NULL,
  `dress_code` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `target_audience` enum('all','cadets','mp','both') DEFAULT 'all',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('draft','published','expired','cancelled') DEFAULT 'draft',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `published_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `announcement_date`, `announcement_time`, `end_time`, `location`, `dress_code`, `notes`, `target_audience`, `priority`, `status`, `created_by`, `created_at`, `updated_at`, `published_at`, `expires_at`) VALUES
(1, '1st Military Instructions', 'Announcement is a proclamation or declaration of some happening, future event or something that has taken place. Thus letter announces a special event or an occasion that people need to be aware of. Announcement letter can be written under various topics, it could be an announcement of bad weather, a civil emergency, budget surplus, business anniversary, policy or fee amount, savings plan, change of company’s name, work schedule, job opening, new business location, store or branch opening, special meeting, achievement, a new policy, concert, birthday party, wedding ceremony, musical night or an admission schedule.', '2026-02-07', '07:00:00', '17:00:00', 'BISU BALILIHAN', 'Type C', 'Announcement is a proclamation or declaration of some happening, future event or something that has taken place. Thus letter announces a special event or an occasion that people need to be aware of. Announcement letter can be written under various topics, it could be an announcement of bad weather, a civil emergency, budget surplus, business anniversary, policy or fee amount, savings plan, change of company’s name, work schedule, job opening, new business location, store or branch opening, special meeting, achievement, a new policy, concert, birthday party, wedding ceremony, musical night or an admission schedule.', 'all', 'high', 'published', 18, '2026-02-07 06:10:55', '2026-02-07 06:15:26', '2026-02-07 14:15:26', '2026-02-14 17:00:00'),
(3, 'fvf', 'evfevfv', '2026-02-14', '07:00:00', '17:00:00', 'vfvf', 'fevfv', 'fvfeefv', 'all', 'urgent', 'published', 18, '2026-02-07 21:29:49', '2026-02-07 21:30:10', '2026-02-08 05:30:10', '2026-02-21 17:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `announcement_read_logs`
--

CREATE TABLE `announcement_read_logs` (
  `id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('cadet','mp') NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `device_info` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cadet_accounts`
--

CREATE TABLE `cadet_accounts` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `course` varchar(100) NOT NULL,
  `full_address` text NOT NULL,
  `platoon` enum('1','2','3') NOT NULL,
  `company` enum('Alpha','Bravo','Charlie','Delta','Echo','Foxtrot','Golf') NOT NULL,
  `dob` date NOT NULL,
  `mothers_name` varchar(100) NOT NULL,
  `fathers_name` varchar(100) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','denied') DEFAULT 'pending',
  `last_login` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_archived` tinyint(1) DEFAULT 0,
  `archive_reason` text DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cadet_accounts`
--

INSERT INTO `cadet_accounts` (`id`, `username`, `email`, `password`, `first_name`, `last_name`, `middle_name`, `course`, `full_address`, `platoon`, `company`, `dob`, `mothers_name`, `fathers_name`, `profile_image`, `status`, `last_login`, `created_by`, `created_at`, `updated_at`, `is_archived`, `archive_reason`, `archived_by`, `archived_at`) VALUES
(22, 'john.santos', 'john.santos@bisu.edu.ph', '$2y$10$mRgZqI7Yt8pLd9KjF6hB5.HQvW2XcV3nB4sA5dC6eF7gH8iJ9kL0m', 'John', 'Santos', 'Michael', 'BS in Information Technology', '123 Rizal Street, Balilihan, Bohol', '1', 'Alpha', '2002-05-15', 'Maria Santos', 'Juan Santos', NULL, 'approved', NULL, NULL, '2026-02-01 08:48:11', '2026-02-08 01:57:38', 0, NULL, NULL, NULL),
(26, 'carlos.delacruz', 'carlos.delacruz@bisu.edu.ph', '$2y$10$v/PV.DY0ZE7oebPQw9yewOQHyaDCrASFXPy8Vl5hwBur32WMNZjj2', 'Joshua', 'Dela Cruz', 'Juan', 'BS in Criminology', '789 Bonifacio Street, Balilihan, Bohol', '1', 'Charlie', '2001-11-30', 'Teresa Dela Cruz', 'Antonio Dela Cruz', NULL, 'approved', NULL, NULL, '2026-02-01 11:23:09', '2026-02-01 14:46:52', 0, NULL, NULL, NULL),
(42, 'cdcd', 'dcdd@gmail.com', '$2y$10$F0O8T/F6EFapu7NwQ1FKvOtI7zxlwhHNhVIAMskePk.rS7vIF8xWq', 'efefe', 'efef', 'efef', 'efefe', 'efwe', '1', 'Alpha', '2026-02-02', 'efef', 'efefe', NULL, 'approved', NULL, 2, '2026-02-02 15:06:21', '2026-02-02 15:07:24', 0, NULL, NULL, NULL),
(43, 'maria.reyes', 'maria.reyes@bisu.edu.ph', '$2y$10$mRgZqI7Yt8pLd9KjF6hB5.HQvW2XcV3nB4sA5dC6eF7gH8iJ9kL0m', 'Maria', 'Reyes', 'Clara', 'BS in Nursing', '456 Mabini Street, Balilihan, Bohol', '2', 'Bravo', '2003-08-22', 'Carmen Reyes', 'Pedro Reyes', NULL, 'approved', NULL, NULL, '2026-02-02 15:06:21', '2026-02-02 15:07:30', 0, NULL, NULL, NULL),
(51, 'x c  dsdde', 'ededed@gmail.com', '$2y$10$UsxOMyAxovLPawMk2/eiUeKfheBRD3uYhykYuM13FngwMDckr.iWu', 'wdwd', 'crcr', 'rfrf', 'rfr', 'rfrfr', '1', 'Alpha', '2026-02-09', 'rfrfr', 'rfrf', NULL, 'pending', NULL, 2, '2026-02-08 11:56:11', '2026-02-08 11:56:11', 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cadet_archives`
--

CREATE TABLE `cadet_archives` (
  `id` int(11) NOT NULL,
  `original_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `course` varchar(100) NOT NULL,
  `full_address` text NOT NULL,
  `platoon` enum('1','2','3') NOT NULL,
  `company` enum('Alpha','Bravo','Charlie','Delta','Echo','Foxtrot','Golf') NOT NULL,
  `dob` date NOT NULL,
  `mothers_name` varchar(100) NOT NULL,
  `fathers_name` varchar(100) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','dropped','graduated') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `deleted_by` int(11) NOT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cadet_status_logs`
--

CREATE TABLE `cadet_status_logs` (
  `id` int(11) NOT NULL,
  `cadet_id` int(11) NOT NULL,
  `previous_status` enum('pending','approved','denied') DEFAULT NULL,
  `new_status` enum('pending','approved','denied') DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `changed_by_admin_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cadet_status_logs`
--

INSERT INTO `cadet_status_logs` (`id`, `cadet_id`, `previous_status`, `new_status`, `reason`, `changed_by_admin_id`, `created_at`) VALUES
(13, 42, 'approved', 'approved', '', 18, '2026-02-02 15:07:24'),
(14, 43, 'approved', 'approved', '', 18, '2026-02-02 15:07:30'),
(19, 22, 'pending', 'pending', '', 18, '2026-02-06 00:23:16');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `qr_code_data` text DEFAULT NULL,
  `qr_code_path` varchar(255) DEFAULT NULL,
  `status` enum('scheduled','ongoing','completed','cancelled') DEFAULT 'scheduled',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `event_name`, `description`, `event_date`, `start_time`, `end_time`, `location`, `qr_code_data`, `qr_code_path`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(10, 'dwdw', 'wdwd', '2026-02-07', '08:00:00', '17:00:00', 'wdwd', '{\"event_id\":\"10\",\"event_name\":\"dwdw\",\"date\":\"2026-02-07\",\"time\":\"08:00:00\",\"hash\":\"c706f78ff3eab652813e23a07a2151e1\",\"expires\":\"2026-02-14 04:06:09\"}', 'event_10_1770433569.png', 'scheduled', 18, '2026-02-07 03:06:09', '2026-02-07 03:08:50');

-- --------------------------------------------------------

--
-- Table structure for table `event_attendance`
--

CREATE TABLE `event_attendance` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `cadet_id` int(11) NOT NULL,
  `mp_id` int(11) NOT NULL,
  `check_in_time` datetime NOT NULL,
  `check_out_time` datetime DEFAULT NULL,
  `attendance_status` enum('present','late','absent','excused') DEFAULT 'present',
  `remarks` text DEFAULT NULL,
  `qr_code_verified` tinyint(1) DEFAULT 0,
  `verification_method` enum('qr_scan','manual','api') DEFAULT 'qr_scan',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_qr_logs`
--

CREATE TABLE `event_qr_logs` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `qr_code_data` text NOT NULL,
  `generated_by` int(11) NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `scan_count` int(11) DEFAULT 0,
  `last_scan_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_qr_logs`
--

INSERT INTO `event_qr_logs` (`id`, `event_id`, `qr_code_data`, `generated_by`, `generated_at`, `expires_at`, `is_active`, `scan_count`, `last_scan_at`) VALUES
(1, 10, '{\"event_id\":\"10\",\"event_name\":\"dwdw\",\"date\":\"2026-02-07\",\"time\":\"08:00:00\",\"hash\":\"c706f78ff3eab652813e23a07a2151e1\",\"expires\":\"2026-02-14 04:06:09\"}', 18, '2026-02-07 03:06:09', '2026-02-14 04:06:09', 1, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(50) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `success` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `ip_address`, `username`, `attempted_at`, `success`) VALUES
(1, '::1', 'hinay', '2026-01-31 13:57:06', 1),
(2, '::1', 'hinay', '2026-01-31 14:00:15', 1),
(3, '::1', 'hinay', '2026-01-31 14:34:59', 1),
(4, '::1', 'admin', '2026-01-31 14:40:26', 0),
(5, '::1', 'admin', '2026-01-31 14:44:06', 0),
(6, '::1', 'admin', '2026-01-31 14:46:45', 0),
(7, '::1', 'admin', '2026-01-31 14:47:10', 0),
(8, '::1', 'hinay', '2026-01-31 14:48:14', 1),
(9, '::1', 'Hinay', '2026-01-31 19:10:38', 1),
(10, '::1', 'Hinay', '2026-02-01 06:03:52', 0),
(11, '::1', 'Hinay', '2026-02-01 06:04:00', 1),
(12, '::1', 'Hinay', '2026-02-01 06:29:08', 1),
(13, '::1', 'Hinay', '2026-02-01 06:29:17', 1),
(14, '::1', 'Hinay', '2026-02-01 08:33:29', 1),
(15, '::1', 'Luspoc', '2026-02-01 16:26:44', 0),
(16, '::1', 'Luspoc', '2026-02-01 16:26:52', 0),
(17, '::1', 'hinay', '2026-02-01 16:27:29', 0),
(18, '::1', 'hinay', '2026-02-01 16:27:33', 0),
(19, '::1', 'hinay', '2026-02-01 16:27:47', 0),
(20, '::1', 'hinay', '2026-02-01 16:27:58', 0),
(21, '::1', 'admin', '2026-02-01 16:29:25', 0),
(22, '::1', 'admin', '2026-02-01 16:29:36', 0),
(23, '::1', 'admin', '2026-02-01 16:30:35', 0),
(24, '::1', 'admin', '2026-02-01 16:30:48', 0),
(25, '::1', 'admin', '2026-02-01 16:31:06', 0),
(26, '::1', 'Luspoc', '2026-02-01 16:33:42', 0),
(27, '::1', 'Admin', '2026-02-01 16:34:03', 0),
(28, '::1', 'admin', '2026-02-01 16:34:31', 0),
(29, '::1', 'admin', '2026-02-01 16:36:05', 0),
(30, '::1', 'admin', '2026-02-01 16:36:42', 0),
(31, '::1', 'Luspoc', '2026-02-01 23:14:50', 0),
(32, '::1', 'Admin', '2026-02-01 23:16:52', 0),
(33, '::1', 'Luspoc', '2026-02-01 23:29:49', 0),
(34, '::1', 'Luspoc', '2026-02-02 00:40:52', 1),
(35, '::1', 'Luspocss', '2026-02-02 00:42:14', 0),
(36, '::1', 'Hinay', '2026-02-02 00:43:36', 0),
(37, '::1', 'Luspoc', '2026-02-02 00:44:06', 1);

-- --------------------------------------------------------

--
-- Table structure for table `mp_accounts`
--

CREATE TABLE `mp_accounts` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `course` varchar(100) NOT NULL,
  `full_address` text NOT NULL,
  `platoon` enum('1','2','3') NOT NULL,
  `company` enum('Alpha','Bravo','Charlie','Delta','Echo','Foxtrot','Golf') NOT NULL,
  `dob` date NOT NULL,
  `mothers_name` varchar(100) NOT NULL,
  `fathers_name` varchar(100) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','denied') DEFAULT 'pending',
  `last_login` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_archived` tinyint(1) DEFAULT 0,
  `archived_at` timestamp NULL DEFAULT NULL,
  `archive_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mp_accounts`
--

INSERT INTO `mp_accounts` (`id`, `username`, `email`, `password`, `first_name`, `last_name`, `middle_name`, `course`, `full_address`, `platoon`, `company`, `dob`, `mothers_name`, `fathers_name`, `profile_image`, `status`, `last_login`, `created_by`, `created_at`, `updated_at`, `is_archived`, `archived_at`, `archive_reason`) VALUES
(57, 'sara.williams04', 'sara.w@military.edu', 'hashed_password_101', 'Sara', 'Williams', 'Marie', 'BS Intelligence', '321 Elm St, Camp Pendleton, CA', '1', 'Delta', '2000-01-30', 'Jennifer Williams', 'James Williams', 'profile_04.jpg', 'approved', '2024-01-15 16:10:00', 1, '2026-02-02 03:05:39', '2026-02-02 03:05:39', 0, NULL, NULL),
(60, 'david.miller07', 'david.m@military.edu', 'hashed_password_415', 'David', 'Miller', 'William', 'BS Engineering', '147 Birch St, Fort Benning, GA', '1', 'Golf', '1997-12-25', 'Susan Miller', 'Thomas Miller', 'profile_07.jpg', 'approved', '2024-01-14 13:30:00', 1, '2026-02-02 03:05:39', '2026-02-02 03:05:39', 1, NULL, NULL),
(65, 'jane.smith02', 'jane.smith@military.edu', 'hashed_password_456', 'Jane', 'Smith', 'Elizabeth', 'BS Leadership', '456 Oak Ave, West Point, NY', '2', 'Bravo', '1999-07-22', 'Sarah Smith', 'Michael Smith', 'profile_02.jpg', 'approved', '2024-01-14 14:45:00', 1, '2026-02-02 15:06:21', '2026-02-02 15:06:54', 0, NULL, NULL),
(66, 'alex.brown05', 'alex.b@military.edu', 'hashed_password_112', 'Alexander', 'Brown', 'Thomas', 'BS Logistics', '654 Cedar Ln, Fort Hood, TX', '2', 'Echo', '1998-09-12', 'Patricia Brown', 'Richard Brown', 'profile_05.jpg', 'approved', '2024-01-05 08:15:00', 1, '2026-02-02 15:06:21', '2026-02-02 15:06:58', 0, NULL, NULL),
(68, 'john.doe01', 'john.doe@military.edu', 'hashed_password_123', 'John', 'Doe', 'Alexander', 'BS Military Science', '123 Main St, Fort Bragg, NC', '1', 'Alpha', '1998-03-15', 'Mary Doe', 'Robert Doe', 'profile_01.jpg', 'approved', '2024-01-15 09:30:00', 1, '2026-02-02 15:06:21', '2026-02-02 16:28:22', 0, NULL, NULL),
(69, 'lisa.wilson08', 'lisa.w@military.edu', 'hashed_password_161', 'Lisa', 'Wilson', 'Ann', 'BS Military History', '258 Spruce Ave, Fort Campbell, KY', '2', 'Alpha', '2000-06-08', 'Karen Wilson', 'Edward Wilson', 'profile_08.jpg', 'approved', NULL, 1, '2026-02-02 15:06:21', '2026-02-02 16:28:27', 0, NULL, NULL),
(74, 'robert.moore09', 'robert.m@military.edu', '$2y$10$NXZv/qiL3ne10RYGf09/0.Tpgr2PKuNMtV3Sz2QgpRaofbFwr.ThW', 'Robert', 'Moore', 'Joseph', 'BS Cybersecurity', '369 Walnut Blvd, Fort Carson, CO', '3', 'Bravo', '1998-08-14', 'Barbara Moore', 'George Moore', 'profile_09.jpg', 'approved', '2024-01-13 15:45:00', 1, '2026-02-02 15:43:50', '2026-02-08 04:30:29', 0, NULL, NULL),
(82, 'amanda.taylor10', 'amanda.t@military.edu', 'hashed_password_192', 'Amanda', 'Taylor', 'Rose', 'BS Medicine', '741 Hickory Ct, Fort Sam Houston, TX', '1', 'Charlie', '1999-02-28', 'Jessica Taylor', 'Andrew Taylor', 'profile_10.jpg', 'pending', '2024-01-15 07:20:00', 1, '2026-02-08 04:57:07', '2026-02-08 04:57:07', 0, NULL, NULL),
(83, 'efrfr', 'rfrfrgggrg@gmail.com', '$2y$10$auMZ91gduPtaKKfY3L.OhOdQX3rWUpop20uOuIux5nloB7OoqmI1a', 'Jhonajay', 'Jhonjat', 'ede', 'ede', 'edw', '1', 'Alpha', '2026-02-09', 'ewdwe', 'edw', NULL, 'pending', NULL, 18, '2026-02-08 04:57:07', '2026-02-08 04:57:07', 0, NULL, NULL),
(84, 'emily.davis06', 'emily.d@military.edu', '$2y$10$m.gyN76d4fg1FiF4so7DL.oN5chnsqlQeXV4AExIJgc0OjyDE579W', 'Emily', 'Davis', 'Grace', 'BS Communications', '987 Maple Dr, Fort Lewis, WA', '3', 'Foxtrot', '1999-04-18', 'Nancy Davis', 'Charles Davis', 'profile_06.jpg', 'pending', NULL, 1, '2026-02-08 04:57:07', '2026-02-08 04:57:07', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `mp_archives`
--

CREATE TABLE `mp_archives` (
  `id` int(11) NOT NULL,
  `original_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `course` varchar(100) NOT NULL,
  `full_address` text NOT NULL,
  `platoon` enum('1','2','3') NOT NULL,
  `company` enum('Alpha','Bravo','Charlie','Delta','Echo','Foxtrot','Golf') NOT NULL,
  `dob` date NOT NULL,
  `mothers_name` varchar(100) NOT NULL,
  `fathers_name` varchar(100) NOT NULL,
  `mp_rank` enum('Private','Private First Class','Corporal','Sergeant','Staff Sergeant','Sergeant First Class') DEFAULT 'Private',
  `profile_image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','suspended','discharged') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `deleted_by` int(11) NOT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qr_scan_logs`
--

CREATE TABLE `qr_scan_logs` (
  `id` int(11) NOT NULL,
  `qr_id` int(11) NOT NULL,
  `cadet_id` int(11) NOT NULL,
  `mp_id` int(11) DEFAULT NULL,
  `scan_time` datetime NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `verification_status` enum('success','failed','expired','tampered') DEFAULT 'success',
  `verification_details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- Indexes for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `admin_archives`
--
ALTER TABLE `admin_archives`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_deleted_at` (`deleted_at`),
  ADD KEY `idx_deleted_by` (`deleted_by`);

--
-- Indexes for table `admin_status_logs`
--
ALTER TABLE `admin_status_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `changed_by_admin_id` (`changed_by_admin_id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_announcement_date` (`announcement_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_target_audience` (`target_audience`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `announcement_read_logs`
--
ALTER TABLE `announcement_read_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_announcement_user` (`announcement_id`,`user_id`,`user_type`),
  ADD KEY `idx_announcement_id` (`announcement_id`),
  ADD KEY `idx_user` (`user_id`,`user_type`),
  ADD KEY `idx_read_at` (`read_at`);

--
-- Indexes for table `cadet_accounts`
--
ALTER TABLE `cadet_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_platoon` (`platoon`),
  ADD KEY `idx_company` (`company`),
  ADD KEY `idx_course` (`course`),
  ADD KEY `archived_by` (`archived_by`);

--
-- Indexes for table `cadet_archives`
--
ALTER TABLE `cadet_archives`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_deleted_at` (`deleted_at`),
  ADD KEY `idx_deleted_by` (`deleted_by`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `cadet_status_logs`
--
ALTER TABLE `cadet_status_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cadet_id` (`cadet_id`),
  ADD KEY `changed_by_admin_id` (`changed_by_admin_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_date` (`event_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `event_attendance`
--
ALTER TABLE `event_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_event_cadet` (`event_id`,`cadet_id`),
  ADD KEY `idx_event_id` (`event_id`),
  ADD KEY `idx_cadet_id` (`cadet_id`),
  ADD KEY `idx_mp_id` (`mp_id`),
  ADD KEY `idx_check_in_time` (`check_in_time`);

--
-- Indexes for table `event_qr_logs`
--
ALTER TABLE `event_qr_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_id` (`event_id`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `generated_by` (`generated_by`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_attempted_at` (`attempted_at`);

--
-- Indexes for table `mp_accounts`
--
ALTER TABLE `mp_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_platoon` (`platoon`),
  ADD KEY `idx_company` (`company`),
  ADD KEY `idx_course` (`course`);

--
-- Indexes for table `mp_archives`
--
ALTER TABLE `mp_archives`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_deleted_at` (`deleted_at`),
  ADD KEY `idx_deleted_by` (`deleted_by`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `qr_scan_logs`
--
ALTER TABLE `qr_scan_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_qr_id` (`qr_id`),
  ADD KEY `idx_cadet_id` (`cadet_id`),
  ADD KEY `idx_scan_time` (`scan_time`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=159;

--
-- AUTO_INCREMENT for table `admin_archives`
--
ALTER TABLE `admin_archives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `admin_status_logs`
--
ALTER TABLE `admin_status_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `announcement_read_logs`
--
ALTER TABLE `announcement_read_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cadet_accounts`
--
ALTER TABLE `cadet_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `cadet_archives`
--
ALTER TABLE `cadet_archives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `cadet_status_logs`
--
ALTER TABLE `cadet_status_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `event_attendance`
--
ALTER TABLE `event_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `event_qr_logs`
--
ALTER TABLE `event_qr_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `mp_accounts`
--
ALTER TABLE `mp_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `mp_archives`
--
ALTER TABLE `mp_archives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qr_scan_logs`
--
ALTER TABLE `qr_scan_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_archives`
--
ALTER TABLE `admin_archives`
  ADD CONSTRAINT `admin_archives_ibfk_1` FOREIGN KEY (`deleted_by`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `admin_status_logs`
--
ALTER TABLE `admin_status_logs`
  ADD CONSTRAINT `admin_status_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_status_logs_ibfk_2` FOREIGN KEY (`changed_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `announcement_read_logs`
--
ALTER TABLE `announcement_read_logs`
  ADD CONSTRAINT `announcement_read_logs_ibfk_1` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cadet_accounts`
--
ALTER TABLE `cadet_accounts`
  ADD CONSTRAINT `cadet_accounts_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `cadet_accounts_ibfk_2` FOREIGN KEY (`archived_by`) REFERENCES `admins` (`id`);

--
-- Constraints for table `cadet_archives`
--
ALTER TABLE `cadet_archives`
  ADD CONSTRAINT `cadet_archives_ibfk_1` FOREIGN KEY (`deleted_by`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cadet_archives_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cadet_status_logs`
--
ALTER TABLE `cadet_status_logs`
  ADD CONSTRAINT `cadet_status_logs_ibfk_1` FOREIGN KEY (`cadet_id`) REFERENCES `cadet_accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cadet_status_logs_ibfk_2` FOREIGN KEY (`changed_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_attendance`
--
ALTER TABLE `event_attendance`
  ADD CONSTRAINT `event_attendance_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_attendance_ibfk_2` FOREIGN KEY (`cadet_id`) REFERENCES `cadet_accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_attendance_ibfk_3` FOREIGN KEY (`mp_id`) REFERENCES `mp_accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_qr_logs`
--
ALTER TABLE `event_qr_logs`
  ADD CONSTRAINT `event_qr_logs_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_qr_logs_ibfk_2` FOREIGN KEY (`generated_by`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mp_accounts`
--
ALTER TABLE `mp_accounts`
  ADD CONSTRAINT `mp_accounts_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`);

--
-- Constraints for table `mp_archives`
--
ALTER TABLE `mp_archives`
  ADD CONSTRAINT `mp_archives_ibfk_1` FOREIGN KEY (`deleted_by`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mp_archives_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `qr_scan_logs`
--
ALTER TABLE `qr_scan_logs`
  ADD CONSTRAINT `qr_scan_logs_ibfk_1` FOREIGN KEY (`qr_id`) REFERENCES `event_qr_codes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `qr_scan_logs_ibfk_2` FOREIGN KEY (`cadet_id`) REFERENCES `cadet_accounts` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
