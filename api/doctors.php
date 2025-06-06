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

// Get all active doctors with their specializations
$stmt = $conn->prepare("
    SELECT d.id, 
           CONCAT(d.first_name, ' ', d.last_name) as name,
           s.name as specialization,
           d.license_number
    FROM doctors d
    LEFT JOIN specializations s ON d.specialization_id = s.id
    WHERE d.status = 'active'
    ORDER BY d.first_name, d.last_name
");

try {
    $stmt->execute();
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'doctors' => $doctors]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch doctors']);
} 