-- Optimized Database Schema for Electronic Check-in System
-- Version 3.0 - Complete and Normalized with Password Reset Support

CREATE DATABASE IF NOT EXISTS rfid_checkin_system;
USE rfid_checkin_system;

-- Users table with complete fields
CREATE TABLE IF NOT EXISTS Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50),
    phone VARCHAR(20),
    rfid_tag VARCHAR(50) UNIQUE,
    role ENUM('admin', 'user', 'moderator') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    last_login DATETIME,
    login_attempts INT DEFAULT 0,
    locked_until DATETIME NULL,
    avatar VARCHAR(255),
    department VARCHAR(100),
    position VARCHAR(100),
    bio TEXT,
    preferences JSON,
    deleted_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_rfid_tag (rfid_tag),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
);

-- Password Reset table for secure password reset functionality
CREATE TABLE IF NOT EXISTS password_resets (
    reset_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires)
);

-- Events table with complete fields
CREATE TABLE IF NOT EXISTS Events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    location VARCHAR(200),
    start_time DATETIME NOT NULL,
    end_time DATETIME,
    capacity INT,
    current_participants INT DEFAULT 0,
    require_checkin TINYINT(1) DEFAULT 1,
    is_public TINYINT(1) DEFAULT 1,
    event_type VARCHAR(50) DEFAULT 'general',
    tags JSON,
    breaks_info JSON,  -- Store break schedule information
    active TINYINT(1) DEFAULT 1,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES Users(user_id) ON DELETE SET NULL,
    INDEX idx_start_time (start_time),
    INDEX idx_active (active),
    INDEX idx_event_type (event_type),
    INDEX idx_created_by (created_by),
    INDEX idx_current_participants (current_participants)
);

-- Check-In table with enhanced tracking
CREATE TABLE IF NOT EXISTS CheckIn (
    checkin_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    checkin_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    checkout_time DATETIME NULL,
    status ENUM('checked-in', 'checked-out', 'no-show') DEFAULT 'checked-in',
    method ENUM('rfid', 'manual', 'mobile', 'qr-code') DEFAULT 'rfid',
    device_id INT,
    notes TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    location_lat DECIMAL(10, 8),
    location_lng DECIMAL(11, 8),
    duration_minutes INT GENERATED ALWAYS AS (
        CASE 
            WHEN checkout_time IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, checkin_time, checkout_time)
            ELSE NULL
        END
    ) STORED,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES Events(event_id) ON DELETE CASCADE,
    INDEX idx_user_event (user_id, event_id),
    INDEX idx_checkin_time (checkin_time),
    INDEX idx_status (status),
    INDEX idx_method (method),
    UNIQUE KEY unique_user_event (user_id, event_id, checkin_time)
);

-- RFID Devices table with enhanced management
CREATE TABLE IF NOT EXISTS RFIDDevices (
    device_id INT AUTO_INCREMENT PRIMARY KEY,
    device_name VARCHAR(100) NOT NULL,
    device_serial VARCHAR(100) UNIQUE,
    ip_address VARCHAR(45),
    mac_address VARCHAR(17),
    location VARCHAR(200),
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    firmware_version VARCHAR(50),
    last_ping DATETIME,
    battery_level INT, -- For battery-powered devices
    signal_strength INT,
    configuration JSON,
    assigned_events JSON, -- Array of event IDs this device handles
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_status (status),
    INDEX idx_ip_address (ip_address),
    INDEX idx_location (location)
);

-- User Settings table for preferences and configurations
CREATE TABLE IF NOT EXISTS user_settings (
    settings_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    email_notifications BOOLEAN DEFAULT TRUE,
    sms_notifications BOOLEAN DEFAULT FALSE,
    event_reminders BOOLEAN DEFAULT TRUE,
    newsletter BOOLEAN DEFAULT TRUE,
    profile_visibility ENUM('public', 'private', 'friends') DEFAULT 'public',
    show_email BOOLEAN DEFAULT FALSE,
    show_phone BOOLEAN DEFAULT FALSE,
    language VARCHAR(10) DEFAULT 'en',
    timezone VARCHAR(50) DEFAULT 'UTC',
    theme VARCHAR(20) DEFAULT 'light',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);

-- Access Logs table for security and audit trail
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
);

-- Activity Log for user actions
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
);

-- Reports table for generated reports
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
    event_id INT, -- Optional: if report is for specific event
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
);

-- Event Registration table (if registration is required)
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
);

-- Notifications table
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
);

-- System Settings table
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
);

-- Insert default system admin user (password: admin123)
-- Login with: admin@example.com / admin123
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
    '$2y$10$rBJHYKcCh5hHjn1d3j5KUOzO3WpHhXZ9LmXgWzK2iRaW7YXZK9v7e', -- admin123 (working hash)
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
    email_verified = VALUES(email_verified);

