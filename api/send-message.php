<?php
// Turn off error display to prevent HTML output
ini_set('display_errors', 0);
error_reporting(0);

// Start output buffering to catch any unwanted output
ob_start();

session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    ob_end_clean(); // Clear any output buffer
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Get JSON data from request body
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['receiver_id']) || !isset($data['subject']) || !isset($data['message'])) {
    ob_end_clean(); // Clear any output buffer
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Check if receiver exists and is approved
    $check_sql = "SELECT id FROM users WHERE id = ? AND approval_status = 'approved' LIMIT 1";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute([$data['receiver_id']]);
    if ($check_stmt->rowCount() === 0) {
        ob_end_clean(); // Clear any output buffer
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Recipient not found or not approved']);
        exit();
    }

    // Insert message into database
    $sql = "INSERT INTO messages (sender_id, receiver_id, subject, message, is_read) VALUES (?, ?, ?, ?, 0)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $_SESSION['user_id'],
        $data['receiver_id'],
        $data['subject'],
        $data['message']
    ]);

    ob_end_clean(); // Clear any output buffer
    echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
} catch (Exception $e) {
    error_log("Error sending message: " . $e->getMessage());
    ob_end_clean(); // Clear any output buffer
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to send message']);
} 