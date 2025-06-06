<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once '../config/database.php';

// Set proper content type header
header('Content-Type: application/json');

// Check if user is logged in and has patient access
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get medical record ID
$medical_record_id = $_GET['id'] ?? null;

if (!$medical_record_id) {
    echo json_encode(['success' => false, 'message' => 'Missing medical record ID']);
    exit;
}

// Get patient ID from user ID
try {
    $stmt = $db->prepare("SELECT id FROM patients WHERE user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        echo json_encode(['success' => false, 'message' => 'Patient not found.']);
        exit;
    }
    $patient_id = $patient['id'];

} catch (PDOException $e) {
    error_log("Error fetching patient ID: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error.']);
    exit;
}


// Log the request
error_log("Get medical record request for ID: $medical_record_id from patient user: {$_SESSION['user_id']}");

try {
    // Get medical record details including vital signs, doctor info, and appointment purpose
    $query = "SELECT mr.*,
                     d.first_name as doctor_first_name,
                     d.last_name as doctor_last_name,
                     vs.blood_pressure_systolic,
                     vs.blood_pressure_diastolic,
                     vs.heart_rate,
                     vs.respiratory_rate,
                     vs.temperature,
                     vs.oxygen_saturation,
                     vs.weight,
                     vs.height,
                     vs.bmi,
                     vs.pain_scale,
                     vs.recorded_at as vitals_recorded_at,
                     vs.notes as vitals_notes,
                     a.purpose as appointment_purpose
              FROM medical_records mr
              JOIN doctors d ON mr.doctor_id = d.id
              LEFT JOIN vital_signs vs ON mr.id = vs.medical_record_id
              LEFT JOIN appointments a ON mr.appointment_id = a.id
              WHERE mr.id = :id AND mr.patient_id = :patient_id LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $medical_record_id, PDO::PARAM_INT);
    $stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
    $stmt->execute();
    $medicalRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($medicalRecord) {
        // Format the data for the response
        $formatted_record = [
            'id' => $medicalRecord['id'],
            'doctor_first_name' => $medicalRecord['doctor_first_name'] ?? '',
            'doctor_last_name' => $medicalRecord['doctor_last_name'] ?? '',
            'record_date' => $medicalRecord['record_date'] ?? '',
            'record_time' => $medicalRecord['record_time'] ?? '',
            'record_type' => $medicalRecord['record_type'] ?? '',
            'appointment_purpose' => $medicalRecord['appointment_purpose'] ?? 'No purpose specified',
            'chief_complaint' => $medicalRecord['chief_complaint'] ?? '',
            'present_illness' => $medicalRecord['present_illness'] ?? '',
            'past_medical_history' => $medicalRecord['past_medical_history'] ?? '',
            'family_history' => $medicalRecord['family_history'] ?? '',
            'social_history' => $medicalRecord['social_history'] ?? '',
            'allergies' => $medicalRecord['allergies'] ?? '',
            'medications' => $medicalRecord['medications'] ?? '',
            'physical_examination' => $medicalRecord['physical_examination'] ?? '',
            'diagnosis' => $medicalRecord['diagnosis'] ?? '',
            'treatment_plan' => $medicalRecord['treatment_plan'] ?? '',
            'prescription' => $medicalRecord['prescription'] ?? '',
            'notes' => $medicalRecord['notes'] ?? '',
            'created_at' => $medicalRecord['created_at'] ?? '',
            'updated_at' => $medicalRecord['updated_at'] ?? '',
            'vital_signs' => [
                'blood_pressure_systolic' => $medicalRecord['blood_pressure_systolic'] ?? null,
                'blood_pressure_diastolic' => $medicalRecord['blood_pressure_diastolic'] ?? null,
                'heart_rate' => $medicalRecord['heart_rate'] ?? null,
                'respiratory_rate' => $medicalRecord['respiratory_rate'] ?? null,
                'temperature' => $medicalRecord['temperature'] ?? null,
                'oxygen_saturation' => $medicalRecord['oxygen_saturation'] ?? null,
                'weight' => $medicalRecord['weight'] ?? null,
                'height' => $medicalRecord['height'] ?? null,
                'bmi' => $medicalRecord['bmi'] ?? null,
                'pain_scale' => $medicalRecord['pain_scale'] ?? null,
                'recorded_at' => $medicalRecord['vitals_recorded_at'] ?? null,
                'notes' => $medicalRecord['vitals_notes'] ?? null,
            ]
        ];

        echo json_encode(['success' => true, 'medicalRecord' => $formatted_record]);
        error_log("Medical record data found and returned for ID: $medical_record_id");
    } else {
        error_log("Medical record not found for ID: $medical_record_id or does not belong to patient user: {$_SESSION['user_id']}");
        echo json_encode(['success' => false, 'message' => 'Medical record not found or unauthorized access.', 'medical_record_id' => $medical_record_id]);
    }
} catch (Exception $e) {
    error_log("Error fetching medical record details for ID: $medical_record_id - " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while fetching medical record details.',
        'debug_message' => $e->getMessage()
    ]);
}
?> 