<?php
/**
 * Enterprise Login Page with Enhanced Security
 * 
 * Modern login interface with comprehensive security features including
 * CSRF protection, rate limiting, and enterprise component integration.
 */

require_once '../core/config.php';
require_once '../core/auth.php';

// Initialize enterprise components
$container = EnterpriseContainer::getInstance();
$securityManager = $container->get('securityManager');
$errorHandler = $container->get('errorHandler');
$performanceManager = $container->get('performanceManager');
$assetOptimizer = $container->get('assetOptimizer');

// Start performance monitoring
$performanceManager->startTimer('login_page_load');

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    header('Location: ' . BASE_URL . '/frontend/dashboard.php');
    exit();
}

// Generate CSRF token
$csrfToken = $securityManager->generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RFID Check-in System</title>
    
    <!-- Optimized CSS loading with enterprise asset management -->
    <?php
    echo $assetOptimizer->loadCSS([
        'main.css',
        'forms.css'
    ]);
    ?>
    
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Secure login to RFID Check-in System">
    
    <!-- Security headers -->
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header">
            <h1>📟 RFID Check-in System</h1>
            <p>Please sign in to your account</p>
        </div>
        
        <form id="loginForm" method="POST" action="login-process.php" class="login-form">
            <!-- Enhanced CSRF Protection -->
            <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error" role="alert" aria-live="polite">
                    <?php echo $securityManager->sanitizeOutput($_GET['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success" role="alert" aria-live="polite">
                    <?php echo $securityManager->sanitizeOutput($_GET['success']); ?>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       required 
                       autocomplete="email"
                       aria-describedby="email-error"
                       maxlength="255"
                       pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                       value="<?php echo isset($_GET['email']) ? $securityManager->sanitizeOutput($_GET['email']) : ''; ?>">
                <div id="email-error" class="field-error" role="alert" aria-live="polite"></div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       required 
                       autocomplete="current-password"
                       aria-describedby="password-error"
                       minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                       maxlength="255">
                <div id="password-error" class="field-error" role="alert" aria-live="polite"></div>
            </div>
            
            <div class="form-group checkbox-group">
                <label>
                    <input type="checkbox" name="remember_me" value="1" aria-describedby="remember-help">
                    Remember me for 30 days
                </label>
                <div id="remember-help" class="field-help">
                    Keep me signed in on this device (not recommended on shared computers)
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-full" id="loginButton">
                <span class="btn-text">Sign In</span>
                <span class="btn-loading" style="display: none;">
                    <span class="spinner"></span> Signing In...
                </span>
            </button>
            
            <?php if (DEBUG_MODE): ?>
            <div class="debug-info">
                <strong>Development Mode:</strong><br>
                Enterprise components loaded successfully<br>
                CSRF Token: <?php echo substr($csrfToken, 0, 8); ?>...<br>
                Performance monitoring active
            </div>
            <?php endif; ?>
        </form>
        
        
        <!-- Enhanced JavaScript with enterprise security -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('loginForm');
                const button = document.getElementById('loginButton');
                const btnText = button.querySelector('.btn-text');
                const btnLoading = button.querySelector('.btn-loading');
                const emailField = document.getElementById('email');
                const passwordField = document.getElementById('password');
                let submitted = false;
                
                // Enhanced validation with enterprise security patterns
                function validateEmail(email) {
                    const emailRegex = /^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i;
                    return emailRegex.test(email) && email.length <= 255;
                }
                
                function validatePassword(password) {
                    return password.length >= <?php echo PASSWORD_MIN_LENGTH; ?> && password.length <= 255;
                }
                
                function showFieldError(field, message) {
                    const errorDiv = document.getElementById(field.id + '-error');
                    if (errorDiv) {
                        errorDiv.textContent = message;
                        errorDiv.style.display = 'block';
                        field.setAttribute('aria-invalid', 'true');
                        field.classList.add('error');
                    }
                }
                
                function clearFieldError(field) {
                    const errorDiv = document.getElementById(field.id + '-error');
                    if (errorDiv) {
                        errorDiv.textContent = '';
                        errorDiv.style.display = 'none';
                        field.setAttribute('aria-invalid', 'false');
                        field.classList.remove('error');
                    }
                }
                
                // Real-time validation
                emailField.addEventListener('blur', function() {
                    const email = this.value.trim();
                    if (email && !validateEmail(email)) {
                        showFieldError(this, 'Please enter a valid email address');
                    } else {
                        clearFieldError(this);
                    }
                });
                
                passwordField.addEventListener('blur', function() {
                    const password = this.value;
                    if (password && !validatePassword(password)) {
                        showFieldError(this, 'Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long');
                    } else {
                        clearFieldError(this);
                    }
                });
                
                // Clear errors on input
                [emailField, passwordField].forEach(field => {
                    field.addEventListener('input', function() {
                        clearFieldError(this);
                    });
                });
                
                // Enhanced form submission with security checks
                form.addEventListener('submit', function(e) {
                    if (submitted) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Client-side validation
                    let isValid = true;
                    const email = emailField.value.trim();
                    const password = passwordField.value;
                    
                    if (!email) {
                        showFieldError(emailField, 'Email is required');
                        isValid = false;
                    } else if (!validateEmail(email)) {
                        showFieldError(emailField, 'Please enter a valid email address');
                        isValid = false;
                    }
                    
                    if (!password) {
                        showFieldError(passwordField, 'Password is required');
                        isValid = false;
                    } else if (!validatePassword(password)) {
                        showFieldError(passwordField, 'Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long');
                        isValid = false;
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Show loading state
                    submitted = true;
                    button.disabled = true;
                    btnText.style.display = 'none';
                    btnLoading.style.display = 'inline-flex';
                    
                    // Reset after timeout as failsafe
                    setTimeout(function() {
                        submitted = false;
                        button.disabled = false;
                        btnText.style.display = 'inline';
                        btnLoading.style.display = 'none';
                    }, 15000);
                });
                
                // Security: Clear form on page unload
                window.addEventListener('beforeunload', function() {
                    passwordField.value = '';
                });
                
                // Reset on pageshow (back button)
                window.addEventListener('pageshow', function(e) {
                    if (e.persisted) {
                        submitted = false;
                        button.disabled = false;
                        btnText.style.display = 'inline';
                        btnLoading.style.display = 'none';
                        passwordField.value = '';
                    }
                });
                
                // Focus management for accessibility
                if (emailField.value === '') {
                    emailField.focus();
                } else {
                    passwordField.focus();
                }
            });
        </script>
        
        <div class="login-footer">
            <a href="forgot-password.php">Forgot your password?</a>
            <div class="divider"></div>
            <p>Don't have an account? Contact your administrator.</p>
        </div>
    </div>
    
    <?php
    // Load optimized JavaScript
    echo $assetOptimizer->loadJS(['login.js']);
    
    // Complete performance monitoring
    $performanceManager->endTimer('login_page_load');
    ?>
</body>
</html>
