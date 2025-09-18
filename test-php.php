<?php
// Basic PHP test file
echo "<h1>PHP Test</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Current Time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Working Directory: " . getcwd() . "</p>";

// Test if required extensions are loaded
$requiredExtensions = ['pdo', 'pdo_mysql', 'session', 'json'];
echo "<h2>PHP Extensions</h2>";
foreach ($requiredExtensions as $ext) {
    $status = extension_loaded($ext) ? "✓ Loaded" : "✗ Not loaded";
    echo "<p>{$ext}: {$status}</p>";
}

// Test file paths
echo "<h2>File System</h2>";
echo "<p>Index.php exists: " . (file_exists('index.php') ? "✓ Yes" : "✗ No") . "</p>";
echo "<p>Bootstrap.php exists: " . (file_exists('bootstrap.php') ? "✓ Yes" : "✗ No") . "</p>";
echo "<p>Core directory exists: " . (is_dir('core') ? "✓ Yes" : "✗ No") . "</p>";
echo "<p>Src directory exists: " . (is_dir('src') ? "✓ Yes" : "✗ No") . "</p>";

echo "<h2>Success</h2>";
echo "<p>If you can see this, PHP is working properly!</p>";
?>