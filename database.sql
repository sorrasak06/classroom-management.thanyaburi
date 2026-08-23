-- ====================================================================
-- Database: classroom_db
-- Web Application: ระบบบริหารจัดการชั้นเรียน (Classroom Management System)
-- Level: ประกาศนียบัตรวิชาชีพชั้นสูง (ปวส.) / ปริญญาตรี
-- Character Set: utf8mb4 / Collation: utf8mb4_unicode_ci
-- Supported Environment: XAMPP (Apache + MySQL / MariaDB)
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `classroom_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `classroom_db`;

-- ปิดการตรวจสอบ Foreign Key ชั่วคราวเพื่อป้องกัน Error #1451
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
SET NAMES utf8mb4;

-- --------------------------------------------------------
-- ลบตารางเดิมทั้งหมดตามลำดับความสัมพันธ์ (Child -> Parent)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `announcements`;
DROP TABLE IF EXISTS `scores`;
DROP TABLE IF EXISTS `submissions`;
DROP TABLE IF EXISTS `assignments`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `schedules`;
DROP TABLE IF EXISTS `subjects`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `teachers`;
DROP TABLE IF EXISTS `classrooms`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `system_settings`;

-- --------------------------------------------------------
-- 1. Table: system_settings (การตั้งค่าระบบและข้อมูลสถาบัน)
-- --------------------------------------------------------
CREATE TABLE `system_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(50) NOT NULL UNIQUE,
  `setting_value` TEXT DEFAULT NULL,
  `setting_group` VARCHAR(30) NOT NULL DEFAULT 'general',
  `description` VARCHAR(255) DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. Table: users (ตารางข้อมูลผู้ใช้งานและรหัสผ่าน)
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `full_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Table: classrooms (ตารางห้องเรียน)
-- --------------------------------------------------------
CREATE TABLE `classrooms` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `level` VARCHAR(20) NOT NULL,
  `department` VARCHAR(100) NOT NULL DEFAULT 'แผนกวิชาเทคโนโลยีสารสนเทศ',
  `academic_year` VARCHAR(10) NOT NULL DEFAULT '2567',
  `homeroom_teacher_id` INT DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. Table: teachers (ตารางข้อมูลครูผู้สอน)
-- --------------------------------------------------------
CREATE TABLE `teachers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `teacher_code` VARCHAR(20) NOT NULL UNIQUE,
  `department` VARCHAR(100) NOT NULL DEFAULT 'แผนกวิชาเทคโนโลยีสารสนเทศ',
  `position` VARCHAR(100) NOT NULL DEFAULT 'ครูชำนาญการ',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_teachers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Table: students (ตารางข้อมูลนักศึกษา)
-- --------------------------------------------------------
CREATE TABLE `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `student_code` VARCHAR(20) NOT NULL UNIQUE,
  `classroom_id` INT NOT NULL,
  `gender` ENUM('male', 'female', 'other') DEFAULT 'male',
  `birth_date` DATE DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `parent_name` VARCHAR(100) DEFAULT NULL,
  `parent_phone` VARCHAR(20) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_students_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE RESTRICT,
  INDEX `idx_students_code` (`student_code`),
  INDEX `idx_students_classroom` (`classroom_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. Table: subjects (ตารางรายวิชา)
