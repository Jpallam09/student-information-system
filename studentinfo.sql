-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 06, 2026 at 12:41 PM
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
-- Database: `studentinfo`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `course_id` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `year_level` varchar(20) NOT NULL,
  `section` varchar(50) NOT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `pinned` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `teacher_id`, `course_id`, `title`, `content`, `year_level`, `section`, `priority`, `pinned`, `created_at`) VALUES
(33, 11, 'BSIT', 'QWER', 'Qwer', '2nd Year', 'A', 'low', 0, '2026-04-06 07:10:00');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('present','absent') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `date` date DEFAULT NULL,
  `school_year` varchar(20) DEFAULT NULL,
  `semester` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `subject_id`, `attendance_date`, `status`, `created_at`, `date`, `school_year`, `semester`) VALUES
(39, 48, NULL, '0000-00-00', 'present', '2026-03-12 03:48:07', '2026-03-12', NULL, NULL),
(40, 46, NULL, '0000-00-00', 'present', '2026-03-12 06:33:58', '2026-03-12', NULL, NULL),
(41, 47, NULL, '0000-00-00', 'present', '2026-03-12 06:33:58', '2026-03-12', NULL, NULL),
(42, 46, NULL, '0000-00-00', 'present', '2026-03-13 20:40:17', '2026-03-13', NULL, NULL),
(43, 47, NULL, '0000-00-00', 'present', '2026-03-13 20:40:17', '2026-03-13', NULL, NULL),
(44, 49, NULL, '0000-00-00', 'present', '2026-03-16 11:24:49', '2026-03-16', NULL, NULL),
(45, 46, NULL, '0000-00-00', 'present', '2026-03-16 11:36:08', '2026-03-16', NULL, NULL),
(46, 47, NULL, '0000-00-00', 'present', '2026-03-16 11:36:08', '2026-03-16', NULL, NULL),
(47, 46, NULL, '0000-00-00', 'present', '2026-03-18 01:59:08', '2026-03-18', NULL, NULL),
(48, 47, NULL, '0000-00-00', 'present', '2026-03-18 01:59:08', '2026-03-18', NULL, NULL),
(49, 46, NULL, '0000-00-00', 'absent', '2026-03-19 02:22:12', '2026-03-19', NULL, NULL),
(50, 47, NULL, '0000-00-00', 'absent', '2026-03-19 02:22:12', '2026-03-19', NULL, NULL),
(51, 49, NULL, '0000-00-00', 'present', '2026-03-20 07:57:25', '2026-03-20', NULL, NULL),
(52, 46, NULL, '0000-00-00', 'present', '2026-03-21 09:28:46', '2026-03-21', NULL, NULL),
(53, 47, NULL, '0000-00-00', 'present', '2026-03-21 09:28:46', '2026-03-21', NULL, NULL),
(54, 49, NULL, '0000-00-00', 'present', '2026-03-23 01:14:20', '2026-03-23', NULL, NULL),
(55, 46, NULL, '0000-00-00', 'absent', '2026-03-25 11:12:27', '2026-03-25', NULL, NULL),
(56, 47, NULL, '0000-00-00', 'absent', '2026-03-25 11:12:27', '2026-03-25', NULL, NULL),
(57, 46, NULL, '0000-00-00', 'present', '2026-03-30 07:04:55', '2026-03-30', NULL, NULL),
(58, 47, NULL, '0000-00-00', 'present', '2026-03-30 07:04:56', '2026-03-30', NULL, NULL),
(59, 46, NULL, '0000-00-00', 'present', '2026-04-01 08:38:55', '2026-04-01', NULL, NULL),
(60, 47, NULL, '0000-00-00', 'present', '2026-04-01 08:38:55', '2026-04-01', NULL, NULL),
(61, 46, NULL, '0000-00-00', 'absent', '2026-04-05 23:15:50', '2026-04-06', NULL, NULL),
(62, 47, NULL, '0000-00-00', 'absent', '2026-04-05 23:15:50', '2026-04-06', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `section` varchar(10) NOT NULL,
  `year_level` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `course_id`, `section`, `year_level`) VALUES
