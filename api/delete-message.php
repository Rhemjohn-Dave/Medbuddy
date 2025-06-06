<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Get JSON data from request body
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['message_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message ID is required']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Check if user owns the message (either as sender or receiver)
    $check_sql = "SELECT * FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute([$data['message_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    
    if ($check_stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You do not have permission to delete this message']);
        exit();
    }

    // Delete the message
    $delete_sql = "DELETE FROM messages WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->execute([$data['message_id']]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Error deleting message: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete message']);
} 