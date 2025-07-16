<?php
require_once '../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Check if duration_per_appointment column exists
    $stmt = $conn->query("SHOW COLUMNS FROM doctor_schedules LIKE 'duration_per_appointment'");
    if ($stmt->rowCount() > 0) {
        // Drop duration_per_appointment column
        $conn->exec("ALTER TABLE doctor_schedules DROP COLUMN duration_per_appointment");
        echo "Removed duration_per_appointment column\n";
    }

    // Check if max_appointments_per_slot column exists
    $stmt = $conn->query("SHOW COLUMNS FROM doctor_schedules LIKE 'max_appointments_per_slot'");
    if ($stmt->rowCount() == 0) {
        // Add max_appointments_per_slot column
        $conn->exec("ALTER TABLE doctor_schedules ADD COLUMN max_appointments_per_slot INT NOT NULL DEFAULT 1 AFTER break_end");
        echo "Added max_appointments_per_slot column\n";
    }

    echo "Database update completed successfully\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 