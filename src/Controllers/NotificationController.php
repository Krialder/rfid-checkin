<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers;

use Exception;

/**
 * Notification Controller
 * Handles user notifications, system alerts, and notification management
 */
class NotificationController extends BaseController
{
    /**
     * User notifications page
     */
    public function index(): string
    {
        try {
            $this->requireAuth();
            $user = $this->getCurrentUser();

            $page = (int)($_GET['page'] ?? 1);
            $filter = $_GET['filter'] ?? 'all'; // all, unread, read
            $type = $_GET['type'] ?? 'all'; // all, system, event, attendance

            $notifications = $this->getUserNotifications($user['id'], $page, $filter, $type);

            $data = [
                'pageTitle' => 'Notifications',
                'notifications' => $notifications['data'],
                'pagination' => $notifications['pagination'],
                'filters' => [
                    'filter' => $filter,
                    'type' => $type
                ],
                'stats' => $this->getNotificationStats($user['id'])
            ];

            return $this->renderView('notifications/index', $data);

        } catch (Exception $e) {
            $this->logError('Notifications page error', $e);
            return $this->renderError('Unable to load notifications');
        }
    }

    /**
     * Admin notification management
     */
    public function admin(): string
    {
        try {
            $this->requireAuth();
            $this->requireRole(['admin', 'manager']);

            $page = (int)($_GET['page'] ?? 1);
            $search = trim($_GET['search'] ?? '');
            $type = $_GET['type'] ?? 'all';
            $status = $_GET['status'] ?? 'all';

            $notifications = $this->getAllNotifications($page, $search, $type, $status);

            $data = [
                'pageTitle' => 'Notification Management',
                'notifications' => $notifications['data'],
                'pagination' => $notifications['pagination'],
                'filters' => [
                    'search' => $search,
                    'type' => $type,
                    'status' => $status
                ],
                'stats' => $this->getAdminNotificationStats(),
                'notificationTypes' => $this->getNotificationTypes()
            ];

            return $this->renderView('notifications/admin', $data);

        } catch (Exception $e) {
            $this->logError('Admin notifications error', $e);
            return $this->renderError('Unable to load notification management');
        }
    }

