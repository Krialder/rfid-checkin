<?php
/**
 * Event Management Dashboard Template
 * 
 * Main dashboard for event management with statistics,
 * recent events, and quick actions.
 */
?>

<div class="events-dashboard">
    <div class="dashboard-header">
        <div class="header-content">
            <h1 class="page-title">
                <i class="fas fa-calendar-alt"></i>
                Event Management
            </h1>
            <div class="header-actions">
                <a href="/admin/events/create" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Event
                </a>
                <a href="/admin/events/calendar" class="btn btn-outline">
                    <i class="fas fa-calendar"></i> Calendar View
                </a>
                <a href="/admin/events/analytics" class="btn btn-outline">
                    <i class="fas fa-chart-bar"></i> Analytics
                </a>
            </div>
        </div>
    </div>

    <!-- Event Statistics -->
    <div class="dashboard-stats">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check text-success"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= htmlspecialchars($stats['total_events'] ?? 0) ?></div>
                    <div class="stat-label">Total Events</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock text-warning"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= htmlspecialchars($stats['active_events'] ?? 0) ?></div>
                    <div class="stat-label">Active Events</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users text-info"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= htmlspecialchars($stats['total_participants'] ?? 0) ?></div>
                    <div class="stat-label">Total Participants</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-percentage text-primary"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= htmlspecialchars(number_format($stats['avg_attendance_rate'] ?? 0, 1)) ?>%</div>
                    <div class="stat-label">Avg Attendance</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Content -->
    <div class="dashboard-content">
        <div class="dashboard-grid">
            <!-- Recent Events -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-history"></i>
                        Recent Events
                    </h2>
                    <a href="/admin/events" class="section-action">View All</a>
                </div>
                <div class="events-list">
                    <?php if (!empty($recent_events)): ?>
                        <?php foreach ($recent_events as $event): ?>
                            <div class="event-item">
                                <div class="event-info">
                                    <h4 class="event-name">
                                        <a href="/admin/events/view?id=<?= $event['id'] ?>">
                                            <?= htmlspecialchars($event['name']) ?>
                                        </a>
                                    </h4>
                                    <div class="event-meta">
                                        <span class="event-date">
                                            <i class="fas fa-calendar"></i>
                                            <?= date('M j, Y', strtotime($event['start_date'])) ?>
                                        </span>
                                        <span class="event-time">
                                            <i class="fas fa-clock"></i>
                                            <?= date('g:i A', strtotime($event['start_time'])) ?>
                                        </span>
                                        <?php if ($event['location']): ?>
                                            <span class="event-location">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?= htmlspecialchars($event['location']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="event-status">
                                    <span class="status-badge status-<?= $event['status'] ?>">
                                        <?= ucfirst($event['status']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <p>No recent events found</p>
                            <a href="/admin/events/create" class="btn btn-primary">Create First Event</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upcoming Events -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-clock"></i>
                        Upcoming Events
                    </h2>
                    <a href="/admin/events/calendar" class="section-action">View Calendar</a>
                </div>
                <div class="events-list">
                    <?php if (!empty($upcoming_events)): ?>
                        <?php foreach ($upcoming_events as $event): ?>
                            <div class="event-item upcoming">
                                <div class="event-info">
                                    <h4 class="event-name">
                                        <a href="/admin/events/view?id=<?= $event['id'] ?>">
                                            <?= htmlspecialchars($event['name']) ?>
                                        </a>
                                    </h4>
                                    <div class="event-meta">
                                        <span class="event-date">
                                            <i class="fas fa-calendar"></i>
                                            <?= date('M j, Y', strtotime($event['start_date'])) ?>
                                        </span>
                                        <span class="event-time">
                                            <i class="fas fa-clock"></i>
                                            <?= date('g:i A', strtotime($event['start_time'])) ?>
                                        </span>
                                        <?php if ($event['capacity']): ?>
                                            <span class="event-capacity">
                                                <i class="fas fa-users"></i>
                                                <?= $event['registered_count'] ?? 0 ?>/<?= $event['capacity'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="event-actions">
                                    <a href="/admin/events/edit?id=<?= $event['id'] ?>" class="btn btn-sm btn-outline">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-plus"></i>
                            <p>No upcoming events scheduled</p>
                            <a href="/admin/events/create" class="btn btn-primary">Schedule Event</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="actions-grid">
                <a href="/admin/events/create" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="action-content">
                        <h3>Create Event</h3>
                        <p>Schedule a new event with participants</p>
                    </div>
                </a>
                
                <a href="/admin/events?status=active" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="action-content">
                        <h3>Active Events</h3>
                        <p>View and manage currently active events</p>
                    </div>
                </a>
                
                <a href="/admin/events/analytics" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="action-content">
                        <h3>Event Analytics</h3>
                        <p>View attendance and performance metrics</p>
                    </div>
                </a>
                
                <a href="/admin/user-groups" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="action-content">
                        <h3>Manage Groups</h3>
                        <p>Configure participant groups</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard features
    initializeEventDashboard();
});

function initializeEventDashboard() {
    // Auto-refresh stats every 5 minutes
    setInterval(function() {
        refreshDashboardStats();
    }, 300000);
    
    // Real-time event updates
    if (typeof WebSocket !== 'undefined') {
        const ws = new WebSocket(`ws://${window.location.host}/ws/events`);
        ws.onmessage = function(event) {
            const data = JSON.parse(event.data);
            if (data.type === 'event_update') {
                updateEventDisplay(data.event);
            }
        };
    }
}

function refreshDashboardStats() {
    fetch('/api/events/stats', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= $csrf_token ?>'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateStatsDisplay(data.stats);
        }
    })
    .catch(error => {
        console.error('Error refreshing stats:', error);
    });
}

