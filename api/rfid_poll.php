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

// Only allow authenticated admin users
if (!Auth::isLoggedIn() || !Auth::hasRole(['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
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
        // Check for recent RFID scans in AccessLogs
        $stmt = $db->prepare("
            SELECT rfid_tag, timestamp, details 
            FROM AccessLogs 
            WHERE rfid_tag IS NOT NULL 
            AND rfid_tag != '' 
            AND timestamp >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
            ORDER BY timestamp DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $scan = $stmt->fetch();
        
        if ($scan && $scan['rfid_tag'] !== $lastTag) {
            // New RFID tag detected
            echo json_encode([
                'success' => true,
                'rfid_tag' => $scan['rfid_tag'],
                'timestamp' => $scan['timestamp'],
                'details' => $scan['details'] ? json_decode($scan['details'], true) : null
            ]);
            exit;
        }
        
        // Also check if there's a manual scan request in a temporary table/cache
        // This would be populated by the hardware when it detects a scan
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
