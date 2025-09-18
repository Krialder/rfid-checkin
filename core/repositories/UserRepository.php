<?php
/**
 * User Repository Class
 * 
 * Handles all database operations related to users including authentication,
 * profile management, role assignments, and user statistics. Provides
 * optimized queries for common user operations and maintains data integrity.
 * 
 * Features:
 * - User authentication and validation
 * - Profile management and updates
 * - Role-based queries and permissions
 * - User statistics and activity tracking
 * - RFID tag management
 * - Advanced search and filtering
 * - Bulk operations for user management
 * 
 * @package    RFID Check-in System
 * @subpackage Data Access Layer - Users
 * @version    2.0.0
 * @author     Senior Developer Team
 * @since      2.0.0
 */

require_once __DIR__ . '/BaseRepository.php';

class UserRepository extends BaseRepository {
    
    protected $table = 'users';
    protected $primaryKey = 'user_id';
    
    /**
     * Find user by email address or username (for login)
     * 
     * @param string $identifier Email or username
     * @return array|null User data or null if not found
     */
    public function findByEmailOrUsername($identifier) {
        $sql = "
            SELECT user_id, username, email, password, first_name, last_name, 
                   role, is_active, failed_login_attempts, locked_until
            FROM users 
            WHERE (email = ? OR username = ?) AND is_active = 1
            LIMIT 1
        ";
        
        $stmt = $this->query($sql, [$identifier, $identifier]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Reset failed login attempts after successful login
     * 
     * @param int $userId User ID
     * @return bool Success status
     */
    public function resetFailedAttempts($userId) {
        return $this->update($userId, [
            'failed_login_attempts' => 0,
            'locked_until' => null
        ]);
    }
    
    /**
     * Update last login timestamp
     * 
     * @param int $userId User ID
     * @return bool Success status
     */
    public function updateLastLogin($userId) {
        return $this->update($userId, [
            'last_login' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Increment failed login attempts with lockout handling
     * 
     * @param int $userId User ID
     * @return bool Success status
     */
    public function incrementFailedAttempts($userId) {
        $user = $this->findById($userId);
        if (!$user) {
            return false;
        }
        
        $attempts = $user['failed_login_attempts'] + 1;
        $updateData = ['failed_login_attempts' => $attempts];
        
        // Lock account if too many attempts
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $updateData['locked_until'] = date('Y-m-d H:i:s', time() + ACCOUNT_LOCK_DURATION);
        }
        
        return $this->update($userId, $updateData);
    }
    
    /**
     * Log user activity to activity log
     * 
     * @param int|null $userId User ID (null for system events)
     * @param string $action Action performed
     * @param string $details Action details
     * @return bool Success status
     */
    public function logActivity($userId, $action, $details) {
        $sql = "
            INSERT INTO activitylog (user_id, action, details, ip_address, timestamp)
            VALUES (?, ?, ?, ?, NOW())
        ";
        
        $stmt = $this->query($sql, [
            $userId,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Find user by username
     * 
     * @param string $username Username
     * @return array|null User data or null if not found
     */
    public function findByUsername($username) {
        return $this->findWhere(['username' => $username])[0] ?? null;
    }
    
    /**
     * Find user by RFID tag
     * 
     * @param string $rfidTag RFID tag value
     * @return array|null User data or null if not found
     */
    public function findByRfidTag($rfidTag) {
        return $this->findWhere(['rfid_tag' => $rfidTag])[0] ?? null;
    }
    
    /**
     * Authenticate user credentials
     * 
     * @param string $email Email address
     * @param string $password Plain text password
     * @return array|null User data if valid, null if invalid
     */
    public function authenticate($email, $password) {
        $user = $this->findByEmail($email);
        
        if ($user && $user['is_active'] && password_verify($password, $user['password'])) {
            // Update last login
            $this->update($user['user_id'], [
                'last_login' => date('Y-m-d H:i:s'),
                'failed_login_attempts' => 0
            ]);
            
            return $user;
        }
        
        return null;
    }
    
    /**
     * Get users with pagination and filtering
     * 
     * @param array $filters Filter conditions
     * @param int $page Page number (1-based)
     * @param int $limit Records per page
     * @return array ['users' => array, 'total' => int, 'page' => int, 'totalPages' => int]
     */
    public function getUsersPaginated(array $filters = [], $page = 1, $limit = 25) {
        $conditions = ['1=1'];
        $params = [];
        
        // Apply search filter
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $conditions[] = "(u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $params = array_merge($params, [$search, $search, $search, $search]);
        }
        
        // Apply role filter
        if (!empty($filters['role'])) {
            $conditions[] = "u.role = ?";
            $params[] = $filters['role'];
        }
        
        // Apply status filter
        if (isset($filters['status'])) {
            $conditions[] = "u.is_active = ?";
            $params[] = $filters['status'] ? 1 : 0;
        }
        
        $whereClause = implode(' AND ', $conditions);
        
        // Get total count
        $countSql = "SELECT COUNT(*) FROM users u WHERE $whereClause";
        $stmt = $this->query($countSql, $params);
        $total = (int)$stmt->fetchColumn();
        
        // Calculate pagination
        $offset = ($page - 1) * $limit;
        $totalPages = ceil($total / $limit);
        
        // Get users with statistics
        $sql = "
            SELECT u.*, 
                   COUNT(DISTINCT c.checkin_id) as total_checkins,
                   MAX(c.checkin_time) as last_checkin,
                   COUNT(DISTINCT ugm.group_id) as group_count
            FROM users u 
            LEFT JOIN checkin c ON u.user_id = c.user_id 
            LEFT JOIN usergroupmemberships ugm ON u.user_id = ugm.user_id AND ugm.is_active = 1
            WHERE $whereClause 
            GROUP BY u.user_id 
            ORDER BY u.created_at DESC 
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->query($sql, array_merge($params, [$limit, $offset]));
        $users = $stmt->fetchAll();
        
        return [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'limit' => $limit
        ];
    }
    
    /**
     * Get user statistics
     * 
     * @param int $userId User ID
     * @return array User statistics
     */
    public function getUserStats($userId) {
        $sql = "
            SELECT 
                COUNT(*) as total_checkins,
                COUNT(CASE WHEN MONTH(checkin_time) = MONTH(CURRENT_DATE()) 
                      AND YEAR(checkin_time) = YEAR(CURRENT_DATE()) THEN 1 END) as month_checkins,
                COUNT(DISTINCT event_id) as unique_events,
                MIN(checkin_time) as first_checkin,
                MAX(checkin_time) as last_checkin,
                AVG(TIMESTAMPDIFF(MINUTE, 
                    (SELECT start_time FROM events WHERE event_id = checkin.event_id), 
                    checkin_time)) as avg_checkin_delay
            FROM checkin 
            WHERE user_id = ?
        ";
        
        $stmt = $this->query($sql, [$userId]);
        $stats = $stmt->fetch();
        
        return [
            'total_checkins' => (int)$stats['total_checkins'],
            'month_checkins' => (int)$stats['month_checkins'],
            'unique_events' => (int)$stats['unique_events'],
            'first_checkin' => $stats['first_checkin'],
            'last_checkin' => $stats['last_checkin'],
            'avg_checkin_delay' => $stats['avg_checkin_delay'] ? round($stats['avg_checkin_delay'], 1) : null
        ];
    }
    
    /**
     * Get recent check-ins for user
     * 
     * @param int $userId User ID
     * @param int $limit Number of records to return
     * @return array Recent check-ins
     */
    public function getRecentCheckins($userId, $limit = 10) {
        $sql = "
            SELECT 
                c.*,
                e.name as event_name,
                e.location,
                e.start_time as event_start_time,
                e.end_time as event_end_time
            FROM checkin c
            JOIN events e ON c.event_id = e.event_id
            WHERE c.user_id = ?
            ORDER BY c.checkin_time DESC
            LIMIT ?
        ";
        
        $stmt = $this->query($sql, [$userId, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Create new user
     * 
     * @param array $userData User data
     * @return int|bool User ID on success, false on failure
     */
    public function createUser(array $userData) {
        // Validate required fields
        $required = ['username', 'email', 'password'];
        foreach ($required as $field) {
            if (empty($userData[$field])) {
                throw new InvalidArgumentException("Field '$field' is required");
            }
        }
        
        // Check for duplicates
        if ($this->findByEmail($userData['email'])) {
            throw new InvalidArgumentException('Email address already exists');
        }
        
        if ($this->findByUsername($userData['username'])) {
            throw new InvalidArgumentException('Username already exists');
        }
        
        // Hash password
        $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
        
        // Set defaults
        $userData['created_at'] = date('Y-m-d H:i:s');
        $userData['is_active'] = $userData['is_active'] ?? true;
        $userData['role'] = $userData['role'] ?? 'user';
        
        return $this->insert($userData);
    }
    
    /**
     * Update user profile
     * 
     * @param int $userId User ID
     * @param array $userData Updated user data
     * @return bool Success status
     */
    public function updateProfile($userId, array $userData) {
        // Remove sensitive fields that shouldn't be updated via profile
        unset($userData['password'], $userData['role'], $userData['user_id'], $userData['created_at']);
        
        // Validate email uniqueness if being changed
        if (isset($userData['email'])) {
            $existingUser = $this->findByEmail($userData['email']);
            if ($existingUser && $existingUser['user_id'] != $userId) {
                throw new InvalidArgumentException('Email address already exists');
            }
        }
        
        // Validate username uniqueness if being changed
        if (isset($userData['username'])) {
            $existingUser = $this->findByUsername($userData['username']);
            if ($existingUser && $existingUser['user_id'] != $userId) {
                throw new InvalidArgumentException('Username already exists');
            }
        }
        
        $userData['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->update($userId, $userData);
    }
    
    /**
     * Update user password
     * 
     * @param int $userId User ID
     * @param string $newPassword New password (plain text)
     * @return bool Success status
     */
    public function updatePassword($userId, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        return $this->update($userId, [
            'password' => $hashedPassword,
            'password_reset_token' => null,
            'password_reset_expires' => null,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Find user by email address
     * 
     * @param string $email Email address
     * @return array|null User data or null if not found
     */
    public function findByEmail($email) {
        return $this->findWhere(['email' => $email])[0] ?? null;
    }
    
    /**
     * Get users by role
     * 
     * @param string|array $roles Role(s) to filter by
     * @param bool $activeOnly Only active users
     * @return array Users matching criteria
     */
    public function getUsersByRole($roles, $activeOnly = true) {
        $conditions = [];
        
        if (is_array($roles)) {
            $conditions['role'] = $roles;
        } else {
            $conditions['role'] = $roles;
        }
        
        if ($activeOnly) {
            $conditions['is_active'] = 1;
        }
        
        return $this->findWhere($conditions, [
            'orderBy' => 'first_name ASC, last_name ASC'
        ]);
    }
    
    /**
     * Search users by query
     * 
     * @param string $query Search query
     * @param int $limit Result limit
     * @return array Matching users
     */
    public function searchUsers($query, $limit = 50) {
        $searchTerm = '%' . $query . '%';
        
        $sql = "
            SELECT user_id, username, email, first_name, last_name, role, is_active
            FROM users 
            WHERE (username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)
            AND is_active = 1
            ORDER BY 
                CASE 
                    WHEN username = ? THEN 1
                    WHEN email = ? THEN 2
                    WHEN CONCAT(first_name, ' ', last_name) = ? THEN 3
                    ELSE 4
                END,
                first_name ASC, last_name ASC
            LIMIT ?
        ";
        
        $stmt = $this->query($sql, [
            $searchTerm, $searchTerm, $searchTerm, $searchTerm,
            $query, $query, $query,
            $limit
        ]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get user groups for a user
     * 
     * @param int $userId User ID
     * @return array User groups
     */
    public function getUserGroups($userId) {
        $sql = "
            SELECT ug.*, ugm.role as membership_role, ugm.joined_at
            FROM usergroups ug
            JOIN usergroupmemberships ugm ON ug.group_id = ugm.group_id
            WHERE ugm.user_id = ? AND ugm.is_active = 1 AND ug.is_active = 1
            ORDER BY ug.group_name ASC
        ";
        
        $stmt = $this->query($sql, [$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Check if RFID tag is available
     * 
     * @param string $rfidTag RFID tag to check
     * @param int|null $excludeUserId User ID to exclude from check
     * @return bool True if available, false if taken
     */
    public function isRfidTagAvailable($rfidTag, $excludeUserId = null) {
        $conditions = ['rfid_tag' => $rfidTag];
        $users = $this->findWhere($conditions);
        
        if (empty($users)) {
            return true;
        }
        
        // If excluding a user, check if the tag belongs to them
        if ($excludeUserId && count($users) === 1 && $users[0]['user_id'] == $excludeUserId) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get system-wide user statistics
     * 
     * @return array System statistics
     */
    public function getSystemStats() {
        $sql = "
            SELECT 
                COUNT(*) as total_users,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_users,
                COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_users,
                COUNT(CASE WHEN role = 'user' THEN 1 END) as regular_users,
                COUNT(CASE WHEN rfid_tag IS NOT NULL AND rfid_tag != '' THEN 1 END) as users_with_rfid,
                COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as active_last_30_days
            FROM users
        ";
        
        $stmt = $this->query($sql);
        return $stmt->fetch();
    }
}
