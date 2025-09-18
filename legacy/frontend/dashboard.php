<?php
/**
 * User Dashboard Interface
 * 
 * Dashboard providing overview of user activities with real-time statistics,
 * responsive design, and progressive web app capabilities.
 * 
 * @package RfidCheckin\Frontend
 * @author Kralder
 */

// Load configuration and components
require_once '../core/config.php';
require_once '../core/auth.php';

// Initialize components
$container = EnterpriseContainer::getInstance();
$errorHandler = $container->get('errorHandler');
$securityManager = $container->get('securityManager');
$performanceManager = $container->get('performanceManager');
$assetOptimizer = $container->get('assetOptimizer');

// Initialize repositories and services
$userRepository = new UserRepository();
$eventRepository = new EventRepository();
$checkinRepository = new CheckinRepository();
$dataService = new DataService();

// Start performance monitoring
$performanceManager->startTimer('dashboard_page_load');

try {
    // Enterprise authentication and session validation
    Auth::requireLogin();
    $user = Auth::getCurrentUser();
    
    if (!$user) {
        throw new Exception('Invalid user session');
    }
    
    // Enterprise security validation
    $securityManager->validateRequest($_SERVER);
    
    // Check dashboard access permissions
    if (!$securityManager->hasPermission($user, 'view_dashboard')) {
        $errorHandler->log('Unauthorized dashboard access attempt', null, 'WARNING', [
            'user_id' => $user['user_id'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        header('Location: ../auth/login.php?error=access_denied');
        exit;
    }
    
    $performanceManager->startTimer('dashboard_data_load');
    
    // Get comprehensive dashboard data using enterprise service layer
    $dashboardData = $dataService->getDashboardData($user['user_id']);
    
    // Get user permissions for feature visibility
    $userPermissions = [
        'manual_checkin' => $securityManager->hasPermission($user, 'manual_checkin'),
        'view_analytics' => $securityManager->hasPermission($user, 'view_analytics'),
        'manage_profile' => $securityManager->hasPermission($user, 'manage_profile'),
        'view_events' => $securityManager->hasPermission($user, 'view_events')
    ];
    
    // Generate CSRF token for form submissions
    $csrfToken = $securityManager->generateCsrfToken();
    
    $performanceManager->endTimer('dashboard_data_load');
    
    // Record dashboard access metrics
    $performanceManager->recordMetric('dashboard_access', 1);
    $performanceManager->recordMetric('dashboard_user_' . $user['role'], 1);

} catch (Exception $e) {
    // Enterprise error handling
    $errorHandler->log('Dashboard page error', $e, 'ERROR', [
        'user_id' => $user['user_id'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    // Redirect to login with error message
    header('Location: ../auth/login.php?error=system_error');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <title>Dashboard - Electronic Check-in System</title>
    
    <!-- Enterprise Security Headers -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' fonts.googleapis.com; font-src 'self' fonts.gstatic.com; connect-src 'self';">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    
    <!-- Progressive Web App Manifest -->
    <link rel="manifest" href="../assets/manifest.json">
    <meta name="theme-color" content="#2c3e50">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    
    <!-- SEO and Social Meta Tags -->
    <meta name="description" content="RFID Check-in System Dashboard - Track your attendance, view analytics, and manage your check-in activities">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- DNS Prefetch for Performance -->
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    
    <?php 
    // Generate optimized asset preloads and critical CSS
    echo $assetOptimizer->generatePreloads(['dashboard.css', 'main.css', 'navigation.css']);
    echo $assetOptimizer->inlineCriticalCSS(['main.css', 'navigation.css']);
    echo $assetOptimizer->loadCSS(['dashboard.css', 'forms.css', 'modal.css'], false);
    ?>
    
    <!-- Performance and Analytics -->
    <script>
        // Performance timing data for enterprise monitoring
        window.enterpriseConfig = {
            apiVersion: '4.0.0',
            csrfToken: '<?php echo $csrfToken; ?>',
            userId: <?php echo $user['user_id']; ?>,
            permissions: <?php echo json_encode($userPermissions); ?>,
            pageLoadStart: performance.now()
        };
    </script>
</head>
<body class="dashboard-page" data-user-role="<?php echo htmlspecialchars($user['role']); ?>">
    <!-- Skip to main content link for accessibility -->
    <a href="#main-content" class="skip-link">Skip to main content</a>
    
    <?php include '../includes/navigation.php'; ?>
    
    <main id="main-content" class="main-content" role="main">
        <!-- Dashboard Header -->
        <header class="dashboard-header">
            <div class="header-content">
                <h1 class="welcome-message">
                    Welcome back, 
                    <span class="user-name"><?php 
                        $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                        echo htmlspecialchars($fullName ?: $user['username'] ?? 'User', ENT_QUOTES, 'UTF-8'); 
                    ?>!</span>
                    <span class="wave-emoji" aria-label="waving hand">👋</span>
                </h1>
                <p class="subtitle">Here's what's happening with your check-ins</p>
                <div class="quick-stats-summary">
                    <span class="quick-stat">
                        <strong><?php echo number_format($dashboardData['stats']['total_checkins'] ?? 0); ?></strong> 
                        total check-ins
                    </span>
                    <span class="quick-stat">
                        <strong><?php echo number_format($dashboardData['stats']['this_month'] ?? 0); ?></strong> 
                        this month
                    </span>
                </div>
            </div>
        </header>
        
        <!-- Dashboard Grid Layout -->
        <div class="dashboard-grid" role="region" aria-label="Dashboard widgets">
            <!-- Enhanced Quick Stats Card -->
            <section class="card stats-card" id="quickStats" aria-labelledby="stats-heading">
                <header class="card-header">
                    <h2 id="stats-heading" class="card-title">
                        <span class="icon" aria-hidden="true">📊</span>
                        Quick Statistics
                    </h2>
                    <button class="card-action-btn" onclick="refreshStats()" aria-label="Refresh statistics">
                        <span class="refresh-icon">🔄</span>
                    </button>
                </header>
                <div class="card-content">
                    <div class="stats-grid" role="grid">
                        <div class="stat-item" role="gridcell">
                            <span class="stat-number" aria-describedby="total-checkins-desc">
                                <?php echo number_format($dashboardData['stats']['total_checkins'] ?? 0); ?>
                            </span>
                            <span class="stat-label" id="total-checkins-desc">Total Check-ins</span>
                            <span class="stat-trend <?php echo ($dashboardData['stats']['trend_total'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>">
                                <?php echo ($dashboardData['stats']['trend_total'] ?? 0) >= 0 ? '↗' : '↘'; ?>
                            </span>
                        </div>
                        <div class="stat-item" role="gridcell">
                            <span class="stat-number" aria-describedby="month-checkins-desc">
                                <?php echo number_format($dashboardData['stats']['this_month'] ?? 0); ?>
                            </span>
                            <span class="stat-label" id="month-checkins-desc">This Month</span>
                            <span class="stat-trend <?php echo ($dashboardData['stats']['trend_month'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>">
                                <?php echo ($dashboardData['stats']['trend_month'] ?? 0) >= 0 ? '↗' : '↘'; ?>
                            </span>
                        </div>
                        <div class="stat-item" role="gridcell">
                            <span class="stat-number" aria-describedby="avg-time-desc">
                                <?php echo $dashboardData['stats']['avg_time'] ?? '-'; ?>
                            </span>
                            <span class="stat-label" id="avg-time-desc">Avg. Session Time</span>
                        </div>
                        <div class="stat-item" role="gridcell">
                            <span class="stat-number" aria-describedby="unique-events-desc">
                                <?php echo number_format($dashboardData['stats']['unique_events'] ?? 0); ?>
                            </span>
                            <span class="stat-label" id="unique-events-desc">Unique Events</span>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Enhanced Recent Check-ins Card -->
            <section class="card recent-checkins-card" id="recentCheckins" aria-labelledby="recent-heading">
                <header class="card-header">
                    <h2 id="recent-heading" class="card-title">
                        <span class="icon" aria-hidden="true">🕒</span>
                        Recent Activity
                    </h2>
                    <a href="check-ins.php" class="card-action-link" aria-label="View all check-ins">
                        View All
                    </a>
                </header>
                <div class="card-content">
                    <div class="recent-checkins" role="list">
                        <?php if (empty($dashboardData['recent_checkins'])): ?>
                            <div class="empty-state" role="listitem">
                                <span class="empty-icon" aria-hidden="true">📭</span>
                                <p class="empty-message">No recent check-ins found.</p>
                                <?php if ($userPermissions['manual_checkin']): ?>
                                    <button class="btn btn-outline-primary" onclick="showCheckInModal()">
                                        Start Your First Check-in
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?php foreach (array_slice($dashboardData['recent_checkins'], 0, 5) as $index => $checkin): ?>
                                <article class="checkin-item" role="listitem" tabindex="0">
                                    <div class="checkin-info">
                                        <h3 class="checkin-event-name">
                                            <?php echo htmlspecialchars($checkin['event_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </h3>
                                        <div class="checkin-details">
                                            <time datetime="<?php echo date('c', strtotime($checkin['created_at'])); ?>">
                                                <?php echo date('M j, Y', strtotime($checkin['created_at'])); ?>
                                            </time>
                                            <span class="badge badge-<?php echo $checkin['checkout_time'] ? 'success' : 'warning'; ?>"
                                                  aria-label="Check-in status">
                                                <?php echo $checkin['checkout_time'] ? 'Completed' : 'Active'; ?>
                                            </span>
                                            <?php if ($checkin['location']): ?>
                                                <span class="location" aria-label="Location">
                                                    📍 <?php echo htmlspecialchars($checkin['location'], ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="checkin-time">
                                        <time datetime="<?php echo date('c', strtotime($checkin['created_at'])); ?>">
                                            <?php echo date('g:i A', strtotime($checkin['created_at'])); ?>
                                        </time>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            
            <!-- Enhanced Upcoming Events Card -->
            <section class="card upcoming-events-card" id="upcomingEvents" aria-labelledby="upcoming-heading">
                <header class="card-header">
                    <h2 id="upcoming-heading" class="card-title">
                        <span class="icon" aria-hidden="true">📅</span>
                        Upcoming Events
                    </h2>
                    <?php if ($userPermissions['view_events']): ?>
                        <a href="events.php" class="card-action-link" aria-label="View all events">
                            View All
                        </a>
                    <?php endif; ?>
                </header>
                <div class="card-content">
                    <div class="upcoming-events" role="list">
                        <?php if (empty($dashboardData['upcoming_events'])): ?>
                            <div class="empty-state" role="listitem">
                                <span class="empty-icon" aria-hidden="true">📅</span>
                                <p class="empty-message">No upcoming events scheduled.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach (array_slice($dashboardData['upcoming_events'], 0, 4) as $event): ?>
                                <article class="event-item" role="listitem" tabindex="0">
                                    <div class="event-header">
                                        <h3 class="event-name">
                                            <?php echo htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </h3>
                                        <time class="event-date" datetime="<?php echo $event['start_date']; ?>">
                                            <?php echo date('M j', strtotime($event['start_date'])); ?>
                                        </time>
                                    </div>
                                    <div class="event-details">
                                        <time datetime="<?php echo $event['start_time']; ?>">
                                            <?php echo date('g:i A', strtotime($event['start_time'])); ?>
                                        </time>
                                        <?php if ($event['location']): ?>
                                            <span class="event-location" aria-label="Location">
                                                📍 <?php echo htmlspecialchars($event['location'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($event['capacity']): ?>
                                            <span class="event-capacity" aria-label="Capacity">
                                                👥 <?php echo $event['current_participants']; ?>/<?php echo $event['capacity']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($userPermissions['manual_checkin'] && $event['is_available']): ?>
                                        <button class="btn btn-sm btn-outline-primary quick-checkin-btn" 
                                                onclick="quickCheckIn(<?php echo $event['event_id']; ?>)"
                                                aria-label="Quick check-in to <?php echo htmlspecialchars($event['name']); ?>">
                                            Quick Check-in
                                        </button>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            
            <!-- Enhanced User Groups Card -->
            <section class="card user-groups-card" id="userGroups" aria-labelledby="groups-heading">
                <header class="card-header">
                    <h2 id="groups-heading" class="card-title">
                        <span class="icon" aria-hidden="true">🏢</span>
                        My Groups
                    </h2>
                    <?php if ($userPermissions['manage_profile']): ?>
                        <a href="profile.php" class="card-action-link" aria-label="Manage groups">
                            Manage
                        </a>
                    <?php endif; ?>
                </header>
                <div class="card-content">
                    <div class="user-groups" role="list">
                        <?php if (empty($dashboardData['user_groups'])): ?>
                            <div class="empty-state" role="listitem">
                                <span class="empty-icon" aria-hidden="true">👥</span>
                                <p class="empty-message">Not a member of any groups</p>
                            </div>
                        <?php else: ?>
                            <div class="groups-list">
                                <?php foreach (array_slice($dashboardData['user_groups'], 0, 4) as $group): ?>
                                    <span class="group-badge" role="listitem">
                                        <?php echo htmlspecialchars($group['group_name']); ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count($dashboardData['user_groups']) > 4): ?>
                                    <span class="more-groups">
                                        +<?php echo count($dashboardData['user_groups']) - 4; ?> more
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            
            <!-- Enhanced Quick Actions Card -->
            <section class="card quick-actions-card" id="quickActions" aria-labelledby="actions-heading">
                <header class="card-header">
                    <h2 id="actions-heading" class="card-title">
                        <span class="icon" aria-hidden="true">⚡</span>
                        Quick Actions
                    </h2>
                </header>
                <div class="card-content">
                    <div class="quick-actions" role="list">
                        <?php if ($userPermissions['manual_checkin']): ?>
                            <button class="action-btn" onclick="showCheckInModal()" 
                                    aria-label="Manual check-in to event">
                                <span class="action-icon">📟</span>
                                <span class="action-text">Manual Check-in</span>
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($userPermissions['view_events']): ?>
                            <a href="events.php" class="action-btn" aria-label="Browse all events">
                                <span class="action-icon">📅</span>
                                <span class="action-text">Browse Events</span>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($userPermissions['view_analytics']): ?>
                            <a href="analytics.php" class="action-btn" aria-label="View analytics dashboard">
                                <span class="action-icon">📈</span>
                                <span class="action-text">View Analytics</span>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($userPermissions['manage_profile']): ?>
                            <a href="account-settings.php" class="action-btn" aria-label="Manage account settings">
                                <span class="action-icon">⚙️</span>
                                <span class="action-text">Account Settings</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
        
        <!-- Performance Metrics Display for Development -->
        <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
            <div class="debug-panel" id="debugPanel">
                <h3>Performance Metrics</h3>
                <div class="debug-metrics">
                    <span>Page Load: <strong><?php echo round($performanceManager->getTimer('dashboard_page_load') * 1000, 2); ?>ms</strong></span>
                    <span>Data Load: <strong><?php echo round($performanceManager->getTimer('dashboard_data_load') * 1000, 2); ?>ms</strong></span>
                    <span>Memory: <strong><?php echo round(memory_get_peak_usage(true) / 1024 / 1024, 2); ?>MB</strong></span>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- Enhanced Manual Check-in Modal -->
    <?php if ($userPermissions['manual_checkin']): ?>
        <div id="checkInModal" class="modal" role="dialog" aria-labelledby="modal-title" aria-hidden="true">
            <div class="modal-backdrop" onclick="closeCheckInModal()"></div>
            <div class="modal-content">
                <header class="modal-header">
                    <h3 id="modal-title">Manual Check-in</h3>
                    <button class="modal-close" onclick="closeCheckInModal()" aria-label="Close modal">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </header>
                <form id="manualCheckInForm" class="modal-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <div class="form-group">
                        <label for="eventSelect" class="form-label">Select Event</label>
                        <select id="eventSelect" name="event_id" class="form-select" required 
                                aria-describedby="event-help">
                            <option value="">Choose an event...</option>
                        </select>
                        <small id="event-help" class="form-help">
                            Select an active event to check in to
                        </small>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeCheckInModal()">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" id="checkInSubmitBtn">
                            <span class="btn-text">Check In</span>
                            <span class="btn-loading" style="display: none;">
                                <span class="spinner"></span> Checking in...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Toast Notification Container -->
    <div id="toastContainer" class="toast-container" aria-live="polite" aria-atomic="true"></div>
    
    <?php 
    // Load optimized JavaScript with enterprise features
    echo $assetOptimizer->loadJS(['dashboard.js', 'notifications.js', 'modal.js'], true);
    include '../includes/theme_script.php'; 
    
    // Complete performance monitoring
    $performanceManager->endTimer('dashboard_page_load');
    $performanceManager->recordMetric('dashboard_total_load_time', $performanceManager->getTimer('dashboard_page_load'));
    ?>
    
    <script>
        // Initialize enterprise dashboard features
        document.addEventListener('DOMContentLoaded', function() {
            initializeDashboard({
                csrfToken: '<?php echo $csrfToken; ?>',
                permissions: <?php echo json_encode($userPermissions); ?>,
                userId: <?php echo $user['user_id']; ?>,
                refreshInterval: 30000 // 30 seconds
            });
        });
    </script>
</body>
</html>
