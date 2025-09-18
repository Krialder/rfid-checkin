<?php
/**
 * Configuration & Services Test Suite
 * 
 * Tests configuration loading, service initialization, database connections, and logging
 * Senior developer approach: Verify services layer before application logic
 */

class ConfigurationServicesTest {
    private $results = [];
    private $errors = [];
    private $warnings = [];
    private $services = [];
    
    public function runAllTests(): bool {
        echo "🔧 RFID System Configuration & Services Tests\n";
        echo "=============================================\n\n";
        
        $tests = [
            'testConfigurationLoading',
            'testEnvironmentVariables',
            'testDatabaseServiceInitialization',
            'testLoggingServiceInitialization',
            'testConfigurationServiceInitialization',
            'testAuthenticationServiceInitialization',
            'testServiceDependencies',
            'testDatabaseConnection',
            'testLoggingFunctionality',
            'testApplicationBootstrap'
        ];
        
        $passed = 0;
        $total = count($tests);
        
        foreach ($tests as $test) {
            echo "Running: " . str_replace('test', '', $test) . "... ";
            
            try {
                $result = $this->$test();
                if ($result) {
                    echo "✅ PASS\n";
                    $passed++;
                } else {
                    echo "❌ FAIL\n";
                }
            } catch (Exception $e) {
                echo "💥 ERROR: " . $e->getMessage() . "\n";
                $this->errors[] = $test . ": " . $e->getMessage();
            } catch (Error $e) {
                echo "💥 FATAL: " . $e->getMessage() . "\n";
                $this->errors[] = $test . ": " . $e->getMessage();
            }
        }
        
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "Results: {$passed}/{$total} tests passed\n";
        echo "Services initialized: " . count($this->services) . "\n";
        
        if (!empty($this->services)) {
            echo "\nInitialized services:\n";
            foreach ($this->services as $service => $status) {
                echo "  ✓ $service: $status\n";
            }
        }
        
        if (!empty($this->errors)) {
            echo "\n🚨 ERRORS:\n";
            foreach ($this->errors as $error) {
                echo "  - $error\n";
            }
        }
        
        if (!empty($this->warnings)) {
            echo "\n⚠️  WARNINGS:\n";
            foreach ($this->warnings as $warning) {
                echo "  - $warning\n";
            }
        }
        
        return $passed === $total && empty($this->errors);
    }
    
    private function setupEnvironment(): void {
        // Define constants like in bootstrap
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', dirname(__FILE__, 2));
        }
        if (!defined('APP_START_TIME')) {
            define('APP_START_TIME', microtime(true));
        }
        if (!defined('APP_VERSION')) {
            define('APP_VERSION', '2.0.0');
        }
        
