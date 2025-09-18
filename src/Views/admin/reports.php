<?php
$page_title = 'Reports & Analytics';
$page_description = 'Comprehensive system reports and analytics dashboard';
$breadcrumbs = [
    ['title' => 'Administration', 'url' => '/admin', 'icon' => 'fas fa-shield-alt'],
    ['title' => 'Reports', 'url' => '/admin/reports', 'icon' => 'fas fa-chart-bar']
];
$current_page = 'admin-reports';

// Page-specific assets
$assets = [
    'css' => ['admin.css', 'reports.css', 'charts.css'],
    'js' => ['admin-reports.js', 'chart.js', 'date-picker.js', 'export.js']
];

// Page actions
$page_actions = [
    ['title' => 'Export All Reports', 'url' => '#', 'type' => 'primary', 'icon' => 'fas fa-download', 'onclick' => 'reportsManager.exportAllReports()'],
    ['title' => 'Schedule Report', 'url' => '#', 'type' => 'outline', 'icon' => 'fas fa-calendar-plus', 'onclick' => 'reportsManager.scheduleReport()'],
    ['title' => 'Custom Report', 'url' => '#', 'type' => 'outline', 'icon' => 'fas fa-plus', 'onclick' => 'reportsManager.createCustomReport()']
];
?>

