<!-- Application Sidebar -->
<aside class="sidebar" role="navigation" aria-label="Main navigation">
    <div class="sidebar-container">
        
        <!-- Sidebar Header -->
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <img src="/assets/images/logo-small.png" alt="Logo" class="sidebar-logo">
                <span class="sidebar-title"><?= htmlspecialchars($app_name ?? 'RFID System') ?></span>
            </div>
            
            <button class="sidebar-close" aria-label="Close navigation">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- User Quick Info -->
        <div class="sidebar-user">
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
            <div class="user-details">
                <div class="user-name"><?= htmlspecialchars($current_user['full_name'] ?? $current_user['username']) ?></div>
                <div class="user-role"><?= htmlspecialchars($current_user['group_name'] ?? 'User') ?></div>
            </div>
        </div>
        
        <!-- Navigation Menu -->
        <nav class="sidebar-nav">
            <ul class="nav-list">
                
                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="/dashboard" class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                
                <!-- Events Section -->
                <li class="nav-item">
                    <a href="/events" class="nav-link <?= in_array($current_page, ['events', 'event-details']) ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-calendar"></i>
                        <span class="nav-text">Events</span>
                        <?php if (isset($active_events_count) && $active_events_count > 0): ?>
                            <span class="nav-badge"><?= $active_events_count ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                
                <!-- Check-ins -->
                <li class="nav-item">
                    <a href="/checkins" class="nav-link <?= $current_page === 'checkins' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-user-check"></i>
                        <span class="nav-text">Check-ins</span>
                    </a>
                </li>
                
                <!-- Profile -->
                <li class="nav-item">
                    <a href="/profile" class="nav-link <?= $current_page === 'profile' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-user"></i>
                        <span class="nav-text">My Profile</span>
                    </a>
                </li>
                
                <!-- Analytics (if user has access) -->
                <?php if ($current_user['group_id'] <= 2): ?>
                <li class="nav-item">
                    <a href="/analytics" class="nav-link <?= $current_page === 'analytics' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-chart-bar"></i>
                        <span class="nav-text">Analytics</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Admin Section (Admin only) -->
                <?php if ($current_user['group_id'] == 1): ?>
                <li class="nav-section">
                    <div class="nav-section-title">Administration</div>
                </li>
                
                <li class="nav-item nav-expandable">
                    <a href="#" class="nav-link nav-toggle <?= in_array($current_page, ['admin-users', 'admin-user-groups']) ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <span class="nav-text">User Management</span>
                        <i class="nav-arrow fas fa-chevron-down"></i>
                    </a>
                    <ul class="nav-submenu">
                        <li class="nav-subitem">
                            <a href="/admin/users" class="nav-link <?= $current_page === 'admin-users' ? 'active' : '' ?>">
                                <span class="nav-text">All Users</span>
                            </a>
                        </li>
                        <li class="nav-subitem">
                            <a href="/admin/users/create" class="nav-link">
                                <span class="nav-text">Add User</span>
                            </a>
                        </li>
                        <li class="nav-subitem">
                            <a href="/admin/user-groups" class="nav-link <?= $current_page === 'admin-user-groups' ? 'active' : '' ?>">
                                <span class="nav-text">User Groups</span>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <li class="nav-item nav-expandable">
                    <a href="#" class="nav-link nav-toggle <?= in_array($current_page, ['admin-events', 'admin-event-analytics']) ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-calendar-alt"></i>
                        <span class="nav-text">Event Management</span>
                        <i class="nav-arrow fas fa-chevron-down"></i>
                    </a>
                    <ul class="nav-submenu">
                        <li class="nav-subitem">
                            <a href="/admin/events" class="nav-link <?= $current_page === 'admin-events' ? 'active' : '' ?>">
                                <span class="nav-text">All Events</span>
                            </a>
                        </li>
                        <li class="nav-subitem">
                            <a href="/admin/events/create" class="nav-link">
                                <span class="nav-text">Create Event</span>
                            </a>
                        </li>
                        <li class="nav-subitem">
                            <a href="/admin/events/analytics" class="nav-link <?= $current_page === 'admin-event-analytics' ? 'active' : '' ?>">
                                <span class="nav-text">Event Analytics</span>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <li class="nav-item nav-expandable">
                    <a href="#" class="nav-link nav-toggle <?= in_array($current_page, ['admin-rfid', 'admin-rfid-test']) ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-id-card"></i>
                        <span class="nav-text">RFID Management</span>
                        <i class="nav-arrow fas fa-chevron-down"></i>
                    </a>
                    <ul class="nav-submenu">
                        <li class="nav-subitem">
                            <a href="/admin/rfid" class="nav-link <?= $current_page === 'admin-rfid' ? 'active' : '' ?>">
                                <span class="nav-text">RFID Tags</span>
                            </a>
                        </li>
                        <li class="nav-subitem">
                            <a href="/admin/rfid/assign" class="nav-link">
                                <span class="nav-text">Assign Tags</span>
                            </a>
                        </li>
                        <li class="nav-subitem">
                            <a href="/admin/rfid/test" class="nav-link <?= $current_page === 'admin-rfid-test' ? 'active' : '' ?>">
                                <span class="nav-text">Test Reader</span>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <li class="nav-item nav-expandable">
                    <a href="#" class="nav-link nav-toggle <?= in_array($current_page, ['admin-reports', 'admin-performance']) ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-chart-line"></i>
                        <span class="nav-text">Reports</span>
                        <i class="nav-arrow fas fa-chevron-down"></i>
                    </a>
                    <ul class="nav-submenu">
                        <li class="nav-subitem">
                            <a href="/admin/reports" class="nav-link <?= $current_page === 'admin-reports' ? 'active' : '' ?>">
                                <span class="nav-text">System Reports</span>
                            </a>
                        </li>
                        <li class="nav-subitem">
                            <a href="/admin/reports/attendance" class="nav-link">
                                <span class="nav-text">Attendance Reports</span>
                            </a>
                        </li>
                        <li class="nav-subitem">
                            <a href="/admin/performance" class="nav-link <?= $current_page === 'admin-performance' ? 'active' : '' ?>">
                                <span class="nav-text">Performance</span>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <li class="nav-item">
                    <a href="/admin/settings" class="nav-link <?= $current_page === 'admin-settings' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-cog"></i>
                        <span class="nav-text">System Settings</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Support Section -->
                <li class="nav-section">
                    <div class="nav-section-title">Support</div>
                </li>
                
                <li class="nav-item">
                    <a href="/help" class="nav-link <?= $current_page === 'help' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-question-circle"></i>
                        <span class="nav-text">Help & Documentation</span>
                    </a>
                </li>
                
                <!-- System Status -->
                <li class="nav-item">
                    <div class="nav-link system-status">
                        <i class="nav-icon fas fa-heartbeat"></i>
                        <span class="nav-text">System Status</span>
                        <div class="status-indicator status-online"></div>
                    </div>
                </li>
                
            </ul>
        </nav>
        
        <!-- Sidebar Footer -->
        <div class="sidebar-footer">
            <div class="app-version">
                <?php if (!empty($app_version)): ?>
                    v<?= htmlspecialchars($app_version) ?>
                <?php endif; ?>
            </div>
            
            <!-- Quick Logout -->
            <form action="/auth/logout" method="POST" class="quick-logout">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <button type="submit" class="logout-btn" title="Sign Out">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </form>
        </div>
        
    </div>
