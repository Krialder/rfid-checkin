<?php

declare(strict_types=1);

namespace RfidCheckin\Repositories;

use RfidCheckin\Models\Event;
use RfidCheckin\Services\DatabaseService;
use RfidCheckin\Services\LoggingService;
use PDO;
use Exception;

/**
 * Event Repository
 * 
 * Handles all event-related database operations including CRUD operations,
 * event scheduling, check-in tracking, and analytics. Replaces scattered
 * event queries throughout the application.
 * 
 * Features:
 * - Complete event lifecycle management
 * - Event scheduling and recurrence
 * - Check-in tracking and statistics
 * - Event analytics and reporting
 * - Bulk operations support
 * - Advanced filtering and search
 * 
 * @package RfidCheckin\Repositories
 * @version 1.0.0
 * @author Senior Development Team
 */
class EventRepository extends BaseRepository
{
    protected string $table = 'events';
    protected string $primaryKey = 'event_id';
    
    private LoggingService $logger;

    public function __construct()
    {
        parent::__construct();
        $this->logger = LoggingService::getInstance();
    }

    /**
     * Create new event with validation
     * 
     * @param array $data Event data
     * @return int Created event ID
     * @throws Exception If creation fails
     */
    public function create(array $data): int
    {
        $this->validateEventData($data);

        $sql = "
            INSERT INTO {$this->table} (
                name, description, event_date, end_date, location, 
                capacity, registration_deadline, status, created_by,
                allow_self_checkin, require_prereg, is_recurring,
                recurrence_pattern, tags, metadata, created_at
            ) VALUES (
                :name, :description, :event_date, :end_date, :location,
                :capacity, :registration_deadline, :status, :created_by,
                :allow_self_checkin, :require_prereg, :is_recurring,
                :recurrence_pattern, :tags, :metadata, NOW()
            )
        ";

        $params = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'event_date' => $data['event_date'],
            'end_date' => $data['end_date'] ?? null,
            'location' => $data['location'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'registration_deadline' => $data['registration_deadline'] ?? null,
            'status' => $data['status'] ?? 'active',
            'created_by' => $data['created_by'],
            'allow_self_checkin' => $data['allow_self_checkin'] ?? 1,
            'require_prereg' => $data['require_prereg'] ?? 0,
            'is_recurring' => $data['is_recurring'] ?? 0,
            'recurrence_pattern' => $data['recurrence_pattern'] ?? null,
            'tags' => isset($data['tags']) ? json_encode($data['tags']) : null,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
        ];

        $eventId = $this->executeInsert($sql, $params);

        // Handle recurring events
        if (!empty($data['is_recurring']) && !empty($data['recurrence_pattern'])) {
            $this->createRecurringInstances($eventId, $data);
        }

        $this->logger->info('Event created', [
            'event_id' => $eventId,
            'name' => $data['name'],
            'created_by' => $data['created_by']
        ]);

        return $eventId;
    }

    /**
     * Update existing event
     * 
     * @param int $id Event ID
     * @param array $data Updated data
     * @return bool True if successful
     * @throws Exception If update fails
     */
    public function update(int $id, array $data): bool
    {
        $event = $this->find($id);
        if (!$event) {
            throw new Exception("Event not found: {$id}");
        }

        $this->validateEventData($data, $id);

        $sql = "
            UPDATE {$this->table} 
            SET name = :name, description = :description, event_date = :event_date,
                end_date = :end_date, location = :location, capacity = :capacity,
                registration_deadline = :registration_deadline, status = :status,
                allow_self_checkin = :allow_self_checkin, require_prereg = :require_prereg,
                tags = :tags, metadata = :metadata, updated_at = NOW()
            WHERE {$this->primaryKey} = :id
        ";

        $params = [
            'id' => $id,
            'name' => $data['name'] ?? $event['name'],
            'description' => $data['description'] ?? $event['description'],
            'event_date' => $data['event_date'] ?? $event['event_date'],
            'end_date' => $data['end_date'] ?? $event['end_date'],
            'location' => $data['location'] ?? $event['location'],
            'capacity' => $data['capacity'] ?? $event['capacity'],
            'registration_deadline' => $data['registration_deadline'] ?? $event['registration_deadline'],
            'status' => $data['status'] ?? $event['status'],
            'allow_self_checkin' => $data['allow_self_checkin'] ?? $event['allow_self_checkin'],
            'require_prereg' => $data['require_prereg'] ?? $event['require_prereg'],
            'tags' => isset($data['tags']) ? json_encode($data['tags']) : $event['tags'],
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : $event['metadata'],
        ];

        $result = $this->executeUpdate($sql, $params);

        $this->logger->info('Event updated', [
            'event_id' => $id,
            'updated_fields' => array_keys($data)
        ]);

        return $result;
    }

