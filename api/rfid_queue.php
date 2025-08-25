<?php
/**
 * RFID Queue API Endpoint
 * Allows hardware devices to push RFID scans to queue for web interface
 * This creates a bridge between hardware scanning and web form scanning
 */

require_once '../core/config.php';
require_once '../core/database.php';

// Set appropriate headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
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
    
    // Get RFID data from request
    $rfid = trim($_POST['rfid'] ?? '');
    $device_id = intval($_POST['device_id'] ?? 1);
    $source = $_POST['source'] ?? 'hardware';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    if (empty($rfid)) {
        throw new Exception('RFID tag is required');
    }
    
    // Validate RFID format
    if (!preg_match('/^[A-Fa-f0-9]{6,20}$/', $rfid)) {
        throw new Exception('Invalid RFID format');
    }
    
    // Convert to uppercase
    $rfid = strtoupper($rfid);
    
    // Ensure rfid_scan_queue table exists (create if not)
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS `rfid_scan_queue` (
          `queue_id` INT AUTO_INCREMENT PRIMARY KEY,
          `tag_value` VARCHAR(50) NOT NULL,
          `device_id` INT DEFAULT 1,
          `source_ip` VARCHAR(45),
          `source` VARCHAR(50) DEFAULT 'hardware',
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          KEY `idx_created_at` (`created_at`),
          KEY `idx_tag_value` (`tag_value`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $db->exec($createTableSQL);
    
    // Clean up old entries first
    $stmt = $db->prepare("DELETE FROM rfid_scan_queue WHERE created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $stmt->execute();
    
    // Check if this RFID was already queued recently (prevent duplicates)
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM rfid_scan_queue 
        WHERE tag_value = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
    ");
    $stmt->execute([$rfid]);
    
    if ($stmt->fetchColumn() > 0) {
        // Duplicate scan within 10 seconds, acknowledge but don't queue again
        echo json_encode([
            'success' => true,
            'message' => 'RFID already queued recently',
            'rfid' => $rfid,
            'action' => 'duplicate'
        ]);
        exit;
    }
    
    // Add to queue
    $stmt = $db->prepare("
        INSERT INTO rfid_scan_queue (tag_value, device_id, source_ip, source, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$rfid, $device_id, $ip_address, $source]);
    
    // Also try the regular check-in process if this is meant for immediate check-in
    $checkinResult = null;
    if ($source === 'hardware') {
        try {
            // Call the regular RFID check-in process
            $formData = [
                'rfid' => $rfid,
                'device_id' => $device_id
            ];
            
            // Include the regular check-in logic but don't fail if it doesn't work
            require_once '../api/rfid_checkin.php';
            
        } catch (Exception $e) {
            // Log but don't fail - the queue entry is still valid
            error_log('Regular check-in failed for queued RFID: ' . $e->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'RFID scan queued successfully',
        'rfid' => $rfid,
        'device_id' => $device_id,
        'queue_id' => $db->lastInsertId(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log('RFID Queue Error: ' . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage(),
        'rfid' => $rfid ?? null
    ]);
}
?>
