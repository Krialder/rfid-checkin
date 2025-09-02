<?php
/**
 * RFID Polling API Endpoint
 * Provides real-time RFID scanning support for web interface
 * Used as fallback when Web Serial API is not available
 */

require_once '../core/config.php';
require_once '../core/database.php';
require_once '../core/auth.php';

// Set appropriate headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Only allow authenticated admin users (use same auth as register-user.php)
try {
    Auth::requireLogin();
    $current_user = Auth::getCurrentUser();
    
    if ($current_user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $db = getDB();
    
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    $lastTag = $input['last_tag'] ?? '';
    $timeout = min(max(intval($input['timeout'] ?? 1000), 100), 5000); // 0.1-5 seconds
    
    $startTime = microtime(true);
    $maxWaitTime = $timeout / 1000; // Convert to seconds
    
    // Poll for new RFID scans from the hardware
    while ((microtime(true) - $startTime) < $maxWaitTime) {
        // Check if there's a manual scan request in the queue (primary method)
        // This is populated by the ESP32 when it detects a scan
        $stmt = $db->prepare("
            SELECT tag_value, created_at 
            FROM rfid_scan_queue 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
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
        
        // Short sleep to prevent excessive CPU usage
        usleep(200000); // 0.2 seconds
    }
    
    // No new scans found within timeout
    echo json_encode([
        'success' => false,
        'message' => 'No new scans detected'
    ]);
    
} catch (Exception $e) {
    error_log('RFID Polling Error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => DEBUG_MODE ? $e->getMessage() : 'Internal server error'
    ]);
}
?>
