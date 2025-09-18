<?php
// Simple test without autoloader
echo "Simple routing test<br>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'not set') . "<br>";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'not set') . "<br>";
echo "PATH_INFO: " . ($_SERVER['PATH_INFO'] ?? 'not set') . "<br>";

// Test if config exists and can be loaded
if (file_exists(__DIR__ . '/core/config.php')) {
    echo "Config file exists<br>";
    try {
        require_once __DIR__ . '/core/config.php';
        echo "Config loaded successfully<br>";
        echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'not defined') . "<br>";
        echo "BASE_URL: " . (defined('BASE_URL') ? BASE_URL : 'not defined') . "<br>";
    } catch (Exception $e) {
        echo "Config error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "Config file missing<br>";
}

// Check .htaccess
if (file_exists(__DIR__ . '/.htaccess')) {
    echo ".htaccess exists<br>";
    echo ".htaccess content:<pre>" . htmlspecialchars(file_get_contents(__DIR__ . '/.htaccess')) . "</pre>";
} else {
    echo ".htaccess missing<br>";
}
?>