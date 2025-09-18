<?php
$page_title = 'User Management';
$page_description = 'Manage system users, permissions, and access controls';
$breadcrumbs = [
    ['title' => 'Administration', 'url' => '/admin', 'icon' => 'fas fa-shield-alt'],
    ['title' => 'User Management', 'url' => '/admin/users', 'icon' => 'fas fa-users']
];
$current_page = 'admin-users';

// Page-specific assets
$assets = [
    'css' => ['admin.css', 'data-tables.css', 'modals.css'],
    'js' => ['admin-users.js', 'data-tables.js', 'bulk-actions.js']
];

// Page actions
$page_actions = [
    ['title' => 'Add New User', 'url' => '/admin/users/create', 'type' => 'primary', 'icon' => 'fas fa-user-plus'],
    ['title' => 'Import Users', 'url' => '/admin/users/import', 'type' => 'outline', 'icon' => 'fas fa-upload'],
    ['title' => 'Export Users', 'url' => '/admin/users/export', 'type' => 'outline', 'icon' => 'fas fa-download']
];
?>

<!-- User Management Interface -->
<div class="admin-content">
    
    <!-- Page Header -->
    <div class="admin-header">
        <div class="header-content">
            <div class="header-info">
                <h1 class="page-title">
                    <i class="fas fa-users"></i>
                    User Management
                </h1>
                <p class="page-description">
                    Manage system users, their permissions, and access controls.
                    <span class="user-count">Total users: <strong id="total-users-count"><?= number_format($stats['total_users'] ?? 0) ?></strong></span>
                </p>
            </div>
            
            <div class="header-stats">
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['active_users'] ?? 0) ?></div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['pending_users'] ?? 0) ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($stats['suspended_users'] ?? 0) ?></div>
                    <div class="stat-label">Suspended</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters and Search -->
    <div class="admin-filters">
        <div class="filters-row">
            
            <!-- Search -->
            <div class="filter-group search-group">
                <div class="search-input-wrapper">
                    <input type="text" 
                           id="user-search" 
                           class="form-input search-input" 
                           placeholder="Search users by name, email, or username..."
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
                    <option value="active">Active</option>
                    <option value="pending">Pending Approval</option>
                    <option value="suspended">Suspended</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            
            <!-- Role Filter -->
            <div class="filter-group">
                <label for="role-filter" class="filter-label">Role</label>
                <select id="role-filter" class="form-select">
                    <option value="">All Roles</option>
                    <option value="1">Administrator</option>
                    <option value="2">Moderator</option>
                    <option value="3">User</option>
                </select>
            </div>
            
            <!-- RFID Filter -->
            <div class="filter-group">
                <label for="rfid-filter" class="filter-label">RFID Status</label>
                <select id="rfid-filter" class="form-select">
                    <option value="">All Users</option>
                    <option value="assigned">Has RFID Tag</option>
                    <option value="unassigned">No RFID Tag</option>
                </select>
            </div>
            
            <!-- Date Filter -->
            <div class="filter-group">
                <label for="date-filter" class="filter-label">Registered</label>
                <select id="date-filter" class="form-select">
                    <option value="">All Time</option>
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="quarter">This Quarter</option>
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
            <span class="selected-count">0</span> users selected
        </div>
        <div class="bulk-actions">
            <button type="button" class="btn btn-sm btn-outline" data-bulk-action="activate">
                <i class="fas fa-check"></i>
                Activate
            </button>
            <button type="button" class="btn btn-sm btn-outline" data-bulk-action="suspend">
                <i class="fas fa-ban"></i>
                Suspend
            </button>
            <button type="button" class="btn btn-sm btn-outline" data-bulk-action="assign-rfid">
                <i class="fas fa-id-card"></i>
                Assign RFID
            </button>
            <button type="button" class="btn btn-sm btn-outline" data-bulk-action="send-email">
                <i class="fas fa-envelope"></i>
                Send Email
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
    
    <!-- Users Data Table -->
    <div class="admin-table-container">
        <div class="table-wrapper">
            <table class="data-table" id="users-table">
                <thead>
                    <tr>
                        <th class="checkbox-column">
                            <label class="checkbox-label">
                                <input type="checkbox" id="select-all" class="checkbox-input">
                                <span class="checkbox-custom"></span>
                            </label>
                        </th>
                        <th class="sortable" data-sort="user_id">
                            ID
                            <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th class="sortable" data-sort="username">
                            User
                            <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th class="sortable" data-sort="email">
                            Email
                            <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th class="sortable" data-sort="group_name">
                            Role
                            <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th class="sortable" data-sort="status">
                            Status
                            <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th class="sortable" data-sort="rfid_tag">
                            RFID Tag
                            <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th class="sortable" data-sort="last_login">
                            Last Login
                            <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th class="sortable" data-sort="created_at">
                            Registered
                            <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th class="actions-column">Actions</th>
                    </tr>
                </thead>
                <tbody id="users-table-body">
                    <!-- Table rows will be loaded here -->
                    <tr class="loading-row">
                        <td colspan="10" class="text-center">
                            <div class="loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i>
                                <span>Loading users...</span>
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
                    Showing 0 to 0 of 0 users
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

