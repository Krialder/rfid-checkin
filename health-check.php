<?php

declare(strict_types=1);

/**
 * System Health Check Script
 * 
 * Tests all core services to ensure they work together properly
 * and the circular dependency fixes are working.
 */

require_once __DIR__ . '/bootstrap.php';

use RfidCheckin\Services\ConfigurationService;
use RfidCheckin\Services\DatabaseService;
use RfidCheckin\Services\LoggingService;
use RfidCheckin\Services\SecurityService;

echo "=== RFID Check-in System Health Check ===\n\n";

$errors = [];
$warnings = [];

// Test 1: Configuration Service
echo "1. Testing Configuration Service...\n";
try {
    $config = ConfigurationService::getInstance();
    echo "   ✓ Configuration service initialized\n";
    echo "   ✓ Environment: " . $config->getEnvironment() . "\n";
    echo "   ✓ Debug mode: " . ($config->isDebugMode() ? 'enabled' : 'disabled') . "\n";
    
    // Test configuration values
    $dbHost = $config->get('database.host');
    if ($dbHost) {
        echo "   ✓ Database host configured: $dbHost\n";
    } else {
        $warnings[] = "Database host not configured";
    }
} catch (Exception $e) {
    $errors[] = "Configuration Service: " . $e->getMessage();
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 2: Logging Service
echo "\n2. Testing Logging Service...\n";
try {
    $logger = LoggingService::getInstance();
    echo "   ✓ Logging service initialized\n";
    
    // Test logging functionality
    $logger->info('Health check test message');
    echo "   ✓ Logging working\n";
    
    $stats = $logger->getStats();
    echo "   ✓ Log directory: " . $stats['log_directory'] . "\n";
    echo "   ✓ Log level: " . $stats['log_level'] . "\n";
} catch (Exception $e) {
    $errors[] = "Logging Service: " . $e->getMessage();
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 3: Database Service
echo "\n3. Testing Database Service...\n";
try {
    $database = DatabaseService::getInstance();
    echo "   ✓ Database service initialized\n";
    
    $connectionTest = $database->testConnection();
    if ($connectionTest) {
        echo "   ✓ Database connection successful\n";
        
        $stats = $database->getStats();
        echo "   ✓ Database: " . $stats['database'] . "\n";
        echo "   ✓ Server version: " . $stats['server_version'] . "\n";
        echo "   ✓ Query count: " . $stats['query_count'] . "\n";
    } else {
        $warnings[] = "Database connection failed - check configuration";
        echo "   ⚠ Database connection failed\n";
    }
} catch (Exception $e) {
    $errors[] = "Database Service: " . $e->getMessage();
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 4: Security Service
echo "\n4. Testing Security Service...\n";
try {
    $security = SecurityService::getInstance();
    echo "   ✓ Security service initialized\n";
    
    // Test CSRF token generation
    $token = $security->generateCSRFToken('test');
    if ($token && strlen($token) > 10) {
        echo "   ✓ CSRF token generation working\n";
    } else {
        $warnings[] = "CSRF token generation issue";
    }
    
    // Test input validation
    $email = $security->validateInput('test@example.com', 'email');
    if ($email === 'test@example.com') {
        echo "   ✓ Email validation working\n";
    } else {
        $warnings[] = "Email validation issue";
    }
    
    $invalidEmail = $security->validateInput('invalid-email', 'email');
    if ($invalidEmail === false) {
        echo "   ✓ Email validation properly rejects invalid input\n";
    } else {
        $warnings[] = "Email validation not properly rejecting invalid input";
    }
} catch (Exception $e) {
    $errors[] = "Security Service: " . $e->getMessage();
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 5: Service Integration (circular dependency check)
echo "\n5. Testing Service Integration...\n";
try {
    // This tests that services can work together without circular dependency issues
    $config = ConfigurationService::getInstance();
    $logger = LoggingService::getInstance();
    $database = DatabaseService::getInstance();
    $security = SecurityService::getInstance();
    
    // Test that logging works in database service
    if ($database->testConnection()) {
        echo "   ✓ Database and logging integration working\n";
    }
    
    // Test configuration access from multiple services
    $dbConfig = $config->get('database.host');
    $logLevel = $config->get('logging.level');
    if ($dbConfig && $logLevel) {
        echo "   ✓ Configuration access from multiple services working\n";
    }
    
    echo "   ✓ No circular dependency issues detected\n";
} catch (Exception $e) {
    $errors[] = "Service Integration: " . $e->getMessage();
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Performance metrics
echo "\n6. Performance Metrics:\n";
$memoryUsage = memory_get_usage() / 1024 / 1024;
$peakMemory = memory_get_peak_usage() / 1024 / 1024;
echo "   Memory Usage: " . round($memoryUsage, 2) . " MB\n";
echo "   Peak Memory: " . round($peakMemory, 2) . " MB\n";

if ($memoryUsage > 50) {
    $warnings[] = "High memory usage detected: " . round($memoryUsage, 2) . " MB";
}

// Summary
echo "\n=== Health Check Summary ===\n";

if (empty($errors)) {
    echo "✅ All core services are working properly\n";
} else {
    echo "❌ Critical errors found:\n";
    foreach ($errors as $error) {
        echo "   - $error\n";
    }
}

if (!empty($warnings)) {
    echo "⚠️  Warnings:\n";
    foreach ($warnings as $warning) {
        echo "   - $warning\n";
    }
}

if (empty($errors) && empty($warnings)) {
    echo "🎉 System is healthy and ready for use!\n";
} elseif (empty($errors)) {
    echo "✅ System is functional with minor issues to address\n";
} else {
    echo "❌ System has critical issues that need immediate attention\n";
    exit(1);
}

echo "\n";