    /**
     * Find event by ID with related data
     * 
     * @param int $id Event ID
     * @param bool $includeStats Include check-in statistics
     * @return array|null Event data
     */
    public function find(int $id, bool $includeStats = false): ?array
    {
        $sql = "
            SELECT e.*, 
                   u.firstname, u.lastname, u.email as creator_email,
                   COUNT(DISTINCT er.user_id) as registered_count,
                   COUNT(DISTINCT al.user_id) as checked_in_count
            FROM {$this->table} e
            LEFT JOIN users u ON e.created_by = u.user_id
            LEFT JOIN event_registrations er ON e.event_id = er.event_id AND er.status = 'confirmed'
            LEFT JOIN access_logs al ON e.event_id = al.event_id
            WHERE e.{$this->primaryKey} = :id
            GROUP BY e.event_id
        ";

        $result = $this->executeQuery($sql, ['id' => $id]);
        
        if (empty($result)) {
            return null;
        }

        $event = $result[0];

        // Parse JSON fields
        $event['tags'] = $event['tags'] ? json_decode($event['tags'], true) : [];
        $event['metadata'] = $event['metadata'] ? json_decode($event['metadata'], true) : [];

        if ($includeStats) {
            $event['statistics'] = $this->getEventStatistics($id);
        }

        return $event;
    }

    /**
     * Get events with filtering and pagination
     * 
     * @param array $filters Filtering options
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array Events with pagination info
     */
    public function findMany(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $where = ['1 = 1'];
        $params = [];

        // Apply filters
        if (!empty($filters['status'])) {
            $where[] = 'e.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'e.event_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'e.event_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['location'])) {
            $where[] = 'e.location LIKE :location';
            $params['location'] = '%' . $filters['location'] . '%';
        }

        if (!empty($filters['created_by'])) {
            $where[] = 'e.created_by = :created_by';
            $params['created_by'] = $filters['created_by'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(e.name LIKE :search OR e.description LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['tags'])) {
            $where[] = 'JSON_CONTAINS(e.tags, :tags)';
            $params['tags'] = json_encode($filters['tags']);
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $limit;

        // Get total count
        $countSql = "
            SELECT COUNT(*) as total
            FROM {$this->table} e
            WHERE {$whereClause}
        ";
        $countResult = $this->executeQuery($countSql, $params);
        $total = $countResult[0]['total'];

        // Get events
        $sql = "
            SELECT e.*, 
                   u.firstname, u.lastname,
                   COUNT(DISTINCT er.user_id) as registered_count,
                   COUNT(DISTINCT al.user_id) as checked_in_count
            FROM {$this->table} e
            LEFT JOIN users u ON e.created_by = u.user_id
            LEFT JOIN event_registrations er ON e.event_id = er.event_id AND er.status = 'confirmed'
            LEFT JOIN access_logs al ON e.event_id = al.event_id
            WHERE {$whereClause}
            GROUP BY e.event_id
            ORDER BY e.event_date DESC
            LIMIT :limit OFFSET :offset
        ";

        $params['limit'] = $limit;
        $params['offset'] = $offset;

        $events = $this->executeQuery($sql, $params);

        // Parse JSON fields for each event
        foreach ($events as &$event) {
            $event['tags'] = $event['tags'] ? json_decode($event['tags'], true) : [];
            $event['metadata'] = $event['metadata'] ? json_decode($event['metadata'], true) : [];
        }

        return [
            'data' => $events,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total / $limit),
                'total_items' => $total,
                'items_per_page' => $limit
            ]
        ];
    }

    /**
     * Get upcoming events
     * 
     * @param int $limit Number of events to return
     * @param int|null $userId Filter by user's registered events
     * @return array Upcoming events
     */
    public function getUpcoming(int $limit = 10, ?int $userId = null): array
    {
        $userJoin = $userId ? 'INNER JOIN event_registrations er ON e.event_id = er.event_id AND er.user_id = :user_id AND er.status = "confirmed"' : '';
        
        $sql = "
            SELECT e.*, 
                   COUNT(DISTINCT er2.user_id) as registered_count,
                   COUNT(DISTINCT al.user_id) as checked_in_count
            FROM {$this->table} e
            {$userJoin}
            LEFT JOIN event_registrations er2 ON e.event_id = er2.event_id AND er2.status = 'confirmed'
            LEFT JOIN access_logs al ON e.event_id = al.event_id
            WHERE e.event_date >= NOW() AND e.status = 'active'
            GROUP BY e.event_id
            ORDER BY e.event_date ASC
            LIMIT :limit
        ";

        $params = ['limit' => $limit];
        if ($userId) {
            $params['user_id'] = $userId;
        }

        $events = $this->executeQuery($sql, $params);

        foreach ($events as &$event) {
            $event['tags'] = $event['tags'] ? json_decode($event['tags'], true) : [];
            $event['metadata'] = $event['metadata'] ? json_decode($event['metadata'], true) : [];
        }

        return $events;
    }

