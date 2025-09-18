<?php
/**
 * Enterprise Password Reset Request Handler
 * 
 * Secure password reset functionality with enterprise security,
 * audit logging, and performance monitoring.
 */

require_once '../core/config.php';
require_once '../core/auth.php';

// Initialize enterprise components
$container = EnterpriseContainer::getInstance();
$errorHandler = $container->get('errorHandler');
$securityManager = $container->get('securityManager');
$performanceManager = $container->get('performanceManager');
$assetOptimizer = $container->get('assetOptimizer');

// Start performance monitoring
$performanceManager->startTimer('password_reset_page');

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!$securityManager->validateCSRFToken($_POST['_token'] ?? '')) {
            throw new Exception('Invalid security token. Please refresh and try again.');
        }
        
        // Validate and sanitize input
        $email = $securityManager->validateInput($_POST['email'] ?? '', 'email');
        
        if (!$email) {
            throw new Exception('Please enter a valid email address.');
        }
        
        // Check rate limiting for password reset requests
        if (!$securityManager->checkRateLimit('password_reset', $email)) {
            $errorHandler->log('Password reset rate limit exceeded', null, 'WARNING', [
                'email' => $email,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            throw new Exception('Too many password reset requests. Please try again in 5 minutes.');
        }
        
        // Use Auth class method for password reset
        $result = Auth::generatePasswordResetToken($email);
        
        if ($result['success']) {
            // Log successful reset request
            $errorHandler->log('Password reset token generated', null, 'INFO', [
                'email' => $email,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            // Record performance metrics
            $performanceManager->recordMetric('password_reset_request', 1);
            
            $message = 'Password reset instructions have been sent to your email address if an account exists.';
            $messageType = 'success';
        } else {
            // Always show success message for security (don't reveal if email exists)
            $message = 'Password reset instructions have been sent to your email address if an account exists.';
            $messageType = 'success';
        }
        
    } catch (Exception $e) {
        $errorHandler->log('Password reset error', $e, 'ERROR', [
            'email' => $email ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Generate CSRF token
$csrfToken = $securityManager->generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - RFID Check-in System</title>
    
    <!-- Enterprise asset optimization -->
    <?php
    echo $assetOptimizer->loadCSS(['main.css', 'forms.css']);
    ?>
    
    <!-- Security headers -->
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    
    <style>
        .forgot-password-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 2rem;
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow);
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .header h1 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .header p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        .message {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .message.success {
            background: var(--success-bg, #d4edda);
            color: var(--success-text, #155724);
            border: 1px solid var(--success-border, #c3e6cb);
        }
        
        .message.error {
            background: var(--error-bg, #f8d7da);
            color: var(--error-text, #721c24);
            border: 1px solid var(--error-border, #f5c6cb);
        }
        
        .security-notice {
            background: var(--info-bg, #e3f2fd);
            color: var(--info-text, #0d47a1);
            border: 1px solid var(--info-border, #bbdefb);
            padding: 1rem;
            border-radius: 6px;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }
        
        .security-notice h4 {
            margin: 0 0 0.5rem 0;
            color: var(--info-text, #0d47a1);
        }
        
        @media (max-width: 480px) {
            .forgot-password-container {
                margin: 20px;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-password-container">
        <div class="header">
            <h1>Forgot Password</h1>
            <p>Enter your email address to receive password reset instructions</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $securityManager->sanitizeOutput($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($messageType !== 'success'): ?>
            <form method="POST" action="" id="resetForm">
                <!-- CSRF Protection -->
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        required 
                        maxlength="255"
                        pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                        placeholder="Enter your email address"
                        value="<?php echo isset($_POST['email']) ? $securityManager->sanitizeOutput($_POST['email']) : ''; ?>"
                        autocomplete="email"
                        aria-describedby="email-error"
                    >
                    <div id="email-error" class="field-error" role="alert" aria-live="polite"></div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full" id="submitBtn">
                    <span class="btn-text">Send Reset Instructions</span>
                    <span class="btn-loading" style="display: none;">
                        <span class="spinner"></span> Sending...
                    </span>
                </button>
            </form>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="login.php">&larr; Back to Login</a>
        </div>
        
        <div class="security-notice">
            <h4>🔒 Security Notice</h4>
            <ul style="margin: 0; padding-left: 1.2rem;">
                <li>Reset links expire after 1 hour</li>
                <li>Only one active reset link per account</li>
                <li>Rate limiting prevents abuse</li>
                <li>Check your spam folder if email doesn't arrive</li>
                <li>Contact support if you continue having issues</li>
            </ul>
        </div>
    </div>
    
    <!-- Enhanced JavaScript with enterprise security -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resetForm');
            const emailInput = document.getElementById('email');
            const submitBtn = document.getElementById('submitBtn');
            let submitted = false;
            
            if (emailInput) {
                emailInput.focus();
            }
            
            // Enhanced email validation
            function validateEmail(email) {
                const emailRegex = /^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i;
                return emailRegex.test(email) && email.length <= 255;
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
            if (emailInput) {
                emailInput.addEventListener('blur', function() {
                    const email = this.value.trim();
                    if (email && !validateEmail(email)) {
                        showFieldError(this, 'Please enter a valid email address');
                    } else {
                        clearFieldError(this);
                    }
                });
                
                emailInput.addEventListener('input', function() {
                    clearFieldError(this);
                });
            }
            
            // Enhanced form submission
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (submitted) {
                        e.preventDefault();
                        return false;
                    }
                    
                    const email = emailInput.value.trim();
                    
                    if (!email) {
                        e.preventDefault();
                        showFieldError(emailInput, 'Email is required');
                        return false;
                    }
                    
                    if (!validateEmail(email)) {
                        e.preventDefault();
                        showFieldError(emailInput, 'Please enter a valid email address');
                        return false;
                    }
                    
                    // Show loading state
                    submitted = true;
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        const btnText = submitBtn.querySelector('.btn-text');
                        const btnLoading = submitBtn.querySelector('.btn-loading');
                        if (btnText && btnLoading) {
                            btnText.style.display = 'none';
                            btnLoading.style.display = 'inline-flex';
                        }
                    }
                    
                    // Reset after timeout as failsafe
                    setTimeout(function() {
                        submitted = false;
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            const btnText = submitBtn.querySelector('.btn-text');
                            const btnLoading = submitBtn.querySelector('.btn-loading');
                            if (btnText && btnLoading) {
                                btnText.style.display = 'inline';
                                btnLoading.style.display = 'none';
                            }
                        }
                    }, 15000);
                });
            }
        });
    </script>
    
    <?php
    // Complete performance monitoring
    $performanceManager->endTimer('password_reset_page');
    ?>
</body>
</html>
