<?php
$page_title = 'Dashboard';
$page_description = 'Overview of system activity and key metrics';
$breadcrumbs = [];
$current_page = 'dashboard';

// Page-specific assets
$assets = [
    'css' => ['dashboard.css', 'charts.css', 'widgets.css'],
    'js' => ['dashboard.js', 'charts.js', 'real-time.js']
];
?>

<!-- Dashboard Header -->
<div class="dashboard-header">
    <div class="header-content">
        <div class="welcome-section">
            <h1 class="page-title">
                <i class="fas fa-tachometer-alt"></i>
                Welcome back, <?= htmlspecialchars($current_user['first_name'] ?? $current_user['username']) ?>!
            </h1>
            <p class="page-subtitle">
                Here's what's happening with your RFID check-in system today.
                <span class="last-update">Last updated: <span id="last-refresh">just now</span></span>
            </p>
        </div>
        
        <div class="header-actions">
            <!-- Quick Actions -->
            <div class="quick-actions">
                <?php if ($current_user['group_id'] <= 2): ?>
                    <a href="/events/create" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i>
                        <span>New Event</span>
                    </a>
                    
                    <a href="/admin/users/create" class="btn btn-outline btn-sm">
                        <i class="fas fa-user-plus"></i>
                        <span>Add User</span>
                    </a>
                <?php endif; ?>
                
                <button class="btn btn-outline btn-sm" id="refresh-dashboard" title="Refresh Dashboard">
                    <i class="fas fa-sync-alt"></i>
                    <span>Refresh</span>
                </button>
            </div>
            
            <!-- Date Range Selector -->
            <div class="date-range-selector">
                <select id="dashboard-period" class="form-select">
                    <option value="today">Today</option>
                    <option value="week" selected>This Week</option>
                    <option value="month">This Month</option>
                    <option value="quarter">This Quarter</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- System Status Banner -->
<div class="status-banner" id="status-banner">
    <div class="status-item">
        <div class="status-indicator status-online" id="system-status"></div>
        <span class="status-label">System Status</span>
    </div>
    
    <div class="status-item">
        <div class="status-indicator" id="rfid-status"></div>
        <span class="status-label">RFID Reader</span>
    </div>
    
    <div class="status-item">
        <div class="status-indicator" id="database-status"></div>
        <span class="status-label">Database</span>
    </div>
    
    <div class="status-item">
        <span class="status-value" id="active-users"><?= number_format($stats['active_users'] ?? 0) ?></span>
        <span class="status-label">Active Users</span>
    </div>
    
    <div class="status-item">
        <span class="status-value" id="active-events"><?= number_format($stats['active_events'] ?? 0) ?></span>
        <span class="status-label">Active Events</span>
    </div>
</div>

