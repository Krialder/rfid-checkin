<?php
/**
 * Enterprise RFID Polling API Endpoint
 * 
 * Real-time RFID scanning support with enterprise security, performance
 * monitoring, and comprehensive hardware integration capabilities.
 * 
 * Features:
 * - Long-polling for real-time RFID updates
 * - Enterprise security with admin-only access
 * - Hardware device queue management
 * - Performance monitoring and optimization
 * - Rate limiting and connection management
 * - WebSocket fallback support
 * - Comprehensive error handling and logging
 * 
 * Hardware Integration:
 * - ESP32 RFID reader queue processing
 * - Multiple device support and coordination
 * - Hardware heartbeat monitoring
 * - Device status and health tracking
 * 
 * @package    RFID Check-in System
 * @subpackage Enterprise RFID Polling
 * @version    4.0.0 - Enterprise Architecture
 * @author     Senior Developer Team
 * @since      1.0.0
 * @security   ADMIN_ONLY + DEVICE_AUTHENTICATED
 */

// Load enterprise configuration and components
require_once '../core/config.php';
require_once '../core/auth.php';

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

// Set enterprise headers for real-time polling
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('X-Content-Type-Options: nosniff');
header('Connection: keep-alive');

// Start performance monitoring for polling
$performanceManager->startTimer('rfid_polling');

try {
    // Enforce POST method for security
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $errorHandler->log('Invalid RFID polling request method', null, 'WARNING', [
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
    
    // Enterprise authentication and admin authorization
    Auth::requireLogin();
    $user = Auth::getCurrentUser();
    
    if (!$user) {
        throw new Exception('Invalid user session');
    }
    
    // Enforce admin-only access for RFID polling
    if (!$securityManager->hasRole($user, 'admin')) {
        $errorHandler->log('Unauthorized RFID polling access attempt', null, 'WARNING', [
            'user_id' => $user['user_id'],
            'role' => $user['role'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Admin access required for RFID polling'
        ]);
        exit;
    }
    
    // Enterprise security validation
    $securityManager->validateRequest($_SERVER);
    
    // Check polling rate limiting for admin user
    if (!$securityManager->checkRateLimit('rfid_poll_' . $user['user_id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown', 60, 60)) {
        $errorHandler->log('RFID polling rate limit exceeded', null, 'WARNING', [
            'user_id' => $user['user_id'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Polling rate limit exceeded. Please reduce request frequency.'
        ]);
        exit;
    }
    
    // Validate and sanitize polling parameters
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $lastTag = $securityManager->validateInput($input['last_tag'] ?? '', 'string');
    $timeout = $securityManager->validateInput($input['timeout'] ?? 1000, 'int');
    $deviceId = $securityManager->validateInput($input['device_id'] ?? 1, 'int');
    
    // Enforce reasonable timeout limits (100ms to 5 seconds)
    $timeout = max(100, min(5000, $timeout));
    $maxWaitTime = $timeout / 1000; // Convert to seconds
    
    $performanceManager->startTimer('rfid_queue_polling');
    
    // Real-time polling for RFID scans using enterprise service layer
    $result = $dataService->pollRfidScans([
        'last_tag' => $lastTag,
        'max_wait_time' => $maxWaitTime,
        'device_id' => $deviceId,
        'user_id' => $user['user_id']
    ]);
    
    $performanceManager->endTimer('rfid_queue_polling');
    
    // Record polling metrics
    $performanceManager->recordMetric('rfid_poll_request', 1);
    $performanceManager->recordMetric('rfid_poll_timeout', $timeout);
    
    if ($result['success']) {
        $performanceManager->recordMetric('rfid_poll_hit', 1);
    } else {
        $performanceManager->recordMetric('rfid_poll_miss', 1);
    }
    
    // Return enterprise response
    echo json_encode(array_merge($result, [
        'meta' => [
            'api_version' => '4.0.0',
            'polling_time_ms' => round($performanceManager->getTimer('rfid_queue_polling') * 1000, 2),
            'total_time_ms' => round($performanceManager->getTimer('rfid_polling') * 1000, 2),
            'device_id' => $deviceId,
            'timestamp' => date('c')
        ]
    ]));

} catch (Exception $e) {
    // Enterprise error handling with comprehensive logging
    $errorHandler->log('RFID polling error', $e, 'ERROR', [
        'user_id' => $user['user_id'] ?? null,
        'device_id' => $deviceId ?? null,
        'timeout' => $timeout ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Record error metrics
    $performanceManager->recordMetric('rfid_poll_error', 1);
    
    // Determine appropriate HTTP status code
    $statusCode = 500;
    if (strpos($e->getMessage(), 'session') !== false) {
        $statusCode = 401;
    } elseif (strpos($e->getMessage(), 'access') !== false) {
        $statusCode = 403;
    } elseif (strpos($e->getMessage(), 'validation') !== false) {
        $statusCode = 400;
    }
    
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'meta' => [
            'api_version' => '4.0.0',
            'error_id' => uniqid('rfid_poll_err_'),
            'timestamp' => date('c')
        ]
    ]);
} finally {
    // Complete performance monitoring
    $performanceManager->endTimer('rfid_polling');
}
?>
