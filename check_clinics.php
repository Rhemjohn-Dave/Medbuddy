<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check all clinics
    $stmt = $conn->prepare("SELECT * FROM clinics WHERE status = 'active'");
    $stmt->execute();
    $clinics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>All Active Clinics:</h2>";
    echo "<pre>";
    print_r($clinics);
    echo "</pre>";
    
    // Check doctor-clinics relationships
    $stmt = $conn->prepare("SELECT dc.*, c.name as clinic_name, d.first_name, d.last_name 
                           FROM doctor_clinics dc 
                           JOIN clinics c ON dc.clinic_id = c.id 
                           JOIN doctors d ON dc.doctor_id = d.id");
    $stmt->execute();
    $doctorClinics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Doctor-Clinic Assignments:</h2>";
    echo "<pre>";
    print_r($doctorClinics);
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 