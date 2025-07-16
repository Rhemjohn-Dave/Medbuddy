<?php
// Disable error display to prevent HTML errors from breaking JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../../logs/php-error.log');

// Set JSON header
header('Content-Type: application/json');

try {
    // Check if required files exist
    if (!file_exists('../../config/database.php')) {
        throw new Exception('Database configuration file not found');
    }
    if (!file_exists('../../config/config.php')) {
        throw new Exception('Application configuration file not found');
    }

    require_once '../../config/database.php';
    require_once '../../config/config.php';

    // Check if user is logged in and is admin
    session_start();
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Unauthorized access');
    }

    // Get user ID and notes from POST data
    $userId = isset($_POST['userId']) ? (int)$_POST['userId'] : 0;
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';

    if (!$userId) {
        throw new Exception('Invalid user ID');
    }

    $db = new Database();
    $conn = $db->getConnection();

    // Start transaction
    $conn->beginTransaction();

    // First check if user exists and is pending
    $checkSql = "SELECT id, approval_status FROM users WHERE id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([$userId]);
    $user = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    if ($user['approval_status'] !== 'pending') {
        throw new Exception('User is not in pending status');
    }

    // Update user approval status
    $sql = "UPDATE users SET approval_status = 'approved', updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt->execute([$userId])) {
        throw new Exception('Failed to update user status');
    }

    // Get user email and role for notification
    $sql = "SELECT email, role FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt->execute([$userId])) {
        throw new Exception('Failed to fetch user details');
    }
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Get full name from the appropriate table
        $full_name = '';
        if ($user['role'] === 'doctor') {
            $name_stmt = $conn->prepare("SELECT first_name, last_name FROM doctors WHERE user_id = ?");
            $name_stmt->execute([$userId]);
            $name = $name_stmt->fetch(PDO::FETCH_ASSOC);
            if ($name) $full_name = trim($name['first_name'] . ' ' . $name['last_name']);
        } elseif ($user['role'] === 'patient') {
            $name_stmt = $conn->prepare("SELECT first_name, last_name FROM patients WHERE user_id = ?");
            $name_stmt->execute([$userId]);
            $name = $name_stmt->fetch(PDO::FETCH_ASSOC);
            if ($name) $full_name = trim($name['first_name'] . ' ' . $name['last_name']);
        } elseif ($user['role'] === 'staff') {
            $name_stmt = $conn->prepare("SELECT first_name, last_name FROM staff WHERE user_id = ?");
            $name_stmt->execute([$userId]);
            $name = $name_stmt->fetch(PDO::FETCH_ASSOC);
            if ($name) $full_name = trim($name['first_name'] . ' ' . $name['last_name']);
        }
        if (empty($full_name)) {
            $full_name = $user['email'];
        }
        // Send approval email
        $to = $user['email'];
        $subject = "Your MedBuddy Account Has Been Approved";
        $message = "Dear " . $full_name . ",\n\n";
        $message .= "Your MedBuddy account has been approved. You can now log in and access all features.\n\n";
        
        if (!empty($notes)) {
            $message .= "Additional Notes:\n" . $notes . "\n\n";
        }
        
        $message .= "Best regards,\nMedBuddy Team";
        $headers = "From: " . ADMIN_EMAIL;

        if (!mail($to, $subject, $message, $headers)) {
            error_log("Failed to send approval email to: " . $to);
        }
    }

    // Commit transaction
    $conn->commit();

    // Return success response
    echo json_encode(['success' => true, 'message' => 'User approved successfully']);

} catch (Exception $e) {
    // Rollback transaction if active
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Log the error with more details
    error_log("Error in approve-user.php: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
    
    // Return error response
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Error $e) {
    // Catch PHP 7+ errors
    error_log("Fatal error in approve-user.php: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An internal server error occurred']);
} 