-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 13, 2025 at 07:29 PM
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
-- Database: `medbuddy`
--

-- --------------------------------------------------------

--
-- Table structure for table `allergies`
--

CREATE TABLE `allergies` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `allergen` varchar(100) NOT NULL,
  `reaction` text DEFAULT NULL,
  `severity` enum('mild','moderate','severe') NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `allergies`
--

INSERT INTO `allergies` (`id`, `patient_id`, `allergen`, `reaction`, `severity`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'Penicillin', 'Rash', 'moderate', 'Avoid use', '2025-07-13 14:05:44', '2025-07-13 14:05:44'),
(2, 2, 'None', NULL, 'mild', NULL, '2025-07-13 14:05:44', '2025-07-13 14:05:44');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `clinic_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `purpose` text NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled','no-show') DEFAULT 'scheduled',
  `vitals_recorded` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `doctor_id`, `clinic_id`, `date`, `time`, `purpose`, `notes`, `status`, `vitals_recorded`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, '2024-07-01', '09:00:00', 'Routine checkup', 'Patient in good health', 'completed', 1, '2025-07-13 14:05:44', '2025-07-13 14:05:44'),
(2, 2, 2, 2, '2024-07-02', '10:30:00', 'Child fever', 'High temperature', 'completed', 1, '2025-07-13 14:05:44', '2025-07-13 14:05:44'),
(3, 2, 2, 2, '2025-07-13', '08:00:00', 'asd', 'asd', 'completed', 1, '2025-07-13 14:13:24', '2025-07-13 14:17:04'),
(4, 2, 2, 2, '2025-07-13', '08:30:00', 'asd', 'asd', 'scheduled', 1, '2025-07-13 14:58:30', '2025-07-13 14:58:46');

-- --------------------------------------------------------

--
-- Table structure for table `clinics`
--

CREATE TABLE `clinics` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinics`
--

