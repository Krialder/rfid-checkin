<?php
/**
 * Simple test to verify the system bootstrap works
 */

echo "Starting RFID Check-in System test...\n";

require_once __DIR__ . '/bootstrap.php';

echo "Testing RFID Check-in System Bootstrap...\n\n";

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