<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'Performance Dashboard') ?> - RFID Check-in System</title>
    
    <!-- Critical CSS - Preloaded for performance -->
    <link rel="preload" href="/assets/css/critical.css" as="style">
    <link rel="preload" href="/assets/css/performance.css" as="style">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="/assets/css/critical.css">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin-tools.css">
    <link rel="stylesheet" href="/assets/css/performance.css">
    <link rel="stylesheet" href="/assets/css/modal.css">
    
    <!-- Chart.js for performance visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Performance monitoring script -->
    <script>
        // Real-time performance monitoring
        window.performanceConfig = {
            refreshInterval: 5000,
            endpoints: {
                dashboard: '/api/performance/dashboard',
                realtime: '/api/performance/realtime-metrics',
                cache: '/api/performance/cache-stats',
                database: '/api/performance/database-metrics'
            }
        };
    </script>
</head>
<body class="admin-body">
    <!-- Navigation -->
    <?php include 'includes/navigation.php'; ?>
    
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <h3>Performance Dashboard</h3>
            </div>
            
            <nav class="sidebar-nav">
                <a href="/admin/performance" class="nav-item <?= $current_page === 'overview' ? 'active' : '' ?>">
                    <i class="icon-dashboard"></i>
                    Overview
                </a>
                <a href="/admin/performance/cache" class="nav-item <?= $current_page === 'cache' ? 'active' : '' ?>">
                    <i class="icon-cache"></i>
                    Cache Management
                </a>
                <a href="/admin/performance/database" class="nav-item <?= $current_page === 'database' ? 'active' : '' ?>">
                    <i class="icon-database"></i>
                    Database Optimization
                </a>
                <a href="/admin/performance/assets" class="nav-item <?= $current_page === 'assets' ? 'active' : '' ?>">
                    <i class="icon-assets"></i>
                    Asset Optimization
                </a>
                <a href="/admin/performance/monitoring" class="nav-item <?= $current_page === 'monitoring' ? 'active' : '' ?>">
                    <i class="icon-monitor"></i>
                    System Monitoring
                </a>
                <a href="/admin/performance/reports" class="nav-item <?= $current_page === 'reports' ? 'active' : '' ?>">
                    <i class="icon-reports"></i>
                    Performance Reports
                </a>
                <a href="/admin/performance/wizard" class="nav-item <?= $current_page === 'wizard' ? 'active' : '' ?>">
                    <i class="icon-wizard"></i>
                    Optimization Wizard
                </a>
            </nav>
            
            <!-- Quick Actions -->
            <div class="sidebar-section">
                <h4>Quick Actions</h4>
                <div class="quick-actions">
                    <button class="btn btn-sm btn-primary" onclick="runOptimizationSuite()">
                        <i class="icon-optimize"></i>
                        Run Full Optimization
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="clearAllCache()">
                        <i class="icon-clear"></i>
                        Clear All Cache
                    </button>
                    <button class="btn btn-sm btn-info" onclick="generateReport()">
                        <i class="icon-report"></i>
                        Generate Report
                    </button>
                </div>
            </div>
            
            <!-- System Status -->
            <div class="sidebar-section">
                <h4>System Status</h4>
                <div class="status-indicators">
                    <div class="status-item">
                        <span class="status-label">Cache</span>
                        <span class="status-value" id="cache-status">
                            <span class="status-indicator status-good"></span>
                            Healthy
                        </span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Database</span>
                        <span class="status-value" id="database-status">
                            <span class="status-indicator status-good"></span>
                            Optimal
                        </span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Memory</span>
                        <span class="status-value" id="memory-status">
                            <span class="status-indicator status-warning"></span>
                            75%
                        </span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Disk</span>
                        <span class="status-value" id="disk-status">
                            <span class="status-indicator status-good"></span>
                            45%
                        </span>
                    </div>
                </div>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="admin-main">
            <!-- Performance Alerts -->
            <?php if (!empty($performance_alerts)): ?>
                <div class="alerts-section">
                    <?php foreach ($performance_alerts as $alert): ?>
                        <div class="alert alert-<?= htmlspecialchars($alert['level']) ?> alert-dismissible">
                            <strong><?= htmlspecialchars($alert['title']) ?></strong>
                            <p><?= htmlspecialchars($alert['message']) ?></p>
                            <?php if (isset($alert['action'])): ?>
                                <p class="alert-action">
                                    <strong>Recommended Action:</strong> <?= htmlspecialchars($alert['action']) ?>
                                </p>
                            <?php endif; ?>
                            <button type="button" class="alert-close" onclick="this.parentElement.remove()">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Real-time Metrics Bar -->
            <div class="metrics-bar">
                <div class="metric-item">
                    <div class="metric-label">Response Time</div>
                    <div class="metric-value" id="response-time">--ms</div>
                </div>
                <div class="metric-item">
                    <div class="metric-label">Memory Usage</div>
                    <div class="metric-value" id="memory-usage">--%</div>
                </div>
                <div class="metric-item">
                    <div class="metric-label">Cache Hit Rate</div>
                    <div class="metric-value" id="cache-hit-rate">--%</div>
                </div>
                <div class="metric-item">
                    <div class="metric-label">Active Requests</div>
                    <div class="metric-value" id="active-requests">--</div>
                </div>
                <div class="metric-item">
                    <div class="metric-label">CPU Load</div>
                    <div class="metric-value" id="cpu-load">--</div>
                </div>
                <div class="metric-item">
                    <div class="metric-label">Disk I/O</div>
                    <div class="metric-value" id="disk-io">--</div>
                </div>
            </div>
            
            <!-- Page Content Area -->
            <div class="content-area">
                <!-- This will be filled by specific page templates -->
                <?php if (isset($content)): ?>
                    <?= $content ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Performance Optimization Modal -->
    <div id="optimizationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Performance Optimization</h2>
                <span class="modal-close" onclick="closeModal('optimizationModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="optimization-progress">
                    <div class="progress-header">
                        <h3 id="optimization-title">Running Optimization Suite...</h3>
                        <div class="progress-bar">
                            <div class="progress-fill" id="optimization-progress"></div>
                        </div>
                    </div>
                    <div class="optimization-steps">
                        <div class="step" id="step-cache">
                            <span class="step-icon">⏳</span>
                            <span class="step-text">Optimizing Cache</span>
                            <span class="step-status">Pending</span>
                        </div>
                        <div class="step" id="step-database">
                            <span class="step-icon">⏳</span>
                            <span class="step-text">Optimizing Database</span>
                            <span class="step-status">Pending</span>
                        </div>
                        <div class="step" id="step-assets">
                            <span class="step-icon">⏳</span>
                            <span class="step-text">Optimizing Assets</span>
                            <span class="step-status">Pending</span>
                        </div>
                        <div class="step" id="step-cleanup">
                            <span class="step-icon">⏳</span>
                            <span class="step-text">System Cleanup</span>
                            <span class="step-status">Pending</span>
                        </div>
                    </div>
                    <div class="optimization-results" id="optimization-results" style="display: none;">
                        <h4>Optimization Complete!</h4>
                        <div class="results-summary">
                            <!-- Results will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('optimizationModal')" id="optimization-close" style="display: none;">Close</button>
                <button class="btn btn-danger" onclick="cancelOptimization()" id="optimization-cancel">Cancel</button>
            </div>
        </div>
    </div>
    
    <!-- Cache Management Modal -->
    <div id="cacheModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Cache Management</h2>
                <span class="modal-close" onclick="closeModal('cacheModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="cache-controls">
                    <div class="control-group">
                        <label for="cache-namespace">Cache Namespace</label>
                        <select id="cache-namespace" class="form-control">
                            <option value="all">All Namespaces</option>
                            <option value="default">Default</option>
                            <option value="database">Database</option>
                            <option value="templates">Templates</option>
                            <option value="api">API</option>
                            <option value="config">Configuration</option>
                            <option value="sessions">Sessions</option>
                        </select>
                    </div>
                    <div class="control-actions">
                        <button class="btn btn-primary" onclick="warmupCache()">Warmup Cache</button>
                        <button class="btn btn-warning" onclick="clearSelectedCache()">Clear Cache</button>
                        <button class="btn btn-info" onclick="analyzeCacheUsage()">Analyze Usage</button>
                    </div>
                </div>
                <div class="cache-stats" id="cache-stats-display">
                    <!-- Cache statistics will be populated here -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('cacheModal')">Close</button>
            </div>
        </div>
    </div>
    
    <!-- Performance Report Modal -->
    <div id="reportModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2>Performance Report</h2>
                <span class="modal-close" onclick="closeModal('reportModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="report-controls">
                    <div class="control-group">
                        <label for="report-timeframe">Timeframe</label>
                        <select id="report-timeframe" class="form-control">
                            <option value="1h">Last Hour</option>
                            <option value="24h" selected>Last 24 Hours</option>
                            <option value="7d">Last 7 Days</option>
                            <option value="30d">Last 30 Days</option>
                        </select>
                    </div>
                    <div class="control-group">
                        <label for="report-type">Report Type</label>
                        <select id="report-type" class="form-control">
                            <option value="overview" selected>Overview</option>
                            <option value="detailed">Detailed Analysis</option>
                            <option value="comparison">Performance Comparison</option>
                            <option value="trends">Trend Analysis</option>
                        </select>
                    </div>
                    <div class="control-actions">
                        <button class="btn btn-primary" onclick="generatePerformanceReport()">Generate Report</button>
                        <button class="btn btn-secondary" onclick="exportReport('pdf')">Export PDF</button>
                        <button class="btn btn-secondary" onclick="exportReport('csv')">Export CSV</button>
                    </div>
                </div>
                <div class="report-content" id="report-content">
                    <!-- Report content will be populated here -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('reportModal')">Close</button>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="/assets/js/performance-dashboard.js"></script>
    <script src="/assets/js/modal.js"></script>
    <script src="/assets/js/charts.js"></script>
    
    <!-- Real-time Performance Monitoring -->
    <script>
        // Initialize performance monitoring
        document.addEventListener('DOMContentLoaded', function() {
            initializePerformanceMonitoring();
            startRealtimeUpdates();
            loadInitialData();
        });
        
        // Global functions for template usage
        function runOptimizationSuite() {
            showModal('optimizationModal');
            startOptimizationSuite();
        }
        
        function clearAllCache() {
            if (confirm('Are you sure you want to clear all cache? This may temporarily slow down the system.')) {
                clearCache('all');
            }
        }
        
        function generateReport() {
            showModal('reportModal');
        }
        
        function showCacheManagement() {
            showModal('cacheModal');
            loadCacheStats();
        }
    </script>
    
    <!-- Performance tracking -->
    <script>
        // Track page load performance
        window.addEventListener('load', function() {
            const loadTime = performance.now();
            const navigation = performance.getEntriesByType('navigation')[0];
            
            // Send performance data to server
            fetch('/api/performance/track-page-load', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    url: window.location.pathname,
                    load_time: loadTime,
                    dom_content_loaded: navigation.domContentLoadedEventEnd - navigation.domContentLoadedEventStart,
                    first_contentful_paint: performance.getEntriesByName('first-contentful-paint')[0]?.startTime || 0,
                    largest_contentful_paint: performance.getEntriesByName('largest-contentful-paint')[0]?.startTime || 0
                })
            }).catch(error => {
                console.warn('Failed to track performance:', error);
            });
        });
    </script>
</body>
</html>
