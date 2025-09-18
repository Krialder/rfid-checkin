<?php
/**
 * Enterprise Event Details API Endpoint
 * 
 * Comprehensive API for retrieving detailed event information with
 * user-specific check-in status, analytics, and real-time data.
 * 
 * Features:
 * - Repository pattern for all data operations
 * - Role-based access control and permission validation
 * - Real-time event status and participant tracking
 * - User-specific check-in history and status
 * - Event analytics and capacity management
 * - Performance monitoring and caching
 * - Comprehensive error handling and validation
 * 
 * Data Response:
 * - Complete event details with metadata
 * - User check-in status and history
 * - Real-time participant count and capacity
 * - Event timing and availability status
 * - Recent activity for authorized users
 * - Event organizer and creation details
 * 
 * @package    RFID Check-in System
 * @subpackage Enterprise Event Management
 * @version    4.0.0 - Enterprise Architecture
 * @author     Senior Developer Team
 * @since      1.0.0
 * @security   AUTHENTICATED_USERS_ONLY + ROLE_BASED
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

// Set enterprise headers for event details API
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=300'); // 5-minute cache for event details
header('Vary: Authorization, User-Agent');

// Start performance monitoring
$performanceManager->startTimer('event_details');

try {
    // Enterprise authentication and session validation
    Auth::requireLogin();
    $user = Auth::getCurrentUser();
    
    if (!$user) {
        throw new Exception('Invalid user session');
    }
    
    // Enterprise security validation
    $securityManager->validateRequest($_SERVER);
    
    // Validate and sanitize event ID parameter
    $eventId = $securityManager->validateInput($_GET['event_id'] ?? 0, 'int');
    
    if (!$eventId) {
        throw new Exception('Valid event ID is required');
    }
    
    // Check user permissions for event details
    if (!$securityManager->hasPermission($user, 'view_events')) {
        $errorHandler->log('Unauthorized event details access attempt', null, 'WARNING', [
            'user_id' => $user['user_id'],
            'event_id' => $eventId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Insufficient permissions to view event details'
        ]);
        exit;
    }
    
    // Check event details rate limiting
    if (!$securityManager->checkRateLimit('event_details_' . $user['user_id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown', 30, 300)) {
        $errorHandler->log('Event details rate limit exceeded', null, 'WARNING', [
            'user_id' => $user['user_id'],
            'event_id' => $eventId
        ]);
        
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Too many requests. Please wait before requesting event details again.',
            'retry_after' => 300
        ]);
        exit;
    }
    
    $performanceManager->startTimer('event_data_retrieval');
    
    // Get comprehensive event details using enterprise service layer
    $eventDetails = $dataService->getEventDetails($eventId, $user['user_id']);
    
    if (!$eventDetails) {
        $errorHandler->log('Event not found', null, 'INFO', [
            'user_id' => $user['user_id'],
            'event_id' => $eventId
        ]);
        
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Event not found or access denied'
        ]);
        exit;
    }
    
    // Check if user can view additional details (admin/organizer permissions)
    $canViewDetails = $securityManager->hasRole($user, 'admin') || 
                     $eventDetails['created_by'] == $user['user_id'] ||
                     $securityManager->hasPermission($user, 'manage_events');
    
    if ($canViewDetails) {
        $performanceManager->startTimer('admin_details');
        
        // Get recent check-ins and analytics for authorized users
        $eventDetails['recent_checkins'] = $checkinRepository->getRecentCheckinsForEvent($eventId, 10);
        $eventDetails['analytics'] = $eventRepository->getEventAnalytics($eventId);
        $eventDetails['device_stats'] = $checkinRepository->getDeviceStatsForEvent($eventId);
        
        $performanceManager->endTimer('admin_details');
    }
    
    $performanceManager->endTimer('event_data_retrieval');
    
    // Record success metrics
    $performanceManager->recordMetric('event_details_success', 1);
    $performanceManager->recordMetric('event_details_processing_time', $performanceManager->getTimer('event_data_retrieval'));
    
    // Build enterprise response structure
    $response = [
        'success' => true,
        'data' => $eventDetails,
        'permissions' => [
            'can_checkin' => $securityManager->hasPermission($user, 'manual_checkin'),
            'can_view_analytics' => $canViewDetails,
            'can_manage_event' => $securityManager->hasPermission($user, 'manage_events') && 
                                 ($user['user_id'] == $eventDetails['created_by'] || $securityManager->hasRole($user, 'admin'))
        ],
        'meta' => [
            'api_version' => '4.0.0',
            'processing_time_ms' => round($performanceManager->getTimer('event_details') * 1000, 2),
            'timestamp' => date('c'),
            'cache_ttl' => 300
        ]
    ];
    
    // Return enterprise JSON response
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

} catch (Exception $e) {
    // Enterprise error handling with comprehensive logging
    $errorHandler->log('Event details API error', $e, 'ERROR', [
        'user_id' => $user['user_id'] ?? null,
        'event_id' => $eventId ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Record error metrics
    $performanceManager->recordMetric('event_details_error', 1);
    
    // Determine appropriate HTTP status code
    $statusCode = 500;
    if (strpos($e->getMessage(), 'not found') !== false) {
        $statusCode = 404;
    } elseif (strpos($e->getMessage(), 'permission') !== false) {
        $statusCode = 403;
    } elseif (strpos($e->getMessage(), 'session') !== false) {
        $statusCode = 401;
    } elseif (strpos($e->getMessage(), 'required') !== false || strpos($e->getMessage(), 'validation') !== false) {
        $statusCode = 400;
    }
    
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'meta' => [
            'api_version' => '4.0.0',
            'error_id' => uniqid('event_details_err_'),
            'timestamp' => date('c')
        ]
    ]);
} finally {
    // Complete performance monitoring
    $performanceManager->endTimer('event_details');
}
?>
