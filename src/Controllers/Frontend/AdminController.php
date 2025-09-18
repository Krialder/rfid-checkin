<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers\Frontend;

use RfidCheckin\Controllers\BaseFrontendController;
use RfidCheckin\Repositories\UserRepository;
use RfidCheckin\Repositories\EventRepository;
use RfidCheckin\Repositories\CheckinRepository;
use Exception;

/**
 * Admin Controller
 * 
 * Handles administrative functionality including system management,
 * user administration, event management, reports, and system configuration.
 * 
 * Features:
 * - System overview and statistics
 * - User management (create, edit, delete, permissions)
 * - Event management and scheduling
 * - RFID device management
 * - System reports and analytics
 * - Security audit logs
 * - System configuration
 * - Database maintenance
 * 
 * @package RfidCheckin\Controllers\Frontend
 * @version 1.0.0
 * @author Senior Development Team
 */
class AdminController extends BaseFrontendController
{
    private UserRepository $userRepository;
    private EventRepository $eventRepository;
    private CheckinRepository $checkinRepository;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->userRepository = new UserRepository($this->db);
        $this->eventRepository = new EventRepository($this->db);
        $this->checkinRepository = new CheckinRepository($this->db);
        
        // Require admin permissions for all admin actions
        $this->requirePermissions(['admin']);
        
