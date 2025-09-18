<?php
/**
 * Enterprise Analytics Dashboard
 * 
 * Advanced analytics interface with comprehensive data visualization,
 * real-time insights, and enterprise security features.
 * 
 * Features:
 * - Enterprise security with role-based access control
 * - Repository pattern for all data operations
 * - Advanced Chart.js visualizations with theme support
 * - Real-time data updates and performance monitoring
 * - Export capabilities for reports and data
 * - Progressive Web App features for mobile analytics
 * - Accessibility compliance (WCAG 2.1 AA)
 * 
 * Analytics Categories:
 * - Personal user statistics and trends
 * - System-wide administrative analytics
 * - Event performance and participation metrics
 * - Device usage and RFID scanning patterns
 * - Predictive insights and recommendations
 * 
 * @package    RFID Check-in System
 * @subpackage Enterprise Analytics Interface
 * @version    4.0.0 - Enterprise Architecture
 * @author     Senior Developer Team
 * @since      1.0.0
 * @security   AUTHENTICATED_USERS_ONLY + ROLE_BASED
 */

// Load enterprise configuration and components
require_once '../core/config.php';
require_once '../core/auth.php';

// Initialize enterprise components
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
$performanceManager->startTimer('analytics_page_load');

try {
    // Enterprise authentication and session validation
    Auth::requireLogin();
    $user = Auth::getCurrentUser();
    
    if (!$user) {
        throw new Exception('Invalid user session');
    }
    
    // Enterprise security validation
    $securityManager->validateRequest($_SERVER);
    
    // Check analytics access permissions
    if (!$securityManager->hasPermission($user, 'view_analytics')) {
        $errorHandler->log('Unauthorized analytics access attempt', null, 'WARNING', [
            'user_id' => $user['user_id'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        header('Location: ../frontend/dashboard.php?error=access_denied');
        exit;
    }
    
    // Validate and sanitize input parameters
    $dateRange = $securityManager->validateInput($_GET['range'] ?? '30', 'string', ['7', '30', '90', '365', 'custom']);
    $customStart = $securityManager->validateInput($_GET['custom_start'] ?? '', 'date');
    $customEnd = $securityManager->validateInput($_GET['custom_end'] ?? '', 'date');
    $viewMode = $securityManager->validateInput($_GET['view'] ?? 'personal', 'string', ['personal', 'system']);
    $exportFormat = $securityManager->validateInput($_GET['export'] ?? '', 'string', ['', 'csv', 'json', 'pdf']);
    
    // Enforce role-based view permissions
    if ($viewMode === 'system' && !$securityManager->hasRole($user, 'admin')) {
        $viewMode = 'personal';
        $errorHandler->log('Non-admin user attempted system analytics view', null, 'INFO', [
            'user_id' => $user['user_id']
        ]);
    }
    
    // Generate CSRF token for form submissions
    $csrfToken = $securityManager->generateCsrfToken();
    
    // Get user permissions for feature visibility
    $userPermissions = [
        'export_analytics' => $securityManager->hasPermission($user, 'export_analytics'),
        'system_view' => $securityManager->hasRole($user, 'admin'),
        'advanced_filters' => $securityManager->hasPermission($user, 'advanced_analytics')
    ];
    
    $performanceManager->startTimer('analytics_initial_load');
    
    // Get initial analytics metadata for page setup
    $analyticsMetadata = $dataService->getAnalyticsMetadata($user['user_id'], $viewMode);
    
    $performanceManager->endTimer('analytics_initial_load');
    
    // Record analytics page access metrics
    $performanceManager->recordMetric('analytics_page_access', 1);
    $performanceManager->recordMetric('analytics_view_mode_' . $viewMode, 1);

} catch (Exception $e) {
    // Enterprise error handling
    $errorHandler->log('Analytics page error', $e, 'ERROR', [
        'user_id' => $user['user_id'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    // Redirect to dashboard with error message
    header('Location: ../frontend/dashboard.php?error=analytics_unavailable');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <title>Analytics Dashboard - Electronic Check-in System</title>
    
    <!-- Enterprise Security Headers -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' fonts.googleapis.com; font-src 'self' fonts.gstatic.com; connect-src 'self';">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    
    <!-- Progressive Web App Manifest -->
    <link rel="manifest" href="../assets/manifest.json">
    <meta name="theme-color" content="#2c3e50">
    <meta name="apple-mobile-web-app-capable" content="yes">
    
    <!-- SEO and Social Meta Tags -->
    <meta name="description" content="RFID Check-in System Analytics - Comprehensive data insights and performance metrics">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- DNS Prefetch for Performance -->
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    
    <?php 
    // Generate optimized asset preloads and critical CSS
    echo $assetOptimizer->generatePreloads(['analytics.css', 'main.css', 'navigation.css', 'dashboard.css']);
    echo $assetOptimizer->inlineCriticalCSS(['main.css', 'navigation.css']);
    echo $assetOptimizer->loadCSS(['analytics.css', 'dashboard.css', 'forms.css', 'modal.css'], false);
    ?>
    
    <!-- Chart.js CDN for data visualization -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js" as="script">
    
    <!-- Performance and Analytics Configuration -->
    <script>
        // Enterprise analytics configuration
        window.enterpriseAnalyticsConfig = {
            apiVersion: '4.0.0',
            csrfToken: '<?php echo $csrfToken; ?>',
            userId: <?php echo $user['user_id']; ?>,
            userRole: '<?php echo $user['role']; ?>',
            permissions: <?php echo json_encode($userPermissions); ?>,
            currentView: '<?php echo $viewMode; ?>',
            dateRange: '<?php echo $dateRange; ?>',
            customStart: '<?php echo $customStart; ?>',
            customEnd: '<?php echo $customEnd; ?>',
            metadata: <?php echo json_encode($analyticsMetadata); ?>,
            pageLoadStart: performance.now(),
            refreshInterval: 60000, // 1 minute for real-time updates
            chartTheme: document.documentElement.getAttribute('data-theme') || 'light'
        };
    </script>
</head>
<body class="analytics-page" data-user-role="<?php echo htmlspecialchars($user['role']); ?>" data-view-mode="<?php echo $viewMode; ?>">
    <!-- Skip to main content link for accessibility -->
    <a href="#main-content" class="skip-link">Skip to main content</a>
    
    <?php include '../includes/navigation.php'; ?>
    
    <main id="main-content" class="main-content" role="main">
        <!-- Analytics Header -->
        <header class="page-header analytics-header">
            <div class="header-content">
                <h1 class="page-title">
                    <span class="icon" aria-hidden="true">📈</span>
                    Analytics Dashboard
                </h1>
                <p class="page-subtitle">
                    <?php if ($viewMode === 'system'): ?>
                        System-wide analytics and performance insights
                    <?php else: ?>
                        Personal check-in analytics and insights
                    <?php endif; ?>
                </p>
                <div class="header-actions">
                    <?php if ($userPermissions['export_analytics']): ?>
                        <button class="btn btn-outline-primary" onclick="showExportModal()" aria-label="Export analytics data">
                            <span class="icon">📊</span> Export Data
                        </button>
                    <?php endif; ?>
                    <button class="btn btn-outline-secondary" onclick="refreshAnalytics()" aria-label="Refresh analytics data">
                        <span class="icon">🔄</span> Refresh
                    </button>
                </div>
            </div>
        </header>
        
        <!-- Advanced Filters Section -->
        <section class="filters-section" aria-labelledby="filters-heading">
            <h2 id="filters-heading" class="sr-only">Analytics Filters</h2>
            <form method="GET" class="filters-form enterprise-form" id="analyticsFilters">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <?php if ($userPermissions['system_view']): ?>
                    <input type="hidden" name="view" value="<?php echo htmlspecialchars($viewMode); ?>">
                <?php endif; ?>
                
                <div class="filters-row">
                    <div class="filters-group">
                        <div class="filter-item">
                            <label for="range" class="filter-label">Time Period</label>
                            <select name="range" id="range" class="filter-select" onchange="toggleCustomDates()" aria-describedby="range-help">
                                <option value="7" <?php echo $dateRange === '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                                <option value="30" <?php echo $dateRange === '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="90" <?php echo $dateRange === '90' ? 'selected' : ''; ?>>Last 90 Days</option>
                                <option value="365" <?php echo $dateRange === '365' ? 'selected' : ''; ?>>Last Year</option>
                                <option value="custom" <?php echo $dateRange === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                            </select>
                            <small id="range-help" class="filter-help">Select the time period for analytics</small>
                        </div>
                        
                        <div class="filter-item custom-dates" id="customDatesContainer" style="display: <?php echo $dateRange === 'custom' ? 'flex' : 'none'; ?>;">
                            <div class="date-input-group">
                                <label for="custom_start" class="filter-label">From</label>
                                <input type="date" name="custom_start" id="custom_start" class="filter-input" 
                                       value="<?php echo htmlspecialchars($customStart); ?>" 
                                       max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="date-input-group">
                                <label for="custom_end" class="filter-label">To</label>
                                <input type="date" name="custom_end" id="custom_end" class="filter-input" 
                                       value="<?php echo htmlspecialchars($customEnd); ?>" 
                                       max="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        
                        <div class="filter-item">
                            <label class="sr-only">Apply Filters</label>
                            <button type="submit" class="btn btn-primary filter-submit">
                                <span class="icon">🔍</span> Update Analytics
                            </button>
                        </div>
                    </div>
                    
                    <?php if ($userPermissions['system_view']): ?>
                        <div class="view-mode-selector">
                            <label class="view-selector-label">View Mode</label>
                            <div class="view-selector-group" role="tablist">
                                <a href="?view=personal&<?php echo http_build_query(array_diff_key($_GET, ['view' => ''])); ?>" 
                                   class="view-btn <?php echo $viewMode === 'personal' ? 'active' : ''; ?>"
                                   role="tab" aria-selected="<?php echo $viewMode === 'personal' ? 'true' : 'false'; ?>">
                                    <span class="icon">👤</span> Personal
                                </a>
                                <a href="?view=system&<?php echo http_build_query(array_diff_key($_GET, ['view' => ''])); ?>" 
                                   class="view-btn <?php echo $viewMode === 'system' ? 'active' : ''; ?>"
                                   role="tab" aria-selected="<?php echo $viewMode === 'system' ? 'true' : 'false'; ?>">
                                    <span class="icon">🏢</span> System-wide
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </section>
        
        <!-- Loading State -->
        <div class="analytics-loading" id="analyticsLoading">
            <div class="loading-spinner"></div>
            <p>Loading analytics data...</p>
        </div>
        
        <!-- Key Statistics Dashboard -->
        <section class="key-stats-section" aria-labelledby="stats-heading" style="display: none;" id="keyStatsSection">
            <h2 id="stats-heading" class="section-title">Key Statistics</h2>
            <div class="stats-grid enterprise-stats" id="keyStats">
                <!-- Dynamically populated by JavaScript -->
            </div>
        </section>
        
        <!-- Analytics Charts Grid -->
        <section class="charts-section" aria-labelledby="charts-heading" style="display: none;" id="chartsSection">
            <h2 id="charts-heading" class="section-title">Data Visualizations</h2>
            <div class="analytics-grid charts-grid">
                <article class="chart-card" id="timelineChartCard">
                    <header class="chart-header">
                        <h3 class="chart-title">
                            <span class="icon">📊</span> Check-ins Over Time
                        </h3>
                        <button class="chart-action-btn" onclick="exportChart('timeline')" aria-label="Export timeline chart">
                            <span class="icon">📥</span>
                        </button>
                    </header>
                    <div class="chart-container">
                        <canvas id="timelineChart" role="img" aria-label="Timeline chart showing check-ins over time"></canvas>
                    </div>
                </article>
                
                <article class="chart-card" id="hoursChartCard">
                    <header class="chart-header">
                        <h3 class="chart-title">
                            <span class="icon">⏰</span> Peak Hours
                        </h3>
                        <button class="chart-action-btn" onclick="exportChart('hours')" aria-label="Export peak hours chart">
                            <span class="icon">📥</span>
                        </button>
                    </header>
                    <div class="chart-container">
                        <canvas id="hoursChart" role="img" aria-label="Bar chart showing peak hours for check-ins"></canvas>
                    </div>
                </article>
                
                <article class="chart-card" id="daysChartCard">
                    <header class="chart-header">
                        <h3 class="chart-title">
                            <span class="icon">📅</span> Days of Week
                        </h3>
                        <button class="chart-action-btn" onclick="exportChart('days')" aria-label="Export days of week chart">
                            <span class="icon">📥</span>
                        </button>
                    </header>
                    <div class="chart-container">
                        <canvas id="daysChart" role="img" aria-label="Doughnut chart showing check-ins by day of week"></canvas>
                    </div>
                </article>
                
                <article class="chart-card" id="typesChartCard">
                    <header class="chart-header">
                        <h3 class="chart-title">
                            <span class="icon">🏷️</span> Event Types
                        </h3>
                        <button class="chart-action-btn" onclick="exportChart('types')" aria-label="Export event types chart">
                            <span class="icon">📥</span>
                        </button>
                    </header>
                    <div class="chart-container">
                        <canvas id="typesChart" role="img" aria-label="Pie chart showing check-ins by event type"></canvas>
                    </div>
                </article>
                
                <article class="chart-card" id="locationsChartCard">
                    <header class="chart-header">
                        <h3 class="chart-title">
                            <span class="icon">📍</span> Top Locations
                        </h3>
                        <button class="chart-action-btn" onclick="exportChart('locations')" aria-label="Export locations chart">
                            <span class="icon">📥</span>
                        </button>
                    </header>
                    <div class="chart-container">
                        <canvas id="locationsChart" role="img" aria-label="Horizontal bar chart showing top locations"></canvas>
                    </div>
                </article>
                
                <article class="chart-card small-chart" id="methodsChartCard">
                    <header class="chart-header">
                        <h3 class="chart-title">
                            <span class="icon">📟</span> Check-in Methods
                        </h3>
                        <button class="chart-action-btn" onclick="exportChart('methods')" aria-label="Export methods chart">
                            <span class="icon">📥</span>
                        </button>
                    </header>
                    <div class="chart-container small">
                        <canvas id="methodsChart" role="img" aria-label="Doughnut chart showing check-in methods distribution"></canvas>
                    </div>
                </article>
            </div>
        </section>
        
        <!-- AI-Powered Insights Section -->
        <section class="insights-section" aria-labelledby="insights-heading" style="display: none;" id="insightsSection">
            <h2 id="insights-heading" class="section-title">
                <span class="icon">💡</span> Key Insights & Recommendations
            </h2>
            <div class="insights-grid" id="insights">
                <!-- Dynamically populated by JavaScript -->
            </div>
        </section>
        
        <!-- Performance Metrics Display for Development -->
        <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
            <div class="debug-panel analytics-debug" id="debugPanel">
                <h3>Performance Metrics</h3>
                <div class="debug-metrics">
                    <span>Page Load: <strong><?php echo round($performanceManager->getTimer('analytics_page_load') * 1000, 2); ?>ms</strong></span>
                    <span>Initial Load: <strong><?php echo round($performanceManager->getTimer('analytics_initial_load') * 1000, 2); ?>ms</strong></span>
                    <span>Memory: <strong><?php echo round(memory_get_peak_usage(true) / 1024 / 1024, 2); ?>MB</strong></span>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- Export Modal -->
    <?php if ($userPermissions['export_analytics']): ?>
        <div id="exportModal" class="modal" role="dialog" aria-labelledby="export-modal-title" aria-hidden="true">
            <div class="modal-backdrop" onclick="closeExportModal()"></div>
            <div class="modal-content">
                <header class="modal-header">
                    <h3 id="export-modal-title">Export Analytics Data</h3>
                    <button class="modal-close" onclick="closeExportModal()" aria-label="Close export modal">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </header>
                <form id="exportForm" class="modal-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <div class="form-group">
                        <label for="exportFormat" class="form-label">Export Format</label>
                        <select id="exportFormat" name="format" class="form-select" required>
                            <option value="">Choose format...</option>
                            <option value="csv">CSV (Spreadsheet)</option>
                            <option value="json">JSON (Data)</option>
                            <option value="pdf">PDF (Report)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="exportData" class="form-label">Data to Export</label>
                        <div class="checkbox-group">
                            <label class="checkbox-item">
                                <input type="checkbox" name="include[]" value="stats" checked> Key Statistics
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="include[]" value="timeline" checked> Timeline Data
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="include[]" value="insights" checked> Insights & Recommendations
                            </label>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeExportModal()">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" id="exportSubmitBtn">
                            <span class="btn-text">Export Data</span>
                            <span class="btn-loading" style="display: none;">
                                <span class="spinner"></span> Exporting...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Toast Notification Container -->
    <div id="toastContainer" class="toast-container" aria-live="polite" aria-atomic="true"></div>
    
    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
    
    <?php 
    // Load optimized JavaScript with enterprise features
    echo $assetOptimizer->loadJS(['analytics.js', 'charts.js', 'notifications.js', 'modal.js'], true);
    include '../includes/theme_script.php'; 
    
    // Complete performance monitoring
    $performanceManager->endTimer('analytics_page_load');
    $performanceManager->recordMetric('analytics_total_load_time', $performanceManager->getTimer('analytics_page_load'));
    ?>
    
    <script>
        // Initialize enterprise analytics dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initializeAnalyticsDashboard(window.enterpriseAnalyticsConfig);
        });
    </script>
</body>
</html>
