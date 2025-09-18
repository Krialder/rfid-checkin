<?php

declare(strict_types=1);

namespace RfidCheckin\Repositories;

use RfidCheckin\Services\DatabaseService;
use RfidCheckin\Services\LoggingService;
use PDO;
use Exception;

/**
 * Check-in Repository
 * 
 * Handles all check-in and access log operations including RFID scanning,
 * manual check-ins, attendance tracking, and analytics. Centralizes all
 * access_logs table operations from scattered locations.
 * 
 * Features:
 * - RFID and manual check-in processing
 * - Duplicate check-in prevention
 * - Real-time attendance tracking
 * - Check-in analytics and reporting
 * - Bulk check-in operations
 * - Device management and tracking
 * 
 * @package RfidCheckin\Repositories
 * @version 1.0.0
 * @author Senior Development Team
 */
class CheckinRepository extends BaseRepository
{
    protected string $table = 'access_logs';
    protected string $primaryKey = 'log_id';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Process RFID check-in
     * 
     * @param string $rfidTag RFID tag
     * @param int|null $eventId Event ID (null for general access)
     * @param string $deviceId Device identifier
     * @param array $metadata Additional metadata
     * @return array Check-in result
     * @throws Exception If check-in fails
     */
    public function processRfidCheckin(string $rfidTag, ?int $eventId = null, string $deviceId = 'unknown', array $metadata = []): array
    {
        // Find user by RFID tag
        $user = $this->findUserByRfid($rfidTag);
        if (!$user) {
            $this->logger->warning('RFID check-in failed: Unknown tag', [
                'rfid_tag' => $rfidTag,
                'event_id' => $eventId,
                'device_id' => $deviceId
            ]);
            
            return [
                'success' => false,
                'message' => 'Unknown RFID tag',
                'error_code' => 'UNKNOWN_TAG'
            ];
        }

        return $this->processCheckin($user['user_id'], $eventId, 'rfid', $deviceId, $metadata + [
            'rfid_tag' => $rfidTag,
            'user_name' => $user['firstname'] . ' ' . $user['lastname']
        ]);
    }

    /**
     * Process manual check-in
     * 
     * @param int $userId User ID
     * @param int|null $eventId Event ID
     * @param int $checkedInBy User ID who performed the check-in
     * @param array $metadata Additional metadata
     * @return array Check-in result
     */
    public function processManualCheckin(int $userId, ?int $eventId = null, int $checkedInBy = null, array $metadata = []): array
    {
        return $this->processCheckin($userId, $eventId, 'manual', 'admin', $metadata + [
            'checked_in_by' => $checkedInBy,
            'manual_checkin' => true
        ]);
    }

    /**
     * Core check-in processing logic
     * 
     * @param int $userId User ID
     * @param int|null $eventId Event ID
     * @param string $method Check-in method (rfid, manual, qr, mobile)
     * @param string $deviceId Device identifier
     * @param array $metadata Additional metadata
     * @return array Check-in result
     */
    private function processCheckin(int $userId, ?int $eventId, string $method, string $deviceId, array $metadata = []): array
    {
        try {
            // Validate user exists and is active
            $user = $this->findUserById($userId);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found',
                    'error_code' => 'USER_NOT_FOUND'
                ];
            }

            if ($user['status'] !== 'active') {
                return [
                    'success' => false,
                    'message' => 'User account is not active',
                    'error_code' => 'USER_INACTIVE'
                ];
            }

            // Validate event if specified
            if ($eventId) {
                $event = $this->findEventById($eventId);
                if (!$event) {
                    return [
                        'success' => false,
                        'message' => 'Event not found',
                        'error_code' => 'EVENT_NOT_FOUND'
                    ];
                }

                if ($event['status'] !== 'active') {
                    return [
                        'success' => false,
                        'message' => 'Event is not active',
                        'error_code' => 'EVENT_INACTIVE'
                    ];
                }

                // Check if user is registered for event (if required)
                if ($event['require_prereg'] && !$this->isUserRegistered($userId, $eventId)) {
                    return [
                        'success' => false,
                        'message' => 'User not registered for this event',
                        'error_code' => 'NOT_REGISTERED'
                    ];
                }
            }

            // Check for duplicate check-in
            $duplicateCheck = $this->checkForDuplicate($userId, $eventId);
            if ($duplicateCheck['is_duplicate']) {
                return [
                    'success' => false,
                    'message' => 'User already checked in',
                    'error_code' => 'DUPLICATE_CHECKIN',
                    'last_checkin' => $duplicateCheck['last_checkin']
                ];
            }

            // Create check-in record
            $checkinId = $this->createCheckinRecord($userId, $eventId, $method, $deviceId, $metadata);

            // Update user's last access
            $this->updateUserLastAccess($userId);