        // Set common breadcrumbs
        $this->addBreadcrumb('Dashboard', '/dashboard');
        $this->addBreadcrumb('Admin', '/admin');
    }

    /**
     * Admin dashboard overview
     */
    public function index(): void
    {
        try {
            $data = [
                'system_stats' => $this->getSystemStats(),
                'recent_activity' => $this->getRecentActivity(),
                'pending_tasks' => $this->getPendingTasks(),
                'system_health' => $this->getSystemHealth(),
                'security_alerts' => $this->getSecurityAlerts()
            ];

            $this->render('admin.index', $data, [
                'title' => 'System Administration',
                'description' => 'System overview and administrative tools',
                'page_class' => 'admin-dashboard',
                'require_charts' => true
            ]);

            $this->logUserAction('admin_dashboard_viewed');

        } catch (Exception $e) {
            $this->logger->error('Admin dashboard error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->redirectWithError('/dashboard', 'Unable to load admin dashboard');
        }
    }

    /**
     * User management interface
     */
    public function users(): void
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? '';
            $groupFilter = $_GET['group'] ?? '';
            $statusFilter = $_GET['status'] ?? '';

            $filters = [];
            if ($search) $filters['search'] = $search;
            if ($groupFilter) $filters['group_id'] = $groupFilter;
            if ($statusFilter) $filters['status'] = $statusFilter;

            $users = $this->userRepository->getUsers($filters, $page, 25);
            $totalUsers = $this->userRepository->getUserCount($filters);
            $userGroups = $this->getUserGroups();

            $data = [
                'users' => $users,
                'pagination' => $this->calculatePagination($totalUsers, $page, 25),
                'user_groups' => $userGroups,
                'search' => $search,
                'group_filter' => $groupFilter,
                'status_filter' => $statusFilter,
                'user_stats' => $this->getUserStats()
            ];

            $this->addBreadcrumb('User Management');
            
            $this->render('admin.users', $data, [
                'title' => 'User Management',
                'description' => 'Manage system users and permissions',
                'page_class' => 'admin-users',
                'require_datatables' => true
            ]);

            $this->logUserAction('admin_users_viewed', ['filters' => $filters]);

        } catch (Exception $e) {
            $this->logger->error('Admin users page error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->redirectWithError('/admin', 'Unable to load user management');
        }
    }

    /**
     * Create/Edit user form
     */
    public function userForm(): void
    {
        $userId = $_GET['id'] ?? null;
        $isEdit = $userId !== null;

        if ($this->isMethod('POST')) {
            $this->handleUserFormSubmission($userId);
            return;
        }

        try {
            $user = $isEdit ? $this->userRepository->getUserById((int)$userId) : null;
            $userGroups = $this->getUserGroups();

            if ($isEdit && !$user) {
                $this->redirectWithError('/admin/users', 'User not found');
                return;
            }

            $data = [
                'user' => $user,
                'user_groups' => $userGroups,
                'is_edit' => $isEdit,
                'available_permissions' => $this->getAvailablePermissions()
            ];

            $this->addBreadcrumb('User Management', '/admin/users');
            $this->addBreadcrumb($isEdit ? 'Edit User' : 'Create User');

            $this->render('admin.user-form', $data, [
                'title' => $isEdit ? 'Edit User' : 'Create User',
                'description' => $isEdit ? 'Edit user details and permissions' : 'Create new system user',
                'page_class' => 'admin-user-form'
            ]);

        } catch (Exception $e) {
            $this->logger->error('Admin user form error', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->redirectWithError('/admin/users', 'Unable to load user form');
        }
    }

    /**
     * Events management interface
     */
    public function events(): void
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? '';
            $statusFilter = $_GET['status'] ?? '';
            $dateFilter = $_GET['date'] ?? '';

            $filters = [];
            if ($search) $filters['search'] = $search;
            if ($statusFilter) $filters['status'] = $statusFilter;
            if ($dateFilter) $filters['date_range'] = $dateFilter;

            $events = $this->eventRepository->getEvents($filters, $page, 25);
            $totalEvents = $this->eventRepository->getEventCount($filters);

            $data = [
                'events' => $events,
                'pagination' => $this->calculatePagination($totalEvents, $page, 25),
                'search' => $search,
                'status_filter' => $statusFilter,
                'date_filter' => $dateFilter,
                'event_stats' => $this->getEventStats()
            ];

            $this->addBreadcrumb('Event Management');

            $this->render('admin.events', $data, [
                'title' => 'Event Management',
                'description' => 'Manage events and check-in sessions',
                'page_class' => 'admin-events',
                'require_datatables' => true
            ]);

            $this->logUserAction('admin_events_viewed', ['filters' => $filters]);

        } catch (Exception $e) {
            $this->logger->error('Admin events page error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->redirectWithError('/admin', 'Unable to load event management');
        }
    }

    /**
     * RFID devices management
     */
    public function rfidDevices(): void
    {
        try {
            $devices = $this->getRfidDevices();
            $deviceStats = $this->getRfidDeviceStats();

            $data = [
                'devices' => $devices,
                'device_stats' => $deviceStats,
                'connection_status' => $this->checkDeviceConnections($devices)
            ];

            $this->addBreadcrumb('RFID Devices');

            $this->render('admin.rfid-devices', $data, [
                'title' => 'RFID Device Management',
                'description' => 'Manage RFID scanners and monitoring',
                'page_class' => 'admin-rfid-devices'
            ]);

            $this->logUserAction('admin_rfid_devices_viewed');

        } catch (Exception $e) {
            $this->logger->error('Admin RFID devices error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->redirectWithError('/admin', 'Unable to load RFID device management');
        }
    }

    /**
     * System reports interface
     */
    public function reports(): void
    {
        try {
            $reportType = $_GET['type'] ?? 'overview';
            $dateRange = $_GET['range'] ?? '30days';
            $format = $_GET['format'] ?? 'web';

            if ($format === 'export') {
                $this->exportReport($reportType, $dateRange);
                return;
            }

            $reportData = $this->generateReport($reportType, $dateRange);

            $data = [
                'report_type' => $reportType,
                'date_range' => $dateRange,
                'report_data' => $reportData,
                'available_reports' => $this->getAvailableReports(),
                'export_formats' => ['csv', 'excel', 'pdf']
            ];

            $this->addBreadcrumb('Reports');

            $this->render('admin.reports', $data, [
                'title' => 'System Reports',
                'description' => 'Generate and view system reports',
                'page_class' => 'admin-reports',
                'require_charts' => true
            ]);

            $this->logUserAction('admin_reports_viewed', [
                'report_type' => $reportType,
                'date_range' => $dateRange
            ]);

        } catch (Exception $e) {
            $this->logger->error('Admin reports error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->redirectWithError('/admin', 'Unable to load reports');
        }
    }

    /**
     * Security audit logs
     */
    public function securityLogs(): void
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            $severity = $_GET['severity'] ?? '';
            $dateFilter = $_GET['date'] ?? '';
            $userFilter = $_GET['user'] ?? '';

            $filters = [];
            if ($severity) $filters['severity'] = $severity;
            if ($dateFilter) $filters['date_range'] = $dateFilter;
            if ($userFilter) $filters['user_id'] = $userFilter;

            $logs = $this->getSecurityLogs($filters, $page, 50);
            $totalLogs = $this->getSecurityLogCount($filters);

            $data = [
                'logs' => $logs,
                'pagination' => $this->calculatePagination($totalLogs, $page, 50),
                'severity_filter' => $severity,
                'date_filter' => $dateFilter,
                'user_filter' => $userFilter,
                'security_summary' => $this->getSecuritySummary()
            ];

            $this->addBreadcrumb('Security Logs');

            $this->render('admin.security-logs', $data, [
                'title' => 'Security Audit Logs',
                'description' => 'View security events and audit trail',
                'page_class' => 'admin-security-logs',
                'require_datatables' => true
            ]);

            $this->logUserAction('admin_security_logs_viewed', ['filters' => $filters]);

        } catch (Exception $e) {
            $this->logger->error('Admin security logs error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->redirectWithError('/admin', 'Unable to load security logs');
        }
    }

    /**
     * System settings interface
     */
    public function settings(): void
    {
        if ($this->isMethod('POST')) {
            $this->handleSettingsUpdate();
            return;
        }

        try {
            $settings = $this->getSystemSettings();
            $settingsGroups = $this->groupSettings($settings);

            $data = [
                'settings_groups' => $settingsGroups,
                'validation_rules' => $this->getSettingsValidationRules(),
                'backup_info' => $this->getBackupInfo()
            ];

            $this->addBreadcrumb('System Settings');

            $this->render('admin.settings', $data, [
                'title' => 'System Settings',
                'description' => 'Configure system settings and preferences',
                'page_class' => 'admin-settings'
            ]);

        } catch (Exception $e) {
            $this->logger->error('Admin settings error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->redirectWithError('/admin', 'Unable to load system settings');
        }
    }

    /**
     * Database maintenance interface
     */
    public function database(): void
    {
        try {
            $action = $_GET['action'] ?? '';

            if ($action && $this->isMethod('POST')) {
                $this->handleDatabaseAction($action);
                return;
            }

            $data = [
                'database_info' => $this->getDatabaseInfo(),
                'table_stats' => $this->getTableStats(),
                'maintenance_history' => $this->getMaintenanceHistory(),
                'backup_status' => $this->getBackupStatus()
            ];

            $this->addBreadcrumb('Database Management');

            $this->render('admin.database', $data, [
                'title' => 'Database Management',
                'description' => 'Database maintenance and optimization',
                'page_class' => 'admin-database'
            ]);

        } catch (Exception $e) {
            $this->logger->error('Admin database error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->redirectWithError('/admin', 'Unable to load database management');
        }
    }

    /**
     * AJAX: Get real-time system stats
     */
    public function ajaxSystemStats(): void
    {
        $this->requireAjax();

        try {
            $stats = $this->getSystemStats();
            $this->renderJson(['success' => true, 'stats' => $stats]);

        } catch (Exception $e) {
            $this->logger->error('AJAX system stats error', [
                'message' => $e->getMessage()
            ]);

            $this->renderJson(['success' => false, 'error' => 'Unable to fetch system stats'], 500);
        }
    }

    /**
     * AJAX: Delete user
     */
    public function ajaxDeleteUser(): void
    {
        $this->requireAjax();

        if (!$this->validateCsrfToken()) {
            $this->renderJson(['success' => false, 'error' => 'Invalid security token'], 403);
            return;
        }

        try {
            $userId = (int)($_POST['user_id'] ?? 0);

            if (!$userId) {
                $this->renderJson(['success' => false, 'error' => 'Invalid user ID'], 400);
                return;
            }

            // Prevent deletion of current user
            $currentUser = $this->auth->getCurrentUser();
            if ($userId === $currentUser['user_id']) {
                $this->renderJson(['success' => false, 'error' => 'Cannot delete your own account'], 400);
                return;
            }

            $result = $this->userRepository->deleteUser($userId);

            if ($result) {
                $this->logUserAction('user_deleted', ['deleted_user_id' => $userId]);
                $this->renderJson(['success' => true, 'message' => 'User deleted successfully']);
            } else {
                $this->renderJson(['success' => false, 'error' => 'Failed to delete user'], 500);
            }

        } catch (Exception $e) {
            $this->logger->error('AJAX delete user error', [
                'message' => $e->getMessage(),
                'user_id' => $_POST['user_id'] ?? null
            ]);

            $this->renderJson(['success' => false, 'error' => 'Unable to delete user'], 500);
        }
    }

    /**
     * Handle user form submission
     */
    private function handleUserFormSubmission(?string $userId): void
    {
        if (!$this->validateCsrfToken()) {
            $this->redirectWithError('/admin/users', 'Invalid security token');
            return;
        }

        try {
            $input = $this->sanitizeInput($_POST);
            $isEdit = $userId !== null;

            // Validate required fields
            $required = ['email', 'first_name', 'last_name', 'group_id'];
            if (!$isEdit) {
                $required[] = 'password';
            }

            foreach ($required as $field) {
                if (empty($input[$field])) {
                    $this->redirectWithError(
                        $isEdit ? "/admin/users/edit?id={$userId}" : '/admin/users/create',
                        "Field '{$field}' is required"
                    );
                    return;
                }
            }

            // Prepare user data
            $userData = [
                'email' => $input['email'],
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
                'group_id' => (int)$input['group_id'],
                'is_active' => isset($input['is_active']) ? 1 : 0
            ];

            if (!empty($input['password'])) {
                $userData['password'] = password_hash($input['password'], PASSWORD_DEFAULT);
            }

            if (!empty($input['rfid_tag'])) {
                $userData['rfid_tag'] = $input['rfid_tag'];
            }

            if ($isEdit) {
                $result = $this->userRepository->updateUser((int)$userId, $userData);
                $action = 'updated';
                $logAction = 'user_updated';
            } else {
                $result = $this->userRepository->createUser($userData);
                $action = 'created';
                $logAction = 'user_created';
                $userId = $result;
            }

            if ($result) {
                $this->logUserAction($logAction, ['target_user_id' => $userId, 'user_data' => $userData]);
                $this->redirectWithSuccess('/admin/users', "User {$action} successfully");
            } else {
                $this->redirectWithError(
                    $isEdit ? "/admin/users/edit?id={$userId}" : '/admin/users/create',
                    "Failed to {$action} user"
                );
            }

        } catch (Exception $e) {
            $this->logger->error('User form submission error', [
                'message' => $e->getMessage(),
                'user_id' => $userId,
                'input' => $_POST
            ]);

            $this->redirectWithError('/admin/users', 'An error occurred while saving user');
        }
    }

    /**
     * Handle settings update
     */
    private function handleSettingsUpdate(): void
    {
        if (!$this->validateCsrfToken()) {
            $this->redirectWithError('/admin/settings', 'Invalid security token');
            return;
        }

        try {
            $input = $this->sanitizeInput($_POST);
            $settings = $input['settings'] ?? [];

            $updated = 0;
            foreach ($settings as $key => $value) {
                if ($this->updateSystemSetting($key, $value)) {
                    $updated++;
                }
            }

            $this->logUserAction('settings_updated', [
                'updated_count' => $updated,
                'settings' => array_keys($settings)
            ]);

            $this->redirectWithSuccess('/admin/settings', "Updated {$updated} settings successfully");

        } catch (Exception $e) {
            $this->logger->error('Settings update error', [
                'message' => $e->getMessage(),
                'input' => $_POST
            ]);

            $this->redirectWithError('/admin/settings', 'Failed to update settings');
        }
    }

    /**
     * Get system statistics
     */
    private function getSystemStats(): array
    {
        return [
            'total_users' => $this->userRepository->getUserCount(),
            'active_users' => $this->userRepository->getUserCount(['status' => 'active']),
            'total_events' => $this->eventRepository->getEventCount(),
            'active_events' => $this->eventRepository->getEventCount(['status' => 'active']),
            'total_checkins' => $this->checkinRepository->getCheckinCount(),
            'today_checkins' => $this->checkinRepository->getCheckinCount(['date' => date('Y-m-d')]),
            'system_uptime' => $this->getSystemUptime(),
            'database_size' => $this->getDatabaseSize(),
            'memory_usage' => $this->getMemoryUsage(),
            'disk_usage' => $this->getDiskUsage()
        ];
    }

    /**
     * Get recent system activity
     */
    private function getRecentActivity(): array
    {
        // Implementation would fetch recent activity from audit logs
        return [];
    }

    /**
     * Get pending administrative tasks
     */
    private function getPendingTasks(): array
    {
        // Implementation would identify pending tasks
        return [];
    }

    /**
     * Get system health status
     */
    private function getSystemHealth(): array
    {
        return [
            'database' => $this->checkDatabaseHealth(),
            'storage' => $this->checkStorageHealth(),
            'security' => $this->checkSecurityHealth(),
            'performance' => $this->checkPerformanceHealth()
        ];
    }

    /**
     * Calculate pagination data
     */
    private function calculatePagination(int $total, int $currentPage, int $perPage): array
    {
        $totalPages = (int)ceil($total / $perPage);
        
        return [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'per_page' => $perPage,
            'total_items' => $total,
            'has_previous' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages,
            'previous_page' => max(1, $currentPage - 1),
            'next_page' => min($totalPages, $currentPage + 1)
        ];
    }

    // Additional helper methods would be implemented here...
    // These would handle specific administrative functions like:
    // - getUserGroups()
    // - getUserStats()
    // - getEventStats()
    // - getRfidDevices()
    // - checkDatabaseHealth()
    // - etc.
}