    /**
     * Create notification form
     */
    public function create(): string
    {
        try {
            $this->requireAuth();
            $this->requireRole(['admin', 'manager']);

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                return $this->processCreateNotification();
            }

            $data = [
                'pageTitle' => 'Create Notification',
                'notificationTypes' => $this->getNotificationTypes(),
                'users' => $this->getUsersForNotification(),
                'groups' => $this->getGroupsForNotification(),
                'events' => $this->getEventsForNotification()
            ];

            return $this->renderView('notifications/create', $data);

        } catch (Exception $e) {
            $this->logError('Create notification error', $e);
            return $this->renderError('Unable to load notification form');
        }
    }

    /**
     * Notification templates management
     */
    public function templates(): string
    {
        try {
            $this->requireAuth();
            $this->requireRole(['admin', 'manager']);

            $templates = $this->getNotificationTemplates();

            $data = [
                'pageTitle' => 'Notification Templates',
                'templates' => $templates
            ];

            return $this->renderView('notifications/templates', $data);

        } catch (Exception $e) {
            $this->logError('Notification templates error', $e);
            return $this->renderError('Unable to load notification templates');
        }
    }

    /**
     * AJAX: Mark notification as read
     */
    public function markAsRead(): void
    {
        try {
            $this->requireAuth();
            $user = $this->getCurrentUser();

            $notificationId = (int)($_POST['notification_id'] ?? 0);

            if ($notificationId <= 0) {
                $this->jsonResponse(['error' => 'Invalid notification ID'], 400);
                return;
            }

            // Verify notification belongs to user
            $notification = $this->db->selectRow(
                "SELECT * FROM user_notifications WHERE id = ? AND user_id = ?",
                [$notificationId, $user['id']]
            );

            if (!$notification) {
                $this->jsonResponse(['error' => 'Notification not found'], 404);
                return;
            }

            // Mark as read
            $this->db->execute(
                "UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE id = ?",
                [$notificationId]
            );

            $this->logInfo('Notification marked as read', [
                'notification_id' => $notificationId,
                'user_id' => $user['id']
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);

        } catch (Exception $e) {
            $this->logError('Mark notification as read error', $e);
            $this->jsonResponse(['error' => 'Unable to mark notification as read'], 500);
        }
    }

    /**
     * AJAX: Mark all notifications as read
     */
    public function markAllAsRead(): void
    {
        try {
            $this->requireAuth();
            $user = $this->getCurrentUser();

            $this->db->execute(
                "UPDATE user_notifications SET is_read = 1, read_at = NOW() 
                 WHERE user_id = ? AND is_read = 0",
                [$user['id']]
            );

            $affectedRows = $this->db->affectedRows();

            $this->logInfo('All notifications marked as read', [
                'user_id' => $user['id'],
                'count' => $affectedRows
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => "Marked {$affectedRows} notifications as read"
            ]);

        } catch (Exception $e) {
            $this->logError('Mark all notifications as read error', $e);
            $this->jsonResponse(['error' => 'Unable to mark notifications as read'], 500);
        }
    }

    /**
     * AJAX: Delete notification
     */
    public function delete(): void
    {
        try {
            $this->requireAuth();
            $user = $this->getCurrentUser();

            $notificationId = (int)($_POST['notification_id'] ?? 0);

            if ($notificationId <= 0) {
                $this->jsonResponse(['error' => 'Invalid notification ID'], 400);
                return;
            }

            // Verify notification belongs to user or user is admin
            $whereClause = "id = ?";
            $params = [$notificationId];

            if (!$this->hasRole(['admin', 'manager'])) {
                $whereClause .= " AND user_id = ?";
                $params[] = $user['id'];
            }

            $notification = $this->db->selectRow(
                "SELECT * FROM user_notifications WHERE {$whereClause}",
                $params
            );

            if (!$notification) {
                $this->jsonResponse(['error' => 'Notification not found'], 404);
                return;
            }

            // Delete notification
            $this->db->execute(
                "DELETE FROM user_notifications WHERE id = ?",
                [$notificationId]
            );

            $this->logInfo('Notification deleted', [
                'notification_id' => $notificationId,
                'user_id' => $user['id']
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Notification deleted successfully'
            ]);

        } catch (Exception $e) {
            $this->logError('Delete notification error', $e);
            $this->jsonResponse(['error' => 'Unable to delete notification'], 500);
        }
    }

    /**
     * AJAX: Get unread notification count
     */
    public function getUnreadCount(): void
    {
        try {
            $this->requireAuth();
            $user = $this->getCurrentUser();

            $count = $this->db->selectValue(
                "SELECT COUNT(*) FROM user_notifications 
                 WHERE user_id = ? AND is_read = 0",
                [$user['id']]
            );

            $this->jsonResponse([
                'success' => true,
                'count' => (int)$count
            ]);

        } catch (Exception $e) {
            $this->logError('Get unread count error', $e);
            $this->jsonResponse(['error' => 'Unable to get unread count'], 500);
        }
    }

    /**
     * AJAX: Get recent notifications
     */
    public function getRecent(): void
    {
        try {
            $this->requireAuth();
            $user = $this->getCurrentUser();

            $limit = (int)($_GET['limit'] ?? 5);

            $notifications = $this->db->selectAll(
                "SELECT n.*, nt.name as type_name, nt.icon
                 FROM user_notifications n
                 INNER JOIN notification_types nt ON n.type = nt.type
                 WHERE n.user_id = ?
                 ORDER BY n.created_at DESC
                 LIMIT ?",
                [$user['id'], $limit]
            );

            $this->jsonResponse([
                'success' => true,
                'data' => $notifications
            ]);

        } catch (Exception $e) {
            $this->logError('Get recent notifications error', $e);
            $this->jsonResponse(['error' => 'Unable to get recent notifications'], 500);
        }
    }

    /**
     * AJAX: Send notification to users/groups
     */
    public function send(): void
    {
        try {
            $this->requireAuth();
            $this->requireRole(['admin', 'manager']);

            $type = $_POST['type'] ?? 'general';
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $recipients = $_POST['recipients'] ?? [];
            $recipientType = $_POST['recipient_type'] ?? 'users'; // users, groups, all
            $priority = $_POST['priority'] ?? 'normal';
            $sendEmail = isset($_POST['send_email']);

            if (empty($title) || empty($message)) {
                $this->jsonResponse(['error' => 'Title and message are required'], 400);
                return;
            }

            $userIds = $this->resolveRecipients($recipientType, $recipients);

            if (empty($userIds)) {
                $this->jsonResponse(['error' => 'No valid recipients found'], 400);
                return;
            }

            $senderId = $this->getCurrentUser()['id'];
            $notificationCount = 0;

            foreach ($userIds as $userId) {
                $this->db->execute(
                    "INSERT INTO user_notifications (user_id, type, title, message, priority, sender_id, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [$userId, $type, $title, $message, $priority, $senderId]
                );
                $notificationCount++;

                // Send email if requested and user has email
                if ($sendEmail) {
                    $this->sendNotificationEmail($userId, $title, $message);
                }
            }

            $this->logInfo('Bulk notification sent', [
                'sender_id' => $senderId,
                'type' => $type,
                'recipient_count' => $notificationCount,
                'title' => $title
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => "Notification sent to {$notificationCount} users"
            ]);

        } catch (Exception $e) {
            $this->logError('Send notification error', $e);
            $this->jsonResponse(['error' => 'Unable to send notification'], 500);
        }
    }

    /**
     * System notification creation (for internal use)
     */
    public function createSystemNotification(int $userId, string $type, string $title, string $message, string $priority = 'normal', array $metadata = []): bool
    {
        try {
            $this->db->execute(
                "INSERT INTO user_notifications (user_id, type, title, message, priority, metadata, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [$userId, $type, $title, $message, $priority, json_encode($metadata)]
            );

            return true;

        } catch (Exception $e) {
            $this->logError('Create system notification error', $e);
            return false;
        }
    }

    /**
     * Get user notifications with pagination and filtering
     */
    private function getUserNotifications(int $userId, int $page, string $filter, string $type): array
    {
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $whereConditions = ["n.user_id = ?"];
        $params = [$userId];

        if ($filter === 'unread') {
            $whereConditions[] = "n.is_read = 0";
        } elseif ($filter === 'read') {
            $whereConditions[] = "n.is_read = 1";
        }

        if ($type !== 'all') {
            $whereConditions[] = "n.type = ?";
            $params[] = $type;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $totalNotifications = $this->db->selectValue(
            "SELECT COUNT(*) FROM user_notifications n WHERE {$whereClause}",
            $params
        );

        $notifications = $this->db->selectAll(
            "SELECT n.*, nt.name as type_name, nt.icon, nt.color,
                    u.first_name as sender_first_name, u.last_name as sender_last_name
             FROM user_notifications n
             LEFT JOIN notification_types nt ON n.type = nt.type
             LEFT JOIN users u ON n.sender_id = u.id
             WHERE {$whereClause}
             ORDER BY n.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );

        return [
            'data' => $notifications,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($totalNotifications / $limit),
                'total_items' => $totalNotifications,
                'per_page' => $limit
            ]
        ];
    }

    /**
     * Get all notifications for admin view
     */
    private function getAllNotifications(int $page, string $search, string $type, string $status): array
    {
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $whereConditions = ["1=1"];
        $params = [];

        if (!empty($search)) {
            $whereConditions[] = "(n.title LIKE ? OR n.message LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
            $searchTerm = "%{$search}%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        if ($type !== 'all') {
            $whereConditions[] = "n.type = ?";
            $params[] = $type;
        }

        if ($status === 'read') {
            $whereConditions[] = "n.is_read = 1";
        } elseif ($status === 'unread') {
            $whereConditions[] = "n.is_read = 0";
        }

        $whereClause = implode(' AND ', $whereConditions);

        $totalNotifications = $this->db->selectValue(
            "SELECT COUNT(*) FROM user_notifications n 
             INNER JOIN users u ON n.user_id = u.id
             WHERE {$whereClause}",
            $params
        );

        $notifications = $this->db->selectAll(
            "SELECT n.*, nt.name as type_name, nt.icon,
                    u.first_name as user_first_name, u.last_name as user_last_name,
                    s.first_name as sender_first_name, s.last_name as sender_last_name
             FROM user_notifications n
             INNER JOIN users u ON n.user_id = u.id
             LEFT JOIN notification_types nt ON n.type = nt.type
             LEFT JOIN users s ON n.sender_id = s.id
             WHERE {$whereClause}
             ORDER BY n.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );

        return [
            'data' => $notifications,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($totalNotifications / $limit),
                'total_items' => $totalNotifications,
                'per_page' => $limit
            ]
        ];
    }

    /**
     * Get notification statistics for user
     */
    private function getNotificationStats(int $userId): array
    {
        return [
            'total' => $this->db->selectValue(
                "SELECT COUNT(*) FROM user_notifications WHERE user_id = ?",
                [$userId]
            ),
            'unread' => $this->db->selectValue(
                "SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND is_read = 0",
                [$userId]
            ),
            'today' => $this->db->selectValue(
                "SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND DATE(created_at) = CURDATE()",
                [$userId]
            ),
            'this_week' => $this->db->selectValue(
                "SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$userId]
            )
        ];
    }

    /**
     * Get admin notification statistics
     */
    private function getAdminNotificationStats(): array
    {
        return [
            'total_sent' => $this->db->selectValue("SELECT COUNT(*) FROM user_notifications"),
            'unread_total' => $this->db->selectValue("SELECT COUNT(*) FROM user_notifications WHERE is_read = 0"),
            'sent_today' => $this->db->selectValue("SELECT COUNT(*) FROM user_notifications WHERE DATE(created_at) = CURDATE()"),
            'by_type' => $this->db->selectAll(
                "SELECT type, COUNT(*) as count 
                 FROM user_notifications 
                 GROUP BY type 
                 ORDER BY count DESC"
            )
        ];
    }

    /**
     * Get notification types
     */
    private function getNotificationTypes(): array
    {
        return [
            'system' => ['name' => 'System', 'icon' => 'cog', 'color' => 'blue'],
            'event' => ['name' => 'Event', 'icon' => 'calendar', 'color' => 'green'],
            'attendance' => ['name' => 'Attendance', 'icon' => 'check', 'color' => 'orange'],
            'alert' => ['name' => 'Alert', 'icon' => 'warning', 'color' => 'red'],
            'general' => ['name' => 'General', 'icon' => 'info', 'color' => 'gray'],
            'reminder' => ['name' => 'Reminder', 'icon' => 'bell', 'color' => 'yellow']
        ];
    }

    /**
     * Get users for notification targeting
     */
    private function getUsersForNotification(): array
    {
        return $this->db->selectAll(
            "SELECT id, first_name, last_name, email, role 
             FROM users 
             WHERE is_active = 1 
             ORDER BY first_name, last_name"
        );
    }

    /**
     * Get groups for notification targeting
     */
    private function getGroupsForNotification(): array
    {
        return $this->db->selectAll(
            "SELECT id, name, category 
             FROM groups 
             WHERE is_active = 1 
             ORDER BY name"
        );
    }

    /**
     * Get events for notification targeting
     */
    private function getEventsForNotification(): array
    {
        return $this->db->selectAll(
            "SELECT id, name, start_date, end_date 
             FROM events 
             WHERE status = 'active' 
             ORDER BY start_date DESC"
        );
    }

    /**
     * Get notification templates
     */
    private function getNotificationTemplates(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Event Reminder',
                'type' => 'event',
                'subject' => 'Upcoming Event: {event_name}',
                'content' => 'Don\'t forget about the upcoming event "{event_name}" scheduled for {event_date}.',
                'variables' => ['event_name', 'event_date', 'event_location']
            ],
            [
                'id' => 2,
                'name' => 'Attendance Alert',
                'type' => 'attendance',
                'subject' => 'Attendance Required',
                'content' => 'Your attendance is required for the event "{event_name}". Please check in on time.',
                'variables' => ['event_name', 'user_name']
            ],
            [
                'id' => 3,
                'name' => 'System Maintenance',
                'type' => 'system',
                'subject' => 'System Maintenance Notice',
                'content' => 'The RFID check-in system will be undergoing maintenance on {maintenance_date}.',
                'variables' => ['maintenance_date', 'maintenance_duration']
            ]
        ];
    }

    /**
     * Process create notification form
     */
    private function processCreateNotification(): string
    {
        try {
            $type = $_POST['type'] ?? 'general';
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $priority = $_POST['priority'] ?? 'normal';
            $recipientType = $_POST['recipient_type'] ?? 'users';
            $recipients = $_POST['recipients'] ?? [];
            $sendEmail = isset($_POST['send_email']);

            if (empty($title) || empty($message)) {
                throw new Exception('Title and message are required');
            }

            $userIds = $this->resolveRecipients($recipientType, $recipients);

            if (empty($userIds)) {
                throw new Exception('No valid recipients selected');
            }

            $senderId = $this->getCurrentUser()['id'];
            $sentCount = 0;

            foreach ($userIds as $userId) {
                $this->db->execute(
                    "INSERT INTO user_notifications (user_id, type, title, message, priority, sender_id, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [$userId, $type, $title, $message, $priority, $senderId]
                );
                $sentCount++;

                if ($sendEmail) {
                    $this->sendNotificationEmail($userId, $title, $message);
                }
            }

            $this->logInfo('Notification created and sent', [
                'sender_id' => $senderId,
                'type' => $type,
                'recipient_count' => $sentCount
            ]);

            $_SESSION['success_message'] = "Notification sent to {$sentCount} users successfully";
            header('Location: /notifications/admin');
            exit;

        } catch (Exception $e) {
            $this->logError('Process create notification error', $e);

            $data = [
                'pageTitle' => 'Create Notification',
                'error' => $e->getMessage(),
                'formData' => $_POST,
                'notificationTypes' => $this->getNotificationTypes(),
                'users' => $this->getUsersForNotification(),
                'groups' => $this->getGroupsForNotification(),
                'events' => $this->getEventsForNotification()
            ];

            return $this->renderView('notifications/create', $data);
        }
    }

    /**
     * Resolve recipients based on type and selection
     */
    private function resolveRecipients(string $recipientType, array $recipients): array
    {
        $userIds = [];

        switch ($recipientType) {
            case 'all':
                $userIds = $this->db->selectColumn(
                    "SELECT id FROM users WHERE is_active = 1"
                );
                break;

            case 'users':
                $userIds = array_filter($recipients, fn($id) => is_numeric($id) && $id > 0);
                break;

            case 'groups':
                if (!empty($recipients)) {
                    $groupIds = array_filter($recipients, fn($id) => is_numeric($id) && $id > 0);
                    if (!empty($groupIds)) {
                        $placeholders = str_repeat('?,', count($groupIds) - 1) . '?';
                        $userIds = $this->db->selectColumn(
                            "SELECT DISTINCT user_id FROM user_groups 
                             WHERE group_id IN ({$placeholders})",
                            $groupIds
                        );
                    }
                }
                break;

            case 'roles':
                if (!empty($recipients)) {
                    $roles = array_filter($recipients, fn($role) => in_array($role, ['admin', 'manager', 'user']));
                    if (!empty($roles)) {
                        $placeholders = str_repeat('?,', count($roles) - 1) . '?';
                        $userIds = $this->db->selectColumn(
                            "SELECT id FROM users 
                             WHERE is_active = 1 AND role IN ({$placeholders})",
                            $roles
                        );
                    }
                }
                break;
        }

        return array_unique($userIds);
    }

    /**
     * Send notification email
     */
    private function sendNotificationEmail(int $userId, string $title, string $message): bool
    {
        try {
            $user = $this->db->selectRow(
                "SELECT email, first_name, last_name FROM users WHERE id = ?",
                [$userId]
            );

            if (!$user || empty($user['email'])) {
                return false;
            }

            // Email sending implementation would go here
            // This is a placeholder for the actual email sending logic
            
            $this->logInfo('Notification email sent', [
                'user_id' => $userId,
                'email' => $user['email'],
                'title' => $title
            ]);

            return true;

        } catch (Exception $e) {
            $this->logError('Send notification email error', $e);
            return false;
        }
    }
}