<?php
// Simple test API to debug the issue
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

try {
    require_once '../core/config.php';
    require_once '../core/database.php';
    require_once '../core/auth.php';
    
    $response = [
        'success' => true,
        'message' => 'API working',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Test auth
    if (Auth::isLoggedIn()) {
        $response['logged_in'] = true;
        $user = Auth::getCurrentUser();
        $response['user_role'] = $user['role'] ?? 'unknown';
        $response['is_admin'] = Auth::hasRole(['admin']);
    } else {
        $response['logged_in'] = false;
    }
    
    // Test database
    $db = getDB();
    if ($db) {
        $response['database'] = 'connected';
        
        // Test if system_settings table exists
        $stmt = $db->query("SHOW TABLES LIKE 'system_settings'");
        $response['system_settings_exists'] = $stmt->rowCount() > 0;
    } else {
        $response['database'] = 'failed';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
