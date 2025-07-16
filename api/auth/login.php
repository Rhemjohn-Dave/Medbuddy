<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Prevent any output before headers
ob_start();

// Set headers for JSON response
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Function to send JSON response
function sendJsonResponse($status, $message, $data = null) {
    http_response_code($status);
    $response = ["message" => $message];
    if ($data !== null) {
        $response = array_merge($response, $data);
    }
    echo json_encode($response);
    exit();
}

try {
    // Log the raw input
    $raw_data = file_get_contents("php://input");
    error_log("Raw input: " . $raw_data);

    // Include required files
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../objects/user.php';

    // Get database connection
    $database = new Database();
    $db = $database->getConnection();

    // Initialize user object
    $user = new User($db);

    // Parse JSON data
    $data = json_decode($raw_data);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        sendJsonResponse(400, "Invalid JSON data");
    }

    // Validate input
    if (empty($data->email) || empty($data->password)) {
        error_log("Missing email or password");
        sendJsonResponse(400, "Email and password are required");
    }

    // Set user properties
    $user->email = $data->email;
    $user->password = $data->password;

    // Attempt login
    $stmt = $user->login();
    if (!$stmt) {
        error_log("Login query failed");
        sendJsonResponse(500, "Database error occurred");
    }

    // Get user data
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        error_log("No user found with email: " . $data->email);
        sendJsonResponse(401, "Invalid email or password");
    }

    // Verify password
    if (!password_verify($data->password, $row['password'])) {
        error_log("Invalid password for user: " . $data->email);
        sendJsonResponse(401, "Invalid email or password");
    }

    // Check approval status
    if ($row['approval_status'] === 'pending') {
        error_log("User pending approval: " . $data->email);
        sendJsonResponse(401, "Your account is pending approval. Please try again later.");
    }
    if ($row['approval_status'] === 'rejected') {
        error_log("User rejected: " . $data->email);
        sendJsonResponse(401, "Your account has been rejected. Please contact support.");
    }

    // Start session
    session_start();

    // Set session variables
    $_SESSION['user_id'] = $row['id'];
    $_SESSION['role'] = $row['role'];
    $_SESSION['email'] = $row['email'];

    // Determine redirect URL based on role
    $redirect_url = '';
    switch($row['role']) {
        case 'admin':
            $redirect_url = 'pages/admin/index.php';
            break;
        case 'doctor':
            $redirect_url = 'pages/doctor/index.php';
            break;
        case 'patient':
            $redirect_url = 'pages/patient/index.php';
            break;
        case 'staff':
            $redirect_url = 'pages/staff/index.php';
            break;
        default:
            $redirect_url = 'index.php';
    }

    // Send success response
    sendJsonResponse(200, "Login successful", [
        "user" => [
            "id" => $row['id'],
            "email" => $row['email'],
            "role" => $row['role']
        ],
        "redirect_url" => $redirect_url
    ]);

} catch (Exception $e) {
    // Log error
    error_log("Login error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Send error response
    sendJsonResponse(500, "An error occurred during login");
}
?> 