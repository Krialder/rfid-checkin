<?php

declare(strict_types=1);

namespace RfidCheckin\Services;

use RfidCheckin\Models\Checkin;
use RfidCheckin\Models\RfidDevice;
use RfidCheckin\Models\Event;
use RfidCheckin\Database\ConnectionManager;
use RfidCheckin\Database\DatabaseUtils;
use DateTime;
use Exception;
use InvalidArgumentException;

/**
 * Checkin Service
 * 
 * Handles all RFID check-in/check-out business logic including
 * validation, permissions, notifications, and analytics.
 * 
 * @package RfidCheckin\Services
 * @version 2.0.0
 * @author Senior Development Team
 */
class CheckinService
{
    private DatabaseUtils $db;
    private EventService $eventService;
    private NotificationService $notificationService;
    private array $config;

    /**
     * Constructor with dependency injection
     * 
     * @param DatabaseUtils|null $database Database utilities
     * @param EventService|null $eventService Event service
     * @param NotificationService|null $notificationService Notification service
     */
    public function __construct(
        ?DatabaseUtils $database = null,
        ?EventService $eventService = null,
        ?NotificationService $notificationService = null
    ) {
        $this->db = $database ?? new DatabaseUtils();
        $this->eventService = $eventService ?? new EventService();
        $this->notificationService = $notificationService ?? new NotificationService();
        $this->loadConfiguration();
    }