<!-- Dashboard Widgets Grid -->
<div class="dashboard-grid">
    
    <!-- Statistics Cards Row -->
    <div class="grid-section">
        <h2 class="section-title">
            <i class="fas fa-chart-bar"></i>
            Key Metrics
        </h2>
        
        <div class="stats-grid">
            
            <!-- Total Check-ins Widget -->
            <div class="stat-card" data-widget="checkins">
                <div class="stat-header">
                    <div class="stat-icon checkins">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-trend" id="checkins-trend">
                        <i class="fas fa-arrow-up"></i>
                        <span>+12%</span>
                    </div>
                </div>
                <div class="stat-body">
                    <div class="stat-value" id="total-checkins"><?= number_format($stats['total_checkins'] ?? 0) ?></div>
                    <div class="stat-label">Total Check-ins</div>
                    <div class="stat-period" id="checkins-period">This week</div>
                </div>
                <div class="stat-footer">
                    <a href="/checkins" class="stat-link">View all check-ins</a>
                </div>
            </div>
            
            <!-- Unique Visitors Widget -->
            <div class="stat-card" data-widget="visitors">
                <div class="stat-header">
                    <div class="stat-icon visitors">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-trend" id="visitors-trend">
                        <i class="fas fa-arrow-up"></i>
                        <span>+8%</span>
                    </div>
                </div>
                <div class="stat-body">
                    <div class="stat-value" id="unique-visitors"><?= number_format($stats['unique_visitors'] ?? 0) ?></div>
                    <div class="stat-label">Unique Visitors</div>
                    <div class="stat-period" id="visitors-period">This week</div>
                </div>
                <div class="stat-footer">
                    <a href="/analytics/visitors" class="stat-link">View visitor analytics</a>
                </div>
            </div>
            
            <!-- Events Widget -->
            <div class="stat-card" data-widget="events">
                <div class="stat-header">
                    <div class="stat-icon events">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="stat-trend" id="events-trend">
                        <i class="fas fa-minus"></i>
                        <span>0%</span>
                    </div>
                </div>
                <div class="stat-body">
                    <div class="stat-value" id="total-events"><?= number_format($stats['total_events'] ?? 0) ?></div>
                    <div class="stat-label">Active Events</div>
                    <div class="stat-period" id="events-period">Currently</div>
                </div>
                <div class="stat-footer">
                    <a href="/events" class="stat-link">Manage events</a>
                </div>
            </div>
            
            <!-- RFID Tags Widget -->
            <div class="stat-card" data-widget="rfid">
                <div class="stat-header">
                    <div class="stat-icon rfid">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div class="stat-trend" id="rfid-trend">
                        <i class="fas fa-arrow-up"></i>
                        <span>+3%</span>
                    </div>
                </div>
                <div class="stat-body">
                    <div class="stat-value" id="rfid-tags"><?= number_format($stats['rfid_tags'] ?? 0) ?></div>
                    <div class="stat-label">RFID Tags</div>
                    <div class="stat-period" id="rfid-period">Assigned</div>
                </div>
                <div class="stat-footer">
                    <?php if ($current_user['group_id'] <= 2): ?>
                        <a href="/admin/rfid" class="stat-link">Manage RFID tags</a>
                    <?php else: ?>
                        <a href="/profile" class="stat-link">View my profile</a>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
    </div>
    
    <!-- Charts Section -->
    <div class="grid-section">
        <div class="charts-row">
            
            <!-- Check-ins Timeline Chart -->
            <div class="chart-widget chart-large">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-chart-line"></i>
                        Check-ins Over Time
                    </h3>
                    <div class="widget-actions">
                        <button class="btn btn-sm btn-ghost" data-action="fullscreen" title="Fullscreen">
                            <i class="fas fa-expand"></i>
                        </button>
                        <button class="btn btn-sm btn-ghost" data-action="export" title="Export Chart">
                            <i class="fas fa-download"></i>
                        </button>
                    </div>
                </div>
                <div class="widget-body">
                    <canvas id="checkins-chart" class="chart-canvas"></canvas>
                    <div class="chart-loading" id="checkins-loading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Loading chart...</span>
                    </div>
                </div>
                <div class="widget-footer">
                    <div class="chart-legend" id="checkins-legend"></div>
                </div>
            </div>
            
            <!-- Events Distribution Chart -->
            <div class="chart-widget chart-medium">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-chart-pie"></i>
                        Events by Category
                    </h3>
                    <div class="widget-actions">
                        <button class="btn btn-sm btn-ghost" data-action="refresh" title="Refresh">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="widget-body">
                    <canvas id="events-chart" class="chart-canvas"></canvas>
                    <div class="chart-loading" id="events-loading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Loading chart...</span>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
    <!-- Activity and Data Section -->
    <div class="grid-section">
        <div class="activity-row">
            
            <!-- Recent Activity Widget -->
            <div class="activity-widget">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-clock"></i>
                        Recent Activity
                    </h3>
                    <div class="widget-actions">
                        <a href="/activity" class="btn btn-sm btn-ghost">View All</a>
                    </div>
                </div>
                <div class="widget-body">
                    <div class="activity-list" id="recent-activity">
                        <!-- Activity items will be loaded here -->
                        <div class="activity-loading">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span>Loading activity...</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Events Widget -->
            <div class="top-events-widget">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-star"></i>
                        Top Events
                    </h3>
                    <div class="widget-actions">
                        <a href="/events" class="btn btn-sm btn-ghost">View All</a>
                    </div>
                </div>
                <div class="widget-body">
                    <div class="events-list" id="top-events">
                        <!-- Top events will be loaded here -->
                        <div class="events-loading">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span>Loading events...</span>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
    <?php if ($current_user['group_id'] <= 2): ?>
    <!-- Admin Section -->
    <div class="grid-section admin-section">
        <h2 class="section-title">
            <i class="fas fa-shield-alt"></i>
            System Administration
        </h2>
        
        <div class="admin-grid">
            
            <!-- System Health Widget -->
            <div class="admin-widget health-widget">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-heartbeat"></i>
                        System Health
                    </h3>
                    <div class="health-indicator" id="health-indicator">
                        <div class="health-status status-good">Good</div>
                    </div>
                </div>
                <div class="widget-body">
                    <div class="health-metrics" id="health-metrics">
                        <!-- Health metrics will be loaded here -->
                        <div class="metrics-loading">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span>Loading health metrics...</span>
                        </div>
                    </div>
                </div>
                <div class="widget-footer">
                    <a href="/admin/performance" class="btn btn-sm btn-outline">View Details</a>
                </div>
            </div>
            
            <!-- Quick Admin Actions -->
            <div class="admin-widget actions-widget">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-tools"></i>
                        Quick Actions
                    </h3>
                </div>
                <div class="widget-body">
                    <div class="admin-actions">
                        <a href="/admin/users" class="admin-action">
                            <i class="fas fa-users"></i>
                            <span>Manage Users</span>
                            <div class="action-count"><?= number_format($stats['pending_users'] ?? 0) ?> pending</div>
                        </a>
                        
                        <a href="/admin/events" class="admin-action">
                            <i class="fas fa-calendar"></i>
                            <span>Manage Events</span>
                            <div class="action-count"><?= number_format($stats['upcoming_events'] ?? 0) ?> upcoming</div>
                        </a>
                        
                        <a href="/admin/rfid" class="admin-action">
                            <i class="fas fa-id-card"></i>
                            <span>RFID Management</span>
                            <div class="action-count"><?= number_format($stats['unassigned_rfid'] ?? 0) ?> unassigned</div>
                        </a>
                        
                        <a href="/admin/reports" class="admin-action">
                            <i class="fas fa-chart-bar"></i>
                            <span>View Reports</span>
                            <div class="action-count">Generate</div>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Security Alerts Widget -->
            <div class="admin-widget security-widget">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-shield-alt"></i>
                        Security Alerts
                    </h3>
                    <div class="alert-count" id="security-alert-count">0</div>
                </div>
                <div class="widget-body">
                    <div class="security-alerts" id="security-alerts">
                        <!-- Security alerts will be loaded here -->
                        <div class="no-alerts">
                            <i class="fas fa-check-circle"></i>
                            <span>No security alerts</span>
                        </div>
                    </div>
                </div>
                <div class="widget-footer">
                    <a href="/admin/security" class="btn btn-sm btn-outline">View Security Log</a>
                </div>
            </div>
            
        </div>
    </div>
    <?php endif; ?>
    
