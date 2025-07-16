<?php
// Check if this file is being included
if (!defined('ADMIN_ACCESS')) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        // Validate and sanitize input
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

        // Validate required fields
        if (empty($name) || empty($address)) {
            throw new Exception("Please fill in all required fields.");
        }

        // Validate email format if provided
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }

        // Validate status
        if (!empty($status) && !in_array($status, ['active', 'inactive'])) {
            throw new Exception("Invalid status value.");
        }

        // Insert clinic into database
        $stmt = $conn->prepare("
            INSERT INTO clinics (name, address, phone, email, status, created_at)
            VALUES (:name, :address, :phone, :email, :status, NOW())
        ");

        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':status', $status);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Clinic added successfully!";
        } else {
            throw new Exception("Error adding clinic to database.");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// Return to the reports page
header("Location: reports.php");
exit(); 