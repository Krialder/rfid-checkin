<?php
$page_title = 'RFID Management';
$page_description = 'Manage RFID tags, devices, and scanning operations';
$breadcrumbs = [
    ['title' => 'Administration', 'url' => '/admin', 'icon' => 'fas fa-shield-alt'],
    ['title' => 'RFID Management', 'url' => '/admin/rfid', 'icon' => 'fas fa-id-card']
];
$current_page = 'admin-rfid';

// Page-specific assets
$assets = [
    'css' => ['admin.css', 'rfid.css', 'data-tables.css', 'modals.css'],
    'js' => ['admin-rfid.js', 'rfid-scanner.js', 'data-tables.js', 'bulk-actions.js']
];

// Page actions
$page_actions = [
    ['title' => 'Scan New Tag', 'url' => '#', 'type' => 'primary', 'icon' => 'fas fa-wifi', 'onclick' => 'rfidManager.startScanning()'],
    ['title' => 'Import Tags', 'url' => '/admin/rfid/import', 'type' => 'outline', 'icon' => 'fas fa-upload'],
    ['title' => 'Export Tags', 'url' => '/admin/rfid/export', 'type' => 'outline', 'icon' => 'fas fa-download']
];
?>

<!-- RFID Management Interface -->
<div class="admin-content">
    
    <!-- Page Header -->
    <div class="admin-header">
        <div class="header-content">
            <div class="header-info">
                <h1 class="page-title">
                    <i class="fas fa-id-card"></i>
                    RFID Management
                </h1>
                <p class="page-description">
                    Manage RFID tags, scanning devices, and tag assignments.
                    <span class="tag-count">Total tags: <strong id="total-tags-count"><?= number_format($stats['total_tags'] ?? 0) ?></strong></span>
                </p>
            </div>
            
            <div class="header-stats">
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['assigned_tags'] ?? 0) ?></div>
                    <div class="stat-label">Assigned</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['unassigned_tags'] ?? 0) ?></div>
                    <div class="stat-label">Unassigned</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['active_devices'] ?? 0) ?></div>
                    <div class="stat-label">Devices</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- RFID Scanner Status -->
    <div class="scanner-status-bar" id="scanner-status">
        <div class="status-indicator offline" id="scanner-indicator">
            <i class="fas fa-circle"></i>
        </div>
        <div class="status-text">
            <span class="status-label">Scanner Status:</span>
            <span class="status-value" id="scanner-status-text">Offline</span>
        </div>
        <div class="scanner-actions">
            <button type="button" class="btn btn-sm btn-outline" id="connect-scanner">
                <i class="fas fa-plug"></i>
                Connect Scanner
            </button>
            <button type="button" class="btn btn-sm btn-outline" id="test-scanner" disabled>
                <i class="fas fa-vial"></i>
                Test Scanner
            </button>
        </div>
    </div>
    
    <!-- Tab Navigation -->
    <div class="tab-navigation">
        <button type="button" class="tab-btn active" data-tab="tags">
            <i class="fas fa-tags"></i>
            RFID Tags
        </button>
        <button type="button" class="tab-btn" data-tab="devices">
            <i class="fas fa-wifi"></i>
            Devices
        </button>
        <button type="button" class="tab-btn" data-tab="assignments">
            <i class="fas fa-user-tag"></i>
            Assignments
        </button>
        <button type="button" class="tab-btn" data-tab="activity">
            <i class="fas fa-history"></i>
            Activity Log
        </button>
    </div>
    
    <!-- RFID Tags Tab -->
    <div class="tab-content active" id="tags-tab">
        
        <!-- Filters and Search -->
        <div class="admin-filters">
            <div class="filters-row">
                
                <!-- Search -->
                <div class="filter-group search-group">
                    <div class="search-input-wrapper">
                        <input type="text" 
                               id="tag-search" 
                               class="form-input search-input" 
                               placeholder="Search tags by ID, user, or status..."
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
                    <label for="tag-status-filter" class="filter-label">Status</label>
                    <select id="tag-status-filter" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="assigned">Assigned</option>
                        <option value="unassigned">Unassigned</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="lost">Lost/Stolen</option>
                    </select>
                </div>
                
                <!-- User Type Filter -->
                <div class="filter-group">
                    <label for="user-type-filter" class="filter-label">User Type</label>
                    <select id="user-type-filter" class="form-select">
                        <option value="">All Users</option>
                        <option value="1">Administrator</option>
                        <option value="2">Moderator</option>
                        <option value="3">User</option>
                    </select>
                </div>
                
                <!-- Date Filter -->
                <div class="filter-group">
                    <label for="tag-date-filter" class="filter-label">Last Used</label>
                    <select id="tag-date-filter" class="form-select">
                        <option value="">All Time</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="never">Never Used</option>
                    </select>
                </div>
                
                <!-- Reset Filters -->
                <div class="filter-group">
                    <button type="button" class="btn btn-outline btn-sm" id="reset-tag-filters">
                        <i class="fas fa-undo"></i>
                        Reset
                    </button>
                </div>
                
            </div>
        </div>
        
        <!-- Bulk Actions Bar -->
        <div class="bulk-actions-bar" id="bulk-tags-bar" style="display: none;">
            <div class="bulk-info">
                <span class="selected-count">0</span> tags selected
            </div>
            <div class="bulk-actions">
                <button type="button" class="btn btn-sm btn-outline" data-bulk-action="assign">
                    <i class="fas fa-user-plus"></i>
                    Assign Users
                </button>
                <button type="button" class="btn btn-sm btn-outline" data-bulk-action="unassign">
                    <i class="fas fa-user-minus"></i>
                    Unassign
                </button>
                <button type="button" class="btn btn-sm btn-outline" data-bulk-action="activate">
                    <i class="fas fa-check"></i>
                    Activate
                </button>
                <button type="button" class="btn btn-sm btn-outline" data-bulk-action="deactivate">
                    <i class="fas fa-ban"></i>
                    Deactivate
                </button>
                <button type="button" class="btn btn-sm btn-danger" data-bulk-action="delete">
                    <i class="fas fa-trash"></i>
                    Delete
                </button>
            </div>
            <div class="bulk-close">
                <button type="button" class="btn btn-sm btn-ghost" id="clear-tag-selection">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <!-- Tags Data Table -->
        <div class="admin-table-container">
            <div class="table-wrapper">
                <table class="data-table" id="tags-table">
                    <thead>
                        <tr>
                            <th class="checkbox-column">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="select-all-tags" class="checkbox-input">
                                    <span class="checkbox-custom"></span>
                                </label>
                            </th>
                            <th class="sortable" data-sort="tag_id">
                                Tag ID
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="assigned_user">
                                Assigned User
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="status">
                                Status
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="last_used">
                                Last Used
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="usage_count">
                                Usage Count
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="assigned_date">
                                Assigned Date
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="created_at">
                                Added Date
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="actions-column">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tags-table-body">
                        <!-- Table rows will be loaded here -->
                        <tr class="loading-row">
                            <td colspan="9" class="text-center">
                                <div class="loading-spinner">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <span>Loading RFID tags...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Table Footer -->
            <div class="table-footer">
                <div class="table-info">
                    <span class="results-info" id="tags-results-info">
                        Showing 0 to 0 of 0 tags
                    </span>
                </div>
                
                <div class="table-pagination">
                    <div class="pagination-info">
                        <select id="tags-per-page" class="form-select pagination-select">
                            <option value="10">10 per page</option>
                            <option value="25" selected>25 per page</option>
                            <option value="50">50 per page</option>
                            <option value="100">100 per page</option>
                        </select>
                    </div>
                    
                    <nav class="pagination" id="tags-pagination">
                        <!-- Pagination will be generated here -->
                    </nav>
                </div>
            </div>
        </div>
        
    </div>
    
    <!-- Devices Tab -->
    <div class="tab-content" id="devices-tab">
        
        <!-- Device Status Cards -->
        <div class="devices-grid">
            <!-- Devices will be loaded here -->
            <div class="loading-content">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Loading devices...</span>
            </div>
        </div>
        
        <!-- Add Device Button -->
        <div class="add-device-section">
            <button type="button" class="btn btn-primary" id="add-device-btn">
                <i class="fas fa-plus"></i>
                Add New Device
            </button>
        </div>
        
    </div>
    
    <!-- Assignments Tab -->
    <div class="tab-content" id="assignments-tab">
        
        <!-- Assignment Actions -->
        <div class="assignment-actions">
            <button type="button" class="btn btn-primary" id="bulk-assign-btn">
                <i class="fas fa-users"></i>
                Bulk Assignment
            </button>
            <button type="button" class="btn btn-outline" id="assignment-template-btn">
                <i class="fas fa-download"></i>
                Download Template
            </button>
            <button type="button" class="btn btn-outline" id="import-assignments-btn">
                <i class="fas fa-upload"></i>
                Import Assignments
            </button>
        </div>
        
        <!-- Assignment Statistics -->
        <div class="assignment-stats">
            <div class="stat-card">
                <div class="stat-value" id="total-assignments"><?= number_format($stats['total_assignments'] ?? 0) ?></div>
                <div class="stat-label">Total Assignments</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="recent-assignments"><?= number_format($stats['recent_assignments'] ?? 0) ?></div>
                <div class="stat-label">This Week</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="unassigned-users"><?= number_format($stats['unassigned_users'] ?? 0) ?></div>
                <div class="stat-label">Users Without Tags</div>
            </div>
        </div>
        
        <!-- Recent Assignments -->
        <div class="recent-assignments">
            <h3>Recent Assignments</h3>
            <div class="assignments-list" id="recent-assignments-list">
                <!-- Recent assignments will be loaded here -->
            </div>
        </div>
        
    </div>
    
    <!-- Activity Log Tab -->
    <div class="tab-content" id="activity-tab">
        
        <!-- Activity Filters -->
        <div class="admin-filters">
            <div class="filters-row">
                <div class="filter-group">
                    <label for="activity-type-filter" class="filter-label">Activity Type</label>
                    <select id="activity-type-filter" class="form-select">
                        <option value="">All Activities</option>
                        <option value="scan">Tag Scans</option>
                        <option value="assignment">Assignments</option>
                        <option value="device">Device Events</option>
                        <option value="system">System Events</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="activity-date-filter" class="filter-label">Time Period</label>
                    <select id="activity-date-filter" class="form-select">
                        <option value="">All Time</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Activity Timeline -->
        <div class="activity-timeline" id="activity-timeline">
            <!-- Activity items will be loaded here -->
            <div class="loading-content">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Loading activity log...</span>
            </div>
        </div>
        
    </div>
    
