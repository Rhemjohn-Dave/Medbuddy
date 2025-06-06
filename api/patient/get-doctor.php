<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once '../../config/database.php';

// Set proper content type header
header('Content-Type: application/json');

// Check if user is logged in and has patient access
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get doctor ID from request
$doctor_id = $_GET['id'] ?? null;

if (!$doctor_id) {
    echo json_encode(['success' => false, 'message' => 'Missing doctor ID']);
    exit;
}

// Log the request
error_log("Get doctor request for ID: $doctor_id from patient user: {$_SESSION['user_id']}");

try {
    // Fetch doctor details
    $query = "SELECT d.id, d.first_name, d.middle_name, d.last_name, s.name as specialization_name, 
                     u.email, d.contact_number as phone, d.status,
                     GROUP_CONCAT(DISTINCT c.name SEPARATOR '||') as clinic_names
              FROM doctors d
              LEFT JOIN specializations s ON d.specialization_id = s.id
              INNER JOIN users u ON d.user_id = u.id
              LEFT JOIN doctor_clinics dc ON d.id = dc.doctor_id
              LEFT JOIN clinics c ON dc.clinic_id = c.id
              WHERE d.id = :id AND d.status = 'active'
              GROUP BY d.id, d.first_name, d.middle_name, d.last_name, s.name, u.email, d.contact_number, d.status";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $doctor_id, PDO::PARAM_INT);
    $stmt->execute();
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($doctor) {
        // Get doctor's schedule
        $schedule_query = "SELECT day_of_week, start_time, end_time, break_start, break_end
                           FROM doctor_schedules
                           WHERE doctor_id = :doctor_id
                           ORDER BY day_of_week, start_time";
        $schedule_stmt = $db->prepare($schedule_query);
        $schedule_stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
        $schedule_stmt->execute();
        $schedule = $schedule_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the schedule
        $formatted_schedule = [];
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        foreach ($schedule as $entry) {
            $day_name = $days[$entry['day_of_week'] - 1]; // Adjust for 1-based day_of_week
            $formatted_schedule[] = [
                'day' => $day_name,
                'start' => date('h:i A', strtotime($entry['start_time'])),
                'end' => date('h:i A', strtotime($entry['end_time'])),
                'break_start' => $entry['break_start'] ? date('h:i A', strtotime($entry['break_start'])) : null,
                'break_end' => $entry['break_end'] ? date('h:i A', strtotime($entry['break_end'])) : null,
            ];
        }

        // Format the data for the response
        $formatted_doctor = [
            'id' => $doctor['id'],
            'first_name' => $doctor['first_name'] ?? '',
            'middle_name' => $doctor['middle_name'] ?? '',
            'last_name' => $doctor['last_name'] ?? '',
            'email' => $doctor['email'] ?? '',
            'phone' => $doctor['phone'] ?? '',
            'status' => ucfirst($doctor['status'] ?? 'inactive'),
            'specialization_name' => $doctor['specialization_name'] ?? 'N/A',
            'clinics' => $doctor['clinic_names'] ? explode('||', $doctor['clinic_names']) : [],
            'schedule' => $formatted_schedule
        ];

        echo json_encode(['success' => true, 'doctor' => $formatted_doctor]);
        error_log("Doctor data found and returned for ID: $doctor_id");
    } else {
        error_log("Doctor not found for ID: $doctor_id");
        echo json_encode(['success' => false, 'message' => 'Doctor not found', 'doctor_id' => $doctor_id]);
    }
} catch (Exception $e) {
    error_log("Error fetching doctor details for ID: $doctor_id - " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while fetching doctor details.',
        'debug_message' => $e->getMessage()
    ]);
}
?> 