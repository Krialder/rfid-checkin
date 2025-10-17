<?php

namespace App\Services;

use App\Database\DatabaseUtils;
use App\Core\LoggingService;
use App\Services\PerformanceCacheService;
use Exception;

/**
 * Notification Service
 * 
 * Manages notifications and alerts:
 * - Real-time notifications for check-ins/out
 * - Event reminders and alerts
 * - System notifications
 * - Multi-channel delivery (email, SMS, push, in-app)
 * - Notification preferences and templates
 */
class NotificationService
{
    private DatabaseUtils $db;
    private LoggingService $logger;
    private PerformanceCacheService $cache;
    
    // Notification types
    public const TYPE_CHECKIN = 'checkin';
    public const TYPE_CHECKOUT = 'checkout';
    public const TYPE_EVENT_REMINDER = 'event_reminder';
    public const TYPE_LATE_ARRIVAL = 'late_arrival';
    public const TYPE_EARLY_DEPARTURE = 'early_departure';
    public const TYPE_SYSTEM_ALERT = 'system_alert';
    public const TYPE_DEVICE_OFFLINE = 'device_offline';
    public const TYPE_SECURITY_ALERT = 'security_alert';
    
    // Delivery channels
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_PUSH = 'push';
    public const CHANNEL_IN_APP = 'in_app';
    public const CHANNEL_WEBSOCKET = 'websocket';
    
    // Priority levels
    public const PRIORITY_LOW = 1;
    public const PRIORITY_NORMAL = 2;
    public const PRIORITY_HIGH = 3;
    public const PRIORITY_URGENT = 4;
    
    public function __construct()
    {
        $this->db = new DatabaseUtils();
        $this->logger = LoggingService::getInstance();
        $this->cache = PerformanceCacheService::getInstance();
    }
    
    /**
     * Send notification to user or group
     */
    public function sendNotification(array $data): array
    {
        try {
            // Validate notification data
            $validation = $this->validateNotificationData($data);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'validation_errors' => $validation['errors']
                ];
            }
            
