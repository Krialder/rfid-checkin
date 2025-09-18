<!DOCTYPE html>
<html lang="en" data-theme="<?= $current_user['theme'] ?? 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- Page Meta -->
    <title><?= htmlspecialchars($page_title ?? $app_name) ?></title>
    <meta name="description" content="<?= htmlspecialchars($page_description ?? 'RFID Check-in System') ?>">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Security Headers -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self';">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
    
    <!-- Application Info -->
    <meta name="app-name" content="<?= htmlspecialchars($app_name) ?>">
    <meta name="app-version" content="<?= htmlspecialchars($app_version) ?>">
    <meta name="base-url" content="<?= htmlspecialchars($base_url) ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/apple-touch-icon.png">
    
    <!-- Core CSS -->
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/components.css">
    <link rel="stylesheet" href="/assets/css/utilities.css">
    
    <!-- Page-specific CSS -->
    <?php if (!empty($assets['css'])): ?>
        <?php foreach ($assets['css'] as $css): ?>
            <link rel="stylesheet" href="/assets/css/<?= htmlspecialchars($css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Theme CSS -->
    <link rel="stylesheet" href="/assets/css/themes/<?= htmlspecialchars($current_user['theme'] ?? 'light') ?>.css">
    
    <!-- Custom Page Styles -->
    <?php if (isset($custom_styles)): ?>
        <style><?= $custom_styles ?></style>
    <?php endif; ?>
</head>
<body class="<?= htmlspecialchars($page_class ?? '') ?>" data-user-role="<?= htmlspecialchars($current_user['group_id'] ?? '3') ?>">
    
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Loading...</p>
        </div>
    </div>
    
    <!-- Skip Navigation -->
    <a href="#main-content" class="skip-nav">Skip to main content</a>
    
    <!-- Application Header -->
    <?php if ($is_authenticated): ?>
        <?php include __DIR__ . '/../partials/header.php'; ?>
    <?php endif; ?>
    
    <!-- Main Application Container -->
    <div class="app-container">
        
        <!-- Sidebar Navigation -->
        <?php if ($is_authenticated): ?>
            <?php include __DIR__ . '/../partials/sidebar.php'; ?>
        <?php endif; ?>
        
        <!-- Main Content Area -->
        <main id="main-content" class="main-content" role="main">
            
            <!-- Breadcrumbs -->
            <?php if (!empty($breadcrumbs) && $is_authenticated): ?>
                <?php include __DIR__ . '/../partials/breadcrumbs.php'; ?>
            <?php endif; ?>
            
            <!-- Flash Messages -->
            <?php if (!empty($flash_messages)): ?>
                <?php include __DIR__ . '/../partials/flash-messages.php'; ?>
            <?php endif; ?>
            
            <!-- Page Content -->
            <div class="page-content">
                <?= $content ?>
            </div>
            
        </main>
        
    </div>
    
    <!-- Application Footer -->
    <?php if ($is_authenticated): ?>
        <?php include __DIR__ . '/../partials/footer.php'; ?>
    <?php endif; ?>
    
    <!-- Modals Container -->
    <div id="modals-container"></div>
    
    <!-- Notification Container -->
    <div id="notifications-container" class="notifications-container"></div>
    
    <!-- Core JavaScript -->
    <script src="/assets/js/core/app.js"></script>
    <script src="/assets/js/core/utils.js"></script>
    <script src="/assets/js/core/ajax.js"></script>
    <script src="/assets/js/core/notifications.js"></script>
    
    <!-- Security JavaScript -->
    <script src="/assets/js/security/csrf.js"></script>
    
    <!-- UI JavaScript -->
    <script src="/assets/js/ui/modal.js"></script>
    <script src="/assets/js/ui/tooltips.js"></script>
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
            name: <?= json_encode($app_name) ?>,
            version: <?= json_encode($app_version) ?>,
            baseUrl: <?= json_encode($base_url) ?>,
            csrfToken: <?= json_encode($csrf_token) ?>,
            currentUser: <?= json_encode($current_user) ?>,
            isAuthenticated: <?= json_encode($is_authenticated) ?>,
            environment: <?= json_encode($environment) ?>,
            debugMode: <?= json_encode($debug_mode) ?>
        };
        
        // Initialize CSRF token for AJAX requests
        if (typeof window.App.csrfToken !== 'undefined') {
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
            });
        }
    </script>
    
    <!-- Custom Page Scripts -->
    <?php if (isset($custom_scripts)): ?>
        <script><?= $custom_scripts ?></script>
    <?php endif; ?>
    
    <!-- Google Analytics / Tracking (if configured) -->
    <?php if (!empty($tracking_id) && $environment === 'production'): ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($tracking_id) ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', <?= json_encode($tracking_id) ?>);
        </script>
    <?php endif; ?>
    
    <!-- Development Tools -->
    <?php if ($debug_mode): ?>
        <script src="/assets/js/debug/debug-panel.js"></script>
        <div id="debug-panel" class="debug-panel">
            <button id="debug-toggle" class="debug-toggle">🐛</button>
            <div class="debug-content">
                <h3>Debug Information</h3>
                <p><strong>Environment:</strong> <?= htmlspecialchars($environment) ?></p>
                <p><strong>User ID:</strong> <?= htmlspecialchars($current_user['user_id'] ?? 'Not logged in') ?></p>
                <p><strong>Page Load Time:</strong> <span id="page-load-time"></span></p>
                <p><strong>Memory Usage:</strong> <?= round(memory_get_peak_usage(true) / 1024 / 1024, 2) ?>MB</p>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const loadTime = (performance.now() / 1000).toFixed(3);
                document.getElementById('page-load-time').textContent = loadTime + 's';
            });
        </script>
    <?php endif; ?>
    
    <!-- Performance Monitoring -->
    <script>
        // Basic performance monitoring
        window.addEventListener('load', function() {
            if ('performance' in window) {
                const loadTime = performance.now();
                
                // Send performance data (if analytics enabled)
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'page_load_time', {
                        event_category: 'Performance',
                        value: Math.round(loadTime)
                    });
                }
                
                // Log slow pages in debug mode
                if (window.App.debugMode && loadTime > 2000) {
                    console.warn('Slow page load detected:', loadTime + 'ms');
                }
            }
        });
        
        // Error tracking
        window.addEventListener('error', function(e) {
            console.error('JavaScript Error:', e.error);
            
            // Send error to logging service (if configured)
            if (typeof window.ErrorLogger !== 'undefined') {
                window.ErrorLogger.log(e.error);
            }
        });
    </script>
    
</body>
</html>
