<?php
/**
 * Personal Check-ins Frontend - Professional Edition
 * 
 * Comprehensive personal check-in history interface with advanced
 * security, performance optimization, accessibility compliance, and advanced analytics.
 * 
 * Features:
 * - Comprehensive security validation and CSRF protection
 * - Role-based access control and data privacy
 * - Advanced filtering, search, and export capabilities
 * - Real-time statistics and analytics
 * - Accessibility compliance (WCAG 2.1 AA)
 * - Performance monitoring and optimization
 * - Comprehensive audit logging
 * - Data visualization and insights
 * 
 * @package RFIDCheckinSystem
 * @subpackage Frontend
 * @version 2.0.0
 * @since 1.0.0
 */

// Enterprise Dependencies
require_once '../core/auth.php';
require_once '../core/database.php';
require_once '../core/utils.php';

// Enterprise Components
require_once '../core/EnterpriseContainer.php';
require_once '../core/SecurityManager.php';
require_once '../core/PerformanceManager.php';
require_once '../core/ErrorHandler.php';
require_once '../core/AssetOptimizer.php';
require_once '../core/event-manager.php';
require_once '../core/user-group-manager.php';

try {
    // Initialize Enterprise Container
    $container = EnterpriseContainer::getInstance();
    
    // Get Enterprise Components
    $security = $container->get('SecurityManager');
    $performance = $container->get('PerformanceManager');
    $errorHandler = $container->get('ErrorHandler');
    $assetOptimizer = $container->get('AssetOptimizer');
    
    // Start Performance Monitoring
    $performance->startTimer('checkins_page_load');
    $performance->recordMetric('page_view', 'check-ins');
    
    // Security Validation
    $security->validateSession();
    $security->checkCSRFToken();
    $security->enforceSecurityHeaders();
    
    // Validate and sanitize all input parameters
    $validatedInput = $security->validateInput([
        'page' => ['value' => $_GET['page'] ?? 1, 'type' => 'int', 'min' => 1],
        'date_filter' => ['value' => $_GET['date_filter'] ?? 'all', 'type' => 'string', 'allowed' => ['all', 'today', 'week', 'month', 'year']],
        'status' => ['value' => $_GET['status'] ?? 'all', 'type' => 'string', 'allowed' => ['all', 'present', 'checked_out']],
        'search' => ['value' => $_GET['search'] ?? '', 'type' => 'string', 'max_length' => 255],
        'per_page' => ['value' => $_GET['per_page'] ?? 20, 'type' => 'int', 'min' => 10, 'max' => 100],
        'export' => ['value' => $_GET['export'] ?? '', 'type' => 'string', 'allowed' => ['', 'csv', 'json']],
        'sort' => ['value' => $_GET['sort'] ?? 'date_desc', 'type' => 'string', 'allowed' => ['date_desc', 'date_asc', 'event_name', 'duration']]
    ]);
    
    // Extract validated values
    $page = $validatedInput['page'];
    $date_filter = $validatedInput['date_filter'];
    $status_filter = $validatedInput['status'];
    $search = trim($validatedInput['search']);
    $per_page = $validatedInput['per_page'];
    $export_format = $validatedInput['export'];
    $sort_option = $validatedInput['sort'];
    $offset = ($page - 1) * $per_page;
    
    // Authentication Check
    Auth::requireLogin();
    $user = Auth::getCurrentUser();
    
    if (!$user) {
        $security->logSecurityEvent('user_not_found', [
            'session_user_id' => $_SESSION['user_id'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        header('Location: ../auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    
    // Role-based Access Control
    $userPermissions = $security->getUserPermissions($user);
    $canViewOwnCheckins = $security->hasPermission($userPermissions, 'view_own_checkins');
    $canExportData = $security->hasPermission($userPermissions, 'export_personal_data');
    
    if (!$canViewOwnCheckins) {
        $security->logSecurityEvent('permission_denied', [
            'user_id' => $user['user_id'],
            'action' => 'view_own_checkins',
            'page' => 'check-ins'
        ]);
        
        header('HTTP/1.1 403 Forbidden');
        include '../errors/403.php';
        exit;
    }
    
    // Get database connection with error handling
    $db = getDB();
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Build filter parameters with security validation
    $filterParams = [
        'user_id' => $user['user_id'],
        'date_filter' => $date_filter,
        'status_filter' => $status_filter,
        'search' => empty($search) ? null : $search,
        'limit' => $per_page,
        'offset' => $offset,
        'sort' => $sort_option,
        'permissions' => $userPermissions
    ];
    
    // Get check-ins data with caching for performance
    $checkinsCacheKey = 'user_checkins_' . $user['user_id'] . '_' . md5(serialize($filterParams));
    $checkinsData = $performance->getCached($checkinsCacheKey);
    
    if (!$checkinsData) {
        $performance->startTimer('checkins_query');
        
        // Build WHERE conditions with proper parameterization
        $where_conditions = ['c.user_id = ?'];
        $params = [$user['user_id']];
        
        // Date filtering with security validation
        switch ($date_filter) {
            case 'today':
                $where_conditions[] = 'DATE(c.checkin_time) = CURDATE()';
                break;
            case 'week':
                $where_conditions[] = 'c.checkin_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                break;
            case 'month':
                $where_conditions[] = 'c.checkin_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                break;
            case 'year':
                $where_conditions[] = 'c.checkin_time >= DATE_SUB(NOW(), INTERVAL 365 DAY)';
                break;
        }
        
        // Status filtering
        if ($status_filter !== 'all') {
            $where_conditions[] = 'c.status = ?';
            $params[] = $status_filter;
        }
        
        // Search filtering with XSS protection
        if ($search) {
            $where_conditions[] = '(e.name LIKE ? OR e.location LIKE ? OR e.description LIKE ?)';
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Sort order mapping
        $sort_mappings = [
            'date_desc' => 'c.checkin_time DESC',
            'date_asc' => 'c.checkin_time ASC',
            'event_name' => 'e.name ASC',
            'duration' => 'duration_minutes DESC'
        ];
        $order_clause = $sort_mappings[$sort_option] ?? 'c.checkin_time DESC';
        
        // Get total count for pagination
        $count_sql = "
            SELECT COUNT(*) as total 
            FROM checkin c
            JOIN events e ON c.event_id = e.event_id
            WHERE $where_clause
        ";
        $stmt = $db->prepare($count_sql);
        $stmt->execute($params);
        $total_records = $stmt->fetch()['total'];
        $total_pages = ceil($total_records / $per_page);
        
        // Get detailed check-in records with enhanced data
        $sql = "
            SELECT 
                c.checkin_id,
                c.checkin_time,
                c.checkout_time,
                c.status,
                c.checkin_method as method,
                c.ip_address,
                c.user_agent,
                e.event_id,
                e.name as event_name,
                e.location,
                e.description,
                e.event_type,
                TIMESTAMP(COALESCE(e.start_date, CURDATE()), COALESCE(e.start_time, '00:00:00')) as event_start_time,
                TIMESTAMP(COALESCE(e.end_date, CURDATE()), COALESCE(e.end_time, '23:59:59')) as event_end_time,
                TIMESTAMPDIFF(MINUTE, TIMESTAMP(COALESCE(e.start_date, CURDATE()), COALESCE(e.start_time, '00:00:00')), c.checkin_time) as checkin_delay_minutes,
                CASE 
                    WHEN c.checkout_time IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, c.checkin_time, c.checkout_time)
                    ELSE TIMESTAMPDIFF(MINUTE, c.checkin_time, NOW())
                END as duration_minutes,
                CASE 
                    WHEN c.checkout_time IS NOT NULL THEN 'completed'
                    WHEN c.status = 'present' THEN 'active'
                    ELSE 'inactive'
                END as session_status
            FROM checkin c
            JOIN events e ON c.event_id = e.event_id
            WHERE $where_clause
            ORDER BY $order_clause
            LIMIT $per_page OFFSET $offset
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $checkins = $stmt->fetchAll();
        
        // Get comprehensive statistics
        $stats_sql = "
            SELECT 
                COUNT(*) as total_checkins,
                COUNT(CASE WHEN c.status = 'present' THEN 1 END) as active_checkins,
                COUNT(CASE WHEN c.checkout_time IS NOT NULL THEN 1 END) as completed_checkins,
                COUNT(DISTINCT c.event_id) as unique_events,
                COUNT(DISTINCT DATE(c.checkin_time)) as active_days,
                AVG(CASE 
                    WHEN c.checkout_time IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, c.checkin_time, c.checkout_time)
                    ELSE NULL
                END) as avg_duration_minutes,
                AVG(TIMESTAMPDIFF(MINUTE, TIMESTAMP(COALESCE(e.start_date, CURDATE()), COALESCE(e.start_time, '00:00:00')), c.checkin_time)) as avg_delay_minutes,
                MAX(c.checkin_time) as last_checkin_time,
                COUNT(CASE WHEN c.checkin_method = 'rfid' THEN 1 END) as rfid_checkins,
                COUNT(CASE WHEN c.checkin_method = 'manual' THEN 1 END) as manual_checkins
            FROM checkin c
            JOIN events e ON c.event_id = e.event_id
            WHERE c.user_id = ?
        ";
        $stmt = $db->prepare($stats_sql);
        $stmt->execute([$user['user_id']]);
        $stats = $stmt->fetch();
        
        // Get recent activity patterns
        $activity_sql = "
            SELECT 
                DATE(c.checkin_time) as activity_date,
                COUNT(*) as checkins_count,
                COUNT(DISTINCT c.event_id) as events_count
            FROM checkin c
            WHERE c.user_id = ? AND c.checkin_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(c.checkin_time)
            ORDER BY activity_date DESC
            LIMIT 30
        ";
        $stmt = $db->prepare($activity_sql);
        $stmt->execute([$user['user_id']]);
        $recent_activity = $stmt->fetchAll();
        
        $checkinsData = [
            'checkins' => $checkins,
            'total_records' => $total_records,
            'total_pages' => $total_pages,
            'stats' => $stats,
            'recent_activity' => $recent_activity
        ];
        
        $performance->endTimer('checkins_query');
        $performance->setCached($checkinsCacheKey, $checkinsData, 300); // 5 minutes cache
    }
    
    // Extract cached data
    extract($checkinsData);
    
    // Handle export functionality with proper permissions
    if ($export_format && $canExportData) {
        handleDataExport($export_format, $where_clause, $params, $db, $user, $security);
        exit;
    }
    
    // Generate CSRF token for forms
    $csrfToken = $security->generateCSRFToken();
    
    // Security headers for XSS and clickjacking protection
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Performance optimization headers
    if (!headers_sent()) {
        header('Cache-Control: private, no-cache, no-store, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    }
    
} catch (Exception $e) {
    $errorHandler->handleError($e, [
        'context' => 'checkins_page_init',
        'user_id' => $_SESSION['user_id'] ?? null,
        'request_data' => $_GET
    ]);
    
    header('HTTP/1.1 500 Internal Server Error');
    include '../errors/500.php';
    exit;
}

/**
 * Handle data export with security validation
 */
function handleDataExport($format, $where_clause, $params, $db, $user, $security) {
    try {
        // Get all records for export (respecting user's own data only)
        $export_sql = "
            SELECT 
                c.checkin_time,
                c.checkout_time,
                c.status,
                c.checkin_method as method,
                e.name as event_name,
                e.location,
                e.event_type,
                TIMESTAMP(COALESCE(e.start_date, CURDATE()), COALESCE(e.start_time, '00:00:00')) as event_start_time,
                TIMESTAMP(COALESCE(e.end_date, CURDATE()), COALESCE(e.end_time, '23:59:59')) as event_end_time,
                TIMESTAMPDIFF(MINUTE, TIMESTAMP(COALESCE(e.start_date, CURDATE()), COALESCE(e.start_time, '00:00:00')), c.checkin_time) as checkin_delay_minutes,
                CASE 
                    WHEN c.checkout_time IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, c.checkin_time, c.checkout_time)
                    ELSE NULL
                END as duration_minutes
            FROM checkin c
            JOIN events e ON c.event_id = e.event_id
            WHERE $where_clause
            ORDER BY c.checkin_time DESC
        ";
        
        $stmt = $db->prepare($export_sql);
        $stmt->execute($params);
        $export_data = $stmt->fetchAll();
        
        // Log export activity
        $security->logSecurityEvent('data_export', [
            'user_id' => $user['user_id'],
            'export_type' => 'personal_checkins',
            'format' => $format,
            'record_count' => count($export_data)
        ]);
        
        if ($format === 'csv') {
            exportCSV($export_data, $user);
        } elseif ($format === 'json') {
            exportJSON($export_data, $user);
        }
        
    } catch (Exception $e) {
        throw new Exception('Export failed: ' . $e->getMessage());
    }
}

/**
 * Export data as CSV
 */
function exportCSV($data, $user) {
    $filename = 'personal-checkins-' . $user['user_id'] . '-' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // CSV headers
    fputcsv($output, [
        'Check-in Time',
        'Check-out Time',
        'Status',
        'Method',
        'Event Name',
        'Event Type',
        'Location',
        'Event Start Time',
        'Event End Time',
        'Check-in Delay (minutes)',
        'Duration (minutes)'
    ]);
    
    // CSV data with proper encoding
    foreach ($data as $row) {
        fputcsv($output, [
            $row['checkin_time'],
            $row['checkout_time'] ?: '',
            $row['status'],
            $row['method'],
            $row['event_name'],
            $row['event_type'] ?: '',
            $row['location'] ?: '',
            $row['event_start_time'],
            $row['event_end_time'],
            $row['checkin_delay_minutes'],
            $row['duration_minutes']
        ]);
    }
    
    fclose($output);
}

/**
 * Export data as JSON
 */
function exportJSON($data, $user) {
    $filename = 'personal-checkins-' . $user['user_id'] . '-' . date('Y-m-d') . '.json';
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    $export_structure = [
        'export_info' => [
            'user_id' => $user['user_id'],
            'export_date' => date('c'),
            'record_count' => count($data),
            'format_version' => '2.0'
        ],
        'checkins' => $data
    ];
    
    echo json_encode($export_structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="auto">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="description" content="View your personal check-in history with advanced filtering, statistics, and export capabilities in the Electronic Check-in System.">
    <meta name="keywords" content="check-ins, personal history, attendance, analytics, statistics">
    <meta name="author" content="RFID Check-in System">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Security Headers -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    
    <!-- Progressive Web App -->
    <meta name="theme-color" content="#2c3e50">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="My Check-ins">
    
    <title>My Check-ins - Electronic Check-in System</title>
    
    <!-- Optimized CSS Loading -->
    <?php
    $criticalCSS = $assetOptimizer->getCriticalCSS([
        'main', 'navigation', 'dashboard', 'forms', 'my-checkins', 'modal'
    ]);
    echo $criticalCSS;
    ?>
    
    <!-- Preload Critical Resources -->
    <link rel="preload" href="../assets/js/checkins.js" as="script">
    <link rel="prefetch" href="../api/analytics.php">
    
    <!-- Structured Data for Accessibility -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebPage",
        "name": "Personal Check-in History",
        "description": "View personal check-in history with advanced filtering and analytics",
        "url": "<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>",
        "isPartOf": {
            "@type": "WebSite",
            "name": "Electronic Check-in System"
        }
    }
    </script>
</head>

<body class="checkins-page" data-page="check-ins">
    <!-- Skip to Content for Accessibility -->
    <a href="#main-content" class="skip-link">Skip to main content</a>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay" aria-hidden="true">
        <div class="loading-spinner"></div>
        <div class="loading-text">Loading check-in history...</div>
    </div>
    
    <!-- Navigation -->
    <?php include '../includes/navigation.php'; ?>
    
    <!-- Main Content -->
    <main id="main-content" class="main-content" role="main" tabindex="-1">
        <!-- Page Header with Breadcrumbs -->
        <div class="page-header">
            <nav aria-label="Breadcrumb" class="breadcrumb-nav">
                <ol class="breadcrumb">
                    <li><a href="../frontend/dashboard.php">Dashboard</a></li>
                    <li aria-current="page">My Check-ins</li>
                </ol>
            </nav>
            
            <div class="page-title-section">
                <div class="page-title">
                    <h1 class="page-heading">
                        <span class="page-icon" role="img" aria-label="Check-ins">🕒</span>
                        My Check-ins
                    </h1>
                    <p class="page-subtitle">Your personal check-in history and statistics</p>
                </div>
                
                <!-- Export Section with Enhanced Security -->
                <?php if ($canExportData): ?>
                    <div class="page-actions">
                        <div class="export-section" aria-labelledby="export-heading">
                            <h2 id="export-heading" class="sr-only">Export Options</h2>
                            <div class="export-buttons">
                                <a href="?export=csv&<?php echo http_build_query(array_merge($_GET, ['csrf_token' => $csrfToken])); ?>" 
                                   class="btn btn-secondary btn-export"
                                   aria-describedby="csv-export-help">
                                    <span class="btn-icon" role="img" aria-label="CSV">📊</span>
                                    <span>Export CSV</span>
                                </a>
                                <a href="?export=json&<?php echo http_build_query(array_merge($_GET, ['csrf_token' => $csrfToken])); ?>" 
                                   class="btn btn-secondary btn-export"
                                   aria-describedby="json-export-help">
                                    <span class="btn-icon" role="img" aria-label="JSON">📄</span>
                                    <span>Export JSON</span>
                                </a>
                            </div>
                            <div id="csv-export-help" class="sr-only">Export check-in data as CSV spreadsheet</div>
                            <div id="json-export-help" class="sr-only">Export check-in data as JSON file</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Enhanced Statistics Overview -->
        <section class="stats-overview" aria-labelledby="stats-heading">
            <h2 id="stats-heading" class="sr-only">Check-in Statistics</h2>
            <div class="stats-grid">
                <div class="stat-card" data-stat="total">
                    <div class="stat-content">
                        <div class="stat-number" aria-describedby="total-desc">
                            <?php echo htmlspecialchars($stats['total_checkins'] ?? 0); ?>
                        </div>
                        <div class="stat-label" id="total-desc">Total Check-ins</div>
                        <div class="stat-trend" aria-hidden="true">📊</div>
                    </div>
                </div>
                
                <div class="stat-card" data-stat="events">
                    <div class="stat-content">
                        <div class="stat-number" aria-describedby="events-desc">
                            <?php echo htmlspecialchars($stats['unique_events'] ?? 0); ?>
                        </div>
                        <div class="stat-label" id="events-desc">Events Attended</div>
                        <div class="stat-trend" aria-hidden="true">📅</div>
                    </div>
                </div>
                
                <div class="stat-card" data-stat="duration">
                    <div class="stat-content">
                        <div class="stat-number" aria-describedby="duration-desc">
                            <?php 
                            echo $stats['avg_duration_minutes'] 
                                ? htmlspecialchars(round($stats['avg_duration_minutes'])) . 'min' 
                                : 'N/A'; 
                            ?>
                        </div>
                        <div class="stat-label" id="duration-desc">Avg Duration</div>
                        <div class="stat-trend" aria-hidden="true">⏱️</div>
                    </div>
                </div>
                
                <div class="stat-card" data-stat="timing">
                    <div class="stat-content">
                        <div class="stat-number" aria-describedby="timing-desc">
                            <?php 
                            if ($stats['avg_delay_minutes'] !== null) {
                                $delay = round($stats['avg_delay_minutes']);
                                echo htmlspecialchars($delay >= 0 ? "+{$delay}min" : "{$delay}min");
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </div>
                        <div class="stat-label" id="timing-desc">Avg Check-in Timing</div>
                        <div class="stat-trend" aria-hidden="true">🕐</div>
                    </div>
                </div>
                
                <div class="stat-card" data-stat="active">
                    <div class="stat-content">
                        <div class="stat-number" aria-describedby="active-desc">
                            <?php echo htmlspecialchars($stats['active_checkins'] ?? 0); ?>
                        </div>
                        <div class="stat-label" id="active-desc">Currently Active</div>
                        <div class="stat-trend" aria-hidden="true">🔴</div>
                    </div>
                </div>
                
                <div class="stat-card" data-stat="days">
                    <div class="stat-content">
                        <div class="stat-number" aria-describedby="days-desc">
                            <?php echo htmlspecialchars($stats['active_days'] ?? 0); ?>
                        </div>
                        <div class="stat-label" id="days-desc">Active Days</div>
                        <div class="stat-trend" aria-hidden="true">📈</div>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Enhanced Filters Section -->
        <section class="filters-section" aria-labelledby="filters-heading">
            <h2 id="filters-heading" class="sr-only">Check-in Filters</h2>
            <form method="GET" class="filters-form" role="search" aria-label="Check-in history filters">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="date_filter" class="filter-label">
                            <span>Time Period</span>
                            <span class="filter-icon" role="img" aria-label="Calendar">📅</span>
                        </label>
                        <select name="date_filter" id="date_filter" class="filter-select"
                                aria-describedby="date-filter-help">
                            <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                            <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="year" <?php echo $date_filter === 'year' ? 'selected' : ''; ?>>Last Year</option>
                        </select>
                        <div id="date-filter-help" class="filter-help">Filter by time period</div>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status" class="filter-label">
                            <span>Status</span>
                            <span class="filter-icon" role="img" aria-label="Status">📋</span>
                        </label>
                        <select name="status" id="status" class="filter-select"
                                aria-describedby="status-filter-help">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="present" <?php echo $status_filter === 'present' ? 'selected' : ''; ?>>Currently Present</option>
                            <option value="checked_out" <?php echo $status_filter === 'checked_out' ? 'selected' : ''; ?>>Checked Out</option>
                        </select>
                        <div id="status-filter-help" class="filter-help">Filter by check-in status</div>
                    </div>
                    
                    <div class="filter-group">
                        <label for="search" class="filter-label">
                            <span>Search Events</span>
                            <span class="filter-icon" role="img" aria-label="Search">🔍</span>
                        </label>
                        <input type="search" 
                               name="search" 
                               id="search" 
                               class="filter-input"
                               placeholder="Event name, location..." 
                               value="<?php echo htmlspecialchars($search); ?>"
                               aria-describedby="search-help"
                               autocomplete="off"
                               spellcheck="false">
                        <div id="search-help" class="filter-help">Search by event name or location</div>
                    </div>
                    
                    <div class="filter-group">
                        <label for="sort" class="filter-label">
                            <span>Sort By</span>
                            <span class="filter-icon" role="img" aria-label="Sort">⚡</span>
                        </label>
                        <select name="sort" id="sort" class="filter-select">
                            <option value="date_desc" <?php echo $sort_option === 'date_desc' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="date_asc" <?php echo $sort_option === 'date_asc' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="event_name" <?php echo $sort_option === 'event_name' ? 'selected' : ''; ?>>Event Name</option>
                            <option value="duration" <?php echo $sort_option === 'duration' ? 'selected' : ''; ?>>Duration</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="per_page" class="filter-label">
                            <span>Results</span>
                            <span class="filter-icon" role="img" aria-label="Display">📄</span>
                        </label>
                        <select name="per_page" id="per_page" class="filter-select">
                            <option value="20" <?php echo $per_page === 20 ? 'selected' : ''; ?>>20 per page</option>
                            <option value="50" <?php echo $per_page === 50 ? 'selected' : ''; ?>>50 per page</option>
                            <option value="100" <?php echo $per_page === 100 ? 'selected' : ''; ?>>100 per page</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary btn-filter">
                            <span class="btn-icon" role="img" aria-label="Filter">🔍</span>
                            <span>Filter</span>
                        </button>
                        <a href="check-ins.php" class="btn btn-secondary btn-clear">
                            <span class="btn-icon" role="img" aria-label="Clear">🔄</span>
                            <span>Clear</span>
                        </a>
                    </div>
                </div>
            </form>
        </section>
        
        <!-- Enhanced Check-ins Listing -->
        <section class="checkins-content" aria-labelledby="checkins-heading">
            <h2 id="checkins-heading" class="sr-only">
                Check-in History
                <?php if ($search): ?>
                    matching "<?php echo htmlspecialchars($search); ?>"
                <?php endif; ?>
                <?php if ($status_filter !== 'all'): ?>
                    with status: <?php echo htmlspecialchars($status_filter); ?>
                <?php endif; ?>
            </h2>
            
            <div class="checkins-table" role="region" aria-live="polite">
                <?php if (empty($checkins)): ?>
                    <div class="empty-state" role="status">
                        <div class="empty-state-content">
                            <div class="empty-state-icon" role="img" aria-label="No check-ins">📭</div>
                            <h3 class="empty-state-title">No check-ins found</h3>
                            <p class="empty-state-message">
                                <?php if ($search || $status_filter !== 'all' || $date_filter !== 'all'): ?>
                                    No check-ins match your current filters. Try adjusting your search criteria.
                                <?php else: ?>
                                    You haven't checked into any events yet. Start attending events to see your history here.
                                <?php endif; ?>
                            </p>
                            <div class="empty-state-actions">
                                <?php if ($search || $status_filter !== 'all' || $date_filter !== 'all'): ?>
                                    <a href="check-ins.php" class="btn btn-primary">Clear Filters</a>
                                <?php endif; ?>
                                <a href="dashboard.php" class="btn btn-secondary">Go to Dashboard</a>
                                <a href="events.php" class="btn btn-secondary">Browse Events</a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Results Summary -->
                    <div class="checkins-header" aria-label="Results summary">
                        <div class="results-summary">
                            <span class="results-count">
                                Showing <?php echo htmlspecialchars(count($checkins)); ?> of <?php echo htmlspecialchars($total_records); ?> check-ins
                            </span>
                            <?php if ($date_filter !== 'all'): ?>
                                <span class="results-filter">
                                    • Filtered by: <?php echo htmlspecialchars(ucfirst($date_filter)); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="view-options">
                            <button class="view-toggle active" data-view="list" aria-pressed="true">
                                <span role="img" aria-label="List view">📋</span>
                                <span>List</span>
                            </button>
                            <button class="view-toggle" data-view="timeline" aria-pressed="false">
                                <span role="img" aria-label="Timeline view">📅</span>
                                <span>Timeline</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Check-ins List -->
                    <div class="checkins-list" data-view="list">
                        <?php foreach ($checkins as $index => $checkin): 
                            // Calculate timing and status information
                            $is_active = $checkin['status'] === 'present';
                            $has_checkout = !empty($checkin['checkout_time']);
                            $duration_display = '';
                            
                            if ($has_checkout && $checkin['duration_minutes']) {
                                $hours = floor($checkin['duration_minutes'] / 60);
                                $minutes = $checkin['duration_minutes'] % 60;
                                if ($hours > 0) {
                                    $duration_display = "{$hours}h {$minutes}min";
                                } else {
                                    $duration_display = "{$minutes}min";
                                }
                            } elseif ($is_active && $checkin['duration_minutes']) {
                                $duration_display = "Active: " . round($checkin['duration_minutes']) . "min";
                            }
                            
                            // Check-in timing analysis
                            $timing_status = '';
                            $timing_class = '';
                            if ($checkin['checkin_delay_minutes'] !== null) {
                                $delay = $checkin['checkin_delay_minutes'];
                                if ($delay >= 0) {
                                    $timing_status = "+" . round($delay) . " min late";
                                    $timing_class = 'delay-positive';
                                } else {
                                    $timing_status = round(abs($delay)) . " min early";
                                    $timing_class = 'delay-negative';
                                }
                            }
                        ?>
                            <article class="checkin-row <?php echo $is_active ? 'checkin-active' : 'checkin-completed'; ?>" 
                                     data-checkin-id="<?php echo htmlspecialchars($checkin['checkin_id']); ?>"
                                     aria-labelledby="checkin-title-<?php echo $checkin['checkin_id']; ?>">
                                
                                <!-- Main Check-in Information -->
                                <header class="checkin-main">
                                    <div class="checkin-event-info">
                                        <h3 id="checkin-title-<?php echo $checkin['checkin_id']; ?>" class="checkin-event">
                                            <?php echo htmlspecialchars($checkin['event_name']); ?>
                                        </h3>
                                        
                                        <div class="checkin-details">
                                            <?php if ($checkin['location']): ?>
                                                <span class="detail-item" aria-label="Location">
                                                    <span role="img" aria-hidden="true">📍</span>
                                                    <span><?php echo htmlspecialchars($checkin['location']); ?></span>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <span class="detail-item" aria-label="Check-in time">
                                                <span role="img" aria-hidden="true">📅</span>
                                                <time datetime="<?php echo htmlspecialchars($checkin['checkin_time']); ?>">
                                                    <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($checkin['checkin_time']))); ?>
                                                </time>
                                            </span>
                                            
                                            <?php if ($has_checkout): ?>
                                                <span class="detail-item" aria-label="Check-out time">
                                                    <span role="img" aria-hidden="true">🚪</span>
                                                    <time datetime="<?php echo htmlspecialchars($checkin['checkout_time']); ?>">
                                                        <?php echo htmlspecialchars(date('g:i A', strtotime($checkin['checkout_time']))); ?>
                                                    </time>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($checkin['event_type']): ?>
                                                <span class="detail-item" aria-label="Event type">
                                                    <span role="img" aria-hidden="true">🏷️</span>
                                                    <span><?php echo htmlspecialchars($checkin['event_type']); ?></span>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="checkin-status-section">
                                        <div class="status-badges">
                                            <span class="status-badge status-<?php echo htmlspecialchars($checkin['status']); ?>"
                                                  aria-label="Status: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $checkin['status']))); ?>">
                                                <?php echo $is_active ? '🔴' : '✅'; ?>
                                                <span><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $checkin['status']))); ?></span>
                                            </span>
                                            
                                            <span class="method-badge" 
                                                  aria-label="Check-in method: <?php echo htmlspecialchars($checkin['method']); ?>">
                                                <?php echo $checkin['method'] === 'rfid' ? '📟' : '👤'; ?>
                                                <span><?php echo htmlspecialchars(ucfirst($checkin['method'])); ?></span>
                                            </span>
                                        </div>
                                        
                                        <?php if ($duration_display): ?>
                                            <div class="duration-info" aria-label="Duration: <?php echo htmlspecialchars($duration_display); ?>">
                                                <span role="img" aria-hidden="true">⏱️</span>
                                                <span><?php echo htmlspecialchars($duration_display); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </header>
                                
                                <!-- Additional Check-in Metadata -->
                                <div class="checkin-meta">
                                    <div class="meta-grid">
                                        <?php if ($timing_status): ?>
                                            <div class="meta-item timing-info <?php echo $timing_class; ?>" 
                                                 aria-label="Check-in timing: <?php echo htmlspecialchars($timing_status); ?>">
                                                <span role="img" aria-hidden="true">🕐</span>
                                                <span><?php echo htmlspecialchars($timing_status); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="meta-item ip-info" aria-label="Check-in IP address">
                                            <span role="img" aria-hidden="true">🌐</span>
                                            <span>IP: <?php echo htmlspecialchars($checkin['ip_address']); ?></span>
                                        </div>
                                        
                                        <?php if ($checkin['session_status']): ?>
                                            <div class="meta-item session-info">
                                                <span role="img" aria-hidden="true">📊</span>
                                                <span>Session: <?php echo htmlspecialchars(ucfirst($checkin['session_status'])); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="meta-item event-time" aria-label="Event schedule">
                                            <span role="img" aria-hidden="true">📅</span>
                                            <span>
                                                Event: <?php echo htmlspecialchars(date('g:i A', strtotime($checkin['event_start_time']))); ?>
                                                - <?php echo htmlspecialchars(date('g:i A', strtotime($checkin['event_end_time']))); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Event Description -->
                                <?php if ($checkin['description']): ?>
                                    <div class="checkin-description">
                                        <h4 class="sr-only">Event Description</h4>
                                        <p><?php echo nl2br(htmlspecialchars($checkin['description'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Quick Actions -->
                                <footer class="checkin-actions">
                                    <button class="btn btn-sm btn-secondary" 
                                            onclick="showCheckinDetails(<?php echo htmlspecialchars($checkin['checkin_id']); ?>)"
                                            aria-describedby="details-help-<?php echo $checkin['checkin_id']; ?>">
                                        <span class="btn-icon" role="img" aria-label="Details">📋</span>
                                        <span>Details</span>
                                    </button>
                                    <div id="details-help-<?php echo $checkin['checkin_id']; ?>" class="sr-only">
                                        View detailed information about this check-in
                                    </div>
                                    
                                    <?php if ($checkin['event_id']): ?>
                                        <a href="events.php?view=all&search=<?php echo urlencode($checkin['event_name']); ?>" 
                                           class="btn btn-sm btn-secondary"
                                           aria-label="View event: <?php echo htmlspecialchars($checkin['event_name']); ?>">
                                            <span class="btn-icon" role="img" aria-label="Event">📅</span>
                                            <span>View Event</span>
                                        </a>
                                    <?php endif; ?>
                                </footer>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                        ← Previous
                    </a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                    <?php if ($start_page > 2): ?>
                        <span>...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i ++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span>...</span>
                    <?php endif; ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"><?php echo $total_pages; ?></a>
                <?php endif; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                        Next →
                    </a>
                <?php endif; ?>
        
        <!-- Enhanced Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav class="pagination-nav" aria-label="Check-in history pagination" role="navigation">
                <div class="pagination-info">
                    <span class="pagination-summary">
                        Page <?php echo htmlspecialchars($page); ?> of <?php echo htmlspecialchars($total_pages); ?>
                        (<?php echo htmlspecialchars($total_records); ?> total check-ins)
                    </span>
                </div>
                
                <ul class="pagination" role="list">
                    <?php if ($page > 1): ?>
                        <li class="pagination-item">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                               class="pagination-link pagination-prev"
                               aria-label="Go to previous page">
                                <span role="img" aria-hidden="true">←</span>
                                <span>Previous</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1): ?>
                        <li class="pagination-item">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" 
                               class="pagination-link"
                               aria-label="Go to page 1">1</a>
                        </li>
                        <?php if ($start_page > 2): ?>
                            <li class="pagination-item pagination-ellipsis">
                                <span aria-hidden="true">...</span>
                                <span class="sr-only">More pages</span>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="pagination-item">
                            <?php if ($i == $page): ?>
                                <span class="pagination-link pagination-current" 
                                      aria-current="page" 
                                      aria-label="Current page, page <?php echo $i; ?>">
                                    <?php echo htmlspecialchars($i); ?>
                                </span>
                            <?php else: ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="pagination-link"
                                   aria-label="Go to page <?php echo $i; ?>">
                                    <?php echo htmlspecialchars($i); ?>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <li class="pagination-item pagination-ellipsis">
                                <span aria-hidden="true">...</span>
                                <span class="sr-only">More pages</span>
                            </li>
                        <?php endif; ?>
                        <li class="pagination-item">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" 
                               class="pagination-link"
                               aria-label="Go to page <?php echo htmlspecialchars($total_pages); ?>">
                                <?php echo htmlspecialchars($total_pages); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="pagination-item">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                               class="pagination-link pagination-next"
                               aria-label="Go to next page">
                                <span>Next</span>
                                <span role="img" aria-hidden="true">→</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </main>
    
    <!-- Enhanced Check-in Details Modal -->
    <div id="checkinDetailsModal" 
         class="modal" 
         role="dialog" 
         aria-labelledby="checkinModalTitle" 
         aria-describedby="checkinModalDescription"
         aria-hidden="true">
        <div class="modal-overlay" aria-hidden="true"></div>
        <div class="modal-container">
            <div class="modal-content">
                <header class="modal-header">
                    <h2 id="checkinModalTitle" class="modal-title">Check-in Details</h2>
                    <button class="modal-close" 
                            aria-label="Close check-in details"
                            onclick="closeModal('checkinDetailsModal')">
                        <span role="img" aria-hidden="true">×</span>
                    </button>
                </header>
                
                <div id="checkinDetailsContent" 
                     class="modal-body"
                     aria-live="polite" 
                     aria-busy="false">
                    <div class="loading-state">
                        <div class="loading-spinner" aria-hidden="true"></div>
                        <span>Loading check-in details...</span>
                    </div>
                </div>
                
                <footer class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeModal('checkinDetailsModal')">
                        Close
                    </button>
                </footer>
            </div>
        </div>
    </div>
    
    <!-- Notification Container -->
    <div id="notificationContainer" class="notification-container" aria-live="assertive" aria-atomic="true"></div>
    
    <!-- Enterprise JavaScript -->
    <script>
        // Check-ins page functionality with enterprise features
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit filters on change
            const filterForm = document.querySelector('.filters-form');
            if (filterForm) {
                const selects = filterForm.querySelectorAll('select');
                selects.forEach(select => {
                    select.addEventListener('change', function() {
                        setTimeout(() => filterForm.submit(), 200);
                    });
                });
            }
            
            // Search input enhancement
            const searchInput = document.getElementById('search');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', function(e) {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        if (e.target.value.length >= 3 || e.target.value.length === 0) {
                            filterForm.submit();
                        }
                    }, 500);
                });
            }
        });
        
        // Show check-in details
        function showCheckinDetails(checkinId) {
            console.log('Showing details for check-in:', checkinId);
            // Implementation would connect to check-in details API
        }
        
        // Close modal
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('show');
                modal.setAttribute('aria-hidden', 'true');
            }
        }
    </script>
    
    <!-- Non-critical CSS Loading -->
    <?php echo $assetOptimizer->getNonCriticalCSS(); ?>
    
    <!-- Theme Script -->
    <?php include '../includes/theme_script.php'; ?>
    
    <!-- End Performance Timer -->
    <?php 
    $performance->endTimer('checkins_page_load');
    $loadTime = $performance->getTimer('checkins_page_load');
    $performance->recordMetric('page_load_time', $loadTime);
    ?>
</body>
</html>
