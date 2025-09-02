<?php
/**
 * Fix AccessLogs Table Constraints
 * This script fixes foreign key constraints that prevent NULL user_id values
 */

require_once '../core/config.php';
require_once '../core/database.php';

header('Content-Type: text/plain');

try {
    $db = getDB();
    
    echo "Fixing AccessLogs table constraints...\n\n";
    
    // First, check current table structure
    echo "Current AccessLogs table structure:\n";
    $stmt = $db->prepare("DESCRIBE AccessLogs");
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $column) {
        echo sprintf("%-15s %-15s %-5s %-10s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null'], 
            $column['Key']
        );
    }
    echo "\n";
    
    // Check current foreign key constraints
    echo "Current foreign key constraints:\n";
    $stmt = $db->prepare("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME,
            DELETE_RULE
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_NAME = 'AccessLogs' 
        AND TABLE_SCHEMA = ?
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $stmt->execute([DB_NAME]);
    $constraints = $stmt->fetchAll();
    
    if (empty($constraints)) {
        echo "No foreign key constraints found.\n\n";
    } else {
        foreach ($constraints as $constraint) {
            echo sprintf("%-20s %-15s -> %-20s %-15s (DELETE: %s)\n",
                $constraint['CONSTRAINT_NAME'],
                $constraint['COLUMN_NAME'],
                $constraint['REFERENCED_TABLE_NAME'],
                $constraint['REFERENCED_COLUMN_NAME'],
                $constraint['DELETE_RULE']
            );
        }
        echo "\n";
    }
    
    // Try to drop and recreate the problematic constraint
    echo "Attempting to fix foreign key constraints...\n";
    
    try {
        // Drop existing constraints if they exist
        $db->exec("ALTER TABLE AccessLogs DROP FOREIGN KEY accesslogs_ibfk_1");
        echo "✓ Dropped old user_id constraint\n";
    } catch (PDOException $e) {
        echo "- No old user_id constraint to drop (this is fine)\n";
    }
    
    try {
        $db->exec("ALTER TABLE AccessLogs DROP FOREIGN KEY accesslogs_ibfk_2");
        echo "✓ Dropped old device_id constraint\n";
    } catch (PDOException $e) {
        echo "- No old device_id constraint to drop (this is fine)\n";
    }
    
    // Ensure user_id column allows NULL
    try {
        $db->exec("ALTER TABLE AccessLogs MODIFY COLUMN user_id INT NULL");
        echo "✓ Modified user_id column to allow NULL\n";
    } catch (PDOException $e) {
        echo "- Error modifying user_id column: " . $e->getMessage() . "\n";
    }
    
    // Add proper foreign key constraints
    try {
        $db->exec("
            ALTER TABLE AccessLogs 
            ADD CONSTRAINT fk_accesslogs_user 
            FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE SET NULL
        ");
        echo "✓ Added proper user_id foreign key constraint (allows NULL)\n";
    } catch (PDOException $e) {
        echo "- Error adding user_id constraint: " . $e->getMessage() . "\n";
    }
    
    try {
        $db->exec("
            ALTER TABLE AccessLogs 
            ADD CONSTRAINT fk_accesslogs_device 
            FOREIGN KEY (device_id) REFERENCES RFIDDevices(device_id) ON DELETE SET NULL
        ");
        echo "✓ Added proper device_id foreign key constraint\n";
    } catch (PDOException $e) {
        echo "- Error adding device_id constraint: " . $e->getMessage() . "\n";
    }
    
    // Test the fix by trying to insert a NULL user_id
    echo "\nTesting NULL user_id insert...\n";
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("
            INSERT INTO AccessLogs (user_id, device_id, ip_address, action, status, resource, timestamp) 
            VALUES (NULL, 1, '127.0.0.1', 'test_scan', 'failure', 'RFID: TEST123', NOW())
        ");
        $stmt->execute();
        
        // Clean up test record
        $stmt = $db->prepare("DELETE FROM AccessLogs WHERE resource = 'RFID: TEST123'");
        $stmt->execute();
        
        $db->commit();
        echo "✓ NULL user_id insert test successful!\n";
        
    } catch (PDOException $e) {
        $db->rollback();
        echo "✗ NULL user_id insert test failed: " . $e->getMessage() . "\n";
    }
    
    echo "\nFix complete! Your ESP32 should now work properly.\n";
    echo "\nYou can test it by:\n";
    echo "1. Scanning an unregistered RFID tag with your ESP32\n";
    echo "2. The tag should be accepted in registration mode\n";
    echo "3. No more foreign key constraint errors should appear\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
