<?php $current_page = 'overview'; ?>
<?php ob_start(); ?>

<!-- Performance Overview Dashboard -->
<div class="performance-overview">
    <!-- Key Metrics Cards -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-header">
                <h3>Response Time</h3>
                <i class="icon-clock"></i>
            </div>
            <div class="metric-value">
                <span class="value" id="avg-response-time"><?= $performance_summary['avg_response_time'] ?? '0' ?>ms</span>
                <span class="trend trend-up">↑ 15%</span>
            </div>
            <div class="metric-chart">
                <canvas id="responseTimeChart" width="100" height="40"></canvas>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-header">
                <h3>Cache Hit Rate</h3>
                <i class="icon-cache"></i>
            </div>
            <div class="metric-value">
                <span class="value" id="cache-hit-rate-display"><?= $cache_stats['hit_rate'] ?? '0' ?>%</span>
                <span class="trend trend-up">↑ 8%</span>
            </div>
            <div class="metric-chart">
                <canvas id="cacheHitChart" width="100" height="40"></canvas>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-header">
                <h3>Memory Usage</h3>
                <i class="icon-memory"></i>
            </div>
            <div class="metric-value">
                <span class="value" id="memory-usage-display"><?= $system_info['memory_usage_percent'] ?? '0' ?>%</span>
                <span class="trend trend-down">↓ 3%</span>
            </div>
            <div class="metric-chart">
                <canvas id="memoryUsageChart" width="100" height="40"></canvas>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-header">
                <h3>Database Performance</h3>
                <i class="icon-database"></i>
            </div>
            <div class="metric-value">
                <span class="value" id="db-performance"><?= $db_metrics['avg_query_time'] ?? '0' ?>ms</span>
                <span class="trend trend-stable">→ 0%</span>
            </div>
            <div class="metric-chart">
                <canvas id="dbPerformanceChart" width="100" height="40"></canvas>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-header">
                <h3>Asset Optimization</h3>
                <i class="icon-assets"></i>
            </div>
            <div class="metric-value">
                <span class="value" id="asset-optimization"><?= $asset_stats['compression_ratio'] ?? '0' ?>%</span>
                <span class="trend trend-up">↑ 12%</span>
            </div>
            <div class="metric-chart">
                <canvas id="assetOptChart" width="100" height="40"></canvas>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-header">
                <h3>System Load</h3>
                <i class="icon-cpu"></i>
            </div>
            <div class="metric-value">
                <span class="value" id="system-load"><?= $system_info['load_average_1min'] ?? '0.0' ?></span>
                <span class="trend trend-stable">→ 2%</span>
            </div>
            <div class="metric-chart">
                <canvas id="systemLoadChart" width="100" height="40"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Performance Charts Section -->
    <div class="charts-section">
        <div class="chart-row">
            <div class="chart-container">
                <div class="chart-header">
                    <h3>Response Time Trends</h3>
                    <div class="chart-controls">
                        <select class="form-control" id="responseTimeRange">
                            <option value="1h">Last Hour</option>
                            <option value="24h" selected>Last 24 Hours</option>
                            <option value="7d">Last 7 Days</option>
                        </select>
                    </div>
                </div>
                <div class="chart-content">
                    <canvas id="responseTimeTrendChart"></canvas>
                </div>
            </div>
            
            <div class="chart-container">
                <div class="chart-header">
                    <h3>Cache Performance</h3>
                    <div class="chart-controls">
                        <button class="btn btn-sm btn-outline" onclick="refreshCacheChart()">Refresh</button>
                    </div>
                </div>
                <div class="chart-content">
                    <canvas id="cachePerformanceChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="chart-row">
            <div class="chart-container">
                <div class="chart-header">
                    <h3>System Resources</h3>
                    <div class="chart-legend">
                        <span class="legend-item"><span class="legend-color cpu"></span>CPU</span>
                        <span class="legend-item"><span class="legend-color memory"></span>Memory</span>
                        <span class="legend-item"><span class="legend-color disk"></span>Disk</span>
                    </div>
                </div>
                <div class="chart-content">
                    <canvas id="systemResourcesChart"></canvas>
                </div>
            </div>
            
            <div class="chart-container">
                <div class="chart-header">
                    <h3>Database Metrics</h3>
                    <div class="chart-controls">
                        <button class="btn btn-sm btn-outline" onclick="showSlowQueries()">View Slow Queries</button>
                    </div>
                </div>
                <div class="chart-content">
                    <canvas id="databaseMetricsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Performance Recommendations -->
    <div class="recommendations-section">
        <div class="section-header">
            <h3>Performance Recommendations</h3>
            <button class="btn btn-primary" onclick="runPerformanceAnalysis()">Run Analysis</button>
        </div>
        
        <div class="recommendations-grid">
            <?php if (!empty($recommendations)): ?>
                <?php foreach ($recommendations as $recommendation): ?>
                    <div class="recommendation-card priority-<?= htmlspecialchars($recommendation['priority']) ?>">
                        <div class="recommendation-header">
                            <h4><?= htmlspecialchars($recommendation['title']) ?></h4>
                            <span class="priority-badge"><?= ucfirst(htmlspecialchars($recommendation['priority'])) ?></span>
                        </div>
                        <p class="recommendation-description">
                            <?= htmlspecialchars($recommendation['description']) ?>
                        </p>
                        <div class="recommendation-metrics">
                            <span class="metric">
                                <strong>Impact:</strong> <?= htmlspecialchars($recommendation['impact']) ?>
                            </span>
                            <span class="metric">
                                <strong>Difficulty:</strong> <?= htmlspecialchars($recommendation['difficulty']) ?>
                            </span>
                        </div>
                        <div class="recommendation-actions">
                            <button class="btn btn-sm btn-primary" onclick="applyRecommendation('<?= htmlspecialchars($recommendation['id'] ?? '') ?>')">
                                Apply
                            </button>
                            <button class="btn btn-sm btn-outline" onclick="learnMore('<?= htmlspecialchars($recommendation['id'] ?? '') ?>')">
                                Learn More
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-recommendations">
                    <i class="icon-check-circle"></i>
                    <h4>No Performance Issues Detected</h4>
                    <p>Your system is running optimally. Keep up the good work!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="activity-section">
        <div class="section-header">
            <h3>Recent Performance Activity</h3>
            <div class="activity-filters">
                <select class="form-control" id="activityFilter">
                    <option value="all">All Activities</option>
                    <option value="optimizations">Optimizations</option>
                    <option value="cache">Cache Operations</option>
                    <option value="database">Database Operations</option>
                    <option value="alerts">Performance Alerts</option>
                </select>
            </div>
        </div>
        
        <div class="activity-timeline">
            <div class="timeline-item">
                <div class="timeline-icon success">
                    <i class="icon-optimize"></i>
                </div>
                <div class="timeline-content">
                    <h4>Cache Optimization Completed</h4>
                    <p>Cache hit rate improved from 72% to 87%. Response time reduced by 23%.</p>
                    <span class="timeline-time">2 hours ago</span>
                </div>
            </div>
            
            <div class="timeline-item">
                <div class="timeline-icon info">
                    <i class="icon-database"></i>
                </div>
                <div class="timeline-content">
                    <h4>Database Index Added</h4>
                    <p>Added index on access_logs.created_at. Query performance improved by 45%.</p>
                    <span class="timeline-time">4 hours ago</span>
                </div>
            </div>
            
            <div class="timeline-item">
                <div class="timeline-icon warning">
                    <i class="icon-alert"></i>
                </div>
                <div class="timeline-content">
                    <h4>High Memory Usage Alert</h4>
                    <p>Memory usage exceeded 85%. Automatic cleanup performed.</p>
                    <span class="timeline-time">6 hours ago</span>
                </div>
            </div>
            
            <div class="timeline-item">
                <div class="timeline-icon success">
                    <i class="icon-assets"></i>
                </div>
                <div class="timeline-content">
                    <h4>Asset Optimization Completed</h4>
                    <p>15 CSS and 8 JS files optimized. Total size reduced by 58%.</p>
                    <span class="timeline-time">8 hours ago</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions Panel -->
    <div class="quick-actions-panel">
        <div class="panel-header">
            <h3>Quick Actions</h3>
        </div>
        <div class="actions-grid">
            <button class="action-btn" onclick="runOptimizationSuite()">
                <i class="icon-optimize"></i>
                <span>Run Full Optimization</span>
            </button>
            <button class="action-btn" onclick="showCacheManagement()">
                <i class="icon-cache"></i>
                <span>Manage Cache</span>
            </button>
            <button class="action-btn" onclick="optimizeDatabase()">
                <i class="icon-database"></i>
                <span>Optimize Database</span>
            </button>
            <button class="action-btn" onclick="optimizeAssets()">
                <i class="icon-assets"></i>
                <span>Optimize Assets</span>
            </button>
            <button class="action-btn" onclick="generateReport()">
                <i class="icon-report"></i>
                <span>Generate Report</span>
            </button>
            <button class="action-btn" onclick="viewLogs()">
                <i class="icon-logs"></i>
                <span>View Logs</span>
            </button>
        </div>
    </div>
