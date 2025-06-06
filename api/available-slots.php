<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Check required parameters
$required = ['doctor_id', 'clinic_id', 'date'];
foreach ($required as $param) {
    if (!isset($_GET[$param])) {
        echo json_encode(['success' => false, 'message' => "Missing required parameter: $param"]);
        exit();
    }
}

$doctorId = $_GET['doctor_id'];
$clinicId = $_GET['clinic_id'];
$date = $_GET['date'];

// Get the day of week in database format (1=Sunday to 7=Saturday)
$dayOfWeek = date('N', strtotime($date)); // PHP's date('N') returns 1=Monday to 7=Sunday
$dayOfWeek = $dayOfWeek == 7 ? 1 : $dayOfWeek; // Convert to database format (1=Sunday to 7=Saturday)

// Debug information
$debug = [
    'doctor_id' => $doctorId,
    'clinic_id' => $clinicId,
    'date' => $date,
    'php_day' => date('N', strtotime($date)), // Original PHP day (1=Monday to 7=Sunday)
    'db_day' => $dayOfWeek, // Converted to DB format (1=Sunday to 7=Saturday)
    'day_name' => date('l', strtotime($date)), // Day name for debugging
    'date_object' => date('Y-m-d', strtotime($date))
];

try {
    // First, check if the doctor has any schedules at all
    $stmt = $conn->prepare("
        SELECT * FROM doctor_schedules 
        WHERE doctor_id = ? AND clinic_id = ?
    ");
    $stmt->execute([$doctorId, $clinicId]);
    $allSchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debug['all_schedules'] = $allSchedules;

    // Get doctor's schedule for the day
    $stmt = $conn->prepare("
        SELECT start_time, end_time, break_start, break_end, max_appointments_per_slot
        FROM doctor_schedules
        WHERE doctor_id = ? 
        AND clinic_id = ? 
        AND day_of_week = ?
    ");
    
    $stmt->execute([$doctorId, $clinicId, $dayOfWeek]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Add schedule to debug info
    $debug['schedule'] = $schedule;
    $debug['query_params'] = [
        'doctor_id' => $doctorId,
        'clinic_id' => $clinicId,
        'day_of_week' => $dayOfWeek
    ];

    if (!$schedule) {
        echo json_encode([
            'success' => true, 
            'slots' => [],
            'debug' => $debug,
            'message' => 'No schedule found for this day'
        ]);
        exit();
    }

    // Validate and fix time format
    function fixTime($time) {
        if (!$time) return null;
        $parts = explode(':', $time);
        if (count($parts) !== 3) return null;
        
        $hours = (int)$parts[0];
        $minutes = (int)$parts[1];
        $seconds = (int)$parts[2];
        
        // Ensure hours are in 24-hour format
        if ($hours < 0 || $hours > 23) return null;
        if ($minutes < 0 || $minutes > 59) return null;
        if ($seconds < 0 || $seconds > 59) return null;
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    // Fix and validate times
    $startTime = fixTime($schedule['start_time']);
    $endTime = fixTime($schedule['end_time']);
    $breakStart = fixTime($schedule['break_start']);
    $breakEnd = fixTime($schedule['break_end']);

    // Validate time ranges
    if (!$startTime || !$endTime) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid schedule times',
            'debug' => $debug
        ]);
        exit();
    }

    // Convert to DateTime objects
    $startDateTime = new DateTime($startTime);
    $endDateTime = new DateTime($endTime);
    
    // If end time is before start time, assume it's the next day
    if ($endDateTime < $startDateTime) {
        $endDateTime->modify('+1 day');
    }

    $breakStartDateTime = $breakStart ? new DateTime($breakStart) : null;
    $breakEndDateTime = $breakEnd ? new DateTime($breakEnd) : null;

    // If break end is before break start, assume it's the next day
    if ($breakStartDateTime && $breakEndDateTime && $breakEndDateTime < $breakStartDateTime) {
        $breakEndDateTime->modify('+1 day');
    }

    $debug['time_calculations'] = [
        'start_time' => $startDateTime->format('H:i:s'),
        'end_time' => $endDateTime->format('H:i:s'),
        'break_start' => $breakStartDateTime ? $breakStartDateTime->format('H:i:s') : null,
        'break_end' => $breakEndDateTime ? $breakEndDateTime->format('H:i:s') : null,
        'max_appointments' => $schedule['max_appointments_per_slot'] ?? 1
    ];

    // Get booked appointments for the day
    $stmt = $conn->prepare("
        SELECT time, COUNT(*) as count
        FROM appointments
        WHERE doctor_id = ? 
        AND clinic_id = ? 
        AND date = ? 
        AND status != 'cancelled'
        GROUP BY time
    ");
    
    $stmt->execute([$doctorId, $clinicId, $date]);
    $bookedSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a map of booked slots with their counts
    $bookedSlotsMap = [];
    foreach ($bookedSlots as $slot) {
        $bookedSlotsMap[$slot['time']] = $slot['count'];
    }
    
    $debug['booked_slots'] = $bookedSlotsMap;

    // Generate available time slots
    $slots = [];
    $currentTime = clone $startDateTime;
    $interval = new DateInterval('PT30M'); // 30 minutes interval
    $maxAppointments = $schedule['max_appointments_per_slot'] ?? 1;

    while ($currentTime < $endDateTime) {
        // Skip break time
        if ($breakStartDateTime && $breakEndDateTime && 
            $currentTime >= $breakStartDateTime && $currentTime < $breakEndDateTime) {
            $currentTime = clone $breakEndDateTime;
            continue;
        }

        $timeSlot = $currentTime->format('H:i:s');
        $bookedCount = isset($bookedSlotsMap[$timeSlot]) ? $bookedSlotsMap[$timeSlot] : 0;

        // Add slot if it hasn't reached max appointments
        if ($bookedCount < $maxAppointments) {
            $slots[] = $timeSlot;
        }

        $currentTime->add($interval);
    }

    $debug['available_slots'] = $slots;

    echo json_encode([
        'success' => true, 
        'slots' => $slots,
        'debug' => $debug
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch available slots',
        'debug' => $debug,
        'error' => $e->getMessage()
    ]);
} 