<!-- User Details Modal -->
<div class="modal" id="user-details-modal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-user"></i>
                    User Details
                </h3>
                <button type="button" class="modal-close" data-modal-close>
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="user-details-content">
                <!-- User details will be loaded here -->
                <div class="loading-content">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Loading user details...</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Close</button>
                <button type="button" class="btn btn-primary" id="edit-user-btn">
                    <i class="fas fa-edit"></i>
                    Edit User
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Action Modal -->
<div class="modal" id="bulk-action-modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="bulk-action-title">
                    <i class="fas fa-users"></i>
                    Bulk Action
                </h3>
                <button type="button" class="modal-close" data-modal-close>
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="bulk-action-content">
                <!-- Bulk action content will be generated here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-bulk-action">
                    <i class="fas fa-check"></i>
                    Confirm Action
                </button>
            </div>
        </div>
    </div>
</div>

<!-- User Management JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const userManager = new UserManager();
    userManager.init();
});

class UserManager {
    constructor() {
        this.currentPage = 1;
        this.perPage = 25;
        this.sortField = 'created_at';
        this.sortDirection = 'desc';
        this.filters = {};
        this.selectedUsers = new Set();
        this.searchTimeout = null;
    }
    
    init() {
        this.setupEventListeners();
        this.loadUsers();
    }
    
    setupEventListeners() {
        // Search
        const searchInput = document.getElementById('user-search');
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
        const filters = ['status-filter', 'role-filter', 'rfid-filter', 'date-filter'];
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
            this.loadUsers();
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
        
        // Modal actions
        document.getElementById('edit-user-btn').addEventListener('click', () => {
            this.editCurrentUser();
        });
        
        document.getElementById('confirm-bulk-action').addEventListener('click', () => {
            this.confirmBulkAction();
        });
    }
    