<!-- Reports & Analytics Interface -->
<div class="admin-content">
    
    <!-- Page Header -->
    <div class="admin-header">
        <div class="header-content">
            <div class="header-info">
                <h1 class="page-title">
                    <i class="fas fa-chart-bar"></i>
                    Reports & Analytics
                </h1>
                <p class="page-description">
                    Comprehensive system reports, analytics, and data insights.
                    <span class="data-range">Data from <strong id="data-range-text">last 30 days</strong></span>
                </p>
            </div>
            
            <div class="header-stats">
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['total_reports'] ?? 0) ?></div>
                    <div class="stat-label">Generated</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['scheduled_reports'] ?? 0) ?></div>
                    <div class="stat-label">Scheduled</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['data_points'] ?? 0) ?></div>
                    <div class="stat-label">Data Points</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Date Range Selector -->
    <div class="date-range-selector">
        <div class="range-presets">
            <button type="button" class="range-btn active" data-range="7">Last 7 Days</button>
            <button type="button" class="range-btn" data-range="30">Last 30 Days</button>
            <button type="button" class="range-btn" data-range="90">Last 90 Days</button>
            <button type="button" class="range-btn" data-range="365">Last Year</button>
            <button type="button" class="range-btn" data-range="custom">Custom Range</button>
        </div>
        
        <div class="custom-range" id="custom-range" style="display: none;">
            <div class="date-inputs">
                <input type="date" id="start-date" class="form-input">
                <span class="date-separator">to</span>
                <input type="date" id="end-date" class="form-input">
                <button type="button" class="btn btn-outline btn-sm" id="apply-range">Apply</button>
            </div>
        </div>
    </div>
    
    <!-- Quick Stats Overview -->
    <div class="quick-stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon attendance">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-trend positive" id="attendance-trend">
                    <i class="fas fa-arrow-up"></i>
                    <span>12%</span>
                </div>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="total-checkins"><?= number_format($stats['total_checkins'] ?? 0) ?></div>
                <div class="stat-label">Total Check-ins</div>
                <div class="stat-subtitle">Across all events</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon events">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-trend positive" id="events-trend">
                    <i class="fas fa-arrow-up"></i>
                    <span>8%</span>
                </div>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="active-events"><?= number_format($stats['active_events'] ?? 0) ?></div>
                <div class="stat-label">Active Events</div>
                <div class="stat-subtitle">This period</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon users">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-trend neutral" id="users-trend">
                    <i class="fas fa-arrow-right"></i>
                    <span>2%</span>
                </div>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="active-users"><?= number_format($stats['active_users'] ?? 0) ?></div>
                <div class="stat-label">Active Users</div>
                <div class="stat-subtitle">With activity</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon attendance-rate">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-trend positive" id="rate-trend">
                    <i class="fas fa-arrow-up"></i>
                    <span>5%</span>
                </div>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="attendance-rate">84.3%</div>
                <div class="stat-label">Attendance Rate</div>
                <div class="stat-subtitle">Average across events</div>
            </div>
        </div>
    </div>
    
    <!-- Charts Section -->
    <div class="charts-section">
        <div class="charts-grid">
            
            <!-- Attendance Trends Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-line"></i>
                        Attendance Trends
                    </h3>
                    <div class="chart-actions">
                        <select id="attendance-chart-period" class="form-select chart-period">
                            <option value="daily">Daily</option>
                            <option value="weekly" selected>Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-ghost" onclick="reportsManager.exportChart('attendance')">
                            <i class="fas fa-download"></i>
                        </button>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="attendance-chart" width="400" height="200"></canvas>
                </div>
            </div>
            
            <!-- Event Types Distribution -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-pie"></i>
                        Event Types Distribution
                    </h3>
                    <div class="chart-actions">
                        <button type="button" class="btn btn-sm btn-ghost" onclick="reportsManager.exportChart('event-types')">
                            <i class="fas fa-download"></i>
                        </button>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="event-types-chart" width="400" height="200"></canvas>
                </div>
                <div class="chart-legend" id="event-types-legend">
                    <!-- Legend will be generated dynamically -->
                </div>
            </div>
            
            <!-- Peak Hours Heatmap -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="fas fa-clock"></i>
                        Peak Hours Activity
                    </h3>
                    <div class="chart-actions">
                        <select id="peak-hours-view" class="form-select chart-period">
                            <option value="hourly">Hourly</option>
                            <option value="daily">Daily</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-ghost" onclick="reportsManager.exportChart('peak-hours')">
                            <i class="fas fa-download"></i>
                        </button>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="peak-hours-chart" width="400" height="200"></canvas>
                </div>
            </div>
            
            <!-- User Engagement -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-bar"></i>
                        User Engagement
                    </h3>
                    <div class="chart-actions">
                        <select id="engagement-metric" class="form-select chart-period">
                            <option value="checkins">Check-ins</option>
                            <option value="events">Events Attended</option>
                            <option value="duration">Average Duration</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-ghost" onclick="reportsManager.exportChart('engagement')">
                            <i class="fas fa-download"></i>
                        </button>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="engagement-chart" width="400" height="200"></canvas>
                </div>
            </div>
            
        </div>
    </div>
    
    <!-- Report Categories -->
    <div class="report-categories">
        <div class="categories-grid">
            
            <!-- Attendance Reports -->
            <div class="category-card">
                <div class="category-header">
                    <div class="category-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <h3 class="category-title">Attendance Reports</h3>
                </div>
                <div class="category-description">
                    Detailed attendance tracking, check-in/out reports, and participation analysis.
                </div>
                <div class="category-reports">
                    <button type="button" class="report-btn" onclick="reportsManager.generateReport('daily-attendance')">
                        <i class="fas fa-calendar-day"></i>
                        Daily Attendance
                    </button>
                    <button type="button" class="report-btn" onclick="reportsManager.generateReport('event-attendance')">
                        <i class="fas fa-calendar-alt"></i>
                        Event Attendance
                    </button>
                    <button type="button" class="report-btn" onclick="reportsManager.generateReport('user-attendance')">
                        <i class="fas fa-user"></i>
                        User Attendance
                    </button>
                    <button type="button" class="report-btn" onclick="reportsManager.generateReport('absence-report')">
                        <i class="fas fa-user-times"></i>
                        Absence Report
                    </button>
                </div>
            </div>
            
            <!-- Event Reports -->
            <div class="category-card">
                <div class="category-header">
                    <div class="category-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3 class="category-title">Event Reports</h3>
                </div>
                <div class="category-description">
                    Event performance, capacity utilization, and event-specific analytics.
                </div>
                <div class="category-reports">
                    <button type="button" class="report-btn" onclick="reportsManager.generateReport('event-summary')">
                        <i class="fas fa-list"></i>
                        Event Summary
                    </button>
                    <button type="button" class="report-btn" onclick="reportsManager.generateReport('capacity-utilization')">
                        <i class="fas fa-chart-area"></i>
                        Capacity Utilization
                    </button>
                    <button type="button" class="report-btn" onclick="reportsManager.generateReport('event-performance')">
                        <i class="fas fa-trophy"></i>
                        Event Performance
                    </button>
                    <button type="button" class="report-btn" onclick="reportsManager.generateReport('no-show-analysis')">
                        <i class="fas fa-exclamation-triangle"></i>
                        No-Show Analysis
                    </button>
                </div>
            </div>
            
            <!-- User Reports -->
            <div class="category-card">
                <div class="category-header">
                    <div class="category-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="category-title">User Reports</h3>
                </div>
                <div class="category-description">
                    User activity, engagement metrics, and demographic analysis.
                </div>
                <div class="category-reports">
                    <button type="button" class="report-btn" onclick="reportsManager.generateReport('user-activity')">
                        <i class="fas fa-chart-line"></i>
                        User Activity
                    </button>
                    <button type="button" class="report-btn" onclick="reportsManager.generateReport('engagement-metrics')">
                        <i class="fas fa-heart"></i>
                        Engagement Metrics
                    </button>
                    <button type="button" class="report-btn" onclick="reportsManager.generateReport('user-demographics')">
                        <i class="fas fa-chart-pie"></i>
                        Demographics
                    </button>
                    <button type="button" class="report-btn" onclick="reportsManager.generateReport('rfid-usage')">
                        <i class="fas fa-id-card"></i>
                        RFID Usage
                    </button>
                </div>
            </div>
            
            <!-- System Reports -->
            <div class="category-card">
                <div class="category-header">
                    <div class="category-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <h3 class="category-title">System Reports</h3>
                </div>
                <div class="category-description">
                    System performance, security logs, and operational metrics.
                </div>
                <div class="category-reports">
                    <button type="button" class="report-btn" onclick="reportsManager.generateReport('system-performance')">
                        <i class="fas fa-tachometer-alt"></i>
                        System Performance
                    </button>
                    <button type="button" class="report-btn" onclick="reportsManager.generateReport('security-audit')">
                        <i class="fas fa-shield-alt"></i>
                        Security Audit
                    </button>
                    <button type="button" class="report-btn" onclick="reportsManager.generateReport('device-status')">
                        <i class="fas fa-wifi"></i>
                        Device Status
                    </button>
                    <button type="button" class="report-btn" onclick="reportsManager.generateReport('error-log')">
                        <i class="fas fa-exclamation-circle"></i>
                        Error Log
                    </button>
                </div>
            </div>
            
        </div>
    </div>
    
    <!-- Recent Reports -->
    <div class="recent-reports-section">
        <div class="section-header">
            <h3 class="section-title">
                <i class="fas fa-history"></i>
                Recent Reports
            </h3>
            <div class="section-actions">
                <button type="button" class="btn btn-outline btn-sm" onclick="reportsManager.viewAllReports()">
                    <i class="fas fa-list"></i>
                    View All
                </button>
            </div>
        </div>
        
        <div class="recent-reports-list" id="recent-reports-list">
            <!-- Recent reports will be loaded here -->
            <div class="loading-content">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Loading recent reports...</span>
            </div>
        </div>
    </div>
    