</div>

<!-- JavaScript for Dashboard -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboardCharts();
    startRealtimeMetrics();
    loadPerformanceData();
});

function initializeDashboardCharts() {
    // Initialize small metric charts
    initializeSparklineCharts();
    
    // Initialize main dashboard charts
    initializeResponseTimeTrend();
    initializeCachePerformanceChart();
    initializeSystemResourcesChart();
    initializeDatabaseMetricsChart();
}

function initializeSparklineCharts() {
    const chartConfigs = [
        { id: 'responseTimeChart', color: '#3498db' },
        { id: 'cacheHitChart', color: '#2ecc71' },
        { id: 'memoryUsageChart', color: '#e74c3c' },
        { id: 'dbPerformanceChart', color: '#f39c12' },
        { id: 'assetOptChart', color: '#9b59b6' },
        { id: 'systemLoadChart', color: '#1abc9c' }
    ];
    
    chartConfigs.forEach(config => {
        const ctx = document.getElementById(config.id);
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: Array.from({length: 20}, (_, i) => ''),
                    datasets: [{
                        data: generateSparklineData(),
                        borderColor: config.color,
                        borderWidth: 1,
                        fill: false,
                        pointRadius: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { display: false },
                        y: { display: false }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }
    });
}

function generateSparklineData() {
    return Array.from({length: 20}, () => Math.random() * 100);
}

function initializeResponseTimeTrend() {
    const ctx = document.getElementById('responseTimeTrendChart');
    if (ctx) {
        window.responseTimeTrendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Average Response Time',
                    data: [],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.1
                }, {
                    label: '95th Percentile',
                    data: [],
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Response Time (ms)' }
                    },
                    x: {
                        title: { display: true, text: 'Time' }
                    }
                }
            }
        });
    }
}