    async loadUsers() {
        try {
            this.showTableLoading();
            
            const params = new URLSearchParams({
                page: this.currentPage,
                per_page: this.perPage,
                sort: this.sortField,
                direction: this.sortDirection,
                ...this.filters
            });
            
            const response = await fetch(`/api/admin/users?${params}`, {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.renderUsers(data.data.users);
                this.renderPagination(data.data.pagination);
                this.updateResultsInfo(data.data.pagination);
                this.updateTotalCount(data.data.pagination.total);
            } else {
                this.showError('Failed to load users: ' + data.message);
            }
        } catch (error) {
            console.error('Error loading users:', error);
            this.showError('Failed to load users. Please try again.');
        }
    }
    
    renderUsers(users) {
        const tbody = document.getElementById('users-table-body');
        
        if (users.length === 0) {
            tbody.innerHTML = `
                <tr class="no-data-row">
                    <td colspan="10" class="text-center">
                        <div class="no-data">
                            <i class="fas fa-users"></i>
                            <h3>No Users Found</h3>
                            <p>No users match your current filters.</p>
                            <button type="button" class="btn btn-outline" onclick="userManager.resetFilters()">
                                Clear Filters
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = users.map(user => `
            <tr class="table-row" data-user-id="${user.user_id}">
                <td class="checkbox-column">
                    <label class="checkbox-label">
                        <input type="checkbox" 
                               class="checkbox-input user-checkbox" 
                               value="${user.user_id}"
                               ${this.selectedUsers.has(user.user_id) ? 'checked' : ''}>
                        <span class="checkbox-custom"></span>
                    </label>
                </td>
                <td class="user-id">${user.user_id}</td>
                <td class="user-info">
                    <div class="user-avatar">
                        ${user.avatar ? 
                            `<img src="${this.escapeHtml(user.avatar)}" alt="Avatar" class="avatar-img">` :
                            `<div class="avatar-placeholder">${user.username.charAt(0).toUpperCase()}</div>`
                        }
                    </div>
                    <div class="user-details">
                        <div class="user-name">${this.escapeHtml(user.full_name || user.username)}</div>
                        <div class="username">@${this.escapeHtml(user.username)}</div>
                    </div>
                </td>
                <td class="user-email">
                    <a href="mailto:${this.escapeHtml(user.email)}" class="email-link">
                        ${this.escapeHtml(user.email)}
                    </a>
                </td>
                <td class="user-role">
                    <span class="role-badge role-${user.group_id}">
                        ${this.escapeHtml(user.group_name)}
                    </span>
                </td>
                <td class="user-status">
                    <span class="status-badge status-${user.status}">
                        <i class="fas fa-${this.getStatusIcon(user.status)}"></i>
                        ${this.capitalizeFirst(user.status)}
                    </span>
                </td>
                <td class="rfid-tag">
                    ${user.rfid_tag ? 
                        `<span class="rfid-assigned">
                            <i class="fas fa-id-card"></i>
                            ${this.escapeHtml(user.rfid_tag)}
                        </span>` :
                        `<span class="rfid-unassigned">
                            <i class="fas fa-minus-circle"></i>
                            Not assigned
                        </span>`
                    }
                </td>
                <td class="last-login">
                    ${user.last_login ? 
                        `<span class="login-time" title="${user.last_login}">
                            ${this.formatRelativeTime(user.last_login)}
                        </span>` :
                        `<span class="never-logged">Never</span>`
                    }
                </td>
                <td class="created-date">
                    <span class="creation-time" title="${user.created_at}">
                        ${this.formatDate(user.created_at)}
                    </span>
                </td>
                <td class="actions-column">
                    <div class="action-buttons">
                        <button type="button" 
                                class="btn btn-sm btn-ghost action-btn" 
                                onclick="userManager.viewUser(${user.user_id})"
                                title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button type="button" 
                                class="btn btn-sm btn-ghost action-btn" 
                                onclick="userManager.editUser(${user.user_id})"
                                title="Edit User">
                            <i class="fas fa-edit"></i>
                        </button>
                        <div class="dropdown action-dropdown">
                            <button type="button" 
                                    class="btn btn-sm btn-ghost dropdown-toggle" 
                                    data-dropdown="user-actions-${user.user_id}">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu" id="user-actions-${user.user_id}">
                                ${user.status === 'active' ? 
                                    `<button type="button" class="dropdown-item" onclick="userManager.suspendUser(${user.user_id})">
                                        <i class="fas fa-ban"></i>
                                        Suspend User
                                    </button>` :
                                    `<button type="button" class="dropdown-item" onclick="userManager.activateUser(${user.user_id})">
                                        <i class="fas fa-check"></i>
                                        Activate User
                                    </button>`
                                }
                                <button type="button" class="dropdown-item" onclick="userManager.resetPassword(${user.user_id})">
                                    <i class="fas fa-key"></i>
                                    Reset Password
                                </button>
                                <button type="button" class="dropdown-item" onclick="userManager.assignRfid(${user.user_id})">
                                    <i class="fas fa-id-card"></i>
                                    ${user.rfid_tag ? 'Change' : 'Assign'} RFID
                                </button>
                                <div class="dropdown-divider"></div>
                                <button type="button" class="dropdown-item text-danger" onclick="userManager.deleteUser(${user.user_id})">
                                    <i class="fas fa-trash"></i>
                                    Delete User
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
    
    setupRowCheckboxes() {
        document.querySelectorAll('.user-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                const userId = parseInt(e.target.value);
                if (e.target.checked) {
                    this.selectedUsers.add(userId);
                } else {
                    this.selectedUsers.delete(userId);
                }
                this.updateBulkActionsBar();
                this.updateSelectAllState();
            });
        });
    }
    
