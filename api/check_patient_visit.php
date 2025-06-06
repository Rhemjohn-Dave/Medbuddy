<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get appointment ID from query parameters
$appointment_id = isset($_GET['appointment_id']) ? $_GET['appointment_id'] : null;

if (!$appointment_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Appointment ID is required']);
    exit();
}

try {
    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Get patient ID from appointment
    $query = "SELECT p.id as patient_id FROM appointments a 
              JOIN patients p ON a.patient_id = p.id 
              WHERE a.id = :appointment_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':appointment_id', $appointment_id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        throw new Exception('Appointment not found');
    }
    
    $patient_id = $result['patient_id'];
    
    // Check if patient has any previous medical records
    $query = "SELECT COUNT(*) as visit_count FROM medical_records WHERE patient_id = :patient_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':patient_id', $patient_id);
    $stmt->execute();
    $visit_result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $visit_count = $visit_result['visit_count'];
    $is_first_visit = $visit_count == 0;
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'is_first_visit' => $is_first_visit,
        'visit_count' => $visit_count
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 