</aside>

<!-- Sidebar Overlay (for mobile) -->
<div class="sidebar-overlay"></div>

<!-- Sidebar JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Sidebar close button
    const sidebarClose = document.querySelector('.sidebar-close');
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.querySelector('.sidebar-overlay');
    
    if (sidebarClose && sidebar) {
        sidebarClose.addEventListener('click', function() {
            sidebar.classList.remove('sidebar-open');
            document.body.classList.remove('sidebar-open');
        });
    }
    
    // Sidebar overlay click
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('sidebar-open');
            document.body.classList.remove('sidebar-open');
        });
    }
    
    // Expandable navigation items
    const expandableItems = document.querySelectorAll('.nav-expandable');
    
    expandableItems.forEach(item => {
        const toggle = item.querySelector('.nav-toggle');
        const submenu = item.querySelector('.nav-submenu');
        
        if (toggle && submenu) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Close other expandable items
                expandableItems.forEach(otherItem => {
                    if (otherItem !== item) {
                        otherItem.classList.remove('nav-expanded');
                    }
                });
                
                // Toggle current item
                item.classList.toggle('nav-expanded');
            });
            
            // Auto-expand if has active submenu item
            if (submenu.querySelector('.nav-link.active')) {
                item.classList.add('nav-expanded');
            }
        }
    });
    
    // Store sidebar state in localStorage
    const sidebarState = localStorage.getItem('sidebarCollapsed');
    if (sidebarState === 'true') {
        document.body.classList.add('sidebar-collapsed');
    }
    
    // Toggle sidebar collapse (desktop)
    const toggleSidebar = function() {
        document.body.classList.toggle('sidebar-collapsed');
        const isCollapsed = document.body.classList.contains('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    };
    
    // Add sidebar toggle functionality to header mobile toggle for desktop
    const mobileToggle = document.querySelector('.mobile-menu-toggle');
    if (mobileToggle) {
        // Check if we're on desktop
        const mediaQuery = window.matchMedia('(min-width: 768px)');
        
        const handleToggle = function() {
            if (mediaQuery.matches) {
                // Desktop: toggle collapse
                toggleSidebar();
            } else {
                // Mobile: toggle open/close
                sidebar.classList.toggle('sidebar-open');
                document.body.classList.toggle('sidebar-open');
            }
        };
        
        mobileToggle.addEventListener('click', handleToggle);
    }
    
    // Handle nav link clicks
    const navLinks = document.querySelectorAll('.nav-link:not(.nav-toggle)');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Close mobile sidebar when link is clicked
            if (window.innerWidth < 768) {
                sidebar.classList.remove('sidebar-open');
                document.body.classList.remove('sidebar-open');
            }
        });
    });
    
    // System status indicator
    updateSystemStatus();
    setInterval(updateSystemStatus, 60000); // Check every minute
    
    function updateSystemStatus() {
        fetch('/api/system/status')
            .then(response => response.json())
            .then(data => {
                const statusIndicator = document.querySelector('.system-status .status-indicator');
                if (statusIndicator && data.success) {
                    statusIndicator.className = `status-indicator status-${data.data.status}`;
                    statusIndicator.title = `System ${data.data.status} - ${data.data.message}`;
                }
            })
            .catch(error => {
                console.error('Error checking system status:', error);
                const statusIndicator = document.querySelector('.system-status .status-indicator');
                if (statusIndicator) {
                    statusIndicator.className = 'status-indicator status-unknown';
                    statusIndicator.title = 'System status unknown';
                }
            });
    }
    
    // Quick logout confirmation
    const quickLogout = document.querySelector('.quick-logout');
    if (quickLogout) {
        quickLogout.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to sign out?')) {
                e.preventDefault();
            }
        });
    }
    
    // Add keyboard navigation
    document.addEventListener('keydown', function(e) {
        // Alt + S to toggle sidebar
        if (e.altKey && e.key === 's') {
            e.preventDefault();
            if (window.innerWidth >= 768) {
                toggleSidebar();
            } else {
                sidebar.classList.toggle('sidebar-open');
                document.body.classList.toggle('sidebar-open');
            }
        }
        
        // Escape to close mobile sidebar
        if (e.key === 'Escape' && window.innerWidth < 768) {
            sidebar.classList.remove('sidebar-open');
            document.body.classList.remove('sidebar-open');
        }
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        // Remove mobile classes when switching to desktop
        if (window.innerWidth >= 768) {
            sidebar.classList.remove('sidebar-open');
            document.body.classList.remove('sidebar-open');
        }
    });
    
});
</script>
