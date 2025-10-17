<?php 
$renderer->startSection('styles');
echo '<link rel="stylesheet" href="' . $renderer->helper('asset', 'css/profile.css') . '">';
$renderer->endSection();

$pageTitle = 'My Profile';
$pageDescription = 'Manage your account information and check-in history';
?>

<!-- Profile Header -->
<div class="profile-header">
    <div class="header-content">
        <div class="profile-info">
            <div class="profile-avatar">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?= $renderer->helper('asset', $user['avatar']) ?>" alt="Profile Picture" class="avatar-image">
                <?php else: ?>
                    <div class="avatar-placeholder">
                        <?= strtoupper(substr($user['first_name'] ?? $user['username'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                
                <button class="avatar-upload-btn" id="avatarUploadBtn" title="Change Profile Picture">
                    <i class="fas fa-camera"></i>
                </button>
                <input type="file" id="avatarUpload" accept="image/*" style="display: none;">
            </div>
            
            <div class="profile-details">
                <h1 class="profile-name">
                    <?= $renderer->helper('e', $user['first_name'] . ' ' . $user['last_name']) ?>
                </h1>
                <div class="profile-meta">
                    <span class="profile-username">@<?= $renderer->helper('e', $user['username']) ?></span>
                    <span class="profile-role"><?= $renderer->helper('e', $user['group_name'] ?? 'Member') ?></span>
                </div>
                <div class="profile-stats">
                    <div class="stat-item">
                        <span class="stat-value"><?= $renderer->helper('number', $stats['total_checkins'] ?? 0) ?></span>
                        <span class="stat-label">Total Check-ins</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?= $renderer->helper('number', $stats['events_attended'] ?? 0) ?></span>
                        <span class="stat-label">Events Attended</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?= $renderer->helper('number', $stats['current_streak'] ?? 0) ?></span>
                        <span class="stat-label">Day Streak</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="profile-actions">
            <button class="btn btn-outline" id="editProfileBtn">
                <i class="fas fa-edit"></i>
                Edit Profile
            </button>
            
            <?php if ($renderer->helper('can', 'admin') || $user['id'] === ($current_user['id'] ?? null)): ?>
                <button class="btn btn-secondary" id="securitySettingsBtn">
                    <i class="fas fa-shield-alt"></i>
                    Security
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Profile Content -->
<div class="profile-content">
    
    <!-- Profile Tabs -->
    <div class="profile-tabs">
        <nav class="tabs-nav">
            <button class="tab-btn active" data-tab="overview">
                <i class="fas fa-chart-line"></i>
                Overview
            </button>
            <button class="tab-btn" data-tab="checkins">
                <i class="fas fa-user-check"></i>
                Check-ins
            </button>
            <button class="tab-btn" data-tab="events">
                <i class="fas fa-calendar"></i>
                My Events
            </button>
            <button class="tab-btn" data-tab="settings">
                <i class="fas fa-cog"></i>
                Settings
            </button>
        </nav>
    </div>
    
    <!-- Tab Content -->
    <div class="tab-content">
        
        <!-- Overview Tab -->
        <div class="tab-panel active" id="overview-tab">
            
            <!-- Activity Summary -->
            <div class="profile-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-chart-bar"></i>
                        Activity Summary
                    </h2>
                    <div class="section-actions">
                        <select id="activity-period" class="form-select">
                            <option value="week">This Week</option>
                            <option value="month" selected>This Month</option>
                            <option value="quarter">This Quarter</option>
                            <option value="year">This Year</option>
                        </select>
                    </div>
                </div>
                
                <div class="activity-cards">
                    <div class="activity-card">
                        <div class="card-icon primary">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-value"><?= $renderer->helper('number', $stats['period_checkins'] ?? 0) ?></div>
                            <div class="card-label">Check-ins This Month</div>
                            <div class="card-trend <?= ($stats['checkin_trend'] ?? 0) >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= ($stats['checkin_trend'] ?? 0) >= 0 ? 'up' : 'down' ?>"></i>
                                <?= abs($stats['checkin_trend'] ?? 0) ?>% from last month
                            </div>
                        </div>
                    </div>
                    
                    <div class="activity-card">
                        <div class="card-icon success">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-value"><?= $renderer->helper('number', $stats['period_events'] ?? 0) ?></div>
                            <div class="card-label">Events Attended</div>
                            <div class="card-trend positive">
                                <i class="fas fa-check"></i>
                                <?= $renderer->helper('number', $stats['attendance_rate'] ?? 0) ?>% attendance rate
                            </div>
                        </div>
                    </div>
                    
                    <div class="activity-card">
                        <div class="card-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-value"><?= $renderer->helper('number', $stats['total_hours'] ?? 0) ?>h</div>
                            <div class="card-label">Total Hours</div>
                            <div class="card-trend">
                                <i class="fas fa-calculator"></i>
                                <?= $renderer->helper('number', $stats['avg_hours_per_event'] ?? 0) ?>h avg per event
                            </div>
                        </div>
                    </div>
                    
                    <div class="activity-card">
                        <div class="card-icon info">
                            <i class="fas fa-fire"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-value"><?= $renderer->helper('number', $stats['current_streak'] ?? 0) ?></div>
                            <div class="card-label">Current Streak</div>
                            <div class="card-trend">
                                <i class="fas fa-trophy"></i>
                                Best: <?= $renderer->helper('number', $stats['best_streak'] ?? 0) ?> days
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Activity Chart -->
            <div class="profile-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-chart-line"></i>
                        Check-in Trends
                    </h2>
                </div>
                
                <div class="chart-container">
                    <canvas id="activity-chart" class="activity-chart"></canvas>
                    <div class="chart-loading" id="activity-chart-loading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Loading chart...</span>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="profile-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-clock"></i>
                        Recent Activity
                    </h2>
                    <a href="#checkins-tab" class="section-link" onclick="switchTab('checkins')">View all</a>
                </div>
                
                <div class="recent-activity">
                    <?php if (!empty($recentActivity)): ?>
                        <?php foreach (array_slice($recentActivity, 0, 5) as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon <?= $activity['type'] ?>">
                                    <i class="fas fa-<?= $activity['icon'] ?? 'user-check' ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?= $renderer->helper('e', $activity['title']) ?></div>
                                    <div class="activity-description"><?= $renderer->helper('e', $activity['description']) ?></div>
                                    <div class="activity-time"><?= $renderer->helper('timeAgo', $activity['created_at']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state small">
                            <div class="empty-icon">
                                <i class="fas fa-inbox"></i>
                            </div>
                            <p>No recent activity to display</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
        
        <!-- Check-ins Tab -->
        <div class="tab-panel" id="checkins-tab">
            
            <div class="profile-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-user-check"></i>
                        Check-in History
                    </h2>
                    <div class="section-filters">
                        <select id="checkin-filter" class="form-select">
                            <option value="all">All Time</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="quarter">This Quarter</option>
                        </select>
                        
                        <input type="text" id="checkin-search" class="form-input" placeholder="Search events...">
                    </div>
                </div>
                
                <div class="checkins-list">
                    <?php if (!empty($checkins)): ?>
                        <?php foreach ($checkins as $checkin): ?>
                            <div class="checkin-item">
                                <div class="checkin-event">
                                    <div class="event-name">
                                        <a href="/events/<?= $checkin['event_id'] ?>"><?= $renderer->helper('e', $checkin['event_name']) ?></a>
                                    </div>
                                    <div class="event-category"><?= $renderer->helper('e', $checkin['category_name'] ?? 'General') ?></div>
                                </div>
                                
                                <div class="checkin-details">
                                    <div class="checkin-time">
                                        <i class="fas fa-clock"></i>
                                        <?= $renderer->helper('date', $checkin['checkin_time'], 'M j, Y g:i A') ?>
                                    </div>
                                    
                                    <?php if ($checkin['checkout_time']): ?>
                                        <div class="checkout-time">
                                            <i class="fas fa-sign-out-alt"></i>
                                            <?= $renderer->helper('date', $checkin['checkout_time'], 'M j, Y g:i A') ?>
                                        </div>
                                        
                                        <div class="duration">
                                            <i class="fas fa-stopwatch"></i>
                                            <?= $renderer->helper('duration', $checkin['checkin_time'], $checkin['checkout_time']) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="status-badge status-active">
                                            <i class="fas fa-circle"></i>
                                            Active
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="checkin-actions">
                                    <?php if (!$checkin['checkout_time'] && $renderer->helper('can', 'checkout')): ?>
                                        <button class="btn btn-sm btn-outline" onclick="checkOut(<?= $checkin['id'] ?>)">
                                            <i class="fas fa-sign-out-alt"></i>
                                            Check Out
                                        </button>
                                    <?php endif; ?>
                                    
                                    <a href="/events/<?= $checkin['event_id'] ?>" class="btn btn-sm btn-ghost">
                                        <i class="fas fa-external-link-alt"></i>
                                        View Event
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <h3>No check-ins yet</h3>
                            <p>Your check-in history will appear here once you start attending events.</p>
                            <a href="/events" class="btn btn-primary">Browse Events</a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Load More Button -->
                <?php if (!empty($hasMoreCheckins)): ?>
                    <div class="load-more">
                        <button class="btn btn-outline" id="loadMoreCheckins">
                            <i class="fas fa-chevron-down"></i>
                            Load More
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
        
        <!-- Events Tab -->
        <div class="tab-panel" id="events-tab">
            
            <div class="profile-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-calendar"></i>
                        My Events
                    </h2>
                    <div class="section-filters">
                        <select id="event-filter" class="form-select">
                            <option value="all">All Events</option>
                            <option value="upcoming">Upcoming</option>
                            <option value="attended">Attended</option>
                            <option value="missed">Missed</option>
                        </select>
                    </div>
                </div>
                
                <div class="user-events-list">
                    <?php if (!empty($userEvents)): ?>
                        <?php foreach ($userEvents as $event): ?>
                            <div class="user-event-item">
                                <div class="event-info">
                                    <div class="event-name">
                                        <a href="/events/<?= $event['id'] ?>"><?= $renderer->helper('e', $event['name']) ?></a>
                                    </div>
                                    <div class="event-meta">
                                        <span class="event-date">
                                            <i class="fas fa-calendar"></i>
                                            <?= $renderer->helper('date', $event['start_date'], 'M j, Y') ?>
                                        </span>
                                        <span class="event-time">
                                            <i class="fas fa-clock"></i>
                                            <?= $renderer->helper('date', $event['start_time'], 'g:i A') ?>
                                        </span>
                                        <?php if ($event['location']): ?>
                                            <span class="event-location">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?= $renderer->helper('e', $event['location']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="event-status">
                                    <?php if ($event['attendance_status'] === 'attended'): ?>
                                        <span class="status-badge status-success">
                                            <i class="fas fa-check"></i>
                                            Attended
                                        </span>
                                    <?php elseif ($event['attendance_status'] === 'missed'): ?>
                                        <span class="status-badge status-danger">
                                            <i class="fas fa-times"></i>
                                            Missed
                                        </span>
                                    <?php elseif ($event['status'] === 'upcoming'): ?>
                                        <span class="status-badge status-info">
                                            <i class="fas fa-calendar-plus"></i>
                                            Upcoming
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-warning">
                                            <i class="fas fa-clock"></i>
                                            <?= $renderer->helper('e', ucfirst($event['status'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="event-actions">
                                    <a href="/events/<?= $event['id'] ?>" class="btn btn-sm btn-outline">
                                        View Details
                                    </a>
                                    
                                    <?php if ($event['status'] === 'active' && !$event['checkin_id']): ?>
                                        <button class="btn btn-sm btn-primary" onclick="quickCheckin(<?= $event['id'] ?>)">
                                            Check In
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-calendar-times"></i>
                            </div>
                            <h3>No events found</h3>
                            <p>You haven't attended any events yet.</p>
                            <a href="/events" class="btn btn-primary">Browse Events</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
        
        <!-- Settings Tab -->
        <div class="tab-panel" id="settings-tab">
            
            <!-- Profile Information -->
            <div class="profile-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-user"></i>
                        Profile Information
                    </h2>
                </div>
                
                <form id="profileForm" class="profile-form">
                    <?= $renderer->helper('csrfField') ?>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-input" 
                                   value="<?= $renderer->helper('e', $user['first_name'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-input" 
                                   value="<?= $renderer->helper('e', $user['last_name'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" class="form-input" 
                                   value="<?= $renderer->helper('e', $user['email'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-input" 
                                   value="<?= $renderer->helper('e', $user['phone'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="bio" class="form-label">Bio</label>
                        <textarea id="bio" name="bio" class="form-textarea" rows="3" 
                                  placeholder="Tell us about yourself..."><?= $renderer->helper('e', $user['bio'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Save Changes
                        </button>
                        <button type="button" class="btn btn-outline" id="cancelEditBtn">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- RFID Information -->
            <div class="profile-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-id-card"></i>
                        RFID Information
                    </h2>
                </div>
                
                <div class="rfid-info">
                    <?php if (!empty($user['rfid_tag'])): ?>
                        <div class="rfid-card">
                            <div class="rfid-status status-active">
                                <i class="fas fa-check-circle"></i>
                                RFID Tag Assigned
                            </div>
                            <div class="rfid-details">
                                <div class="rfid-tag">
                                    <strong>Tag ID:</strong> <?= $renderer->helper('e', $user['rfid_tag']) ?>
                                </div>
                                <div class="rfid-assigned">
                                    <strong>Assigned:</strong> <?= $renderer->helper('date', $user['rfid_assigned_at'], 'M j, Y') ?>
                                </div>
                            </div>
                            
                            <?php if ($renderer->helper('can', 'manage_rfid')): ?>
                                <div class="rfid-actions">
                                    <button class="btn btn-sm btn-outline" id="updateRfidBtn">
                                        <i class="fas fa-edit"></i>
                                        Update Tag
                                    </button>
                                    <button class="btn btn-sm btn-danger" id="removeRfidBtn">
                                        <i class="fas fa-trash"></i>
                                        Remove Tag
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="rfid-card">
                            <div class="rfid-status status-inactive">
                                <i class="fas fa-exclamation-circle"></i>
                                No RFID Tag Assigned
                            </div>
                            <p>You don't have an RFID tag assigned yet. Contact an administrator to get one assigned.</p>
                            
                            <?php if ($renderer->helper('can', 'manage_rfid')): ?>
                                <div class="rfid-actions">
                                    <button class="btn btn-primary" id="assignRfidBtn">
                                        <i class="fas fa-plus"></i>
                                        Assign RFID Tag
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Notification Preferences -->
            <div class="profile-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-bell"></i>
                        Notification Preferences
                    </h2>
                </div>
                
                <form id="notificationForm" class="notification-form">
                    <?= $renderer->helper('csrfField') ?>
                    
                    <div class="notification-options">
                        <div class="notification-group">
                            <div class="notification-item">
                                <div class="notification-info">
                                    <div class="notification-title">Email Notifications</div>
                                    <div class="notification-description">Receive email updates about events and check-ins</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="email_notifications" 
                                           <?= (!empty($user['email_notifications'])) ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <div class="notification-item">
                                <div class="notification-info">
                                    <div class="notification-title">Event Reminders</div>
                                    <div class="notification-description">Get reminded about upcoming events you're registered for</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="event_reminders" 
                                           <?= (!empty($user['event_reminders'])) ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <div class="notification-item">
                                <div class="notification-info">
                                    <div class="notification-title">Check-in Confirmations</div>
                                    <div class="notification-description">Receive confirmation when you check in or out</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="checkin_confirmations" 
                                           <?= (!empty($user['checkin_confirmations'])) ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Save Preferences
                        </button>
                    </div>
                </form>
            </div>
            
        </div>
        
    </div>
    
</div>

<?php $renderer->startSection('scripts'); ?>
<script src="<?= $renderer->helper('asset', 'js/profile/profile.js') ?>"></script>
<script src="<?= $renderer->helper('asset', 'js/charts.js') ?>"></script>
<script>
// Set CSRF token for API calls
window.App = window.App || {};
window.App.csrfToken = <?= json_encode($renderer->helper('csrfToken')) ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize profile manager
    if (typeof ProfileManager !== 'undefined') {
        new ProfileManager({
            userId: <?= json_encode($user['id']) ?>,
            activityChart: document.getElementById('activity-chart'),
            profileForm: document.getElementById('profileForm'),
            notificationForm: document.getElementById('notificationForm')
        });
    }
    
    // Tab switching functionality
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabPanels = document.querySelectorAll('.tab-panel');
    
    tabButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            switchTab(tabId);
        });
    });
    
    window.switchTab = function(tabId) {
        // Update active button
        tabButtons.forEach(btn => btn.classList.remove('active'));
        document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
        
        // Update active panel
        tabPanels.forEach(panel => panel.classList.remove('active'));
        document.getElementById(tabId + '-tab').classList.add('active');
        
        // Load tab-specific data
        if (tabId === 'checkins') {
            loadCheckins();
        } else if (tabId === 'events') {
            loadUserEvents();
        }
    };
    
    // Avatar upload functionality
    const avatarUploadBtn = document.getElementById('avatarUploadBtn');
    const avatarUpload = document.getElementById('avatarUpload');
    
    if (avatarUploadBtn && avatarUpload) {
        avatarUploadBtn.addEventListener('click', () => avatarUpload.click());
        
        avatarUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                uploadAvatar(file);
            }
        });
    }
    
    // Profile form submission
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            updateProfile();
        });
    }
    
    // Notification form submission
    const notificationForm = document.getElementById('notificationForm');
    if (notificationForm) {
        notificationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            updateNotificationPreferences();
        });
    }
    
    // Period change handlers
    const activityPeriod = document.getElementById('activity-period');
    if (activityPeriod) {
        activityPeriod.addEventListener('change', function() {
            loadActivityChart(this.value);
        });
    }
    
    // Filter handlers
    const checkinFilter = document.getElementById('checkin-filter');
    const eventFilter = document.getElementById('event-filter');
    
    if (checkinFilter) {
        checkinFilter.addEventListener('change', loadCheckins);
    }
    
    if (eventFilter) {
        eventFilter.addEventListener('change', loadUserEvents);
    }
    
    // Search handlers
    const checkinSearch = document.getElementById('checkin-search');
    if (checkinSearch) {
        let timeout;
        checkinSearch.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(loadCheckins, 500);
        });
    }
    
    // Load more functionality
    const loadMoreBtn = document.getElementById('loadMoreCheckins');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', loadMoreCheckins);
    }
    
    // Functions
    function uploadAvatar(file) {
        const formData = new FormData();
        formData.append('avatar', file);
        formData.append('_token', window.App.csrfToken);
        
        fetch('/api/profile/avatar', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update avatar display
                const avatarImage = document.querySelector('.avatar-image');
                const avatarPlaceholder = document.querySelector('.avatar-placeholder');
                
                if (data.avatar_url) {
                    if (avatarImage) {
                        avatarImage.src = data.avatar_url;
                    } else if (avatarPlaceholder) {
                        avatarPlaceholder.outerHTML = `<img src="${data.avatar_url}" alt="Profile Picture" class="avatar-image">`;
                    }
                }
                
                if (typeof window.showNotification === 'function') {
                    window.showNotification('success', 'Profile picture updated successfully!');
                }
            } else {
                if (typeof window.showNotification === 'function') {
                    window.showNotification('error', data.message || 'Failed to update profile picture');
                }
            }
        })
        .catch(error => {
            console.error('Avatar upload error:', error);
            if (typeof window.showNotification === 'function') {
                window.showNotification('error', 'An error occurred while updating your profile picture');
            }
        });
    }
    
    function updateProfile() {
        const formData = new FormData(profileForm);
        
        const btn = profileForm.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        btn.disabled = true;
        
        fetch('/api/profile/update', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof window.showNotification === 'function') {
                    window.showNotification('success', 'Profile updated successfully!');
                }
                
                // Update profile display
                const profileName = document.querySelector('.profile-name');
                if (profileName) {
                    profileName.textContent = `${formData.get('first_name')} ${formData.get('last_name')}`;
                }
            } else {
                if (typeof window.showNotification === 'function') {
                    window.showNotification('error', data.message || 'Failed to update profile');
                }
            }
        })
        .catch(error => {
            console.error('Profile update error:', error);
            if (typeof window.showNotification === 'function') {
                window.showNotification('error', 'An error occurred while updating your profile');
            }
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
    
    function updateNotificationPreferences() {
        const formData = new FormData(notificationForm);
        
        const btn = notificationForm.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        btn.disabled = true;
        
        fetch('/api/profile/notifications', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof window.showNotification === 'function') {
                    window.showNotification('success', 'Notification preferences updated!');
                }
            } else {
                if (typeof window.showNotification === 'function') {
                    window.showNotification('error', data.message || 'Failed to update preferences');
                }
            }
        })
        .catch(error => {
            console.error('Notification update error:', error);
            if (typeof window.showNotification === 'function') {
                window.showNotification('error', 'An error occurred while updating preferences');
            }
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
    
    function loadActivityChart(period) {
        const chartContainer = document.getElementById('activity-chart').parentElement;
        const loading = document.getElementById('activity-chart-loading');
        
        loading.style.display = 'flex';
        
        fetch(`/api/profile/activity-chart?period=${period}`, {
            headers: {
                'X-CSRF-TOKEN': window.App.csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateActivityChart(data.data);
            }
        })
        .catch(error => {
            console.error('Activity chart error:', error);
        })
        .finally(() => {
            loading.style.display = 'none';
        });
    }
    
    function loadCheckins() {
        const filter = checkinFilter?.value || 'all';
        const search = checkinSearch?.value || '';
        
        fetch(`/api/profile/checkins?filter=${filter}&search=${encodeURIComponent(search)}`, {
            headers: {
                'X-CSRF-TOKEN': window.App.csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCheckinsDisplay(data.data);
            }
        })
        .catch(error => {
            console.error('Checkins load error:', error);
        });
    }
    
    function loadUserEvents() {
        const filter = eventFilter?.value || 'all';
        
        fetch(`/api/profile/events?filter=${filter}`, {
            headers: {
                'X-CSRF-TOKEN': window.App.csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateEventsDisplay(data.data);
            }
        })
        .catch(error => {
            console.error('Events load error:', error);
        });
    }
    
    // Initialize with current period
    loadActivityChart(activityPeriod?.value || 'month');
});
</script>
<?php $renderer->endSection(); ?>