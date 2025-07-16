<?php
// Set content type to JSON
header('Content-Type: application/json');

// Basic error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

// Simple function to log messages
function log_message($message) {
    error_log($message);
}

try {
    // Start session
    session_start();
    
    // Basic auth check
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    
    // Get user ID
    $user_id = $_SESSION['user_id'];
    
    // Get POST data
    $post_data = file_get_contents('php://input');
    $data = json_decode($post_data, true);
    
    // Basic validation
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data received']);
        exit;
    }
    
    if (!isset($data['appointment_id'])) {
        echo json_encode(['success' => false, 'message' => 'Appointment ID is required']);
        exit;
    }
    
    if (!isset($data['chief_complaint']) || empty(trim($data['chief_complaint']))) {
        echo json_encode(['success' => false, 'message' => 'Chief complaint is required']);
        exit;
    }
    
    if (!isset($data['diagnosis']) || empty(trim($data['diagnosis']))) {
        echo json_encode(['success' => false, 'message' => 'At least one diagnosis is required']);
        exit;
    }
    
    if (!isset($data['prescription']) || empty(trim($data['prescription']))) {
        echo json_encode(['success' => false, 'message' => 'At least one prescription is required']);
        exit;
    }
    
    // Get values from data
    $appointment_id = $data['appointment_id'];
    $chief_complaint = trim($data['chief_complaint']);
    $diagnosis = trim($data['diagnosis']);
    $prescription = trim($data['prescription']);
    $notes = isset($data['notes']) ? trim($data['notes']) : '';
    
    // Connect to database
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Get appointment details to get patient_id and doctor_id
        $sql = "SELECT patient_id, doctor_id FROM appointments WHERE id = :appointment_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':appointment_id', $appointment_id);
        $stmt->execute();
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            throw new Exception('Appointment not found');
        }
        
        // Check for existing medical record
        $sql = "SELECT id FROM medical_records WHERE appointment_id = :appointment_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':appointment_id', $appointment_id);
        $stmt->execute();
        
        $medical_record_id = null;
        
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $medical_record_id = $row['id'];
            
            // Update existing medical record
            $sql = "UPDATE medical_records SET 
                    chief_complaint = :chief_complaint,
                    notes = :notes,
                    updated_at = NOW()
                    WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':chief_complaint', $chief_complaint);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':id', $medical_record_id);
            $stmt->execute();
        } else {
            // Create new medical record
            $sql = "INSERT INTO medical_records 
                    (patient_id, doctor_id, appointment_id, record_date, record_time, record_type,
                     chief_complaint, notes, created_at) 
                    VALUES 
                    (:patient_id, :doctor_id, :appointment_id, CURDATE(), CURTIME(), 'initial',
                     :chief_complaint, :notes, NOW())";
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':patient_id', $appointment['patient_id']);
            $stmt->bindParam(':doctor_id', $appointment['doctor_id']);
            $stmt->bindParam(':appointment_id', $appointment_id);
            $stmt->bindParam(':chief_complaint', $chief_complaint);
            $stmt->bindParam(':notes', $notes);
            $stmt->execute();
            
            $medical_record_id = $db->lastInsertId();
        }

        // Store diagnoses
        if (!empty($diagnosis)) {
            // First delete any existing diagnoses for this medical record
            $sql = "DELETE FROM diagnoses WHERE medical_record_id = :medical_record_id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':medical_record_id', $medical_record_id);
            $stmt->execute();
            
            // Split diagnoses by newline
            $diagnoses = explode("\n", $diagnosis);
            
            // Prepare the insert statement
            $sql = "INSERT INTO diagnoses 
                    (medical_record_id, diagnosis, created_by, created_at) 
                    VALUES 
                    (:medical_record_id, :diagnosis, :created_by, NOW())";
            
            $stmt = $db->prepare($sql);
            
            // Process each diagnosis
            foreach ($diagnoses as $diagnosis_line) {
                if (empty(trim($diagnosis_line))) continue;
                
                // Parse diagnosis data - format: "diagnosis [type] - description - Status: status"
                if (preg_match('/^(.*?)\s*\[(.*?)\]\s*-\s*(.*?)\s*-\s*Status:\s*(.*?)$/', $diagnosis_line, $matches)) {
                    $diagnosis_text = trim($matches[1]);
                    $type = trim($matches[2]);
                    $description = trim($matches[3]);
                    $status = trim($matches[4]);
                    
                    // Combine all diagnosis information into one text field
                    $full_diagnosis = $diagnosis_text;
                    if (!empty($type)) $full_diagnosis .= " [Type: $type]";
                    if (!empty($description)) $full_diagnosis .= " - $description";
                    if (!empty($status)) $full_diagnosis .= " - Status: $status";
                    
                    $stmt->bindParam(':medical_record_id', $medical_record_id);
                    $stmt->bindParam(':diagnosis', $full_diagnosis);
                    $stmt->bindParam(':created_by', $user_id);
                    $stmt->execute();
                }
            }
        }
        
        // Store prescriptions
        if (!empty($prescription)) {
            // First delete any existing prescriptions for this medical record
            $sql = "DELETE FROM prescriptions WHERE medical_record_id = :medical_record_id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':medical_record_id', $medical_record_id);
            $stmt->execute();
            
            // Split prescriptions by newline
            $prescriptions = explode("\n", $prescription);
            
            // Prepare the insert statement
            $sql = "INSERT INTO prescriptions 
                    (medical_record_id, prescription_text, created_by, created_at) 
                    VALUES 
                    (:medical_record_id, :prescription_text, :created_by, NOW())";
            
            $stmt = $db->prepare($sql);
            
            // Process each prescription
            foreach ($prescriptions as $index => $prescription_line) {
                if (empty(trim($prescription_line))) continue;
                // Parse prescription data - format: "medication_name|dosage|frequency|duration durationUnit|instructions"
                $prescription_parts = explode('|', $prescription_line);
                if (count($prescription_parts) >= 5) {
                    $medication_name = trim($prescription_parts[0]);
                    $dosage = trim($prescription_parts[1]);
                    $frequency = trim($prescription_parts[2]);
                    // Parse duration and unit
                    $duration_raw = trim($prescription_parts[3]); // e.g., '30 days', '2 weeks', '1 month'
                    $duration_parts = preg_split('/\s+/', $duration_raw);
                    $duration_number = isset($duration_parts[0]) ? intval($duration_parts[0]) : 0;
                    $duration_unit = isset($duration_parts[1]) ? strtolower($duration_parts[1]) : 'days';
                    $duration_str = $duration_number . ' ' . $duration_unit;
                    $instructions = trim($prescription_parts[4]);

                    // Validate prescription data
                    if (empty($medication_name)) {
                        throw new Exception("Medication name is required for prescription #" . ($index + 1));
                    }
                    if (empty($dosage)) {
                        throw new Exception("Dosage is required for prescription #" . ($index + 1));
                    }
                    if (empty($frequency)) {
                        throw new Exception("Frequency is required for prescription #" . ($index + 1));
                    }
                    if (empty($duration_number) || $duration_number <= 0) {
                        throw new Exception("Duration is required for prescription #" . ($index + 1));
                    }
                    if (!in_array($duration_unit, ['day', 'days', 'week', 'weeks', 'month', 'months'])) {
                        throw new Exception("Invalid duration unit for prescription #" . ($index + 1));
                    }

                    // Format prescription text
                    $prescription_text = "$medication_name|$dosage|$frequency|$duration_str|$instructions";

                    // Insert prescription
                    $stmt->bindParam(':medical_record_id', $medical_record_id);
                    $stmt->bindParam(':prescription_text', $prescription_text);
                    $stmt->bindParam(':created_by', $user_id);
                    $stmt->execute();

                    // Calculate end_date interval for SQL
                    $interval_unit = 'DAY';
                    if (strpos($duration_unit, 'week') !== false) {
                        $interval_unit = 'WEEK';
                    } elseif (strpos($duration_unit, 'month') !== false) {
                        $interval_unit = 'MONTH';
                    }

                    // Also add to medications table for patient's medication history
                    $sql_med = "INSERT INTO medications 
                               (patient_id, medication_name, dosage, frequency, duration, start_date, end_date, 
                                prescribed_by, status, notes, created_at) 
                               VALUES 
                               (:patient_id, :medication_name, :dosage, :frequency, :duration, CURDATE(), 
                                DATE_ADD(CURDATE(), INTERVAL :duration_number $interval_unit), :doctor_id, 'active', :instructions, NOW())";
                    
                    $stmt_med = $db->prepare($sql_med);
                    $stmt_med->bindParam(':patient_id', $appointment['patient_id']);
                    $stmt_med->bindParam(':medication_name', $medication_name);
                    $stmt_med->bindParam(':dosage', $dosage);
                    $stmt_med->bindParam(':frequency', $frequency);
                    $stmt_med->bindParam(':duration', $duration_str); // Store the full duration string (e.g., '30 days')
                    $stmt_med->bindParam(':duration_number', $duration_number);
                    $stmt_med->bindParam(':doctor_id', $appointment['doctor_id']);
                    $stmt_med->bindParam(':instructions', $instructions);
                    $stmt_med->execute();
                } else {
                    throw new Exception('Invalid prescription format for prescription #' . ($index + 1) . ': ' . $prescription_line);
                }
            }
        }
        
        // Update appointment status to completed
        $sql = "UPDATE appointments SET status = 'completed' WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $appointment_id);
        $stmt->execute();
        
        // Commit transaction
        $db->commit();
        
        // Success response
        echo json_encode([
            'success' => true,
            'message' => 'Consultation saved successfully',
            'appointment_id' => $appointment_id,
            'medical_record_id' => $medical_record_id
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    // Log error detail
    log_message('Error in save_consultation.php: ' . $e->getMessage());
    
    // Return detailed error message
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 