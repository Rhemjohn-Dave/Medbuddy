<?php
// Database connection parameters
$host = "localhost";
$dbname = "medbuddy";
$username = "root";
$password = "";

try {
    // Create connection to the database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Disable emulation of prepared statements
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Set charset to utf8mb4
    $pdo->exec("SET NAMES utf8mb4");
    
    // Create messages table
    $sql = "CREATE TABLE IF NOT EXISTS messages (
        id INT PRIMARY KEY AUTO_INCREMENT,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id),
        FOREIGN KEY (receiver_id) REFERENCES users(id)
    )";
    $pdo->exec($sql);
    
} catch(PDOException $e) {
    // Log the error and display a user-friendly message
    error_log("Connection failed: " . $e->getMessage());
    die("Could not connect to the database. Please try again later.");
}

// Function to get the database connection
function getDB() {
    global $pdo;
    return $pdo;
}
?> 