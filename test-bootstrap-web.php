<?php
// Test bootstrap loading
echo "<h1>Bootstrap Test</h1>";

try {
    // Start output buffering like index.php does
    ob_start();
    
    echo "<p>Loading bootstrap.php...</p>";
    require_once 'bootstrap.php';
    
    echo "<p>✓ Bootstrap loaded successfully!</p>";
    echo "<p>✓ Application initialized!</p>";
    
} catch (Exception $e) {
    echo "<p>✗ Error loading bootstrap: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Error file: " . $e->getFile() . " line " . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
} catch (Error $e) {
    echo "<p>✗ Fatal error loading bootstrap: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Error file: " . $e->getFile() . " line " . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<h2>Test Complete</h2>";
?>