-- --------------------------------------------------------
CREATE TABLE `subjects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject_code` VARCHAR(20) NOT NULL,
  `name_th` VARCHAR(100) NOT NULL,
  `name_en` VARCHAR(100) DEFAULT NULL,
  `credits` DECIMAL(3,1) NOT NULL DEFAULT 3.0,
  `description` TEXT DEFAULT NULL,
  `teacher_id` INT NOT NULL,
  `classroom_id` INT NOT NULL,
  `term` VARCHAR(10) NOT NULL DEFAULT '1',
  `academic_year` VARCHAR(10) NOT NULL DEFAULT '2567',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_subjects_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subjects_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE,
  INDEX `idx_subjects_code` (`subject_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. Table: schedules (ตารางเรียนรายสัปดาห์)
-- --------------------------------------------------------
CREATE TABLE `schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject_id` INT NOT NULL,
  `classroom_id` INT NOT NULL,
  `day_of_week` TINYINT NOT NULL COMMENT '1=จันทร์, 2=อังคาร, 3=พุธ, 4=พฤหัสบดี, 5=ศุกร์',
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `room_number` VARCHAR(50) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_schedules_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_schedules_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE,
  INDEX `idx_schedules_day` (`day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 8. Table: attendance (ตารางเช็กชื่อเข้าเรียน)
-- --------------------------------------------------------
CREATE TABLE `attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `classroom_id` INT NOT NULL,
  `attendance_date` DATE NOT NULL,
  `status` ENUM('present', 'absent', 'late', 'leave') NOT NULL DEFAULT 'present' COMMENT 'present=มา, absent=ขาด, late=สาย, leave=ลา',
  `remark` VARCHAR(255) DEFAULT NULL,
  `recorded_by` INT NOT NULL COMMENT 'Teacher User ID',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_attendance` (`student_id`, `subject_id`, `attendance_date`),
  CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attendance_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attendance_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE,
  INDEX `idx_attendance_date` (`attendance_date`),
  INDEX `idx_attendance_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 9. Table: assignments (ตารางงานและการบ้าน)
-- --------------------------------------------------------
CREATE TABLE `assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject_id` INT NOT NULL,
  `teacher_id` INT NOT NULL,
  `classroom_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `max_score` DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  `file_attachment` VARCHAR(255) DEFAULT NULL,
  `due_date` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_assignments_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignments_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignments_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE,
  INDEX `idx_assignments_due` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 10. Table: submissions (ตารางการส่งงานและการตรวจให้คะแนน)
-- --------------------------------------------------------
CREATE TABLE `submissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `assignment_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `submission_file` VARCHAR(255) DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `score` DECIMAL(5,2) DEFAULT NULL,
  `feedback` TEXT DEFAULT NULL,
  `graded_at` DATETIME DEFAULT NULL,
  `status` ENUM('submitted', 'graded', 'late') NOT NULL DEFAULT 'submitted',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_student_assignment` (`assignment_id`, `student_id`),
  CONSTRAINT `fk_submissions_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  INDEX `idx_submissions_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 11. Table: scores (ตารางบันทึกคะแนนรวมและตัดเกรด)
-- --------------------------------------------------------
CREATE TABLE `scores` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `term` VARCHAR(10) NOT NULL DEFAULT '1',
  `academic_year` VARCHAR(10) NOT NULL DEFAULT '2567',
  `attendance_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'คะแนนจิตพิสัย/เข้าเรียน (10%)',
  `assignment_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'คะแนนงาน/การบ้าน (40%)',
  `midterm_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'คะแนนกลางภาค (25%)',
  `final_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'คะแนนปลายภาค (25%)',
  `total_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'คะแนนรวม (100%)',
  `grade` VARCHAR(5) NOT NULL DEFAULT 'F' COMMENT 'A, B+, B, C+, C, D+, D, F',
  `recorded_by` INT NOT NULL COMMENT 'Teacher User ID',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_student_subject_term` (`student_id`, `subject_id`, `term`, `academic_year`),
  CONSTRAINT `fk_scores_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_scores_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 12. Table: announcements (ตารางประกาศข่าวสาร)
-- --------------------------------------------------------
CREATE TABLE `announcements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `target_role` ENUM('all', 'teacher', 'student') NOT NULL DEFAULT 'all',
  `image_attachment` VARCHAR(255) DEFAULT NULL,
  `file_attachment` VARCHAR(255) DEFAULT NULL,
  `author_id` INT NOT NULL,
  `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_announcements_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_announcements_pinned` (`is_pinned`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 13. Table: notifications (ตารางแจ้งเตือนระบบ)
-- --------------------------------------------------------
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `link` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_notifications_user_read` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- SEED DATA (ข้อมูลเริ่มต้นสำหรับการสาธิตและการทดสอบระบบ)
-- ====================================================================

-- 1. System Settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`, `description`) VALUES
('school_name', 'วิทยาลัยเทคนิคธัญบุรี', 'general', 'ชื่อสถานศึกษา (ภาษาไทย)'),
('school_name_en', 'Thanyaburi Technical College', 'general', 'ชื่อสถานศึกษา (ภาษาอังกฤษ)'),
('system_title', 'ระบบบริหารจัดการชั้นเรียน', 'general', 'ชื่อระบบแอปพลิเคชัน'),
('current_academic_year', '2567', 'academic', 'ปีการศึกษาปัจจุบัน'),
('current_term', '1', 'academic', 'ภาคเรียนปัจจุบัน'),
('director_name', 'ดร.สมศักดิ์ วัฒนากุล', 'general', 'ผู้อำนวยการสถานศึกษา'),
('contact_email', 'contact@thanya.ac.th', 'contact', 'อีเมลติดต่อสถาบัน'),
('contact_phone', '02-577-1111', 'contact', 'เบอร์โทรศัพท์ติดต่อ'),
('address', 'เลขที่ 1 หมู่ 3 ถ.รังสิต-นครนายก ต.รังสิต อ.ธัญบุรี จ.ปทุมธานี 12110', 'contact', 'ที่อยู่สถานศึกษา');

-- 2. Users (Admin, Teachers, 20 Students)
-- Password for all accounts: 'admin123' / 'teacher123' / 'student123'
INSERT INTO `users` (`id`, `username`, `password`, `role`, `email`, `full_name`, `phone`, `avatar`, `status`, `created_at`) VALUES
-- 1 Admin
(1, 'admin', '$2y$10$5jWzP0cW1YgK7j5rX8o7r.ZJ1z4pC3fT6vN9qL8yU2mR4bE7aG1mC', 'admin', 'admin@thanya.ac.th', 'ผู้ดูแลระบบหลัก (Admin System)', '081-234-5678', NULL, 'active', NOW()),

-- 3 Teachers
(2, 'teacher', '$2y$10$y6uH9jN8xL4rP1vC5mQ7w.rT4aK8zB2fG6wO1eJ5sU9tV3bM7nE2y', 'teacher', 'somchai.j@thanya.ac.th', 'ดร.สมชาย ใจดี', '089-111-2233', NULL, 'active', NOW()),
(3, 'teacher2', '$2y$10$y6uH9jN8xL4rP1vC5mQ7w.rT4aK8zB2fG6wO1eJ5sU9tV3bM7nE2y', 'teacher', 'somsri.m@thanya.ac.th', 'อ.สมศรี มีสุข', '089-444-5566', NULL, 'active', NOW()),
(4, 'teacher3', '$2y$10$y6uH9jN8xL4rP1vC5mQ7w.rT4aK8zB2fG6wO1eJ5sU9tV3bM7nE2y', 'teacher', 'wichan.m@thanya.ac.th', 'อ.วิชาญ มั่นคง', '089-777-8899', NULL, 'active', NOW()),

-- 20 Students
(5, 'student', '$2y$10$a1b2c3d4e5f6g7h8i9j0k.1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6', 'student', 'anan.r@thanya.ac.th', 'นายอนันต์ รักเรียน', '086-111-0001', NULL, 'active', NOW()),
(6, 'student2', '$2y$10$a1b2c3d4e5f6g7h8i9j0k.1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6', 'student', 'kanda.s@thanya.ac.th', 'นางสาวกานดา สุขใจ', '086-111-0002', NULL, 'active', NOW()),
(7, 'student3', '$2y$10$a1b2c3d4e5f6g7h8i9j0k.1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6', 'student', 'kittisak.m@thanya.ac.th', 'นายกิตติศักดิ์ มั่งมี', '086-111-0003', NULL, 'active', NOW()),
(8, 'student4', '$2y$10$a1b2c3d4e5f6g7h8i9j0k.1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6', 'student', 'jiraporn.s@thanya.ac.th', 'นางสาวจิราภรณ์ แสนดี', '086-111-0004', NULL, 'active', NOW()),
(9, 'student5', '$2y$10$a1b2c3d4e5f6g7h8i9j0k.1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6', 'student', 'chatchai.b@thanya.ac.th', 'นายฉัตรชัย บุญส่ง', '086-111-0005', NULL, 'active', NOW()),
(10, 'student6', '$2y$10$a1b2c3d4e5f6g7h8i9j0k.1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6', 'student', 'natnicha.n@thanya.ac.th', 'นางสาวณัฐณิชา นามดี', '086-111-0006', NULL, 'active', NOW()),
(11, 'student7', '$2y$10$a1b2c3d4e5f6g7h8i9j0k.1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6', 'student', 'thanakorn.r@thanya.ac.th', 'นายธนกร รุ่งเรือง', '086-111-0007', NULL, 'active', NOW()),
(12, 'student8', '$2y$10$a1b2c3d4e5f6g7h8i9j0k.1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6', 'student', 'paweena.p@thanya.ac.th', 'นางสาวปวีณา พรหมมา', '086-111-0008', NULL, 'active', NOW()),
(13, 'student9', '$2y$10$a1b2c3d4e5f6g7h8i9j0k.1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6', 'student', 'peerapat.s@thanya.ac.th', 'นายพีรภัทร สุวรรณ', '086-111-0009', NULL, 'active', NOW()),
(14, 'student10', '$2y$10$a1b2c3d4e5f6g7h8i9j0k.1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6', 'student', 'wiphada.d@thanya.ac.th', 'นางสาววิภาดา ดำรงศิลป์', '086-111-0010', NULL, 'active', NOW()),
(15, 'student11', '$2y$10$a1b2c3d4e5f6g7h8i9j0k.1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6', 'student', 'saran.t@thanya.ac.th', 'นายศรัณย์ ทองแท้', '086-111-0011', NULL, 'active', NOW()),
(16, 'student12', '$2y$10$a1b2c3d4e5f6g7h8i9j0k.1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6', 'student', 'arisa.k@thanya.ac.th', 'นางสาวอริสา แก้วใส', '086-111-0012', NULL, 'active', NOW()),
(17, 'student13', '$2y$10$a1b2c3d4e5f6g7h8i9j0k.1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6', 'student', 'ekkachai.p@thanya.ac.th', 'นายเอกชัย ประเสริฐ', '086-111-0013', NULL, 'active', NOW()),
(18, 'student14', '$2y$10$a1b2c3d4e5f6g7h8i9j0k.1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6', 'student', 'kamonwan.y@thanya.ac.th', 'นางสาวกมลวรรณ ยอดทอง', '086-111-0014', NULL, 'active', NOW()),
(19, 'student15', '$2y$10$a1b2c3d4e5f6g7h8i9j0k.1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6', 'student', 'korawit.c@thanya.ac.th', 'นายกรวิชญ์ เชิดชู', '086-111-0015', NULL, 'active', NOW()),
(20, 'student16', '$2y$10$a1b2c3d4e5f6g7h8i9j0k.1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6', 'student', 'chananchida.m@thanya.ac.th', 'นางสาวชนัญชิดา มิตรดี', '086-111-0016', NULL, 'active', NOW()),
(21, 'student17', '$2y$10$a1b2c3d4e5f6g7h8i9j0k.1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6', 'student', 'damrong.s@thanya.ac.th', 'นายดำรงศักดิ์ ศรีสุข', '086-111-0017', NULL, 'active', NOW()),
(22, 'student18', '$2y$10$a1b2c3d4e5f6g7h8i9j0k.1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6', 'student', 'thidarat.w@thanya.ac.th', 'นางสาวธิดารัตน์ วงศ์ษา', '086-111-0018', NULL, 'active', NOW()),
(23, 'student19', '$2y$10$a1b2c3d4e5f6g7h8i9j0k.1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6', 'student', 'nattapong.k@thanya.ac.th', 'นายณัฐพงษ์ กลิ่นหอม', '086-111-0019', NULL, 'active', NOW()),
(24, 'student20', '$2y$10$a1b2c3d4e5f6g7h8i9j0k.1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6', 'student', 'patsara.l@thanya.ac.th', 'นางสาวภัสรา เลิศลักษณ์', '086-111-0020', NULL, 'active', NOW());

-- 3. Classrooms (3 Classrooms)
INSERT INTO `classrooms` (`id`, `name`, `level`, `department`, `academic_year`, `homeroom_teacher_id`, `description`) VALUES
(1, 'ปวส.1 เทคโนโลยีสารสนเทศ 1/1', 'ปวส.1', 'แผนกวิชาเทคโนโลยีสารสนเทศ', '2567', 1, 'ห้องเรียนประจำกลุ่ม IT 1/1 (ห้อง 401)'),
(2, 'ปวส.1 คอมพิวเตอร์ธุรกิจ 1/2', 'ปวส.1', 'แผนกวิชาคอมพิวเตอร์ธุรกิจ', '2567', 2, 'ห้องเรียนประจำกลุ่ม BC 1/2 (ห้อง 402)'),
(3, 'ปวส.2 เทคโนโลยีสารสนเทศ 2/1', 'ปวส.2', 'แผนกวิชาเทคโนโลยีสารสนเทศ', '2567', 3, 'ห้องเรียนประจำกลุ่ม IT 2/1 (ห้อง 403)');

-- 4. Teachers
INSERT INTO `teachers` (`id`, `user_id`, `teacher_code`, `department`, `position`) VALUES
(1, 2, 'T67001', 'แผนกวิชาเทคโนโลยีสารสนเทศ', 'หัวหน้าแผนกวิชา / ครูชำนาญการพิเศษ'),
(2, 3, 'T67002', 'แผนกวิชาคอมพิวเตอร์ธุรกิจ', 'ครูชำนาญการ'),
(3, 4, 'T67003', 'แผนกวิชาเทคโนโลยีสารสนเทศ', 'ครูผู้ช่วย');

-- 5. Students (20 Students)
INSERT INTO `students` (`id`, `user_id`, `student_code`, `classroom_id`, `gender`, `birth_date`, `address`, `parent_name`, `parent_phone`) VALUES
-- Classroom 1 (ปวส.1 IT 1/1) - 10 students
(1,  5,  '67309010001', 1, 'male',   '2005-05-14', '123/45 ถ.รังสิต-นครนายก ต.รังสิต อ.ธัญบุรี จ.ปทุมธานี', 'นายสมเกียรติ รักเรียน', '081-999-1001'),
(2,  6,  '67309010002', 1, 'female', '2005-08-20', '45/6 หมู่ 2 ต.คลองหก อ.คลองหลวง จ.ปทุมธานี', 'นางสมร สุขใจ', '081-999-1002'),
(3,  7,  '67309010003', 1, 'male',   '2005-01-11', '78/90 ต.ประชาธิปัตย์ อ.ธัญบุรี จ.ปทุมธานี', 'นายสมควร มั่งมี', '081-999-1003'),
(4,  8,  '67309010004', 1, 'female', '2005-11-03', '12 หมู่ 4 ต.ลำลูกกา อ.ลำลูกกา จ.ปทุมธานี', 'นางสมศรี แสนดี', '081-999-1004'),
(5,  9,  '67309010005', 1, 'male',   '2005-03-25', '99/1 ต.บึงน้ำรักษ์ อ.ธัญบุรี จ.ปทุมธานี', 'นายบุญส่ง บุญส่ง', '081-999-1005'),
(6,  10, '67309010006', 1, 'female', '2005-06-18', '56/2 ถ.พหลโยธิน อ.เมือง จ.ปทุมธานี', 'นางนภา นามดี', '081-999-1006'),
(7,  11, '67309010007', 1, 'male',   '2005-09-09', '89/3 ต.คลองหนึ่ง อ.คลองหลวง จ.ปทุมธานี', 'นายรุ่งเรือง รุ่งเรือง', '081-999-1007'),
(8,  12, '67309010008', 1, 'female', '2005-12-30', '101/5 ต.ลาดสวาย อ.ลำลูกกา จ.ปทุมธานี', 'นางปราณี พรหมมา', '081-999-1008'),
(9,  13, '67309010009', 1, 'male',   '2005-02-14', '33/7 ต.คูคต อ.ลำลูกกา จ.ปทุมธานี', 'นายสุวรรณ สุวรรณ', '081-999-1009'),
(10, 14, '67309010010', 1, 'female', '2005-07-22', '77/8 ต.บางพูน อ.เมือง จ.ปทุมธานี', 'นางดาว ดำรงศิลป์', '081-999-1010'),

-- Classroom 2 (ปวส.1 BC 1/2) - 5 students
(11, 15, '67309010011', 2, 'male',   '2005-04-10', '15/2 หมู่ 1 ต.คลองสอง อ.คลองหลวง จ.ปทุมธานี', 'นายทอง ทองแท้', '081-999-1011'),
(12, 16, '67309010012', 2, 'female', '2005-09-15', '22/9 ถ.พหลโยธิน ต.ประชาธิปัตย์ อ.ธัญบุรี', 'นางแก้ว แก้วใส', '081-999-1012'),
(13, 17, '67309010013', 2, 'male',   '2005-10-05', '88/1 หมู่ 5 ต.บึงสนั่น อ.ธัญบุรี จ.ปทุมธานี', 'นายประสิทธิ์ ประเสริฐ', '081-999-1013'),
(14, 18, '67309010014', 2, 'female', '2005-12-12', '41/3 ต.ลำผักกูด อ.ธัญบุรี จ.ปทุมธานี', 'นางวันดี ยอดทอง', '081-999-1014'),
(15, 19, '67309010015', 2, 'male',   '2005-03-30', '95/4 ถ.รังสิต-นครนายก อ.ธัญบุรี', 'นายเชิด เชิดชู', '081-999-1015'),

-- Classroom 3 (ปวส.2 IT 2/1) - 5 students
(16, 20, '67309010016', 3, 'female', '2004-06-25', '112/3 ต.คลองสาม อ.คลองหลวง จ.ปทุมธานี', 'นางมิตร มิตรดี', '081-999-1016'),
(17, 21, '67309010017', 3, 'male',   '2004-08-14', '63/8 ต.คลองสี่ อ.คลองหลวง จ.ปทุมธานี', 'นายศรี ศรีสุข', '081-999-1017'),
(18, 22, '67309010018', 3, 'female', '2004-11-20', '34/2 ต.บึงน้ำรักษ์ อ.ธัญบุรี จ.ปทุมธานี', 'นางรัตน์ วงศ์ษา', '081-999-1018'),
(19, 23, '67309010019', 3, 'male',   '2004-01-08', '76/1 ต.คูคต อ.ลำลูกกา จ.ปทุมธานี', 'นายกลิ่น กลิ่นหอม', '081-999-1019'),
(20, 24, '67309010020', 3, 'female', '2004-05-19', '59/4 ต.บางพูน อ.เมือง จ.ปทุมธานี', 'นางลักษณ์ เลิศลักษณ์', '081-999-1020');

-- 6. Subjects (5 Subjects)
INSERT INTO `subjects` (`id`, `subject_code`, `name_th`, `name_en`, `credits`, `description`, `teacher_id`, `classroom_id`, `term`, `academic_year`) VALUES
(1, '30901-2001', 'การพัฒนาโปรแกรมบนเว็บ', 'Web Programming Development', 3.0, 'ศึกษาและปฏิบัติเกี่ยวกับการพัฒนาเว็บแอปพลิเคชันด้วยภาษา PHP, JavaScript, Bootstrap และการเชื่อมต่อฐานข้อมูล MySQL', 1, 1, '1', '2567'),
(2, '30901-2002', 'ระบบการจัดการฐานข้อมูล', 'Database Management Systems', 3.0, 'ศึกษาหลักการของฐานข้อมูลเชิงสัมพันธ์ การออกแบบ ER-Diagram นอร์มัลไลเซชัน และการใช้คำสั่ง SQL', 1, 1, '1', '2567'),
(3, '30901-2003', 'ระบบเครือข่ายคอมพิวเตอร์', 'Computer Network Systems', 3.0, 'ศึกษาโครงสร้างเครือข่าย แบบจำลอง OSI และ TCP/IP การกำหนดหมายเลข IP และการตั้งค่า Router/Switch', 3, 1, '1', '2567'),
(4, '30901-2004', 'การวิเคราะห์และออกแบบระบบ', 'System Analysis and Design', 3.0, 'ศึกษาขั้นตอนวงจรการพัฒนาระบบ SDLC, DFD, UML Diagrams และการจัดทำข้อกำหนดความต้องการของระบบ', 2, 2, '1', '2567'),
(5, '30901-2005', 'การเขียนโปรแกรมเชิงวัตถุ', 'Object-Oriented Programming', 3.0, 'ศึกษาแนวคิด OOP คลาส อ็อบเจกต์ การสืบทอดคุณสมบัติ โพลีมอร์ฟิซึม และการประยุกต์ใช้งาน', 3, 3, '1', '2567');

-- 7. Schedules (Timetable for Weekdays)
INSERT INTO `schedules` (`id`, `subject_id`, `classroom_id`, `day_of_week`, `start_time`, `end_time`, `room_number`) VALUES
-- ห้อง 1 (ปวส.1/1 IT)
(1, 1, 1, 1, '08:30:00', '11:30:00', 'Lab IT 401'), -- จันทร์ เช้า: Web Programming
(2, 2, 1, 1, '13:00:00', '16:00:00', 'Lab IT 402'), -- จันทร์ บ่าย: Database
(3, 3, 1, 3, '08:30:00', '11:30:00', 'Network Lab 305'), -- พุธ เช้า: Computer Network
(4, 1, 1, 4, '08:30:00', '11:30:00', 'Lab IT 401'), -- พฤหัส เช้า: Web Programming Workshop
(5, 2, 1, 5, '13:00:00', '16:00:00', 'Lab IT 402'), -- ศุกร์ บ่าย: Database Project

-- ห้อง 2 (ปวส.1/2 BC)
(6, 4, 2, 2, '09:00:00', '12:00:00', 'Room 302'), -- อังคาร เช้า: SA&D
(7, 4, 2, 4, '13:00:00', '16:00:00', 'Room 302'), -- พฤหัส บ่าย: SA&D

-- ห้อง 3 (ปวส.2/1 IT)
(8, 5, 3, 2, '13:00:00', '16:00:00', 'Lab IT 403'), -- อังคาร บ่าย: OOP
(9, 5, 3, 5, '08:30:00', '11:30:00', 'Lab IT 403'); -- ศุกร์ เช้า: OOP

-- 8. Attendance Records (Sample for previous weeks)
INSERT INTO `attendance` (`student_id`, `subject_id`, `classroom_id`, `attendance_date`, `status`, `remark`, `recorded_by`) VALUES
-- สัปดาห์ที่ 1 (2026-08-03)
(1, 1, 1, '2026-08-03', 'present', 'ตรงเวลา', 2),
(2, 1, 1, '2026-08-03', 'present', 'ตรงเวลา', 2),
(3, 1, 1, '2026-08-03', 'late', 'สาย 15 นาที', 2),
(4, 1, 1, '2026-08-03', 'present', 'ตรงเวลา', 2),
(5, 1, 1, '2026-08-03', 'leave', 'ลาป่วย มีใบรับรองแพทย์', 2),
(6, 1, 1, '2026-08-03', 'present', 'ตรงเวลา', 2),
(7, 1, 1, '2026-08-03', 'present', 'ตรงเวลา', 2),
(8, 1, 1, '2026-08-03', 'present', 'ตรงเวลา', 2),
(9, 1, 1, '2026-08-03', 'present', 'ตรงเวลา', 2),
(10, 1, 1, '2026-08-03', 'present', 'ตรงเวลา', 2),

-- สัปดาห์ที่ 2 (2026-08-10)
(1, 1, 1, '2026-08-10', 'present', 'ตรงเวลา', 2),
(2, 1, 1, '2026-08-10', 'present', 'ตรงเวลา', 2),
(3, 1, 1, '2026-08-10', 'present', 'ตรงเวลา', 2),
(4, 1, 1, '2026-08-10', 'absent', 'ขาดเรียนโดยไม่แจ้ง', 2),
(5, 1, 1, '2026-08-10', 'present', 'ตรงเวลา', 2),
(6, 1, 1, '2026-08-10', 'present', 'ตรงเวลา', 2),
(7, 1, 1, '2026-08-10', 'late', 'สาย 20 นาที', 2),
(8, 1, 1, '2026-08-10', 'present', 'ตรงเวลา', 2),
(9, 1, 1, '2026-08-10', 'present', 'ตรงเวลา', 2),
(10, 1, 1, '2026-08-10', 'present', 'ตรงเวลา', 2),

-- สัปดาห์ที่ 3 (2026-08-17)
(1, 1, 1, '2026-08-17', 'present', 'ตรงเวลา', 2),
(2, 1, 1, '2026-08-17', 'present', 'ตรงเวลา', 2),
(3, 1, 1, '2026-08-17', 'present', 'ตรงเวลา', 2),
(4, 1, 1, '2026-08-17', 'present', 'ตรงเวลา', 2),
(5, 1, 1, '2026-08-17', 'present', 'ตรงเวลา', 2),
(6, 1, 1, '2026-08-17', 'present', 'ตรงเวลา', 2),
(7, 1, 1, '2026-08-17', 'present', 'ตรงเวลา', 2),
(8, 1, 1, '2026-08-17', 'leave', 'ลากิจ', 2),
(9, 1, 1, '2026-08-17', 'present', 'ตรงเวลา', 2),
(10, 1, 1, '2026-08-17', 'present', 'ตรงเวลา', 2);

-- 9. Assignments
INSERT INTO `assignments` (`id`, `subject_id`, `teacher_id`, `classroom_id`, `title`, `description`, `max_score`, `file_attachment`, `due_date`, `created_at`) VALUES
(1, 1, 1, 1, 'ใบงานที่ 1: การออกแบบระบบฐานข้อมูลและสร้างตาราง MySQL', 'ให้นักศึกษาออกแบบฐานข้อมูลสำหรับระบบจำหน่ายสินค้าออนไลน์ พร้อมเขียนคำสั่ง DDL สร้างตารางและความสัมพันธ์ (Foreign Keys) ให้ครบถ้วน ส่งเป็นไฟล์ .sql หรือ .pdf', 10.00, NULL, '2026-08-25 23:59:00', NOW() - INTERVAL 5 DAY),
(2, 1, 1, 1, 'ใบงานที่ 2: การพัฒนาหน้า Login และระบบตรวจสอบสิทธิ์ด้วย PHP', 'ให้นักศึกษาเขียนฟอร์ม Login ด้วย Bootstrap 5 และเขียนโค้ด PHP PDO ตรวจสอบรหัสผ่านด้วย password_verify() พร้อมบันทึก Session', 10.00, NULL, '2026-08-30 23:59:00', NOW() - INTERVAL 2 DAY),
(3, 2, 1, 1, 'แบบฝึกหัดการเขียน SQL Query ขั้นสูง (JOIN & Aggregate Functions)', 'จงเขียนคำสั่ง SQL เพื่อค้นหาข้อมูลและจัดกลุ่มรายงานตามโจทย์ที่กำหนดในเอกสารแนบ', 10.00, NULL, '2026-09-05 23:59:00', NOW()),
(4, 3, 3, 1, 'การคำนวณ IPv4 Subnetting และการตั้งค่า Router', 'ให้นักศึกษาแสดงวิธีทำคำนวณ Subnet Mask และ IP Range ตามโจทย์ที่ได้รับมอบหมาย', 10.00, NULL, '2026-09-10 23:59:00', NOW());

-- 10. Submissions
INSERT INTO `submissions` (`id`, `assignment_id`, `student_id`, `submission_file`, `note`, `submitted_at`, `score`, `feedback`, `graded_at`, `status`) VALUES
-- งานที่ 1 (ตรวจแล้ว)
(1, 1, 1, NULL, 'ส่งงานครับ ออกแบบตารางครบ 8 ตาราง พร้อมความสัมพันธ์ Foreign Keys ครบถ้วนครับ', NOW() - INTERVAL 4 DAY, 9.50, 'ออกแบบตารางได้ดีมาก โครงสร้างตรงตาม Third Normal Form', NOW() - INTERVAL 3 DAY, 'graded'),
(2, 1, 2, NULL, 'ส่งงานใบงานที่ 1 ค่ะอาจารย์', NOW() - INTERVAL 4 DAY, 10.00, 'ผลงานสมบูรณ์แบบมาก เขียนคำอธิบายฟิลด์ได้ชัดเจน', NOW() - INTERVAL 3 DAY, 'graded'),
(3, 1, 3, NULL, 'ส่งงานครับ', NOW() - INTERVAL 3 DAY, 8.00, 'ควรเพิ่ม Index ใน foreign key เพื่อประสิทธิภาพ', NOW() - INTERVAL 2 DAY, 'graded'),
(4, 1, 4, NULL, 'ส่งงานค่ะ', NOW() - INTERVAL 2 DAY, 8.50, 'ครบถ้วน ถูกต้องตามโจทย์', NOW() - INTERVAL 1 DAY, 'graded'),
(5, 1, 5, NULL, 'ส่งงานครับอาจารย์', NOW() - INTERVAL 2 DAY, NULL, NULL, NULL, 'submitted'),
-- งานที่ 2 (ส่งแล้ว รอตรวจ)
(6, 2, 1, NULL, 'ส่งงานใบงานที่ 2 ครับ ทำระบบ Show/Hide Password และ Session Guard เรียบร้อยครับ', NOW() - INTERVAL 1 DAY, NULL, NULL, NULL, 'submitted'),
(7, 2, 2, NULL, 'ส่งใบงานที่ 2 ค่ะ', NOW() - INTERVAL 1 DAY, NULL, NULL, NULL, 'submitted');

-- 11. Scores & Grades
INSERT INTO `scores` (`student_id`, `subject_id`, `term`, `academic_year`, `attendance_score`, `assignment_score`, `midterm_score`, `final_score`, `total_score`, `grade`, `recorded_by`) VALUES
(1, 1, '1', '2567', 10.00, 38.00, 23.50, 23.00, 94.50, 'A', 2),
(2, 1, '1', '2567', 10.00, 39.00, 24.00, 24.00, 97.00, 'A', 2),
(3, 1, '1', '2567', 8.00,  32.00, 20.00, 19.00, 79.00, 'B+', 2),
(4, 1, '1', '2567', 9.00,  30.00, 18.00, 17.00, 74.00, 'B', 2),
(5, 1, '1', '2567', 7.00,  28.00, 16.00, 16.00, 67.00, 'C+', 2),
(6, 1, '1', '2567', 10.00, 35.00, 21.00, 21.00, 87.00, 'A', 2),
(7, 1, '1', '2567', 8.00,  31.00, 19.00, 18.00, 76.00, 'B+', 2),
(8, 1, '1', '2567', 9.00,  29.00, 17.00, 17.00, 72.00, 'B', 2),
(9, 1, '1', '2567', 10.00, 34.00, 20.00, 20.00, 84.00, 'A', 2),
(10, 1, '1', '2567', 9.00, 27.00, 15.00, 15.00, 66.00, 'C+', 2),

-- วิชาที่ 2 (Database)
(1, 2, '1', '2567', 10.00, 36.00, 22.00, 21.00, 89.00, 'A', 2),
(2, 2, '1', '2567', 10.00, 37.00, 22.50, 22.50, 92.00, 'A', 2);

-- 12. Announcements
INSERT INTO `announcements` (`id`, `title`, `content`, `target_role`, `image_attachment`, `file_attachment`, `author_id`, `is_pinned`, `created_at`) VALUES
(1, 'ยินดีต้อนรับสู่ระบบบริหารจัดการชั้นเรียน ภาคเรียนที่ 1/2567', 'ขอให้นักศึกษาทุกคนตรวจสอบตารางเรียน รายวิชาที่ลงทะเบียน และติดตามงานที่ได้รับมอบหมายอย่างสม่ำเสมอ หากมีข้อสงสัยสามารถติดต่อครูที่ปรึกษาได้ทันที', 'all', NULL, NULL, 1, 1, NOW() - INTERVAL 7 DAY),
(2, 'กำหนดการสอบกลางภาค ประจำภาคเรียนที่ 1/2567', 'การสอบกลางภาคจะมีขึ้นระหว่างวันที่ 15 - 19 กันยายน 2567 ขอให้นักศึกษาเตรียมตัวอ่านหนังสือ และตรวจสอบห้องสอบให้เรียบร้อย', 'student', NULL, NULL, 2, 1, NOW() - INTERVAL 3 DAY),
(3, 'แจ้งอาจารย์ผู้สอนบันทึกเวลาเรียนและการส่งงานสัปดาห์ที่ 4', 'ขอให้อาจารย์ผู้สอนทุกท่านดำเนินการบันทึกข้อมูลการเข้าเรียนของนักศึกษาและสรุปผลคะแนนเก็บในระบบให้เรียบร้อยภายในวันศุกร์นี้', 'teacher', NULL, NULL, 1, 0, NOW() - INTERVAL 1 DAY);

-- 13. Notifications
INSERT INTO `notifications` (`user_id`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(5, 'ได้รับคะแนนงานแล้ว', 'อาจารย์ได้ตรวจและให้คะแนน "ใบงานที่ 1: การออกแบบระบบฐานข้อมูล" แล้ว (9.5/10)', 'student/assignments.php', 0, NOW() - INTERVAL 1 DAY),
(5, 'มีการบ้านใหม่', 'วิชาการพัฒนาโปรแกรมบนเว็บ ได้มอบหมาย "ใบงานที่ 2: การพัฒนาหน้า Login"', 'student/assignments.php', 0, NOW() - INTERVAL 2 DAY),
(2, 'นักศึกษาได้ส่งงานใหม่', 'นายอนันต์ รักเรียน ได้ส่งงาน "ใบงานที่ 2: การพัฒนาหน้า Login"', 'teacher/assignment-submissions.php?id=2', 0, NOW() - INTERVAL 1 DAY),
(1, 'ระบบพร้อมใช้งาน', 'ระบบบริหารจัดการชั้นเรียนติดตั้งและพร้อมให้บริการเรียบร้อยแล้ว', 'admin/dashboard.php', 1, NOW() - INTERVAL 7 DAY);

-- คืนค่าการตรวจสอบ Foreign Key
SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;
SET SQL_MODE = @OLD_SQL_MODE;
