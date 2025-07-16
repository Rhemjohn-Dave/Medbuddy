<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Get user ID from query or body
$user_id = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['user_id']) || isset($_GET['staff_id']))) {
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : intval($_GET['staff_id']);
} else if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'])) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        // Fallback to form data
        $data = $_POST;
    }
    if (isset($data['user_id']) || isset($data['staff_id'])) {
        $user_id = isset($data['user_id']) ? intval($data['user_id']) : intval($data['staff_id']);
    }
}

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get all clinics and current assignments for this staff
        try {
            $stmt = $conn->prepare("
                SELECT c.*, 
                       CASE WHEN sc.staff_id IS NOT NULL THEN 1 ELSE 0 END as is_assigned
                FROM clinics c
                LEFT JOIN staff_clinics sc ON c.id = sc.clinic_id 
                LEFT JOIN staff s ON sc.staff_id = s.id AND s.user_id = ?
                WHERE c.status = 'active'
                ORDER BY c.name ASC
            ");
            $stmt->execute([$user_id]);
            $clinics = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'clinics' => $clinics]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to fetch clinics: ' . $e->getMessage()]);
        }
        break;
    case 'POST':
        // Assign clinic to staff
        if (!isset($data['clinic_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Clinic ID is required']);
            exit();
        }
        $clinic_id = intval($data['clinic_id']);
        try {
            // Check if clinic exists and is active
            $stmt = $conn->prepare("SELECT id FROM clinics WHERE id = ? AND status = 'active'");
            $stmt->execute([$clinic_id]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Clinic not found or inactive']);
                exit();
            }
            // Get staff_id for this user
            $staff_stmt = $conn->prepare("SELECT id FROM staff WHERE user_id = ?");
            $staff_stmt->execute([$user_id]);
            $staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$staff) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Staff not found for this user']);
                exit();
            }
            
            $staff_id = $staff['id'];
            
            // Check if assignment already exists
            $stmt = $conn->prepare("SELECT staff_id FROM staff_clinics WHERE staff_id = ? AND clinic_id = ?");
            $stmt->execute([$staff_id, $clinic_id]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Clinic is already assigned to this staff']);
                exit();
            }
            // Assign clinic to staff
            $stmt = $conn->prepare("INSERT INTO staff_clinics (staff_id, clinic_id) VALUES (?, ?)");
            $stmt->execute([$staff_id, $clinic_id]);
            echo json_encode(['success' => true, 'message' => 'Clinic assigned successfully']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to assign clinic', 'error' => $e->getMessage()]);
        }
        break;
    case 'DELETE':
        // Remove clinic assignment from staff
        if (!isset($data['clinic_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Clinic ID is required']);
            exit();
        }
        $clinic_id = intval($data['clinic_id']);
        try {
            // Get staff_id for this user
            $staff_stmt = $conn->prepare("SELECT id FROM staff WHERE user_id = ?");
            $staff_stmt->execute([$user_id]);
            $staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$staff) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Staff not found for this user']);
                exit();
            }
            
            $staff_id = $staff['id'];
            
            // Check if assignment exists
            $stmt = $conn->prepare("SELECT staff_id FROM staff_clinics WHERE staff_id = ? AND clinic_id = ?");
            $stmt->execute([$staff_id, $clinic_id]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Clinic is not assigned to this staff']);
                exit();
            }
            // Remove clinic assignment
            $stmt = $conn->prepare("DELETE FROM staff_clinics WHERE staff_id = ? AND clinic_id = ?");
            $stmt->execute([$staff_id, $clinic_id]);
            echo json_encode(['success' => true, 'message' => 'Clinic assignment removed successfully']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to remove clinic assignment', 'error' => $e->getMessage()]);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
} 