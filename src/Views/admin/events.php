<?php
$page_title = 'Event Management';
$page_description = 'Manage events, schedules, and attendance tracking';
$breadcrumbs = [
    ['title' => 'Administration', 'url' => '/admin', 'icon' => 'fas fa-shield-alt'],
    ['title' => 'Event Management', 'url' => '/admin/events', 'icon' => 'fas fa-calendar-alt']
];
$current_page = 'admin-events';

// Page-specific assets
$assets = [
    'css' => ['admin.css', 'calendar.css', 'data-tables.css', 'modals.css'],
    'js' => ['admin-events.js', 'calendar.js', 'data-tables.js', 'bulk-actions.js']
];

// Page actions
$page_actions = [
    ['title' => 'Create Event', 'url' => '/admin/events/create', 'type' => 'primary', 'icon' => 'fas fa-plus'],
    ['title' => 'Import Events', 'url' => '/admin/events/import', 'type' => 'outline', 'icon' => 'fas fa-upload'],
    ['title' => 'Export Events', 'url' => '/admin/events/export', 'type' => 'outline', 'icon' => 'fas fa-download']
];
?>

<!-- Event Management Interface -->
<div class="admin-content">
    
    <!-- Page Header -->
    <div class="admin-header">
        <div class="header-content">
            <div class="header-info">
                <h1 class="page-title">
                    <i class="fas fa-calendar-alt"></i>
                    Event Management
                </h1>
                <p class="page-description">
                    Create, schedule, and manage events with attendance tracking.
                    <span class="event-count">Total events: <strong id="total-events-count"><?= number_format($stats['total_events'] ?? 0) ?></strong></span>
                </p>
            </div>
            
            <div class="header-stats">
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['upcoming_events'] ?? 0) ?></div>
                    <div class="stat-label">Upcoming</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['active_events'] ?? 0) ?></div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['completed_events'] ?? 0) ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View Toggle -->
    <div class="view-toggle">
        <div class="toggle-group">
            <button type="button" class="toggle-btn active" data-view="list">
                <i class="fas fa-list"></i>
                List View
            </button>
            <button type="button" class="toggle-btn" data-view="calendar">
                <i class="fas fa-calendar"></i>
                Calendar View
            </button>
            <button type="button" class="toggle-btn" data-view="grid">
                <i class="fas fa-th"></i>
                Grid View
            </button>
        </div>
        
        <div class="view-actions">
            <button type="button" class="btn btn-outline btn-sm" id="sync-calendar">
                <i class="fas fa-sync-alt"></i>
                Sync Calendar
            </button>
        </div>
    </div>
    
    <!-- List View Container -->
    <div class="view-container" id="list-view">
        
        <!-- Filters and Search -->
        <div class="admin-filters">
            <div class="filters-row">
                
                <!-- Search -->
                <div class="filter-group search-group">
                    <div class="search-input-wrapper">
                        <input type="text" 
                               id="event-search" 
                               class="form-input search-input" 
                               placeholder="Search events by title, description, or location..."
                               autocomplete="off">
                        <button type="button" class="search-button" id="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                        <button type="button" class="search-clear" id="search-clear" style="display: none;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Status Filter -->
                <div class="filter-group">
                    <label for="status-filter" class="filter-label">Status</label>
                    <select id="status-filter" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <!-- Category Filter -->
                <div class="filter-group">
                    <label for="category-filter" class="filter-label">Category</label>
                    <select id="category-filter" class="form-select">
                        <option value="">All Categories</option>
                        <option value="meeting">Meeting</option>
                        <option value="workshop">Workshop</option>
                        <option value="conference">Conference</option>
                        <option value="training">Training</option>
                        <option value="seminar">Seminar</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <!-- Date Range Filter -->
                <div class="filter-group">
                    <label for="date-range-filter" class="filter-label">Date Range</label>
                    <select id="date-range-filter" class="form-select">
                        <option value="">All Dates</option>
                        <option value="today">Today</option>
                        <option value="tomorrow">Tomorrow</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="quarter">This Quarter</option>
                        <option value="past">Past Events</option>
                    </select>
                </div>
                
                <!-- Location Filter -->
                <div class="filter-group">
                    <label for="location-filter" class="filter-label">Location</label>
                    <select id="location-filter" class="form-select">
                        <option value="">All Locations</option>
                        <!-- Populated dynamically -->
                    </select>
                </div>
                
                <!-- Reset Filters -->
                <div class="filter-group">
                    <button type="button" class="btn btn-outline btn-sm" id="reset-filters">
                        <i class="fas fa-undo"></i>
                        Reset
                    </button>
                </div>
                
            </div>
        </div>
        
        <!-- Bulk Actions Bar -->
        <div class="bulk-actions-bar" id="bulk-actions-bar" style="display: none;">
            <div class="bulk-info">
                <span class="selected-count">0</span> events selected
            </div>
            <div class="bulk-actions">
                <button type="button" class="btn btn-sm btn-outline" data-bulk-action="publish">
                    <i class="fas fa-eye"></i>
                    Publish
                </button>
                <button type="button" class="btn btn-sm btn-outline" data-bulk-action="duplicate">
                    <i class="fas fa-copy"></i>
                    Duplicate
                </button>
                <button type="button" class="btn btn-sm btn-outline" data-bulk-action="export">
                    <i class="fas fa-download"></i>
                    Export
                </button>
                <button type="button" class="btn btn-sm btn-outline" data-bulk-action="cancel">
                    <i class="fas fa-ban"></i>
                    Cancel
                </button>
                <button type="button" class="btn btn-sm btn-danger" data-bulk-action="delete">
                    <i class="fas fa-trash"></i>
                    Delete
                </button>
            </div>
            <div class="bulk-close">
                <button type="button" class="btn btn-sm btn-ghost" id="clear-selection">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <!-- Events Data Table -->
        <div class="admin-table-container">
            <div class="table-wrapper">
                <table class="data-table" id="events-table">
                    <thead>
                        <tr>
                            <th class="checkbox-column">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="select-all" class="checkbox-input">
                                    <span class="checkbox-custom"></span>
                                </label>
                            </th>
                            <th class="sortable" data-sort="event_id">
                                ID
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="title">
                                Event
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="category">
                                Category
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="start_datetime">
                                Date & Time
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="location">
                                Location
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="status">
                                Status
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="attendee_count">
                                Attendees
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="created_at">
                                Created
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="actions-column">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="events-table-body">
                        <!-- Table rows will be loaded here -->
                        <tr class="loading-row">
                            <td colspan="10" class="text-center">
                                <div class="loading-spinner">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <span>Loading events...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Table Footer -->
            <div class="table-footer">
                <div class="table-info">
                    <span class="results-info" id="results-info">
                        Showing 0 to 0 of 0 events
                    </span>
                </div>
                
                <div class="table-pagination">
                    <div class="pagination-info">
                        <select id="per-page" class="form-select pagination-select">
                            <option value="10">10 per page</option>
                            <option value="25" selected>25 per page</option>
                            <option value="50">50 per page</option>
                            <option value="100">100 per page</option>
                        </select>
                    </div>
                    
                    <nav class="pagination" id="pagination">
                        <!-- Pagination will be generated here -->
                    </nav>
                </div>
            </div>
        </div>
        
    </div>
    
    <!-- Calendar View Container -->
    <div class="view-container" id="calendar-view" style="display: none;">
        
        <!-- Calendar Header -->
        <div class="calendar-header">
            <div class="calendar-nav">
                <button type="button" class="btn btn-ghost" id="prev-month">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <h2 class="calendar-title" id="calendar-title">
                    <span id="current-month">January</span>
                    <span id="current-year">2024</span>
                </h2>
                <button type="button" class="btn btn-ghost" id="next-month">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            
            <div class="calendar-actions">
                <button type="button" class="btn btn-outline btn-sm" id="today-btn">
                    <i class="fas fa-calendar-day"></i>
                    Today
                </button>
                <div class="view-mode-selector">
                    <select id="calendar-mode" class="form-select">
                        <option value="month">Month View</option>
                        <option value="week">Week View</option>
                        <option value="day">Day View</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Calendar Grid -->
        <div class="calendar-container">
            <div class="calendar-grid" id="calendar-grid">
                <!-- Calendar will be rendered here -->
            </div>
        </div>
        
    </div>
    
    <!-- Grid View Container -->
    <div class="view-container" id="grid-view" style="display: none;">
        
        <!-- Grid Filters -->
        <div class="grid-filters">
            <div class="filter-group">
                <select id="grid-sort" class="form-select">
                    <option value="start_datetime">Sort by Date</option>
                    <option value="title">Sort by Title</option>
                    <option value="status">Sort by Status</option>
                    <option value="attendee_count">Sort by Attendees</option>
                </select>
            </div>
            <div class="filter-group">
                <select id="grid-filter" class="form-select">
                    <option value="">All Events</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="today">Today</option>
                    <option value="this-week">This Week</option>
                </select>
            </div>
        </div>
        
        <!-- Event Cards Grid -->
        <div class="events-grid" id="events-grid">
            <!-- Event cards will be rendered here -->
        </div>
        
    </div>
    
