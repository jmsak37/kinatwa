-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 16, 2026 at 10:00 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kinatwa_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` tinyint(4) NOT NULL DEFAULT 2,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `restore_until` datetime DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password_hash`, `role`, `created_by`, `created_at`, `is_active`, `deleted_at`, `restore_until`, `email`) VALUES
(5, 'jmsak', '$2y$10$P003ZeeMefO7kYlEGcr72.fDFIt6iI748KWLmENML8JL2SVqiPwZ2', 1, NULL, '2026-03-15 09:50:31', 1, NULL, NULL, 'jmsak37@gmail.com'),
(6, 'jmsaka', '$2y$10$XOsjFNSiCmUenRHZL2xY7ecqCsX0iRBHqaRoaWQXd4j4eGhV5TQ7K', 2, 5, '2026-03-15 10:12:58', 1, NULL, NULL, 'jmsak377@gmail.com'),
(8, 'jmsakaaaa', '$2y$10$1xAcuG7U76U4mndZaXX5xuaZpulXaF5MlXvcAdYSNejzMM4ol2qYW', 2, 5, '2026-03-15 18:07:12', 1, NULL, NULL, 'jmsak37ii@gmail.com'),
(10, 'jmsakAAW', '$2y$10$lbM/4ZqSXeHwdwKXf/RsK.nYYuwmsiEhWyJ2LROhZIvi6N4rGlaz2', 2, 5, '2026-03-16 11:07:53', 1, NULL, NULL, 'jmsak9937@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `admin_code_requests`
--

CREATE TABLE `admin_code_requests` (
  `id` int(11) NOT NULL,
  `requested_username` varchar(100) NOT NULL,
  `requested_email` varchar(255) NOT NULL DEFAULT '',
  `status` enum('pending','approved','rejected','accepted','expired') NOT NULL DEFAULT 'pending',
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_code` varchar(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_code_requests`
--

INSERT INTO `admin_code_requests` (`id`, `requested_username`, `requested_email`, `status`, `requested_at`, `responded_at`, `approved_by`, `approved_code`) VALUES
(14, 'jmsakAAW', 'jmsak9937@gmail.com', 'expired', '2026-03-16 11:05:41', '2026-03-16 11:06:42', NULL, NULL),
(15, 'jmsakAAW', 'jmsak9937@gmail.com', 'rejected', '2026-03-16 11:06:51', '2026-03-16 11:07:11', 5, NULL),
(16, 'jmsakAAW', 'jmsak9937@gmail.com', 'accepted', '2026-03-16 11:07:39', '2026-03-16 11:07:53', 5, '097067');

-- --------------------------------------------------------

--
-- Table structure for table `deleted_admin_accounts`
--

CREATE TABLE `deleted_admin_accounts` (
  `id` int(11) NOT NULL,
  `original_admin_id` int(11) DEFAULT NULL,
  `admin_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` tinyint(4) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `deleted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `restore_until` datetime NOT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `delete_code` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `display_state`
--

CREATE TABLE `display_state` (
  `id` int(11) NOT NULL,
  `forced_file_id` int(11) DEFAULT NULL,
  `priority_action` varchar(50) DEFAULT NULL,
  `play_type` varchar(50) NOT NULL DEFAULT 'loop',
  `minutes` int(11) NOT NULL DEFAULT 5,
  `start_time` varchar(10) DEFAULT NULL,
  `active_until` datetime DEFAULT NULL,
  `scheduled_file_id` int(11) DEFAULT NULL,
  `scheduled_action` varchar(50) DEFAULT NULL,
  `scheduled_play_type` varchar(50) DEFAULT NULL,
  `scheduled_minutes` int(11) DEFAULT NULL,
  `scheduled_time` varchar(10) DEFAULT NULL,
  `admin_message` text DEFAULT NULL,
  `admin_message_until` datetime DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `display_state`
--

INSERT INTO `display_state` (`id`, `forced_file_id`, `priority_action`, `play_type`, `minutes`, `start_time`, `active_until`, `scheduled_file_id`, `scheduled_action`, `scheduled_play_type`, `scheduled_minutes`, `scheduled_time`, `admin_message`, `admin_message_until`, `version`, `updated_at`) VALUES
(1, 14, 'play_now', 'loop', 5, NULL, '2026-03-16 12:01:41', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 58, '2026-03-16 11:56:41');

-- --------------------------------------------------------

--
-- Table structure for table `media_files`
--

CREATE TABLE `media_files` (
  `id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `preview_path` varchar(500) DEFAULT NULL,
  `file_ext` varchar(20) DEFAULT NULL,
  `file_type` enum('image','video','pdf','docx','pptx','text','other') NOT NULL DEFAULT 'other',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `fit_mode` varchar(50) NOT NULL DEFAULT 'fit_both',
  `upload_type` varchar(50) NOT NULL DEFAULT 'auto_resize',
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `play_seconds` int(11) NOT NULL DEFAULT 6,
  `play_order` int(11) NOT NULL DEFAULT 0,
  `show_bottom_messages` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `media_files`
--

INSERT INTO `media_files` (`id`, `file_name`, `original_name`, `file_path`, `preview_path`, `file_ext`, `file_type`, `enabled`, `fit_mode`, `upload_type`, `uploaded_by`, `uploaded_at`, `play_seconds`, `play_order`, `show_bottom_messages`) VALUES
(10, 'KINATWA.mp4', 'KINATWA.mp4', 'C:\\xampp\\htdocs\\kinatwa\\uploads\\KINAT\\KINATWA.mp4', NULL, '.mp4', 'video', 1, 'fit_both', 'auto_resize', NULL, '2026-03-14 15:17:55', 65, 3, 1),
(11, 'kinatwa1.png.png', 'kinatwa1.png.png', 'C:\\xampp\\htdocs\\kinatwa\\uploads\\KINAT\\kinatwa1.png.png', NULL, '.png', 'image', 1, 'fit_both', 'auto_resize', NULL, '2026-03-14 15:17:55', 6, 2, 1),
(13, 'freq.mp4', 'freq.mp4', 'C:\\xampp\\htdocs\\kinatwa\\uploads\\KINAT\\freq.mp4', NULL, '.mp4', 'video', 1, 'fit_both', 'auto_resize', 5, '2026-03-16 11:37:13', 151, 4, 1),
(14, '2a85c5ce-a89b-429c-ad04-290a783b05bd-7.png', '2a85c5ce-a89b-429c-ad04-290a783b05bd-7.png', 'C:\\xampp\\htdocs\\kinatwa\\uploads\\KINAT\\2a85c5ce-a89b-429c-ad04-290a783b05bd-7.png', NULL, '.png', 'image', 1, 'fit_both', 'auto_resize', 5, '2026-03-16 11:49:10', 6, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link_url` varchar(500) DEFAULT NULL,
  `target_role` enum('all','main_admin','normal_admin') NOT NULL DEFAULT 'all',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `security_codes`
--

CREATE TABLE `security_codes` (
  `id` int(11) NOT NULL,
  `code` varchar(6) NOT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `intended_username` varchar(100) DEFAULT NULL,
  `intended_email` varchar(255) DEFAULT NULL,
  `request_id` int(11) DEFAULT NULL,
  `status` enum('pending','used','expired','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `used_by` int(11) DEFAULT NULL,
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `security_codes`
--

INSERT INTO `security_codes` (`id`, `code`, `generated_by`, `intended_username`, `intended_email`, `request_id`, `status`, `created_at`, `expires_at`, `used_by`, `used_at`) VALUES
(18, '097067', 5, 'jmsakAAW', 'jmsak9937@gmail.com', 16, 'used', '2026-03-16 11:07:46', '2026-03-16 11:08:46', 10, '2026-03-16 11:07:53');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_admin_created_by` (`created_by`),
  ADD KEY `idx_admins_email` (`email`);

--
-- Indexes for table `admin_code_requests`
--
ALTER TABLE `admin_code_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_request_approved_by` (`approved_by`);

--
-- Indexes for table `deleted_admin_accounts`
--
ALTER TABLE `deleted_admin_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `display_state`
--
ALTER TABLE `display_state`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_display_forced_file` (`forced_file_id`),
  ADD KEY `fk_display_scheduled_file` (`scheduled_file_id`);

--
-- Indexes for table `media_files`
--
ALTER TABLE `media_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_media_uploaded_by` (`uploaded_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `security_codes`
--
ALTER TABLE `security_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_code_generated_by` (`generated_by`),
  ADD KEY `fk_code_used_by` (`used_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `admin_code_requests`
--
ALTER TABLE `admin_code_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `deleted_admin_accounts`
--
ALTER TABLE `deleted_admin_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `media_files`
--
ALTER TABLE `media_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `security_codes`
--
ALTER TABLE `security_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `fk_admin_created_by` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `admin_code_requests`
--
ALTER TABLE `admin_code_requests`
  ADD CONSTRAINT `fk_request_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `display_state`
--
ALTER TABLE `display_state`
  ADD CONSTRAINT `fk_display_forced_file` FOREIGN KEY (`forced_file_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_display_scheduled_file` FOREIGN KEY (`scheduled_file_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `media_files`
--
ALTER TABLE `media_files`
  ADD CONSTRAINT `fk_media_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `security_codes`
--
ALTER TABLE `security_codes`
  ADD CONSTRAINT `fk_code_generated_by` FOREIGN KEY (`generated_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_code_used_by` FOREIGN KEY (`used_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
