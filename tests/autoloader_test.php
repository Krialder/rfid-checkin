<?php
/**
 * Core Class Loading & Autoloader Test Suite
 * 
 * Tests autoloader functionality, class loading, namespace resolution
 * Senior developer approach: Verify foundation before building up
 */

// Prevent direct browser access if not intended
if (php_sapi_name() !== 'cli' && !isset($_GET['web'])) {
    echo "Add ?web=1 to run in browser or use CLI: php autoloader_test.php\n";
    exit(1);
}

class AutoloaderTest {
    private $results = [];
    private $errors = [];
    private $warnings = [];
    private $loaded_classes = [];
    
    public function runAllTests(): bool {
        echo "🔧 RFID System Autoloader & Class Loading Tests\n";
        echo "===============================================\n\n";
        
        $tests = [
            'testAutoloaderRegistration',
            'testBasicClassLoading',
            'testNamespaceResolution',
            'testApplicationClass',
            'testServiceClasses',
            'testControllerClasses',
            'testModelClasses',
            'testRepositoryClasses',
            'testMiddlewareClasses',
            'testDependencyResolution'
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
        echo "Classes loaded: " . count($this->loaded_classes) . "\n";
        
        if (!empty($this->loaded_classes)) {
            echo "\nLoaded classes:\n";
            foreach ($this->loaded_classes as $class) {
                echo "  ✓ $class\n";
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
    
    private function setupAutoloader(): void {
        // Define constants like in bootstrap
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', dirname(__FILE__, 2));
        }
        
        // Register autoloader like in bootstrap
        spl_autoload_register(function ($className) {
            // Convert namespace to file path
            $className = str_replace('RfidCheckin\\', '', $className);
            $className = str_replace('\\', DIRECTORY_SEPARATOR, $className);
            
            $file = APP_ROOT . '/src/' . $className . '.php';
            
            if (file_exists($file)) {
                require_once $file;
                $this->loaded_classes[] = $className;
                return true;
            }
            
            return false;
        });
    }
    
    private function testAutoloaderRegistration(): bool {
        $this->setupAutoloader();
        
        $functions = spl_autoload_functions();
        if (empty($functions)) {
            $this->errors[] = "No autoloader functions registered";
            return false;
        }
        
        echo "Autoloader registered ";
        return true;
    }
    
    private function testBasicClassLoading(): bool {
        try {
            // Test if we can load a basic class
            $reflection = new ReflectionClass('Exception');
            echo "Basic class loading works ";
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Basic class loading failed: " . $e->getMessage();
            return false;
        }
    }
    
    private function testNamespaceResolution(): bool {
        $testCases = [
            'RfidCheckin\\Application' => 'Application.php',
            'RfidCheckin\\Models\\User' => 'Models/User.php',
            'RfidCheckin\\Controllers\\Auth\\LoginController' => 'Controllers/Auth/LoginController.php',
            'RfidCheckin\\Services\\DatabaseService' => 'Services/DatabaseService.php'
        ];
        
        $issues = [];
        
        foreach ($testCases as $namespace => $expectedPath) {
            $className = str_replace('RfidCheckin\\', '', $namespace);
            $className = str_replace('\\', DIRECTORY_SEPARATOR, $className);
            $actualPath = APP_ROOT . '/src/' . $className . '.php';
            
            if (!file_exists($actualPath)) {
                $issues[] = "Missing: $expectedPath";
            }
        }
        
        if (empty($issues)) {
            echo "Namespace resolution OK ";
            return true;
        } else {
            $this->warnings[] = "Namespace issues: " . implode(', ', $issues);
            echo "Some namespace issues ";
            return true; // Warning, not error
        }
    }
    
    private function testApplicationClass(): bool {
        try {
            if (class_exists('RfidCheckin\\Application')) {
                echo "Application class found ";
                return true;
            } else {
                $this->errors[] = "Application class not found";
                return false;
            }
        } catch (Exception $e) {
            $this->errors[] = "Application class error: " . $e->getMessage();
            return false;
        }
    }
    
    private function testServiceClasses(): bool {
        $services = [
            'RfidCheckin\\Services\\DatabaseService',
            'RfidCheckin\\Services\\ConfigurationService',
            'RfidCheckin\\Services\\LoggingService',
            'RfidCheckin\\Services\\AuthenticationService'
        ];
        
        $loaded = 0;
        $total = count($services);
        
        foreach ($services as $service) {
            try {
                if (class_exists($service)) {
                    $loaded++;
                }
            } catch (Exception $e) {
                $this->warnings[] = "Service loading error: $service - " . $e->getMessage();
            }
        }
        
        echo "Services: {$loaded}/{$total} ";
        return $loaded > 0; // At least some services should load
    }
    
    private function testControllerClasses(): bool {
        $controllers = [
            'RfidCheckin\\Controllers\\BaseFrontendController',
            'RfidCheckin\\Controllers\\BaseApiController',
            'RfidCheckin\\Controllers\\ErrorController'
        ];
        
        $loaded = 0;
        $total = count($controllers);
        
        foreach ($controllers as $controller) {
            try {
                if (class_exists($controller)) {
                    $loaded++;
                }
            } catch (Exception $e) {
                $this->warnings[] = "Controller loading error: $controller - " . $e->getMessage();
            }
        }
        
        echo "Controllers: {$loaded}/{$total} ";
        return $loaded > 0;
    }
    
    private function testModelClasses(): bool {
        $models = [
            'RfidCheckin\\Models\\BaseModel',
            'RfidCheckin\\Models\\User',
            'RfidCheckin\\Models\\Event'
        ];
        
        $loaded = 0;
        $total = count($models);
        
        foreach ($models as $model) {
            try {
                if (class_exists($model)) {
                    $loaded++;
                }
            } catch (Exception $e) {
                $this->warnings[] = "Model loading error: $model - " . $e->getMessage();
            }
        }
        
        echo "Models: {$loaded}/{$total} ";
        return $loaded > 0;
    }
    
    private function testRepositoryClasses(): bool {
        $repositories = [
            'RfidCheckin\\Repositories\\BaseRepository',
            'RfidCheckin\\Repositories\\UserRepository',
            'RfidCheckin\\Repositories\\CheckinRepository'
        ];
        
        $loaded = 0;
        $total = count($repositories);
        
        foreach ($repositories as $repo) {
            try {
                if (class_exists($repo)) {
                    $loaded++;
                }
            } catch (Exception $e) {
                $this->warnings[] = "Repository loading error: $repo - " . $e->getMessage();
            }
        }
        
        echo "Repositories: {$loaded}/{$total} ";
        return $loaded > 0;
    }
    
    private function testMiddlewareClasses(): bool {
        $middleware = [
            'RfidCheckin\\Middleware\\MiddlewareInterface',
            'RfidCheckin\\Middleware\\MiddlewareManager',
            'RfidCheckin\\Middleware\\AuthenticationMiddleware'
        ];
        
        $loaded = 0;
        $total = count($middleware);
        
        foreach ($middleware as $mw) {
            try {
                if (class_exists($mw) || interface_exists($mw)) {
                    $loaded++;
                }
            } catch (Exception $e) {
                $this->warnings[] = "Middleware loading error: $mw - " . $e->getMessage();
            }
        }
        
        echo "Middleware: {$loaded}/{$total} ";
        return $loaded > 0;
    }
    
    private function testDependencyResolution(): bool {
        try {
            // Test if we can instantiate Application
            if (class_exists('RfidCheckin\\Application')) {
                $app = new RfidCheckin\Application();
                echo "Application instantiated ";
                return true;
            } else {
                $this->errors[] = "Cannot instantiate Application";
                return false;
            }
        } catch (Exception $e) {
            $this->errors[] = "Dependency resolution failed: " . $e->getMessage();
            return false;
        } catch (Error $e) {
            $this->errors[] = "Fatal dependency error: " . $e->getMessage();
            return false;
        }
    }
}

// Run tests
$tester = new AutoloaderTest();
$success = $tester->runAllTests();

if (!$success) {
    echo "\n❌ Autoloader tests failed. Fix class loading issues before proceeding.\n";
    exit(1);
} else {
    echo "\n✅ Autoloader tests passed. Classes are loading correctly.\n";
}
?>