function initializeCachePerformanceChart() {
    const ctx = document.getElementById('cachePerformanceChart');
    if (ctx) {
        window.cachePerformanceChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Cache Hits', 'Cache Misses'],
                datasets: [{
                    data: [<?= $cache_stats['hits'] ?? 0 ?>, <?= $cache_stats['misses'] ?? 0 ?>],
                    backgroundColor: ['#2ecc71', '#e74c3c']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }
}

function initializeSystemResourcesChart() {
    const ctx = document.getElementById('systemResourcesChart');
    if (ctx) {
        window.systemResourcesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'CPU Usage (%)',
                    data: [],
                    borderColor: '#f39c12',
                    backgroundColor: 'rgba(243, 156, 18, 0.1)'
                }, {
                    label: 'Memory Usage (%)',
                    data: [],
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)'
                }, {
                    label: 'Disk Usage (%)',
                    data: [],
                    borderColor: '#9b59b6',
                    backgroundColor: 'rgba(155, 89, 182, 0.1)'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: { display: true, text: 'Usage (%)' }
                    }
                }
            }
        });
    }
}

function initializeDatabaseMetricsChart() {
    const ctx = document.getElementById('databaseMetricsChart');
    if (ctx) {
        window.databaseMetricsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['SELECT', 'INSERT', 'UPDATE', 'DELETE'],
                datasets: [{
                    label: 'Average Query Time (ms)',
                    data: [<?= $db_metrics['select_avg'] ?? 0 ?>, <?= $db_metrics['insert_avg'] ?? 0 ?>, <?= $db_metrics['update_avg'] ?? 0 ?>, <?= $db_metrics['delete_avg'] ?? 0 ?>],
                    backgroundColor: ['#3498db', '#2ecc71', '#f39c12', '#e74c3c']
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Time (ms)' }
                    }
                }
            }
        });
    }
}