</div>

<!-- Real-time Updates -->
<div class="real-time-indicator" id="real-time-indicator">
    <div class="indicator-dot"></div>
    <span>Live updates active</span>
</div>

<!-- Dashboard JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dashboard = new DashboardManager();
    dashboard.init();
});

class DashboardManager {
    constructor() {
        this.refreshInterval = null;
        this.charts = {};
        this.updateInterval = 30000; // 30 seconds
        this.isVisible = true;
    }
    
    init() {
        this.setupEventListeners();
        this.initializeCharts();
        this.loadDashboardData();
        this.startRealTimeUpdates();
        this.setupVisibilityHandling();
    }
    
    setupEventListeners() {
        // Refresh button
        const refreshBtn = document.getElementById('refresh-dashboard');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.refreshDashboard();
            });
        }
        
        // Period selector
        const periodSelector = document.getElementById('dashboard-period');
        if (periodSelector) {
            periodSelector.addEventListener('change', () => {
                this.changePeriod(periodSelector.value);
            });
        }
        
        // Widget actions
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-action="fullscreen"]')) {
                this.toggleFullscreen(e.target.closest('.chart-widget'));
            } else if (e.target.closest('[data-action="export"]')) {
                this.exportChart(e.target.closest('.chart-widget'));
            } else if (e.target.closest('[data-action="refresh"]')) {
                this.refreshWidget(e.target.closest('.chart-widget'));
            }
        });
    }
    
    initializeCharts() {
        // Initialize check-ins timeline chart
        this.initCheckinsChart();
        
        // Initialize events distribution chart
        this.initEventsChart();
    }
    
    initCheckinsChart() {
        const ctx = document.getElementById('checkins-chart');
        if (!ctx) return;
        
        this.charts.checkins = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Check-ins',
                    data: [],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Time'
                        }
                    },
                    y: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Check-ins'
                        },
                        beginAtZero: true
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
    }
    
    initEventsChart() {
        const ctx = document.getElementById('events-chart');
        if (!ctx) return;
        
        this.charts.events = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: [
                        '#3b82f6',
                        '#10b981',
                        '#f59e0b',
                        '#ef4444',
                        '#8b5cf6'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    loadDashboardData() {
        const period = document.getElementById('dashboard-period')?.value || 'week';
        
        // Load all dashboard data
        Promise.all([
            this.loadStats(period),
            this.loadChartData(period),
            this.loadActivity(),
            this.loadTopEvents(),
            this.loadSystemHealth(),
            this.loadSecurityAlerts()
        ]).then(() => {
            this.updateLastRefresh();
        }).catch(error => {
            console.error('Error loading dashboard data:', error);
            this.showError('Failed to load dashboard data');
        });
    }
    
    async loadStats(period) {
        try {
            const response = await fetch(`/api/dashboard/stats?period=${period}`, {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateStats(data.data);
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }
    
    async loadChartData(period) {
        try {
            const response = await fetch(`/api/dashboard/charts?period=${period}`, {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateCharts(data.data);
            }
        } catch (error) {
            console.error('Error loading chart data:', error);
        }
    }
    
    async loadActivity() {
        try {
            const response = await fetch('/api/dashboard/activity', {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateActivity(data.data);
            }
        } catch (error) {
            console.error('Error loading activity:', error);
        }
    }
    
    async loadTopEvents() {
        try {
            const response = await fetch('/api/dashboard/top-events', {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateTopEvents(data.data);
            }
        } catch (error) {
            console.error('Error loading top events:', error);
        }
    }
    
    async loadSystemHealth() {
        if (!document.querySelector('.admin-section')) return;
        
        try {
            const response = await fetch('/api/admin/system-health', {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateSystemHealth(data.data);
            }
        } catch (error) {
            console.error('Error loading system health:', error);
        }
    }
    
    async loadSecurityAlerts() {
        if (!document.querySelector('.admin-section')) return;
        
        try {
            const response = await fetch('/api/admin/security-alerts', {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateSecurityAlerts(data.data);
            }
        } catch (error) {
            console.error('Error loading security alerts:', error);
        }
    }
    
    updateStats(stats) {
        // Update stat values and trends
        Object.keys(stats).forEach(key => {
            const element = document.getElementById(key);
            if (element) {
                if (typeof stats[key] === 'number') {
                    element.textContent = this.formatNumber(stats[key]);
                } else {
                    element.textContent = stats[key];
                }
            }
            
            // Update trends
            const trendElement = document.getElementById(key + '-trend');
            if (trendElement && stats[key + '_trend']) {
                this.updateTrend(trendElement, stats[key + '_trend']);
            }
        });
    }
    
    updateCharts(chartData) {
        // Update check-ins chart
        if (this.charts.checkins && chartData.checkins) {
            this.charts.checkins.data.labels = chartData.checkins.labels;
            this.charts.checkins.data.datasets[0].data = chartData.checkins.data;
            this.charts.checkins.update();
            
            this.hideLoading('checkins-loading');
        }
        
        // Update events chart
        if (this.charts.events && chartData.events) {
            this.charts.events.data.labels = chartData.events.labels;
            this.charts.events.data.datasets[0].data = chartData.events.data;
            this.charts.events.update();
            
            this.hideLoading('events-loading');
        }
    }
    
    updateActivity(activities) {
        const container = document.getElementById('recent-activity');
        if (!container) return;
        
        if (activities.length === 0) {
            container.innerHTML = `
                <div class="no-activity">
                    <i class="fas fa-inbox"></i>
                    <span>No recent activity</span>
                </div>
            `;
            return;
        }
        
        container.innerHTML = activities.map(activity => `
            <div class="activity-item">
                <div class="activity-icon ${activity.type}">
                    <i class="fas fa-${this.getActivityIcon(activity.type)}"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-title">${this.escapeHtml(activity.title)}</div>
                    <div class="activity-description">${this.escapeHtml(activity.description)}</div>
                    <div class="activity-time">${this.formatRelativeTime(activity.created_at)}</div>
                </div>
            </div>
        `).join('');
    }
    
    updateTopEvents(events) {
        const container = document.getElementById('top-events');
        if (!container) return;
        
        if (events.length === 0) {
            container.innerHTML = `
                <div class="no-events">
                    <i class="fas fa-calendar-times"></i>
                    <span>No active events</span>
                </div>
            `;
            return;
        }
        
        container.innerHTML = events.map(event => `
            <div class="event-item">
                <div class="event-info">
                    <div class="event-name">${this.escapeHtml(event.event_name)}</div>
                    <div class="event-details">
                        <span class="event-date">${this.formatDate(event.event_date)}</span>
                        <span class="event-checkins">${event.checkin_count} check-ins</span>
                    </div>
                </div>
                <div class="event-actions">
                    <a href="/events/${event.event_id}" class="btn btn-sm btn-ghost">View</a>
                </div>
            </div>
        `).join('');
    }
    
    updateSystemHealth(health) {
        const healthIndicator = document.getElementById('health-indicator');
        const healthMetrics = document.getElementById('health-metrics');
        
        if (healthIndicator) {
            const statusElement = healthIndicator.querySelector('.health-status');
            statusElement.textContent = health.overall_status;
            statusElement.className = `health-status status-${health.overall_status.toLowerCase()}`;
        }
        
        if (healthMetrics) {
            healthMetrics.innerHTML = Object.keys(health.metrics).map(key => `
                <div class="health-metric">
                    <div class="metric-label">${this.formatMetricLabel(key)}</div>
                    <div class="metric-value ${health.metrics[key].status}">${health.metrics[key].value}</div>
                </div>
            `).join('');
        }
    }
    
    updateSecurityAlerts(alerts) {
        const alertCount = document.getElementById('security-alert-count');
        const alertsContainer = document.getElementById('security-alerts');
        
        if (alertCount) {
            alertCount.textContent = alerts.length;
        }
        
        if (alertsContainer) {
            if (alerts.length === 0) {
                alertsContainer.innerHTML = `
                    <div class="no-alerts">
                        <i class="fas fa-check-circle"></i>
                        <span>No security alerts</span>
                    </div>
                `;
            } else {
                alertsContainer.innerHTML = alerts.map(alert => `
                    <div class="security-alert alert-${alert.severity}">
                        <div class="alert-icon">
                            <i class="fas fa-${this.getAlertIcon(alert.severity)}"></i>
                        </div>
                        <div class="alert-content">
                            <div class="alert-title">${this.escapeHtml(alert.title)}</div>
                            <div class="alert-time">${this.formatRelativeTime(alert.created_at)}</div>
                        </div>
                    </div>
                `).join('');
            }
        }
    }
    
    updateTrend(element, trend) {
        const icon = element.querySelector('i');
        const span = element.querySelector('span');
        
        if (trend.direction === 'up') {
            icon.className = 'fas fa-arrow-up';
            element.className = 'stat-trend trend-up';
        } else if (trend.direction === 'down') {
            icon.className = 'fas fa-arrow-down';
            element.className = 'stat-trend trend-down';
        } else {
            icon.className = 'fas fa-minus';
            element.className = 'stat-trend trend-neutral';
        }
        
        span.textContent = trend.percentage;
    }
    
    refreshDashboard() {
        const refreshBtn = document.getElementById('refresh-dashboard');
        const icon = refreshBtn.querySelector('i');
        
        // Show loading state
        icon.classList.add('fa-spin');
        refreshBtn.disabled = true;
        
        this.loadDashboardData().finally(() => {
            // Reset button state
            icon.classList.remove('fa-spin');
            refreshBtn.disabled = false;
        });
    }
    
    changePeriod(period) {
        this.showLoading();
        this.loadStats(period);
        this.loadChartData(period);
    }
    
    startRealTimeUpdates() {
        this.refreshInterval = setInterval(() => {
            if (this.isVisible) {
                this.loadDashboardData();
            }
        }, this.updateInterval);
    }
    
    setupVisibilityHandling() {
        document.addEventListener('visibilitychange', () => {
            this.isVisible = !document.hidden;
            
            if (this.isVisible) {
                // Refresh data when page becomes visible
                this.loadDashboardData();
            }
        });
    }
    
    // Utility methods
    formatNumber(num) {
        return new Intl.NumberFormat().format(num);
    }
    
    formatRelativeTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);
        
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;
        
        return date.toLocaleDateString();
    }
    
    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString();
    }
    
    getActivityIcon(type) {
        const icons = {
            'checkin': 'user-check',
            'event': 'calendar',
            'user': 'user',
            'system': 'cog',
            'security': 'shield-alt'
        };
        return icons[type] || 'info-circle';
    }
    
    getAlertIcon(severity) {
        const icons = {
            'high': 'exclamation-triangle',
            'medium': 'exclamation-circle',
            'low': 'info-circle'
        };
        return icons[severity] || 'info-circle';
    }
    
    formatMetricLabel(key) {
        return key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    showLoading() {
        document.querySelectorAll('.chart-loading').forEach(el => {
            el.style.display = 'flex';
        });
    }
    
    hideLoading(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.style.display = 'none';
        }
    }
    
    updateLastRefresh() {
        const element = document.getElementById('last-refresh');
        if (element) {
            element.textContent = 'just now';
        }
    }
    
    showError(message) {
        if (typeof window.addFlashMessage === 'function') {
            window.addFlashMessage('error', message);
        }
    }
    
    // Widget actions
    toggleFullscreen(widget) {
        widget.classList.toggle('widget-fullscreen');
        
        if (widget.classList.contains('widget-fullscreen')) {
            // Resize chart when going fullscreen
            const chartCanvas = widget.querySelector('canvas');
            if (chartCanvas) {
                const chartId = chartCanvas.id.replace('-chart', '');
                if (this.charts[chartId]) {
                    setTimeout(() => this.charts[chartId].resize(), 100);
                }
            }
        }
    }
    
    exportChart(widget) {
        const chartCanvas = widget.querySelector('canvas');
        if (chartCanvas) {
            const link = document.createElement('a');
            link.download = `${chartCanvas.id}-${new Date().toISOString().split('T')[0]}.png`;
            link.href = chartCanvas.toDataURL();
            link.click();
        }
    }
    
    refreshWidget(widget) {
        const chartCanvas = widget.querySelector('canvas');
        if (chartCanvas) {
            this.showLoading();
            this.loadChartData(document.getElementById('dashboard-period')?.value || 'week');
        }
    }
}
</script>
