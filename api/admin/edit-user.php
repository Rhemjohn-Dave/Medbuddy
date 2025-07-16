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
                 email = ?, 
                 approval_status = ? 
                 WHERE id = ?";
                 
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->execute([
        $data['email'],
        $data['approval_status'],
        $data['user_id']
    ]);
    
    // Update role-specific table based on user role
    switch ($data['role']) {
        case 'doctor':
            // First, get or create specialization
            $spec_sql = "SELECT id FROM specializations WHERE name = ?";
            $spec_stmt = $conn->prepare($spec_sql);
            $spec_stmt->execute([$data['specialization']]);
            $spec_result = $spec_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($spec_result) {
                $specialization_id = $spec_result['id'];
            } else {
                // Create new specialization if it doesn't exist
                $insert_spec_sql = "INSERT INTO specializations (name) VALUES (?)";
                $insert_spec_stmt = $conn->prepare($insert_spec_sql);
                $insert_spec_stmt->execute([$data['specialization']]);
                $specialization_id = $conn->lastInsertId();
            }
            
            $role_sql = "UPDATE doctors SET 
                        first_name = ?, 
                        middle_name = ?, 
                        last_name = ?, 
                        specialization_id = ?, 
                        license_number = ?,
                        contact_number = ?,
                        address = ?
                        WHERE user_id = ?";
            $role_stmt = $conn->prepare($role_sql);
            $role_stmt->execute([
                $data['first_name'],
                $data['middle_name'],
                $data['last_name'],
                $specialization_id,
                $data['license_number'],
                $data['contact_number'],
                $data['address'],
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
                        contact_number = ?,
                        address = ?
                        WHERE user_id = ?";
            $role_stmt = $conn->prepare($role_sql);
            $role_stmt->execute([
                $data['first_name'],
                $data['middle_name'],
                $data['last_name'],
                $data['contact_number'],
                $data['address'],
                $data['user_id']
            ]);

            // Update staff_clinics assignments if provided
            if (isset($data['assigned_clinics']) && is_array($data['assigned_clinics'])) {
                // Get staff_id for this user
                $staff_stmt = $conn->prepare("SELECT id FROM staff WHERE user_id = ?");
                $staff_stmt->execute([$data['user_id']]);
                $staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);

                if ($staff) {
                    $staff_id = $staff['id'];
                    // Remove all current assignments
                    $conn->prepare("DELETE FROM staff_clinics WHERE staff_id = ?")->execute([$staff_id]);
                    // Add new assignments
                    if (!empty($data['assigned_clinics'])) {
                        $insert_stmt = $conn->prepare("INSERT INTO staff_clinics (staff_id, clinic_id) VALUES (?, ?)");
                        foreach ($data['assigned_clinics'] as $clinic_id) {
                            $insert_stmt->execute([$staff_id, $clinic_id]);
                        }
                    }
                }
            }
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