            $this->logger->info('Successful check-in', [
                'checkin_id' => $checkinId,
                'user_id' => $userId,
                'event_id' => $eventId,
                'method' => $method,
                'device_id' => $deviceId
            ]);

            return [
                'success' => true,
                'message' => 'Check-in successful',
                'checkin_id' => $checkinId,
                'user' => [
                    'name' => $user['firstname'] . ' ' . $user['lastname'],
                    'email' => $user['email']
                ],
                'event' => $eventId ? [
                    'name' => $event['name'],
                    'date' => $event['event_date']
                ] : null,
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            $this->logger->error('Check-in processing failed', [
                'user_id' => $userId,
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Check-in failed: ' . $e->getMessage(),
                'error_code' => 'PROCESSING_ERROR'
            ];
        }
    }

    /**
     * Create check-in record in database
     * 
     * @param int $userId User ID
     * @param int|null $eventId Event ID
     * @param string $method Check-in method
     * @param string $deviceId Device identifier
     * @param array $metadata Additional metadata
     * @return int Check-in ID
     */
    private function createCheckinRecord(int $userId, ?int $eventId, string $method, string $deviceId, array $metadata = []): int
    {
        $sql = "
            INSERT INTO {$this->table} (
                user_id, event_id, access_time, access_method, 
                device_id, ip_address, user_agent, metadata, created_at
            ) VALUES (
                :user_id, :event_id, NOW(), :access_method,
                :device_id, :ip_address, :user_agent, :metadata, NOW()
            )
        ";

        $params = [
            'user_id' => $userId,
            'event_id' => $eventId,
            'access_method' => $method,
            'device_id' => $deviceId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'metadata' => json_encode($metadata)
        ];

        return $this->executeInsert($sql, $params);
    }

    /**
     * Check for duplicate check-in within time window
     * 
     * @param int $userId User ID
     * @param int|null $eventId Event ID
     * @param int $windowMinutes Time window in minutes
     * @return array Duplicate check result
     */
    private function checkForDuplicate(int $userId, ?int $eventId, int $windowMinutes = 5): array
    {
        $sql = "
            SELECT log_id, access_time
            FROM {$this->table}
            WHERE user_id = :user_id 
            AND (:event_id IS NULL OR event_id = :event_id)
            AND access_time >= DATE_SUB(NOW(), INTERVAL :window_minutes MINUTE)
            ORDER BY access_time DESC
            LIMIT 1
        ";

        $params = [
            'user_id' => $userId,
            'event_id' => $eventId,
            'window_minutes' => $windowMinutes
        ];

        $result = $this->executeQuery($sql, $params);

        return [
            'is_duplicate' => !empty($result),
            'last_checkin' => $result[0]['access_time'] ?? null
        ];
    }

