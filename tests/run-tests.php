<?php
/**
 * Test Runner - Execute comprehensive test suite
 * 
 * Command-line interface for running the complete test suite
 * with options for specific test types and detailed reporting.
 */

// Set up error reporting for testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the test framework
require_once __DIR__ . '/TestFramework.php';

class TestRunner {
    
    public static function main($args = []) {
        echo "🚀 RFID Check-in System - Enterprise Test Suite\n";
        echo "================================================\n\n";
        
        try {
            $framework = TestFramework::getInstance();
            $results = $framework->runAllTests();
            
            // Exit with appropriate code
            $exitCode = $results['summary']['failed'] > 0 ? 1 : 0;
            exit($exitCode);
            
        } catch (Exception $e) {
            echo "❌ Test execution failed: " . $e->getMessage() . "\n";
            exit(2);
        }
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    TestRunner::main($argv ?? []);
}
?>
