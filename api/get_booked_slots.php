<?php
header('Content-Type: application/json');

// Include database configuration
require_once __DIR__ . '/../config/database.php';

// Validate required parameters
if (!isset($_GET['doctor_id']) || !isset($_GET['clinic_id']) || !isset($_GET['date'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get booked slots for the specified doctor, clinic, and date
    $stmt = $conn->prepare("
        SELECT time, status
        FROM appointments
        WHERE doctor_id = ?
        AND clinic_id = ?
        AND date = ?
        AND status != 'cancelled'
    ");
    
    $stmt->execute([
        $_GET['doctor_id'],
        $_GET['clinic_id'],
        $_GET['date']
    ]);
    
    $booked_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert to simple array of times
    $booked_times = array_column($booked_slots, 'time');

    // Debug information
    error_log("Booked slots for doctor_id: {$_GET['doctor_id']}, clinic_id: {$_GET['clinic_id']}, date: {$_GET['date']}");
    error_log("Booked slots: " . print_r($booked_times, true));

    echo json_encode([
        'success' => true,
        'booked_slots' => $booked_times,
        'raw_data' => $booked_slots // Include raw data for debugging
    ]);

} catch (Exception $e) {
    error_log("Error in get_booked_slots.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching booked slots'
    ]);
} 