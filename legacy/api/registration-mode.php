<?php
/**
 * Enterprise Registration Mode API
 * 
 * Secure management of RFID registration mode with enterprise
 * security, comprehensive logging, and real-time state management.
 * 
 * @package    RFID Check-in System
 * @subpackage Enterprise Registration Management
 * @version    4.0.0 - Enterprise Architecture
 * @author     Senior Developer Team
 * @security   ADMIN_ONLY + CSRF_PROTECTED
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
$dataService = new DataService();

// Set enterprise headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start performance monitoring
$performanceManager->startTimer('registration_mode_api');

try {
    // Enterprise authentication and admin authorization
    Auth::requireLogin();
    $user = Auth::getCurrentUser();
    
    if (!$user) {
        throw new Exception('Invalid user session');
    }
    
    // Enforce admin-only access
    if (!$securityManager->hasRole($user, 'admin')) {
        $errorHandler->log('Unauthorized registration mode access attempt', null, 'WARNING', [
            'user_id' => $user['user_id'],
            'role' => $user['role'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Admin access required for registration mode management'
        ]);
        exit;
    }
    
    // Enterprise security validation
    $securityManager->validateRequest($_SERVER);
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get current registration mode status
        $registrationModeData = $dataService->getRegistrationModeStatus();
        
        $performanceManager->recordMetric('registration_mode_status_check', 1);
        
        echo json_encode([
            'success' => true,
            'data' => $registrationModeData,
            'meta' => [
                'api_version' => '4.0.0',
                'timestamp' => date('c')
            ]
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token for state-changing operations
        $securityManager->validateCsrfToken($_POST['csrf_token'] ?? '');
        
        // Check rate limiting for registration mode changes
        if (!$securityManager->checkRateLimit('registration_mode_' . $user['user_id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown', 5, 300)) {
            $errorHandler->log('Registration mode rate limit exceeded', null, 'WARNING', [
                'user_id' => $user['user_id']
            ]);
            
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'error' => 'Too many registration mode changes. Please wait before trying again.'
            ]);
            exit;
        }
        
        $action = $securityManager->validateInput($_POST['action'] ?? '', 'string', ['enable', 'disable']);
        
        if (!$action) {
            throw new Exception('Invalid action. Use "enable" or "disable"');
        }
        
        $performanceManager->startTimer('registration_mode_toggle');
        
        // Process registration mode change using enterprise service layer
        $result = $dataService->setRegistrationMode($action === 'enable', $user['user_id'], [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        $performanceManager->endTimer('registration_mode_toggle');
        
        $performanceManager->recordMetric('registration_mode_' . $action, 1);
        
        echo json_encode(array_merge($result, [
            'meta' => [
                'api_version' => '4.0.0',
                'processing_time_ms' => round($performanceManager->getTimer('registration_mode_toggle') * 1000, 2),
                'timestamp' => date('c')
            ]
        ]));
        
    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'Method not allowed',
            'allowed_methods' => ['GET', 'POST', 'OPTIONS']
        ]);
    }

} catch (Exception $e) {
    // Enterprise error handling
    $errorHandler->log('Registration mode API error', $e, 'ERROR', [
        'user_id' => $user['user_id'] ?? null,
        'method' => $_SERVER['REQUEST_METHOD'],
        'action' => $_POST['action'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    $performanceManager->recordMetric('registration_mode_error', 1);
    
    $statusCode = 500;
    if (strpos($e->getMessage(), 'session') !== false) {
        $statusCode = 401;
    } elseif (strpos($e->getMessage(), 'access') !== false) {
        $statusCode = 403;
    } elseif (strpos($e->getMessage(), 'action') !== false || strpos($e->getMessage(), 'validation') !== false) {
        $statusCode = 400;
    }
    
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'meta' => [
            'api_version' => '4.0.0',
            'error_id' => uniqid('reg_mode_err_'),
            'timestamp' => date('c')
        ]
    ]);
} finally {
    $performanceManager->endTimer('registration_mode_api');
}
?>
