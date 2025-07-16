<?php
// api/upload_lab_result.php
header('Content-Type: application/json');
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['lab_request_id']) || !isset($_FILES['result_file'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$lab_request_id = $_POST['lab_request_id'];
$file = $_FILES['result_file'];

// Check file type
if ($file['type'] !== 'application/pdf') {
    echo json_encode(['success' => false, 'message' => 'Only PDF files are allowed.']);
    exit;
}

// Save file
$upload_dir = '../uploads/lab_results/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}
$filename = 'labresult_' . $lab_request_id . '_' . time() . '.pdf';
$filepath = $upload_dir . $filename;
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to upload file.']);
    exit;
}

// Update database
try {
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("UPDATE lab_requests SET result_file = ? WHERE id = ?");
    $stmt->execute([$filename, $lab_request_id]);
    echo json_encode(['success' => true, 'file' => $filename]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 