    /**
     * Get check-in history for user
     * 
     * @param int $userId User ID
     * @param array $filters Additional filters
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array Check-in history with pagination
     */
    public function getUserCheckinHistory(int $userId, array $filters = [], int $page = 1, int $limit = 20): array
    {
        $where = ['al.user_id = :user_id'];
        $params = ['user_id' => $userId];

        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(al.access_time) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(al.access_time) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['event_id'])) {
            $where[] = 'al.event_id = :event_id';
            $params['event_id'] = $filters['event_id'];
        }

        if (!empty($filters['method'])) {
            $where[] = 'al.access_method = :method';
            $params['method'] = $filters['method'];
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $limit;

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} al WHERE {$whereClause}";
        $countResult = $this->executeQuery($countSql, $params);
        $total = $countResult[0]['total'];

        // Get check-in records
        $sql = "
            SELECT al.*, e.name as event_name, e.event_date
            FROM {$this->table} al
            LEFT JOIN events e ON al.event_id = e.event_id
            WHERE {$whereClause}
            ORDER BY al.access_time DESC
            LIMIT :limit OFFSET :offset
        ";

        $params['limit'] = $limit;
        $params['offset'] = $offset;

        $checkins = $this->executeQuery($sql, $params);

        // Parse metadata for each record
        foreach ($checkins as &$checkin) {
            $checkin['metadata'] = $checkin['metadata'] ? json_decode($checkin['metadata'], true) : [];
        }

        return [
            'data' => $checkins,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total / $limit),
                'total_items' => $total,
                'items_per_page' => $limit
            ]
        ];
    }

    /**
     * Get event check-in list
     * 
     * @param int $eventId Event ID
     * @param array $filters Additional filters
     * @return array Event check-ins
     */
    public function getEventCheckins(int $eventId, array $filters = []): array
    {
        $where = ['al.event_id = :event_id'];
        $params = ['event_id' => $eventId];

        if (!empty($filters['date_from'])) {
            $where[] = 'al.access_time >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'al.access_time <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['method'])) {
            $where[] = 'al.access_method = :method';
            $params['method'] = $filters['method'];
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT al.*, u.firstname, u.lastname, u.email, u.rfid_tag
            FROM {$this->table} al
            INNER JOIN users u ON al.user_id = u.user_id
            WHERE {$whereClause}
            ORDER BY al.access_time DESC
        ";

        $checkins = $this->executeQuery($sql, $params);

        foreach ($checkins as &$checkin) {
            $checkin['metadata'] = $checkin['metadata'] ? json_decode($checkin['metadata'], true) : [];
        }

        return $checkins;
    }

    /**
     * Get attendance analytics
     * 
     * @param array $filters Date range and other filters
     * @return array Analytics data
     */
    public function getAttendanceAnalytics(array $filters = []): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(al.access_time) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(al.access_time) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['event_id'])) {
            $where[] = 'al.event_id = :event_id';
            $params['event_id'] = $filters['event_id'];
        }

        $whereClause = implode(' AND ', $where);

        // Daily attendance
        $dailySql = "
            SELECT DATE(al.access_time) as date,
                   COUNT(*) as total_checkins,
                   COUNT(DISTINCT al.user_id) as unique_users
            FROM {$this->table} al
            WHERE {$whereClause}
            GROUP BY DATE(al.access_time)
            ORDER BY date DESC
        ";

        $dailyStats = $this->executeQuery($dailySql, $params);

        // Method breakdown
        $methodSql = "
            SELECT al.access_method,
                   COUNT(*) as count,
                   COUNT(DISTINCT al.user_id) as unique_users
            FROM {$this->table} al
            WHERE {$whereClause}
            GROUP BY al.access_method
            ORDER BY count DESC
        ";

        $methodStats = $this->executeQuery($methodSql, $params);

        // Hourly distribution
        $hourlySql = "
            SELECT HOUR(al.access_time) as hour,
                   COUNT(*) as count
            FROM {$this->table} al
            WHERE {$whereClause}
            GROUP BY HOUR(al.access_time)
            ORDER BY hour
        ";

        $hourlyStats = $this->executeQuery($hourlySql, $params);

        // Top users
        $topUsersSql = "
            SELECT u.firstname, u.lastname, u.email,
                   COUNT(al.log_id) as checkin_count
            FROM {$this->table} al
            INNER JOIN users u ON al.user_id = u.user_id
            WHERE {$whereClause}
            GROUP BY al.user_id
            ORDER BY checkin_count DESC
            LIMIT 10
        ";

        $topUsers = $this->executeQuery($topUsersSql, $params);

        return [
            'daily_stats' => $dailyStats,
            'method_breakdown' => $methodStats,
            'hourly_distribution' => $hourlyStats,
            'top_users' => $topUsers,
            'summary' => [
                'total_checkins' => array_sum(array_column($dailyStats, 'total_checkins')),
                'unique_users' => count($topUsers),
                'most_popular_method' => $methodStats[0]['access_method'] ?? 'N/A',
                'peak_hour' => $this->findPeakHour($hourlyStats)
            ]
        ];
    }

    /**
     * Get real-time check-in feed
     * 
     * @param int $limit Number of recent check-ins
     * @param int|null $eventId Filter by event
     * @return array Recent check-ins
     */
    public function getRealtimeFeed(int $limit = 10, ?int $eventId = null): array
    {
        $where = '1 = 1';
        $params = ['limit' => $limit];

        if ($eventId) {
            $where = 'al.event_id = :event_id';
            $params['event_id'] = $eventId;
        }

        $sql = "
            SELECT al.*, u.firstname, u.lastname, e.name as event_name
            FROM {$this->table} al
            INNER JOIN users u ON al.user_id = u.user_id
            LEFT JOIN events e ON al.event_id = e.event_id
            WHERE {$where}
            ORDER BY al.access_time DESC
            LIMIT :limit
        ";

        $feed = $this->executeQuery($sql, $params);

        foreach ($feed as &$item) {
            $item['metadata'] = $item['metadata'] ? json_decode($item['metadata'], true) : [];
            $item['time_ago'] = $this->timeAgo($item['access_time']);
        }

        return $feed;
    }

    /**
     * Bulk check-in operation
     * 
     * @param array $userIds Array of user IDs
     * @param int|null $eventId Event ID
     * @param int $checkedInBy User performing bulk check-in
     * @return array Bulk operation result
     */
    public function bulkCheckin(array $userIds, ?int $eventId = null, int $checkedInBy = null): array
    {
        $results = [
            'successful' => [],
            'failed' => [],
            'total' => count($userIds)
        ];

        foreach ($userIds as $userId) {
            $result = $this->processManualCheckin($userId, $eventId, $checkedInBy, [
                'bulk_operation' => true,
                'batch_id' => uniqid('bulk_')
            ]);

            if ($result['success']) {
                $results['successful'][] = [
                    'user_id' => $userId,
                    'checkin_id' => $result['checkin_id']
                ];
            } else {
                $results['failed'][] = [
                    'user_id' => $userId,
                    'error' => $result['message']
                ];
            }
        }

        $this->logger->info('Bulk check-in completed', [
            'total' => $results['total'],
            'successful' => count($results['successful']),
            'failed' => count($results['failed']),
            'event_id' => $eventId,
            'performed_by' => $checkedInBy
        ]);

        return $results;
    }

    /**
     * Helper methods for data lookup
     */
    private function findUserByRfid(string $rfidTag): ?array
    {
        $sql = "SELECT user_id, firstname, lastname, email, status FROM users WHERE rfid_tag = :rfid_tag";
        $result = $this->executeQuery($sql, ['rfid_tag' => $rfidTag]);
        return $result[0] ?? null;
    }

    private function findUserById(int $userId): ?array
    {
        $sql = "SELECT user_id, firstname, lastname, email, status FROM users WHERE user_id = :user_id";
        $result = $this->executeQuery($sql, ['user_id' => $userId]);
        return $result[0] ?? null;
    }

    private function findEventById(int $eventId): ?array
    {
        $sql = "SELECT event_id, name, event_date, status, require_prereg FROM events WHERE event_id = :event_id";
        $result = $this->executeQuery($sql, ['event_id' => $eventId]);
        return $result[0] ?? null;
    }

    private function isUserRegistered(int $userId, int $eventId): bool
    {
        $sql = "SELECT registration_id FROM event_registrations WHERE user_id = :user_id AND event_id = :event_id AND status = 'confirmed'";
        $result = $this->executeQuery($sql, ['user_id' => $userId, 'event_id' => $eventId]);
        return !empty($result);
    }

    private function updateUserLastAccess(int $userId): void
    {
        $sql = "UPDATE users SET last_access = NOW() WHERE user_id = :user_id";
        $this->executeUpdate($sql, ['user_id' => $userId]);
    }

    private function findPeakHour(array $hourlyStats): string
    {
        if (empty($hourlyStats)) {
            return 'N/A';
        }

        $maxCount = 0;
        $peakHour = 0;
        
        foreach ($hourlyStats as $stat) {
            if ($stat['count'] > $maxCount) {
                $maxCount = $stat['count'];
                $peakHour = $stat['hour'];
            }
        }

        return sprintf('%02d:00', $peakHour);
    }

    /**
     * Get today's check-in count
     */
    public function getTodayCount(): int
    {
        $sql = "
            SELECT COUNT(*) as count 
            FROM {$this->table} 
            WHERE DATE(checkin_time) = CURDATE()
        ";
        $result = $this->db->selectOne($sql);
        return (int) $result['count'];
    }

    /**
     * Get check-in count for period
     */
    public function getPeriodCount(string $dateFrom, string $dateTo): int
    {
        $sql = "
            SELECT COUNT(*) as count 
            FROM {$this->table} 
            WHERE DATE(checkin_time) BETWEEN ? AND ?
        ";
        $result = $this->db->selectOne($sql, [$dateFrom, $dateTo]);
        return (int) $result['count'];
    }

    /**
     * Get user's today check-in count
     */
    public function getUserTodayCount(int $userId): int
    {
        $sql = "
            SELECT COUNT(*) as count 
            FROM {$this->table} 
            WHERE user_id = ? AND DATE(checkin_time) = CURDATE()
        ";
        $result = $this->db->selectOne($sql, [$userId]);
        return (int) $result['count'];
    }

    /**
     * Get user's check-in count for period
     */
    public function getUserPeriodCount(int $userId, string $dateFrom, string $dateTo): int
    {
        $sql = "
            SELECT COUNT(*) as count 
            FROM {$this->table} 
            WHERE user_id = ? AND DATE(checkin_time) BETWEEN ? AND ?
        ";
        $result = $this->db->selectOne($sql, [$userId, $dateFrom, $dateTo]);
        return (int) $result['count'];
    }

    /**
     * Get user's last check-in
     */
    public function getUserLastCheckin(int $userId): ?array
    {
        $sql = "
            SELECT al.*, e.name as event_name
            FROM {$this->table} al
            LEFT JOIN events e ON al.event_id = e.event_id
            WHERE al.user_id = ?
            ORDER BY al.checkin_time DESC
            LIMIT 1
        ";
        return $this->db->selectOne($sql, [$userId]);
    }

    private function timeAgo(string $datetime): string
    {
        $time = strtotime($datetime);
        $diff = time() - $time;

        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . ' minutes ago';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . ' hours ago';
        } else {
            return floor($diff / 86400) . ' days ago';
        }
    }
}
