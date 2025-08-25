<?php
/**
 * Enhanced Database Generation Script for Electronic Check-in System
 * 
 * This script will:
 * 1. Create the database with enhanced event system
 * 2. Create all tables including new user groups, recurring events, holidays, and pause tracking
 * 3. Insert sample data and default configurations
 * 4. Set up indexes, views, and stored procedures for enhanced functionality
 * 5. Configure the advanced event management system
 * 
 * Version 4.0 - Enhanced Event Management System
 * Run this script ONCE to set up your enhanced database
 */

// Database configuration - Update these values for your setup
$config = [
    'host' => 'localhost',
    'username' => 'root',           // Default XAMPP username
    'password' => '',               // Default XAMPP password (empty)
    'database' => 'rfid_checkin_system',
    'charset' => 'utf8mb4'
];

// Output styling
    echo "<!DOCTYPE html>
<html>
<head>
    <title>Enhanced Database Setup - RFID Check-in System v4.0</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #27ae60; background: #d5f4e6; padding: 10px; border-radius: 4px; margin: 5px 0; }
        .error { color: #e74c3c; background: #fdf2f2; padding: 10px; border-radius: 4px; margin: 5px 0; }
        .info { color: #3498db; background: #e8f4fd; padding: 10px; border-radius: 4px; margin: 5px 0; }
        .warning { color: #f39c12; background: #fef5e7; padding: 10px; border-radius: 4px; margin: 5px 0; }
        .sql-block { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 4px; font-family: monospace; margin: 10px 0; white-space: pre-wrap; }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        h2 { color: #34495e; margin-top: 30px; }
        .step { margin: 20px 0; padding: 15px; border-left: 4px solid #3498db; background: #f8f9fa; }
        .credentials { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .feature-highlight { background: #e8f5e9; border: 1px solid #4caf50; padding: 15px; border-radius: 4px; margin: 15px 0; }
        .new-feature { color: #4caf50; font-weight: bold; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🏗️ Enhanced RFID Check-in System Database Setup v4.0</h1>
    <div class='feature-highlight'>
        <h3>🎯 New Enhanced Features:</h3>
        <ul>
            <li><span class='new-feature'>User Groups:</span> Organize users into groups for event assignments</li>
            <li><span class='new-feature'>Recurring Events:</span> Daily, weekly, monthly recurring schedules</li>
            <li><span class='new-feature'>Holiday Management:</span> Import holidays that automatically cancel events</li>
            <li><span class='new-feature'>Pause/Break Tracking:</span> Track breaks and pauses in attendance</li>
            <li><span class='new-feature'>Advanced Analytics:</span> Enhanced reporting with pause time calculations</li>
        </ul>
    </div>
";// Function to log messages with styling
function logMessage($message, $type = 'info') {
    echo "<div class='$type'>$message</div>";
    flush();
}

// Function to execute SQL with error handling
function executeSQL($pdo, $sql, $description = '') {
    try {
        $pdo->exec($sql);
        logMessage("✅ $description", 'success');
        return true;
    } catch (PDOException $e) {
        logMessage("❌ Error in $description: " . $e->getMessage(), 'error');
        return false;
    }
}

try {
    echo "<div class='step'>";
    logMessage("🔧 Starting database setup process...", 'info');
    
    // Step 1: Connect to MySQL server (without specific database)
    logMessage("📡 Connecting to MySQL server...", 'info');
    $dsn = "mysql:host={$config['host']};charset={$config['charset']}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
    logMessage("✅ Connected to MySQL server successfully!", 'success');
    echo "</div>";
    
    // Step 2: Create database if it doesn't exist
    echo "<div class='step'>";
    logMessage("🗄️ Creating database '{$config['database']}'...", 'info');
    executeSQL($pdo, "CREATE DATABASE IF NOT EXISTS {$config['database']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci", "Database creation");
    executeSQL($pdo, "USE {$config['database']}", "Database selection");
    echo "</div>";
    
    // Step 3: Create Enhanced Database Tables
    echo "<div class='step'>";
    logMessage("🏗️ Creating enhanced database tables with new features...", 'info');
    
    // Enhanced Core Tables with User Groups and Holiday Support
    $tables = [
        'system_settings' => [
            'description' => 'System Settings',
            'sql' => "
                CREATE TABLE IF NOT EXISTS system_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_name VARCHAR(100) NOT NULL UNIQUE,
                    setting_value TEXT,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB;
            "
        ],
        
        'user_groups' => [
            'description' => 'User Groups for Event Organization',
            'sql' => "
                CREATE TABLE IF NOT EXISTS user_groups (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL UNIQUE,
                    description TEXT,
                    color VARCHAR(7) DEFAULT '#3498db',
                    max_members INT DEFAULT NULL,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_name_active (name, is_active)
                ) ENGINE=InnoDB;
            "
        ],
        
        'holidays' => [
            'description' => 'Holiday Management for Event Scheduling',
            'sql' => "
                CREATE TABLE IF NOT EXISTS holidays (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(200) NOT NULL,
                    holiday_date DATE NOT NULL,
                    is_recurring BOOLEAN DEFAULT FALSE,
                    holiday_type ENUM('fixed', 'moveable', 'observed') DEFAULT 'fixed',
                    country_code VARCHAR(3) DEFAULT 'US',
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_holiday_date (holiday_date),
                    INDEX idx_country_active (country_code, is_active)
                ) ENGINE=InnoDB;
            "
        ],
        
        'users' => [
            'description' => 'Enhanced Users with Group Support',
            'sql' => "
                CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) NOT NULL UNIQUE,
                    email VARCHAR(100) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    first_name VARCHAR(50),
                    last_name VARCHAR(50),
                    rfid_tag VARCHAR(50) UNIQUE,
                    role ENUM('admin', 'user', 'viewer') DEFAULT 'user',
                    user_group_id INT,
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
                    preferences JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_group_id) REFERENCES user_groups(id) ON DELETE SET NULL,
                    INDEX idx_rfid_active (rfid_tag, is_active),
                    INDEX idx_email_verified (email, email_verified),
                    INDEX idx_username_active (username, is_active),
                    INDEX idx_group_active (user_group_id, is_active)
                ) ENGINE=InnoDB;
            "
        ],
        
        'password_resets' => [
            'description' => 'Password Reset Tokens',
            'sql' => "
                CREATE TABLE IF NOT EXISTS password_resets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    token VARCHAR(64) NOT NULL UNIQUE,
                    expires TIMESTAMP NOT NULL,
                    used BOOLEAN DEFAULT FALSE,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_token (token),
                    INDEX idx_user_expires (user_id, expires)
                ) ENGINE=InnoDB;
            "
        ],
        
        'events' => [
            'description' => 'Base Events Table',
            'sql' => "
                CREATE TABLE IF NOT EXISTS events (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(200) NOT NULL,
                    description TEXT,
                    location VARCHAR(200),
                    event_type VARCHAR(50) DEFAULT 'general',
                    capacity INT DEFAULT NULL,
                    require_checkin BOOLEAN DEFAULT TRUE,
                    is_public BOOLEAN DEFAULT TRUE,
                    is_recurring BOOLEAN DEFAULT FALSE,
                    recurrence_pattern JSON,
                    assigned_groups JSON,
                    tags JSON,
                    metadata JSON,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_by INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                    INDEX idx_name_active (name, is_active),
                    INDEX idx_type_active (event_type, is_active),
                    INDEX idx_recurring (is_recurring),
                    INDEX idx_created_by (created_by)
                ) ENGINE=InnoDB;
            "
        ],
        
        'event_instances' => [
            'description' => 'Event Instances for Recurring Events',
            'sql' => "
                CREATE TABLE IF NOT EXISTS event_instances (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    event_id INT NOT NULL,
                    instance_date DATE NOT NULL,
                    start_time TIME NOT NULL,
                    end_time TIME,
                    actual_start_time TIMESTAMP NULL,
                    actual_end_time TIMESTAMP NULL,
                    status ENUM('scheduled', 'active', 'paused', 'completed', 'cancelled', 'holiday_cancelled') DEFAULT 'scheduled',
                    current_participants INT DEFAULT 0,
                    max_participants INT,
                    location_override VARCHAR(200),
                    notes TEXT,
                    cancelled_reason VARCHAR(500),
                    holiday_id INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                    FOREIGN KEY (holiday_id) REFERENCES holidays(id) ON DELETE SET NULL,
                    UNIQUE KEY unique_event_date (event_id, instance_date),
                    INDEX idx_date_status (instance_date, status),
                    INDEX idx_event_date (event_id, instance_date),
                    INDEX idx_status_date (status, instance_date)
                ) ENGINE=InnoDB;
            "
        ],
        
        'event_participants' => [
            'description' => 'Event Participant Assignments',
            'sql' => "
                CREATE TABLE IF NOT EXISTS event_participants (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    event_id INT NOT NULL,
                    user_id INT NOT NULL,
                    participation_type ENUM('required', 'optional', 'invited') DEFAULT 'required',
                    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    status ENUM('registered', 'confirmed', 'declined', 'no_show') DEFAULT 'registered',
                    notes TEXT,
                    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_event_user (event_id, user_id),
                    INDEX idx_event_status (event_id, status),
                    INDEX idx_user_type (user_id, participation_type)
                ) ENGINE=InnoDB;
            "
        ],
        
        'checkins' => [
            'description' => 'Enhanced Check-ins with Pause Support',
            'sql' => "
                CREATE TABLE IF NOT EXISTS checkins (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    event_instance_id INT NOT NULL,
                    user_id INT NOT NULL,
                    checkin_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    checkout_time TIMESTAMP NULL,
                    status ENUM('checked_in', 'checked_out', 'paused', 'no_show') DEFAULT 'checked_in',
                    checkin_method ENUM('rfid', 'manual', 'mobile', 'qr_code') DEFAULT 'rfid',
                    checkout_method ENUM('rfid', 'manual', 'mobile', 'qr_code', 'auto') DEFAULT NULL,
                    device_id INT,
                    location_checkin VARCHAR(200),
                    location_checkout VARCHAR(200),
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    notes TEXT,
                    total_pause_duration INT DEFAULT 0 COMMENT 'Total pause time in minutes',
                    duration_minutes INT GENERATED ALWAYS AS (
                        CASE 
                            WHEN checkout_time IS NOT NULL 
                            THEN TIMESTAMPDIFF(MINUTE, checkin_time, checkout_time) - IFNULL(total_pause_duration, 0)
                            ELSE NULL
                        END
                    ) STORED,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (event_instance_id) REFERENCES event_instances(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_event_user (event_instance_id, user_id),
                    INDEX idx_checkin_time (checkin_time),
                    INDEX idx_status_time (status, checkin_time),
                    INDEX idx_user_status (user_id, status),
                    UNIQUE KEY unique_event_user_checkin (event_instance_id, user_id)
                ) ENGINE=InnoDB;
            "
        ],
        
        'event_pause_sessions' => [
            'description' => 'Pause/Break Session Tracking',
            'sql' => "
                CREATE TABLE IF NOT EXISTS event_pause_sessions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    checkin_id INT NOT NULL,
                    pause_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    pause_end TIMESTAMP NULL,
                    pause_reason ENUM('break', 'lunch', 'emergency', 'technical', 'personal', 'other') DEFAULT 'break',
                    pause_description VARCHAR(500),
                    duration_minutes INT GENERATED ALWAYS AS (
                        CASE 
                            WHEN pause_end IS NOT NULL 
                            THEN TIMESTAMPDIFF(MINUTE, pause_start, pause_end)
                            ELSE NULL
                        END
                    ) STORED,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (checkin_id) REFERENCES checkins(id) ON DELETE CASCADE,
                    INDEX idx_checkin_pause (checkin_id, pause_start),
                    INDEX idx_pause_reason (pause_reason),
                    INDEX idx_duration (duration_minutes)
                ) ENGINE=InnoDB;
            "
        ],
        
        'rfid_devices' => [
            'description' => 'RFID Device Management',
            'sql' => "
                CREATE TABLE IF NOT EXISTS rfid_devices (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    device_name VARCHAR(100) NOT NULL,
                    device_serial VARCHAR(100) UNIQUE,
                    ip_address VARCHAR(45),
                    mac_address VARCHAR(17),
                    location VARCHAR(200),
                    status ENUM('active', 'inactive', 'maintenance', 'error') DEFAULT 'active',
                    firmware_version VARCHAR(50),
                    last_ping TIMESTAMP NULL,
                    last_scan TIMESTAMP NULL,
                    battery_level INT,
                    signal_strength INT,
                    scan_count INT DEFAULT 0,
                    configuration JSON,
                    assigned_events JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_status_location (status, location),
                    INDEX idx_serial_active (device_serial, status),
                    INDEX idx_last_ping (last_ping)
                ) ENGINE=InnoDB;
            "
        ],
        
        'rfid_scan_queue' => [
            'description' => 'RFID Scan Processing Queue',
            'sql' => "
                CREATE TABLE IF NOT EXISTS rfid_scan_queue (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    device_id INT,
                    rfid_tag VARCHAR(50) NOT NULL,
                    scan_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    processed BOOLEAN DEFAULT FALSE,
                    processing_result VARCHAR(500),
                    user_id INT,
                    event_instance_id INT,
                    action_taken ENUM('checkin', 'checkout', 'pause_start', 'pause_end', 'ignored', 'error'),
                    error_message TEXT,
                    processed_at TIMESTAMP NULL,
                    FOREIGN KEY (device_id) REFERENCES rfid_devices(id) ON DELETE SET NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                    FOREIGN KEY (event_instance_id) REFERENCES event_instances(id) ON DELETE SET NULL,
                    INDEX idx_processed_time (processed, scan_time),
                    INDEX idx_rfid_time (rfid_tag, scan_time),
                    INDEX idx_device_processed (device_id, processed)
                ) ENGINE=InnoDB;
            "
        ],
        
        'audit_logs' => [
            'description' => 'System Activity Audit Trail',
            'sql' => "
                CREATE TABLE IF NOT EXISTS audit_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT,
                    action VARCHAR(100) NOT NULL,
                    table_name VARCHAR(50),
                    record_id INT,
                    old_values JSON,
                    new_values JSON,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                    INDEX idx_user_time (user_id, timestamp),
                    INDEX idx_action_time (action, timestamp),
                    INDEX idx_table_record (table_name, record_id)
                ) ENGINE=InnoDB;
            "
        ]
    ];
    
    // Execute table creation
    foreach ($tables as $tableName => $tableInfo) {
        logMessage("🔨 Creating {$tableInfo['description']}...", 'info');
        if (executeSQL($pdo, $tableInfo['sql'], $tableInfo['description'])) {
            logMessage("✅ Successfully created $tableName table", 'success');
        } else {
            logMessage("❌ Failed to create $tableName table", 'error');
        }
    }
    
    echo "</div>";

    // Step 4: Create Stored Procedures and Functions
    echo "<div class='step'>";
    logMessage("⚙️ Creating stored procedures and functions...", 'info');
    
    // Stored Procedures for Enhanced Event Management
    $procedures = [
        'CreateRecurringEventInstances' => "
            DROP PROCEDURE IF EXISTS CreateRecurringEventInstances;
            CREATE PROCEDURE CreateRecurringEventInstances(
                IN p_event_id INT,
                IN p_start_date DATE,
                IN p_end_date DATE
            )
            BEGIN
                DECLARE done INT DEFAULT FALSE;
                DECLARE v_pattern JSON;
                DECLARE v_frequency VARCHAR(20);
                DECLARE v_interval_val INT;
                DECLARE v_days_of_week JSON;
                DECLARE v_current_date DATE;
                DECLARE v_start_time TIME;
                DECLARE v_end_time TIME;
                
                -- Get event recurrence pattern
                SELECT recurrence_pattern INTO v_pattern
                FROM events WHERE id = p_event_id;
                
                SET v_frequency = JSON_UNQUOTE(JSON_EXTRACT(v_pattern, '$.frequency'));
                SET v_interval_val = JSON_UNQUOTE(JSON_EXTRACT(v_pattern, '$.interval'));
                SET v_days_of_week = JSON_EXTRACT(v_pattern, '$.days_of_week');
                SET v_start_time = TIME(JSON_UNQUOTE(JSON_EXTRACT(v_pattern, '$.start_time')));
                SET v_end_time = TIME(JSON_UNQUOTE(JSON_EXTRACT(v_pattern, '$.end_time')));
                
                SET v_current_date = p_start_date;
                
                WHILE v_current_date <= p_end_date DO
                    -- Check if this date should have an instance based on frequency
                    IF (v_frequency = 'daily') OR 
                       (v_frequency = 'weekly' AND JSON_CONTAINS(v_days_of_week, CAST(DAYOFWEEK(v_current_date) AS JSON))) OR
                       (v_frequency = 'monthly' AND DAY(v_current_date) = DAY(p_start_date)) THEN
                        
                        -- Check if it's not a holiday
                        IF NOT EXISTS (SELECT 1 FROM holidays WHERE holiday_date = v_current_date AND is_active = TRUE) THEN
                            INSERT IGNORE INTO event_instances 
                            (event_id, instance_date, start_time, end_time, status) 
                            VALUES (p_event_id, v_current_date, v_start_time, v_end_time, 'scheduled');
                        ELSE
                            INSERT IGNORE INTO event_instances 
                            (event_id, instance_date, start_time, end_time, status, cancelled_reason, holiday_id) 
                            VALUES (p_event_id, v_current_date, v_start_time, v_end_time, 'holiday_cancelled', 
                                   'Cancelled due to holiday', 
                                   (SELECT id FROM holidays WHERE holiday_date = v_current_date AND is_active = TRUE LIMIT 1));
                        END IF;
                    END IF;
                    
                    -- Increment date based on frequency
                    IF v_frequency = 'daily' THEN
                        SET v_current_date = DATE_ADD(v_current_date, INTERVAL v_interval_val DAY);
                    ELSEIF v_frequency = 'weekly' THEN
                        SET v_current_date = DATE_ADD(v_current_date, INTERVAL 1 DAY);
                    ELSEIF v_frequency = 'monthly' THEN
                        SET v_current_date = DATE_ADD(v_current_date, INTERVAL v_interval_val MONTH);
                    ELSE
                        SET v_current_date = DATE_ADD(v_current_date, INTERVAL 1 DAY);
                    END IF;
                END WHILE;
            END;
        ",
        
        'ProcessPauseSession' => "
            DROP PROCEDURE IF EXISTS ProcessPauseSession;
            CREATE PROCEDURE ProcessPauseSession(
                IN p_checkin_id INT,
                IN p_action ENUM('start', 'end'),
                IN p_reason ENUM('break', 'lunch', 'emergency', 'technical', 'personal', 'other'),
                IN p_description VARCHAR(500)
            )
            BEGIN
                DECLARE v_active_pause_id INT DEFAULT NULL;
                DECLARE v_total_pause INT DEFAULT 0;
                
                IF p_action = 'start' THEN
                    -- Check if there's already an active pause
                    SELECT id INTO v_active_pause_id 
                    FROM event_pause_sessions 
                    WHERE checkin_id = p_checkin_id AND pause_end IS NULL
                    LIMIT 1;
                    
                    IF v_active_pause_id IS NULL THEN
                        INSERT INTO event_pause_sessions (checkin_id, pause_reason, pause_description)
                        VALUES (p_checkin_id, p_reason, p_description);
                        
                        UPDATE checkins SET status = 'paused' WHERE id = p_checkin_id;
                    END IF;
                    
                ELSEIF p_action = 'end' THEN
                    -- End the active pause session
                    UPDATE event_pause_sessions 
                    SET pause_end = CURRENT_TIMESTAMP
                    WHERE checkin_id = p_checkin_id AND pause_end IS NULL;
                    
                    -- Calculate total pause duration
                    SELECT IFNULL(SUM(duration_minutes), 0) INTO v_total_pause
                    FROM event_pause_sessions 
                    WHERE checkin_id = p_checkin_id AND duration_minutes IS NOT NULL;
                    
                    -- Update checkin with total pause duration
                    UPDATE checkins 
                    SET total_pause_duration = v_total_pause,
                        status = 'checked_in'
                    WHERE id = p_checkin_id;
                END IF;
            END;
        "
    ];
    
    foreach ($procedures as $procName => $procSQL) {
        logMessage("🔧 Creating stored procedure: $procName", 'info');
        executeSQL($pdo, $procSQL, "Stored procedure: $procName");
    }
    
    echo "</div>";

    // Step 5: Insert Sample Data and Default Configuration
    echo "<div class='step'>";
    logMessage("📊 Inserting sample data and default configuration...", 'info');
    
    // System Settings
    $defaultSettings = [
        ['setting_name' => 'system_name', 'setting_value' => 'RFID Check-in System', 'description' => 'System display name'],
        ['setting_name' => 'system_version', 'setting_value' => '4.0.0', 'description' => 'Current system version'],
        ['setting_name' => 'timezone', 'setting_value' => 'America/New_York', 'description' => 'System timezone'],
        ['setting_name' => 'auto_checkout_hours', 'setting_value' => '12', 'description' => 'Hours after which to auto-checkout users'],
        ['setting_name' => 'max_pause_minutes', 'setting_value' => '60', 'description' => 'Maximum pause duration in minutes'],
        ['setting_name' => 'holiday_api_enabled', 'setting_value' => 'true', 'description' => 'Enable automatic holiday imports'],
        ['setting_name' => 'default_event_capacity', 'setting_value' => '50', 'description' => 'Default capacity for new events'],
        ['setting_name' => 'enable_mobile_checkin', 'setting_value' => 'true', 'description' => 'Allow mobile device check-ins']
    ];
    
    foreach ($defaultSettings as $setting) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_name, setting_value, description) VALUES (?, ?, ?)");
        $stmt->execute([$setting['setting_name'], $setting['setting_value'], $setting['description']]);
    }
    logMessage("✅ System settings configured", 'success');
    
    // Default User Groups
    $defaultGroups = [
        ['name' => 'Staff', 'description' => 'Full-time staff members', 'color' => '#3498db', 'max_members' => null],
        ['name' => 'Volunteers', 'description' => 'Volunteer participants', 'color' => '#2ecc71', 'max_members' => null],
        ['name' => 'Trainees', 'description' => 'Training program participants', 'color' => '#f39c12', 'max_members' => 25],
        ['name' => 'Visitors', 'description' => 'Guest participants', 'color' => '#9b59b6', 'max_members' => 10],
        ['name' => 'Administrators', 'description' => 'System administrators', 'color' => '#e74c3c', 'max_members' => 5]
    ];
    
    foreach ($defaultGroups as $group) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO user_groups (name, description, color, max_members) VALUES (?, ?, ?, ?)");
        $stmt->execute([$group['name'], $group['description'], $group['color'], $group['max_members']]);
    }
    logMessage("✅ Default user groups created", 'success');
    
    // US Federal Holidays for Current Year
    $currentYear = date('Y');
    $holidays = [
        ['name' => 'New Year\'s Day', 'holiday_date' => "$currentYear-01-01", 'is_recurring' => true],
        ['name' => 'Martin Luther King Jr. Day', 'holiday_date' => "$currentYear-01-15", 'is_recurring' => true, 'holiday_type' => 'moveable'],
        ['name' => 'Presidents Day', 'holiday_date' => "$currentYear-02-19", 'is_recurring' => true, 'holiday_type' => 'moveable'],
        ['name' => 'Memorial Day', 'holiday_date' => "$currentYear-05-27", 'is_recurring' => true, 'holiday_type' => 'moveable'],
        ['name' => 'Independence Day', 'holiday_date' => "$currentYear-07-04", 'is_recurring' => true],
        ['name' => 'Labor Day', 'holiday_date' => "$currentYear-09-02", 'is_recurring' => true, 'holiday_type' => 'moveable'],
        ['name' => 'Columbus Day', 'holiday_date' => "$currentYear-10-14", 'is_recurring' => true, 'holiday_type' => 'moveable'],
        ['name' => 'Veterans Day', 'holiday_date' => "$currentYear-11-11", 'is_recurring' => true],
        ['name' => 'Thanksgiving Day', 'holiday_date' => "$currentYear-11-28", 'is_recurring' => true, 'holiday_type' => 'moveable'],
        ['name' => 'Christmas Day', 'holiday_date' => "$currentYear-12-25", 'is_recurring' => true]
    ];
    
    foreach ($holidays as $holiday) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO holidays (name, holiday_date, is_recurring, holiday_type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$holiday['name'], $holiday['holiday_date'], $holiday['is_recurring'], $holiday['holiday_type'] ?? 'fixed']);
    }
    logMessage("✅ Default holidays imported for $currentYear", 'success');
    
    echo "</div>";

    // Step 6: Create Default Admin User
    
    // Access Logs table
    $accessLogsSQL = "
    CREATE TABLE IF NOT EXISTS AccessLogs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action ENUM('login', 'logout', 'checkin', 'checkout', 'failed_login', 'password_change', 'profile_update') NOT NULL,
        rfid_tag VARCHAR(50),
        device_id INT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        details JSON,
        status ENUM('success', 'failed', 'blocked') DEFAULT 'success',
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE SET NULL,
        FOREIGN KEY (device_id) REFERENCES RFIDDevices(device_id) ON DELETE SET NULL,
        INDEX idx_user_id (user_id),
        INDEX idx_action (action),
        INDEX idx_timestamp (timestamp),
        INDEX idx_status (status)
    )";
    executeSQL($pdo, $accessLogsSQL, "Access logs table");
    
    // Activity Log table
    $activityLogSQL = "
    CREATE TABLE IF NOT EXISTS ActivityLog (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE SET NULL,
        INDEX idx_user_id (user_id),
        INDEX idx_timestamp (timestamp)
    )";
    executeSQL($pdo, $activityLogSQL, "Activity log table");
    
    // System Settings table
    $systemSettingsSQL = "
    CREATE TABLE IF NOT EXISTS SystemSettings (
        setting_id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
        description TEXT,
        category VARCHAR(50),
        is_public BOOLEAN DEFAULT FALSE,
        updated_by INT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (updated_by) REFERENCES Users(user_id) ON DELETE SET NULL,
        INDEX idx_category (category),
        INDEX idx_is_public (is_public)
    )";
    executeSQL($pdo, $systemSettingsSQL, "System settings table");
    
    // Notifications table
    $notificationsSQL = "
    CREATE TABLE IF NOT EXISTS Notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        title VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'warning', 'error', 'success') DEFAULT 'info',
        read_status BOOLEAN DEFAULT FALSE,
        action_url VARCHAR(500),
        expires_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
        INDEX idx_user_read (user_id, read_status),
        INDEX idx_created_at (created_at)
    )";
    executeSQL($pdo, $notificationsSQL, "Notifications table");
    
    // Reports table
    $reportsSQL = "
    CREATE TABLE IF NOT EXISTS Reports (
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
        
        FOREIGN KEY (generated_by) REFERENCES Users(user_id) ON DELETE SET NULL,
        FOREIGN KEY (event_id) REFERENCES Events(event_id) ON DELETE SET NULL,
        INDEX idx_report_type (report_type),
        INDEX idx_generated_by (generated_by),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at)
    )";
    executeSQL($pdo, $reportsSQL, "Reports table");
    
    // Event Registration table
    $eventRegistrationSQL = "
    CREATE TABLE IF NOT EXISTS EventRegistration (
        registration_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        event_id INT NOT NULL,
        registration_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        status ENUM('registered', 'cancelled', 'waitlist') DEFAULT 'registered',
        notes TEXT,
        
        FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (event_id) REFERENCES Events(event_id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_event_reg (user_id, event_id),
        INDEX idx_status (status)
    )";
    executeSQL($pdo, $eventRegistrationSQL, "Event registration table");
    
    echo "</div>";
    
    // Step 9: Insert default admin user
    echo "<div class='step'>";
    logMessage("👤 Creating default admin user...", 'info');
    
    // Create a secure password hash for 'admin123'
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    
    $adminUserSQL = "
    INSERT INTO Users (
        username, 
        email, 
        password, 
        first_name, 
        last_name, 
        role, 
        is_active, 
        email_verified, 
        created_at
    ) VALUES (
        'admin',
        'admin@example.com',
        '$adminPassword',
        'System',
        'Administrator',
        'admin',
        TRUE,
        TRUE,
        NOW()
    ) ON DUPLICATE KEY UPDATE 
        email = VALUES(email),
        password = VALUES(password),
        first_name = VALUES(first_name),
        last_name = VALUES(last_name),
        is_active = VALUES(is_active),
        email_verified = VALUES(email_verified)";
    
    executeSQL($pdo, $adminUserSQL, "Default admin user");
    
    // Create user settings for admin
    $adminSettingsSQL = "
    INSERT INTO user_settings (user_id, theme, language) 
    VALUES (1, 'light', 'en') 
    ON DUPLICATE KEY UPDATE theme = VALUES(theme)";
    executeSQL($pdo, $adminSettingsSQL, "Admin user settings");
    
    echo "</div>";
    
    // Step 10: Insert sample data
    echo "<div class='step'>";
    logMessage("📊 Inserting sample data...", 'info');
    
    // Sample events
    $sampleEventsSQL = "
    INSERT INTO Events (name, description, location, start_time, end_time, created_by, event_type, breaks_info, active) VALUES
    ('Weekly Team Meeting', 'Regular team synchronization meeting', 'Conference Room A', DATE_ADD(NOW(), INTERVAL 1 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 1 DAY), INTERVAL 1 HOUR), 1, 'meeting', NULL, 1),
    ('Tech Conference 2025', 'Annual technology conference', 'Main Auditorium', DATE_ADD(NOW(), INTERVAL 7 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 7 DAY), INTERVAL 8 HOUR), 1, 'conference', NULL, 1),
    ('Security Training', 'Mandatory security awareness training', 'Training Room B', DATE_ADD(NOW(), INTERVAL 3 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 3 DAY), INTERVAL 2 HOUR), 1, 'training', NULL, 1),
    ('Holiday Party', 'Annual company holiday celebration', 'Main Hall', DATE_ADD(NOW(), INTERVAL 30 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 30 DAY), INTERVAL 4 HOUR), 1, 'social', NULL, 1),
    ('IT-Ausbildung', 'Daily IT training program with scheduled breaks', 'IT Training Center', 
     CONCAT(CURDATE(), ' 07:30:00'), 
     CONCAT(CURDATE(), ' 16:00:00'), 
     1, 'training', 
     JSON_OBJECT(
       \"daily_schedule\", true,
       \"recurring\", \"workdays\",
       \"breaks\", JSON_ARRAY(
         JSON_OBJECT(\"name\", \"Morning Break\", \"start_time\", \"09:00\", \"end_time\", \"09:30\", \"duration_minutes\", 30),
         JSON_OBJECT(\"name\", \"Lunch Break\", \"start_time\", \"12:30\", \"end_time\", \"13:00\", \"duration_minutes\", 30)
       ),
       \"total_duration_hours\", 8.5,
       \"effective_training_hours\", 7.5
     ), 
     1)
    ON DUPLICATE KEY UPDATE name = VALUES(name)";
    executeSQL($pdo, $sampleEventsSQL, "Sample events");
    
    // Create recurring workday IT-Ausbildung events for the next 30 days
    logMessage("📅 Creating recurring IT-Ausbildung workday events...", 'info');
    
    for ($i = 0; $i < 30; $i++) {
        $date = date('Y-m-d', strtotime("+$i days"));
        $dayOfWeek = date('N', strtotime($date)); // 1 = Monday, 7 = Sunday
        
        // Only create events for workdays (Monday to Friday)
        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
            $dayName = date('l', strtotime($date)); // Monday, Tuesday, etc.
            
            $recurringEventSQL = "
                INSERT IGNORE INTO Events (
                    name, description, location, start_time, end_time, 
                    created_by, event_type, breaks_info, active
                ) VALUES (
                    'IT-Ausbildung - $dayName ($date)',
                    'Daily IT training program with scheduled breaks',
                    'IT Training Center',
                    '$date 07:30:00',
                    '$date 16:00:00',
                    1,
                    'training',
                    JSON_OBJECT(
                        \"daily_schedule\", true,
                        \"recurring\", \"workdays\",
                        \"date\", \"$date\",
                        \"day\", \"$dayName\",
                        \"breaks\", JSON_ARRAY(
                            JSON_OBJECT(\"name\", \"Morning Break\", \"start_time\", \"09:00\", \"end_time\", \"09:30\", \"duration_minutes\", 30),
                            JSON_OBJECT(\"name\", \"Lunch Break\", \"start_time\", \"12:30\", \"end_time\", \"13:00\", \"duration_minutes\", 30)
                        ),
                        \"total_duration_hours\", 8.5,
                        \"effective_training_hours\", 7.5
                    ),
                    1
                )
            ";
            executeSQL($pdo, $recurringEventSQL, "IT-Ausbildung event for $dayName ($date)");
        }
    }
    
    echo "</div>";
    
    // Sample RFID devices
    $sampleDevicesSQL = "
    INSERT INTO RFIDDevices (device_name, device_serial, ip_address, location, status) VALUES
    ('Main Entrance Reader', 'RFID001', '192.168.1.100', 'Main Building Entrance', 'active'),
    ('Conference Room Reader', 'RFID002', '192.168.1.101', 'Conference Room A', 'active'),
    ('Training Room Reader', 'RFID003', '192.168.1.102', 'Training Room B', 'active')
    ON DUPLICATE KEY UPDATE device_name = VALUES(device_name)";
    executeSQL($pdo, $sampleDevicesSQL, "Sample RFID devices");
    
    // System settings
    $systemSettingsDataSQL = "
    INSERT INTO SystemSettings (setting_key, setting_value, setting_type, description, category, is_public) VALUES
    ('system_name', 'Electronic Check-in System', 'string', 'Name of the system', 'general', TRUE),
    ('max_login_attempts', '5', 'number', 'Maximum login attempts before lockout', 'security', FALSE),
    ('session_timeout', '3600', 'number', 'Session timeout in seconds', 'security', FALSE),
    ('enable_rfid', 'true', 'boolean', 'Enable RFID check-in functionality', 'features', FALSE),
    ('enable_mobile_checkin', 'true', 'boolean', 'Enable mobile check-in', 'features', FALSE),
    ('default_event_duration', '120', 'number', 'Default event duration in minutes', 'events', FALSE),
    ('company_name', 'Your Company Name', 'string', 'Company name for branding', 'general', TRUE),
    ('support_email', 'support@yourcompany.com', 'string', 'Support contact email', 'general', TRUE)
    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
    executeSQL($pdo, $systemSettingsDataSQL, "System settings");
    
    // Sample test users
    $testUsersSQL = "
    INSERT INTO Users (username, email, password, first_name, last_name, role, is_active, email_verified, rfid_tag) VALUES
    ('testuser1', 'user1@test.com', '" . password_hash('password123', PASSWORD_DEFAULT) . "', 'John', 'Doe', 'user', TRUE, TRUE, 'RFID123456'),
    ('testuser2', 'user2@test.com', '" . password_hash('password123', PASSWORD_DEFAULT) . "', 'Jane', 'Smith', 'user', TRUE, TRUE, 'RFID789012'),
    ('moderator', 'mod@test.com', '" . password_hash('mod123', PASSWORD_DEFAULT) . "', 'Mike', 'Johnson', 'moderator', TRUE, TRUE, 'RFIDMOD001')
    ON DUPLICATE KEY UPDATE email = VALUES(email)";
    executeSQL($pdo, $testUsersSQL, "Sample test users");
    
    echo "</div>";
    
    // Step 11: Create views for common queries
    echo "<div class='step'>";
    logMessage("👁️ Creating database views...", 'info');
    
    $viewActiveUsersSQL = "
    CREATE OR REPLACE VIEW view_active_users AS
    SELECT 
        user_id, 
        CONCAT(first_name, ' ', COALESCE(last_name, '')) as full_name, 
        email, 
        role, 
        last_login, 
        created_at,
        rfid_tag
    FROM Users 
    WHERE is_active = TRUE AND deleted_at IS NULL";
    executeSQL($pdo, $viewActiveUsersSQL, "Active users view");
    
    $viewCurrentEventsSQL = "
    CREATE OR REPLACE VIEW view_current_events AS
    SELECT 
        e.*, 
        CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as created_by_name
    FROM Events e
    LEFT JOIN Users u ON e.created_by = u.user_id
    WHERE e.active = TRUE 
    AND e.start_time <= NOW() 
    AND (e.end_time IS NULL OR e.end_time >= NOW())";
    executeSQL($pdo, $viewCurrentEventsSQL, "Current events view");
    
    $viewUpcomingEventsSQL = "
    CREATE OR REPLACE VIEW view_upcoming_events AS
    SELECT 
        e.*, 
        CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as created_by_name
    FROM Events e
    LEFT JOIN Users u ON e.created_by = u.user_id
    WHERE e.active = TRUE 
    AND e.start_time > NOW()
    ORDER BY e.start_time ASC";
    executeSQL($pdo, $viewUpcomingEventsSQL, "Upcoming events view");
    
    echo "</div>";
    
    // Step 12: Performance indexes
    echo "<div class='step'>";
    logMessage("⚡ Creating performance indexes...", 'info');
    
    executeSQL($pdo, "CREATE INDEX IF NOT EXISTS idx_checkin_user_time ON CheckIn(user_id, checkin_time DESC)", "CheckIn user-time index");
    executeSQL($pdo, "CREATE INDEX IF NOT EXISTS idx_events_time_active ON Events(start_time, active)", "Events time-active index");
    executeSQL($pdo, "CREATE INDEX IF NOT EXISTS idx_accesslog_time_action ON AccessLogs(timestamp DESC, action)", "Access log time-action index");
    executeSQL($pdo, "CREATE INDEX IF NOT EXISTS idx_users_role_active ON Users(role, is_active)", "Users role-active index");
    
    echo "</div>";
    
    // Step 12.5: Create stored procedures
    echo "<div class='step'>";
    logMessage("🔧 Creating stored procedures...", 'info');
    
    // Stored procedure for user check-in
    $procedureSQL = "
    DROP PROCEDURE IF EXISTS sp_user_checkin;
    CREATE PROCEDURE sp_user_checkin(
        IN p_user_id INT,
        IN p_event_id INT,
        IN p_method VARCHAR(20),
        IN p_device_id INT,
        IN p_ip_address VARCHAR(45)
    )
    BEGIN
        DECLARE EXIT HANDLER FOR SQLEXCEPTION
        BEGIN
            ROLLBACK;
            RESIGNAL;
        END;
        
        START TRANSACTION;
        
        -- Check if user is already checked in
        IF EXISTS (
            SELECT 1 FROM CheckIn 
            WHERE user_id = p_user_id 
            AND event_id = p_event_id 
            AND status = 'checked-in'
        ) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User already checked in to this event';
        END IF;
        
        -- Insert check-in record
        INSERT INTO CheckIn (user_id, event_id, method, device_id, ip_address)
        VALUES (p_user_id, p_event_id, p_method, p_device_id, p_ip_address);
        
        -- Update event participant count
        UPDATE Events 
        SET current_participants = current_participants + 1
        WHERE event_id = p_event_id;
        
        COMMIT;
    END";
    executeSQL($pdo, $procedureSQL, "User check-in stored procedure");
    
    // Procedure to recalculate participants
    $recalcSQL = "
    DROP PROCEDURE IF EXISTS sp_recalculate_participants;
    CREATE PROCEDURE sp_recalculate_participants()
    BEGIN
        UPDATE Events e
        SET current_participants = (
            SELECT COALESCE(COUNT(*), 0)
            FROM CheckIn c
            WHERE c.event_id = e.event_id
            AND c.status = 'checked-in'
        );
    END";
    executeSQL($pdo, $recalcSQL, "Recalculate participants stored procedure");
    
    echo "</div>";
    
    // Step 14: Create config.php if it doesn't exist
    echo "<div class='step'>";
    logMessage("⚙️ Checking configuration file...", 'info');
    
    $configPath = dirname(__DIR__) . '/core/config.php';
    if (!file_exists($configPath)) {
        $configContent = "<?php
/**
 * Configuration for Electronic Check-in System
 * Generated automatically by database setup
 */

// Database Configuration
define('DB_HOST', '{$config['host']}');
define('DB_NAME', '{$config['database']}');
define('DB_USER', '{$config['username']}');
define('DB_PASS', '{$config['password']}');
define('DB_CHARSET', '{$config['charset']}');

// Application Configuration
define('APP_NAME', 'Electronic Check-in System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/rfid-checkin');  // Update with your URL

// Security Configuration
define('SESSION_LIFETIME', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);

// Error Reporting (set DEBUG_MODE to false in production)
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('Europe/Berlin');  // Update to your timezone

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0);  // Set to 1 if using HTTPS
";
        
        if (file_put_contents($configPath, $configContent)) {
            logMessage("✅ Created config.php file", 'success');
        } else {
            logMessage("⚠️ Could not create config.php file automatically. Please create it manually.", 'warning');
        }
    } else {
        logMessage("✅ Config.php already exists", 'success');
    }
    
    echo "</div>";
    
    // Success summary
    echo "<div class='step'>";
    logMessage("🎉 Database setup completed successfully!", 'success');
    
    echo "<div class='credentials'>
        <h3>🔐 Default Login Credentials</h3>
        <strong>Admin Account:</strong><br>
        Email: admin@example.com<br>
        Password: admin123<br><br>
        
        <strong>Test User Accounts:</strong><br>
        Email: user1@test.com | Password: password123 | RFID: RFID123456<br>
        Email: user2@test.com | Password: password123 | RFID: RFID789012<br>
        Email: mod@test.com | Password: mod123 | RFID: RFIDMOD001<br><br>
        
        <strong>⚠️ Important:</strong> Change these default passwords in production!
    </div>";
    
    echo "<div class='info'>
        <h3>📋 Next Steps:</h3>
        <ol>
            <li>Navigate to your application: <a href='../index.php' target='_blank'>../index.php</a></li>
            <li>Login with the admin credentials above</li>
            <li>Update system settings in the admin panel</li>
            <li>Create real user accounts</li>
            <li>Configure RFID devices</li>
            <li>Set up your first events</li>
        </ol>
    </div>";
    
    echo "<div class='warning'>
        <h3>🔒 Security Recommendations:</h3>
        <ul>
            <li>Change all default passwords immediately</li>
            <li>Update the database connection credentials if needed</li>
            <li>Set DEBUG_MODE to false in production</li>
            <li>Configure HTTPS for production use</li>
            <li>Regularly backup your database</li>
        </ul>
    </div>";
    
    echo "</div>";
    
} catch (PDOException $e) {
    logMessage("❌ Database connection failed: " . $e->getMessage(), 'error');
    echo "<div class='error'>
        <h3>Troubleshooting Tips:</h3>
        <ul>
            <li>Make sure XAMPP/MySQL is running</li>
            <li>Check if the database credentials are correct</li>
            <li>Ensure MySQL user has CREATE DATABASE privileges</li>
            <li>Try accessing phpMyAdmin to test the connection</li>
        </ul>
    </div>";
} catch (Exception $e) {
    logMessage("❌ Unexpected error: " . $e->getMessage(), 'error');
}

echo "</div></body></html>";
?>
