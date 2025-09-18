<!-- Application Header -->
<header class="app-header" role="banner">
    <div class="header-container">
        
        <!-- Mobile Menu Toggle -->
        <button class="mobile-menu-toggle" aria-label="Toggle navigation menu" data-target="sidebar">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>
        
        <!-- Application Logo/Brand -->
        <div class="header-brand">
            <a href="/dashboard" class="brand-link">
                <img src="/assets/images/logo-small.png" alt="Logo" class="brand-logo">
                <span class="brand-text"><?= htmlspecialchars($app_name ?? 'RFID Check-in') ?></span>
            </a>
        </div>
        
        <!-- Search Bar (if enabled) -->
        <?php if (isset($show_search) && $show_search): ?>
        <div class="header-search">
            <form class="search-form" action="/search" method="GET">
                <div class="search-input-group">
                    <input type="text" 
                           name="q" 
                           class="search-input" 
                           placeholder="Search users, events..." 
                           value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                           autocomplete="off">
                    <button type="submit" class="search-button" aria-label="Search">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Header Actions -->
        <div class="header-actions">
            
            <!-- Real-time Status Indicator -->
            <div class="status-indicator">
                <div class="status-dot status-online" title="System Online"></div>
                <span class="status-text">Online</span>
            </div>
            
            <!-- Notifications Dropdown -->
            <div class="dropdown notifications-dropdown">
                <button class="dropdown-toggle notification-toggle" 
                        aria-label="Notifications" 
                        data-dropdown="notifications">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" data-count="0">0</span>
                </button>
                
                <div class="dropdown-menu" id="notifications">
                    <div class="dropdown-header">
                        <h3>Notifications</h3>
                        <button class="mark-all-read" data-action="mark-all-read">
                            Mark all as read
                        </button>
                    </div>
                    
                    <div class="notifications-list" id="notifications-list">
                        <div class="no-notifications">
                            <i class="fas fa-bell-slash"></i>
                            <p>No new notifications</p>
                        </div>
                    </div>
                    
                    <div class="dropdown-footer">
                        <a href="/notifications" class="view-all-link">View all notifications</a>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions (Admin only) -->
            <?php if ($current_user['group_id'] == 1): ?>
            <div class="dropdown quick-actions-dropdown">
                <button class="dropdown-toggle quick-actions-toggle" 
                        aria-label="Quick Actions" 
                        data-dropdown="quick-actions">
                    <i class="fas fa-plus"></i>
                </button>
                
                <div class="dropdown-menu" id="quick-actions">
                    <div class="dropdown-header">
                        <h3>Quick Actions</h3>
                    </div>
                    
                    <a href="/admin/users/create" class="dropdown-item">
                        <i class="fas fa-user-plus"></i>
                        <span>Add User</span>
                    </a>
                    
                    <a href="/events/create" class="dropdown-item">
                        <i class="fas fa-calendar-plus"></i>
                        <span>Create Event</span>
                    </a>
                    
                    <a href="/admin/rfid/assign" class="dropdown-item">
                        <i class="fas fa-id-card"></i>
                        <span>Assign RFID</span>
                    </a>
                    
                    <div class="dropdown-divider"></div>
                    
                    <a href="/admin/reports" class="dropdown-item">
                        <i class="fas fa-chart-bar"></i>
                        <span>View Reports</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Theme Toggle -->
            <button class="theme-toggle" 
                    aria-label="Toggle theme" 
                    data-action="toggle-theme"
                    title="Switch between light and dark theme">
                <i class="fas fa-moon theme-icon-dark"></i>
                <i class="fas fa-sun theme-icon-light"></i>
            </button>
            
            <!-- User Profile Dropdown -->
            <div class="dropdown user-dropdown">
                <button class="dropdown-toggle user-toggle" 
                        aria-label="User menu" 
                        data-dropdown="user-menu">
                    <div class="user-avatar">
                        <?php if (!empty($current_user['avatar'])): ?>
                            <img src="<?= htmlspecialchars($current_user['avatar']) ?>" 
                                 alt="<?= htmlspecialchars($current_user['username']) ?>" 
                                 class="avatar-image">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <?= strtoupper(substr($current_user['username'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span class="user-name"><?= htmlspecialchars($current_user['username']) ?></span>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </button>
                
                <div class="dropdown-menu" id="user-menu">
                    <div class="dropdown-header">
                        <div class="user-info">
                            <strong><?= htmlspecialchars($current_user['full_name'] ?? $current_user['username']) ?></strong>
                            <span class="user-role"><?= htmlspecialchars($current_user['group_name'] ?? 'User') ?></span>
                        </div>
                    </div>
                    
                    <div class="dropdown-divider"></div>
                    
                    <a href="/profile" class="dropdown-item">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                    
                    <a href="/profile/settings" class="dropdown-item">
                        <i class="fas fa-cog"></i>
                        <span>Account Settings</span>
                    </a>
                    
                    <a href="/profile/checkins" class="dropdown-item">
                        <i class="fas fa-history"></i>
                        <span>My Check-ins</span>
                    </a>
                    
                    <?php if ($current_user['group_id'] <= 2): ?>
                        <div class="dropdown-divider"></div>
                        
                        <a href="/admin" class="dropdown-item">
                            <i class="fas fa-shield-alt"></i>
                            <span>Admin Panel</span>
                        </a>
                    <?php endif; ?>
                    
                    <div class="dropdown-divider"></div>
                    
                    <a href="/help" class="dropdown-item">
                        <i class="fas fa-question-circle"></i>
                        <span>Help & Support</span>
                    </a>
                    
                    <div class="dropdown-divider"></div>
                    
                    <form action="/auth/logout" method="POST" class="logout-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <button type="submit" class="dropdown-item logout-item">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Sign Out</span>
                        </button>
                    </form>
                </div>
            </div>
            
        </div>
        
    </div>
</header>

<!-- Header JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Mobile menu toggle
    const mobileToggle = document.querySelector('.mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('sidebar-open');
            mobileToggle.classList.toggle('active');
            document.body.classList.toggle('sidebar-open');
        });
    }
    
    // Dropdown functionality
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        const menu = dropdown.querySelector('.dropdown-menu');
        
        if (toggle && menu) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Close other dropdowns
                dropdowns.forEach(otherDropdown => {
                    if (otherDropdown !== dropdown) {
                        otherDropdown.classList.remove('dropdown-open');
                    }
                });
                
                // Toggle current dropdown
                dropdown.classList.toggle('dropdown-open');
            });
        }
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove('dropdown-open');
            });
        }
    });
    
    // Theme toggle
    const themeToggle = document.querySelector('.theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            // Send theme preference to server
            fetch('/api/user/theme', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.App.csrfToken
                },
                body: JSON.stringify({ theme: newTheme })
            });
        });
    }
    
    // Load notifications
    loadNotifications();
    
    // Poll for new notifications every 30 seconds
    setInterval(loadNotifications, 30000);
    
    function loadNotifications() {
        fetch('/api/notifications')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNotificationsBadge(data.data.unread_count);
                    updateNotificationsList(data.data.notifications);
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
            });
    }
    
    function updateNotificationsBadge(count) {
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            badge.textContent = count;
            badge.setAttribute('data-count', count);
            badge.style.display = count > 0 ? 'inline' : 'none';
        }
    }
    
    function updateNotificationsList(notifications) {
        const list = document.getElementById('notifications-list');
        if (!list) return;
        
        if (notifications.length === 0) {
            list.innerHTML = `
                <div class="no-notifications">
                    <i class="fas fa-bell-slash"></i>
                    <p>No new notifications</p>
                </div>
            `;
            return;
        }
        
        list.innerHTML = notifications.map(notification => `
            <div class="notification-item ${notification.is_read ? '' : 'unread'}" data-id="${notification.id}">
                <div class="notification-icon">
                    <i class="fas fa-${getNotificationIcon(notification.type)}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${escapeHtml(notification.title)}</div>
                    <div class="notification-message">${escapeHtml(notification.message)}</div>
                    <div class="notification-time">${formatRelativeTime(notification.created_at)}</div>
                </div>
                ${!notification.is_read ? '<div class="unread-indicator"></div>' : ''}
            </div>
        `).join('');
        
        // Add click handlers for notifications
        list.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function() {
                const notificationId = this.dataset.id;
                markNotificationAsRead(notificationId);
            });
        });
    }
    
    function getNotificationIcon(type) {
        const icons = {
            'checkin': 'user-check',
            'event': 'calendar',
            'system': 'cog',
            'security': 'shield-alt',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        return icons[type] || 'bell';
    }
    
    function markNotificationAsRead(notificationId) {
        fetch(`/api/notifications/${notificationId}/read`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': window.App.csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Refresh notifications
                loadNotifications();
            }
        });
    }
    
    // Mark all notifications as read
    const markAllReadBtn = document.querySelector('.mark-all-read');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function() {
            fetch('/api/notifications/mark-all-read', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications();
                }
            });
        });
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatRelativeTime(dateString) {
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
});
</script>
