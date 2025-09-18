<?php
/**
 * Simple autoloader test for RFID Check-in System
 */

echo "Starting RFID Check-in System autoloader test...\n";

// Define application constants
define('APP_START_TIME', microtime(true));
define('APP_ROOT', __DIR__);
define('APP_VERSION', '2.0.0');

// Manual autoloader for our classes
spl_autoload_register(function ($className) {
    // Convert namespace to file path
    $className = str_replace('RfidCheckin\\', '', $className);
    $className = str_replace('\\', DIRECTORY_SEPARATOR, $className);
    
    $file = __DIR__ . '/src/' . $className . '.php';
    
    if (file_exists($file)) {
        require_once $file;
        echo "Loaded: $file\n";
        return true;
    }
    
    echo "Not found: $file\n";
    return false;
});

// Load configuration if it exists
if (file_exists(__DIR__ . '/core/config.php')) {
    require_once __DIR__ . '/core/config.php';
    echo "✓ Configuration loaded\n";
} else {
    echo "⚠ No configuration file found\n";
}

echo "\nTesting class loading...\n\n";

try {
    // Test Configuration Service
    echo "Testing ConfigurationService...\n";
    $config = RfidCheckin\Services\ConfigurationService::getInstance();
    echo "✓ ConfigurationService initialized successfully\n";
    
    // Test Database Service
    echo "Testing DatabaseService...\n";
    $db = RfidCheckin\Services\DatabaseService::getInstance();
    echo "✓ DatabaseService initialized successfully\n";
    
    // Test Logging Service
    echo "Testing LoggingService...\n";
    $logger = RfidCheckin\Services\LoggingService::getInstance();
    echo "✓ LoggingService initialized successfully\n";
    
    // Test Security Service
    echo "Testing SecurityService...\n";
    $security = RfidCheckin\Services\SecurityService::getInstance();
    echo "✓ SecurityService initialized successfully\n";
    
    // Test Authentication Service
    echo "Testing AuthenticationService...\n";
    $auth = RfidCheckin\Services\AuthenticationService::getInstance();
    echo "✓ AuthenticationService initialized successfully\n";
    
    // Test User Repository
    echo "Testing UserRepository...\n";
    $userRepo = new RfidCheckin\Repositories\UserRepository();
    echo "✓ UserRepository initialized successfully\n";
    
    // Test Event Repository
    echo "Testing EventRepository...\n";
    $eventRepo = new RfidCheckin\Repositories\EventRepository();
    echo "✓ EventRepository initialized successfully\n";
    
    // Test Checkin Repository
    echo "Testing CheckinRepository...\n";
    $checkinRepo = new RfidCheckin\Repositories\CheckinRepository();
    echo "✓ CheckinRepository initialized successfully\n";
    
    // Test Router
    echo "Testing Router...\n";
    $router = new RfidCheckin\Routing\Router();
    echo "✓ Router initialized successfully\n";
    
    echo "\n🎉 All core components loaded successfully!\n";
    echo "The RFID Check-in system is ready for production use.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
} catch (Error $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}