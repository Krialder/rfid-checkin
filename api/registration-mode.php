<?php
/**
 * Registration Mode API
 * Manages the system state for RFID registration mode
 * When enabled, ESP32 devices will accept any RFID tag for registration
 */

// Turn off all error reporting to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

require_once '../core/config.php';
require_once '../core/database.php';
require_once '../core/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Only admin users can manage registration mode
    if (!Auth::isLoggedIn() || !Auth::hasRole(['admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }

    $db = getDB();
    $current_user = Auth::getCurrentUser();
    
    // Test database connection first
    if (!$db) {
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    
    // Ensure system_settings table exists with better error handling
    try {
        $createTableSQL = "
            CREATE TABLE IF NOT EXISTS `system_settings` (
              `setting_id` INT AUTO_INCREMENT PRIMARY KEY,
              `setting_key` VARCHAR(100) NOT NULL UNIQUE,
              `setting_value` TEXT,
              `description` VARCHAR(255),
              `updated_by` INT,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              KEY `idx_setting_key` (`setting_key`),
              FOREIGN KEY (`updated_by`) REFERENCES `Users`(`user_id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $db->exec($createTableSQL);
    } catch (Exception $tableError) {
        // If table creation fails, try without foreign key constraint
        try {
            $createTableSQL = "
                CREATE TABLE IF NOT EXISTS `system_settings` (
                  `setting_id` INT AUTO_INCREMENT PRIMARY KEY,
                  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
                  `setting_value` TEXT,
                  `description` VARCHAR(255),
                  `updated_by` INT,
                  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  KEY `idx_setting_key` (`setting_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            $db->exec($createTableSQL);
        } catch (Exception $fallbackError) {
            echo json_encode(['error' => 'Database setup failed']);
            exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get current registration mode status
        $stmt = $db->prepare("
            SELECT setting_value, updated_by, updated_at 
            FROM system_settings 
            WHERE setting_key = 'rfid_registration_mode'
        ");
        $stmt->execute();
        $result = $stmt->fetch();
        
        $is_enabled = false;
        $last_updated = null;
        $updated_by = null;
        
        if ($result) {
            $is_enabled = (bool)$result['setting_value'];
            $last_updated = $result['updated_at'];
            $updated_by = $result['updated_by'];
        }
        
        // Get active session info if mode is enabled
        $session_info = null;
        if ($is_enabled) {
            $stmt = $db->prepare("
                SELECT u.first_name, u.last_name, s.updated_at
                FROM system_settings s
                LEFT JOIN users u ON s.updated_by = u.user_id
                WHERE s.setting_key = 'rfid_registration_mode'
            ");
            $stmt->execute();
            $session_data = $stmt->fetch();
            
            if ($session_data) {
                $session_info = [
                    'admin_name' => trim($session_data['first_name'] . ' ' . $session_data['last_name']),
                    'started_at' => $session_data['updated_at']
                ];
            }
        }
        
        echo json_encode([
            'registration_mode_enabled' => $is_enabled,
            'last_updated' => $last_updated,
            'session_info' => $session_info
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Toggle registration mode
        $action = $_POST['action'] ?? '';
        
        if ($action === 'enable') {
            // Enable registration mode
            $stmt = $db->prepare("
                INSERT INTO system_settings (setting_key, setting_value, description, updated_by) 
                VALUES ('rfid_registration_mode', 1, 'RFID Registration Mode - allows unregistered tags', ?)
                ON DUPLICATE KEY UPDATE 
                setting_value = 1, updated_by = ?, updated_at = NOW()
            ");
            $stmt->execute([$current_user['user_id'], $current_user['user_id']]);
            
            // Log the action
            $stmt = $db->prepare("
                INSERT INTO activitylog (user_id, action, details, ip_address, timestamp) 
                VALUES (?, 'rfid_registration_mode_enabled', ?, ?, NOW())
            ");
            $stmt->execute([
                $current_user['user_id'],
                json_encode(['admin' => $current_user['first_name'] . ' ' . $current_user['last_name']]),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Registration mode enabled',
                'registration_mode_enabled' => true
            ]);
            
        } elseif ($action === 'disable') {
            // Disable registration mode
            $stmt = $db->prepare("
                UPDATE system_settings 
                SET setting_value = 0, updated_by = ?, updated_at = NOW()
                WHERE setting_key = 'rfid_registration_mode'
            ");
            $stmt->execute([$current_user['user_id']]);
            
            // Log the action
            $stmt = $db->prepare("
                INSERT INTO activitylog (user_id, action, details, ip_address, timestamp) 
                VALUES (?, 'rfid_registration_mode_disabled', ?, ?, NOW())
            ");
            $stmt->execute([
                $current_user['user_id'],
                json_encode(['admin' => $current_user['first_name'] . ' ' . $current_user['last_name']]),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Registration mode disabled',
                'registration_mode_enabled' => false
            ]);
            
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action. Use "enable" or "disable"']);
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    // Log error but don't expose details
    error_log('Registration Mode API Error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Registration mode operation failed',
        'message' => 'Internal server error'
    ]);
}
?>