-- Insert sample events
INSERT INTO Events (name, description, location, start_time, end_time, created_by, event_type, breaks_info, active) VALUES
('Weekly Team Meeting', 'Regular team synchronization meeting', 'Conference Room A', DATE_ADD(NOW(), INTERVAL 1 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 1 DAY), INTERVAL 1 HOUR), 1, 'meeting', NULL, 1),
('Tech Conference 2024', 'Annual technology conference', 'Main Auditorium', DATE_ADD(NOW(), INTERVAL 7 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 7 DAY), INTERVAL 8 HOUR), 1, 'conference', NULL, 1),
('Security Training', 'Mandatory security awareness training', 'Training Room B', DATE_ADD(NOW(), INTERVAL 3 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 3 DAY), INTERVAL 2 HOUR), 1, 'training', NULL, 1),
('IT-Ausbildung', 'Daily IT training program with scheduled breaks', 'IT Training Center', 
 CONCAT(CURDATE() + INTERVAL 1 DAY, ' 07:30:00'), 
 CONCAT(CURDATE() + INTERVAL 1 DAY, ' 16:00:00'), 
 1, 'training', 
 JSON_OBJECT(
   'daily_schedule', true,
   'breaks', JSON_ARRAY(
     JSON_OBJECT('name', 'Morning Break', 'start_time', '09:00', 'end_time', '09:30', 'duration_minutes', 30),
     JSON_OBJECT('name', 'Lunch Break', 'start_time', '12:30', 'end_time', '13:00', 'duration_minutes', 30)
   ),
   'total_duration_hours', 8.5,
   'effective_training_hours', 7.5
 ), 
 1);

-- Insert sample RFID device
INSERT INTO RFIDDevices (device_name, device_serial, ip_address, location, status) VALUES
('Main Entrance Reader', 'RFID001', '192.168.1.100', 'Main Building Entrance', 'active'),
('Conference Room Reader', 'RFID002', '192.168.1.101', 'Conference Room A', 'active');

-- Insert default system settings
INSERT INTO SystemSettings (setting_key, setting_value, setting_type, description, category, is_public) VALUES
('system_name', 'Electronic Check-in System', 'string', 'Name of the system', 'general', TRUE),
('max_login_attempts', '5', 'number', 'Maximum login attempts before lockout', 'security', FALSE),
('session_timeout', '3600', 'number', 'Session timeout in seconds', 'security', FALSE),
('enable_rfid', 'true', 'boolean', 'Enable RFID check-in functionality', 'features', FALSE),
('enable_mobile_checkin', 'true', 'boolean', 'Enable mobile check-in', 'features', FALSE),
('default_event_duration', '120', 'number', 'Default event duration in minutes', 'events', FALSE);

-- Create views for common queries
CREATE VIEW view_active_users AS
SELECT 
    user_id, CONCAT(first_name, ' ', COALESCE(last_name, '')) as name, email, role, last_login, created_at
FROM Users 
WHERE is_active = TRUE;

CREATE VIEW view_current_events AS
SELECT 
    e.*, CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as created_by_name
FROM Events e
LEFT JOIN Users u ON e.created_by = u.user_id
WHERE e.active = TRUE 
AND e.start_time <= NOW() 
AND e.end_time >= NOW();

CREATE VIEW view_upcoming_events AS
SELECT 
    e.*, CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as created_by_name
FROM Events e
LEFT JOIN Users u ON e.created_by = u.user_id
WHERE e.active = TRUE 
AND e.start_time > NOW()
ORDER BY e.start_time ASC;

-- Create stored procedures for common operations
DELIMITER //

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
END//

-- Procedure to recalculate current_participants for all events (for data consistency)
CREATE PROCEDURE sp_recalculate_participants()
BEGIN
    UPDATE Events e
    SET current_participants = (
        SELECT COALESCE(COUNT(*), 0)
        FROM CheckIn c
        WHERE c.event_id = e.event_id
        AND c.status = 'checked-in'
    );
END//

DELIMITER ;

-- Create triggers for audit logging
DELIMITER //

CREATE TRIGGER tr_users_insert_log
AFTER INSERT ON Users
FOR EACH ROW
BEGIN
    INSERT INTO ActivityLog (user_id, action, details)
    VALUES (NEW.user_id, 'user_created', CONCAT('New user created: ', NEW.first_name, ' ', COALESCE(NEW.last_name, '')));
END//

CREATE TRIGGER tr_users_update_log
AFTER UPDATE ON Users
FOR EACH ROW
BEGIN
    INSERT INTO ActivityLog (user_id, action, details)
    VALUES (NEW.user_id, 'user_updated', 'User profile updated');
END//

-- Triggers to maintain current_participants count in Events table
CREATE TRIGGER tr_checkin_insert
AFTER INSERT ON CheckIn
FOR EACH ROW
BEGIN
    IF NEW.status = 'checked-in' THEN
        UPDATE Events 
        SET current_participants = current_participants + 1
        WHERE event_id = NEW.event_id;
    END IF;
END//

CREATE TRIGGER tr_checkin_update
AFTER UPDATE ON CheckIn
FOR EACH ROW
BEGIN
    -- Handle status changes
    IF OLD.status != NEW.status THEN
        IF OLD.status = 'checked-in' AND NEW.status != 'checked-in' THEN
            -- User checked out or became no-show
            UPDATE Events 
            SET current_participants = GREATEST(0, current_participants - 1)
            WHERE event_id = NEW.event_id;
        ELSEIF OLD.status != 'checked-in' AND NEW.status = 'checked-in' THEN
            -- User checked back in
            UPDATE Events 
            SET current_participants = current_participants + 1
            WHERE event_id = NEW.event_id;
        END IF;
    END IF;
END//

CREATE TRIGGER tr_checkin_delete
AFTER DELETE ON CheckIn
FOR EACH ROW
BEGIN
    IF OLD.status = 'checked-in' THEN
        UPDATE Events 
        SET current_participants = GREATEST(0, current_participants - 1)
        WHERE event_id = OLD.event_id;
    END IF;
END//

DELIMITER ;

-- Add indexes for performance
CREATE INDEX idx_checkin_user_time ON CheckIn(user_id, checkin_time DESC);
CREATE INDEX idx_events_time_active ON Events(start_time, active);
CREATE INDEX idx_accesslog_time_action ON AccessLogs(timestamp DESC, action);

COMMIT;
