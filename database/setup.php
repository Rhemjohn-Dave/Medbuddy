<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers for JSON response
header("Content-Type: application/json; charset=UTF-8");

// Function to send JSON response
function sendJsonResponse($status, $message, $data = null) {
    http_response_code($status);
    $response = ["message" => $message];
    if ($data !== null) {
        $response = array_merge($response, $data);
    }
    echo json_encode($response);
    exit();
}

try {
    // Connect to MySQL without database
    $pdo = new PDO("mysql:host=localhost", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS medbuddy");
    $pdo->exec("USE medbuddy");

    // Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'doctor', 'patient', 'staff') NOT NULL,
        approval_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(["admin@medbuddy.com"]);
    
    if ($stmt->rowCount() == 0) {
        // Create admin user
        $stmt = $pdo->prepare("INSERT INTO users (email, password, role, approval_status) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            "admin@medbuddy.com",
            password_hash("admin123", PASSWORD_DEFAULT),
            "admin",
            "approved"
        ]);
    }

    // Check if test user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(["test@example.com"]);
    
    if ($stmt->rowCount() == 0) {
        // Create test user
        $stmt = $pdo->prepare("INSERT INTO users (email, password, role, approval_status) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            "test@example.com",
            password_hash("test123", PASSWORD_DEFAULT),
            "patient",
            "approved"
        ]);
    }

    sendJsonResponse(200, "Database setup completed successfully");

} catch (Exception $e) {
    error_log("Setup error: " . $e->getMessage());
    sendJsonResponse(500, "Database setup failed: " . $e->getMessage());
}
?> 