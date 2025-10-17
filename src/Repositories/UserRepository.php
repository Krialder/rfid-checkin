<?php

declare(strict_types=1);

namespace RfidCheckin\Repositories;

use RfidCheckin\Models\User;
use RfidCheckin\Database\ConnectionManager;
use DateTime;
use PDO;

/**
 * User Repository
 * 
 * Handles database operations for users including authentication,
 * RFID tag management, and user profiles.
 * 
 * @package RfidCheckin\Repositories
 * @author Kralder
 */
class UserRepository
{
    private PDO $db;
    
    public function __construct(DatabaseConnection $connection)
    {
        $this->db = $connection->getConnection();
    }
    
    /**
     * Find user by ID
     * 
     * @param int $id User ID
     * @return User|null User object or null if not found
     */
    public function find(int $id): ?User
    {
        $stmt = $this->db->prepare('
            SELECT * FROM users WHERE id = ?
        ');
        $stmt->execute([$id]);
        
        $data = $stmt->fetch();
        return $data ? $this->hydrate($data) : null;
    }
    
    /**
     * Find user by email
     * 
     * @param string $email User email
     * @return User|null User object or null if not found
     */
    public function findByEmail(string $email): ?User
    {
        $stmt = $this->db->prepare('
            SELECT * FROM users WHERE email = ?
        ');
        $stmt->execute([$email]);
        
        $data = $stmt->fetch();
        return $data ? $this->hydrate($data) : null;
    }
    
    /**
     * Find user by RFID card
     * 
     * @param string $rfidCard RFID card value
     * @return User|null User object or null if not found
     */
    public function findByRfidCard(string $rfidCard): ?User
    {
        $stmt = $this->db->prepare('
            SELECT * FROM users WHERE rfid_card = ?
        ');
        $stmt->execute([$rfidCard]);
        
        $data = $stmt->fetch();
        return $data ? $this->hydrate($data) : null;
    }
    
    /**
     * Find all users with optional filters
     * 
     * @param array $filters Filters for the query
     * @return User[] Array of user objects
     */
    public function findAll(array $filters = []): array
    {
        $query = 'SELECT * FROM users WHERE 1=1';
        $params = [];
        
        if (isset($filters['role'])) {
            $query .= ' AND role = ?';
            $params[] = $filters['role'];
        }
        
        if (isset($filters['status'])) {
            $query .= ' AND status = ?';
            $params[] = $filters['status'];
        }
        
        if (isset($filters['year_level'])) {
            $query .= ' AND year_level = ?';
            $params[] = $filters['year_level'];
        }
        
        if (isset($filters['specialization'])) {
            $query .= ' AND specialization = ?';
            $params[] = $filters['specialization'];
        }
        
        $query .= ' ORDER BY last_name, first_name';
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        $results = [];
        while ($data = $stmt->fetch()) {
            $results[] = $this->hydrate($data);
        }
        
        return $results;
    }
    
    /**
     * Save user (insert or update)
     * 
     * @param User $user User object
     * @return User Saved user object
     */
    public function save(User $user): User
    {
        if ($user->getId() === null) {
            return $this->insert($user);
        }
        
        return $this->update($user);
    }
    
    /**
     * Delete user by ID
     * 
     * @param int $id User ID
     * @return bool True if successful
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        return $stmt->execute([$id]);
    }
    
    /**
     * Assign user to groups
     * 
     * @param int $userId User ID
     * @param array $groupIds Array of group IDs
     */
    public function assignToGroups(int $userId, array $groupIds): void
    {
        // First remove existing assignments
        $stmt = $this->db->prepare('DELETE FROM user_groups WHERE user_id = ?');
        $stmt->execute([$userId]);
        
        // Insert new assignments
        if (!empty($groupIds)) {
            $stmt = $this->db->prepare('
                INSERT INTO user_groups (user_id, group_id) VALUES (?, ?)
            ');
            
            foreach ($groupIds as $groupId) {
                $stmt->execute([$userId, $groupId]);
            }
        }
    }
    
    /**
     * Get group IDs for a user
     * 
     * @param int $userId User ID
     * @return array Array of group IDs
     */
    public function getGroupIds(int $userId): array
    {
        $stmt = $this->db->prepare('
            SELECT group_id FROM user_groups WHERE user_id = ?
        ');
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Hydrate user object from database row
     * 
     * @param array $data Database row data
     * @return User User object
     */
    private function hydrate(array $data): User
    {
        $user = new User();
        $user->setId((int) $data['id']);
        $user->setFirstName($data['first_name']);
        $user->setLastName($data['last_name']);
        $user->setEmail($data['email']);
        $user->setPasswordHash($data['password_hash']);
        $user->setRole(UserRole::from($data['role']));
        $user->setRfidCard($data['rfid_card']);
        $user->setStatus(UserStatus::from($data['status']));
        $user->setYearLevel($data['year_level'] ? (int) $data['year_level'] : null);
        $user->setSpecialization($data['specialization']);
        
        if ($data['enrollment_date']) {
            $user->setEnrollmentDate(new DateTime($data['enrollment_date']));
        }
        
        if ($data['created_at']) {
            $user->setCreatedAt(new DateTime($data['created_at']));
        }
        
        if ($data['updated_at']) {
            $user->setUpdatedAt(new DateTime($data['updated_at']));
        }
        
        return $user;
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
