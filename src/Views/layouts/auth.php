<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- Page Meta -->
    <title><?= htmlspecialchars($page_title ?? 'RFID Check-in System') ?></title>
    <meta name="description" content="<?= htmlspecialchars($page_description ?? 'Secure authentication for RFID Check-in System') ?>">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Security Headers -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self';">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
    
    <!-- Application Info -->
    <meta name="base-url" content="<?= htmlspecialchars($base_url) ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    
    <!-- Core CSS -->
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
    <link rel="stylesheet" href="/assets/css/forms.css">
    
    <!-- Page-specific CSS -->
    <?php if (!empty($assets['css'])): ?>
        <?php foreach ($assets['css'] as $css): ?>
            <link rel="stylesheet" href="/assets/css/<?= htmlspecialchars($css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Custom Page Styles -->
    <?php if (isset($custom_styles)): ?>
        <style><?= $custom_styles ?></style>
    <?php endif; ?>
</head>
<body class="auth-layout <?= htmlspecialchars($page_class ?? '') ?>">
    
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Loading...</p>
        </div>
    </div>
    
    <!-- Authentication Container -->
    <div class="auth-container">
        
        <!-- Background Pattern -->
        <div class="auth-background">
            <div class="pattern-overlay"></div>
        </div>
        
        <!-- Authentication Card -->
        <div class="auth-card">
            
            <!-- Application Logo/Branding -->
            <div class="auth-header">
                <div class="app-logo">
                    <img src="/assets/images/logo.png" alt="RFID Check-in System" class="logo-image">
                    <h1 class="app-title"><?= htmlspecialchars($app_name ?? 'RFID Check-in') ?></h1>
                </div>
                
                <?php if (!empty($page_subtitle)): ?>
                    <p class="auth-subtitle"><?= htmlspecialchars($page_subtitle) ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Flash Messages -->
            <?php if (!empty($flash_messages)): ?>
                <div class="flash-messages">
                    <?php foreach ($flash_messages as $type => $messages): ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="alert alert-<?= htmlspecialchars($type) ?>" role="alert">
                                <i class="alert-icon fas fa-<?= $type === 'error' ? 'exclamation-circle' : ($type === 'success' ? 'check-circle' : 'info-circle') ?>"></i>
                                <span class="alert-message"><?= htmlspecialchars($message) ?></span>
                                <button type="button" class="alert-close" aria-label="Close">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Authentication Content -->
            <div class="auth-content">
                <?= $content ?>
            </div>
            
            <!-- Authentication Footer -->
            <div class="auth-footer">
                
                <!-- Navigation Links -->
                <div class="auth-links">
                    <?php if (isset($show_register_link) && $show_register_link): ?>
                        <a href="/auth/register" class="auth-link">Create Account</a>
                    <?php endif; ?>
                    
                    <?php if (isset($show_login_link) && $show_login_link): ?>
                        <a href="/auth/login" class="auth-link">Sign In</a>
                    <?php endif; ?>
                    
                    <?php if (isset($show_forgot_link) && $show_forgot_link): ?>
                        <a href="/auth/forgot-password" class="auth-link">Forgot Password?</a>
                    <?php endif; ?>
                </div>
                
                <!-- Security Notice -->
                <div class="security-notice">
                    <i class="fas fa-shield-alt"></i>
                    <span>This is a secure system. All activities are logged and monitored.</span>
                </div>
                
                <!-- Copyright/Version -->
                <div class="auth-meta">
                    <p class="copyright">© <?= date('Y') ?> RFID Check-in System</p>
                    <?php if (!empty($app_version)): ?>
                        <p class="version">Version <?= htmlspecialchars($app_version) ?></p>
                    <?php endif; ?>
                </div>
                
            </div>
            
        </div>
        
        <!-- System Status -->
        <div class="system-status">
            <div class="status-indicator status-<?= $system_status ?? 'online' ?>">
                <i class="fas fa-circle"></i>
                <span>System <?= ucfirst($system_status ?? 'Online') ?></span>
            </div>
        </div>
        
    </div>
    
    <!-- Notification Container -->
    <div id="notifications-container" class="notifications-container"></div>
    
    <!-- Core JavaScript -->
    <script src="/assets/js/core/utils.js"></script>
    <script src="/assets/js/core/ajax.js"></script>
    <script src="/assets/js/core/notifications.js"></script>
    
    <!-- Security JavaScript -->
    <script src="/assets/js/security/csrf.js"></script>
    
    <!-- Authentication JavaScript -->
    <script src="/assets/js/auth/auth-forms.js"></script>
    <script src="/assets/js/ui/forms.js"></script>
    
    <!-- Page-specific JavaScript -->
    <?php if (!empty($assets['js'])): ?>
        <?php foreach ($assets['js'] as $js): ?>
            <script src="/assets/js/<?= htmlspecialchars($js) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Application Configuration -->
    <script>
        window.App = {
            baseUrl: <?= json_encode($base_url) ?>,
            csrfToken: <?= json_encode($csrf_token) ?>,
            environment: <?= json_encode($environment ?? 'production') ?>,
            debugMode: <?= json_encode($debug_mode ?? false) ?>
        };
        
        // Initialize CSRF token for AJAX requests
        document.addEventListener('DOMContentLoaded', function() {
            // Set CSRF token for all AJAX requests
            if (typeof window.AjaxManager !== 'undefined') {
                window.AjaxManager.setDefaultHeader('X-CSRF-TOKEN', window.App.csrfToken);
            }
            
            // Add CSRF token to all forms
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                if (!form.querySelector('input[name="csrf_token"]')) {
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = 'csrf_token';
                    csrfInput.value = window.App.csrfToken;
                    form.appendChild(csrfInput);
                }
            });
            
            // Auto-hide flash messages
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const closeBtn = alert.querySelector('.alert-close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        alert.style.animation = 'slideOutUp 0.3s ease-out';
                        setTimeout(() => alert.remove(), 300);
                    });
                }
                
                // Auto-hide success messages after 5 seconds
                if (alert.classList.contains('alert-success')) {
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.style.animation = 'slideOutUp 0.3s ease-out';
                            setTimeout(() => alert.remove(), 300);
                        }
                    }, 5000);
                }
            });
            
            // Enhanced form validation
            const authForms = document.querySelectorAll('form');
            authForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                        
                        // Re-enable button after 5 seconds as fallback
                        setTimeout(() => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = submitBtn.dataset.originalText || 'Submit';
                        }, 5000);
                    }
                });
            });
            
            // Store original button text
            const submitButtons = document.querySelectorAll('button[type="submit"]');
            submitButtons.forEach(btn => {
                btn.dataset.originalText = btn.innerHTML;
            });
        });
    </script>
    
    <!-- Custom Page Scripts -->
    <?php if (isset($custom_scripts)): ?>
        <script><?= $custom_scripts ?></script>
    <?php endif; ?>
    
    <!-- Error tracking -->
    <script>
        window.addEventListener('error', function(e) {
            console.error('JavaScript Error:', e.error);
            
            // Send critical auth errors to logging service
            if (typeof window.ErrorLogger !== 'undefined') {
                window.ErrorLogger.log(e.error, 'auth');
            }
        });
    </script>
    
    <!-- Performance Monitoring -->
    <script>
        window.addEventListener('load', function() {
            if ('performance' in window) {
                const loadTime = performance.now();
                
                // Log slow auth pages in debug mode
                if (window.App.debugMode && loadTime > 1500) {
                    console.warn('Slow auth page load detected:', loadTime + 'ms');
                }
            }
        });
    </script>
    
</body>
</html>
