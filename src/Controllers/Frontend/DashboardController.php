<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers\Frontend;

use RfidCheckin\Controllers\BaseFrontendController;
use RfidCheckin\Repositories\EventRepository;
use RfidCheckin\Repositories\UserRepository;
use RfidCheckin\Repositories\CheckinRepository;
use RfidCheckin\Services\AuthenticationService;

/**
 * Dashboard Frontend Controller
 * 
 * Handles the main dashboard interface showing overview data,
 * recent activity, upcoming events, and quick actions.
 * Replaces scattered dashboard functionality.
 * 
 * @package RfidCheckin\Controllers\Frontend
 * @version 1.0.0
 * @author Senior Development Team
 */
class DashboardController extends BaseFrontendController
{
    private EventRepository $eventRepo;
    private UserRepository $userRepo;
    private CheckinRepository $checkinRepo;

    public function __construct()
    {
        parent::__construct();
        
        $this->eventRepo = new EventRepository();
        $this->userRepo = new UserRepository();
        $this->checkinRepo = new CheckinRepository();
    }

    /**
     * Main dashboard page
     */
    public function index(): void
    {
        $this->requireAuth();
        
        $user = $this->auth->getCurrentUser();
        $isAdmin = $this->hasPermissions(['admin']);
        $canManageEvents = $this->hasPermissions(['admin', 'manage_events']);

        // Get dashboard data based on user permissions
        $data = [
            'user' => $user,
            'statistics' => $this->getDashboardStatistics($user, $isAdmin),
            'recent_activity' => $this->getRecentActivity($user, $isAdmin),
            'upcoming_events' => $this->getUpcomingEvents($user),
            'quick_actions' => $this->getQuickActions($user),
            'system_alerts' => $isAdmin ? $this->getSystemAlerts() : []
        ];

        // Add admin-specific data
        if ($isAdmin) {
            $data['admin_overview'] = $this->getAdminOverview();
            $data['performance_metrics'] = $this->getPerformanceMetrics();
        }

        $this->render('dashboard/index', $data, [
            'title' => 'Dashboard',
            'page_class' => 'dashboard-page',
            'require_charts' => true
        ]);
    }

    /**
     * Real-time activity feed (AJAX endpoint)
     */
    public function activity(): void
    {
        $this->requireAuth();
        $this->requireAjax();

        $user = $this->auth->getCurrentUser();
        $isAdmin = $this->hasPermissions(['admin', 'view_reports']);
        
        $limit = (int) ($_GET['limit'] ?? 10);
        $since = $_GET['since'] ?? null;

        $activity = $this->getRecentActivity($user, $isAdmin, $limit, $since);

        $this->renderJson([
            'success' => true,
            'activity' => $activity,
            'timestamp' => date('c')
        ]);
    }

    /**
     * Dashboard statistics widget
     */
    public function statistics(): void
    {
        $this->requireAuth();
        $this->requireAjax();

        $user = $this->auth->getCurrentUser();
        $isAdmin = $this->hasPermissions(['admin']);
        $timeframe = $_GET['timeframe'] ?? '7'; // days

        $stats = $this->getDashboardStatistics($user, $isAdmin, (int) $timeframe);

        $this->renderJson([
            'success' => true,
            'statistics' => $stats,
            'timeframe' => $timeframe
        ]);
    }

    /**
     * Quick check-in interface
     */
    public function quickCheckin(): void
    {
        $this->requireAuth();

        if (!$this->hasPermissions(['admin', 'check_in_users'])) {
            $this->redirectWithError('/dashboard', 'Insufficient permissions');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processQuickCheckin();
            return;
        }

        $data = [
            'active_events' => $this->eventRepo->findMany(['status' => 'active'], 1, 50)['data'],
            'recent_users' => $this->userRepo->getRecentUsers(20)
        ];

        $this->render('dashboard/quick-checkin', $data, [
            'title' => 'Quick Check-in',
            'page_class' => 'quick-checkin-page'
        ]);
    }

