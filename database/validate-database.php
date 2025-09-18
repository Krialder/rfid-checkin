<?php
/**
 * Database Validation and Repair Script
 * Validates database structure and fixes common issues
 * 
 * @author Senior Developer
 * @version 1.0 - Database Validation and Repair
 */

require_once '../core/config.php';
require_once '../core/database.php';

// Set content type for proper output
header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Validation - RFID Check-in System</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .step { background: #f8f9fa; padding: 20px; margin: 15px 0; border-left: 4px solid #007bff; border-radius: 4px; }
        .success { color: #155724; background: #d4edda; padding: 10px; border-radius: 4px; margin: 5px 0; }
        .error { color: #721c24; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 5px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px; margin: 5px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 4px; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px 12px; border: 1px solid #ddd; text-align: left; }
        th { background: #f8f9fa; font-weight: bold; }
        .fix-btn { background: #28a745; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; }
        .fix-btn:hover { background: #218838; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 Database Validation and Repair Tool</h1>";
echo "<p>This tool validates your database structure and provides fixes for common issues.</p>";

try {
    $db = getDB();
    echo "<div class='success'>✅ Database connection successful</div>";
    
    // Get database name
    $dbName = DB_NAME;
    
    echo "<div class='step'>";
    echo "<h2>📊 Database Overview</h2>";
    
    // Check database existence
    $stmt = $db->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
    $stmt->execute([$dbName]);
    if ($stmt->fetch()) {
        echo "<div class='success'>✅ Database '$dbName' exists</div>";
    } else {
        echo "<div class='error'>❌ Database '$dbName' does not exist</div>";
        echo "<p>Please run the database setup script first.</p>";
        exit;
    }
    
    // Get current tables
    $stmt = $db->query("SHOW TABLES");
    $currentTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Current Tables:</h3>";
    if (empty($currentTables)) {
        echo "<div class='warning'>⚠️ No tables found in database</div>";
    } else {
        echo "<ul>";
        foreach ($currentTables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
    }
    
    echo "</div>";
    
    // Expected table structure
    $expectedTables = [
        'users' => 'Core user management table',
        'events' => 'Event management table', 
        'checkin' => 'Check-in records table',
        'rfiddevices' => 'RFID device management table',
        'activitylog' => 'User activity logging table',
        'accesslogs' => 'System access logging table',
        'system_settings' => 'System configuration table',
        'notifications' => 'User notifications table',
        'reports' => 'Report management table',
        'eventregistration' => 'Event registration table',
        'eventinstances' => 'Event instances table',
        'eventgroupassignments' => 'Event group assignments table',
        'usergroupmemberships' => 'User group memberships table',
        'usergroups' => 'User groups table',
        'holidays' => 'Holidays table',
        'rfid_scan_queue' => 'RFID scan queue table',
        'password_resets' => 'Password reset tokens table'
    ];
    
    echo "<div class='step'>";
    echo "<h2>🔍 Table Structure Validation</h2>";
    
    $missingTables = [];
    $existingTables = [];
    
    foreach ($expectedTables as $tableName => $description) {
        if (in_array($tableName, $currentTables)) {
            echo "<div class='success'>✅ $tableName: $description</div>";
            $existingTables[] = $tableName;
        } else {
            echo "<div class='error'>❌ Missing: $tableName ($description)</div>";
            $missingTables[] = $tableName;
        }
    }
    
    echo "</div>";
    
    // Check for old/incorrect table names
    echo "<div class='step'>";
    echo "<h2>🔄 Legacy Table Detection</h2>";
    
    $legacyTables = [];
    $legacyMappings = [
        'Users' => 'users',
        'Events' => 'events',
        'CheckIn' => 'checkin', 
        'UserGroups' => 'usergroups',
        'RFIDDevices' => 'rfiddevices',
        'SystemSettings' => 'system_settings',
        'ActivityLog' => 'activitylog',
        'AccessLogs' => 'accesslogs'
    ];
    
    foreach ($legacyMappings as $oldName => $newName) {
        if (in_array($oldName, $currentTables)) {
            echo "<div class='warning'>⚠️ Found legacy table: $oldName (should be $newName)</div>";
            $legacyTables[$oldName] = $newName;
        }
    }
    
    if (empty($legacyTables)) {
        echo "<div class='success'>✅ No legacy tables found</div>";
    }
    
    echo "</div>";
    
    // Validate table structures for existing tables
    echo "<div class='step'>";
    echo "<h2>🏗️ Table Structure Details</h2>";
    
    foreach ($existingTables as $tableName) {
        echo "<h3>Table: $tableName</h3>";
        
        try {
            $stmt = $db->query("DESCRIBE `$tableName`");
            $columns = $stmt->fetchAll();
            
            if (!empty($columns)) {
                echo "<table>";
                echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
                
                foreach ($columns as $column) {
                    echo "<tr>";
                    echo "<td>{$column['Field']}</td>";
                    echo "<td>{$column['Type']}</td>";
                    echo "<td>{$column['Null']}</td>";
                    echo "<td>{$column['Key']}</td>";
                    echo "<td>{$column['Default']}</td>";
                    echo "<td>{$column['Extra']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
                
                // Count records
                $stmt = $db->prepare("SELECT COUNT(*) FROM `$tableName`");
                $stmt->execute();
                $count = $stmt->fetchColumn();
                echo "<div class='info'>📊 Records: $count</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>❌ Error reading table structure: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
    
    echo "</div>";
    
    // Check for required data
    echo "<div class='step'>";
    echo "<h2>👤 Data Validation</h2>";
    
    if (in_array('users', $existingTables)) {
        $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $adminCount = $stmt->fetchColumn();
        
        if ($adminCount > 0) {
            echo "<div class='success'>✅ Admin users found: $adminCount</div>";
            
            // Show admin users
            $stmt = $db->query("SELECT username, email, first_name, last_name, is_active FROM users WHERE role = 'admin'");
            $admins = $stmt->fetchAll();
            
            echo "<h4>Admin Users:</h4>";
            echo "<table>";
            echo "<tr><th>Username</th><th>Email</th><th>Name</th><th>Status</th></tr>";
            foreach ($admins as $admin) {
                $name = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''));
                $status = $admin['is_active'] ? 'Active' : 'Inactive';
                echo "<tr>";
                echo "<td>{$admin['username']}</td>";
                echo "<td>{$admin['email']}</td>";
                echo "<td>$name</td>";
                echo "<td>$status</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='error'>❌ No admin users found</div>";
        }
        
        $stmt = $db->query("SELECT COUNT(*) FROM users");
        $totalUsers = $stmt->fetchColumn();
        echo "<div class='info'>📊 Total users: $totalUsers</div>";
    }
    
    if (in_array('events', $existingTables)) {
        $stmt = $db->query("SELECT COUNT(*) FROM events WHERE active = 1");
        $activeEvents = $stmt->fetchColumn();
        echo "<div class='info'>📅 Active events: $activeEvents</div>";
    }
    
    if (in_array('checkin', $existingTables)) {
        $stmt = $db->query("SELECT COUNT(*) FROM checkin");
        $totalCheckins = $stmt->fetchColumn();
        echo "<div class='info'>✅ Total check-ins: $totalCheckins</div>";
    }
    
    echo "</div>";
    
    // Configuration validation
    echo "<div class='step'>";
    echo "<h2>⚙️ Configuration Validation</h2>";
    
    // Check config constants
    $configItems = [
        'DB_HOST' => DB_HOST,
        'DB_NAME' => DB_NAME,
        'DB_USER' => DB_USER,
        'DB_CHARSET' => DB_CHARSET,
        'BASE_URL' => defined('BASE_URL') ? BASE_URL : 'Not defined',
        'SESSION_LIFETIME' => defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 'Not defined',
        'DEBUG_MODE' => defined('DEBUG_MODE') ? (DEBUG_MODE ? 'true' : 'false') : 'Not defined'
    ];
    
    echo "<table>";
    echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";
    
    foreach ($configItems as $key => $value) {
        $status = ($value !== 'Not defined') ? '✅ OK' : '❌ Missing';
        echo "<tr>";
        echo "<td>$key</td>";
        echo "<td>" . htmlspecialchars($value) . "</td>";
        echo "<td>$status</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "</div>";
    
    // Recommendations
    echo "<div class='step'>";
    echo "<h2>💡 Recommendations</h2>";
    
    if (!empty($missingTables)) {
        echo "<div class='warning'>";
        echo "<h4>Missing Tables:</h4>";
        echo "<p>The following tables are missing from your database:</p>";
        echo "<ul>";
        foreach ($missingTables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
        echo "<p><strong>Solution:</strong> Run the database setup script to create missing tables.</p>";
        echo "</div>";
    }
    
    if (!empty($legacyTables)) {
        echo "<div class='warning'>";
        echo "<h4>Legacy Table Names:</h4>";
        echo "<p>Consider running the table migration script to rename these tables for consistency.</p>";
        echo "</div>";
    }
    
    if (in_array('Users', $existingTables)) {
        $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $adminCount = $stmt->fetchColumn();
        
        if ($adminCount == 0) {
            echo "<div class='error'>";
            echo "<h4>No Admin Users:</h4>";
            echo "<p>Your system has no admin users. This will prevent access to admin functions.</p>";
            echo "<p><strong>Solution:</strong> Create an admin user through the database setup script or SQL command.</p>";
            echo "</div>";
        }
    }
    
    // Security recommendations
    echo "<div class='info'>";
    echo "<h4>Security Recommendations:</h4>";
    echo "<ul>";
    echo "<li>Ensure DEBUG_MODE is set to false in production</li>";
    echo "<li>Use strong passwords for all admin accounts</li>";
    echo "<li>Regularly update the system and review access logs</li>";
    echo "<li>Enable HTTPS for production deployment</li>";
    echo "<li>Backup your database regularly</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>🎉 Validation Complete</h2>";
    echo "<p>Database validation finished. Review the results above and follow any recommendations.</p>";
    
    if (empty($missingTables) && empty($legacyTables)) {
        echo "<div class='success'>🎉 Your database structure looks good!</div>";
    } else {
        echo "<div class='warning'>⚠️ Some issues were found. Please review the recommendations above.</div>";
    }
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Validation Failed</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "</div></body></html>";
?>
