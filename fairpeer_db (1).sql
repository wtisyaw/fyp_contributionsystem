-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 09, 2026 at 05:42 PM
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
-- Database: `fairpeer_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `assignment_id` int(11) NOT NULL,
  `lecturer_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `subject_section_id` int(11) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `attachment_file` varchar(255) DEFAULT NULL,
  `due_date` date NOT NULL,
  `status` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assignments`
--

INSERT INTO `assignments` (`assignment_id`, `lecturer_id`, `subject_id`, `subject_section_id`, `title`, `description`, `attachment_file`, `due_date`, `status`, `created_at`) VALUES
(1, NULL, NULL, NULL, 'Calculus', 'Complete the group calculus assignment.', NULL, '2026-09-10', 'active', '2026-09-05 12:14:02'),
(2, NULL, NULL, NULL, 'Web Development', 'Develop a group website project.', NULL, '2026-09-20', 'pending', '2026-09-05 12:14:02'),
(3, NULL, NULL, NULL, 'System Analysis', 'Prepare the system analysis documentation.', NULL, '2026-10-01', 'active', '2026-09-05 12:14:02'),
(4, NULL, NULL, NULL, 'Web Development Project', 'Develop a responsive website for your group project.', NULL, '2026-09-20', 'active', '2026-09-05 15:03:10'),
(5, NULL, NULL, NULL, 'Database Design', 'Create and document the database structure for the system.', NULL, '2026-09-30', 'active', '2026-09-05 15:03:10'),
(6, 10, 4, 1, 'Final Development', '3 people per group', '1791196914_6ac37ef2df7a4.pdf', '2026-10-09', 'Active', '2026-09-30 17:32:06'),
(7, 10, 5, 2, 'Asessment 2', '', '1790878083_6abea183d7b48.pdf', '2026-10-05', 'Active', '2026-10-01 18:08:03'),
(8, 18, 6, 3, 'Final Year Project', 'make group of 3 people', '1791209479_6ac3b00791bba.pdf', '2026-10-10', 'Active', '2026-10-05 14:11:19'),
(9, 18, 7, 4, 'Application', '2 people per group', '1791215324_6ac3c6dca7729.pdf', '2026-10-07', 'Active', '2026-10-05 15:48:44'),
(10, 18, 7, 4, 'Final Assessment', '', '1791528523_6ac88e4b02d7d.pdf', '2026-10-12', 'Active', '2026-10-09 06:48:43');

-- --------------------------------------------------------

--
-- Table structure for table `contributions`
--

CREATE TABLE `contributions` (
  `contribution_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `contribution_percentage` decimal(5,2) DEFAULT 0.00,
  `contribution_details` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contributions`
--

INSERT INTO `contributions` (`contribution_id`, `group_id`, `user_id`, `contribution_percentage`, `contribution_details`, `updated_at`) VALUES
(1, 1, 1, 85.00, 'Completed assigned tasks and contributed to group discussions.', '2026-09-05 12:14:44'),
(2, 1, 2, 75.00, 'Completed most assigned tasks.', '2026-09-05 12:14:44'),
(3, 2, 3, 90.00, 'Actively contributed to research and documentation.', '2026-09-05 12:14:44'),
(4, 3, 4, 80.00, 'Completed development tasks and attended group meetings.', '2026-09-05 12:14:44'),
(5, 1, 2, 80.00, 'Completed database design and helped with testing.', '2026-09-10 15:52:28'),
(6, 1, 6, 80.00, 'Completed database design task', '2026-09-11 13:57:06');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `submission_id` int(11) DEFAULT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `feedback_text` text NOT NULL,
  `review_decision` enum('satisfactory','revision_required') NOT NULL DEFAULT 'satisfactory',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedback_id`, `group_id`, `submission_id`, `sender_id`, `receiver_id`, `feedback_text`, `review_decision`, `created_at`) VALUES