</div>

<!-- Event Details Modal -->
<div class="modal" id="event-details-modal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-calendar-alt"></i>
                    Event Details
                </h3>
                <button type="button" class="modal-close" data-modal-close>
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="event-details-content">
                <!-- Event details will be loaded here -->
                <div class="loading-content">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Loading event details...</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Close</button>
                <button type="button" class="btn btn-outline" id="manage-attendees-btn">
                    <i class="fas fa-users"></i>
                    Manage Attendees
                </button>
                <button type="button" class="btn btn-primary" id="edit-event-btn">
                    <i class="fas fa-edit"></i>
                    Edit Event
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Attendees Modal -->
<div class="modal" id="attendees-modal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-users"></i>
                    Event Attendees
                </h3>
                <button type="button" class="modal-close" data-modal-close>
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="attendees-content">
                <!-- Attendees list will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Close</button>
                <button type="button" class="btn btn-outline" id="export-attendees-btn">
                    <i class="fas fa-download"></i>
                    Export List
                </button>
                <button type="button" class="btn btn-primary" id="add-attendee-btn">
                    <i class="fas fa-user-plus"></i>
                    Add Attendee
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Quick Event Creation Modal -->
<div class="modal" id="quick-event-modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-plus"></i>
                    Quick Event Creation
                </h3>
                <button type="button" class="modal-close" data-modal-close>
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="quick-event-form" class="form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="quick-title" class="form-label">Event Title</label>
                            <input type="text" id="quick-title" name="title" class="form-input" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="quick-start-date" class="form-label">Start Date</label>
                            <input type="date" id="quick-start-date" name="start_date" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label for="quick-start-time" class="form-label">Start Time</label>
                            <input type="time" id="quick-start-time" name="start_time" class="form-input" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="quick-end-date" class="form-label">End Date</label>
                            <input type="date" id="quick-end-date" name="end_date" class="form-input">
                        </div>
                        <div class="form-group">
                            <label for="quick-end-time" class="form-label">End Time</label>
                            <input type="time" id="quick-end-time" name="end_time" class="form-input">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="quick-location" class="form-label">Location</label>
                            <input type="text" id="quick-location" name="location" class="form-input">
                        </div>
                        <div class="form-group">
                            <label for="quick-category" class="form-label">Category</label>
                            <select id="quick-category" name="category" class="form-select">
                                <option value="meeting">Meeting</option>
                                <option value="workshop">Workshop</option>
                                <option value="conference">Conference</option>
                                <option value="training">Training</option>
                                <option value="seminar">Seminar</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="quick-description" class="form-label">Description</label>
                        <textarea id="quick-description" name="description" class="form-textarea" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="button" class="btn btn-outline" id="save-draft-btn">
                    <i class="fas fa-save"></i>
                    Save as Draft
                </button>
                <button type="button" class="btn btn-primary" id="create-event-btn">
                    <i class="fas fa-plus"></i>
                    Create Event
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Event Management JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const eventManager = new EventManager();
    eventManager.init();
});

