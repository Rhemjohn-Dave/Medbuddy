<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';
require_once '../../config/config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') {
    echo json_encode([
        'success' => false, 
        'error' => 'Unauthorized access',
        'debug' => [
            'session' => $_SESSION,
            'user_id' => $_SESSION['user_id'] ?? 'not set',
            'role' => $_SESSION['role'] ?? 'not set'
        ]
    ]);
    exit();
}

// Get record ID from request
$record_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$record_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid record ID']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // First, get the patient ID from the patients table
    $patient_query = "SELECT id FROM patients WHERE user_id = :user_id";
    $patient_stmt = $db->prepare($patient_query);
    $patient_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $patient_stmt->execute();
    $patient = $patient_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        echo json_encode([
            'success' => false,
            'error' => 'Patient record not found',
            'debug' => [
                'user_id' => $_SESSION['user_id']
            ]
        ]);
        exit();
    }

    // Get medical record with related information
    $query = "SELECT 
        mr.id,
        mr.patient_id,
        mr.doctor_id,
        mr.appointment_id,
        mr.chief_complaint,
        mr.notes,
        mr.created_at,
        d.first_name as doctor_first_name, 
        d.last_name as doctor_last_name,
        a.date as appointment_date,
        a.purpose,
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
        GROUP_CONCAT(DISTINCT pr.prescription_text SEPARATOR '||') as prescriptions,
        GROUP_CONCAT(DISTINCT di.diagnosis SEPARATOR '||') as diagnoses
        FROM medical_records mr
        LEFT JOIN doctors d ON mr.doctor_id = d.id
        LEFT JOIN appointments a ON mr.appointment_id = a.id
        LEFT JOIN vital_signs vs ON mr.id = vs.medical_record_id
        LEFT JOIN prescriptions pr ON mr.id = pr.medical_record_id
        LEFT JOIN diagnoses di ON mr.id = di.medical_record_id
        WHERE mr.id = :record_id 
        AND mr.patient_id = :patient_id
        GROUP BY mr.id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':record_id', $record_id);
    $stmt->bindParam(':patient_id', $patient['id']);
    $stmt->execute();

    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($record) {
        // Format the data before sending
        $record['created_at'] = date('Y-m-d H:i:s', strtotime($record['created_at']));
        $record['appointment_date'] = $record['appointment_date'] ? date('Y-m-d H:i:s', strtotime($record['appointment_date'])) : null;
        
        // Ensure arrays are properly formatted
        $record['prescriptions'] = $record['prescriptions'] ? explode('||', $record['prescriptions']) : [];
        $record['diagnoses'] = $record['diagnoses'] ? explode('||', $record['diagnoses']) : [];

        echo json_encode([
            'success' => true,
            'record' => $record
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Record not found',
            'debug' => [
                'record_id' => $record_id,
                'patient_id' => $patient['id'],
                'user_id' => $_SESSION['user_id']
            ]
        ]);
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'debug' => [
            'message' => $e->getMessage(),
            'code' => $e->getCode()
        ]
    ]);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred',
        'debug' => [
            'message' => $e->getMessage()
        ]
    ]);
} 