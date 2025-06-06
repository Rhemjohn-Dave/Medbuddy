<?php
header("Content-Type: application/json; charset=UTF-8");

error_log("Testing database connection...");

require_once '../../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    error_log("Database connection successful");

    // Test users table
    $stmt = $conn->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() === 0) {
        throw new Exception("Users table does not exist");
    }
    error_log("Users table exists");

    // Test doctors table
    $stmt = $conn->query("SHOW TABLES LIKE 'doctors'");
    if ($stmt->rowCount() === 0) {
        throw new Exception("Doctors table does not exist");
    }
    error_log("Doctors table exists");

    // Test appointments table
    $stmt = $conn->query("SHOW TABLES LIKE 'appointments'");
    if ($stmt->rowCount() === 0) {
        throw new Exception("Appointments table does not exist");
    }
    error_log("Appointments table exists");

    // Test patients table
    $stmt = $conn->query("SHOW TABLES LIKE 'patients'");
    if ($stmt->rowCount() === 0) {
        throw new Exception("Patients table does not exist");
    }
    error_log("Patients table exists");

    // Get table structures
    $tables = ['users', 'doctors', 'appointments', 'patients'];
    $structures = [];
    
    foreach ($tables as $table) {
        $stmt = $conn->query("DESCRIBE $table");
        $structures[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Database connection and tables verified',
        'structures' => $structures
    ]);

} catch (Exception $e) {
    error_log("Database test error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 