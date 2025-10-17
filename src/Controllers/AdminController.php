<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers;

use RfidCheckin\Services\DatabaseService;
use RfidCheckin\Services\LoggingService;
use Exception;

/**
 * Admin Controller
 * 
 * Handles administrative functionality:
 * - Admin dashboard
 * - User management
 * - System settings
 * - Analytics and reports
 * - System monitoring
 */
class AdminController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Admin dashboard
     */
    public function index(): void
    {
        $user = $this->requireAuth();
        if (!$user) return;
        
        if ($user['role'] !== 'admin') {
            $this->renderForbidden();
            return;
        }
        
        try {
            $data = [
                'pageTitle' => 'Admin Dashboard',
                'system_stats' => $this->getSystemStats(),
                'recent_activity' => $this->getRecentActivity(),
                'system_health' => $this->getSystemHealth(),
                'pending_tasks' => $this->getPendingTasks(),
                'user' => $user
            ];
            
            echo $this->render('admin/dashboard', $data);
            
        } catch (Exception $e) {
            $this->logger->error('Admin dashboard error', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            $this->renderError('Unable to load admin dashboard');
        }
    }
    
    /**
     * User management page
     */
    public function users(): void
    {
        $user = $this->requireAuth();
        if (!$user) return;
        
        if ($user['role'] !== 'admin') {
            $this->renderForbidden();
            return;
        }
        
        try {
            $page = (int)($_GET['page'] ?? 1);
            $search = trim($_GET['search'] ?? '');
            $role = $_GET['role'] ?? '';
            $status = $_GET['status'] ?? '';
            
            $filters = array_filter([
                'search' => $search,
                'role' => $role,
                'status' => $status
            ]);
            
            $users = $this->getUsersList($filters, $page);
            
            $data = [
                'pageTitle' => 'User Management',
                'users' => $users['users'],
                'pagination' => $users['pagination'],
                'filters' => $filters,
                'roles' => ['admin', 'manager', 'user'],
                'statuses' => ['active', 'inactive'],
                'stats' => $this->getUserStats(),
                'user' => $user
            ];
            
            echo $this->render('admin/users', $data);
            
        } catch (Exception $e) {
            $this->logger->error('User management error', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            $this->renderError('Unable to load user management');
        }
    }
    
    /**
     * System settings page
     */
    public function settings(): void
    {
        $user = $this->requireAuth();
        if (!$user) return;
        
        if ($user['role'] !== 'admin') {
            $this->renderForbidden();
            return;
        }
        
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->updateSettings();
                return;
            }
            
            $data = [
                'pageTitle' => 'System Settings',
                'settings' => $this->getSystemSettings(),
                'categories' => [
                    'general' => 'General Settings',
                    'security' => 'Security Settings',
                    'notifications' => 'Notification Settings',
                    'rfid' => 'RFID Settings'
                ],
                'user' => $user
            ];
            
            echo $this->render('admin/settings', $data);
            
        } catch (Exception $e) {
            $this->logger->error('Settings error', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            $this->renderError('Unable to load settings');
        }
    }
    
    /**
     * Get system statistics
     */
    private function getSystemStats(): array
    {
        return [
            'users' => [
                'total' => $this->db->selectValue("SELECT COUNT(*) FROM users"),
                'active' => $this->db->selectValue("SELECT COUNT(*) FROM users WHERE is_active = 1"),
                'new_today' => $this->db->selectValue("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")
            ],
            'events' => [
                'total' => $this->db->selectValue("SELECT COUNT(*) FROM events"),
                'active' => $this->db->selectValue("SELECT COUNT(*) FROM events WHERE status = 'active'"),
                'upcoming' => $this->db->selectValue("SELECT COUNT(*) FROM events WHERE start_date > CURDATE()")
            ],
            'attendance' => [
                'total' => $this->db->selectValue("SELECT COUNT(*) FROM attendance"),
                'today' => $this->db->selectValue("SELECT COUNT(*) FROM attendance WHERE DATE(check_in_time) = CURDATE()"),
                'active_now' => $this->db->selectValue("SELECT COUNT(*) FROM attendance WHERE DATE(check_in_time) = CURDATE() AND check_out_time IS NULL")
            ],
            'devices' => [
                'total' => $this->db->selectValue("SELECT COUNT(*) FROM rfid_devices WHERE status != 'deleted'"),
                'online' => $this->db->selectValue("SELECT COUNT(*) FROM rfid_devices WHERE last_heartbeat >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)"),
                'offline' => $this->db->selectValue("SELECT COUNT(*) FROM rfid_devices WHERE last_heartbeat < DATE_SUB(NOW(), INTERVAL 5 MINUTE)")
            ]
        ];
    }
    
    /**
     * Get recent system activity
     */
    private function getRecentActivity(): array
    {
        return $this->db->selectAll(
            "SELECT 
                'checkin' as type,
                CONCAT(u.first_name, ' ', u.last_name) as user_name,
                e.name as event_name,
                a.check_in_time as timestamp
             FROM attendance a
             INNER JOIN users u ON a.user_id = u.id
             LEFT JOIN events e ON a.event_id = e.id
             WHERE a.check_in_time >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
             ORDER BY timestamp DESC
             LIMIT 15"
        );
    }
    
    /**
     * Get system health status
     */
    private function getSystemHealth(): array
    {
        $health = [];
        
        // Database health
        try {
            $this->db->selectValue("SELECT 1");
            $health['database'] = ['status' => 'healthy', 'message' => 'Database OK'];
        } catch (Exception $e) {
            $health['database'] = ['status' => 'error', 'message' => 'Database error'];
        }
        
        // Storage space
        $logPath = dirname(__DIR__, 2) . '/logs';
        if (is_dir($logPath)) {
            $freeBytes = disk_free_space($logPath);
            $totalBytes = disk_total_space($logPath);
            if ($totalBytes > 0) {
                $usedPercent = (($totalBytes - $freeBytes) / $totalBytes) * 100;
                if ($usedPercent > 90) {
                    $health['storage'] = ['status' => 'error', 'message' => 'Storage critical'];
                } elseif ($usedPercent > 80) {
                    $health['storage'] = ['status' => 'warning', 'message' => 'Storage low'];
                } else {
                    $health['storage'] = ['status' => 'healthy', 'message' => 'Storage OK'];
                }
            }
        }
        
        // RFID devices
        $offlineDevices = $this->db->selectValue(
            "SELECT COUNT(*) FROM rfid_devices 
             WHERE status = 'active' AND last_heartbeat < DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
        );
        
        if ($offlineDevices > 0) {
            $health['devices'] = ['status' => 'warning', 'message' => "{$offlineDevices} devices offline"];
        } else {
            $health['devices'] = ['status' => 'healthy', 'message' => 'All devices online'];
        }
        
        return $health;
    }
    
    /**
     * Get pending administrative tasks
     */
    private function getPendingTasks(): array
    {
        $tasks = [];
        
        // Inactive users
        $inactiveUsers = $this->db->selectValue("SELECT COUNT(*) FROM users WHERE is_active = 0");
        if ($inactiveUsers > 0) {
            $tasks[] = [
                'type' => 'users',
                'message' => "{$inactiveUsers} inactive users need review",
                'priority' => 'low',
                'url' => '/admin/users?status=inactive'
            ];
        }
        
        // Offline devices
        $offlineDevices = $this->db->selectValue(
            "SELECT COUNT(*) FROM rfid_devices 
             WHERE status = 'active' AND last_heartbeat < DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
        );
        if ($offlineDevices > 0) {
            $tasks[] = [
                'type' => 'devices',
                'message' => "{$offlineDevices} RFID devices are offline",
                'priority' => 'high',
                'url' => '/admin/devices'
            ];
        }
        
        return $tasks;
    }
    
    /**
     * Get users list with pagination and filtering
     */
    private function getUsersList(array $filters, int $page): array
    {
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(first_name LIKE ? OR last_name LIKE ? OR username LIKE ? OR email LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        if (!empty($filters['role'])) {
            $whereConditions[] = "role = ?";
            $params[] = $filters['role'];
        }
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "is_active = ?";
            $params[] = $filters['status'] === 'active' ? 1 : 0;
        }
        
        $whereClause = empty($whereConditions) ? '1=1' : implode(' AND ', $whereConditions);
        
        $totalUsers = $this->db->selectValue(
            "SELECT COUNT(*) FROM users WHERE {$whereClause}",
            $params
        );
        
        $users = $this->db->selectAll(
            "SELECT id, username, email, first_name, last_name, role, is_active,
                    last_login, created_at,
                    (SELECT COUNT(*) FROM attendance WHERE user_id = users.id) as checkin_count
             FROM users
             WHERE {$whereClause}
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );
        
        return [
            'users' => $users,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($totalUsers / $limit),
                'total_items' => $totalUsers,
                'per_page' => $limit
            ]
        ];
    }
    
    /**
     * Get user statistics
     */
    private function getUserStats(): array
    {
        return [
            'total' => $this->db->selectValue("SELECT COUNT(*) FROM users"),
            'active' => $this->db->selectValue("SELECT COUNT(*) FROM users WHERE is_active = 1"),
            'admins' => $this->db->selectValue("SELECT COUNT(*) FROM users WHERE role = 'admin'"),
            'managers' => $this->db->selectValue("SELECT COUNT(*) FROM users WHERE role = 'manager'"),
            'users' => $this->db->selectValue("SELECT COUNT(*) FROM users WHERE role = 'user'")
        ];
    }
    
    /**
     * Get system settings grouped by category
     */
    private function getSystemSettings(): array
    {
        $settings = $this->db->selectAll(
            "SELECT setting_key, setting_value, category, description 
             FROM system_settings 
             ORDER BY category, setting_key"
        );
        
        $grouped = [];
        foreach ($settings as $setting) {
            $grouped[$setting['category']][] = $setting;
        }
        
        return $grouped;
    }
    
    /**
     * Update system settings
     */
    private function updateSettings(): void
    {
        try {
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'setting_') === 0) {
                    $settingKey = substr($key, 8); // Remove 'setting_' prefix
                    
                    $this->db->execute(
                        "UPDATE system_settings SET setting_value = ? WHERE setting_key = ?",
                        [$value, $settingKey]
                    );
                }
            }
            
            $_SESSION['success_message'] = 'Settings updated successfully';
            header('Location: /admin/settings');
            exit;
            
        } catch (Exception $e) {
            $this->logger->error('Settings update error', [
                'user_id' => $this->getCurrentUser()['id'],
                'error' => $e->getMessage()
            ]);
            
            $_SESSION['error_message'] = 'Failed to update settings';
            header('Location: /admin/settings');
            exit;
        }
    }
}
