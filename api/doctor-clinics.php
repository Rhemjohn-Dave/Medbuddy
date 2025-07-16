<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Get doctor ID
$stmt = $conn->prepare("SELECT id FROM doctors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);
$doctor_id = $doctor['id'];

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get all available clinics and current assignments
        try {
            // Debug: Log the doctor ID
            error_log("Doctor ID: " . $doctor_id);
            
            // Get all active clinics
            $stmt = $conn->prepare("
                SELECT c.*, 
                       CASE WHEN dc.doctor_id IS NOT NULL THEN 1 ELSE 0 END as is_assigned
                FROM clinics c
                LEFT JOIN doctor_clinics dc ON c.id = dc.clinic_id AND dc.doctor_id = ?
                WHERE c.status = 'active'
                ORDER BY c.name ASC
            ");
            $stmt->execute([$doctor_id]);
            $clinics = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Debug: Log the results
            error_log("Found " . count($clinics) . " clinics");
            error_log("Clinics data: " . json_encode($clinics));
            
            echo json_encode(['success' => true, 'clinics' => $clinics]);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to fetch clinics: ' . $e->getMessage()]);
        }
        break;

    case 'POST':
        // Assign clinic to doctor
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['clinic_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Clinic ID is required']);
            exit();
        }

        $clinic_id = $data['clinic_id'];

        try {
            // Check if clinic exists and is active
            $stmt = $conn->prepare("SELECT id FROM clinics WHERE id = ? AND status = 'active'");
            $stmt->execute([$clinic_id]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Clinic not found or inactive']);
                exit();
            }

            // Check if assignment already exists
            $stmt = $conn->prepare("SELECT doctor_id FROM doctor_clinics WHERE doctor_id = ? AND clinic_id = ?");
            $stmt->execute([$doctor_id, $clinic_id]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Clinic is already assigned to you']);
                exit();
            }

            // Assign clinic to doctor
            $stmt = $conn->prepare("INSERT INTO doctor_clinics (doctor_id, clinic_id) VALUES (?, ?)");
            $stmt->execute([$doctor_id, $clinic_id]);
            
            echo json_encode(['success' => true, 'message' => 'Clinic assigned successfully']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to assign clinic']);
        }
        break;

    case 'DELETE':
        // Remove clinic assignment from doctor
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['clinic_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Clinic ID is required']);
            exit();
        }

        $clinic_id = $data['clinic_id'];

        try {
            // Check if assignment exists
            $stmt = $conn->prepare("SELECT doctor_id FROM doctor_clinics WHERE doctor_id = ? AND clinic_id = ?");
            $stmt->execute([$doctor_id, $clinic_id]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Clinic is not assigned to you']);
                exit();
            }

            // Check if there are any schedules for this clinic
            $stmt = $conn->prepare("SELECT id FROM doctor_schedules WHERE doctor_id = ? AND clinic_id = ?");
            $stmt->execute([$doctor_id, $clinic_id]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Cannot remove clinic assignment. You have schedules for this clinic. Please remove all schedules first.']);
                exit();
            }

            // Remove clinic assignment
            $stmt = $conn->prepare("DELETE FROM doctor_clinics WHERE doctor_id = ? AND clinic_id = ?");
            $stmt->execute([$doctor_id, $clinic_id]);
            
            echo json_encode(['success' => true, 'message' => 'Clinic assignment removed successfully']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to remove clinic assignment']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
} 