</div>

<!-- RFID Scanner Modal -->
<div class="modal" id="scanner-modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-wifi"></i>
                    RFID Scanner
                </h3>
                <button type="button" class="modal-close" data-modal-close>
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="scanner-interface">
                    <div class="scanner-visual" id="scanner-visual">
                        <div class="scanner-animation">
                            <div class="scan-line"></div>
                            <i class="fas fa-wifi scanner-icon"></i>
                        </div>
                        <div class="scanner-status">
                            <p class="status-text">Place RFID tag near the scanner</p>
                            <p class="status-subtext">Scanner is ready and listening...</p>
                        </div>
                    </div>
                    
                    <div class="scanned-tag-info" id="scanned-tag-info" style="display: none;">
                        <div class="tag-details">
                            <h4>Tag Detected</h4>
                            <div class="tag-id">Tag ID: <span id="detected-tag-id"></span></div>
                            <div class="tag-status-info" id="tag-status-info"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="button" class="btn btn-outline" id="manual-tag-entry">
                    <i class="fas fa-keyboard"></i>
                    Manual Entry
                </button>
                <button type="button" class="btn btn-primary" id="assign-scanned-tag" style="display: none;">
                    <i class="fas fa-user-plus"></i>
                    Assign Tag
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Tag Assignment Modal -->
<div class="modal" id="tag-assignment-modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-user-tag"></i>
                    Assign RFID Tag
                </h3>
                <button type="button" class="modal-close" data-modal-close>
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="tag-assignment-form" class="form">
                    <div class="form-group">
                        <label for="assignment-tag-id" class="form-label">RFID Tag ID</label>
                        <input type="text" id="assignment-tag-id" name="tag_id" class="form-input" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="assignment-user" class="form-label">Select User</label>
                        <select id="assignment-user" name="user_id" class="form-select" required>
                            <option value="">Choose a user...</option>
                            <!-- Users will be populated dynamically -->
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="assignment-notes" class="form-label">Notes (Optional)</label>
                        <textarea id="assignment-notes" name="notes" class="form-textarea" rows="3" placeholder="Assignment notes or comments..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-assignment">
                    <i class="fas fa-check"></i>
                    Assign Tag
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Device Configuration Modal -->
<div class="modal" id="device-config-modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-cog"></i>
                    Device Configuration
                </h3>
                <button type="button" class="modal-close" data-modal-close>
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="device-config-form" class="form">
                    <div class="form-group">
                        <label for="device-name" class="form-label">Device Name</label>
                        <input type="text" id="device-name" name="device_name" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="device-location" class="form-label">Location</label>
                        <input type="text" id="device-location" name="location" class="form-input">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="device-ip" class="form-label">IP Address</label>
                            <input type="text" id="device-ip" name="ip_address" class="form-input" pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$">
                        </div>
                        <div class="form-group">
                            <label for="device-port" class="form-label">Port</label>
                            <input type="number" id="device-port" name="port" class="form-input" min="1" max="65535">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="device-type" class="form-label">Device Type</label>
                        <select id="device-type" name="device_type" class="form-select" required>
                            <option value="">Select device type...</option>
                            <option value="esp32">ESP32 RFID Reader</option>
                            <option value="arduino">Arduino RFID Module</option>
                            <option value="usb">USB RFID Reader</option>
                            <option value="network">Network RFID Reader</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="device-description" class="form-label">Description</label>
                        <textarea id="device-description" name="description" class="form-textarea" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="button" class="btn btn-outline" id="test-device-connection">
                    <i class="fas fa-vial"></i>
                    Test Connection
                </button>
                <button type="button" class="btn btn-primary" id="save-device-config">
                    <i class="fas fa-save"></i>
                    Save Device
                </button>
            </div>
        </div>
    </div>
