<?php
/**
 * Infrastructure & Environment Test Suite
 * 
 * Tests the fundamental infrastructure requirements for the RFID system
 * Senior developer approach: Test environment before application code
 */

// Prevent direct browser access if not intended
if (php_sapi_name() !== 'cli' && !isset($_GET['web'])) {
    echo "Add ?web=1 to run in browser or use CLI: php infrastructure_test.php\n";
    exit(1);
}

class InfrastructureTest {
    private $results = [];
    private $errors = [];
    private $warnings = [];
    
    public function runAllTests(): bool {
        echo "🔧 RFID System Infrastructure Tests\n";
        echo "=====================================\n\n";
        
        $tests = [
            'testPHPVersion',
            'testRequiredExtensions', 
            'testFilePermissions',
            'testDirectoryStructure',
            'testWebServerConfiguration',
            'testDatabaseConnectivity',
            'testConfigurationFiles',
            'testLogDirectories',
            'testSecuritySettings'
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
                $this->errors[] = $e->getMessage();
            }
        }
        
        echo "\n" . str_repeat("=", 40) . "\n";
        echo "Results: {$passed}/{$total} tests passed\n";
        
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
    
    private function testPHPVersion(): bool {
        $version = phpversion();
        $required = '8.1.0';
        
        if (version_compare($version, $required, '>=')) {
            echo "PHP {$version} ";
            return true;
        } else {
            $this->errors[] = "PHP {$version} < required {$required}";
            return false;
        }
    }
    
    private function testRequiredExtensions(): bool {
        $required = ['pdo', 'pdo_mysql', 'mysqli', 'json', 'mbstring', 'openssl', 'session'];
        $missing = [];
        
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }
        
        if (empty($missing)) {
            echo "All extensions loaded ";
            return true;
        } else {
            $this->errors[] = "Missing extensions: " . implode(', ', $missing);
            return false;
        }
    }
    
    private function testFilePermissions(): bool {
        $root = dirname(__FILE__, 2);
        $paths = [
            $root . '/logs' => 'writable',
            $root . '/core/config.php' => 'readable',
            $root . '/.htaccess' => 'readable',
            $root . '/index.php' => 'readable'
        ];
        
        $issues = [];
        
        foreach ($paths as $path => $requirement) {
            if (!file_exists($path)) {
                $issues[] = "Missing: " . basename($path);
                continue;
            }
            
            if ($requirement === 'writable' && !is_writable($path)) {
                $issues[] = "Not writable: " . basename($path);
            } elseif ($requirement === 'readable' && !is_readable($path)) {
                $issues[] = "Not readable: " . basename($path);
            }
        }
        
        if (empty($issues)) {
            echo "Permissions OK ";
            return true;
        } else {
            $this->errors[] = "Permission issues: " . implode(', ', $issues);
            return false;
        }
    }
    
    private function testDirectoryStructure(): bool {
        $root = dirname(__FILE__, 2);
        $required = [
            'src', 'core', 'assets', 'config', 'database', 
            'tests', 'logs', 'src/Controllers', 'src/Models', 
            'src/Services', 'src/Repositories'
        ];
        
        $missing = [];
        
        foreach ($required as $dir) {
            if (!is_dir($root . '/' . $dir)) {
                $missing[] = $dir;
            }
        }
        
        if (empty($missing)) {
            echo "Structure complete ";
            return true;
        } else {
            $this->warnings[] = "Missing directories: " . implode(', ', $missing);
            return true; // Warning, not error
        }
    }
    
    private function testWebServerConfiguration(): bool {
        $issues = [];
        
        // Test if running through web server
        if (isset($_SERVER['SERVER_SOFTWARE'])) {
            echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . " ";
            
            // Test URL rewriting
            if (isset($_SERVER['REQUEST_URI'])) {
                echo "URL rewrite capable ";
            } else {
                $this->warnings[] = "URL rewriting may not work";
            }
            
            // Test mod_rewrite (Apache specific)
            if (function_exists('apache_get_modules')) {
                $modules = apache_get_modules();
                if (!in_array('mod_rewrite', $modules)) {
                    $this->warnings[] = "mod_rewrite not detected";
                }
            }
        } else {
            echo "CLI mode ";
        }
        
        return true;
    }
    
    private function testDatabaseConnectivity(): bool {
        $root = dirname(__FILE__, 2);
        
        // Load config if available
        if (file_exists($root . '/core/config.php')) {
            require_once $root . '/core/config.php';
        } else {
            $this->errors[] = "Config file missing";
            return false;
        }
        
        if (!defined('DB_HOST')) {
            $this->errors[] = "Database constants not defined";
            return false;
        }
        
        try {
            $dsn = "mysql:host=" . DB_HOST . ";charset=" . (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
            
            // Test database exists
            $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
            $stmt->execute([DB_NAME]);
            
            if ($stmt->fetch()) {
                echo "DB connected ";
                return true;
            } else {
                $this->errors[] = "Database '" . DB_NAME . "' does not exist";
                return false;
            }
            
        } catch (PDOException $e) {
            $this->errors[] = "Database connection failed: " . $e->getMessage();
            return false;
        }
    }
    
    private function testConfigurationFiles(): bool {
        $root = dirname(__FILE__, 2);
        $configs = [
            $root . '/core/config.php' => 'Main config',
            $root . '/.htaccess' => 'URL rewrite config'
        ];
        
        $issues = [];
        
        foreach ($configs as $file => $desc) {
            if (!file_exists($file)) {
                $issues[] = "$desc missing";
            } elseif (filesize($file) === 0) {
                $issues[] = "$desc empty";
            }
        }
        
        if (empty($issues)) {
            echo "Config files OK ";
            return true;
        } else {
            $this->errors[] = implode(', ', $issues);
            return false;
        }
    }
    
    private function testLogDirectories(): bool {
        $root = dirname(__FILE__, 2);
        $logDir = $root . '/logs';
        
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true)) {
                $this->errors[] = "Cannot create logs directory";
                return false;
            }
        }
        
        if (!is_writable($logDir)) {
            $this->errors[] = "Logs directory not writable";
            return false;
        }
        
        // Test log file creation
        $testFile = $logDir . '/test_' . time() . '.log';
        if (file_put_contents($testFile, "test\n") === false) {
            $this->errors[] = "Cannot write to logs directory";
            return false;
        }
        
        unlink($testFile);
        echo "Logging ready ";
        return true;
    }
    
    private function testSecuritySettings(): bool {
        $issues = [];
        
        // Check dangerous functions (Note: Application doesn't use shell execution)
        $dangerous = ['exec', 'shell_exec', 'system', 'passthru'];
        foreach ($dangerous as $func) {
            if (function_exists($func)) {
                $this->warnings[] = "Dangerous function '$func' available (consider disable_functions in production)";
            }
        }
        
        // Check error reporting in production
        if (defined('DEBUG_MODE') && !DEBUG_MODE) {
            if (ini_get('display_errors')) {
                $this->warnings[] = "Error display enabled in production mode";
            }
        }
        
        // Check session settings (Note: Application configures secure sessions on startup)
        if (!ini_get('session.cookie_httponly')) {
            $this->warnings[] = "Session cookies not HTTP-only (configured securely by application)";
        }
        
        echo "Security checked ";
        return true;
    }
}

// Run tests
$tester = new InfrastructureTest();
$success = $tester->runAllTests();

if (!$success) {
    echo "\n❌ Infrastructure tests failed. Fix issues before proceeding.\n";
    exit(1);
} else {
    echo "\n✅ Infrastructure tests passed. System ready for application testing.\n";
}
?>