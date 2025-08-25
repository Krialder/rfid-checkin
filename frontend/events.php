<?php
/**
 * Events Page
 * Public events listing with search, filtering, and registration
 */

require_once '../core/auth.php';
require_once '../core/database.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();
$db = getDB();

// Get filter parameters
$view_filter = $_GET['view'] ?? 'upcoming';
$category_filter = $_GET['category'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Build WHERE conditions
$where_conditions = ['e.active = 1'];
$params = [];

// View filtering
switch ($view_filter) {
    case 'upcoming':
        $where_conditions[] = 'e.start_time > NOW()';
        $order_by = 'e.start_time ASC';
        break;
    case 'current':
        $where_conditions[] = 'e.start_time <= NOW() AND e.end_time >= NOW()';
        $order_by = 'e.start_time ASC';
        break;
    case 'past':
        $where_conditions[] = 'e.end_time < NOW()';
        $order_by = 'e.start_time DESC';
        break;
    default:
        $order_by = 'e.start_time ASC';
}

// Category filtering
if ($category_filter !== 'all') {
    $where_conditions[] = 'e.event_type = ?';
    $params[] = $category_filter;
}

// Search filtering
if ($search) {
    $where_conditions[] = '(e.name LIKE ? OR e.description LIKE ? OR e.location LIKE ?)';
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total 
    FROM Events e
    WHERE $where_clause
";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Get events with check-in status for current user
$sql = "
    SELECT 
        e.*,
        CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as created_by_name,
        CASE 
            WHEN c.checkin_id IS NOT NULL THEN c.status
            ELSE NULL
        END as user_checkin_status,
        c.checkin_time as user_checkin_time,
        (e.current_participants / NULLIF(e.capacity, 0) * 100) as capacity_percentage
    FROM Events e
    LEFT JOIN Users u ON e.created_by = u.user_id
    LEFT JOIN CheckIn c ON e.event_id = c.event_id AND c.user_id = ? 
        AND DATE(c.checkin_time) = DATE(e.start_time)
    WHERE $where_clause
    ORDER BY $order_by
    LIMIT $per_page OFFSET $offset
";

$all_params = array_merge([$user['user_id']], $params);
$stmt = $db->prepare($sql);
$stmt->execute($all_params);
$events = $stmt->fetchAll();

// Get event categories for filter dropdown
$cat_sql = "SELECT DISTINCT event_type FROM Events WHERE active = 1 AND event_type IS NOT NULL ORDER BY event_type";
$stmt = $db->prepare($cat_sql);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - Electronic Check-in System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <link rel="stylesheet" href="../assets/css/events.css">
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="main-content">
        <div class="events-header">
            <div>
                <h1>📅 Events</h1>
                <p class="subtitle">Discover and join upcoming events</p>
            </div>
        </div>
        
        <!-- View Tabs -->
        <div class="view-tabs">
            <a href="?view=upcoming&<?php echo http_build_query(array_diff_key($_GET, ['view' => ''])); ?>" 
               class="view-tab <?php echo $view_filter === 'upcoming' ? 'active' : ''; ?>">
                📅 Upcoming
            </a>
            <a href="?view=current&<?php echo http_build_query(array_diff_key($_GET, ['view' => ''])); ?>" 
               class="view-tab <?php echo $view_filter === 'current' ? 'active' : ''; ?>">
                🔴 Current
            </a>
            <a href="?view=past&<?php echo http_build_query(array_diff_key($_GET, ['view' => ''])); ?>" 
               class="view-tab <?php echo $view_filter === 'past' ? 'active' : ''; ?>">
                📋 Past
            </a>
            <a href="?view=all&<?php echo http_build_query(array_diff_key($_GET, ['view' => ''])); ?>" 
               class="view-tab <?php echo $view_filter === 'all' ? 'active' : ''; ?>">
                🗂️ All
            </a>
        </div>
        
        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_filter); ?>">
                <div class="filters-row">
                    <div class="filter-group">
                        <label for="category">Category</label>
                        <select name="category" id="category">
                            <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>>All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" 
                                        <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(htmlspecialchars($category)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="search">Search Events</label>
                        <input type="text" name="search" id="search" placeholder="Event name, description, location..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="events.php?view=<?php echo $view_filter; ?>" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Events Grid -->
        <div class="events-grid">
            <?php if (empty($events)): ?>
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <div class="empty-state-icon">📅</div>
                    <h3>No events found</h3>
                    <p>No events match your current filters. Try adjusting your search criteria or check back later for new events.</p>
                    <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                </div>
            <?php else: ?>
                <?php foreach ($events as $event): 
                    $is_past = strtotime($event['end_time']) < time();
                    $is_current = strtotime($event['start_time']) <= time() && strtotime($event['end_time']) >= time();
                    $is_upcoming = strtotime($event['start_time']) > time();
                    
                    $capacity_percentage = 0;
                    if ($event['capacity'] > 0) {
                        $capacity_percentage = ($event['current_participants'] / $event['capacity']) * 100;
                    }
                    
                    $card_class = 'event-card';
                    if ($is_past) $card_class .= ' event-past';
                    elseif ($is_current) $card_class .= ' event-current';
                    elseif ($is_upcoming) $card_class .= ' event-upcoming';
                ?>
                    <div class="<?php echo $card_class; ?>" data-event-id="<?php echo $event['event_id']; ?>">
                        <div class="event-header">
                            <div>
                                <h3 class="event-title"><?php echo htmlspecialchars($event['name']); ?></h3>
                            </div>
                            <?php if ($event['event_type']): ?>
                                <div class="event-type"><?php echo htmlspecialchars($event['event_type']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="event-datetime">
                            <span>🕒</span>
                            <span>
                                <?php echo date('M j, Y g:i A', strtotime($event['start_time'])); ?>
                                <?php if (date('Y-m-d', strtotime($event['start_time'])) !== date('Y-m-d', strtotime($event['end_time']))): ?>
                                    - <?php echo date('M j, Y g:i A', strtotime($event['end_time'])); ?>
                                <?php else: ?>
                                    - <?php echo date('g:i A', strtotime($event['end_time'])); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <?php if ($event['location']): ?>
                            <div class="event-location">
                                <span>📍</span>
                                <span><?php echo htmlspecialchars($event['location']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($event['description']): ?>
                            <div class="event-description">
                                <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="event-footer">
                            <div>
                                <?php if ($event['capacity'] > 0): ?>
                                    <div class="event-capacity">
                                        <div>
                                            <?php echo $event['current_participants']; ?> / <?php echo $event['capacity']; ?> participants
                                        </div>
                                        <div class="capacity-bar">
                                            <div class="capacity-fill <?php echo $capacity_percentage >= 100 ? 'capacity-full' : ''; ?>" 
                                                 style="width: <?php echo min(100, $capacity_percentage); ?>%"></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="event-capacity">
                                        <?php echo $event['current_participants']; ?> participants
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="event-actions">
                                <?php if ($event['user_checkin_status']): ?>
                                    <div class="checkin-status status-checked-in">
                                        <span>✅</span>
                                        <span>Checked In</span>
                                    </div>
                                <?php elseif ($is_current): ?>
                                    <button class="btn btn-primary btn-sm" onclick="quickCheckIn(<?php echo $event['event_id']; ?>)">
                                        Quick Check-in
                                    </button>
                                <?php elseif ($is_upcoming): ?>
                                    <div class="checkin-status status-not-checked-in">
                                        <span>⏰</span>
                                        <span>Upcoming</span>
                                    </div>
                                <?php elseif ($is_past): ?>
                                    <div class="checkin-status status-not-checked-in">
                                        <span>📋</span>
                                        <span>Completed</span>
                                    </div>
                                <?php endif; ?>
                                
                                <button class="btn btn-secondary btn-sm" onclick="showEventDetails(<?php echo $event['event_id']; ?>)">
                                    Details
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                        ← Previous
                    </a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                    <?php if ($start_page > 2): ?>
                        <span>...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i ++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span>...</span>
                    <?php endif; ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"><?php echo $total_pages; ?></a>
                <?php endif; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                        Next →
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Event Details Modal -->
    <div id="eventDetailsModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div id="eventDetailsContent">
                Loading...
            </div>
        </div>
    </div>

    <script>
        // Quick check-in function
        async function quickCheckIn(eventId) {
            try {
                const response = await fetch('../api/manual_checkin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `event_id=${eventId}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message, 'success');
                    // Reload page to update status
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(result.error || 'Check-in failed', 'error');
                }
            } catch (error) {
                showNotification('Network error occurred', 'error');
                console.error('Quick check-in error:', error);
            }
        }
        
        // Show event details modal
        async function showEventDetails(eventId) {
            const modal = document.getElementById('eventDetailsModal');
            const content = document.getElementById('eventDetailsContent');
            
            modal.classList.add('show');
            content.innerHTML = 'Loading...';
            
            try {
                const response = await fetch(`api/event_details.php?event_id=${eventId}`);
                const event = await response.json();
                
                if (event.error) {
                    content.innerHTML = `<div class="alert alert-error">${event.error}</div>`;
                    return;
                }
                
                content.innerHTML = `
                    <h2>${escapeHtml(event.name)}</h2>
                    <div class="modal-section">
                        <span class="event-type">${escapeHtml(event.event_type || 'Event')}</span>
                    </div>
                    
                    <div class="modal-section">
                        <strong>📅 Date & Time:</strong><br>
                        ${formatDateTime(event.start_time)} - ${formatDateTime(event.end_time)}
                    </div>
                    
                    ${event.location ? `
                        <div class="modal-section">
                            <strong>📍 Location:</strong><br>
                            ${escapeHtml(event.location)}
                        </div>
                    ` : ''}
                    
                    ${event.description ? `
                        <div class="modal-section">
                            <strong>📝 Description:</strong><br>
                            ${escapeHtml(event.description).replace(/\n/g, '<br>')}
                        </div>
                    ` : ''}
                    
                    <div class="modal-section">
                        <strong>👥 Participants:</strong><br>
                        ${event.current_participants} ${event.max_participants > 0 ? '/ ' + event.max_participants : ''} participants
                    </div>
                    
                    ${event.created_by_name ? `
                        <div style="margin-bottom: 1rem;">
                            <strong>👤 Organizer:</strong><br>
                            ${escapeHtml(event.created_by_name)}
                        </div>
                    ` : ''}
                    
                    <div style="margin-top: 2rem; text-align: center;">
                        ${event.user_checkin_status ? 
                            '<span class="status-badge status-checked-in">✅ Already Checked In</span>' : 
                            (event.is_current ? 
                                `<button class="btn btn-primary" onclick="quickCheckIn(${event.event_id}); closeModal('eventDetailsModal');">Check In Now</button>` : 
                                '<span class="text-muted">Check-in not available</span>')
                        }
                    </div>
                `;
                
            } catch (error) {
                content.innerHTML = '<div class="alert alert-error">Failed to load event details</div>';
                console.error('Error loading event details:', error);
            }
        }
        
        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Close modal on background click
            document.getElementById('eventDetailsModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal('eventDetailsModal');
                }
            });
            
            // Close modal on X click
            document.querySelector('#eventDetailsModal .close').addEventListener('click', function() {
                closeModal('eventDetailsModal');
            });
        });
        
        // Utility functions
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
        
        function formatDateTime(dateString) {
            return new Date(dateString).toLocaleString();
        }
        
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            document.querySelectorAll('.notification').forEach(n => n.remove());
            
            const notification = document.createElement('div');
            notification.className = `notification alert alert-${type}`;
            notification.innerHTML = `
                <span>${escapeHtml(message)}</span>
                <button class="notification-close" onclick="this.parentElement.remove()">&times;</button>
            `;
            
            notification.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
                max-width: 500px;
                padding: 15px 20px;
                border-radius: 6px;
                box-shadow: var(-- shadow-lg);
                display: flex;
                align-items: center;
                justify-content: space-between;
                animation: slideInRight 0.3s ease-out;
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }
    </script>

    <?php include '../includes/theme_script.php'; ?>
</body>
</html>
