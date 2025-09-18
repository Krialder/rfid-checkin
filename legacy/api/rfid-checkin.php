<?php
/**
 * Enterprise RFID Check-in Handler
 * 
 * High-performance API endpoint for processing RFID tag scans with
 * enterprise security, comprehensive validation, and real-time
 * check-in/checkout functionality.
 * 
 * Features:
 * - Enterprise security validation with CSRF protection
 * - Repository pattern for all data operations
 * - Real-time event matching and status management
 * - Comprehensive audit logging with device tracking
 * - Registration mode support for onboarding
 * - Performance monitoring and rate limiting
 * - Hardware device integration support
 * 
 * Security Layers:
 * - Input validation and sanitization
 * - Rate limiting per device/IP
 * - RFID format validation with enterprise patterns
 * - Device authentication and authorization
 * - Comprehensive activity logging
 * 
 * @package    RFID Check-in System
 * @subpackage Enterprise RFID Processing
 * @version    4.0.0 - Enterprise Architecture
 * @author     Senior Developer Team
 * @since      1.0.0
 * @security   DEVICE_AUTHENTICATED + RATE_LIMITED
 */

// Load enterprise configuration and components
require_once '../core/config.php';

// Initialize enterprise components
$container = EnterpriseContainer::getInstance();
$errorHandler = $container->get('errorHandler');
$securityManager = $container->get('securityManager');
$performanceManager = $container->get('performanceManager');

// Initialize repositories and services
$userRepository = new UserRepository();
$eventRepository = new EventRepository();
$checkinRepository = new CheckinRepository();
$dataService = new DataService();

// Set enterprise headers for RFID API
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Max-Age: 3600');

// Start performance monitoring for RFID processing
$performanceManager->startTimer('rfid_processing');

try {
    // Enforce POST method for security
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $errorHandler->log('Invalid RFID request method', null, 'WARNING', [
            'method' => $_SERVER['REQUEST_METHOD'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'Method not allowed',
            'allowed_methods' => ['POST']
        ]);
        exit;
    }
    
    // Enterprise security validation
    $securityManager->validateRequest($_SERVER);
    
    // Get and validate device information
    $deviceInfo = [
        'device_id' => $securityManager->validateInput($_POST['device_id'] ?? 1, 'int'),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    // Check device-specific rate limiting
    $rateLimitKey = 'rfid_device_' . $deviceInfo['device_id'];
    if (!$securityManager->checkRateLimit($rateLimitKey, $deviceInfo['ip_address'], 30, 60)) {
        $errorHandler->log('RFID device rate limit exceeded', null, 'WARNING', $deviceInfo);
        
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Device rate limit exceeded. Please wait before scanning again.',
            'retry_after' => 60
        ]);
        exit;
    }
    
    // Get and validate RFID code with enterprise validation
    $rfidTag = $securityManager->validateInput($_POST['rfid'] ?? '', 'rfid');
    
    if (empty($rfidTag)) {
        throw new Exception('RFID code is required');
    }
    
    // Enhanced RFID format validation for enterprise standards
    if (!$securityManager->validateRfidFormat($rfidTag)) {
        $errorHandler->log('Invalid RFID format detected', null, 'WARNING', [
            'rfid_partial' => substr($rfidTag, 0, 4) . '***',
            'device_info' => $deviceInfo
        ]);
        
        throw new Exception('Invalid RFID format');
    }
    
    $performanceManager->startTimer('rfid_lookup');
    
    // Process RFID check-in using enterprise service layer
    $result = $dataService->processRfidCheckin($rfidTag, $deviceInfo);
    
    $performanceManager->endTimer('rfid_lookup');
    
    // Record successful processing metrics
    $performanceManager->recordMetric('rfid_scan_success', 1);
    $performanceManager->recordMetric('rfid_processing_time', $performanceManager->getTimer('rfid_processing'));
    
    // Return enterprise response
    echo json_encode(array_merge($result, [
        'meta' => [
            'api_version' => '4.0.0',
            'processing_time_ms' => round($performanceManager->getTimer('rfid_processing') * 1000, 2),
            'timestamp' => date('c')
        ]
    ]));

} catch (Exception $e) {
    // Enterprise error handling with comprehensive logging
    $errorHandler->log('RFID processing error', $e, 'ERROR', [
        'rfid_partial' => isset($rfidTag) ? substr($rfidTag, 0, 4) . '***' : 'unknown',
        'device_info' => $deviceInfo ?? [],
        'request_data' => array_keys($_POST)
    ]);
    
    // Record error metrics
    $performanceManager->recordMetric('rfid_scan_error', 1);
    
    // Determine appropriate HTTP status code
    $statusCode = 400;
    if (strpos($e->getMessage(), 'rate limit') !== false) {
        $statusCode = 429;
    } elseif (strpos($e->getMessage(), 'not found') !== false) {
        $statusCode = 404;
    } elseif (strpos($e->getMessage(), 'system') !== false) {
        $statusCode = 500;
    }
    
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'meta' => [
            'api_version' => '4.0.0',
            'error_id' => uniqid('rfid_err_'),
            'timestamp' => date('c')
        ]
    ]);
} finally {
    // Complete performance monitoring
    $performanceManager->endTimer('rfid_processing');
}
?>
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
