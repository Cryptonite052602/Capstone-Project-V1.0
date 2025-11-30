-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 21, 2025 at 12:29 PM
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
-- Database: `healthpatient`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`, `full_name`, `created_at`) VALUES
(1, 'admin', '$2y$10$ZnTO45cy54Fi30sQ.04qPehw./Z7YAxrirQWR.qE3b/RLcpivKaTm', 'System Administrator', '2025-05-01 21:20:54');

-- --------------------------------------------------------

--
-- Table structure for table `announcement_targets`
--

CREATE TABLE `announcement_targets` (
  `id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointment_reschedule_log`
--

CREATE TABLE `appointment_reschedule_log` (
  `id` int(11) NOT NULL,
  `user_appointment_id` int(11) NOT NULL,
  `old_appointment_id` int(11) NOT NULL,
  `new_appointment_id` int(11) NOT NULL,
  `reschedule_date` datetime NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('admin','staff','user') NOT NULL,
  `action` varchar(255) NOT NULL,
  `table_affected` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `user_type`, `action`, `table_affected`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 2, 'staff', 'create patient', 'sitio1_patients', 10, NULL, NULL, '::1', NULL, '2025-05-04 01:40:46');

-- --------------------------------------------------------

--
-- Table structure for table `chat_sessions`
--

CREATE TABLE `chat_sessions` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `status` enum('active','closed','archived') DEFAULT 'active',
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ended_at` timestamp NULL DEFAULT NULL,
  `last_message_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deleted_patients`
--

