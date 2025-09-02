<?php
/**
 * Enhanced RFID Queue API with Registration Mode Support
 * Shows recent RFID scans from hardware devices for assignment to users
 */

// Turn off all error reporting to prevent HTM} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    error_log('RFID Queue Manager Error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Queue operation failed',
        'message' => (defined('DEBUG_MODE') && DEBUG_MODE) ? $e->getMessage() : 'Internal server error'
    ]);
}or_reporting(0);
ini_set('display_errors', 0);

require_once '../core/config.php';
require_once '../core/database.php';
require_once '../core/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Only admin users can access the queue
    if (!Auth::isLoggedIn() || !Auth::hasRole(['admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }

    $db = getDB();
    
    // Test database connection
    if (!$db) {
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get recent RFID scans from queue
        $limit = intval($_GET['limit'] ?? 20);
        $limit = min($limit, 100); // Max 100 items
        
        $stmt = $db->prepare("
            SELECT 
                rq.queue_id,
                rq.tag_value,
                rq.device_id,
                rq.source_ip,
                rq.source,
                rq.created_at,
                u.user_id,
                CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as assigned_user,
                u.is_active as user_active
            FROM rfid_scan_queue rq
            LEFT JOIN users u ON rq.tag_value = u.rfid_tag
            ORDER BY rq.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $queue_items = $stmt->fetchAll();
        
        // Check registration mode status
        $stmt = $db->prepare("
            SELECT setting_value 
            FROM system_settings 
            WHERE setting_key = 'rfid_registration_mode'
        ");
        $stmt->execute();
        $registration_mode = $stmt->fetch();
        $is_registration_mode = $registration_mode && (bool)$registration_mode['setting_value'];
        
        // Get queue statistics
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_scans,
                COUNT(DISTINCT tag_value) as unique_tags,
                SUM(CASE WHEN u.user_id IS NULL THEN 1 ELSE 0 END) as unregistered_tags
            FROM rfid_scan_queue rq
            LEFT JOIN Users u ON rq.tag_value = u.rfid_tag
            WHERE rq.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute();
        $stats = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'registration_mode_enabled' => $is_registration_mode,
            'queue_items' => $queue_items,
            'stats' => $stats,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'assign_to_user') {
            // Assign RFID tag to a user
            $tag_value = trim($_POST['tag_value'] ?? '');
            $user_id = intval($_POST['user_id'] ?? 0);
            
            if (empty($tag_value) || $user_id <= 0) {
                throw new Exception('Tag value and user ID are required');
            }
            
            $db->beginTransaction();
            
            // Check if user exists
            $stmt = $db->prepare("SELECT user_id, first_name, last_name FROM Users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('User not found');
            }
            
            // Check if tag is already assigned
            $stmt = $db->prepare("SELECT user_id FROM Users WHERE rfid_tag = ? AND user_id != ?");
            $stmt->execute([$tag_value, $user_id]);
            $existing_user = $stmt->fetch();
            
            if ($existing_user) {
                throw new Exception('RFID tag is already assigned to another user');
            }
            
            // Update user's RFID tag
            $stmt = $db->prepare("UPDATE Users SET rfid_tag = ? WHERE user_id = ?");
            $stmt->execute([$tag_value, $user_id]);
            
            // Remove from queue
            $stmt = $db->prepare("DELETE FROM rfid_scan_queue WHERE tag_value = ?");
            $stmt->execute([$tag_value]);
            
            // Log the assignment
            $current_user = Auth::getCurrentUser();
            $stmt = $db->prepare("
                INSERT INTO ActivityLog (user_id, action, details, ip_address, timestamp) 
                VALUES (?, 'rfid_tag_assigned', ?, ?, NOW())
            ");
            $stmt->execute([
                $current_user['user_id'],
                json_encode([
                    'tag_value' => $tag_value,
                    'assigned_to' => $user['first_name'] . ' ' . $user['last_name'],
                    'assigned_to_user_id' => $user_id
                ]),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'RFID tag assigned successfully',
                'tag_value' => $tag_value,
                'user_name' => $user['first_name'] . ' ' . $user['last_name']
            ]);
            
        } elseif ($action === 'remove_from_queue') {
            // Remove item from queue
            $queue_id = intval($_POST['queue_id'] ?? 0);
            
            if ($queue_id <= 0) {
                throw new Exception('Queue ID is required');
            }
            
            $stmt = $db->prepare("DELETE FROM rfid_scan_queue WHERE queue_id = ?");
            $stmt->execute([$queue_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Item removed from queue'
            ]);
            
        } elseif ($action === 'clear_queue') {
            // Clear all queue items older than 5 minutes
            $stmt = $db->prepare("DELETE FROM rfid_scan_queue WHERE created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
            $stmt->execute();
            $cleared_count = $stmt->rowCount();
            
            echo json_encode([
                'success' => true,
                'message' => "Cleared {$cleared_count} old items from queue"
            ]);
            
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    if (isset($db) && $db->getConnection()->inTransaction()) {
        $db->rollback();
    }
    
    error_log('RFID Queue API Error: ' . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'error' => 'Queue operation failed',
        'message' => 'Internal server error'
    ]);
}
?>
