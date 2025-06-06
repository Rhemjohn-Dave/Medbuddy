<?php
// DEBUGGING ONLY - Remove in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once '../config/database.php';

// Set content type
header('Content-Type: application/json');

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Hard-code appointment ID for testing
$appointment_id = 35; // Use your problematic appointment ID

try {
    // Query appointment with minimal fields
    $query = "SELECT a.id, a.date, a.time, a.status, a.purpose, a.notes, 
              p.first_name, p.last_name, p.contact_number
              FROM appointments a
              JOIN patients p ON a.patient_id = p.id
              WHERE a.id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $appointment_id);
    $stmt->execute();
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    // Debug output
    echo json_encode([
        'success' => ($appointment !== false),
        'appointment_id' => $appointment_id,
        'found' => ($appointment !== false),
        'data' => $appointment,
        'raw_sql' => str_replace(':id', $appointment_id, $query)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
?> 