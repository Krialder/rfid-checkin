<?php
/**
 * Enhanced Event Management System
 * Supports user groups, recurring events, holidays, and pause tracking
 */

require_once '../core/config.php';
require_once '../core/database.php';
require_once '../core/auth.php';
require_once '../core/utils.php';

// Check if user is admin
if (!Auth::isLoggedIn() || !Auth::hasRole(['admin'])) {
    http_response_code(403);
    header('Location: ../auth/login.php');
    exit;
}

$db = getDB();
$user = Auth::getCurrentUser();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_event':
                $result = createEvent($_POST, $db, $user['user_id']);
                echo json_encode($result);
                break;
                
            case 'update_event':
                $result = updateEvent($_POST, $db, $user['user_id']);
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
                $result = generateEventInstances($_POST, $db);
                echo json_encode($result);
                break;
                
            case 'load_groups':
                $result = loadUserGroups($db);
                echo json_encode($result);
                break;
                
            case 'load_users':
                $result = loadUsers($_POST, $db);
                echo json_encode($result);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log('Event Management Error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Event management functions
function createEvent($data, $db, $userId) {
    $db->beginTransaction();
    
    try {
        // Insert main event
        $stmt = $db->prepare("
            INSERT INTO Events (
                name, description, location, start_date, end_date, start_time, end_time,
                recurrence_type, recurrence_days, recurrence_interval, recurrence_end_date,
                max_occurrences, capacity, event_type, has_pauses, pause_schedule, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $pauseSchedule = null;
        if (isset($data['pauses']) && is_array($data['pauses'])) {
            $pauseSchedule = json_encode($data['pauses']);
        }
        
        $recurrenceDays = null;
        if ($data['recurrence_type'] === 'weekly' && isset($data['recurrence_days'])) {
            $recurrenceDays = json_encode($data['recurrence_days']);
        }
        
        $stmt->execute([
            $data['name'],
            $data['description'] ?? '',
            $data['location'] ?? '',
            $data['start_date'],
            $data['end_date'] ?? null,
            $data['start_time'],
            $data['end_time'] ?? null,
            $data['recurrence_type'] ?? 'one_time',
            $recurrenceDays,
            intval($data['recurrence_interval'] ?? 1),
            $data['recurrence_end_date'] ?? null,
            intval($data['max_occurrences'] ?? null),
            intval($data['capacity'] ?? null),
            $data['event_type'] ?? 'general',
            !empty($data['pauses']),
            $pauseSchedule,
            $userId
        ]);
        
        $eventId = $db->lastInsertId();
        
        // Add participants (users and groups)
        if (isset($data['participants'])) {
            foreach ($data['participants'] as $participant) {
                $stmt = $db->prepare("
                    INSERT INTO EventParticipants (event_id, user_id, group_id, participation_type, added_by)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $eventId,
                    $participant['type'] === 'user' ? $participant['id'] : null,
                    $participant['type'] === 'group' ? $participant['id'] : null,
                    $participant['participation_type'] ?? 'required',
                    $userId
                ]);
            }
        }
        
        // Generate initial event instances
        if ($data['recurrence_type'] !== 'one_time') {
            $endDate = $data['recurrence_end_date'] ?? date('Y-m-d', strtotime('+1 year'));
            generateInstances($eventId, $data['start_date'], $endDate, $db);
        } else {
            // Create single instance for one-time event
            $stmt = $db->prepare("
                INSERT INTO EventInstances (event_id, instance_date, instance_start_time, instance_end_time)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$eventId, $data['start_date'], $data['start_time'], $data['end_time']]);
        }
        
        $db->commit();
        
        Utilities::logActivity($userId, 'event_created', "Created event: {$data['name']}");
        
        return ['success' => true, 'event_id' => $eventId, 'message' => 'Event created successfully'];
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function updateEvent($data, $db, $userId) {
    $db->beginTransaction();
    
    try {
        $eventId = intval($data['event_id']);
        
        // Update main event
        $stmt = $db->prepare("
            UPDATE Events SET 
                name = ?, description = ?, location = ?, start_date = ?, end_date = ?,
                start_time = ?, end_time = ?, recurrence_type = ?, recurrence_days = ?,
                recurrence_interval = ?, recurrence_end_date = ?, max_occurrences = ?,
                capacity = ?, event_type = ?, has_pauses = ?, pause_schedule = ?,
                updated_at = NOW()
            WHERE event_id = ?
        ");
        
        $pauseSchedule = null;
        if (isset($data['pauses']) && is_array($data['pauses'])) {
            $pauseSchedule = json_encode($data['pauses']);
        }
        
        $recurrenceDays = null;
        if ($data['recurrence_type'] === 'weekly' && isset($data['recurrence_days'])) {
            $recurrenceDays = json_encode($data['recurrence_days']);
        }
        
        $stmt->execute([
            $data['name'],
            $data['description'] ?? '',
            $data['location'] ?? '',
            $data['start_date'],
            $data['end_date'] ?? null,
            $data['start_time'],
            $data['end_time'] ?? null,
            $data['recurrence_type'] ?? 'one_time',
            $recurrenceDays,
            intval($data['recurrence_interval'] ?? 1),
            $data['recurrence_end_date'] ?? null,
            intval($data['max_occurrences'] ?? null),
            intval($data['capacity'] ?? null),
            $data['event_type'] ?? 'general',
            !empty($data['pauses']),
            $pauseSchedule,
            $eventId
        ]);
        
        // Update participants
        if (isset($data['participants'])) {
            // Delete existing participants
            $stmt = $db->prepare("DELETE FROM EventParticipants WHERE event_id = ?");
            $stmt->execute([$eventId]);
            
            // Add new participants
            foreach ($data['participants'] as $participant) {
                $stmt = $db->prepare("
                    INSERT INTO EventParticipants (event_id, user_id, group_id, participation_type, added_by)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $eventId,
                    $participant['type'] === 'user' ? $participant['id'] : null,
                    $participant['type'] === 'group' ? $participant['id'] : null,
                    $participant['participation_type'] ?? 'required',
                    $userId
                ]);
            }
        }
        
        // Regenerate instances if recurrence changed
        if (isset($data['regenerate_instances']) && $data['regenerate_instances']) {
            // Delete future instances
            $stmt = $db->prepare("DELETE FROM EventInstances WHERE event_id = ? AND instance_date > CURDATE()");
            $stmt->execute([$eventId]);
            
            // Generate new instances
            if ($data['recurrence_type'] !== 'one_time') {
                $endDate = $data['recurrence_end_date'] ?? date('Y-m-d', strtotime('+1 year'));
                generateInstances($eventId, $data['start_date'], $endDate, $db);
            }
        }
        
        $db->commit();
        
        Utilities::logActivity($userId, 'event_updated', "Updated event ID: $eventId");
        
        return ['success' => true, 'message' => 'Event updated successfully'];
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function deleteEvent($eventId, $db) {
    $db->beginTransaction();
    
    try {
        // Check if event has any check-ins
        $stmt = $db->prepare("SELECT COUNT(*) FROM CheckIn WHERE event_id = ?");
        $stmt->execute([$eventId]);
        $checkinCount = $stmt->fetchColumn();
        
        if ($checkinCount > 0) {
            // Soft delete - mark as inactive
            $stmt = $db->prepare("UPDATE Events SET active = FALSE WHERE event_id = ?");
            $stmt->execute([$eventId]);
            $message = 'Event deactivated (has existing check-ins)';
        } else {
            // Hard delete
            $stmt = $db->prepare("DELETE FROM Events WHERE event_id = ?");
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
        $where[] = "e.start_date > CURDATE()";
    } elseif ($status === 'ongoing') {
        $where[] = "e.start_date <= CURDATE() AND (e.recurrence_end_date IS NULL OR e.recurrence_end_date >= CURDATE())";
    } elseif ($status === 'past') {
        $where[] = "e.recurrence_end_date < CURDATE()";
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Get total count
    $stmt = $db->prepare("SELECT COUNT(*) FROM Events e WHERE $whereClause");
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    // Get events
    $stmt = $db->prepare("
        SELECT 
            e.*,
            CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as created_by_name,
            (SELECT COUNT(*) FROM EventParticipants ep WHERE ep.event_id = e.event_id) as participant_count,
            (SELECT COUNT(*) FROM EventInstances ei WHERE ei.event_id = e.event_id AND ei.instance_date >= CURDATE()) as future_instances
        FROM Events e
        LEFT JOIN Users u ON e.created_by = u.user_id
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
        FROM Events e
        LEFT JOIN Users u ON e.created_by = u.user_id
        WHERE e.event_id = ?
    ");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    
    if (!$event) {
        return ['success' => false, 'error' => 'Event not found'];
    }
    
    // Get participants
    $stmt = $db->prepare("
        SELECT 
            ep.*,
            CASE 
                WHEN ep.user_id IS NOT NULL THEN CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))
                ELSE ug.name
            END as participant_name,
            CASE 
                WHEN ep.user_id IS NOT NULL THEN 'user'
                ELSE 'group'
            END as participant_type
        FROM EventParticipants ep
        LEFT JOIN Users u ON ep.user_id = u.user_id
        LEFT JOIN UserGroups ug ON ep.group_id = ug.group_id
        WHERE ep.event_id = ?
        ORDER BY participant_type, participant_name
    ");
    $stmt->execute([$eventId]);
    $participants = $stmt->fetchAll();
    
    // Get upcoming instances
    $stmt = $db->prepare("
        SELECT *
        FROM EventInstances
        WHERE event_id = ? AND instance_date >= CURDATE()
        ORDER BY instance_date, instance_start_time
        LIMIT 10
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

function generateEventInstances($data, $db) {
    $eventId = intval($data['event_id']);
    $startDate = $data['start_date'];
    $endDate = $data['end_date'];
    
    try {
        $stmt = $db->prepare("CALL sp_generate_event_instances(?, ?, ?)");
        $stmt->execute([$eventId, $startDate, $endDate]);
        
        return ['success' => true, 'message' => 'Event instances generated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function generateInstances($eventId, $startDate, $endDate, $db) {
    $stmt = $db->prepare("CALL sp_generate_event_instances(?, ?, ?)");
    $stmt->execute([$eventId, $startDate, $endDate]);
}

function loadUserGroups($db) {
    $stmt = $db->prepare("
        SELECT g.*, 
               COUNT(ugm.user_id) as member_count
        FROM UserGroups g
        LEFT JOIN UserGroupMembers ugm ON g.group_id = ugm.group_id AND ugm.is_active = TRUE
        WHERE g.is_active = TRUE
        GROUP BY g.group_id
        ORDER BY g.name
    ");
    $stmt->execute();
    $groups = $stmt->fetchAll();
    
    return ['success' => true, 'groups' => $groups];
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
        FROM Users u
        WHERE $whereClause
        ORDER BY u.first_name, u.last_name
        LIMIT ?
    ");
    
    $params[] = $limit;
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    return ['success' => true, 'users' => $users];
}

// Load initial data for the page
$userGroups = loadUserGroups($db);
$holidays = [];

$stmt = $db->prepare("
    SELECT * FROM Holidays 
    WHERE holiday_date >= CURDATE() OR is_recurring = TRUE
    ORDER BY holiday_date
");
$stmt->execute();
$holidays = $stmt->fetchAll();

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
                            </select>
                        </div>
                        
                        <div id="recurrenceOptions" class="recurrence-options" style="display: none;">
                            <div class="event-form-grid">
                                <div class="form-group">
                                    <label for="recurrenceInterval">Repeat Every</label>
                                    <input type="number" id="recurrenceInterval" name="recurrence_interval" min="1" value="1" class="form-control">
                                    <small class="form-text">E.g., every 2 weeks</small>
                                </div>
                                <div class="form-group">
                                    <label for="recurrenceEndDate">Recurrence End Date</label>
                                    <input type="date" id="recurrenceEndDate" name="recurrence_end_date" class="form-control">
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

                    <!-- Pause Schedule -->
                    <div class="form-section">
                        <h4>Pause/Break Schedule</h4>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="hasPauses" name="has_pauses" onchange="togglePauseSchedule()">
                                This event has scheduled breaks/pauses
                            </label>
                        </div>
                        <div id="pauseSchedule" class="pause-schedule" style="display: none;">
                            <div id="pausesList"></div>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addPause()">+ Add Pause</button>
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

    <script src="../assets/js/enhanced-events.js"></script>
    <script>
        // Initialize the event management system
        document.addEventListener('DOMContentLoaded', function() {
            loadEvents();
            loadEventStats();
            initializeEventManagement();
        });
        
        // Pass server data to JavaScript
        window.userGroups = <?php echo json_encode($userGroups['groups'] ?? []); ?>;
        window.holidays = <?php echo json_encode($holidays); ?>;
    </script>
</body>
</html>
