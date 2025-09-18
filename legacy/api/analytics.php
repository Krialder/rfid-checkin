<?php
/**
 * Enterprise Analytics API Endpoint
 * 
 * Advanced analytics engine providing comprehensive data insights with
 * enterprise security, caching, and performance optimization.
 * 
 * Features:
 * - Repository pattern for all data operations
 * - Enterprise security with role-based access control
 * - Advanced caching for performance optimization
 * - Comprehensive metrics and KPI calculations
 * - Real-time data aggregation and insights
 * - Custom date range and filtering support
 * - Export capabilities for reports
 * 
 * Analytics Data:
 * - User engagement statistics and trends
 * - Event attendance patterns and insights
 * - Peak usage analysis and optimization
 * - Location and device utilization metrics
 * - Predictive analytics and recommendations
 * 
 * @package    RFID Check-in System
 * @subpackage Enterprise Analytics
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

// Initialize repositories and services
$userRepository = new UserRepository();
$eventRepository = new EventRepository();
$checkinRepository = new CheckinRepository();
$dataService = new DataService();

// Set enterprise headers for analytics API
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Start performance monitoring for analytics
$performanceManager->startTimer('analytics_processing');

try {
    // Enterprise authentication and authorization
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
        
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Insufficient permissions to access analytics'
        ]);
        exit;
    }
    
    // Validate and sanitize input parameters with enterprise validation
    $params = [
        'date_range' => $securityManager->validateInput($_GET['range'] ?? '30', 'string', ['7', '30', '90', '365', 'custom']),
        'custom_start' => $securityManager->validateInput($_GET['custom_start'] ?? '', 'date'),
        'custom_end' => $securityManager->validateInput($_GET['custom_end'] ?? '', 'date'),
        'view_mode' => $securityManager->validateInput($_GET['view'] ?? 'personal', 'string', ['personal', 'system']),
        'export_format' => $securityManager->validateInput($_GET['export'] ?? '', 'string', ['', 'csv', 'json']),
        'user_id' => $user['user_id']
    ];
    
    // Enforce role-based view permissions
    if ($params['view_mode'] === 'system' && !$securityManager->hasRole($user, 'admin')) {
        $params['view_mode'] = 'personal';
        $errorHandler->log('Non-admin user attempted system analytics view', null, 'INFO', [
            'user_id' => $user['user_id']
        ]);
    }
    
    // Check analytics rate limiting
    if (!$securityManager->checkRateLimit('analytics_' . $user['user_id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown', 10, 300)) {
        $errorHandler->log('Analytics rate limit exceeded', null, 'WARNING', [
            'user_id' => $user['user_id']
        ]);
        
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Too many analytics requests. Please wait before requesting again.',
            'retry_after' => 300
        ]);
        exit;
    }
    
    $performanceManager->startTimer('analytics_data_generation');
    
    // Get comprehensive analytics data using enterprise service layer
    $analyticsData = $dataService->getAnalyticsData($params);
    
    $performanceManager->endTimer('analytics_data_generation');
    
    // Record analytics usage metrics
    $performanceManager->recordMetric('analytics_request', 1);
    $performanceManager->recordMetric('analytics_view_mode_' . $params['view_mode'], 1);
    $performanceManager->recordMetric('analytics_date_range_' . $params['date_range'], 1);
    
    // Build enterprise response structure
    $response = [
        'success' => true,
        'data' => $analyticsData,
        'parameters' => [
            'date_range' => $params['date_range'],
            'view_mode' => $params['view_mode'],
            'date_start' => $analyticsData['date_range']['start'] ?? null,
            'date_end' => $analyticsData['date_range']['end'] ?? null
        ],
        'meta' => [
            'api_version' => '4.0.0',
            'user_permissions' => [
                'view_analytics' => true,
                'system_view' => $securityManager->hasRole($user, 'admin'),
                'export_data' => $securityManager->hasPermission($user, 'export_analytics')
            ],
            'processing_time_ms' => round($performanceManager->getTimer('analytics_processing') * 1000, 2),
            'cache_info' => $analyticsData['cache_info'] ?? null,
            'timestamp' => date('c')
        ]
    ];
    
    // Handle export requests if user has permission
    if (!empty($params['export_format']) && $securityManager->hasPermission($user, 'export_analytics')) {
        $performanceManager->startTimer('analytics_export');
        
        $exportData = $dataService->exportAnalyticsData($analyticsData, $params['export_format']);
        
        $performanceManager->endTimer('analytics_export');
        $performanceManager->recordMetric('analytics_export_' . $params['export_format'], 1);
        
        $response['export'] = $exportData;
    }
    
    // Return enterprise JSON response
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

} catch (Exception $e) {
    // Enterprise error handling with comprehensive logging
    $errorHandler->log('Analytics API error', $e, 'ERROR', [
        'user_id' => $user['user_id'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'parameters' => $params ?? [],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Record error metrics
    $performanceManager->recordMetric('analytics_api_error', 1);
    
    // Determine appropriate HTTP status code
    $statusCode = 500;
    if (strpos($e->getMessage(), 'permission') !== false) {
        $statusCode = 403;
    } elseif (strpos($e->getMessage(), 'validation') !== false) {
        $statusCode = 400;
    } elseif (strpos($e->getMessage(), 'session') !== false) {
        $statusCode = 401;
    }
    
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'meta' => [
            'api_version' => '4.0.0',
            'error_id' => uniqid('analytics_err_'),
            'timestamp' => date('c')
        ]
    ]);
} finally {
    // Complete performance monitoring
    $performanceManager->endTimer('analytics_processing');
}
?>
    
    // Admin can view system-wide analytics
    if ($user['role'] === 'admin' && $view_mode === 'system') {
        $user_condition = '';
        $user_params = [];
    }
    
    $all_params = array_merge($user_params, $date_params);
    
    $response = [
        'stats' => getKeyStats($db, $user_condition, $date_condition, $all_params),
        'timeline' => getTimelineData($db, $user_condition, $date_condition, $all_params, $date_range),
        'peak_hours' => getPeakHours($db, $user_condition, $date_condition, $all_params),
        'days_of_week' => getDaysOfWeek($db, $user_condition, $date_condition, $all_params),
        'event_types' => getEventTypes($db, $user_condition, $date_condition, $all_params),
        'locations' => getTopLocations($db, $user_condition, $date_condition, $all_params),
        'methods' => getCheckinMethods($db, $user_condition, $date_condition, $all_params),
        'insights' => generateInsights($db, $user_condition, $date_condition, $all_params, $view_mode)
    ];
    
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Analytics API Error: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to load analytics data']);
}

function getKeyStats($db, $user_condition, $date_condition, $params) {
    try {
        // Total check-ins
        $stmt = $db->prepare("
            SELECT COUNT(*) as total,
                   COUNT(DISTINCT e.event_id) as events,
                   AVG(TIMESTAMPDIFF(MINUTE, c.checkin_time, COALESCE(c.checkout_time, NOW()))) as avg_duration
            FROM checkin c
            LEFT JOIN events e ON c.event_id = e.event_id
            WHERE 1=1 $user_condition $date_condition
        ");
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate attendance rate for personal view
        $attendance_rate = 'N/A';
        if (!empty($user_condition)) {
            $user_id = $params[0];
            $rate_stmt = $db->prepare("
                SELECT 
                    COUNT(DISTINCT c.event_id) as attended,
                    COUNT(DISTINCT e.event_id) as total_events
                FROM events e
                LEFT JOIN checkin c ON e.event_id = c.event_id AND c.user_id = ?
                WHERE e.start_time <= NOW() $date_condition
            ");
            $rate_params = array_merge([$user_id], array_slice($params, 1));
            $rate_stmt->execute($rate_params);
            $rate_result = $rate_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($rate_result['total_events'] > 0) {
                $attendance_rate = round(($rate_result['attended'] / $rate_result['total_events']) * 100) . '%';
            }
        }
        
        return [
            'total_checkins' => $result['total'] ?? 0,
            'unique_events' => $result['events'] ?? 0,
            'avg_duration' => $result['avg_duration'] ? round($result['avg_duration']) . ' min' : 'N/A',
            'attendance_rate' => $attendance_rate
        ];
        
    } catch (Exception $e) {
        error_log("Key stats error: " . $e->getMessage());
        return ['total_checkins' => 0, 'unique_events' => 0, 'avg_duration' => 'N/A', 'attendance_rate' => 'N/A'];
    }
}

function getTimelineData($db, $user_condition, $date_condition, $params, $date_range) {
    try {
        $group_by = 'DATE(c.checkin_time)';
        $date_format = '%Y-%m-%d';
        
        if ($date_range === '7') {
            $group_by = 'DATE(c.checkin_time)';
            $date_format = '%a %m/%d';
        } elseif (in_array($date_range, ['90', '365'])) {
            $group_by = 'YEAR(c.checkin_time), WEEK(c.checkin_time)';
            $date_format = 'Week %u';
        }
        
        $stmt = $db->prepare("
            SELECT 
                $group_by as period,
                DATE_FORMAT(c.checkin_time, '$date_format') as label,
                COUNT(*) as count
            FROM checkin c
            WHERE 1=1 $user_condition $date_condition
            GROUP BY $group_by
            ORDER BY period
        ");
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'labels' => array_column($results, 'label'),
            'values' => array_column($results, 'count')
        ];
        
    } catch (Exception $e) {
        error_log("Timeline data error: " . $e->getMessage());
        return ['labels' => [], 'values' => []];
    }
}

function getPeakHours($db, $user_condition, $date_condition, $params) {
    try {
        $stmt = $db->prepare("
            SELECT 
                HOUR(c.checkin_time) as hour,
                COUNT(*) as count
            FROM checkin c
            WHERE 1=1 $user_condition $date_condition
            GROUP BY HOUR(c.checkin_time)
            ORDER BY hour
        ");
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fill in missing hours with 0
        $hours = [];
        for ($i = 0; $i < 24; $i ++) {
            $hours[$i] = 0;
        }
        
        foreach ($results as $result) {
            $hours[$result['hour']] = $result['count'];
        }
        
        $labels = [];
        $values = [];
        
        for ($i = 0; $i < 24; $i ++) {
            $labels[] = sprintf('%02d:00', $i);
            $values[] = $hours[$i];
        }
        
        return [
            'labels' => $labels,
            'values' => $values
        ];
        
    } catch (Exception $e) {
        error_log("Peak hours error: " . $e->getMessage());
        return ['labels' => [], 'values' => []];
    }
}

function getDaysOfWeek($db, $user_condition, $date_condition, $params) {
    try {
        $stmt = $db->prepare("
            SELECT 
                DAYNAME(c.checkin_time) as day_name,
                DAYOFWEEK(c.checkin_time) as day_num,
                COUNT(*) as count
            FROM checkin c
            WHERE 1=1 $user_condition $date_condition
            GROUP BY DAYOFWEEK(c.checkin_time), DAYNAME(c.checkin_time)
            ORDER BY day_num
        ");
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'labels' => array_column($results, 'day_name'),
            'values' => array_column($results, 'count')
        ];
        
    } catch (Exception $e) {
        error_log("Days of week error: " . $e->getMessage());
        return ['labels' => [], 'values' => []];
    }
}

function getEventTypes($db, $user_condition, $date_condition, $params) {
    try {
        $stmt = $db->prepare("
            SELECT 
                COALESCE(e.event_type, 'General') as type,
                COUNT(*) as count
            FROM checkin c
            LEFT JOIN events e ON c.event_id = e.event_id
            WHERE 1=1 $user_condition $date_condition
            GROUP BY COALESCE(e.event_type, 'General')
            ORDER BY count DESC
        ");
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'labels' => array_column($results, 'type'),
            'values' => array_column($results, 'count')
        ];
        
    } catch (Exception $e) {
        error_log("Event types error: " . $e->getMessage());
        return ['labels' => [], 'values' => []];
    }
}

function getTopLocations($db, $user_condition, $date_condition, $params) {
    try {
        $stmt = $db->prepare("
            SELECT 
                COALESCE(e.location, 'Unknown') as location,
                COUNT(*) as count
            FROM checkin c
            LEFT JOIN events e ON c.event_id = e.event_id
            WHERE 1=1 $user_condition $date_condition
            GROUP BY COALESCE(e.location, 'Unknown')
            ORDER BY count DESC
            LIMIT 10
        ");
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'labels' => array_column($results, 'location'),
            'values' => array_column($results, 'count')
        ];
        
    } catch (Exception $e) {
        error_log("Top locations error: " . $e->getMessage());
        return ['labels' => [], 'values' => []];
    }
}

function getCheckinMethods($db, $user_condition, $date_condition, $params) {
    try {
        $stmt = $db->prepare("
            SELECT 
                CASE 
                    WHEN c.checkin_method = 'rfid' THEN 'RFID'
                    ELSE 'Manual'
                END as method,
                COUNT(*) as count
            FROM checkin c
            WHERE 1=1 $user_condition $date_condition
            GROUP BY method
        ");
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'labels' => array_column($results, 'method'),
            'values' => array_column($results, 'count')
        ];
        
    } catch (Exception $e) {
        error_log("Checkin methods error: " . $e->getMessage());
        return ['labels' => [], 'values' => []];
    }
}

function generateInsights($db, $user_condition, $date_condition, $params, $view_mode) {
    $insights = [];
    
    try {
        // Most active day
        $stmt = $db->prepare("
            SELECT 
                DAYNAME(c.checkin_time) as day_name,
                COUNT(*) as count
            FROM checkin c
            WHERE 1=1 $user_condition $date_condition
            GROUP BY DAYOFWEEK(c.checkin_time), DAYNAME(c.checkin_time)
            ORDER BY count DESC
            LIMIT 1
        ");
        $stmt->execute($params);
        $mostActiveDay = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mostActiveDay) {
            $insights[] = [
                'value' => $mostActiveDay['day_name'],
                'title' => 'Most Active Day',
                'description' => "You checked in {$mostActiveDay['count']} times on {$mostActiveDay['day_name']}s"
            ];
        }
        
        // Peak hour
        $stmt = $db->prepare("
            SELECT 
                HOUR(c.checkin_time) as hour,
                COUNT(*) as count
            FROM checkin c
            WHERE 1=1 $user_condition $date_condition
            GROUP BY HOUR(c.checkin_time)
            ORDER BY count DESC
            LIMIT 1
        ");
        $stmt->execute($params);
        $peakHour = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($peakHour) {
            $hour24 = $peakHour['hour'];
            $hour12 = $hour24 == 0 ? '12 AM' : ($hour24 <= 12 ? $hour24 . ' AM' : ($hour24 - 12) . ' PM');
            
            $insights[] = [
                'value' => $hour12,
                'title' => 'Peak Hour',
                'description' => "{$peakHour['count']} check-ins happened around this time"
            ];
        }
        
        // Favorite event type
        $stmt = $db->prepare("
            SELECT 
                COALESCE(e.event_type, 'General') as type,
                COUNT(*) as count
            FROM checkin c
            LEFT JOIN events e ON c.event_id = e.event_id
            WHERE 1=1 $user_condition $date_condition
            GROUP BY COALESCE(e.event_type, 'General')
            ORDER BY count DESC
            LIMIT 1
        ");
        $stmt->execute($params);
        $favoriteType = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($favoriteType) {
            $insights[] = [
                'value' => $favoriteType['type'],
                'title' => 'Favorite Event Type',
                'description' => "{$favoriteType['count']} check-ins for {$favoriteType['type']} events"
            ];
        }
        
        // Average check-ins per day
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) / COUNT(DISTINCT DATE(c.checkin_time)) as avg_per_day
            FROM checkin c
            WHERE 1=1 $user_condition $date_condition
            HAVING COUNT(DISTINCT DATE(c.checkin_time)) > 0
        ");
        $stmt->execute($params);
        $avgPerDay = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($avgPerDay && $avgPerDay['avg_per_day'] > 0) {
            $insights[] = [
                'value' => round($avgPerDay['avg_per_day'], 1),
                'title' => 'Daily Average',
                'description' => 'Average check-ins per active day'
            ];
        }
        
        // Check-in streak (for personal view)
        if (!empty($user_condition)) {
            $streak = calculateCheckInStreak($db, $params[0]);
            if ($streak > 0) {
                $insights[] = [
                    'value' => $streak,
                    'title' => 'Current Streak',
                    'description' => 'Consecutive days with at least one check-in'
                ];
            }
        }
        
        // System-wide insights for admins
        if ($view_mode === 'system') {
            $stmt = $db->prepare("
                SELECT COUNT(DISTINCT c.user_id) as active_users
                FROM checkin c
                WHERE 1=1 $date_condition
            ");
            $stmt->execute($date_params);
            $activeUsers = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($activeUsers) {
                $insights[] = [
                    'value' => $activeUsers['active_users'],
                    'title' => 'Active Users',
                    'description' => 'Users who checked in during this period'
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Generate insights error: " . $e->getMessage());
    }
    
    return $insights;
}

function calculateCheckInStreak($db, $user_id) {
    try {
        $stmt = $db->prepare("
            SELECT DATE(checkin_time) as checkin_date
            FROM checkin
            WHERE user_id = ?
            GROUP BY DATE(checkin_time)
            ORDER BY checkin_date DESC
        ");
        $stmt->execute([$user_id]);
        $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($dates)) {
            return 0;
        }
        
        $streak = 0;
        $current_date = new DateTime();
        
        foreach ($dates as $date) {
            $check_date = new DateTime($date);
            $diff = $current_date->diff($check_date)->days;
            
            if ($diff === $streak) {
                $streak ++;
                $current_date->modify('-1 day');
            } else {
                break;
            }
        }
        
        return $streak;
        
    } catch (Exception $e) {
        error_log("Calculate streak error: " . $e->getMessage());
        return 0;
    }
}
