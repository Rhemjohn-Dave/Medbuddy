<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in to view messages']);
    exit();
}

// Get message ID from query string
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message ID is required']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // First check if message exists
    $check_sql = "SELECT id, sender_id, receiver_id FROM messages WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute([$_GET['id']]);
    $message_exists = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$message_exists) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Message not found']);
        exit();
    }

    // Check if user has access to this message
    if ($message_exists['sender_id'] != $_SESSION['user_id'] && $message_exists['receiver_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You do not have permission to view this message']);
        exit();
    }

    // Get message details
    $sql = "SELECT m.*, 
        u_sender.role as sender_role,
        CASE 
            WHEN u_sender.role = 'doctor' THEN d.first_name 
            WHEN u_sender.role = 'patient' THEN p.first_name 
            WHEN u_sender.role = 'staff' THEN s.first_name
            WHEN u_sender.role = 'admin' THEN 'Admin'
            ELSE NULL 
        END as sender_first_name,
        CASE 
            WHEN u_sender.role = 'doctor' THEN d.last_name 
            WHEN u_sender.role = 'patient' THEN p.last_name 
            WHEN u_sender.role = 'staff' THEN s.last_name
            WHEN u_sender.role = 'admin' THEN ''
            ELSE NULL 
        END as sender_last_name
        FROM messages m
        JOIN users u_sender ON m.sender_id = u_sender.id
        LEFT JOIN doctors d ON u_sender.id = d.user_id 
        LEFT JOIN patients p ON u_sender.id = p.user_id 
        LEFT JOIN staff s ON u_sender.id = s.user_id 
        WHERE m.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['id']]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);

    // Mark message as read if user is the receiver
    if ($message['receiver_id'] == $_SESSION['user_id'] && !$message['is_read']) {
        $update_sql = "UPDATE messages SET is_read = TRUE WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([$_GET['id']]);
    }

    // Format message data
    $sender_name = $message['sender_first_name'];
    if (!empty($message['sender_last_name'])) {
        $sender_name .= ' ' . $message['sender_last_name'];
    }
    $message['sender_name'] = $sender_name;
    $message['created_at'] = date('M d, Y h:i A', strtotime($message['created_at']));

    echo json_encode(['success' => true, 'message' => $message]);
} catch (Exception $e) {
    error_log("Error getting message: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred while retrieving the message. Please try again.']);
} 