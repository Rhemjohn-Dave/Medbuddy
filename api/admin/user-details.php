<?php
// Disable error display to prevent HTML errors from breaking JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../../logs/php-error.log');

// Set proper JSON header
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if user ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user ID']);
    exit();
}

try {
    require_once '../../config/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get user details with role-specific information
    $sql = "SELECT u.*, 
            CASE 
                WHEN u.role = 'doctor' THEN d.first_name 
                WHEN u.role = 'patient' THEN p.first_name 
                WHEN u.role = 'staff' THEN st.first_name
                ELSE NULL 
            END as first_name,
            CASE 
                WHEN u.role = 'doctor' THEN d.middle_name 
                WHEN u.role = 'patient' THEN p.middle_name 
                WHEN u.role = 'staff' THEN st.middle_name
                ELSE NULL 
            END as middle_name,
            CASE 
                WHEN u.role = 'doctor' THEN d.last_name 
                WHEN u.role = 'patient' THEN p.last_name 
                WHEN u.role = 'staff' THEN st.last_name
                ELSE NULL 
            END as last_name,
            CASE 
                WHEN u.role = 'doctor' THEN s.name 
                ELSE NULL 
            END as specialization,
            CASE 
                WHEN u.role = 'doctor' THEN d.license_number 
                ELSE NULL 
            END as license_number,
            CASE 
                WHEN u.role = 'doctor' THEN d.contact_number 
                ELSE NULL 
            END as contact_number,
            CASE 
                WHEN u.role = 'doctor' THEN d.address 
                ELSE NULL 
            END as doctor_address,
            CASE 
                WHEN u.role = 'patient' THEN p.date_of_birth 
                ELSE NULL 
            END as date_of_birth,
            CASE 
                WHEN u.role = 'patient' THEN p.gender 
                ELSE NULL 
            END as gender,
            CASE 
                WHEN u.role = 'patient' THEN p.address 
                ELSE NULL 
            END as patient_address,
            CASE 
                WHEN u.role = 'staff' THEN st.contact_number 
                ELSE NULL 
            END as staff_contact_number,
            CASE 
                WHEN u.role = 'staff' THEN st.address 
                ELSE NULL 
            END as staff_address,
            CASE 
                WHEN u.role = 'staff' THEN CONCAT(d2.first_name, ' ', d2.last_name)
                ELSE NULL 
            END as assigned_doctor
            FROM users u 
            LEFT JOIN doctors d ON u.id = d.user_id 
            LEFT JOIN specializations s ON d.specialization_id = s.id
            LEFT JOIN patients p ON u.id = p.user_id 
            LEFT JOIN staff st ON u.id = st.user_id 
            LEFT JOIN doctors d2 ON st.assigned_doctor_id = d2.id
            WHERE u.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit();
    }
    
    // Format date fields
    if ($user['date_of_birth']) {
        $user['date_of_birth'] = date('Y-m-d', strtotime($user['date_of_birth']));
    }
    if ($user['created_at']) {
        $user['created_at'] = date('Y-m-d H:i:s', strtotime($user['created_at']));
    }
    if ($user['updated_at']) {
        $user['updated_at'] = date('Y-m-d H:i:s', strtotime($user['updated_at']));
    }
    
    // Remove sensitive information
    unset($user['password']);
    
    echo json_encode($user);
    
} catch (PDOException $e) {
    error_log("Database error in user-details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error occurred',
        'message' => 'Failed to fetch user details'
    ]);
} catch (Exception $e) {
    error_log("Error in user-details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch user details',
        'message' => $e->getMessage()
    ]);
}
?> 