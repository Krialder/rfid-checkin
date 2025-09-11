<?php
/**
 * Dashboard Data API Endpoint
 * 
 * RESTful API endpoint providing comprehensive dashboard data including
 * user statistics, recent activity, upcoming events, and available
 * check-in opportunities. Optimized for real-time updates and mobile apps.
 * 
 * Response Data:
 * - User-specific statistics (total/monthly check-ins, unique events)
 * - Recent check-in history with event details and timestamps
 * - Upcoming events with capacity and availability information
 * - Available events for immediate manual check-in
 * - Performance metrics and user activity insights
 * 
 * @package    RFID Check-in System
 * @subpackage REST API
 * @version    2.0.0
 * @author     Senior Developer Team
 * @since      1.0.0
 * @security   AUTHENTICATED_USERS_ONLY
 */

// Load authentication and database systems
require_once '../core/auth.php';
require_once '../core/database.php';
require_once '../core/user-group-manager.php';

// Set JSON response headers for API compliance
header('Content-Type: application/json');

// Enforce user authentication for data access
Auth::requireLogin();
$user = Auth::getCurrentUser();
$db = getDB();
$groupManager = new UserGroupManager();

try {
    // Initialize response container
    $response = [];
    
    // Calculate comprehensive user statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_checkins,
            COUNT(CASE WHEN MONTH(checkin_time) = MONTH(CURRENT_DATE()) 
                  AND YEAR(checkin_time) = YEAR(CURRENT_DATE()) THEN 1 END) as month_checkins,
            COUNT(DISTINCT event_id) as unique_events,
            AVG(TIMESTAMPDIFF(MINUTE, 
                (SELECT start_time FROM events WHERE event_id = checkin.event_id), 
                checkin_time)) as avg_checkin_delay
        FROM checkin 
        WHERE user_id = ?
    ");
    $stmt->execute([$user['user_id']]);
    $stats = $stmt->fetch();
    
    // Build statistics response with formatted data
    $response['stats'] = [
        'total_checkins' => (int)$stats['total_checkins'],
        'month_checkins' => (int)$stats['month_checkins'],
        'unique_events' => (int)$stats['unique_events'],
        'avg_checkin_time' => $stats['avg_checkin_delay'] ? round($stats['avg_checkin_delay'], 1) . ' min' : 'N/A'
    ];
    
    // Retrieve recent check-in activity with event details
    $stmt = $db->prepare("
        SELECT 
            e.name as event_name,
            e.location,
            c.checkin_time,
            c.status
        FROM checkin c
        JOIN events e ON c.event_id = e.event_id
        WHERE c.user_id = ?
        ORDER BY c.checkin_time DESC
        LIMIT 10
    ");
    $stmt->execute([$user['user_id']]);
    $response['recent_checkins'] = $stmt->fetchAll();
    
    // Fetch upcoming events for planning purposes
    $stmt = $db->prepare("
        SELECT 
            event_id,
            name as event_name,
            location,
            start_time,
            end_time,
            description
        FROM events
        WHERE start_time > NOW()
        AND active = 1
        ORDER BY start_time ASC
        LIMIT 5
    ");
    $stmt->execute();
    $response['upcoming_events'] = $stmt->fetchAll();
    
    // Identify events available for immediate manual check-in
    $stmt = $db->prepare("
        SELECT 
            event_id,
            name as event_name,
            location,
            start_time,
            end_time
        FROM events
        WHERE DATE(start_time) = CURDATE()
        OR (start_time <= NOW() AND end_time >= NOW())
        AND active = 1
        ORDER BY start_time ASC
    ");
    $stmt->execute();
    $response['available_events'] = $stmt->fetchAll();
    
    // Get user's group memberships
    $userGroups = $groupManager->getUserGroups($user['user_id']);
    $response['user_groups'] = [
        'count' => count($userGroups),
        'groups' => array_slice($userGroups, 0, 3), // Show first 3 groups
        'has_more' => count($userGroups) > 3
    ];
    
    // Return structured JSON response
    echo json_encode($response);
    
} catch (PDOException $e) {
    // Log database errors for system monitoring
    error_log('Dashboard API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
}
