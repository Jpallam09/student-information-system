-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 14, 2026 at 12:47 AM
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
(28, 9, 'BSIT', 'AAAA', 'Aaaaaa', '1st Year', 'A', 'high', 0, '2026-03-12 11:34:00'),
(30, 10, 'BSIT', 'RRRRRTTTTT', 'Test\r\n', '3rd Year', 'A', 'low', 0, '2026-03-12 05:59:00'),
(31, 9, 'BSIT', 'QQQ', 'Qqqq', '', '', 'high', 1, '2026-03-12 14:45:00');

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
  `date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `subject_id`, `attendance_date`, `status`, `created_at`, `date`) VALUES
(39, 48, NULL, '0000-00-00', 'present', '2026-03-12 03:48:07', '2026-03-12'),
(40, 46, NULL, '0000-00-00', 'present', '2026-03-12 06:33:58', '2026-03-12'),
(41, 47, NULL, '0000-00-00', 'present', '2026-03-12 06:33:58', '2026-03-12'),
(42, 46, NULL, '0000-00-00', 'present', '2026-03-13 20:40:17', '2026-03-13'),
(43, 47, NULL, '0000-00-00', 'present', '2026-03-13 20:40:17', '2026-03-13');

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
(31, 1, 'A', '1st Year'),
(32, 1, 'A', '2nd Year'),
(33, 1, 'A', '3rd Year'),
(34, 1, 'A', '4th Year'),
(35, 1, 'B', '1st Year'),
(36, 1, 'B', '2nd Year'),
(37, 1, 'B', '3rd Year'),
(38, 1, 'B', '4th Year'),
(39, 1, 'C', '1st Year'),
(40, 1, 'C', '2nd Year'),
(41, 1, 'C', '3rd Year'),
(42, 1, 'C', '4th Year'),
(43, 1, 'D', '1st Year'),
(44, 1, 'D', '2nd Year'),
(45, 1, 'D', '3rd Year');

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
(46, NULL, '26-1111', '', 'Continuing', 'Eveland Christian College', 'Barangay 3, San Mateo, Isabela', 'B+', 'NA', 'NA', 'JUAN', 'LAGING', 'TAMADD', '', '2000-02-01', 26, 'Marasat Grande, San Mateo, Isabela', 'Male', 'Single', 'Filipino', 'Roman Catholic', 'BSIT', '2nd Year', 'A', '2025-2026', '1st', 'juanLtamad@gmail.com', '09083245765', 'Marasat Grande, San Mateo, Isabela', '3320', 'penduko soper tamad', 'maria laging tamad', 'maria laging tamad', '09072249813', 'labandera', 'jan lang sa kapitbahay', 'Mudrabels', '09081167387', '$2y$10$yfjkYJHkSnOnfeHG2gvpPuisY/QGwZn.SvohKj8ywzeGkRGrWl0SS', '2026-03-12 02:46:44', 'Regular', 0.00, ''),
(47, NULL, '26-2222', NULL, 'Continuing', 'Cabatuan National High School', 'Del Pilar, Cabatuan, Isabela', 'A', 'NA', 'NA', 'JULIANA', 'DIVINA', 'PARISCOVA', '', '2001-09-08', 25, 'Magdalena, Cabatuan, Isabela', 'Female', 'Single', 'Filipino', 'Roman Catholic', 'BSIT', '2nd Year', 'B', '2025-2026', '1st', 'juliana@gmail.com', '09876543212', 'Magdalena, Cabatuan, Isabela', '3315', 'penduko labrador pariscova', 'juanang  divina pariscova', 'penduko labrador pariscova', '09876545434', 'driver', 'la suerte', 'Paderbels', '09876545678', '$2y$10$fpqziqszKExo4iJ7vFCyheBoeMDIvYZ1eFH4RNZDS09j4EFS2uYcW', '2026-03-12 02:53:15', 'Regular', 0.00, NULL),
(48, NULL, '26-3333', NULL, 'Continuing', 'Cabatuan National High School', 'Del Pilar, Cabatuan, Isabela', 'AB', 'NA', 'NA', 'CELSA', 'MALITTAY', 'TABIOS', '', '1999-11-21', 27, 'Saranay, Cabatuan, Isabela', 'Female', 'Single', 'Filipino', 'Roman Catholic', 'BSED', '1st Year', 'A', '2025-2026', '1st', 'celsamtabios@gmail.com', '09876566565', 'Saranay, Cabatuan, Isabela', '3315', 'cerelio protacio tabios', 'modesa malittay tabios', 'cerelio protacio tabios', '08746543456', 'construction', 'NA', 'Mudrabels', '98343232334', '$2y$10$6V9oxbN5U9xl7CHgiI7Ga.seeYUdvnhXeLor8SucgcaFF6EE4rHue', '2026-03-12 03:46:27', 'Regular', 0.00, NULL);

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
  `time_end` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `course_id`, `course`, `year_level`, `section`, `code`, `subject_name`, `description`, `room`, `schedule`, `instructor`, `day`, `time_start`, `time_end`) VALUES
(44, 1, '', '2nd Year', 'A', 'APPDEV 4', 'GAME DEVELOPMENT', '', 'LAB 5', NULL, 'AAAA', 'Monday', '08:00:00', '10:00:00'),
(45, 1, '', '2nd Year', 'A', 'APPDEV5', 'HOSTING', '', 'LAB 5', NULL, 'MARK', 'Monday', '08:00:00', '11:00:00');

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
  `due_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `task_type`, `subject_id`, `title`, `description`, `attachment`, `original_filename`, `created_at`, `updated_at`, `due_date`) VALUES
