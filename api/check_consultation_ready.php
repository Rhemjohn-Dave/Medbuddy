<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get appointment ID
$appointment_id = isset($_GET['appointment_id']) ? $_GET['appointment_id'] : null;

// Debug input
error_log("Raw appointment_id from request: " . print_r($_GET['appointment_id'] ?? 'not set', true));

// Ensure it's properly formatted
if ($appointment_id !== null) {
    // Try to sanitize the ID - make sure it's a valid integer
    $appointment_id = filter_var($appointment_id, FILTER_SANITIZE_NUMBER_INT);
    error_log("Sanitized appointment_id: " . $appointment_id);
}

if (!$appointment_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Appointment ID is required']);
    exit();
}

try {
    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // First check if the appointment exists at all
    $query = "SELECT * FROM appointments WHERE id = :appointment_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':appointment_id', $appointment_id);
    $stmt->execute();
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug log - if no appointment found, log SQL and parameters
    if (!$appointment) {
        error_log("Appointment not found for ID: " . $appointment_id);
        error_log("SQL Query: " . $query);
        try {
            // Try to determine if the appointment exists with a different id format
            $query_all = "SELECT id FROM appointments LIMIT 10";
            $stmt_all = $db->prepare($query_all);
            $stmt_all->execute();
            $sample_ids = $stmt_all->fetchAll(PDO::FETCH_COLUMN);
            error_log("Sample appointment IDs: " . implode(", ", $sample_ids));
        } catch (Exception $e) {
            error_log("Error fetching sample IDs: " . $e->getMessage());
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Appointment not found',
            'appointment_id' => $appointment_id,
            'debug' => 'No appointment with this ID exists'
        ]);
        exit();
    }
    
    // Now check if vital signs have been recorded
    $query = "SELECT mr.id as medical_record_id 
              FROM medical_records mr 
              WHERE mr.appointment_id = :appointment_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':appointment_id', $appointment_id);
    $stmt->execute();
    $medical_record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get the vitals_recorded flag from the appointments table
    $vitals_recorded = $appointment['vitals_recorded'] ?? 0;
    
    // Check if appointment is scheduled
    if ($appointment['status'] !== 'scheduled') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Appointment is not scheduled',
            'status' => $appointment['status']
        ]);
        exit();
    }
    
    // Check if vital signs have been recorded
    if (!$medical_record && !$vitals_recorded) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Vital signs have not been recorded yet',
            'medical_record_id' => $medical_record['medical_record_id'] ?? null,
            'vitals_recorded' => $vitals_recorded
        ]);
        exit();
    }
    
    // All checks passed, the appointment is ready for consultation
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Patient is ready for consultation',
        'appointment_id' => $appointment_id,
        'medical_record_id' => $medical_record['medical_record_id'] ?? null
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error checking consultation readiness: ' . $e->getMessage()
    ]);
} 