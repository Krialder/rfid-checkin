<?php
// Minimal bootstrap test
echo "<h1>Minimal Bootstrap Test</h1>";

try {
    echo "<p>Step 1: Setting error reporting...</p>";
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    echo "<p>Step 2: Starting session...</p>";
    session_start();
    
    echo "<p>Step 3: Setting up autoloader...</p>";
    require_once __DIR__ . '/src/Application.php';
    
    // Try to create the autoloader function
    function autoloadClasses($className) {
        $className = ltrim($className, '\\');
        $fileName = '';
        $namespace = '';
        
        if ($lastNsPos = strrpos($className, '\\')) {
            $namespace = substr($className, 0, $lastNsPos);
            $className = substr($className, $lastNsPos + 1);
            $fileName = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
        }
        
        $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
        $fullPath = __DIR__ . '/src/' . $fileName;
        
        if (file_exists($fullPath)) {
            require_once $fullPath;
            return true;
        }
        
        return false;
    }
    
    echo "<p>Step 4: Registering autoloader...</p>";
    spl_autoload_register('autoloadClasses');
    
    echo "<p>Step 5: Testing class loading...</p>";
    
    // Test if we can load a simple class
    if (class_exists('RfidCheckin\\Application')) {
        echo "<p>✓ Application class found</p>";
    } else {
        echo "<p>✗ Application class not found</p>";
    }
    
    echo "<p>✓ Bootstrap components loaded successfully!</p>";
    
} catch (Exception $e) {
    echo "<p>✗ Exception: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
} catch (Error $e) {
    echo "<p>✗ Fatal Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
}

echo "<h2>Test Complete</h2>";
?>