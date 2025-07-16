<?php
/**
 * Migration script to remove the position column from the staff table
 * This script should be run once to update the database schema
 */

require_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "Starting migration to remove position column from staff table...\n";
    
    // Check if position column exists
    $check_sql = "SHOW COLUMNS FROM staff LIKE 'position'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        // Remove the position column
        $alter_sql = "ALTER TABLE staff DROP COLUMN position";
        $alter_stmt = $conn->prepare($alter_sql);
        $alter_stmt->execute();
        
        echo "Successfully removed position column from staff table.\n";
    } else {
        echo "Position column does not exist in staff table. No action needed.\n";
    }
    
    echo "Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
?> 