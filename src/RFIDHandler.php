<?php
namespace App;

require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/RateLimiter.php';

class RFIDHandler {
    private $db;
    private $logger;
    private $rateLimiter;
    private $config;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        
        // Logger and RateLimiter are in global namespace, not App namespace
        if (class_exists('Logger')) {
            $this->logger = \Logger::getInstance();
        } else {
            // Fallback if Logger class is not available
            $this->logger = new class {
                public function info($msg, $context = []) {}
                public function error($msg, $context = []) {}
                public function warning($msg, $context = []) {}
                public function debug($msg, $context = []) {}
            };
        }
        
        if (class_exists('RateLimiter')) {
            $this->rateLimiter = \RateLimiter::getInstance();
        } else {
            // Fallback if RateLimiter is not available
            $this->rateLimiter = new class {
                public function checkRateLimit($type) { return true; }
            };
        }
        
        $this->config = require __DIR__ . '/../config/config.php';
    }
    
    /**
     * Process RFID check-in with validation and duplicate prevention
     */
    public function processCheckIn($rfidTag) {
        try {
            // Rate limiting check
            if (!$this->rateLimiter->checkRateLimit('checkin')) {
                $this->logger->warning('Check-in rate limit exceeded', ['rfid_tag' => $rfidTag]);
                return ['status' => 'error', 'message' => 'Too many requests. Please wait.', 'data' => []];
            }
            
            // Input validation
            $rfidTag = $this->validateRfidTag($rfidTag);
            if (!$rfidTag) {
                return ['status' => 'error', 'message' => 'Invalid RFID tag format', 'data' => []];
            }
            
            // Check if user exists
            $stmt = $this->db->prepare("SELECT id, name, is_active FROM users WHERE rfid_tag = ?");
            $stmt->bind_param("s", $rfidTag);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $this->logger->info('Check-in attempt with unregistered RFID tag', ['rfid_tag' => $rfidTag]);
                return ['status' => 'error', 'message' => 'RFID tag not registered', 'data' => []];
            }
            
            $user = $result->fetch_assoc();
            
            // Check if user is active
            if (!$user['is_active']) {
                $this->logger->info('Check-in attempt with inactive user', ['user_id' => $user['id'], 'rfid_tag' => $rfidTag]);
                return ['status' => 'error', 'message' => 'User account is inactive', 'data' => []];
            }
            
            // Get last check log with duplicate prevention
            $stmt = $this->db->prepare("SELECT check_type, check_time FROM check_logs WHERE user_id = ? ORDER BY check_time DESC LIMIT 1");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $lastCheck = $stmt->get_result()->fetch_assoc();
            
            // Prevent duplicate check-ins within 1 minute
            if ($lastCheck && (time() - strtotime($lastCheck['check_time'])) < 60) {
                $this->logger->info('Duplicate check-in attempt prevented', ['user_id' => $user['id']]);
                return ['status' => 'error', 'message' => 'Please wait before checking in again', 'data' => []];
            }
            
            // Auto-checkout users who forgot to check out after 24 hours
            $this->performAutoCheckout($user['id']);
            
            // Determine check type
            $checkType = (!$lastCheck || $lastCheck['check_type'] === 'out') ? 'in' : 'out';
            
            // Insert check log
            $stmt = $this->db->prepare("INSERT INTO check_logs (user_id, check_type, check_time) VALUES (?, ?, NOW())");
            $stmt->bind_param("is", $user['id'], $checkType);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to insert check log");
            }
            
            $this->logger->info('Successful check-in/out', [
                'user_id' => $user['id'],
                'check_type' => $checkType,
                'rfid_tag' => $rfidTag
            ]);
            
            return [
                'status' => 'success',
                'message' => "Successfully checked {$checkType}",
                'data' => [
                    'user' => $user['name'],
                    'check_type' => $checkType,
                    'time' => date('Y-m-d H:i:s')
                ]
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Check-in process error', [
                'error' => $e->getMessage(),
                'rfid_tag' => $rfidTag ?? 'unknown'
            ]);
            return ['status' => 'error', 'message' => 'System error. Please try again.', 'data' => []];
        }
    }
    
    /**
     * Process RFID check-out with validation
     */
    public function processCheckOut($rfidTag) {
        try {
            // Rate limiting check
            if (!$this->rateLimiter->checkRateLimit('checkin')) {
                $this->logger->warning('Check-out rate limit exceeded', ['rfid_tag' => $rfidTag]);
                return ['status' => 'error', 'message' => 'Too many requests. Please wait.', 'data' => []];
            }
            
            // Input validation
            $rfidTag = $this->validateRfidTag($rfidTag);
            if (!$rfidTag) {
                return ['status' => 'error', 'message' => 'Invalid RFID tag format', 'data' => []];
            }
            
            // Check if user exists
            $stmt = $this->db->prepare("SELECT id, name, is_active FROM users WHERE rfid_tag = ?");
            $stmt->bind_param("s", $rfidTag);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $this->logger->info('Check-out attempt with unregistered RFID tag', ['rfid_tag' => $rfidTag]);
                return ['status' => 'error', 'message' => 'RFID tag not registered', 'data' => []];
            }
            
            $user = $result->fetch_assoc();
            
            // Check if user is active
            if (!$user['is_active']) {
                $this->logger->info('Check-out attempt with inactive user', ['user_id' => $user['id']]);
                return ['status' => 'error', 'message' => 'User account is inactive', 'data' => []];
            }
            
            // Verify user has an active check-in
            $stmt = $this->db->prepare("SELECT check_type, check_time FROM check_logs WHERE user_id = ? ORDER BY check_time DESC LIMIT 1");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $lastCheck = $stmt->get_result()->fetch_assoc();
            
            if (!$lastCheck || $lastCheck['check_type'] === 'out') {
                $this->logger->info('Check-out attempted without active check-in', ['user_id' => $user['id']]);
                return ['status' => 'error', 'message' => 'No active check-in found. Please check in first.', 'data' => []];
            }
            
            // Prevent duplicate check-outs within 1 minute
            if ((time() - strtotime($lastCheck['check_time'])) < 60) {
                $this->logger->info('Duplicate check-out attempt prevented', ['user_id' => $user['id']]);
                return ['status' => 'error', 'message' => 'Please wait before checking out again', 'data' => []];
            }
            
            // Insert check-out log
            $stmt = $this->db->prepare("INSERT INTO check_logs (user_id, check_type, check_time) VALUES (?, 'out', NOW())");
            $stmt->bind_param("i", $user['id']);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to insert check-out log");
            }
            
            $this->logger->info('Successful check-out', [
                'user_id' => $user['id'],
                'rfid_tag' => $rfidTag
            ]);
            
            return [
                'status' => 'success',
                'message' => 'Successfully checked out',
                'data' => [
                    'user' => $user['name'],
                    'check_type' => 'out',
                    'time' => date('Y-m-d H:i:s')
                ]
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Check-out process error', [
                'error' => $e->getMessage(),
                'rfid_tag' => $rfidTag ?? 'unknown'
            ]);
            return ['status' => 'error', 'message' => 'System error. Please try again.', 'data' => []];
        }
    }
    
    /**
     * Validate RFID tag format (alphanumeric only, max 50 chars)
     */
    private function validateRfidTag($rfidTag) {
        $rfidTag = trim($rfidTag);
        
        if (empty($rfidTag)) {
            return false;
        }
        
        // Alphanumeric only, 4-50 characters
        if (!preg_match('/^[a-zA-Z0-9]{4,50}$/', $rfidTag)) {
            return false;
        }
        
        return $rfidTag;
    }
    
    /**
     * Validate user name (letters, spaces, hyphens only, max 100 chars)
     */
    public function validateName($name) {
        $name = trim($name);
        
        if (empty($name) || strlen($name) > 100) {
            return false;
        }
        
        // Letters, spaces, hyphens, apostrophes only
        if (!preg_match('/^[a-zA-Z\s\-\']+$/', $name)) {
            return false;
        }
        
        return $name;
    }
    
    /**
     * Validate department (alphanumeric, spaces, max 100 chars)
     */
    public function validateDepartment($department) {
        $department = trim($department);
        
        if (strlen($department) > 100) {
            return false;
        }
        
        // Alphanumeric, spaces, hyphens only
        if (!empty($department) && !preg_match('/^[a-zA-Z0-9\s\-]+$/', $department)) {
            return false;
        }
        
        return $department;
    }
    
    /**
     * Get user by RFID tag
     */
    public function getUserByRFID($rfidTag) {
        try {
            $rfidTag = $this->validateRfidTag($rfidTag);
            if (!$rfidTag) {
                return null;
            }
            
            $stmt = $this->db->prepare("SELECT id, name, rfid_tag, department, is_active FROM users WHERE rfid_tag = ?");
            $stmt->bind_param("s", $rfidTag);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return null;
            }
            
            return $result->fetch_assoc();
            
        } catch (Exception $e) {
            $this->logger->error('Error getting user by RFID', [
                'error' => $e->getMessage(),
                'rfid_tag' => $rfidTag
            ]);
            return null;
        }
    }
    
    /**
     * Auto-checkout users who have been checked in for more than 24 hours
     */
    private function performAutoCheckout($currentUserId = null) {
        try {
            $autoCheckoutHours = $this->config['rfid']['auto_checkout_hours'];
            $cutoffTime = date('Y-m-d H:i:s', time() - ($autoCheckoutHours * 3600));
            
            // Find users who need auto-checkout
            $query = "SELECT DISTINCT cl1.user_id, u.name 
                     FROM check_logs cl1 
                     JOIN users u ON cl1.user_id = u.id
                     WHERE cl1.check_type = 'in' 
                     AND cl1.check_time < ? 
                     AND NOT EXISTS (
                         SELECT 1 FROM check_logs cl2 
                         WHERE cl2.user_id = cl1.user_id 
                         AND cl2.check_time > cl1.check_time 
                         AND cl2.check_type = 'out'
                     )";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("s", $cutoffTime);
            $stmt->execute();
            $usersToCheckout = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Auto-checkout each user
            foreach ($usersToCheckout as $user) {
                $stmt = $this->db->prepare("INSERT INTO check_logs (user_id, check_type, check_time) VALUES (?, 'out', NOW())");
                $stmt->bind_param("i", $user['user_id']);
                $stmt->execute();
                
                $this->logger->info('Auto-checkout performed', [
                    'user_id' => $user['user_id'],
                    'user_name' => $user['name']
                ]);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Auto-checkout error', ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Get recent check-in/out logs with user information
     */
    public function getRecentLogs($limit = 10) {
        try {
            // Ensure limit is within reasonable bounds
            $limit = max(1, min(100, (int)$limit));
            
            $query = "SELECT u.name, u.rfid_tag, c.check_time, c.check_type, c.user_id
                      FROM check_logs c 
                      JOIN users u ON c.user_id = u.id 
                      ORDER BY c.check_time DESC 
                      LIMIT ?";
            
            $stmt = $this->db->prepare($query);
            if (!$stmt) {
                throw new Exception("Failed to prepare query: " . $this->db->error);
            }
            
            $stmt->bind_param("i", $limit);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute query: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $logs = $result->fetch_all(MYSQLI_ASSOC);
            
            $this->logger->debug('Recent logs retrieved', [
                'count' => count($logs),
                'limit' => $limit
            ]);
            
            return $logs;
            
        } catch (Exception $e) {
            $this->logger->error('Error retrieving recent logs', [
                'error' => $e->getMessage(),
                'limit' => $limit
            ]);
            
            // Return empty array on error instead of throwing
            return [];
        }
    }
    
    /**
     * Get system statistics for dashboard
     */
    public function getSystemStats() {
        try {
            $stats = [];
            
            // Total users
            $result = $this->db->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
            $stats['total_users'] = $result->fetch_assoc()['total'];
            
            // Today's check-ins
            $stmt = $this->db->prepare("SELECT COUNT(*) as today FROM check_logs WHERE DATE(check_time) = CURDATE() AND check_type = 'in'");
            $stmt->execute();
            $stats['todays_checkins'] = $stmt->get_result()->fetch_assoc()['today'];
            
            // Currently checked in users
            $query = "SELECT COUNT(DISTINCT cl1.user_id) as checked_in
                     FROM check_logs cl1 
                     WHERE cl1.check_type = 'in' 
                     AND NOT EXISTS (
                         SELECT 1 FROM check_logs cl2 
                         WHERE cl2.user_id = cl1.user_id 
                         AND cl2.check_time > cl1.check_time 
                         AND cl2.check_type = 'out'
                     )";
            $result = $this->db->query($query);
            $stats['currently_checked_in'] = $result->fetch_assoc()['checked_in'];
            
            // This week's activity
            $stmt = $this->db->prepare("SELECT COUNT(*) as week FROM check_logs WHERE check_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stmt->execute();
            $stats['week_activity'] = $stmt->get_result()->fetch_assoc()['week'];
            
            return $stats;
            
        } catch (Exception $e) {
            $this->logger->error('Error retrieving system stats', ['error' => $e->getMessage()]);
            return [
                'total_users' => 0,
                'todays_checkins' => 0,
                'currently_checked_in' => 0,
                'week_activity' => 0
            ];
        }
    }
}
