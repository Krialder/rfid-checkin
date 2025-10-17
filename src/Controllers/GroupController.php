<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers;

use Exception;

/**
 * Group Controller
 * Handles group management, members, and assignments
 */
class GroupController extends BaseController
{
    /**
     * Groups listing page
     */
    public function index(): string
    {
        try {
            $this->requireAuth();
            $this->requireRole(['admin', 'manager']);

            $page = (int)($_GET['page'] ?? 1);
            $search = trim($_GET['search'] ?? '');
            $status = $_GET['status'] ?? '';

            $groups = $this->getGroupsList($page, $search, $status);

            $data = [
                'pageTitle' => 'Group Management',
                'groups' => $groups['data'],
                'pagination' => $groups['pagination'],
                'filters' => [
                    'search' => $search,
                    'status' => $status
                ],
                'stats' => $this->getGroupStats()
            ];

            return $this->renderView('groups/index', $data);

        } catch (Exception $e) {
            $this->logError('Groups listing error', $e);
            return $this->renderError('Unable to load groups');
        }
    }

    /**
     * Group detail page
     */
    public function show(): string
    {
        try {
            $this->requireAuth();
            $this->requireRole(['admin', 'manager']);

            $groupId = (int)($_GET['id'] ?? 0);
            if ($groupId <= 0) {
                return $this->renderError('Invalid group ID', 400);
            }

            $group = $this->getGroupDetails($groupId);
            if (!$group) {
                return $this->renderError('Group not found', 404);
            }

            $data = [
                'pageTitle' => $group['name'],
                'group' => $group,
                'members' => $this->getGroupMembers($groupId),
                'events' => $this->getGroupEvents($groupId),
                'stats' => $this->getGroupStatistics($groupId),
                'availableUsers' => $this->getAvailableUsers($groupId)
            ];

            return $this->renderView('groups/show', $data);

        } catch (Exception $e) {
            $this->logError('Group detail error', $e);
            return $this->renderError('Unable to load group details');
        }
    }

