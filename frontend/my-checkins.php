<?php
/**
 * User Personal Check-in History
 * Displays user's personal check-in history with filtering and search
 */

require_once '../core/auth.php';
require_once '../core/database.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();
$db = getDB();

// Get filter parameters
$date_filter = $_GET['date_filter'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build WHERE conditions
$where_conditions = ['c.user_id = ?'];
$params = [$user['user_id']];

// Date filtering
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

// Search filtering
if ($search) {
    $where_conditions[] = '(e.name LIKE ? OR e.location LIKE ? OR e.description LIKE ?)';
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total 
    FROM CheckIn c
    JOIN Events e ON c.event_id = e.event_id
    WHERE $where_clause
";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Get check-in records
$sql = "
    SELECT 
        c.checkin_id,
        c.checkin_time,
        c.checkout_time,
        c.status,
        c.method,
        c.ip_address,
        e.name as event_name,
        e.location,
        e.description,
        e.start_time,
        e.end_time,
        TIMESTAMPDIFF(MINUTE, e.start_time, c.checkin_time) as checkin_delay_minutes,
        CASE 
            WHEN c.checkout_time IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, c.checkin_time, c.checkout_time)
            ELSE NULL
        END as duration_minutes
    FROM CheckIn c
    JOIN Events e ON c.event_id = e.event_id
    WHERE $where_clause
    ORDER BY c.checkin_time DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$checkins = $stmt->fetchAll();

// Get summary statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total_checkins,
        COUNT(CASE WHEN c.status = 'checked-in' THEN 1 END) as active_checkins,
        COUNT(CASE WHEN c.status = 'checked-out' THEN 1 END) as completed_checkins,
        COUNT(DISTINCT c.event_id) as unique_events,
        AVG(CASE 
            WHEN c.checkout_time IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, c.checkin_time, c.checkout_time)
            ELSE NULL
        END) as avg_duration_minutes,
        AVG(TIMESTAMPDIFF(MINUTE, e.start_time, c.checkin_time)) as avg_delay_minutes
    FROM CheckIn c
    JOIN Events e ON c.event_id = e.event_id
    WHERE c.user_id = ?
