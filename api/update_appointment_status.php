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

// If marking as no-show, check if 5 minutes have passed since appointment time
if ($status === 'no-show') {
    $check_query = "SELECT appointment_date, appointment_time, status FROM appointments WHERE id = :id";
    $stmt = $conn->prepare($check_query);
    $stmt->bindParam(':id', $appointment_id);
    $stmt->execute();
    $appt = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$appt) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit;
    }
    if ($appt['status'] !== 'scheduled') {
        echo json_encode(['success' => false, 'message' => 'Only scheduled appointments can be marked as no-show']);
        exit;
    }
    $appt_datetime = strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time']);
    $now = time();
    if ($now < $appt_datetime + 5 * 60) {
        echo json_encode(['success' => false, 'message' => 'You can only mark as no-show 5 minutes after the scheduled time.']);
        exit;
    }
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

// Ensure JSON output and no accidental HTML
ob_start();
header('Content-Type: application/json');

if (ob_get_length()) ob_end_clean();
echo json_encode(['success' => false, 'message' => 'Unknown error occurred.']);
?> 