            // Prepare notification record
            $notificationData = [
                'type' => $data['type'],
                'title' => $data['title'],
                'message' => $data['message'],
                'user_id' => $data['user_id'] ?? null,
                'group_id' => $data['group_id'] ?? null,
                'event_id' => $data['event_id'] ?? null,
                'priority' => $data['priority'] ?? self::PRIORITY_NORMAL,
                'channels' => json_encode($data['channels'] ?? [self::CHANNEL_IN_APP]),
                'metadata' => json_encode($data['metadata'] ?? []),
                'scheduled_for' => $data['scheduled_for'] ?? date('Y-m-d H:i:s'),
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Insert notification record
            $notificationId = $this->db->insert('notifications', $notificationData);
            
            if (!$notificationId) {
                throw new Exception('Failed to create notification record');
            }
            
            // Process immediate notifications
            if (empty($data['scheduled_for']) || strtotime($data['scheduled_for']) <= time()) {
                $deliveryResult = $this->processNotification($notificationId, $data);
                
                return [
                    'success' => true,
                    'notification_id' => $notificationId,
                    'delivery_result' => $deliveryResult,
                    'message' => 'Notification sent successfully'
                ];
            }
            
            return [
                'success' => true,
                'notification_id' => $notificationId,
                'message' => 'Notification scheduled successfully'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to send notification', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to send notification: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send check-in notification
     */
    public function sendCheckinNotification(array $checkinData): array
    {
        $notificationData = [
            'type' => self::TYPE_CHECKIN,
            'title' => 'Check-in Recorded',
            'message' => sprintf(
                '%s checked in at %s for %s',
                $checkinData['user_name'],
                date('g:i A', strtotime($checkinData['check_in_time'])),
                $checkinData['event_name']
            ),
            'user_id' => $checkinData['user_id'],
            'event_id' => $checkinData['event_id'],
            'priority' => self::PRIORITY_NORMAL,
            'channels' => [self::CHANNEL_IN_APP, self::CHANNEL_WEBSOCKET],
            'metadata' => [
                'checkin_id' => $checkinData['checkin_id'],
                'rfid_code' => $checkinData['rfid_code'],
                'device_id' => $checkinData['device_id']
            ]
        ];
        
        return $this->sendNotification($notificationData);
    }
    
    /**
     * Send check-out notification
     */
    public function sendCheckoutNotification(array $checkoutData): array
    {
        $duration = $this->calculateDuration($checkoutData['check_in_time'], $checkoutData['check_out_time']);
        
        $notificationData = [
            'type' => self::TYPE_CHECKOUT,
            'title' => 'Check-out Recorded',
            'message' => sprintf(
                '%s checked out at %s (Duration: %s)',
                $checkoutData['user_name'],
                date('g:i A', strtotime($checkoutData['check_out_time'])),
                $duration
            ),
            'user_id' => $checkoutData['user_id'],
            'event_id' => $checkoutData['event_id'],
            'priority' => self::PRIORITY_NORMAL,
            'channels' => [self::CHANNEL_IN_APP, self::CHANNEL_WEBSOCKET],
            'metadata' => [
                'checkin_id' => $checkoutData['checkin_id'],
                'duration' => $duration,
                'rfid_code' => $checkoutData['rfid_code']
            ]
        ];
        
        return $this->sendNotification($notificationData);
    }
    
    /**
     * Send event reminder
     */
    public function sendEventReminder(int $eventId, array $userIds = [], int $reminderMinutes = 30): array
    {
        try {
            // Get event details
            $event = $this->db->fetchOne(
                "SELECT * FROM events WHERE id = ? AND status = 'active'",
                [$eventId]
            );
            
            if (!$event) {
                return [
                    'success' => false,
                    'error' => 'Event not found'
                ];
            }
            
            // Get participants if no specific users provided
            if (empty($userIds)) {
                $participants = $this->db->fetchAll(
                    "SELECT DISTINCT u.id, u.name, u.email 
                     FROM users u
                     INNER JOIN user_groups ug ON u.id = ug.user_id
                     INNER JOIN event_groups eg ON ug.group_id = eg.group_id
                     WHERE eg.event_id = ? AND u.status = 'active'",
                    [$eventId]
                );
                $userIds = array_column($participants, 'id');
            }
            
            $sentCount = 0;
            $errors = [];
            
            foreach ($userIds as $userId) {
                $reminderData = [
                    'type' => self::TYPE_EVENT_REMINDER,
                    'title' => 'Event Reminder',
                    'message' => sprintf(
                        'Reminder: %s starts at %s (%d minutes)',
                        $event['name'],
                        date('g:i A', strtotime($event['start_time'])),
                        $reminderMinutes
                    ),
                    'user_id' => $userId,
                    'event_id' => $eventId,
                    'priority' => self::PRIORITY_NORMAL,
                    'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_IN_APP],
                    'metadata' => [
                        'reminder_minutes' => $reminderMinutes,
                        'event_location' => $event['location'] ?? ''
                    ]
                ];
                
                $result = $this->sendNotification($reminderData);
                if ($result['success']) {
                    $sentCount++;
                } else {
                    $errors[] = "Failed to send reminder to user {$userId}: " . $result['error'];
                }
            }
            
            return [
                'success' => true,
                'sent_count' => $sentCount,
                'total_recipients' => count($userIds),
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to send event reminders', [
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to send event reminders: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send security alert
     */
    public function sendSecurityAlert(string $alertType, array $alertData): array
    {
        $notificationData = [
            'type' => self::TYPE_SECURITY_ALERT,
            'title' => 'Security Alert',
            'message' => $this->generateSecurityAlertMessage($alertType, $alertData),
            'priority' => self::PRIORITY_URGENT,
            'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_SMS, self::CHANNEL_IN_APP],
            'metadata' => array_merge(['alert_type' => $alertType], $alertData)
        ];
        
        // Send to administrators
        $admins = $this->getAdministrators();
        $results = [];
        
        foreach ($admins as $admin) {
            $notificationData['user_id'] = $admin['id'];
            $results[] = $this->sendNotification($notificationData);
        }
        
        return [
            'success' => true,
            'alerts_sent' => count($results),
            'results' => $results
        ];
    }
    
    /**
     * Send device offline alert
     */
    public function sendDeviceOfflineAlert(int $deviceId): array
    {
        $device = $this->db->fetchOne(
            "SELECT * FROM rfid_devices WHERE id = ?",
            [$deviceId]
        );
        
        if (!$device) {
            return [
                'success' => false,
                'error' => 'Device not found'
            ];
        }
        
        $notificationData = [
            'type' => self::TYPE_DEVICE_OFFLINE,
            'title' => 'Device Offline Alert',
            'message' => sprintf(
                'RFID Device "%s" (ID: %d) at location "%s" has gone offline',
                $device['name'],
                $device['id'],
                $device['location'] ?? 'Unknown'
            ),
            'priority' => self::PRIORITY_HIGH,
            'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_IN_APP],
            'metadata' => [
                'device_id' => $deviceId,
                'device_name' => $device['name'],
                'last_heartbeat' => $device['last_heartbeat']
            ]
        ];
        
        // Send to device administrators
        $deviceAdmins = $this->getDeviceAdministrators($deviceId);
        $results = [];
        
        foreach ($deviceAdmins as $admin) {
            $notificationData['user_id'] = $admin['id'];
            $results[] = $this->sendNotification($notificationData);
        }
        
        return [
            'success' => true,
            'alerts_sent' => count($results),
            'results' => $results
        ];
    }
    
    /**
     * Get user notifications with pagination
     */
    public function getUserNotifications(int $userId, int $page = 1, int $limit = 25, bool $unreadOnly = false): array
    {
        try {
            $offset = ($page - 1) * $limit;
            $whereClause = "user_id = ?";
            $params = [$userId];
            
            if ($unreadOnly) {
                $whereClause .= " AND read_at IS NULL";
            }
            
            // Get total count
            $totalCount = $this->db->fetchValue(
                "SELECT COUNT(*) FROM notifications WHERE {$whereClause}",
                $params
            );
            
            // Get notifications
            $notifications = $this->db->fetchAll(
                "SELECT * FROM notifications 
                 WHERE {$whereClause}
                 ORDER BY created_at DESC 
                 LIMIT ? OFFSET ?",
                array_merge($params, [$limit, $offset])
            );
            
            // Process notifications
            foreach ($notifications as &$notification) {
                $notification['channels'] = json_decode($notification['channels'], true);
                $notification['metadata'] = json_decode($notification['metadata'], true);
                $notification['is_read'] = !empty($notification['read_at']);
            }
            
            return [
                'success' => true,
                'notifications' => $notifications,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalCount,
                    'pages' => ceil($totalCount / $limit)
                ]
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get user notifications', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve notifications: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, int $userId): array
    {
        try {
            $result = $this->db->update(
                'notifications',
                ['read_at' => date('Y-m-d H:i:s')],
                "id = ? AND user_id = ? AND read_at IS NULL",
                [$notificationId, $userId]
            );
            
            if ($result) {
                // Clear user notification cache
                $this->cache->delete("user_notifications_{$userId}");
                
                return [
                    'success' => true,
                    'message' => 'Notification marked as read'
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Notification not found or already read'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to mark notification as read', [
                'notification_id' => $notificationId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to mark notification as read: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead(int $userId): array
    {
        try {
            $result = $this->db->update(
                'notifications',
                ['read_at' => date('Y-m-d H:i:s')],
                "user_id = ? AND read_at IS NULL",
                [$userId]
            );
            
            // Clear user notification cache
            $this->cache->delete("user_notifications_{$userId}");
            
            return [
                'success' => true,
                'updated_count' => $result,
                'message' => 'All notifications marked as read'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to mark all notifications as read', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to mark notifications as read: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get unread notification count
     */
    public function getUnreadCount(int $userId): int
    {
        try {
            $cacheKey = "unread_count_{$userId}";
            $count = $this->cache->get($cacheKey, 'notifications');
            
            if ($count === null) {
                $count = $this->db->fetchValue(
                    "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL",
                    [$userId]
                );
                
                // Cache for 1 minute
                $this->cache->set($cacheKey, $count, 60, 'notifications');
            }
            
            return (int)$count;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get unread notification count', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return 0;
        }
    }
    
    /**
     * Process pending notifications (for scheduled notifications)
     */
    public function processPendingNotifications(): array
    {
        try {
            $pendingNotifications = $this->db->fetchAll(
                "SELECT * FROM notifications 
                 WHERE status = 'pending' 
                 AND scheduled_for <= ? 
                 ORDER BY priority DESC, created_at ASC 
                 LIMIT 100",
                [date('Y-m-d H:i:s')]
            );
            
            $processed = 0;
            $errors = [];
            
            foreach ($pendingNotifications as $notification) {
                try {
                    $this->processNotification($notification['id'], $notification);
                    $processed++;
                } catch (Exception $e) {
                    $errors[] = "Failed to process notification {$notification['id']}: " . $e->getMessage();
                }
            }
            
            return [
                'success' => true,
                'processed' => $processed,
                'total_pending' => count($pendingNotifications),
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to process pending notifications', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to process pending notifications: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Clean up old notifications
     */
    public function cleanupOldNotifications(int $daysOld = 90): array
    {
        try {
            $cutoffDate = date('Y-m-d', strtotime("-{$daysOld} days"));
            
            $deletedCount = $this->db->delete(
                'notifications',
                "created_at < ? AND status = 'delivered'",
                [$cutoffDate]
            );
            
            return [
                'success' => true,
                'deleted_count' => $deletedCount,
                'cutoff_date' => $cutoffDate
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to cleanup old notifications', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to cleanup notifications: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate notification data
     */
    private function validateNotificationData(array $data): array
    {
        $errors = [];
        
        // Required fields
        if (empty($data['type'])) {
            $errors['type'] = 'Notification type is required';
        }
        
        if (empty($data['title'])) {
            $errors['title'] = 'Notification title is required';
        }
        
        if (empty($data['message'])) {
            $errors['message'] = 'Notification message is required';
        }
        
        // Must have either user_id or group_id
        if (empty($data['user_id']) && empty($data['group_id'])) {
            $errors['recipient'] = 'Either user_id or group_id is required';
        }
        
        // Validate priority
        if (!empty($data['priority']) && !in_array($data['priority'], [1, 2, 3, 4])) {
            $errors['priority'] = 'Invalid priority level';
        }
        
        // Validate channels
        if (!empty($data['channels'])) {
            $validChannels = [self::CHANNEL_EMAIL, self::CHANNEL_SMS, self::CHANNEL_PUSH, self::CHANNEL_IN_APP, self::CHANNEL_WEBSOCKET];
            $invalidChannels = array_diff($data['channels'], $validChannels);
            if (!empty($invalidChannels)) {
                $errors['channels'] = 'Invalid channels: ' . implode(', ', $invalidChannels);
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Process individual notification
     */
    private function processNotification(int $notificationId, array $notification): array
    {
        $channels = json_decode($notification['channels'], true);
        $results = [];
        
        foreach ($channels as $channel) {
            switch ($channel) {
                case self::CHANNEL_EMAIL:
                    $results[$channel] = $this->sendEmail($notification);
                    break;
                case self::CHANNEL_SMS:
                    $results[$channel] = $this->sendSMS($notification);
                    break;
                case self::CHANNEL_PUSH:
                    $results[$channel] = $this->sendPushNotification($notification);
                    break;
                case self::CHANNEL_IN_APP:
                    $results[$channel] = ['success' => true, 'message' => 'In-app notification stored'];
                    break;
                case self::CHANNEL_WEBSOCKET:
                    $results[$channel] = $this->sendWebSocketNotification($notification);
                    break;
            }
        }
        
        // Update notification status
        $allSuccess = array_reduce($results, function($carry, $result) {
            return $carry && $result['success'];
        }, true);
        
        $this->db->update(
            'notifications',
            [
                'status' => $allSuccess ? 'delivered' : 'partially_delivered',
                'delivery_results' => json_encode($results),
                'delivered_at' => date('Y-m-d H:i:s')
            ],
            "id = ?",
            [$notificationId]
        );
        
        return $results;
    }
    
    /**
     * Send email notification (placeholder)
     */
    private function sendEmail(array $notification): array
    {
        // Implement email sending logic here
        return ['success' => true, 'message' => 'Email sent'];
    }
    
    /**
     * Send SMS notification (placeholder)
     */
    private function sendSMS(array $notification): array
    {
        // Implement SMS sending logic here
        return ['success' => true, 'message' => 'SMS sent'];
    }
    
    /**
     * Send push notification (placeholder)
     */
    private function sendPushNotification(array $notification): array
    {
        // Implement push notification logic here
        return ['success' => true, 'message' => 'Push notification sent'];
    }
    
    /**
     * Send WebSocket notification (placeholder)
     */
    private function sendWebSocketNotification(array $notification): array
    {
        // Implement WebSocket notification logic here
        return ['success' => true, 'message' => 'WebSocket notification sent'];
    }
    
    /**
     * Calculate duration between times
     */
    private function calculateDuration(string $startTime, string $endTime): string
    {
        $start = new \DateTime($startTime);
        $end = new \DateTime($endTime);
        $interval = $start->diff($end);
        
        $hours = $interval->h + ($interval->days * 24);
        $minutes = $interval->i;
        
        if ($hours > 0) {
            return sprintf('%d hours, %d minutes', $hours, $minutes);
        } else {
            return sprintf('%d minutes', $minutes);
        }
    }
    
    /**
     * Generate security alert message
     */
    private function generateSecurityAlertMessage(string $alertType, array $alertData): string
    {
        switch ($alertType) {
            case 'unauthorized_access':
                return sprintf(
                    'Unauthorized access attempt detected from IP %s at %s',
                    $alertData['ip_address'] ?? 'unknown',
                    date('Y-m-d H:i:s')
                );
            case 'multiple_failed_logins':
                return sprintf(
                    'Multiple failed login attempts detected for user %s from IP %s',
                    $alertData['username'] ?? 'unknown',
                    $alertData['ip_address'] ?? 'unknown'
                );
            case 'suspicious_activity':
                return sprintf(
                    'Suspicious activity detected: %s',
                    $alertData['description'] ?? 'No description provided'
                );
            default:
                return 'Security alert: ' . ($alertData['message'] ?? 'Unknown security event');
        }
    }
    
    /**
     * Get administrators
     */
    private function getAdministrators(): array
    {
        return $this->db->fetchAll(
            "SELECT id, name, email FROM users WHERE role = 'admin' AND status = 'active'"
        );
    }
    
    /**
     * Get device administrators
     */
    private function getDeviceAdministrators(int $deviceId): array
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT u.id, u.name, u.email 
             FROM users u
             WHERE u.role IN ('admin', 'device_admin') 
             AND u.status = 'active'"
        );
    }
}