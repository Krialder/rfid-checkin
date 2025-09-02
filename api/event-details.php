<?php
/**
 * API endpoint for event details
 * Returns detailed information about a specific event
 */

require_once '../core/auth.php';
require_once '../core/database.php';

header('Content-Type: application/json');
Auth::requireLogin();

$user = Auth::getCurrentUser();
$db = getDB();

try {
    $event_id = intval($_GET['event_id'] ?? 0);
    
    if (!$event_id) {
        throw new Exception('Event ID is required');
    }
    
    // Get event details with user check-in status
    $stmt = $db->prepare("
        SELECT 
            e.*,
            CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as created_by_name,
            CASE 
                WHEN c.checkin_id IS NOT NULL THEN c.status
                ELSE NULL
            END as user_checkin_status,
            c.checkin_time as user_checkin_time,
            c.checkout_time as user_checkout_time,
            (TIMESTAMP(COALESCE(e.start_date, CURDATE()), COALESCE(e.start_time, '00:00:00')) <= NOW() 
             AND TIMESTAMP(COALESCE(e.end_date, CURDATE()), COALESCE(e.end_time, '23:59:59')) >= NOW()) as is_current,
            (TIMESTAMP(COALESCE(e.start_date, CURDATE()), COALESCE(e.start_time, '00:00:00')) > NOW()) as is_upcoming,
            (TIMESTAMP(COALESCE(e.end_date, CURDATE()), COALESCE(e.end_time, '23:59:59')) < NOW()) as is_past,
            (SELECT COUNT(*) FROM checkin ci WHERE ci.event_id = e.event_id AND ci.status = 'checked_in') as current_participants
        FROM events e
        LEFT JOIN users u ON e.created_by = u.user_id
        LEFT JOIN checkin c ON e.event_id = c.event_id AND c.user_id = ? 
            AND DATE(c.checkin_time) = e.start_date
        WHERE e.event_id = ? AND e.active = 1
    ");
    
    $stmt->execute([$user['user_id'], $event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        throw new Exception('Event not found');
    }
    
    // Convert boolean fields
    $event['is_current'] = (bool)$event['is_current'];
    $event['is_upcoming'] = (bool)$event['is_upcoming'];
    $event['is_past'] = (bool)$event['is_past'];
    $event['active'] = (bool)$event['active'];
    
    // Get recent check-ins for this event (for admin/organizer view)
    if ($user['role'] === 'admin' || $user['user_id'] == $event['created_by']) {
        $stmt = $db->prepare("
            SELECT 
                CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as user_name,
                c.checkin_time,
                c.checkout_time,
                c.status,
                c.checkin_method as method
            FROM checkin c
            JOIN users u ON c.user_id = u.user_id
            WHERE c.event_id = ?
            ORDER BY c.checkin_time DESC
            LIMIT 10
        ");
        $stmt->execute([$event_id]);
        $event['recent_checkins'] = $stmt->fetchAll();
    }
    
    echo json_encode($event);
    
} catch (Exception $e) {
    error_log('Event Details API error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
