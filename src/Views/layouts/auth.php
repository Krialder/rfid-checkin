<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- Page Meta -->
    <title><?= $renderer->helper('e', $title ?? 'Login - RFID Check-in System') ?></title>
    <meta name="description" content="<?= $renderer->helper('e', $description ?? 'Secure login to RFID check-in system') ?>">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Security Headers -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self';">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?= $renderer->helper('csrf_token') ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= $renderer->helper('asset', 'images/favicon.ico') ?>">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?= $renderer->helper('asset', 'css/app.css') ?>">
    <link rel="stylesheet" href="<?= $renderer->helper('asset', 'css/auth.css') ?>">
    
    <!-- Custom Page Styles -->
    <?= $renderer->yieldSection('styles') ?>
</head>
<body class="auth-layout <?= $renderer->helper('e', $bodyClass ?? '') ?>">
    
    <!-- Skip Navigation -->
    <a href="#main-content" class="skip-nav">Skip to main content</a>
    
    <!-- Authentication Container -->
    <div class="auth-container">
        
        <!-- Header -->
        <header class="auth-header">
            <div class="auth-logo">
                <img src="<?= $renderer->helper('asset', 'images/logo.svg') ?>" alt="RFID Check-in System" class="logo">
                <h1 class="logo-text">RFID Check-in</h1>
            </div>
        </header>
        
        <!-- Main Content -->
        <main id="main-content" class="auth-main" role="main">
            
            <!-- Flash Messages -->
            <?php $flashMessages = $renderer->helper('flash'); ?>
            <?php if (!empty($flashMessages)): ?>
                <?= $renderer->include('partials.flash-messages', ['messages' => $flashMessages]) ?>
            <?php endif; ?>
            
            <!-- Authentication Card -->
            <div class="auth-card">
                <?= $content ?>
            </div>
            
        </main>
        
        <!-- Footer -->
        <footer class="auth-footer">
            <p class="auth-footer-text">
                &copy; <?= date('Y') ?> RFID Check-in System. All rights reserved.
            </p>
            <div class="auth-footer-links">
                <a href="/help" class="auth-footer-link">Help</a>
                <a href="/privacy" class="auth-footer-link">Privacy</a>
                <a href="/terms" class="auth-footer-link">Terms</a>
            </div>
        </footer>
        
    </div>
    
    <!-- JavaScript -->
    <script src="<?= $renderer->helper('asset', 'js/core/utils.js') ?>"></script>
    <script src="<?= $renderer->helper('asset', 'js/auth/auth.js') ?>"></script>
    
    <!-- Application Configuration -->
    <script>
        window.App = {
            baseUrl: <?= $renderer->helper('json', $renderer->helper('url')) ?>,
            csrfToken: <?= $renderer->helper('json', $renderer->helper('csrf_token')) ?>,
            isAuthenticated: false
        };
        
        // Initialize CSRF for forms
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                if (!form.querySelector('input[name="_token"]')) {
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_token';
                    csrfInput.value = window.App.csrfToken;
                    form.appendChild(csrfInput);
                }
            });
        });
    </script>
    
    <!-- Custom Page Scripts -->
    <?= $renderer->yieldSection('scripts') ?>
    
</body>
</html>