</div>

<!-- Report Generation Modal -->
<div class="modal" id="report-generation-modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-chart-bar"></i>
                    Generate Report
                </h3>
                <button type="button" class="modal-close" data-modal-close>
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="report-generation-content">
                    <div class="generation-status" id="generation-status">
                        <div class="status-icon">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                        <div class="status-text">
                            <h4 id="status-title">Generating Report</h4>
                            <p id="status-description">Please wait while we prepare your report...</p>
                        </div>
                    </div>
                    
                    <div class="generation-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progress-fill"></div>
                        </div>
                        <div class="progress-text">
                            <span id="progress-percentage">0%</span>
                            <span id="progress-step">Initializing...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close id="cancel-generation">Cancel</button>
                <button type="button" class="btn btn-primary" id="download-report" style="display: none;">
                    <i class="fas fa-download"></i>
                    Download Report
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Custom Report Builder Modal -->
<div class="modal" id="custom-report-modal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-plus"></i>
                    Custom Report Builder
                </h3>
                <button type="button" class="modal-close" data-modal-close>
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="custom-report-form" class="form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="report-name" class="form-label">Report Name</label>
                            <input type="text" id="report-name" name="report_name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label for="report-format" class="form-label">Format</label>
                            <select id="report-format" name="format" class="form-select" required>
                                <option value="pdf">PDF</option>
                                <option value="excel">Excel</option>
                                <option value="csv">CSV</option>
                                <option value="json">JSON</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="report-description" class="form-label">Description</label>
                        <textarea id="report-description" name="description" class="form-textarea" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Data Sources</label>
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="data_sources[]" value="users" class="checkbox-input">
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Users</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="data_sources[]" value="events" class="checkbox-input">
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Events</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="data_sources[]" value="checkins" class="checkbox-input">
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Check-ins</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="data_sources[]" value="rfid" class="checkbox-input">
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">RFID Activity</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="custom-start-date" class="form-label">Start Date</label>
                            <input type="date" id="custom-start-date" name="start_date" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label for="custom-end-date" class="form-label">End Date</label>
                            <input type="date" id="custom-end-date" name="end_date" class="form-input" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Filters</label>
                        <div class="filters-builder" id="filters-builder">
                            <button type="button" class="btn btn-outline btn-sm" id="add-filter">
                                <i class="fas fa-plus"></i>
                                Add Filter
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Output Options</label>
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="include_charts" class="checkbox-input">
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Include Charts</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="include_summary" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Include Summary</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="email_report" class="checkbox-input">
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Email Report</span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="button" class="btn btn-outline" id="preview-custom-report">
                    <i class="fas fa-eye"></i>
                    Preview
                </button>
                <button type="button" class="btn btn-primary" id="generate-custom-report">
                    <i class="fas fa-chart-bar"></i>
                    Generate Report
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reports Management JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const reportsManager = new ReportsManager();
    reportsManager.init();
});

