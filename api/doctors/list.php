<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Query to get all approved doctors
    $query = "SELECT d.id, d.first_name, d.last_name, d.specialization 
              FROM doctors d 
              INNER JOIN users u ON d.user_id = u.id 
              WHERE u.approval_status = 'approved' 
              ORDER BY d.last_name, d.first_name";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $doctors = array();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        array_push($doctors, array(
            "id" => $row['id'],
            "first_name" => $row['first_name'],
            "last_name" => $row['last_name'],
            "specialization" => $row['specialization']
        ));
    }
    
    // Set response code - 200 OK
    http_response_code(200);
    echo json_encode($doctors);
    
} catch(PDOException $e) {
    // Set response code - 500 Internal Server Error
    http_response_code(500);
    echo json_encode(array("message" => "Failed to fetch doctors: " . $e->getMessage()));
}
?> 