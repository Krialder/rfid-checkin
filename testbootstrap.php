<?php
echo "Testing bootstrap...<br>";

// Define constants like in bootstrap
define('APP_START_TIME', microtime(true));
define('APP_ROOT', __DIR__);
define('APP_VERSION', '2.0.0');

echo "Constants defined<br>";

// Test autoloader
spl_autoload_register(function ($className) {
    echo "Trying to load: $className<br>";
    
    // Convert namespace to file path
    $className = str_replace('RfidCheckin\\', '', $className);
    $className = str_replace('\\', DIRECTORY_SEPARATOR, $className);
    
    $file = __DIR__ . '/src/' . $className . '.php';
    
    echo "Looking for file: $file<br>";
    
    if (file_exists($file)) {
        echo "File exists, requiring...<br>";
        require_once $file;
        echo "File loaded successfully<br>";
        return true;
    } else {
        echo "File not found<br>";
    }
    
    return false;
});

echo "Autoloader registered<br>";

// Try to load the Application class
try {
    echo "Attempting to load Application class...<br>";
    use RfidCheckin\Application;
    echo "Application class use statement processed<br>";
    
    $app = new Application();
    echo "Application instance created successfully!<br>";
    
} catch (Exception $e) {
    echo "Error creating Application: " . $e->getMessage() . "<br>";
    echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "Fatal error: " . $e->getMessage() . "<br>";
    echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}
?>