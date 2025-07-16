<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    } else {
        header('Location: ../pages/doctor/index.php?page=schedule');
        exit();
    }
}

$db = new Database();
$conn = $db->getConnection();

// Get doctor ID
$stmt = $conn->prepare("SELECT id FROM doctors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);
$doctor_id = $doctor['id'];

// Determine the request method
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

switch ($method) {
    case 'GET':
        // Get single schedule or list of schedules
        if (isset($_GET['id'])) {
            // Get single schedule
            $stmt = $conn->prepare("
                SELECT s.*, c.name as clinic_name
                FROM doctor_schedules s
                JOIN clinics c ON s.clinic_id = c.id
                WHERE s.id = ? AND s.doctor_id = ?
            ");
            $stmt->execute([$_GET['id'], $doctor_id]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($schedule) {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    echo json_encode(['success' => true, 'schedule' => $schedule]);
                } else {
                    // For non-AJAX requests, redirect to edit form
                    header('Location: ../pages/doctor/pages/schedule.php?edit=' . $_GET['id']);
                    exit();
                }
            } else {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    echo json_encode(['success' => false, 'message' => 'Schedule not found']);
                } else {
                    header('Location: ../pages/doctor/pages/schedule.php?error=Schedule not found');
                    exit();
                }
            }
        } else {
            // Get list of schedules with filters
            $where = ['s.doctor_id = ?'];
            $params = [$doctor_id];

            if (!empty($_GET['clinic_id'])) {
                $where[] = 's.clinic_id = ?';
                $params[] = $_GET['clinic_id'];
            }

            if (!empty($_GET['day'])) {
                $where[] = 's.day_of_week = ?';
                $params[] = $_GET['day'];
            }

            $sql = "
                SELECT s.*, c.name as clinic_name
                FROM doctor_schedules s
                JOIN clinics c ON s.clinic_id = c.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY s.day_of_week, s.start_time
            ";

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                echo json_encode(['success' => true, 'schedules' => $schedules]);
            } else {
                // For non-AJAX requests, display the schedules
                include '../pages/doctor/pages/schedule.php';
                exit();
            }
        }
        break;

    case 'POST':
        // Create new schedule
        $data = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? json_decode(file_get_contents('php://input'), true) : $_POST;

        // Validate required fields
        $required_fields = ['clinic_id', 'day_of_week', 'start_time', 'end_time', 'max_appointments_per_slot'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
                } else {
                    header('Location: ../pages/doctor/index.php?page=schedule&error=Missing required fields');
                }
                exit();
            }
        }

        // Check if schedule already exists for this doctor, clinic, and day
        $stmt = $conn->prepare("
            SELECT id FROM doctor_schedules 
            WHERE doctor_id = ? AND clinic_id = ? AND day_of_week = ?
        ");
        $stmt->execute([$doctor_id, $data['clinic_id'], $data['day_of_week']]);
        if ($stmt->fetch()) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo json_encode(['success' => false, 'message' => 'Schedule already exists for this clinic and day']);
            } else {
                header('Location: ../pages/doctor/index.php?page=schedule&error=Schedule already exists for this clinic and day');
            }
            exit();
        }

        // Insert new schedule
        $stmt = $conn->prepare("
            INSERT INTO doctor_schedules (
                doctor_id, clinic_id, day_of_week, start_time, end_time,
                break_start, break_end, duration_per_appointment, max_appointments_per_slot
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        try {
            $stmt->execute([
                $doctor_id,
                $data['clinic_id'],
                $data['day_of_week'],
                $data['start_time'],
                $data['end_time'],
                $data['break_start'] ?? null,
                $data['break_end'] ?? null,
                $data['duration_per_appointment'],
                $data['max_appointments_per_slot']
            ]);
            
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo json_encode(['success' => true, 'message' => 'Schedule created successfully']);
            } else {
                header('Location: ../pages/doctor/index.php?page=schedule&success=Schedule created successfully');
            }
        } catch (PDOException $e) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo json_encode(['success' => false, 'message' => 'Failed to create schedule']);
            } else {
                header('Location: ../pages/doctor/index.php?page=schedule&error=Failed to create schedule');
            }
        }
        break;

    case 'PUT':
        // Update existing schedule
        $data = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? json_decode(file_get_contents('php://input'), true) : $_POST;
        $schedule_id = isset($_GET['id']) ? $_GET['id'] : $data['schedule_id'];

        if (empty($schedule_id)) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo json_encode(['success' => false, 'message' => 'Schedule ID is required']);
            } else {
                header('Location: ../pages/doctor/index.php?page=schedule&error=Schedule ID is required');
            }
            exit();
        }

        // Validate required fields
        $required_fields = ['clinic_id', 'day_of_week', 'start_time', 'end_time', 'max_appointments_per_slot'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
                } else {
                    header('Location: ../pages/doctor/index.php?page=schedule&error=Missing required fields');
                }
                exit();
            }
        }

        // Check if schedule exists and belongs to the doctor
        $stmt = $conn->prepare("
            SELECT id FROM doctor_schedules 
            WHERE id = ? AND doctor_id = ?
        ");
        $stmt->execute([$schedule_id, $doctor_id]);
        if (!$stmt->fetch()) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo json_encode(['success' => false, 'message' => 'Schedule not found']);
            } else {
                header('Location: ../pages/doctor/index.php?page=schedule&error=Schedule not found');
            }
            exit();
        }

        // Check for duplicate schedule
        $stmt = $conn->prepare("
            SELECT id FROM doctor_schedules 
            WHERE doctor_id = ? AND clinic_id = ? AND day_of_week = ? AND id != ?
        ");
        $stmt->execute([$doctor_id, $data['clinic_id'], $data['day_of_week'], $schedule_id]);
        if ($stmt->fetch()) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo json_encode(['success' => false, 'message' => 'Schedule already exists for this clinic and day']);
            } else {
                header('Location: ../pages/doctor/index.php?page=schedule&error=Schedule already exists for this clinic and day');
            }
            exit();
        }

        // Update schedule
        $stmt = $conn->prepare("
            UPDATE doctor_schedules SET
                clinic_id = ?,
                day_of_week = ?,
                start_time = ?,
                end_time = ?,
                break_start = ?,
                break_end = ?,
                duration_per_appointment = ?,
                max_appointments_per_slot = ?
            WHERE id = ? AND doctor_id = ?
        ");

        try {
            $stmt->execute([
                $data['clinic_id'],
                $data['day_of_week'],
                $data['start_time'],
                $data['end_time'],
                $data['break_start'] ?? null,
                $data['break_end'] ?? null,
                $data['duration_per_appointment'],
                $data['max_appointments_per_slot'],
                $schedule_id,
                $doctor_id
            ]);
            
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
            } else {
                header('Location: ../pages/doctor/index.php?page=schedule&success=Schedule updated successfully');
            }
        } catch (PDOException $e) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo json_encode(['success' => false, 'message' => 'Failed to update schedule']);
            } else {
                header('Location: ../pages/doctor/index.php?page=schedule&error=Failed to update schedule');
            }
        }
        break;

    case 'DELETE':
        // Delete schedule
        $schedule_id = isset($_GET['id']) ? $_GET['id'] : (isset($_POST['id']) ? $_POST['id'] : null);

        if (empty($schedule_id)) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo json_encode(['success' => false, 'message' => 'Schedule ID is required']);
            } else {
                header('Location: ../pages/doctor/index.php?page=schedule&error=Schedule ID is required');
            }
            exit();
        }

        // Check if schedule exists and belongs to the doctor
        $stmt = $conn->prepare("
            SELECT id FROM doctor_schedules 
            WHERE id = ? AND doctor_id = ?
        ");
        $stmt->execute([$schedule_id, $doctor_id]);
        if (!$stmt->fetch()) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo json_encode(['success' => false, 'message' => 'Schedule not found']);
            } else {
                header('Location: ../pages/doctor/index.php?page=schedule&error=Schedule not found');
            }
            exit();
        }

        // Delete schedule
        $stmt = $conn->prepare("DELETE FROM doctor_schedules WHERE id = ? AND doctor_id = ?");
        try {
            $stmt->execute([$schedule_id, $doctor_id]);
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo json_encode(['success' => true, 'message' => 'Schedule deleted successfully']);
            } else {
                header('Location: ../pages/doctor/index.php?page=schedule&success=Schedule deleted successfully');
            }
        } catch (PDOException $e) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo json_encode(['success' => false, 'message' => 'Failed to delete schedule']);
            } else {
                header('Location: ../pages/doctor/index.php?page=schedule&error=Failed to delete schedule');
            }
        }
        break;

    default:
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        } else {
            header('Location: ../pages/doctor/index.php?page=schedule&error=Method not allowed');
        }
        break;
} 