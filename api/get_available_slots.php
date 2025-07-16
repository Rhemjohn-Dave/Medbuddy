<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Check if required parameters are provided
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

    $doctor_id = $_GET['doctor_id'];
    $clinic_id = $_GET['clinic_id'];
    $date = $_GET['date'];
    
    // Get all schedules for this doctor and clinic
    $stmt = $conn->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ? AND clinic_id = ?");
    $stmt->execute([$doctor_id, $clinic_id]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all booked slots for this doctor, clinic, and date
    $stmt = $conn->prepare("SELECT time FROM appointments WHERE doctor_id = ? AND clinic_id = ? AND date = ? AND status != 'cancelled'");
    $stmt->execute([$doctor_id, $clinic_id, $date]);
    $booked_slots = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $day_of_week = date('w', strtotime($date)) + 1; // 1 (Sunday) to 7 (Saturday)
    $available_slots = [];

    foreach ($schedules as $schedule) {
        if ($schedule['day_of_week'] == $day_of_week) {
            $start_time = strtotime($schedule['start_time']);
            $end_time = strtotime($schedule['end_time']);
            $break_start = $schedule['break_start'] ? strtotime($schedule['break_start']) : null;
            $break_end = $schedule['break_end'] ? strtotime($schedule['break_end']) : null;
            for ($time = $start_time; $time < $end_time; $time += 1800) { // 30-minute slots
                if ($break_start && $break_end && $time >= $break_start && $time < $break_end) {
                    continue;
                }
                $time_str = date('H:i:s', $time);
                if (!in_array($time_str, $booked_slots)) {
                    $available_slots[] = [
                        'time' => $time_str,
                        'formatted_time' => date('h:i A', $time)
                    ];
                }
            }
            break;
        }
    }

    echo json_encode([
        'success' => true,
        'available_slots' => $available_slots
    ]);

} catch (Exception $e) {
    error_log("Error in get_available_slots.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching available slots'
    ]);
} 