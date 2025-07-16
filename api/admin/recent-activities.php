<?php
// Prevent any output before headers
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Start output buffering to catch any unexpected output
ob_start();

session_start();
error_log("Admin recent activities request received");

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    error_log("Unauthorized access attempt - User ID: " . ($_SESSION['user_id'] ?? 'not set') . ", Role: " . ($_SESSION['role'] ?? 'not set'));
    ob_end_clean(); // Clear any output
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get the absolute path to the database config file
$config_path = realpath(__DIR__ . '/../../config/database.php');
error_log("Looking for database config at: " . $config_path);

if (!file_exists($config_path)) {
    error_log("Database config file not found at: " . $config_path);
    ob_end_clean(); // Clear any output
    http_response_code(500);
    echo json_encode(['error' => 'Database configuration not found']);
    exit();
}

require_once $config_path;
error_log("Database config loaded");

try {
    $database = new Database();
    error_log("Database class instantiated");
    
    try {
        $conn = $database->getConnection();
        error_log("Database connection established successfully");
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw new Exception("Could not connect to database: " . $e->getMessage());
    }

    // Verify required tables exist
    $required_tables = ['users', 'appointments', 'doctors', 'patients'];
    foreach ($required_tables as $table) {
        try {
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() === 0) {
                throw new Exception("Required table '$table' does not exist");
            }
            error_log("Table '$table' exists");
        } catch (PDOException $e) {
            error_log("Error checking table '$table': " . $e->getMessage());
            throw new Exception("Error checking database tables: " . $e->getMessage());
        }
    }
    error_log("All required tables exist");

    // Get recent activities (appointments and user registrations)
    $sql = "
        (SELECT 
            'appointment' as type,
            CONCAT(a.date, ' ', a.time) as date,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
            a.status,
            a.created_at
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN doctors d ON a.doctor_id = d.id
        ORDER BY a.created_at DESC
        LIMIT 5)
        UNION ALL
        (SELECT 
            'registration' as type,
            u.created_at as date,
            CASE 
                WHEN u.role = 'doctor' THEN CONCAT(d.first_name, ' ', d.last_name)
                WHEN u.role = 'patient' THEN CONCAT(p.first_name, ' ', p.last_name)
                WHEN u.role = 'staff' THEN CONCAT(s.first_name, ' ', s.last_name)
                ELSE NULL 
            END as name,
            u.role as role,
            u.approval_status as status,
            u.created_at
        FROM users u
        LEFT JOIN doctors d ON u.id = d.user_id
        LEFT JOIN patients p ON u.id = p.user_id
        LEFT JOIN staff s ON u.id = s.user_id
        WHERE u.role != 'admin'
        ORDER BY u.created_at DESC
        LIMIT 5)
        ORDER BY created_at DESC
        LIMIT 10
    ";

    try {
        error_log("Executing SQL query for recent activities");
        $stmt = $conn->query($sql);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Retrieved " . count($activities) . " activities");
    } catch (PDOException $e) {
        error_log("Error executing SQL query: " . $e->getMessage());
        throw new Exception("Error fetching activities: " . $e->getMessage());
    }

    // Format activities for display
    try {
        $formatted_activities = array_map(function($activity) {
            $time = date('M d, Y h:i A', strtotime($activity['date']));
            
            if ($activity['type'] === 'appointment') {
                $status = ucfirst($activity['status']);
                return [
                    'title' => 'New Appointment',
                    'description' => "Patient {$activity['patient_name']} scheduled an appointment with Dr. {$activity['doctor_name']}",
                    'time' => $time,
                    'status' => $status
                ];
            } else {
                $role = ucfirst($activity['role']);
                $status = ucfirst($activity['status']);
                return [
                    'title' => 'New User Registration',
                    'description' => "{$role} {$activity['name']} registered",
                    'time' => $time,
                    'status' => $status
                ];
            }
        }, $activities);

        error_log("Successfully formatted " . count($formatted_activities) . " activities");
        ob_end_clean(); // Clear any output before sending JSON
        echo json_encode(['activities' => $formatted_activities]);
    } catch (Exception $e) {
        error_log("Error formatting activities: " . $e->getMessage());
        throw new Exception("Error formatting activities: " . $e->getMessage());
    }

} catch (Exception $e) {
    error_log("Error in recent activities: " . $e->getMessage());
    ob_end_clean(); // Clear any output before sending error
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch recent activities: ' . $e->getMessage()]);
}
?> 