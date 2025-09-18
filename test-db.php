<?php
// Simple database connection test
require_once 'core/config.php';

echo "<h1>Database Connection Test</h1>";

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    
    echo "<p style='color: green;'>✅ Database connection successful!</p>";
    
    // Test if tables exist
    $tables = ['users', 'events', 'checkin'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✅ Table '$table' exists</p>";
        } else {
            echo "<p style='color: red;'>❌ Table '$table' missing</p>";
        }
    }
    
    // Test default admin user
    $stmt = $pdo->query("SELECT username, email FROM users WHERE role = 'admin' LIMIT 1");
    if ($admin = $stmt->fetch()) {
        echo "<p style='color: green;'>✅ Admin user found: {$admin['username']} ({$admin['email']})</p>";
    } else {
        echo "<p style='color: red;'>❌ No admin user found</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='http://localhost/rfid-checkin/'>← Back to main application</a></p>";
?>