";
$stmt = $db->prepare($stats_sql);
$stmt->execute([$user['user_id']]);
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Check-ins - Electronic Check-in System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <style>
        .checkins-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .filters-section {
            background: var(-- bg-secondary);
            padding: 1.5rem;
            border-radius: var(-- radius-md);
            margin-bottom: 2rem;
            box-shadow: var(-- shadow-sm);
        }
        
        .filters-row {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 150px;
        }
        
        .filter-group label {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(-- text-primary);
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .checkins-table {
            background: var(-- bg-primary);
            border-radius: var(-- radius-md);
            overflow: hidden;
            box-shadow: var(-- shadow-sm);
        }
        
        .table-header {
            background: var(-- bg-secondary);
            padding: 1rem;
            border-bottom: 1px solid var(-- border-color);
            font-weight: 600;
        }
        
        .checkin-row {
            padding: 1rem;
            border-bottom: 1px solid var(-- border-color);
            transition: background-color 0.2s ease;
        }
        
        .checkin-row:hover {
            background: var(-- bg-secondary);
        }
        
        .checkin-row:last-child {
            border-bottom: none;
        }
        
        .checkin-main {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }
        
        .checkin-event {
            font-weight: 600;
            color: var(-- text-primary);
            margin-bottom: 0.25rem;
        }
        
        .checkin-details {
            display: flex;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(-- text-secondary);
            flex-wrap: wrap;
        }
        
        .checkin-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(-- text-muted);
            margin-top: 0.5rem;
            flex-wrap: wrap;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .status-checked-in {
            background-color: #dcfce7;
            color: #15803d;
        }
        
        .status-checked-out {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .method-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            background: var(-- bg-tertiary);
            color: var(-- text-secondary);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .pagination a,
        .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid var(-- border-color);
            border-radius: var(-- radius-sm);
            text-decoration: none;
            color: var(-- text-secondary);
            background: var(-- bg-primary);
            transition: all 0.2s ease;
        }
        
        .pagination a:hover {
            background: var(-- bg-secondary);
            color: var(-- text-primary);
        }
        
        .pagination .current {
            background: var(-- primary-color);
            color: white;
            border-color: var(-- primary-color);
        }
        
        .export-section {
            text-align: right;
            margin-bottom: 1rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(-- text-secondary);
        }
        
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .delay-positive {
            color: var(-- warning-color);
        }
        
        .delay-negative {
            color: var(-- success-color);
        }
        
        @media (max-width: 768px) {
            .checkins-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filters-row {
                flex-direction: column;
            }
            
            .filter-group {
                min-width: auto;
            }
            
            .checkin-main {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .checkin-details,
            .checkin-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .stats-overview {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="main-content">
        <div class="checkins-header">
            <div>
                <h1>🕒 My Check-ins</h1>
                <p class="subtitle">Your personal check-in history and statistics</p>
            </div>
            <div class="export-section">
                <a href="?export=csv&<?php echo http_build_query($_GET); ?>" class="btn btn-secondary">
                    📊 Export CSV
                </a>
            </div>
        </div>
        
        <!-- Statistics Overview -->
        <div class="stats-overview">
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats['total_checkins'] ?: 0; ?></div>
                <div class="stat-label">Total Check-ins</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats['unique_events'] ?: 0; ?></div>
                <div class="stat-label">Events Attended</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">
                    <?php 
                    echo $stats['avg_duration_minutes'] 
                        ? round($stats['avg_duration_minutes']) . 'min' 
                        : 'N/A'; 
                    ?>
                </div>
                <div class="stat-label">Avg Duration</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">
                    <?php 
                    if ($stats['avg_delay_minutes'] !== null) {
                        $delay = round($stats['avg_delay_minutes']);
                        echo $delay >= 0 ? "+{$delay}min" : "{$delay}min";
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </div>
                <div class="stat-label">Avg Check-in Delay</div>
            </div>
        </div>
        
        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <div class="filters-row">
                    <div class="filter-group">
                        <label for="date_filter">Time Period</label>
                        <select name="date_filter" id="date_filter">
                            <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                            <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="year" <?php echo $date_filter === 'year' ? 'selected' : ''; ?>>Last Year</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="checked-in" <?php echo $status_filter === 'checked-in' ? 'selected' : ''; ?>>Checked In</option>
                            <option value="checked-out" <?php echo $status_filter === 'checked-out' ? 'selected' : ''; ?>>Checked Out</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="search">Search Events</label>
                        <input type="text" name="search" id="search" placeholder="Event name, location..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="my-checkins.php" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Check-ins Table -->
        <div class="checkins-table">
            <?php if (empty($checkins)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>No check-ins found</h3>
                    <p>No check-ins match your current filters. Try adjusting your search criteria.</p>
                    <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                </div>
            <?php else: ?>
                <div class="table-header">
                    Showing <?php echo count($checkins); ?> of <?php echo $total_records; ?> check-ins
                </div>
                
                <?php foreach ($checkins as $checkin): ?>
                    <div class="checkin-row">
                        <div class="checkin-main">
                            <div>
                                <div class="checkin-event"><?php echo htmlspecialchars($checkin['event_name']); ?></div>
                                <div class="checkin-details">
                                    <span>📍 <?php echo htmlspecialchars($checkin['location'] ?: 'No location'); ?></span>
                                    <span>📅 <?php echo date('M j, Y g:i A', strtotime($checkin['checkin_time'])); ?></span>
                                    <?php if ($checkin['checkout_time']): ?>
                                        <span>🚪 <?php echo date('g:i A', strtotime($checkin['checkout_time'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <span class="status-badge status-<?php echo $checkin['status']; ?>">
                                    <?php echo $checkin['status'] === 'checked-in' ? '✅' : '⏱️'; ?>
                                    <?php echo ucfirst(str_replace('-', ' ', $checkin['status'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="checkin-meta">
                            <span class="method-badge">
                                <?php echo $checkin['method'] === 'rfid' ? '📟 RFID' : '👤 Manual'; ?>
                            </span>
                            
                            <?php if ($checkin['duration_minutes'] !== null): ?>
                                <span>⏱️ Duration: <?php echo round($checkin['duration_minutes']); ?> min</span>
                            <?php endif; ?>
                            
                            <?php if ($checkin['checkin_delay_minutes'] !== null): ?>
                                <span class="<?php echo $checkin['checkin_delay_minutes'] >= 0 ? 'delay-positive' : 'delay-negative'; ?>">
                                    🕐 <?php 
                                    $delay = $checkin['checkin_delay_minutes'];
                                    if ($delay >= 0) {
                                        echo "+" . round($delay) . " min late";
                                    } else {
                                        echo round(abs($delay)) . " min early";
                                    }
                                    ?>
                                </span>
                            <?php endif; ?>
                            
                            <span>📧 IP: <?php echo htmlspecialchars($checkin['ip_address']); ?></span>
                        </div>
                        
                        <?php if ($checkin['description']): ?>
                            <div style="margin-top: 0.5rem; font-size: 0.875rem; color: var(-- text-secondary);">
                                <?php echo htmlspecialchars($checkin['description']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
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
            </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/theme_script.php'; ?>
</body>
</html>

<?php
// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Get all records without pagination for export
    $export_sql = "
        SELECT 
            c.checkin_time,
            c.checkout_time,
            c.status,
            c.method,
            e.name as event_name,
            e.location,
            e.start_time,
            e.end_time,
            TIMESTAMPDIFF(MINUTE, e.start_time, c.checkin_time) as checkin_delay_minutes,
            CASE 
                WHEN c.checkout_time IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, c.checkin_time, c.checkout_time)
                ELSE NULL
            END as duration_minutes
        FROM CheckIn c
        JOIN Events e ON c.event_id = e.event_id
        WHERE $where_clause
        ORDER BY c.checkin_time DESC
    ";
    
    $stmt = $db->prepare($export_sql);
    $stmt->execute($params);
    $export_data = $stmt->fetchAll();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=my-checkins-' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'Check-in Time',
        'Check-out Time', 
        'Status',
        'Method',
        'Event Name',
        'Location',
        'Event Start Time',
        'Event End Time',
        'Check-in Delay (minutes)',
        'Duration (minutes)'
    ]);
    
    // CSV data
    foreach ($export_data as $row) {
        fputcsv($output, [
            $row['checkin_time'],
            $row['checkout_time'],
            $row['status'],
            $row['method'],
            $row['event_name'],
            $row['location'],
            $row['start_time'],
            $row['end_time'],
            $row['checkin_delay_minutes'],
            $row['duration_minutes']
        ]);
    }
    
    fclose($output);
    exit();
}
