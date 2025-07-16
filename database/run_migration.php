<?php
require_once '../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if column already exists
    $stmt = $conn->prepare("SHOW COLUMNS FROM doctor_schedules LIKE 'duration_per_appointment'");
    $stmt->execute();
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        // Add the column
        $conn->exec("ALTER TABLE doctor_schedules ADD COLUMN duration_per_appointment INT DEFAULT 30 AFTER break_end");
        echo "Added duration_per_appointment column to doctor_schedules table.\n";
        
        // Update existing records
        $conn->exec("UPDATE doctor_schedules SET duration_per_appointment = 30 WHERE duration_per_appointment IS NULL");
        echo "Updated existing records with default duration of 30 minutes.\n";
    } else {
        echo "duration_per_appointment column already exists.\n";
    }
    
    echo "Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?> 