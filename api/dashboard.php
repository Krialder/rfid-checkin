<?php
/**
 * API endpoint for dashboard data
 */

require_once '../core/auth.php';
header('Content-Type: application/json');

Auth::requireLogin();
$user = Auth::getCurrentUser();
$db = getDB();

try {
    $response = [];
    
    // Get quick stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_checkins,
            COUNT(CASE WHEN MONTH(checkin_time) = MONTH(CURRENT_DATE()) 
                  AND YEAR(checkin_time) = YEAR(CURRENT_DATE()) THEN 1 END) as month_checkins,
            COUNT(DISTINCT event_id) as unique_events,
            AVG(TIMESTAMPDIFF(MINUTE, 
                (SELECT start_time FROM Events WHERE event_id = CheckIn.event_id), 
                checkin_time)) as avg_checkin_delay
        FROM CheckIn 
        WHERE user_id = ?
    ");
    $stmt->execute([$user['user_id']]);
    $stats = $stmt->fetch();
    
    $response['stats'] = [
        'total_checkins' => (int)$stats['total_checkins'],
        'month_checkins' => (int)$stats['month_checkins'],
        'unique_events' => (int)$stats['unique_events'],
        'avg_checkin_time' => $stats['avg_checkin_delay'] ? round($stats['avg_checkin_delay'], 1) . ' min' : 'N/A'
    ];
    
    // Get recent check-ins
    $stmt = $db->prepare("
        SELECT 
            e.name as event_name,
            e.location,
            c.checkin_time,
            c.status
        FROM CheckIn c
        JOIN Events e ON c.event_id = e.event_id
        WHERE c.user_id = ?
        ORDER BY c.checkin_time DESC
        LIMIT 10
    ");
    $stmt->execute([$user['user_id']]);
    $response['recent_checkins'] = $stmt->fetchAll();
    
    // Get upcoming events
    $stmt = $db->prepare("
        SELECT 
            event_id,
            name as event_name,
            location,
            start_time,
            end_time,
            description
        FROM Events
        WHERE start_time > NOW()
        AND active = 1
        ORDER BY start_time ASC
        LIMIT 5
    ");
    $stmt->execute();
    $response['upcoming_events'] = $stmt->fetchAll();
    
    // Get available events for manual check-in
    $stmt = $db->prepare("
        SELECT 
            event_id,
            name as event_name,
            location,
            start_time,
            end_time
        FROM Events
        WHERE DATE(start_time) = CURDATE()
        OR (start_time <= NOW() AND end_time >= NOW())
        AND active = 1
        ORDER BY start_time ASC
    ");
    $stmt->execute();
    $response['available_events'] = $stmt->fetchAll();
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log('Dashboard API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
}
