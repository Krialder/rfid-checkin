<?php

declare(strict_types=1);

namespace RfidCheckin\Repositories;

use Exception;

/**
 * User Repository
 * 
 * Handles database operations for users including authentication,
 * RFID tag management, and user profiles.
 * 
 * @package RfidCheckin\Repositories
 * @author Kralder
 */
class UserRepository extends BaseRepository
{
    protected string $tableName = 'users';
    protected string $primaryKey = 'user_id';
    protected array $fillable = [
        'username', 'email', 'password', 'first_name', 'last_name',
        'rfid_tag', 'role', 'is_active', 'phone', 'department',
        'position', 'bio', 'avatar', 'preferences'
    ];
    protected array $hidden = ['password'];

    /**
     * Find user by email or username
     * 
     * @param string $identifier Email or username
     * @return array|null User data or null if not found
     * @throws Exception If query fails
     */
    public function findByEmailOrUsername(string $identifier): ?array
    {
        $cacheKey = "user_by_identifier_" . md5($identifier);
        $cached = $this->cacheGet($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }

        $query = "
            SELECT user_id, username, email, password, first_name, last_name, 
                   rfid_tag, role, is_active, last_login, failed_login_attempts, 
                   locked_until, phone, department, position, bio, avatar, 
                   preferences, created_at, updated_at
            FROM {$this->tableName} 
            WHERE (email = ? OR username = ?) AND is_active = 1
        ";
        
        $result = $this->db->selectOne($query, [$identifier, $identifier]);
        
        if ($result) {
            // Don't cache password data
            $result = $this->filterHiddenFields($result);
            $this->cacheSet($cacheKey, $result);
        }
        
        return $result;
    }

    /**
     * Find user by email
     * 
     * @param string $email Email address
     * @return array|null User data or null if not found
     * @throws Exception If query fails
     */
    public function findByEmail(string $email): ?array
    {
        return $this->findByEmailOrUsername($email);
    }

    /**
     * Find user by RFID tag
     * 
     * @param string $rfidTag RFID tag value
     * @return array|null User data or null if not found
     * @throws Exception If query fails
     */
    public function findByRfidTag(string $rfidTag): ?array
    {
        $cacheKey = "user_by_rfid_" . md5($rfidTag);
        $cached = $this->cacheGet($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }

        $query = "
            SELECT user_id, username, email, first_name, last_name, 
                   rfid_tag, role, is_active, department, position,
                   created_at, updated_at
            FROM {$this->tableName} 
            WHERE rfid_tag = ? AND is_active = 1
        ";
        
        $result = $this->db->selectOne($query, [$rfidTag]);
        
        if ($result) {
            $this->cacheSet($cacheKey, $result);
        }
        
        return $result;
    }

    /**
     * Create new user with password hashing
     * 
     * @param array $data User data
     * @return string User ID
     * @throws Exception If creation fails
     */
    public function createUser(array $data): string
    {
        $this->validateRequired($data, ['username', 'email', 'password']);
        
        // Hash password
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        // Set defaults
        $data['role'] = $data['role'] ?? 'user';
        $data['is_active'] = $data['is_active'] ?? true;
        $data['failed_login_attempts'] = 0;
        
        // Ensure unique username and email
        if ($this->findByEmailOrUsername($data['email'])) {
            throw new Exception('Email address already exists');
        }
        
        if ($this->findByEmailOrUsername($data['username'])) {
            throw new Exception('Username already exists');
        }
        
        $userId = $this->create($data);
        
        // Clear cache
        $this->cacheClear();
        
        $this->logger->info('User created', [
            'user_id' => $userId,
            'username' => $data['username'],
            'email' => $data['email']
        ]);
        
        return $userId;
    }

    /**
     * Update user password
     * 
     * @param int $userId User ID
     * @param string $newPassword New password
     * @return bool True if successful
     * @throws Exception If update fails
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $affectedRows = $this->update($userId, [
            'password' => $hashedPassword,
            'password_reset_token' => null,
            'password_reset_expires' => null
        ]);
        
        if ($affectedRows > 0) {
            $this->cacheClear();
            $this->logger->info('Password updated', ['user_id' => $userId]);
            return true;
        }
        
        return false;
    }

    /**
     * Verify user password
     * 
     * @param string $identifier Email or username
     * @param string $password Plain text password
     * @return array|null User data if valid, null if invalid
     * @throws Exception If query fails
     */
    public function verifyPassword(string $identifier, string $password): ?array
    {
        $query = "
            SELECT user_id, username, email, password, first_name, last_name, 
                   role, is_active, failed_login_attempts, locked_until
            FROM {$this->tableName} 
            WHERE (email = ? OR username = ?) AND is_active = 1
        ";
        
        $user = $this->db->selectOne($query, [$identifier, $identifier]);
        
        if (!$user) {
            return null;
        }
        
        // Check if account is locked
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            throw new Exception('Account is temporarily locked due to too many failed login attempts');
        }
        
