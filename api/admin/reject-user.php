<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

// Check if user is logged in and is admin
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get request body
$data = json_decode(file_get_contents('php://input'), true);
$userId = isset($data['userId']) ? (int)$data['userId'] : 0;

if (!$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user ID']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Start transaction
    $conn->beginTransaction();

    // Update user approval status
    $sql = "UPDATE users SET approval_status = 'rejected', updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);

    // Get user email for notification
    $sql = "SELECT email, role FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Send rejection email
        $to = $user['email'];
        $subject = "Your MedBuddy Account Registration Status";
        $message = "Dear User,\n\n";
        $message .= "We regret to inform you that your MedBuddy account registration has been rejected. ";
        $message .= "If you believe this is an error, please contact our support team.\n\n";
        $message .= "Best regards,\nMedBuddy Team";
        $headers = "From: " . ADMIN_EMAIL;

        mail($to, $subject, $message, $headers);
    }

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'User rejected successfully']);

} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 