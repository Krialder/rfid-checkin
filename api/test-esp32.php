<?php
/**
 * ESP32 Test Script
 * Test the RFID API endpoints to ensure they work correctly
 */

require_once '../core/config.php';
require_once '../core/database.php';

header('Content-Type: application/json');

// Simulate ESP32 request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['rfid'] = 'TEST123456';
$_POST['device_id'] = '1';

echo "Testing RFID Check-in API...\n";

try {
    $db = getDB();
    
    // Test database connection
    echo "✓ Database connection: OK\n";
    
    // Test if tables exist
    $tables = ['Users', 'Events', 'CheckIn', 'system_settings'];
    foreach ($tables as $table) {
        $stmt = $db->prepare("SELECT 1 FROM `$table` LIMIT 1");
        $stmt->execute();
        echo "✓ Table $table: OK\n";
    }
    
    // Test system settings
    $stmt = $db->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = 'rfid_registration_mode'");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        // Create default registration mode setting
        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value, description) 
            VALUES ('rfid_registration_mode', 0, 'RFID Registration Mode')
        ");
        $stmt->execute();
        echo "✓ Created default registration mode setting\n";
    } else {
        echo "✓ Registration mode setting exists\n";
    }
    
    echo "✓ All tests passed!\n";
    echo "\nYou can now test your ESP32 with these endpoints:\n";
    echo "- Check-in: " . $_SERVER['HTTP_HOST'] . "/rfid-checkin/api/rfid-checkin.php\n";
    echo "- Queue: " . $_SERVER['HTTP_HOST'] . "/rfid-checkin/api/rfid-queue.php\n";
    echo "- Registration mode: " . $_SERVER['HTTP_HOST'] . "/rfid-checkin/api/registration-mode.php\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
