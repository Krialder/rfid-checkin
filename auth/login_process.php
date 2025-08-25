<?php
/**
 * Login Process Handler
 */

require_once '../core/auth.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    header('Location: ' . BASE_URL . '/frontend/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    // Debug logging if enabled
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log("Login attempt for: $email");
    }
    
    // Basic validation
    if (empty($email) || empty($password)) {
        header('Location: login.php?error=' . urlencode('Please fill in all fields') . '&email=' . urlencode($email));
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: login.php?error=' . urlencode('Please enter a valid email address') . '&email=' . urlencode($email));
        exit();
    }
    
    // Attempt login
    if (Auth::login($email, $password)) {
        // Debug logging if enabled
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Login successful for: $email");
        }
        
        // Set remember me cookie if requested
        if ($remember_me) {
            setcookie('remember_token', 
                hash('sha256', $email . time()), 
                time() + (30 * 24 * 60 * 60), // 30 days
                '/', 
                '', 
                false, // secure - set to true in production with HTTPS
                true   // httponly
            );
        }
        
        // Redirect to dashboard
        header('Location: ' . BASE_URL . '/frontend/dashboard.php?success=' . urlencode('Welcome back!'));
        exit();
    } else {
        // Debug logging if enabled
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Login failed for: $email");
        }
        
        header('Location: login.php?error=' . urlencode('Invalid email or password. Please try again.') . '&email=' . urlencode($email));
        exit();
    }
} else {
    header('Location: login.php');
    exit();
}