(19, 3, 'A', '1st Year'),
(47, 1, 'B', '1st Year'),
(48, 1, 'C', '1st Year'),
(49, 1, 'D', '1st Year'),
(50, 1, 'A', '2nd Year'),
(51, 1, 'B', '2nd Year'),
(52, 1, 'C', '2nd Year'),
(53, 1, 'D', '2nd Year'),
(54, 1, 'A', '3rd Year'),
(55, 1, 'B', '3rd Year'),
(56, 1, 'C', '3rd Year'),
(57, 1, 'D', '3rd Year'),
(58, 1, 'A', '4th Year'),
(59, 1, 'B', '4th Year'),
(60, 1, 'C', '4th Year'),
(61, 1, 'D', '4th Year'),
(62, 1, 'A', '1st Year');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `course_name`, `created_at`) VALUES
(1, 'BSIT', '2026-03-01 09:46:15'),
(2, 'BSED', '2026-03-01 09:46:15'),
(3, 'BTVTED', '2026-03-01 09:46:15'),
(4, 'BAT', '2026-03-01 09:46:15');

-- --------------------------------------------------------

--
-- Table structure for table `course_fees`
--

CREATE TABLE `course_fees` (
  `id` int(11) NOT NULL,
  `course_name` varchar(50) NOT NULL,
  `tuition_per_unit` decimal(10,2) NOT NULL DEFAULT 500.00,
  `misc_fee` decimal(10,2) NOT NULL DEFAULT 1000.00,
  `lab_fee_per_major` decimal(10,2) NOT NULL DEFAULT 500.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `quiz` decimal(5,2) DEFAULT 0.00,
  `homework` decimal(5,2) DEFAULT 0.00,
  `activities` decimal(5,2) DEFAULT 0.00,
  `prelim` decimal(5,2) DEFAULT 0.00,
  `midterm` decimal(5,2) DEFAULT 0.00,
  `final` decimal(5,2) DEFAULT 0.00,
  `lab` decimal(5,2) DEFAULT 0.00,
  `percentage` decimal(5,2) DEFAULT NULL,
  `letter_grade` varchar(5) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grades`
--

INSERT INTO `grades` (`id`, `student_id`, `subject_id`, `quiz`, `homework`, `activities`, `prelim`, `midterm`, `final`, `lab`, `percentage`, `letter_grade`, `created_at`, `updated_at`) VALUES
(25, 46, 58, 20.00, 50.00, 0.00, 0.00, 0.00, 0.00, 0.00, 7.00, '5.0', '2026-04-05 23:09:20', '2026-04-05 23:09:20');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `course` varchar(100) NOT NULL,
  `subject` varchar(150) NOT NULL,
  `year_level` varchar(20) NOT NULL,
  `section` varchar(50) NOT NULL,
  `day` enum('Monday','Tuesday','Wednesday','Thursday','Friday') NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `room` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school_years`
--

CREATE TABLE `school_years` (
  `id` int(11) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `semester` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `school_years`
--

INSERT INTO `school_years` (`id`, `school_year`, `is_active`, `semester`) VALUES
(17, '2027-2028', 0, '2nd'),
(26, '2026-2027', 0, '1st'),
(27, '2026-2027', 1, '2nd');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `student_id` varchar(50) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `student_type` enum('New','Transferee','Continuing','Returnee','Cross-enrollee') NOT NULL,
  `last_school_attended` varchar(200) DEFAULT NULL,
  `last_school_address` varchar(200) DEFAULT NULL,
  `blood_type` varchar(5) DEFAULT NULL,
  `medical_conditions` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `dob` date NOT NULL,
  `age` int(11) DEFAULT NULL,
  `place_of_birth` varchar(150) NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `civil_status` enum('Single','Married','Widowed','Separated','Divorced') NOT NULL,
  `nationality` varchar(100) NOT NULL,
  `religion` varchar(100) DEFAULT NULL,
  `course` varchar(50) NOT NULL,
  `year_level` varchar(20) NOT NULL,
  `section` varchar(10) DEFAULT NULL,
  `school_year` varchar(20) NOT NULL,
  `semester` varchar(10) NOT NULL,
  `email` varchar(150) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `home_address` text NOT NULL,
  `zip_code` varchar(10) DEFAULT NULL,
  `father_name` varchar(150) DEFAULT NULL,
  `mother_name` varchar(150) DEFAULT NULL,
  `guardian_name` varchar(150) DEFAULT NULL,
  `parent_contact` varchar(20) DEFAULT NULL,
  `parent_occupation` varchar(150) DEFAULT NULL,
  `parent_employer` varchar(150) DEFAULT NULL,
  `emergency_person` varchar(150) NOT NULL,
  `emergency_number` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Regular','Irregular','Probation','Graduated','Dropped','Transferred') NOT NULL DEFAULT 'Regular',
  `gpa` decimal(3,2) NOT NULL DEFAULT 0.00,
  `profile_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `course_id`, `student_id`, `profile_picture`, `student_type`, `last_school_attended`, `last_school_address`, `blood_type`, `medical_conditions`, `allergies`, `first_name`, `middle_name`, `last_name`, `suffix`, `dob`, `age`, `place_of_birth`, `gender`, `civil_status`, `nationality`, `religion`, `course`, `year_level`, `section`, `school_year`, `semester`, `email`, `mobile`, `home_address`, `zip_code`, `father_name`, `mother_name`, `guardian_name`, `parent_contact`, `parent_occupation`, `parent_employer`, `emergency_person`, `emergency_number`, `password`, `created_at`, `status`, `gpa`, `profile_image`) VALUES
(46, NULL, '26-1111', 'student_46_1775047890.jpg', 'Continuing', 'Cabatuan National High School', 'Del Pilar, Cabatuan, Isabela', 'O', 'NA', 'NA', 'MACCOY', 'MALITTAY', 'TABIOS', '', '2002-08-17', 23, 'Saranay, Cabatuan, Isabela', 'Male', 'Single', 'Filipino', 'INC', 'BSIT', '2nd Year', 'A', '2026-2027', '2nd', 'maccoytabios@gmail.com', '09667154926', 'Saranay, Cabatuan, Isabela', '3315', 'MARIO O. TABIOS', 'CELSA M. TABIOS', 'MARIO ORBITA TABIOS', '09061167387', 'DELIVERY BOY', 'B-MEG', 'Mudrabels', '09081167387', '$2y$10$yfjkYJHkSnOnfeHG2gvpPuisY/QGwZn.SvohKj8ywzeGkRGrWl0SS', '2026-03-12 02:46:44', 'Regular', 5.00, 'student_46_1775047890.jpg'),
(47, NULL, '26-2222', NULL, 'Continuing', 'Cabatuan National High School', 'Del Pilar, Cabatuan, Isabela', 'A', 'NA', 'NA', 'JULIANA', 'DIVINA', 'PARISCOVA', '', '2001-09-08', 25, 'Magdalena, Cabatuan, Isabela', 'Female', 'Single', 'Filipino', 'Roman Catholic', 'BSIT', '2nd Year', 'B', '2026-2027', '2nd', 'juliana@gmail.com', '09876543212', 'Magdalena, Cabatuan, Isabela', '3315', 'penduko labrador pariscova', 'juanang  divina pariscova', 'penduko labrador pariscova', '09876545434', 'driver', 'la suerte', 'Paderbels', '09876545678', '$2y$10$fpqziqszKExo4iJ7vFCyheBoeMDIvYZ1eFH4RNZDS09j4EFS2uYcW', '2026-03-12 02:53:15', 'Regular', 0.00, NULL),
(48, NULL, '26-3333', NULL, 'Continuing', 'Cabatuan National High School', 'Del Pilar, Cabatuan, Isabela', 'AB', 'NA', 'NA', 'CELSA', 'MALITTAY', 'TABIOS', '', '1999-11-21', 27, 'Saranay, Cabatuan, Isabela', 'Female', 'Single', 'Filipino', 'Roman Catholic', 'BSED', '1st Year', 'A', '2026-2027', '2nd', 'celsamtabios@gmail.com', '09876566565', 'Saranay, Cabatuan, Isabela', '3315', 'cerelio protacio tabios', 'modesa malittay tabios', 'cerelio protacio tabios', '08746543456', 'construction', 'NA', 'Mudrabels', '98343232334', '$2y$10$6V9oxbN5U9xl7CHgiI7Ga.seeYUdvnhXeLor8SucgcaFF6EE4rHue', '2026-03-12 03:46:27', 'Regular', 0.00, NULL),
(49, NULL, '26-4444', NULL, 'New', 'Cabatuan National High School', 'Del Pilar, Cabatuan, Isabela', 'B', 'NA', 'NA', 'PEDRO', 'SULAYMAN', 'PENDUKO', 'Jr.', '2001-04-12', 25, 'Saranay, Cabatuan, Isabela', 'Male', 'Single', 'Filipino', 'Roman Catholic', 'BSIT', '1st Year', 'C', '2026-2027', '2nd', 'pedro@gmail.com', '09081167387', 'Saranay, Cabatuan, Isabela', '3315', 'Pedro Owen Penduko', 'Maria Sulayman Penduko', 'Pedro Owen Penduko', '09876543212', 'Construction', 'NA', 'Pudra', '09876543212', '$2y$10$cC/GD64SzQUdlqMOx0vlA.HJNa8Ks1j90Cx0WzR6YehHmq49Domsa', '2026-03-16 02:42:07', 'Regular', 0.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `course` varchar(100) NOT NULL,
  `year_level` varchar(20) NOT NULL,
  `section` varchar(5) NOT NULL,
  `code` varchar(20) NOT NULL,
  `subject_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `room` varchar(50) DEFAULT NULL,
  `schedule` varchar(50) DEFAULT NULL,
  `instructor` varchar(100) DEFAULT NULL,
  `day` enum('Monday','Tuesday','Wednesday','Thursday','Friday') NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `subject_type` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `course_id`, `course`, `year_level`, `section`, `code`, `subject_name`, `description`, `room`, `schedule`, `instructor`, `day`, `time_start`, `time_end`, `teacher_id`, `subject_type`) VALUES
(58, 1, 'BSIT', '2nd Year', 'A', 'CO67', 'APPDEV5', 'cloud computing', 'LAB 5', NULL, '321', 'Monday', '07:00:00', '09:00:00', NULL, 'Major'),
(59, 1, 'BSIT', '2nd Year', 'C', 'C067', 'APPDEV5', 'cloud computing', 'LAB 4', NULL, '322', 'Monday', '07:00:00', '08:00:00', NULL, 'Major');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `task_type` enum('activities','homework','laboratory') NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `due_date` datetime DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `task_type`, `subject_id`, `title`, `description`, `attachment`, `original_filename`, `created_at`, `updated_at`, `due_date`, `teacher_id`) VALUES
(88, 'activities', 44, 'testing 1', 'qwertyuiop', '1773377480_69b397c851268.png', 'task.jpg.png', '2026-03-13 12:51:20', '2026-03-13 04:51:20', NULL, NULL),
(90, 'homework', 44, 'test 3', 'tessting', '1773379144_69b39e48ab62f.png', 'task.jpg.png', '2026-03-13 13:19:04', '2026-03-13 05:19:04', '2026-03-13 13:19:00', NULL),
(91, 'activities', 44, 'test 5', '5 testing', '1773383531_69b3af6b567d2.jpg', '622685015_925666030131412_6886851389087569993_n.jpg', '2026-03-13 14:29:57', '2026-03-13 06:32:11', '2026-03-14 14:32:00', NULL),
(92, 'laboratory', 44, 'test 6', 'testing number 6', '1773427185_69b459f1eb365.jpg', '3223ae7efbcd98dadbe20465fcd6b7ab.jpg', '2026-03-14 02:39:45', '2026-03-13 18:39:45', '2026-03-20 02:39:00', NULL),
(93, 'activities', 46, 'TESTING SEC C', 'QWEREASDDA', '1773655557_69b7d6052578e.jpg', '3223ae7efbcd98dadbe20465fcd6b7ab.jpg', '2026-03-16 18:05:57', '2026-03-16 10:05:57', '2026-03-24 17:00:00', NULL),
(94, 'laboratory', 46, 'test lab', 'jfjdljfdlfldjflf', '1773657869_69b7df0d951cd.jpg', 'e9205e33-458b-45df-bd6b-8920c0370510.jpg', '2026-03-16 18:44:30', '2026-03-16 10:44:30', '2026-03-20 18:44:00', NULL),
(124, 'activities', 48, 'test 3', 'test 3', '1773894110_69bb79dea00a0.png', 'Bright and colorful class schedule logo.png', '2026-03-19 12:21:50', '2026-03-19 04:27:17', NULL, NULL),
(125, 'activities', 54, 'debug', 'debug', 'task_11_1775423775.jpg', 'ME.jpg', '2026-04-06 05:16:15', '2026-04-05 21:16:15', '2026-04-08 23:59:59', 11),
(128, 'homework', 58, 'debugggggg', 'test', 'task_11_1775471592.pdf', 'Assessment_MACCOY_MALITTAY_TABIOS_2026-04-06 (1).pdf', '2026-04-06 18:33:12', '2026-04-06 10:33:12', '2026-04-07 23:59:59', 11);