CREATE TABLE `deleted_patients` (
  `id` int(11) NOT NULL,
  `original_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `last_checkup` date DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `existing_info_patients`
--

CREATE TABLE `existing_info_patients` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `blood_type` varchar(3) DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `current_medications` text DEFAULT NULL,
  `family_history` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `temperature` decimal(4,2) DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `immunization_record` text DEFAULT NULL,
  `chronic_conditions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `existing_info_patients`
--

INSERT INTO `existing_info_patients` (`id`, `patient_id`, `gender`, `height`, `weight`, `blood_type`, `allergies`, `medical_history`, `current_medications`, `family_history`, `updated_at`, `temperature`, `blood_pressure`, `immunization_record`, `chronic_conditions`) VALUES
(118, 160, 'male', 23.30, 23.30, 'A+', 'sad', 'sda', 'sad', 'asd', '2025-11-18 10:59:09', 45.00, '120/80', 'sad', 'sad');

-- --------------------------------------------------------

--
-- Table structure for table `health_chats`
--

CREATE TABLE `health_chats` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` longtext NOT NULL,
  `sender_type` enum('staff','user') NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `health_questions`
--

CREATE TABLE `health_questions` (
  `id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `question` text NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `health_questions`
--

INSERT INTO `health_questions` (`id`, `category`, `question`, `description`, `icon`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'General Health', 'What symptoms are you experiencing?', 'Describe your current symptoms in detail', '🏥', 1, '2025-11-13 18:32:37', '2025-11-13 18:32:37'),
(2, 'General Health', 'How long have you had these symptoms?', 'Timeline and duration of symptoms', '⏱️', 1, '2025-11-13 18:32:37', '2025-11-13 18:32:37'),
(3, 'Fever', 'What is your current body temperature?', 'Your temperature reading', '🌡️', 1, '2025-11-13 18:32:37', '2025-11-13 18:32:37'),
(4, 'Fever', 'Do you have any cold or flu-like symptoms?', 'Chills, cough, sore throat, etc.', '❄️', 1, '2025-11-13 18:32:37', '2025-11-13 18:32:37'),
(5, 'Pain', 'Where exactly is the pain located?', 'Specify the body part and area', '📍', 1, '2025-11-13 18:32:37', '2025-11-13 18:32:37'),
(6, 'Pain', 'On a scale of 1-10, how severe is the pain?', 'Rate your pain level', '📊', 1, '2025-11-13 18:32:37', '2025-11-13 18:32:37'),
(7, 'Digestive', 'Are you experiencing nausea or vomiting?', 'Describe your digestive symptoms', '🤢', 1, '2025-11-13 18:32:37', '2025-11-13 18:32:37'),
(8, 'Digestive', 'Any changes in appetite or diet?', 'Changes in eating patterns', '🍽️', 1, '2025-11-13 18:32:37', '2025-11-13 18:32:37'),
(9, 'Respiratory', 'Are you having trouble breathing?', 'Shortness of breath or respiratory issues', '💨', 1, '2025-11-13 18:32:37', '2025-11-13 18:32:37'),
(10, 'Respiratory', 'Do you have a persistent cough?', 'Type and duration of cough', '😷', 1, '2025-11-13 18:32:37', '2025-11-13 18:32:37'),
(11, 'Allergy', 'Do you have any known allergies?', 'Medications, food, or environmental allergies', '⚠️', 1, '2025-11-13 18:32:37', '2025-11-13 18:32:37'),
(12, 'Medication', 'What medications are you currently taking?', 'List all current medications and dosages', '💊', 1, '2025-11-13 18:32:37', '2025-11-13 18:32:37'),
(13, 'Pregnancy', 'When is your due date?', 'Expected delivery date', '👶', 1, '2025-11-13 18:32:37', '2025-11-13 18:32:37'),
(14, 'Pregnancy', 'Any complications during pregnancy?', 'Pregnancy-related concerns', '⚕️', 1, '2025-11-13 18:32:37', '2025-11-13 18:32:37'),
(15, 'Chronic', 'Do you have any chronic conditions?', 'Diabetes, hypertension, asthma, etc.', '📋', 1, '2025-11-13 18:32:37', '2025-11-13 18:32:37'),
(16, 'Emergency', 'Is this a medical emergency?', 'Seek immediate medical attention if yes', '🚨', 1, '2025-11-13 18:32:37', '2025-11-13 18:32:37'),
(17, 'Lifestyle', 'How much exercise do you get weekly?', 'Physical activity level', '🏃', 1, '2025-11-13 18:32:37', '2025-11-13 18:32:37'),
(18, 'Lifestyle', 'What is your sleep pattern like?', 'Hours and quality of sleep', '😴', 1, '2025-11-13 18:32:37', '2025-11-13 18:32:37'),
(19, 'Mental Health', 'Are you experiencing stress or anxiety?', 'Mental and emotional health concerns', '🧠', 1, '2025-11-13 18:32:37', '2025-11-13 18:32:37'),
(20, 'Follow-up', 'How are you feeling after treatment?', 'Recovery and follow-up status', '✅', 1, '2025-11-13 18:32:37', '2025-11-13 18:32:37');

-- --------------------------------------------------------

--
-- Table structure for table `patient_visits`
--

CREATE TABLE `patient_visits` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `visit_date` datetime NOT NULL,
  `visit_type` enum('checkup','consultation','emergency','followup') NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `next_visit_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `symptoms` text DEFAULT NULL,
  `vital_signs` text DEFAULT NULL,
  `visit_purpose` varchar(100) DEFAULT NULL,
  `referral_info` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sitio1_announcements`
--

CREATE TABLE `sitio1_announcements` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('normal','medium','high') DEFAULT 'normal',
  `expiry_date` date DEFAULT NULL,
  `post_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','archived','expired') DEFAULT 'active',
  `audience_type` enum('public','specific') NOT NULL DEFAULT 'public',
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sitio1_announcements`
--

INSERT INTO `sitio1_announcements` (`id`, `staff_id`, `title`, `message`, `priority`, `expiry_date`, `post_date`, `updated_at`, `status`, `audience_type`, `image_path`) VALUES
(34, 2, 'asd', 'asdas', 'medium', '2025-11-20', '2025-11-19 03:10:17', NULL, 'active', '', '/community-health-tracker/uploads/announcements/691d3519b0c48.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `sitio1_appointments`
--

CREATE TABLE `sitio1_appointments` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `max_slots` int(11) DEFAULT 1,
  `slots_taken` int(11) NOT NULL DEFAULT 0,
  `slots_available` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `health_condition` varchar(255) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `service_type` enum('General Checkup','Vaccination','Dental','Blood Test') DEFAULT 'General Checkup',
  `is_auto_created` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sitio1_appointments`
--

INSERT INTO `sitio1_appointments` (`id`, `staff_id`, `date`, `start_time`, `end_time`, `max_slots`, `slots_taken`, `slots_available`, `created_at`, `health_condition`, `service_id`, `service_type`, `is_auto_created`) VALUES
(147, 2, '2025-11-18', '16:00:00', '17:00:00', 5, 0, 0, '2025-11-18 06:26:19', NULL, NULL, 'General Checkup', 0);

-- --------------------------------------------------------

--
-- Table structure for table `sitio1_consultations`
--

CREATE TABLE `sitio1_consultations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `response` text DEFAULT NULL,
  `responded_by` int(11) DEFAULT NULL,
  `is_custom` tinyint(1) DEFAULT 0,
  `status` enum('pending','responded') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sitio1_patients`
--

CREATE TABLE `sitio1_patients` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `sitio` varchar(255) DEFAULT NULL,
  `disease` varchar(255) DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `last_checkup` date DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `consultation_type` varchar(20) DEFAULT 'onsite',
  `civil_status` varchar(20) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `consent_given` tinyint(1) DEFAULT 0,
  `consent_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sitio1_patients`
--

INSERT INTO `sitio1_patients` (`id`, `user_id`, `full_name`, `age`, `address`, `sitio`, `disease`, `contact`, `last_checkup`, `medical_history`, `added_by`, `created_at`, `deleted_at`, `gender`, `updated_at`, `consultation_type`, `civil_status`, `occupation`, `consent_given`, `consent_date`) VALUES
(148, NULL, 'Creshiel Manloloyo', 23, 'Toong Cebu City', 'Proper Toong', NULL, '09206001470', '2025-11-17', NULL, 2, '2025-11-17 10:06:37', NULL, 'Female', '2025-11-17 13:30:10', 'onsite', 'Separated', 'Student', 0, NULL),
(155, NULL, 'Creshiel Manloloyo', 23, 'Gawi, Oslob, Cebu', 'Buad', NULL, '09816497664', '2025-11-17', NULL, 2, '2025-11-17 09:41:59', NULL, 'Male', '2025-11-17 09:41:59', 'onsite', 'Single', 'PO', 1, '2025-11-17 17:41:59'),
(156, 142, 'Jerecho Latosa', 23, 'Barangay Toong, Cebu City', 'Kalumboyan', NULL, '09206001470', NULL, NULL, 2, '2025-11-17 10:03:23', NULL, 'male', '2025-11-17 10:03:23', 'onsite', 'separated', 'Student', 1, '2025-11-17 18:03:23'),
(157, 146, 'Franco V. Medina', 22, 'Barangay Toong, Cebu City', 'Badiang', NULL, '09193341279', NULL, NULL, 2, '2025-11-17 10:06:03', NULL, 'male', '2025-11-17 10:06:03', 'onsite', 'single', 'Student', 1, '2025-11-17 18:06:03'),
(158, NULL, 'Warren Miras', 24, 'Labangon Cebu City', 'Pangpang', NULL, '09816497664', '2025-11-18', NULL, 2, '2025-11-17 10:07:31', NULL, 'Male', '2025-11-17 10:07:31', 'onsite', 'Widowed', 'PO', 1, '2025-11-17 18:07:31'),
(159, NULL, 'Rica Mae Java', 34, 'Toong Cebu City', 'NapNapan', NULL, '09206001470', '2025-11-18', NULL, 2, '2025-11-17 10:08:28', NULL, 'Female', '2025-11-17 10:08:28', 'onsite', 'Widowed', 'PO', 1, '2025-11-17 18:08:28'),
(160, 137, 'Warren Miguel Miras', 25, 'Barangay Toong, Cebu City', NULL, NULL, '09206001470', '2025-11-18', NULL, 2, '2025-11-17 11:41:30', NULL, 'male', '2025-11-18 10:55:07', 'onsite', NULL, NULL, 1, '2025-11-17 19:41:30');

-- --------------------------------------------------------

--
-- Table structure for table `sitio1_staff`
--

CREATE TABLE `sitio1_staff` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active',
  `is_active` tinyint(1) DEFAULT 1,
  `work_days` varchar(20) DEFAULT '1111100' COMMENT '7-digit string (1=working, 0=off), Mon-Sun',
  `specialization` varchar(100) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sitio1_staff`
--

INSERT INTO `sitio1_staff` (`id`, `username`, `password`, `full_name`, `position`, `created_by`, `created_at`, `status`, `is_active`, `work_days`, `specialization`, `license_number`) VALUES
(1, 'Lance', '$2y$10$61keyKT9UVY4PQIN1jD2TOLwqv2i5C0cpE82vyi4lBeajqmJg8EbS', 'Lance Christine Gallardo', 'Nurse', 1, '2025-05-01 21:25:31', 'inactive', 0, '1111100', NULL, '1'),
(2, 'Archiel', '$2y$10$6JkB04nXJ2E14yUo5einmusdXo1hIJdLSLgrim2w51DMh8f7T7en.', 'Archiel  Rosel Cabanag', 'Health Worker', 1, '2025-05-01 23:05:06', 'active', 1, '1111100', NULL, '1'),
(3, 'Jerecho', '$2y$10$rpOSWoHbDr1xq2lKxoms/.QlKfNSuC.rkDl343C3WYVgdVuiZ2qvK', 'Jerecho Latosa', 'Health Worker', 1, '2025-05-04 00:53:14', 'inactive', 0, '1111100', NULL, '1'),
(5, 'Arnold', '$2y$10$QQecwmqob/kZq9IDqaHrAODAE8ju9aRDUh0tmV0NS/SoktAwet46i', 'Arnold R. Cabanag', 'Doctor', 1, '2025-08-19 11:29:12', 'inactive', 0, '1111100', NULL, '1');

-- --------------------------------------------------------

--
-- Table structure for table `sitio1_staff_schedule`
--

CREATE TABLE `sitio1_staff_schedule` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `is_working` tinyint(1) DEFAULT 0,
  `start_time` time DEFAULT '08:00:00',
  `end_time` time DEFAULT '17:00:00',
  `deleted_at` datetime DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sitio1_staff_schedule`
--

INSERT INTO `sitio1_staff_schedule` (`id`, `staff_id`, `date`, `is_working`, `start_time`, `end_time`, `deleted_at`, `status`) VALUES
(28, 2, '2025-08-21', 0, '08:00:00', '17:00:00', NULL, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `sitio1_users`
--

CREATE TABLE `sitio1_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `sitio` varchar(100) DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `civil_status` varchar(50) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `approved` tinyint(1) DEFAULT 0,
  `approved_by` int(11) DEFAULT NULL,
  `unique_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','declined') DEFAULT 'pending',
  `role` varchar(20) DEFAULT 'patient',
  `specialization` varchar(255) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `verification_method` enum('manual_verification','id_upload') DEFAULT 'manual_verification',
  `id_image_path` varchar(255) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `verification_notes` text DEFAULT NULL,
  `verification_consent` tinyint(1) DEFAULT 0,
  `id_verified` tinyint(1) DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sitio1_users`
--

INSERT INTO `sitio1_users` (`id`, `username`, `password`, `email`, `full_name`, `gender`, `age`, `address`, `sitio`, `contact`, `civil_status`, `occupation`, `approved`, `approved_by`, `unique_number`, `created_at`, `status`, `role`, `specialization`, `license_number`, `updated_at`, `verification_method`, `id_image_path`, `profile_image`, `verification_notes`, `verification_consent`, `id_verified`, `verified_at`) VALUES
(136, 'Archiel', '$2y$10$letu9vbv0SXMhuQJ2wSdieTLyGZpbfjq0NjgG4KIhPKOlJ3HoGFYS', 'cabanagarchielrosel@gmail.com', 'Archiel  Rosel Cabanag', 'male', 23, 'Barangay Toong, Cebu City', 'Buad', '09816497664', 'separated', 'Student', 0, NULL, NULL, '2025-11-16 18:05:59', 'pending', 'patient', NULL, NULL, NULL, 'id_upload', 'uploads/id_documents/id_Archiel_1763316359.png', NULL, '', 1, 0, NULL),
(137, 'Warren', '$2y$10$6UGSpg0QTXpeu5TDTQaccObRK9UAbHifbHQwcvCBNfYl.sS6IfO6S', 'warrenmiguel789@gmail.com', 'Warren Miguel Miras', 'male', 25, 'Barangay Toong, Cebu City', 'Pangpang', '09206001470', 'married', 'Student', 1, NULL, 'CHT929804', '2025-11-16 18:06:37', 'approved', 'patient', NULL, NULL, '2025-11-18 04:11:42', 'id_upload', 'uploads/id_documents/id_Warren_1763316397.jpg', NULL, '', 1, 0, NULL),
(138, 'Creshiel', '$2y$10$X1.GK4hjUooc43U7FU9NhehMmUvv6rlpyp5cZM//neeiLrJPo1Dey', 'creshielmanloloyo@gmail.com', 'Creshiel Manloloyo', 'female', 22, 'Barangay Toong, Cebu City', 'Acasia', '09312312421', 'divorced', 'Student', 0, NULL, NULL, '2025-11-16 18:08:33', 'pending', 'patient', NULL, NULL, '2025-11-16 18:08:44', 'id_upload', 'uploads/id_documents/id_Creshiel_1763316513.jpg', NULL, '', 1, 1, '2025-11-16 18:08:44'),
(139, 'Rica', '$2y$10$IJReJRMrWQ3qcL19f2i2BOJ8hqp3QHqNb.vfZOCsdh9SROvwv7ite', 'ricamaejava@gmail.com', 'Rica Mae Java', 'female', 24, 'Barangay Toong, Cebu City', 'Caolong', '09193341279', 'married', 'Student', 0, NULL, NULL, '2025-11-16 18:09:46', 'pending', 'patient', NULL, NULL, NULL, 'id_upload', 'uploads/id_documents/id_Rica_1763316586.jpg', NULL, '', 1, 0, NULL),
(140, 'Russel', '$2y$10$dNNipOhwgVaMDidz0w08s.1vQsgxfchkyWJaiWfnPJAsyX7O1nFKm', 'russel@gmail.com', 'Russel Loquinario', 'male', 27, 'Barangay Toong, Cebu City', 'Kaangking', '09193341279', 'separated', 'Student', 0, NULL, NULL, '2025-11-16 18:10:32', 'pending', 'patient', NULL, NULL, NULL, 'id_upload', 'uploads/id_documents/id_Russel_1763316632.png', NULL, '', 1, 0, NULL),
(141, 'Leandro', '$2y$10$AUUBHQjCJUyWSkjOmFgw7u2q79GRkVuplBc4LAeosccMaYQDqU3yq', 'leandrolabos822@gmail.com', 'Leandro Labos', 'male', 26, 'Barangay Toong, Cebu City', 'Bugna', '09312312421', 'single', 'Student', 1, NULL, 'CHT304732', '2025-11-16 18:11:30', 'approved', 'patient', NULL, NULL, '2025-11-19 00:19:04', 'id_upload', 'uploads/id_documents/id_Leandro_1763316690.jpg', NULL, '', 1, 0, NULL),
(142, 'Jerecho', '$2y$10$O629g3YleyR0W50VExl3w.DaOQQJbaO2.nSf7xyj0uuuornXsbTeW', 'jerecho@gmail.com', 'Jerecho Latosa', 'male', 23, 'Barangay Toong, Cebu City', 'Kalumboyan', '09206001470', 'separated', 'Student', 1, NULL, 'CHT203262', '2025-11-16 18:12:46', 'approved', 'patient', NULL, NULL, '2025-11-17 10:02:42', 'id_upload', 'uploads/id_documents/id_Jerecho_1763316766.jpg', NULL, '', 1, 0, NULL),
(143, 'Ramon', '$2y$10$8Kqf4fqbFRMfSpeo2dgFHup.93MdyUjIRNY2PNo7ndOwOtzk7MCGe', 'johnramon@gmail.com', 'John Ramon Arancillo', 'male', 24, 'Barangay Toong, Cebu City', 'Buyo', '09312312421', 'widowed', 'Student', 0, NULL, NULL, '2025-11-16 18:14:21', 'pending', 'patient', NULL, NULL, '2025-11-18 01:14:03', 'id_upload', 'uploads/id_documents/id_Ramon_1763316861.jpg', NULL, '', 1, 1, '2025-11-18 01:14:03'),
(144, 'Jaycar', '$2y$10$mDIDcEE7qN52UKSxaa2d0OMT1HfeYdXgKh8s2wBGBDQr9Gjw0cYcO', 'jaycarotida@gmail.com', 'Jaycar Otida', 'male', 23, 'Barangay Toong, Cebu City', 'NapNapan', '09551008414', 'married', 'Student', 1, NULL, 'CHT405284', '2025-11-17 01:17:11', 'approved', 'patient', NULL, NULL, '2025-11-17 15:25:13', 'id_upload', 'uploads/id_documents/id_Jaycar_1763342231.jpg', NULL, '', 1, 0, NULL),
(146, 'Franco', '$2y$10$ZpERLXTFW4PjnoZQL0CR8ePf30eGwDAw07zpnDra7jTfbWgGXEeeO', 'francomedina@gmail.com', 'Franco V. Medina', 'male', 22, 'Barangay Toong, Cebu City', 'Badiang', '09193341279', 'single', 'Student', 1, NULL, 'CHT637489', '2025-11-17 01:21:31', 'approved', 'patient', NULL, NULL, '2025-11-17 10:05:32', 'id_upload', 'uploads/id_documents/id_Franco_1763342491.jpg', NULL, '', 1, 0, NULL),
(147, 'Gil', '$2y$10$6JY1/5BmaWRBdAEwWXcA8.TWR4XNxw56NxziTFoSTlVlCuqPFwpiS', 'gilarda@gmail.com', 'Gil Arda', 'male', 24, 'Barangay Toong, Cebu City', 'Angay-Angay', '09206001470', 'single', 'Student', 1, NULL, NULL, '2025-11-17 01:23:29', 'approved', 'patient', NULL, NULL, '2025-11-18 08:46:05', 'id_upload', 'uploads/id_documents/id_Gil_1763342608.jpg', NULL, '', 1, 0, '2025-11-18 08:46:05'),
(148, 'Daniel', '$2y$10$WVxzCz0JR8ivQxzO1BW4tet9KUNFVHf/kPUptDdBOTOmrIvprQUIq', 'danielsollano@gmail.com', 'Daniel T. Sollano', 'male', 24, 'Barangay Toong, Cebu City', 'Buacao', '09816497664', 'single', 'Student', 1, NULL, 'CHT388527', '2025-11-17 01:25:57', 'approved', 'patient', NULL, NULL, '2025-11-18 08:45:16', 'id_upload', 'uploads/id_documents/id_Daniel_1763342757.jpg', NULL, '', 1, 0, NULL),
(149, 'John Carl', '$2y$10$CO6Y7bjO8/EC.3PjHraiVerJu927GoM0JruIOn1k1V/DBFHu03Qum', 'johncarl@gmail.com', 'John Carl Villamonte', 'male', 25, 'Barangay Toong, Cebu City', 'Lower Toong', '09193341279', 'married', 'Student', 0, NULL, NULL, '2025-11-17 01:28:41', 'declined', 'patient', NULL, NULL, '2025-11-18 08:44:47', 'id_upload', 'uploads/id_documents/id_John Carl_1763342921.jpg', NULL, '', 1, 0, NULL),
(150, 'Christopher', '$2y$10$KsETdUM3BQjW0tX6tWm27eOuglFVzURtEyh17XkvOSZnrRTOPY6su', 'lancechristopher@gmail.com', 'Lance Christopher Gallardo', 'male', 28, 'Barangay Toong, Cebu City', 'Proper Toong', '09551008414', 'divorced', 'Student', 0, NULL, NULL, '2025-11-17 01:31:04', 'declined', 'patient', NULL, NULL, '2025-11-18 08:43:21', 'id_upload', 'uploads/id_documents/id_Christopher_1763343064.png', NULL, '', 1, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `staff_documents`
--

CREATE TABLE `staff_documents` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` int(11) NOT NULL COMMENT 'in bytes',
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_documents`
--

INSERT INTO `staff_documents` (`id`, `title`, `description`, `file_name`, `file_type`, `file_size`, `uploaded_by`, `uploaded_at`, `is_public`, `created_at`, `updated_at`) VALUES
(7, 'Health Records - 2025-2026', 'For Keeps', '1746553255_consultations_report_2025-05-01_to_2025-05-31.csv', '', 0, 1, '2025-05-06 17:40:55', 0, '2025-05-06 17:40:55', '2025-05-06 17:40:55');

-- --------------------------------------------------------

--
-- Table structure for table `user_announcements`
--

CREATE TABLE `user_announcements` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `status` enum('accepted','dismissed') NOT NULL,
  `response_date` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_appointments`
--

CREATE TABLE `user_appointments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) NOT NULL,
  `status` enum('pending','approved','completed','cancelled','rejected','rescheduled') NOT NULL DEFAULT 'pending',
  `priority_number` varchar(50) DEFAULT NULL,
  `invoice_number` varchar(50) DEFAULT NULL,
  `rescheduled_from` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `rejection_reason` text DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `last_processed` datetime DEFAULT NULL,
  `reschedule_count` int(11) DEFAULT 0,
  `previous_appointment_id` int(11) DEFAULT NULL,
  `service_type` enum('General Checkup','Vaccination','Dental','Blood Test') DEFAULT 'General Checkup',
  `processed_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when appointment was processed (approved/rejected/completed)',
  `invoice_generated_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `health_concerns` text DEFAULT NULL,
  `consent` datetime DEFAULT NULL COMMENT 'Timestamp when consent was given',
  `rescheduled_at` datetime DEFAULT NULL,
  `rescheduled_count` int(11) DEFAULT 0,
  `cancelled_by_user` tinyint(1) DEFAULT 0,
  `appointment_ticket` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_appointments`
--

INSERT INTO `user_appointments` (`id`, `user_id`, `service_id`, `appointment_id`, `status`, `priority_number`, `invoice_number`, `rescheduled_from`, `notes`, `created_at`, `rejection_reason`, `cancel_reason`, `cancelled_at`, `last_processed`, `reschedule_count`, `previous_appointment_id`, `service_type`, `processed_at`, `invoice_generated_at`, `completed_at`, `health_concerns`, `consent`, `rescheduled_at`, `rescheduled_count`, `cancelled_by_user`, `appointment_ticket`) VALUES
(211, 137, 0, 147, 'rejected', NULL, NULL, NULL, 'sad', '2025-11-18 06:26:33', 'sad', NULL, NULL, NULL, 0, NULL, 'General Checkup', NULL, NULL, NULL, 'Other', '0000-00-00 00:00:00', NULL, 0, 0, NULL),
(212, 137, 0, 147, 'completed', 'HW-00', 'INV-20251118-0212', NULL, 'sad', '2025-11-18 06:29:24', NULL, NULL, NULL, NULL, 0, NULL, 'General Checkup', '2025-11-18 06:29:32', '2025-11-18 14:29:32', '2025-11-18 19:15:37', 'Other', '0000-00-00 00:00:00', NULL, 0, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_notifications`
--

CREATE TABLE `user_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `announcement_targets`
--
ALTER TABLE `announcement_targets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `announcement_id` (`announcement_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `appointment_reschedule_log`
--
ALTER TABLE `appointment_reschedule_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_appointment_id` (`user_appointment_id`),
  ADD KEY `old_appointment_id` (`old_appointment_id`),
  ADD KEY `new_appointment_id` (`new_appointment_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_sessions`
--
ALTER TABLE `chat_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_conversation` (`staff_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_last_message` (`last_message_at`);

--
-- Indexes for table `deleted_patients`
--
ALTER TABLE `deleted_patients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `existing_info_patients`
--
ALTER TABLE `existing_info_patients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `health_chats`
--
ALTER TABLE `health_chats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_staff_user` (`staff_id`,`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `health_questions`
--
ALTER TABLE `health_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `patient_visits`
--
ALTER TABLE `patient_visits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `visit_date` (`visit_date`);

--
-- Indexes for table `sitio1_announcements`
--
ALTER TABLE `sitio1_announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `sitio1_appointments`
--
ALTER TABLE `sitio1_appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `sitio1_consultations`
--
ALTER TABLE `sitio1_consultations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `responded_by` (`responded_by`),
  ADD KEY `sitio1_consultations_ibfk_1` (`user_id`);

--
-- Indexes for table `sitio1_patients`
--
ALTER TABLE `sitio1_patients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `added_by` (`added_by`);

--
-- Indexes for table `sitio1_staff`
--
ALTER TABLE `sitio1_staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `sitio1_staff_schedule`
--
ALTER TABLE `sitio1_staff_schedule`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_staff_date` (`staff_id`,`date`);

--
-- Indexes for table `sitio1_users`
--
ALTER TABLE `sitio1_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email_UNIQUE` (`email`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `staff_documents`
--
ALTER TABLE `staff_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `user_announcements`
--
ALTER TABLE `user_announcements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_announcement` (`user_id`,`announcement_id`),
  ADD KEY `user_announcements_ibfk_2` (`announcement_id`);

--
-- Indexes for table `user_appointments`
--
ALTER TABLE `user_appointments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `announcement_targets`
--
ALTER TABLE `announcement_targets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `appointment_reschedule_log`
--
ALTER TABLE `appointment_reschedule_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `chat_sessions`
--
ALTER TABLE `chat_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deleted_patients`
--
ALTER TABLE `deleted_patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `existing_info_patients`
--
ALTER TABLE `existing_info_patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=119;

--
-- AUTO_INCREMENT for table `health_chats`
--
ALTER TABLE `health_chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `health_questions`
--
ALTER TABLE `health_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `patient_visits`
--
ALTER TABLE `patient_visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `sitio1_announcements`
--
ALTER TABLE `sitio1_announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `sitio1_appointments`
--
ALTER TABLE `sitio1_appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=148;

--
-- AUTO_INCREMENT for table `sitio1_consultations`
--
ALTER TABLE `sitio1_consultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `sitio1_patients`
--
ALTER TABLE `sitio1_patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

--
-- AUTO_INCREMENT for table `sitio1_staff`
--
ALTER TABLE `sitio1_staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sitio1_staff_schedule`
--
ALTER TABLE `sitio1_staff_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `sitio1_users`
--
ALTER TABLE `sitio1_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=151;

--
-- AUTO_INCREMENT for table `staff_documents`
--
ALTER TABLE `staff_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_announcements`
--
ALTER TABLE `user_announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `user_appointments`
--
ALTER TABLE `user_appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=213;

--
-- AUTO_INCREMENT for table `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcement_targets`
--
ALTER TABLE `announcement_targets`
  ADD CONSTRAINT `announcement_targets_ibfk_1` FOREIGN KEY (`announcement_id`) REFERENCES `sitio1_announcements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcement_targets_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `sitio1_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `appointment_reschedule_log`
--
ALTER TABLE `appointment_reschedule_log`
  ADD CONSTRAINT `appointment_reschedule_log_ibfk_1` FOREIGN KEY (`user_appointment_id`) REFERENCES `user_appointments` (`id`),
  ADD CONSTRAINT `appointment_reschedule_log_ibfk_2` FOREIGN KEY (`old_appointment_id`) REFERENCES `sitio1_appointments` (`id`),
  ADD CONSTRAINT `appointment_reschedule_log_ibfk_3` FOREIGN KEY (`new_appointment_id`) REFERENCES `sitio1_appointments` (`id`);

--
-- Constraints for table `chat_sessions`
--
ALTER TABLE `chat_sessions`
  ADD CONSTRAINT `chat_sessions_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `sitio1_staff` (`id`),
  ADD CONSTRAINT `chat_sessions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `sitio1_users` (`id`);

--
-- Constraints for table `existing_info_patients`
--
ALTER TABLE `existing_info_patients`
  ADD CONSTRAINT `existing_info_patients_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `sitio1_patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `health_chats`
--
ALTER TABLE `health_chats`
  ADD CONSTRAINT `health_chats_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `sitio1_staff` (`id`),
  ADD CONSTRAINT `health_chats_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `sitio1_users` (`id`);

--
-- Constraints for table `sitio1_announcements`
--
ALTER TABLE `sitio1_announcements`
  ADD CONSTRAINT `sitio1_announcements_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `sitio1_staff` (`id`);

--
-- Constraints for table `sitio1_appointments`
--
ALTER TABLE `sitio1_appointments`
  ADD CONSTRAINT `sitio1_appointments_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `sitio1_staff` (`id`);

--
-- Constraints for table `sitio1_consultations`
--
ALTER TABLE `sitio1_consultations`
  ADD CONSTRAINT `sitio1_consultations_ibfk_2` FOREIGN KEY (`responded_by`) REFERENCES `sitio1_staff` (`id`);

--
-- Constraints for table `sitio1_patients`
--
ALTER TABLE `sitio1_patients`
  ADD CONSTRAINT `sitio1_patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `sitio1_users` (`id`),
  ADD CONSTRAINT `sitio1_patients_ibfk_2` FOREIGN KEY (`added_by`) REFERENCES `sitio1_staff` (`id`);

--
-- Constraints for table `sitio1_staff`
--
ALTER TABLE `sitio1_staff`
  ADD CONSTRAINT `sitio1_staff_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admin` (`id`);

--
-- Constraints for table `sitio1_staff_schedule`
--
ALTER TABLE `sitio1_staff_schedule`
  ADD CONSTRAINT `sitio1_staff_schedule_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `sitio1_staff` (`id`);

--
-- Constraints for table `sitio1_users`
--
ALTER TABLE `sitio1_users`
  ADD CONSTRAINT `sitio1_users_ibfk_1` FOREIGN KEY (`approved_by`) REFERENCES `sitio1_staff` (`id`);

--
-- Constraints for table `staff_documents`
--
ALTER TABLE `staff_documents`
  ADD CONSTRAINT `staff_documents_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `admin` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_announcements`
--
ALTER TABLE `user_announcements`
  ADD CONSTRAINT `user_announcements_ibfk_2` FOREIGN KEY (`announcement_id`) REFERENCES `sitio1_announcements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD CONSTRAINT `user_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `sitio1_users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
