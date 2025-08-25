<?php
/**
 * Configuration Template for Electronic Check-in System
 * 
 * INSTRUCTIONS:
 * 1. Copy this file to 'config.php' (same directory)
 * 2. Update all the values below with your actual settings
 * 3. The real config.php file is ignored by Git for security
 */

// Database Configuration
define('DB_HOST', 'localhost');                    // Your database host
define('DB_NAME', 'rfid_checkin_system');         // Your database name
define('DB_USER', 'your_db_user');                // Your database username
define('DB_PASS', 'your_secure_password');        // Your database password
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_NAME', 'Electronic Check-in System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://your-domain.com/rfid-checkin');  // Update with your URL

// Security Configuration
define('SESSION_LIFETIME', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 8);

// Error Reporting
define('DEBUG_MODE', false);  // Set to false in production

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set your timezone
date_default_timezone_set('Europe/Berlin');  // Update to your timezone
