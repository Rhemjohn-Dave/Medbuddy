<?php
session_start();
require_once '../../../config/database.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

try {
    // Get recent appointments with patient and doctor details
    $stmt = $conn->prepare("
        SELECT 
            a.id,
            a.appointment_date,
            a.status,
            p.first_name as patient_first_name,
            p.last_name as patient_last_name,
            d.first_name as doctor_first_name,
            d.last_name as doctor_last_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN doctors d ON a.doctor_id = d.id
        ORDER BY a.appointment_date DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the data for display
    $activities = array_map(function($appointment) {
        return [
            'id' => $appointment['id'],
            'date' => date('M d, Y h:i A', strtotime($appointment['appointment_date'])),
            'patient' => $appointment['patient_first_name'] . ' ' . $appointment['patient_last_name'],
            'doctor' => $appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name'],
            'status' => ucfirst($appointment['status'])
        ];
    }, $recent_appointments);

    // Return the activities
    echo json_encode(['activities' => $activities]);

} catch (PDOException $e) {
    error_log("Recent activities error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch recent activities']);
} 