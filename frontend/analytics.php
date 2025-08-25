<?php
/**
 * Analytics Dashboard
 * Comprehensive analytics with charts and reports
 */

require_once '../core/auth.php';
require_once '../core/database.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();
$db = getDB();

// Get date range filter
$date_range = $_GET['range'] ?? '30';
$custom_start = $_GET['custom_start'] ?? '';
$custom_end = $_GET['custom_end'] ?? '';

// Build date filter
$date_condition = '';
$date_params = [];

switch ($date_range) {
    case '7':
        $date_condition = "AND c.checkin_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case '30':
        $date_condition = "AND c.checkin_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case '90':
        $date_condition = "AND c.checkin_time >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
        break;
    case '365':
        $date_condition = "AND c.checkin_time >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
        break;
    case 'custom':
        if ($custom_start && $custom_end) {
            $date_condition = "AND DATE(c.checkin_time) BETWEEN ? AND ?";
            $date_params = [$custom_start, $custom_end];
        }
        break;
}

// Get user's personal analytics
$user_condition = "AND c.user_id = ?";
$user_params = [$user['user_id']];

// Admin can view system-wide analytics
$view_mode = $_GET['view'] ?? 'personal';
if ($user['role'] === 'admin' && $view_mode === 'system') {
    $user_condition = '';
    $user_params = [];
}