(1, 1, NULL, 2, 6, 'Good contribution and always completed assigned tasks on time.', 'satisfactory', '2026-09-05 12:14:58'),
(2, 1, NULL, 1, 2, 'Good teamwork and participated in group discussions.', 'satisfactory', '2026-09-05 12:14:58'),
(3, 2, NULL, 3, 3, 'Excellent contribution to research and documentation.', 'satisfactory', '2026-09-05 12:14:58'),
(4, 4, 2, 6, 1, 'Please justify your report', 'satisfactory', '2026-09-19 12:42:58'),
(5, 4, 2, 6, 1, 'some of your work is incomplete and did not follow the rubric.', 'satisfactory', '2026-09-21 12:36:36'),
(6, 4, 2, 6, 1, 'Please complete your introduction', 'revision_required', '2026-09-25 09:08:20'),
(7, 4, 4, 6, 1, 'good content', 'satisfactory', '2026-09-25 09:28:03'),
(8, 4, 4, 6, 1, 'Make sure use Arial 12 and justify your paragraph!!', 'revision_required', '2026-09-25 16:59:39'),
(9, 9, 5, 7, 14, 'intro tak lengkap and make sure pakai arial 12', 'revision_required', '2026-09-30 19:10:45'),
(10, 9, 6, 7, 14, 'great', 'satisfactory', '2026-09-30 19:30:39'),
(11, 9, 7, 7, 13, 'a lot of typo error and wrong spelling', 'revision_required', '2026-10-01 07:14:51'),
(12, 9, 8, 13, 7, 'data tak sama dengan apa yang lecturer nak', 'revision_required', '2026-10-02 03:33:43'),
(13, 13, 10, 20, 19, 'question 3.1 ask for 3, u only gave 2', 'revision_required', '2026-10-05 14:32:47'),
(14, 13, 11, 19, 20, 'all good!!', 'satisfactory', '2026-10-05 14:33:49'),
(15, 13, 12, 19, 21, '1.2 ask for 3 objectives, not 2', 'revision_required', '2026-10-05 14:35:13'),
(16, 13, 13, 20, 19, 'great!!', 'satisfactory', '2026-10-05 14:35:54'),
(17, 13, 14, 19, 21, 'all good alice', 'satisfactory', '2026-10-05 14:36:36'),
(18, 14, 15, 21, 20, 'some part is not complete', 'revision_required', '2026-10-05 15:52:21'),
(19, 14, 16, 20, 21, 'your answer is not the same like question', 'revision_required', '2026-10-05 15:53:09'),
(20, 14, 17, 21, 20, 'the answer is not accurate', 'revision_required', '2026-10-05 15:53:48'),
(21, 14, 19, 21, 20, 'all good', 'satisfactory', '2026-10-06 07:26:05');

-- --------------------------------------------------------

--
-- Table structure for table `feedback_assignments`
--

