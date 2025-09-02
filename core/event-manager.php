<?php
/**
 * Enhanced Event Management System
 * 
 * Comprehensive event management with support for:
 * - Recurring events (daily, weekly, monthly, yearly)
 * - Holiday integration
 * - Break/pause time management
 * - Event instance generation and management
 * 
 * @author Senior Developer
 * @version 1.0 - Enhanced Event Management
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/user-group-manager.php';

class EventManager {
    
    private $db;
    private $groupManager;
    
    public function __construct() {
        $this->db = getDB();
        $this->groupManager = new UserGroupManager();
    }
    
    /**
     * Create a new event with full recurring and break support
     */
    public function createEvent($eventData, $userId) {
        $this->db->beginTransaction();
        
        try {
            // Validate required fields
            if (empty($eventData['name']) || empty($eventData['start_date'])) {
                throw new Exception('Event name and start date are required');
            }
            
            // Insert main event
            $sql = "INSERT INTO Events (
                name, description, location, event_type, capacity,
                start_date, end_date, start_time, end_time,
                is_recurring, recurrence_type, recurrence_interval, 
                recurrence_days, recurrence_end_date, max_occurrences,
                exclude_holidays, has_breaks, break_schedule,
                require_checkin, allow_manual_checkin, auto_checkout, auto_checkout_minutes,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $eventData['name'],
                $eventData['description'] ?? '',
                $eventData['location'] ?? '',
                $eventData['event_type'] ?? 'general',
                $eventData['capacity'] ?? null,
                $eventData['start_date'],
                $eventData['end_date'] ?? null,
                $eventData['start_time'] ?? null,
                $eventData['end_time'] ?? null,
                $eventData['is_recurring'] ?? false,
                $eventData['recurrence_type'] ?? null,
                $eventData['recurrence_interval'] ?? 1,
                $eventData['recurrence_days'] ? json_encode($eventData['recurrence_days']) : null,
                $eventData['recurrence_end_date'] ?? null,
                $eventData['max_occurrences'] ?? null,
                $eventData['exclude_holidays'] ?? true,
                $eventData['has_breaks'] ?? false,
                $eventData['break_schedule'] ? json_encode($eventData['break_schedule']) : null,
                $eventData['require_checkin'] ?? true,
                $eventData['allow_manual_checkin'] ?? true,
                $eventData['auto_checkout'] ?? false,
                $eventData['auto_checkout_minutes'] ?? 480,
                $userId
            ]);
            
            $eventId = $this->db->lastInsertId();
            
            // Generate event instances for recurring events
            if ($eventData['is_recurring']) {
                $this->generateEventInstances($eventId, $eventData);
            } else {
                // Create single instance for non-recurring events
                $this->createSingleEventInstance($eventId, $eventData);
            }
            
            $this->db->commit();
            
            Utilities::logActivity($userId, 'event_created', "Created event: {$eventData['name']}", [
                'event_id' => $eventId,
                'is_recurring' => $eventData['is_recurring'] ?? false
            ]);
            
            return ['success' => true, 'event_id' => $eventId];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Generate instances for recurring events
     */
    public function generateEventInstances($eventId, $eventData) {
        $startDate = new DateTime($eventData['start_date']);
        $endDate = $eventData['recurrence_end_date'] ? new DateTime($eventData['recurrence_end_date']) : null;
        $maxOccurrences = $eventData['max_occurrences'] ?? null;
        $excludeHolidays = $eventData['exclude_holidays'] ?? true;
        
        $instances = [];
        $occurrenceCount = 0;
        $currentDate = clone $startDate;
        
        // Generate instances for up to 2 years or max occurrences
        $maxDate = (new DateTime())->modify('+2 years');
        if ($endDate && $endDate < $maxDate) {
            $maxDate = $endDate;
        }
        
        while ($currentDate <= $maxDate && (!$maxOccurrences || $occurrenceCount < $maxOccurrences)) {
            if ($this->shouldCreateInstance($currentDate, $eventData)) {
                $instanceDate = $currentDate->format('Y-m-d');
                
                // Check for holiday conflicts
                $holidayInfo = $this->checkHolidayConflict($instanceDate);
                $isHolidayConflict = $excludeHolidays && $holidayInfo['is_holiday'];
                
                if (!$isHolidayConflict || !$excludeHolidays) {
                    $startDatetime = $instanceDate . ' ' . ($eventData['start_time'] ?? '09:00:00');
                    $endDatetime = null;
                    if ($eventData['end_time']) {
                        $endDatetime = $instanceDate . ' ' . $eventData['end_time'];
                    }
                    
                    $instances[] = [
                        'parent_event_id' => $eventId,
                        'instance_date' => $instanceDate,
                        'start_datetime' => $startDatetime,
                        'end_datetime' => $endDatetime,
                        'status' => $isHolidayConflict ? 'cancelled' : 'scheduled',
                        'is_holiday_conflict' => $isHolidayConflict,
                        'holiday_name' => $holidayInfo['holiday_name'] ?? null
                    ];
                    
                    $occurrenceCount++;
                }
            }
            
            $currentDate = $this->getNextOccurrence($currentDate, $eventData);
        }
        
        // Insert all instances
        if (!empty($instances)) {
            $sql = "INSERT INTO EventInstances (
                parent_event_id, instance_date, start_datetime, end_datetime, 
                status, is_holiday_conflict, holiday_name
            ) VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            foreach ($instances as $instance) {
                $stmt->execute([
                    $instance['parent_event_id'],
                    $instance['instance_date'],
                    $instance['start_datetime'],
                    $instance['end_datetime'],
                    $instance['status'],
                    $instance['is_holiday_conflict'],
                    $instance['holiday_name']
                ]);
            }
        }
        
        return count($instances);
    }
    
    /**
     * Create single instance for non-recurring events
     */
    private function createSingleEventInstance($eventId, $eventData) {
        $instanceDate = $eventData['start_date'];
        $startDatetime = $instanceDate . ' ' . ($eventData['start_time'] ?? '09:00:00');
        $endDatetime = null;
        
        if ($eventData['end_time']) {
            $endDate = $eventData['end_date'] ?? $eventData['start_date'];
            $endDatetime = $endDate . ' ' . $eventData['end_time'];
        }
        
        // Check for holiday conflicts
        $holidayInfo = $this->checkHolidayConflict($instanceDate);
        
        $sql = "INSERT INTO EventInstances (
            parent_event_id, instance_date, start_datetime, end_datetime, 
            status, is_holiday_conflict, holiday_name
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $eventId,
            $instanceDate,
            $startDatetime,
            $endDatetime,
            'scheduled',
            $holidayInfo['is_holiday'],
            $holidayInfo['holiday_name'] ?? null
        ]);
    }
    
    /**
     * Check if an instance should be created for a given date
     */
    private function shouldCreateInstance($date, $eventData) {
        $recurrenceType = $eventData['recurrence_type'];
        $recurrenceDays = $eventData['recurrence_days'] ?? [];
        
        switch ($recurrenceType) {
            case 'daily':
                return true;
                
            case 'weekly':
                if (empty($recurrenceDays)) {
                    return true; // Every week on the same day
                }
                $dayOfWeek = $date->format('N'); // 1 (Monday) to 7 (Sunday)
                return in_array($dayOfWeek, $recurrenceDays);
                
            case 'monthly':
                // Same day of month
                return true;
                
            case 'yearly':
                // Same date every year
                return true;
                
            default:
                return false;
        }
    }
    
    /**
     * Get next occurrence date based on recurrence rules
     */
    private function getNextOccurrence($currentDate, $eventData) {
        $interval = $eventData['recurrence_interval'] ?? 1;
        $recurrenceType = $eventData['recurrence_type'];
        
        switch ($recurrenceType) {
            case 'daily':
                return $currentDate->modify("+{$interval} days");
                
            case 'weekly':
                return $currentDate->modify("+{$interval} weeks");
                
            case 'monthly':
                return $currentDate->modify("+{$interval} months");
                
            case 'yearly':
                return $currentDate->modify("+{$interval} years");
                
            default:
                return $currentDate->modify('+1 day');
        }
    }
    
    /**
     * Check if a date conflicts with holidays
     */
    public function checkHolidayConflict($date) {
        $stmt = $this->db->prepare("
            SELECT name, type, state_codes 
            FROM Holidays 
            WHERE date = ? AND is_active = 1
        ");
        $stmt->execute([$date]);
        $holiday = $stmt->fetch();
        
        if ($holiday) {
            return [
                'is_holiday' => true,
                'holiday_name' => $holiday['name'],
                'holiday_type' => $holiday['type'],
                'state_codes' => $holiday['state_codes'] ? json_decode($holiday['state_codes'], true) : null
            ];
        }
        
        return ['is_holiday' => false];
    }
    
    /**
     * Get holidays for a specific year
     */
    public function getHolidays($year) {
        $stmt = $this->db->prepare("
            SELECT * FROM Holidays 
            WHERE year = ? AND is_active = 1 
            ORDER BY date
        ");
        $stmt->execute([$year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update event and regenerate instances if needed
     */
    public function updateEvent($eventId, $eventData, $userId) {
        $this->db->beginTransaction();
        
        try {
            // Get current event data
            $stmt = $this->db->prepare("SELECT * FROM Events WHERE event_id = ?");
            $stmt->execute([$eventId]);
            $currentEvent = $stmt->fetch();
            
            if (!$currentEvent) {
                throw new Exception('Event not found');
            }
            
            // Update main event
            $sql = "UPDATE Events SET 
                name = ?, description = ?, location = ?, event_type = ?, capacity = ?,
                start_date = ?, end_date = ?, start_time = ?, end_time = ?,
                is_recurring = ?, recurrence_type = ?, recurrence_interval = ?, 
                recurrence_days = ?, recurrence_end_date = ?, max_occurrences = ?,
                exclude_holidays = ?, has_breaks = ?, break_schedule = ?,
                require_checkin = ?, allow_manual_checkin = ?, auto_checkout = ?, auto_checkout_minutes = ?,
                updated_at = NOW()
                WHERE event_id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $eventData['name'],
                $eventData['description'] ?? '',
                $eventData['location'] ?? '',
                $eventData['event_type'] ?? 'general',
                $eventData['capacity'] ?? null,
                $eventData['start_date'],
                $eventData['end_date'] ?? null,
                $eventData['start_time'] ?? null,
                $eventData['end_time'] ?? null,
                $eventData['is_recurring'] ?? false,
                $eventData['recurrence_type'] ?? null,
                $eventData['recurrence_interval'] ?? 1,
                $eventData['recurrence_days'] ? json_encode($eventData['recurrence_days']) : null,
                $eventData['recurrence_end_date'] ?? null,
                $eventData['max_occurrences'] ?? null,
                $eventData['exclude_holidays'] ?? true,
                $eventData['has_breaks'] ?? false,
                $eventData['break_schedule'] ? json_encode($eventData['break_schedule']) : null,
                $eventData['require_checkin'] ?? true,
                $eventData['allow_manual_checkin'] ?? true,
                $eventData['auto_checkout'] ?? false,
                $eventData['auto_checkout_minutes'] ?? 480,
                $eventId
            ]);
            
            // If recurrence settings changed, regenerate future instances
            $recurrenceChanged = (
                $currentEvent['is_recurring'] != ($eventData['is_recurring'] ?? false) ||
                $currentEvent['recurrence_type'] != ($eventData['recurrence_type'] ?? null) ||
                $currentEvent['recurrence_interval'] != ($eventData['recurrence_interval'] ?? 1) ||
                $currentEvent['recurrence_days'] != ($eventData['recurrence_days'] ? json_encode($eventData['recurrence_days']) : null)
            );
            
            if ($recurrenceChanged) {
                // Delete future instances (keep past ones with check-ins)
                $stmt = $this->db->prepare("
                    DELETE FROM EventInstances 
                    WHERE parent_event_id = ? 
                    AND instance_date > CURDATE()
                    AND instance_id NOT IN (
                        SELECT DISTINCT instance_id FROM CheckIn WHERE instance_id IS NOT NULL
                    )
                ");
                $stmt->execute([$eventId]);
                
                // Generate new instances if recurring
                if ($eventData['is_recurring']) {
                    $this->generateEventInstances($eventId, $eventData);
                }
            }
            
            $this->db->commit();
            
            Utilities::logActivity($userId, 'event_updated', "Updated event: {$eventData['name']}", [
                'event_id' => $eventId,
                'recurrence_changed' => $recurrenceChanged
            ]);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Get events with instances for a date range
     */
    public function getEventsWithInstances($startDate, $endDate, $filters = []) {
        $where = ['e.active = 1'];
        $params = [];
        
        // Date range filter
        $where[] = "(
            (e.is_recurring = 0 AND e.start_date BETWEEN ? AND ?) OR
            (e.is_recurring = 1 AND ei.instance_date BETWEEN ? AND ?)
        )";
        $params = array_merge($params, [$startDate, $endDate, $startDate, $endDate]);
        
        // Additional filters
        if (!empty($filters['event_type'])) {
            $where[] = "e.event_type = ?";
            $params[] = $filters['event_type'];
        }
        
        if (!empty($filters['location'])) {
            $where[] = "e.location LIKE ?";
            $params[] = "%{$filters['location']}%";
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "
            SELECT 
                e.*,
                ei.instance_id,
                ei.instance_date,
                ei.start_datetime,
                ei.end_datetime,
                ei.status as instance_status,
                ei.is_holiday_conflict,
                ei.holiday_name,
                CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as created_by_name,
                (SELECT COUNT(*) FROM CheckIn c WHERE c.event_id = e.event_id AND c.instance_id = ei.instance_id) as checkin_count
            FROM Events e
            LEFT JOIN EventInstances ei ON e.event_id = ei.parent_event_id
            LEFT JOIN Users u ON e.created_by = u.user_id
            WHERE $whereClause
            ORDER BY COALESCE(ei.start_datetime, CONCAT(e.start_date, ' ', COALESCE(e.start_time, '00:00:00')))
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Process break check-ins for an event
     */
    public function processBreakCheckin($checkinId, $breakType, $timestamp = null) {
        if (!$timestamp) {
            $timestamp = date('Y-m-d H:i:s');
        }
        
        $stmt = $this->db->prepare("SELECT break_checkins FROM CheckIn WHERE checkin_id = ?");
        $stmt->execute([$checkinId]);
        $row = $stmt->fetch();
        
        if (!$row) {
            throw new Exception('Check-in not found');
        }
        
        $breakCheckins = $row['break_checkins'] ? json_decode($row['break_checkins'], true) : [];
        
        // Add break check-in/out record
        $breakCheckins[] = [
            'type' => $breakType, // 'break_start' or 'break_end'
            'timestamp' => $timestamp,
            'break_name' => $breakType === 'break_start' ? 'Break Start' : 'Break End'
        ];
        
        // Calculate total break time
        $totalBreakMinutes = $this->calculateTotalBreakTime($breakCheckins);
        
        $stmt = $this->db->prepare("
            UPDATE CheckIn 
            SET break_checkins = ?, total_break_minutes = ? 
            WHERE checkin_id = ?
        ");
        $stmt->execute([
            json_encode($breakCheckins),
            $totalBreakMinutes,
            $checkinId
        ]);
        
        return $totalBreakMinutes;
    }
    
    /**
     * Calculate total break time from break check-ins
     */
    private function calculateTotalBreakTime($breakCheckins) {
        $totalMinutes = 0;
        $breakStart = null;
        
        foreach ($breakCheckins as $breakRecord) {
            if ($breakRecord['type'] === 'break_start') {
                $breakStart = new DateTime($breakRecord['timestamp']);
            } elseif ($breakRecord['type'] === 'break_end' && $breakStart) {
                $breakEnd = new DateTime($breakRecord['timestamp']);
                $diff = $breakStart->diff($breakEnd);
                $totalMinutes += ($diff->h * 60) + $diff->i;
                $breakStart = null;
            }
        }
        
        return $totalMinutes;
    }
    
    /**
     * Assign user groups to an event
     * This ensures users are only counted once even if in multiple assigned groups
     */
    public function assignGroupsToEvent($eventId, $groupIds, $userId) {
        return $this->groupManager->assignGroupsToEvent($eventId, $groupIds, $userId);
    }
    
    /**
     * Get unique users assigned to an event through groups
     * Key feature: Deduplicates users who are in multiple groups
     */
    public function getEventParticipants($eventId) {
        return $this->groupManager->getUniqueUsersForEvent($eventId);
    }
    
    /**
     * Get groups assigned to an event
     */
    public function getEventGroups($eventId) {
        try {
            $sql = "SELECT ug.group_id, ug.group_name, ug.description, ug.group_type,
                           ega.assigned_at, ega.assigned_by,
                           CONCAT(u.first_name, ' ', u.last_name) as assigned_by_name,
                           COUNT(ugm.user_id) as member_count
                    FROM UserGroups ug
                    INNER JOIN EventGroupAssignments ega ON ug.group_id = ega.group_id
                    LEFT JOIN Users u ON ega.assigned_by = u.user_id
                    LEFT JOIN UserGroupMemberships ugm ON ug.group_id = ugm.group_id AND ugm.is_active = TRUE
                    WHERE ega.event_id = ? AND ega.is_active = TRUE
                    GROUP BY ug.group_id
                    ORDER BY ug.group_name";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$eventId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log('Error getting event groups: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get event statistics including unique participant count
     */
    public function getEventStatistics($eventId) {
        try {
            $stats = [];
            
            // Get unique participants (deduplicated across groups)
            $participants = $this->getEventParticipants($eventId);
            $stats['unique_participants'] = count($participants);
            
            // Get total groups assigned
            $groups = $this->getEventGroups($eventId);
            $stats['assigned_groups'] = count($groups);
            
            // Get total memberships (before deduplication)
            $sql = "SELECT COUNT(ugm.user_id) as total_memberships
                    FROM UserGroupMemberships ugm
                    INNER JOIN EventGroupAssignments ega ON ugm.group_id = ega.group_id
                    WHERE ega.event_id = ? 
                      AND ugm.is_active = TRUE 
                      AND ega.is_active = TRUE";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$eventId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_memberships'] = $result['total_memberships'] ?? 0;
            
            // Calculate deduplication savings
            $stats['deduplication_savings'] = $stats['total_memberships'] - $stats['unique_participants'];
            
            // Get check-in statistics
            $sql = "SELECT COUNT(*) as checkin_count
                    FROM CheckIn ci
                    INNER JOIN UserGroupMemberships ugm ON ci.user_id = ugm.user_id
                    INNER JOIN EventGroupAssignments ega ON ugm.group_id = ega.group_id
                    WHERE ega.event_id = ? 
                      AND ugm.is_active = TRUE 
                      AND ega.is_active = TRUE";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$eventId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['checkin_count'] = $result['checkin_count'] ?? 0;
            
            $stats['attendance_rate'] = $stats['unique_participants'] > 0 
                ? round(($stats['checkin_count'] / $stats['unique_participants']) * 100, 1) 
                : 0;
            
            return $stats;
            
        } catch (Exception $e) {
            error_log('Error getting event statistics: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all available groups for event assignment
     */
    public function getAvailableGroups() {
        return $this->groupManager->getAllGroupsWithStats();
    }
    
    /**
     * Check if a user can access an event (through group membership)
     */
    public function canUserAccessEvent($userId, $eventId) {
        try {
            $sql = "SELECT COUNT(*) as can_access
                    FROM UserGroupMemberships ugm
                    INNER JOIN EventGroupAssignments ega ON ugm.group_id = ega.group_id
                    WHERE ugm.user_id = ? 
                      AND ega.event_id = ? 
                      AND ugm.is_active = TRUE 
                      AND ega.is_active = TRUE";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId, $eventId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['can_access'] > 0;
            
        } catch (Exception $e) {
            error_log('Error checking user event access: ' . $e->getMessage());
            return false;
        }
    }
}
