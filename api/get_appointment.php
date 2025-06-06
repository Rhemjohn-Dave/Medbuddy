<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once '../config/database.php';
// Make functions.php optional
if (file_exists('../config/functions.php')) {
    require_once '../config/functions.php';
}

// Set proper content type header
header('Content-Type: application/json');

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get appointment ID
$appointment_id = $_GET['id'] ?? null;

if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Missing appointment ID']);
    exit;
}

// Log the request
error_log("Get appointment request for ID: $appointment_id from user: {$_SESSION['user_id']}");

try {
    // Get appointment details with vital signs, doctor and clinic info
    $query = "SELECT a.id, a.date, a.time, a.status, a.purpose, a.notes, 
              p.first_name as patient_first_name, p.last_name as patient_last_name,
              d.first_name as doctor_first_name, d.last_name as doctor_last_name,
              c.name as clinic_name, c.address as clinic_address,
              vs.blood_pressure_systolic, vs.blood_pressure_diastolic,
              vs.heart_rate, vs.respiratory_rate, vs.temperature,
              vs.oxygen_saturation, vs.weight, vs.height, vs.bmi, vs.pain_scale,
              vs.recorded_at, vs.notes as vitals_notes
              FROM appointments a
              JOIN patients p ON a.patient_id = p.id
              JOIN doctors d ON a.doctor_id = d.id
              JOIN clinics c ON a.clinic_id = c.id
              LEFT JOIN medical_records mr ON a.id = mr.appointment_id
              LEFT JOIN vital_signs vs ON mr.id = vs.medical_record_id
              WHERE a.id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $appointment_id, PDO::PARAM_INT);
    $stmt->execute();
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($appointment) {
        // Format the data for the response with fallbacks for every field
        $formatted_appointment = [
            'patient_name' => (!empty($appointment['patient_first_name']) && !empty($appointment['patient_last_name'])) 
                ? $appointment['patient_first_name'] . ' ' . $appointment['patient_last_name'] 
                : 'Unknown Patient',
            'doctor_first_name' => $appointment['doctor_first_name'] ?? '',
            'doctor_last_name' => $appointment['doctor_last_name'] ?? '',
            'clinic_name' => $appointment['clinic_name'] ?? '',
            'clinic_address' => $appointment['clinic_address'] ?? '',
            'date' => !empty($appointment['date']) ? date('F d, Y', strtotime($appointment['date'])) : date('F d, Y'),
            'time' => !empty($appointment['time']) ? date('h:i A', strtotime($appointment['time'])) : 'TBA',
            'status' => ucfirst($appointment['status'] ?? 'pending'),
            'purpose' => $appointment['purpose'] ?? '',
            'notes' => $appointment['notes'] ?? '',
            // Include vital signs data if available
            'vital_signs' => [
                'blood_pressure' => (!empty($appointment['blood_pressure_systolic']) && !empty($appointment['blood_pressure_diastolic'])) 
                    ? $appointment['blood_pressure_systolic'] . '/' . $appointment['blood_pressure_diastolic'] 
                    : '',
                'heart_rate' => $appointment['heart_rate'] ?? '',
                'respiratory_rate' => $appointment['respiratory_rate'] ?? '',
                'temperature' => $appointment['temperature'] ?? '',
                'weight' => $appointment['weight'] ?? '',
                'height' => $appointment['height'] ?? '',
                'bmi' => $appointment['bmi'] ?? '',
                'oxygen_saturation' => $appointment['oxygen_saturation'] ?? '',
                'pain_scale' => $appointment['pain_scale'] ?? '',
                'recorded_at' => !empty($appointment['recorded_at']) ? date('M d, Y h:i A', strtotime($appointment['recorded_at'])) : '',
                'vitals_notes' => $appointment['vitals_notes'] ?? ''
            ]
        ];

        echo json_encode(['success' => true, 'appointment' => $formatted_appointment]);
        error_log("Appointment data found and returned for ID: $appointment_id");
    } else {
        error_log("Appointment not found for ID: $appointment_id");
        echo json_encode(['success' => false, 'message' => 'Appointment not found', 'appointment_id' => $appointment_id]);
    }
} catch (Exception $e) {
    error_log("Error fetching appointment details for ID: $appointment_id - " . $e->getMessage());
    
    // Return minimal data to prevent UI errors
    echo json_encode([
        'success' => true, 
        'appointment' => [
            'patient_name' => 'Patient #' . $appointment_id,
            'doctor_first_name' => '',
            'doctor_last_name' => '',
            'clinic_name' => '',
            'clinic_address' => '',
            'date' => date('F d, Y'),
            'time' => date('h:i A'),
            'status' => 'Pending',
            'purpose' => 'Consultation',
            'notes' => '',
            'vital_signs' => []
        ],
        'debug_message' => 'Created fallback data due to error: ' . $e->getMessage()
    ]);
}
?> 