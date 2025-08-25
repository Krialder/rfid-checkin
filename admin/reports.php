<?php
/**
 * System Reports - Placeholder
 * Advanced reporting and analytics for administrators
 */

require_once '../core/config.php';
require_once '../core/database.php';
require_once '../core/auth.php';

// Check if user is admin
if (!Auth::isLoggedIn() || !Auth::hasRole(['admin'])) {
    http_response_code(403);
    header('Location: ../auth/login.php');
    exit;
}

$user = Auth::getCurrentUser();
$db = getDB();

// Fetch real statistics
try {
    // Total check-ins
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM CheckIn");
    $stmt->execute();
    $total_checkins = $stmt->fetch()['total'];
    
    // Active events (current and upcoming)
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM Events WHERE active = 1 AND end_time >= NOW()");
    $stmt->execute();
    $active_events = $stmt->fetch()['total'];
    
    // Registered users
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM Users WHERE active = 1");
    $stmt->execute();
    $registered_users = $stmt->fetch()['total'];
    
    // Recent check-ins (last 24 hours)
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM CheckIn WHERE checkin_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute();
    $recent_checkins = $stmt->fetch()['total'];
    
    // Events this month
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM Events WHERE active = 1 AND MONTH(start_time) = MONTH(CURRENT_DATE()) AND YEAR(start_time) = YEAR(CURRENT_DATE())");
    $stmt->execute();
    $events_this_month = $stmt->fetch()['total'];
    
    // Check-in success rate (assuming successful check-ins vs total attempts)
    $stmt = $db->prepare("SELECT COUNT(*) as successful FROM CheckIn WHERE status = 'checked_in'");
    $stmt->execute();
    $successful_checkins = $stmt->fetch()['successful'];
    
    $checkin_success_rate = $total_checkins > 0 ? round(($successful_checkins / $total_checkins) * 100) : 0;
    
} catch (Exception $e) {
    // Fallback values if database query fails
    $total_checkins = 0;
    $active_events = 0;
    $registered_users = 0;
    $recent_checkins = 0;
    $events_this_month = 0;
    $checkin_success_rate = 0;
    
    // Debug: Log the error (remove in production)
    error_log("Database error in reports.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Reports - Electronic Check-in System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/admin-tools.css">
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <h1>📊 System Reports</h1>
            <p class="subtitle">Advanced analytics and reporting for system administrators</p>
        </div>
        
        <!-- Quick Statistics - At the Top -->
        <div class="card stats-card" style="margin-bottom: 2rem; grid-column: 1 / -1;">
            <h3>📊 Quick Statistics</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($total_checkins); ?></span>
                    <span class="stat-label">Total Check-ins</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($active_events); ?></span>
                    <span class="stat-label">Active Events</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($registered_users); ?></span>
                    <span class="stat-label">Registered Users</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $checkin_success_rate; ?>%</span>
                    <span class="stat-label">Success Rate</span>
                </div>
            </div>
            
            <!-- Additional Stats Row -->
            <div class="stats-grid" style="margin-top: 1rem;">
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($recent_checkins); ?></span>
                    <span class="stat-label">Recent Check-ins (24h)</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($events_this_month); ?></span>
                    <span class="stat-label">Events This Month</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo date('Y-m-d'); ?></span>
                    <span class="stat-label">Current Date</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo date('H:i'); ?></span>
                    <span class="stat-label">Current Time</span>
                </div>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <!-- Report Categories -->
            <div class="card">
                <h3>📈 Available Reports</h3>
                <div class="report-categories">
                    <div class="report-category" onclick="alert('Feature coming soon!')">
                        <h4>👥 User Activity Reports</h4>
                        <p>Attendance patterns, login statistics, user engagement metrics</p>
                        <button class="btn btn-secondary">Generate Report</button>
                    </div>
                    
                    <div class="report-category" onclick="alert('Feature coming soon!')">
                        <h4>📅 Event Analytics</h4>
                        <p>Event attendance, capacity utilization, popular events</p>
                        <button class="btn btn-secondary">Generate Report</button>
                    </div>
                    
                    <div class="report-category" onclick="alert('Feature coming soon!')">
                        <h4>🔒 Security Audit</h4>
                        <p>Login attempts, access logs, security incidents</p>
                        <button class="btn btn-secondary">Generate Report</button>
                    </div>
                    
                    <div class="report-category" onclick="alert('Feature coming soon!')">
                        <h4>⚡ System Performance</h4>
                        <p>Database performance, API usage, system health metrics</p>
                        <button class="btn btn-secondary">Generate Report</button>
                    </div>
                </div>
            </div>
            
            <!-- Export Options -->
            <div class="card">
                <h3>📄 Export Options</h3>
                <div style="background: #e8f4fd; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h4>🚧 Advanced Reporting Coming Soon</h4>
                    <p>This section will include:</p>
                    <ul style="margin: 15px 0; padding-left: 25px;">
                        <li>Custom date range selection</li>
                        <li>Multiple export formats (PDF, Excel, CSV)</li>
                        <li>Scheduled report generation</li>
                        <li>Email report delivery</li>
                        <li>Interactive charts and graphs</li>
                        <li>Comparative analysis tools</li>
                    </ul>
                </div>
                
                <div class="quick-actions">
                    <button class="btn btn-secondary" onclick="alert('Feature coming soon!')">
                        📊 Custom Report Builder
                    </button>
                    <button class="btn btn-secondary" onclick="alert('Feature coming soon!')">
                        📅 Schedule Reports
                    </button>
                    <a href="../frontend/analytics.php" class="btn btn-primary">
                        📈 View Current Analytics
                    </a>
                </div>
            </div>
            
            <!-- Current Analytics -->
            <div class="card">
                <h3>💡 Available Now</h3>
                <div style="background: #f0f8f0; padding: 15px; border-radius: 8px;">
                    <p><strong>Current Analytics Available:</strong></p>
                    <ul style="margin: 10px 0; padding-left: 25px;">
                        <li><a href="../frontend/analytics.php">Interactive Analytics Dashboard</a> - Charts and visualizations</li>
                        <li><a href="../frontend/my-checkins.php">Personal Check-in History</a> - Individual user reports</li>
                        <li><a href="../admin_dev_tools.php">Database Inspector</a> - Raw data access</li>
                        <li><a href="../admin/users.php">User Management</a> - User statistics and management</li>
                        <li><a href="../admin/frontend/events.php">Event Management</a> - Event analytics and management</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        /* Ensure statistics are visible */
        .stats-card {
            background: var(--bg-primary, #ffffff) !important;
            border: 1px solid var(--border-color, #e2e8f0) !important;
            border-radius: var(--radius-md, 0.5rem) !important;
            padding: 1.5rem !important;
            margin-bottom: 2rem !important;
        }
        
        .stats-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)) !important;
            gap: 1rem !important;
            margin-top: 1rem !important;
        }
        
        .stat-item {
            text-align: center !important;
            padding: 1rem !important;
            background: var(--bg-secondary, #f8fafc) !important;
            border-radius: var(--radius-md, 0.5rem) !important;
            border: 1px solid var(--border-color, #e2e8f0) !important;
        }
        
        .stat-number {
            display: block !important;
            font-size: 2rem !important;
            font-weight: 700 !important;
            color: var(--primary-color, #2563eb) !important;
            margin-bottom: 0.25rem !important;
        }
        
        .stat-label {
            font-size: 0.875rem !important;
            color: var(--text-secondary, #475569) !important;
            font-weight: 500 !important;
        }
        
        .report-categories {
            display: grid;
            gap: 20px;
            margin: 20px 0;
        }
        
        .report-category {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .report-category:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        .report-category h4 {
            margin: 0 0 10px 0;
            color: #495057;
        }
        
        .report-category p {
            margin: 0 0 15px 0;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 20px 0;
        }
        
        .quick-actions .btn {
            flex: 1;
            min-width: 200px;
        }
    </style>
    
    <?php include '../includes/theme_script.php'; ?>
</body>
</html>
