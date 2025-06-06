<?php
// This script adds a vitals_recorded column to the appointments table if it doesn't exist
require_once '../config/database.php';

try {
    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if the vitals_recorded column exists
    $query = "SHOW COLUMNS FROM appointments LIKE 'vitals_recorded'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // Column doesn't exist, add it
        $query = "ALTER TABLE appointments ADD COLUMN vitals_recorded TINYINT(1) DEFAULT 0";
        $stmt = $db->prepare($query);
        $stmt->execute();
        echo "Successfully added vitals_recorded column to appointments table.\n";
        
        // Update existing records where medical records exist
        $query = "UPDATE appointments a
                  JOIN medical_records mr ON a.id = mr.appointment_id
                  SET a.vitals_recorded = 1
                  WHERE mr.id IS NOT NULL";
        $stmt = $db->prepare($query);
        $stmt->execute();
        echo "Successfully updated existing records with vitals_recorded = 1.\n";
    } else {
        echo "vitals_recorded column already exists in appointments table.\n";
    }
    
    echo "Done.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 