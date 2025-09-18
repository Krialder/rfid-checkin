<?php
/**
 * RFID Test Endpoint
 * Allows manual testing of RFID scanning functionality
 * REMOVE THIS FILE IN PRODUCTION
 */

require_once '../core/config.php';
require_once '../core/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add a test RFID to the queue
    try {
        $db = getDB();
        
        // Create test RFID
        $testRFID = 'TEST' . strtoupper(substr(md5(uniqid()), 0, 8));
        
        // Ensure table exists
        $createTableSQL = "
            CREATE TABLE IF NOT EXISTS `rfid_scan_queue` (
              `queue_id` INT AUTO_INCREMENT PRIMARY KEY,
              `tag_value` VARCHAR(50) NOT NULL,
              `device_id` INT DEFAULT 1,
              `source_ip` VARCHAR(45),
              `source` VARCHAR(50) DEFAULT 'test',
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              KEY `idx_created_at` (`created_at`),
              KEY `idx_tag_value` (`tag_value`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $db->exec($createTableSQL);
        
        // Add test scan to queue
        $stmt = $db->prepare("
            INSERT INTO rfid_scan_queue (tag_value, device_id, source_ip, source, created_at) 
            VALUES (?, 999, ?, 'test', NOW())
        ");
        $stmt->execute([$testRFID, $_SERVER['REMOTE_ADDR'] ?? 'test']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Test RFID added to queue',
            'rfid' => $testRFID,
            'queue_id' => $db->lastInsertId()
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'error' => $e->getMessage()
        ]);
    }
} else {
    // GET request - show current queue status
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            SELECT * FROM rfid_scan_queue 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute();
        $queue = $stmt->fetchAll();
        
        echo json_encode([
            'queue_items' => count($queue),
            'items' => $queue
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'error' => $e->getMessage()
        ]);
    }
}
?>