    /**
     * Process RFID scan and handle check-in/check-out logic
     * 
     * @param string $rfidTag RFID tag identifier
     * @param string $deviceId Device identifier
     * @param array $options Additional options
     * @return CheckinResult Processing result
     */
    public function processRfidScan(string $rfidTag, string $deviceId, array $options = []): CheckinResult
    {
        try {
            // Validate inputs
            $this->validateScanInputs($rfidTag, $deviceId);

            // Find user by RFID tag
            $user = $this->findUserByRfidTag($rfidTag);
            if (!$user) {
                return CheckinResult::error('RFID tag not recognized', [
                    'rfid_tag' => $rfidTag,
                    'device_id' => $deviceId
                ]);
            }

            // Check if user is active
            if (!$this->isUserActive($user)) {
                return CheckinResult::error('User account is not active', [
                    'user_id' => $user['id'],
                    'status' => $user['status']
                ]);
            }

            // Find device
            $device = $this->findDevice($deviceId);
            if (!$device) {
                return CheckinResult::error('Device not found', [
                    'device_id' => $deviceId
                ]);
            }

            // Check device status
            if (!$this->isDeviceOnline($device)) {
                return CheckinResult::warning('Device may be offline but processing scan', [
                    'device_id' => $deviceId,
                    'last_heartbeat' => $device['last_heartbeat']
                ]);
            }

            // Update device activity
            $this->updateDeviceActivity($device);

            // Determine action (check-in or check-out)
            $action = $this->determineAction($user, $options);

            // Get current event (if any)
            $currentEvent = $this->getCurrentEvent($options);

            // Process the check-in/check-out
            if ($action === 'check_in') {
                return $this->processCheckIn($user, $device, $currentEvent, $options);
            } else {
                return $this->processCheckOut($user, $device, $currentEvent, $options);
            }

        } catch (Exception $e) {
            $this->logError('RFID scan processing failed', [
                'rfid_tag' => $rfidTag,
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return CheckinResult::error('System error occurred during scan processing');
        }
    }

    /**
     * Process check-in operation
     * 
     * @param array $user User data
     * @param array $device Device data
     * @param array|null $event Event data
     * @param array $options Additional options
     * @return CheckinResult Processing result
     */
    private function processCheckIn(array $user, array $device, ?array $event, array $options): CheckinResult
    {
        // Check for duplicate check-in
        $existingCheckin = $this->getActiveCheckin($user['id'], $event['id'] ?? null);
        if ($existingCheckin) {
            return CheckinResult::warning('User is already checked in', [
                'checkin_id' => $existingCheckin['id'],
                'checkin_time' => $existingCheckin['checkin_time']
            ]);
        }

        // Validate check-in permissions
        $permissionResult = $this->validateCheckinPermissions($user, $event, $options);
        if (!$permissionResult->isSuccess()) {
            return $permissionResult;
        }

        // Create check-in record
        $checkinData = [
            'user_id' => $user['id'],
            'event_id' => $event['id'] ?? null,
            'device_id' => $device['id'],
            'rfid_tag' => $options['rfid_tag'] ?? null,
            'checkin_time' => date('Y-m-d H:i:s'),
            'status' => Checkin::STATUS_CHECKED_IN,
            'method' => Checkin::METHOD_RFID,
            'location' => $device['location'] ?? null,
            'metadata' => json_encode([
                'device_name' => $device['name'],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ])
        ];

        // Check if late (for events)
        $isLate = false;
        if ($event && isset($event['start_time'])) {
            $eventStart = new DateTime($event['start_time']);
            $checkinTime = new DateTime($checkinData['checkin_time']);
            $isLate = $checkinTime > $eventStart;
            
            if ($isLate) {
                $checkinData['notes'] = 'Late check-in';
            }
        }

        try {
            $checkinId = $this->db->insert('checkins', $checkinData);

            // Send notifications
            $this->sendCheckinNotifications($user, $event, $checkinData, 'check_in');

            // Log activity
            $this->logActivity('checkin', $user['id'], [
                'checkin_id' => $checkinId,
                'event_id' => $event['id'] ?? null,
                'device_id' => $device['id'],
                'is_late' => $isLate
            ]);

            return CheckinResult::success('Check-in successful', [
                'checkin_id' => $checkinId,
                'user_name' => $user['name'],
                'checkin_time' => $checkinData['checkin_time'],
                'is_late' => $isLate,
                'event_name' => $event['name'] ?? null
            ]);

        } catch (Exception $e) {
            throw new Exception('Failed to create check-in record: ' . $e->getMessage());
        }
    }

    /**
     * Process check-out operation
     * 
     * @param array $user User data
     * @param array $device Device data
     * @param array|null $event Event data
     * @param array $options Additional options
     * @return CheckinResult Processing result
     */
    private function processCheckOut(array $user, array $device, ?array $event, array $options): CheckinResult
    {
        // Find active check-in
        $activeCheckin = $this->getActiveCheckin($user['id'], $event['id'] ?? null);
        if (!$activeCheckin) {
            return CheckinResult::error('No active check-in found for this user', [
                'user_id' => $user['id'],
                'event_id' => $event['id'] ?? null
            ]);
        }

        // Update check-in record with check-out time
        $checkoutTime = date('Y-m-d H:i:s');
        $updateData = [
            'checkout_time' => $checkoutTime,
            'status' => Checkin::STATUS_CHECKED_OUT
        ];

        // Calculate duration
        $checkinTime = new DateTime($activeCheckin['checkin_time']);
        $checkoutTimeObj = new DateTime($checkoutTime);
        $duration = $checkoutTimeObj->getTimestamp() - $checkinTime->getTimestamp();

        // Add duration to metadata
        $metadata = json_decode($activeCheckin['metadata'] ?? '{}', true);
        $metadata['checkout_device'] = $device['name'];
        $metadata['duration_seconds'] = $duration;
        $updateData['metadata'] = json_encode($metadata);

        try {
            $this->db->update('checkins', $updateData, ['id' => $activeCheckin['id']]);

            // Send notifications
            $checkinData = array_merge($activeCheckin, $updateData);
            $this->sendCheckinNotifications($user, $event, $checkinData, 'check_out');

            // Log activity
            $this->logActivity('checkout', $user['id'], [
                'checkin_id' => $activeCheckin['id'],
                'duration_seconds' => $duration,
                'event_id' => $event['id'] ?? null,
                'device_id' => $device['id']
            ]);

            return CheckinResult::success('Check-out successful', [
                'checkin_id' => $activeCheckin['id'],
                'user_name' => $user['name'],
                'checkout_time' => $checkoutTime,
                'duration' => $this->formatDuration($duration),
                'event_name' => $event['name'] ?? null
            ]);

        } catch (Exception $e) {
            throw new Exception('Failed to update check-out record: ' . $e->getMessage());
        }
    }

    /**
     * Get today's attendance summary
     * 
     * @param int|null $eventId Optional event ID filter
     * @return array Attendance summary
     */
    public function getTodayAttendanceSummary(?int $eventId = null): array
    {
        $today = date('Y-m-d');
        $conditions = [
            'checkin_time_gte' => $today . ' 00:00:00',
            'checkin_time_lt' => $today . ' 23:59:59'
        ];

        if ($eventId) {
            $conditions['event_id'] = $eventId;
        }

        // Get all checkins for today
        $sql = "SELECT 
                    COUNT(*) as total_checkins,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(CASE WHEN checkout_time IS NULL THEN 1 END) as currently_present,
                    COUNT(CASE WHEN checkout_time IS NOT NULL THEN 1 END) as completed_sessions,
                    AVG(CASE WHEN checkout_time IS NOT NULL THEN 
                        TIMESTAMPDIFF(SECOND, checkin_time, checkout_time) 
                    END) as avg_duration_seconds
                FROM checkins 
                WHERE DATE(checkin_time) = :today";

        $params = [':today' => $today];

        if ($eventId) {
            $sql .= " AND event_id = :event_id";
            $params[':event_id'] = $eventId;
        }

        $summary = $this->db->fetchOne($sql, $params);

        return [
            'date' => $today,
            'total_checkins' => (int) $summary['total_checkins'],
            'unique_users' => (int) $summary['unique_users'],
            'currently_present' => (int) $summary['currently_present'],
            'completed_sessions' => (int) $summary['completed_sessions'],
            'average_duration' => $this->formatDuration((int) $summary['avg_duration_seconds']),
            'event_id' => $eventId
        ];
    }

    /**
     * Get user's check-in history
     * 
     * @param int $userId User ID
     * @param array $filters Optional filters
     * @param int $limit Number of records to return
     * @return array Check-in history
     */
    public function getUserCheckinHistory(int $userId, array $filters = [], int $limit = 50): array
    {
        $sql = "SELECT c.*, e.name as event_name, d.name as device_name 
                FROM checkins c
                LEFT JOIN events e ON c.event_id = e.id
                LEFT JOIN rfid_devices d ON c.device_id = d.id
                WHERE c.user_id = :user_id";

        $params = [':user_id' => $userId];

        // Apply filters
        if (isset($filters['date_from'])) {
            $sql .= " AND c.checkin_time >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (isset($filters['date_to'])) {
            $sql .= " AND c.checkin_time <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (isset($filters['event_id'])) {
            $sql .= " AND c.event_id = :event_id";
            $params[':event_id'] = $filters['event_id'];
        }

        if (isset($filters['status'])) {
            $sql .= " AND c.status = :status";
            $params[':status'] = $filters['status'];
        }

        $sql .= " ORDER BY c.checkin_time DESC LIMIT :limit";
        $params[':limit'] = $limit;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Force check-out all active users
     * 
     * @param string $reason Reason for force checkout
     * @param int|null $eventId Optional event ID filter
     * @return array Results of force checkout
     */
    public function forceCheckoutAll(string $reason, ?int $eventId = null): array
    {
        $sql = "SELECT c.*, u.name as user_name 
                FROM checkins c
                JOIN users u ON c.user_id = u.id
                WHERE c.checkout_time IS NULL AND c.status = :status";

        $params = [':status' => Checkin::STATUS_CHECKED_IN];

        if ($eventId) {
            $sql .= " AND c.event_id = :event_id";
            $params[':event_id'] = $eventId;
        }

        $activeCheckins = $this->db->fetchAll($sql, $params);
        $results = [];

        foreach ($activeCheckins as $checkin) {
            try {
                $checkoutTime = date('Y-m-d H:i:s');
                $updateData = [
                    'checkout_time' => $checkoutTime,
                    'status' => Checkin::STATUS_CHECKED_OUT,
                    'notes' => 'Force checkout: ' . $reason
                ];

                $this->db->update('checkins', $updateData, ['id' => $checkin['id']]);

                $results[] = [
                    'success' => true,
                    'checkin_id' => $checkin['id'],
                    'user_name' => $checkin['user_name'],
                    'checkout_time' => $checkoutTime
                ];

                // Log force checkout
                $this->logActivity('force_checkout', $checkin['user_id'], [
                    'checkin_id' => $checkin['id'],
                    'reason' => $reason,
                    'admin_action' => true
                ]);

            } catch (Exception $e) {
                $results[] = [
                    'success' => false,
                    'checkin_id' => $checkin['id'],
                    'user_name' => $checkin['user_name'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Validate scan inputs
     */
    private function validateScanInputs(string $rfidTag, string $deviceId): void
    {
        if (empty($rfidTag)) {
            throw new InvalidArgumentException('RFID tag cannot be empty');
        }

        if (empty($deviceId)) {
            throw new InvalidArgumentException('Device ID cannot be empty');
        }

        if (strlen($rfidTag) > 50) {
            throw new InvalidArgumentException('RFID tag too long');
        }

        if (strlen($deviceId) > 50) {
            throw new InvalidArgumentException('Device ID too long');
        }
    }

    /**
     * Find user by RFID tag
     */
    private function findUserByRfidTag(string $rfidTag): ?array
    {
        $sql = "SELECT u.* FROM users u 
                JOIN user_rfid_tags ur ON u.id = ur.user_id 
                WHERE ur.rfid_tag = :rfid_tag AND ur.is_active = 1 AND u.status = 'active'";

        return $this->db->fetchOne($sql, [':rfid_tag' => $rfidTag]);
    }

    /**
     * Check if user is active
     */
    private function isUserActive(array $user): bool
    {
        return isset($user['status']) && $user['status'] === 'active';
    }

    /**
     * Find device by ID
     */
    private function findDevice(string $deviceId): ?array
    {
        return $this->db->fetchOne("SELECT * FROM rfid_devices WHERE device_id = :device_id", [
            ':device_id' => $deviceId
        ]);
    }

    /**
     * Check if device is online
     */
    private function isDeviceOnline(array $device): bool
    {
        if (!isset($device['last_heartbeat'])) {
            return false;
        }

        $lastHeartbeat = new DateTime($device['last_heartbeat']);
        $now = new DateTime();
        $timeDiff = $now->getTimestamp() - $lastHeartbeat->getTimestamp();

        return $timeDiff <= RfidDevice::OFFLINE_THRESHOLD;
    }

    /**
     * Update device activity
     */
    private function updateDeviceActivity(array $device): void
    {
        $this->db->update('rfid_devices', [
            'last_scan' => date('Y-m-d H:i:s'),
            'total_scans' => ($device['total_scans'] ?? 0) + 1
        ], ['id' => $device['id']]);
    }

    /**
     * Determine action based on current state
     */
    private function determineAction(array $user, array $options): string
    {
        if (isset($options['action']) && in_array($options['action'], ['check_in', 'check_out'])) {
            return $options['action'];
        }

        // Auto-determine based on current status
        $activeCheckin = $this->getActiveCheckin($user['id']);
        return $activeCheckin ? 'check_out' : 'check_in';
    }

    /**
     * Get current event
     */
    private function getCurrentEvent(array $options): ?array
    {
        if (isset($options['event_id'])) {
            return $this->eventService->getEventById((int) $options['event_id']);
        }

        // Get currently active event
        return $this->eventService->getCurrentEvent();
    }

    /**
     * Get active checkin for user
     */
    private function getActiveCheckin(int $userId, ?int $eventId = null): ?array
    {
        $sql = "SELECT * FROM checkins 
                WHERE user_id = :user_id AND checkout_time IS NULL AND status = :status";

        $params = [
            ':user_id' => $userId,
            ':status' => Checkin::STATUS_CHECKED_IN
        ];

        if ($eventId) {
            $sql .= " AND event_id = :event_id";
            $params[':event_id'] = $eventId;
        }

        $sql .= " ORDER BY checkin_time DESC LIMIT 1";

        return $this->db->fetchOne($sql, $params);
    }

    /**
     * Validate checkin permissions
     */
    private function validateCheckinPermissions(array $user, ?array $event, array $options): CheckinResult
    {
        // Check event-specific permissions
        if ($event) {
            // Check if event is active
            if ($event['status'] !== 'active') {
                return CheckinResult::error('Event is not active');
            }

            // Check if user is registered (if required)
            if ($event['require_prereg'] && !$this->isUserRegisteredForEvent($user['id'], $event['id'])) {
                return CheckinResult::error('User is not registered for this event');
            }

            // Check event timing
            if (!$this->isEventCheckInTimeValid($event)) {
                return CheckinResult::error('Check-in not allowed at this time for the event');
            }
        }

        return CheckinResult::success('Permissions validated');
    }

    /**
     * Check if user is registered for event
     */
    private function isUserRegisteredForEvent(int $userId, int $eventId): bool
    {
        $registration = $this->db->fetchOne(
            "SELECT id FROM event_registrations WHERE user_id = :user_id AND event_id = :event_id AND status = 'confirmed'",
            [':user_id' => $userId, ':event_id' => $eventId]
        );

        return $registration !== null;
    }

    /**
     * Check if event check-in time is valid
     */
    private function isEventCheckInTimeValid(array $event): bool
    {
        $now = new DateTime();
        $eventStart = new DateTime($event['start_time']);
        $eventEnd = isset($event['end_time']) ? new DateTime($event['end_time']) : null;

        // Allow check-in 30 minutes before event start
        $checkinStart = (clone $eventStart)->modify('-30 minutes');

        // Allow check-in until event end (or 4 hours after start if no end time)
        $checkinEnd = $eventEnd ?? (clone $eventStart)->modify('+4 hours');

        return $now >= $checkinStart && $now <= $checkinEnd;
    }

    /**
     * Send check-in/check-out notifications
     */
    private function sendCheckinNotifications(array $user, ?array $event, array $checkinData, string $action): void
    {
        $message = [
            'type' => $action,
            'user' => $user,
            'event' => $event,
            'checkin_data' => $checkinData,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->notificationService->sendRealTimeNotification('checkin_update', $message);
    }

    /**
     * Format duration in human readable format
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            return "{$minutes}m";
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return "{$hours}h {$minutes}m";
        }
    }

    /**
     * Log activity
     */
    private function logActivity(string $action, int $userId, array $data): void
    {
        // Implementation would use proper logging system
        if (function_exists('logMessage')) {
            logMessage('INFO', "Checkin activity: $action", array_merge($data, [
                'user_id' => $userId,
                'timestamp' => date('Y-m-d H:i:s')
            ]));
        }
    }

    /**
     * Log error
     */
    private function logError(string $message, array $context): void
    {
        if (function_exists('logMessage')) {
            logMessage('ERROR', $message, $context);
        }
    }

    /**
     * Load configuration
     */
    private function loadConfiguration(): void
    {
        $this->config = [
            'allow_duplicate_checkins' => false,
            'max_checkin_duration' => 86400, // 24 hours
            'require_checkout' => true,
            'auto_checkout_after' => 86400 // 24 hours
        ];
    }
}

/**
 * Checkin Result Class
 * 
 * Represents the result of a check-in operation
 */
class CheckinResult
{
    private bool $success;
    private string $message;
    private array $data;
    private string $level;

    private function __construct(bool $success, string $message, array $data = [], string $level = 'info')
    {
        $this->success = $success;
        $this->message = $message;
        $this->data = $data;
        $this->level = $level;
    }

    public static function success(string $message, array $data = []): self
    {
        return new self(true, $message, $data, 'success');
    }

    public static function error(string $message, array $data = []): self
    {
        return new self(false, $message, $data, 'error');
    }

    public static function warning(string $message, array $data = []): self
    {
        return new self(true, $message, $data, 'warning');
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
            'level' => $this->level
        ];
    }
}