<?php
/**
 * Quick User Activation Script
 * Use this to quickly activate a specific user by email or username
 */

require_once '../core/config.php';
require_once '../core/database.php';
require_once '../core/auth.php';

// Check if admin is logged in
Auth::requireLogin();
$current_user = Auth::getCurrentUser();

if ($current_user['role'] !== 'admin') {
    die('Access denied - Admin only');
}

$db = getDB();

// Check if we have a user identifier
$identifier = $_GET['email'] ?? $_GET['username'] ?? '';

if (empty($identifier)) {
    die('Usage: activate_user.php?email=user@example.com OR activate_user.php?username=username');
}

try {
    // Determine if it's an email or username
    $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
    $field = $isEmail ? 'email' : 'username';
    
    // Find and activate the user
    $stmt = $db->prepare("
        UPDATE Users 
        SET is_active = 1, deleted_at = NULL, updated_at = NOW()
        WHERE $field = ? AND is_active = 0
    ");
    
    $stmt->execute([$identifier]);
    $rowsAffected = $stmt->rowCount();
    
    if ($rowsAffected > 0) {
        echo "<h2>✅ User Activated Successfully!</h2>";
        echo "<p>User with $field '<strong>$identifier</strong>' has been activated.</p>";
        
        // Log the activity
        $stmt = $db->prepare("
            INSERT INTO ActivityLog (user_id, action, details, ip_address, timestamp) 
            VALUES (?, 'user_activation', ?, ?, NOW())
        ");
        $stmt->execute([
            $current_user['user_id'],
            "Activated user: $identifier",
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
        
    } else {
        // Check if user exists but is already active
        $stmt = $db->prepare("SELECT is_active FROM Users WHERE $field = ?");
        $stmt->execute([$identifier]);
        $user = $stmt->fetch();
        
        if ($user) {
            if ($user['is_active'] == 1) {
                echo "<h2>ℹ️ User Already Active</h2>";
                echo "<p>User with $field '<strong>$identifier</strong>' is already active.</p>";
            }
        } else {
            echo "<h2>❌ User Not Found</h2>";
            echo "<p>No user found with $field '<strong>$identifier</strong>'.</p>";
        }
    }
    
    echo "<br><a href='users.php'>← Back to User Management</a>";
    
} catch (PDOException $e) {
    error_log('User activation error: ' . $e->getMessage());
    echo "<h2>❌ Database Error</h2>";
    echo "<p>Could not activate user. Please check the logs.</p>";
}
?>
