<?php
// Set content type for browser display
header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html><html><head><title>Debug Info</title></head><body>";
echo "<h1>Debug Information</h1>";
echo "<pre>";

try {
    echo "Test file is working!\n";
    echo "PHP Version: " . phpversion() . "\n";
    echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'not set') . "\n";
    echo "Script name: " . ($_SERVER['SCRIPT_NAME'] ?? 'not set') . "\n";
    echo "Query string: " . ($_SERVER['QUERY_STRING'] ?? 'not set') . "\n";
    echo "HTTP Host: " . ($_SERVER['HTTP_HOST'] ?? 'not set') . "\n";
    echo "Document root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'not set') . "\n";
    echo "Current working directory: " . getcwd() . "\n";
    echo "File exists check for .htaccess: " . (file_exists('.htaccess') ? 'YES' : 'NO') . "\n";
    echo "File exists check for index.php: " . (file_exists('index.php') ? 'YES' : 'NO') . "\n";
    echo "File exists check for bootstrap.php: " . (file_exists('bootstrap.php') ? 'YES' : 'NO') . "\n";
    
    echo "\n--- .htaccess content ---\n";
    if (file_exists('.htaccess')) {
        echo htmlspecialchars(file_get_contents('.htaccess'));
    } else {
        echo ".htaccess file not found";
    }
    
    echo "\n--- Apache modules (if available) ---\n";
    if (function_exists('apache_get_modules')) {
        $modules = apache_get_modules();
        echo "mod_rewrite enabled: " . (in_array('mod_rewrite', $modules) ? 'YES' : 'NO') . "\n";
    } else {
        echo "apache_get_modules() function not available\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "</body></html>";