CREATE TABLE `feedback_assignments` (
  `assignment_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `giver_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `submission_id` int(11) DEFAULT NULL,
  `status` enum('pending','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback_assignments`
--

INSERT INTO `feedback_assignments` (`assignment_id`, `group_id`, `giver_id`, `receiver_id`, `submission_id`, `status`, `created_at`) VALUES
(5, 1, 2, 1, 2, 'pending', '2026-09-25 06:52:18'),
(6, 1, 1, 2, NULL, 'pending', '2026-09-25 06:52:18'),
(7, 4, 1, 6, 3, 'pending', '2026-09-25 08:52:56'),
(8, 4, 6, 1, 2, 'completed', '2026-09-25 08:52:56'),
(9, 4, 6, 1, 4, 'completed', '2026-09-25 14:57:06'),
(10, 9, 13, 7, NULL, 'pending', '2026-09-30 18:51:04'),
(11, 9, 7, 14, 5, 'completed', '2026-09-30 18:51:04'),
(12, 9, 14, 13, NULL, 'pending', '2026-09-30 18:51:04'),
(13, 9, 7, 14, 6, 'completed', '2026-09-30 19:11:23'),
(14, 10, 7, 13, NULL, 'pending', '2026-10-02 04:10:38'),
(15, 12, 7, 13, NULL, 'pending', '2026-10-02 14:50:22'),
(16, 13, 20, 19, NULL, 'pending', '2026-10-05 14:20:34'),
(17, 14, 20, 21, NULL, 'pending', '2026-10-05 15:49:48');

-- --------------------------------------------------------

--
-- Table structure for table `feedback_categories`
--

CREATE TABLE `feedback_categories` (
  `category_id` int(11) NOT NULL,
  `feedback_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback_categories`
--

INSERT INTO `feedback_categories` (`category_id`, `feedback_id`, `category`) VALUES
(1, 4, 'Design / Presentation'),
(2, 5, 'Justification & Accuracy'),
(3, 5, 'Alignment with Requirements'),
(4, 6, 'Content Completeness'),
(5, 8, 'Justification & Accuracy'),
(6, 9, 'Content Completeness'),
(7, 9, 'Organization & Structure'),
(8, 11, 'Language & Writing Quality'),
(9, 12, 'Alignment with Requirements'),
(10, 13, 'Content Completeness'),
(11, 15, 'Alignment with Requirements'),
(12, 18, 'Content Completeness'),
(13, 19, 'Alignment with Requirements'),
(14, 20, 'Justification & Accuracy');

-- --------------------------------------------------------

--
-- Table structure for table `final_submissions`
--

CREATE TABLE `final_submissions` (
  `final_submission_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `submitted_by` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `submitted_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `final_submissions`
--

INSERT INTO `final_submissions` (`final_submission_id`, `group_id`, `assignment_id`, `submitted_by`, `file_name`, `file_path`, `submitted_at`) VALUES
(1, 9, 6, 7, 'task 4_nurbatrisyiaathirah.pdf', 'uploads/final_submissions/1790875185_9_task 4_nurbatrisyiaathirah.pdf', '2026-10-02 01:19:45'),
(2, 13, 8, 19, 'FYP_Full_Final_Submission.pdf', 'uploads/final_submissions/1791214820_13_FYP_Full_Final_Submission.pdf', '2026-10-05 23:40:20'),
(3, 14, 9, 21, 'MobileApplication_Full_Final_Submission.pdf', 'uploads/final_submissions/1791271616_14_MobileApplication_Full_Final_Submission.pdf', '2026-10-06 15:26:56');

-- --------------------------------------------------------

--
-- Table structure for table `group_members`
--

CREATE TABLE `group_members` (
  `group_member_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `group_members`
--

INSERT INTO `group_members` (`group_member_id`, `group_id`, `user_id`, `joined_at`) VALUES
(1, 1, 1, '2026-09-05 12:14:31'),
(2, 1, 2, '2026-09-05 12:14:31'),
(3, 2, 3, '2026-09-05 12:14:31'),
(4, 3, 4, '2026-09-05 12:14:31'),
(5, 4, 1, '2026-09-05 15:05:32'),
(6, 5, 1, '2026-09-05 15:05:32'),
(9, 4, 6, '2026-09-05 16:02:09'),
(10, 5, 6, '2026-09-05 16:02:09'),
(11, 6, 6, '2026-09-06 09:12:14'),
(14, 9, 7, '2026-09-30 18:13:33'),
(15, 9, 13, '2026-09-30 18:42:53'),
(16, 9, 14, '2026-09-30 18:47:08'),
(23, 12, 13, '2026-10-02 13:55:41'),
(24, 12, 14, '2026-10-02 13:56:29'),
(25, 12, 7, '2026-10-02 14:30:43'),
(26, 13, 19, '2026-10-05 14:19:00'),
(27, 13, 20, '2026-10-05 14:19:18'),
(28, 13, 21, '2026-10-05 14:19:34'),
(29, 14, 21, '2026-10-05 15:49:20'),
(30, 14, 20, '2026-10-05 15:49:37'),
(31, 15, 20, '2026-10-09 06:49:28');

-- --------------------------------------------------------

--
-- Table structure for table `group_tasks`
--

CREATE TABLE `group_tasks` (
  `task_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `assigned_to` int(11) NOT NULL,
  `task_title` varchar(150) NOT NULL,
  `task_description` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `group_tasks`
--

INSERT INTO `group_tasks` (`task_id`, `group_id`, `assignment_id`, `assigned_to`, `task_title`, `task_description`, `status`, `created_at`, `completed_at`) VALUES
(1, 1, 1, 2, 'Database Design', 'Design database structure and ERD.', 'Completed', '2026-09-10 15:51:02', '2026-09-10 15:51:02'),
(2, 1, 1, 2, 'Frontend Interface', 'Create user interface pages.', 'In Progress', '2026-09-10 15:51:02', NULL),
(3, 1, 1, 2, 'Testing Report', 'Prepare testing documentation.', 'Pending', '2026-09-10 15:51:02', NULL),
(4, 1, 1, 6, 'Database Design', 'Create database structure and ERD.', 'Completed', '2026-09-11 08:11:01', NULL),
(5, 1, 1, 6, 'Frontend Interface', 'Design the user interface.', 'In Progress', '2026-09-11 08:11:01', NULL),
(6, 1, 1, 6, 'Testing Report', 'Prepare testing documentation.', 'Pending', '2026-09-11 08:11:01', NULL),
(7, 9, 6, 13, 'introduction and reference', 'make sure intro  paragraph and reference ada 10', 'Revision Required', '2026-10-01 01:40:26', NULL),
(8, 9, 6, 14, 'proejct scope and questionnaire', 'questionnaira kena ada 20 soalan', 'Completed', '2026-10-01 01:46:57', '2026-10-01 01:47:09'),
(9, 9, 6, 7, 'flowchart and coding', 'send coding dalam github', 'Under Review', '2026-10-01 01:57:48', NULL),
(10, 13, 8, 21, '1.0 Introduction', '1.0 - 1.3', 'Completed', '2026-10-05 14:23:22', NULL),
(11, 13, 8, 20, '2.0 User Interface', '2.0 - 2.3', 'Completed', '2026-10-05 14:23:51', NULL),
(12, 13, 8, 19, '3.0 Application Features', '3.0 - 3.3', 'Completed', '2026-10-05 14:24:35', NULL),
(13, 14, 9, 20, 'Mobile', '1.0 - 2.1', 'Completed', '2026-10-05 15:51:11', NULL),
(14, 14, 9, 21, 'Mobile', '2.2 - 3.3', 'Under Review', '2026-10-05 15:51:26', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `lecturer_subjects`
--

CREATE TABLE `lecturer_subjects` (
  `lecturer_subject_id` int(11) NOT NULL,
  `lecturer_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lecturer_subjects`
--

INSERT INTO `lecturer_subjects` (`lecturer_subject_id`, `lecturer_id`, `subject_id`) VALUES
(1, 9, 1),
(2, 9, 2),
(3, 9, 3),
(4, 10, 4),
(5, 10, 5),
(6, 18, 5),
(7, 18, 7);

-- --------------------------------------------------------

--
-- Table structure for table `peer_ratings`
--

CREATE TABLE `peer_ratings` (
  `rating_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `peer_ratings`
--

INSERT INTO `peer_ratings` (`rating_id`, `group_id`, `sender_id`, `receiver_id`, `rating`, `comment`, `created_at`) VALUES
(1, 1, 3, 2, 5, 'Good communication during group discussion.', '2026-09-10 15:51:44'),
(2, 1, 4, 2, 4, 'Helped the team complete the database section.', '2026-09-10 15:51:44');

-- --------------------------------------------------------

--
-- Table structure for table `student_groups`
--

CREATE TABLE `student_groups` (
  `group_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `group_name` varchar(100) NOT NULL,
  `join_code` varchar(10) DEFAULT NULL,
  `submission_deadline` date DEFAULT NULL,
  `num_members` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_groups`
--

INSERT INTO `student_groups` (`group_id`, `created_by`, `group_name`, `join_code`, `submission_deadline`, `num_members`, `assignment_id`, `created_at`) VALUES
(1, 0, 'Group 1', NULL, NULL, 0, 1, '2026-09-05 12:14:16'),
(2, 0, 'Group 2', NULL, NULL, 0, 2, '2026-09-05 12:14:16'),
(3, 0, 'Group 3', NULL, NULL, 0, 3, '2026-09-05 12:14:16'),
(4, 0, 'Group Alpha', 'ALPHA001', NULL, 4, 2, '2026-09-05 15:04:44'),
(5, 0, 'Group Beta', 'BETA001', NULL, 4, 5, '2026-09-05 15:04:44'),
(6, 0, 'Power', 'C8F87D', NULL, 3, 3, '2026-09-06 09:12:14'),
(9, 7, 'project development', '6E0885', '2026-10-09', 3, 6, '2026-09-30 18:13:33'),
(12, 13, '5', '183630', '2026-10-06', 3, 7, '2026-10-02 13:55:41'),
(13, 19, 'Gemilang', 'FDFD7A', '2026-10-08', 3, 8, '2026-10-05 14:19:00'),
(14, 21, 'Stream', '2CC1A4', '2026-10-06', 2, 9, '2026-10-05 15:49:20'),
(15, 20, 'Power', '5A3106', '2026-10-10', 3, 10, '2026-10-09 06:49:28');

-- --------------------------------------------------------

--
-- Table structure for table `student_subjects`
--

CREATE TABLE `student_subjects` (
  `student_subject_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_section_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_subjects`
--

INSERT INTO `student_subjects` (`student_subject_id`, `student_id`, `subject_section_id`) VALUES
(1, 7, 1),
(7, 7, 2),
(2, 11, 1),
(3, 12, 1),
(5, 13, 1),
(6, 13, 2),
(4, 14, 1),
(8, 19, 3),
(13, 19, 4),
(9, 20, 3),
(11, 20, 4),
(10, 21, 3),
(12, 21, 4);

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `subject_id` int(11) NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`subject_id`, `subject_code`, `subject_name`) VALUES
(1, 'CSC101', 'Programming Fundamentals'),
(2, 'CSC205', 'Database Systems'),
(3, 'CSC301', 'Web Development'),
(4, 'FYP3024', 'Final Year Project'),
(5, 'MAT2114', 'Calculus'),
(6, 'FYP3023', 'Computing Project'),
(7, 'SWC3403', 'Mobile Application');

-- --------------------------------------------------------

--
-- Table structure for table `subject_sections`
--

CREATE TABLE `subject_sections` (
  `subject_section_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `lecturer_id` int(11) NOT NULL,
  `programme_code` varchar(50) NOT NULL,
  `semester` varchar(50) NOT NULL,
  `section` varchar(50) NOT NULL,
  `join_code` varchar(20) DEFAULT NULL,
  `expected_students` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subject_sections`
--

INSERT INTO `subject_sections` (`subject_section_id`, `subject_id`, `lecturer_id`, `programme_code`, `semester`, `section`, `join_code`, `expected_students`) VALUES
(1, 4, 10, 'CC101', '6', '1', 'CF4504', 6),
(2, 5, 10, 'CC101', '6', '2', '24D902', 10),
(3, 6, 18, 'CC101', '6', '2', '4A4B27', 10),
(4, 7, 18, 'CC101', '6', '2', '694848', 5);

-- --------------------------------------------------------

--
-- Table structure for table `submissions`
--

CREATE TABLE `submissions` (
  `submission_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `task_id` int(11) DEFAULT NULL,
  `assignment_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `version` varchar(20) DEFAULT 'V1',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `submissions`
--

INSERT INTO `submissions` (`submission_id`, `user_id`, `task_id`, `assignment_id`, `title`, `file_name`, `file_path`, `version`, `uploaded_at`) VALUES
(1, 6, NULL, NULL, 'Mobile Application', 'reportSWC2703.pdf', 'uploads/1789812607_reportSWC2703.pdf', 'V1', '2026-09-19 10:10:07'),
(2, 1, NULL, 1, 'Calculus', 'task 1_nurbatrisyia.pdf', 'uploads/1789816937_task 1_nurbatrisyia.pdf', 'V1', '2026-09-19 11:22:17'),
(3, 6, NULL, 1, 'Calculus', 'task 1_nurbatrisyia.pdf', 'uploads/1789997754_task 1_nurbatrisyia.pdf', 'V1', '2026-09-21 13:35:54'),
(4, 1, NULL, 1, 'Calculus', 'task 4_nurbatrisyiaathirah.pdf', 'uploads/1790328175_task 4_nurbatrisyiaathirah.pdf', 'V2', '2026-09-25 09:22:55'),
(5, 14, NULL, 6, 'Development', 'task 1_nurbatrisyia.pdf', 'uploads/1790795072_task 1_nurbatrisyia.pdf', 'V1', '2026-09-30 19:04:32'),
(6, 14, 8, 6, 'Development', 'Q2.pdf', 'uploads/1790795483_Q2.pdf', 'V2', '2026-09-30 19:11:23'),
(7, 13, 7, 6, 'Development', 'cpITC.pdf', 'uploads/1790828421_cpITC.pdf', 'V1', '2026-10-01 04:20:21'),
(8, 7, 9, 6, 'Development', 'SWC assignment-5.pdf', 'uploads/1790877325_SWC assignment-5.pdf', 'V1', '2026-10-01 17:55:25'),
(9, 7, 9, 6, 'Development', 'Q1.pdf', 'uploads/1790913032_Q1.pdf', 'V2', '2026-10-02 03:50:32'),
(10, 19, 12, 8, 'Final Year Project', 'Ray_FYP_V1.pdf', 'uploads/1791210296_Ray_FYP_V1.pdf', 'V1', '2026-10-05 14:24:56'),
(11, 20, 11, 8, 'Final Year Project', 'Altaf_FYP_V1.pdf', 'uploads/1791210796_Altaf_FYP_V1.pdf', 'V1', '2026-10-05 14:33:16'),
(12, 21, 10, 8, 'Final Year Project', 'Alice_FYP_V1.pdf', 'uploads/1791210870_Alice_FYP_V1.pdf', 'V1', '2026-10-05 14:34:30'),
(13, 19, 12, 8, 'Final Year Project', 'Ray_FYP_V2.pdf', 'uploads/1791210933_Ray_FYP_V2.pdf', 'V2', '2026-10-05 14:35:33'),
(14, 21, 10, 8, 'Final Year Project', 'Alice_FYP_V2.pdf', 'uploads/1791210977_Alice_FYP_V2.pdf', 'V2', '2026-10-05 14:36:17'),
(15, 20, 13, 9, 'Application', 'Ray_MobileApplication_V1.pdf', 'uploads/1791215511_Ray_MobileApplication_V1.pdf', 'V1', '2026-10-05 15:51:51'),
(16, 21, 14, 9, 'Application', 'Alice_MobileApplication_V1.pdf', 'uploads/1791215559_Alice_MobileApplication_V1.pdf', 'V1', '2026-10-05 15:52:39'),
(17, 20, 13, 9, 'Application', 'Ray_MobileApplication_V2.pdf', 'uploads/1791215603_Ray_MobileApplication_V2.pdf', 'V2', '2026-10-05 15:53:23'),
(18, 21, 14, 9, 'Application', 'Alice_MobileApplication_V2.pdf', 'uploads/1791215640_Alice_MobileApplication_V2.pdf', 'V2', '2026-10-05 15:54:00'),
(19, 20, 13, 9, 'Application', 'Ray_MobileApplication_V3.pdf', 'uploads/1791271539_Ray_MobileApplication_V3.pdf', 'V3', '2026-10-06 07:25:39');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `user_code` varchar(50) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `nickname` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_role` varchar(20) DEFAULT 'Student',
  `programme_code` varchar(20) DEFAULT NULL,
  `faculty_code` varchar(20) DEFAULT NULL,
  `semester` varchar(20) DEFAULT NULL,
  `section` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `user_code`, `full_name`, `nickname`, `email`, `password`, `user_role`, `programme_code`, `faculty_code`, `semester`, `section`, `created_at`) VALUES
(1, NULL, 'park sunghoon', 'hoon', 'sunghoon@gmail.com', '$2y$10$PmjmBd1gGfk8WJWmkqq7Y.4e7iM47NCYLl/gPws81P3/oZLfGETi.', 'Student', 'CC101', NULL, NULL, '2', '2026-09-05 12:13:45'),
(2, NULL, 'haechan', NULL, 'haechan@example.com', '123456', 'student', NULL, NULL, NULL, NULL, '2026-09-05 12:13:45'),
(3, NULL, 'beomgyu', NULL, 'beomgyu@example.com', '123456', 'student', NULL, NULL, NULL, NULL, '2026-09-05 12:13:45'),
(4, NULL, 'jaemin', NULL, 'jaemin@example.com', '123456', 'student', NULL, NULL, NULL, NULL, '2026-09-05 12:13:45'),
(6, 'AM987654321', 'wonwoo bin jeon', 'wonuu', 'wonwoojeon@gmail.com', '$2y$10$aZStzWlEBbu/jvM5e.seT.D.QsHmOBQb6XeTHoftjzbiJejs0iVS2', 'Student', 'CC101', NULL, NULL, '2', '2026-09-05 15:59:33'),
(7, 'AM789456', 'hawwas bin abu', 'hawwas', 'haechan@gmail.com', '$2y$10$z6bMhpZXuru6wP/fSyrpaOzZlkz93gzDpz.Rn6y2iPGnH/vNCS3km', 'Student', 'CC101', NULL, '6', '1', '2026-09-26 07:09:24'),
(8, '525252', 'ali', 'lili', 'ali@gmail.com', '$2y$10$PpWe6ViRzmTe8TYcQiDcL.O2nX2cXGUto1VD2SSyBNrwQzkq/6UxO', 'Student', 'CC101', NULL, '3', '4', '2026-09-26 15:08:32'),
(9, 'LC001', 'Dr. Aina Rahman', 'Dr. Aina', 'aina@fairpeer.com', '$2y$10$iRksa6uZFcNd5LPGkpTXIuj3/I.1Q1WvXOCgek8fP6IX.wRLCByFW', 'Lecturer', NULL, 'FCOM', NULL, NULL, '2026-09-28 15:45:18'),
(10, 'L001', 'Norhasiah', 'Norhasiah', 'norhasiah@fairpeer.com', '$2y$10$oNp3PDc4.2lnz1zYsxRaAe2OVRmnxk1Koqmnlx5WDzoLRRMHlLgAW', 'Lecturer', NULL, NULL, NULL, NULL, '2026-09-29 13:32:44'),
(11, 'AM24656', 'Testing', 'kil', 'testkil1@gmail.com', '$2y$10$fyQIGEHOAWvRNeTUOEBELeUDqm0H2BnhxHuwwkwf65Bkf7yv2Wx5O', 'Student', 'CC101', NULL, '6', '1', '2026-09-30 18:22:54'),
(12, 'AM4898', 'miltest', 'mil', 'miltest2@gmail.com', '$2y$10$VsRUeYmct4S/V0bYzTNOTuzgV3xVDQ0g9vPRW8EUF2BLbDVFx0XXK', 'Student', 'CC101', NULL, '6', '1', '2026-09-30 18:31:54'),
(13, 'AM4589', 'thariq bin thar', 'thariq', 'cubaansatu@gmail.com', '$2y$10$lBqrauem.2mTx7TIYdomMOW9C0YUsvkItbaAYDVcuRrIr.zEo.PKi', 'Student', 'CC101', NULL, '6', '1', '2026-09-30 18:41:21'),
(14, 'AM47865', 'alice binti ali', 'alice', 'cubaan2@gmail.com', '$2y$10$1ZirK9zYttBc6oU1sbHcvuOyaBclp393uxxymV7NpGj8ZiqHg7s92', 'Student', 'CC101', NULL, '6', '1', '2026-09-30 18:45:59'),
(15, 'AM2412018158', 'Putri Sarah', 'Sarah', 'putrisarahmz@gmail.com', '$2y$10$XXwJVkL1A0U.izUcIe1mEeM/D/CToLpMYR3VVRB.DbepF/ir.5Ehm', 'Student', 'CC101', NULL, '6', '2', '2026-10-02 17:56:17'),
(16, 'AM2412018168', 'Siti Aishah', 'solace', 'nraqielahh56@gmail.com', '$2y$10$Sw7kS3UnU.vNVM5TVn0cQeFib93LS5cUW97WO8NMXkubLhd6H6Fua', 'Student', 'CT204', NULL, '7', '1', '2026-10-02 17:56:33'),
(17, 'AM2412018163', 'batrisyia', 'tisya', 'wnww17@gmail.com', '$2y$10$jJ.hA1j/1Fbela8Jy67hquBd/bZcXePZb5oVnlykgQVaW9GWYnlWK', 'Student', 'CC101', NULL, '6', '2', '2026-10-02 18:06:44'),
(18, 'LI0610', 'Norhasiah binti Mohd Akhir', 'Norhasiah', 'norhasiah@gmail.com', '$2y$10$z82mG4txXpfxKP9.w3SGPuraRvGw.bBhNZFdtsSySCM2EJ9.qiwXW', 'Lecturer', NULL, 'FCOM', NULL, NULL, '2026-10-05 11:40:06'),
(19, 'AM2412018163', 'Ray Qhalif', 'Ray', 'ray@gmail.com', '$2y$10$3cSaIVo.1NLXTH.uqcy/pe41EDPz8XI1frxeBVDJXUVmjD0y0cm1m', 'Student', 'CC101', NULL, '6', '2', '2026-10-05 12:54:10'),
(20, 'AM2412018123', 'Altaf Qhadi', 'Altaf', 'altaf@gmail.com', '$2y$10$jNs16byKGrGowWMgtKk7o.Mizkjjb44o7ZONtS3tBOe7BQU93R1Pm', 'Student', 'CC101', NULL, '6', '2', '2026-10-05 12:59:17'),
(21, 'AM2412018456', 'Alice Dayana', 'Alice', 'alice@gmail.com', '$2y$10$x1MyWRDt5wXNVqzB6D5IXucdNT3W.ZRx0ELBDz9tix09zzc4l01Ri', 'Student', 'CC101', NULL, '6', '2', '2026-10-05 13:02:36'),
(22, 'LI1630', 'batrisyia', 'Tisya', 'tisya@gmail.com', '$2y$10$X3EvUQxm.jFBoEwwA.VWG.QTqmGFEnRUrFBy.tWOP0/v6p/Sm4laK', 'Lecturer', NULL, 'FCOM', NULL, NULL, '2026-10-06 03:37:29');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `lecturer_id` (`lecturer_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `fk_assignment_subject_section` (`subject_section_id`);

--
-- Indexes for table `contributions`
--
ALTER TABLE `contributions`
  ADD PRIMARY KEY (`contribution_id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `feedback_assignments`
--
ALTER TABLE `feedback_assignments`
  ADD PRIMARY KEY (`assignment_id`);

--
-- Indexes for table `feedback_categories`
--
ALTER TABLE `feedback_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD KEY `feedback_id` (`feedback_id`);

--
-- Indexes for table `final_submissions`
--
ALTER TABLE `final_submissions`
  ADD PRIMARY KEY (`final_submission_id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `assignment_id` (`assignment_id`),
  ADD KEY `submitted_by` (`submitted_by`);

--
-- Indexes for table `group_members`
--
ALTER TABLE `group_members`
  ADD PRIMARY KEY (`group_member_id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `group_tasks`
--
ALTER TABLE `group_tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `assignment_id` (`assignment_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `lecturer_subjects`
--
ALTER TABLE `lecturer_subjects`
  ADD PRIMARY KEY (`lecturer_subject_id`),
  ADD UNIQUE KEY `lecturer_id` (`lecturer_id`,`subject_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `peer_ratings`
--
ALTER TABLE `peer_ratings`
  ADD PRIMARY KEY (`rating_id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `student_groups`
--
ALTER TABLE `student_groups`
  ADD PRIMARY KEY (`group_id`),
  ADD UNIQUE KEY `join_code` (`join_code`),
  ADD KEY `assignment_id` (`assignment_id`);

--
-- Indexes for table `student_subjects`
--
ALTER TABLE `student_subjects`
  ADD PRIMARY KEY (`student_subject_id`),
  ADD UNIQUE KEY `student_id` (`student_id`,`subject_section_id`),
  ADD KEY `subject_section_id` (`subject_section_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`subject_id`),
  ADD UNIQUE KEY `subject_code` (`subject_code`);

--
-- Indexes for table `subject_sections`
--
ALTER TABLE `subject_sections`
  ADD PRIMARY KEY (`subject_section_id`),
  ADD UNIQUE KEY `join_code` (`join_code`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `lecturer_id` (`lecturer_id`);

--
-- Indexes for table `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`submission_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `submissions_ibfk_2` (`assignment_id`),
  ADD KEY `fk_submissions_task` (`task_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `contributions`
--
ALTER TABLE `contributions`
  MODIFY `contribution_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `feedback_assignments`
--
ALTER TABLE `feedback_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `feedback_categories`
--
ALTER TABLE `feedback_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `final_submissions`
--
ALTER TABLE `final_submissions`
  MODIFY `final_submission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `group_members`
--
ALTER TABLE `group_members`
  MODIFY `group_member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `group_tasks`
--
ALTER TABLE `group_tasks`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `lecturer_subjects`
--
ALTER TABLE `lecturer_subjects`
  MODIFY `lecturer_subject_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `peer_ratings`
--
ALTER TABLE `peer_ratings`
  MODIFY `rating_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `student_groups`
--
ALTER TABLE `student_groups`
  MODIFY `group_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `student_subjects`
--
ALTER TABLE `student_subjects`
  MODIFY `student_subject_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `subject_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `subject_sections`
--
ALTER TABLE `subject_sections`
  MODIFY `subject_section_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `submission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `fk_assignment_lecturer` FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assignment_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assignment_subject_section` FOREIGN KEY (`subject_section_id`) REFERENCES `subject_sections` (`subject_section_id`);

--
-- Constraints for table `contributions`
--
ALTER TABLE `contributions`
  ADD CONSTRAINT `contributions_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `student_groups` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contributions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `student_groups` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback_categories`
--
ALTER TABLE `feedback_categories`
  ADD CONSTRAINT `feedback_categories_ibfk_1` FOREIGN KEY (`feedback_id`) REFERENCES `feedback` (`feedback_id`) ON DELETE CASCADE;

--
-- Constraints for table `final_submissions`
--
ALTER TABLE `final_submissions`
  ADD CONSTRAINT `final_submissions_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `student_groups` (`group_id`),
  ADD CONSTRAINT `final_submissions_ibfk_2` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`assignment_id`),
  ADD CONSTRAINT `final_submissions_ibfk_3` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `group_members`
--
ALTER TABLE `group_members`
  ADD CONSTRAINT `group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `student_groups` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `group_tasks`
--
ALTER TABLE `group_tasks`
  ADD CONSTRAINT `group_tasks_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `student_groups` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_tasks_ibfk_2` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`assignment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_tasks_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `lecturer_subjects`
--
ALTER TABLE `lecturer_subjects`
  ADD CONSTRAINT `lecturer_subjects_ibfk_1` FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `lecturer_subjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `peer_ratings`
--
ALTER TABLE `peer_ratings`
  ADD CONSTRAINT `peer_ratings_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `student_groups` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `peer_ratings_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `peer_ratings_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_groups`
--
ALTER TABLE `student_groups`
  ADD CONSTRAINT `student_groups_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`assignment_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_subjects`
--
ALTER TABLE `student_subjects`
  ADD CONSTRAINT `student_subjects_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_subjects_ibfk_2` FOREIGN KEY (`subject_section_id`) REFERENCES `subject_sections` (`subject_section_id`) ON DELETE CASCADE;

--
-- Constraints for table `subject_sections`
--
ALTER TABLE `subject_sections`
  ADD CONSTRAINT `subject_sections_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subject_sections_ibfk_2` FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `submissions`
--
ALTER TABLE `submissions`
  ADD CONSTRAINT `fk_submissions_task` FOREIGN KEY (`task_id`) REFERENCES `group_tasks` (`task_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `submissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `submissions_ibfk_2` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`assignment_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
