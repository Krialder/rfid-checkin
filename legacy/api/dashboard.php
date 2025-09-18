<?php
/**
 * Enterprise Dashboard Data API Endpoint
 * 
 * Modern RESTful API endpoint providing comprehensive dashboard data with
 * enterprise architecture including repository pattern, service layer,
 * comprehensive security, and performance monitoring.
 * 
 * Features:
 * - Repository pattern for all data access
 * - Service layer for business logic
 * - Enterprise security validation
 * - Performance monitoring and metrics
 * - Comprehensive error handling
 * - API rate limiting and throttling
 * 
 * Response Data:
 * - User-specific statistics with trend analysis
 * - Recent check-in history with event details
 * - Upcoming events with capacity and availability
 * - Available events for immediate check-in
 * - Performance metrics and insights
 * 
 * @package    RFID Check-in System
 * @subpackage Enterprise REST API
 * @version    4.0.0 - Enterprise Architecture
 * @author     Senior Developer Team
 * @since      1.0.0
 * @security   AUTHENTICATED_USERS_ONLY
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

// Set enterprise API headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Start performance monitoring
$performanceManager->startTimer('dashboard_api');

try {
    // Enterprise security validation
    $securityManager->validateRequest($_SERVER);
    
    // Check API rate limiting
    if (!$securityManager->checkRateLimit('api_dashboard', $_SERVER['REMOTE_ADDR'] ?? 'unknown')) {
        $errorHandler->log('API rate limit exceeded for dashboard', null, 'WARNING', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'endpoint' => 'dashboard'
        ]);
        
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded. Please try again later.',
            'timestamp' => date('c')
        ]);
        exit;
    }
    
    // Enforce user authentication for data access
    Auth::requireLogin();
    $user = Auth::getCurrentUser();
    
    if (!$user) {
        throw new Exception('Invalid user session');
    }
    
    // Get comprehensive dashboard data using enterprise service layer
    $performanceManager->startTimer('data_aggregation');
    
    // User statistics using repository pattern
    $userStats = $userRepository->getUserStats($user['user_id']);
    
    // Recent check-ins with event details
    $recentCheckins = $userRepository->getRecentCheckins($user['user_id'], 10);
    
    // Upcoming events from event repository
    $upcomingEvents = $eventRepository->getUpcomingEvents(5);
    
    // Available events for manual check-in
    $availableEvents = $eventRepository->getAvailableEvents();
    
    // User groups using service layer
    $userGroups = $dataService->getUserGroups($user['user_id']);
    
    $performanceManager->endTimer('data_aggregation');
    
    // Build enterprise response structure
    $response = [
        'success' => true,
        'data' => [
            'user' => [
                'id' => $user['user_id'],
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'role' => $user['role']
            ],
            'stats' => $userStats,
            'recent_checkins' => $recentCheckins,
            'upcoming_events' => $upcomingEvents,
            'available_events' => $availableEvents,
            'user_groups' => $userGroups
        ],
        'meta' => [
            'timestamp' => date('c'),
            'api_version' => '4.0.0',
            'response_time_ms' => round($performanceManager->getTimer('dashboard_api') * 1000, 2)
        ]
    ];
    
    // Record performance metrics
    $performanceManager->recordMetric('dashboard_api_success', 1);
    $performanceManager->recordMetric('dashboard_data_points', count($recentCheckins) + count($upcomingEvents));
    
    // Return enterprise JSON response
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

} catch (Exception $e) {
    // Enterprise error handling
    $errorHandler->log('Dashboard API error', $e, 'ERROR', [
        'user_id' => $user['user_id'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Record error metrics
    $performanceManager->recordMetric('dashboard_api_error', 1);
    
    // Return structured error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load dashboard data',
        'meta' => [
            'timestamp' => date('c'),
            'api_version' => '4.0.0',
            'error_id' => uniqid('dash_err_')
        ]
    ]);
} finally {
    // Complete performance monitoring
    $performanceManager->endTimer('dashboard_api');
}
?>
