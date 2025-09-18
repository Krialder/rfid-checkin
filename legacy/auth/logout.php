<?php
/**
 * Enterprise Logout Handler
 * 
 * Secure logout process with comprehensive session cleanup,
 * security logging, and performance monitoring.
 */

require_once '../core/config.php';
require_once '../core/auth.php';

// Initialize enterprise components
$container = EnterpriseContainer::getInstance();
$errorHandler = $container->get('errorHandler');
$securityManager = $container->get('securityManager');
$performanceManager = $container->get('performanceManager');

// Start performance monitoring
$performanceManager->startTimer('logout_process');

try {
    // Log logout activity before session destruction
    $user = Auth::getCurrentUser();
    if ($user) {
        $errorHandler->log('User logout initiated', null, 'INFO', [
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }
    
    // Clear remember me cookie if it exists
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
    
    // Perform secure logout
    Auth::logout();
    
    // Record performance metrics
    $performanceManager->recordMetric('logout_success', 1);
    
    // Additional security: Clear any cached data
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    
    // Set security headers
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    
    // Redirect to login page with success message
    header('Location: ' . BASE_URL . '/auth/login.php?success=' . urlencode('You have been successfully logged out'));
    exit();
    
} catch (Exception $e) {
    // Log error
    $errorHandler->log('Logout error', $e, 'ERROR');
    
    // Record error metrics
    $performanceManager->recordMetric('logout_error', 1);
    
    // Redirect with error message
    header('Location: ' . BASE_URL . '/auth/login.php?error=' . urlencode('Logout failed. Please close your browser.'));
    exit();
    
} finally {
    // Complete performance monitoring
    $performanceManager->endTimer('logout_process');
}
?>
