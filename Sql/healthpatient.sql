-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 15, 2026 at 03:07 AM
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
  `date_of_birth` date DEFAULT NULL,
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

--
-- Dumping data for table `deleted_patients`
--

INSERT INTO `deleted_patients` (`id`, `original_id`, `full_name`, `date_of_birth`, `age`, `gender`, `address`, `contact`, `last_checkup`, `added_by`, `user_id`, `deleted_at`, `deleted_by`) VALUES
(86, 175, 'Russel Evan Loquinario', '2000-01-15', 26, 'Male', 'Tisa Cebu City', '09206001470', '2026-01-15', 2, NULL, '2026-01-15 02:02:47', 2);

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
(129, 166, 'Male', 34.00, 34.00, 'AB+', 'sad', 'sad', 'sad', 'sad', '2026-01-08 11:51:56', 45.00, '120/80', 'sad', 'sad'),
(142, 172, 'Female', 45.00, 45.00, 'B+', 'sad', 'asd', 'asd', 'asd', NULL, 45.00, '120/80', 'sad', 'asd'),
(146, 174, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

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
  `audience_type` enum('public','specific','landing_page') NOT NULL DEFAULT 'public',
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sitio1_announcements`
--

INSERT INTO `sitio1_announcements` (`id`, `staff_id`, `title`, `message`, `priority`, `expiry_date`, `post_date`, `updated_at`, `status`, `audience_type`, `image_path`) VALUES
(50, 2, 'Cebu Eastern College Inc. Sinulog Festival', 'Everyone must come for the celebration of fiesta senor santo nino here in oval at cebu city', 'medium', '2026-01-10', '2026-01-09 00:05:23', NULL, 'archived', 'landing_page', NULL),
(51, 2, 'Cebu Eastern College Defense', 'Hello', 'normal', '2026-01-14', '2026-01-14 07:17:57', NULL, 'active', 'landing_page', NULL),
(52, 2, 'Cebu Eastern College Defense', 'Hello', 'medium', '2026-01-14', '2026-01-14 07:18:41', NULL, 'active', 'landing_page', NULL),
(53, 2, 'Cebu Eastern College Defense', 'Hello', 'high', '2026-01-14', '2026-01-14 07:19:06', NULL, 'active', 'landing_page', NULL);

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
(172, 2, '2026-01-09', '08:00:00', '09:00:00', 5, 0, 0, '2026-01-08 05:08:14', NULL, NULL, 'General Checkup', 0),
(173, 2, '2026-01-12', '08:00:00', '09:00:00', 5, 0, 0, '2026-01-09 02:32:42', NULL, NULL, 'General Checkup', 0),
(174, 2, '2026-01-12', '09:00:00', '10:00:00', 5, 0, 0, '2026-01-10 12:27:26', NULL, NULL, 'General Checkup', 0),
(175, 2, '2026-01-12', '11:00:00', '12:00:00', 5, 0, 0, '2026-01-12 02:07:30', NULL, NULL, 'General Checkup', 0),
(176, 2, '2026-01-15', '08:00:00', '09:00:00', 5, 0, 0, '2026-01-14 07:02:18', NULL, NULL, 'General Checkup', 0),
(177, 2, '2026-01-16', '08:00:00', '09:00:00', 5, 0, 0, '2026-01-14 07:03:41', NULL, NULL, 'General Checkup', 0),
(178, 2, '2026-01-15', '09:00:00', '10:00:00', 5, 0, 0, '2026-01-14 07:03:56', NULL, NULL, 'General Checkup', 0);

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
  `date_of_birth` date DEFAULT NULL,
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
  `restored_at` timestamp NULL DEFAULT NULL,
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

INSERT INTO `sitio1_patients` (`id`, `user_id`, `full_name`, `date_of_birth`, `age`, `address`, `sitio`, `disease`, `contact`, `last_checkup`, `medical_history`, `added_by`, `created_at`, `deleted_at`, `restored_at`, `gender`, `updated_at`, `consultation_type`, `civil_status`, `occupation`, `consent_given`, `consent_date`) VALUES
(166, NULL, 'Archiel R. Cabanag', '1999-01-08', 27, 'Labangon Cebu City', 'Luz Proper', NULL, '09206001470', '2026-01-08', NULL, 2, '2026-01-08 05:28:14', NULL, NULL, 'Male', '2026-01-08 05:28:14', 'onsite', 'Single', 'Student', 1, '2026-01-08 13:28:14'),
(172, NULL, 'Creshiel Manloloyo', '2026-01-15', 26, 'Labangon Cebu City', 'Panganiban', NULL, '09816497664', '2026-01-15', NULL, 2, '2026-01-14 23:38:51', NULL, '2026-01-14 23:38:51', 'Female', '2026-01-14 23:39:34', 'onsite', 'Single', 'Student', 0, NULL),
(174, 162, 'Jaycar Otida', NULL, 21, 'Barangay Luz, Cebu City', NULL, NULL, '09206001470', '2026-01-15', NULL, 2, '2026-01-15 00:44:56', NULL, NULL, 'Male', '2026-01-15 00:44:56', 'onsite', NULL, NULL, 0, NULL);

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
(1, 'Lance', '$2y$10$61keyKT9UVY4PQIN1jD2TOLwqv2i5C0cpE82vyi4lBeajqmJg8EbS', 'Lance Christine Gallardo', 'Nurse', 1, '2025-05-01 21:25:31', 'active', 1, '1111100', NULL, '1'),
(2, 'Archiel', '$2y$10$6JkB04nXJ2E14yUo5einmusdXo1hIJdLSLgrim2w51DMh8f7T7en.', 'Archiel  Rosel Cabanag', 'Health Worker', 1, '2025-05-01 23:05:06', 'active', 1, '1111100', NULL, '1'),
(3, 'Jerecho', '$2y$10$rpOSWoHbDr1xq2lKxoms/.QlKfNSuC.rkDl343C3WYVgdVuiZ2qvK', 'Jerecho Latosa', 'Health Worker', 1, '2025-05-04 00:53:14', 'active', 1, '1111100', NULL, '1'),
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
  `date_of_birth` date DEFAULT NULL,
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

INSERT INTO `sitio1_users` (`id`, `username`, `password`, `email`, `full_name`, `gender`, `age`, `date_of_birth`, `address`, `sitio`, `contact`, `civil_status`, `occupation`, `approved`, `approved_by`, `unique_number`, `created_at`, `status`, `role`, `specialization`, `license_number`, `updated_at`, `verification_method`, `id_image_path`, `profile_image`, `verification_notes`, `verification_consent`, `id_verified`, `verified_at`) VALUES
(158, 'Warren', '$2y$10$5le3PRRAJGZ9lJffOMWvwulrPIqhJLUG0gAjy8IyPUUrmscWKqDhK', 'warrenmiguel789@gmail.com', 'Warren Miguel Miras', 'male', 25, '2001-01-06', 'Barangay Luz, Cebu City', 'Panganiban', '09206001470', 'married', 'Test', 1, NULL, 'CHT931628', '2026-01-08 04:39:10', 'approved', 'patient', NULL, NULL, '2026-01-08 12:07:44', 'id_upload', 'uploads/id_documents/id_Warren_1767847150.jpg', NULL, 'Hello Po sir', 1, 0, NULL),
(160, 'Russel', '$2y$10$xvmVSl1fpBeYvHBkwGJ2Le/Qse58qDDbnELmriPZvcbzP8Xrqr9BO', 'russel@gmail.com', 'Russel Loquinario', 'male', 31, '1995-01-10', 'Barangay Luz, Cebu City', 'Proper Luz', '09312312421', 'single', 'Test', 0, NULL, NULL, '2026-01-10 12:34:19', 'pending', 'patient', NULL, NULL, NULL, 'id_upload', 'uploads/id_documents/id_Russel_1768048459.pdf', NULL, 'Hello', 1, 0, NULL),
(161, 'Jerecho', '$2y$10$kDHnv2QWqBn6jNYemkxU7ew26thxLOW.utuMUWrVr0IPfhx1ZAWr2', 'jerecho@gmail.com', 'Jerecho Latosa', 'male', 26, '2000-01-10', 'Barangay Luz, Cebu City', 'Panganiban', '09206001470', 'married', 'Test', 1, NULL, 'CHT429900', '2026-01-10 12:39:29', 'approved', 'patient', NULL, NULL, '2026-01-11 13:43:42', 'id_upload', 'uploads/id_documents/id_Jerecho_1768048769.jpg', NULL, 'Boss', 1, 0, NULL),
(162, 'Jaycar', '$2y$10$yq5EHa6NdURONXjJ8HB/yuDTFJNlNWgtRhOb2lLcMv6Hqyzm.d8n6', 'jaycarotida@gmail.com', 'Jaycar Otida', 'male', 21, '2004-04-03', 'Barangay Luz, Cebu City', 'Panganiban', '09206001470', 'single', 'Test', 1, NULL, 'CHT895106', '2026-01-14 06:59:42', 'approved', 'patient', NULL, NULL, '2026-01-14 07:01:26', 'id_upload', 'uploads/id_documents/id_Jaycar_1768373982.jpg', NULL, 'Hello', 1, 0, NULL),
(163, 'Archiel', '$2y$10$7Pc5AbU2AG2o.0grI0ii6.WwF9xAHCsCdQOBPfE4RatsCIS4Lpq22', 'cabanagarchielrosel@gmail.com', 'Archiel  Rosel Cabanag', 'male', 23, '2002-05-26', 'Barangay Luz, Cebu City', 'Panganiban', '09816497664', 'separated', 'Test', 1, NULL, 'CHT049042', '2026-01-14 07:26:42', 'approved', 'patient', NULL, NULL, '2026-01-14 07:28:03', 'id_upload', 'uploads/id_documents/id_Archiel_1768375602.jpg', NULL, 'asdad', 1, 0, NULL);

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
  `status` enum('pending','approved','completed','cancelled','rejected','rescheduled','missed') NOT NULL DEFAULT 'pending',
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
  `appointment_ticket` longtext DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_appointments`
--

INSERT INTO `user_appointments` (`id`, `user_id`, `service_id`, `appointment_id`, `status`, `priority_number`, `invoice_number`, `rescheduled_from`, `notes`, `created_at`, `rejection_reason`, `cancel_reason`, `cancelled_at`, `last_processed`, `reschedule_count`, `previous_appointment_id`, `service_type`, `processed_at`, `invoice_generated_at`, `completed_at`, `health_concerns`, `consent`, `rescheduled_at`, `rescheduled_count`, `cancelled_by_user`, `appointment_ticket`, `updated_at`) VALUES
(268, 159, 174, 174, 'completed', '01', 'INV-20260112-0268', NULL, 'sad', '2026-01-12 01:44:22', NULL, NULL, NULL, NULL, 0, NULL, 'General Checkup', '2026-01-12 01:44:38', '2026-01-12 09:44:38', '2026-01-12 09:45:58', 'Obesity', '0000-00-00 00:00:00', NULL, 0, 0, NULL, '2026-01-12 01:45:58'),
(269, 159, 175, 175, 'cancelled', '1', NULL, NULL, 'sad', '2026-01-12 02:07:51', NULL, 'jgfhfgderwrtgereartfdsf', '2026-01-12 10:10:12', NULL, 0, NULL, 'General Checkup', NULL, NULL, NULL, 'Other', '0000-00-00 00:00:00', NULL, 0, 1, NULL, '2026-01-12 02:10:12'),
(270, 159, 175, 175, 'missed', '1', NULL, NULL, 'bvcbvc', '2026-01-12 02:10:43', NULL, NULL, NULL, NULL, 0, NULL, 'General Checkup', NULL, NULL, NULL, 'Depression', '0000-00-00 00:00:00', NULL, 0, 0, NULL, '2026-01-12 12:00:37'),
(271, 162, 176, 176, 'cancelled', '1', NULL, NULL, 'Sakit ako likod', '2026-01-14 07:05:30', NULL, 'asdasdgasdasdasdasdas', '2026-01-14 15:05:57', NULL, 0, NULL, 'General Checkup', NULL, NULL, NULL, 'Obesity, Other', '0000-00-00 00:00:00', NULL, 0, 1, NULL, '2026-01-14 07:05:57'),
(272, 162, 178, 178, 'rejected', '1', NULL, NULL, 'sad', '2026-01-14 07:06:29', 'scam appointment', NULL, NULL, NULL, 0, NULL, 'General Checkup', NULL, NULL, NULL, 'Other', '0000-00-00 00:00:00', NULL, 0, 0, NULL, '2026-01-14 07:07:19'),
(273, 162, 176, 176, 'completed', '01', 'INV-20260114-0273', NULL, 'sad', '2026-01-14 07:07:38', NULL, NULL, NULL, NULL, 0, NULL, 'General Checkup', '2026-01-14 07:07:55', '2026-01-14 15:07:55', '2026-01-14 15:15:48', 'Depression', '0000-00-00 00:00:00', NULL, 0, 0, NULL, '2026-01-14 07:15:48'),
(274, 162, 178, 178, 'completed', '01', 'INV-20260114-0274', NULL, 'sad', '2026-01-14 12:47:18', NULL, NULL, NULL, NULL, 0, NULL, 'General Checkup', '2026-01-14 12:47:27', '2026-01-14 20:47:27', '2026-01-14 23:11:10', 'Other', '0000-00-00 00:00:00', NULL, 0, 0, NULL, '2026-01-14 15:11:10'),
(275, 162, 177, 177, 'cancelled', '1', NULL, NULL, 'sad', '2026-01-14 15:12:56', NULL, 'asddsadasd', '2026-01-14 23:13:27', NULL, 0, NULL, 'General Checkup', NULL, NULL, NULL, 'Depression', '0000-00-00 00:00:00', NULL, 0, 1, NULL, '2026-01-14 15:13:27'),
(276, 162, 177, 177, 'approved', '01', 'INV-20260115-0276', NULL, 'sad', '2026-01-14 23:27:14', NULL, NULL, NULL, NULL, 0, NULL, 'General Checkup', '2026-01-14 23:27:26', '2026-01-15 07:27:26', NULL, 'Other', '0000-00-00 00:00:00', NULL, 0, 0, NULL, '2026-01-14 23:27:26');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `existing_info_patients`
--
ALTER TABLE `existing_info_patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=148;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `sitio1_appointments`
--
ALTER TABLE `sitio1_appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=179;

--
-- AUTO_INCREMENT for table `sitio1_consultations`
--
ALTER TABLE `sitio1_consultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `sitio1_patients`
--
ALTER TABLE `sitio1_patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=176;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=164;

--
-- AUTO_INCREMENT for table `staff_documents`
--
ALTER TABLE `staff_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_announcements`
--
ALTER TABLE `user_announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `user_appointments`
--
ALTER TABLE `user_appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=277;

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
