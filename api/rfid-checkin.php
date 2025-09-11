<?php
/**
 * RFID Check-in Handler
 * Optimized version with proper error handling and security
 */

require_once '../core/auth.php';
require_once '../core/database.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$db = getDB();

try {
    // Get RFID code from POST request
    $rfid = trim($_POST['rfid'] ?? '');
    
    if (empty($rfid)) {
        throw new Exception('RFID code is required');
    }
    
    // Validate RFID format (basic validation)
    if (!preg_match('/^[A-Za-z0-9]{6,20}$/', $rfid)) {
        throw new Exception('Invalid RFID format');
    }
    
    // Get device information if provided
    $device_id = intval($_POST['device_id'] ?? 1);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $db->beginTransaction();
    
    // Check if registration mode is enabled
    $stmt = $db->prepare("
        SELECT setting_value 
        FROM system_settings 
        WHERE setting_key = 'rfid_registration_mode'
    ");
    $stmt->execute();
    $registration_mode = $stmt->fetch();
    $is_registration_mode = $registration_mode && (bool)$registration_mode['setting_value'];
    
    // Check if RFID exists and get user info
    $stmt = $db->prepare("
        SELECT user_id, CONCAT(first_name, ' ', COALESCE(last_name, '')) as name, is_active 
        FROM users 
        WHERE rfid_tag = ? AND is_active = 1
    ");
    $stmt->execute([$rfid]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Log failed attempt with NULL user_id (should work after database fix)
            try {
                $stmt = $db->prepare("
                    INSERT INTO accesslogs (user_id, device_id, ip_address, action, status, resource, timestamp) 
                    VALUES (NULL, ?, ?, 'rfid_scan', 'failure', ?, NOW())
                ");
                $stmt->execute([$device_id, $ip_address, "RFID: $rfid"]);
            } catch (PDOException $logError) {
                // If foreign key constraint fails, log without user_id column
                error_log('accesslogs constraint error: ' . $logError->getMessage());
                try {
                    $stmt = $db->prepare("
                        INSERT INTO accesslogs (device_id, ip_address, action, status, resource, timestamp) 
                        VALUES (?, ?, 'rfid_scan', 'failure', ?, NOW())
                    ");
                    $stmt->execute([$device_id, $ip_address, "RFID: $rfid"]);
                } catch (PDOException $fallbackError) {
                    // If even that fails, just continue without logging
                    error_log('accesslogs fallback failed: ' . $fallbackError->getMessage());
                }
            }        $db->commit();
        
        if ($is_registration_mode) {
            // In registration mode, accept unregistered RFID tags
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'RFID scanned for registration',
                'action' => 'registration',
                'rfid' => $rfid,
                'registration_mode' => true,
                'user' => [
                    'name' => 'Unregistered User',
                    'user_id' => null
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit();
        } else {
            http_response_code(404);
            echo json_encode([
                'error' => 'RFID not recognized',
                'rfid' => $rfid,
                'registration_mode' => false
            ]);
            exit();
        }
    }
    
    // Check for current active events
    $stmt = $db->prepare("
        SELECT event_id, name as event_name, location 
        FROM events 
        WHERE DATE(start_date) = CURDATE() 
        AND start_time <= TIME(NOW()) 
        AND end_time >= TIME(NOW()) 
        AND active = 1
        ORDER BY start_time ASC 
        LIMIT 1
    ");
    $stmt->execute();
    $current_event = $stmt->fetch();
    
    if (!$current_event) {
        // Log attempt but no event
        $stmt = $db->prepare("
            INSERT INTO accesslogs (user_id, rfid_tag, device_id, ip_address, action, status, details, timestamp) 
            VALUES (?, ?, ?, ?, 'failed_login', 'failed', ?, NOW())
        ");
        $stmt->execute([$user['user_id'], $rfid, $device_id, $ip_address, json_encode(['error' => 'No active event found'])]);
        
        $db->commit();
        
        echo json_encode([
            'warning' => 'No active event found for check-in',
            'user' => $user['name'],
            'rfid' => $rfid
        ]);
        exit();
    }
    
    // Check if user is already checked in to this event
    $stmt = $db->prepare("
        SELECT checkin_id, status 
        FROM checkin 
        WHERE user_id = ? AND event_id = ? AND DATE(checkin_time) = CURDATE()
        ORDER BY checkin_time DESC 
        LIMIT 1
    ");
    $stmt->execute([$user['user_id'], $current_event['event_id']]);
    $existing_checkin = $stmt->fetch();
    
    if ($existing_checkin && $existing_checkin['status'] === 'checked_in') {
        // User is checking out
        $stmt = $db->prepare("
            UPDATE checkin 
            SET checkout_time = NOW(), status = 'checked_out', updated_at = NOW()
            WHERE checkin_id = ?
        ");
        $stmt->execute([$existing_checkin['checkin_id']]);
        
        $action = 'checkout';
        $message = 'Successfully checked out';
        
        // Note: current_participants column removed - calculated dynamically
        
    } else {
        // User is checking in (new or re-entry)
        $stmt = $db->prepare("
            INSERT INTO checkin (user_id, event_id, checkin_time, checkin_method, device_id, ip_address, status) 
            VALUES (?, ?, NOW(), 'rfid', ?, ?, 'checked_in')
        ");
        $stmt->execute([$user['user_id'], $current_event['event_id'], $device_id, $ip_address]);
        
        $action = 'checkin';
        $message = 'Successfully checked in';
        
        // Note: current_participants column removed - calculated dynamically
    }
    
    // Log successful access
    $stmt = $db->prepare("
        INSERT INTO accesslogs (user_id, rfid_tag, device_id, ip_address, action, status, details, timestamp) 
        VALUES (?, ?, ?, ?, ?, 'success', ?, NOW())
    ");
    $stmt->execute([
        $user['user_id'], 
        $rfid, 
        $device_id, 
        $ip_address, 
        $action, // This will be 'checkin' or 'checkout'
        json_encode(['event' => $current_event['event_name'], 'location' => $current_event['location']])
    ]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'user' => [
            'name' => $user['name'],
            'user_id' => $user['user_id']
        ],
        'event' => [
            'name' => $current_event['event_name'],
            'location' => $current_event['location']
        ],
        'action' => $action,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollback();
    }
    error_log('RFID Handler Error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Check-in failed',
        'message' => (defined('DEBUG_MODE') && DEBUG_MODE) ? $e->getMessage() : 'Internal server error'
    ]);
}