(88, 'activities', 44, 'testing 1', 'qwertyuiop', '1773377480_69b397c851268.png', 'task.jpg.png', '2026-03-13 12:51:20', '2026-03-13 04:51:20', NULL),
(90, 'homework', 44, 'test 3', 'tessting', '1773379144_69b39e48ab62f.png', 'task.jpg.png', '2026-03-13 13:19:04', '2026-03-13 05:19:04', '2026-03-13 13:19:00'),
(91, 'activities', 44, 'test 5', '5 testing', '1773383531_69b3af6b567d2.jpg', '622685015_925666030131412_6886851389087569993_n.jpg', '2026-03-13 14:29:57', '2026-03-13 06:32:11', '2026-03-14 14:32:00'),
(92, 'laboratory', 44, 'test 6', 'testing number 6', '1773427185_69b459f1eb365.jpg', '3223ae7efbcd98dadbe20465fcd6b7ab.jpg', '2026-03-14 02:39:45', '2026-03-13 18:39:45', '2026-03-20 02:39:00');

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
(19, 92, 46, '1773427306_student46_69b45a6ae364e.jpg', '3223ae7efbcd98dadbe20465fcd6b7ab.jpg', 'undefined', '2026-03-14 02:41:46', 1);

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
(10, '123', 'mark', 'malittay', 'tabios', '', '2002-08-17', 'Male', 'Single', 'Filipino', 'Instructor 1', 'BSIT', 'mark@gmail.com', '09667154926', 'Saranay, Cabatuan, Isabela', 'mudrabels', '09876543211', '$2y$10$ps0YKAW9MnUFS27fz8pqiuCGWRndDc8Ps2W9qrnzS/h3EGbd.dXhO', '2026-03-12 05:10:31', '1st Year,3rd Year', 'A,B'),
(11, '321', 'maccoy', 'tabios', 'malittay', 'jr.', '1987-03-10', 'Male', 'Single', 'filipino', 'instructor 1', 'BSIT', 'maccoy@gmail.com', '09876543211', 'saranay, cabatuan, isabela', 'mudrabels', '0987654321112', '$2y$10$jW5VbLsBcVcUj/DGj6YFxe9fXijRsmGkT/Wk7.LzbxPhHo68U46PW', '2026-03-12 06:11:03', '2nd Year,4th Year', 'A,B,C');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `task_submissions`
--
ALTER TABLE `task_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

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
