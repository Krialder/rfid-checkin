<?php
// Simple test endpoint that bypasses auth for debugging
require_once '../core/config.php';
require_once '../core/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $db = getDB();
    
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    $lastTag = $input['last_tag'] ?? '';
    
    echo json_encode(['debug' => 'Starting queue check', 'last_tag' => $lastTag]);
    
    // Check for RFID scans in the queue
    $stmt = $db->prepare("
        SELECT tag_value, created_at 
        FROM rfid_scan_queue 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
        AND tag_value != ?
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$lastTag]);
    $queuedScan = $stmt->fetch();
    
    if ($queuedScan) {
        // Remove the processed scan from queue
        $stmt = $db->prepare("DELETE FROM rfid_scan_queue WHERE tag_value = ? AND created_at = ?");
        $stmt->execute([$queuedScan['tag_value'], $queuedScan['created_at']]);
        
        echo json_encode([
            'success' => true,
            'rfid_tag' => $queuedScan['tag_value'],
            'timestamp' => $queuedScan['created_at'],
            'source' => 'queue'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'No new scans detected',
        'debug' => 'Queue checked, no results'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>