</div>

<!-- RFID Management JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const rfidManager = new RFIDManager();
    rfidManager.init();
});

class RFIDManager {
    constructor() {
        this.currentTab = 'tags';
        this.currentPage = 1;
        this.perPage = 25;
        this.sortField = 'created_at';
        this.sortDirection = 'desc';
        this.filters = {};
        this.selectedTags = new Set();
        this.searchTimeout = null;
        this.scannerConnected = false;
        this.scannerWebSocket = null;
        this.currentTagId = null;
    }
    
    init() {
        this.setupEventListeners();
        this.setupTabNavigation();
        this.loadTags();
        this.initializeScanner();
    }
    
    setupEventListeners() {
        // Search
        const searchInput = document.getElementById('tag-search');
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
        const filters = ['tag-status-filter', 'user-type-filter', 'tag-date-filter'];
        filters.forEach(filterId => {
            const element = document.getElementById(filterId);
            if (element) {
                element.addEventListener('change', () => {
                    this.handleFilterChange();
                });
            }
        });
        
        // Reset filters
        document.getElementById('reset-tag-filters').addEventListener('click', () => {
            this.resetFilters();
        });
        
        // Table sorting
        document.querySelectorAll('.sortable').forEach(header => {
            header.addEventListener('click', () => {
                this.handleSort(header.dataset.sort);
            });
        });
        
        // Select all checkbox
        document.getElementById('select-all-tags').addEventListener('change', (e) => {
            this.handleSelectAll(e.target.checked);
        });
        
        // Pagination
        document.getElementById('tags-per-page').addEventListener('change', (e) => {
            this.perPage = parseInt(e.target.value);
            this.currentPage = 1;
            this.loadTags();
        });
        
        // Bulk actions
        document.querySelectorAll('[data-bulk-action]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.handleBulkAction(e.target.dataset.bulkAction);
            });
        });
        
        // Clear selection
        document.getElementById('clear-tag-selection').addEventListener('click', () => {
            this.clearSelection();
        });
        
        // Scanner actions
        document.getElementById('connect-scanner').addEventListener('click', () => {
            this.connectScanner();
        });
        
        document.getElementById('test-scanner').addEventListener('click', () => {
            this.testScanner();
        });
        
        // Modal actions
        document.getElementById('assign-scanned-tag').addEventListener('click', () => {
            this.showAssignmentModal();
        });
        
        document.getElementById('confirm-assignment').addEventListener('click', () => {
            this.confirmTagAssignment();
        });
        
        document.getElementById('manual-tag-entry').addEventListener('click', () => {
            this.showManualTagEntry();
        });
        
        // Device management
        document.getElementById('add-device-btn').addEventListener('click', () => {
            this.showDeviceConfig();
        });
        
        document.getElementById('save-device-config').addEventListener('click', () => {
            this.saveDeviceConfig();
        });
        
        document.getElementById('test-device-connection').addEventListener('click', () => {
            this.testDeviceConnection();
        });
    }
    
    setupTabNavigation() {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const tab = e.target.dataset.tab;
                this.switchTab(tab);
            });
        });
    }
    
    switchTab(tab) {
        // Update active tab button
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
        
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        
        // Show selected tab
        document.getElementById(`${tab}-tab`).classList.add('active');
        
        this.currentTab = tab;
        
        // Load appropriate data
        switch (tab) {
            case 'tags':
                this.loadTags();
                break;
            case 'devices':
                this.loadDevices();
                break;
            case 'assignments':
                this.loadAssignments();
                break;
            case 'activity':
                this.loadActivity();
                break;
        }
    }
    
    async loadTags() {
        if (this.currentTab !== 'tags') return;
        
        try {
            this.showTableLoading();
            
            const params = new URLSearchParams({
                page: this.currentPage,
                per_page: this.perPage,
                sort: this.sortField,
                direction: this.sortDirection,
                ...this.filters
            });
            
            const response = await fetch(`/api/admin/rfid/tags?${params}`, {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.renderTags(data.data.tags);
                this.renderPagination(data.data.pagination);
                this.updateResultsInfo(data.data.pagination);
                this.updateTotalCount(data.data.pagination.total);
            } else {
                this.showError('Failed to load RFID tags: ' + data.message);
            }
        } catch (error) {
            console.error('Error loading tags:', error);
            this.showError('Failed to load RFID tags. Please try again.');
        }
    }
    
    renderTags(tags) {
        const tbody = document.getElementById('tags-table-body');
        
        if (tags.length === 0) {
            tbody.innerHTML = `
                <tr class="no-data-row">
                    <td colspan="9" class="text-center">
                        <div class="no-data">
                            <i class="fas fa-id-card"></i>
                            <h3>No RFID Tags Found</h3>
                            <p>No tags match your current filters.</p>
                            <button type="button" class="btn btn-primary" onclick="rfidManager.startScanning()">
                                <i class="fas fa-wifi"></i>
                                Scan First Tag
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = tags.map(tag => `
            <tr class="table-row" data-tag-id="${tag.tag_id}">
                <td class="checkbox-column">
                    <label class="checkbox-label">
                        <input type="checkbox" 
                               class="checkbox-input tag-checkbox" 
                               value="${tag.tag_id}"
                               ${this.selectedTags.has(tag.tag_id) ? 'checked' : ''}>
                        <span class="checkbox-custom"></span>
                    </label>
                </td>
                <td class="tag-id">
                    <div class="tag-info">
                        <span class="tag-id-text">${this.escapeHtml(tag.tag_id)}</span>
                        <div class="tag-type">${tag.tag_type || 'Unknown'}</div>
                    </div>
                </td>
                <td class="assigned-user">
                    ${tag.assigned_user ? `
                        <div class="user-info">
                            <div class="user-name">${this.escapeHtml(tag.user_name)}</div>
                            <div class="user-role">${this.escapeHtml(tag.user_role)}</div>
                        </div>
                    ` : `
                        <span class="unassigned">Unassigned</span>
                    `}
                </td>
                <td class="tag-status">
                    <span class="status-badge status-${tag.status}">
                        <i class="fas fa-${this.getTagStatusIcon(tag.status)}"></i>
                        ${this.capitalizeFirst(tag.status)}
                    </span>
                </td>
                <td class="last-used">
                    ${tag.last_used ? 
                        `<span class="usage-time" title="${tag.last_used}">
                            ${this.formatRelativeTime(tag.last_used)}
                        </span>` :
                        `<span class="never-used">Never</span>`
                    }
                </td>
                <td class="usage-count">
                    <span class="count-value">${this.formatNumber(tag.usage_count || 0)}</span>
                </td>
                <td class="assigned-date">
                    ${tag.assigned_date ? 
                        `<span class="assignment-date" title="${tag.assigned_date}">
                            ${this.formatDate(tag.assigned_date)}
                        </span>` :
                        `<span class="not-assigned">Not assigned</span>`
                    }
                </td>
                <td class="created-date">
                    <span class="creation-time" title="${tag.created_at}">
                        ${this.formatDate(tag.created_at)}
                    </span>
                </td>
                <td class="actions-column">
                    <div class="action-buttons">
                        <button type="button" 
                                class="btn btn-sm btn-ghost action-btn" 
                                onclick="rfidManager.viewTagDetails('${tag.tag_id}')"
                                title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button type="button" 
                                class="btn btn-sm btn-ghost action-btn" 
                                onclick="rfidManager.editTag('${tag.tag_id}')"
                                title="Edit Tag">
                            <i class="fas fa-edit"></i>
                        </button>
                        <div class="dropdown action-dropdown">
                            <button type="button" 
                                    class="btn btn-sm btn-ghost dropdown-toggle" 
                                    data-dropdown="tag-actions-${tag.tag_id}">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu" id="tag-actions-${tag.tag_id}">
                                ${tag.assigned_user ? `
                                    <button type="button" class="dropdown-item" onclick="rfidManager.reassignTag('${tag.tag_id}')">
                                        <i class="fas fa-user-edit"></i>
                                        Reassign User
                                    </button>
                                    <button type="button" class="dropdown-item" onclick="rfidManager.unassignTag('${tag.tag_id}')">
                                        <i class="fas fa-user-minus"></i>
                                        Unassign User
                                    </button>
                                ` : `
                                    <button type="button" class="dropdown-item" onclick="rfidManager.assignTag('${tag.tag_id}')">
                                        <i class="fas fa-user-plus"></i>
                                        Assign User
                                    </button>
                                `}
                                <button type="button" class="dropdown-item" onclick="rfidManager.viewTagHistory('${tag.tag_id}')">
                                    <i class="fas fa-history"></i>
                                    View History
                                </button>
                                <button type="button" class="dropdown-item" onclick="rfidManager.testTag('${tag.tag_id}')">
                                    <i class="fas fa-vial"></i>
                                    Test Tag
                                </button>
                                <div class="dropdown-divider"></div>
                                ${tag.status === 'active' ? `
                                    <button type="button" class="dropdown-item" onclick="rfidManager.deactivateTag('${tag.tag_id}')">
                                        <i class="fas fa-ban"></i>
                                        Deactivate Tag
                                    </button>
                                ` : `
                                    <button type="button" class="dropdown-item" onclick="rfidManager.activateTag('${tag.tag_id}')">
                                        <i class="fas fa-check"></i>
                                        Activate Tag
                                    </button>
                                `}
                                <button type="button" class="dropdown-item text-danger" onclick="rfidManager.deleteTag('${tag.tag_id}')">
                                    <i class="fas fa-trash"></i>
                                    Delete Tag
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
    
    // Scanner functionality
    async initializeScanner() {
        try {
            // Check scanner status
            const response = await fetch('/api/admin/rfid/scanner/status', {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateScannerStatus(data.data.connected, data.data.status);
            }
        } catch (error) {
            console.log('Scanner initialization failed:', error);
            this.updateScannerStatus(false, 'offline');
        }
    }
    
    updateScannerStatus(connected, status) {
        const indicator = document.getElementById('scanner-indicator');
        const statusText = document.getElementById('scanner-status-text');
        const connectBtn = document.getElementById('connect-scanner');
        const testBtn = document.getElementById('test-scanner');
        
        this.scannerConnected = connected;
        
        indicator.className = `status-indicator ${connected ? 'online' : 'offline'}`;
        statusText.textContent = this.capitalizeFirst(status);
        
        if (connected) {
            connectBtn.innerHTML = '<i class="fas fa-unplug"></i> Disconnect';
            testBtn.disabled = false;
        } else {
            connectBtn.innerHTML = '<i class="fas fa-plug"></i> Connect Scanner';
            testBtn.disabled = true;
        }
    }
    
    async connectScanner() {
        if (this.scannerConnected) {
            await this.disconnectScanner();
            return;
        }
        
        try {
            const response = await fetch('/api/admin/rfid/scanner/connect', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateScannerStatus(true, 'connected');
                this.initWebSocket();
                this.showSuccess('Scanner connected successfully.');
            } else {
                this.showError('Failed to connect scanner: ' + data.message);
            }
        } catch (error) {
            this.showError('Failed to connect scanner.');
        }
    }
    
    async disconnectScanner() {
        try {
            const response = await fetch('/api/admin/rfid/scanner/disconnect', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateScannerStatus(false, 'offline');
                this.closeWebSocket();
                this.showSuccess('Scanner disconnected.');
            } else {
                this.showError('Failed to disconnect scanner: ' + data.message);
            }
        } catch (error) {
            this.showError('Failed to disconnect scanner.');
        }
    }
    
    initWebSocket() {
        if (this.scannerWebSocket) {
            this.scannerWebSocket.close();
        }
        
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${protocol}//${window.location.host}/ws/rfid-scanner`;
        
        this.scannerWebSocket = new WebSocket(wsUrl);
        
        this.scannerWebSocket.onopen = () => {
            console.log('Scanner WebSocket connected');
        };
        
        this.scannerWebSocket.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.handleScannerMessage(data);
        };
        
        this.scannerWebSocket.onclose = () => {
            console.log('Scanner WebSocket disconnected');
            this.updateScannerStatus(false, 'offline');
        };
        
        this.scannerWebSocket.onerror = (error) => {
            console.error('Scanner WebSocket error:', error);
            this.showError('Scanner connection error.');
        };
    }
    
    closeWebSocket() {
        if (this.scannerWebSocket) {
            this.scannerWebSocket.close();
            this.scannerWebSocket = null;
        }
    }
    
    handleScannerMessage(data) {
        switch (data.type) {
            case 'tag_detected':
                this.handleTagDetected(data.tag_id);
                break;
            case 'scanner_status':
                this.updateScannerStatus(data.connected, data.status);
                break;
            case 'error':
                this.showError('Scanner error: ' + data.message);
                break;
        }
    }
    
    handleTagDetected(tagId) {
        this.currentTagId = tagId;
        
        // Update scanner modal if open
        const modal = document.getElementById('scanner-modal');
        if (modal.classList.contains('modal-show')) {
            this.displayDetectedTag(tagId);
        }
        
        // Show notification
        this.showSuccess(`RFID tag detected: ${tagId}`);
    }
    
    displayDetectedTag(tagId) {
        const tagInfo = document.getElementById('scanned-tag-info');
        const detectedTagId = document.getElementById('detected-tag-id');
        const statusInfo = document.getElementById('tag-status-info');
        const assignBtn = document.getElementById('assign-scanned-tag');
        
        detectedTagId.textContent = tagId;
        
        // Check tag status
        this.checkTagStatus(tagId).then(status => {
            if (status.exists) {
                statusInfo.innerHTML = `
                    <div class="tag-status-existing">
                        <span class="status-badge status-${status.status}">
                            ${this.capitalizeFirst(status.status)}
                        </span>
                        ${status.assigned_user ? 
                            `<span class="assigned-user">Assigned to: ${status.assigned_user}</span>` :
                            `<span class="unassigned">Not assigned to any user</span>`
                        }
                    </div>
                `;
                
                if (!status.assigned_user) {
                    assignBtn.style.display = 'inline-block';
                }
            } else {
                statusInfo.innerHTML = `
                    <div class="tag-status-new">
                        <span class="new-tag-badge">New Tag</span>
                        <span class="new-tag-text">This tag is not registered in the system</span>
                    </div>
                `;
                assignBtn.style.display = 'inline-block';
            }
        });
        
        tagInfo.style.display = 'block';
    }
    
    async checkTagStatus(tagId) {
        try {
            const response = await fetch(`/api/admin/rfid/tags/${encodeURIComponent(tagId)}/status`, {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            return data.success ? data.data : { exists: false };
        } catch (error) {
            console.error('Error checking tag status:', error);
            return { exists: false };
        }
    }
    
    startScanning() {
        if (!this.scannerConnected) {
            this.showError('Scanner not connected. Please connect the scanner first.');
            return;
        }
        
        this.showModal('scanner-modal');
        
        // Reset scanner interface
        document.getElementById('scanned-tag-info').style.display = 'none';
        document.getElementById('assign-scanned-tag').style.display = 'none';
        this.currentTagId = null;
    }
    
    async testScanner() {
        try {
            const response = await fetch('/api/admin/rfid/scanner/test', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Scanner test successful.');
            } else {
                this.showError('Scanner test failed: ' + data.message);
            }
        } catch (error) {
            this.showError('Scanner test failed.');
        }
    }
    
    // Tag assignment functionality
    showAssignmentModal() {
        if (!this.currentTagId) {
            this.showError('No tag selected.');
            return;
        }
        
        document.getElementById('assignment-tag-id').value = this.currentTagId;
        this.loadUsersForAssignment();
        this.hideModal('scanner-modal');
        this.showModal('tag-assignment-modal');
    }
    
    async loadUsersForAssignment() {
        try {
            const response = await fetch('/api/admin/users/unassigned-rfid', {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                const userSelect = document.getElementById('assignment-user');
                userSelect.innerHTML = '<option value="">Choose a user...</option>';
                
                data.data.users.forEach(user => {
                    userSelect.innerHTML += `
                        <option value="${user.user_id}">
                            ${this.escapeHtml(user.full_name || user.username)} (${this.escapeHtml(user.email)})
                        </option>
                    `;
                });
            } else {
                this.showError('Failed to load users: ' + data.message);
            }
        } catch (error) {
            this.showError('Failed to load users.');
        }
    }
    
    async confirmTagAssignment() {
        const form = document.getElementById('tag-assignment-form');
        const formData = new FormData(form);
        
        if (!formData.get('user_id')) {
            this.showError('Please select a user.');
            return;
        }
        
        try {
            const response = await fetch('/api/admin/rfid/assign', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                },
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('RFID tag assigned successfully.');
                this.hideModal('tag-assignment-modal');
                this.loadTags();
                this.currentTagId = null;
            } else {
                this.showError('Assignment failed: ' + data.message);
            }
        } catch (error) {
            this.showError('Assignment failed.');
        }
    }
    
    // Device management
    async loadDevices() {
        try {
            const response = await fetch('/api/admin/rfid/devices', {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.renderDevices(data.data.devices);
            } else {
                this.showError('Failed to load devices: ' + data.message);
            }
        } catch (error) {
            console.error('Error loading devices:', error);
            this.showError('Failed to load devices.');
        }
    }
    
    renderDevices(devices) {
        const grid = document.getElementById('devices-grid');
        
        if (devices.length === 0) {
            grid.innerHTML = `
                <div class="no-devices">
                    <i class="fas fa-wifi"></i>
                    <h3>No Devices Configured</h3>
                    <p>Add your first RFID scanning device to get started.</p>
                    <button type="button" class="btn btn-primary" onclick="rfidManager.showDeviceConfig()">
                        <i class="fas fa-plus"></i>
                        Add Device
                    </button>
                </div>
            `;
            return;
        }
        
        grid.innerHTML = devices.map(device => `
            <div class="device-card ${device.status}" data-device-id="${device.device_id}">
                <div class="device-header">
                    <div class="device-icon">
                        <i class="fas fa-${this.getDeviceIcon(device.device_type)}"></i>
                    </div>
                    <div class="device-status">
                        <span class="status-indicator ${device.status}"></span>
                        <span class="status-text">${this.capitalizeFirst(device.status)}</span>
                    </div>
                </div>
                
                <div class="device-info">
                    <h3 class="device-name">${this.escapeHtml(device.device_name)}</h3>
                    <p class="device-type">${this.capitalizeFirst(device.device_type)}</p>
                    ${device.location ? `<p class="device-location">${this.escapeHtml(device.location)}</p>` : ''}
                </div>
                
                <div class="device-stats">
                    <div class="stat-item">
                        <div class="stat-value">${device.total_scans || 0}</div>
                        <div class="stat-label">Total Scans</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${device.uptime_percentage || 0}%</div>
                        <div class="stat-label">Uptime</div>
                    </div>
                </div>
                
                <div class="device-actions">
                    <button type="button" class="btn btn-sm btn-outline" onclick="rfidManager.configureDevice('${device.device_id}')">
                        <i class="fas fa-cog"></i>
                        Configure
                    </button>
                    <button type="button" class="btn btn-sm btn-outline" onclick="rfidManager.testDevice('${device.device_id}')">
                        <i class="fas fa-vial"></i>
                        Test
                    </button>
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-ghost dropdown-toggle" data-dropdown="device-actions-${device.device_id}">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu" id="device-actions-${device.device_id}">
                            <button type="button" class="dropdown-item" onclick="rfidManager.viewDeviceLogs('${device.device_id}')">
                                <i class="fas fa-file-alt"></i>
                                View Logs
                            </button>
                            <button type="button" class="dropdown-item" onclick="rfidManager.resetDevice('${device.device_id}')">
                                <i class="fas fa-redo"></i>
                                Reset Device
                            </button>
                            <div class="dropdown-divider"></div>
                            <button type="button" class="dropdown-item text-danger" onclick="rfidManager.deleteDevice('${device.device_id}')">
                                <i class="fas fa-trash"></i>
                                Delete Device
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
        
        // Setup dropdowns
        this.setupDropdowns();
    }
    
    // Utility methods
    getTagStatusIcon(status) {
        const icons = {
            'assigned': 'user-check',
            'unassigned': 'user-times',
            'active': 'check-circle',
            'inactive': 'times-circle',
            'lost': 'exclamation-triangle'
        };
        return icons[status] || 'question-circle';
    }
    
    getDeviceIcon(type) {
        const icons = {
            'esp32': 'microchip',
            'arduino': 'microchip',
            'usb': 'usb',
            'network': 'network-wired'
        };
        return icons[type] || 'wifi';
    }
    
    // ... Additional utility methods similar to other managers
    
    capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    formatNumber(num) {
        return new Intl.NumberFormat().format(num);
    }
    
    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString();
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

// Expose rfidManager globally
window.rfidManager = null;
document.addEventListener('DOMContentLoaded', function() {
    window.rfidManager = new RFIDManager();
});
</script>
