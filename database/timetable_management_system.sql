-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 03, 2025 at 02:20 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE DATABASE IF NOT EXISTS `timetable_management_system`;
USE `timetable_management_system`;


--
-- Database: `timetable_management_system`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `CheckSchedulingConflicts` (IN `p_faculty_id` INT, IN `p_classroom_id` INT, IN `p_slot_id` INT, IN `p_semester` INT, IN `p_academic_year` VARCHAR(10), OUT `p_has_conflict` BOOLEAN, OUT `p_conflict_message` TEXT)   BEGIN
    DECLARE faculty_conflict_count INT DEFAULT 0;
    DECLARE classroom_conflict_count INT DEFAULT 0;
    
    -- Check faculty conflict
    SELECT COUNT(*) INTO faculty_conflict_count
    FROM timetables 
    WHERE faculty_id = p_faculty_id 
        AND slot_id = p_slot_id 
        AND semester = p_semester 
        AND academic_year = p_academic_year 
        AND is_active = TRUE;
    
    -- Check classroom conflict
    SELECT COUNT(*) INTO classroom_conflict_count
    FROM timetables 
    WHERE classroom_id = p_classroom_id 
        AND slot_id = p_slot_id 
        AND semester = p_semester 
        AND academic_year = p_academic_year 
        AND is_active = TRUE;
    
    -- Set results
    IF faculty_conflict_count > 0 THEN
        SET p_has_conflict = TRUE;
        SET p_conflict_message = 'Faculty member is already scheduled at this time slot';
    ELSEIF classroom_conflict_count > 0 THEN
        SET p_has_conflict = TRUE;
        SET p_conflict_message = 'Classroom is already booked at this time slot';
    ELSE
        SET p_has_conflict = FALSE;
        SET p_conflict_message = 'No conflicts detected';
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `CleanupExpiredData` ()   BEGIN
    -- Clean up expired verification tokens (older than 24 hours)
    UPDATE users 
    SET verification_token = NULL 
    WHERE verification_token IS NOT NULL 
      AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
      AND email_verified = FALSE;
    
    -- Clean up expired password reset tokens (older than 1 hour)
    UPDATE users 
    SET password_reset_token = NULL, password_reset_expires = NULL
    WHERE password_reset_token IS NOT NULL 
      AND password_reset_expires < NOW();
    
    -- Clean up old read notifications (older than 30 days)
    DELETE FROM notifications 
    WHERE is_read = TRUE 
      AND read_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- Clean up old audit logs (older than 1 year, keep only admin actions)
    DELETE FROM audit_logs 
    WHERE timestamp < DATE_SUB(NOW(), INTERVAL 1 YEAR)
      AND action NOT LIKE 'ADMIN_%';
      
    -- Clean up expired remember tokens
    DELETE FROM remember_tokens WHERE expires_at < NOW();
    
    -- Clean up old inactive remember tokens (older than 7 days)
    DELETE FROM remember_tokens 
    WHERE is_active = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
      
    SELECT 'Cleanup completed successfully (including remember tokens)' as status;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `CleanupRememberTokens` ()   BEGIN
    DECLARE deleted_expired INT DEFAULT 0;
    DECLARE deleted_inactive INT DEFAULT 0;
    
    -- Delete expired tokens
    DELETE FROM remember_tokens WHERE expires_at < NOW();
    SET deleted_expired = ROW_COUNT();
    
    -- Delete inactive tokens older than 7 days
    DELETE FROM remember_tokens 
    WHERE is_active = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
    SET deleted_inactive = ROW_COUNT();
    
    -- Log cleanup results
    SELECT CONCAT('Remember token cleanup completed. Expired: ', deleted_expired, ', Inactive: ', deleted_inactive) as status;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetDashboardStats` (IN `p_user_role` VARCHAR(20), OUT `p_total_users` INT, OUT `p_pending_registrations` INT, OUT `p_active_timetables` INT, OUT `p_total_subjects` INT)   BEGIN
    -- Total users
    SELECT COUNT(*) INTO p_total_users FROM users WHERE status = 'active';
    
    -- Pending registrations
    SELECT COUNT(*) INTO p_pending_registrations FROM users WHERE status = 'pending';
    
    -- Active timetables
    SELECT COUNT(*) INTO p_active_timetables FROM timetables WHERE is_active = TRUE;
    
    -- Total subjects
    SELECT COUNT(*) INTO p_total_subjects FROM subjects WHERE is_active = TRUE;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `ResetLoginAttempts` ()   BEGIN
    UPDATE users 
    SET login_attempts = 0, last_attempt_time = NULL
    WHERE login_attempts > 0 
      AND (last_attempt_time IS NULL OR last_attempt_time < DATE_SUB(NOW(), INTERVAL 1 HOUR));
      
    SELECT CONCAT('Reset login attempts for ', ROW_COUNT(), ' users') as status;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin_profiles`
--