-- --------------------------------------------------------

--
-- Table structure for table `task_submissions`
--

CREATE TABLE `task_submissions` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `submitted_at` datetime DEFAULT current_timestamp(),
  `teacher_read` tinyint(1) DEFAULT 0 COMMENT '1 if teacher marked as read'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_submissions`
--

INSERT INTO `task_submissions` (`id`, `task_id`, `student_id`, `file_path`, `original_filename`, `notes`, `submitted_at`, `teacher_read`) VALUES
(15, 90, 46, '1773379552_student46_69b39fe050ecf.png', 'task.jpg.png', 'undefined', '2026-03-13 13:25:52', 1),
(17, 88, 46, '1773382534_student46_69b3ab86b8da7.png', 'task.jpg.png', 'undefined', '2026-03-13 14:15:34', 1),
(18, 91, 46, '1773383564_student46_69b3af8c6b6d1.png', 'task.jpg.png', 'done', '2026-03-13 14:33:00', 1),
(19, 92, 46, '1773427306_student46_69b45a6ae364e.jpg', '3223ae7efbcd98dadbe20465fcd6b7ab.jpg', 'undefined', '2026-03-14 02:41:46', 1),
(21, 93, 49, '1773656450_student49_69b7d9821e57e.jpg', 'e9205e33-458b-45df-bd6b-8920c0370510.jpg', 'undefined', '2026-03-16 18:20:51', 1),
(22, 94, 49, '1773657909_student49_69b7df352a321.jpg', '70c7fe3c-2b1c-4886-b808-9f538cfa9dc2.jpg', 'undefined', '2026-03-16 18:45:09', 1),
(23, 95, 46, '1773799968_student46_69ba0a20dc6fb.png', 'Teachers surrounded by knowledge icons.png', 'undefined', '2026-03-18 10:12:49', 0),
(31, 124, 46, '1774183468_student46_69bfe42ccc224.png', 'Bright and colorful class schedule logo.png', 'test', '2026-03-22 20:51:23', 0),
(33, 128, 46, 'student_46_1775471611.pdf', 'Assessment_MACCOY_MALITTAY_TABIOS_2026-04-06 (1).pdf', 'undefined', '2026-04-06 18:33:31', 0);

-- --------------------------------------------------------

--
-- Stand-in structure for view `task_summary`
-- (See below for the actual view)
--
CREATE TABLE `task_summary` (
`id` int(11)
,`task_type` enum('activities','homework','laboratory')
,`title` varchar(255)
,`description_preview` varchar(100)
,`attachment_status` varchar(14)
,`created_date` date
,`created_time` time
);

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `teacher_id` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `dob` date NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `civil_status` enum('Single','Married','Widowed','Separated','Divorced') NOT NULL,
  `nationality` varchar(100) NOT NULL,
  `teacher_type` varchar(100) NOT NULL,
  `course` varchar(20) NOT NULL,
  `email` varchar(150) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `home_address` text NOT NULL,
  `emergency_person` varchar(150) NOT NULL,
  `emergency_number` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `year_levels` varchar(255) DEFAULT NULL,
  `sections` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `teacher_id`, `first_name`, `middle_name`, `last_name`, `suffix`, `dob`, `gender`, `civil_status`, `nationality`, `teacher_type`, `course`, `email`, `mobile`, `home_address`, `emergency_person`, `emergency_number`, `password`, `created_at`, `year_levels`, `sections`) VALUES
(9, 'Registrar', 'System', '', 'Administrator', '', '1980-01-01', 'Female', 'Single', 'Filipino', 'Administrator', '', 'admin@school.edu.ph', '09171234567', 'School Campus, San Mateo, Isabela', 'Maccoy Tabios', '09179876543', '$2y$10$w95Z1.n9XYQHqulWbPzU3uDSR8mUgfJCysHGEGNE9CGrJaPVXHBoq', '2026-03-10 01:55:09', NULL, NULL),
(10, '123', 'mark', 'malittay', 'tabios', '', '2002-08-17', 'Male', 'Single', 'Filipino', 'Instructor 1', 'BSIT', 'mark@gmail.com', '09667154926', 'Saranay, Cabatuan, Isabela', 'mudrabels', '09876543211', '$2y$10$ps0YKAW9MnUFS27fz8pqiuCGWRndDc8Ps2W9qrnzS/h3EGbd.dXhO', '2026-03-12 05:10:31', '1st Year,3rd Year', 'B,C'),
(11, '321', 'maccoy', 'tabios', 'malittay', 'jr.', '1987-03-10', 'Male', 'Single', 'filipino', 'instructor 1', 'BSIT', 'maccoy@gmail.com', '09876543211', 'saranay, cabatuan, isabela', 'mudrabels', '0987654321112', '$2y$10$jW5VbLsBcVcUj/DGj6YFxe9fXijRsmGkT/Wk7.LzbxPhHo68U46PW', '2026-03-12 06:11:03', '2nd Year,4th Year', 'A,B'),
(12, '322', 'JAN KENNETH', 'BAYANI', 'MANUEL', '', '2001-03-07', 'Male', 'Single', 'Filipino', 'Instructor 1', 'BSIT', 'kenneth@gmail.com', '09876512345', 'marasat pequeno', 'mama', '09876543211', '$2y$10$RaIE0aC91Mwcwvx.izwT0uE9ja2rL3ldKcEQTEjgXpXnuN77.eB7W', '2026-04-05 21:26:10', '1st Year,2nd Year,3rd Year', 'C,D');

-- --------------------------------------------------------

--
-- Structure for view `task_summary`
--
DROP TABLE IF EXISTS `task_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `task_summary`  AS SELECT `tasks`.`id` AS `id`, `tasks`.`task_type` AS `task_type`, `tasks`.`title` AS `title`, left(`tasks`.`description`,100) AS `description_preview`, CASE WHEN `tasks`.`attachment` is not null THEN 'Has attachment' ELSE 'No attachment' END AS `attachment_status`, cast(`tasks`.`created_at` as date) AS `created_date`, cast(`tasks`.`created_at` as time) AS `created_time` FROM `tasks` ORDER BY `tasks`.`created_at` DESC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_classes_course` (`course_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `course_name` (`course_name`);

--
-- Indexes for table `course_fees`
--
ALTER TABLE `course_fees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_subject` (`student_id`,`subject_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `school_years`
--
ALTER TABLE `school_years`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `fk_students_course` (`course_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_subjects_course` (`course_id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `task_submissions`
--
ALTER TABLE `task_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_submission` (`task_id`,`student_id`),
  ADD KEY `idx_task_student` (`task_id`,`student_id`),
  ADD KEY `idx_teacher_read` (`teacher_read`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `teacher_id` (`teacher_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `course_fees`
--
ALTER TABLE `course_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `school_years`
--
ALTER TABLE `school_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT for table `task_submissions`
--
ALTER TABLE `task_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`);

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `fk_subjects_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