$all_params = array_merge($user_params, $date_params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Electronic Check-in System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <link rel="stylesheet" href="../assets/css/analytics.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="main-content">
        <div class="analytics-header">
            <div>
                <h1>📈 Analytics</h1>
                <p class="subtitle"><?php echo $view_mode === 'system' ? 'System-wide' : 'Personal'; ?> check-in analytics and insights</p>
            </div>
            
            <?php if ($user['role'] === 'admin'): ?>
                <div class="view-selector">
                    <a href="?view=personal&<?php echo http_build_query(array_diff_key($_GET, ['view' => ''])); ?>" 
                       class="view-btn <?php echo $view_mode === 'personal' ? 'active' : ''; ?>">
                        👤 Personal
                    </a>
                    <a href="?view=system&<?php echo http_build_query(array_diff_key($_GET, ['view' => ''])); ?>" 
                       class="view-btn <?php echo $view_mode === 'system' ? 'active' : ''; ?>">
                        🏢 System-wide
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <?php if ($user['role'] === 'admin'): ?>
                    <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode); ?>">
                <?php endif; ?>
                <div class="filters-row">
                    <div class="filter-group">
                        <label for="range">Time Period</label>
                        <select name="range" id="range" onchange="toggleCustomDates()">
                            <option value="7" <?php echo $date_range === '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="30" <?php echo $date_range === '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="90" <?php echo $date_range === '90' ? 'selected' : ''; ?>>Last 90 Days</option>
                            <option value="365" <?php echo $date_range === '365' ? 'selected' : ''; ?>>Last Year</option>
                            <option value="custom" <?php echo $date_range === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                    
                    <div class="filter-group" id="custom-dates" style="display: <?php echo $date_range === 'custom' ? 'flex' : 'none'; ?>;">
                        <label for="custom_start">From</label>
                        <input type="date" name="custom_start" id="custom_start" value="<?php echo htmlspecialchars($custom_start); ?>">
                    </div>
                    
                    <div class="filter-group" id="custom-dates-end" style="display: <?php echo $date_range === 'custom' ? 'flex' : 'none'; ?>;">
                        <label for="custom_end">To</label>
                        <input type="date" name="custom_end" id="custom_end" value="<?php echo htmlspecialchars($custom_end); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Key Statistics -->
        <div class="stats-grid" id="keyStats">
            <div class="loading">Loading statistics...</div>
        </div>
        
        <!-- Charts Grid -->
        <div class="analytics-grid">
            <div class="chart-card">
                <h3>📊 Check-ins Over Time</h3>
                <div class="chart-container">
                    <canvas id="timelineChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h3>⏰ Peak Hours</h3>
                <div class="chart-container">
                    <canvas id="hoursChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h3>📅 Days of Week</h3>
                <div class="chart-container">
                    <canvas id="daysChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h3>🏷️ Event Types</h3>
                <div class="chart-container">
                    <canvas id="typesChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h3>📍 Top Locations</h3>
                <div class="chart-container">
                    <canvas id="locationsChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h3>📟 Check-in Methods</h3>
                <div class="chart-container small">
                    <canvas id="methodsChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Insights Section -->
        <div class="insights-section">
            <h3>💡 Key Insights</h3>
            <div class="insights-grid" id="insights">
                <div class="loading">Generating insights...</div>
            </div>
        </div>
    </div>

    <script>
        // Chart.js configuration
        Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--text-secondary');
        Chart.defaults.borderColor = getComputedStyle(document.documentElement).getPropertyValue('--border-color');
        Chart.defaults.backgroundColor = getComputedStyle(document.documentElement).getPropertyValue('--bg-secondary');
        
        let charts = {};
        
        // Toggle custom date fields
        function toggleCustomDates() {
            const range = document.getElementById('range').value;
            const customDates = document.getElementById('custom-dates');
            const customDatesEnd = document.getElementById('custom-dates-end');
            
            if (range === 'custom') {
                customDates.style.display = 'flex';
                customDatesEnd.style.display = 'flex';
            } else {
                customDates.style.display = 'none';
                customDatesEnd.style.display = 'none';
            }
        }
        
        // Load analytics data
        async function loadAnalytics() {
            const params = new URLSearchParams(window.location.search);
            
            try {
                const response = await fetch(`../api/analytics.php?${params.toString()}`);
                const data = await response.json();
                
                if (data.error) {
                    showError(data.error);
                    return;
                }
                
                updateKeyStats(data.stats);
                createTimelineChart(data.timeline);
                createHoursChart(data.peak_hours);
                createDaysChart(data.days_of_week);
                createTypesChart(data.event_types);
                createLocationsChart(data.locations);
                createMethodsChart(data.methods);
                updateInsights(data.insights);
                
            } catch (error) {
                console.error('Error loading analytics:', error);
                showError('Failed to load analytics data');
            }
        }
        
        // Update key statistics
        function updateKeyStats(stats) {
            const container = document.getElementById('keyStats');
            container.innerHTML = `
                <div class="stat-item">
                    <div class="stat-number">${stats.total_checkins || 0}</div>
                    <div class="stat-label">Total Check-ins</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">${stats.unique_events || 0}</div>
                    <div class="stat-label">Events Attended</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">${stats.avg_duration || 'N/A'}</div>
                    <div class="stat-label">Avg Duration</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">${stats.attendance_rate || 'N/A'}</div>
                    <div class="stat-label">Attendance Rate</div>
                </div>
            `;
        }
        
        // Create timeline chart
        function createTimelineChart(data) {
            const ctx = document.getElementById('timelineChart').getContext('2d');
            
            if (charts.timeline) {
                charts.timeline.destroy();
            }
            
            charts.timeline = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        label: 'Check-ins',
                        data: data.values || [],
                        borderColor: getComputedStyle(document.documentElement).getPropertyValue('--primary-color'),
                        backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--primary-color') + '20',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
        
        // Create peak hours chart
        function createHoursChart(data) {
            const ctx = document.getElementById('hoursChart').getContext('2d');
            
            if (charts.hours) {
                charts.hours.destroy();
            }
            
            charts.hours = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        label: 'Check-ins',
                        data: data.values || [],
                        backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--primary-color') + '80',
                        borderColor: getComputedStyle(document.documentElement).getPropertyValue('--primary-color'),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
        
        // Create days of week chart
        function createDaysChart(data) {
            const ctx = document.getElementById('daysChart').getContext('2d');
            
            if (charts.days) {
                charts.days.destroy();
            }
            
            const colors = [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
                '#9966FF', '#FF9F40', '#FF6384'
            ];
            
            charts.days = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        data: data.values || [],
                        backgroundColor: colors,
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        // Create event types chart
        function createTypesChart(data) {
            const ctx = document.getElementById('typesChart').getContext('2d');
            
            if (charts.types) {
                charts.types.destroy();
            }
            
            const colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'];
            
            charts.types = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        data: data.values || [],
                        backgroundColor: colors,
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        // Create locations chart
        function createLocationsChart(data) {
            const ctx = document.getElementById('locationsChart').getContext('2d');
            
            if (charts.locations) {
                charts.locations.destroy();
            }
            
            charts.locations = new Chart(ctx, {
                type: 'horizontalBar',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        label: 'Check-ins',
                        data: data.values || [],
                        backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--success-color') + '80',
                        borderColor: getComputedStyle(document.documentElement).getPropertyValue('--success-color'),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
        
        // Create methods chart
        function createMethodsChart(data) {
            const ctx = document.getElementById('methodsChart').getContext('2d');
            
            if (charts.methods) {
                charts.methods.destroy();
            }
            
            charts.methods = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        data: data.values || [],
                        backgroundColor: ['#FF6384', '#36A2EB'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        // Update insights
        function updateInsights(insights) {
            const container = document.getElementById('insights');
            
            if (!insights || insights.length === 0) {
                container.innerHTML = '<div class="insight-item">No insights available for this period.</div>';
                return;
            }
            
            container.innerHTML = insights.map(insight => `
                <div class="insight-item">
                    <div class="insight-value">${insight.value}</div>
                    <div class="insight-label">${insight.title}</div>
                    <div class="insight-description">${insight.description}</div>
                </div>
            `).join('');
        }
        
        // Utility functions
        function showError(message) {
            console.error('Analytics Error:', message);
            document.querySelectorAll('.loading').forEach(loader => {
                loader.innerHTML = `<div class="alert alert-error">${message}</div>`;
            });
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadAnalytics();
        });
        
        // Handle theme changes
        document.addEventListener('themeChanged', function() {
            // Reload charts with new theme colors
            setTimeout(() => {
                loadAnalytics();
            }, 100);
        });
    </script>

    <?php include '../includes/theme_script.php'; ?>
</body>
</html>
