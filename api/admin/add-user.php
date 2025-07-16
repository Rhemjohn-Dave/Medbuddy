<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['email', 'password', 'role', 'first_name', 'last_name'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit();
    }
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Start transaction
    $conn->beginTransaction();
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->rowCount() > 0) {
        throw new Exception('Email already exists');
    }
    
    // Hash password
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Insert into users table
    $stmt = $conn->prepare("
        INSERT INTO users (email, password, role, approval_status, created_at, updated_at)
        VALUES (?, ?, ?, 'pending', NOW(), NOW())
    ");
    $stmt->execute([
        $data['email'],
        $hashed_password,
        $data['role']
    ]);
    
    $user_id = $conn->lastInsertId();
    
    // Insert role-specific data
    if ($data['role'] === 'doctor') {
        // Handle specialization
        $specialization_id = null;
        if (!empty($data['specialization'])) {
            // Check if specialization exists
            $spec_stmt = $conn->prepare("SELECT id FROM specializations WHERE name = ?");
            $spec_stmt->execute([$data['specialization']]);
            $spec_result = $spec_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($spec_result) {
                $specialization_id = $spec_result['id'];
            } else {
                // Create new specialization
                $insert_spec_stmt = $conn->prepare("INSERT INTO specializations (name) VALUES (?)");
                $insert_spec_stmt->execute([$data['specialization']]);
                $specialization_id = $conn->lastInsertId();
            }
        }
        
        $stmt = $conn->prepare("
            INSERT INTO doctors (user_id, first_name, middle_name, last_name, specialization_id, license_number, contact_number, address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $data['first_name'],
            $data['middle_name'] ?? null,
            $data['last_name'],
            $specialization_id,
            $data['license_number'] ?? null,
            $data['contact_number'] ?? null,
            $data['address'] ?? null
        ]);
    } elseif ($data['role'] === 'patient') {
        $stmt = $conn->prepare("
            INSERT INTO patients (user_id, first_name, middle_name, last_name, date_of_birth, gender, address)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $data['first_name'],
            $data['middle_name'] ?? null,
            $data['last_name'],
            $data['date_of_birth'] ?? null,
            $data['gender'] ?? null,
            $data['address'] ?? null
        ]);
    } elseif ($data['role'] === 'staff') {
        $stmt = $conn->prepare("
            INSERT INTO staff (user_id, first_name, middle_name, last_name, contact_number, address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $data['first_name'],
            $data['middle_name'] ?? null,
            $data['last_name'],
            $data['contact_number'] ?? null,
            $data['address'] ?? null
        ]);
    }
    
    // Commit transaction
    $conn->commit();
    
    // Send success response
    echo json_encode([
        'success' => true,
        'message' => 'User added successfully',
        'user_id' => $user_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to add user',
        'message' => $e->getMessage()
    ]);
}
?> 