    /**
     * Get event statistics
     * 
     * @param int $eventId Event ID
     * @return array Event statistics
     */
    public function getEventStatistics(int $eventId): array
    {
        $sql = "
            SELECT 
                COUNT(DISTINCT er.user_id) as total_registered,
                COUNT(DISTINCT CASE WHEN er.status = 'confirmed' THEN er.user_id END) as confirmed_registered,
                COUNT(DISTINCT al.user_id) as total_checked_in,
                COUNT(DISTINCT CASE WHEN al.access_time >= DATE(e.event_date) 
                    AND al.access_time < DATE_ADD(e.event_date, INTERVAL 1 DAY) 
                    THEN al.user_id END) as checked_in_on_day,
                MIN(al.access_time) as first_checkin,
                MAX(al.access_time) as last_checkin,
                AVG(CASE WHEN al.access_time IS NOT NULL THEN 1 ELSE 0 END) * 100 as attendance_rate
            FROM events e
            LEFT JOIN event_registrations er ON e.event_id = er.event_id
            LEFT JOIN access_logs al ON e.event_id = al.event_id
            WHERE e.event_id = :event_id
            GROUP BY e.event_id
        ";

        $result = $this->executeQuery($sql, ['event_id' => $eventId]);
        
        if (empty($result)) {
            return [
                'total_registered' => 0,
                'confirmed_registered' => 0,
                'total_checked_in' => 0,
                'checked_in_on_day' => 0,
                'first_checkin' => null,
                'last_checkin' => null,
                'attendance_rate' => 0
            ];
        }

        return $result[0];
    }

    /**
     * Register user for event
     * 
     * @param int $eventId Event ID
     * @param int $userId User ID
     * @param array $registrationData Additional registration data
     * @return bool True if successful
     * @throws Exception If registration fails
     */
    public function registerUser(int $eventId, int $userId, array $registrationData = []): bool
    {
        $event = $this->find($eventId);
        if (!$event) {
            throw new Exception("Event not found: {$eventId}");
        }

        if ($event['status'] !== 'active') {
            throw new Exception("Event is not active for registration");
        }

        if ($event['registration_deadline'] && date('Y-m-d H:i:s') > $event['registration_deadline']) {
            throw new Exception("Registration deadline has passed");
        }

        // Check capacity
        if ($event['capacity'] && $event['registered_count'] >= $event['capacity']) {
            throw new Exception("Event is at full capacity");
        }

        // Check if already registered
        $existingSql = "SELECT registration_id FROM event_registrations WHERE event_id = :event_id AND user_id = :user_id";
        $existing = $this->executeQuery($existingSql, ['event_id' => $eventId, 'user_id' => $userId]);
        
        if (!empty($existing)) {
            throw new Exception("User is already registered for this event");
        }

        $sql = "
            INSERT INTO event_registrations (
                event_id, user_id, registration_date, status, 
                registration_data, created_at
            ) VALUES (
                :event_id, :user_id, NOW(), :status, 
                :registration_data, NOW()
            )
        ";

        $params = [
            'event_id' => $eventId,
            'user_id' => $userId,
            'status' => $registrationData['status'] ?? 'confirmed',
            'registration_data' => !empty($registrationData['data']) ? json_encode($registrationData['data']) : null,
        ];

        $result = $this->executeInsert($sql, $params);

        $this->logger->info('User registered for event', [
            'event_id' => $eventId,
            'user_id' => $userId,
            'registration_id' => $result
        ]);

        return $result > 0;
    }

