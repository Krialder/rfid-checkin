<?php
/**
 * Manual Check-in API Handler
 * Handles manual check-ins from the dashboard
 */

require_once '../core/auth.php';
require_once '../core/database.php';

header('Content-Type: application/json');
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$user = Auth::getCurrentUser();
$db = getDB();

try {
    $event_id = intval($_POST['event_id'] ?? 0);
    
    if (!$event_id) {
        throw new Exception('Event ID is required');
    }
    
    $db->beginTransaction();
    
    // Verify event exists and is active
    $stmt = $db->prepare("
        SELECT event_id, name as event_name, location, start_date, end_date, start_time, end_time, capacity
        FROM events 
        WHERE event_id = ? AND active = 1
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        throw new Exception('Event not found or not available');
    }
    
    // Check if event is currently active (optional - allow future/past manual check-ins)
    $now = new DateTime();
    if ($event['start_date'] && $event['start_time']) {
        $start_datetime = new DateTime($event['start_date'] . ' ' . $event['start_time']);
    } elseif ($event['start_date']) {
        $start_datetime = new DateTime($event['start_date'] . ' 00:00:00');
    }
    if ($event['end_date'] && $event['end_time']) {
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
