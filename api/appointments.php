<?php
session_start();
require_once '../config/database.php';

// Set JSON content type for all responses
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // If both patient_id and doctor_id are provided in GET, allow admin/doctor-side fetch
    if (isset($_GET['patient_id']) && isset($_GET['doctor_id'])) {
        $patient_id = $_GET['patient_id'];
        $doctor_id = $_GET['doctor_id'];
        $stmt = $conn->prepare("SELECT a.id, a.date, a.time, c.name as clinic_name FROM appointments a JOIN clinics c ON a.clinic_id = c.id WHERE a.patient_id = ? AND a.doctor_id = ? ORDER BY a.date DESC, a.time DESC");
        $stmt->execute([$patient_id, $doctor_id]);
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'appointments' => $appointments]);
        exit();
    }
    // Default: get patient ID from session user
    $stmt = $conn->prepare("SELECT id FROM patients WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$patient) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Patient not found']);
        exit();
    }
    $patient_id = $patient['id'];

    // Handle different HTTP methods
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            // Get appointments
            if (isset($_GET['id'])) {
                // Get single appointment
                $appointmentId = $_GET['id'];
                $stmt = $conn->prepare("
                    SELECT a.*, 
                           CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
                           c.name as clinic_name, 
                           c.address as clinic_address
                    FROM appointments a
                    JOIN doctors d ON a.doctor_id = d.id
                    JOIN clinics c ON a.clinic_id = c.id
                    WHERE a.id = ? AND a.patient_id = ?
                ");
                $stmt->execute([$appointmentId, $patient_id]);
                $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($appointment) {
                    echo json_encode(['success' => true, 'appointment' => $appointment]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
                }
            } else {
                // Get filtered appointments
                $where = ['a.patient_id = ?'];
                $params = [$patient_id];

                if (!empty($_GET['status'])) {
                    $where[] = 'a.status = ?';
                    $params[] = $_GET['status'];
                }

                if (!empty($_GET['doctor'])) {
                    $where[] = 'a.doctor_id = ?';
                    $params[] = $_GET['doctor'];
                }

                if (!empty($_GET['dateRange'])) {
                    $today = date('Y-m-d');
                    switch ($_GET['dateRange']) {
                        case 'upcoming':
                            $where[] = 'a.date >= ?';
                            $params[] = $today;
                            break;
                        case 'past':
                            $where[] = 'a.date < ?';
                            $params[] = $today;
                            break;
                    }
                }

                if (!empty($_GET['search'])) {
                    $search = '%' . $_GET['search'] . '%';
                    $where[] = '(a.purpose LIKE ? OR a.notes LIKE ?)';
                    $params[] = $search;
                    $params[] = $search;
                }

                $sql = "
                    SELECT a.*, 
                           CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
                           c.name as clinic_name
                    FROM appointments a
                    JOIN doctors d ON a.doctor_id = d.id
                    JOIN clinics c ON a.clinic_id = c.id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY a.date DESC, a.time DESC
                ";

                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'appointments' => $appointments]);
            }
            break;

        case 'POST':
            // Create new appointment
            // Handle both JSON and form data
            $data = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? 
                    json_decode(file_get_contents('php://input'), true) : 
                    $_POST;
            
            // Check if patient profile is complete (required for appointment booking)
            $stmt = $conn->prepare("SELECT date_of_birth, gender, contact_number, address, emergency_contact_name, emergency_contact_number FROM patients WHERE id = ?");
            $stmt->execute([$patient_id]);
            $patient_profile = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $profile_complete = true;
            $missing_fields = [];
            
            if (empty($patient_profile['date_of_birth'])) {
                $profile_complete = false;
                $missing_fields[] = 'Date of Birth';
            }
            if (empty($patient_profile['gender'])) {
                $profile_complete = false;
                $missing_fields[] = 'Gender';
            }
            if (empty($patient_profile['contact_number'])) {
                $profile_complete = false;
                $missing_fields[] = 'Contact Number';
            }
            if (empty($patient_profile['address'])) {
                $profile_complete = false;
                $missing_fields[] = 'Address';
            }
            if (empty($patient_profile['emergency_contact_name'])) {
                $profile_complete = false;
                $missing_fields[] = 'Emergency Contact Name';
            }
            if (empty($patient_profile['emergency_contact_number'])) {
                $profile_complete = false;
                $missing_fields[] = 'Emergency Contact Number';
            }
            
            if (!$profile_complete) {
                error_log("Profile completion check failed for patient ID: $patient_id. Missing fields: " . implode(', ', $missing_fields));
                echo json_encode([
                    'success' => false, 
                    'message' => 'Profile incomplete. Please complete your profile before booking appointments.',
                    'missing_fields' => $missing_fields
                ]);
                exit();
            }
            
            // Validate required fields
            $required = ['doctor_id', 'clinic_id', 'date', 'time', 'purpose'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
                    exit();
                }
            }

            // Check if time slot is available
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM appointments 
                WHERE doctor_id = ? AND clinic_id = ? AND date = ? AND time = ? AND status != 'cancelled'
            ");
            $stmt->execute([$data['doctor_id'], $data['clinic_id'], $data['date'], $data['time']]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'This time slot is not available']);
                exit();
            }

            // Insert appointment
            $stmt = $conn->prepare("
                INSERT INTO appointments (patient_id, doctor_id, clinic_id, date, time, purpose, notes, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled')
            ");
            
            try {
                $stmt->execute([
                    $patient_id,
                    $data['doctor_id'],
                    $data['clinic_id'],
                    $data['date'],
                    $data['time'],
                    $data['purpose'],
                    $data['notes'] ?? null
                ]);
                
                // Get the inserted appointment details
                $appointment_id = $conn->lastInsertId();
                $stmt = $conn->prepare("
                    SELECT a.*, 
                           CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
                           c.name as clinic_name
                    FROM appointments a
                    JOIN doctors d ON a.doctor_id = d.id
                    JOIN clinics c ON a.clinic_id = c.id
                    WHERE a.id = ?
                ");
                $stmt->execute([$appointment_id]);
                $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Appointment scheduled successfully',
                    'appointment_id' => $appointment_id,
                    'doctor_name' => $appointment['doctor_name'],
                    'clinic_name' => $appointment['clinic_name'],
                    'date' => $appointment['date'],
                    'time' => $appointment['time'],
                    'purpose' => $appointment['purpose']
                ]);
                exit();
        
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to schedule appointment']);
                exit();
            }
            break;

        case 'DELETE':
            // Cancel appointment
            if (!isset($_GET['id'])) {
                echo json_encode(['success' => false, 'message' => 'Appointment ID is required']);
                exit();
            }

            $stmt = $conn->prepare("
                UPDATE appointments 
                SET status = 'cancelled' 
                WHERE id = ? AND patient_id = ? AND status = 'scheduled'
            ");
            
            try {
                $stmt->execute([$_GET['id'], $patient_id]);
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Appointment not found or cannot be cancelled']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to cancel appointment']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your request']);
    exit();
} 