class ReportsManager {
    constructor() {
        this.currentDateRange = 7;
        this.customStartDate = null;
        this.customEndDate = null;
        this.charts = {};
        this.reportGenerationId = null;
    }
    
    init() {
        this.setupEventListeners();
        this.loadQuickStats();
        this.initializeCharts();
        this.loadRecentReports();
    }
    
    setupEventListeners() {
        // Date range selection
        document.querySelectorAll('.range-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.selectDateRange(e.target.dataset.range);
            });
        });
        
        // Custom date range
        document.getElementById('apply-range').addEventListener('click', () => {
            this.applyCustomRange();
        });
        
        // Chart period changes
        document.getElementById('attendance-chart-period').addEventListener('change', (e) => {
            this.updateAttendanceChart(e.target.value);
        });
        
        document.getElementById('peak-hours-view').addEventListener('change', (e) => {
            this.updatePeakHoursChart(e.target.value);
        });
        
        document.getElementById('engagement-metric').addEventListener('change', (e) => {
            this.updateEngagementChart(e.target.value);
        });
        
        // Report generation
        document.getElementById('cancel-generation').addEventListener('click', () => {
            this.cancelReportGeneration();
        });
        
        document.getElementById('download-report').addEventListener('click', () => {
            this.downloadGeneratedReport();
        });
        
        // Custom report builder
        document.getElementById('add-filter').addEventListener('click', () => {
            this.addCustomFilter();
        });
        
        document.getElementById('preview-custom-report').addEventListener('click', () => {
            this.previewCustomReport();
        });
        
        document.getElementById('generate-custom-report').addEventListener('click', () => {
            this.generateCustomReport();
        });
    }
    
    selectDateRange(range) {
        // Update active button
        document.querySelectorAll('.range-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-range="${range}"]`).classList.add('active');
        
        const customRange = document.getElementById('custom-range');
        
        if (range === 'custom') {
            customRange.style.display = 'block';
            this.setupCustomDateRange();
        } else {
            customRange.style.display = 'none';
            this.currentDateRange = parseInt(range);
            this.updateDateRangeText();
            this.refreshData();
        }
    }
    
    setupCustomDateRange() {
        const today = new Date();
        const weekAgo = new Date(today.getTime() - (7 * 24 * 60 * 60 * 1000));
        
        document.getElementById('end-date').value = today.toISOString().split('T')[0];
        document.getElementById('start-date').value = weekAgo.toISOString().split('T')[0];
    }
    
    applyCustomRange() {
        const startDate = document.getElementById('start-date').value;
        const endDate = document.getElementById('end-date').value;
        
        if (!startDate || !endDate) {
            this.showError('Please select both start and end dates.');
            return;
        }
        
        if (new Date(startDate) > new Date(endDate)) {
            this.showError('Start date must be before end date.');
            return;
        }
        
        this.customStartDate = startDate;
        this.customEndDate = endDate;
        this.currentDateRange = 'custom';
        
        this.updateDateRangeText();
        this.refreshData();
    }
    
    updateDateRangeText() {
        const textElement = document.getElementById('data-range-text');
        
        if (this.currentDateRange === 'custom') {
            const start = new Date(this.customStartDate).toLocaleDateString();
            const end = new Date(this.customEndDate).toLocaleDateString();
            textElement.textContent = `${start} to ${end}`;
        } else {
            textElement.textContent = `last ${this.currentDateRange} days`;
        }
    }
    
    async refreshData() {
        await Promise.all([
            this.loadQuickStats(),
            this.updateAllCharts(),
            this.loadRecentReports()
        ]);
    }
    
    async loadQuickStats() {
        try {
            const params = new URLSearchParams();
            
            if (this.currentDateRange === 'custom') {
                params.append('start_date', this.customStartDate);
                params.append('end_date', this.customEndDate);
            } else {
                params.append('days', this.currentDateRange);
            }
            
            const response = await fetch(`/api/admin/reports/quick-stats?${params}`, {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateQuickStats(data.data);
            } else {
                this.showError('Failed to load statistics: ' + data.message);
            }
        } catch (error) {
            console.error('Error loading quick stats:', error);
            this.showError('Failed to load statistics.');
        }
    }
    
    updateQuickStats(stats) {
        // Update stat values
        document.getElementById('total-checkins').textContent = this.formatNumber(stats.total_checkins);
        document.getElementById('active-events').textContent = this.formatNumber(stats.active_events);
        document.getElementById('active-users').textContent = this.formatNumber(stats.active_users);
        document.getElementById('attendance-rate').textContent = stats.attendance_rate + '%';
        
        // Update trends
        this.updateTrendIndicator('attendance-trend', stats.checkins_trend);
        this.updateTrendIndicator('events-trend', stats.events_trend);
        this.updateTrendIndicator('users-trend', stats.users_trend);
        this.updateTrendIndicator('rate-trend', stats.rate_trend);
    }
    
    updateTrendIndicator(elementId, trend) {
        const element = document.getElementById(elementId);
        const icon = element.querySelector('i');
        const span = element.querySelector('span');
        
        element.className = `stat-trend ${trend.direction}`;
        
        if (trend.direction === 'positive') {
            icon.className = 'fas fa-arrow-up';
        } else if (trend.direction === 'negative') {
            icon.className = 'fas fa-arrow-down';
        } else {
            icon.className = 'fas fa-arrow-right';
        }
        
        span.textContent = Math.abs(trend.percentage) + '%';
    }
    
    async initializeCharts() {
        await this.loadChartData();
        
        this.initAttendanceChart();
        this.initEventTypesChart();
        this.initPeakHoursChart();
        this.initEngagementChart();
    }
    
    async loadChartData() {
        try {
            const params = new URLSearchParams();
            
            if (this.currentDateRange === 'custom') {
                params.append('start_date', this.customStartDate);
                params.append('end_date', this.customEndDate);
            } else {
                params.append('days', this.currentDateRange);
            }
            
            const response = await fetch(`/api/admin/reports/chart-data?${params}`, {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.chartData = data.data;
            } else {
                this.showError('Failed to load chart data: ' + data.message);
            }
        } catch (error) {
            console.error('Error loading chart data:', error);
            this.showError('Failed to load chart data.');
        }
    }
    
    initAttendanceChart() {
        const ctx = document.getElementById('attendance-chart').getContext('2d');
        
        this.charts.attendance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: this.chartData.attendance.labels,
                datasets: [{
                    label: 'Check-ins',
                    data: this.chartData.attendance.data,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
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
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    
    initEventTypesChart() {
        const ctx = document.getElementById('event-types-chart').getContext('2d');
        
        this.charts.eventTypes = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: this.chartData.event_types.labels,
                datasets: [{
                    data: this.chartData.event_types.data,
                    backgroundColor: [
                        '#4f46e5',
                        '#06b6d4',
                        '#10b981',
                        '#f59e0b',
                        '#ef4444',
                        '#8b5cf6'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        this.updateEventTypesLegend();
    }
    
    updateEventTypesLegend() {
        const legend = document.getElementById('event-types-legend');
        const data = this.chartData.event_types;
        
        legend.innerHTML = data.labels.map((label, index) => `
            <div class="legend-item">
                <div class="legend-color" style="background-color: ${this.charts.eventTypes.data.datasets[0].backgroundColor[index]}"></div>
                <div class="legend-label">${label}</div>
                <div class="legend-value">${data.data[index]}</div>
            </div>
        `).join('');
    }
    
    initPeakHoursChart() {
        const ctx = document.getElementById('peak-hours-chart').getContext('2d');
        
        this.charts.peakHours = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: this.chartData.peak_hours.labels,
                datasets: [{
                    label: 'Activity',
                    data: this.chartData.peak_hours.data,
                    backgroundColor: 'rgba(79, 70, 229, 0.8)',
                    borderColor: '#4f46e5',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    
    initEngagementChart() {
        const ctx = document.getElementById('engagement-chart').getContext('2d');
        
        this.charts.engagement = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: this.chartData.engagement.labels,
                datasets: [{
                    label: 'Engagement',
                    data: this.chartData.engagement.data,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: '#10b981',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    
    async updateAllCharts() {
        await this.loadChartData();
        
        // Update each chart
        if (this.charts.attendance) {
            this.charts.attendance.data.labels = this.chartData.attendance.labels;
            this.charts.attendance.data.datasets[0].data = this.chartData.attendance.data;
            this.charts.attendance.update();
        }
        
        if (this.charts.eventTypes) {
            this.charts.eventTypes.data.labels = this.chartData.event_types.labels;
            this.charts.eventTypes.data.datasets[0].data = this.chartData.event_types.data;
            this.charts.eventTypes.update();
            this.updateEventTypesLegend();
        }
        
        if (this.charts.peakHours) {
            this.charts.peakHours.data.labels = this.chartData.peak_hours.labels;
            this.charts.peakHours.data.datasets[0].data = this.chartData.peak_hours.data;
            this.charts.peakHours.update();
        }
        
        if (this.charts.engagement) {
            this.charts.engagement.data.labels = this.chartData.engagement.labels;
            this.charts.engagement.data.datasets[0].data = this.chartData.engagement.data;
            this.charts.engagement.update();
        }
    }
    
    async generateReport(reportType) {
        try {
            this.showModal('report-generation-modal');
            this.resetGenerationProgress();
            
            const params = new URLSearchParams();
            params.append('type', reportType);
            
            if (this.currentDateRange === 'custom') {
                params.append('start_date', this.customStartDate);
                params.append('end_date', this.customEndDate);
            } else {
                params.append('days', this.currentDateRange);
            }
            
            const response = await fetch('/api/admin/reports/generate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': window.App.csrfToken
                },
                body: params
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.reportGenerationId = data.data.generation_id;
                this.trackReportGeneration();
            } else {
                this.showGenerationError('Failed to start report generation: ' + data.message);
            }
        } catch (error) {
            console.error('Error generating report:', error);
            this.showGenerationError('Failed to generate report.');
        }
    }
    
    resetGenerationProgress() {
        document.getElementById('status-title').textContent = 'Generating Report';
        document.getElementById('status-description').textContent = 'Please wait while we prepare your report...';
        document.getElementById('progress-fill').style.width = '0%';
        document.getElementById('progress-percentage').textContent = '0%';
        document.getElementById('progress-step').textContent = 'Initializing...';
        document.getElementById('download-report').style.display = 'none';
        document.getElementById('cancel-generation').style.display = 'inline-block';
    }
    
    async trackReportGeneration() {
        if (!this.reportGenerationId) return;
        
        try {
            const response = await fetch(`/api/admin/reports/generation/${this.reportGenerationId}/status`, {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateGenerationProgress(data.data);
                
                if (data.data.status === 'completed') {
                    this.showGenerationComplete(data.data);
                } else if (data.data.status === 'failed') {
                    this.showGenerationError(data.data.error || 'Report generation failed.');
                } else {
                    // Continue tracking
                    setTimeout(() => this.trackReportGeneration(), 1000);
                }
            } else {
                this.showGenerationError('Failed to track report generation.');
            }
        } catch (error) {
            console.error('Error tracking report generation:', error);
            this.showGenerationError('Failed to track report generation.');
        }
    }
    
    updateGenerationProgress(progress) {
        document.getElementById('progress-fill').style.width = progress.percentage + '%';
        document.getElementById('progress-percentage').textContent = progress.percentage + '%';
        document.getElementById('progress-step').textContent = progress.step;
    }
    
    showGenerationComplete(data) {
        document.getElementById('status-title').textContent = 'Report Complete';
        document.getElementById('status-description').textContent = 'Your report has been generated successfully.';
        document.getElementById('cancel-generation').style.display = 'none';
        document.getElementById('download-report').style.display = 'inline-block';
        document.getElementById('download-report').dataset.downloadUrl = data.download_url;
    }
    
    showGenerationError(message) {
        document.getElementById('status-title').textContent = 'Generation Failed';
        document.getElementById('status-description').textContent = message;
        document.getElementById('cancel-generation').textContent = 'Close';
    }
    
    cancelReportGeneration() {
        if (this.reportGenerationId) {
            fetch(`/api/admin/reports/generation/${this.reportGenerationId}/cancel`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
        }
        
        this.hideModal('report-generation-modal');
        this.reportGenerationId = null;
    }
    
    downloadGeneratedReport() {
        const downloadUrl = document.getElementById('download-report').dataset.downloadUrl;
        if (downloadUrl) {
            window.open(downloadUrl, '_blank');
        }
        this.hideModal('report-generation-modal');
    }
    
    async loadRecentReports() {
        try {
            const response = await fetch('/api/admin/reports/recent', {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.renderRecentReports(data.data.reports);
            } else {
                this.showError('Failed to load recent reports: ' + data.message);
            }
        } catch (error) {
            console.error('Error loading recent reports:', error);
            this.showError('Failed to load recent reports.');
        }
    }
    
    renderRecentReports(reports) {
        const container = document.getElementById('recent-reports-list');
        
        if (reports.length === 0) {
            container.innerHTML = `
                <div class="no-reports">
                    <i class="fas fa-chart-bar"></i>
                    <p>No recent reports found.</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = reports.map(report => `
            <div class="recent-report-item">
                <div class="report-info">
                    <div class="report-title">${this.escapeHtml(report.name)}</div>
                    <div class="report-meta">
                        <span class="report-type">${this.capitalizeFirst(report.type)}</span>
                        <span class="report-date">${this.formatRelativeTime(report.created_at)}</span>
                        <span class="report-size">${this.formatFileSize(report.file_size)}</span>
                    </div>
                </div>
                <div class="report-actions">
                    <button type="button" class="btn btn-sm btn-ghost" onclick="reportsManager.downloadReport('${report.report_id}')">
                        <i class="fas fa-download"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-ghost" onclick="reportsManager.shareReport('${report.report_id}')">
                        <i class="fas fa-share"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-ghost" onclick="reportsManager.deleteReport('${report.report_id}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');
    }
    
    // Custom report builder
    createCustomReport() {
        this.showModal('custom-report-modal');
        this.setupCustomReportForm();
    }
    
    setupCustomReportForm() {
        const today = new Date();
        const weekAgo = new Date(today.getTime() - (7 * 24 * 60 * 60 * 1000));
        
        document.getElementById('custom-end-date').value = today.toISOString().split('T')[0];
        document.getElementById('custom-start-date').value = weekAgo.toISOString().split('T')[0];
    }
    
    addCustomFilter() {
        const container = document.getElementById('filters-builder');
        const filterCount = container.querySelectorAll('.filter-row').length;
        
        const filterRow = document.createElement('div');
        filterRow.className = 'filter-row';
        filterRow.innerHTML = `
            <div class="filter-controls">
                <select class="form-select filter-field" name="filters[${filterCount}][field]">
                    <option value="">Select field...</option>
                    <option value="user_role">User Role</option>
                    <option value="event_type">Event Type</option>
                    <option value="checkin_method">Check-in Method</option>
                    <option value="date_range">Date Range</option>
                </select>
                <select class="form-select filter-operator" name="filters[${filterCount}][operator]">
                    <option value="equals">Equals</option>
                    <option value="not_equals">Not Equals</option>
                    <option value="contains">Contains</option>
                    <option value="greater_than">Greater Than</option>
                    <option value="less_than">Less Than</option>
                </select>
                <input type="text" class="form-input filter-value" name="filters[${filterCount}][value]" placeholder="Value">
                <button type="button" class="btn btn-sm btn-ghost remove-filter" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        container.appendChild(filterRow);
    }
    
    async generateCustomReport() {
        const form = document.getElementById('custom-report-form');
        const formData = new FormData(form);
        
        if (!formData.get('report_name')) {
            this.showError('Please enter a report name.');
            return;
        }
        
        try {
            this.hideModal('custom-report-modal');
            this.showModal('report-generation-modal');
            this.resetGenerationProgress();
            
            const response = await fetch('/api/admin/reports/custom', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                },
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.reportGenerationId = data.data.generation_id;
                this.trackReportGeneration();
            } else {
                this.showGenerationError('Failed to generate custom report: ' + data.message);
            }
        } catch (error) {
            console.error('Error generating custom report:', error);
            this.showGenerationError('Failed to generate custom report.');
        }
    }
    
    // Utility methods
    formatNumber(num) {
        return new Intl.NumberFormat().format(num);
    }
    
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
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
    
    capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('modal-show');
            document.body.classList.add('modal-open');
        }
    }
    
    hideModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('modal-show');
            document.body.classList.remove('modal-open');
        }
    }
    
    showSuccess(message) {
        if (typeof window.addFlashMessage === 'function') {
            window.addFlashMessage('success', message);
        }
    }
    
    showError(message) {
        if (typeof window.addFlashMessage === 'function') {
            window.addFlashMessage('error', message);
        }
    }
}

// Expose reportsManager globally
window.reportsManager = null;
document.addEventListener('DOMContentLoaded', function() {
    window.reportsManager = new ReportsManager();
});
</script>
