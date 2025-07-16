<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

session_start();
error_log("Admin dashboard stats request received");

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    error_log("Unauthorized access attempt - User ID: " . ($_SESSION['user_id'] ?? 'not set') . ", Role: " . ($_SESSION['role'] ?? 'not set'));
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get the absolute path to the database config file
$config_path = realpath(__DIR__ . '/../../config/database.php');
error_log("Looking for database config at: " . $config_path);

if (!file_exists($config_path)) {
    error_log("Database config file not found at: " . $config_path);
    http_response_code(500);
    echo json_encode(['error' => 'Database configuration not found']);
    exit();
}

require_once $config_path;
error_log("Database config loaded");

// Initialize database connection
try {
    $database = new Database();
    $conn = $database->getConnection();
    error_log("Database connection established");

    // Verify required tables exist
    $required_tables = ['users', 'doctors', 'appointments'];
    foreach ($required_tables as $table) {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() === 0) {
            throw new Exception("Required table '$table' does not exist");
        }
    }
    error_log("All required tables exist");

} catch(Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

try {
    // Initialize default values
    $totalUsers = 0;
    $activeDoctors = 0;
    $appointmentsToday = 0;
    $pendingApprovals = 0;

    // Get total users
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users");
        $stmt->execute();
        $totalUsers = $stmt->fetchColumn();
        error_log("Total users count: " . $totalUsers);
    } catch (PDOException $e) {
        error_log("Error getting total users: " . $e->getMessage());
    }

    // Get active doctors
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM doctors WHERE status = 'active'");
        $stmt->execute();
        $activeDoctors = $stmt->fetchColumn();
        error_log("Active doctors count: " . $activeDoctors);
    } catch (PDOException $e) {
        error_log("Error getting active doctors: " . $e->getMessage());
    }

    // Get today's appointments
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()");
        $stmt->execute();
        $appointmentsToday = $stmt->fetchColumn();
        error_log("Today's appointments count: " . $appointmentsToday);
    } catch (PDOException $e) {
        error_log("Error getting today's appointments: " . $e->getMessage());
    }

    // Get pending approvals
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE status = 'pending'");
        $stmt->execute();
        $pendingApprovals = $stmt->fetchColumn();
        error_log("Pending approvals count: " . $pendingApprovals);
    } catch (PDOException $e) {
        error_log("Error getting pending approvals: " . $e->getMessage());
    }

    $response = [
        'totalUsers' => (int)$totalUsers,
        'activeDoctors' => (int)$activeDoctors,
        'appointmentsToday' => (int)$appointmentsToday,
        'pendingApprovals' => (int)$pendingApprovals
    ];
    error_log("Sending response: " . json_encode($response));
    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Admin dashboard stats error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch dashboard statistics: ' . $e->getMessage()]);
}
?> 