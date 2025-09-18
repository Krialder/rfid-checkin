<?php
/**
 * Event Repository Class
 * 
 * Handles all database operations related to events including creation,
 * scheduling, capacity management, and event statistics. Provides
 * advanced querying capabilities for recurring events and attendance tracking.
 * 
 * Features:
 * - Event lifecycle management (CRUD operations)
 * - Recurring event pattern handling
 * - Capacity management and availability checking
 * - Event statistics and attendance tracking
 * - Advanced filtering and search capabilities
 * - Instance management for recurring events
 * - Integration with user and check-in systems
 * 
 * @package    RFID Check-in System
 * @subpackage Data Access Layer - Events
 * @version    2.0.0
 * @author     Senior Developer Team
 * @since      2.0.0
 */

require_once __DIR__ . '/BaseRepository.php';

class EventRepository extends BaseRepository {
    
    protected $table = 'events';
    protected $primaryKey = 'event_id';
    
    /**
     * Get upcoming events for a user
     * 
     * @param int|null $userId User ID (null for all events)
     * @param int $limit Number of events to return
     * @param bool $activeOnly Only active events
     * @return array Upcoming events
     */
    public function getUpcomingEvents($userId = null, $limit = 10, $activeOnly = true) {
        $sql = "
            SELECT 
                e.*,
                COUNT(DISTINCT c.checkin_id) as current_attendance,
                (CASE WHEN e.capacity IS NOT NULL THEN 
                    (e.capacity - COUNT(DISTINCT c.checkin_id)) 
                ELSE NULL END) as available_spots,
                (CASE WHEN uc.checkin_id IS NOT NULL THEN 1 ELSE 0 END) as user_checked_in
            FROM events e
            LEFT JOIN checkin c ON e.event_id = c.event_id
            LEFT JOIN checkin uc ON e.event_id = uc.event_id AND uc.user_id = ?
            WHERE e.start_date >= CURDATE()
        ";
        
        $params = [$userId];
        
        if ($activeOnly) {
            $sql .= " AND e.active = 1";
        }
        
        $sql .= "
            GROUP BY e.event_id
            ORDER BY e.start_date ASC, e.start_time ASC
            LIMIT ?
        ";
        
        $params[] = $limit;
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get events available for immediate check-in
     * 
     * @param int|null $userId User ID to exclude already checked-in events
     * @return array Available events
     */
    public function getAvailableForCheckin($userId = null) {
        $sql = "
            SELECT 
                e.*,
                COUNT(DISTINCT c.checkin_id) as current_attendance,
                (CASE WHEN e.capacity IS NOT NULL THEN 
                    (e.capacity - COUNT(DISTINCT c.checkin_id)) 
                ELSE NULL END) as available_spots
            FROM events e
            LEFT JOIN checkin c ON e.event_id = c.event_id
            WHERE e.active = 1
            AND e.require_checkin = 1
            AND (
                (e.start_date = CURDATE() AND TIME(NOW()) BETWEEN 
                    SUBTIME(e.start_time, '00:30:00') AND 
                    ADDTIME(IFNULL(e.end_time, '23:59:59'), '00:30:00'))
                OR
                (e.start_date < CURDATE() AND (e.end_date IS NULL OR e.end_date >= CURDATE()))
            )
        ";
        
        $params = [];
        
        if ($userId) {
            $sql .= " AND e.event_id NOT IN (
                SELECT event_id FROM checkin 
                WHERE user_id = ? AND DATE(checkin_time) = CURDATE()
            )";
            $params[] = $userId;
        }
        
        $sql .= "
            GROUP BY e.event_id
            HAVING (e.capacity IS NULL OR available_spots > 0)
            ORDER BY e.start_time ASC
        ";
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get events with pagination and filtering
     * 
     * @param array $filters Filter conditions
     * @param int $page Page number (1-based)
     * @param int $limit Records per page
     * @return array ['events' => array, 'total' => int, 'page' => int, 'totalPages' => int]
     */
    public function getEventsPaginated(array $filters = [], $page = 1, $limit = 25) {
        $conditions = ['1=1'];
        $params = [];
        
        // Apply search filter
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $conditions[] = "(e.name LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
            $params = array_merge($params, [$search, $search, $search]);
        }
        
        // Apply type filter
        if (!empty($filters['type'])) {
            $conditions[] = "e.event_type = ?";
            $params[] = $filters['type'];
        }
        
        // Apply status filter
        if (isset($filters['status'])) {
            $conditions[] = "e.active = ?";
            $params[] = $filters['status'] ? 1 : 0;
        }
        
        // Apply date range filter
        if (!empty($filters['date_from'])) {
            $conditions[] = "e.start_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $conditions[] = "e.start_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        $whereClause = implode(' AND ', $conditions);
        
        // Get total count
        $countSql = "SELECT COUNT(*) FROM events e WHERE $whereClause";
        $stmt = $this->query($countSql, $params);
        $total = (int)$stmt->fetchColumn();
        
        // Calculate pagination
        $offset = ($page - 1) * $limit;
        $totalPages = ceil($total / $limit);
        
        // Get events with statistics
        $sql = "
            SELECT e.*, 
                   u.username as created_by_username,
                   COUNT(DISTINCT c.checkin_id) as total_checkins,
                   COUNT(DISTINCT c.user_id) as unique_attendees,
                   MAX(c.checkin_time) as last_checkin
            FROM events e 
            LEFT JOIN users u ON e.created_by = u.user_id
            LEFT JOIN checkin c ON e.event_id = c.event_id 
            WHERE $whereClause 
            GROUP BY e.event_id 
            ORDER BY e.start_date DESC, e.start_time DESC 
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->query($sql, array_merge($params, [$limit, $offset]));
        $events = $stmt->fetchAll();
        
        return [
            'events' => $events,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'limit' => $limit
        ];
    }
    
    /**
     * Get event statistics
     * 
     * @param int $eventId Event ID
     * @return array Event statistics
     */
    public function getEventStats($eventId) {
        $sql = "
            SELECT 
                e.capacity,
                COUNT(DISTINCT c.checkin_id) as total_checkins,
                COUNT(DISTINCT c.user_id) as unique_attendees,
                COUNT(DISTINCT DATE(c.checkin_time)) as days_with_checkins,
                MIN(c.checkin_time) as first_checkin,
                MAX(c.checkin_time) as last_checkin,
                AVG(TIMESTAMPDIFF(MINUTE, 
                    TIMESTAMP(e.start_date, e.start_time), 
                    c.checkin_time)) as avg_checkin_timing,
                (CASE WHEN e.capacity IS NOT NULL THEN 
                    (e.capacity - COUNT(DISTINCT c.checkin_id)) 
                ELSE NULL END) as available_spots
            FROM events e
            LEFT JOIN checkin c ON e.event_id = c.event_id
            WHERE e.event_id = ?
            GROUP BY e.event_id
        ";
        
        $stmt = $this->query($sql, [$eventId]);
        $stats = $stmt->fetch();
        
        if (!$stats) {
            return null;
        }
        
        return [
            'capacity' => $stats['capacity'],
            'total_checkins' => (int)$stats['total_checkins'],
            'unique_attendees' => (int)$stats['unique_attendees'],
            'days_with_checkins' => (int)$stats['days_with_checkins'],
            'first_checkin' => $stats['first_checkin'],
            'last_checkin' => $stats['last_checkin'],
            'avg_checkin_timing' => $stats['avg_checkin_timing'] ? round($stats['avg_checkin_timing'], 1) : null,
            'available_spots' => $stats['available_spots']
        ];
    }
    
    /**
     * Get event attendees
     * 
     * @param int $eventId Event ID
     * @param array $options Query options
     * @return array Event attendees
     */
    public function getEventAttendees($eventId, array $options = []) {
        $sql = "
            SELECT 
                u.user_id,
                u.username,
                u.first_name,
                u.last_name,
                u.email,
                c.checkin_time,
                c.status as checkin_status,
                ROW_NUMBER() OVER (ORDER BY c.checkin_time ASC) as checkin_order
            FROM checkin c
            JOIN users u ON c.user_id = u.user_id
            WHERE c.event_id = ?
        ";
        
        $params = [$eventId];
        
        // Add date filter if specified
        if (!empty($options['date'])) {
            $sql .= " AND DATE(c.checkin_time) = ?";
            $params[] = $options['date'];
        }
        
        // Add status filter if specified
        if (!empty($options['status'])) {
            $sql .= " AND c.status = ?";
            $params[] = $options['status'];
        }
        
        $sql .= " ORDER BY c.checkin_time ASC";
        
        // Add limit if specified
        if (!empty($options['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = $options['limit'];
        }
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Create new event
     * 
     * @param array $eventData Event data
     * @return int|bool Event ID on success, false on failure
     */
    public function createEvent(array $eventData) {
        // Validate required fields
        $required = ['name', 'start_date'];
        foreach ($required as $field) {
            if (empty($eventData[$field])) {
                throw new InvalidArgumentException("Field '$field' is required");
            }
        }
        
        // Set defaults
        $eventData['created_at'] = date('Y-m-d H:i:s');
        $eventData['active'] = $eventData['active'] ?? true;
        $eventData['event_type'] = $eventData['event_type'] ?? 'meeting';
        $eventData['require_checkin'] = $eventData['require_checkin'] ?? true;
        $eventData['allow_manual_checkin'] = $eventData['allow_manual_checkin'] ?? true;
        
        return $this->insert($eventData);
    }
    
    /**
     * Update event
     * 
     * @param int $eventId Event ID
     * @param array $eventData Updated event data
     * @return bool Success status
     */
    public function updateEvent($eventId, array $eventData) {
        // Remove ID and timestamps from update data
        unset($eventData['event_id'], $eventData['created_at']);
        
        $eventData['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->update($eventId, $eventData);
    }
    
    /**
     * Get events by date range
     * 
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @param bool $activeOnly Only active events
     * @return array Events in date range
     */
    public function getEventsByDateRange($startDate, $endDate, $activeOnly = true) {
        $conditions = [
            'start_date >=' => $startDate,
            'start_date <=' => $endDate
        ];
        
        if ($activeOnly) {
            $conditions['active'] = 1;
        }
        
        $sql = "
            SELECT e.*, 
                   COUNT(DISTINCT c.checkin_id) as total_checkins
            FROM events e
            LEFT JOIN checkin c ON e.event_id = c.event_id
            WHERE e.start_date >= ? AND e.start_date <= ?
        ";
        
        $params = [$startDate, $endDate];
        
        if ($activeOnly) {
            $sql .= " AND e.active = 1";
        }
        
        $sql .= "
            GROUP BY e.event_id
            ORDER BY e.start_date ASC, e.start_time ASC
        ";
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Search events by query
     * 
     * @param string $query Search query
     * @param int $limit Result limit
     * @return array Matching events
     */
    public function searchEvents($query, $limit = 50) {
        $searchTerm = '%' . $query . '%';
        
        $sql = "
            SELECT event_id, name, description, location, start_date, start_time, event_type, active
            FROM events 
            WHERE (name LIKE ? OR description LIKE ? OR location LIKE ?)
            AND active = 1
            ORDER BY 
                CASE 
                    WHEN name = ? THEN 1
                    WHEN name LIKE ? THEN 2
                    ELSE 3
                END,
                start_date ASC, start_time ASC
            LIMIT ?
        ";
        
        $stmt = $this->query($sql, [
            $searchTerm, $searchTerm, $searchTerm,
            $query, $query . '%',
            $limit
        ]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get recurring events
     * 
     * @param bool $activeOnly Only active events
     * @return array Recurring events
     */
    public function getRecurringEvents($activeOnly = true) {
        $conditions = ['is_recurring' => 1];
        
        if ($activeOnly) {
            $conditions['active'] = 1;
        }
        
        return $this->findWhere($conditions, [
            'orderBy' => 'name ASC'
        ]);
    }
    
    /**
     * Get events by type
     * 
     * @param string $type Event type
     * @param bool $activeOnly Only active events
     * @return array Events of specified type
     */
    public function getEventsByType($type, $activeOnly = true) {
        $conditions = ['event_type' => $type];
        
        if ($activeOnly) {
            $conditions['active'] = 1;
        }
        
        return $this->findWhere($conditions, [
            'orderBy' => 'start_date DESC, start_time DESC'
        ]);
    }
    
    /**
     * Check if event has capacity available
     * 
     * @param int $eventId Event ID
     * @return bool|null True if available, false if full, null if no capacity limit
     */
    public function hasCapacityAvailable($eventId) {
        $sql = "
            SELECT 
                e.capacity,
                COUNT(DISTINCT c.checkin_id) as current_attendance
            FROM events e
            LEFT JOIN checkin c ON e.event_id = c.event_id
            WHERE e.event_id = ?
            GROUP BY e.event_id
        ";
        
        $stmt = $this->query($sql, [$eventId]);
        $result = $stmt->fetch();
        
        if (!$result || $result['capacity'] === null) {
            return null; // No capacity limit
        }
        
        return $result['current_attendance'] < $result['capacity'];
    }
    
    /**
     * Get system-wide event statistics
     * 
     * @return array System statistics
     */
    public function getSystemStats() {
        $sql = "
            SELECT 
                COUNT(*) as total_events,
                COUNT(CASE WHEN active = 1 THEN 1 END) as active_events,
                COUNT(CASE WHEN start_date >= CURDATE() THEN 1 END) as upcoming_events,
                COUNT(CASE WHEN is_recurring = 1 THEN 1 END) as recurring_events,
                COUNT(CASE WHEN capacity IS NOT NULL THEN 1 END) as events_with_capacity,
                AVG(capacity) as avg_capacity
            FROM events
        ";
        
        $stmt = $this->query($sql);
        return $stmt->fetch();
    }
    
    /**
     * Get popular events (by attendance)
     * 
     * @param int $limit Number of events to return
     * @param int $days Number of days to look back
     * @return array Popular events
     */
    public function getPopularEvents($limit = 10, $days = 30) {
        $sql = "
            SELECT 
                e.*,
                COUNT(DISTINCT c.checkin_id) as total_checkins,
                COUNT(DISTINCT c.user_id) as unique_attendees
            FROM events e
            INNER JOIN checkin c ON e.event_id = c.event_id
            WHERE c.checkin_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND e.active = 1
            GROUP BY e.event_id
            ORDER BY total_checkins DESC, unique_attendees DESC
            LIMIT ?
        ";
        
        $stmt = $this->query($sql, [$days, $limit]);
        return $stmt->fetchAll();
    }
}
