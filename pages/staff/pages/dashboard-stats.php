<?php
session_start();
require_once '../../../config/database.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if database connection is available
if (!isset($conn)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection not available']);
    exit();
}

// Get selected date from request, default to today
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

try {
    // Get total appointments for selected date
    $stmt = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = ?");
    $stmt->execute([$selected_date]);
    $total_appointments = $stmt->fetchColumn();

    // Get pending approvals
    $stmt = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE status = 'pending'");
    $stmt->execute();
    $pending_approvals = $stmt->fetchColumn();

    // Get patients seen today
    $stmt = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = ? AND status = 'completed'");
    $stmt->execute([$selected_date]);
    $patients_seen = $stmt->fetchColumn();

    // Get pending vital signs
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM appointments a 
        LEFT JOIN medical_records m ON a.id = m.appointment_id 
        WHERE DATE(a.appointment_date) = ? AND m.id IS NULL
    ");
    $stmt->execute([$selected_date]);
    $pending_vitals = $stmt->fetchColumn();

    // Return the statistics
    echo json_encode([
        'total_appointments' => (int)$total_appointments,
        'pending_approvals' => (int)$pending_approvals,
        'patients_seen' => (int)$patients_seen,
        'pending_vitals' => (int)$pending_vitals
    ]);

} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch dashboard statistics']);
} 