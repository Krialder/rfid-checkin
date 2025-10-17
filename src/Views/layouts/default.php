<!DOCTYPE html>
<html lang="en" data-theme="<?= $renderer->helper('user')['theme'] ?? 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- Page Meta -->
    <title><?= $renderer->helper('e', $title ?? 'RFID Check-in System') ?></title>
    <meta name="description" content="<?= $renderer->helper('e', $description ?? 'Modern RFID attendance tracking system') ?>">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Security Headers -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self';">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?= $renderer->helper('csrf_token') ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= $renderer->helper('asset', 'images/favicon.ico') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= $renderer->helper('asset', 'images/apple-touch-icon.png') ?>">
    
    <!-- Core CSS -->
    <link rel="stylesheet" href="<?= $renderer->helper('asset', 'css/app.css') ?>">
    <link rel="stylesheet" href="<?= $renderer->helper('asset', 'css/components.css') ?>">
    
    <!-- Page-specific CSS -->
    <?php if (isset($cssFiles)): ?>
        <?php foreach ($cssFiles as $css): ?>
            <link rel="stylesheet" href="<?= $renderer->helper('asset', 'css/' . $css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Custom Page Styles -->
    <?= $renderer->yieldSection('styles') ?>
</head>
<body class="<?= $renderer->helper('e', $bodyClass ?? '') ?>" data-user-role="<?= $renderer->helper('e', ($renderer->helper('user')['role'] ?? 'guest')) ?>">
    
    <!-- Skip Navigation -->
    <a href="#main-content" class="skip-nav">Skip to main content</a>
    
    <!-- Application Header -->
    <?php if ($renderer->helper('auth')): ?>
        <?= $renderer->include('partials.header') ?>
    <?php endif; ?>
    
    <!-- Main Application Container -->
    <div class="app-container">
        
        <!-- Sidebar Navigation -->
        <?php if ($renderer->helper('auth')): ?>
            <?= $renderer->include('partials.sidebar') ?>
        <?php endif; ?>
        
        <!-- Main Content Area -->
        <main id="main-content" class="main-content" role="main">
            
            <!-- Breadcrumbs -->
            <?php if (isset($breadcrumbs) && $renderer->helper('auth')): ?>
                <?= $renderer->include('partials.breadcrumbs', ['breadcrumbs' => $breadcrumbs]) ?>
            <?php endif; ?>
            
            <!-- Flash Messages -->
            <?php $flashMessages = $renderer->helper('flash'); ?>
            <?php if (!empty($flashMessages)): ?>
                <?= $renderer->include('partials.flash-messages', ['messages' => $flashMessages]) ?>
            <?php endif; ?>
            
            <!-- Page Content -->
            <div class="page-content">
                <?= $content ?>
            </div>
            
        </main>
        
    </div>
    
    <!-- Application Footer -->
    <?php if ($renderer->helper('auth')): ?>
        <?= $renderer->include('partials.footer') ?>
    <?php endif; ?>
    
    <!-- Modals Container -->
    <div id="modals-container"></div>
    
    <!-- Notification Container -->
    <div id="notifications-container" class="notifications-container"></div>
    
    <!-- Core JavaScript -->
    <script src="<?= $renderer->helper('asset', 'js/core/app.js') ?>"></script>
    <script src="<?= $renderer->helper('asset', 'js/core/utils.js') ?>"></script>
    <script src="<?= $renderer->helper('asset', 'js/core/api.js') ?>"></script>
    
    <!-- Page-specific JavaScript -->
    <?php if (isset($jsFiles)): ?>
        <?php foreach ($jsFiles as $js): ?>
            <script src="<?= $renderer->helper('asset', 'js/' . $js) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Application Configuration -->
    <script>
        window.App = {
            baseUrl: <?= $renderer->helper('json', $renderer->helper('url')) ?>,
            csrfToken: <?= $renderer->helper('json', $renderer->helper('csrf_token')) ?>,
            currentUser: <?= $renderer->helper('json', $renderer->helper('user')) ?>,
            isAuthenticated: <?= $renderer->helper('json', $renderer->helper('auth')) ?>
        };
    </script>
    
    <!-- Custom Page Scripts -->
    <?= $renderer->yieldSection('scripts') ?>
    
</body>
</html>
