<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request data']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Start transaction
    $conn->beginTransaction();
    
    // Update users table
    $user_sql = "UPDATE users SET 
                 username = ?, 
                 email = ?, 
                 approval_status = ? 
                 WHERE id = ?";
                 
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->execute([
        $data['username'],
        $data['email'],
        $data['approval_status'],
        $data['user_id']
    ]);
    
    // Update role-specific table based on user role
    switch ($data['role']) {
        case 'doctor':
            $role_sql = "UPDATE doctors SET 
                        first_name = ?, 
                        middle_name = ?, 
                        last_name = ?, 
                        specialization = ?, 
                        license_number = ? 
                        WHERE user_id = ?";
            $role_stmt = $conn->prepare($role_sql);
            $role_stmt->execute([
                $data['first_name'],
                $data['middle_name'],
                $data['last_name'],
                $data['specialization'],
                $data['license_number'],
                $data['user_id']
            ]);
            break;
            
        case 'patient':
            $role_sql = "UPDATE patients SET 
                        first_name = ?, 
                        middle_name = ?, 
                        last_name = ?, 
                        date_of_birth = ?, 
                        gender = ?, 
                        address = ? 
                        WHERE user_id = ?";
            $role_stmt = $conn->prepare($role_sql);
            $role_stmt->execute([
                $data['first_name'],
                $data['middle_name'],
                $data['last_name'],
                $data['date_of_birth'],
                $data['gender'],
                $data['address'],
                $data['user_id']
            ]);
            break;
            
        case 'staff':
            $role_sql = "UPDATE staff SET 
                        first_name = ?, 
                        middle_name = ?, 
                        last_name = ?, 
                        position = ?, 
                        contact_number = ?,
                        address = ?
                        WHERE user_id = ?";
            $role_stmt = $conn->prepare($role_sql);
            $role_stmt->execute([
                $data['first_name'],
                $data['middle_name'],
                $data['last_name'],
                $data['position'],
                $data['contact_number'],
                $data['address'],
                $data['user_id']
            ]);
            break;
    }
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Error updating user: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to update user: ' . $e->getMessage()
    ]);
}
?> 