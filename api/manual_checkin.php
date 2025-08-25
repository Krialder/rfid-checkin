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
        SELECT event_id, name as event_name, location, start_time, end_time, capacity, current_participants
        FROM Events 
        WHERE event_id = ? AND active = 1
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        throw new Exception('Event not found or not available');
    }
    
    // Check if event is currently active (optional - allow future/past manual check-ins)
    $now = new DateTime();
    $start_time = new DateTime($event['start_time']);
    $end_time = new DateTime($event['end_time']);
    
    // Check if user is already checked in
    $stmt = $db->prepare("
        SELECT checkin_id, status
        FROM CheckIn 
        WHERE user_id = ? AND event_id = ? AND DATE(checkin_time) = CURDATE()
        ORDER BY checkin_time DESC 
        LIMIT 1
    ");
    $stmt->execute([$user['user_id'], $event_id]);
    $existing_checkin = $stmt->fetch();
    
    if ($existing_checkin && $existing_checkin['status'] === 'checked-in') {
        throw new Exception('You are already checked in to this event');
    }
    
    // Check capacity if specified
    if ($event['capacity'] > 0 && $event['current_participants'] >= $event['capacity']) {
        throw new Exception('Event is at full capacity');
    }
    
    // Create manual check-in
    $stmt = $db->prepare("
        INSERT INTO CheckIn (user_id, event_id, checkin_time, method, ip_address, status) 
        VALUES (?, ?, NOW(), 'manual', ?, 'checked-in')
    ");
    $stmt->execute([$user['user_id'], $event_id, $_SERVER['REMOTE_ADDR']]);
    
    $checkin_id = $db->lastInsertId();
    
    // Update event participant count
    $stmt = $db->prepare("
        UPDATE Events 
        SET current_participants = current_participants + 1
        WHERE event_id = ?
    ");
    $stmt->execute([$event_id]);
    
    // Log the activity
    $stmt = $db->prepare("
        INSERT INTO ActivityLog (user_id, action, details, timestamp) 
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
