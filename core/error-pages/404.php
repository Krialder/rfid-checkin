<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - RFID Check-in System</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <style>
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            padding: var(--space-8);
        }
        
        .error-card {
            background: var(--card-bg);
            padding: var(--space-12);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            text-align: center;
            max-width: 600px;
            width: 100%;
        }
        
        .error-icon {
            font-size: 6rem;
            margin-bottom: var(--space-6);
            opacity: 0.8;
        }
        
        .error-title {
            font-size: var(--font-size-5xl);
            font-weight: var(--font-weight-extrabold);
            color: var(--primary-color);
            margin-bottom: var(--space-2);
        }
        
        .error-subtitle {
            font-size: var(--font-size-xl);
            color: var(--text-secondary);
            margin-bottom: var(--space-6);
        }
        
        .error-message {
            font-size: var(--font-size-lg);
            color: var(--text-secondary);
            margin-bottom: var(--space-8);
            line-height: var(--line-height-relaxed);
        }
        
        .error-actions {
            display: flex;
            gap: var(--space-4);
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .popular-links {
            margin-top: var(--space-8);
            padding-top: var(--space-6);
            border-top: 1px solid var(--border-color);
        }
        
        .popular-links h3 {
            font-size: var(--font-size-lg);
            margin-bottom: var(--space-4);
            color: var(--text-primary);
        }
        
        .links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-3);
            text-align: left;
        }
        
        .link-item {
            padding: var(--space-3);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            transition: all var(--transition-fast);
            text-decoration: none;
            color: var(--text-primary);
        }
        
        .link-item:hover {
            background-color: var(--hover-bg);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
            text-decoration: none;
        }
        
        .link-title {
            font-weight: var(--font-weight-semibold);
            margin-bottom: var(--space-1);
        }
        
        .link-desc {
            font-size: var(--font-size-sm);
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-card">
            <div class="error-icon">🔍</div>
            
            <h1 class="error-title">404</h1>
            <h2 class="error-subtitle">Page Not Found</h2>
            
            <p class="error-message">
                Sorry, we couldn't find the page you're looking for. 
                The page might have been moved, deleted, or you might have mistyped the URL.
            </p>
            
            <div class="error-actions">
                <a href="javascript:history.back()" class="btn btn-secondary">
                    ← Go Back
                </a>
                <a href="/" class="btn btn-primary">
                    🏠 Return Home
                </a>
                <a href="/frontend/dashboard.php" class="btn btn-ghost">
                    📊 Dashboard
                </a>
            </div>
            
            <div class="popular-links">
                <h3>🌟 Popular Pages</h3>
                <div class="links-grid">
                    <a href="/frontend/dashboard.php" class="link-item">
                        <div class="link-title">📊 Dashboard</div>
                        <div class="link-desc">View your check-in statistics and recent activity</div>
                    </a>
                    
                    <a href="/frontend/events.php" class="link-item">
                        <div class="link-title">📅 Events</div>
                        <div class="link-desc">Browse and manage upcoming events</div>
                    </a>
                    
                    <a href="/frontend/check-ins.php" class="link-item">
                        <div class="link-title">📝 My Check-ins</div>
                        <div class="link-desc">Review your check-in history and records</div>
                    </a>
                    
                    <a href="/frontend/profile.php" class="link-item">
                        <div class="link-title">👤 Profile</div>
                        <div class="link-desc">Update your personal information and settings</div>
                    </a>
                    
                    <a href="/frontend/analytics.php" class="link-item">
                        <div class="link-title">📈 Analytics</div>
                        <div class="link-desc">Analyze your attendance patterns and trends</div>
                    </a>
                    
                    <a href="/frontend/help.php" class="link-item">
                        <div class="link-title">❓ Help & Support</div>
                        <div class="link-desc">Get assistance and find answers to common questions</div>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Track 404 errors for analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', 'page_not_found', {
                'page_path': window.location.pathname,
                'referrer': document.referrer
            });
        }
        
        // Report to error tracking service if available
        if (typeof Sentry !== 'undefined') {
            Sentry.captureMessage('404 Page Not Found: ' + window.location.pathname, 'info');
        }
    </script>
</body>
</html>