CREATE TABLE `admin_profiles` (
  `admin_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `department` varchar(100) NOT NULL,
  `designation` varchar(50) NOT NULL DEFAULT 'System Administrator',
  `phone` varchar(15) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `office_location` varchar(50) DEFAULT NULL,
  `emergency_contact` varchar(100) DEFAULT NULL,
  `emergency_phone` varchar(15) DEFAULT NULL,
  `date_joined` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_profiles`
--

INSERT INTO `admin_profiles` (`admin_id`, `user_id`, `employee_id`, `first_name`, `last_name`, `department`, `designation`, `phone`, `bio`, `office_location`, `emergency_contact`, `emergency_phone`, `date_joined`, `created_at`, `updated_at`) VALUES
(1, 1, 'ADMIN001', 'System', 'Administrator', 'Information Technology', 'System Administrator', '0544250759', NULL, NULL, NULL, '0544250759', '2025-07-24', '2025-07-24 11:34:26', '2025-07-24 14:43:04');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_affected` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `table_affected`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `timestamp`, `description`) VALUES
(1, 1, 'CREATE_USER', 'users', 1, NULL, '{\"role\": \"admin\", \"email\": \"admin@university.edu\", \"status\": \"active\"}', NULL, NULL, NULL, '2025-06-25 22:17:50', NULL),
(2, 2, 'CREATE_USER', 'users', 2, NULL, '{\"role\": \"faculty\", \"email\": \"john.smith@university.edu\", \"status\": \"active\"}', NULL, NULL, NULL, '2025-06-25 22:17:50', NULL),
(3, 3, 'CREATE_USER', 'users', 3, NULL, '{\"role\": \"faculty\", \"email\": \"mary.johnson@university.edu\", \"status\": \"active\"}', NULL, NULL, NULL, '2025-06-25 22:17:50', NULL),
(4, 4, 'CREATE_USER', 'users', 4, NULL, '{\"role\": \"faculty\", \"email\": \"david.williams@university.edu\", \"status\": \"active\"}', NULL, NULL, NULL, '2025-06-25 22:17:50', NULL),
(5, NULL, 'CREATE_USER', 'users', 5, NULL, '{\"role\": \"faculty\", \"email\": \"lisa.brown@university.edu\", \"status\": \"active\"}', NULL, NULL, NULL, '2025-06-25 22:17:50', NULL),
(6, NULL, 'CREATE_USER', 'users', 6, NULL, '{\"role\": \"faculty\", \"email\": \"michael.davis@university.edu\", \"status\": \"active\"}', NULL, NULL, NULL, '2025-06-25 22:17:50', NULL),
(7, NULL, 'CREATE_USER', 'users', 7, NULL, '{\"role\": \"student\", \"email\": \"alice.wilson@student.university.edu\", \"status\": \"active\"}', NULL, NULL, NULL, '2025-06-25 22:17:51', NULL),
(8, 8, 'CREATE_USER', 'users', 8, NULL, '{\"role\": \"student\", \"email\": \"bob.taylor@student.university.edu\", \"status\": \"active\"}', NULL, NULL, NULL, '2025-06-25 22:17:51', NULL),
(9, 9, 'CREATE_USER', 'users', 9, NULL, '{\"role\": \"student\", \"email\": \"carol.anderson@student.university.edu\", \"status\": \"active\"}', NULL, NULL, NULL, '2025-06-25 22:17:51', NULL),
(10, NULL, 'CREATE_USER', 'users', 10, NULL, '{\"role\": \"student\", \"email\": \"david.thomas@student.university.edu\", \"status\": \"active\"}', NULL, NULL, NULL, '2025-06-25 22:17:51', NULL),
(11, NULL, 'CREATE_USER', 'users', 11, NULL, '{\"role\": \"student\", \"email\": \"eve.jackson@student.university.edu\", \"status\": \"active\"}', NULL, NULL, NULL, '2025-06-25 22:17:51', NULL),
(12, NULL, 'CREATE_USER', 'users', 12, NULL, '{\"role\": \"faculty\", \"email\": \"odarteypaul690@mail.com\", \"status\": \"pending\"}', NULL, NULL, NULL, '2025-06-25 22:45:58', NULL),
(13, 13, 'CREATE_USER', 'users', 13, NULL, '{\"role\": \"student\", \"email\": \"odarteypaul690@mail.com\", \"status\": \"pending\"}', NULL, NULL, NULL, '2025-06-28 11:34:24', NULL),
(14, 14, 'CREATE_USER', 'users', 14, NULL, '{\"role\": \"faculty\", \"email\": \"obibinipaulson@gmail.com\", \"status\": \"pending\"}', NULL, NULL, NULL, '2025-06-28 12:21:56', NULL),
(15, 15, 'CREATE_USER', 'users', 15, NULL, '{\"role\": \"student\", \"email\": \"gladys10@gmail.com\", \"status\": \"active\"}', NULL, NULL, NULL, '2025-06-28 18:55:24', NULL),
(16, 16, 'CREATE_USER', 'users', 16, NULL, '{\"role\": \"faculty\", \"email\": \"doris1010@gmail.com\", \"status\": \"pending\"}', NULL, NULL, NULL, '2025-06-29 16:54:37', NULL),
(17, 17, 'CREATE_USER', 'users', 17, NULL, '{\"role\": \"student\", \"email\": \"podartey001@st.ug.edu.gh\", \"status\": \"pending\"}', NULL, NULL, NULL, '2025-07-04 11:43:40', NULL),
(18, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-04 15:38:49', 'Updated user profile'),
(19, 1, 'CREATE_BACKUP', 'backup', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 09:58:28', 'Database backup created: backup_2025_07_06_09_58_21.sql'),
(20, 1, 'CREATE_BACKUP', 'backup', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 09:58:48', 'Database backup created: backup_2025_07_06_09_58_47.sql'),
(21, 1, 'CREATE_BACKUP', 'backup', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 10:16:39', 'Database backup created: backup_2025_07_06_10_16_33.sql'),
(22, 1, 'CREATE_BACKUP', 'backup', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 10:57:28', 'Database backup created: backup_2025_07_06_10_57_26.sql'),
(23, 1, 'CREATE_BACKUP', 'backup', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 12:39:03', 'Database backup created: backup_2025_07_06_12_38_59.sql'),
(24, 1, 'CREATE_BACKUP', 'backup', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 12:39:14', 'Database backup created: backup_2025_07_06_12_39_13.sql'),
(25, 1, 'UPDATE_BACKUP_SETTINGS', 'system_settings', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 12:39:30', 'Backup settings updated'),
(26, 1, 'UPDATE_BACKUP_SETTINGS', 'system_settings', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 12:39:48', 'Backup settings updated'),
(27, 1, 'UPDATE_BACKUP_SETTINGS', 'system_settings', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 12:54:45', 'Backup settings updated'),
(28, 1, 'UPDATE_BACKUP_SETTINGS', 'system_settings', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 12:54:58', 'Backup settings updated'),
(29, 1, 'UPDATE_BACKUP_SETTINGS', 'system_settings', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 12:55:19', 'Backup settings updated'),
(30, 1, 'UPDATE_BACKUP_SETTINGS', 'system_settings', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 12:55:25', 'Backup settings updated'),
(31, 1, 'UPDATE_BACKUP_SETTINGS', 'system_settings', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 12:55:31', 'Backup settings updated'),
(32, 1, 'UPDATE_BACKUP_SETTINGS', 'system_settings', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 12:55:33', 'Backup settings updated'),
(33, 1, 'UPDATE_BACKUP_SETTINGS', 'system_settings', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 12:55:40', 'Backup settings updated'),
(34, 1, 'UPDATE_BACKUP_SETTINGS', 'system_settings', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 12:57:51', 'Backup settings updated'),
(35, 1, 'UPDATE_BACKUP_SETTINGS', 'system_settings', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 12:58:18', 'Backup settings updated'),
(36, 1, 'UPDATE_BACKUP_SETTINGS', 'system_settings', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 12:58:25', 'Backup settings updated'),
(37, 1, 'CREATE_BACKUP', 'backup', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 12:59:09', 'Database backup created: backup_2025_07_06_12_59_07.sql'),
(38, 1, 'CREATE_BACKUP', 'backup', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 13:01:15', 'Database backup created: backup_2025_07_06_13_01_14.sql'),
(39, 1, 'CREATE_BACKUP', 'backup', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 13:01:21', 'Database backup created: backup_2025_07_06_13_01_20.sql'),
(40, 1, 'CREATE_BACKUP', 'backup', 0, NULL, NULL, NULL, NULL, NULL, '2025-07-06 13:05:06', 'Database backup created: backup_2025_07_06_13_05_05.sql'),
(41, 1, 'SYSTEM_MAINTENANCE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 13:29:42', 'Maintenance completed: 0 logs, 0 notifications, 3 login resets'),
(42, 1, 'CREATE_BACKUP', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 13:56:24', 'Created full backup: daily_backup_2025_07_06_13_56_21.sql (Frequency: daily)'),
(43, 1, 'CREATE_BACKUP', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 14:11:09', 'Created full backup: manual_backup_2025_07_06_14_11_05.sql (Frequency: manual)'),
(44, 1, 'CREATE_BACKUP', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 15:21:50', 'Created full backup: manual_backup_2025_07_06_15_21_47.sql (Frequency: manual)'),
(45, 1, 'CHANGE_PASSWORD', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 15:57:45', 'Password changed successfully'),
(46, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:00:51', 'Updated user profile'),
(47, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:01:21', 'Updated setting: user_pref_timezone'),
(48, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:01:21', 'Updated setting: user_pref_date_format'),
(49, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:01:21', 'Updated setting: user_pref_time_format'),
(50, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:01:21', 'Updated setting: user_pref_notifications_email'),
(51, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:01:21', 'Updated setting: user_pref_notifications_system'),
(52, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:01:21', 'Updated setting: user_pref_language'),
(53, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:01:22', 'Updated setting: user_pref_theme'),
(54, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:01:52', 'Updated setting: user_pref_timezone'),
(55, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:01:52', 'Updated setting: user_pref_date_format'),
(56, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:01:52', 'Updated setting: user_pref_time_format'),
(57, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:01:52', 'Updated setting: user_pref_notifications_email'),
(58, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:01:52', 'Updated setting: user_pref_notifications_system'),
(59, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:01:52', 'Updated setting: user_pref_language'),
(60, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:01:52', 'Updated setting: user_pref_theme'),
(61, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:02:50', 'Updated setting: user_pref_timezone'),
(62, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:02:50', 'Updated setting: user_pref_date_format'),
(63, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:02:50', 'Updated setting: user_pref_time_format'),
(64, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:02:50', 'Updated setting: user_pref_notifications_email'),
(65, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:02:50', 'Updated setting: user_pref_notifications_system'),
(66, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:02:50', 'Updated setting: user_pref_language'),
(67, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:02:50', 'Updated setting: user_pref_theme'),
(68, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:13:13', 'Updated user profile'),
(69, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:30:15', 'Updated user profile'),
(70, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:30:20', 'Updated user profile'),
(71, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:30:54', 'Updated user profile'),
(72, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:31:05', 'Updated user profile'),
(73, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:42:04', 'Updated setting: user_pref_timezone'),
(74, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:42:07', 'Updated setting: user_pref_date_format'),
(75, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:42:07', 'Updated setting: user_pref_time_format'),
(76, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:42:07', 'Updated setting: user_pref_notifications_email'),
(77, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:42:07', 'Updated setting: user_pref_notifications_system'),
(78, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:42:07', 'Updated setting: user_pref_language'),
(79, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:42:07', 'Updated setting: user_pref_theme'),
(80, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:42:19', 'Updated user profile'),
(81, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:42:54', 'Updated setting: user_pref_timezone'),
(82, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:42:54', 'Updated setting: user_pref_date_format'),
(83, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:42:54', 'Updated setting: user_pref_time_format'),
(84, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:42:54', 'Updated setting: user_pref_notifications_email'),
(85, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:42:55', 'Updated setting: user_pref_notifications_system'),
(86, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:42:55', 'Updated setting: user_pref_language'),
(87, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:42:55', 'Updated setting: user_pref_theme'),
(88, 1, 'CREATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:46:38', 'Created setting: user_pref_timezone'),
(89, 1, 'CREATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:46:38', 'Created setting: user_pref_timezone'),
(90, 1, 'CREATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:46:38', 'Created setting: user_pref_date_format'),
(91, 1, 'CREATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:46:38', 'Created setting: user_pref_date_format'),
(92, 1, 'CREATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:46:39', 'Created setting: user_pref_time_format'),
(93, 1, 'CREATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:46:39', 'Created setting: user_pref_time_format'),
(94, 1, 'CREATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:46:39', 'Created setting: user_pref_notifications_email'),
(95, 1, 'CREATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:46:39', 'Created setting: user_pref_notifications_email'),
(96, 1, 'CREATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:46:39', 'Created setting: user_pref_notifications_system'),
(97, 1, 'CREATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:46:39', 'Created setting: user_pref_notifications_system'),
(98, 1, 'CREATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:46:39', 'Created setting: user_pref_language'),
(99, 1, 'CREATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:46:39', 'Created setting: user_pref_language'),
(100, 1, 'CREATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:46:40', 'Created setting: user_pref_theme'),
(101, 1, 'CREATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:46:40', 'Created setting: user_pref_theme'),
(102, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:47:03', 'Updated setting: user_pref_timezone'),
(103, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:47:03', 'Updated setting: user_pref_date_format'),
(104, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:47:03', 'Updated setting: user_pref_time_format'),
(105, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:47:03', 'Updated setting: user_pref_notifications_email'),
(106, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:47:03', 'Updated setting: user_pref_notifications_system'),
(107, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:47:03', 'Updated setting: user_pref_language'),
(108, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:47:03', 'Updated setting: user_pref_theme'),
(109, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:48:14', 'Updated setting: user_pref_timezone'),
(110, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:48:14', 'Updated setting: user_pref_date_format'),
(111, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:48:14', 'Updated setting: user_pref_time_format'),
(112, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:48:15', 'Updated setting: user_pref_notifications_email'),
(113, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:48:15', 'Updated setting: user_pref_notifications_system'),
(114, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:48:15', 'Updated setting: user_pref_language'),
(115, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:48:15', 'Updated setting: user_pref_theme'),
(116, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:53:54', 'Updated setting: user_pref_timezone'),
(117, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:53:54', 'Updated setting: user_pref_date_format'),
(118, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:53:54', 'Updated setting: user_pref_time_format'),
(119, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:53:56', 'Updated setting: user_pref_notifications_email'),
(120, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:53:56', 'Updated setting: user_pref_notifications_system'),
(121, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:53:56', 'Updated setting: user_pref_language'),
(122, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:53:56', 'Updated setting: user_pref_theme'),
(123, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:54:40', 'Updated setting: user_pref_timezone'),
(124, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:54:40', 'Updated setting: user_pref_date_format'),
(125, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:54:41', 'Updated setting: user_pref_time_format'),
(126, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:54:41', 'Updated setting: user_pref_notifications_email'),
(127, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:54:41', 'Updated setting: user_pref_notifications_system'),
(128, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:54:41', 'Updated setting: user_pref_language'),
(129, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:54:41', 'Updated setting: user_pref_theme'),
(130, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:55:12', 'Updated setting: user_pref_timezone'),
(131, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:55:12', 'Updated setting: user_pref_date_format'),
(132, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:55:12', 'Updated setting: user_pref_time_format'),
(133, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:55:13', 'Updated setting: user_pref_notifications_email'),
(134, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:55:13', 'Updated setting: user_pref_notifications_system'),
(135, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:55:13', 'Updated setting: user_pref_language'),
(136, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:55:13', 'Updated setting: user_pref_theme'),
(137, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:55:47', 'Updated setting: user_pref_timezone'),
(138, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:55:47', 'Updated setting: user_pref_date_format'),
(139, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:55:47', 'Updated setting: user_pref_time_format'),
(140, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:55:48', 'Updated setting: user_pref_notifications_email'),
(141, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:55:48', 'Updated setting: user_pref_notifications_system'),
(142, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:55:48', 'Updated setting: user_pref_language'),
(143, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:55:48', 'Updated setting: user_pref_theme'),
(144, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:56:08', 'Updated setting: user_pref_timezone'),
(145, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:56:08', 'Updated setting: user_pref_date_format'),
(146, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:56:09', 'Updated setting: user_pref_time_format'),
(147, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:56:09', 'Updated setting: user_pref_notifications_email'),
(148, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:56:09', 'Updated setting: user_pref_notifications_system'),
(149, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:56:09', 'Updated setting: user_pref_language'),
(150, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 16:56:09', 'Updated setting: user_pref_theme'),
(151, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:18:32', 'Updated setting: user_pref_timezone'),
(152, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:18:32', 'Updated setting: user_pref_date_format'),
(153, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:18:32', 'Updated setting: user_pref_time_format'),
(154, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:18:33', 'Updated setting: user_pref_notifications_email'),
(155, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:18:33', 'Updated setting: user_pref_notifications_system'),
(156, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:18:33', 'Updated setting: user_pref_language'),
(157, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:18:33', 'Updated setting: user_pref_theme'),
(158, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:24:57', 'Updated setting: user_pref_timezone'),
(159, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:24:59', 'Updated setting: user_pref_date_format'),
(160, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:24:59', 'Updated setting: user_pref_time_format'),
(161, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:24:59', 'Updated setting: user_pref_notifications_email'),
(162, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:24:59', 'Updated setting: user_pref_notifications_system'),
(163, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:25:00', 'Updated setting: user_pref_language'),
(164, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:25:00', 'Updated setting: user_pref_theme'),
(165, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:25:02', 'Updated user profile'),
(166, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:25:11', 'Updated user profile'),
(167, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:27:12', 'Updated user profile'),
(168, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:27:18', 'Updated user profile'),
(169, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:27:22', 'Updated user profile'),
(170, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:27:59', 'Updated user profile'),
(171, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:31:53', 'Updated user profile'),
(172, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:32:00', 'Updated user profile'),
(173, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:32:05', 'Updated user profile'),
(174, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:33:46', 'Updated user profile'),
(175, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:43:09', 'Updated user profile'),
(176, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:43:17', 'Updated user profile'),
(177, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:44:18', 'Updated setting: user_pref_timezone'),
(178, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:44:18', 'Updated setting: user_pref_date_format'),
(179, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:44:18', 'Updated setting: user_pref_time_format'),
(180, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:44:19', 'Updated setting: user_pref_notifications_email'),
(181, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:44:19', 'Updated setting: user_pref_notifications_system'),
(182, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:44:19', 'Updated setting: user_pref_language'),
(183, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:44:19', 'Updated setting: user_pref_theme'),
(184, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:44:33', 'Updated setting: user_pref_timezone'),
(185, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:44:33', 'Updated setting: user_pref_date_format'),
(186, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:44:33', 'Updated setting: user_pref_time_format'),
(187, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:44:33', 'Updated setting: user_pref_notifications_email'),
(188, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:44:33', 'Updated setting: user_pref_notifications_system'),
(189, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:44:33', 'Updated setting: user_pref_language'),
(190, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-06 17:44:33', 'Updated setting: user_pref_theme'),
(191, 18, 'CREATE_USER', 'users', 18, NULL, '{\"role\": \"faculty\", \"email\": \"frank.owusu@university.edu\", \"status\": \"active\"}', NULL, NULL, NULL, '2025-07-09 14:10:46', NULL),
(192, 19, 'CREATE_USER', 'users', 19, NULL, '{\"role\": \"student\", \"email\": \"grace.adjei@student.university.edu\", \"status\": \"active\"}', NULL, NULL, NULL, '2025-07-09 14:11:20', NULL),
(193, 20, 'CREATE_USER', 'users', 20, NULL, '{\"role\": \"faculty\", \"email\": \"kingluckycollins@gmail.com\", \"status\": \"active\"}', NULL, NULL, NULL, '2025-07-09 20:03:03', NULL),
(194, 21, 'CREATE_USER', 'users', 21, NULL, '{\"role\": \"student\", \"email\": \"dianalamptey@gmail.com\", \"status\": \"active\"}', NULL, NULL, NULL, '2025-07-11 15:11:58', NULL),
(195, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-23 21:07:14', 'Updated user profile'),
(196, 1, 'SYSTEM_MAINTENANCE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-24 14:00:11', 'Maintenance completed: 0 logs, 0 notifications, 2 login resets'),
(197, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-24 14:42:19', 'Updated user profile'),
(198, 1, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-24 14:43:04', 'Updated user profile'),
(199, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-24 14:44:09', 'Updated setting: user_pref_theme'),
(200, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-24 14:44:09', 'Updated setting: user_pref_language'),
(201, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-24 14:44:09', 'Updated setting: user_pref_timezone'),
(202, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-24 14:44:09', 'Updated setting: user_pref_date_format'),
(203, 1, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-24 14:44:09', 'Updated setting: user_pref_notifications_email'),
(204, 1, 'CREATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-24 14:44:09', 'Created setting: user_pref_notifications_browser'),
(205, 1, 'CREATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-24 14:44:10', 'Created setting: user_pref_notifications_browser'),
(206, 1, 'CREATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-24 14:44:10', 'Created setting: user_pref_dashboard_widgets'),
(207, 1, 'CREATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-24 14:44:10', 'Created setting: user_pref_dashboard_widgets'),
(208, 1, 'CREATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-24 14:44:10', 'Created setting: user_pref_sidebar_collapsed'),
(209, 1, 'CREATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-24 14:44:10', 'Created setting: user_pref_sidebar_collapsed'),
(210, 1, 'TIMETABLE_DELETE', 'timetables', 13, NULL, '{\"timetable_id\":13,\"subject_id\":7,\"faculty_id\":2,\"classroom_id\":2,\"slot_id\":68,\"section\":\"A\",\"semester\":1,\"academic_year\":\"2025-2026\",\"batch_year\":null,\"max_students\":null,\"created_at\":\"2025-06-25 22:17:51\",\"created_by\":1,\"modified_at\":\"2025-07-25 21:45:14\",\"modified_by\":null,\"is_active\":1,\"notes\":null,\"subject_code\":\"MATH101\",\"subject_name\":\"Calculus I\",\"subject_department\":\"Mathematics\",\"faculty_name\":\"Mary Johnson\",\"employee_id\":\"FAC002\",\"room_number\":\"102\",\"building\":\"Main Building\",\"classroom_capacity\":60,\"day_of_week\":\"Friday\",\"start_time\":\"07:30:00\",\"end_time\":\"09:20:00\",\"slot_name\":\"Morning 1\",\"time_range\":\"07:30 AM - 09:20 AM\"}', NULL, NULL, NULL, '2025-07-25 21:48:31', 'Timetable DELETE operation performed'),
(211, 1, 'TIMETABLE_UPDATE', 'timetables', 5, NULL, '{\"subject_id\":15,\"faculty_id\":1,\"classroom_id\":3,\"slot_id\":56,\"section\":\"A\",\"semester\":1,\"academic_year\":\"2025-2026\",\"batch_year\":null,\"max_students\":null,\"notes\":null,\"modified_by\":1}', NULL, NULL, NULL, '2025-07-25 21:50:48', 'Timetable UPDATE operation performed'),
(212, 1, 'TIMETABLE_DELETE', 'timetables', 19, NULL, '{\"timetable_id\":19,\"subject_id\":7,\"faculty_id\":2,\"classroom_id\":4,\"slot_id\":61,\"section\":\"A\",\"semester\":1,\"academic_year\":\"2025-2026\",\"batch_year\":null,\"max_students\":40,\"created_at\":\"2025-07-25 15:04:31\",\"created_by\":1,\"modified_at\":\"2025-07-25 21:43:25\",\"modified_by\":null,\"is_active\":1,\"notes\":null,\"subject_code\":\"MATH101\",\"subject_name\":\"Calculus I\",\"subject_department\":\"Mathematics\",\"faculty_name\":\"Mary Johnson\",\"employee_id\":\"FAC002\",\"room_number\":\"201\",\"building\":\"Main Building\",\"classroom_capacity\":60,\"day_of_week\":\"Wednesday\",\"start_time\":\"09:30:00\",\"end_time\":\"11:20:00\",\"slot_name\":\"Morning 2\",\"time_range\":\"09:30 AM - 11:20 AM\"}', NULL, NULL, NULL, '2025-07-25 22:14:20', 'Timetable DELETE operation performed'),
(213, 1, 'TIMETABLE_DELETE', 'timetables', 14, NULL, '{\"timetable_id\":14,\"subject_id\":11,\"faculty_id\":3,\"classroom_id\":14,\"slot_id\":69,\"section\":\"A\",\"semester\":1,\"academic_year\":\"2025-2026\",\"batch_year\":null,\"max_students\":null,\"created_at\":\"2025-06-25 22:17:51\",\"created_by\":1,\"modified_at\":\"2025-07-25 21:45:14\",\"modified_by\":null,\"is_active\":1,\"notes\":null,\"subject_code\":\"PHY102\",\"subject_name\":\"Physics Lab I\",\"subject_department\":\"Physics\",\"faculty_name\":\"David Williams\",\"employee_id\":\"FAC003\",\"room_number\":\"SCI-101\",\"building\":\"Science Building\",\"classroom_capacity\":35,\"day_of_week\":\"Friday\",\"start_time\":\"09:30:00\",\"end_time\":\"11:20:00\",\"slot_name\":\"Morning 2\",\"time_range\":\"09:30 AM - 11:20 AM\"}', NULL, NULL, NULL, '2025-07-25 22:14:52', 'Timetable DELETE operation performed'),
(214, 1, 'LOGIN_SUCCESS', 'users', 1, NULL, NULL, NULL, NULL, NULL, '2025-07-27 09:10:09', 'Successful login from IP: ::1'),
(215, 22, 'CREATE_USER', 'users', 22, NULL, '{\"role\": \"faculty\", \"email\": \"comfortamanuah70@gmail.com\", \"status\": \"pending\"}', NULL, NULL, NULL, '2025-08-01 19:49:25', NULL),
(216, NULL, 'CREATE_USER', 'users', 23, NULL, '{\"role\": \"student\", \"email\": \"provencalcollins@gmail.com\", \"status\": \"pending\"}', NULL, NULL, NULL, '2025-08-01 20:26:54', NULL),
(217, NULL, 'CREATE_USER', 'users', 24, NULL, '{\"role\": \"student\", \"email\": \"provencalcollins@gmail.com\", \"status\": \"pending\"}', NULL, NULL, NULL, '2025-08-01 21:09:02', NULL),
(218, 20, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-02 22:36:24', 'Updated user profile'),
(219, 20, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-02 22:40:27', 'Updated setting: user_pref_theme'),
(220, 20, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-02 22:40:27', 'Updated setting: user_pref_timezone'),
(221, 20, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-02 22:40:27', 'Updated setting: user_pref_date_format'),
(222, 20, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-02 22:40:27', 'Updated setting: user_pref_time_format'),
(223, 20, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-02 22:40:27', 'Updated setting: user_pref_notifications_email'),
(224, 20, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-02 22:40:27', 'Updated setting: user_pref_notifications_system'),
(225, 20, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-02 22:40:27', 'Updated setting: user_pref_language'),
(226, 20, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-02 22:46:10', 'Updated setting: user_pref_theme'),
(227, 20, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-02 22:46:10', 'Updated setting: user_pref_timezone'),
(228, 20, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-02 22:46:10', 'Updated setting: user_pref_date_format'),
(229, 20, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-02 22:46:10', 'Updated setting: user_pref_time_format'),
(230, 20, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-02 22:46:10', 'Updated setting: user_pref_notifications_email'),
(231, 20, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-02 22:46:10', 'Updated setting: user_pref_notifications_system'),
(232, 20, 'UPDATE_SETTING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-02 22:46:10', 'Updated setting: user_pref_language'),
(233, 20, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-02 22:46:33', 'Updated user profile'),
(234, 20, 'UPDATE_PROFILE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-02 22:47:01', 'Updated user profile'),
(235, 25, 'CREATE_USER', 'users', 25, NULL, '{\"role\": \"student\", \"email\": \"checkfinal1@gmail.com\", \"status\": \"pending\"}', NULL, NULL, NULL, '2025-08-03 00:21:15', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `backup_logs`
--

CREATE TABLE `backup_logs` (
  `backup_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `backup_type` enum('full','structure','data') NOT NULL DEFAULT 'full',
  `file_size` bigint(20) NOT NULL DEFAULT 0,
  `frequency` enum('daily','weekly','monthly','manual') DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('completed','failed','in_progress','deleted') DEFAULT 'completed',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `backup_logs`
--

INSERT INTO `backup_logs` (`backup_id`, `filename`, `backup_type`, `file_size`, `frequency`, `description`, `status`, `created_by`, `created_at`, `deleted_at`, `deleted_by`) VALUES
(1, 'daily_backup_2025_07_06_13_56_21.sql', 'full', 69485, 'daily', NULL, 'completed', 1, '2025-07-06 13:56:24', NULL, NULL),
(2, 'manual_backup_2025_07_06_14_11_05.sql', 'full', 69811, 'manual', 'Quick backup from settings dashboard', 'completed', 1, '2025-07-06 14:11:09', NULL, NULL),
(3, 'manual_backup_2025_07_06_15_21_47.sql', 'full', 69875, 'manual', 'Quick backup from settings dashboard', 'completed', 1, '2025-07-06 15:21:50', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `classrooms`
--

CREATE TABLE `classrooms` (
  `classroom_id` int(11) NOT NULL,
  `room_number` varchar(20) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `is_shared` tinyint(1) DEFAULT 0,
  `building` varchar(50) NOT NULL,
  `floor` int(11) DEFAULT NULL,
  `capacity` int(11) NOT NULL CHECK (`capacity` > 0),
  `type` enum('lecture','lab','seminar','auditorium') DEFAULT 'lecture',
  `facilities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`facilities`)),
  `equipment` text DEFAULT NULL,
  `status` enum('available','maintenance','reserved','closed') DEFAULT 'available',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `classrooms`
--

INSERT INTO `classrooms` (`classroom_id`, `room_number`, `department_id`, `is_shared`, `building`, `floor`, `capacity`, `type`, `facilities`, `equipment`, `status`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '101', NULL, 1, 'Main Building', 1, 60, 'lecture', NULL, 'Projector, Whiteboard, Air Conditioning', 'available', 1, '2025-06-25 22:17:50', '2025-07-28 14:20:19'),
(2, '102', NULL, 1, 'Main Building', 1, 60, 'lecture', NULL, 'Projector, Whiteboard, Air Conditioning', 'available', 1, '2025-06-25 22:17:50', '2025-07-28 14:20:19'),
(3, '103', NULL, 1, 'Main Building', 1, 80, 'lecture', NULL, 'Projector, Smart Board, Air Conditioning, Audio System', 'available', 1, '2025-06-25 22:17:50', '2025-07-28 14:20:19'),
(4, '201', NULL, 1, 'Main Building', 2, 60, 'lecture', NULL, 'Projector, Whiteboard, Air Conditioning', 'available', 1, '2025-06-25 22:17:50', '2025-07-28 14:20:19'),
(5, '202', NULL, 1, 'Main Building', 2, 60, 'lecture', NULL, 'Projector, Whiteboard, Air Conditioning', 'available', 0, '2025-06-25 22:17:50', '2025-07-28 14:20:19'),
(6, '203', NULL, 1, 'Main Building', 2, 100, 'auditorium', NULL, 'Projector, Audio System, Air Conditioning, Stage', 'available', 1, '2025-06-25 22:17:50', '2025-07-28 14:20:19'),
(7, 'CS-101', 1, 0, 'CS Building', 1, 40, 'lab', NULL, 'Computers, Projector, Air Conditioning, Network Access', 'available', 1, '2025-06-25 22:17:50', '2025-07-28 14:20:18'),
(8, 'CS-102', 1, 0, 'CS Building', 1, 40, 'lab', NULL, 'Computers, Projector, Air Conditioning, Network Access', 'available', 0, '2025-06-25 22:17:50', '2025-07-28 14:20:18'),
(9, 'CS-201', 1, 0, 'CS Building', 2, 50, 'lecture', NULL, 'Projector, Smart Board, Air Conditioning', 'available', 1, '2025-06-25 22:17:50', '2025-08-02 08:58:14'),
(10, 'CS-202', 1, 0, 'CS Building', 2, 30, 'seminar', NULL, 'Projector, Whiteboard, Air Conditioning', 'available', 0, '2025-06-25 22:17:50', '2025-07-28 14:20:18'),
(11, 'ENG-101', 4, 0, 'Engineering Building', 1, 30, 'lab', NULL, 'Equipment, Safety Gear, Projector', 'available', 0, '2025-06-25 22:17:50', '2025-07-28 14:20:18'),
(12, 'ENG-102', 4, 0, 'Engineering Building', 1, 30, 'lab', NULL, 'Equipment, Safety Gear, Projector', 'available', 0, '2025-06-25 22:17:50', '2025-07-28 14:20:18'),
(13, 'ENG-201', 4, 0, 'Engineering Building', 2, 40, 'lecture', NULL, 'Projector, Whiteboard, Air Conditioning', 'available', 1, '2025-06-25 22:17:50', '2025-07-28 14:20:18'),
(14, 'SCI-101', 3, 0, 'Science Building', 1, 35, 'lab', NULL, 'Lab Equipment, Fume Hood, Safety Equipment', 'available', 0, '2025-06-25 22:17:50', '2025-07-28 14:20:18'),
(15, 'SCI-102', 3, 0, 'Science Building', 1, 35, 'lab', NULL, 'Lab Equipment, Fume Hood, Safety Equipment', 'available', 1, '2025-06-25 22:17:50', '2025-07-28 14:20:18'),
(17, '205', NULL, 1, 'Main Building', NULL, 70, 'lecture', NULL, '', 'available', 0, '2025-07-02 10:48:17', '2025-07-28 14:20:19'),
(18, '210', NULL, 1, 'Main Building', 3, 80, 'lecture', '{\"facilities\":[\"Computer Lab\",\" Audio System\"]}', '', 'available', 0, '2025-07-02 11:00:00', '2025-07-28 14:20:19');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `department_code` varchar(10) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `department_head_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `established_date` date DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(15) DEFAULT NULL,
  `building_location` varchar(50) DEFAULT NULL,
  `budget_allocation` decimal(15,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `department_code`, `department_name`, `department_head_id`, `description`, `established_date`, `contact_email`, `contact_phone`, `building_location`, `budget_allocation`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'CS', 'Computer Science', NULL, 'Department of Computer Science and Information Technology', NULL, NULL, NULL, NULL, NULL, 1, '2025-07-28 14:55:17', '2025-07-28 14:55:17'),
(2, 'MATH', 'Mathematics', NULL, 'Department of Mathematics and Statistics', NULL, NULL, NULL, NULL, NULL, 1, '2025-07-28 14:55:17', '2025-07-28 14:55:17'),
(3, 'PHY', 'Physics', NULL, 'Department of Physics and Applied Sciences', NULL, NULL, NULL, NULL, NULL, 1, '2025-07-28 14:55:17', '2025-07-28 14:55:17'),
(4, 'ENG', 'Engineering', NULL, 'Department of Engineering and Technology', NULL, NULL, NULL, NULL, NULL, 1, '2025-07-28 14:55:17', '2025-07-28 14:55:17'),
(5, 'HIST', 'History', NULL, 'Department of History and Social Sciences', NULL, NULL, NULL, NULL, NULL, 1, '2025-07-28 14:55:17', '2025-07-28 14:55:17'),
(6, 'ENGL', 'English', NULL, 'Department of English and Literature', NULL, NULL, NULL, NULL, NULL, 1, '2025-07-28 14:55:17', '2025-07-28 14:55:17'),
(7, 'BUS', 'Business Administration', NULL, 'Department of Business and Management', NULL, NULL, NULL, NULL, NULL, 1, '2025-07-28 14:55:17', '2025-07-28 14:55:17'),
(8, 'IT', 'Information Technology', NULL, 'Department of Information Technology', NULL, NULL, NULL, NULL, NULL, 1, '2025-07-28 14:55:17', '2025-07-28 14:55:17');

-- --------------------------------------------------------

--
-- Stand-in structure for view `department_overview`
-- (See below for the actual view)
--
CREATE TABLE `department_overview` (
`department_id` int(11)
,`department_code` varchar(10)
,`department_name` varchar(100)
,`description` text
,`is_active` tinyint(1)
,`department_head_name` varchar(101)
,`head_employee_id` varchar(20)
,`total_users` bigint(21)
,`active_faculty` bigint(21)
,`active_students` bigint(21)
,`total_subjects` bigint(21)
,`owned_classrooms` bigint(21)
,`shared_classrooms` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `department_resources`
--

CREATE TABLE `department_resources` (
  `resource_id` int(11) NOT NULL,
  `owner_department_id` int(11) DEFAULT NULL,
  `shared_with_department_id` int(11) DEFAULT NULL,
  `resource_type` enum('classroom','equipment','faculty') NOT NULL,
  `resource_reference_id` int(11) NOT NULL,
  `sharing_conditions` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `enrollment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `section` varchar(10) DEFAULT 'A',
  `semester` int(11) NOT NULL CHECK (`semester` between 1 and 12),
  `academic_year` varchar(10) NOT NULL,
  `enrollment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('enrolled','dropped','completed','failed') DEFAULT 'enrolled',
  `enrolled_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`enrollment_id`, `student_id`, `subject_id`, `section`, `semester`, `academic_year`, `enrollment_date`, `status`, `enrolled_by`) VALUES
(8, 2, 2, 'A', 1, '2025-2026', '2025-07-25 18:39:46', 'enrolled', 1),
(9, 2, 7, 'A', 1, '2025-2026', '2025-07-25 18:39:46', 'enrolled', 1),
(10, 2, 10, 'A', 1, '2025-2026', '2025-07-25 18:39:46', 'enrolled', 1),
(11, 2, 11, 'A', 1, '2025-2026', '2025-07-25 18:39:46', 'enrolled', 1),
(12, 2, 15, 'A', 1, '2025-2026', '2025-07-25 18:39:46', 'enrolled', 1),
(13, 3, 8, 'A', 3, '2025-2026', '2025-07-25 18:39:46', 'enrolled', 1),
(14, 3, 9, 'A', 3, '2025-2026', '2025-07-25 18:39:46', 'enrolled', 1),
(18, 7, 7, 'A', 1, '2025-2026', '2025-07-24 14:54:54', 'enrolled', 1),
(20, 6, 1, 'A', 1, '2025-2026', '2025-07-25 22:01:05', 'enrolled', 1),
(21, 2, 1, 'A', 1, '2025-2026', '2025-08-02 08:42:04', 'enrolled', 1),
(22, 3, 1, 'A', 1, '2025-2026', '2025-08-02 08:42:04', 'enrolled', 1),
(23, 7, 1, 'A', 1, '2025-2026', '2025-08-02 08:42:04', 'enrolled', 1),
(24, 9, 1, 'B', 1, '2025-2026', '2025-08-02 08:42:04', 'enrolled', 1),
(25, 10, 1, 'B', 1, '2025-2026', '2025-08-02 08:42:04', 'enrolled', 1),
(26, 2, 4, 'A', 1, '2025-2026', '2025-08-02 08:42:04', 'enrolled', 1),
(27, 3, 4, 'A', 1, '2025-2026', '2025-08-02 08:42:04', 'enrolled', 1),
(28, 6, 4, 'A', 1, '2025-2026', '2025-08-02 08:42:04', 'enrolled', 1),
(29, 7, 4, 'A', 1, '2025-2026', '2025-08-02 08:42:04', 'enrolled', 1),
(30, 2, 5, 'A', 1, '2025-2026', '2025-08-02 08:42:04', 'enrolled', 1),
(31, 3, 5, 'A', 1, '2025-2026', '2025-08-02 08:42:04', 'enrolled', 1),
(32, 6, 5, 'A', 1, '2025-2026', '2025-08-02 08:42:04', 'enrolled', 1),
(33, 7, 5, 'A', 1, '2025-2026', '2025-08-02 08:42:04', 'enrolled', 1),
(34, 9, 5, 'A', 1, '2025-2026', '2025-08-02 08:42:04', 'enrolled', 1);

-- --------------------------------------------------------

--
-- Table structure for table `faculty`
--

CREATE TABLE `faculty` (
  `faculty_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `department` varchar(100) NOT NULL,
  `designation` varchar(50) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `specialization` text DEFAULT NULL,
  `qualification` varchar(100) DEFAULT NULL,
  `experience_years` int(11) DEFAULT NULL CHECK (`experience_years` >= 0),
  `date_joined` date DEFAULT NULL,
  `office_location` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `faculty`
--

INSERT INTO `faculty` (`faculty_id`, `user_id`, `employee_id`, `first_name`, `last_name`, `department`, `designation`, `phone`, `specialization`, `qualification`, `experience_years`, `date_joined`, `office_location`, `created_at`, `updated_at`) VALUES
(1, 2, 'FAC001', 'John', 'Smith', 'Computer Science', 'Associate Professor', '+1-555-0101', 'Programming Languages, Software Engineering', 'PhD in Computer Science', 8, NULL, 'CS-301', '2025-06-25 22:17:51', '2025-06-25 22:17:51'),
(2, 3, 'FAC002', 'Mary', 'Johnson', 'Mathematics', 'Professor', '(024) 104-8205', 'Calculus, Linear Algebra', 'PhD in Mathematics', 15, NULL, 'MATH-201', '2025-06-25 22:17:51', '2025-07-10 09:28:00'),
(3, 4, 'FAC003', 'David', 'Williams', 'Physics', 'Assistant Professor', '+1-555-0103', 'Quantum Physics, Mechanics', 'PhD in Physics', 5, NULL, 'PHY-101', '2025-06-25 22:17:51', '2025-06-25 22:17:51'),
(6, 14, '10102020', 'Patrick', 'Nartey', 'Computer Science', 'Lecturer', '(054) 425-0759', '', NULL, NULL, NULL, NULL, '2025-06-28 12:21:57', '2025-06-28 12:21:57'),
(7, 16, '60704', 'Doris', 'Lamptey', 'Engineering', 'Lecturer', '(054) 425-0759', '', NULL, NULL, NULL, NULL, '2025-06-29 16:54:37', '2025-06-29 16:54:37'),
(8, 18, 'FAC010', 'Frank', 'Owusu', 'Engineering', 'Senior Lecturer', '+233245678900', 'Structural Analysis', 'MSc in Civil Engineering', 6, '2022-09-01', 'ENG-301', '2025-07-09 14:10:46', '2025-07-09 14:10:46'),
(9, 20, '024175', 'Michael', 'Provencal', 'Computer Science', 'Professor', '(024) 104-8205', 'Communication', 'PhD in Computer Science', 6, NULL, 'Room 21 Computer Science Department', '2025-07-09 20:03:04', '2025-08-02 22:47:00');

-- --------------------------------------------------------

--
-- Stand-in structure for view `faculty_schedules`
-- (See below for the actual view)
--
CREATE TABLE `faculty_schedules` (
`faculty_id` int(11)
,`faculty_name` varchar(101)
,`employee_id` varchar(20)
,`subject_code` varchar(10)
,`subject_name` varchar(100)
,`room_number` varchar(20)
,`building` varchar(50)
,`day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday')
,`start_time` time
,`end_time` time
,`section` varchar(10)
,`semester` int(11)
,`academic_year` varchar(10)
,`enrolled_students` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `faculty_subjects`
--

CREATE TABLE `faculty_subjects` (
  `assignment_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `max_students` int(11) DEFAULT 60 CHECK (`max_students` > 0),
  `assigned_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_by` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `faculty_subjects`
--

INSERT INTO `faculty_subjects` (`assignment_id`, `faculty_id`, `subject_id`, `max_students`, `assigned_date`, `assigned_by`, `is_active`, `notes`) VALUES
(2, 1, 2, 60, '2025-06-25 22:17:51', 1, 1, NULL),
(5, 2, 7, 60, '2025-06-25 22:17:51', 1, 1, NULL),
(6, 2, 8, 60, '2025-06-25 22:17:51', 1, 1, NULL),
(7, 2, 9, 60, '2025-06-25 22:17:51', 1, 1, NULL),
(8, 3, 10, 60, '2025-06-25 22:17:51', 1, 1, NULL),
(9, 3, 11, 60, '2025-06-25 22:17:51', 1, 1, NULL),
(10, 3, 12, 60, '2025-06-25 22:17:51', 1, 1, NULL),
(12, 1, 15, 60, '2025-06-25 22:17:51', 1, 1, NULL),
(13, 1, 3, 60, '2025-06-28 17:59:19', 1, 1, ''),
(14, 2, 16, 60, '2025-06-28 18:00:11', 1, 1, ''),
(15, 9, 4, 60, '2025-07-10 09:31:27', 1, 0, ''),
(17, 9, 1, 60, '2025-07-10 11:16:50', 1, 0, ''),
(18, 8, 5, 60, '2025-07-10 11:23:37', 1, 0, ''),
(19, 9, 4, 60, '2025-07-10 11:29:34', 1, 1, ''),
(20, 9, 5, 60, '2025-07-10 14:03:34', 1, 1, ''),
(21, 9, 1, 60, '2025-07-18 22:23:56', 1, 1, '');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error','urgent') DEFAULT 'info',
  `target_role` enum('all','admin','faculty','student') DEFAULT 'all',
  `target_user_id` int(11) DEFAULT NULL,
  `related_table` varchar(50) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `title`, `message`, `type`, `target_role`, `target_user_id`, `related_table`, `related_id`, `is_read`, `read_at`, `priority`, `created_by`, `created_at`, `expires_at`, `is_active`) VALUES
(2, 'Schedule Update Notice', 'Please note that some schedules may be updated. You will receive notifications for any changes affecting your classes.', 'warning', 'student', NULL, NULL, NULL, 1, '2025-07-13 14:44:53', 'normal', 1, '2025-06-25 22:17:52', NULL, 1),
(3, 'Faculty Meeting Reminder', 'Monthly faculty meeting scheduled for next Friday at 2 PM in the conference room.', 'info', 'faculty', NULL, NULL, NULL, 1, '2025-07-16 16:23:38', 'normal', 1, '2025-06-25 22:17:52', NULL, 1),
(4, 'System Maintenance', 'System maintenance is scheduled for Sunday 2 AM - 4 AM. The system will be temporarily unavailable.', 'warning', 'all', NULL, NULL, NULL, 0, NULL, 'normal', 1, '2025-06-25 22:17:52', NULL, 1),
(9, 'update', 'we updating our system', 'info', 'all', NULL, NULL, NULL, 0, NULL, 'normal', 1, '2025-07-06 12:33:55', NULL, 1),
(10, 'update', 'we updating our system', 'info', 'all', NULL, NULL, NULL, 1, '2025-07-09 20:45:50', 'normal', 1, '2025-07-06 12:35:55', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `token_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(64) NOT NULL COMMENT 'SHA-256 hash of the actual token',
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'When the token expires (30 days)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_used_at` timestamp NULL DEFAULT NULL COMMENT 'Last time token was used for auto-login',
  `user_agent` varchar(255) DEFAULT NULL COMMENT 'Browser/device information',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address when token was created/used',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Whether token is still valid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores secure tokens for Remember Me functionality';

--
-- Dumping data for table `remember_tokens`
--

INSERT INTO `remember_tokens` (`token_id`, `user_id`, `token_hash`, `expires_at`, `created_at`, `last_used_at`, `user_agent`, `ip_address`, `is_active`) VALUES
(11, 20, 'a3606f2a9a41d9631769d6e342cb3f8f324f25a6eef3606a12e89d5a55410230', '2025-09-01 21:09:53', '2025-08-02 21:09:53', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '::1', 1),
(12, 20, '4ecb0e1739a9c79bce0ea4ad776fbad9f7b5213af083598bfd3e0aa0520644ad', '2025-09-01 21:43:00', '2025-08-02 21:43:00', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '::1', 1),
(13, 20, 'b9c5c1d2018396898e2bffed5cee42d65b1a442f687607f64f5f56ce32a3ef81', '2025-09-01 22:20:25', '2025-08-02 22:20:26', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '::1', 1),
(14, 20, '801c411eb5c5fede6e5548e7c47c71418935cde11a825c1f9757bbaf0d4ce895', '2025-09-01 23:01:29', '2025-08-02 23:01:29', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '::1', 1),
(19, 20, 'f43bf4c73fcbf643518fc26d9fd21b58c2bf1672cf9b2ed5010878264283bfe1', '2025-08-03 01:21:11', '2025-08-03 00:41:31', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '::1', 0);

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL,
  `room_number` varchar(20) NOT NULL,
  `room_name` varchar(100) DEFAULT NULL,
  `building` varchar(50) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `room_type` enum('classroom','laboratory','auditorium') DEFAULT 'classroom',
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `room_number`, `room_name`, `building`, `capacity`, `room_type`, `status`) VALUES
(1, '101', 'Computer Lab 1', 'Science Building', 30, 'laboratory', 'active'),
(2, '102', 'Lecture Hall A', 'Science Building', 50, 'classroom', 'active'),
(3, '201', 'Mathematics Room', 'Science Building', 35, 'classroom', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `student_number` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `department` varchar(100) NOT NULL,
  `year_of_study` int(11) NOT NULL CHECK (`year_of_study` between 1 and 6),
  `semester` int(11) NOT NULL CHECK (`semester` between 1 and 12),
  `phone` varchar(15) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `guardian_name` varchar(100) DEFAULT NULL,
  `guardian_phone` varchar(15) DEFAULT NULL,
  `guardian_email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `user_id`, `student_number`, `first_name`, `last_name`, `department`, `year_of_study`, `semester`, `phone`, `date_of_birth`, `address`, `guardian_name`, `guardian_phone`, `guardian_email`, `created_at`, `updated_at`) VALUES
(2, 8, 'STU2024002', 'Bob', 'Taylor', 'Computer Science', 1, 1, '+1-555-1002', NULL, NULL, 'Jennifer Taylor', '+1-555-1002', NULL, '2025-06-25 22:17:51', '2025-06-25 22:17:51'),
(3, 9, 'STU2023001', 'Carol', 'Anderson', 'Mathematics', 2, 3, '+1-555-1003', NULL, NULL, 'Mark Anderson', '+1-555-1003', NULL, '2025-06-25 22:17:51', '2025-06-25 22:17:51'),
(6, 13, '10977321', 'Odartey', 'Lamptey', 'Computer Science', 4, 2, '(054) 425-0759', NULL, NULL, NULL, NULL, NULL, '2025-06-28 11:34:25', '2025-06-28 11:34:25'),
(7, 15, '30304040', 'Gladys', 'Lamptey', 'Business Administration', 2, 2, '0551605034', NULL, NULL, NULL, NULL, NULL, '2025-06-28 18:55:24', '2025-06-28 18:55:24'),
(8, 17, '10102020', 'Comfort', 'Amanuah', 'Mathematics', 3, 1, '(054) 793-7353', NULL, NULL, NULL, NULL, NULL, '2025-07-04 11:43:41', '2025-07-04 11:43:41'),
(9, 19, 'STU2025001', 'Grace', 'Adjei', 'Mathematics', 1, 1, '+233201234567', '2006-04-15', 'Kumasi, Ghana', 'Ama Adjei', '+233205554444', 'ama.adjei@example.com', '2025-07-09 14:11:20', '2025-07-09 14:11:20'),
(10, 21, '161610', 'Diana', 'Lamptey', 'Information Technology', 2, 1, '0552580593', NULL, NULL, NULL, NULL, NULL, '2025-07-11 15:11:58', '2025-07-11 15:11:58'),
(13, 25, '4040', 'Final', 'Check', 'Computer Science', 1, 1, '', NULL, NULL, NULL, NULL, NULL, '2025-08-03 00:21:16', '2025-08-03 00:21:16');

-- --------------------------------------------------------

--
-- Stand-in structure for view `student_schedules`
-- (See below for the actual view)
--
CREATE TABLE `student_schedules` (
`student_id` int(11)
,`student_name` varchar(101)
,`student_number` varchar(20)
,`subject_code` varchar(10)
,`subject_name` varchar(100)
,`faculty_name` varchar(101)
,`room_number` varchar(20)
,`building` varchar(50)
,`day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday')
,`start_time` time
,`end_time` time
,`section` varchar(10)
,`semester` int(11)
,`academic_year` varchar(10)
,`enrollment_status` enum('enrolled','dropped','completed','failed')
);

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `subject_id` int(11) NOT NULL,
  `subject_code` varchar(10) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `credits` int(11) NOT NULL CHECK (`credits` between 1 and 6),
  `duration_hours` int(11) NOT NULL CHECK (`duration_hours` > 0),
  `type` enum('theory','practical','lab') DEFAULT 'theory',
  `department` varchar(100) NOT NULL,
  `semester` int(11) NOT NULL CHECK (`semester` between 1 and 12),
  `year_level` int(11) NOT NULL CHECK (`year_level` between 1 and 6),
  `prerequisites` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `syllabus` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`subject_id`, `subject_code`, `subject_name`, `department_id`, `credits`, `duration_hours`, `type`, `department`, `semester`, `year_level`, `prerequisites`, `description`, `syllabus`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'CS101', 'Introduction to Programming', 1, 3, 3, 'theory', 'Computer Science', 1, 1, NULL, 'Basic programming concepts using C++', NULL, 1, '2025-06-25 22:17:50', '2025-07-28 14:20:15'),
(2, 'CS102', 'Programming Lab', 1, 1, 2, 'lab', 'Computer Science', 1, 1, NULL, 'Practical programming exercises', NULL, 1, '2025-06-25 22:17:50', '2025-07-28 14:20:15'),
(3, 'CS201', 'Data Structures', 1, 3, 3, 'theory', 'Computer Science', 3, 2, NULL, 'Linear and non-linear data structures', NULL, 1, '2025-06-25 22:17:50', '2025-07-28 14:20:15'),
(4, 'CS202', 'Data Structures Lab', 1, 1, 2, 'lab', 'Computer Science', 3, 2, NULL, 'Implementation of data structures', NULL, 1, '2025-06-25 22:17:50', '2025-07-28 14:20:15'),
(5, 'CS301', 'Database Systems', 1, 3, 3, 'theory', 'Computer Science', 5, 3, NULL, 'Database design and management', NULL, 1, '2025-06-25 22:17:50', '2025-07-28 14:20:15'),
(6, 'CS302', 'Database Lab', 1, 1, 2, 'lab', 'Computer Science', 5, 3, NULL, 'Hands-on database implementation', NULL, 1, '2025-06-25 22:17:50', '2025-07-28 14:20:15'),
(7, 'MATH101', 'Calculus I', 2, 3, 3, 'theory', 'Mathematics', 1, 1, NULL, 'Differential calculus and applications', NULL, 1, '2025-06-25 22:17:50', '2025-07-28 14:20:15'),
(8, 'MATH201', 'Calculus II', 2, 3, 3, 'theory', 'Mathematics', 3, 2, NULL, 'Integral calculus and series', NULL, 1, '2025-06-25 22:17:50', '2025-07-28 14:20:15'),
(9, 'MATH301', 'Linear Algebra', 2, 3, 3, 'theory', 'Mathematics', 5, 3, NULL, 'Matrices, vectors, and linear transformations', NULL, 1, '2025-06-25 22:17:50', '2025-07-28 14:20:15'),
(10, 'PHY101', 'Physics I', 3, 3, 3, 'theory', 'Physics', 2, 1, NULL, 'Mechanics and thermodynamics', NULL, 1, '2025-06-25 22:17:50', '2025-07-28 14:20:15'),
(11, 'PHY102', 'Physics Lab I', 3, 1, 2, 'lab', 'Physics', 2, 1, NULL, 'Experimental physics', NULL, 1, '2025-06-25 22:17:50', '2025-07-28 14:20:15'),
(12, 'PHY201', 'Physics II', 3, 3, 3, 'theory', 'Physics', 4, 2, NULL, 'Electricity and magnetism', NULL, 1, '2025-06-25 22:17:50', '2025-07-28 14:20:15'),
(13, 'ENG101', 'Engineering Drawing', 4, 2, 2, 'practical', 'Engineering', 1, 1, NULL, 'Technical drawing and CAD', NULL, 1, '2025-06-25 22:17:50', '2025-07-28 14:20:15'),
(14, 'ENG201', 'Thermodynamics', 4, 3, 3, 'theory', 'Engineering', 3, 2, NULL, 'Heat transfer and energy systems', NULL, 1, '2025-06-25 22:17:50', '2025-07-28 14:20:15'),
(15, 'ENG001', 'English Communication', 6, 2, 2, 'theory', 'English', 1, 1, NULL, 'Business and technical communication', NULL, 1, '2025-06-25 22:17:50', '2025-07-28 14:37:28'),
(16, 'HIST101', 'History of Science', 5, 2, 2, 'theory', 'History', 2, 1, NULL, 'Development of scientific thought', NULL, 1, '2025-06-25 22:17:50', '2025-07-28 14:20:15');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `is_active`, `created_at`, `updated_at`, `updated_by`) VALUES
(1, 'system_name', 'University Timetable Management System', 'string', 'Application name', 1, '2025-06-25 22:17:50', '2025-06-25 22:17:50', NULL),
(2, 'academic_year_current', '2025-2026', 'string', 'Current academic year', 1, '2025-06-25 22:17:50', '2025-08-02 02:35:39', NULL),
(3, 'semester_current', '1', 'integer', 'Current semester', 1, '2025-06-25 22:17:50', '2025-06-25 22:17:50', NULL),
(4, 'max_login_attempts', '5', 'integer', 'Maximum failed login attempts', 1, '2025-06-25 22:17:50', '2025-06-25 22:17:50', NULL),
(5, 'session_timeout', '30', 'integer', 'Session timeout in minutes', 1, '2025-06-25 22:17:50', '2025-06-25 22:17:50', NULL),
(6, 'email_verification_expiry', '24', 'integer', 'Email verification expiry in hours', 1, '2025-06-25 22:17:50', '2025-06-25 22:17:50', NULL),
(7, 'password_reset_expiry', '1', 'integer', 'Password reset token expiry in hours', 1, '2025-06-25 22:17:50', '2025-06-25 22:17:50', NULL),
(8, 'notification_retention_days', '30', 'integer', 'Days to keep read notifications', 1, '2025-06-25 22:17:50', '2025-06-25 22:17:50', NULL),
(9, 'backup_frequency', '24', 'integer', 'Backup frequency in hours', 1, '2025-06-25 22:17:50', '2025-06-25 22:17:50', NULL),
(10, 'system_maintenance', 'false', 'boolean', 'System maintenance mode', 1, '2025-06-25 22:17:50', '2025-06-25 22:17:50', NULL),
(11, 'user_pref_timezone', 'Europe/London', 'string', 'User preference', 1, '2025-07-06 16:46:38', '2025-08-02 22:40:27', 20),
(12, 'user_pref_date_format', 'd/m/Y', 'string', 'User preference', 1, '2025-07-06 16:46:38', '2025-08-02 22:40:27', 20),
(13, 'user_pref_time_format', 'H:i', 'string', 'User preference', 1, '2025-07-06 16:46:38', '2025-08-02 22:40:27', 20),
(14, 'user_pref_notifications_email', 'false', 'string', 'User preference', 1, '2025-07-06 16:46:39', '2025-08-02 22:40:27', 20),
(15, 'user_pref_notifications_system', 'false', 'string', 'User preference', 1, '2025-07-06 16:46:39', '2025-08-02 22:40:27', 20),
(16, 'user_pref_language', 'en', 'string', 'User preference', 1, '2025-07-06 16:46:39', '2025-08-02 22:40:27', 20),
(17, 'user_pref_theme', 'dark', 'string', 'User preference', 1, '2025-07-06 16:46:40', '2025-08-02 22:40:26', 20),
(18, 'user_pref_notifications_browser', 'true', 'string', 'User preference', 1, '2025-07-24 14:44:09', '2025-07-24 14:44:09', 1),
(19, 'user_pref_dashboard_widgets', 'true', 'string', 'User preference', 1, '2025-07-24 14:44:10', '2025-07-24 14:44:10', 1),
(20, 'user_pref_sidebar_collapsed', 'false', 'string', 'User preference', 1, '2025-07-24 14:44:10', '2025-07-24 14:44:10', 1),
(21, 'multi_department_enabled', 'true', 'boolean', 'Enable multi-department support', 1, '2025-07-28 14:20:24', '2025-07-28 14:20:24', NULL),
(22, 'default_department_id', '1', 'integer', 'Default department ID for new users', 1, '2025-07-28 14:20:24', '2025-07-28 14:20:24', NULL),
(23, 'inter_department_sharing', 'true', 'boolean', 'Allow resource sharing between departments', 1, '2025-07-28 14:20:24', '2025-07-28 14:20:24', NULL),
(24, 'department_approval_required', 'false', 'boolean', 'Require approval for department changes', 1, '2025-07-28 14:20:24', '2025-07-28 14:20:24', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `timetables`
--

CREATE TABLE `timetables` (
  `timetable_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `classroom_id` int(11) NOT NULL,
  `slot_id` int(11) NOT NULL,
  `section` varchar(10) DEFAULT 'A',
  `semester` int(11) NOT NULL CHECK (`semester` between 1 and 12),
  `academic_year` varchar(10) NOT NULL,
  `batch_year` int(11) DEFAULT NULL,
  `max_students` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `modified_at` timestamp NULL DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `timetables`
--

INSERT INTO `timetables` (`timetable_id`, `subject_id`, `faculty_id`, `classroom_id`, `slot_id`, `section`, `semester`, `academic_year`, `batch_year`, `max_students`, `created_at`, `created_by`, `modified_at`, `modified_by`, `is_active`, `notes`) VALUES
(2, 7, 2, 2, 53, 'A', 1, '2025-2026', NULL, NULL, '2025-06-25 22:17:51', 1, '2025-07-25 21:45:14', NULL, 1, NULL),
(4, 10, 3, 15, 54, 'A', 1, '2025-2026', NULL, NULL, '2025-06-25 22:17:51', 1, '2025-07-25 21:45:14', NULL, 1, NULL),
(5, 15, 1, 3, 56, 'A', 1, '2025-2026', NULL, NULL, '2025-06-25 22:17:51', 1, '2025-07-25 21:50:48', 1, 1, NULL),
(7, 11, 3, 14, 58, 'A', 1, '2025-2026', NULL, NULL, '2025-06-25 22:17:51', 1, '2025-07-25 21:45:14', NULL, 1, NULL),
(10, 10, 3, 15, 62, 'A', 1, '2025-2026', NULL, NULL, '2025-06-25 22:17:51', 1, '2025-07-25 21:45:14', NULL, 1, NULL),
(12, 15, 1, 3, 66, 'A', 1, '2025-2026', NULL, NULL, '2025-06-25 22:17:51', 1, '2025-07-25 21:45:14', NULL, 1, NULL),
(13, 7, 2, 2, 68, 'A', 1, '2025-2026', NULL, NULL, '2025-06-25 22:17:51', 1, '2025-07-25 21:48:30', 1, 0, NULL),
(14, 11, 3, 14, 69, 'A', 1, '2025-2026', NULL, NULL, '2025-06-25 22:17:51', 1, '2025-07-25 22:14:51', 1, 0, NULL),
(18, 1, 9, 5, 57, 'A', 1, '2025-2026', NULL, NULL, '2025-07-18 23:03:35', 1, '2025-07-25 21:43:25', NULL, 1, NULL),
(19, 7, 2, 4, 61, 'A', 1, '2025-2026', NULL, 40, '2025-07-25 15:04:31', 1, '2025-07-25 22:14:18', 1, 0, NULL),
(25, 1, 9, 7, 53, 'A', 1, '2025-2026', NULL, NULL, '2025-08-02 00:58:10', 1, NULL, NULL, 1, 'Introduction to Programming'),
(26, 5, 9, 9, 58, 'A', 1, '2025-2026', NULL, NULL, '2025-08-02 00:58:10', 1, NULL, NULL, 1, 'Database Systems Theory'),
(27, 4, 9, 7, 61, 'B', 1, '2025-2026', NULL, NULL, '2025-08-02 00:58:10', 1, NULL, NULL, 1, 'Data Structures Lab Session'),
(28, 1, 9, 7, 66, 'B', 1, '2025-2026', NULL, NULL, '2025-08-02 00:58:10', 1, NULL, NULL, 1, 'Programming Practice Session'),
(29, 5, 9, 9, 68, 'A', 1, '2025-2026', NULL, NULL, '2025-08-02 00:58:10', 1, NULL, NULL, 1, 'Database Design Workshop'),
(30, 4, 9, 7, 52, 'A', 1, '2025-2026', NULL, NULL, '2025-08-02 08:42:04', 1, NULL, NULL, 1, 'Data Structures Lab - Practical Session'),
(31, 5, 9, 9, 60, 'A', 1, '2025-2026', NULL, NULL, '2025-08-02 08:42:04', 1, NULL, NULL, 1, 'Database Systems - Theory'),
(32, 1, 9, 7, 65, 'B', 1, '2025-2026', NULL, NULL, '2025-08-02 08:42:04', 1, NULL, NULL, 1, 'Programming Fundamentals - Section B'),
(33, 5, 9, 7, 70, 'A', 1, '2025-2026', NULL, NULL, '2025-08-02 08:42:04', 1, NULL, NULL, 1, 'Database Lab - Hands-on Practice'),
(34, 4, 9, 13, 64, 'A', 1, '2025-2026', NULL, NULL, '2025-08-02 08:42:04', 1, NULL, NULL, 1, 'Data Structures - Theory Session'),
(35, 2, 1, 7, 54, 'A', 1, '2025-2026', NULL, NULL, '2025-08-02 08:42:05', 1, NULL, NULL, 1, 'Programming Lab'),
(36, 3, 1, 1, 58, 'A', 1, '2025-2026', NULL, NULL, '2025-08-02 08:42:05', 1, NULL, NULL, 1, 'Data Structures Theory'),
(37, 8, 2, 2, 52, 'A', 1, '2025-2026', NULL, NULL, '2025-08-02 08:42:05', 1, NULL, NULL, 1, 'Calculus II'),
(38, 9, 2, 2, 64, 'A', 1, '2025-2026', NULL, NULL, '2025-08-02 08:42:05', 1, NULL, NULL, 1, 'Linear Algebra');

--
-- Triggers `timetables`
--
DELIMITER $$
CREATE TRIGGER `update_timetable_modified` BEFORE UPDATE ON `timetables` FOR EACH ROW BEGIN
    SET NEW.modified_at = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `timetable_details`
-- (See below for the actual view)
--
CREATE TABLE `timetable_details` (
`timetable_id` int(11)
,`subject_code` varchar(10)
,`subject_name` varchar(100)
,`credits` int(11)
,`faculty_name` varchar(101)
,`room_number` varchar(20)
,`building` varchar(50)
,`capacity` int(11)
,`day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday')
,`start_time` time
,`end_time` time
,`slot_name` varchar(20)
,`section` varchar(10)
,`semester` int(11)
,`academic_year` varchar(10)
,`is_active` tinyint(1)
,`enrolled_students` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `time_slots`
--

CREATE TABLE `time_slots` (
  `slot_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `slot_name` varchar(20) NOT NULL,
  `slot_type` enum('regular','break','lunch') DEFAULT 'regular',
  `is_active` tinyint(1) DEFAULT 1
) ;

--
-- Dumping data for table `time_slots`
--

INSERT INTO `time_slots` (`slot_id`, `day_of_week`, `start_time`, `end_time`, `slot_name`, `slot_type`, `is_active`) VALUES
(1, 'Monday', '08:00:00', '09:00:00', 'Period 1', 'regular', 0),
(2, 'Monday', '09:00:00', '10:00:00', 'Period 2', 'regular', 0),
(3, 'Monday', '10:00:00', '10:15:00', 'Break 1', 'break', 0),
(4, 'Monday', '10:15:00', '11:15:00', 'Period 3', 'regular', 0),
(5, 'Monday', '11:15:00', '12:15:00', 'Period 4', 'regular', 0),
(6, 'Monday', '12:15:00', '13:15:00', 'Lunch', 'lunch', 0),
(7, 'Monday', '13:15:00', '14:15:00', 'Period 5', 'regular', 0),
(8, 'Monday', '14:15:00', '15:15:00', 'Period 6', 'regular', 0),
(9, 'Monday', '15:15:00', '15:30:00', 'Break 2', 'break', 0),
(10, 'Monday', '15:30:00', '16:30:00', 'Period 7', 'regular', 0),
(11, 'Tuesday', '08:00:00', '09:00:00', 'Period 1', 'regular', 0),
(12, 'Tuesday', '09:00:00', '10:00:00', 'Period 2', 'regular', 0),
(13, 'Tuesday', '10:00:00', '10:15:00', 'Break 1', 'break', 0),
(14, 'Tuesday', '10:15:00', '11:15:00', 'Period 3', 'regular', 0),
(15, 'Tuesday', '11:15:00', '12:15:00', 'Period 4', 'regular', 0),
(16, 'Tuesday', '12:15:00', '13:15:00', 'Lunch', 'lunch', 0),
(17, 'Tuesday', '13:15:00', '14:15:00', 'Period 5', 'regular', 0),
(18, 'Tuesday', '14:15:00', '15:15:00', 'Period 6', 'regular', 0),
(19, 'Monday', '05:30:00', '06:30:00', 'Quiz Time', 'regular', 0),
(20, 'Tuesday', '15:30:00', '16:30:00', 'Period 7', 'regular', 0),
(21, 'Wednesday', '08:00:00', '09:00:00', 'Period 1', 'regular', 0),
(22, 'Wednesday', '09:00:00', '10:00:00', 'Period 2', 'regular', 0),
(23, 'Wednesday', '10:00:00', '10:15:00', 'Break 1', 'break', 0),
(24, 'Wednesday', '10:15:00', '11:15:00', 'Period 3', 'regular', 0),
(25, 'Wednesday', '11:15:00', '12:15:00', 'Period 4', 'regular', 0),
(26, 'Wednesday', '12:15:00', '13:15:00', 'Lunch', 'lunch', 0),
(27, 'Wednesday', '13:15:00', '14:15:00', 'Period 5', 'regular', 0),
(28, 'Wednesday', '14:15:00', '15:15:00', 'Period 6', 'regular', 0),
(29, 'Wednesday', '15:15:00', '15:30:00', 'Break 2', 'break', 0),
(30, 'Wednesday', '15:30:00', '16:30:00', 'Period 7', 'regular', 0),
(31, 'Thursday', '08:00:00', '09:00:00', 'Period 1', 'regular', 0),
(32, 'Thursday', '09:00:00', '10:00:00', 'Period 2', 'regular', 0),
(33, 'Thursday', '10:00:00', '10:15:00', 'Break 1', 'break', 0),
(34, 'Thursday', '10:15:00', '11:15:00', 'Period 3', 'regular', 0),
(35, 'Thursday', '11:15:00', '12:15:00', 'Period 4', 'regular', 0),
(36, 'Thursday', '12:15:00', '13:15:00', 'Lunch', 'lunch', 0),
(37, 'Thursday', '13:15:00', '14:15:00', 'Period 5', 'regular', 0),
(38, 'Thursday', '14:15:00', '15:15:00', 'Period 6', 'regular', 0),
(39, 'Thursday', '15:15:00', '15:30:00', 'Break 2', 'break', 0),
(40, 'Thursday', '15:30:00', '16:30:00', 'Period 7', 'regular', 0),
(41, 'Friday', '08:00:00', '09:00:00', 'Period 1', 'regular', 0),
(42, 'Friday', '09:00:00', '10:00:00', 'Period 2', 'regular', 0),
(43, 'Friday', '10:00:00', '10:15:00', 'Break 1', 'break', 0),
(44, 'Friday', '10:15:00', '11:15:00', 'Period 3', 'regular', 0),
(45, 'Friday', '11:15:00', '12:15:00', 'Period 4', 'regular', 0),
(46, 'Friday', '12:15:00', '13:15:00', 'Lunch', 'lunch', 0),
(47, 'Friday', '13:15:00', '14:15:00', 'Period 5', 'regular', 0),
(48, 'Friday', '14:15:00', '15:15:00', 'Period 6', 'regular', 0),
(49, 'Friday', '15:15:00', '15:30:00', 'Break 2', 'break', 0),
(51, 'Wednesday', '06:00:00', '06:15:00', 'Worship', 'regular', 0),
(52, 'Monday', '07:30:00', '09:20:00', 'Morning 1', 'regular', 1),
(53, 'Monday', '09:30:00', '11:20:00', 'Morning 2', 'regular', 1),
(54, 'Monday', '12:30:00', '14:20:00', 'Afternoon 1', 'regular', 1),
(55, 'Monday', '14:30:00', '16:20:00', 'Afternoon 2', 'regular', 0),
(56, 'Tuesday', '07:30:00', '09:20:00', 'Morning 1', 'regular', 1),
(57, 'Tuesday', '09:30:00', '11:20:00', 'Morning 2', 'regular', 1),
(58, 'Tuesday', '12:30:00', '14:20:00', 'Afternoon 1', 'regular', 1),
(59, 'Tuesday', '14:30:00', '16:20:00', 'Afternoon 2', 'regular', 0),
(60, 'Wednesday', '07:30:00', '09:20:00', 'Morning 1', 'regular', 1),
(61, 'Wednesday', '09:30:00', '11:20:00', 'Morning 2', 'regular', 1),
(62, 'Wednesday', '12:30:00', '14:20:00', 'Afternoon 1', 'regular', 1),
(63, 'Wednesday', '14:30:00', '16:20:00', 'Afternoon 2', 'regular', 0),
(64, 'Thursday', '07:30:00', '09:20:00', 'Morning 1', 'regular', 1),
(65, 'Thursday', '09:30:00', '11:20:00', 'Morning 2', 'regular', 1),
(66, 'Thursday', '12:30:00', '14:20:00', 'Afternoon 1', 'regular', 1),
(67, 'Thursday', '14:30:00', '16:20:00', 'Afternoon 2', 'regular', 1),
(68, 'Friday', '07:30:00', '09:20:00', 'Morning 1', 'regular', 1),
(69, 'Friday', '09:30:00', '11:20:00', 'Morning 2', 'regular', 1),
(70, 'Friday', '12:30:00', '14:20:00', 'Afternoon 1', 'regular', 1),
(71, 'Friday', '14:30:00', '16:20:00', 'Afternoon 2', 'regular', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','faculty','student') NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `status` enum('pending','active','inactive','rejected') DEFAULT 'pending',
  `email_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `password_reset_expires` timestamp NULL DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `last_attempt_time` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password_hash`, `role`, `department_id`, `status`, `email_verified`, `verification_token`, `created_at`, `approved_by`, `approved_at`, `last_login`, `password_reset_token`, `password_reset_expires`, `login_attempts`, `last_attempt_time`) VALUES
(1, 'Paulson', 'admin@university.edu', '$2y$10$bwhgt46ocmxc.XI6wjxLJuJ/VYWWkdGUDuIpm9M4Rs76/qMciUcNa', 'admin', 8, 'active', 1, NULL, '2025-06-25 22:17:50', NULL, NULL, '2025-08-01 21:10:01', NULL, NULL, 0, NULL),
(2, 'dr.smith', 'john.smith@university.edu', '$2y$12$O5e27L1xu0yU65tTg8AFH.fSkY6MDt2V44K0XKV8H1FRmzzIOMH9W', 'faculty', 1, 'active', 1, NULL, '2025-06-25 22:17:50', NULL, NULL, NULL, NULL, NULL, 0, NULL),
(3, 'prof.johnson', 'mary.johnson@university.edu', '$2y$12$O5e27L1xu0yU65tTg8AFH.fSkY6MDt2V44K0XKV8H1FRmzzIOMH9W', 'faculty', 2, 'active', 1, NULL, '2025-06-25 22:17:50', NULL, NULL, NULL, NULL, NULL, 0, NULL),
(4, 'dr.williams', 'david.williams@university.edu', '$2y$12$O5e27L1xu0yU65tTg8AFH.fSkY6MDt2V44K0XKV8H1FRmzzIOMH9W', 'faculty', 3, 'active', 1, NULL, '2025-06-25 22:17:50', NULL, NULL, NULL, NULL, NULL, 0, NULL),
(8, 'bob.taylor', 'bob.taylor@student.university.edu', '$2y$12$O5e27L1xu0yU65tTg8AFH.fSkY6MDt2V44K0XKV8H1FRmzzIOMH9W', 'student', 1, 'active', 1, NULL, '2025-06-25 22:17:51', NULL, NULL, NULL, NULL, NULL, 0, NULL),
(9, 'carol.anderson', 'carol.anderson@student.university.edu', '$2y$12$O5e27L1xu0yU65tTg8AFH.fSkY6MDt2V44K0XKV8H1FRmzzIOMH9W', 'student', 2, 'active', 1, NULL, '2025-06-25 22:17:51', NULL, NULL, NULL, NULL, NULL, 0, NULL),
(13, 'odarteylamptey', 'odarteypaul690@mail.com', '$2y$10$EpznseWrjwqUrSvA2UbjauDeB/0w4mxYjPR1amClaM2ebFJYuYX4i', 'student', 1, 'active', 0, NULL, '2025-06-28 11:34:24', 1, '2025-06-30 20:04:54', NULL, '42c14129c820dfe8596ab36a49db552068e8eb309fbed56ff74feba89005fafc', '2025-08-03 01:28:14', 0, NULL),
(14, 'patricknartey', 'obibinipaulson@gmail.com', '$2y$10$LB3SiJGFPpUjE4B4Ksa/u.0gmdNlL.j5Jr4QUAJGMbhX8ZczUPG3G', 'faculty', 1, 'rejected', 0, NULL, '2025-06-28 12:21:56', 1, '2025-06-29 20:46:39', NULL, NULL, NULL, 0, NULL),
(15, 'gladyslamptey', 'gladys10@gmail.com', '$2y$10$alK2agD/2V4FpnWbHLZUj.xaz5RxuX7StlRbS0K40PR9z6GtqdGKi', 'student', 7, 'active', 1, NULL, '2025-06-28 18:55:24', 1, '2025-07-03 10:59:21', '2025-07-29 23:45:21', NULL, NULL, 0, NULL),
(16, 'dorislamptey', 'doris1010@gmail.com', '$2y$10$fvymdkFjZ6R6GPsDKjaVQ.pQXOEfi91BL2Xj.aUAxDms8vOQjq4/K', 'faculty', 4, 'active', 0, NULL, '2025-06-29 16:54:37', 1, '2025-06-29 21:08:50', NULL, NULL, NULL, 0, NULL),
(17, 'comfortamanuah', 'podartey001@st.ug.edu.gh', '$2y$10$cnwlfb56jzuotKvTY2GOiO6oEcQQ9NndXgzObI8odb7mtNL4qLaYe', 'student', 2, 'rejected', 0, NULL, '2025-07-04 11:43:40', 1, '2025-07-04 11:45:19', NULL, NULL, NULL, 0, NULL),
(18, 'frank.owusu', 'frank.owusu@university.edu', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMaFaO8WMkKddHtCx9x6dW4K6O', 'faculty', 4, 'active', 1, NULL, '2025-07-09 14:10:46', NULL, NULL, NULL, NULL, NULL, 0, NULL),
(19, 'grace.adjei', 'grace.adjei@student.university.edu', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMaFaO8WMkKddHtCx9x6dW4K6O', 'student', 2, 'active', 1, NULL, '2025-07-09 14:11:20', NULL, NULL, NULL, NULL, NULL, 0, NULL),
(20, 'Michael J', 'kingluckycollins@gmail.com', '$2y$10$lJipXKfasmHjM660gg2k9uW.vlrucNeyiTy0wGZp8PuxA.gi6gQKm', 'faculty', 1, 'active', 1, NULL, '2025-07-09 20:03:03', 1, '2025-07-09 20:03:03', '2025-08-03 00:41:31', NULL, NULL, 0, NULL),
(21, 'Diana', 'dianalamptey@gmail.com', '$2y$10$uuJDNLa/QRqw3l2mrGO.BeVkpfPid1Owic28H04Xk1MNOHqpQIE3S', 'student', 8, 'active', 1, NULL, '2025-07-11 15:11:58', 1, '2025-07-11 15:11:58', '2025-07-16 10:15:51', NULL, NULL, 0, NULL),
(22, 'King', 'comfortamanuah70@gmail.com', '$2y$10$YyzhXPyzEF6nGZmmaaNCWuJbnaLhnNBT4qVR0.MNXXix3sl1MX.A2', 'faculty', NULL, 'pending', 0, '3a5e4efc2ce8015a0519a25d7a56dbeefd83e6a5f5b37449410293e840eedf99', '2025-08-01 19:49:25', NULL, NULL, NULL, NULL, NULL, 0, NULL),
(25, 'Final', 'checkfinal1@gmail.com', '$2y$10$QVXUL771zkDeN4pegVM84evqsWP2KdnKN2y5pf0zw7XbuerZxp6mO', 'student', NULL, 'pending', 0, '739c956f4a0f2272018d850de172795e5f3be107b01b4e4c87d43e8d46a3a17c', '2025-08-03 00:21:15', NULL, NULL, NULL, NULL, NULL, 0, NULL);

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `auto_verify_admin` BEFORE INSERT ON `users` FOR EACH ROW BEGIN
    IF NEW.role = 'admin' THEN
        SET NEW.email_verified = TRUE;
        SET NEW.status = 'active';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `create_admin_profile_on_user_insert` AFTER INSERT ON `users` FOR EACH ROW BEGIN
    IF NEW.role = 'admin' THEN
        INSERT INTO admin_profiles (
            user_id, 
            employee_id, 
            first_name, 
            last_name, 
            department, 
            designation,
            date_joined
        ) VALUES (
            NEW.user_id,
            CONCAT('ADMIN', LPAD(NEW.user_id, 3, '0')),
            'Admin',
            'User',
            'Information Technology',
            'System Administrator',
            CURDATE()
        );
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `log_user_creation` AFTER INSERT ON `users` FOR EACH ROW BEGIN
    INSERT INTO audit_logs (user_id, action, table_affected, record_id, new_values, timestamp)
    VALUES (NEW.user_id, 'CREATE_USER', 'users', NEW.user_id, 
            JSON_OBJECT('role', NEW.role, 'email', NEW.email, 'status', NEW.status), NOW());
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `prevent_admin_pending` BEFORE INSERT ON `users` FOR EACH ROW BEGIN
    IF NEW.role = 'admin' AND NEW.status = 'pending' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Admin accounts cannot have pending status';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `user_profiles`
-- (See below for the actual view)
--
CREATE TABLE `user_profiles` (
`user_id` int(11)
,`username` varchar(50)
,`email` varchar(100)
,`role` enum('admin','faculty','student')
,`status` enum('pending','active','inactive','rejected')
,`created_at` timestamp
,`full_name` varchar(101)
,`department` varchar(100)
,`identifier` varchar(20)
,`designation` varchar(50)
,`phone` varchar(15)
);

-- --------------------------------------------------------

--
-- Structure for view `department_overview`
--
DROP TABLE IF EXISTS `department_overview`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `department_overview`  AS SELECT `d`.`department_id` AS `department_id`, `d`.`department_code` AS `department_code`, `d`.`department_name` AS `department_name`, `d`.`description` AS `description`, `d`.`is_active` AS `is_active`, concat(`f`.`first_name`,' ',`f`.`last_name`) AS `department_head_name`, `f`.`employee_id` AS `head_employee_id`, count(distinct `u`.`user_id`) AS `total_users`, count(distinct case when `u`.`role` = 'faculty' and `u`.`status` = 'active' then `u`.`user_id` end) AS `active_faculty`, count(distinct case when `u`.`role` = 'student' and `u`.`status` = 'active' then `u`.`user_id` end) AS `active_students`, count(distinct `s`.`subject_id`) AS `total_subjects`, count(distinct `c`.`classroom_id`) AS `owned_classrooms`, count(distinct case when `c`.`is_shared` = 1 then `c`.`classroom_id` end) AS `shared_classrooms` FROM ((((`departments` `d` left join `faculty` `f` on(`d`.`department_head_id` = `f`.`faculty_id`)) left join `users` `u` on(`d`.`department_id` = `u`.`department_id`)) left join `subjects` `s` on(`d`.`department_id` = `s`.`department_id` and `s`.`is_active` = 1)) left join `classrooms` `c` on(`d`.`department_id` = `c`.`department_id` and `c`.`is_active` = 1)) WHERE `d`.`is_active` = 1 GROUP BY `d`.`department_id` ;

-- --------------------------------------------------------

--
-- Structure for view `faculty_schedules`
--
DROP TABLE IF EXISTS `faculty_schedules`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `faculty_schedules`  AS SELECT `f`.`faculty_id` AS `faculty_id`, concat(`f`.`first_name`,' ',`f`.`last_name`) AS `faculty_name`, `f`.`employee_id` AS `employee_id`, `s`.`subject_code` AS `subject_code`, `s`.`subject_name` AS `subject_name`, `c`.`room_number` AS `room_number`, `c`.`building` AS `building`, `ts`.`day_of_week` AS `day_of_week`, `ts`.`start_time` AS `start_time`, `ts`.`end_time` AS `end_time`, `t`.`section` AS `section`, `t`.`semester` AS `semester`, `t`.`academic_year` AS `academic_year`, count(`e`.`enrollment_id`) AS `enrolled_students` FROM (((((`faculty` `f` join `timetables` `t` on(`f`.`faculty_id` = `t`.`faculty_id`)) join `subjects` `s` on(`t`.`subject_id` = `s`.`subject_id`)) join `classrooms` `c` on(`t`.`classroom_id` = `c`.`classroom_id`)) join `time_slots` `ts` on(`t`.`slot_id` = `ts`.`slot_id`)) left join `enrollments` `e` on(`t`.`subject_id` = `e`.`subject_id` and `t`.`section` = `e`.`section` and `t`.`academic_year` = `e`.`academic_year` and `t`.`semester` = `e`.`semester` and `e`.`status` = 'enrolled')) WHERE `t`.`is_active` = 1 GROUP BY `t`.`timetable_id` ;

-- --------------------------------------------------------

--
-- Structure for view `student_schedules`
--
DROP TABLE IF EXISTS `student_schedules`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `student_schedules`  AS SELECT `s`.`student_id` AS `student_id`, concat(`s`.`first_name`,' ',`s`.`last_name`) AS `student_name`, `s`.`student_number` AS `student_number`, `sub`.`subject_code` AS `subject_code`, `sub`.`subject_name` AS `subject_name`, concat(`f`.`first_name`,' ',`f`.`last_name`) AS `faculty_name`, `c`.`room_number` AS `room_number`, `c`.`building` AS `building`, `ts`.`day_of_week` AS `day_of_week`, `ts`.`start_time` AS `start_time`, `ts`.`end_time` AS `end_time`, `e`.`section` AS `section`, `e`.`semester` AS `semester`, `e`.`academic_year` AS `academic_year`, `e`.`status` AS `enrollment_status` FROM ((((((`students` `s` join `enrollments` `e` on(`s`.`student_id` = `e`.`student_id`)) join `subjects` `sub` on(`e`.`subject_id` = `sub`.`subject_id`)) join `timetables` `t` on(`sub`.`subject_id` = `t`.`subject_id` and `e`.`section` = `t`.`section` and `e`.`semester` = `t`.`semester` and `e`.`academic_year` = `t`.`academic_year`)) join `faculty` `f` on(`t`.`faculty_id` = `f`.`faculty_id`)) join `classrooms` `c` on(`t`.`classroom_id` = `c`.`classroom_id`)) join `time_slots` `ts` on(`t`.`slot_id` = `ts`.`slot_id`)) WHERE `e`.`status` = 'enrolled' AND `t`.`is_active` = 1 ;

-- --------------------------------------------------------

--
-- Structure for view `timetable_details`
--
DROP TABLE IF EXISTS `timetable_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `timetable_details`  AS SELECT `t`.`timetable_id` AS `timetable_id`, `s`.`subject_code` AS `subject_code`, `s`.`subject_name` AS `subject_name`, `s`.`credits` AS `credits`, concat(`f`.`first_name`,' ',`f`.`last_name`) AS `faculty_name`, `c`.`room_number` AS `room_number`, `c`.`building` AS `building`, `c`.`capacity` AS `capacity`, `ts`.`day_of_week` AS `day_of_week`, `ts`.`start_time` AS `start_time`, `ts`.`end_time` AS `end_time`, `ts`.`slot_name` AS `slot_name`, `t`.`section` AS `section`, `t`.`semester` AS `semester`, `t`.`academic_year` AS `academic_year`, `t`.`is_active` AS `is_active`, count(`e`.`enrollment_id`) AS `enrolled_students` FROM (((((`timetables` `t` join `subjects` `s` on(`t`.`subject_id` = `s`.`subject_id`)) join `faculty` `f` on(`t`.`faculty_id` = `f`.`faculty_id`)) join `classrooms` `c` on(`t`.`classroom_id` = `c`.`classroom_id`)) join `time_slots` `ts` on(`t`.`slot_id` = `ts`.`slot_id`)) left join `enrollments` `e` on(`t`.`subject_id` = `e`.`subject_id` and `t`.`section` = `e`.`section` and `t`.`academic_year` = `e`.`academic_year` and `t`.`semester` = `e`.`semester` and `e`.`status` = 'enrolled')) WHERE `t`.`is_active` = 1 GROUP BY `t`.`timetable_id` ;

-- --------------------------------------------------------

--
-- Structure for view `user_profiles`
--
DROP TABLE IF EXISTS `user_profiles`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `user_profiles`  AS SELECT `u`.`user_id` AS `user_id`, `u`.`username` AS `username`, `u`.`email` AS `email`, `u`.`role` AS `role`, `u`.`status` AS `status`, `u`.`created_at` AS `created_at`, CASE WHEN `u`.`role` = 'admin' THEN concat(`a`.`first_name`,' ',`a`.`last_name`) WHEN `u`.`role` = 'student' THEN concat(`s`.`first_name`,' ',`s`.`last_name`) WHEN `u`.`role` = 'faculty' THEN concat(`f`.`first_name`,' ',`f`.`last_name`) ELSE `u`.`username` END AS `full_name`, CASE WHEN `u`.`role` = 'admin' THEN `a`.`department` WHEN `u`.`role` = 'student' THEN `s`.`department` WHEN `u`.`role` = 'faculty' THEN `f`.`department` ELSE NULL END AS `department`, CASE WHEN `u`.`role` = 'admin' THEN `a`.`employee_id` WHEN `u`.`role` = 'student' THEN `s`.`student_number` WHEN `u`.`role` = 'faculty' THEN `f`.`employee_id` ELSE NULL END AS `identifier`, CASE WHEN `u`.`role` = 'admin' THEN `a`.`designation` WHEN `u`.`role` = 'faculty' THEN `f`.`designation` ELSE NULL END AS `designation`, CASE WHEN `u`.`role` = 'admin' THEN `a`.`phone` WHEN `u`.`role` = 'student' THEN `s`.`phone` WHEN `u`.`role` = 'faculty' THEN `f`.`phone` ELSE NULL END AS `phone` FROM (((`users` `u` left join `admin_profiles` `a` on(`u`.`user_id` = `a`.`user_id` and `u`.`role` = 'admin')) left join `students` `s` on(`u`.`user_id` = `s`.`user_id` and `u`.`role` = 'student')) left join `faculty` `f` on(`u`.`user_id` = `f`.`user_id` and `u`.`role` = 'faculty')) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_profiles`
--
ALTER TABLE `admin_profiles`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `idx_employee_id` (`employee_id`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_designation` (`designation`),
  ADD KEY `idx_name` (`first_name`,`last_name`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_table` (`table_affected`),
  ADD KEY `idx_timestamp` (`timestamp`),
  ADD KEY `idx_session` (`session_id`);

--
-- Indexes for table `backup_logs`
--
ALTER TABLE `backup_logs`
  ADD PRIMARY KEY (`backup_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `deleted_by` (`deleted_by`);

--
-- Indexes for table `classrooms`
--
ALTER TABLE `classrooms`
  ADD PRIMARY KEY (`classroom_id`),
  ADD UNIQUE KEY `unique_room` (`room_number`,`building`),
  ADD KEY `idx_building` (`building`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_capacity` (`capacity`),
  ADD KEY `idx_status_active` (`status`,`is_active`),
  ADD KEY `idx_classroom_department` (`department_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`),
  ADD UNIQUE KEY `department_code` (`department_code`),
  ADD KEY `idx_department_code` (`department_code`),
  ADD KEY `idx_department_head` (`department_head_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `department_resources`
--
ALTER TABLE `department_resources`
  ADD PRIMARY KEY (`resource_id`),
  ADD KEY `idx_owner_dept` (`owner_department_id`),
  ADD KEY `idx_shared_dept` (`shared_with_department_id`),
  ADD KEY `idx_resource_type` (`resource_type`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `fk_resource_creator` (`created_by`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD UNIQUE KEY `unique_enrollment` (`student_id`,`subject_id`,`section`,`academic_year`,`semester`),
  ADD KEY `enrolled_by` (`enrolled_by`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_subject` (`subject_id`),
  ADD KEY `idx_semester_year` (`semester`,`academic_year`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_section` (`section`),
  ADD KEY `idx_enrollments_lookup` (`academic_year`,`semester`,`status`);

--
-- Indexes for table `faculty`
--
ALTER TABLE `faculty`
  ADD PRIMARY KEY (`faculty_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `idx_employee_id` (`employee_id`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_designation` (`designation`),
  ADD KEY `idx_name` (`first_name`,`last_name`);

--
-- Indexes for table `faculty_subjects`
--
ALTER TABLE `faculty_subjects`
  ADD PRIMARY KEY (`assignment_id`),
  ADD UNIQUE KEY `unique_active_assignment` (`faculty_id`,`subject_id`,`is_active`),
  ADD KEY `idx_faculty` (`faculty_id`),
  ADD KEY `idx_subject` (`subject_id`),
  ADD KEY `idx_assigned_by` (`assigned_by`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_target_role` (`target_role`),
  ADD KEY `idx_target_user` (`target_user_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `idx_read_status` (`is_read`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_notifications_target` (`target_role`,`target_user_id`,`is_active`);

--
-- Indexes for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`token_id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_token_lookup` (`token_hash`,`expires_at`,`is_active`),
  ADD KEY `idx_user_active_tokens` (`user_id`,`is_active`,`expires_at`),
  ADD KEY `idx_remember_cleanup` (`is_active`,`created_at`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `student_number` (`student_number`),
  ADD KEY `idx_student_number` (`student_number`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_year_semester` (`year_of_study`,`semester`),
  ADD KEY `idx_name` (`first_name`,`last_name`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`subject_id`),
  ADD UNIQUE KEY `subject_code` (`subject_code`),
  ADD KEY `idx_subject_code` (`subject_code`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_semester_year` (`semester`,`year_level`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_subject_department` (`department_id`),
  ADD KEY `idx_subjects_dept_active` (`department_id`,`is_active`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_setting_key` (`setting_key`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `timetables`
--
ALTER TABLE `timetables`
  ADD PRIMARY KEY (`timetable_id`),
  ADD UNIQUE KEY `unique_faculty_slot` (`faculty_id`,`slot_id`,`academic_year`,`semester`,`is_active`),
  ADD UNIQUE KEY `unique_classroom_slot` (`classroom_id`,`slot_id`,`academic_year`,`semester`,`is_active`),
  ADD KEY `modified_by` (`modified_by`),
  ADD KEY `idx_subject` (`subject_id`),
  ADD KEY `idx_faculty` (`faculty_id`),
  ADD KEY `idx_classroom` (`classroom_id`),
  ADD KEY `idx_slot` (`slot_id`),
  ADD KEY `idx_semester_year` (`semester`,`academic_year`),
  ADD KEY `idx_section` (`section`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_timetables_lookup` (`academic_year`,`semester`,`is_active`),
  ADD KEY `idx_timetables_year_sem` (`academic_year`,`semester`,`is_active`);

--
-- Indexes for table `time_slots`
--
ALTER TABLE `time_slots`
  ADD PRIMARY KEY (`slot_id`),
  ADD UNIQUE KEY `unique_time_slot` (`day_of_week`,`start_time`,`end_time`),
  ADD KEY `idx_day` (`day_of_week`),
  ADD KEY `idx_time_range` (`start_time`,`end_time`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_slot_type` (`slot_type`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role_status` (`role`,`status`),
  ADD KEY `idx_verification_token` (`verification_token`),
  ADD KEY `idx_password_reset_token` (`password_reset_token`),
  ADD KEY `idx_users_auth` (`email`,`password_hash`,`status`),
  ADD KEY `idx_users_department_id` (`department_id`),
  ADD KEY `idx_users_dept_role` (`department_id`,`role`,`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_profiles`
--
ALTER TABLE `admin_profiles`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=236;

--
-- AUTO_INCREMENT for table `backup_logs`
--
ALTER TABLE `backup_logs`
  MODIFY `backup_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `classrooms`
--
ALTER TABLE `classrooms`
  MODIFY `classroom_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `department_resources`
--
ALTER TABLE `department_resources`
  MODIFY `resource_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `faculty_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `faculty_subjects`
--
ALTER TABLE `faculty_subjects`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `token_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `subject_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `timetables`
--
ALTER TABLE `timetables`
  MODIFY `timetable_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `time_slots`
--
ALTER TABLE `time_slots`
  MODIFY `slot_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_profiles`
--
ALTER TABLE `admin_profiles`
  ADD CONSTRAINT `admin_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `backup_logs`
--
ALTER TABLE `backup_logs`
  ADD CONSTRAINT `backup_logs_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `backup_logs_ibfk_2` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `classrooms`
--
ALTER TABLE `classrooms`
  ADD CONSTRAINT `fk_classrooms_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL;

--
-- Constraints for table `department_resources`
--
ALTER TABLE `department_resources`
  ADD CONSTRAINT `fk_resource_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_resource_owner_dept` FOREIGN KEY (`owner_department_id`) REFERENCES `departments` (`department_id`),
  ADD CONSTRAINT `fk_resource_shared_dept` FOREIGN KEY (`shared_with_department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`),
  ADD CONSTRAINT `enrollments_ibfk_3` FOREIGN KEY (`enrolled_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `faculty`
--
ALTER TABLE `faculty`
  ADD CONSTRAINT `faculty_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `faculty_subjects`
--
ALTER TABLE `faculty_subjects`
  ADD CONSTRAINT `faculty_subjects_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `faculty_subjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `faculty_subjects_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `fk_remember_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `fk_subjects_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`);

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `timetables`
--
ALTER TABLE `timetables`
  ADD CONSTRAINT `timetables_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`),
  ADD CONSTRAINT `timetables_ibfk_2` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`),
  ADD CONSTRAINT `timetables_ibfk_3` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`classroom_id`),
  ADD CONSTRAINT `timetables_ibfk_4` FOREIGN KEY (`slot_id`) REFERENCES `time_slots` (`slot_id`),
  ADD CONSTRAINT `timetables_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `timetables_ibfk_6` FOREIGN KEY (`modified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
