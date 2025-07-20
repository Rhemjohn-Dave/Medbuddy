<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$stmt = $db->prepare("SELECT * FROM appointments WHERE doctor_id = 2 ORDER BY date DESC, time DESC");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode([
    'db_host' => $database->host ?? 'unknown',
    'db_name' => $database->db_name ?? 'unknown',
    'row_count' => count($rows),
    'rows' => $rows
]);