        if (password_verify($password, $user['password'])) {
            // Reset failed login attempts on successful login
            $this->resetFailedLoginAttempts($user['user_id']);
            
            // Remove password from returned data
            unset($user['password']);
            return $user;
        }
        
        // Increment failed login attempts
        $this->incrementFailedLoginAttempts($user['user_id']);
        
        return null;
    }

    /**
     * Increment failed login attempts
     * 
     * @param int $userId User ID
     * @throws Exception If update fails
     */
    private function incrementFailedLoginAttempts(int $userId): void
    {
        $query = "
            UPDATE {$this->tableName} 
            SET failed_login_attempts = failed_login_attempts + 1,
                locked_until = CASE 
                    WHEN failed_login_attempts >= 4 THEN DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                    ELSE locked_until
                END
            WHERE user_id = ?
        ";
        
        $this->db->update($query, [$userId]);
        $this->cacheClear();
    }

    /**
     * Reset failed login attempts
     * 
     * @param int $userId User ID
     * @throws Exception If update fails
     */
    private function resetFailedLoginAttempts(int $userId): void
    {
        $this->update($userId, [
            'failed_login_attempts' => 0,
            'locked_until' => null
        ]);
        
        $this->cacheClear();
    }

    /**
     * Associate RFID tag with user
     * 
     * @param int $userId User ID
     * @param string $rfidTag RFID tag value
     * @return bool True if successful
     * @throws Exception If update fails or tag already exists
     */
    public function associateRfidTag(int $userId, string $rfidTag): bool
    {
        // Check if RFID tag is already associated with another user
        $existingUser = $this->findByRfidTag($rfidTag);
        if ($existingUser && $existingUser['user_id'] != $userId) {
            throw new Exception('RFID tag is already associated with another user');
        }
        
        $affectedRows = $this->update($userId, ['rfid_tag' => $rfidTag]);
        
        if ($affectedRows > 0) {
            $this->cacheClear();
            $this->logger->info('RFID tag associated', [
                'user_id' => $userId,
                'rfid_tag' => $rfidTag
            ]);
            return true;
        }
        
        return false;
    }

    /**
     * Remove RFID tag from user
     * 
     * @param int $userId User ID
     * @return bool True if successful
     * @throws Exception If update fails
     */
    public function removeRfidTag(int $userId): bool
    {
        $affectedRows = $this->update($userId, ['rfid_tag' => null]);
        
        if ($affectedRows > 0) {
            $this->cacheClear();
            $this->logger->info('RFID tag removed', ['user_id' => $userId]);
            return true;
        }
        
        return false;
    }

    /**
     * Get users by role
     * 
     * @param string $role User role
     * @param bool $activeOnly Whether to include only active users
     * @return array Array of users
     * @throws Exception If query fails
     */
    public function getUsersByRole(string $role, bool $activeOnly = true): array
    {
        $criteria = ['role' => $role];
        if ($activeOnly) {
            $criteria['is_active'] = 1;
        }
        
        return $this->findBy($criteria, ['*'], 'first_name, last_name');
    }

    /**
     * Get users by department
     * 
     * @param string $department Department name
     * @param bool $activeOnly Whether to include only active users
     * @return array Array of users
     * @throws Exception If query fails
     */
    public function getUsersByDepartment(string $department, bool $activeOnly = true): array
    {
        $criteria = ['department' => $department];
        if ($activeOnly) {
            $criteria['is_active'] = 1;
        }
        
        return $this->findBy($criteria, ['*'], 'first_name, last_name');
    }

    /**
     * Search users by name or email
     * 
     * @param string $search Search term
     * @param int $limit Maximum results
     * @return array Array of users
     * @throws Exception If query fails
     */
    public function searchUsers(string $search, int $limit = 50): array
    {
        $searchTerm = "%{$search}%";
        
        $query = "
            SELECT user_id, username, email, first_name, last_name, 
                   role, department, is_active
            FROM {$this->tableName} 
            WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR username LIKE ?)
              AND is_active = 1
            ORDER BY first_name, last_name
            LIMIT ?
        ";
        
        return $this->db->select($query, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]);
    }

    /**
     * Get user statistics
     * 
     * @return array User statistics
     * @throws Exception If query fails
     */
    public function getUserStats(): array
    {
        $stats = [];
        
        // Total users
        $stats['total'] = $this->count();
        
        // Active users
        $stats['active'] = $this->count(['is_active' => 1]);
        
        // Users by role
        $roleQuery = "SELECT role, COUNT(*) as count FROM {$this->tableName} WHERE is_active = 1 GROUP BY role";
        $roleStats = $this->db->select($roleQuery);
        $stats['by_role'] = array_column($roleStats, 'count', 'role');
        
        // Users with RFID tags
        $stats['with_rfid'] = $this->count(['is_active' => 1]) - $this->count(['is_active' => 1, 'rfid_tag' => null]);
        
        // Recent registrations (last 30 days)
        $recentQuery = "SELECT COUNT(*) as count FROM {$this->tableName} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $recentResult = $this->db->selectOne($recentQuery);
        $stats['recent_registrations'] = $recentResult['count'];
        
        return $stats;
    }

    /**
     * Get total user count
     */
    public function getTotalCount(): int
    {
        return $this->count();
    }

    /**
     * Get active user count
     */
    public function getActiveCount(): int
    {
        return $this->count(['is_active' => 1]);
    }

    /**
     * Get pending user count
     */
    public function getPendingCount(): int
    {
        return $this->count(['is_active' => 0]);
    }

    /**
     * Get RFID tag count
     */
    public function getRfidTagCount(): int
    {
        $query = "SELECT COUNT(*) as count FROM {$this->tableName} WHERE rfid_tag IS NOT NULL AND rfid_tag != ''";
        $result = $this->db->selectOne($query);
        return (int) $result['count'];
    }

    /**
     * Get recent users
     */
    public function getRecentUsers(int $limit = 20): array
    {
        $query = "
            SELECT user_id, username, first_name, last_name, email, role, created_at
            FROM {$this->tableName} 
            WHERE is_active = 1 
            ORDER BY created_at DESC 
            LIMIT ?
        ";
        
        return $this->db->select($query, [$limit]);
    }

    /**
     * Update last login with IP
     */
    public function updateLastLogin(int $userId, string $ipAddress = ''): bool
    {
        $data = ['last_login' => date('Y-m-d H:i:s')];
        if ($ipAddress) {
            $data['last_login_ip'] = $ipAddress;
        }
        
        $affectedRows = $this->update($userId, $data);
        
        if ($affectedRows > 0) {
            $this->cacheClear();
            return true;
        }
        
        return false;
    }

    /**
     * Activate user account
     * 
     * @param int $userId User ID
     * @return bool True if successful
     * @throws Exception If update fails
     */
    public function activateUser(int $userId): bool
    {
        $affectedRows = $this->update($userId, ['is_active' => 1]);
        
        if ($affectedRows > 0) {
            $this->cacheClear();
            $this->logger->info('User activated', ['user_id' => $userId]);
            return true;
        }
        
        return false;
    }

    /**
     * Deactivate user account
     * 
     * @param int $userId User ID
     * @return bool True if successful
     * @throws Exception If update fails
     */
    public function deactivateUser(int $userId): bool
    {
        $affectedRows = $this->update($userId, ['is_active' => 0]);
        
        if ($affectedRows > 0) {
            $this->cacheClear();
            $this->logger->info('User deactivated', ['user_id' => $userId]);
            return true;
        }
        
        return false;
    }

    /**
     * Update user preferences
     * 
     * @param int $userId User ID
     * @param array $preferences User preferences
     * @return bool True if successful
     * @throws Exception If update fails
     */
    public function updatePreferences(int $userId, array $preferences): bool
    {
        $affectedRows = $this->update($userId, [
            'preferences' => json_encode($preferences)
        ]);
        
        if ($affectedRows > 0) {
            $this->cacheClear();
            return true;
        }
        
        return false;
    }

    /**
     * Get user preferences
     * 
     * @param int $userId User ID
     * @return array User preferences
     * @throws Exception If query fails
     */
    public function getPreferences(int $userId): array
    {
        $user = $this->find($userId, ['preferences']);
        
        if (!$user || !$user['preferences']) {
            return [];
        }
        
        return json_decode($user['preferences'], true) ?: [];
    }

    /**
     * Set password reset token
     * 
     * @param string $email User email
     * @param string $token Reset token
     * @return bool True if successful
     * @throws Exception If update fails
     */
    public function setPasswordResetToken(string $email, string $token): bool
    {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $affectedRows = $this->updateBy(
            ['email' => $email],
            [
                'password_reset_token' => $token,
                'password_reset_expires' => $expiresAt
            ]
        );
        
        if ($affectedRows > 0) {
            $this->cacheClear();
            return true;
        }
        
        return false;
    }

    /**
     * Verify password reset token
     * 
     * @param string $token Reset token
     * @return array|null User data if valid token, null if invalid
     * @throws Exception If query fails
     */
    public function verifyPasswordResetToken(string $token): ?array
    {
        $query = "
            SELECT user_id, email, password_reset_expires
            FROM {$this->tableName} 
            WHERE password_reset_token = ? 
              AND password_reset_expires > NOW()
              AND is_active = 1
        ";
        
        return $this->db->selectOne($query, [$token]);
    }
}