    /**
     * Get event registrations
     * 
     * @param int $eventId Event ID
     * @param array $filters Additional filters
     * @return array Event registrations
     */
    public function getRegistrations(int $eventId, array $filters = []): array
    {
        $where = ['er.event_id = :event_id'];
        $params = ['event_id' => $eventId];

        if (!empty($filters['status'])) {
            $where[] = 'er.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['has_checked_in'])) {
            if ($filters['has_checked_in']) {
                $where[] = 'al.user_id IS NOT NULL';
            } else {
                $where[] = 'al.user_id IS NULL';
            }
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT er.*, u.firstname, u.lastname, u.email, u.rfid_tag,
                   COUNT(al.log_id) as checkin_count,
                   MAX(al.access_time) as last_checkin
            FROM event_registrations er
            INNER JOIN users u ON er.user_id = u.user_id
            LEFT JOIN access_logs al ON er.event_id = al.event_id AND er.user_id = al.user_id
            WHERE {$whereClause}
            GROUP BY er.registration_id
            ORDER BY er.registration_date ASC
        ";

        $registrations = $this->executeQuery($sql, $params);

        foreach ($registrations as &$registration) {
            $registration['registration_data'] = $registration['registration_data'] 
                ? json_decode($registration['registration_data'], true) 
                : [];
        }

        return $registrations;
    }

    /**
     * Cancel event registration
     * 
     * @param int $eventId Event ID
     * @param int $userId User ID
     * @return bool True if successful
     */
    public function cancelRegistration(int $eventId, int $userId): bool
    {
        $sql = "
            UPDATE event_registrations 
            SET status = 'cancelled', cancelled_at = NOW()
            WHERE event_id = :event_id AND user_id = :user_id AND status != 'cancelled'
        ";

        $result = $this->executeUpdate($sql, [
            'event_id' => $eventId,
            'user_id' => $userId
        ]);

        if ($result) {
            $this->logger->info('Event registration cancelled', [
                'event_id' => $eventId,
                'user_id' => $userId
            ]);
        }

        return $result;
    }

    /**
     * Create recurring event instances
     * 
     * @param int $parentEventId Parent event ID
     * @param array $eventData Event data
     * @return array Created instance IDs
     */
    private function createRecurringInstances(int $parentEventId, array $eventData): array
    {
        $pattern = json_decode($eventData['recurrence_pattern'], true);
        $instanceIds = [];

        if (!$pattern || empty($pattern['type'])) {
            return $instanceIds;
        }

        $startDate = new \DateTime($eventData['event_date']);
        $endDate = isset($pattern['end_date']) ? new \DateTime($pattern['end_date']) : null;
        $maxInstances = $pattern['max_instances'] ?? 52; // Default to 1 year worth
        $interval = $pattern['interval'] ?? 1;

        $instanceCount = 0;
        $currentDate = clone $startDate;

        while ($instanceCount < $maxInstances) {
            // Move to next occurrence
            switch ($pattern['type']) {
                case 'daily':
                    $currentDate->add(new \DateInterval("P{$interval}D"));
                    break;
                case 'weekly':
                    $currentDate->add(new \DateInterval("P{$interval}W"));
                    break;
                case 'monthly':
                    $currentDate->add(new \DateInterval("P{$interval}M"));
                    break;
                case 'yearly':
                    $currentDate->add(new \DateInterval("P{$interval}Y"));
                    break;
                default:
                    break 2; // Exit while loop
            }

            if ($endDate && $currentDate > $endDate) {
                break;
            }

            // Create instance
            $instanceData = $eventData;
            $instanceData['event_date'] = $currentDate->format('Y-m-d H:i:s');
            $instanceData['parent_event_id'] = $parentEventId;
            $instanceData['is_recurring'] = 0; // Instances are not recurring themselves
            $instanceData['recurrence_pattern'] = null;

            if (isset($eventData['end_date'])) {
                $duration = strtotime($eventData['end_date']) - strtotime($eventData['event_date']);
                $instanceData['end_date'] = date('Y-m-d H:i:s', $currentDate->getTimestamp() + $duration);
            }

            $instanceId = $this->create($instanceData);
            $instanceIds[] = $instanceId;
            $instanceCount++;
        }

        return $instanceIds;
    }

    /**
     * Validate event data
     * 
     * @param array $data Event data
     * @param int|null $eventId Event ID for updates
     * @throws Exception If validation fails
     */
    private function validateEventData(array $data, ?int $eventId = null): void
    {
        $required = ['name', 'event_date'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '{$field}' is required");
            }
        }

        // Validate date format
        if (!strtotime($data['event_date'])) {
            throw new Exception("Invalid event date format");
        }

        // Validate end date if provided
        if (!empty($data['end_date'])) {
            if (!strtotime($data['end_date'])) {
                throw new Exception("Invalid end date format");
            }
            if (strtotime($data['end_date']) <= strtotime($data['event_date'])) {
                throw new Exception("End date must be after event date");
            }
        }

        // Validate capacity
        if (isset($data['capacity']) && $data['capacity'] !== null) {
            if (!is_numeric($data['capacity']) || $data['capacity'] < 0) {
                throw new Exception("Capacity must be a positive number");
            }
        }

        // Validate status
        $validStatuses = ['active', 'cancelled', 'completed', 'draft'];
        if (isset($data['status']) && !in_array($data['status'], $validStatuses)) {
            throw new Exception("Invalid status. Must be one of: " . implode(', ', $validStatuses));
        }

        // Check for duplicate event names on same date (excluding current event)
        $sql = "SELECT event_id FROM {$this->table} WHERE name = :name AND DATE(event_date) = DATE(:event_date)";
        $params = ['name' => $data['name'], 'event_date' => $data['event_date']];
        
        if ($eventId) {
            $sql .= " AND event_id != :event_id";
            $params['event_id'] = $eventId;
        }

        $existing = $this->executeQuery($sql, $params);
        if (!empty($existing)) {
            throw new Exception("An event with this name already exists on the same date");
        }
    }
}
