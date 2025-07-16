<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request data']);
    exit();
}

require_once '../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Begin transaction
    $conn->beginTransaction();

    // Prepare the update statement
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) 
                           VALUES (:key, :value) 
                           ON DUPLICATE KEY UPDATE setting_value = :value");

    // Update each setting
    foreach ($data as $key => $value) {
        $stmt->execute([
            ':key' => $key,
            ':value' => $value
        ]);
    }

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("Error saving system settings: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save settings: ' . $e->getMessage()]);
} 