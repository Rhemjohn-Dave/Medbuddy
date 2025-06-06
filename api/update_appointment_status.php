<?php
require_once '../config/database.php';
require_once '../config/functions.php';

// Check if user is logged in and is staff
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$appointment_id = $_POST['appointment_id'] ?? null;
$status = $_POST['status'] ?? null;
$notes = $_POST['notes'] ?? '';

if (!$appointment_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    // Begin transaction
    $conn->beginTransaction();

    // Update appointment status
    $update_query = "UPDATE appointments SET status = :status, staff_notes = :notes, updated_at = NOW() WHERE id = :id";
    $stmt = $conn->prepare($update_query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':notes', $notes);
    $stmt->bindParam(':id', $appointment_id);
    $stmt->execute();

    // Get appointment details for notification
    $appointment_query = "SELECT a.*, p.email as patient_email, p.first_name as patient_first_name, p.last_name as patient_last_name 
                         FROM appointments a 
                         JOIN patients p ON a.patient_id = p.id 
                         WHERE a.id = :id";
    $stmt = $conn->prepare($appointment_query);
    $stmt->bindParam(':id', $appointment_id);
    $stmt->execute();
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    // Send notification to patient
    if ($appointment) {
        $subject = "Appointment " . ucfirst($status);
        $message = "Dear " . $appointment['patient_first_name'] . ",\n\n";
        $message .= "Your appointment scheduled for " . date('F d, Y h:i A', strtotime($appointment['appointment_date'])) . " has been " . $status . ".\n\n";
        if ($notes) {
            $message .= "Notes: " . $notes . "\n\n";
        }
        $message .= "Thank you,\nMedBuddy Team";

        // Send email notification
        sendEmail($appointment['patient_email'], $subject, $message);
    }

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Appointment status updated successfully']);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    error_log("Error updating appointment status: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating the appointment status']);
}
?> 