<?php
/**
 * Enterprise Manual Check-in API Handler
 * 
 * Secure API endpoint for processing manual check-ins from dashboard
 * and mobile applications with comprehensive validation and monitoring.
 * 
 * Features:
 * - Enterprise security with CSRF protection
 * - Repository pattern for all data operations
 * - Real-time event validation and capacity management
 * - Comprehensive audit logging and monitoring
 * - User permission and group validation
 * - Performance metrics and error tracking
 * - Rate limiting and abuse prevention
 * 
 * Validation Layers:
 * - User authentication and session validation
 * - Event availability and capacity checking
 * - Time-based access control and restrictions
 * - User group membership verification
 * - Input sanitization and format validation
 * 
 * @package    RFID Check-in System
 * @subpackage Enterprise Manual Check-in
 * @version    4.0.0 - Enterprise Architecture
 * @author     Senior Developer Team
 * @since      1.0.0
 * @security   AUTHENTICATED_USERS_ONLY + CSRF_PROTECTED
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

// Set enterprise headers for manual check-in API
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Start performance monitoring
$performanceManager->startTimer('manual_checkin');

try {
    // Enforce POST method for security
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $errorHandler->log('Invalid manual check-in request method', null, 'WARNING', [
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
    
    // Enterprise authentication and session validation
    Auth::requireLogin();
    $user = Auth::getCurrentUser();
    
    if (!$user) {
        throw new Exception('Invalid user session');
    }
    
    // Enterprise security validation including CSRF
    $securityManager->validateRequest($_SERVER);
    $securityManager->validateCsrfToken($_POST['csrf_token'] ?? '');
    
    // Check manual check-in rate limiting
    if (!$securityManager->checkRateLimit('manual_checkin_' . $user['user_id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown', 5, 300)) {
        $errorHandler->log('Manual check-in rate limit exceeded', null, 'WARNING', [
            'user_id' => $user['user_id'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Too many check-in attempts. Please wait before trying again.',
            'retry_after' => 300
        ]);
        exit;
    }
    
    // Validate and sanitize input with enterprise validation
    $eventId = $securityManager->validateInput($_POST['event_id'] ?? 0, 'int');
    $deviceInfo = [
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'source' => 'manual_dashboard'
    ];
    
    if (!$eventId) {
        throw new Exception('Valid event ID is required');
    }
    
    // Check user permissions for manual check-in
    if (!$securityManager->hasPermission($user, 'manual_checkin')) {
        $errorHandler->log('Unauthorized manual check-in attempt', null, 'WARNING', [
            'user_id' => $user['user_id'],
            'event_id' => $eventId,
            'ip' => $deviceInfo['ip_address']
        ]);
        
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Insufficient permissions for manual check-in'
        ]);
        exit;
    }
    
    $performanceManager->startTimer('checkin_processing');
    
    // Process manual check-in using enterprise service layer
    $result = $dataService->processManualCheckin($user['user_id'], $eventId, $deviceInfo);
    
    $performanceManager->endTimer('checkin_processing');
    
    // Record success metrics
    $performanceManager->recordMetric('manual_checkin_success', 1);
    $performanceManager->recordMetric('manual_checkin_processing_time', $performanceManager->getTimer('checkin_processing'));
    
    // Return enterprise response
    echo json_encode(array_merge($result, [
        'meta' => [
            'api_version' => '4.0.0',
            'processing_time_ms' => round($performanceManager->getTimer('manual_checkin') * 1000, 2),
            'timestamp' => date('c')
        ]
    ]));

} catch (Exception $e) {
    // Enterprise error handling with comprehensive logging
    $errorHandler->log('Manual check-in error', $e, 'ERROR', [
        'user_id' => $user['user_id'] ?? null,
        'event_id' => $eventId ?? null,
        'device_info' => $deviceInfo ?? [],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Record error metrics
    $performanceManager->recordMetric('manual_checkin_error', 1);
    
    // Determine appropriate HTTP status code
    $statusCode = 400;
    if (strpos($e->getMessage(), 'permission') !== false) {
        $statusCode = 403;
    } elseif (strpos($e->getMessage(), 'session') !== false) {
        $statusCode = 401;
    } elseif (strpos($e->getMessage(), 'not found') !== false) {
        $statusCode = 404;
    } elseif (strpos($e->getMessage(), 'capacity') !== false || strpos($e->getMessage(), 'already checked') !== false) {
        $statusCode = 409; // Conflict
    } elseif (strpos($e->getMessage(), 'system') !== false) {
        $statusCode = 500;
    }
    
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'meta' => [
            'api_version' => '4.0.0',
            'error_id' => uniqid('manual_checkin_err_'),
            'timestamp' => date('c')
        ]
    ]);
} finally {
    // Complete performance monitoring
    $performanceManager->endTimer('manual_checkin');
}
?>
        $end_datetime = new DateTime($event['end_date'] . ' ' . $event['end_time']);
    } elseif ($event['end_date']) {
        $end_datetime = new DateTime($event['end_date'] . ' 23:59:59');
    }
    
    // Check if user is already checked in
    $stmt = $db->prepare("
        SELECT checkin_id, status
        FROM checkin 
        WHERE user_id = ? AND event_id = ? AND DATE(checkin_time) = CURDATE()
        ORDER BY checkin_time DESC 
        LIMIT 1
    ");
    $stmt->execute([$user['user_id'], $event_id]);
    $existing_checkin = $stmt->fetch();
    
    if ($existing_checkin && $existing_checkin['status'] === 'checked_in') {
        throw new Exception('You are already checked in to this event');
    }
    
    // Check capacity if specified (calculate current participants dynamically)
    if ($event['capacity'] > 0) {
        $stmt = $db->prepare("SELECT COUNT(*) as current_count FROM checkin WHERE event_id = ? AND status = 'checked_in'");
        $stmt->execute([$event_id]);
        $current_participants = $stmt->fetch()['current_count'];
        
        if ($current_participants >= $event['capacity']) {
            throw new Exception('Event is at full capacity');
        }
    }
    
    // Create manual check-in
    $stmt = $db->prepare("
        INSERT INTO checkin (user_id, event_id, checkin_time, checkin_method, ip_address, status) 
        VALUES (?, ?, NOW(), 'manual', ?, 'checked_in')
    ");
    $stmt->execute([$user['user_id'], $event_id, $_SERVER['REMOTE_ADDR']]);

    $checkin_id = $db->lastInsertId();

    // Log the activity
    $stmt = $db->prepare("
        INSERT INTO activitylog (user_id, action, details, timestamp) 
        VALUES (?, 'manual_checkin', ?, NOW())
    ");
    $stmt->execute([
        $user['user_id'], 
        "Manual check-in to event: {$event['event_name']}"
    ]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Successfully checked in to event',
        'checkin' => [
            'checkin_id' => $checkin_id,
            'event_name' => $event['event_name'],
            'location' => $event['location'],
            'checkin_time' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollback();
    }
    
    error_log('Manual Check-in Error: ' . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