    /**
     * Group form (create/edit)
     */
    public function form(): string
    {
        try {
            $this->requireAuth();
            $this->requireRole(['admin', 'manager']);

            $action = $_GET['action'] ?? 'create';
            $groupId = (int)($_GET['id'] ?? 0);

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                return $this->processGroupForm($action, $groupId);
            }

            $group = null;
            if ($action === 'edit' && $groupId > 0) {
                $group = $this->getGroupDetails($groupId);
                if (!$group) {
                    return $this->renderError('Group not found', 404);
                }
            }

            $data = [
                'pageTitle' => $action === 'edit' ? 'Edit Group' : 'Create Group',
                'action' => $action,
                'group' => $group,
                'categories' => [
                    'department' => 'Department',
                    'team' => 'Team',
                    'project' => 'Project',
                    'class' => 'Class',
                    'other' => 'Other'
                ]
            ];

            return $this->renderView('groups/form', $data);

        } catch (Exception $e) {
            $this->logError('Group form error', $e);
            return $this->renderError('Unable to load group form');
        }
    }

    /**
     * Group members management
     */
    public function members(): string
    {
        try {
            $this->requireAuth();
            $this->requireRole(['admin', 'manager']);

            $groupId = (int)($_GET['id'] ?? 0);
            if ($groupId <= 0) {
                return $this->renderError('Invalid group ID', 400);
            }

            $group = $this->getGroupDetails($groupId);
            if (!$group) {
                return $this->renderError('Group not found', 404);
            }

            $data = [
                'pageTitle' => 'Manage Members - ' . $group['name'],
                'group' => $group,
                'members' => $this->getGroupMembers($groupId),
                'availableUsers' => $this->getAvailableUsers($groupId),
                'memberStats' => $this->getMemberStats($groupId)
            ];

            return $this->renderView('groups/members', $data);

        } catch (Exception $e) {
            $this->logError('Group members error', $e);
            return $this->renderError('Unable to load group members');
        }
    }

    /**
     * AJAX: Add member to group
     */
    public function addMember(): void
    {
        try {
            $this->requireAuth();
            $this->requireRole(['admin', 'manager']);

            $groupId = (int)($_POST['group_id'] ?? 0);
            $userId = (int)($_POST['user_id'] ?? 0);

            if ($groupId <= 0 || $userId <= 0) {
                $this->jsonResponse(['error' => 'Invalid group or user ID'], 400);
                return;
            }

            // Check if user is already in group
            $existing = $this->db->selectValue(
                "SELECT COUNT(*) FROM user_groups WHERE user_id = ? AND group_id = ?",
                [$userId, $groupId]
            );

            if ($existing > 0) {
                $this->jsonResponse(['error' => 'User is already in this group'], 400);
                return;
            }

            // Add user to group
            $this->db->execute(
                "INSERT INTO user_groups (user_id, group_id, created_at) VALUES (?, ?, NOW())",
                [$userId, $groupId]
            );

            // Get user info for response
            $user = $this->db->selectRow(
                "SELECT id, first_name, last_name, email FROM users WHERE id = ?",
                [$userId]
            );

            $this->logInfo('User added to group', [
                'user_id' => $userId,
                'group_id' => $groupId,
                'admin_id' => $this->getCurrentUser()['id']
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => 'User added to group successfully',
                'user' => $user
            ]);

        } catch (Exception $e) {
            $this->logError('Add member error', $e);
            $this->jsonResponse(['error' => 'Unable to add member to group'], 500);
        }
    }

    /**
     * AJAX: Remove member from group
     */
    public function removeMember(): void
    {
        try {
            $this->requireAuth();
            $this->requireRole(['admin', 'manager']);

            $groupId = (int)($_POST['group_id'] ?? 0);
            $userId = (int)($_POST['user_id'] ?? 0);

            if ($groupId <= 0 || $userId <= 0) {
                $this->jsonResponse(['error' => 'Invalid group or user ID'], 400);
                return;
            }

            // Remove user from group
            $this->db->execute(
                "DELETE FROM user_groups WHERE user_id = ? AND group_id = ?",
                [$userId, $groupId]
            );

            $this->logInfo('User removed from group', [
                'user_id' => $userId,
                'group_id' => $groupId,
                'admin_id' => $this->getCurrentUser()['id']
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => 'User removed from group successfully'
            ]);

        } catch (Exception $e) {
            $this->logError('Remove member error', $e);
            $this->jsonResponse(['error' => 'Unable to remove member from group'], 500);
        }
    }

    /**
     * AJAX: Delete group
     */
    public function delete(): void
    {
        try {
            $this->requireAuth();
            $this->requireRole(['admin', 'manager']);

            $groupId = (int)($_POST['group_id'] ?? 0);
            if ($groupId <= 0) {
                $this->jsonResponse(['error' => 'Invalid group ID'], 400);
                return;
            }

            // Check if group has events assigned
            $eventCount = $this->db->selectValue(
                "SELECT COUNT(*) FROM event_groups WHERE group_id = ?",
                [$groupId]
            );

            if ($eventCount > 0) {
                // Soft delete - mark as inactive
                $this->db->execute(
                    "UPDATE groups SET is_active = 0, deleted_at = NOW() WHERE id = ?",
                    [$groupId]
                );
                $message = 'Group deactivated (has assigned events)';
            } else {
                // Hard delete
                $this->db->execute("DELETE FROM user_groups WHERE group_id = ?", [$groupId]);
                $this->db->execute("DELETE FROM groups WHERE id = ?", [$groupId]);
                $message = 'Group deleted successfully';
            }

            $this->logInfo('Group deletion', [
                'group_id' => $groupId,
                'admin_id' => $this->getCurrentUser()['id'],
                'type' => $eventCount > 0 ? 'deactivated' : 'deleted'
            ]);

            $this->jsonResponse(['success' => true, 'message' => $message]);

        } catch (Exception $e) {
            $this->logError('Delete group error', $e);
            $this->jsonResponse(['error' => 'Unable to delete group'], 500);
        }
    }

    /**
     * AJAX: Get group statistics
     */
    public function getStats(): void
    {
        try {
            $this->requireAuth();
            $this->requireRole(['admin', 'manager']);

            $groupId = (int)($_GET['group_id'] ?? 0);
            if ($groupId <= 0) {
                $this->jsonResponse(['error' => 'Invalid group ID'], 400);
                return;
            }

            $stats = $this->getGroupStatistics($groupId);

            $this->jsonResponse(['success' => true, 'data' => $stats]);

        } catch (Exception $e) {
            $this->logError('Group stats error', $e);
            $this->jsonResponse(['error' => 'Unable to get group statistics'], 500);
        }
    }

    /**
     * Get groups list with pagination and filtering
     */
    private function getGroupsList(int $page, string $search, string $status): array
    {
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $whereConditions = [];
        $params = [];

        if (!empty($search)) {
            $whereConditions[] = "(name LIKE ? OR description LIKE ?)";
            $searchTerm = "%{$search}%";
            $params = array_merge($params, [$searchTerm, $searchTerm]);
        }

        if ($status === 'active') {
            $whereConditions[] = "is_active = 1";
        } elseif ($status === 'inactive') {
            $whereConditions[] = "is_active = 0";
        }

        $whereClause = empty($whereConditions) ? '1=1' : implode(' AND ', $whereConditions);

        $totalGroups = $this->db->selectValue(
            "SELECT COUNT(*) FROM groups WHERE {$whereClause}",
            $params
        );

        $groups = $this->db->selectAll(
            "SELECT g.*, 
                    COUNT(ug.user_id) as member_count,
                    COUNT(eg.event_id) as event_count,
                    u.first_name as creator_first_name,
                    u.last_name as creator_last_name
             FROM groups g
             LEFT JOIN user_groups ug ON g.id = ug.group_id
             LEFT JOIN event_groups eg ON g.id = eg.group_id
             LEFT JOIN users u ON g.created_by = u.id
             WHERE {$whereClause}
             GROUP BY g.id
             ORDER BY g.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );

        return [
            'data' => $groups,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($totalGroups / $limit),
                'total_items' => $totalGroups,
                'per_page' => $limit
            ]
        ];
    }

    /**
     * Get group statistics
     */
    private function getGroupStats(): array
    {
        return [
            'total' => $this->db->selectValue("SELECT COUNT(*) FROM groups"),
            'active' => $this->db->selectValue("SELECT COUNT(*) FROM groups WHERE is_active = 1"),
            'with_members' => $this->db->selectValue(
                "SELECT COUNT(DISTINCT g.id) FROM groups g 
                 INNER JOIN user_groups ug ON g.id = ug.group_id
                 WHERE g.is_active = 1"
            ),
            'with_events' => $this->db->selectValue(
                "SELECT COUNT(DISTINCT g.id) FROM groups g 
                 INNER JOIN event_groups eg ON g.id = eg.group_id
                 WHERE g.is_active = 1"
            )
        ];
    }

    /**
     * Get group details
     */
    private function getGroupDetails(int $groupId): ?array
    {
        return $this->db->selectRow(
            "SELECT g.*, 
                    u.first_name as creator_first_name,
                    u.last_name as creator_last_name,
                    COUNT(DISTINCT ug.user_id) as member_count,
                    COUNT(DISTINCT eg.event_id) as event_count
             FROM groups g
             LEFT JOIN users u ON g.created_by = u.id
             LEFT JOIN user_groups ug ON g.id = ug.group_id
             LEFT JOIN event_groups eg ON g.id = eg.group_id
             WHERE g.id = ?
             GROUP BY g.id",
            [$groupId]
        );
    }

    /**
     * Get group members
     */
    private function getGroupMembers(int $groupId): array
    {
        return $this->db->selectAll(
            "SELECT u.*, ug.created_at as joined_at,
                    (SELECT COUNT(*) FROM attendance a 
                     INNER JOIN event_groups eg ON a.event_id = eg.event_id 
                     WHERE a.user_id = u.id AND eg.group_id = ?) as group_attendance_count
             FROM users u
             INNER JOIN user_groups ug ON u.id = ug.user_id
             WHERE ug.group_id = ?
             ORDER BY ug.created_at DESC",
            [$groupId, $groupId]
        );
    }

    /**
     * Get group events
     */
    private function getGroupEvents(int $groupId): array
    {
        return $this->db->selectAll(
            "SELECT e.*, eg.created_at as assigned_at,
                    COUNT(DISTINCT a.user_id) as attendance_count
             FROM events e
             INNER JOIN event_groups eg ON e.id = eg.event_id
             LEFT JOIN attendance a ON e.id = a.event_id
             WHERE eg.group_id = ?
             GROUP BY e.id
             ORDER BY e.start_date DESC",
            [$groupId]
        );
    }

    /**
     * Get detailed group statistics
     */
    private function getGroupStatistics(int $groupId): array
    {
        $stats = [];

        // Member statistics
        $stats['members'] = [
            'total' => $this->db->selectValue(
                "SELECT COUNT(*) FROM user_groups WHERE group_id = ?",
                [$groupId]
            ),
            'active' => $this->db->selectValue(
                "SELECT COUNT(*) FROM user_groups ug 
                 INNER JOIN users u ON ug.user_id = u.id 
                 WHERE ug.group_id = ? AND u.is_active = 1",
                [$groupId]
            ),
            'by_role' => $this->db->selectAll(
                "SELECT u.role, COUNT(*) as count
                 FROM user_groups ug
                 INNER JOIN users u ON ug.user_id = u.id
                 WHERE ug.group_id = ?
                 GROUP BY u.role",
                [$groupId]
            )
        ];

        // Event statistics
        $stats['events'] = [
            'total' => $this->db->selectValue(
                "SELECT COUNT(*) FROM event_groups WHERE group_id = ?",
                [$groupId]
            ),
            'active' => $this->db->selectValue(
                "SELECT COUNT(*) FROM event_groups eg 
                 INNER JOIN events e ON eg.event_id = e.id 
                 WHERE eg.group_id = ? AND e.status = 'active'",
                [$groupId]
            ),
            'upcoming' => $this->db->selectValue(
                "SELECT COUNT(*) FROM event_groups eg 
                 INNER JOIN events e ON eg.event_id = e.id 
                 WHERE eg.group_id = ? AND e.start_date > NOW()",
                [$groupId]
            )
        ];

        // Attendance statistics
        $stats['attendance'] = [
            'total' => $this->db->selectValue(
                "SELECT COUNT(*) FROM attendance a
                 INNER JOIN event_groups eg ON a.event_id = eg.event_id
                 WHERE eg.group_id = ?",
                [$groupId]
            ),
            'this_month' => $this->db->selectValue(
                "SELECT COUNT(*) FROM attendance a
                 INNER JOIN event_groups eg ON a.event_id = eg.event_id
                 WHERE eg.group_id = ? AND MONTH(a.check_in_time) = MONTH(NOW()) 
                 AND YEAR(a.check_in_time) = YEAR(NOW())",
                [$groupId]
            ),
            'avg_per_event' => $this->db->selectValue(
                "SELECT AVG(attendance_count) FROM (
                     SELECT COUNT(*) as attendance_count
                     FROM attendance a
                     INNER JOIN event_groups eg ON a.event_id = eg.event_id
                     WHERE eg.group_id = ?
                     GROUP BY a.event_id
                 ) as event_attendance",
                [$groupId]
            )
        ];

        return $stats;
    }

    /**
     * Get available users not in group
     */
    private function getAvailableUsers(int $groupId): array
    {
        return $this->db->selectAll(
            "SELECT id, first_name, last_name, email, role
             FROM users 
             WHERE is_active = 1 
             AND id NOT IN (
                 SELECT user_id FROM user_groups WHERE group_id = ?
             )
             ORDER BY first_name, last_name",
            [$groupId]
        );
    }

    /**
     * Get member statistics for a group
     */
    private function getMemberStats(int $groupId): array
    {
        return [
            'total_members' => $this->db->selectValue(
                "SELECT COUNT(*) FROM user_groups WHERE group_id = ?",
                [$groupId]
            ),
            'recent_joins' => $this->db->selectValue(
                "SELECT COUNT(*) FROM user_groups 
                 WHERE group_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                [$groupId]
            ),
            'role_distribution' => $this->db->selectAll(
                "SELECT u.role, COUNT(*) as count
                 FROM user_groups ug
                 INNER JOIN users u ON ug.user_id = u.id
                 WHERE ug.group_id = ?
                 GROUP BY u.role",
                [$groupId]
            )
        ];
    }

    /**
     * Process group form submission
     */
    private function processGroupForm(string $action, int $groupId): string
    {
        try {
            // Basic validation
            $requiredFields = ['name', 'description', 'category'];
            foreach ($requiredFields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Field '{$field}' is required");
                }
            }

            // Prepare group data
            $groupData = [
                'name' => trim($_POST['name']),
                'description' => trim($_POST['description']),
                'category' => $_POST['category'],
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];

            if ($action === 'create') {
                $groupData['created_by'] = $this->getCurrentUser()['id'];
                $groupData['created_at'] = date('Y-m-d H:i:s');

                $this->db->execute(
                    "INSERT INTO groups (name, description, category, is_active, created_by, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?)",
                    array_values($groupData)
                );

                $newGroupId = $this->db->lastInsertId();

                $this->logInfo('Group created', [
                    'group_id' => $newGroupId,
                    'name' => $groupData['name']
                ]);

                $message = 'Group created successfully';
                $redirectUrl = '/groups/' . $newGroupId;

            } else {
                // Update existing group
                $updateFields = [];
                $updateParams = [];
                foreach ($groupData as $field => $value) {
                    if (!in_array($field, ['created_by', 'created_at'])) {
                        $updateFields[] = "{$field} = ?";
                        $updateParams[] = $value;
                    }
                }
                $updateParams[] = $groupId;

                $this->db->execute(
                    "UPDATE groups SET " . implode(', ', $updateFields) . " WHERE id = ?",
                    $updateParams
                );

                $this->logInfo('Group updated', ['group_id' => $groupId]);
                $message = 'Group updated successfully';
                $redirectUrl = '/groups/' . $groupId;
            }

            // Redirect with success message
            $_SESSION['success_message'] = $message;
            header('Location: ' . $redirectUrl);
            exit;

        } catch (Exception $e) {
            $this->logError('Group form processing error', $e);
            $errorMessage = $e->getMessage();

            // Return form with error
            $data = [
                'pageTitle' => $action === 'edit' ? 'Edit Group' : 'Create Group',
                'action' => $action,
                'group' => $_POST,
                'error' => $errorMessage,
                'categories' => [
                    'department' => 'Department',
                    'team' => 'Team',
                    'project' => 'Project',
                    'class' => 'Class',
                    'other' => 'Other'
                ]
            ];

            return $this->renderView('groups/form', $data);
        }
    }
}