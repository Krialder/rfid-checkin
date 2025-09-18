<?php
/**
 * Check-in Repository Class
 * 
 * Handles all database operations related to check-ins including recording
 * attendance, tracking user activity, and generating comprehensive reports.
 * Provides optimized queries for attendance analytics and real-time monitoring.
 * 
 * Features:
 * - Check-in/check-out management
 * - Real-time attendance tracking
 * - Comprehensive analytics and reporting
 * - Bulk operations for administrative tasks
 * - Activity monitoring and audit trails
 * - Integration with events and user systems
 * - Performance-optimized queries for large datasets
 * 
 * @package    RFID Check-in System
 * @subpackage Data Access Layer - Check-ins
 * @version    2.0.0
 * @author     Senior Developer Team
 * @since      2.0.0
 */

require_once __DIR__ . '/BaseRepository.php';

class CheckinRepository extends BaseRepository {
    
    protected $table = 'checkin';
    protected $primaryKey = 'checkin_id';
    
    /**
     * Record a new check-in
     * 
     * @param int $userId User ID
     * @param int $eventId Event ID
     * @param array $options Additional options (device_id, notes, status)
     * @return int|bool Check-in ID on success, false on failure
     */
    public function recordCheckin($userId, $eventId, array $options = []) {
        // Check if user already checked in today for this event
        $existing = $this->findWhere([
            'user_id' => $userId,
            'event_id' => $eventId
        ]);
        
        foreach ($existing as $checkin) {
            if (date('Y-m-d', strtotime($checkin['checkin_time'])) === date('Y-m-d')) {
                throw new InvalidArgumentException('User already checked in today for this event');
            }
        }
        
        $checkinData = [
            'user_id' => $userId,
            'event_id' => $eventId,
            'checkin_time' => date('Y-m-d H:i:s'),
            'status' => $options['status'] ?? 'present',
            'notes' => $options['notes'] ?? null,
            'device_id' => $options['device_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->insert($checkinData);
    }
    
    /**
     * Get check-ins with pagination and filtering
     * 
     * @param array $filters Filter conditions
     * @param int $page Page number (1-based)
     * @param int $limit Records per page
     * @return array ['checkins' => array, 'total' => int, 'page' => int, 'totalPages' => int]
     */
    public function getCheckinsPaginated(array $filters = [], $page = 1, $limit = 25) {
        $conditions = ['1=1'];
        $params = [];
        
        // Apply user filter
        if (!empty($filters['user_id'])) {
            $conditions[] = "c.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        // Apply event filter
        if (!empty($filters['event_id'])) {
            $conditions[] = "c.event_id = ?";
            $params[] = $filters['event_id'];
        }
        
        // Apply date range filter
        if (!empty($filters['date_from'])) {
            $conditions[] = "DATE(c.checkin_time) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $conditions[] = "DATE(c.checkin_time) <= ?";
            $params[] = $filters['date_to'];
        }
        
        // Apply status filter
        if (!empty($filters['status'])) {
            $conditions[] = "c.status = ?";
            $params[] = $filters['status'];
        }
        
        // Apply search filter
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $conditions[] = "(u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR e.name LIKE ?)";
            $params = array_merge($params, [$search, $search, $search, $search]);
        }
        
        $whereClause = implode(' AND ', $conditions);
        
        // Get total count
        $countSql = "
            SELECT COUNT(*) 
            FROM checkin c
            JOIN users u ON c.user_id = u.user_id
            JOIN events e ON c.event_id = e.event_id
            WHERE $whereClause
        ";
        $stmt = $this->query($countSql, $params);
        $total = (int)$stmt->fetchColumn();
        
        // Calculate pagination
        $offset = ($page - 1) * $limit;
        $totalPages = ceil($total / $limit);
        
        // Get check-ins with related data
        $sql = "
            SELECT 
                c.*,
                u.username,
                u.first_name,
                u.last_name,
                u.email,
                e.name as event_name,
                e.location as event_location,
                e.start_time as event_start_time,
                rd.device_name
            FROM checkin c
            JOIN users u ON c.user_id = u.user_id
            JOIN events e ON c.event_id = e.event_id
            LEFT JOIN rfiddevices rd ON c.device_id = rd.device_id
            WHERE $whereClause
            ORDER BY c.checkin_time DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->query($sql, array_merge($params, [$limit, $offset]));
        $checkins = $stmt->fetchAll();
        
        return [
            'checkins' => $checkins,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'limit' => $limit
        ];
    }
    
    /**
     * Get recent check-ins
     * 
     * @param int $limit Number of records to return
     * @param int|null $userId Filter by user ID
     * @return array Recent check-ins
     */
    public function getRecentCheckins($limit = 20, $userId = null) {
        $sql = "
            SELECT 
                c.*,
                u.username,
                u.first_name,
                u.last_name,
                e.name as event_name,
                e.location as event_location
            FROM checkin c
            JOIN users u ON c.user_id = u.user_id
            JOIN events e ON c.event_id = e.event_id
        ";
        
        $params = [];
        
        if ($userId) {
            $sql .= " WHERE c.user_id = ?";
            $params[] = $userId;
        }
        
        $sql .= " ORDER BY c.checkin_time DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get check-in statistics for a user
     * 
     * @param int $userId User ID
     * @param array $options Options for date range and grouping
     * @return array Check-in statistics
     */
    public function getUserCheckinStats($userId, array $options = []) {
        $dateFilter = '';
        $params = [$userId];
        
        if (!empty($options['date_from'])) {
            $dateFilter .= " AND DATE(c.checkin_time) >= ?";
            $params[] = $options['date_from'];
        }
        
        if (!empty($options['date_to'])) {
            $dateFilter .= " AND DATE(c.checkin_time) <= ?";
            $params[] = $options['date_to'];
        }
        
        $sql = "
            SELECT 
                COUNT(*) as total_checkins,
                COUNT(DISTINCT c.event_id) as unique_events,
                COUNT(DISTINCT DATE(c.checkin_time)) as unique_days,
                MIN(c.checkin_time) as first_checkin,
                MAX(c.checkin_time) as last_checkin,
                COUNT(CASE WHEN MONTH(c.checkin_time) = MONTH(CURRENT_DATE()) 
                      AND YEAR(c.checkin_time) = YEAR(CURRENT_DATE()) THEN 1 END) as current_month_checkins,
                COUNT(CASE WHEN WEEK(c.checkin_time) = WEEK(CURRENT_DATE()) 
                      AND YEAR(c.checkin_time) = YEAR(CURRENT_DATE()) THEN 1 END) as current_week_checkins,
                AVG(TIMESTAMPDIFF(MINUTE, 
                    TIMESTAMP(e.start_date, e.start_time), 
                    c.checkin_time)) as avg_checkin_timing
            FROM checkin c
            JOIN events e ON c.event_id = e.event_id
            WHERE c.user_id = ? $dateFilter
        ";
        
        $stmt = $this->query($sql, $params);
        $stats = $stmt->fetch();
        
        return [
            'total_checkins' => (int)$stats['total_checkins'],
            'unique_events' => (int)$stats['unique_events'],
            'unique_days' => (int)$stats['unique_days'],
            'first_checkin' => $stats['first_checkin'],
            'last_checkin' => $stats['last_checkin'],
            'current_month_checkins' => (int)$stats['current_month_checkins'],
            'current_week_checkins' => (int)$stats['current_week_checkins'],
            'avg_checkin_timing' => $stats['avg_checkin_timing'] ? round($stats['avg_checkin_timing'], 1) : null
        ];
    }
    
    /**
     * Get check-in statistics for an event
     * 
     * @param int $eventId Event ID
     * @param array $options Options for date range and analysis
     * @return array Event check-in statistics
     */
    public function getEventCheckinStats($eventId, array $options = []) {
        $dateFilter = '';
        $params = [$eventId];
        
        if (!empty($options['date_from'])) {
            $dateFilter .= " AND DATE(c.checkin_time) >= ?";
            $params[] = $options['date_from'];
        }
        
        if (!empty($options['date_to'])) {
            $dateFilter .= " AND DATE(c.checkin_time) <= ?";
            $params[] = $options['date_to'];
        }
        
        $sql = "
            SELECT 
                COUNT(*) as total_checkins,
                COUNT(DISTINCT c.user_id) as unique_attendees,
                COUNT(DISTINCT DATE(c.checkin_time)) as unique_days,
                MIN(c.checkin_time) as first_checkin,
                MAX(c.checkin_time) as last_checkin,
                e.capacity,
                (CASE WHEN e.capacity IS NOT NULL THEN 
                    (e.capacity - COUNT(DISTINCT c.user_id)) 
                ELSE NULL END) as remaining_capacity,
                AVG(TIMESTAMPDIFF(MINUTE, 
                    TIMESTAMP(e.start_date, e.start_time), 
                    c.checkin_time)) as avg_checkin_timing
            FROM checkin c
            JOIN events e ON c.event_id = e.event_id
            WHERE c.event_id = ? $dateFilter
            GROUP BY e.event_id
        ";
        
        $stmt = $this->query($sql, $params);
        $stats = $stmt->fetch();
        
        if (!$stats) {
            return [
                'total_checkins' => 0,
                'unique_attendees' => 0,
                'unique_days' => 0,
                'first_checkin' => null,
                'last_checkin' => null,
                'capacity' => null,
                'remaining_capacity' => null,
                'avg_checkin_timing' => null
            ];
        }
        
        return [
            'total_checkins' => (int)$stats['total_checkins'],
            'unique_attendees' => (int)$stats['unique_attendees'],
            'unique_days' => (int)$stats['unique_days'],
            'first_checkin' => $stats['first_checkin'],
            'last_checkin' => $stats['last_checkin'],
            'capacity' => $stats['capacity'],
            'remaining_capacity' => $stats['remaining_capacity'],
            'avg_checkin_timing' => $stats['avg_checkin_timing'] ? round($stats['avg_checkin_timing'], 1) : null
        ];
    }
    
    /**
     * Get daily check-in trends
     * 
     * @param array $options Filter options (date_from, date_to, user_id, event_id)
     * @return array Daily check-in data
     */
    public function getDailyTrends(array $options = []) {
        $conditions = ['1=1'];
        $params = [];
        
        if (!empty($options['date_from'])) {
            $conditions[] = "DATE(c.checkin_time) >= ?";
            $params[] = $options['date_from'];
        }
        
        if (!empty($options['date_to'])) {
            $conditions[] = "DATE(c.checkin_time) <= ?";
            $params[] = $options['date_to'];
        }
        
        if (!empty($options['user_id'])) {
            $conditions[] = "c.user_id = ?";
            $params[] = $options['user_id'];
        }
        
        if (!empty($options['event_id'])) {
            $conditions[] = "c.event_id = ?";
            $params[] = $options['event_id'];
        }
        
        $whereClause = implode(' AND ', $conditions);
        
        $sql = "
            SELECT 
                DATE(c.checkin_time) as check_date,
                COUNT(*) as total_checkins,
                COUNT(DISTINCT c.user_id) as unique_users,
                COUNT(DISTINCT c.event_id) as unique_events,
                DAYNAME(c.checkin_time) as day_name
            FROM checkin c
            WHERE $whereClause
            GROUP BY DATE(c.checkin_time)
            ORDER BY check_date ASC
        ";
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get hourly check-in distribution
     * 
     * @param array $options Filter options
     * @return array Hourly distribution data
     */
    public function getHourlyDistribution(array $options = []) {
        $conditions = ['1=1'];
        $params = [];
        
        if (!empty($options['date_from'])) {
            $conditions[] = "DATE(c.checkin_time) >= ?";
            $params[] = $options['date_from'];
        }
        
        if (!empty($options['date_to'])) {
            $conditions[] = "DATE(c.checkin_time) <= ?";
            $params[] = $options['date_to'];
        }
        
        $whereClause = implode(' AND ', $conditions);
        
        $sql = "
            SELECT 
                HOUR(c.checkin_time) as check_hour,
                COUNT(*) as total_checkins,
                COUNT(DISTINCT c.user_id) as unique_users
            FROM checkin c
            WHERE $whereClause
            GROUP BY HOUR(c.checkin_time)
            ORDER BY check_hour ASC
        ";
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get top users by check-in frequency
     * 
     * @param int $limit Number of users to return
     * @param array $options Filter options
     * @return array Top users data
     */
    public function getTopUsers($limit = 10, array $options = []) {
        $conditions = ['1=1'];
        $params = [];
        
        if (!empty($options['date_from'])) {
            $conditions[] = "DATE(c.checkin_time) >= ?";
            $params[] = $options['date_from'];
        }
        
        if (!empty($options['date_to'])) {
            $conditions[] = "DATE(c.checkin_time) <= ?";
            $params[] = $options['date_to'];
        }
        
        $whereClause = implode(' AND ', $conditions);
        
        $sql = "
            SELECT 
                u.user_id,
                u.username,
                u.first_name,
                u.last_name,
                COUNT(*) as total_checkins,
                COUNT(DISTINCT c.event_id) as unique_events,
                COUNT(DISTINCT DATE(c.checkin_time)) as unique_days,
                MAX(c.checkin_time) as last_checkin
            FROM checkin c
            JOIN users u ON c.user_id = u.user_id
            WHERE $whereClause
            GROUP BY u.user_id
            ORDER BY total_checkins DESC, unique_events DESC
            LIMIT ?
        ";
        
        $params[] = $limit;
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get attendance report for specific event and date
     * 
     * @param int $eventId Event ID
     * @param string $date Date in Y-m-d format
     * @return array Attendance report
     */
    public function getAttendanceReport($eventId, $date) {
        $sql = "
            SELECT 
                c.*,
                u.username,
                u.first_name,
                u.last_name,
                u.email,
                u.department,
                ROW_NUMBER() OVER (ORDER BY c.checkin_time ASC) as arrival_order
            FROM checkin c
            JOIN users u ON c.user_id = u.user_id
            WHERE c.event_id = ? AND DATE(c.checkin_time) = ?
            ORDER BY c.checkin_time ASC
        ";
        
        $stmt = $this->query($sql, [$eventId, $date]);
        return $stmt->fetchAll();
    }
    
    /**
     * Check if user has checked in for event today
     * 
     * @param int $userId User ID
     * @param int $eventId Event ID
     * @return bool True if checked in, false otherwise
     */
    public function hasUserCheckedInToday($userId, $eventId) {
        $sql = "
            SELECT COUNT(*) 
            FROM checkin 
            WHERE user_id = ? AND event_id = ? AND DATE(checkin_time) = CURDATE()
        ";
        
        $stmt = $this->query($sql, [$userId, $eventId]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Get system-wide check-in statistics
     * 
     * @return array System statistics
     */
    public function getSystemStats() {
        $sql = "
            SELECT 
                COUNT(*) as total_checkins,
                COUNT(DISTINCT user_id) as total_unique_users,
                COUNT(DISTINCT event_id) as total_unique_events,
                COUNT(CASE WHEN DATE(checkin_time) = CURDATE() THEN 1 END) as today_checkins,
                COUNT(CASE WHEN DATE(checkin_time) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_checkins,
                COUNT(CASE WHEN DATE(checkin_time) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as month_checkins,
                AVG(TIMESTAMPDIFF(MINUTE, 
                    TIMESTAMP(e.start_date, e.start_time), 
                    c.checkin_time)) as avg_checkin_timing
            FROM checkin c
            JOIN events e ON c.event_id = e.event_id
        ";
        
        $stmt = $this->query($sql);
        $stats = $stmt->fetch();
        
        return [
            'total_checkins' => (int)$stats['total_checkins'],
            'total_unique_users' => (int)$stats['total_unique_users'],
            'total_unique_events' => (int)$stats['total_unique_events'],
            'today_checkins' => (int)$stats['today_checkins'],
            'week_checkins' => (int)$stats['week_checkins'],
            'month_checkins' => (int)$stats['month_checkins'],
            'avg_checkin_timing' => $stats['avg_checkin_timing'] ? round($stats['avg_checkin_timing'], 1) : null
        ];
    }
    
    /**
     * Update check-in status
     * 
     * @param int $checkinId Check-in ID
     * @param string $status New status
     * @param string|null $notes Optional notes
     * @return bool Success status
     */
    public function updateStatus($checkinId, $status, $notes = null) {
        $updateData = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($notes !== null) {
            $updateData['notes'] = $notes;
        }
        
        return $this->update($checkinId, $updateData);
    }
    
    /**
     * Bulk import check-ins from array
     * 
     * @param array $checkins Array of check-in data
     * @return array Results with success/failure counts
     */
    public function bulkImport(array $checkins) {
        $success = 0;
        $failures = [];
        
        $this->beginTransaction();
        
        try {
            foreach ($checkins as $index => $checkin) {
                try {
                    $this->recordCheckin(
                        $checkin['user_id'],
                        $checkin['event_id'],
                        $checkin
                    );
                    $success++;
                } catch (Exception $e) {
                    $failures[] = [
                        'index' => $index,
                        'data' => $checkin,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            if (empty($failures)) {
                $this->commit();
            } else {
                $this->rollback();
            }
            
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        
        return [
            'success_count' => $success,
            'failure_count' => count($failures),
            'failures' => $failures
        ];
    }
}
