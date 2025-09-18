<?php
/**
 * Login Process Handler - Enterprise Security Integration
 * 
 * Handles user authentication with comprehensive security validation,
 * rate limiting, audit logging, and enterprise component integration.
 * Implements modern security practices and error handling.
 */

require_once '../core/config.php';
require_once '../core/auth.php';

// Initialize enterprise components
$container = EnterpriseContainer::getInstance();
$errorHandler = $container->get('errorHandler');
$securityManager = $container->get('securityManager');
$performanceManager = $container->get('performanceManager');

// Start performance monitoring
$performanceManager->startTimer('login_process');

try {
    // Redirect if already logged in
    if (Auth::isLoggedIn()) {
        header('Location: ' . BASE_URL . '/frontend/dashboard.php');
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        if (!$securityManager->validateCSRFToken($_POST['_token'] ?? '')) {
            throw new Exception('Invalid security token. Please try again.');
        }
        
        // Validate and sanitize input
        $email = $securityManager->validateInput($_POST['email'] ?? '', 'email');
        $password = $securityManager->validateInput($_POST['password'] ?? '', 'password');
        $remember_me = isset($_POST['remember_me']);
        
        if (!$email || !$password) {
            throw new Exception('Please provide valid email and password.');
        }
        
        // Check rate limiting specifically for this email
        if (!$securityManager->checkRateLimit('login', $email)) {
            $errorHandler->log('Login rate limit exceeded', null, 'WARNING', [
                'email' => $email,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            header('Location: login.php?error=' . urlencode('Too many login attempts. Please try again in 5 minutes.'));
            exit();
        }
        
        // Attempt login with enterprise authentication
        $result = Auth::login($email, $password);
        
        if ($result['success']) {
            // Successful login - handle remember me functionality
            if ($remember_me) {
                $rememberToken = $securityManager->generateSecureToken();
                setcookie('remember_token', 
                    $rememberToken, 
                    time() + (30 * 24 * 60 * 60), // 30 days
                    '/', 
                    '', 
                    isset($_SERVER['HTTPS']), // secure flag
                    true, // httponly
                    'Strict' // samesite
                );
                
                // Store token hash in session for validation
                $_SESSION['remember_token_hash'] = hash('sha256', $rememberToken);
            }
            
            // Record performance metrics
            $performanceManager->recordMetric('successful_login', 1);
            
            // Set security headers
            header('X-Frame-Options: DENY');
            header('X-Content-Type-Options: nosniff');
            header('X-XSS-Protection: 1; mode=block');
            
            // Redirect to appropriate dashboard
            $redirectUrl = $result['redirect'] ?? BASE_URL . '/frontend/dashboard.php';
            header('Location: ' . $redirectUrl . '?success=' . urlencode('Welcome back!'));
            exit();
            
        } else {
            // Failed login
            $performanceManager->recordMetric('failed_login', 1);
            throw new Exception($result['error'] ?? 'Invalid credentials. Please try again.');
        }
        
    } else {
        // Invalid request method
        header('Location: login.php');
        exit();
    }
    
} catch (Exception $e) {
    // Enterprise error handling
    $errorHandler->log('Login process error', $e, 'ERROR', [
        'email' => $email ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    // Record error metrics
    $performanceManager->recordMetric('login_error', 1);
    
    // Redirect with error message
    $errorMessage = $e->getMessage();
    $emailParam = isset($email) ? '&email=' . urlencode($email) : '';
    header('Location: login.php?error=' . urlencode($errorMessage) . $emailParam);
    exit();
    
} finally {
    // Complete performance monitoring
    $performanceManager->endTimer('login_process');
}
?>
