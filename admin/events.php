<?php
/**
 * Enhanced Event Management System
 * 
 * Complete event management with support for:
 * - Recurring events (daily, weekly, monthly, yearly)
 * - Holiday integration and conflict detection
 * - Break/pause time management with check-in tracking
 * - Event instance generation and management
 * 
 * @author Senior Developer (Fixed intern's incomplete implementation)
 * @version 2.0 - Complete Enhanced Event Management
 */

require_once '../core/config.php';
require_once '../core/database.php';
require_once '../core/auth.php';
require_once '../core/utils.php';
require_once '../core/event-manager.php';
require_once '../core/user-group-manager.php';
require_once '../core/holidays.php';

// Check if user is admin
if (!Auth::isLoggedIn() || !Auth::hasRole(['admin'])) {
    http_response_code(403);
    header('Location: ../auth/login.php');
    exit;
}

$db = getDB();
$user = Auth::getCurrentUser();
$eventManager = new EventManager();
$holidayManager = new HolidayManager();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_event':
                $result = createEvent($_POST, $eventManager, $user['user_id']);
                echo json_encode($result);
                break;
                
            case 'update_event':
                $result = updateEvent($_POST, $eventManager, $user['user_id']);
                echo json_encode($result);
                break;
                
            case 'delete_event':
                $result = deleteEvent($_POST['event_id'], $db);
                echo json_encode($result);
                break;
                
            case 'load_events':
                $result = loadEvents($_POST, $db);
                echo json_encode($result);
                break;
                
            case 'load_event_details':
                $result = loadEventDetails($_POST['event_id'], $db);
                echo json_encode($result);
                break;
                
            case 'generate_instances':
                $result = generateEventInstances($_POST, $eventManager);
                echo json_encode($result);
                break;
                
            case 'load_users':
                $result = loadUsers($_POST, $db);
                echo json_encode($result);
                break;
                
            case 'check_holiday_conflicts':
                $result = checkHolidayConflicts($_POST, $holidayManager);
                echo json_encode($result);
                break;
                
            case 'manage_holidays':
                $result = manageHolidays($_POST, $holidayManager);
                echo json_encode($result);
                break;
                
            case 'load_break_templates':
                $result = loadBreakTemplates($db);
                echo json_encode($result);
                break;
                
            case 'load_groups':
                $result = loadGroups($db);
                echo json_encode($result);
                break;
                
            case 'calculate_unique_participants':
                $result = calculateUniqueParticipants($_POST, $eventManager);
                echo json_encode($result);
                break;
                
            case 'load_event_groups':
                $result = loadEventGroups($_POST['event_id'], $eventManager);
                echo json_encode($result);
                break;
                
            case 'load_event_stats':
                $result = loadEventStats($db);
                echo json_encode($result);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log('Enhanced Event Management Error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Enhanced event management functions
function createEvent($data, $eventManager, $userId) {
    try {
        // Prepare event data with proper structure
        $eventData = [
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? '',
            'location' => $data['location'] ?? '',
            'event_type' => $data['event_type'] ?? 'general',
            'capacity' => !empty($data['capacity']) ? intval($data['capacity']) : null,
            'start_date' => $data['start_date'] ?? '',
            'end_date' => $data['end_date'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            
            // Recurring event settings
            'is_recurring' => !empty($data['is_recurring']) || ($data['recurrence_type'] ?? 'one_time') !== 'one_time',
            'recurrence_type' => $data['recurrence_type'] === 'one_time' ? null : ($data['recurrence_type'] ?? null),
            'recurrence_interval' => intval($data['recurrence_interval'] ?? 1),
            'recurrence_days' => !empty($data['recurrence_days']) ? $data['recurrence_days'] : null,
            'recurrence_end_date' => $data['recurrence_end_date'] ?? null,
            'max_occurrences' => !empty($data['max_occurrences']) ? intval($data['max_occurrences']) : null,
            'exclude_holidays' => !empty($data['exclude_holidays']),
            
            // Break/pause settings
            'has_breaks' => !empty($data['has_breaks']),
            'break_schedule' => !empty($data['break_schedule']) ? $data['break_schedule'] : null,
            
            // General settings
            'require_checkin' => !isset($data['require_checkin']) || !empty($data['require_checkin']),
            'allow_manual_checkin' => !isset($data['allow_manual_checkin']) || !empty($data['allow_manual_checkin']),
            'auto_checkout' => !empty($data['auto_checkout']),
            'auto_checkout_minutes' => intval($data['auto_checkout_minutes'] ?? 480)
        ];
        
        $result = $eventManager->createEvent($eventData, $userId);
        
        if ($result['success']) {
            // Assign groups to the event if provided
            if (!empty($data['assigned_groups']) && is_array($data['assigned_groups'])) {
                $groupResult = $eventManager->assignGroupsToEvent(
                    $result['event_id'], 
                    $data['assigned_groups'], 
                    $userId
                );
                
                if (!$groupResult['success']) {
                    error_log('Failed to assign groups to event: ' . $groupResult['error']);
                }
            }
            
            return [
                'success' => true, 
                'event_id' => $result['event_id'],
                'message' => 'Event created successfully with all instances generated'
            ];
        }
        
        return $result;
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function updateEvent($data, $eventManager, $userId) {
    try {
        $eventId = intval($data['event_id']);
        
        // Prepare event data (same structure as create)
        $eventData = [
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? '',
            'location' => $data['location'] ?? '',
            'event_type' => $data['event_type'] ?? 'general',
            'capacity' => !empty($data['capacity']) ? intval($data['capacity']) : null,
            'start_date' => $data['start_date'] ?? '',
            'end_date' => $data['end_date'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            
            'is_recurring' => !empty($data['is_recurring']) || ($data['recurrence_type'] ?? 'one_time') !== 'one_time',
            'recurrence_type' => $data['recurrence_type'] === 'one_time' ? null : ($data['recurrence_type'] ?? null),
            'recurrence_interval' => intval($data['recurrence_interval'] ?? 1),
            'recurrence_days' => !empty($data['recurrence_days']) ? $data['recurrence_days'] : null,
            'recurrence_end_date' => $data['recurrence_end_date'] ?? null,
            'max_occurrences' => !empty($data['max_occurrences']) ? intval($data['max_occurrences']) : null,
            'exclude_holidays' => !empty($data['exclude_holidays']),
            
            'has_breaks' => !empty($data['has_breaks']),
            'break_schedule' => !empty($data['break_schedule']) ? $data['break_schedule'] : null,
            
            'require_checkin' => !isset($data['require_checkin']) || !empty($data['require_checkin']),
            'allow_manual_checkin' => !isset($data['allow_manual_checkin']) || !empty($data['allow_manual_checkin']),
            'auto_checkout' => !empty($data['auto_checkout']),
            'auto_checkout_minutes' => intval($data['auto_checkout_minutes'] ?? 480)
        ];
        
        $result = $eventManager->updateEvent($eventId, $eventData, $userId);
        
        if ($result['success']) {
            // Update group assignments if provided
            if (!empty($data['assigned_groups']) && is_array($data['assigned_groups'])) {
                $groupResult = $eventManager->assignGroupsToEvent(
                    $eventId, 
                    $data['assigned_groups'], 
                    $userId
                );
                
                if (!$groupResult['success']) {
                    error_log('Failed to update group assignments: ' . $groupResult['error']);
                }
            }
            
            return ['success' => true, 'message' => 'Event updated successfully'];
        }
        
        return $result;
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function deleteEvent($eventId, $db) {
    $db->beginTransaction();
    
    try {
        // Check if event has any check-ins
        $stmt = $db->prepare("SELECT COUNT(*) FROM checkin WHERE event_id = ?");
        $stmt->execute([$eventId]);
        $checkinCount = $stmt->fetchColumn();
        
        if ($checkinCount > 0) {
            // Soft delete - mark as inactive
            $stmt = $db->prepare("UPDATE events SET active = FALSE WHERE event_id = ?");
            $stmt->execute([$eventId]);
            $message = 'Event deactivated (has existing check-ins)';
        } else {
            // Delete event instances first
            $stmt = $db->prepare("DELETE FROM eventinstances WHERE parent_event_id = ?");
            $stmt->execute([$eventId]);
            
            // Delete event registrations
            $stmt = $db->prepare("DELETE FROM eventregistration WHERE event_id = ?");
            $stmt->execute([$eventId]);
            
            // Delete main event
            $stmt = $db->prepare("DELETE FROM events WHERE event_id = ?");
            $stmt->execute([$eventId]);
            $message = 'Event deleted successfully';
        }
        
        $db->commit();
        
        return ['success' => true, 'message' => $message];
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function loadEvents($data, $db) {
    $page = max(1, intval($data['page'] ?? 1));
    $limit = max(10, min(100, intval($data['limit'] ?? 25)));
    $offset = ($page - 1) * $limit;
    $search = trim($data['search'] ?? '');
    $status = $data['status'] ?? '';
    
    $where = ['e.active = TRUE'];
    $params = [];
    
    if ($search) {
        $where[] = "(e.name LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    }
    
    if ($status === 'upcoming') {
        $where[] = "(
            (e.is_recurring = 0 AND e.start_date > CURDATE()) OR
            (e.is_recurring = 1 AND (e.recurrence_end_date IS NULL OR e.recurrence_end_date > CURDATE()))
        )";
    } elseif ($status === 'ongoing') {
        $where[] = "(
            (e.is_recurring = 0 AND e.start_date <= CURDATE() AND (e.end_date IS NULL OR e.end_date >= CURDATE())) OR
            (e.is_recurring = 1 AND e.start_date <= CURDATE() AND (e.recurrence_end_date IS NULL OR e.recurrence_end_date >= CURDATE()))
        )";
    } elseif ($status === 'past') {
        $where[] = "(
            (e.is_recurring = 0 AND e.end_date < CURDATE()) OR
            (e.is_recurring = 1 AND e.recurrence_end_date < CURDATE())
        )";
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Get total count
    $stmt = $db->prepare("SELECT COUNT(*) FROM events e WHERE $whereClause");
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    // Get events
    $stmt = $db->prepare("
        SELECT 
            e.*,
            CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as created_by_name,
            (SELECT COUNT(*) FROM eventregistration er WHERE er.event_id = e.event_id AND er.status = 'registered') as registered_count,
            CASE 
                WHEN e.is_recurring = 1 THEN 
                    (SELECT COUNT(*) FROM eventinstances ei WHERE ei.parent_event_id = e.event_id AND ei.instance_date > CURDATE())
                ELSE 1
            END as future_instances,
            CASE 
                WHEN e.is_recurring = 1 THEN 
                    (SELECT COUNT(*) FROM eventinstances ei WHERE ei.parent_event_id = e.event_id AND ei.is_holiday_conflict = 1)
                ELSE 0
            END as holiday_conflicts
        FROM events e
        LEFT JOIN users u ON e.created_by = u.user_id
        WHERE $whereClause
        ORDER BY e.start_date DESC, e.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $events = $stmt->fetchAll();
    
    return [
        'success' => true,
        'events' => $events,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_items' => $total,
            'items_per_page' => $limit
        ]
    ];
}

function loadEventDetails($eventId, $db) {
    // Get event details
    $stmt = $db->prepare("
        SELECT e.*, 
               CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as created_by_name
        FROM events e
        LEFT JOIN users u ON e.created_by = u.user_id
        WHERE e.event_id = ?
    ");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    
    if (!$event) {
        return ['success' => false, 'error' => 'Event not found'];
    }
    
    // Get participants from eventregistration table
    $stmt = $db->prepare("
        SELECT 
            er.*,
            CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as participant_name,
            'user' as participant_type,
            u.email,
            u.department
        FROM eventregistration er
        LEFT JOIN users u ON er.user_id = u.user_id
        WHERE er.event_id = ? AND er.status = 'registered'
        ORDER BY participant_name
    ");
    $stmt->execute([$eventId]);
    $participants = $stmt->fetchAll();
    
    // Get event instances
    $stmt = $db->prepare("
        SELECT * FROM eventinstances 
        WHERE parent_event_id = ? 
        ORDER BY instance_date ASC
        LIMIT 50
    ");
    $stmt->execute([$eventId]);
    $instances = $stmt->fetchAll();
    
    return [
        'success' => true,
        'event' => $event,
        'participants' => $participants,
        'instances' => $instances
    ];
}

function generateEventInstances($data, $eventManager) {
    try {
        $eventId = intval($data['event_id']);
        $regenerate = !empty($data['regenerate']);
        
        if ($regenerate) {
            // Get event data and regenerate instances
            $stmt = $eventManager->db->prepare("SELECT * FROM events WHERE event_id = ?");
            $stmt->execute([$eventId]);
            $event = $stmt->fetch();
            
            if (!$event || !$event['is_recurring']) {
                return ['success' => false, 'error' => 'Event not found or not recurring'];
            }
            
            // Delete future instances without check-ins
            $stmt = $eventManager->db->prepare("
                DELETE FROM eventinstances 
                WHERE parent_event_id = ? 
                AND instance_date > CURDATE()
                AND instance_id NOT IN (
                    SELECT DISTINCT instance_id FROM checkin WHERE instance_id IS NOT NULL
                )
            ");
            $stmt->execute([$eventId]);
            
            // Convert event array to proper format and regenerate
            $eventData = [
                'start_date' => $event['start_date'],
                'end_date' => $event['end_date'],
                'start_time' => $event['start_time'],
                'end_time' => $event['end_time'],
                'is_recurring' => $event['is_recurring'],
                'recurrence_type' => $event['recurrence_type'],
                'recurrence_interval' => $event['recurrence_interval'],
                'recurrence_days' => $event['recurrence_days'] ? json_decode($event['recurrence_days'], true) : null,
                'recurrence_end_date' => $event['recurrence_end_date'],
                'max_occurrences' => $event['max_occurrences'],
                'exclude_holidays' => $event['exclude_holidays']
            ];
            
            $count = $eventManager->generateEventInstances($eventId, $eventData);
            
            return [
                'success' => true, 
                'message' => "Generated $count event instances",
                'instances_generated' => $count
            ];
        }
        
        return ['success' => false, 'error' => 'Invalid request'];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function loadUsers($data, $db) {
    $search = trim($data['search'] ?? '');
    $limit = min(50, intval($data['limit'] ?? 20));
    
    $where = ['u.is_active = TRUE'];
    $params = [];
    
    if ($search) {
        $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    $whereClause = implode(' AND ', $where);
    
    $stmt = $db->prepare("
        SELECT u.user_id, u.username, u.email,
               CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as name,
               u.department, u.position
        FROM users u
        WHERE $whereClause
        ORDER BY u.first_name, u.last_name
        LIMIT ?
    ");
    
    $params[] = $limit;
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    return ['success' => true, 'users' => $users];
}

function checkHolidayConflicts($data, $holidayManager) {
    try {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? $startDate;
        $stateCode = $data['state_code'] ?? null;
        
        if (empty($startDate)) {
            return ['success' => false, 'error' => 'Start date required'];
        }
        
        $holidays = $holidayManager->getHolidaysInRange($startDate, $endDate, $stateCode);
        
        return [
            'success' => true,
            'holidays' => $holidays,
            'has_conflicts' => !empty($holidays)
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function manageHolidays($data, $holidayManager) {
    try {
        $action = $data['holiday_action'] ?? '';
        
        switch ($action) {
            case 'generate_year':
                $year = intval($data['year'] ?? date('Y'));
                $count = $holidayManager->generateHolidaysForYear($year);
                return ['success' => true, 'message' => "Generated $count holidays for $year"];
                
            case 'add_custom':
                $id = $holidayManager->addCustomHoliday(
                    $data['name'],
                    $data['date'],
                    $data['description'] ?? '',
                    $data['state_code'] ?? null
                );
                return ['success' => true, 'message' => 'Custom holiday added', 'holiday_id' => $id];
                
            case 'get_holidays':
                $year = intval($data['year'] ?? date('Y'));
                $stateCode = $data['state_code'] ?? null;
                $holidays = $holidayManager->getHolidaysForYear($year, $stateCode);
                return ['success' => true, 'holidays' => $holidays];
                
            default:
                return ['success' => false, 'error' => 'Invalid holiday action'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function loadBreakTemplates($db) {
    // Common break templates for German work environments
    $templates = [
        [
            'name' => 'Standard Office Breaks',
            'breaks' => [
                ['name' => 'Morning Coffee', 'start' => '10:00', 'end' => '10:15', 'duration' => 15],
                ['name' => 'Lunch Break', 'start' => '12:00', 'end' => '13:00', 'duration' => 60],
                ['name' => 'Afternoon Coffee', 'start' => '15:00', 'end' => '15:15', 'duration' => 15]
            ]
        ],
        [
            'name' => 'Half-Day Workshop',
            'breaks' => [
                ['name' => 'Coffee Break', 'start' => '10:30', 'end' => '10:45', 'duration' => 15]
            ]
        ],
        [
            'name' => 'Full-Day Conference',
            'breaks' => [
                ['name' => 'Morning Coffee', 'start' => '10:00', 'end' => '10:30', 'duration' => 30],
                ['name' => 'Lunch Break', 'start' => '12:30', 'end' => '13:30', 'duration' => 60],
                ['name' => 'Afternoon Coffee', 'start' => '15:30', 'end' => '15:45', 'duration' => 15]
            ]
        ],
        [
            'name' => 'Training Session',
            'breaks' => [
                ['name' => 'Break 1', 'start' => '10:15', 'end' => '10:30', 'duration' => 15],
                ['name' => 'Lunch', 'start' => '12:00', 'end' => '13:00', 'duration' => 60],
                ['name' => 'Break 2', 'start' => '15:00', 'end' => '15:15', 'duration' => 15]
            ]
        ]
    ];
    
    return ['success' => true, 'templates' => $templates];
}

function loadGroups($db) {
    try {
        $stmt = $db->query("
            SELECT ug.group_id, ug.group_name, ug.description, ug.group_type,
                   COUNT(ugm.user_id) as member_count,
                   CONCAT(creator.first_name, ' ', creator.last_name) as created_by_name
            FROM usergroups ug
            LEFT JOIN usergroupmemberships ugm ON ug.group_id = ugm.group_id AND ugm.is_active = TRUE
            LEFT JOIN users creator ON ug.created_by = creator.user_id
            WHERE ug.is_active = TRUE
            GROUP BY ug.group_id
            ORDER BY ug.group_name
        ");
        
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['success' => true, 'groups' => $groups];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function calculateUniqueParticipants($data, $eventManager) {
    try {
        $groupIds = explode(',', $data['group_ids']);
        $groupIds = array_filter(array_map('trim', $groupIds), 'is_numeric');
        
        if (empty($groupIds)) {
            return ['success' => true, 'unique_count' => 0, 'total_memberships' => 0, 'deduplication_savings' => 0];
        }
        
        $db = $eventManager->db;
        
        // Get total memberships (before deduplication)
        $placeholders = str_repeat('?,', count($groupIds) - 1) . '?';
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_memberships
            FROM usergroupmemberships ugm
            WHERE ugm.group_id IN ($placeholders) AND ugm.is_active = TRUE
        ");
        $stmt->execute($groupIds);
        $totalMemberships = $stmt->fetchColumn();
        
        // Get unique users (deduplicated)
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT ugm.user_id) as unique_count
            FROM usergroupmemberships ugm
            INNER JOIN users u ON ugm.user_id = u.user_id
            WHERE ugm.group_id IN ($placeholders) 
              AND ugm.is_active = TRUE 
              AND u.is_active = TRUE
        ");
        $stmt->execute($groupIds);
        $uniqueCount = $stmt->fetchColumn();
        
        $deduplicationSavings = $totalMemberships - $uniqueCount;
        
        return [
            'success' => true,
            'unique_count' => $uniqueCount,
            'total_memberships' => $totalMemberships,
            'deduplication_savings' => $deduplicationSavings
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function loadEventGroups($eventId, $eventManager) {
    try {
        $groups = $eventManager->getEventGroups($eventId);
        return ['success' => true, 'groups' => $groups];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function loadEventStats($db) {
    try {
        $stats = [];
        
        // Total events
        $stmt = $db->query("SELECT COUNT(*) FROM events WHERE active = TRUE");
        $stats['total'] = $stmt->fetchColumn();
        
        // Active events (current or future)
        $stmt = $db->query("
            SELECT COUNT(*) FROM events 
            WHERE active = TRUE 
            AND (
                (is_recurring = 0 AND start_date >= CURDATE()) OR
                (is_recurring = 1 AND (recurrence_end_date IS NULL OR recurrence_end_date >= CURDATE()))
            )
        ");
        $stats['active'] = $stmt->fetchColumn();
        
        // Recurring events
        $stmt = $db->query("SELECT COUNT(*) FROM events WHERE active = TRUE AND is_recurring = TRUE");
        $stats['recurring'] = $stmt->fetchColumn();
        
        // Upcoming instances
        $stmt = $db->query("
            SELECT COUNT(*) FROM eventinstances 
            WHERE instance_date > CURDATE() AND status = 'scheduled'
        ");
        $stats['upcoming_instances'] = $stmt->fetchColumn();
        
        return ['success' => true, 'stats' => $stats];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Load initial data for the page
$currentYear = date('Y');
$nextYear = $currentYear + 1;

// Get holiday statistics
try {
    $holidayStats = $holidayManager->getHolidayStats($currentYear);
} catch (Exception $e) {
    $holidayStats = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Event Management - RFID Check-in System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <link rel="stylesheet" href="../assets/css/events.css">
    <link rel="stylesheet" href="../assets/css/modal.css">
    <link rel="stylesheet" href="../assets/css/admin-tools.css">
    <style>
        .event-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .event-form-full {
            grid-column: 1 / -1;
        }
        
        .recurrence-options {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-top: 1rem;
        }
        
        .weekday-selector {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .weekday-btn {
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .weekday-btn.active {
            background: var(--primary-color);
            color: white;
        }
        
        .participant-selector {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-top: 1rem;
        }
        
        .participant-search {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .participant-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
        }
        
        .participant-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .participant-item:last-child {
            border-bottom: none;
        }
        
        .pause-schedule {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-top: 1rem;
        }
        
        .pause-item {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .pause-item input {
            flex: 1;
        }
        
        .event-instances-preview {
            margin-top: 1rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
        }
        
        .instance-item {
            display: flex;
            justify-content: space-between;
            padding: 0.25rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .instance-item:last-child {
            border-bottom: none;
        }
        
        .holiday-indicator {
            color: var(--warning-color);
            font-weight: bold;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--bg-primary);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            display: block;
        }
        
        .stat-label {
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }
        
        /* Group Assignment Styles */
        .selected-groups-list {
            min-height: 100px;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            background: var(--bg-secondary);
        }
        
        .group-tag {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            margin: 0.125rem;
            background: var(--primary-color);
            color: white;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
        }
        
        .group-tag .remove {
            margin-left: 0.5rem;
            cursor: pointer;
            opacity: 0.8;
        }
        
        .group-tag .remove:hover {
            opacity: 1;
        }
        
        .participant-count {
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-sm);
            text-align: center;
        }
        
        .participant-count .count {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .deduplication-info {
            display: block;
            margin-top: 0.5rem;
            color: var(--text-muted);
            font-size: 0.75rem;
        }
        
        #assignedGroups {
            height: 150px;
        }
        
        #assignedGroups option {
            padding: 0.5rem;
        }
        
        #assignedGroups option:hover {
            background: var(--primary-color);
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <h1>🎯 Enhanced Event Management</h1>
            <p class="subtitle">Manage events with groups, recurring schedules, and holiday integration</p>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number" id="totalEvents">-</span>
                <div class="stat-label">Total Events</div>
            </div>
            <div class="stat-card">
                <span class="stat-number" id="activeEvents">-</span>
                <div class="stat-label">Active Events</div>
            </div>
            <div class="stat-card">
                <span class="stat-number" id="recurringEvents">-</span>
                <div class="stat-label">Recurring Events</div>
            </div>
            <div class="stat-card">
                <span class="stat-number" id="upcomingInstances">-</span>
                <div class="stat-label">Upcoming Instances</div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons" style="margin-bottom: 2rem;">
            <button class="btn btn-primary" onclick="showCreateEventModal()">
                ➕ Create Event
            </button>
            <button class="btn btn-secondary" onclick="showGroupManagementModal()">
                👥 Manage Groups
            </button>
            <button class="btn btn-secondary" onclick="showHolidayManagementModal()">
                🏖️ Manage Holidays
            </button>
            <button class="btn btn-secondary" onclick="generateInstancesModal()">
                📅 Generate Instances
            </button>
        </div>

        <!-- Filters -->
        <div class="filters-section card" style="margin-bottom: 2rem;">
            <div class="filters-row">
                <div class="filter-group">
                    <input type="text" id="searchInput" placeholder="Search events..." class="form-control">
                </div>
                <div class="filter-group">
                    <select id="statusFilter" class="form-control">
                        <option value="">All Events</option>
                        <option value="upcoming">Upcoming</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="past">Past</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button class="btn btn-secondary" onclick="loadEvents()">🔍 Search</button>
                </div>
            </div>
        </div>

        <!-- Events Table -->
        <div class="card">
            <div class="card-header">
                <h3>Events Overview</h3>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Type</th>
                            <th>Schedule</th>
                            <th>Participants</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="eventsTableBody">
                        <tr>
                            <td colspan="6" class="text-center">
                                <div class="loading">Loading events...</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination-container" id="paginationContainer"></div>
        </div>
    </div>

    <!-- Create/Edit Event Modal -->
    <div id="eventModal" class="modal">
        <div class="modal-content large">
            <div class="modal-header">
                <h3 id="eventModalTitle">Create Event</h3>
                <span class="close" onclick="closeModal('eventModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="eventForm">
                    <input type="hidden" id="eventId" name="event_id">
                    
                    <!-- Basic Information -->
                    <div class="form-section">
                        <h4>Basic Information</h4>
                        <div class="event-form-grid">
                            <div class="form-group">
                                <label for="eventName">Event Name *</label>
                                <input type="text" id="eventName" name="name" required class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="eventType">Event Type</label>
                                <select id="eventType" name="event_type" class="form-control">
                                    <option value="general">General</option>
                                    <option value="training">Training</option>
                                    <option value="meeting">Meeting</option>
                                    <option value="conference">Conference</option>
                                    <option value="workshop">Workshop</option>
                                    <option value="social">Social</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group event-form-full">
                            <label for="eventDescription">Description</label>
                            <textarea id="eventDescription" name="description" rows="3" class="form-control"></textarea>
                        </div>
                        
                        <div class="event-form-grid">
                            <div class="form-group">
                                <label for="eventLocation">Location</label>
                                <input type="text" id="eventLocation" name="location" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="eventCapacity">Capacity</label>
                                <input type="number" id="eventCapacity" name="capacity" min="1" class="form-control">
                            </div>
                        </div>
                    </div>

                    <!-- Schedule Configuration -->
                    <div class="form-section">
                        <h4>Schedule Configuration</h4>
                        <div class="event-form-grid">
                            <div class="form-group">
                                <label for="startDate">Start Date *</label>
                                <input type="date" id="startDate" name="start_date" required class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="endDate">End Date</label>
                                <input type="date" id="endDate" name="end_date" class="form-control">
                            </div>
                        </div>
                        
                        <div class="event-form-grid">
                            <div class="form-group">
                                <label for="startTime">Start Time *</label>
                                <input type="time" id="startTime" name="start_time" required class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="endTime">End Time</label>
                                <input type="time" id="endTime" name="end_time" class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="recurrenceType">Recurrence Type</label>
                            <select id="recurrenceType" name="recurrence_type" class="form-control" onchange="toggleRecurrenceOptions()">
                                <option value="one_time">One Time Event</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        
                        <div id="recurrenceOptions" class="recurrence-options" style="display: none;">
                            <div class="event-form-grid">
                                <div class="form-group">
                                    <label for="recurrenceInterval">Repeat Every</label>
                                    <input type="number" id="recurrenceInterval" name="recurrence_interval" min="1" value="1" class="form-control">
                                    <small class="form-text" id="intervalHelpText">E.g., every 2 weeks</small>
                                </div>
                                <div class="form-group">
                                    <label for="recurrenceEndDate">Recurrence End Date</label>
                                    <input type="date" id="recurrenceEndDate" name="recurrence_end_date" class="form-control">
                                </div>
                            </div>
                            
                            <div class="event-form-grid">
                                <div class="form-group">
                                    <label for="maxOccurrences">Max Occurrences (optional)</label>
                                    <input type="number" id="maxOccurrences" name="max_occurrences" min="1" class="form-control">
                                    <small class="form-text">Leave empty for unlimited</small>
                                </div>
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" id="excludeHolidays" name="exclude_holidays" checked>
                                        Exclude Holidays
                                    </label>
                                    <small class="form-text">Skip instances on public holidays</small>
                                </div>
                            </div>
                            
                            <div id="weekdaySelector" style="display: none;">
                                <label>Days of Week</label>
                                <div class="weekday-selector">
                                    <div class="weekday-btn" data-day="1">Mon</div>
                                    <div class="weekday-btn" data-day="2">Tue</div>
                                    <div class="weekday-btn" data-day="3">Wed</div>
                                    <div class="weekday-btn" data-day="4">Thu</div>
                                    <div class="weekday-btn" data-day="5">Fri</div>
                                    <div class="weekday-btn" data-day="6">Sat</div>
                                    <div class="weekday-btn" data-day="0">Sun</div>
                                </div>
                                <small class="form-text">Select which days of the week this event should occur</small>
                            </div>
                            
                            <div id="holidayConflicts" class="holiday-conflicts" style="display: none;">
                                <h5>Holiday Conflicts</h5>
                                <div id="holidayList"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Participants -->
                    <div class="form-section">
                        <h4>Participants</h4>
                        <div class="participant-selector">
                            <div class="participant-search">
                                <input type="text" id="participantSearch" placeholder="Search users or groups..." class="form-control">
                                <select id="participantType" class="form-control">
                                    <option value="user">Users</option>
                                    <option value="group">Groups</option>
                                </select>
                                <button type="button" class="btn btn-secondary" onclick="searchParticipants()">Search</button>
                            </div>
                            <div id="participantResults" class="participant-list" style="display: none;"></div>
                            <div id="selectedParticipants" class="participant-list">
                                <div class="text-center text-muted">No participants selected</div>
                            </div>
                        </div>
                    </div>

                    <!-- Break/Pause Schedule -->
                    <div class="form-section">
                        <h4>Break/Pause Schedule</h4>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="hasBreaks" name="has_breaks" onchange="toggleBreakSchedule()">
                                This event has scheduled breaks/pauses
                            </label>
                        </div>
                        <div id="breakSchedule" class="pause-schedule" style="display: none;">
                            <div class="form-group">
                                <label for="breakTemplate">Use Template</label>
                                <select id="breakTemplate" class="form-control" onchange="loadBreakTemplate()">
                                    <option value="">Select a template...</option>
                                </select>
                            </div>
                            <div id="breaksList"></div>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addBreak()">+ Add Break</button>
                        </div>
                    </div>

                    <!-- Group Assignment -->
                    <div class="form-section">
                        <h4>👥 Group Assignment</h4>
                        <p class="help-text">Assign user groups to this event. Users in multiple groups are only counted once.</p>
                        
                        <div class="form-group">
                            <label for="assignedGroups">Select Groups</label>
                            <select id="assignedGroups" name="assigned_groups[]" class="form-control" multiple size="6">
                                <!-- Groups will be loaded dynamically -->
                            </select>
                            <small class="help-text">Hold Ctrl/Cmd to select multiple groups</small>
                        </div>
                        
                        <div class="event-form-grid">
                            <div class="form-group">
                                <label>Selected Groups</label>
                                <div id="selectedGroupsList" class="selected-groups-list">
                                    <div class="text-muted">No groups selected</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Estimated Participants</label>
                                <div id="estimatedParticipants" class="participant-count">
                                    <span class="count">0</span> unique users
                                    <small class="deduplication-info" style="display: none;">
                                        (<span id="totalMemberships">0</span> total memberships, 
                                        <span id="deduplicationSavings">0</span> duplicates removed)
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="button" class="btn btn-info btn-sm" onclick="showGroupManagementModal()">
                                <i class="fas fa-users"></i> Manage Groups
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="previewEventParticipants()">
                                <i class="fas fa-eye"></i> Preview Participants
                            </button>
                        </div>
                    </div>

                    <!-- Advanced Settings -->
                    <div class="form-section">
                        <h4>Advanced Settings</h4>
                        <div class="event-form-grid">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" id="requireCheckin" name="require_checkin" checked>
                                    Require Check-in
                                </label>
                            </div>
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" id="allowManualCheckin" name="allow_manual_checkin" checked>
                                    Allow Manual Check-in
                                </label>
                            </div>
                        </div>
                        <div class="event-form-grid">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" id="autoCheckout" name="auto_checkout" onchange="toggleAutoCheckout()">
                                    Auto Check-out
                                </label>
                            </div>
                            <div class="form-group" id="autoCheckoutMinutesGroup" style="display: none;">
                                <label for="autoCheckoutMinutes">Auto Check-out After (minutes)</label>
                                <input type="number" id="autoCheckoutMinutes" name="auto_checkout_minutes" value="480" min="1" class="form-control">
                                <small class="form-text">Default: 8 hours (480 minutes)</small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('eventModal')">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveEvent()">Save Event</button>
            </div>
        </div>
    </div>

    <!-- Holiday Management Modal -->
    <div id="holidayModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🏖️ Holiday Management</h3>
                <span class="close" onclick="closeModal('holidayModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-section">
                    <h4>Generate Holidays</h4>
                    <div class="form-group">
                        <label for="holidayYear">Year</label>
                        <select id="holidayYear" class="form-control">
                            <?php for ($y = date('Y') - 1; $y <= date('Y') + 2; $y++): ?>
                                <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="generateHolidays()">Generate Holidays</button>
                </div>
                
                <div class="form-section">
                    <h4>Add Custom Holiday</h4>
                    <div class="form-group">
                        <label for="customHolidayName">Holiday Name</label>
                        <input type="text" id="customHolidayName" class="form-control" placeholder="e.g., Company Day">
                    </div>
                    <div class="form-group">
                        <label for="customHolidayDate">Date</label>
                        <input type="date" id="customHolidayDate" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="customHolidayState">State (optional)</label>
                        <select id="customHolidayState" class="form-control">
                            <option value="">All Germany</option>
                            <?php foreach (HolidayManager::getStates() as $code => $name): ?>
                                <option value="<?= $code ?>"><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="customHolidayDescription">Description</label>
                        <textarea id="customHolidayDescription" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="addCustomHoliday()">Add Custom Holiday</button>
                </div>
                
                <div class="form-section">
                    <h4>Current Holidays</h4>
                    <div id="holidayList" class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>States</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="holidayTableBody">
                                <tr><td colspan="5" class="text-center">Loading holidays...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('holidayModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Break Template Modal -->
    <div id="breakTemplateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>⏰ Break Schedule Templates</h3>
                <span class="close" onclick="closeModal('breakTemplateModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div id="breakTemplateList"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('breakTemplateModal')">Close</button>
                <button type="button" class="btn btn-primary" onclick="applySelectedBreakTemplate()">Apply Selected</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/events.js"></script>
    <script>
        // Initialize the event management system
        document.addEventListener('DOMContentLoaded', function() {
            loadEvents();
            loadEventStats();
            loadBreakTemplates();
            initializeEventManagement();
        });
        
        // Enhanced JavaScript functions
        function toggleRecurrenceOptions() {
            const type = document.getElementById('recurrenceType').value;
            const options = document.getElementById('recurrenceOptions');
            const weekdaySelector = document.getElementById('weekdaySelector');
            const intervalHelp = document.getElementById('intervalHelpText');
            
            if (type === 'one_time') {
                options.style.display = 'none';
                weekdaySelector.style.display = 'none';
            } else {
                options.style.display = 'block';
                
                // Update help text based on recurrence type
                switch (type) {
                    case 'daily':
                        intervalHelp.textContent = 'E.g., every 2 days';
                        weekdaySelector.style.display = 'none';
                        break;
                    case 'weekly':
                        intervalHelp.textContent = 'E.g., every 2 weeks';
                        weekdaySelector.style.display = 'block';
                        break;
                    case 'monthly':
                        intervalHelp.textContent = 'E.g., every 2 months';
                        weekdaySelector.style.display = 'none';
                        break;
                    case 'yearly':
                        intervalHelp.textContent = 'E.g., every 2 years';
                        weekdaySelector.style.display = 'none';
                        break;
                }
                
                // Check for holiday conflicts
                checkHolidayConflicts();
            }
        }
        
        function toggleBreakSchedule() {
            const hasBreaks = document.getElementById('hasBreaks').checked;
            const schedule = document.getElementById('breakSchedule');
            schedule.style.display = hasBreaks ? 'block' : 'none';
        }
        
        function toggleAutoCheckout() {
            const autoCheckout = document.getElementById('autoCheckout').checked;
            const minutesGroup = document.getElementById('autoCheckoutMinutesGroup');
            minutesGroup.style.display = autoCheckout ? 'block' : 'none';
        }
        
        function addBreak() {
            const container = document.getElementById('breaksList');
            const breakCount = container.children.length;
            
            const breakDiv = document.createElement('div');
            breakDiv.className = 'pause-item';
            breakDiv.innerHTML = `
                <input type="text" placeholder="Break name" name="break_name[]" class="form-control">
                <input type="time" name="break_start[]" class="form-control" required>
                <input type="time" name="break_end[]" class="form-control" required>
                <input type="number" placeholder="Duration (min)" name="break_duration[]" class="form-control" min="1" max="480">
                <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">Remove</button>
            `;
            
            container.appendChild(breakDiv);
        }
        
        function loadBreakTemplate() {
            const templateSelect = document.getElementById('breakTemplate');
            const selectedTemplate = templateSelect.value;
            
            if (!selectedTemplate) return;
            
            // Find the template from loaded data
            if (window.breakTemplates) {
                const template = window.breakTemplates.find(t => t.name === selectedTemplate);
                if (template) {
                    applyBreakTemplate(template.breaks);
                }
            }
        }
        
        function applyBreakTemplate(breaks) {
            const container = document.getElementById('breaksList');
            container.innerHTML = ''; // Clear existing breaks
            
            breaks.forEach(breakItem => {
                const breakDiv = document.createElement('div');
                breakDiv.className = 'pause-item';
                breakDiv.innerHTML = `
                    <input type="text" placeholder="Break name" name="break_name[]" class="form-control" value="${breakItem.name}">
                    <input type="time" name="break_start[]" class="form-control" value="${breakItem.start}" required>
                    <input type="time" name="break_end[]" class="form-control" value="${breakItem.end}" required>
                    <input type="number" placeholder="Duration (min)" name="break_duration[]" class="form-control" value="${breakItem.duration}" min="1" max="480">
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">Remove</button>
                `;
                container.appendChild(breakDiv);
            });
        }
        
        function showHolidayManagementModal() {
            showModal('holidayModal');
            loadHolidays();
        }
        
        function generateHolidays() {
            const year = document.getElementById('holidayYear').value;
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'manage_holidays',
                    holiday_action: 'generate_year',
                    year: year
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    loadHolidays();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }
        
        function addCustomHoliday() {
            const name = document.getElementById('customHolidayName').value;
            const date = document.getElementById('customHolidayDate').value;
            const state = document.getElementById('customHolidayState').value;
            const description = document.getElementById('customHolidayDescription').value;
            
            if (!name || !date) {
                alert('Name and date are required');
                return;
            }
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'manage_holidays',
                    holiday_action: 'add_custom',
                    name: name,
                    date: date,
                    state_code: state,
                    description: description
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    loadHolidays();
                    // Clear form
                    document.getElementById('customHolidayName').value = '';
                    document.getElementById('customHolidayDate').value = '';
                    document.getElementById('customHolidayDescription').value = '';
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }
        
        function loadHolidays() {
            const year = document.getElementById('holidayYear').value;
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'manage_holidays',
                    holiday_action: 'get_holidays',
                    year: year
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayHolidays(data.holidays);
                }
            });
        }
        
        function displayHolidays(holidays) {
            const tbody = document.getElementById('holidayTableBody');
            
            if (holidays.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">No holidays found</td></tr>';
                return;
            }
            
            tbody.innerHTML = holidays.map(holiday => `
                <tr>
                    <td>${holiday.date}</td>
                    <td>${holiday.name}</td>
                    <td>
                        <span class="badge ${holiday.type === 'national' ? 'badge-primary' : 'badge-secondary'}">
                            ${holiday.type}
                        </span>
                    </td>
                    <td>${holiday.state_codes ? JSON.parse(holiday.state_codes).join(', ') : 'All'}</td>
                    <td>
                        ${holiday.type === 'custom' ? 
                            '<button class="btn btn-sm btn-danger" onclick="deleteHoliday(' + holiday.holiday_id + ')">Delete</button>' : 
                            '-'
                        }
                    </td>
                </tr>
            `).join('');
        }
        
        function loadBreakTemplates() {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'load_break_templates'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.breakTemplates = data.templates;
                    populateBreakTemplateSelect(data.templates);
                }
            });
        }
        
        function populateBreakTemplateSelect(templates) {
            const select = document.getElementById('breakTemplate');
            
            templates.forEach(template => {
                const option = document.createElement('option');
                option.value = template.name;
                option.textContent = template.name;
                select.appendChild(option);
            });
        }
        
        function checkHolidayConflicts() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value || startDate;
            const recurrenceType = document.getElementById('recurrenceType').value;
            
            if (!startDate || recurrenceType === 'one_time') return;
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'check_holiday_conflicts',
                    start_date: startDate,
                    end_date: endDate
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.has_conflicts) {
                    displayHolidayConflicts(data.holidays);
                }
            });
        }
        
        function displayHolidayConflicts(holidays) {
            const container = document.getElementById('holidayConflicts');
            const list = document.getElementById('holidayList');
            
            if (holidays.length > 0) {
                list.innerHTML = holidays.map(holiday => 
                    `<div class="alert alert-warning">
                        <strong>${holiday.name}</strong> on ${holiday.date} 
                        (${holiday.type === 'national' ? 'National' : 'Regional'})
                    </div>`
                ).join('');
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
        }
        
        // Additional utility functions
        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Weekday selector functionality
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('weekday-btn')) {
                e.target.classList.toggle('active');
            }
        });
        
        // Pass server data to JavaScript
        window.holidayStats = <?php echo json_encode($holidayStats); ?>;
    </script>
    
    <?php include '../includes/theme_script.php'; ?>
</body>
</html>
