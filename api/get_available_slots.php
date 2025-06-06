<?php
header('Content-Type: application/json');
require_once '../config/database.php';

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
    
    // Get the day of week (1-7, where 1 is Monday)
    $day_of_week = date('N', strtotime($date));

    // Get doctor's schedule for this day
    $schedule_sql = "SELECT * FROM doctor_schedules 
                    WHERE doctor_id = ? 
                    AND clinic_id = ? 
                    AND day_of_week = ?";
    $schedule_stmt = $conn->prepare($schedule_sql);
    $schedule_stmt->execute([$doctor_id, $clinic_id, $day_of_week]);
    $schedule = $schedule_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        echo json_encode([
            'success' => true,
            'slots' => []
        ]);
        exit;
    }

    // Get existing appointments for this date
    $appointments_sql = "SELECT time FROM appointments 
                        WHERE doctor_id = ? 
                        AND clinic_id = ? 
                        AND date = ? 
                        AND status != 'cancelled'";
    $appointments_stmt = $conn->prepare($appointments_sql);
    $appointments_stmt->execute([$doctor_id, $clinic_id, $date]);
    $existing_appointments = $appointments_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Generate available time slots
    $slots = [];
    $start_time = strtotime($schedule['start_time']);
    $end_time = strtotime($schedule['end_time']);
    $break_start = $schedule['break_start'] ? strtotime($schedule['break_start']) : null;
    $break_end = $schedule['break_end'] ? strtotime($schedule['break_end']) : null;

    // Generate 30-minute slots
    for ($time = $start_time; $time < $end_time; $time += 1800) {
        // Skip break time
        if ($break_start && $break_end && $time >= $break_start && $time < $break_end) {
            continue;
        }

        $time_str = date('H:i:s', $time);
        
        // Check if slot is available
        if (!in_array($time_str, $existing_appointments)) {
            $slots[] = [
                'time' => $time_str,
                'formatted_time' => date('h:i A', $time)
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'slots' => $slots
    ]);

} catch (Exception $e) {
    error_log("Error in get_available_slots.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving available slots'
    ]);
} 