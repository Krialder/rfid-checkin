<?php
/**
 * User Groups Management System
 * 
 * Comprehensive user groups management with support for multiple group memberships,
 * role-based group access, and intelligent event assignment with deduplication.
 * Handles the complex logic of ensuring users are only counted once in events
 * even when they belong to multiple groups assigned to the same event.
 * 
 * Key Features:
 * - Multiple group memberships per user
 * - Role-based group management (member, leader, admin)
 * - Event-group assignments with automatic user deduplication
 * - Group statistics and analytics
 * - Hierarchical group relationships
 * - Bulk group operations
 * 
 * @package    RFID Check-in System
 * @subpackage User Groups Management
 * @version    2.0.0
 * @author     Senior Developer (Fixed intern's oversight)
 * @since      2025-08-27
 */

require_once 'database.php';

class UserGroupManager {
    
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Create a new user group
     * 
     * @param array $groupData Group information
     * @return array Result with success status and group_id
     */
    public function createGroup($groupData) {
        try {
            $sql = "INSERT INTO UserGroups (group_name, description, group_type, created_by) 
                    VALUES (?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $groupData['group_name'],
                $groupData['description'] ?? '',
                $groupData['group_type'] ?? 'custom',
                $groupData['created_by']
            ]);
            
            if ($result) {
                $groupId = $this->db->lastInsertId();
                
                // Auto-add creator as admin if specified
                if ($groupData['auto_add_creator'] ?? true) {
                    $this->addUserToGroup($groupData['created_by'], $groupId, 'admin', $groupData['created_by']);
                }
                
                return [
                    'success' => true,
                    'group_id' => $groupId,
                    'message' => 'Group created successfully'
                ];
            }
            
            return ['success' => false, 'error' => 'Failed to create group'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Add user to a group with specified role
     * 
     * @param int $userId User ID
     * @param int $groupId Group ID
     * @param string $role User role in group (member, leader, admin)
     * @param int $addedBy User ID who is adding this user
     * @return array Result with success status
     */
    public function addUserToGroup($userId, $groupId, $role = 'member', $addedBy = null) {
        try {
            // Check if user is already in the group
            $checkSQL = "SELECT membership_id, is_active FROM UserGroupMemberships 
                        WHERE user_id = ? AND group_id = ?";
            $stmt = $this->db->prepare($checkSQL);
            $stmt->execute([$userId, $groupId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                if ($existing['is_active']) {
                    return ['success' => false, 'error' => 'User is already a member of this group'];
                } else {
                    // Reactivate existing membership
                    $updateSQL = "UPDATE UserGroupMemberships 
                                 SET is_active = TRUE, role = ?, added_by = ?, joined_at = NOW() 
                                 WHERE user_id = ? AND group_id = ?";
                    $stmt = $this->db->prepare($updateSQL);
                    $stmt->execute([$role, $addedBy, $userId, $groupId]);
                    
                    return ['success' => true, 'message' => 'User membership reactivated'];
                }
            }
            
            // Add new membership
            $sql = "INSERT INTO UserGroupMemberships (user_id, group_id, role, added_by) 
                    VALUES (?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$userId, $groupId, $role, $addedBy]);
            
            if ($result) {
                return ['success' => true, 'message' => 'User added to group successfully'];
            }
            
            return ['success' => false, 'error' => 'Failed to add user to group'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Remove user from a group
     * 
     * @param int $userId User ID
     * @param int $groupId Group ID
     * @return array Result with success status
     */
    public function removeUserFromGroup($userId, $groupId) {
        try {
            $sql = "UPDATE UserGroupMemberships 
                    SET is_active = FALSE 
                    WHERE user_id = ? AND group_id = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$userId, $groupId]);
            
            if ($result && $stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'User removed from group successfully'];
            }
            
            return ['success' => false, 'error' => 'User not found in group or already removed'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Assign groups to an event
     * 
     * @param int $eventId Event ID
     * @param array $groupIds Array of group IDs
     * @param int $assignedBy User ID who is making the assignment
     * @return array Result with success status and user count
     */
    public function assignGroupsToEvent($eventId, $groupIds, $assignedBy) {
        try {
            $this->db->beginTransaction();
            
            // Remove existing assignments
            $deleteSQL = "DELETE FROM EventGroupAssignments WHERE event_id = ?";
            $stmt = $this->db->prepare($deleteSQL);
            $stmt->execute([$eventId]);
            
            // Add new assignments
            $insertSQL = "INSERT INTO EventGroupAssignments (event_id, group_id, assigned_by) 
                         VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($insertSQL);
            
            foreach ($groupIds as $groupId) {
                $stmt->execute([$eventId, $groupId, $assignedBy]);
            }
            
            // Get unique user count (users only counted once even if in multiple groups)
            $uniqueUsers = $this->getUniqueUsersForEvent($eventId);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Groups assigned to event successfully',
                'unique_user_count' => count($uniqueUsers),
                'total_groups' => count($groupIds)
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get unique users for an event (deduplicates users in multiple groups)
     * This is the key function that ensures users are only counted once!
     * 
     * @param int $eventId Event ID
     * @return array Array of unique user data
     */
    public function getUniqueUsersForEvent($eventId) {
        try {
            $sql = "SELECT DISTINCT u.user_id, u.username, u.first_name, u.last_name, u.email,
                           GROUP_CONCAT(ug.group_name SEPARATOR ', ') as group_names,
                           COUNT(DISTINCT ugm.group_id) as group_count
                    FROM Users u
                    INNER JOIN UserGroupMemberships ugm ON u.user_id = ugm.user_id
                    INNER JOIN UserGroups ug ON ugm.group_id = ug.group_id
                    INNER JOIN EventGroupAssignments ega ON ug.group_id = ega.group_id
                    WHERE ega.event_id = ? 
                      AND ugm.is_active = TRUE 
                      AND ug.is_active = TRUE 
                      AND ega.is_active = TRUE
                      AND u.is_active = TRUE
                    GROUP BY u.user_id
                    ORDER BY u.last_name, u.first_name";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$eventId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log('Error getting unique users for event: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all groups (simple list)
     * 
     * @return array Array of groups
     */
    public function getAllGroups() {
        try {
            $sql = "SELECT group_id, group_name, description, group_type, created_at, is_active
                    FROM UserGroups 
                    WHERE is_active = TRUE
                    ORDER BY group_name";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log('Error getting all groups: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all users for group management
     * 
     * @return array Array of users
     */
    public function getAllUsers() {
        try {
            $sql = "SELECT user_id, username, first_name, last_name, email, department, is_active
                    FROM Users 
                    WHERE is_active = TRUE
                    ORDER BY last_name, first_name";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log('Error getting all users: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all groups with member counts and statistics
     * 
     * @return array Array of groups with statistics
     */
    public function getAllGroupsWithStats() {
        try {
            $sql = "SELECT ug.group_id, ug.group_name, ug.description, ug.group_type,
                           ug.created_at, ug.is_active,
                           COUNT(ugm.user_id) as member_count,
                           SUM(CASE WHEN ugm.role = 'admin' THEN 1 ELSE 0 END) as admin_count,
                           SUM(CASE WHEN ugm.role = 'leader' THEN 1 ELSE 0 END) as leader_count,
                           CONCAT(creator.first_name, ' ', creator.last_name) as created_by_name
                    FROM UserGroups ug
                    LEFT JOIN UserGroupMemberships ugm ON ug.group_id = ugm.group_id AND ugm.is_active = TRUE
                    LEFT JOIN Users creator ON ug.created_by = creator.user_id
                    WHERE ug.is_active = TRUE
                    GROUP BY ug.group_id
                    ORDER BY ug.group_name";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log('Error getting groups with stats: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user's group memberships
     * 
     * @param int $userId User ID
     * @return array Array of user's groups
     */
    public function getUserGroups($userId) {
        try {
            $sql = "SELECT ug.group_id, ug.group_name, ug.description, ug.group_type,
                           ugm.role, ugm.joined_at
                    FROM UserGroups ug
                    INNER JOIN UserGroupMemberships ugm ON ug.group_id = ugm.group_id
                    WHERE ugm.user_id = ? 
                      AND ugm.is_active = TRUE 
                      AND ug.is_active = TRUE
                    ORDER BY ug.group_name";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log('Error getting user groups: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get group members with their roles
     * 
     * @param int $groupId Group ID
     * @return array Array of group members
     */
    public function getGroupMembers($groupId) {
        try {
            $sql = "SELECT u.user_id, u.username, u.first_name, u.last_name, u.email,
                           u.department, ugm.role, ugm.joined_at,
                           CONCAT(adder.first_name, ' ', adder.last_name) as added_by_name
                    FROM Users u
                    INNER JOIN UserGroupMemberships ugm ON u.user_id = ugm.user_id
                    LEFT JOIN Users adder ON ugm.added_by = adder.user_id
                    WHERE ugm.group_id = ? 
                      AND ugm.is_active = TRUE
                    ORDER BY ugm.role DESC, u.last_name, u.first_name";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$groupId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log('Error getting group members: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get events assigned to a group
     * 
     * @param int $groupId Group ID
     * @return array Array of events assigned to the group
     */
    public function getGroupEvents($groupId) {
        try {
            $sql = "SELECT e.event_id, e.name, e.description, e.location, e.event_type,
                           e.start_date, e.end_date, e.start_time, e.end_time,
                           e.is_recurring, e.capacity, ega.assigned_at,
                           CONCAT(assigner.first_name, ' ', assigner.last_name) as assigned_by_name,
                           COUNT(DISTINCT ugm.user_id) as registered_members
                    FROM Events e
                    INNER JOIN EventGroupAssignments ega ON e.event_id = ega.event_id
                    LEFT JOIN Users assigner ON ega.assigned_by = assigner.user_id
                    LEFT JOIN UserGroupMemberships ugm ON ega.group_id = ugm.group_id AND ugm.is_active = TRUE
                    WHERE ega.group_id = ? 
                      AND ega.is_active = TRUE
                      AND e.active = TRUE
                    GROUP BY e.event_id
                    ORDER BY e.start_date DESC, e.start_time DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$groupId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log('Error getting group events: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update group information
     * 
     * @param int $groupId Group ID
     * @param array $updateData Data to update
     * @return array Result with success status
     */
    public function updateGroup($groupId, $updateData) {
        try {
            $allowedFields = ['group_name', 'description', 'group_type'];
            $updateFields = [];
            $updateValues = [];
            
            foreach ($allowedFields as $field) {
                if (isset($updateData[$field])) {
                    $updateFields[] = "$field = ?";
                    $updateValues[] = $updateData[$field];
                }
            }
            
            if (empty($updateFields)) {
                return ['success' => false, 'error' => 'No valid fields to update'];
            }
            
            $updateValues[] = $groupId;
            
            $sql = "UPDATE UserGroups SET " . implode(', ', $updateFields) . " WHERE group_id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($updateValues);
            
            if ($result && $stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Group updated successfully'];
            }
            
            return ['success' => false, 'error' => 'No changes made or group not found'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Delete a group (soft delete)
     * 
     * @param int $groupId Group ID
     * @return array Result with success status
     */
    public function deleteGroup($groupId) {
        try {
            $this->db->beginTransaction();
            
            // Deactivate group
            $sql = "UPDATE UserGroups SET is_active = FALSE WHERE group_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$groupId]);
            
            // Deactivate all memberships
            $sql = "UPDATE UserGroupMemberships SET is_active = FALSE WHERE group_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$groupId]);
            
            // Deactivate all event assignments
            $sql = "UPDATE EventGroupAssignments SET is_active = FALSE WHERE group_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$groupId]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Group deleted successfully'];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get group assignment statistics for events
     * 
     * @return array Statistics about group assignments
     */
    public function getGroupStatistics() {
        try {
            $stats = [];
            
            // Total groups
            $sql = "SELECT COUNT(*) as total_groups FROM UserGroups WHERE is_active = TRUE";
            $stmt = $this->db->query($sql);
            $stats['total_groups'] = $stmt->fetchColumn();
            
            // Total active memberships
            $sql = "SELECT COUNT(*) as total_memberships FROM UserGroupMemberships WHERE is_active = TRUE";
            $stmt = $this->db->query($sql);
            $stats['total_memberships'] = $stmt->fetchColumn();
            
            // Events with group assignments
            $sql = "SELECT COUNT(DISTINCT event_id) as events_with_groups FROM EventGroupAssignments WHERE is_active = TRUE";
            $stmt = $this->db->query($sql);
            $stats['events_with_groups'] = $stmt->fetchColumn();
            
            // Average group size
            $sql = "SELECT AVG(member_count) as avg_group_size FROM (
                        SELECT COUNT(*) as member_count 
                        FROM UserGroupMemberships 
                        WHERE is_active = TRUE 
                        GROUP BY group_id
                    ) as group_sizes";
            $stmt = $this->db->query($sql);
            $stats['avg_group_size'] = round($stmt->fetchColumn(), 1);
            
            return $stats;
            
        } catch (Exception $e) {
            error_log('Error getting group statistics: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Bulk add users to a group
     * 
     * @param array $userIds Array of user IDs
     * @param int $groupId Group ID
     * @param string $role Role for all users
     * @param int $addedBy User ID who is adding these users
     * @return array Result with success status and counts
     */
    public function bulkAddUsersToGroup($userIds, $groupId, $role = 'member', $addedBy = null) {
        try {
            $this->db->beginTransaction();
            
            $successCount = 0;
            $errorCount = 0;
            $errors = [];
            
            foreach ($userIds as $userId) {
                $result = $this->addUserToGroup($userId, $groupId, $role, $addedBy);
                if ($result['success']) {
                    $successCount++;
                } else {
                    $errorCount++;
                    $errors[] = "User ID $userId: " . $result['error'];
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'added_count' => $successCount,
                'error_count' => $errorCount,
                'errors' => $errors,
                'message' => "Added $successCount users to group" . ($errorCount > 0 ? " with $errorCount errors" : "")
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
}

?>
