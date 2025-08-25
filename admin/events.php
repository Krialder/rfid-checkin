<?php
require_once '../core/config.php';
require_once '../core/database.php';
require_once '../core/auth.php';

// Check if user is admin
if (!Auth::isLoggedIn() || !Auth::hasRole(['admin'])) {
    http_response_code(403);
    header('Location: ../auth/login.php');
    exit;
}

// Initialize database connection
$db = getDB();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'load_events':
                $page = max(1, intval($_POST['page'] ?? 1));
                $limit = max(10, min(100, intval($_POST['limit'] ?? 25)));
                $offset = ($page - 1) * $limit;
                $search = trim($_POST['search'] ?? '');
                $status = $_POST['status'] ?? '';
                
                $where = ['1=1'];
                $params = [];
                
                if ($search) {
                    $where[] = "(e.name LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
                    $searchParam = "%$search%";
                    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
                }
                
                if ($status === 'upcoming') {
                    $where[] = "e.start_time > NOW()";
                } elseif ($status === 'ongoing') {
                    $where[] = "e.start_time <= NOW() AND e.end_time >= NOW()";
                } elseif ($status === 'past') {
                    $where[] = "e.end_time < NOW()";
                }
                
                $whereClause = implode(' AND ', $where);
                
                // Get total count
                $stmt = $db->prepare("SELECT COUNT(*) FROM Events e WHERE $whereClause");
                $stmt->execute($params);
                $total = $stmt->fetchColumn();
                
                // Get events with attendee counts
                $stmt = $db->prepare("
                    SELECT e.*, 
                           COUNT(DISTINCT c.checkin_id) as attendee_count,
                           u.first_name as creator_name
                    FROM Events e 
                    LEFT JOIN CheckIn c ON e.event_id = c.event_id 
                    LEFT JOIN Users u ON e.created_by = u.user_id
                    WHERE $whereClause 
                    GROUP BY e.event_id 
                    ORDER BY e.start_time DESC 
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute(array_merge($params, [$limit, $offset]));
                $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'events' => $events,
                    'pagination' => [
                        'current_page' => $page,
                        'total_pages' => ceil($total / $limit),
                        'total_records' => $total,
                        'per_page' => $limit
                    ]
                ]);
                break;
                
            case 'create_event':
                $eventData = [
                    'name' => trim($_POST['name'] ?? ''),
                    'description' => trim($_POST['description'] ?? ''),
                    'location' => trim($_POST['location'] ?? ''),
                    'start_time' => $_POST['start_time'] ?? '',
                    'end_time' => $_POST['end_time'] ?? '',
                    'capacity' => intval($_POST['capacity'] ?? 0) ?: null,
                    'event_type' => $_POST['event_type'] ?? 'general',
                    'require_checkin' => isset($_POST['require_checkin']) ? 1 : 0,
                    'is_public' => isset($_POST['is_public']) ? 1 : 0
                ];
                
                // Validation
                if (empty($eventData['name']) || empty($eventData['start_time'])) {
                    throw new Exception('Event name and start time are required');
                }
                
                if ($eventData['end_time'] && $eventData['start_time'] >= $eventData['end_time']) {
                    throw new Exception('End time must be after start time');
                }
                
                // Create event
                $stmt = $db->prepare("
                    INSERT INTO Events (name, description, location, start_time, end_time, capacity, event_type, require_checkin, is_public, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $eventData['name'],
                    $eventData['description'],
                    $eventData['location'],
                    $eventData['start_time'],
                    $eventData['end_time'] ?: null,
                    $eventData['capacity'],
                    $eventData['event_type'],
                    $eventData['require_checkin'],
                    $eventData['is_public'],
                    Auth::getCurrentUser()['user_id']
                ]);
                
                $eventId = $db->lastInsertId();
                
                echo json_encode(['success' => true, 'event_id' => $eventId]);
                break;
                
            case 'update_event':
                $eventId = intval($_POST['event_id'] ?? 0);
                $field = $_POST['field'] ?? '';
                $value = $_POST['value'] ?? '';
                
                if (!$eventId || !$field) {
                    throw new Exception('Missing required parameters');
                }
                
                $allowedFields = ['name', 'description', 'location', 'start_time', 'end_time', 'capacity', 'event_type', 'require_checkin', 'is_public'];
                if (!in_array($field, $allowedFields)) {
                    throw new Exception('Invalid field');
                }
                
                // Update event
                $stmt = $db->prepare("UPDATE Events SET $field = ? WHERE event_id = ?");
                $stmt->execute([$value ?: null, $eventId]);
                
                echo json_encode(['success' => true]);
                break;
                
            case 'delete_event':
                $eventId = intval($_POST['event_id'] ?? 0);
                
                if (!$eventId) {
                    throw new Exception('Event ID required');
                }
                
                // Check if event has check-ins
                $stmt = $db->prepare("SELECT COUNT(*) FROM CheckIn WHERE event_id = ?");
                $stmt->execute([$eventId]);
                $checkinCount = $stmt->fetchColumn();
                
                if ($checkinCount > 0) {
                    throw new Exception('Cannot delete event with existing check-ins');
                }
                
                // Delete event
                $stmt = $db->prepare("DELETE FROM Events WHERE event_id = ?");
                $stmt->execute([$eventId]);
                
                echo json_encode(['success' => true]);
                break;
                
            case 'get_attendees':
                $eventId = intval($_POST['event_id'] ?? 0);
                
                if (!$eventId) {
                    throw new Exception('Event ID required');
                }
                
                $stmt = $db->prepare("
                    SELECT u.user_id, u.first_name, u.last_name, u.email, u.department,
                           c.checkin_time, c.checkout_time, c.method
                    FROM CheckIn c
                    JOIN Users u ON c.user_id = u.user_id
                    WHERE c.event_id = ?
                    ORDER BY c.checkin_time DESC
                ");
                $stmt->execute([$eventId]);
                $attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'attendees' => $attendees
                ]);
                break;
                
            case 'manual_checkin':
                $eventId = intval($_POST['event_id'] ?? 0);
                $userId = intval($_POST['user_id'] ?? 0);
                
                if (!$eventId || !$userId) {
                    throw new Exception('Event ID and User ID required');
                }
                
                // Check if already checked in
                $stmt = $db->prepare("SELECT COUNT(*) FROM CheckIn WHERE event_id = ? AND user_id = ? AND status = 'checked-in'");
                $stmt->execute([$eventId, $userId]);
                
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('User is already checked in to this event');
                }
                
                // Create check-in record
                $stmt = $db->prepare("
                    INSERT INTO CheckIn (user_id, event_id, checkin_time, method, ip_address, status) 
                    VALUES (?, ?, NOW(), 'manual', ?, 'checked-in')
                ");
                $stmt->execute([$userId, $eventId, $_SERVER['REMOTE_ADDR'] ?? 'admin']);
                
                echo json_encode(['success' => true]);
                break;
                
            case 'export_attendees':
                $eventId = intval($_POST['event_id'] ?? 0);
                
                if (!$eventId) {
                    throw new Exception('Event ID required');
                }
                
                $stmt = $db->prepare("
                    SELECT e.name as event_name,
                           u.first_name, u.last_name, u.email, u.department,
                           c.checkin_time, c.checkout_time, c.method
                    FROM CheckIn c
                    JOIN Users u ON c.user_id = u.user_id
                    JOIN Events e ON c.event_id = e.event_id
                    WHERE c.event_id = ?
                    ORDER BY c.checkin_time DESC
                ");
                $stmt->execute([$eventId]);
                $attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Generate CSV
                $filename = 'attendees_event_' . $eventId . '_' . date('Y-m-d') . '.csv';
                
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                
                $output = fopen('php://output', 'w');
                
                // CSV headers
                fputcsv($output, ['Event', 'First Name', 'Last Name', 'Email', 'Department', 'Check-in Time', 'Check-out Time', 'Method']);
                
                // CSV data
                foreach ($attendees as $attendee) {
                    fputcsv($output, [
                        $attendee['event_name'],
                        $attendee['first_name'],
                        $attendee['last_name'],
                        $attendee['email'],
                        $attendee['department'] ?? '',
                        $attendee['checkin_time'],
                        $attendee['checkout_time'] ?? '',
                        $attendee['method']
                    ]);
                }
                
                fclose($output);
                exit;
                
            default:
                throw new Exception('Unknown action');
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Get statistics for dashboard
$stats = [];
try {
    // Total events
    $stmt = $db->prepare("SELECT COUNT(*) FROM Events");
    $stmt->execute();
    $stats['total_events'] = $stmt->fetchColumn();
    
    // Upcoming events
    $stmt = $db->prepare("SELECT COUNT(*) FROM Events WHERE start_time > NOW()");
    $stmt->execute();
    $stats['upcoming_events'] = $stmt->fetchColumn();
    
    // Events this month
    $stmt = $db->prepare("SELECT COUNT(*) FROM Events WHERE start_time >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
    $stmt->execute();
    $stats['events_this_month'] = $stmt->fetchColumn();
    
    // Total attendees this month
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT c.user_id) 
        FROM CheckIn c 
        JOIN Events e ON c.event_id = e.event_id 
        WHERE e.start_time >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
    ");
    $stmt->execute();
    $stats['attendees_this_month'] = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Stats query error: " . $e->getMessage());
}

// Get all users for attendee selection
$users = [];
try {
    $stmt = $db->prepare("SELECT user_id, username, first_name, last_name, email FROM Users WHERE is_active = 1 ORDER BY first_name, last_name");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Users query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management - Electronic Check-in System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <link rel="stylesheet" href="../assets/css/admin-tools.css">
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="admin-header">
        <div class="container">
            <h1>Event Management</h1>
            <p>Create, manage, and track events</p>
        </div>
    </div>
    
    <div class="container">
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_events'] ?? 0; ?></div>
                <div class="stat-label">Total Events</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['upcoming_events'] ?? 0; ?></div>
                <div class="stat-label">Upcoming Events</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['events_this_month'] ?? 0; ?></div>
                <div class="stat-label">Events This Month</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['attendees_this_month'] ?? 0; ?></div>
                <div class="stat-label">Attendees This Month</div>
            </div>
        </div>
        
        <!-- Toolbar -->
        <div class="toolbar">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search events..." class="form-control">
            </div>
            <div class="filter-group">
                <select id="statusFilter">
                    <option value="">All Events</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="ongoing">Ongoing</option>
                    <option value="past">Past</option>
                </select>
            </div>
            <div class="btn-group">
                <button class="btn btn-primary" onclick="openCreateEventModal()">Create Event</button>
            </div>
        </div>
        
        <!-- Events Grid -->
        <div id="loadingIndicator" class="loading">
            Loading events...
        </div>
        
        <div class="events-grid" id="eventsGrid" style="display: none;">
        </div>
        
        <div class="pagination" id="paginationContainer" style="display: none;">
            <div class="pagination-info" id="paginationInfo"></div>
            <div class="pagination-controls">
                <button onclick="changePage('prev')" id="prevBtn">Previous</button>
                <button onclick="changePage('next')" id="nextBtn">Next</button>
            </div>
        </div>
    </div>
    
    <!-- Create Event Modal -->
    <div class="modal" id="createEventModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New Event</h3>
                <button class="close-btn" onclick="closeModal('createEventModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="createEventForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Event Name *</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="event_type">Event Type</label>
                            <select id="event_type" name="event_type">
                                <option value="general">General</option>
                                <option value="meeting">Meeting</option>
                                <option value="training">Training</option>
                                <option value="workshop">Workshop</option>
                                <option value="conference">Conference</option>
                                <option value="social">Social</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="location">Location</label>
                            <input type="text" id="location" name="location">
                        </div>
                        <div class="form-group">
                            <label for="capacity">Capacity</label>
                            <input type="number" id="capacity" name="capacity" min="1">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_time">Start Time *</label>
                            <input type="datetime-local" id="start_time" name="start_time" required>
                        </div>
                        <div class="form-group">
                            <label for="end_time">End Time</label>
                            <input type="datetime-local" id="end_time" name="end_time">
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" id="require_checkin" name="require_checkin" checked>
                                Require Check-in
                            </label>
                            <label>
                                <input type="checkbox" id="is_public" name="is_public" checked>
                                Public Event
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Create Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Attendees Modal -->
    <div class="modal" id="attendeesModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Event Attendees</h3>
                <button class="close-btn" onclick="closeModal('attendeesModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Add Attendee (Manual Check-in):</label>
                    <select id="userSelect">
                        <option value="">Select a user...</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>">
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-primary" onclick="addAttendee()" style="margin-top: 0.5rem;">Add Attendee</button>
                </div>
                
                <div style="margin: 1rem 0;">
                    <button class="btn btn-secondary" onclick="exportAttendees()">Export CSV</button>
                </div>
                
                <div id="attendeesLoading" class="loading" style="display: none;">Loading attendees...</div>
                <table class="attendees-table" id="attendeesTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Check-in Time</th>
                            <th>Method</th>
                        </tr>
                    </thead>
                    <tbody id="attendeesTableBody">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        let currentPage = 1;
        let totalPages = 1;
        let searchTimeout;
        let currentEventId = null;
        
        // Load events on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadEvents();
            
            // Search functionality
            document.getElementById('searchInput').addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    currentPage = 1;
                    loadEvents();
                }, 500);
            });
            
            // Filter functionality
            document.getElementById('statusFilter').addEventListener('change', () => {
                currentPage = 1;
                loadEvents();
            });
        });
        
        function loadEvents() {
            const loadingIndicator = document.getElementById('loadingIndicator');
            const eventsGrid = document.getElementById('eventsGrid');
            const paginationContainer = document.getElementById('paginationContainer');
            
            loadingIndicator.style.display = 'block';
            eventsGrid.style.display = 'none';
            paginationContainer.style.display = 'none';
            
            const formData = new FormData();
            formData.append('action', 'load_events');
            formData.append('page', currentPage);
            formData.append('search', document.getElementById('searchInput').value);
            formData.append('status', document.getElementById('statusFilter').value);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayEvents(data.events);
                    updatePagination(data.pagination);
                } else {
                    alert('Error loading events: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading events');
            })
            .finally(() => {
                loadingIndicator.style.display = 'none';
                eventsGrid.style.display = 'grid';
                paginationContainer.style.display = 'flex';
            });
        }
        
        function displayEvents(events) {
            const eventsGrid = document.getElementById('eventsGrid');
            eventsGrid.innerHTML = '';
            
            events.forEach(event => {
                const eventCard = createEventCard(event);
                eventsGrid.appendChild(eventCard);
            });
        }
        
        function createEventCard(event) {
            const card = document.createElement('div');
            card.className = 'event-card';
            
            const now = new Date();
            const startTime = new Date(event.start_time);
            const endTime = event.end_time ? new Date(event.end_time) : null;
            
            let status = 'upcoming';
            let statusText = 'Upcoming';
            
            if (startTime <= now && (!endTime || endTime >= now)) {
                status = 'ongoing';
                statusText = 'Ongoing';
            } else if (endTime && endTime < now) {
                status = 'past';
                statusText = 'Past';
            }
            
            card.innerHTML = `
                <div class="event-header">
                    <h3 class="event-title">${escapeHtml(event.name)}</h3>
                    <div class="event-meta">
                        <span class="status-badge status-${status}">${statusText}</span>
                        <span>${escapeHtml(event.event_type)}</span>
                        <span>${escapeHtml(event.location || 'No location')}</span>
                    </div>
                </div>
                <div class="event-body">
                    ${event.description ? `<p class="event-description">${escapeHtml(event.description)}</p>` : ''}
                    <div class="event-stats">
                        <div class="event-stat">
                            <div class="event-stat-value">${event.attendee_count || 0}</div>
                            <div class="event-stat-label">Attendees</div>
                        </div>
                        <div class="event-stat">
                            <div class="event-stat-value">${event.capacity || '∞'}</div>
                            <div class="event-stat-label">Capacity</div>
                        </div>
                        <div class="event-stat">
                            <div class="event-stat-value">${new Date(event.start_time).toLocaleDateString()}</div>
                            <div class="event-stat-label">Date</div>
                        </div>
                    </div>
                    <div class="event-actions">
                        <button class="btn btn-primary btn-sm" onclick="viewAttendees(${event.event_id})">View Attendees</button>
                        <button class="btn btn-secondary btn-sm" onclick="editEvent(${event.event_id})">Edit</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteEvent(${event.event_id})">Delete</button>
                    </div>
                </div>
            `;
            
            return card;
        }
        
        function updatePagination(pagination) {
            totalPages = pagination.total_pages;
            currentPage = pagination.current_page;
            
            document.getElementById('paginationInfo').textContent = 
                `Showing ${(currentPage - 1) * pagination.per_page + 1}-${Math.min(currentPage * pagination.per_page, pagination.total_records)} of ${pagination.total_records} events`;
            
            document.getElementById('prevBtn').disabled = currentPage <= 1;
            document.getElementById('nextBtn').disabled = currentPage >= totalPages;
        }
        
        function changePage(direction) {
            if (direction === 'prev' && currentPage > 1) {
                currentPage --;
                loadEvents();
            } else if (direction === 'next' && currentPage < totalPages) {
                currentPage ++;
                loadEvents();
            }
        }
        
        function openCreateEventModal() {
            document.getElementById('createEventModal').style.display = 'block';
            // Set default start time to next hour
            const nextHour = new Date();
            nextHour.setHours(nextHour.getHours() + 1, 0, 0, 0);
            document.getElementById('start_time').value = nextHour.toISOString().slice(0, 16);
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function viewAttendees(eventId) {
            currentEventId = eventId;
            document.getElementById('attendeesModal').style.display = 'block';
            loadAttendees(eventId);
        }
        
        function loadAttendees(eventId) {
            document.getElementById('attendeesLoading').style.display = 'block';
            document.getElementById('attendeesTable').style.display = 'none';
            
            const formData = new FormData();
            formData.append('action', 'get_attendees');
            formData.append('event_id', eventId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayAttendees(data.attendees);
                } else {
                    alert('Error loading attendees: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading attendees');
            })
            .finally(() => {
                document.getElementById('attendeesLoading').style.display = 'none';
                document.getElementById('attendeesTable').style.display = 'table';
            });
        }
        
        function displayAttendees(attendees) {
            const tbody = document.getElementById('attendeesTableBody');
            tbody.innerHTML = '';
            
            attendees.forEach(attendee => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${escapeHtml(attendee.first_name)} ${escapeHtml(attendee.last_name)}</td>
                    <td>${escapeHtml(attendee.email)}</td>
                    <td>${escapeHtml(attendee.department || 'N/A')}</td>
                    <td>${new Date(attendee.checkin_time).toLocaleString()}</td>
                    <td>${escapeHtml(attendee.method)}</td>
                `;
                tbody.appendChild(row);
            });
        }
        
        function addAttendee() {
            const userSelect = document.getElementById('userSelect');
            const userId = userSelect.value;
            
            if (!userId) {
                alert('Please select a user');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'manual_checkin');
            formData.append('event_id', currentEventId);
            formData.append('user_id', userId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    userSelect.value = '';
                    loadAttendees(currentEventId);
                } else {
                    alert('Error adding attendee: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding attendee');
            });
        }
        
        function exportAttendees() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.name = 'action';
            actionInput.value = 'export_attendees';
            form.appendChild(actionInput);
            
            const eventInput = document.createElement('input');
            eventInput.name = 'event_id';
            eventInput.value = currentEventId;
            form.appendChild(eventInput);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        function editEvent(eventId) {
            // Simplified edit - in a full implementation, this would open a modal
            const newName = prompt('Enter new event name:');
            if (newName) {
                updateEvent(eventId, 'name', newName);
            }
        }
        
        function deleteEvent(eventId) {
            if (confirm('Are you sure you want to delete this event?')) {
                const formData = new FormData();
                formData.append('action', 'delete_event');
                formData.append('event_id', eventId);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadEvents();
                    } else {
                        alert('Error deleting event: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting event');
                });
            }
        }
        
        function updateEvent(eventId, field, value) {
            const formData = new FormData();
            formData.append('action', 'update_event');
            formData.append('event_id', eventId);
            formData.append('field', field);
            formData.append('value', value);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadEvents();
                } else {
                    alert('Error updating event: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating event');
            });
        }
        
        // Create event form submission
        document.getElementById('createEventForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'create_event');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal('createEventModal');
                    this.reset();
                    loadEvents();
                    alert('Event created successfully');
                } else {
                    alert('Error creating event: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error creating event');
            });
        });
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Close modals when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });
    </script>
    
    <?php include '../includes/theme_script.php'; ?>
</body>
</html>