class EventManager {
    constructor() {
        this.currentView = 'list';
        this.currentPage = 1;
        this.perPage = 25;
        this.sortField = 'start_datetime';
        this.sortDirection = 'desc';
        this.filters = {};
        this.selectedEvents = new Set();
        this.searchTimeout = null;
        this.currentEventId = null;
        
        // Calendar properties
        this.currentCalendarDate = new Date();
        this.calendarEvents = [];
    }
    
    init() {
        this.setupEventListeners();
        this.setupViewToggle();
        this.loadEvents();
        this.loadLocations();
    }
    
    setupEventListeners() {
        // Search
        const searchInput = document.getElementById('event-search');
        const searchClear = document.getElementById('search-clear');
        
        searchInput.addEventListener('input', (e) => {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.handleSearch(e.target.value);
            }, 300);
        });
        
        searchClear.addEventListener('click', () => {
            searchInput.value = '';
            this.handleSearch('');
        });
        
        // Filters
        const filters = ['status-filter', 'category-filter', 'date-range-filter', 'location-filter'];
        filters.forEach(filterId => {
            const element = document.getElementById(filterId);
            if (element) {
                element.addEventListener('change', () => {
                    this.handleFilterChange();
                });
            }
        });
        
        // Reset filters
        document.getElementById('reset-filters').addEventListener('click', () => {
            this.resetFilters();
        });
        
        // Table sorting
        document.querySelectorAll('.sortable').forEach(header => {
            header.addEventListener('click', () => {
                this.handleSort(header.dataset.sort);
            });
        });
        
        // Select all checkbox
        document.getElementById('select-all').addEventListener('change', (e) => {
            this.handleSelectAll(e.target.checked);
        });
        
        // Pagination
        document.getElementById('per-page').addEventListener('change', (e) => {
            this.perPage = parseInt(e.target.value);
            this.currentPage = 1;
            this.loadEvents();
        });
        
        // Bulk actions
        document.querySelectorAll('[data-bulk-action]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.handleBulkAction(e.target.dataset.bulkAction);
            });
        });
        
        // Clear selection
        document.getElementById('clear-selection').addEventListener('click', () => {
            this.clearSelection();
        });
        
        // Calendar navigation
        document.getElementById('prev-month').addEventListener('click', () => {
            this.navigateCalendar(-1);
        });
        
        document.getElementById('next-month').addEventListener('click', () => {
            this.navigateCalendar(1);
        });
        
        document.getElementById('today-btn').addEventListener('click', () => {
            this.goToToday();
        });
        
        document.getElementById('calendar-mode').addEventListener('change', (e) => {
            this.changeCalendarMode(e.target.value);
        });
        
        // Modal actions
        document.getElementById('edit-event-btn').addEventListener('click', () => {
            this.editCurrentEvent();
        });
        
        document.getElementById('manage-attendees-btn').addEventListener('click', () => {
            this.showAttendees();
        });
        
        // Quick event creation
        document.getElementById('create-event-btn').addEventListener('click', () => {
            this.createQuickEvent();
        });
        
        document.getElementById('save-draft-btn').addEventListener('click', () => {
            this.createQuickEvent(true);
        });
        
        // Sync calendar
        document.getElementById('sync-calendar').addEventListener('click', () => {
            this.syncCalendar();
        });
    }
    
    setupViewToggle() {
        document.querySelectorAll('.toggle-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const view = e.target.dataset.view;
                this.switchView(view);
            });
        });
    }
    
    switchView(view) {
        // Update active toggle button
        document.querySelectorAll('.toggle-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-view="${view}"]`).classList.add('active');
        
        // Hide all view containers
        document.querySelectorAll('.view-container').forEach(container => {
            container.style.display = 'none';
        });
        
        // Show selected view
        document.getElementById(`${view}-view`).style.display = 'block';
        
        this.currentView = view;
        
        // Load appropriate data
        if (view === 'calendar') {
            this.loadCalendar();
        } else if (view === 'grid') {
            this.loadGrid();
        }
    }
    
    async loadEvents() {
        if (this.currentView !== 'list') return;
        
        try {
            this.showTableLoading();
            
            const params = new URLSearchParams({
                page: this.currentPage,
                per_page: this.perPage,
                sort: this.sortField,
                direction: this.sortDirection,
                ...this.filters
            });
            
            const response = await fetch(`/api/admin/events?${params}`, {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.renderEvents(data.data.events);
                this.renderPagination(data.data.pagination);
                this.updateResultsInfo(data.data.pagination);
                this.updateTotalCount(data.data.pagination.total);
            } else {
                this.showError('Failed to load events: ' + data.message);
            }
        } catch (error) {
            console.error('Error loading events:', error);
            this.showError('Failed to load events. Please try again.');
        }
    }
    
    renderEvents(events) {
        const tbody = document.getElementById('events-table-body');
        
        if (events.length === 0) {
            tbody.innerHTML = `
                <tr class="no-data-row">
                    <td colspan="10" class="text-center">
                        <div class="no-data">
                            <i class="fas fa-calendar-alt"></i>
                            <h3>No Events Found</h3>
                            <p>No events match your current filters.</p>
                            <button type="button" class="btn btn-primary" onclick="window.location.href='/admin/events/create'">
                                <i class="fas fa-plus"></i>
                                Create First Event
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = events.map(event => `
            <tr class="table-row" data-event-id="${event.event_id}">
                <td class="checkbox-column">
                    <label class="checkbox-label">
                        <input type="checkbox" 
                               class="checkbox-input event-checkbox" 
                               value="${event.event_id}"
                               ${this.selectedEvents.has(event.event_id) ? 'checked' : ''}>
                        <span class="checkbox-custom"></span>
                    </label>
                </td>
                <td class="event-id">${event.event_id}</td>
                <td class="event-info">
                    <div class="event-details">
                        <div class="event-title">${this.escapeHtml(event.title)}</div>
                        <div class="event-subtitle">${this.escapeHtml(event.description || '').substring(0, 80)}${event.description && event.description.length > 80 ? '...' : ''}</div>
                    </div>
                </td>
                <td class="event-category">
                    <span class="category-badge category-${event.category}">
                        <i class="fas fa-${this.getCategoryIcon(event.category)}"></i>
                        ${this.capitalizeFirst(event.category)}
                    </span>
                </td>
                <td class="event-datetime">
                    <div class="datetime-info">
                        <div class="start-date">${this.formatEventDate(event.start_datetime)}</div>
                        <div class="time-range">${this.formatTimeRange(event.start_datetime, event.end_datetime)}</div>
                        ${event.is_all_day ? '<span class="all-day-badge">All Day</span>' : ''}
                    </div>
                </td>
                <td class="event-location">
                    ${event.location ? 
                        `<span class="location-info">
                            <i class="fas fa-map-marker-alt"></i>
                            ${this.escapeHtml(event.location)}
                        </span>` :
                        `<span class="no-location">Not specified</span>`
                    }
                </td>
                <td class="event-status">
                    <span class="status-badge status-${event.status}">
                        <i class="fas fa-${this.getStatusIcon(event.status)}"></i>
                        ${this.capitalizeFirst(event.status)}
                    </span>
                </td>
                <td class="attendee-count">
                    <div class="attendee-info">
                        <span class="count">${event.attendee_count || 0}</span>
                        ${event.max_attendees ? 
                            `<span class="max">/ ${event.max_attendees}</span>` : 
                            `<span class="unlimited">unlimited</span>`
                        }
                    </div>
                </td>
                <td class="created-date">
                    <span class="creation-time" title="${event.created_at}">
                        ${this.formatDate(event.created_at)}
                    </span>
                </td>
                <td class="actions-column">
                    <div class="action-buttons">
                        <button type="button" 
                                class="btn btn-sm btn-ghost action-btn" 
                                onclick="eventManager.viewEvent(${event.event_id})"
                                title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button type="button" 
                                class="btn btn-sm btn-ghost action-btn" 
                                onclick="eventManager.editEvent(${event.event_id})"
                                title="Edit Event">
                            <i class="fas fa-edit"></i>
                        </button>
                        <div class="dropdown action-dropdown">
                            <button type="button" 
                                    class="btn btn-sm btn-ghost dropdown-toggle" 
                                    data-dropdown="event-actions-${event.event_id}">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu" id="event-actions-${event.event_id}">
                                <button type="button" class="dropdown-item" onclick="eventManager.duplicateEvent(${event.event_id})">
                                    <i class="fas fa-copy"></i>
                                    Duplicate Event
                                </button>
                                <button type="button" class="dropdown-item" onclick="eventManager.manageAttendees(${event.event_id})">
                                    <i class="fas fa-users"></i>
                                    Manage Attendees
                                </button>
                                <button type="button" class="dropdown-item" onclick="eventManager.viewReports(${event.event_id})">
                                    <i class="fas fa-chart-bar"></i>
                                    View Reports
                                </button>
                                <div class="dropdown-divider"></div>
                                ${event.status === 'published' ? 
                                    `<button type="button" class="dropdown-item" onclick="eventManager.cancelEvent(${event.event_id})">
                                        <i class="fas fa-ban"></i>
                                        Cancel Event
                                    </button>` :
                                    `<button type="button" class="dropdown-item" onclick="eventManager.publishEvent(${event.event_id})">
                                        <i class="fas fa-eye"></i>
                                        Publish Event
                                    </button>`
                                }
                                <button type="button" class="dropdown-item text-danger" onclick="eventManager.deleteEvent(${event.event_id})">
                                    <i class="fas fa-trash"></i>
                                    Delete Event
                                </button>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
        `).join('');
        
        // Setup row checkboxes
        this.setupRowCheckboxes();
        
        // Setup dropdowns
        this.setupDropdowns();
    }
    
    // Calendar functionality
    async loadCalendar() {
        try {
            const year = this.currentCalendarDate.getFullYear();
            const month = this.currentCalendarDate.getMonth();
            
            const response = await fetch(`/api/admin/events/calendar?year=${year}&month=${month + 1}`, {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.calendarEvents = data.data.events;
                this.renderCalendar();
            } else {
                this.showError('Failed to load calendar: ' + data.message);
            }
        } catch (error) {
            console.error('Error loading calendar:', error);
            this.showError('Failed to load calendar.');
        }
    }
    
    renderCalendar() {
        const grid = document.getElementById('calendar-grid');
        const monthEl = document.getElementById('current-month');
        const yearEl = document.getElementById('current-year');
        
        // Update header
        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        
        monthEl.textContent = monthNames[this.currentCalendarDate.getMonth()];
        yearEl.textContent = this.currentCalendarDate.getFullYear();
        
        // Generate calendar
        const year = this.currentCalendarDate.getFullYear();
        const month = this.currentCalendarDate.getMonth();
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startDate = new Date(firstDay);
        startDate.setDate(startDate.getDate() - firstDay.getDay());
        
        let calendarHTML = `
            <div class="calendar-header-row">
                <div class="calendar-day-header">Sun</div>
                <div class="calendar-day-header">Mon</div>
                <div class="calendar-day-header">Tue</div>
                <div class="calendar-day-header">Wed</div>
                <div class="calendar-day-header">Thu</div>
                <div class="calendar-day-header">Fri</div>
                <div class="calendar-day-header">Sat</div>
            </div>
        `;
        
        const currentDate = new Date(startDate);
        const today = new Date();
        
        for (let week = 0; week < 6; week++) {
            calendarHTML += '<div class="calendar-week-row">';
            
            for (let day = 0; day < 7; day++) {
                const dateStr = currentDate.toISOString().split('T')[0];
                const dayEvents = this.calendarEvents.filter(event => 
                    event.start_datetime.startsWith(dateStr)
                );
                
                const isCurrentMonth = currentDate.getMonth() === month;
                const isToday = currentDate.toDateString() === today.toDateString();
                
                calendarHTML += `
                    <div class="calendar-day ${isCurrentMonth ? 'current-month' : 'other-month'} ${isToday ? 'today' : ''}" 
                         data-date="${dateStr}">
                        <div class="day-number">${currentDate.getDate()}</div>
                        <div class="day-events">
                            ${dayEvents.slice(0, 3).map(event => `
                                <div class="calendar-event ${event.status}" onclick="eventManager.viewEvent(${event.event_id})">
                                    <span class="event-time">${this.formatTime(event.start_datetime)}</span>
                                    <span class="event-title">${this.escapeHtml(event.title)}</span>
                                </div>
                            `).join('')}
                            ${dayEvents.length > 3 ? `
                                <div class="calendar-event-more" onclick="eventManager.showDayEvents('${dateStr}')">
                                    +${dayEvents.length - 3} more
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
                
                currentDate.setDate(currentDate.getDate() + 1);
            }
            
            calendarHTML += '</div>';
            
            // Stop if we've passed the end of the month and completed a full week
            if (currentDate.getMonth() !== month && day === 6) {
                break;
            }
        }
        
        grid.innerHTML = calendarHTML;
        
        // Add click handlers for empty days to create events
        document.querySelectorAll('.calendar-day').forEach(day => {
            day.addEventListener('dblclick', (e) => {
                if (e.target.classList.contains('calendar-day')) {
                    this.createEventForDate(day.dataset.date);
                }
            });
        });
    }
    
    navigateCalendar(direction) {
        this.currentCalendarDate.setMonth(this.currentCalendarDate.getMonth() + direction);
        this.loadCalendar();
    }
    
    goToToday() {
        this.currentCalendarDate = new Date();
        this.loadCalendar();
    }
    
    changeCalendarMode(mode) {
        // Implementation for different calendar modes
        console.log('Calendar mode changed to:', mode);
        // This would implement week/day views
    }
    
    // Grid view functionality
    async loadGrid() {
        try {
            const response = await fetch('/api/admin/events/grid', {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.renderGrid(data.data.events);
            } else {
                this.showError('Failed to load grid view: ' + data.message);
            }
        } catch (error) {
            console.error('Error loading grid:', error);
            this.showError('Failed to load grid view.');
        }
    }
    
    renderGrid(events) {
        const grid = document.getElementById('events-grid');
        
        if (events.length === 0) {
            grid.innerHTML = `
                <div class="no-events-grid">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>No Events</h3>
                    <p>Create your first event to get started.</p>
                    <button type="button" class="btn btn-primary" onclick="window.location.href='/admin/events/create'">
                        <i class="fas fa-plus"></i>
                        Create Event
                    </button>
                </div>
            `;
            return;
        }
        
        grid.innerHTML = events.map(event => `
            <div class="event-card ${event.status}" data-event-id="${event.event_id}">
                <div class="event-card-header">
                    <div class="event-category">
                        <i class="fas fa-${this.getCategoryIcon(event.category)}"></i>
                        ${this.capitalizeFirst(event.category)}
                    </div>
                    <div class="event-status">
                        <span class="status-badge status-${event.status}">
                            ${this.capitalizeFirst(event.status)}
                        </span>
                    </div>
                </div>
                
                <div class="event-card-content">
                    <h3 class="event-title">${this.escapeHtml(event.title)}</h3>
                    <p class="event-description">${this.escapeHtml(event.description || '').substring(0, 120)}${event.description && event.description.length > 120 ? '...' : ''}</p>
                    
                    <div class="event-details">
                        <div class="event-datetime">
                            <i class="fas fa-calendar"></i>
                            <span>${this.formatEventDate(event.start_datetime)}</span>
                        </div>
                        <div class="event-time">
                            <i class="fas fa-clock"></i>
                            <span>${this.formatTimeRange(event.start_datetime, event.end_datetime)}</span>
                        </div>
                        ${event.location ? `
                            <div class="event-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>${this.escapeHtml(event.location)}</span>
                            </div>
                        ` : ''}
                        <div class="event-attendees">
                            <i class="fas fa-users"></i>
                            <span>${event.attendee_count || 0} attendees</span>
                        </div>
                    </div>
                </div>
                
                <div class="event-card-actions">
                    <button type="button" class="btn btn-sm btn-outline" onclick="eventManager.viewEvent(${event.event_id})">
                        <i class="fas fa-eye"></i>
                        View
                    </button>
                    <button type="button" class="btn btn-sm btn-outline" onclick="eventManager.editEvent(${event.event_id})">
                        <i class="fas fa-edit"></i>
                        Edit
                    </button>
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-ghost dropdown-toggle" data-dropdown="card-actions-${event.event_id}">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu" id="card-actions-${event.event_id}">
                            <button type="button" class="dropdown-item" onclick="eventManager.duplicateEvent(${event.event_id})">
                                <i class="fas fa-copy"></i>
                                Duplicate
                            </button>
                            <button type="button" class="dropdown-item" onclick="eventManager.manageAttendees(${event.event_id})">
                                <i class="fas fa-users"></i>
                                Attendees
                            </button>
                            <div class="dropdown-divider"></div>
                            <button type="button" class="dropdown-item text-danger" onclick="eventManager.deleteEvent(${event.event_id})">
                                <i class="fas fa-trash"></i>
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
        
        // Setup dropdowns for grid cards
        this.setupDropdowns();
    }
    
    // Event actions
    async viewEvent(eventId) {
        try {
            const modal = document.getElementById('event-details-modal');
            const content = document.getElementById('event-details-content');
            
            // Show modal with loading state
            content.innerHTML = `
                <div class="loading-content">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Loading event details...</span>
                </div>
            `;
            
            this.showModal('event-details-modal');
            
            const response = await fetch(`/api/admin/events/${eventId}`, {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.renderEventDetails(data.data);
                this.currentEventId = eventId;
            } else {
                this.showError('Failed to load event details: ' + data.message);
                this.hideModal('event-details-modal');
            }
        } catch (error) {
            console.error('Error viewing event:', error);
            this.showError('Failed to load event details.');
            this.hideModal('event-details-modal');
        }
    }
    
    renderEventDetails(event) {
        const content = document.getElementById('event-details-content');
        
        content.innerHTML = `
            <div class="event-details">
                <div class="event-header">
                    <div class="event-header-info">
                        <h2 class="event-title">${this.escapeHtml(event.title)}</h2>
                        <div class="event-meta">
                            <span class="category-badge category-${event.category}">
                                <i class="fas fa-${this.getCategoryIcon(event.category)}"></i>
                                ${this.capitalizeFirst(event.category)}
                            </span>
                            <span class="status-badge status-${event.status}">
                                <i class="fas fa-${this.getStatusIcon(event.status)}"></i>
                                ${this.capitalizeFirst(event.status)}
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="event-info-grid">
                    <div class="info-section">
                        <h3>Event Information</h3>
                        <div class="info-items">
                            <div class="info-item">
                                <label>Description</label>
                                <span>${this.escapeHtml(event.description) || 'No description provided'}</span>
                            </div>
                            <div class="info-item">
                                <label>Category</label>
                                <span>${this.capitalizeFirst(event.category)}</span>
                            </div>
                            <div class="info-item">
                                <label>Status</label>
                                <span class="status-${event.status}">${this.capitalizeFirst(event.status)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <h3>Date & Time</h3>
                        <div class="info-items">
                            <div class="info-item">
                                <label>Start Date & Time</label>
                                <span>${this.formatDateTime(event.start_datetime)}</span>
                            </div>
                            <div class="info-item">
                                <label>End Date & Time</label>
                                <span>${event.end_datetime ? this.formatDateTime(event.end_datetime) : 'Not specified'}</span>
                            </div>
                            <div class="info-item">
                                <label>Duration</label>
                                <span>${this.calculateDuration(event.start_datetime, event.end_datetime)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <h3>Location & Capacity</h3>
                        <div class="info-items">
                            <div class="info-item">
                                <label>Location</label>
                                <span>${event.location || 'Not specified'}</span>
                            </div>
                            <div class="info-item">
                                <label>Maximum Attendees</label>
                                <span>${event.max_attendees || 'Unlimited'}</span>
                            </div>
                            <div class="info-item">
                                <label>Current Attendees</label>
                                <span>${event.attendee_count || 0}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <h3>Management</h3>
                        <div class="info-items">
                            <div class="info-item">
                                <label>Created By</label>
                                <span>${event.created_by_name || 'Unknown'}</span>
                            </div>
                            <div class="info-item">
                                <label>Created Date</label>
                                <span>${this.formatDateTime(event.created_at)}</span>
                            </div>
                            <div class="info-item">
                                <label>Last Updated</label>
                                <span>${this.formatDateTime(event.updated_at)}</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                ${event.recent_checkins && event.recent_checkins.length > 0 ? `
                    <div class="info-section">
                        <h3>Recent Check-ins</h3>
                        <div class="recent-checkins">
                            ${event.recent_checkins.map(checkin => `
                                <div class="checkin-item">
                                    <div class="checkin-user">
                                        <span class="user-name">${this.escapeHtml(checkin.user_name)}</span>
                                        <span class="checkin-time">${this.formatRelativeTime(checkin.checkin_time)}</span>
                                    </div>
                                    <div class="checkin-method">
                                        <i class="fas fa-${checkin.method === 'rfid' ? 'id-card' : 'hand-pointer'}"></i>
                                        ${this.capitalizeFirst(checkin.method)}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
    }
    
    editEvent(eventId) {
        window.location.href = `/admin/events/${eventId}/edit`;
    }
    
    editCurrentEvent() {
        if (this.currentEventId) {
            this.editEvent(this.currentEventId);
        }
    }
    
    // Utility methods
    getCategoryIcon(category) {
        const icons = {
            'meeting': 'users',
            'workshop': 'tools',
            'conference': 'microphone',
            'training': 'graduation-cap',
            'seminar': 'chalkboard-teacher',
            'other': 'calendar'
        };
        return icons[category] || 'calendar';
    }
    
    getStatusIcon(status) {
        const icons = {
            'draft': 'edit',
            'published': 'eye',
            'active': 'play',
            'completed': 'check',
            'cancelled': 'ban'
        };
        return icons[status] || 'question';
    }
    
    formatEventDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            weekday: 'short',
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }
    
    formatTimeRange(start, end) {
        const startTime = new Date(start).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit'
        });
        
        if (!end) return startTime;
        
        const endTime = new Date(end).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit'
        });
        
        return `${startTime} - ${endTime}`;
    }
    
    formatTime(dateString) {
        return new Date(dateString).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit'
        });
    }
    
    calculateDuration(start, end) {
        if (!end) return 'Not specified';
        
        const startDate = new Date(start);
        const endDate = new Date(end);
        const diffMs = endDate - startDate;
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
        
        if (diffHours > 0) {
            return `${diffHours}h ${diffMinutes}m`;
        } else {
            return `${diffMinutes}m`;
        }
    }
    
    // ... Additional utility methods similar to UserManager
    
    capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    formatNumber(num) {
        return new Intl.NumberFormat().format(num);
    }
    
    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString();
    }
    
    formatDateTime(dateString) {
        return new Date(dateString).toLocaleString();
    }
    
    formatRelativeTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);
        
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;
        
        return date.toLocaleDateString();
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('modal-show');
            document.body.classList.add('modal-open');
        }
    }
    
    hideModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('modal-show');
            document.body.classList.remove('modal-open');
        }
    }
    
    showSuccess(message) {
        if (typeof window.addFlashMessage === 'function') {
            window.addFlashMessage('success', message);
        }
    }
    
    showError(message) {
        if (typeof window.addFlashMessage === 'function') {
            window.addFlashMessage('error', message);
        }
    }
}

// Expose eventManager globally
window.eventManager = null;
document.addEventListener('DOMContentLoaded', function() {
    window.eventManager = new EventManager();
});
</script>
