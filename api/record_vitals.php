<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get POST data
$appointment_id = isset($_POST['appointment_id']) ? $_POST['appointment_id'] : null;
$systolic = isset($_POST['systolic']) ? $_POST['systolic'] : null;
$diastolic = isset($_POST['diastolic']) ? $_POST['diastolic'] : null;
$temperature = isset($_POST['temperature']) ? $_POST['temperature'] : null;
$pulse_rate = isset($_POST['pulse_rate']) ? $_POST['pulse_rate'] : null;
$respiratory_rate = isset($_POST['respiratory_rate']) ? $_POST['respiratory_rate'] : null;
$oxygen_saturation = isset($_POST['oxygen_saturation']) ? $_POST['oxygen_saturation'] : null;
$weight = isset($_POST['weight']) ? $_POST['weight'] : null;
$height = isset($_POST['height']) ? $_POST['height'] : null;
$pain_scale = isset($_POST['pain_scale']) ? $_POST['pain_scale'] : null;
$notes = isset($_POST['notes']) ? $_POST['notes'] : null;
// Calculate BMI if both height and weight are provided
$bmi = null;
if ($height && $weight && $height > 0) {
    // Convert height to meters if in cm
    $height_m = $height > 3 ? $height/100 : $height;
    $bmi = round($weight / ($height_m * $height_m), 1);
}

// Basic validation for required fields - these are always required
if (!$appointment_id || !$systolic || !$diastolic || !$temperature || !$pulse_rate || !$respiratory_rate || !$oxygen_saturation || !$weight) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Missing required basic vital signs',
        'debug' => [
            'appointment_id' => $appointment_id,
            'systolic' => $systolic,
            'diastolic' => $diastolic,
            'temperature' => $temperature,
            'pulse_rate' => $pulse_rate,
            'respiratory_rate' => $respiratory_rate,
            'oxygen_saturation' => $oxygen_saturation,
            'weight' => $weight
        ]
    ]);
    exit();
}

try {
    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Get appointment details
        $query = "SELECT a.*, p.id as patient_id, d.id as doctor_id 
                FROM appointments a 
                JOIN patients p ON a.patient_id = p.id 
                JOIN doctors d ON a.doctor_id = d.id 
                WHERE a.id = :appointment_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':appointment_id', $appointment_id);
        $stmt->execute();
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            throw new Exception('Appointment not found');
        }
    } catch (Exception $e) {
        throw new Exception('Error getting appointment: ' . $e->getMessage());
    }
    
    try {
        // Check if this is the patient's first visit
        $query = "SELECT COUNT(*) as visit_count FROM medical_records WHERE patient_id = :patient_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':patient_id', $appointment['patient_id']);
        $stmt->execute();
        $visit_count = $stmt->fetch(PDO::FETCH_ASSOC)['visit_count'];
        
        // For first-time patients, validate additional required fields
        if ($visit_count == 0) {
            if (!$height || !$pain_scale) {
                throw new Exception('For first-time patients, height and pain scale are required');
            }
        }
    } catch (Exception $e) {
        throw new Exception('Error checking visit count: ' . $e->getMessage());
    }
    
    try {
        // Create medical record
        $query = "INSERT INTO medical_records (
                    patient_id, doctor_id, appointment_id, notes, created_at
                ) VALUES (
                    :patient_id, :doctor_id, :appointment_id, :notes, NOW()
                )";
        
        $stmt = $db->prepare($query);
        
        $vitals_notes = "BP: " . $systolic . "/" . $diastolic . ", Temp: " . $temperature . "°C, HR: " . $pulse_rate . ", RR: " . $respiratory_rate . ", SpO2: " . $oxygen_saturation . "%, Weight: " . $weight . "kg";
        $record_notes = $notes ? $vitals_notes . "\n\nAdditional notes: " . $notes : $vitals_notes;
        
        $stmt->bindParam(':patient_id', $appointment['patient_id']);
        $stmt->bindParam(':doctor_id', $appointment['doctor_id']);
        $stmt->bindParam(':appointment_id', $appointment_id);
        $stmt->bindParam(':notes', $record_notes);
        $stmt->execute();
        
        $medical_record_id = $db->lastInsertId();
    } catch (Exception $e) {
        throw new Exception('Error creating medical record: ' . $e->getMessage());
    }
    
    try {
        // Get staff ID for the current user
        $user_id = $_SESSION['user_id'];
        $query = "SELECT id FROM staff WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $staff_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $staff_id = $staff_result ? $staff_result['id'] : null;
        
        // Prepare the query based on available fields
        $fields = [
            'medical_record_id', 'blood_pressure_systolic', 'blood_pressure_diastolic',
            'heart_rate', 'respiratory_rate', 'temperature', 'oxygen_saturation',
            'weight'
        ];
        $values = [
            ':medical_record_id', ':systolic', ':diastolic',
            ':heart_rate', ':respiratory_rate', ':temperature', ':oxygen_saturation',
            ':weight'
        ];
        
        // Add conditional fields
        if ($height) {
            $fields[] = 'height';
            $values[] = ':height';
        }
        if ($bmi) {
            $fields[] = 'bmi';
            $values[] = ':bmi';
        }
        if ($pain_scale) {
            $fields[] = 'pain_scale';
            $values[] = ':pain_scale';
        }
        if ($staff_id) {
            $fields[] = 'recorded_by';
            $values[] = ':recorded_by';
        }
        if ($notes) {
            $fields[] = 'notes';
            $values[] = ':notes';
        }
        
        // Add recorded_at as it's always needed
        $fields[] = 'recorded_at';
        $values[] = 'NOW()';
        
        // Build the query
        $query = "INSERT INTO vital_signs (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $values) . ")";
        $stmt = $db->prepare($query);
        
        // Bind the parameters
        $stmt->bindParam(':medical_record_id', $medical_record_id);
        $stmt->bindParam(':systolic', $systolic);
        $stmt->bindParam(':diastolic', $diastolic);
        $stmt->bindParam(':heart_rate', $pulse_rate);
        $stmt->bindParam(':respiratory_rate', $respiratory_rate);
        $stmt->bindParam(':temperature', $temperature);
        $stmt->bindParam(':oxygen_saturation', $oxygen_saturation);
        $stmt->bindParam(':weight', $weight);
        
        if ($height) $stmt->bindParam(':height', $height);
        if ($bmi) $stmt->bindParam(':bmi', $bmi);
        if ($pain_scale) $stmt->bindParam(':pain_scale', $pain_scale);
        if ($staff_id) $stmt->bindParam(':recorded_by', $staff_id);
        if ($notes) $stmt->bindParam(':notes', $notes);
        
        $stmt->execute();
    } catch (Exception $e) {
        throw new Exception('Error recording vital signs: ' . $e->getMessage());
    }
    
    try {
        // Update appointment status to "ready for consultation" instead of "completed"
        $query = "UPDATE appointments SET status = 'scheduled', vitals_recorded = 1 WHERE id = :appointment_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':appointment_id', $appointment_id);
        $stmt->execute();
    } catch (Exception $e) {
        throw new Exception('Error updating appointment status: ' . $e->getMessage());
    }
    
    // Commit transaction
    $db->commit();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Vital signs recorded successfully',
        'is_first_visit' => $visit_count == 0
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error recording vital signs: ' . $e->getMessage()
    ]);
}
?> 