function updateStatsDisplay(stats) {
    const statCards = document.querySelectorAll('.stat-number');
    if (statCards[0]) statCards[0].textContent = stats.total_events || 0;
    if (statCards[1]) statCards[1].textContent = stats.active_events || 0;
    if (statCards[2]) statCards[2].textContent = stats.total_participants || 0;
    if (statCards[3]) statCards[3].textContent = (stats.avg_attendance_rate || 0).toFixed(1) + '%';
}

function updateEventDisplay(event) {
    // Update event displays with real-time data
    const eventItems = document.querySelectorAll(`[data-event-id="${event.id}"]`);
    eventItems.forEach(item => {
        // Update event information
        const nameElement = item.querySelector('.event-name a');
        if (nameElement) nameElement.textContent = event.name;
        
        const statusElement = item.querySelector('.status-badge');
        if (statusElement) {
            statusElement.className = `status-badge status-${event.status}`;
            statusElement.textContent = event.status.charAt(0).toUpperCase() + event.status.slice(1);
        }
    });
}
</script>

<style>
.events-dashboard {
    padding: 2rem;
}

.dashboard-header {
    margin-bottom: 2rem;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.page-title {
    font-size: 2rem;
    color: var(--text-primary);
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.dashboard-stats {
    margin-bottom: 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: var(--shadow-card);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    font-size: 2rem;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: var(--text-primary);
}

.stat-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.dashboard-section {
    background: white;
    border-radius: 8px;
    box-shadow: var(--shadow-card);
    overflow: hidden;
}

.section-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.section-title {
    font-size: 1.25rem;
    color: var(--text-primary);
    margin: 0;
}

.section-action {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
}

.events-list {
    max-height: 400px;
    overflow-y: auto;
}

.event-item {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-light);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.event-item:last-child {
    border-bottom: none;
}

.event-name a {
    color: var(--text-primary);
    text-decoration: none;
    font-weight: 500;
}

.event-meta {
    display: flex;
    gap: 1rem;
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
}

.status-active {
    background: var(--success-light);
    color: var(--success-dark);
}

.status-inactive {
    background: var(--warning-light);
    color: var(--warning-dark);
}

.status-cancelled {
    background: var(--error-light);
    color: var(--error-dark);
}

.empty-state {
    padding: 3rem;
    text-align: center;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.quick-actions {
    background: white;
    border-radius: 8px;
    box-shadow: var(--shadow-card);
    padding: 1.5rem;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.action-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border: 2px solid var(--border-light);
    border-radius: 8px;
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.2s ease;
}

.action-card:hover {
    border-color: var(--primary-color);
    transform: translateY(-2px);
}

.action-icon {
    font-size: 2rem;
    color: var(--primary-color);
}

.action-content h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1rem;
}

.action-content p {
    margin: 0;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

@media (max-width: 768px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .header-content {
        flex-direction: column;
        align-items: stretch;
    }
    
    .header-actions {
        justify-content: center;
    }
}
</style>
