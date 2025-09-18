<?php
// Debug version of index.php
echo "<h1>Debug Application Test</h1>";

try {
    echo "<p>Step 1: Starting debug...</p>";
    
    // Enable error reporting
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    echo "<p>Step 2: Request info:</p>";
    echo "<ul>";
    echo "<li>REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'not set') . "</li>";
    echo "<li>REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'not set') . "</li>";
    echo "<li>SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'not set') . "</li>";
    echo "<li>PATH_INFO: " . ($_SERVER['PATH_INFO'] ?? 'not set') . "</li>";
    echo "</ul>";
    
    echo "<p>Step 3: Loading bootstrap...</p>";
    
    // Start output buffering like the real index.php
    ob_start();
    
    // Load bootstrap but catch any output
    require_once __DIR__ . '/bootstrap.php';
    
    // Get any output from bootstrap
    $bootstrapOutput = ob_get_contents();
    ob_end_clean();
    
    echo "<p>Step 4: Bootstrap loaded successfully!</p>";
    
    if (!empty($bootstrapOutput)) {
        echo "<p>Bootstrap output:</p>";
        echo "<pre>" . htmlspecialchars($bootstrapOutput) . "</pre>";
    } else {
        echo "<p>Bootstrap produced no output (normal)</p>";
    }
    
    echo "<p>✓ Application should be working!</p>";
    
} catch (Exception $e) {
    echo "<p>✗ Exception: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
} catch (Error $e) {
    echo "<p>✗ Fatal Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<h2>Debug Complete</h2>";
?>