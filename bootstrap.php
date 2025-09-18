<?php

declare(strict_types=1);

/**
 * RFID Check-in System - Main Entry Point
 * 
 * Application entry point for the RFID check-in system.
 * This file bootstraps the application and handles all incoming requests.
 * 
 * @package RfidCheckin
 * @version 2.0.0
 */

// Define application constants
define('APP_START_TIME', microtime(true));
define('APP_ROOT', __DIR__);
define('APP_VERSION', '2.0.0');

// Composer autoloader (if using Composer)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Manual autoloader for our classes
spl_autoload_register(function ($className) {
    // Convert namespace to file path
    $className = str_replace('RfidCheckin\\', '', $className);
    $className = str_replace('\\', DIRECTORY_SEPARATOR, $className);
    
    $file = __DIR__ . '/src/' . $className . '.php';
    
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    
    return false;
});

// Import the main application class
use RfidCheckin\Application;

try {
    // Create and run the application
    $app = new Application();
    
    // Register shutdown handler
    register_shutdown_function(function() use ($app) {
        $app->shutdown();
    });
    
    
// Debug mode for testing
if (isset($_GET['debug']) || isset($_SERVER['DEBUG_BOOTSTRAP'])) {
    echo '<h2>Bootstrap Debug Mode</h2>';
    echo '<p>Bootstrap started at: ' . date('Y-m-d H:i:s') . '</p>';
}

// Run the application
    $app->run();
    
} catch (Exception $e) {
    // Emergency error handling if application fails to start
    error_log("Critical application error: " . $e->getMessage());
    
    http_response_code(500);
    
    // Check if it's an AJAX request
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'System error occurred',
            'code' => 'CRITICAL_ERROR'
        ]);
    } else {
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Error - RFID Check-in System</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            color: #333; line-height: 1.6;
        }
        .error-container { 
            background: white; padding: 40px; border-radius: 10px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.1); max-width: 500px; width: 90%;
            text-align: center; animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .error-icon { font-size: 64px; margin-bottom: 20px; color: #e74c3c; }
        h1 { color: #2c3e50; margin-bottom: 15px; font-size: 28px; }
        .error-message { color: #7f8c8d; margin-bottom: 30px; font-size: 16px; }
        .error-details { 
            background: #f8f9fa; padding: 20px; border-radius: 5px; 
            border-left: 4px solid #e74c3c; margin: 20px 0; text-align: left;
        }
        .btn { 
            background: #3498db; color: white; padding: 12px 30px; 
            border: none; border-radius: 5px; text-decoration: none;
            display: inline-block; font-size: 16px; cursor: pointer;
            transition: background 0.3s ease;
        }
        .btn:hover { background: #2980b9; }
        .support-info { margin-top: 30px; font-size: 14px; color: #95a5a6; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">🚨</div>
        <h1>System Error</h1>
        <p class="error-message">
            The RFID Check-in System encountered a critical error and cannot continue.
        </p>
        
        <div class="error-details">
            <strong>What happened?</strong><br>
            A system-level error occurred during application startup. This could be due to:
            <ul style="margin-top: 10px; padding-left: 20px;">
                <li>Database connectivity issues</li>
                <li>Configuration problems</li>
                <li>Server resource limitations</li>
                <li>Missing dependencies</li>
            </ul>
        </div>
        
        <a href="javascript:window.location.reload()" class="btn">Try Again</a>
        
        <div class="support-info">
            <p>If this problem persists, please contact your system administrator.</p>
            <p><strong>Error ID:</strong> ' . uniqid('ERR_') . '</p>
            <p><strong>Timestamp:</strong> ' . date('Y-m-d H:i:s T') . '</p>
        </div>
    </div>
</body>
</html>';
    }
    
    exit;
}