INSERT INTO `clinics` (`id`, `name`, `address`, `phone`, `email`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Medbuddy Main Clinic', '123 Main St, City', '1234567890', 'main@medbuddy.com', 'active', '2025-07-13 14:05:44', '2025-07-13 14:05:44'),
(2, 'Medbuddy Pediatrics', '456 Child Ave, City', '0987654321', 'peds@medbuddy.com', 'active', '2025-07-13 14:05:44', '2025-07-13 14:05:44');

-- --------------------------------------------------------

--
-- Table structure for table `diagnoses`
--

CREATE TABLE `diagnoses` (
  `id` int(11) NOT NULL,
  `medical_record_id` int(11) NOT NULL,
  `diagnosis` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `diagnoses`
--

INSERT INTO `diagnoses` (`id`, `medical_record_id`, `diagnosis`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES
(3, 3, 'Asthma (ICD-10: J45) [Type: asd] - asd - Status: asd', 3, '2025-07-13 22:17:04', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `specialization_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `license_number` varchar(50) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`id`, `user_id`, `specialization_id`, `first_name`, `middle_name`, `last_name`, `license_number`, `contact_number`, `address`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 'John', 'A.', 'Doe', 'DOC12345', '1112223333', '123 Main St', 'active', '2025-07-13 14:05:44', '2025-07-13 14:05:44'),
(2, 3, 2, 'Susan', 'B.', 'Lee', 'DOC67890', '4445556661', '456 Child Ave', 'active', '2025-07-13 14:05:44', '2025-07-13 17:17:00');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_clinics`
--

CREATE TABLE `doctor_clinics` (
  `doctor_id` int(11) NOT NULL,
  `clinic_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_clinics`
--

INSERT INTO `doctor_clinics` (`doctor_id`, `clinic_id`, `created_at`) VALUES
(1, 1, '2025-07-13 14:05:44'),
(2, 2, '2025-07-13 14:05:44');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_schedules`
--

CREATE TABLE `doctor_schedules` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `clinic_id` int(11) NOT NULL,
  `day_of_week` tinyint(4) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `break_start` time DEFAULT NULL,
  `break_end` time DEFAULT NULL,
  `duration_per_appointment` int(11) DEFAULT 30,
  `max_appointments_per_slot` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_schedules`
--

INSERT INTO `doctor_schedules` (`id`, `doctor_id`, `clinic_id`, `day_of_week`, `start_time`, `end_time`, `break_start`, `break_end`, `duration_per_appointment`, `max_appointments_per_slot`, `created_at`, `updated_at`) VALUES
(1, 2, 2, 1, '08:00:00', '17:00:00', '12:00:00', '13:00:00', 30, 20, '2025-07-13 14:12:45', '2025-07-13 14:12:45');

-- --------------------------------------------------------

--
-- Table structure for table `immunizations`
--

CREATE TABLE `immunizations` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `vaccine_name` varchar(100) NOT NULL,
  `administration_date` date NOT NULL,
  `administered_by` int(11) NOT NULL,
  `lot_number` varchar(50) DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_requests`
--

CREATE TABLE `lab_requests` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `clinic_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `test_type` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('requested','in_progress','completed','cancelled') DEFAULT 'requested',
  `result` text DEFAULT NULL,
  `doctor_comment` text DEFAULT NULL,
  `requested_at` datetime DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `result_file` varchar(255) DEFAULT NULL,
  `request_slip` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_requests`
--

INSERT INTO `lab_requests` (`id`, `patient_id`, `doctor_id`, `clinic_id`, `appointment_id`, `test_type`, `notes`, `status`, `result`, `doctor_comment`, `requested_at`, `completed_at`, `result_file`, `request_slip`) VALUES
(1, 1, 1, 0, 1, 'CBC', 'Routine annual', 'completed', 'Normal', NULL, '2024-07-01 09:30:00', '2024-07-01 12:00:00', NULL, NULL),
(2, 2, 2, 0, 2, 'Dengue NS1', 'Fever workup', 'completed', 'Negative', 'Negative', '2024-07-02 11:00:00', '2024-07-02 14:00:00', NULL, NULL),
(3, 2, 2, 2, NULL, 'Ultrasound', 'Routine annual', 'completed', 'Negative', 'Very Good', '2025-07-13 22:27:01', '2025-07-13 22:55:34', 'labresult_3_1752418534.pdf', 'labrequest_3_1752416821.pdf');

-- --------------------------------------------------------

--
-- Table structure for table `medical_history`
--

CREATE TABLE `medical_history` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `condition_name` varchar(100) NOT NULL,
  `diagnosis_date` date DEFAULT NULL,
  `status` enum('active','resolved','chronic') NOT NULL,
  `severity` enum('mild','moderate','severe') NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medical_records`
--

CREATE TABLE `medical_records` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `record_date` date NOT NULL,
  `record_time` time NOT NULL,
  `record_type` enum('initial','follow-up','emergency') NOT NULL,
  `chief_complaint` text DEFAULT NULL,
  `present_illness` text DEFAULT NULL,
  `past_medical_history` text DEFAULT NULL,
  `family_history` text DEFAULT NULL,
  `social_history` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `medications` text DEFAULT NULL,
  `vital_signs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`vital_signs`)),
  `physical_examination` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment_plan` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_records`
--

INSERT INTO `medical_records` (`id`, `patient_id`, `doctor_id`, `appointment_id`, `record_date`, `record_time`, `record_type`, `chief_complaint`, `present_illness`, `past_medical_history`, `family_history`, `social_history`, `allergies`, `medications`, `vital_signs`, `physical_examination`, `diagnosis`, `treatment_plan`, `prescription`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, '2024-07-01', '09:15:00', 'initial', 'Routine checkup', 'No complaints', 'Asthma', 'No family history', 'Non-smoker', 'Penicillin', 'Albuterol', '{\"bp\":\"120/80\",\"hr\":72}', 'Normal', 'Healthy', 'Continue current meds', 'None', 'N/A', '2025-07-13 14:05:44', '2025-07-13 14:05:44'),
(2, 2, 2, 2, '2024-07-02', '10:45:00', 'initial', 'Fever', 'Fever for 2 days', 'Hypertension', 'Father has diabetes', 'Non-smoker', 'None', 'Paracetamol', '{\"bp\":\"110/70\",\"hr\":90}', 'Fever, flushed', 'Viral infection', 'Rest, fluids', 'Paracetamol 500mg', 'N/A', '2025-07-13 14:05:44', '2025-07-13 14:05:44'),
(3, 2, 2, 3, '0000-00-00', '00:00:00', 'initial', 'asd', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Healthy', NULL, NULL, 'asd', '2025-07-13 14:14:44', '2025-07-13 16:16:43'),
(4, 2, 2, 4, '0000-00-00', '00:00:00', 'initial', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'test', NULL, NULL, 'BP: 123/123, Temp: 76°C, HR: 23, RR: 123, SpO2: 213%, Weight: 213kg\n\nAdditional notes: adasdad', '2025-07-13 14:58:46', '2025-07-13 16:16:22');

-- --------------------------------------------------------

--
-- Table structure for table `medications`
--

CREATE TABLE `medications` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `medication_name` varchar(100) NOT NULL,
  `dosage` varchar(50) NOT NULL,
  `frequency` varchar(50) NOT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `prescribed_by` int(11) NOT NULL,
  `status` enum('active','discontinued','completed') NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medications`
--

INSERT INTO `medications` (`id`, `patient_id`, `medication_name`, `dosage`, `frequency`, `duration`, `start_date`, `end_date`, `prescribed_by`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'Albuterol', '2 puffs', 'twice daily', '30 days', '2024-06-01', NULL, 1, 'active', 'For asthma', '2025-07-13 14:05:44', '2025-07-13 14:16:49'),
(2, 2, 'Paracetamol', '500mg', 'thrice daily', '30 days', '2024-07-02', '2024-07-07', 2, 'completed', 'For fever', '2025-07-13 14:05:44', '2025-07-13 14:16:49'),
(3, 2, 'Hemarate', '1 Tablet', 'Once daily', '30 days', '2025-07-13', '2025-08-12', 2, 'active', 'Vitamins', '2025-07-13 14:17:04', '2025-07-13 14:35:28');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `type` enum('appointment','system','alert') NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `blood_type` varchar(5) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_number` varchar(20) DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `user_id`, `first_name`, `middle_name`, `last_name`, `date_of_birth`, `gender`, `blood_type`, `contact_number`, `address`, `emergency_contact_name`, `emergency_contact_number`, `medical_history`, `allergies`, `created_at`, `updated_at`) VALUES
(1, 6, 'Jane', NULL, 'Doe', '1990-05-15', 'female', 'A+', '5556667777', '789 Patient Rd', 'Mary Doe', '8889990000', 'Asthma', 'Penicillin', '2025-07-13 14:05:44', '2025-07-13 14:05:44'),
(2, 7, 'Bob', NULL, 'Smith', '1985-09-20', 'male', 'O-', '4445556666', '321 Patient Ln', 'Tom Smith', '1112223333', 'Hypertension', 'None', '2025-07-13 14:05:44', '2025-07-13 14:05:44');

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `medical_record_id` int(11) NOT NULL,
  `prescription_text` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`id`, `medical_record_id`, `prescription_text`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES
(3, 3, 'dasd|dasd|Once daily|30 days|asdas', 3, '2025-07-13 22:17:04', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `specializations`
--

CREATE TABLE `specializations` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `specializations`
--

INSERT INTO `specializations` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Cardiology', 'Heart specialist', '2025-07-13 14:05:44', '2025-07-13 14:05:44'),
(2, 'Pediatrics', 'Child health specialist', '2025-07-13 14:05:44', '2025-07-13 14:05:44');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `user_id`, `first_name`, `middle_name`, `last_name`, `contact_number`, `address`, `created_at`, `updated_at`) VALUES
(1, 4, 'Anna', NULL, 'Smith', '7778889999', '123 Main St', '2025-07-13 14:05:44', '2025-07-13 14:05:44'),
(2, 5, 'Mike', NULL, 'Brown', '2223334444', '456 Child Ave', '2025-07-13 14:05:44', '2025-07-13 14:05:44');

-- --------------------------------------------------------

--
-- Table structure for table `staff_clinics`
--

CREATE TABLE `staff_clinics` (
  `staff_id` int(11) NOT NULL,
  `clinic_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_clinics`
--

INSERT INTO `staff_clinics` (`staff_id`, `clinic_id`, `created_at`) VALUES
(1, 1, '2025-07-13 14:05:44'),
(2, 2, '2025-07-13 14:09:03');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','doctor','patient','staff') NOT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `role`, `approval_status`, `reset_token`, `reset_token_expiry`, `created_at`, `updated_at`) VALUES
(1, 'admin@medbuddy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'approved', NULL, NULL, '2025-07-13 14:05:44', '2025-07-13 14:08:06'),
(2, 'dr.john@medbuddy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'approved', NULL, NULL, '2025-07-13 14:05:44', '2025-07-13 14:05:44'),
(3, 'dr.susan@medbuddy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'approved', NULL, NULL, '2025-07-13 14:05:44', '2025-07-13 14:05:44'),
(4, 'nurse.anna@medbuddy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'approved', NULL, NULL, '2025-07-13 14:05:44', '2025-07-13 14:05:44'),
(5, 'reception.mike@medbuddy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'approved', NULL, NULL, '2025-07-13 14:05:44', '2025-07-13 14:05:44'),
(6, 'jane.doe@medbuddy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'approved', NULL, NULL, '2025-07-13 14:05:44', '2025-07-13 14:05:44'),
(7, 'bob.smith@medbuddy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'approved', NULL, NULL, '2025-07-13 14:05:44', '2025-07-13 14:05:44');

-- --------------------------------------------------------

--
-- Table structure for table `vital_signs`
--

CREATE TABLE `vital_signs` (
  `id` int(11) NOT NULL,
  `medical_record_id` int(11) NOT NULL,
  `blood_pressure_systolic` int(11) DEFAULT NULL,
  `blood_pressure_diastolic` int(11) DEFAULT NULL,
  `heart_rate` int(11) DEFAULT NULL,
  `respiratory_rate` int(11) DEFAULT NULL,
  `temperature` decimal(4,2) DEFAULT NULL,
  `oxygen_saturation` decimal(5,2) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `bmi` decimal(5,2) DEFAULT NULL,
  `pain_scale` int(11) DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vital_signs`
--

INSERT INTO `vital_signs` (`id`, `medical_record_id`, `blood_pressure_systolic`, `blood_pressure_diastolic`, `heart_rate`, `respiratory_rate`, `temperature`, `oxygen_saturation`, `weight`, `height`, `bmi`, `pain_scale`, `recorded_by`, `recorded_at`, `notes`) VALUES
(1, 1, 120, 80, 72, 16, 36.70, 98.00, 65.00, 170.00, 22.50, 0, 1, '2024-07-01 01:20:00', 'Normal'),
(2, 2, 110, 70, 90, 18, 38.50, 97.00, 30.00, 120.00, 20.80, 2, 1, '2024-07-02 02:50:00', 'Fever'),
(3, 3, 123, 123, 123, 123, 99.99, 123.00, 123.00, 123.00, 81.30, 2, 2, '2025-07-13 14:14:44', 'asdas'),
(4, 4, 123, 123, 23, 123, 76.00, 213.00, 213.00, 123.00, 140.80, 2, 2, '2025-07-13 14:58:46', 'adasdad');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `allergies`
--
ALTER TABLE `allergies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_allergies_patient` (`patient_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_appointments_patient` (`patient_id`),
  ADD KEY `idx_appointments_doctor` (`doctor_id`),
  ADD KEY `idx_appointments_clinic` (`clinic_id`),
  ADD KEY `idx_appointments_date` (`date`),
  ADD KEY `idx_appointments_status` (`status`);

--
-- Indexes for table `clinics`
--
ALTER TABLE `clinics`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `diagnoses`
--
ALTER TABLE `diagnoses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medical_record_id` (`medical_record_id`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_number` (`license_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `specialization_id` (`specialization_id`);

--
-- Indexes for table `doctor_clinics`
--
ALTER TABLE `doctor_clinics`
  ADD PRIMARY KEY (`doctor_id`,`clinic_id`),
  ADD KEY `clinic_id` (`clinic_id`);

--
-- Indexes for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_doctor_schedules_doctor` (`doctor_id`),
  ADD KEY `idx_doctor_schedules_clinic` (`clinic_id`),
  ADD KEY `idx_doctor_schedules_day` (`day_of_week`);

--
-- Indexes for table `immunizations`
--
ALTER TABLE `immunizations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `administered_by` (`administered_by`),
  ADD KEY `idx_immunizations_patient` (`patient_id`);

--
-- Indexes for table `lab_requests`
--
ALTER TABLE `lab_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `medical_history`
--
ALTER TABLE `medical_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_medical_history_patient` (`patient_id`);

--
-- Indexes for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_medical_records_patient` (`patient_id`),
  ADD KEY `idx_medical_records_doctor` (`doctor_id`),
  ADD KEY `idx_medical_records_appointment` (`appointment_id`);

--
-- Indexes for table `medications`
--
ALTER TABLE `medications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prescribed_by` (`prescribed_by`),
  ADD KEY `idx_medications_patient` (`patient_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medical_record_id` (`medical_record_id`);

--
-- Indexes for table `specializations`
--
ALTER TABLE `specializations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `staff_clinics`
--
ALTER TABLE `staff_clinics`
  ADD PRIMARY KEY (`staff_id`,`clinic_id`),
  ADD KEY `clinic_id` (`clinic_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vital_signs`
--
ALTER TABLE `vital_signs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recorded_by` (`recorded_by`),
  ADD KEY `idx_vital_signs_medical_record` (`medical_record_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `allergies`
--
ALTER TABLE `allergies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `clinics`
--
ALTER TABLE `clinics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `diagnoses`
--
ALTER TABLE `diagnoses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `immunizations`
--
ALTER TABLE `immunizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_requests`
--
ALTER TABLE `lab_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `medical_history`
--
ALTER TABLE `medical_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medical_records`
--
ALTER TABLE `medical_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `medications`
--
ALTER TABLE `medications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `specializations`
--
ALTER TABLE `specializations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `vital_signs`
--
ALTER TABLE `vital_signs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `allergies`
--
ALTER TABLE `allergies`
  ADD CONSTRAINT `allergies_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `diagnoses`
--
ALTER TABLE `diagnoses`
  ADD CONSTRAINT `diagnoses_ibfk_1` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `doctors_ibfk_2` FOREIGN KEY (`specialization_id`) REFERENCES `specializations` (`id`);

--
-- Constraints for table `doctor_clinics`
--
ALTER TABLE `doctor_clinics`
  ADD CONSTRAINT `doctor_clinics_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `doctor_clinics_ibfk_2` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD CONSTRAINT `doctor_schedules_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `doctor_schedules_ibfk_2` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `immunizations`
--
ALTER TABLE `immunizations`
  ADD CONSTRAINT `immunizations_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `immunizations_ibfk_2` FOREIGN KEY (`administered_by`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medical_history`
--
ALTER TABLE `medical_history`
  ADD CONSTRAINT `medical_history_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD CONSTRAINT `medical_records_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medical_records_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medical_records_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `medications`
--
ALTER TABLE `medications`
  ADD CONSTRAINT `medications_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medications_ibfk_2` FOREIGN KEY (`prescribed_by`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_clinics`
--
ALTER TABLE `staff_clinics`
  ADD CONSTRAINT `staff_clinics_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_clinics_ibfk_2` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vital_signs`
--
ALTER TABLE `vital_signs`
  ADD CONSTRAINT `vital_signs_ibfk_1` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vital_signs_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `staff` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
