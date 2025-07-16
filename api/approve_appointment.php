<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get POST data
$appointment_id = isset($_POST['appointment_id']) ? $_POST['appointment_id'] : null;
$status = isset($_POST['status']) ? $_POST['status'] : null;
$notes = isset($_POST['notes']) ? $_POST['notes'] : null;

// Validate required fields
if (!$appointment_id || !$status) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Convert status to match database values
if ($status === 'approved') {
    $status = 'scheduled'; // Use 'scheduled' as the approved status
} else if ($status === 'rejected') {
    $status = 'cancelled'; // Use 'cancelled' as the rejected status
}

// Validate status
if (!in_array($status, ['scheduled', 'cancelled', 'completed', 'no-show'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    // Get appointment details
    $query = "SELECT a.*, u.email as patient_email, p.first_name, p.last_name, 
                     d.first_name as doctor_first_name, d.last_name as doctor_last_name
              FROM appointments a 
              JOIN patients p ON a.patient_id = p.id 
              JOIN users u ON p.user_id = u.id
              JOIN doctors d ON a.doctor_id = d.id 
              WHERE a.id = :appointment_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':appointment_id', $appointment_id);
    $stmt->execute();
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        throw new Exception('Appointment not found');
    }
    
    // Update appointment status
    $query = "UPDATE appointments 
              SET status = :status, 
                  notes = :notes,
                  updated_at = NOW()
              WHERE id = :appointment_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':notes', $notes);
    $stmt->bindParam(':appointment_id', $appointment_id);
    $stmt->execute();
    
    // Send email notification to patient
    $to = $appointment['patient_email'];
    $subject = "Appointment " . ucfirst($status);
    
    $message = "Dear " . $appointment['first_name'] . " " . $appointment['last_name'] . ",\n\n";
    $message .= "Your appointment scheduled for " . date('F d, Y', strtotime($appointment['date'])) . " at " . date('h:i A', strtotime($appointment['time'])) . " has been " . $status . ".\n\n";
    
    if ($notes) {
        $message .= "Notes: " . $notes . "\n\n";
    }
    
    if ($status === 'scheduled') {
        $message .= "Please arrive 15 minutes before your scheduled time.\n";
        $message .= "Don't forget to bring any relevant medical records or test results.\n\n";
    }
    
    $message .= "Doctor: Dr. " . $appointment['doctor_first_name'] . " " . $appointment['doctor_last_name'] . "\n";
    $message .= "Date: " . date('F d, Y', strtotime($appointment['date'])) . "\n";
    $message .= "Time: " . date('h:i A', strtotime($appointment['time'])) . "\n\n";
    
    $message .= "Thank you,\n";
    $message .= "MedBuddy Team";
    
    $headers = "From: noreply@medbuddy.com\r\n";
    $headers .= "Reply-To: support@medbuddy.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    $email_sent = @mail($to, $subject, $message, $headers);
    
    // Commit transaction
    $db->commit();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Appointment ' . $status . ' successfully' . ($email_sent ? '' : ' (Email notification could not be sent)')
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error updating appointment: ' . $e->getMessage()
    ]);
} 