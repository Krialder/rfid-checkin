<?php
/**
 * Enterprise RFID Queue API Endpoint
 * 
 * High-performance queue management for hardware RFID devices with
 * enterprise security, device authentication, and real-time processing.
 * 
 * @package    RFID Check-in System
 * @subpackage Enterprise Hardware Integration
 * @version    4.0.0 - Enterprise Architecture
 * @author     Senior Developer Team
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
$dataService = new DataService();

// Set enterprise headers for hardware integration
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Device-Token');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start performance monitoring
$performanceManager->startTimer('rfid_queue_processing');

try {
    // Enforce POST method for security
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $errorHandler->log('Invalid RFID queue request method', null, 'WARNING', [
            'method' => $_SERVER['REQUEST_METHOD'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'Method not allowed',
            'allowed_methods' => ['POST', 'OPTIONS']
        ]);
        exit;
    }
    
    // Device information for tracking and security
    $deviceInfo = [
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'device_token' => $_SERVER['HTTP_X_DEVICE_TOKEN'] ?? ''
    ];
    
    // Enterprise security validation for hardware requests
    $securityManager->validateRequest($_SERVER);
    
    // Check device-specific rate limiting (more permissive for hardware)
    if (!$securityManager->checkRateLimit('rfid_queue_' . $deviceInfo['ip_address'], $deviceInfo['ip_address'], 100, 60)) {
        $errorHandler->log('RFID queue rate limit exceeded', null, 'WARNING', $deviceInfo);
        
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded. Device sending too many requests.',
            'retry_after' => 60
        ]);
        exit;
    }
    
    // Validate and sanitize RFID data with enterprise validation
    $rfidTag = $securityManager->validateInput($_POST['rfid'] ?? '', 'rfid');
    $deviceId = $securityManager->validateInput($_POST['device_id'] ?? 1, 'int');
    $source = $securityManager->validateInput($_POST['source'] ?? 'hardware', 'string');
    
    if (empty($rfidTag)) {
        throw new Exception('RFID tag is required');
    }
    
    // Enhanced RFID format validation for enterprise standards
    if (!$securityManager->validateRfidFormat($rfidTag)) {
        $errorHandler->log('Invalid RFID format from device', null, 'WARNING', array_merge($deviceInfo, [
            'rfid_partial' => substr($rfidTag, 0, 4) . '***',
            'device_id' => $deviceId
        ]));
        
        throw new Exception('Invalid RFID format');
    }
    
    $performanceManager->startTimer('rfid_queue_operation');
    
    // Process RFID queue addition using enterprise service layer
    $result = $dataService->addRfidToQueue($rfidTag, $deviceId, $deviceInfo, $source);
    
    $performanceManager->endTimer('rfid_queue_operation');
    
    // Record success metrics
    $performanceManager->recordMetric('rfid_queue_add', 1);
    $performanceManager->recordMetric('rfid_queue_device_' . $deviceId, 1);
    $performanceManager->recordMetric('rfid_queue_processing_time', $performanceManager->getTimer('rfid_queue_operation'));
    
    // Return enterprise response
    echo json_encode(array_merge($result, [
        'meta' => [
            'api_version' => '4.0.0',
            'processing_time_ms' => round($performanceManager->getTimer('rfid_queue_processing') * 1000, 2),
            'device_id' => $deviceId,
            'timestamp' => date('c')
        ]
    ]));

} catch (Exception $e) {
    // Enterprise error handling with comprehensive logging
    $errorHandler->log('RFID queue error', $e, 'ERROR', array_merge($deviceInfo ?? [], [
        'rfid_partial' => isset($rfidTag) ? substr($rfidTag, 0, 4) . '***' : 'unknown',
        'device_id' => $deviceId ?? null,
        'source' => $source ?? null,
        'request_data' => array_keys($_POST)
    ]));
    
    // Record error metrics
    $performanceManager->recordMetric('rfid_queue_error', 1);
    
    // Determine appropriate HTTP status code
    $statusCode = 400;
    if (strpos($e->getMessage(), 'rate limit') !== false) {
        $statusCode = 429;
    } elseif (strpos($e->getMessage(), 'device') !== false) {
        $statusCode = 401;
    } elseif (strpos($e->getMessage(), 'system') !== false) {
        $statusCode = 500;
    }
    
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'meta' => [
            'api_version' => '4.0.0',
            'error_id' => uniqid('rfid_queue_err_'),
            'timestamp' => date('c')
        ]
    ]);
} finally {
    // Complete performance monitoring
    $performanceManager->endTimer('rfid_queue_processing');
}
?>