    setupDropdowns() {
        document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                const dropdown = e.target.closest('.dropdown');
                const menu = dropdown.querySelector('.dropdown-menu');
                
                // Close other dropdowns
                document.querySelectorAll('.dropdown-menu.show').forEach(otherMenu => {
                    if (otherMenu !== menu) {
                        otherMenu.classList.remove('show');
                    }
                });
                
                menu.classList.toggle('show');
            });
        });
        
        // Close dropdowns on outside click
        document.addEventListener('click', () => {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        });
    }
    
    renderPagination(pagination) {
        const container = document.getElementById('pagination');
        
        if (pagination.total_pages <= 1) {
            container.innerHTML = '';
            return;
        }
        
        let paginationHTML = '';
        
        // Previous button
        if (pagination.current_page > 1) {
            paginationHTML += `
                <button type="button" class="pagination-btn" onclick="userManager.changePage(${pagination.current_page - 1})">
                    <i class="fas fa-chevron-left"></i>
                    Previous
                </button>
            `;
        }
        
        // Page numbers
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
        
        if (startPage > 1) {
            paginationHTML += `
                <button type="button" class="pagination-btn" onclick="userManager.changePage(1)">1</button>
            `;
            if (startPage > 2) {
                paginationHTML += `<span class="pagination-ellipsis">...</span>`;
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            paginationHTML += `
                <button type="button" 
                        class="pagination-btn ${i === pagination.current_page ? 'active' : ''}" 
                        onclick="userManager.changePage(${i})">
                    ${i}
                </button>
            `;
        }
        
        if (endPage < pagination.total_pages) {
            if (endPage < pagination.total_pages - 1) {
                paginationHTML += `<span class="pagination-ellipsis">...</span>`;
            }
            paginationHTML += `
                <button type="button" class="pagination-btn" onclick="userManager.changePage(${pagination.total_pages})">
                    ${pagination.total_pages}
                </button>
            `;
        }
        
        // Next button
        if (pagination.current_page < pagination.total_pages) {
            paginationHTML += `
                <button type="button" class="pagination-btn" onclick="userManager.changePage(${pagination.current_page + 1})">
                    Next
                    <i class="fas fa-chevron-right"></i>
                </button>
            `;
        }
        
        container.innerHTML = paginationHTML;
    }
    
    handleSearch(query) {
        const searchClear = document.getElementById('search-clear');
        
        this.filters.search = query;
        this.currentPage = 1;
        
        if (query) {
            searchClear.style.display = 'block';
        } else {
            searchClear.style.display = 'none';
            delete this.filters.search;
        }
        
        this.loadUsers();
    }
    
    handleFilterChange() {
        const filters = {
            status: document.getElementById('status-filter').value,
            role: document.getElementById('role-filter').value,
            rfid: document.getElementById('rfid-filter').value,
            date: document.getElementById('date-filter').value
        };
        
        // Remove empty filters
        Object.keys(filters).forEach(key => {
            if (filters[key]) {
                this.filters[key] = filters[key];
            } else {
                delete this.filters[key];
            }
        });
        
        this.currentPage = 1;
        this.loadUsers();
    }
    
    resetFilters() {
        this.filters = {};
        this.currentPage = 1;
        
        // Reset filter inputs
        document.getElementById('user-search').value = '';
        document.getElementById('search-clear').style.display = 'none';
        document.getElementById('status-filter').value = '';
        document.getElementById('role-filter').value = '';
        document.getElementById('rfid-filter').value = '';
        document.getElementById('date-filter').value = '';
        
        this.loadUsers();
    }
    
    handleSort(field) {
        if (this.sortField === field) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortField = field;
            this.sortDirection = 'asc';
        }
        
        this.updateSortIndicators();
        this.loadUsers();
    }
    
    updateSortIndicators() {
        document.querySelectorAll('.sortable').forEach(header => {
            const icon = header.querySelector('.sort-icon');
            
            if (header.dataset.sort === this.sortField) {
                icon.className = `fas fa-sort-${this.sortDirection === 'asc' ? 'up' : 'down'} sort-icon active`;
                header.classList.add('sorted');
            } else {
                icon.className = 'fas fa-sort sort-icon';
                header.classList.remove('sorted');
            }
        });
    }
    
    handleSelectAll(checked) {
        document.querySelectorAll('.user-checkbox').forEach(checkbox => {
            checkbox.checked = checked;
            const userId = parseInt(checkbox.value);
            
            if (checked) {
                this.selectedUsers.add(userId);
            } else {
                this.selectedUsers.delete(userId);
            }
        });
        
        this.updateBulkActionsBar();
    }
    
    updateSelectAllState() {
        const selectAll = document.getElementById('select-all');
        const checkboxes = document.querySelectorAll('.user-checkbox');
        const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
        
        if (checkedBoxes.length === 0) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        } else if (checkedBoxes.length === checkboxes.length) {
            selectAll.checked = true;
            selectAll.indeterminate = false;
        } else {
            selectAll.checked = false;
            selectAll.indeterminate = true;
        }
    }
    
    updateBulkActionsBar() {
        const bulkBar = document.getElementById('bulk-actions-bar');
        const selectedCount = bulkBar.querySelector('.selected-count');
        
        if (this.selectedUsers.size > 0) {
            bulkBar.style.display = 'flex';
            selectedCount.textContent = this.selectedUsers.size;
        } else {
            bulkBar.style.display = 'none';
        }
    }
    
    clearSelection() {
        this.selectedUsers.clear();
        document.querySelectorAll('.user-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
        this.updateBulkActionsBar();
        this.updateSelectAllState();
    }
    
    changePage(page) {
        this.currentPage = page;
        this.loadUsers();
    }
    
    updateResultsInfo(pagination) {
        const info = document.getElementById('results-info');
        const start = (pagination.current_page - 1) * pagination.per_page + 1;
        const end = Math.min(start + pagination.per_page - 1, pagination.total);
        
        info.textContent = `Showing ${start} to ${end} of ${pagination.total} users`;
    }
    
    updateTotalCount(total) {
        const counter = document.getElementById('total-users-count');
        if (counter) {
            counter.textContent = this.formatNumber(total);
        }
    }
    
    showTableLoading() {
        const tbody = document.getElementById('users-table-body');
        tbody.innerHTML = `
            <tr class="loading-row">
                <td colspan="10" class="text-center">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Loading users...</span>
                    </div>
                </td>
            </tr>
        `;
    }
    
    // User Actions
    async viewUser(userId) {
        try {
            const modal = document.getElementById('user-details-modal');
            const content = document.getElementById('user-details-content');
            
            // Show modal with loading state
            content.innerHTML = `
                <div class="loading-content">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Loading user details...</span>
                </div>
            `;
            
            this.showModal('user-details-modal');
            
            const response = await fetch(`/api/admin/users/${userId}`, {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.renderUserDetails(data.data);
                this.currentUserId = userId;
            } else {
                this.showError('Failed to load user details: ' + data.message);
                this.hideModal('user-details-modal');
            }
        } catch (error) {
            console.error('Error viewing user:', error);
            this.showError('Failed to load user details.');
            this.hideModal('user-details-modal');
        }
    }
    
    renderUserDetails(user) {
        const content = document.getElementById('user-details-content');
        
        content.innerHTML = `
            <div class="user-details">
                <div class="user-header">
                    <div class="user-avatar-large">
                        ${user.avatar ? 
                            `<img src="${this.escapeHtml(user.avatar)}" alt="Avatar" class="avatar-img">` :
                            `<div class="avatar-placeholder-large">${user.username.charAt(0).toUpperCase()}</div>`
                        }
                    </div>
                    <div class="user-header-info">
                        <h2 class="user-full-name">${this.escapeHtml(user.full_name || user.username)}</h2>
                        <p class="user-username">@${this.escapeHtml(user.username)}</p>
                        <div class="user-badges">
                            <span class="status-badge status-${user.status}">
                                <i class="fas fa-${this.getStatusIcon(user.status)}"></i>
                                ${this.capitalizeFirst(user.status)}
                            </span>
                            <span class="role-badge role-${user.group_id}">
                                ${this.escapeHtml(user.group_name)}
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="user-info-grid">
                    <div class="info-section">
                        <h3>Contact Information</h3>
                        <div class="info-items">
                            <div class="info-item">
                                <label>Email</label>
                                <span>${this.escapeHtml(user.email)}</span>
                            </div>
                            <div class="info-item">
                                <label>Phone</label>
                                <span>${user.phone || 'Not provided'}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <h3>Account Details</h3>
                        <div class="info-items">
                            <div class="info-item">
                                <label>User ID</label>
                                <span>#${user.user_id}</span>
                            </div>
                            <div class="info-item">
                                <label>Role</label>
                                <span>${this.escapeHtml(user.group_name)}</span>
                            </div>
                            <div class="info-item">
                                <label>Status</label>
                                <span class="status-${user.status}">${this.capitalizeFirst(user.status)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <h3>RFID Information</h3>
                        <div class="info-items">
                            <div class="info-item">
                                <label>RFID Tag</label>
                                <span>${user.rfid_tag || 'Not assigned'}</span>
                            </div>
                            <div class="info-item">
                                <label>Tag Status</label>
                                <span class="${user.rfid_tag ? 'text-success' : 'text-muted'}">
                                    ${user.rfid_tag ? 'Assigned' : 'Unassigned'}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <h3>Activity</h3>
                        <div class="info-items">
                            <div class="info-item">
                                <label>Last Login</label>
                                <span>${user.last_login ? this.formatDateTime(user.last_login) : 'Never'}</span>
                            </div>
                            <div class="info-item">
                                <label>Total Check-ins</label>
                                <span>${this.formatNumber(user.checkin_count || 0)}</span>
                            </div>
                            <div class="info-item">
                                <label>Registered</label>
                                <span>${this.formatDateTime(user.created_at)}</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                ${user.recent_activity && user.recent_activity.length > 0 ? `
                    <div class="info-section">
                        <h3>Recent Activity</h3>
                        <div class="activity-timeline">
                            ${user.recent_activity.map(activity => `
                                <div class="activity-item">
                                    <div class="activity-icon ${activity.type}">
                                        <i class="fas fa-${this.getActivityIcon(activity.type)}"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">${this.escapeHtml(activity.title)}</div>
                                        <div class="activity-description">${this.escapeHtml(activity.description)}</div>
                                        <div class="activity-time">${this.formatRelativeTime(activity.created_at)}</div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
    }
    
    editUser(userId) {
        window.location.href = `/admin/users/${userId}/edit`;
    }
    
    editCurrentUser() {
        if (this.currentUserId) {
            this.editUser(this.currentUserId);
        }
    }
    
    async activateUser(userId) {
        if (confirm('Are you sure you want to activate this user?')) {
            try {
                const response = await this.makeUserRequest('activate', userId);
                if (response.success) {
                    this.showSuccess('User activated successfully.');
                    this.loadUsers();
                } else {
                    this.showError('Failed to activate user: ' + response.message);
                }
            } catch (error) {
                this.showError('Failed to activate user.');
            }
        }
    }
    
    async suspendUser(userId) {
        if (confirm('Are you sure you want to suspend this user? They will not be able to access the system.')) {
            try {
                const response = await this.makeUserRequest('suspend', userId);
                if (response.success) {
                    this.showSuccess('User suspended successfully.');
                    this.loadUsers();
                } else {
                    this.showError('Failed to suspend user: ' + response.message);
                }
            } catch (error) {
                this.showError('Failed to suspend user.');
            }
        }
    }
    
    async deleteUser(userId) {
        if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            try {
                const response = await fetch(`/api/admin/users/${userId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': window.App.csrfToken
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.showSuccess('User deleted successfully.');
                    this.loadUsers();
                } else {
                    this.showError('Failed to delete user: ' + data.message);
                }
            } catch (error) {
                this.showError('Failed to delete user.');
            }
        }
    }
    
    async makeUserRequest(action, userId, data = {}) {
        const response = await fetch(`/api/admin/users/${userId}/${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.App.csrfToken
            },
            body: JSON.stringify(data)
        });
        
        return await response.json();
    }
    
    // Utility methods
    getStatusIcon(status) {
        const icons = {
            'active': 'check-circle',
            'pending': 'clock',
            'suspended': 'ban',
            'inactive': 'times-circle'
        };
        return icons[status] || 'question-circle';
    }
    
    getActivityIcon(type) {
        const icons = {
            'login': 'sign-in-alt',
            'checkin': 'user-check',
            'profile': 'user-edit',
            'system': 'cog'
        };
        return icons[type] || 'circle';
    }
    
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
    
    // Modal methods
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
    
    // Flash messages
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

// Expose userManager globally
window.userManager = null;
document.addEventListener('DOMContentLoaded', function() {
    window.userManager = new UserManager();
});
</script>