    /**
     * Process quick check-in form
     */
    private function processQuickCheckin(): void
    {
        $input = $this->sanitizeInput($_POST);

        // Validate input
        if (empty($input['user_id'])) {
            $this->redirectWithError('/dashboard/quick-checkin', 'Please select a user');
            return;
        }

        $userId = (int) $input['user_id'];
        $eventId = !empty($input['event_id']) ? (int) $input['event_id'] : null;
        $checkedInBy = $this->auth->getCurrentUserId();

        try {
            $result = $this->checkinRepo->processManualCheckin($userId, $eventId, $checkedInBy);

            if ($result['success']) {
                $this->redirectWithSuccess('/dashboard', 'Check-in successful: ' . $result['user']['name']);
            } else {
                $this->redirectWithError('/dashboard/quick-checkin', $result['message']);
            }
        } catch (\Exception $e) {
            $this->logger->error('Quick check-in failed', [
                'user_id' => $userId,
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);
            
            $this->redirectWithError('/dashboard/quick-checkin', 'Check-in failed');
        }
    }

    /**
     * System status widget (Admin only)
     */
    public function systemStatus(): void
    {
        $this->requireAuth();
        $this->requireAjax();

        if (!$this->hasPermissions(['admin'])) {
            $this->renderJson(['error' => 'Unauthorized'], 403);
            return;
        }

        $status = [
            'database' => $this->checkDatabaseStatus(),
            'storage' => $this->checkStorageStatus(),
            'services' => $this->checkServiceStatus(),
            'security' => $this->checkSecurityStatus()
        ];

        $this->renderJson([
            'success' => true,
            'status' => $status,
            'overall_health' => $this->calculateOverallHealth($status)
        ]);
    }

    /**
     * Helper Methods
     */

    /**
     * Get dashboard statistics
     */
    private function getDashboardStatistics(array $user, bool $isAdmin, int $timeframeDays = 7): array
    {
        $dateFrom = date('Y-m-d', strtotime("-{$timeframeDays} days"));
        $dateTo = date('Y-m-d');

        if ($isAdmin) {
            // Admin sees system-wide statistics
            return [
                'total_users' => $this->userRepo->getTotalCount(),
                'active_users' => $this->userRepo->getActiveCount(),
                'total_events' => $this->eventRepo->getTotalCount(),
                'upcoming_events' => $this->eventRepo->getUpcomingCount(),
                'checkins_today' => $this->checkinRepo->getTodayCount(),
                'checkins_period' => $this->checkinRepo->getPeriodCount($dateFrom, $dateTo),
                'rfid_tags_assigned' => $this->userRepo->getRfidTagCount(),
                'system_uptime' => $this->getSystemUptime()
            ];
        } else {
            // Regular users see their personal statistics
            $userId = $user['user_id'];
            return [
                'my_checkins_today' => $this->checkinRepo->getUserTodayCount($userId),
                'my_checkins_period' => $this->checkinRepo->getUserPeriodCount($userId, $dateFrom, $dateTo),
                'my_registered_events' => $this->eventRepo->getUserRegisteredCount($userId),
                'my_upcoming_events' => $this->eventRepo->getUserUpcomingCount($userId),
                'last_checkin' => $this->checkinRepo->getUserLastCheckin($userId),
                'account_created' => $user['created_at']
            ];
        }
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity(array $user, bool $canViewAll, int $limit = 10, ?string $since = null): array
    {
        if ($canViewAll) {
            // Show system-wide activity
            return $this->checkinRepo->getRealtimeFeed($limit);
        } else {
            // Show only user's activity
            $filters = [];
            if ($since) {
                $filters['since'] = $since;
            }
            
            $result = $this->checkinRepo->getUserCheckinHistory($user['user_id'], $filters, 1, $limit);
            return $result['data'];
        }
    }

    /**
     * Get upcoming events for user
     */
    private function getUpcomingEvents(array $user): array
    {
        $userId = $user['user_id'];
        
        if ($this->hasPermissions(['admin', 'manage_events'])) {
            // Show all upcoming events
            return $this->eventRepo->getUpcoming(10);
        } else {
            // Show only user's registered events
            return $this->eventRepo->getUpcoming(10, $userId);
        }
    }

    /**
     * Get quick actions based on user permissions
     */
    private function getQuickActions(array $user): array
    {
        $actions = [];

        if ($this->hasPermissions(['admin', 'check_in_users'])) {
            $actions[] = [
                'title' => 'Quick Check-in',
                'description' => 'Manually check in users',
                'url' => '/dashboard/quick-checkin',
                'icon' => 'fa-user-check',
                'color' => 'primary'
            ];
        }

        if ($this->hasPermissions(['admin', 'manage_events'])) {
            $actions[] = [
                'title' => 'Create Event',
                'description' => 'Add new event',
                'url' => '/events/create',
                'icon' => 'fa-calendar-plus',
                'color' => 'success'
            ];
        }

        if ($this->hasPermissions(['admin', 'manage_users'])) {
            $actions[] = [
                'title' => 'Add User',
                'description' => 'Register new user',
                'url' => '/users/create',
                'icon' => 'fa-user-plus',
                'color' => 'info'
            ];
        }

        if ($this->hasPermissions(['admin', 'view_reports'])) {
            $actions[] = [
                'title' => 'View Reports',
                'description' => 'Analytics and reports',
                'url' => '/analytics',
                'icon' => 'fa-chart-bar',
                'color' => 'warning'
            ];
        }

        // Always show profile action
        $actions[] = [
            'title' => 'My Profile',
            'description' => 'Update profile settings',
            'url' => '/profile',
            'icon' => 'fa-user-cog',
            'color' => 'secondary'
        ];

        return $actions;
    }

    /**
     * Get system alerts (Admin only)
     */
    private function getSystemAlerts(): array
    {
        $alerts = [];

        // Check for system issues
        if ($this->checkLowStorageSpace()) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Low Storage Space',
                'message' => 'System storage is running low',
                'action_url' => '/admin/system',
                'action_text' => 'Manage Storage'
            ];
        }

        if ($this->checkFailedLogins()) {
            $alerts[] = [
                'type' => 'danger',
                'title' => 'Security Alert',
                'message' => 'Multiple failed login attempts detected',
                'action_url' => '/admin/security',
                'action_text' => 'View Details'
            ];
        }

        if ($this->checkPendingUsers()) {
            $count = $this->userRepo->getPendingCount();
            $alerts[] = [
                'type' => 'info',
                'title' => 'Pending User Approvals',
                'message' => "{$count} users awaiting approval",
                'action_url' => '/admin/users?status=pending',
                'action_text' => 'Review Users'
            ];
        }

        return $alerts;
    }

