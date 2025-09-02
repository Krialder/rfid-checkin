<?php
/**
 * Database Diagnostic Script
 * Check what tables exist and identify missing components
 */

require_once '../core/config.php';

header('Content-Type: text/plain');

echo "=== DATABASE DIAGNOSTIC ===\n\n";

try {
    // Try to connect to database
    $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Database connection successful\n";
    echo "Host: " . DB_HOST . "\n";
    echo "User: " . DB_USER . "\n\n";
    
    // Check if database exists
    $stmt = $pdo->prepare("SHOW DATABASES LIKE ?");
    $stmt->execute([DB_NAME]);
    $dbExists = $stmt->fetch();
    
    if (!$dbExists) {
        echo "✗ Database '" . DB_NAME . "' does not exist!\n";
        echo "Creating database...\n";
        $pdo->exec("CREATE DATABASE `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "✓ Database created\n\n";
    } else {
        echo "✓ Database '" . DB_NAME . "' exists\n\n";
    }
    
    // Connect to the specific database
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check what tables exist
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "EXISTING TABLES:\n";
    if (empty($tables)) {
        echo "✗ No tables found in database!\n\n";
    } else {
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table`");
            $stmt->execute();
            $count = $stmt->fetchColumn();
            echo "  ✓ $table ($count records)\n";
        }
        echo "\n";
    }
    
    // Check for required tables
    $requiredTables = [
        'Users',
        'Events', 
        'CheckIn',
        'AccessLogs',
        'system_settings',
        'rfid_scan_queue',
        'RFIDDevices'
    ];
    
    echo "REQUIRED TABLES CHECK:\n";
    $missingTables = [];
    foreach ($requiredTables as $table) {
        if (in_array($table, $tables)) {
            echo "  ✓ $table - exists\n";
        } else {
            echo "  ✗ $table - MISSING\n";
            $missingTables[] = $table;
        }
    }
    
    if (!empty($missingTables)) {
        echo "\n❌ MISSING TABLES DETECTED!\n";
        echo "Missing: " . implode(', ', $missingTables) . "\n\n";
        echo "SOLUTION: Run the database setup script:\n";
        echo "http://your-server/rfid-checkin/database/setup-database.php\n\n";
    } else {
        echo "\n✅ All required tables exist!\n\n";
    }
    
    // If system_settings exists, check registration mode
    if (in_array('system_settings', $tables)) {
        echo "SYSTEM SETTINGS CHECK:\n";
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings");
        $stmt->execute();
        $settings = $stmt->fetchAll();
        
        if (empty($settings)) {
            echo "  ⚠ system_settings table is empty\n";
            echo "  Creating default registration mode setting...\n";
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, description) 
                VALUES ('rfid_registration_mode', 0, 'RFID Registration Mode - allows unregistered tags')
            ");
            $stmt->execute();
            echo "  ✓ Default setting created\n";
        } else {
            foreach ($settings as $setting) {
                echo "  ✓ {$setting['setting_key']} = {$setting['setting_value']}\n";
            }
        }
        echo "\n";
    }
    
    // Check if we have any users
    if (in_array('Users', $tables)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Users");
        $stmt->execute();
        $userCount = $stmt->fetchColumn();
        
        echo "USERS CHECK:\n";
        if ($userCount == 0) {
            echo "  ⚠ No users in database\n";
            echo "  You'll need to create an admin user\n";
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Users WHERE role = 'admin'");
            $stmt->execute();
            $adminCount = $stmt->fetchColumn();
            echo "  ✓ $userCount total users ($adminCount admins)\n";
        }
        echo "\n";
    }
    
    echo "=== DIAGNOSIS COMPLETE ===\n";
    
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
    echo "\nCHECK YOUR CONFIG:\n";
    echo "DB_HOST = " . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED') . "\n";
    echo "DB_NAME = " . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED') . "\n";
    echo "DB_USER = " . (defined('DB_USER') ? DB_USER : 'NOT DEFINED') . "\n";
    echo "DB_PASS = " . (defined('DB_PASS') ? '[hidden]' : 'NOT DEFINED') . "\n";
}
?>
