-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 25, 2025 at 11:58 AM
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
-- Database: `planora`
--

-- --------------------------------------------------------

--
-- Table structure for table `airecommendation`
--

CREATE TABLE `airecommendation` (
  `recommendationID` int(11) NOT NULL,
  `ScheduleID` int(11) DEFAULT NULL,
  `recommendationDesc` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `airequest`
--

CREATE TABLE `airequest` (
  `aiRequestID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `promptText` text NOT NULL,
  `requestTimeStamp` datetime DEFAULT current_timestamp(),
  `responseData` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `airequest`
--

INSERT INTO `airequest` (`aiRequestID`, `userID`, `promptText`, `requestTimeStamp`, `responseData`, `created_at`) VALUES
(1, 3, '', '2025-12-17 22:16:22', NULL, '2025-12-17 14:16:22'),
(2, 3, '', '2025-12-17 22:16:23', NULL, '2025-12-17 14:16:23'),
(3, 3, '', '2025-12-17 22:16:23', NULL, '2025-12-17 14:16:23'),
(4, 3, 'w', '2025-12-17 22:25:57', NULL, '2025-12-17 14:25:57'),
(5, 3, '', '2025-12-17 22:25:59', NULL, '2025-12-17 14:25:59'),
(6, 3, 'www', '2025-12-17 22:26:00', NULL, '2025-12-17 14:26:00');

-- --------------------------------------------------------

--
-- Table structure for table `course`
--

CREATE TABLE `course` (
  `CourseID` int(11) NOT NULL,
  `CourseName` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course`
--

INSERT INTO `course` (`CourseID`, `CourseName`, `created_at`) VALUES
(1, 'Mathematics', '2025-12-17 08:46:00'),
(2, 'Physics', '2025-12-17 08:46:00'),
(3, 'Programming', '2025-12-17 08:46:00'),
(4, 'Literature', '2025-12-17 08:46:00'),
(5, 'History', '2025-12-17 08:46:00'),
(6, 'Chemistry', '2025-12-17 08:46:00'),
(7, 'Sceince', '2025-12-17 16:57:26'),
(8, 'Algorithm', '2025-12-17 17:34:53'),
(9, 'Medicine', '2025-12-22 08:33:16'),
(10, 'Pharmacy', '2025-12-22 08:34:37'),
(11, 'Calculus', '2025-12-23 14:16:31'),
(12, 'Data structure', '2025-12-23 14:16:42'),
(13, 'Maths', '2025-12-23 14:36:02');

-- --------------------------------------------------------

--
-- Table structure for table `schedule`
--

CREATE TABLE `schedule` (
  `ScheduleID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `TaskID` int(11) DEFAULT NULL,
  `CourseID` int(11) DEFAULT NULL,
  `Deadlines` varchar(255) DEFAULT NULL,
  `startDateTime` datetime NOT NULL,
  `endDateTime` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ScheduleStatus` varchar(20) NOT NULL DEFAULT 'pending',
  `IsDeleted` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `task`
--

CREATE TABLE `task` (
  `TaskID` int(11) NOT NULL,
  `ScheduleID` int(11) DEFAULT NULL,
  `UserID` int(11) NOT NULL,
  `Title` varchar(200) NOT NULL,
  `Description` text DEFAULT NULL,
  `TaskDeadlines` date DEFAULT NULL,
  `Priority` enum('low','medium','high','') DEFAULT 'medium',
  `TaskStatus` varchar(20) DEFAULT 'pending',
  `Deadlines` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tracker`
--

CREATE TABLE `tracker` (
  `TrackerID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `TaskID` int(11) DEFAULT NULL,
  `completionRates` int(11) DEFAULT 0,
  `TaskStatus` varchar(50) DEFAULT 'pending',
  `studyHours` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `UserID` int(11) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`UserID`, `Name`, `Email`, `Password`, `created_at`, `updated_at`) VALUES
(1, 'Ahmad', 'ahmad@example.com', 'password', '2025-12-17 08:46:00', '2025-12-17 14:28:42'),
(2, 'Sarah', 'sarah@example.com', 'password', '2025-12-17 08:46:00', '2025-12-17 14:58:58'),
(3, 'ali', 'ali@gmail.com', 'password', '2025-12-17 12:02:26', '2025-12-17 15:02:25'),
(4, 'Erfan', 'erfanhakimi04@gmail.com', 'password', '2025-12-17 18:46:28', '2025-12-25 10:07:55'),
(6, 'alexa', 'alexa@gmail.com', 'password', '2025-12-22 08:32:40', '2025-12-22 08:32:40');

-- --------------------------------------------------------

--
-- Table structure for table `user_course`
--

CREATE TABLE `user_course` (
  `UserID` int(11) NOT NULL,
  `CourseID` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_course`
--

INSERT INTO `user_course` (`UserID`, `CourseID`, `enrolled_at`) VALUES
(1, 9, '2025-12-24 07:44:04'),
(2, 2, '2025-12-17 08:46:00'),
(3, 7, '2025-12-17 18:36:07'),
(3, 8, '2025-12-17 17:34:53'),
(4, 1, '2025-12-17 18:49:52'),
(4, 7, '2025-12-17 18:47:16'),
(6, 8, '2025-12-22 16:01:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `airecommendation`
--
ALTER TABLE `airecommendation`
  ADD PRIMARY KEY (`recommendationID`),
  ADD KEY `idx_ai_recommendation_schedule` (`ScheduleID`);

--
-- Indexes for table `airequest`
--
ALTER TABLE `airequest`
  ADD PRIMARY KEY (`aiRequestID`),
  ADD KEY `idx_ai_request_user` (`userID`);

--
-- Indexes for table `course`
--
ALTER TABLE `course`
  ADD PRIMARY KEY (`CourseID`);

--
-- Indexes for table `schedule`
--
ALTER TABLE `schedule`
  ADD PRIMARY KEY (`ScheduleID`),
  ADD KEY `CourseID` (`CourseID`),
  ADD KEY `fk_schedule_task` (`TaskID`),
  ADD KEY `idx_schedule_user` (`UserID`),
  ADD KEY `idx_schedule_dates` (`startDateTime`,`endDateTime`);

--
-- Indexes for table `task`
--
ALTER TABLE `task`
  ADD PRIMARY KEY (`TaskID`),
  ADD KEY `idx_task_schedule` (`ScheduleID`),
  ADD KEY `idx_task_status` (`TaskStatus`),
  ADD KEY `task_ibfk_2` (`UserID`);

--
-- Indexes for table `tracker`
--
ALTER TABLE `tracker`
  ADD PRIMARY KEY (`TrackerID`),
  ADD KEY `idx_tracker_user` (`UserID`),
  ADD KEY `idx_tracker_task` (`TaskID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`UserID`),
  ADD UNIQUE KEY `Email` (`Email`);

--
-- Indexes for table `user_course`
--
ALTER TABLE `user_course`
  ADD PRIMARY KEY (`UserID`,`CourseID`),
  ADD KEY `idx_user_course_user` (`UserID`),
  ADD KEY `idx_user_course_course` (`CourseID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `airecommendation`
--
ALTER TABLE `airecommendation`
  MODIFY `recommendationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `airequest`
--
ALTER TABLE `airequest`
  MODIFY `aiRequestID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `course`
--
ALTER TABLE `course`
  MODIFY `CourseID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `schedule`
--
ALTER TABLE `schedule`
  MODIFY `ScheduleID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=163;

--
-- AUTO_INCREMENT for table `task`
--
ALTER TABLE `task`
  MODIFY `TaskID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tracker`
--
ALTER TABLE `tracker`
  MODIFY `TrackerID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `UserID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `airecommendation`
--
ALTER TABLE `airecommendation`
  ADD CONSTRAINT `airecommendation_ibfk_1` FOREIGN KEY (`ScheduleID`) REFERENCES `schedule` (`ScheduleID`) ON DELETE CASCADE;

--
-- Constraints for table `airequest`
--
ALTER TABLE `airequest`
  ADD CONSTRAINT `airequest_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `schedule`
--
ALTER TABLE `schedule`
  ADD CONSTRAINT `fk_schedule_task` FOREIGN KEY (`TaskID`) REFERENCES `task` (`TaskID`) ON DELETE SET NULL,
  ADD CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedule_ibfk_2` FOREIGN KEY (`CourseID`) REFERENCES `course` (`CourseID`) ON DELETE SET NULL;

--
-- Constraints for table `task`
--
ALTER TABLE `task`
  ADD CONSTRAINT `task_ibfk_1` FOREIGN KEY (`ScheduleID`) REFERENCES `schedule` (`ScheduleID`) ON DELETE SET NULL,
  ADD CONSTRAINT `task_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `tracker`
--
ALTER TABLE `tracker`
  ADD CONSTRAINT `tracker_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE,
  ADD CONSTRAINT `tracker_ibfk_2` FOREIGN KEY (`TaskID`) REFERENCES `task` (`TaskID`) ON DELETE SET NULL;

--
-- Constraints for table `user_course`
--
ALTER TABLE `user_course`
  ADD CONSTRAINT `user_course_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_course_ibfk_2` FOREIGN KEY (`CourseID`) REFERENCES `course` (`CourseID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