function startRealtimeMetrics() {
    setInterval(updateRealtimeMetrics, 5000);
}

function updateRealtimeMetrics() {
    fetch('/api/performance/realtime-metrics')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateMetricValues(data.data);
                updateChartData(data.data);
            }
        })
        .catch(error => {
            console.error('Failed to update realtime metrics:', error);
        });
}

function updateMetricValues(data) {
    // Update metric displays
    const elements = {
        'avg-response-time': data.execution_time ? Math.round(data.execution_time * 1000) + 'ms' : '--ms',
        'cache-hit-rate-display': data.cache_hit_rate ? Math.round(data.cache_hit_rate) + '%' : '--%',
        'memory-usage-display': data.memory_usage_percent ? Math.round(data.memory_usage_percent) + '%' : '--%',
        'system-load': data.server_load ? data.server_load['1_minute'] : '--'
    };
    
    Object.entries(elements).forEach(([id, value]) => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    });
}

function updateChartData(data) {
    // Update charts with new data
    // This would involve adding new data points and removing old ones
    // Implementation depends on specific chart requirements
}

function loadPerformanceData() {
    fetch('/api/performance/dashboard')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateDashboardData(data.data);
            }
        })
        .catch(error => {
            console.error('Failed to load performance data:', error);
        });
}

function updateDashboardData(data) {
    // Update dashboard with comprehensive performance data
    // This would update all charts and metrics with fresh data
}

// Action functions
function runPerformanceAnalysis() {
    showLoading('Running performance analysis...');
    
    fetch('/api/performance/analyze', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            // Refresh recommendations
            location.reload();
        } else {
            showAlert('Failed to run performance analysis', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Failed to run performance analysis', 'error');
    });
}

function applyRecommendation(recommendationId) {
    if (confirm('Are you sure you want to apply this recommendation?')) {
        showLoading('Applying recommendation...');
        
        fetch('/api/performance/apply-recommendation', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: recommendationId })
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showAlert('Recommendation applied successfully', 'success');
                // Refresh the page to show updated data
                setTimeout(() => location.reload(), 2000);
            } else {
                showAlert('Failed to apply recommendation', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            showAlert('Failed to apply recommendation', 'error');
        });
    }
}

function optimizeDatabase() {
    if (confirm('This will optimize database indexes and perform maintenance. Continue?')) {
        showLoading('Optimizing database...');
        
        fetch('/api/performance/optimize-database', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showAlert('Database optimization completed', 'success');
            } else {
                showAlert('Database optimization failed', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            showAlert('Database optimization failed', 'error');
        });
    }
}

function optimizeAssets() {
    showLoading('Optimizing assets...');
    
    fetch('/api/performance/optimize-assets', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAlert('Asset optimization completed', 'success');
        } else {
            showAlert('Asset optimization failed', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Asset optimization failed', 'error');
    });
}

function viewLogs() {
    window.open('/admin/logs?filter=performance', '_blank');
}

function showSlowQueries() {
    fetch('/api/performance/slow-queries')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displaySlowQueries(data.data);
            }
        })
        .catch(error => {
            console.error('Failed to load slow queries:', error);
        });
}

function refreshCacheChart() {
    fetch('/api/performance/cache-stats')
        .then(response => response.json())
        .then(data => {
            if (data.success && window.cachePerformanceChart) {
                window.cachePerformanceChart.data.datasets[0].data = [
                    data.data.hits || 0,
                    data.data.misses || 0
                ];
                window.cachePerformanceChart.update();
            }
        })
        .catch(error => {
            console.error('Failed to refresh cache chart:', error);
        });
}
</script>

<?php $content = ob_get_clean(); ?>
<?php include 'layout.php'; ?>
