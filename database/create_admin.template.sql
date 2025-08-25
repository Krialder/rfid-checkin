-- TEMPLATE: Create Admin User for RFID Check-in System
-- 
-- INSTRUCTIONS:
-- 1. Copy this file to 'create_admin.sql' (same directory)
-- 2. Update the password hash and email below
-- 3. The real create_admin.sql file is ignored by Git for security
-- 
-- Run this after importing the main database schema

USE rfid_checkin_system;

-- Insert admin user 
-- IMPORTANT: Generate a new password hash for security!
-- You can generate one using: password_hash('your_password', PASSWORD_DEFAULT) in PHP
INSERT INTO Users (
    username, 
    email, 
    password, 
    first_name, 
    last_name, 
    role, 
    is_active, 
    created_at
) VALUES (
    'admin',
    'your_admin_email@domain.com',              -- UPDATE THIS EMAIL
    'REPLACE_WITH_SECURE_PASSWORD_HASH',        -- UPDATE THIS PASSWORD HASH
    'System',
    'Administrator',
    'admin',
    1,
    NOW()
) ON DUPLICATE KEY UPDATE 
    email = VALUES(email),
    password = VALUES(password),
    first_name = VALUES(first_name),
    last_name = VALUES(last_name),
    is_active = VALUES(is_active);

-- Verify the user was created
SELECT user_id, username, email, role FROM Users WHERE role = 'admin';
