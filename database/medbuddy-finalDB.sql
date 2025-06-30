-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 22, 2025 at 02:41 AM
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
(1, 1, 'Penicillin', 'Rash and hives', 'moderate', 'Avoid all penicillin-based antibiotics', '2025-06-06 07:05:08', '2025-06-06 07:05:08'),
(2, 3, 'Shellfish', 'Anaphylaxis', 'severe', 'Carry EpiPen at all times', '2025-06-06 07:05:08', '2025-06-06 07:05:08'),
(3, 1, 'Penicillin', 'Rash and hives', 'moderate', 'Avoid all penicillin-based antibiotics', '2025-06-06 07:05:18', '2025-06-06 07:05:18'),
(4, 3, 'Shellfish', 'Anaphylaxis', 'severe', 'Carry EpiPen at all times', '2025-06-06 07:05:18', '2025-06-06 07:05:18');

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
(1, 1, 2, 1, '2024-03-20', '09:00:00', 'Regular checkup', NULL, 'scheduled', 0, '2025-06-06 07:02:09', '2025-06-06 07:02:09'),
(2, 2, 3, 1, '2024-03-21', '10:30:00', 'Follow-up consultation', NULL, 'scheduled', 0, '2025-06-06 07:02:09', '2025-06-06 07:02:09'),
(3, 3, 4, 2, '2024-03-22', '14:00:00', 'Initial consultation', NULL, 'scheduled', 0, '2025-06-06 07:02:09', '2025-06-06 07:02:09'),
(4, 1, 2, 1, '2024-03-20', '09:00:00', 'Regular checkup', 'Annual physical examination', 'scheduled', 0, '2025-06-06 07:04:49', '2025-06-06 07:04:49'),
(5, 2, 3, 1, '2024-03-21', '10:30:00', 'Follow-up consultation', 'Review blood test results', 'scheduled', 0, '2025-06-06 07:04:49', '2025-06-06 07:04:49'),
(6, 3, 4, 2, '2024-03-22', '14:00:00', 'Initial consultation', 'New patient visit', 'scheduled', 0, '2025-06-06 07:04:49', '2025-06-06 07:04:49'),
(7, 1, 3, 1, '2025-06-06', '08:00:00', 'Checkup', 'Checkup', 'completed', 1, '2025-06-06 07:43:47', '2025-06-06 10:12:38');

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
(1, 'MedBuddy Main Clinic', '123 Medical Center Dr, Suite 100', '555-0101', 'main@medbuddy.com', 'active', '2025-06-06 06:58:07', '2025-06-06 06:58:07'),
(2, 'MedBuddy North Branch', '456 Health Ave', '555-0102', 'north@medbuddy.com', 'active', '2025-06-06 06:58:07', '2025-06-06 06:58:07'),
(3, 'MedBuddy East Clinic', '789 East Medical Blvd', '555-0103', 'east@medbuddy.com', 'active', '2025-06-06 07:02:08', '2025-06-06 07:02:08'),
(4, 'MedBuddy West Clinic', '321 West Health Street', '555-0104', 'west@medbuddy.com', 'active', '2025-06-06 07:02:08', '2025-06-06 07:02:08'),
(5, 'test', 'test', '(123) 123', 'engrdave25@gmail.com', 'active', '2025-06-06 08:26:24', '2025-06-06 08:26:24'),
(6, 'test', 'test', '(123) 123', 'engrdave25@gmail.com', 'active', '2025-06-06 08:27:05', '2025-06-06 08:27:05'),
(7, 'test1', 'test1', '(123) 213-2131', 'dr.smith@medbuddy.com', 'active', '2025-06-06 08:27:19', '2025-06-06 08:27:19'),
(8, 'test1', 'test1', '(123) 213-2131', 'dr.smith@medbuddy.com', 'active', '2025-06-06 08:29:02', '2025-06-06 08:29:02');

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
(1, 16, 'Asthma (ICD-10: l10) [Type: Primary] - Allergy to dust - Status: Active', 6, '2025-06-06 18:12:37', NULL, NULL);

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
(1, 2, 1, 'John', 'Robert', 'Smith', 'MD123456', '1234567890', NULL, 'active', '2025-06-06 06:58:07', '2025-06-06 06:58:07'),
(2, 5, 2, 'Michael', 'James', 'Johnson', 'MD234567', '2345678901', '789 Doctor Lane, Medical City', 'active', '2025-06-06 07:02:08', '2025-06-06 07:02:08'),
(3, 6, 3, 'Emily', 'Rose', 'Williams', 'MD345678', '3456789012', '456 Health Street, Medical City', 'active', '2025-06-06 07:02:08', '2025-06-06 07:02:08'),
(4, 7, 4, 'David', 'Thomas', 'Brown', 'MD456789', '4567890123', '123 Medical Avenue, Medical City', 'active', '2025-06-06 07:02:08', '2025-06-06 07:02:08');

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
(1, 1, '2025-06-06 06:58:07'),
(1, 2, '2025-06-06 06:58:07'),
(2, 1, '2025-06-06 07:02:08'),
(2, 2, '2025-06-06 07:02:08'),
(2, 3, '2025-06-06 07:02:08'),
(3, 1, '2025-06-06 07:02:08'),
(3, 3, '2025-06-06 07:02:08'),
(4, 2, '2025-06-06 07:02:08'),
(4, 4, '2025-06-06 07:02:08');

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
  `max_appointments_per_slot` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_schedules`
--

INSERT INTO `doctor_schedules` (`id`, `doctor_id`, `clinic_id`, `day_of_week`, `start_time`, `end_time`, `break_start`, `break_end`, `max_appointments_per_slot`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 2, '09:00:00', '17:00:00', '12:00:00', '13:00:00', 1, '2025-06-06 06:58:07', '2025-06-06 06:58:07'),
(2, 1, 2, 4, '09:00:00', '17:00:00', '12:00:00', '13:00:00', 1, '2025-06-06 06:58:07', '2025-06-06 06:58:07'),
(3, 2, 1, 3, '08:00:00', '16:00:00', '12:00:00', '13:00:00', 2, '2025-06-06 07:02:09', '2025-06-06 07:02:09'),
(4, 2, 2, 5, '09:00:00', '17:00:00', '12:30:00', '13:30:00', 2, '2025-06-06 07:02:09', '2025-06-06 07:02:09'),
(5, 3, 1, 4, '10:00:00', '18:00:00', '13:00:00', '14:00:00', 2, '2025-06-06 07:02:09', '2025-06-06 07:02:09'),
(6, 3, 3, 6, '08:30:00', '16:30:00', '12:00:00', '13:00:00', 2, '2025-06-06 07:02:09', '2025-06-06 07:02:09'),
(7, 4, 2, 2, '09:30:00', '17:30:00', '12:30:00', '13:30:00', 2, '2025-06-06 07:02:09', '2025-06-06 07:02:09'),
(8, 4, 4, 5, '08:00:00', '16:00:00', '12:00:00', '13:00:00', 2, '2025-06-06 07:02:09', '2025-06-06 07:02:09'),
(9, 1, 1, 6, '08:00:00', '17:00:00', '12:00:00', '13:00:00', 20, '2025-06-06 07:43:09', '2025-06-06 07:43:09');

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

--
-- Dumping data for table `medical_history`
--

INSERT INTO `medical_history` (`id`, `patient_id`, `condition_name`, `diagnosis_date`, `status`, `severity`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'Hypertension', '2010-01-15', 'chronic', 'moderate', 'Well controlled with medication', '2025-06-06 07:05:08', '2025-06-06 07:05:08'),
(2, 2, 'Type 2 Diabetes', '2015-06-20', 'chronic', 'moderate', 'Requires regular monitoring', '2025-06-06 07:05:08', '2025-06-06 07:05:08'),
(3, 3, 'Asthma', '1990-03-10', 'chronic', 'mild', 'Triggered by exercise and cold weather', '2025-06-06 07:05:08', '2025-06-06 07:05:08'),
(4, 1, 'Hypertension', '2010-01-15', 'chronic', 'moderate', 'Well controlled with medication', '2025-06-06 07:05:18', '2025-06-06 07:05:18'),
(5, 2, 'Type 2 Diabetes', '2015-06-20', 'chronic', 'moderate', 'Requires regular monitoring', '2025-06-06 07:05:18', '2025-06-06 07:05:18'),
(6, 3, 'Asthma', '1990-03-10', 'chronic', 'mild', 'Triggered by exercise and cold weather', '2025-06-06 07:05:18', '2025-06-06 07:05:18');

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
(1, 1, 2, 1, '0000-00-00', '00:00:00', 'initial', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Hypertension', NULL, 'Prescribed medication for blood pressure', 'Patient shows improvement', '2025-06-06 07:02:09', '2025-06-06 07:02:09'),
(2, 2, 3, 2, '0000-00-00', '00:00:00', 'initial', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Type 2 Diabetes', NULL, 'Insulin therapy recommended', 'Regular monitoring required', '2025-06-06 07:02:09', '2025-06-06 07:02:09'),
(3, 3, 4, 3, '0000-00-00', '00:00:00', 'initial', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Asthma', NULL, 'Inhaler prescribed', 'Avoid triggers', '2025-06-06 07:02:09', '2025-06-06 07:02:09'),
(4, 1, 2, 1, '2024-03-20', '09:00:00', 'initial', 'High blood pressure', 'Patient reports elevated BP readings', 'Hypertension since 2010', 'Father had heart disease', 'Non-smoker, occasional alcohol', 'Penicillin', 'Lisinopril 10mg daily', '{\"bp\": \"140/90\", \"hr\": \"72\", \"temp\": \"98.6\"}', 'Normal physical examination', 'Hypertension', 'Continue current medication', 'Lisinopril 10mg daily', 'Patient shows improvement', '2025-06-06 07:04:50', '2025-06-06 07:04:50'),
(5, 2, 3, 2, '2024-03-21', '10:30:00', 'follow-up', 'Blood sugar control', 'Blood glucose levels elevated', 'Type 2 Diabetes', 'Mother has diabetes', 'Sedentary lifestyle', 'None', 'Metformin 500mg BID', '{\"bp\": \"130/85\", \"hr\": \"75\", \"temp\": \"98.4\"}', 'Normal physical examination', 'Type 2 Diabetes', 'Adjust medication dosage', 'Metformin 1000mg BID', 'Regular monitoring required', '2025-06-06 07:04:50', '2025-06-06 07:04:50'),
(6, 3, 4, 3, '2024-03-22', '14:00:00', 'initial', 'Shortness of breath', 'Wheezing and chest tightness', 'Asthma since childhood', 'No family history', 'Non-smoker', 'Shellfish', 'Albuterol PRN', '{\"bp\": \"120/80\", \"hr\": \"78\", \"temp\": \"98.2\"}', 'Wheezing on auscultation', 'Asthma', 'Start maintenance inhaler', 'Albuterol 90mcg PRN', 'Avoid triggers', '2025-06-06 07:04:50', '2025-06-06 07:04:50'),
(7, 1, 2, 1, '2024-03-20', '09:00:00', 'initial', 'High blood pressure', 'Patient reports elevated BP readings', 'Hypertension since 2010', 'Father had heart disease', 'Non-smoker, occasional alcohol', 'Penicillin', 'Lisinopril 10mg daily', '{\"bp\": \"140/90\", \"hr\": \"72\", \"temp\": \"98.6\"}', 'Normal physical examination', 'Hypertension', 'Continue current medication', 'Lisinopril 10mg daily', 'Patient shows improvement', '2025-06-06 07:04:59', '2025-06-06 07:04:59'),
(8, 2, 3, 2, '2024-03-21', '10:30:00', 'follow-up', 'Blood sugar control', 'Blood glucose levels elevated', 'Type 2 Diabetes', 'Mother has diabetes', 'Sedentary lifestyle', 'None', 'Metformin 500mg BID', '{\"bp\": \"130/85\", \"hr\": \"75\", \"temp\": \"98.4\"}', 'Normal physical examination', 'Type 2 Diabetes', 'Adjust medication dosage', 'Metformin 1000mg BID', 'Regular monitoring required', '2025-06-06 07:04:59', '2025-06-06 07:04:59'),
(9, 3, 4, 3, '2024-03-22', '14:00:00', 'initial', 'Shortness of breath', 'Wheezing and chest tightness', 'Asthma since childhood', 'No family history', 'Non-smoker', 'Shellfish', 'Albuterol PRN', '{\"bp\": \"120/80\", \"hr\": \"78\", \"temp\": \"98.2\"}', 'Wheezing on auscultation', 'Asthma', 'Start maintenance inhaler', 'Albuterol 90mcg PRN', 'Avoid triggers', '2025-06-06 07:04:59', '2025-06-06 07:04:59'),
(10, 1, 2, 1, '2024-03-20', '09:00:00', 'initial', 'High blood pressure', 'Patient reports elevated BP readings', 'Hypertension since 2010', 'Father had heart disease', 'Non-smoker, occasional alcohol', 'Penicillin', 'Lisinopril 10mg daily', '{\"bp\": \"140/90\", \"hr\": \"72\", \"temp\": \"98.6\"}', 'Normal physical examination', 'Hypertension', 'Continue current medication', 'Lisinopril 10mg daily', 'Patient shows improvement', '2025-06-06 07:05:08', '2025-06-06 07:05:08'),
(11, 2, 3, 2, '2024-03-21', '10:30:00', 'follow-up', 'Blood sugar control', 'Blood glucose levels elevated', 'Type 2 Diabetes', 'Mother has diabetes', 'Sedentary lifestyle', 'None', 'Metformin 500mg BID', '{\"bp\": \"130/85\", \"hr\": \"75\", \"temp\": \"98.4\"}', 'Normal physical examination', 'Type 2 Diabetes', 'Adjust medication dosage', 'Metformin 1000mg BID', 'Regular monitoring required', '2025-06-06 07:05:08', '2025-06-06 07:05:08'),
(12, 3, 4, 3, '2024-03-22', '14:00:00', 'initial', 'Shortness of breath', 'Wheezing and chest tightness', 'Asthma since childhood', 'No family history', 'Non-smoker', 'Shellfish', 'Albuterol PRN', '{\"bp\": \"120/80\", \"hr\": \"78\", \"temp\": \"98.2\"}', 'Wheezing on auscultation', 'Asthma', 'Start maintenance inhaler', 'Albuterol 90mcg PRN', 'Avoid triggers', '2025-06-06 07:05:08', '2025-06-06 07:05:08'),
(13, 1, 2, 1, '2024-03-20', '09:00:00', 'initial', 'High blood pressure', 'Patient reports elevated BP readings', 'Hypertension since 2010', 'Father had heart disease', 'Non-smoker, occasional alcohol', 'Penicillin', 'Lisinopril 10mg daily', '{\"bp\": \"140/90\", \"hr\": \"72\", \"temp\": \"98.6\"}', 'Normal physical examination', 'Hypertension', 'Continue current medication', 'Lisinopril 10mg daily', 'Patient shows improvement', '2025-06-06 07:05:17', '2025-06-06 07:05:17'),
(14, 2, 3, 2, '2024-03-21', '10:30:00', 'follow-up', 'Blood sugar control', 'Blood glucose levels elevated', 'Type 2 Diabetes', 'Mother has diabetes', 'Sedentary lifestyle', 'None', 'Metformin 500mg BID', '{\"bp\": \"130/85\", \"hr\": \"75\", \"temp\": \"98.4\"}', 'Normal physical examination', 'Type 2 Diabetes', 'Adjust medication dosage', 'Metformin 1000mg BID', 'Regular monitoring required', '2025-06-06 07:05:17', '2025-06-06 07:05:17'),
(15, 3, 4, 3, '2024-03-22', '14:00:00', 'initial', 'Shortness of breath', 'Wheezing and chest tightness', 'Asthma since childhood', 'No family history', 'Non-smoker', 'Shellfish', 'Albuterol PRN', '{\"bp\": \"120/80\", \"hr\": \"78\", \"temp\": \"98.2\"}', 'Wheezing on auscultation', 'Asthma', 'Start maintenance inhaler', 'Albuterol 90mcg PRN', 'Avoid triggers', '2025-06-06 07:05:17', '2025-06-06 07:05:17'),
(16, 1, 3, 7, '0000-00-00', '00:00:00', 'initial', 'Shortness of breath', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Test', '2025-06-06 07:47:04', '2025-06-06 10:12:37');

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

INSERT INTO `medications` (`id`, `patient_id`, `medication_name`, `dosage`, `frequency`, `start_date`, `end_date`, `prescribed_by`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'Lisinopril', '10mg', 'Once daily', '2024-01-01', NULL, 2, 'active', 'Take in the morning', '2025-06-06 07:05:08', '2025-06-06 07:05:08'),
(2, 2, 'Metformin', '500mg', 'Twice daily', '2024-02-01', NULL, 3, 'active', 'Take with meals', '2025-06-06 07:05:08', '2025-06-06 07:05:08'),
(3, 3, 'Albuterol', '90mcg', 'As needed', '2024-03-01', NULL, 4, 'active', 'Use before exercise', '2025-06-06 07:05:08', '2025-06-06 07:05:08'),
(4, 1, 'Lisinopril', '10mg', 'Once daily', '2024-01-01', NULL, 2, 'active', 'Take in the morning', '2025-06-06 07:05:18', '2025-06-06 07:05:18'),
(5, 2, 'Metformin', '500mg', 'Twice daily', '2024-02-01', NULL, 3, 'active', 'Take with meals', '2025-06-06 07:05:18', '2025-06-06 07:05:18'),
(6, 3, 'Albuterol', '90mcg', 'As needed', '2024-03-01', NULL, 4, 'active', 'Use before exercise', '2025-06-06 07:05:18', '2025-06-06 07:05:18'),
(7, 1, 'Test', '10mg', 'Once daily', '2025-06-06', '2025-07-06', 3, 'active', 'Take with food', '2025-06-06 10:12:38', '2025-06-06 10:12:38');

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

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `subject`, `message`, `is_read`, `created_at`) VALUES
(1, 8, 5, 'Question about medication', 'Hello doctor, I have a question about my prescription', 0, '2025-06-06 07:05:18'),
(2, 9, 6, 'Appointment request', 'I would like to schedule an appointment next week', 1, '2025-06-06 07:05:18'),
(3, 10, 7, 'Follow-up query', 'Regarding my last visit, I have some concerns', 0, '2025-06-06 07:05:18'),
(4, 6, 9, 'Regarding our conversation', 'Okay, checking on my schedule, I am available', 1, '2025-06-06 08:01:09'),
(5, 4, 1, 'hi', 'sadsad', 1, '2025-06-06 09:07:37'),
(6, 3, 6, 'Hi', 'Ara si doc?', 1, '2025-06-21 07:22:33'),
(7, 6, 3, 'Regarding our conversation', 'oo', 1, '2025-06-21 07:22:41'),
(8, 1, 4, 'Regarding our conversation', 'hilhlk', 0, '2025-06-22 00:35:05');

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

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 8, 'Appointment Reminder', 'Your appointment is scheduled for tomorrow at 9:00 AM', 'appointment', 0, '2025-06-06 07:05:18'),
(2, 9, 'Test Results', 'Your lab results are now available', 'system', 0, '2025-06-06 07:05:18'),
(3, 10, 'Prescription Update', 'Your prescription has been renewed', 'alert', 0, '2025-06-06 07:05:18');

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
(1, 3, 'Jane', 'Marie', 'Doe', '1990-01-01', 'female', 'O+', '9876543210', '321 Patient Street, Medical City', 'Mary Wilson', '5678901235', 'MPOX', 'Dust', '2025-06-06 06:58:07', '2025-06-06 07:56:55'),
(2, 8, 'Robert', 'Allen', 'Wilson', '1985-05-15', 'male', 'A+', '5678901234', '321 Patient Street, Medical City', 'Mary Wilson', '5678901235', NULL, NULL, '2025-06-06 07:02:08', '2025-06-06 07:02:08'),
(3, 9, 'Lisa', 'Marie', 'Anderson', '1992-08-22', 'female', 'B-', '6789012345', '654 Health Road, Medical City', 'John Anderson', '6789012346', NULL, NULL, '2025-06-06 07:02:08', '2025-06-06 07:02:08'),
(4, 10, 'James', 'William', 'Taylor', '1978-11-30', 'male', 'O-', '7890123456', '987 Wellness Lane, Medical City', 'Sarah Taylor', '7890123457', NULL, NULL, '2025-06-06 07:02:08', '2025-06-06 07:02:08');

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
(1, 16, 'Test|10mg|Once daily|30 days|Take with food', 6, '2025-06-06 18:12:37', NULL, NULL);

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
(1, 'Cardiology', 'Heart and cardiovascular system', '2025-06-06 06:58:06', '2025-06-06 06:58:06'),
(2, 'Neurology', 'Brain and nervous system', '2025-06-06 06:58:06', '2025-06-06 06:58:06'),
(3, 'Pediatrics', 'Child healthcare', '2025-06-06 06:58:06', '2025-06-06 06:58:06'),
(4, 'Dermatology', 'Skin conditions', '2025-06-06 06:58:06', '2025-06-06 06:58:06'),
(5, 'Orthopedics', 'Bones and joints', '2025-06-06 06:58:06', '2025-06-06 06:58:06'),
(6, 'Test', 'Test', '2025-06-06 08:29:28', '2025-06-06 08:29:28');

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
  `position` varchar(50) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `user_id`, `first_name`, `middle_name`, `last_name`, `position`, `contact_number`, `address`, `created_at`, `updated_at`) VALUES
(1, 4, 'Sarah', 'Elizabeth', 'Johnson', 'Receptionist', '5551234567', NULL, '2025-06-06 06:58:09', '2025-06-06 06:58:09'),
(2, 11, 'Jennifer', 'Ann', 'Davis', 'Nurse', '8901234567', '147 Medical Center Dr', '2025-06-06 07:02:08', '2025-06-06 07:02:08'),
(3, 12, 'Robert', 'Lee', 'Miller', 'Receptionist', '9012345678', '258 Health Avenue', '2025-06-06 07:02:08', '2025-06-06 07:02:08');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
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

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `approval_status`, `reset_token`, `reset_token_expiry`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@medbuddy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'approved', NULL, NULL, '2025-06-06 06:58:06', '2025-06-06 06:58:06'),
(2, 'dr.smith', 'dr.smith@medbuddy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'approved', NULL, NULL, '2025-06-06 06:58:07', '2025-06-06 06:58:07'),
(3, 'jane.doe', 'jane.doe@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'approved', NULL, NULL, '2025-06-06 06:58:07', '2025-06-06 06:58:07'),
(4, 'staff1', 'staff1@medbuddy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'approved', NULL, NULL, '2025-06-06 06:58:08', '2025-06-06 06:58:08'),
(5, 'dr.johnson', 'dr.johnson@medbuddy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'approved', NULL, NULL, '2025-06-06 07:02:08', '2025-06-06 07:02:08'),
(6, 'dr.williams', 'dr.williams@medbuddy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'approved', NULL, NULL, '2025-06-06 07:02:08', '2025-06-06 07:02:08'),
(7, 'dr.brown', 'dr.brown@medbuddy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'approved', NULL, NULL, '2025-06-06 07:02:08', '2025-06-06 07:02:08'),
(8, 'patient1', 'patient1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'approved', NULL, NULL, '2025-06-06 07:02:08', '2025-06-06 07:02:08'),
(9, 'patient2', 'patient2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'approved', NULL, NULL, '2025-06-06 07:02:08', '2025-06-06 07:02:08'),
(10, 'patient3', 'patient3@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'approved', NULL, NULL, '2025-06-06 07:02:08', '2025-06-06 07:02:08'),
(11, 'staff2', 'staff2@medbuddy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'approved', NULL, NULL, '2025-06-06 07:02:08', '2025-06-06 07:02:08'),
(12, 'staff3', 'staff3@medbuddy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'approved', NULL, NULL, '2025-06-06 07:02:08', '2025-06-06 07:02:08');

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
(7, 16, 120, 80, 120, 120, 33.00, 80.00, 60.00, 160.00, 23.40, 5, 1, '2025-06-06 07:47:04', 'Goods');

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
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `clinics`
--
ALTER TABLE `clinics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `diagnoses`
--
ALTER TABLE `diagnoses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `immunizations`
--
ALTER TABLE `immunizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `medical_history`
--
ALTER TABLE `medical_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `medical_records`
--
ALTER TABLE `medical_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `medications`
--
ALTER TABLE `medications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `specializations`
--
ALTER TABLE `specializations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `vital_signs`
--
ALTER TABLE `vital_signs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
-- Constraints for table `vital_signs`
--
ALTER TABLE `vital_signs`
  ADD CONSTRAINT `vital_signs_ibfk_1` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vital_signs_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `staff` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
