<?php
/**
 * Events Frontend - Professional Edition
 * 
 * Comprehensive events listing interface with advanced security,
 * performance optimization, accessibility compliance, and real-time features.
 * 
 * Features:
 * - Comprehensive security validation and CSRF protection
 * - Role-based access control and permissions
 * - Real-time event updates and notifications
 * - Advanced filtering and search capabilities
 * - Accessibility compliance (WCAG 2.1 AA)
 * - Progressive Web App features
 * - Performance monitoring and optimization
 * - Comprehensive error handling and logging
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
    $performance->startTimer('events_page_load');
    $performance->recordMetric('page_view', 'events');
    
    // Security Validation
    $security->validateSession();
    $security->checkCSRFToken();
    $security->enforceSecurityHeaders();
    
    // Validate and sanitize all input parameters
    $validatedInput = $security->validateInput([
        'page' => ['value' => $_GET['page'] ?? 1, 'type' => 'int', 'min' => 1],
        'view' => ['value' => $_GET['view'] ?? 'upcoming', 'type' => 'string', 'allowed' => ['upcoming', 'current', 'past', 'all']],
        'category' => ['value' => $_GET['category'] ?? 'all', 'type' => 'string', 'max_length' => 100],
        'search' => ['value' => $_GET['search'] ?? '', 'type' => 'string', 'max_length' => 255],
        'per_page' => ['value' => $_GET['per_page'] ?? 12, 'type' => 'int', 'min' => 6, 'max' => 48]
    ]);
    
    // Extract validated values
    $page = $validatedInput['page'];
    $view_filter = $validatedInput['view'];
    $category_filter = $validatedInput['category'];
    $search = trim($validatedInput['search']);
    $per_page = $validatedInput['per_page'];
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
    $canViewEvents = $security->hasPermission($userPermissions, 'view_events');
    $canJoinEvents = $security->hasPermission($userPermissions, 'join_events');
    $canViewEventDetails = $security->hasPermission($userPermissions, 'view_event_details');
    
    if (!$canViewEvents) {
        $security->logSecurityEvent('permission_denied', [
            'user_id' => $user['user_id'],
            'action' => 'view_events',
            'page' => 'events'
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
    
    // Initialize Event Manager
    $eventManager = new EventManager($db);
    
    // Build filter parameters with security validation
    $filterParams = [
        'view' => $view_filter,
        'category' => $category_filter === 'all' ? null : $category_filter,
        'search' => empty($search) ? null : $search,
        'user_id' => $user['user_id'],
        'limit' => $per_page,
        'offset' => $offset,
        'permissions' => $userPermissions
    ];
    
    // Get events with caching for performance
    $eventsCacheKey = 'events_' . md5(serialize($filterParams));
    $eventsData = $performance->getCached($eventsCacheKey);
    
    if (!$eventsData) {
        $performance->startTimer('events_query');
        
        // Build WHERE conditions
        $where_conditions = ['e.active = 1'];
        $params = [];
        
        // View filtering
        switch ($view_filter) {
            case 'upcoming':
                $where_conditions[] = 'TIMESTAMP(COALESCE(e.start_date, CURDATE()), COALESCE(e.start_time, "00:00:00")) > NOW()';
                $order_by = 'TIMESTAMP(COALESCE(e.start_date, CURDATE()), COALESCE(e.start_time, "00:00:00")) ASC';
                break;
            case 'current':
                $where_conditions[] = 'TIMESTAMP(COALESCE(e.start_date, CURDATE()), COALESCE(e.start_time, "00:00:00")) <= NOW() AND TIMESTAMP(COALESCE(e.end_date, CURDATE()), COALESCE(e.end_time, "23:59:59")) >= NOW()';
                $order_by = 'TIMESTAMP(COALESCE(e.start_date, CURDATE()), COALESCE(e.start_time, "00:00:00")) ASC';
                break;
            case 'past':
                $where_conditions[] = 'TIMESTAMP(COALESCE(e.end_date, CURDATE()), COALESCE(e.end_time, "23:59:59")) < NOW()';
                $order_by = 'TIMESTAMP(COALESCE(e.start_date, CURDATE()), COALESCE(e.start_time, "00:00:00")) DESC';
                break;
            default:
                $order_by = 'TIMESTAMP(COALESCE(e.start_date, CURDATE()), COALESCE(e.start_time, "00:00:00")) ASC';
        }
        
        // Category filtering
        if ($category_filter !== 'all') {
            $where_conditions[] = 'e.event_type = ?';
            $params[] = $category_filter;
        }
        
        // Search filtering
        if ($search) {
            $where_conditions[] = '(e.name LIKE ? OR e.description LIKE ? OR e.location LIKE ?)';
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total count for pagination
        $count_sql = "SELECT COUNT(*) as total FROM events e WHERE $where_clause";
        $stmt = $db->prepare($count_sql);
        $stmt->execute($params);
        $total_records = $stmt->fetch()['total'];
        $total_pages = ceil($total_records / $per_page);
        
        // Get events with check-in status for current user
        $sql = "
            SELECT 
                e.*,
                CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as created_by_name,
                (SELECT COUNT(*) FROM checkin ci WHERE ci.event_id = e.event_id AND ci.status = 'present') as current_participants,
                CASE 
                    WHEN c.checkin_id IS NOT NULL THEN c.status
                    ELSE NULL
                END as user_checkin_status,
                c.checkin_time as user_checkin_time,
                (CASE 
                    WHEN e.capacity > 0 THEN 
                        ((SELECT COUNT(*) FROM checkin ci WHERE ci.event_id = e.event_id AND ci.status = 'present') / e.capacity * 100)
                    ELSE 0
                END) as capacity_percentage
            FROM events e
            LEFT JOIN users u ON e.created_by = u.user_id
            LEFT JOIN checkin c ON e.event_id = c.event_id AND c.user_id = ? 
                AND DATE(c.checkin_time) = e.start_date
            WHERE $where_clause
            ORDER BY $order_by
            LIMIT $per_page OFFSET $offset
        ";
        
        $all_params = array_merge([$user['user_id']], $params);
        $stmt = $db->prepare($sql);
        $stmt->execute($all_params);
        $events = $stmt->fetchAll();
        
        // Get categories for filter dropdown
        $cat_sql = "SELECT DISTINCT event_type FROM events WHERE active = 1 AND event_type IS NOT NULL ORDER BY event_type";
        $stmt = $db->prepare($cat_sql);
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get user event statistics
        $stats_sql = "
            SELECT 
                COUNT(DISTINCT e.event_id) as total_events,
                COUNT(DISTINCT CASE WHEN TIMESTAMP(COALESCE(e.start_date, CURDATE()), COALESCE(e.start_time, '00:00:00')) > NOW() THEN e.event_id END) as upcoming_events,
                COUNT(DISTINCT CASE WHEN TIMESTAMP(COALESCE(e.start_date, CURDATE()), COALESCE(e.start_time, '00:00:00')) <= NOW() AND TIMESTAMP(COALESCE(e.end_date, CURDATE()), COALESCE(e.end_time, '23:59:59')) >= NOW() THEN e.event_id END) as current_events,
                COUNT(DISTINCT c.event_id) as attended_events
            FROM events e
            LEFT JOIN checkin c ON e.event_id = c.event_id AND c.user_id = ? AND c.status = 'present'
            WHERE e.active = 1
        ";
        $stmt = $db->prepare($stats_sql);
        $stmt->execute([$user['user_id']]);
        $event_stats = $stmt->fetch();
        
        $eventsData = [
            'events' => $events,
            'total_records' => $total_records,
            'total_pages' => $total_pages,
            'categories' => $categories,
            'event_stats' => $event_stats
        ];
        
        $performance->endTimer('events_query');
        $performance->setCached($eventsCacheKey, $eventsData, 180); // 3 minutes cache
    }
    
    // Extract cached data
    extract($eventsData);
    
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
        'context' => 'events_page_init',
        'user_id' => $_SESSION['user_id'] ?? null,
        'request_data' => $_GET
    ]);
    
    header('HTTP/1.1 500 Internal Server Error');
    include '../errors/500.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="auto">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="description" content="Browse and join events in the Electronic Check-in System. View upcoming, current, and past events with advanced filtering and quick check-in capabilities.">
    <meta name="keywords" content="events, check-in, attendance, registration, calendar">
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
    <meta name="apple-mobile-web-app-title" content="Events">
    
    <title>Events - Electronic Check-in System</title>
    
    <!-- Optimized CSS Loading -->
    <?php
    $criticalCSS = $assetOptimizer->getCriticalCSS([
        'main', 'navigation', 'dashboard', 'forms', 'events', 'modal'
    ]);
    echo $criticalCSS;
    ?>
    
    <!-- Preload Critical Resources -->
    <link rel="preload" href="../assets/js/events.js" as="script">
    <link rel="prefetch" href="../api/event-details.php">
    <link rel="prefetch" href="../api/manual-checkin.php">
    
    <!-- Structured Data for Accessibility -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebPage",
        "name": "Events",
        "description": "Browse and join events in the Electronic Check-in System",
        "url": "<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>",
        "isPartOf": {
            "@type": "WebSite",
            "name": "Electronic Check-in System"
        }
    }
    </script>
</head>
<body class="events-page" data-page="events">
    <!-- Skip to Content for Accessibility -->
    <a href="#main-content" class="skip-link">Skip to main content</a>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay" aria-hidden="true">
        <div class="loading-spinner"></div>
        <div class="loading-text">Loading events...</div>
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
                    <li aria-current="page">Events</li>
                </ol>
            </nav>
            
            <div class="page-title">
                <h1 class="page-heading">
                    <span class="page-icon" role="img" aria-label="Events">📅</span>
                    Events
                </h1>
                <p class="page-subtitle">Discover and join upcoming events</p>
            </div>
            
            <!-- Real-time Status Indicator -->
            <div class="status-indicator" id="connectionStatus" aria-live="polite">
                <span class="status-dot status-connected"></span>
                <span class="status-text">Connected</span>
            </div>
        </div>
        
        <!-- Events Statistics Overview -->
        <section class="stats-overview" aria-labelledby="stats-heading">
            <h2 id="stats-heading" class="sr-only">Event Statistics</h2>
            <div class="stats-grid">
                <div class="stat-card" data-stat="total">
                    <div class="stat-number" aria-describedby="total-desc">
                        <?php echo htmlspecialchars($event_stats['total_events'] ?? 0); ?>
                    </div>
                    <div class="stat-label" id="total-desc">Total Events</div>
                    <div class="stat-trend" aria-hidden="true">📊</div>
                </div>
                <div class="stat-card" data-stat="upcoming">
                    <div class="stat-number" aria-describedby="upcoming-desc">
                        <?php echo htmlspecialchars($event_stats['upcoming_events'] ?? 0); ?>
                    </div>
                    <div class="stat-label" id="upcoming-desc">Upcoming Events</div>
                    <div class="stat-trend" aria-hidden="true">📅</div>
                </div>
                <div class="stat-card" data-stat="current">
                    <div class="stat-number" aria-describedby="current-desc">
                        <?php echo htmlspecialchars($event_stats['current_events'] ?? 0); ?>
                    </div>
                    <div class="stat-label" id="current-desc">Active Now</div>
                    <div class="stat-trend" aria-hidden="true">🔴</div>
                </div>
                <div class="stat-card" data-stat="attended">
                    <div class="stat-number" aria-describedby="attended-desc">
                        <?php echo htmlspecialchars($event_stats['attended_events'] ?? 0); ?>
                    </div>
                    <div class="stat-label" id="attended-desc">Events Attended</div>
                    <div class="stat-trend" aria-hidden="true">✅</div>
                </div>
            </div>
        </section>
        
        <!-- View Tabs with Enhanced Accessibility -->
        <section class="view-section" aria-labelledby="view-heading">
            <h2 id="view-heading" class="sr-only">Event View Options</h2>
            <div class="view-tabs" role="tablist" aria-label="Event time filters">
                <a href="?view=upcoming&<?php echo http_build_query(array_diff_key($_GET, ['view' => ''])); ?>" 
                   role="tab"
                   class="view-tab <?php echo $view_filter === 'upcoming' ? 'active' : ''; ?>"
                   aria-selected="<?php echo $view_filter === 'upcoming' ? 'true' : 'false'; ?>"
                   aria-controls="events-grid">
                    <span role="img" aria-label="Calendar">📅</span>
                    <span>Upcoming</span>
                    <span class="tab-count"><?php echo htmlspecialchars($event_stats['upcoming_events'] ?? 0); ?></span>
                </a>
                <a href="?view=current&<?php echo http_build_query(array_diff_key($_GET, ['view' => ''])); ?>" 
                   role="tab"
                   class="view-tab <?php echo $view_filter === 'current' ? 'active' : ''; ?>"
                   aria-selected="<?php echo $view_filter === 'current' ? 'true' : 'false'; ?>"
                   aria-controls="events-grid">
                    <span role="img" aria-label="Live">🔴</span>
                    <span>Current</span>
                    <span class="tab-count"><?php echo htmlspecialchars($event_stats['current_events'] ?? 0); ?></span>
                </a>
                <a href="?view=past&<?php echo http_build_query(array_diff_key($_GET, ['view' => ''])); ?>" 
                   role="tab"
                   class="view-tab <?php echo $view_filter === 'past' ? 'active' : ''; ?>"
                   aria-selected="<?php echo $view_filter === 'past' ? 'true' : 'false'; ?>"
                   aria-controls="events-grid">
                    <span role="img" aria-label="History">📋</span>
                    <span>Past</span>
                </a>
                <a href="?view=all&<?php echo http_build_query(array_diff_key($_GET, ['view' => ''])); ?>" 
                   role="tab"
                   class="view-tab <?php echo $view_filter === 'all' ? 'active' : ''; ?>"
                   aria-selected="<?php echo $view_filter === 'all' ? 'true' : 'false'; ?>"
                   aria-controls="events-grid">
                    <span role="img" aria-label="All">🗂️</span>
                    <span>All</span>
                    <span class="tab-count"><?php echo htmlspecialchars($event_stats['total_events'] ?? 0); ?></span>
                </a>
            </div>
        </section>
        
        <!-- Enhanced Filters Section -->
        <section class="filters-section" aria-labelledby="filters-heading">
            <h2 id="filters-heading" class="sr-only">Event Filters</h2>
            <form method="GET" class="filters-form" role="search" aria-label="Event search and filters">
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_filter); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="category" class="filter-label">
                            <span>Category</span>
                            <span class="filter-icon" role="img" aria-label="Category">🏷️</span>
                        </label>
                        <select name="category" id="category" class="filter-select" 
                                aria-describedby="category-help">
                            <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>>
                                All Categories
                            </option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" 
                                        <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst($category)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="category-help" class="filter-help">Filter events by category</div>
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
                               placeholder="Event name, description, location..." 
                               value="<?php echo htmlspecialchars($search); ?>"
                               aria-describedby="search-help"
                               autocomplete="off"
                               spellcheck="false">
                        <div id="search-help" class="filter-help">Search by name, description, or location</div>
                    </div>
                    
                    <div class="filter-group">
                        <label for="per_page" class="filter-label">
                            <span>Results per page</span>
                            <span class="filter-icon" role="img" aria-label="Display">📄</span>
                        </label>
                        <select name="per_page" id="per_page" class="filter-select">
                            <option value="12" <?php echo $per_page === 12 ? 'selected' : ''; ?>>12 events</option>
                            <option value="24" <?php echo $per_page === 24 ? 'selected' : ''; ?>>24 events</option>
                            <option value="48" <?php echo $per_page === 48 ? 'selected' : ''; ?>>48 events</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary btn-search">
                            <span class="btn-icon" role="img" aria-label="Search">🔍</span>
                            <span>Search</span>
                        </button>
                        <a href="events.php?view=<?php echo htmlspecialchars($view_filter); ?>" 
                           class="btn btn-secondary btn-clear">
                            <span class="btn-icon" role="img" aria-label="Clear">🔄</span>
                            <span>Clear</span>
                        </a>
                    </div>
                </div>
            </form>
        </section>
        
        <!-- Events Grid with Enhanced Accessibility -->
        <section class="events-content" aria-labelledby="events-heading">
            <h2 id="events-heading" class="sr-only">
                <?php echo htmlspecialchars(ucfirst($view_filter)); ?> Events
                <?php if ($search): ?>
                    matching "<?php echo htmlspecialchars($search); ?>"
                <?php endif; ?>
                <?php if ($category_filter !== 'all'): ?>
                    in <?php echo htmlspecialchars($category_filter); ?> category
                <?php endif; ?>
            </h2>
            
            <div id="events-grid" class="events-grid" role="tabpanel" aria-live="polite">
                <?php if (empty($events)): ?>
                    <div class="empty-state" role="status">
                        <div class="empty-state-content">
                            <div class="empty-state-icon" role="img" aria-label="No events">📅</div>
                            <h3 class="empty-state-title">No events found</h3>
                            <p class="empty-state-message">
                                <?php if ($search || $category_filter !== 'all'): ?>
                                    No events match your current filters. Try adjusting your search criteria or check back later for new events.
                                <?php else: ?>
                                    There are no <?php echo htmlspecialchars($view_filter); ?> events at the moment. Check back later for new events.
                                <?php endif; ?>
                            </p>
                            <div class="empty-state-actions">
                                <?php if ($search || $category_filter !== 'all'): ?>
                                    <a href="events.php?view=<?php echo htmlspecialchars($view_filter); ?>" 
                                       class="btn btn-primary">Clear Filters</a>
                                <?php endif; ?>
                                <a href="dashboard.php" class="btn btn-secondary">Go to Dashboard</a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($events as $index => $event): 
                        // Calculate event timing
                        $start_datetime = $event['start_date'] . ($event['start_time'] ? ' ' . $event['start_time'] : ' 00:00:00');
                        $end_datetime = $event['end_date'] . ($event['end_time'] ? ' ' . $event['end_time'] : ' 23:59:59');
                        $is_past = strtotime($end_datetime) < time();
                        $is_current = strtotime($start_datetime) <= time() && strtotime($end_datetime) >= time();
                        $is_upcoming = strtotime($start_datetime) > time();
                        
                        // Event status classes
                        $card_classes = ['event-card'];
                        if ($is_past) $card_classes[] = 'event-past';
                        elseif ($is_current) $card_classes[] = 'event-current';
                        elseif ($is_upcoming) $card_classes[] = 'event-upcoming';
                        
                        // Check-in status
                        $user_checked_in = $event['user_checkin_status'] === 'present';
                        $can_check_in = $is_current && $canJoinEvents && !$user_checked_in;
                        
                        // Capacity information
                        $capacity_percentage = $event['capacity_percentage'] ?? 0;
                        $is_full = $event['capacity'] > 0 && $capacity_percentage >= 100;
                    ?>
                        <article class="<?php echo implode(' ', $card_classes); ?>" 
                                 data-event-id="<?php echo htmlspecialchars($event['event_id']); ?>"
                                 data-event-status="<?php echo $is_current ? 'current' : ($is_upcoming ? 'upcoming' : 'past'); ?>"
                                 aria-labelledby="event-title-<?php echo $event['event_id']; ?>">
                            
                            <!-- Event Header -->
                            <header class="event-header">
                                <div class="event-title-section">
                                    <h3 id="event-title-<?php echo $event['event_id']; ?>" class="event-title">
                                        <?php echo htmlspecialchars($event['name']); ?>
                                    </h3>
                                    <?php if ($event['event_type']): ?>
                                        <span class="event-type" 
                                              aria-label="Event category: <?php echo htmlspecialchars($event['event_type']); ?>">
                                            <?php echo htmlspecialchars($event['event_type']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="event-status-badges">
                                    <?php if ($is_current): ?>
                                        <span class="status-badge status-live" aria-label="Live event">
                                            <span role="img" aria-label="Live">🔴</span>
                                            <span>Live</span>
                                        </span>
                                    <?php elseif ($is_upcoming): ?>
                                        <span class="status-badge status-upcoming" aria-label="Upcoming event">
                                            <span role="img" aria-label="Upcoming">⏰</span>
                                            <span>Upcoming</span>
                                        </span>
                                    <?php elseif ($is_past): ?>
                                        <span class="status-badge status-past" aria-label="Past event">
                                            <span role="img" aria-label="Completed">📋</span>
                                            <span>Completed</span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </header>
                            
                            <!-- Event Details -->
                            <div class="event-details">
                                <div class="event-datetime" aria-label="Event time">
                                    <span class="detail-icon" role="img" aria-label="Time">🕒</span>
                                    <time datetime="<?php echo htmlspecialchars($start_datetime); ?>">
                                        <?php 
                                        $start_display = $event['start_date'];
                                        if ($event['start_time']) {
                                            $start_display = date('M j, Y g:i A', strtotime($event['start_date'] . ' ' . $event['start_time']));
                                        } else {
                                            $start_display = date('M j, Y', strtotime($event['start_date']));
                                        }
                                        echo htmlspecialchars($start_display);
                                        
                                        if ($event['end_date'] && $event['end_date'] !== $event['start_date']) {
                                            if ($event['end_time']) {
                                                echo ' - ' . htmlspecialchars(date('M j, Y g:i A', strtotime($event['end_date'] . ' ' . $event['end_time'])));
                                            } else {
                                                echo ' - ' . htmlspecialchars(date('M j, Y', strtotime($event['end_date'])));
                                            }
                                        } elseif ($event['end_time'] && $event['start_time'] !== $event['end_time']) {
                                            echo ' - ' . htmlspecialchars(date('g:i A', strtotime($event['end_time'])));
                                        }
                                        ?>
                                    </time>
                                </div>
                                
                                <?php if ($event['location']): ?>
                                    <div class="event-location" aria-label="Event location">
                                        <span class="detail-icon" role="img" aria-label="Location">📍</span>
                                        <span><?php echo htmlspecialchars($event['location']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($event['description']): ?>
                                    <div class="event-description">
                                        <p><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Event Footer -->
                            <footer class="event-footer">
                                <div class="event-capacity" aria-label="Event capacity">
                                    <?php if ($event['capacity'] > 0): ?>
                                        <div class="capacity-info">
                                            <span class="capacity-text">
                                                <?php echo htmlspecialchars($event['current_participants']); ?> / 
                                                <?php echo htmlspecialchars($event['capacity']); ?> participants
                                            </span>
                                            <div class="capacity-bar" role="progressbar" 
                                                 aria-valuenow="<?php echo htmlspecialchars($capacity_percentage); ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100"
                                                 aria-label="Capacity: <?php echo htmlspecialchars($capacity_percentage); ?>% full">
                                                <div class="capacity-fill <?php echo $is_full ? 'capacity-full' : ''; ?>" 
                                                     style="width: <?php echo min(100, $capacity_percentage); ?>%"></div>
                                            </div>
                                            <?php if ($is_full): ?>
                                                <span class="capacity-status full" aria-label="Event is full">Full</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="capacity-info">
                                            <span class="capacity-text">
                                                <?php echo htmlspecialchars($event['current_participants']); ?> participants
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="event-actions">
                                    <?php if ($user_checked_in): ?>
                                        <div class="checkin-status status-checked-in" aria-label="You are checked in">
                                            <span role="img" aria-label="Checked in">✅</span>
                                            <span>Checked In</span>
                                            <time class="checkin-time" 
                                                  datetime="<?php echo htmlspecialchars($event['user_checkin_time']); ?>">
                                                at <?php echo htmlspecialchars(date('g:i A', strtotime($event['user_checkin_time']))); ?>
                                            </time>
                                        </div>
                                    <?php elseif ($can_check_in && !$is_full): ?>
                                        <button class="btn btn-primary btn-checkin" 
                                                onclick="quickCheckIn(<?php echo htmlspecialchars($event['event_id']); ?>)"
                                                aria-describedby="checkin-help-<?php echo $event['event_id']; ?>">
                                            <span class="btn-icon" role="img" aria-label="Check in">✅</span>
                                            <span>Quick Check-in</span>
                                        </button>
                                        <div id="checkin-help-<?php echo $event['event_id']; ?>" class="sr-only">
                                            Click to check in to this event
                                        </div>
                                    <?php elseif ($is_full && !$user_checked_in): ?>
                                        <div class="checkin-status status-full" aria-label="Event is full">
                                            <span role="img" aria-label="Full">🚫</span>
                                            <span>Event Full</span>
                                        </div>
                                    <?php elseif ($is_upcoming): ?>
                                        <div class="checkin-status status-upcoming" aria-label="Check-in not yet available">
                                            <span role="img" aria-label="Upcoming">⏰</span>
                                            <span>Check-in opens at event start</span>
                                        </div>
                                    <?php elseif ($is_past): ?>
                                        <div class="checkin-status status-past" aria-label="Event completed">
                                            <span role="img" aria-label="Completed">📋</span>
                                            <span>Event Completed</span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($canViewEventDetails): ?>
                                        <button class="btn btn-secondary btn-details" 
                                                onclick="showEventDetails(<?php echo htmlspecialchars($event['event_id']); ?>)"
                                                aria-describedby="details-help-<?php echo $event['event_id']; ?>">
                                            <span class="btn-icon" role="img" aria-label="Details">📋</span>
                                            <span>Details</span>
                                        </button>
                                        <div id="details-help-<?php echo $event['event_id']; ?>" class="sr-only">
                                            View detailed information about this event
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
        
        <!-- Enhanced Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav class="pagination-nav" aria-label="Event pagination" role="navigation">
                <div class="pagination-info">
                    <span class="pagination-summary">
                        Showing page <?php echo htmlspecialchars($page); ?> of <?php echo htmlspecialchars($total_pages); ?>
                        (<?php echo htmlspecialchars($total_records); ?> total events)
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
    
    <!-- Enhanced Event Details Modal -->
    <div id="eventDetailsModal" 
         class="modal" 
         role="dialog" 
         aria-labelledby="modalTitle" 
         aria-describedby="modalDescription"
         aria-hidden="true">
        <div class="modal-overlay" aria-hidden="true"></div>
        <div class="modal-container">
            <div class="modal-content">
                <header class="modal-header">
                    <h2 id="modalTitle" class="modal-title">Event Details</h2>
                    <button class="modal-close" 
                            aria-label="Close event details"
                            onclick="closeModal('eventDetailsModal')">
                        <span role="img" aria-hidden="true">×</span>
                    </button>
                </header>
                
                <div id="eventDetailsContent" 
                     class="modal-body"
                     aria-live="polite" 
                     aria-busy="false">
                    <div class="loading-state">
                        <div class="loading-spinner" aria-hidden="true"></div>
                        <span>Loading event details...</span>
                    </div>
                </div>
                
                <footer class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeModal('eventDetailsModal')">
                        Close
                    </button>
                </footer>
            </div>
        </div>
    </div>
    
    <!-- Notification Container -->
    <div id="notificationContainer" class="notification-container" aria-live="assertive" aria-atomic="true"></div>
    
    <!-- Enterprise JavaScript with Enhanced Features -->
    <script type="module">
        // Enhanced Events Management with Enterprise Security
        class EventsManager {
            constructor() {
                this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                this.connectionStatus = document.getElementById('connectionStatus');
                this.loadingOverlay = document.getElementById('loadingOverlay');
                this.eventDetailsModal = document.getElementById('eventDetailsModal');
                this.notificationContainer = document.getElementById('notificationContainer');
                
                this.initializeEventListeners();
                this.initializePerformanceMonitoring();
                this.initializeAccessibilityFeatures();
                this.startConnectionMonitoring();
            }
            
            initializeEventListeners() {
                // Enhanced keyboard navigation
                document.addEventListener('keydown', this.handleKeyboardNavigation.bind(this));
                
                // Form auto-submit on filter change
                const filterForm = document.querySelector('.filters-form');
                if (filterForm) {
                    const selects = filterForm.querySelectorAll('select');
                    selects.forEach(select => {
                        select.addEventListener('change', () => {
                            this.debounce(() => filterForm.submit(), 300)();
                        });
                    });
                }
                
                // Search input debouncing
                const searchInput = document.getElementById('search');
                if (searchInput) {
                    searchInput.addEventListener('input', this.debounce((e) => {
                        if (e.target.value.length >= 3 || e.target.value.length === 0) {
                            filterForm.submit();
                        }
                    }, 500));
                }
                
                // Modal event listeners
                this.eventDetailsModal?.addEventListener('click', (e) => {
                    if (e.target === this.eventDetailsModal || e.target.classList.contains('modal-overlay')) {
                        this.closeModal('eventDetailsModal');
                    }
                });
            }
            
            initializePerformanceMonitoring() {
                // Performance timing
                if (window.performance && window.performance.mark) {
                    window.performance.mark('events-page-interactive');
                }
                
                // Lazy loading for event images
                if ('IntersectionObserver' in window) {
                    this.setupLazyLoading();
                }
            }
            
            initializeAccessibilityFeatures() {
                // Skip link functionality
                const skipLink = document.querySelector('.skip-link');
                if (skipLink) {
                    skipLink.addEventListener('click', (e) => {
                        e.preventDefault();
                        const target = document.querySelector(skipLink.getAttribute('href'));
                        target?.focus();
                        target?.scrollIntoView({ behavior: 'smooth' });
                    });
                }
                
                // Enhanced focus management
                this.setupFocusTrapping();
                
                // Screen reader announcements
                this.setupLiveRegions();
            }
            
            startConnectionMonitoring() {
                this.checkConnection();
                setInterval(() => this.checkConnection(), 30000); // Check every 30 seconds
            }
            
            async checkConnection() {
                try {
                    const response = await fetch('../api/rfid-poll-noauth.php', { 
                        method: 'HEAD',
                        cache: 'no-cache'
                    });
                    
                    this.updateConnectionStatus(response.ok);
                } catch (error) {
                    this.updateConnectionStatus(false);
                }
            }
            
            updateConnectionStatus(isConnected) {
                if (!this.connectionStatus) return;
                
                const dot = this.connectionStatus.querySelector('.status-dot');
                const text = this.connectionStatus.querySelector('.status-text');
                
                if (isConnected) {
                    dot?.classList.add('status-connected');
                    dot?.classList.remove('status-disconnected');
                    text.textContent = 'Connected';
                } else {
                    dot?.classList.add('status-disconnected');
                    dot?.classList.remove('status-connected');
                    text.textContent = 'Disconnected';
                }
            }
            
            async quickCheckIn(eventId) {
                if (!eventId || !this.csrfToken) {
                    this.showNotification('Invalid request parameters', 'error');
                    return;
                }
                
                const button = document.querySelector(`[onclick="quickCheckIn(${eventId})"]`);
                if (button) {
                    button.disabled = true;
                    button.innerHTML = '<span class="loading-spinner"></span> Checking in...';
                }
                
                try {
                    const formData = new FormData();
                    formData.append('event_id', eventId);
                    formData.append('csrf_token', this.csrfToken);
                    
                    const response = await fetch('../api/manual-checkin.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.showNotification(result.message || 'Successfully checked in!', 'success');
                        
                        // Update UI to reflect check-in
                        this.updateEventCheckInStatus(eventId, true);
                        
                        // Reload page after short delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        this.showNotification(result.error || 'Check-in failed', 'error');
                    }
                } catch (error) {
                    console.error('Quick check-in error:', error);
                    this.showNotification('Network error occurred. Please try again.', 'error');
                } finally {
                    if (button) {
                        button.disabled = false;
                        button.innerHTML = '<span class="btn-icon">✅</span><span>Quick Check-in</span>';
                    }
                }
            }
            
            async showEventDetails(eventId) {
                if (!eventId) {
                    this.showNotification('Invalid event ID', 'error');
                    return;
                }
                
                const modal = this.eventDetailsModal;
                const content = document.getElementById('eventDetailsContent');
                
                if (!modal || !content) return;
                
                // Show modal and loading state
                modal.classList.add('show');
                modal.setAttribute('aria-hidden', 'false');
                content.setAttribute('aria-busy', 'true');
                content.innerHTML = `
                    <div class="loading-state">
                        <div class="loading-spinner" aria-hidden="true"></div>
                        <span>Loading event details...</span>
                    </div>
                `;
                
                // Focus management
                const firstFocusable = modal.querySelector('button, [tabindex="0"]');
                firstFocusable?.focus();
                
                try {
                    const response = await fetch(`../api/event-details.php?event_id=${encodeURIComponent(eventId)}&csrf_token=${encodeURIComponent(this.csrfToken)}`);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const event = await response.json();
                    
                    if (event.error) {
                        content.innerHTML = `
                            <div class="alert alert-error" role="alert">
                                <strong>Error:</strong> ${this.escapeHtml(event.error)}
                            </div>
                        `;
                        return;
                    }
                    
                    // Update modal title
                    const modalTitle = document.getElementById('modalTitle');
                    if (modalTitle) {
                        modalTitle.textContent = event.name || 'Event Details';
                    }
                    
                    content.innerHTML = this.renderEventDetails(event);
                    
                } catch (error) {
                    console.error('Error loading event details:', error);
                    content.innerHTML = `
                        <div class="alert alert-error" role="alert">
                            <strong>Error:</strong> Failed to load event details. Please try again.
                        </div>
                    `;
                } finally {
                    content.setAttribute('aria-busy', 'false');
                }
            }
            
            renderEventDetails(event) {
                const canCheckIn = event.is_current && !event.user_checkin_status;
                
                return `
                    <div class="event-details-content">
                        <div class="event-detail-header">
                            <h3>${this.escapeHtml(event.name)}</h3>
                            ${event.event_type ? `<span class="event-type">${this.escapeHtml(event.event_type)}</span>` : ''}
                        </div>
                        
                        <div class="event-detail-section">
                            <h4><span role="img" aria-label="Calendar">📅</span> Date & Time</h4>
                            <p>
                                <time datetime="${this.escapeHtml(event.start_time)}">
                                    ${this.formatDateTime(event.start_time)}
                                </time>
                                ${event.end_time ? ` - <time datetime="${this.escapeHtml(event.end_time)}">${this.formatDateTime(event.end_time)}</time>` : ''}
                            </p>
                        </div>
                        
                        ${event.location ? `
                            <div class="event-detail-section">
                                <h4><span role="img" aria-label="Location">📍</span> Location</h4>
                                <p>${this.escapeHtml(event.location)}</p>
                            </div>
                        ` : ''}
                        
                        ${event.description ? `
                            <div class="event-detail-section">
                                <h4><span role="img" aria-label="Description">📝</span> Description</h4>
                                <p>${this.escapeHtml(event.description).replace(/\n/g, '<br>')}</p>
                            </div>
                        ` : ''}
                        
                        <div class="event-detail-section">
                            <h4><span role="img" aria-label="Participants">👥</span> Participants</h4>
                            <p>
                                ${event.current_participants} ${event.capacity > 0 ? `/ ${event.capacity}` : ''} participants
                                ${event.capacity > 0 && event.current_participants >= event.capacity ? ' <span class="status-full">(Full)</span>' : ''}
                            </p>
                        </div>
                        
                        ${event.created_by_name ? `
                            <div class="event-detail-section">
                                <h4><span role="img" aria-label="Organizer">👤</span> Organizer</h4>
                                <p>${this.escapeHtml(event.created_by_name)}</p>
                            </div>
                        ` : ''}
                        
                        <div class="event-detail-actions">
                            ${event.user_checkin_status ? 
                                '<span class="status-badge status-checked-in"><span role="img" aria-label="Checked in">✅</span> Already Checked In</span>' : 
                                (canCheckIn ? 
                                    `<button class="btn btn-primary" onclick="eventsManager.quickCheckIn(${event.event_id}); eventsManager.closeModal('eventDetailsModal');" aria-describedby="checkin-modal-help">Check In Now</button>
                                     <div id="checkin-modal-help" class="sr-only">Click to check in to this event and close the modal</div>` : 
                                    '<span class="text-muted">Check-in not available</span>')
                            }
                        </div>
                    </div>
                `;
            }
            
            closeModal(modalId) {
                const modal = document.getElementById(modalId);
                if (!modal) return;
                
                modal.classList.remove('show');
                modal.setAttribute('aria-hidden', 'true');
                
                // Return focus to trigger element if possible
                const triggerElement = document.activeElement;
                if (triggerElement && triggerElement !== document.body) {
                    triggerElement.focus();
                }
            }
            
            updateEventCheckInStatus(eventId, checkedIn) {
                const eventCard = document.querySelector(`[data-event-id="${eventId}"]`);
                if (!eventCard) return;
                
                const actionsContainer = eventCard.querySelector('.event-actions');
                if (!actionsContainer) return;
                
                if (checkedIn) {
                    actionsContainer.innerHTML = `
                        <div class="checkin-status status-checked-in" aria-label="You are checked in">
                            <span role="img" aria-label="Checked in">✅</span>
                            <span>Checked In</span>
                            <time class="checkin-time">just now</time>
                        </div>
                        ${actionsContainer.querySelector('.btn-details')?.outerHTML || ''}
                    `;
                }
            }
            
            showNotification(message, type = 'info', duration = 5000) {
                if (!this.notificationContainer) return;
                
                // Remove existing notifications
                this.notificationContainer.querySelectorAll('.notification').forEach(n => n.remove());
                
                const notification = document.createElement('div');
                notification.className = `notification alert alert-${type}`;
                notification.setAttribute('role', type === 'error' ? 'alert' : 'status');
                notification.setAttribute('aria-live', 'assertive');
                
                notification.innerHTML = `
                    <div class="notification-content">
                        <span class="notification-message">${this.escapeHtml(message)}</span>
                        <button class="notification-close" onclick="this.closest('.notification').remove()" aria-label="Close notification">
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                `;
                
                this.notificationContainer.appendChild(notification);
                
                // Auto-remove after duration
                if (duration > 0) {
                    setTimeout(() => {
                        if (notification.parentElement) {
                            notification.remove();
                        }
                    }, duration);
                }
            }
            
            // Utility methods
            escapeHtml(unsafe) {
                return unsafe
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }
            
            formatDateTime(dateString) {
                return new Date(dateString).toLocaleString();
            }
            
            debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }
            
            handleKeyboardNavigation(e) {
                if (e.key === 'Escape') {
                    if (this.eventDetailsModal?.classList.contains('show')) {
                        this.closeModal('eventDetailsModal');
                    }
                }
            }
            
            setupFocusTrapping() {
                // Focus trapping logic for modals
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    modal.addEventListener('keydown', (e) => {
                        if (e.key === 'Tab') {
                            this.trapFocus(e, modal);
                        }
                    });
                });
            }
            
            trapFocus(e, container) {
                const focusableElements = container.querySelectorAll(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                );
                
                const firstElement = focusableElements[0];
                const lastElement = focusableElements[focusableElements.length - 1];
                
                if (e.shiftKey && document.activeElement === firstElement) {
                    e.preventDefault();
                    lastElement.focus();
                } else if (!e.shiftKey && document.activeElement === lastElement) {
                    e.preventDefault();
                    firstElement.focus();
                }
            }
            
            setupLiveRegions() {
                // Create live region for dynamic updates
                if (!document.getElementById('aria-live-region')) {
                    const liveRegion = document.createElement('div');
                    liveRegion.id = 'aria-live-region';
                    liveRegion.setAttribute('aria-live', 'polite');
                    liveRegion.setAttribute('aria-atomic', 'true');
                    liveRegion.className = 'sr-only';
                    document.body.appendChild(liveRegion);
                }
            }
            
            setupLazyLoading() {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            observer.unobserve(img);
                        }
                    });
                });
                
                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            }
        }
        
        // Initialize Events Manager
        const eventsManager = new EventsManager();
        
        // Global functions for backwards compatibility
        window.quickCheckIn = (eventId) => eventsManager.quickCheckIn(eventId);
        window.showEventDetails = (eventId) => eventsManager.showEventDetails(eventId);
        window.closeModal = (modalId) => eventsManager.closeModal(modalId);
        
        // Performance monitoring
        window.addEventListener('load', () => {
            if (window.performance && window.performance.mark) {
                window.performance.mark('events-page-complete');
                window.performance.measure('events-page-load-time', 'events-page-interactive', 'events-page-complete');
            }
        });
    </script>
    
    <!-- Non-critical CSS Loading -->
    <?php echo $assetOptimizer->getNonCriticalCSS(); ?>
    
    <!-- Theme Script -->
    <?php include '../includes/theme_script.php'; ?>
    
    <!-- End Performance Timer -->
    <?php 
    $performance->endTimer('events_page_load');
    $loadTime = $performance->getTimer('events_page_load');
    $performance->recordMetric('page_load_time', $loadTime);
    ?>
</body>
</html>
</body>
</html>