    /**
     * Get admin overview data
     */
    private function getAdminOverview(): array
    {
        return [
            'active_sessions' => $this->getActiveSessionCount(),
            'daily_checkins' => $this->getTodayCheckinStats(),
            'system_load' => $this->getSystemLoad(),
            'recent_errors' => $this->getRecentErrors()
        ];
    }

    /**
     * Get basic performance metrics
     */
    private function getPerformanceMetrics(): array
    {
        return [
            'response_time' => $this->getAverageResponseTime(),
            'database_queries' => $this->getDatabaseQueryCount(),
            'memory_usage' => $this->getMemoryUsage(),
            'cache_hit_rate' => $this->getCacheHitRate()
        ];
    }

    // Placeholder methods for system checks (would be implemented based on actual system)
    private function checkDatabaseStatus(): array { return ['status' => 'healthy', 'connections' => 5]; }
    private function checkStorageStatus(): array { return ['status' => 'healthy', 'usage' => '45%']; }
    private function checkServiceStatus(): array { return ['status' => 'healthy', 'services' => 3]; }
    private function checkSecurityStatus(): array { return ['status' => 'healthy', 'threats' => 0]; }
    private function calculateOverallHealth(array $status): string { return 'healthy'; }
    private function getSystemUptime(): string { return '99.9%'; }
    private function checkLowStorageSpace(): bool { return false; }
    private function checkFailedLogins(): bool { return false; }
    private function checkPendingUsers(): bool { return $this->userRepo->getPendingCount() > 0; }
    private function getActiveSessionCount(): int { return 42; }
    private function getTodayCheckinStats(): array { return ['total' => 156, 'unique' => 89]; }
    private function getSystemLoad(): string { return '45%'; }
    private function getRecentErrors(): array { return []; }
    private function getAverageResponseTime(): string { return '120ms'; }
    private function getDatabaseQueryCount(): int { return 1250; }
    private function getMemoryUsage(): string { return '62%'; }
    private function getCacheHitRate(): string { return '85%'; }
}
