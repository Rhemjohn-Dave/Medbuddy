<?php
// Simple test script to check the doctor-clinics API
session_start();

// Simulate a logged-in doctor session
$_SESSION['user_id'] = 2; // Assuming this is a doctor user ID
$_SESSION['role'] = 'doctor';

// Include the API file
require_once 'api/doctor-clinics.php';
?> 