<?php
/**
 * Fixed Database Setup Script for RFID Check-in System
 * 
 * This script creates a clean, consistent database structure that matches
 * the application code expectations and fixes all naming inconsistencies.
 * 
 * @author Senior Developer
 * @version 3.0 - Complete Database Restructure
 */

// Database configuration
$config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'rfid_checkin_system',
    'charset' => 'utf8mb4'
];

/**
 * Execute SQL with comprehensive error handling
 */
function executeSQL($pdo, $sql, $description) {
    try {
        $pdo->exec($sql);
        logMessage("✅ $description completed successfully");
        return true;
    } catch (PDOException $e) {
        logMessage("❌ Error in $description: " . $e->getMessage());
        return false;
    }
}

/**
 * Log messages with timestamp
 */
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "<div class='log-entry'>[$timestamp] $message</div>";
    flush();
}

/**
 * Check if table exists
 */
function tableExists($pdo, $tableName) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// HTML output styling
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Fixed Database Setup - RFID Check-in System v3.0</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; }
        .step { background: #f8f9fa; padding: 20px; margin: 15px 0; border-left: 4px solid #007bff; border-radius: 4px; }
        .log-entry { padding: 8px 12px; margin: 4px 0; border-radius: 4px; font-family: monospace; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #b8daff; }
        h1 { color: #2c3e50; margin-bottom: 10px; }
        h2 { color: #34495e; border-bottom: 2px solid #3498db; padding-bottom: 5px; }
        h3 { color: #2980b9; }
        .feature-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin: 20px 0; }
        .feature-card { background: #e8f4fd; padding: 15px; border-radius: 6px; border-left: 4px solid #3498db; }
        .credentials { background: #fff3cd; padding: 15px; border-radius: 6px; border: 2px solid #ffc107; margin: 20px 0; }
        .credentials strong { color: #856404; }
    </style>
</head>
<body>
<div class='container'>";

echo "<div class='header'>";
echo "<h1>🛠️ RFID Check-in System Database Setup v3.0</h1>";
echo "<p><strong>Fixed and Comprehensive Database Structure</strong></p>";
echo "</div>";

echo "<div class='feature-list'>";
echo "<div class='feature-card'><strong>🔧 Fixed Issues:</strong><br>• Consistent table naming<br>• Proper foreign keys<br>• Complete schema</div>";
echo "<div class='feature-card'><strong>🆕 New Features:</strong><br>• Enhanced RFID support<br>• Audit logging<br>• Report generation</div>";
echo "<div class='feature-card'><strong>🔐 Security:</strong><br>• Password policies<br>• Session management<br>• Access control</div>";
echo "</div>";

try {
    // Step 1: Connect to MySQL server
    echo "<div class='step'>";
    echo "<h2>🔌 Database Connection</h2>";
    
    $dsn = "mysql:host={$config['host']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    logMessage("Connected to MySQL server successfully");
    
    // Create database if not exists
    $createDB = "CREATE DATABASE IF NOT EXISTS `{$config['database']}` 
                 CHARACTER SET {$config['charset']} 
                 COLLATE {$config['charset']}_unicode_ci";
    executeSQL($pdo, $createDB, "Database creation/verification");
    
    // Connect to specific database
    $pdo->exec("USE `{$config['database']}`");
    logMessage("Connected to database: {$config['database']}");
    
    echo "</div>";
    
    // Step 2: Drop and recreate tables for clean setup
    echo "<div class='step'>";
    echo "<h2>🧹 Clean Database Setup</h2>";
    
    // Disable foreign key checks
    executeSQL($pdo, "SET FOREIGN_KEY_CHECKS = 0", "Disabling foreign key checks");
    
    // List of tables to drop (in reverse dependency order)
    $tablesToDrop = [
        'activitylog', 'accesslogs', 'reports', 'notifications', 'system_settings', 'rfid_scan_queue',
        'eventgroupassignments', 'usergroupmemberships', 'usergroups',
        'eventregistration', 'checkin', 'events', 'password_resets', 'users', 'rfiddevices'
    ];
    
    foreach ($tablesToDrop as $table) {
        executeSQL($pdo, "DROP TABLE IF EXISTS `$table`", "Dropping table $table");
    }
    
    executeSQL($pdo, "SET FOREIGN_KEY_CHECKS = 1", "Re-enabling foreign key checks");
    
    echo "</div>";
    
    // Step 3: Create core tables
    echo "<div class='step'>";
    echo "<h2>🏗️ Creating Core Tables</h2>";
    
    // users table - Main user management
    $usersSQL = "
    CREATE TABLE users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        first_name VARCHAR(50),
        last_name VARCHAR(50),
        rfid_tag VARCHAR(50) UNIQUE,
        role ENUM('admin', 'user', 'moderator') DEFAULT 'user',
        is_active BOOLEAN DEFAULT TRUE,
        last_login TIMESTAMP NULL,
        failed_login_attempts INT DEFAULT 0,
        locked_until TIMESTAMP NULL,
        password_reset_token VARCHAR(255),
        password_reset_expires TIMESTAMP NULL,
        email_verified BOOLEAN DEFAULT FALSE,
        email_verification_token VARCHAR(255),
        phone VARCHAR(20),
        department VARCHAR(100),
        position VARCHAR(100),
        bio TEXT,
        avatar VARCHAR(255),
        preferences JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_username (username),
        INDEX idx_email (email),
        INDEX idx_rfid_tag (rfid_tag),
        INDEX idx_role_active (role, is_active),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB";
    executeSQL($pdo, $usersSQL, "users table");
    
    // Password resets table
    $passwordResetsSQL = "
    CREATE TABLE password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires TIMESTAMP NOT NULL,
        used BOOLEAN DEFAULT FALSE,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        INDEX idx_token (token),
        INDEX idx_user_expires (user_id, expires),
        INDEX idx_used (used)
    ) ENGINE=InnoDB";
    executeSQL($pdo, $passwordResetsSQL, "Password resets table");
    
    // RFID Devices table
    $rfidDevicesSQL = "
    CREATE TABLE rfiddevices (
        device_id INT AUTO_INCREMENT PRIMARY KEY,
        device_name VARCHAR(100) NOT NULL,
        device_serial VARCHAR(100) UNIQUE,
        ip_address VARCHAR(45),
        location VARCHAR(200),
        status ENUM('active', 'inactive', 'maintenance', 'error') DEFAULT 'active',
        firmware_version VARCHAR(50),
        last_ping TIMESTAMP NULL,
        last_scan TIMESTAMP NULL,
        configuration JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_status (status),
        INDEX idx_location (location),
        INDEX idx_serial (device_serial)
    ) ENGINE=InnoDB";
    executeSQL($pdo, $rfidDevicesSQL, "RFID Devices table");
    
    // events table - Enhanced event management with recurring events and breaks
    $eventsSQL = "
    CREATE TABLE events (
        event_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        description TEXT,
        location VARCHAR(200),
        event_type ENUM('meeting', 'training', 'conference', 'social', 'workshop', 'general', 'recurring', 'other') DEFAULT 'meeting',
        capacity INT DEFAULT NULL,
        start_date DATE NULL,
        end_date DATE NULL,
        start_time TIME NULL,
        end_time TIME NULL,
        
        -- Recurring event configuration
        is_recurring BOOLEAN DEFAULT FALSE,
        recurrence_type ENUM('daily', 'weekly', 'monthly', 'yearly', 'custom') DEFAULT NULL,
        recurrence_interval INT DEFAULT 1, -- Every X days/weeks/months
        recurrence_days JSON DEFAULT NULL, -- For weekly: [1,2,3,4,5] = Monday-Friday
        recurrence_end_date DATE DEFAULT NULL,
        max_occurrences INT DEFAULT NULL,
        exclude_holidays BOOLEAN DEFAULT TRUE,
        
        -- Break/Pause configuration
        has_breaks BOOLEAN DEFAULT FALSE,
        break_schedule JSON DEFAULT NULL, -- [{\"name\":\"Coffee Break\",\"start\":\"10:00\",\"end\":\"10:15\",\"duration\":15}]
        
        -- General settings
        active BOOLEAN DEFAULT TRUE,
        require_checkin BOOLEAN DEFAULT TRUE,
        allow_manual_checkin BOOLEAN DEFAULT TRUE,
        auto_checkout BOOLEAN DEFAULT FALSE,
        auto_checkout_minutes INT DEFAULT 480, -- 8 hours default
        
        -- Metadata
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
        INDEX idx_name (name),
        INDEX idx_type (event_type),
        INDEX idx_active (active),
        INDEX idx_date_range (start_date, end_date),
        INDEX idx_recurring (is_recurring, recurrence_type),
        INDEX idx_created_by (created_by)
    ) ENGINE=InnoDB";
    executeSQL($pdo, $eventsSQL, "Enhanced events table");
    
    // Event Instances table - For managing individual occurrences of recurring events
    $eventInstancesSQL = "
    CREATE TABLE eventinstances (
        instance_id INT AUTO_INCREMENT PRIMARY KEY,
        parent_event_id INT NOT NULL,
        instance_date DATE NOT NULL,
        start_datetime DATETIME NOT NULL,
        end_datetime DATETIME NULL,
        status ENUM('scheduled', 'active', 'completed', 'cancelled', 'postponed') DEFAULT 'scheduled',
        actual_start_time DATETIME NULL,
        actual_end_time DATETIME NULL,
        location_override VARCHAR(200) NULL,
        capacity_override INT NULL,
        notes TEXT NULL,
        is_holiday_conflict BOOLEAN DEFAULT FALSE,
        holiday_name VARCHAR(100) NULL,
        cancelled_reason TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (parent_event_id) REFERENCES events(event_id) ON DELETE CASCADE,
        UNIQUE KEY unique_event_date (parent_event_id, instance_date),
        INDEX idx_date (instance_date),
        INDEX idx_datetime_range (start_datetime, end_datetime),
        INDEX idx_status (status),
        INDEX idx_parent_event (parent_event_id)
    ) ENGINE=InnoDB";
    executeSQL($pdo, $eventInstancesSQL, "Event Instances table");
    
    // Holidays table
    $holidaysSQL = "
    CREATE TABLE holidays (
        holiday_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        date DATE NOT NULL,
        year YEAR NOT NULL,
        type ENUM('national', 'regional', 'religious', 'custom') DEFAULT 'national',
        state_codes JSON DEFAULT NULL, -- State/region codes where applicable: [\"BW\", \"BY\", \"BE\", ...]
        is_active BOOLEAN DEFAULT TRUE,
        description TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        UNIQUE KEY unique_holiday_date (name, date),
        INDEX idx_date (date),
        INDEX idx_year (year),
        INDEX idx_type (type),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB";
    executeSQL($pdo, $holidaysSQL, "Holidays table");
    
    // CheckIn table - Enhanced check-in records with instance support
    $checkinSQL = "
    CREATE TABLE checkin (
        checkin_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        event_id INT,
        instance_id INT NULL, -- Reference to specific event instance for recurring events
        checkin_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        checkout_time TIMESTAMP NULL,
        status ENUM('checked_in', 'checked_out', 'no_show', 'late', 'early') DEFAULT 'checked_in',
        checkin_method ENUM('rfid', 'manual', 'mobile', 'qr_code') DEFAULT 'rfid',
        checkout_method ENUM('rfid', 'manual', 'mobile', 'qr_code', 'auto') DEFAULT NULL,
        device_id INT,
        location VARCHAR(200),
        ip_address VARCHAR(45),
        user_agent TEXT,
        notes TEXT,
        
        -- Break tracking
        break_checkins JSON DEFAULT NULL, -- Track check-ins/outs for breaks
        total_break_minutes INT DEFAULT 0,
        
        -- Timing analytics
        expected_start_time DATETIME NULL,
        delay_minutes INT GENERATED ALWAYS AS (
            CASE 
                WHEN expected_start_time IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, expected_start_time, checkin_time)
                ELSE NULL
            END
        ) STORED,
        duration_minutes INT GENERATED ALWAYS AS (
            CASE 
                WHEN checkout_time IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, checkin_time, checkout_time) - total_break_minutes
                ELSE NULL
            END
        ) STORED,
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE SET NULL,
        FOREIGN KEY (instance_id) REFERENCES eventinstances(instance_id) ON DELETE SET NULL,
        FOREIGN KEY (device_id) REFERENCES rfiddevices(device_id) ON DELETE SET NULL,
        INDEX idx_user_time (user_id, checkin_time),
        INDEX idx_event_time (event_id, checkin_time),
        INDEX idx_instance_time (instance_id, checkin_time),
        INDEX idx_status (status),
        INDEX idx_method (checkin_method),
        INDEX idx_device (device_id),
        INDEX idx_delay (delay_minutes)
    ) ENGINE=InnoDB";
    executeSQL($pdo, $checkinSQL, "Enhanced CheckIn table");
    
    // User Groups table
    $userGroupsSQL = "
    CREATE TABLE usergroups (
        group_id INT AUTO_INCREMENT PRIMARY KEY,
        group_name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        group_type ENUM('department', 'team', 'project', 'custom') DEFAULT 'custom',
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE,
        
        FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT,
        INDEX idx_group_name (group_name),
        INDEX idx_group_type (group_type),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB";
    executeSQL($pdo, $userGroupsSQL, "User Groups table");
    
    // User Group Memberships table
    $userGroupMembershipsSQL = "
    CREATE TABLE usergroupmemberships (
        membership_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        group_id INT NOT NULL,
        role ENUM('member', 'leader', 'admin') DEFAULT 'member',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        added_by INT NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (group_id) REFERENCES usergroups(group_id) ON DELETE CASCADE,
        FOREIGN KEY (added_by) REFERENCES users(user_id) ON DELETE RESTRICT,
        UNIQUE KEY unique_user_group (user_id, group_id),
        INDEX idx_user_id (user_id),
        INDEX idx_group_id (group_id),
        INDEX idx_role (role),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB";
    executeSQL($pdo, $userGroupMembershipsSQL, "User Group Memberships table");
    
    // Event Group Assignments table
    $eventGroupAssignmentsSQL = "
    CREATE TABLE eventgroupassignments (
        assignment_id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        group_id INT NOT NULL,
        assigned_by INT NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE,
        
        FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
        FOREIGN KEY (group_id) REFERENCES usergroups(group_id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE RESTRICT,
        UNIQUE KEY unique_event_group (event_id, group_id),
        INDEX idx_event_id (event_id),
        INDEX idx_group_id (group_id),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB";
    executeSQL($pdo, $eventGroupAssignmentsSQL, "Event Group Assignments table");
    
    echo "</div>";
    
    // Step 4: Create management tables
    echo "<div class='step'>";
    echo "<h2>📊 Creating Management Tables</h2>";
    
    // Event Registration table
    $eventRegistrationSQL = "
    CREATE TABLE eventregistration (
        registration_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        event_id INT NOT NULL,
        registration_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        status ENUM('registered', 'cancelled', 'waitlist', 'confirmed') DEFAULT 'registered',
        notes TEXT,
        
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_event_reg (user_id, event_id),
        INDEX idx_status (status),
        INDEX idx_registration_time (registration_time)
    ) ENGINE=InnoDB";
    executeSQL($pdo, $eventRegistrationSQL, "Event Registration table");
    
    // Activity Log table
    $activityLogSQL = "
    CREATE TABLE activitylog (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        metadata JSON,
        ip_address VARCHAR(45),
        user_agent TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
        INDEX idx_user_id (user_id),
        INDEX idx_action (action),
        INDEX idx_timestamp (timestamp)
    ) ENGINE=InnoDB";
    executeSQL($pdo, $activityLogSQL, "Activity Log table");
    
    // Access Logs table
    $accessLogsSQL = "
    CREATE TABLE accesslogs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(100) NOT NULL,
        resource VARCHAR(200),
        ip_address VARCHAR(45),
        user_agent TEXT,
        status ENUM('success', 'failure', 'blocked') DEFAULT 'success',
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        device_id INT,
        
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
        FOREIGN KEY (device_id) REFERENCES rfiddevices(device_id) ON DELETE SET NULL,
        INDEX idx_user_id (user_id),
        INDEX idx_action (action),
        INDEX idx_timestamp (timestamp),
        INDEX idx_status (status)
    ) ENGINE=InnoDB";
    executeSQL($pdo, $accessLogsSQL, "Access Logs table");
    
    // System Settings table
    $systemSettingsSQL = "
    CREATE TABLE system_settings (
        setting_id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
        description TEXT,
        category VARCHAR(50),
        is_public BOOLEAN DEFAULT FALSE,
        updated_by INT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL,
        INDEX idx_category (category),
        INDEX idx_is_public (is_public)
    ) ENGINE=InnoDB";
    executeSQL($pdo, $systemSettingsSQL, "System Settings table");
    
    // RFID Scan Queue table for Registration Mode
    $rfidScanQueueSQL = "
    CREATE TABLE rfid_scan_queue (
        queue_id INT AUTO_INCREMENT PRIMARY KEY,
        tag_value VARCHAR(50) NOT NULL,
        device_id INT DEFAULT 1,
        source_ip VARCHAR(45),
        source VARCHAR(50) DEFAULT 'hardware',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_created_at (created_at),
        INDEX idx_tag_value (tag_value),
        INDEX idx_device_id (device_id)
    ) ENGINE=InnoDB";
    executeSQL($pdo, $rfidScanQueueSQL, "RFID Scan Queue table");
    
    // Notifications table
    $notificationsSQL = "
    CREATE TABLE notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        title VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'warning', 'error', 'success') DEFAULT 'info',
        read_status BOOLEAN DEFAULT FALSE,
        action_url VARCHAR(500),
        expires_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        INDEX idx_user_read (user_id, read_status),
        INDEX idx_created_at (created_at),
        INDEX idx_type (type)
    ) ENGINE=InnoDB";
    executeSQL($pdo, $notificationsSQL, "Notifications table");
    
    // Reports table
    $reportsSQL = "
    CREATE TABLE reports (
        report_id INT AUTO_INCREMENT PRIMARY KEY,
        report_name VARCHAR(200) NOT NULL,
        report_type ENUM('attendance', 'usage', 'device_status', 'user_activity', 'custom') NOT NULL,
        parameters JSON,
        generated_by INT,
        file_path VARCHAR(500),
        file_size INT,
        format ENUM('pdf', 'excel', 'csv', 'json') DEFAULT 'pdf',
        status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
        event_id INT,
        date_from DATE,
        date_to DATE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME,
        
        FOREIGN KEY (generated_by) REFERENCES users(user_id) ON DELETE SET NULL,
        FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE SET NULL,
        INDEX idx_report_type (report_type),
        INDEX idx_generated_by (generated_by),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB";
    executeSQL($pdo, $reportsSQL, "Reports table");
    
    echo "</div>";
    
    // Step 5: Insert default data
    echo "<div class='step'>";
    echo "<h2>📋 Inserting Default Data</h2>";
    
    // Create default admin user
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $adminSQL = "
    INSERT INTO users (username, email, password, first_name, last_name, role, is_active, email_verified)
    VALUES ('admin', 'admin@rfidcheckin.local', ?, 'System', 'Administrator', 'admin', 1, 1)";
    
    $stmt = $pdo->prepare($adminSQL);
    $stmt->execute([$adminPassword]);
    logMessage("Default admin user created");
    
    // Insert system settings
    $settingsData = [
        ['system_name', 'RFID Check-in System', 'string', 'Name of the system', 'general', 1],
        ['max_login_attempts', '5', 'number', 'Maximum login attempts before lockout', 'security', 0],
        ['session_timeout', '3600', 'number', 'Session timeout in seconds', 'security', 0],
        ['enable_rfid', 'true', 'boolean', 'Enable RFID check-in functionality', 'features', 0],
        ['enable_mobile_checkin', 'true', 'boolean', 'Enable mobile check-in', 'features', 0],
        ['default_event_duration', '120', 'number', 'Default event duration in minutes', 'events', 0],
        ['company_name', 'Your Company Name', 'string', 'Company name for branding', 'general', 1],
        ['support_email', 'support@yourcompany.com', 'string', 'Support contact email', 'general', 1],
        ['rfid_registration_mode', '0', 'boolean', 'Enable RFID registration mode for accepting unregistered tags', 'rfid', 0]
    ];
    
    $settingsSQL = "INSERT INTO system_settings (setting_key, setting_value, setting_type, description, category, is_public) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($settingsSQL);
    
    foreach ($settingsData as $setting) {
        $stmt->execute($setting);
    }
    logMessage("System settings inserted");
    
    // Insert additional legacy settings for code compatibility (avoiding duplicates)
    $additionalSettingsData = [
        ['auto_logout_time', '1800', 'string', 'Automatic logout time in seconds', 'security', 0],
        ['enable_notifications', 'true', 'boolean', 'Enable system notifications', 'features', 0],
        ['backup_retention_days', '30', 'number', 'Number of days to retain backups', 'maintenance', 0],
        ['debug_mode', 'false', 'boolean', 'Enable debug mode for troubleshooting', 'system', 0]
    ];
    
    $additionalSettingsSQL = "INSERT INTO system_settings (setting_key, setting_value, setting_type, description, category, is_public) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($additionalSettingsSQL);
    
    foreach ($additionalSettingsData as $setting) {
        $stmt->execute($setting);
    }
    logMessage("Additional system settings inserted");
    
    // Insert sample RFID device
    $deviceSQL = "INSERT INTO rfiddevices (device_name, device_serial, ip_address, location, status) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($deviceSQL);
    $stmt->execute(['Main Entrance Reader', 'RFID001', '192.168.1.100', 'Main Entrance', 'active']);
    logMessage("Sample RFID device added");
    
    // Insert sample events with new structure
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $nextWeek = date('Y-m-d', strtotime('+1 week'));
    $nextMonth = date('Y-m-d', strtotime('+1 month'));
    $next3Months = date('Y-m-d', strtotime('+3 months'));
    $today = date('Y-m-d');
    
    $eventsData = [
        ['Weekly Team Meeting', 'Regular team synchronization meeting', 'Conference Room A', 'meeting', 20, $tomorrow, $tomorrow, 1, 'weekly', 1, '[1,2,3,4,5]', $next3Months, NULL, 1, '[{"name":"Break","start":"10:30","end":"10:45","duration":15}]'],
        ['Daily Standup', 'Quick daily team sync', 'Open Office Area', 'meeting', 15, $today, $today, 1, 'daily', 1, NULL, $nextMonth, NULL, 0, NULL],
        ['Tech Conference 2025', 'Annual technology conference', 'Main Auditorium', 'conference', 200, $nextWeek, $nextWeek, 0, NULL, NULL, NULL, NULL, NULL, 1, '[{"name":"Coffee Break","start":"10:30","end":"10:45","duration":15},{"name":"Lunch","start":"12:00","end":"13:00","duration":60},{"name":"Afternoon Break","start":"15:00","end":"15:15","duration":15}]']
    ];
    
    foreach ($eventsData as $event) {
        $eventSQL = "INSERT INTO events (
            name, description, location, event_type, capacity, start_date, end_date, 
            is_recurring, recurrence_type, recurrence_interval, recurrence_days, 
            recurrence_end_date, max_occurrences, has_breaks, break_schedule, created_by, active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)";
        $stmt = $pdo->prepare($eventSQL);
        $stmt->execute([
            $event[0], // name
            $event[1], // description  
            $event[2], // location
            $event[3], // event_type
            $event[4], // capacity
            $event[5], // start_date
            $event[6], // end_date
            $event[7], // is_recurring
            $event[8], // recurrence_type
            $event[9], // recurrence_interval
            $event[10], // recurrence_days
            $event[11], // recurrence_end_date
            $event[12], // max_occurrences
            $event[13], // has_breaks
            $event[14], // break_schedule
        ]);
    }
    logMessage("Enhanced sample events created");
    
    // Insert holidays for current and next year
    $currentYear = date('Y');
    $nextYear = $currentYear + 1;
    
    $holidays = [
        // National holidays that are the same every year
        ['Neujahr', 'national', null],
        ['Tag der Arbeit', 'national', null], 
        ['Tag der Deutschen Einheit', 'national', null],
        ['Weihnachtstag (1.)', 'national', null],
        ['Weihnachtstag (2.)', 'national', null],
        
        // Regional holidays
        ['Heilige Drei Könige', 'regional', '["BW","BY","ST"]'],
        ['Fronleichnam', 'regional', '["BW","BY","HE","NW","RP","SL"]'],
        ['Mariä Himmelfahrt', 'regional', '["BY","SL"]'],
        ['Reformationstag', 'regional', '["BB","MV","SN","ST","TH"]'],
        ['Allerheiligen', 'regional', '["BW","BY","NW","RP","SL"]'],
        ['Buß- und Bettag', 'regional', '["SN"]']
    ];
    
    // Function to calculate Easter Sunday for holidays
    function calculateEaster($year) {
        $a = $year % 19;
        $b = intval($year / 100);
        $c = $year % 100;
        $d = intval($b / 4);
        $e = $b % 4;
        $f = intval(($b + 8) / 25);
        $g = intval(($b - $f + 1) / 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intval($c / 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intval(($a + 11 * $h + 22 * $l) / 451);
        $n = intval(($h + $l - 7 * $m + 114) / 31);
        $p = ($h + $l - 7 * $m + 114) % 31;
        return mktime(0, 0, 0, $n, $p + 1, $year);
    }
    
    $holidaySQL = "INSERT INTO holidays (name, date, year, type, state_codes, description) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($holidaySQL);
    
    foreach ([$currentYear, $nextYear] as $year) {
        $easter = calculateEaster($year);
        
        // Fixed date holidays
        $fixedHolidays = [
            ['Neujahr', "$year-01-01", 'national', null, 'New Year\'s Day'],
            ['Tag der Arbeit', "$year-05-01", 'national', null, 'Labour Day'],
            ['Tag der deutschen Einheit', "$year-10-03", 'national', null, 'German Unity Day'],
            ['Weihnachtstag (1.)', "$year-12-25", 'national', null, 'Christmas Day'],
            ['Weihnachtstag (2.)', "$year-12-26", 'national', null, 'Boxing Day'],
            ['Heilige Drei Könige', "$year-01-06", 'regional', '["BW","BY","ST"]', 'Epiphany'],
            ['Mariä Himmelfahrt', "$year-08-15", 'regional', '["BY","SL"]', 'Assumption of Mary'],
            ['Reformationstag', "$year-10-31", 'regional', '["BB","MV","SN","ST","TH"]', 'Reformation Day'],
            ['Allerheiligen', "$year-11-01", 'regional', '["BW","BY","NW","RP","SL"]', 'All Saints Day']
        ];
        
        // Easter-based holidays
        $easterHolidays = [
            ['Karfreitag', date('Y-m-d', strtotime('-2 days', $easter)), 'national', null, 'Good Friday'],
            ['Ostermontag', date('Y-m-d', strtotime('+1 day', $easter)), 'national', null, 'Easter Monday'],
            ['Christi Himmelfahrt', date('Y-m-d', strtotime('+39 days', $easter)), 'national', null, 'Ascension Day'],
            ['Pfingstmontag', date('Y-m-d', strtotime('+50 days', $easter)), 'national', null, 'Whit Monday'],
            ['Fronleichnam', date('Y-m-d', strtotime('+60 days', $easter)), 'regional', '["BW","BY","HE","NW","RP","SL"]', 'Corpus Christi']
        ];
        
        // Insert all holidays for this year
        foreach (array_merge($fixedHolidays, $easterHolidays) as $holiday) {
            $stmt->execute([
                $holiday[0], // name
                $holiday[1], // date
                $year,       // year
                $holiday[2], // type
                $holiday[3], // state_codes
                $holiday[4]  // description
            ]);
        }
    }
    logMessage("Holidays inserted for $currentYear and $nextYear");
    
    // Insert sample user groups
    $groupsData = [
        ['IT Department', 'Information Technology department staff', 'department'],
        ['Marketing Team', 'Marketing and communications team', 'team'],
        ['Management', 'Senior management and executives', 'department'],
        ['Project Alpha', 'Alpha project team members', 'project'],
        ['HR Department', 'Human Resources department', 'department'],
        ['Development Team', 'Software development team', 'team'],
        ['QA Team', 'Quality Assurance team', 'team'],
        ['Sales Team', 'Sales and business development', 'team'],
        ['All Staff', 'All company employees', 'custom']
    ];
    
    $groupSQL = "INSERT INTO usergroups (group_name, description, group_type, created_by) VALUES (?, ?, ?, 1)";
    $stmt = $pdo->prepare($groupSQL);
    
    foreach ($groupsData as $group) {
        $stmt->execute($group);
    }
    logMessage("Sample user groups created");
    
    // Add admin user to all groups as admin
    $adminGroupSQL = "INSERT INTO usergroupmemberships (user_id, group_id, role, added_by) 
                      SELECT 1, group_id, 'admin', 1 FROM usergroups";
    $pdo->exec($adminGroupSQL);
    logMessage("Admin user added to all groups");
    
    // Assign some groups to sample events for demonstration
    $eventGroupAssignments = [
        [1, 1], // Weekly Team Meeting -> IT Department
        [1, 6], // Weekly Team Meeting -> Development Team
        [2, 1], // Daily Standup -> IT Department
        [2, 6], // Daily Standup -> Development Team
        [2, 7], // Daily Standup -> QA Team (same users will be deduplicated)
        [3, 9]  // Tech Conference -> All Staff
    ];
    
    $assignGroupSQL = "INSERT INTO eventgroupassignments (event_id, group_id, assigned_by) VALUES (?, ?, 1)";
    $stmt = $pdo->prepare($assignGroupSQL);
    
    foreach ($eventGroupAssignments as $assignment) {
        $stmt->execute($assignment);
    }
    logMessage("Sample event-group assignments created");
    
    echo "</div>";
    
    // Step 6: Create database views
    echo "<div class='step'>";
    echo "<h2>👁️ Creating Database Views</h2>";
    
    $viewActiveUsersSQL = "
    CREATE OR REPLACE VIEW view_active_users AS
    SELECT 
        user_id, 
        CONCAT(first_name, ' ', COALESCE(last_name, '')) as full_name, 
        username,
        email, 
        role, 
        department,
        last_login, 
        created_at,
        rfid_tag
    FROM users 
    WHERE is_active = TRUE";
    executeSQL($pdo, $viewActiveUsersSQL, "Active users view");
    
    $viewCurrentEventsSQL = "
    CREATE OR REPLACE VIEW view_current_events AS
    SELECT 
        e.*, 
        CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as created_by_name,
        (SELECT COUNT(*) FROM checkin c WHERE c.event_id = e.event_id AND c.status = 'checked_in') as current_checkins,
        CASE 
            WHEN e.is_recurring = 1 THEN 
                (SELECT COUNT(*) FROM eventinstances ei WHERE ei.parent_event_id = e.event_id AND ei.status = 'active')
            ELSE 1
        END as active_instances
    FROM events e
    LEFT JOIN users u ON e.created_by = u.user_id
    WHERE e.active = TRUE 
    AND (
        (e.is_recurring = 0 AND DATE(e.start_date) <= CURDATE() AND (e.end_date IS NULL OR DATE(e.end_date) >= CURDATE()))
        OR 
        (e.is_recurring = 1 AND (e.recurrence_end_date IS NULL OR e.recurrence_end_date >= CURDATE()))
    )";
    executeSQL($pdo, $viewCurrentEventsSQL, "Enhanced current events view");
    
    $viewUpcomingEventsSQL = "
    CREATE OR REPLACE VIEW view_upcoming_events AS
    SELECT 
        e.*, 
        CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as created_by_name,
        (SELECT COUNT(*) FROM eventregistration er WHERE er.event_id = e.event_id AND er.status = 'registered') as registered_count,
        CASE 
            WHEN e.is_recurring = 1 THEN 
                (SELECT COUNT(*) FROM eventinstances ei WHERE ei.parent_event_id = e.event_id AND ei.instance_date > CURDATE())
            ELSE 1
        END as future_instances
    FROM events e
    LEFT JOIN users u ON e.created_by = u.user_id
    WHERE e.active = TRUE 
    AND (
        (e.is_recurring = 0 AND DATE(e.start_date) > CURDATE())
        OR 
        (e.is_recurring = 1 AND (e.recurrence_end_date IS NULL OR e.recurrence_end_date > CURDATE()))
    )
    ORDER BY e.start_date ASC";
    executeSQL($pdo, $viewUpcomingEventsSQL, "Enhanced upcoming events view");
    
    // New view for today's events with instance support
    $viewTodayEventsSQL = "
    CREATE OR REPLACE VIEW view_today_events AS
    SELECT 
        e.event_id,
        e.name,
        e.location,
        e.event_type,
        e.start_time,
        e.end_time,
        e.has_breaks,
        e.break_schedule,
        ei.instance_id,
        ei.instance_date,
        ei.start_datetime,
        ei.end_datetime,
        ei.status as instance_status,
        CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as created_by_name
    FROM events e
    LEFT JOIN users u ON e.created_by = u.user_id
    LEFT JOIN eventinstances ei ON e.event_id = ei.parent_event_id AND ei.instance_date = CURDATE()
    WHERE e.active = TRUE 
    AND (
        (e.is_recurring = 0 AND DATE(e.start_date) = CURDATE())
        OR 
        (e.is_recurring = 1 AND ei.instance_id IS NOT NULL)
    )
    ORDER BY COALESCE(ei.start_datetime, TIMESTAMP(e.start_date, e.start_time)) ASC";
    executeSQL($pdo, $viewTodayEventsSQL, "Today's events view");
    
    echo "</div>";
    
    // Step 7: Create indexes for performance
    echo "<div class='step'>";
    echo "<h2>⚡ Creating Performance Indexes</h2>";
    
    $indexes = [
        "CREATE INDEX idx_checkin_user_time ON checkin(user_id, checkin_time DESC)",
        "CREATE INDEX idx_events_time_active ON events(start_time, active)",
        "CREATE INDEX idx_accesslog_time_action ON accesslogs(timestamp DESC, action)",
        "CREATE INDEX idx_users_role_active ON users(role, is_active)",
        "CREATE INDEX idx_checkin_status_time ON checkin(status, checkin_time)",
        "CREATE INDEX idx_events_type_time ON events(event_type, start_time)"
    ];
    
    foreach ($indexes as $index) {
        executeSQL($pdo, $index, "Performance index");
    }
    
    echo "</div>";
    
    // Step 8: Final verification and summary
    echo "<div class='step'>";
    echo "<h2>✅ Database Setup Complete</h2>";
    
    // Count records in each table
    $tables = [
        'users', 'events', 'eventinstances', 'checkin', 'rfiddevices', 
        'activitylog', 'accesslogs', 'system_settings', 'rfid_scan_queue', 'notifications', 
        'reports', 'holidays', 'eventregistration'
    ];
    
    logMessage("📊 Table Summary:");
    foreach ($tables as $table) {
        if (tableExists($pdo, $table)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table`");
            $stmt->execute();
            $count = $stmt->fetchColumn();
            logMessage("   $table: $count records");
        }
    }
    
    echo "</div>";
    
    // Display credentials
    echo "<div class='credentials'>";
    echo "<h3>🔐 Default Login Credentials</h3>";
    echo "<strong>Username:</strong> admin<br>";
    echo "<strong>Email:</strong> admin@rfidcheckin.local<br>";
    echo "<strong>Password:</strong> admin123<br>";
    echo "<p><strong>⚠️ IMPORTANT:</strong> Change this password immediately after first login!</p>";
    echo "</div>";
    
    // Next steps
    echo "<div class='step'>";
    echo "<h2>🚀 Next Steps</h2>";
    echo "<ol>";
    echo "<li>Update your <code>core/config.php</code> file with correct database settings</li>";
    echo "<li>Log in using the admin credentials above</li>";
    echo "<li>Change the default admin password</li>";
    echo "<li>Create additional users through the admin interface</li>";
    echo "<li>Configure RFID devices if using hardware integration</li>";
    echo "<li>Set up events and start using the system</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Database Setup Failed</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check:</p>";
    echo "<ul>";
    echo "<li>MySQL server is running</li>";
    echo "<li>Database credentials are correct</li>";
    echo "<li>MySQL user has CREATE DATABASE privileges</li>";
    echo "<li>PHP PDO MySQL extension is installed</li>";
    echo "</ul>";
    echo "</div>";
}

echo "</div></body></html>";
?>
