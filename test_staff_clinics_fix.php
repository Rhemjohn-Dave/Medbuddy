<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "Database connection successful!\n";
    
    // Test the fixed query
    $stmt = $conn->prepare("SELECT sc.clinic_id FROM staff_clinics sc 
                           JOIN staff s ON sc.staff_id = s.id 
                           WHERE s.user_id = ?");
    $stmt->execute([1]); // Test with user_id = 1
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Query executed successfully!\n";
    echo "Results: " . print_r($result, true) . "\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 