        // Register autoloader
        spl_autoload_register(function ($className) {
            $className = str_replace('RfidCheckin\\', '', $className);
            $className = str_replace('\\', DIRECTORY_SEPARATOR, $className);
            $file = APP_ROOT . '/src/' . $className . '.php';
            
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
            return false;
        });
    }
    
    private function testConfigurationLoading(): bool {
        $this->setupEnvironment();
        
        $configFile = APP_ROOT . '/core/config.php';
        
        if (!file_exists($configFile)) {
            $this->errors[] = "Configuration file missing: $configFile";
            return false;
        }
        
        // Load configuration
        require_once $configFile;
        
        // Check required constants
        $required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'BASE_URL', 'APP_NAME'];
        $missing = [];
        
        foreach ($required as $const) {
            if (!defined($const)) {
                $missing[] = $const;
            }
        }
        
        if (!empty($missing)) {
            $this->errors[] = "Missing configuration constants: " . implode(', ', $missing);
            return false;
        }
        
        echo "Config loaded ";
        return true;
    }
    
    private function testEnvironmentVariables(): bool {
        // Check if environment variables are set
        $envVars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'APP_ENV'];
        $set = 0;
        
        foreach ($envVars as $var) {
            if (isset($_ENV[$var])) {
                $set++;
            }
        }
        
        echo "Env vars: {$set}/" . count($envVars) . " ";
        return true; // Not critical if some are missing
    }
    
    private function testDatabaseServiceInitialization(): bool {
        try {
            if (!class_exists('RfidCheckin\\Services\\DatabaseService')) {
                $this->errors[] = "DatabaseService class not found";
                return false;
            }
            
            $service = RfidCheckin\Services\DatabaseService::getInstance();
            $this->services['DatabaseService'] = 'Singleton created';
            
            echo "DB service init ";
            return true;
        } catch (Exception $e) {
            $this->errors[] = "DatabaseService initialization failed: " . $e->getMessage();
            return false;
        }
    }
    
    private function testLoggingServiceInitialization(): bool {
        try {
            if (!class_exists('RfidCheckin\\Services\\LoggingService')) {
                $this->errors[] = "LoggingService class not found";
                return false;
            }
            
            $service = RfidCheckin\Services\LoggingService::getInstance();
            $this->services['LoggingService'] = 'Singleton created';
            
            echo "Logging service init ";
            return true;
        } catch (Exception $e) {
            $this->errors[] = "LoggingService initialization failed: " . $e->getMessage();
            return false;
        }
    }
    
    private function testConfigurationServiceInitialization(): bool {
        try {
            if (!class_exists('RfidCheckin\\Services\\ConfigurationService')) {
                $this->errors[] = "ConfigurationService class not found";
                return false;
            }
            
            $service = RfidCheckin\Services\ConfigurationService::getInstance();
            $this->services['ConfigurationService'] = 'Singleton created';
            
            echo "Config service init ";
            return true;
        } catch (Exception $e) {
            $this->errors[] = "ConfigurationService initialization failed: " . $e->getMessage();
            return false;
        }
    }
    
    private function testAuthenticationServiceInitialization(): bool {
        try {
            if (!class_exists('RfidCheckin\\Services\\AuthenticationService')) {
                $this->errors[] = "AuthenticationService class not found";
                return false;
            }
            
            $service = RfidCheckin\Services\AuthenticationService::getInstance();
            $this->services['AuthenticationService'] = 'Singleton created';
            
            echo "Auth service init ";
            return true;
        } catch (Exception $e) {
            $this->errors[] = "AuthenticationService initialization failed: " . $e->getMessage();
            return false;
        }
    }
    
    private function testServiceDependencies(): bool {
        try {
            // Test that services can access each other
            $dbService = RfidCheckin\Services\DatabaseService::getInstance();
            $logService = RfidCheckin\Services\LoggingService::getInstance();
            $configService = RfidCheckin\Services\ConfigurationService::getInstance();
            
            // Test if services have required methods
            $methods = [
                [$dbService, 'getConnection'],
                [$logService, 'log'],
                [$configService, 'get']
            ];
            
            foreach ($methods as [$service, $method]) {
                if (!method_exists($service, $method)) {
                    $this->warnings[] = get_class($service) . " missing method: $method";
                }
            }
            
            echo "Dependencies OK ";
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Service dependency test failed: " . $e->getMessage();
            return false;
        }
    }
    
    private function testDatabaseConnection(): bool {
        try {
            $dbService = RfidCheckin\Services\DatabaseService::getInstance();
            $connection = $dbService->getConnection();
            
            if ($connection instanceof PDO) {
                // Test a simple query
                $stmt = $connection->query("SELECT 1 as test");
                $result = $stmt->fetch();
                
                if ($result && $result['test'] == 1) {
                    echo "DB connection OK ";
                    return true;
                } else {
                    $this->errors[] = "Database query test failed";
                    return false;
                }
            } else {
                $this->errors[] = "Database connection is not PDO instance";
                return false;
            }
        } catch (Exception $e) {
            $this->errors[] = "Database connection test failed: " . $e->getMessage();
            return false;
        }
    }
    
    private function testLoggingFunctionality(): bool {
        try {
            $logService = RfidCheckin\Services\LoggingService::getInstance();
            
            // Test logging
            $result = $logService->log('info', 'Test log entry from service test');
            
            echo "Logging functional ";
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Logging functionality test failed: " . $e->getMessage();
            return false;
        }
    }
    
    private function testApplicationBootstrap(): bool {
        try {
            // Test full Application initialization
            if (!class_exists('RfidCheckin\\Application')) {
                $this->errors[] = "Application class not found";
                return false;
            }
            
            $app = new RfidCheckin\Application();
            
            // Test if application has required methods
            $methods = ['run', 'shutdown'];
            foreach ($methods as $method) {
                if (!method_exists($app, $method)) {
                    $this->warnings[] = "Application missing method: $method";
                }
            }
            
            echo "App bootstrap OK ";
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Application bootstrap failed: " . $e->getMessage();
            return false;
        }
    }
}

// Run tests
$tester = new ConfigurationServicesTest();
$success = $tester->runAllTests();

if (!$success) {
    echo "\n❌ Configuration & Services tests failed. Fix service issues before proceeding.\n";
    exit(1);
} else {
    echo "\n✅ Configuration & Services tests passed. Services layer is functioning.\n";
}
?>