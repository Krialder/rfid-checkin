<?php 
$renderer->startSection('styles');
echo '<link rel="stylesheet" href="' . $renderer->helper('asset', 'css/events.css') . '">';
$renderer->endSection();

$pageTitle = 'Events';
$pageDescription = 'Browse and manage events in the RFID check-in system';
?>

<!-- Events Header -->
<div class="events-header">
    <div class="header-content">
        <div class="title-section">
            <h1 class="page-title">
                <i class="fas fa-calendar"></i>
                Events
            </h1>
            <p class="page-subtitle">
                Browse upcoming events and view attendance data
            </p>
        </div>
        
        <div class="header-actions">
            <?php if ($renderer->helper('can', 'create_event')): ?>
                <a href="/events/create" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Create Event
                </a>
            <?php endif; ?>
            
            <div class="view-toggle">
                <button class="view-btn active" data-view="grid">
                    <i class="fas fa-th-large"></i>
                </button>
                <button class="view-btn" data-view="list">
                    <i class="fas fa-list"></i>
                </button>
                <button class="view-btn" data-view="calendar">
                    <i class="fas fa-calendar-alt"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Events Filters -->
<div class="events-filters">
    <div class="filters-row">
        
        <div class="filter-group">
            <label for="event-status" class="filter-label">Status</label>
            <select id="event-status" class="form-select">
                <option value="">All Events</option>
                <option value="upcoming">Upcoming</option>
                <option value="active">Active</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label for="event-category" class="filter-label">Category</label>
            <select id="event-category" class="form-select">
                <option value="">All Categories</option>
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $renderer->helper('e', $category['id']) ?>">
                            <?= $renderer->helper('e', $category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        
        <div class="filter-group">
            <label for="date-range" class="filter-label">Date Range</label>
            <select id="date-range" class="form-select">
                <option value="all">All Time</option>
                <option value="today">Today</option>
                <option value="week">This Week</option>
                <option value="month">This Month</option>
                <option value="custom">Custom Range</option>
            </select>
        </div>
        
        <div class="filter-group search-group">
            <label for="event-search" class="filter-label">Search</label>
            <div class="search-input-wrapper">
                <input type="text" id="event-search" class="form-input" placeholder="Search events...">
                <i class="fas fa-search search-icon"></i>
            </div>
        </div>
        
        <div class="filter-actions">
            <button class="btn btn-outline btn-sm" id="clear-filters">
                <i class="fas fa-times"></i>
                Clear
            </button>
        </div>
        
    </div>
    
    <!-- Custom Date Range -->
    <div class="custom-date-range" id="custom-date-range" style="display: none;">
        <div class="date-inputs">
            <div class="date-group">
                <label for="start-date">Start Date</label>
                <input type="date" id="start-date" class="form-input">
            </div>
            <div class="date-group">
                <label for="end-date">End Date</label>
                <input type="date" id="end-date" class="form-input">
            </div>
            <button class="btn btn-primary btn-sm" id="apply-date-range">Apply</button>
        </div>
    </div>
</div>

<!-- Events Content -->
<div class="events-content">
    
    <!-- Grid View -->
    <div class="events-view events-grid active" id="grid-view">
        <?php if (!empty($events)): ?>
            <div class="events-grid-container">
                <?php foreach ($events as $event): ?>
                    <div class="event-card" data-event-id="<?= $event['id'] ?>">
                        
                        <div class="event-header">
                            <div class="event-status status-<?= $event['status'] ?>">
                                <?= $renderer->helper('e', ucfirst($event['status'])) ?>
                            </div>
                            <div class="event-category">
                                <?= $renderer->helper('e', $event['category_name'] ?? 'General') ?>
                            </div>
                        </div>
                        
                        <div class="event-body">
                            <h3 class="event-title">
                                <a href="/events/<?= $event['id'] ?>"><?= $renderer->helper('e', $event['name']) ?></a>
                            </h3>
                            
                            <div class="event-meta">
                                <div class="event-date">
                                    <i class="fas fa-calendar"></i>
                                    <?= $renderer->helper('date', $event['start_date'], 'M j, Y') ?>
                                    <?php if ($event['start_date'] !== $event['end_date']): ?>
                                        - <?= $renderer->helper('date', $event['end_date'], 'M j, Y') ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="event-time">
                                    <i class="fas fa-clock"></i>
                                    <?= $renderer->helper('date', $event['start_time'], 'g:i A') ?>
                                    - <?= $renderer->helper('date', $event['end_time'], 'g:i A') ?>
                                </div>
                                
                                <?php if ($event['location']): ?>
                                    <div class="event-location">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?= $renderer->helper('e', $event['location']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($event['description']): ?>
                                <div class="event-description">
                                    <?= $renderer->helper('truncate', $event['description'], 120) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="event-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?= $renderer->helper('number', $event['checkin_count'] ?? 0) ?></div>
                                <div class="stat-label">Check-ins</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= $renderer->helper('number', $event['capacity'] ?? 0) ?></div>
                                <div class="stat-label">Capacity</div>
                            </div>
                            <?php if ($event['capacity'] > 0): ?>
                                <div class="stat-item">
                                    <div class="stat-value"><?= round((($event['checkin_count'] ?? 0) / $event['capacity']) * 100) ?>%</div>
                                    <div class="stat-label">Full</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="event-actions">
                            <a href="/events/<?= $event['id'] ?>" class="btn btn-sm btn-outline">
                                View Details
                            </a>
                            
                            <?php if ($event['status'] === 'active' && $renderer->helper('can', 'checkin')): ?>
                                <button class="btn btn-sm btn-primary" onclick="quickCheckin(<?= $event['id'] ?>)">
                                    Check In
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($renderer->helper('can', 'edit_event', $event)): ?>
                                <a href="/events/<?= $event['id'] ?>/edit" class="btn btn-sm btn-secondary">
                                    Edit
                                </a>
                            <?php endif; ?>
                        </div>
                        
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <h3>No events found</h3>
                <p>There are no events matching your current filters.</p>
                <?php if ($renderer->helper('can', 'create_event')): ?>
                    <a href="/events/create" class="btn btn-primary">Create First Event</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- List View -->
    <div class="events-view events-list" id="list-view">
        <?php if (!empty($events)): ?>
            <div class="events-table-container">
                <table class="events-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Date & Time</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Check-ins</th>
                            <th>Capacity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr data-event-id="<?= $event['id'] ?>">
                                <td class="event-info">
                                    <div class="event-name">
                                        <a href="/events/<?= $event['id'] ?>"><?= $renderer->helper('e', $event['name']) ?></a>
                                    </div>
                                    <div class="event-category"><?= $renderer->helper('e', $event['category_name'] ?? 'General') ?></div>
                                </td>
                                <td class="event-datetime">
                                    <div class="event-date"><?= $renderer->helper('date', $event['start_date'], 'M j, Y') ?></div>
                                    <div class="event-time">
                                        <?= $renderer->helper('date', $event['start_time'], 'g:i A') ?>
                                        - <?= $renderer->helper('date', $event['end_time'], 'g:i A') ?>
                                    </div>
                                </td>
                                <td class="event-location">
                                    <?= $renderer->helper('e', $event['location'] ?? 'TBD') ?>
                                </td>
                                <td class="event-status">
                                    <span class="status-badge status-<?= $event['status'] ?>">
                                        <?= $renderer->helper('e', ucfirst($event['status'])) ?>
                                    </span>
                                </td>
                                <td class="event-checkins">
                                    <?= $renderer->helper('number', $event['checkin_count'] ?? 0) ?>
                                </td>
                                <td class="event-capacity">
                                    <?= $renderer->helper('number', $event['capacity'] ?? 0) ?>
                                </td>
                                <td class="event-actions">
                                    <div class="action-buttons">
                                        <a href="/events/<?= $event['id'] ?>" class="btn btn-xs btn-outline" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($event['status'] === 'active' && $renderer->helper('can', 'checkin')): ?>
                                            <button class="btn btn-xs btn-primary" onclick="quickCheckin(<?= $event['id'] ?>)" title="Check In">
                                                <i class="fas fa-user-check"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($renderer->helper('can', 'edit_event', $event)): ?>
                                            <a href="/events/<?= $event['id'] ?>/edit" class="btn btn-xs btn-secondary" title="Edit Event">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <h3>No events found</h3>
                <p>There are no events matching your current filters.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Calendar View -->
    <div class="events-view events-calendar" id="calendar-view">
        <div class="calendar-container">
            <div id="events-calendar" class="calendar">
                <!-- Calendar will be generated by JavaScript -->
            </div>
        </div>
    </div>
    
</div>

<!-- Pagination -->
<?php if (!empty($pagination)): ?>
    <div class="events-pagination">
        <nav class="pagination-nav">
            
            <?php if ($pagination['current_page'] > 1): ?>
                <a href="?page=<?= $pagination['current_page'] - 1 ?><?= $pagination['query_string'] ?>" class="pagination-btn pagination-prev">
                    <i class="fas fa-chevron-left"></i>
                    Previous
                </a>
            <?php endif; ?>
            
            <div class="pagination-info">
                Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?>
                (<?= $renderer->helper('number', $pagination['total_items']) ?> total events)
            </div>
            
            <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                <a href="?page=<?= $pagination['current_page'] + 1 ?><?= $pagination['query_string'] ?>" class="pagination-btn pagination-next">
                    Next
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
            
        </nav>
    </div>
<?php endif; ?>

<?php $renderer->startSection('scripts'); ?>
<script src="<?= $renderer->helper('asset', 'js/events/events.js') ?>"></script>
<script src="<?= $renderer->helper('asset', 'js/calendar.js') ?>"></script>
<script>
// Set CSRF token for API calls
window.App = window.App || {};
window.App.csrfToken = <?= json_encode($renderer->helper('csrfToken')) ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize events manager
    if (typeof EventsManager !== 'undefined') {
        new EventsManager({
            gridView: document.getElementById('grid-view'),
            listView: document.getElementById('list-view'),
            calendarView: document.getElementById('calendar-view'),
            filterForm: document.querySelector('.events-filters'),
            searchInput: document.getElementById('event-search')
        });
    }
    
    // View toggle functionality
    const viewButtons = document.querySelectorAll('.view-btn');
    const eventViews = document.querySelectorAll('.events-view');
    
    viewButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const viewType = this.dataset.view;
            
            // Update active button
            viewButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Update active view
            eventViews.forEach(view => view.classList.remove('active'));
            document.getElementById(viewType + '-view').classList.add('active');
            
            // Initialize calendar if calendar view is selected
            if (viewType === 'calendar' && typeof initEventsCalendar === 'function') {
                initEventsCalendar();
            }
        });
    });
    
    // Filter functionality
    const statusFilter = document.getElementById('event-status');
    const categoryFilter = document.getElementById('event-category');
    const dateRangeFilter = document.getElementById('date-range');
    const searchInput = document.getElementById('event-search');
    const clearFiltersBtn = document.getElementById('clear-filters');
    const customDateRange = document.getElementById('custom-date-range');
    
    // Date range toggle
    if (dateRangeFilter) {
        dateRangeFilter.addEventListener('change', function() {
            if (this.value === 'custom') {
                customDateRange.style.display = 'block';
            } else {
                customDateRange.style.display = 'none';
                applyFilters();
            }
        });
    }
    
    // Apply custom date range
    const applyDateRangeBtn = document.getElementById('apply-date-range');
    if (applyDateRangeBtn) {
        applyDateRangeBtn.addEventListener('click', applyFilters);
    }
    
    // Filter change handlers
    [statusFilter, categoryFilter, searchInput].forEach(filter => {
        if (filter) {
            filter.addEventListener('change', applyFilters);
            if (filter.type === 'text') {
                let timeout;
                filter.addEventListener('input', function() {
                    clearTimeout(timeout);
                    timeout = setTimeout(applyFilters, 500);
                });
            }
        }
    });
    
    // Clear filters
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function() {
            [statusFilter, categoryFilter, dateRangeFilter, searchInput].forEach(filter => {
                if (filter) {
                    filter.value = '';
                }
            });
            customDateRange.style.display = 'none';
            applyFilters();
        });
    }
    
    function applyFilters() {
        const params = new URLSearchParams();
        
        // Collect filter values
        if (statusFilter?.value) params.set('status', statusFilter.value);
        if (categoryFilter?.value) params.set('category', categoryFilter.value);
        if (dateRangeFilter?.value && dateRangeFilter.value !== 'custom') {
            params.set('date_range', dateRangeFilter.value);
        }
        if (dateRangeFilter?.value === 'custom') {
            const startDate = document.getElementById('start-date')?.value;
            const endDate = document.getElementById('end-date')?.value;
            if (startDate) params.set('start_date', startDate);
            if (endDate) params.set('end_date', endDate);
        }
        if (searchInput?.value) params.set('search', searchInput.value);
        
        // Reload page with filters
        const url = new URL(window.location);
        url.search = params.toString();
        window.location.href = url.toString();
    }
    
    // Quick check-in functionality
    window.quickCheckin = function(eventId) {
        if (!eventId) return;
        
        const btn = event.target;
        const originalText = btn.innerHTML;
        
        // Show loading state
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;
        
        fetch('/api/checkin/quick', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.App.csrfToken
            },
            body: JSON.stringify({ event_id: eventId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof window.showNotification === 'function') {
                    window.showNotification('success', 'Successfully checked into event!');
                }
                
                // Update check-in count if visible
                const eventCard = document.querySelector(`[data-event-id="${eventId}"]`);
                if (eventCard) {
                    const checkinCount = eventCard.querySelector('.stat-value');
                    if (checkinCount) {
                        const currentCount = parseInt(checkinCount.textContent) || 0;
                        checkinCount.textContent = (currentCount + 1).toLocaleString();
                    }
                }
                
                // Disable check-in button
                btn.innerHTML = '<i class="fas fa-check"></i> Checked In';
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-success');
                btn.disabled = true;
            } else {
                btn.innerHTML = originalText;
                btn.disabled = false;
                
                if (typeof window.showNotification === 'function') {
                    window.showNotification('error', data.message || 'Check-in failed');
                }
            }
        })
        .catch(error => {
            console.error('Quick check-in error:', error);
            btn.innerHTML = originalText;
            btn.disabled = false;
            
            if (typeof window.showNotification === 'function') {
                window.showNotification('error', 'An error occurred. Please try again.');
            }
        });
    };
});
</script>
<?php $renderer->endSection(); ?>