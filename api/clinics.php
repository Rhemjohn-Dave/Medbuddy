<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Check if doctor_id is provided
if (!isset($_GET['doctor_id'])) {
    echo json_encode(['success' => false, 'message' => 'Doctor ID is required']);
    exit();
}

$doctorId = $_GET['doctor_id'];

// If patient_id is also provided, return clinics where this patient had appointments with this doctor
if (isset($_GET['patient_id'])) {
    $patientId = $_GET['patient_id'];
    $stmt = $conn->prepare("
        SELECT DISTINCT c.id, c.name
        FROM appointments a
        JOIN clinics c ON a.clinic_id = c.id
        WHERE a.doctor_id = ? AND a.patient_id = ?
        ORDER BY c.name ASC
    ");
    try {
        $stmt->execute([$doctorId, $patientId]);
        $clinics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'clinics' => $clinics]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch clinics']);
    }
    exit();
}

// Get clinics for the specified doctor
$stmt = $conn->prepare("
    SELECT c.*
    FROM clinics c
    JOIN doctor_clinics dc ON c.id = dc.clinic_id
    WHERE dc.doctor_id = ? AND c.status = 'active'
    ORDER BY c.name ASC
");

try {
    $stmt->execute([$doctorId]);
    $clinics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'clinics' => $clinics]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch clinics']);
} 