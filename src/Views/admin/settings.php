<?php
$page_title = 'System Settings';
$page_description = 'Configure system settings, security, and preferences';
$breadcrumbs = [
    ['title' => 'Administration', 'url' => '/admin', 'icon' => 'fas fa-shield-alt'],
    ['title' => 'Settings', 'url' => '/admin/settings', 'icon' => 'fas fa-cogs']
];
$current_page = 'admin-settings';

// Page-specific assets
$assets = [
    'css' => ['admin.css', 'settings.css', 'forms.css'],
    'js' => ['admin-settings.js', 'form-validation.js', 'file-upload.js']
];

// Page actions
$page_actions = [
    ['title' => 'Backup System', 'url' => '#', 'type' => 'primary', 'icon' => 'fas fa-download', 'onclick' => 'settingsManager.backupSystem()'],
    ['title' => 'System Health', 'url' => '#', 'type' => 'outline', 'icon' => 'fas fa-heartbeat', 'onclick' => 'settingsManager.checkSystemHealth()'],
    ['title' => 'Reset Settings', 'url' => '#', 'type' => 'outline', 'icon' => 'fas fa-undo', 'onclick' => 'settingsManager.resetToDefaults()']
];
?>

<!-- System Settings Interface -->
<div class="admin-content">
    
    <!-- Page Header -->
    <div class="admin-header">
        <div class="header-content">
            <div class="header-info">
                <h1 class="page-title">
                    <i class="fas fa-cogs"></i>
                    System Settings
                </h1>
                <p class="page-description">
                    Configure system settings, security preferences, and operational parameters.
                    <span class="last-updated">Last updated: <strong id="last-updated-text"><?= date('M j, Y g:i A') ?></strong></span>
                </p>
            </div>
            
            <div class="header-stats">
                <div class="stat-item">
                    <div class="stat-value" id="system-status">Online</div>
                    <div class="stat-label">Status</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value" id="last-backup">2 days ago</div>
                    <div class="stat-label">Last Backup</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value" id="config-version">v1.2.3</div>
                    <div class="stat-label">Version</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Settings Navigation -->
    <div class="settings-navigation">
        <div class="nav-tabs">
            <button type="button" class="nav-tab active" data-tab="general">
                <i class="fas fa-cog"></i>
                General
            </button>
            <button type="button" class="nav-tab" data-tab="security">
                <i class="fas fa-shield-alt"></i>
                Security
            </button>
            <button type="button" class="nav-tab" data-tab="email">
                <i class="fas fa-envelope"></i>
                Email
            </button>
            <button type="button" class="nav-tab" data-tab="rfid">
                <i class="fas fa-id-card"></i>
                RFID
            </button>
            <button type="button" class="nav-tab" data-tab="notifications">
                <i class="fas fa-bell"></i>
                Notifications
            </button>
            <button type="button" class="nav-tab" data-tab="backup">
                <i class="fas fa-database"></i>
                Backup
            </button>
            <button type="button" class="nav-tab" data-tab="maintenance">
                <i class="fas fa-tools"></i>
                Maintenance
            </button>
        </div>
    </div>
    
    <!-- Settings Content -->
    <div class="settings-content">
        
        <!-- General Settings Tab -->
        <div class="settings-tab active" id="general-tab">
            <div class="settings-section">
                <div class="section-header">
                    <h3 class="section-title">System Information</h3>
                    <p class="section-description">Basic system configuration and display settings.</p>
                </div>
                
                <form id="general-settings-form" class="settings-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="system-name" class="form-label">System Name</label>
                            <input type="text" id="system-name" name="system_name" class="form-input" value="RFID Check-in System" required>
                            <div class="form-help">This name appears in the browser title and navigation.</div>
                        </div>
                        <div class="form-group">
                            <label for="system-description" class="form-label">Description</label>
                            <input type="text" id="system-description" name="system_description" class="form-input" value="Event attendance tracking system">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="timezone" class="form-label">Timezone</label>
                            <select id="timezone" name="timezone" class="form-select" required>
                                <option value="America/New_York" selected>Eastern Time (EST/EDT)</option>
                                <option value="America/Chicago">Central Time (CST/CDT)</option>
                                <option value="America/Denver">Mountain Time (MST/MDT)</option>
                                <option value="America/Los_Angeles">Pacific Time (PST/PDT)</option>
                                <option value="UTC">UTC</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date-format" class="form-label">Date Format</label>
                            <select id="date-format" name="date_format" class="form-select">
                                <option value="m/d/Y" selected>MM/DD/YYYY</option>
                                <option value="d/m/Y">DD/MM/YYYY</option>
                                <option value="Y-m-d">YYYY-MM-DD</option>
                                <option value="M j, Y">Month DD, YYYY</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="language" class="form-label">Default Language</label>
                            <select id="language" name="language" class="form-select">
                                <option value="en" selected>English</option>
                                <option value="es">Spanish</option>
                                <option value="fr">French</option>
                                <option value="de">German</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="items-per-page" class="form-label">Items Per Page</label>
                            <select id="items-per-page" name="items_per_page" class="form-select">
                                <option value="10">10</option>
                                <option value="25" selected>25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">System Features</label>
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="enable_registration" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Allow User Registration</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="enable_guest_checkin" class="checkbox-input">
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Enable Guest Check-in</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="enable_analytics" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Enable Analytics Tracking</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="maintenance_mode" class="checkbox-input">
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Maintenance Mode</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="settingsManager.resetForm('general')">Reset</button>
                        <button type="submit" class="btn btn-primary">Save General Settings</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Security Settings Tab -->
        <div class="settings-tab" id="security-tab">
            <div class="settings-section">
                <div class="section-header">
                    <h3 class="section-title">Security Configuration</h3>
                    <p class="section-description">Configure security policies and access controls.</p>
                </div>
                
                <form id="security-settings-form" class="settings-form">
                    <div class="form-group">
                        <label for="password-policy" class="form-label">Password Policy</label>
                        <div class="password-policy-settings">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="min-password-length" class="form-label">Minimum Length</label>
                                    <input type="number" id="min-password-length" name="min_password_length" class="form-input" value="8" min="6" max="20">
                                </div>
                                <div class="form-group">
                                    <label for="password-expiry" class="form-label">Password Expiry (days)</label>
                                    <input type="number" id="password-expiry" name="password_expiry" class="form-input" value="90" min="30" max="365">
                                </div>
                            </div>
                            
                            <div class="checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="require_uppercase" class="checkbox-input" checked>
                                    <span class="checkbox-custom"></span>
                                    <span class="checkbox-text">Require Uppercase Letters</span>
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="require_lowercase" class="checkbox-input" checked>
                                    <span class="checkbox-custom"></span>
                                    <span class="checkbox-text">Require Lowercase Letters</span>
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="require_numbers" class="checkbox-input" checked>
                                    <span class="checkbox-custom"></span>
                                    <span class="checkbox-text">Require Numbers</span>
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="require_special" class="checkbox-input">
                                    <span class="checkbox-custom"></span>
                                    <span class="checkbox-text">Require Special Characters</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="session-timeout" class="form-label">Session Timeout (minutes)</label>
                            <select id="session-timeout" name="session_timeout" class="form-select">
                                <option value="15">15 minutes</option>
                                <option value="30" selected>30 minutes</option>
                                <option value="60">1 hour</option>
                                <option value="120">2 hours</option>
                                <option value="480">8 hours</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="max-login-attempts" class="form-label">Max Login Attempts</label>
                            <input type="number" id="max-login-attempts" name="max_login_attempts" class="form-input" value="5" min="3" max="10">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="lockout-duration" class="form-label">Lockout Duration (minutes)</label>
                            <input type="number" id="lockout-duration" name="lockout_duration" class="form-input" value="15" min="5" max="60">
                        </div>
                        <div class="form-group">
                            <label for="two-factor-auth" class="form-label">Two-Factor Authentication</label>
                            <select id="two-factor-auth" name="two_factor_auth" class="form-select">
                                <option value="disabled">Disabled</option>
                                <option value="optional">Optional</option>
                                <option value="required" selected>Required for Admins</option>
                                <option value="all">Required for All Users</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Security Features</label>
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="enable_csrf_protection" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Enable CSRF Protection</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="enable_rate_limiting" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Enable Rate Limiting</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="log_failed_logins" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Log Failed Login Attempts</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="enable_ip_whitelist" class="checkbox-input">
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Enable IP Whitelist</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="settingsManager.resetForm('security')">Reset</button>
                        <button type="submit" class="btn btn-primary">Save Security Settings</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Email Settings Tab -->
        <div class="settings-tab" id="email-tab">
            <div class="settings-section">
                <div class="section-header">
                    <h3 class="section-title">Email Configuration</h3>
                    <p class="section-description">Configure email server settings and notification preferences.</p>
                </div>
                
                <form id="email-settings-form" class="settings-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="smtp-host" class="form-label">SMTP Host</label>
                            <input type="text" id="smtp-host" name="smtp_host" class="form-input" placeholder="smtp.gmail.com">
                        </div>
                        <div class="form-group">
                            <label for="smtp-port" class="form-label">SMTP Port</label>
                            <input type="number" id="smtp-port" name="smtp_port" class="form-input" value="587" min="25" max="2525">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="smtp-username" class="form-label">SMTP Username</label>
                            <input type="email" id="smtp-username" name="smtp_username" class="form-input">
                        </div>
                        <div class="form-group">
                            <label for="smtp-password" class="form-label">SMTP Password</label>
                            <input type="password" id="smtp-password" name="smtp_password" class="form-input">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="smtp-encryption" class="form-label">Encryption</label>
                            <select id="smtp-encryption" name="smtp_encryption" class="form-select">
                                <option value="none">None</option>
                                <option value="tls" selected>TLS</option>
                                <option value="ssl">SSL</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="from-email" class="form-label">From Email</label>
                            <input type="email" id="from-email" name="from_email" class="form-input" placeholder="noreply@yoursite.com">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="from-name" class="form-label">From Name</label>
                        <input type="text" id="from-name" name="from_name" class="form-input" value="RFID Check-in System">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Notifications</label>
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="send_welcome_email" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Send Welcome Email to New Users</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="send_password_reset" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Send Password Reset Emails</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="send_event_reminders" class="checkbox-input">
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Send Event Reminders</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="send_admin_notifications" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Send Admin Notifications</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="settingsManager.testEmail()">Test Email</button>
                        <button type="button" class="btn btn-outline" onclick="settingsManager.resetForm('email')">Reset</button>
                        <button type="submit" class="btn btn-primary">Save Email Settings</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- RFID Settings Tab -->
        <div class="settings-tab" id="rfid-tab">
            <div class="settings-section">
                <div class="section-header">
                    <h3 class="section-title">RFID Configuration</h3>
                    <p class="section-description">Configure RFID device settings and scanning parameters.</p>
                </div>
                
                <form id="rfid-settings-form" class="settings-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="rfid-scan-timeout" class="form-label">Scan Timeout (seconds)</label>
                            <input type="number" id="rfid-scan-timeout" name="scan_timeout" class="form-input" value="5" min="1" max="30">
                        </div>
                        <div class="form-group">
                            <label for="duplicate-scan-window" class="form-label">Duplicate Scan Window (seconds)</label>
                            <input type="number" id="duplicate-scan-window" name="duplicate_scan_window" class="form-input" value="30" min="5" max="300">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="max-devices" class="form-label">Maximum Devices</label>
                            <input type="number" id="max-devices" name="max_devices" class="form-input" value="10" min="1" max="50">
                        </div>
                        <div class="form-group">
                            <label for="device-heartbeat" class="form-label">Device Heartbeat (seconds)</label>
                            <input type="number" id="device-heartbeat" name="device_heartbeat" class="form-input" value="60" min="30" max="300">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="tag-format" class="form-label">Tag ID Format</label>
                        <select id="tag-format" name="tag_format" class="form-select">
                            <option value="hex" selected>Hexadecimal</option>
                            <option value="decimal">Decimal</option>
                            <option value="raw">Raw</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">RFID Features</label>
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="auto_assign_tags" class="checkbox-input">
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Auto-assign New Tags</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="validate_tag_format" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Validate Tag Format</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="log_scan_attempts" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Log All Scan Attempts</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="enable_fallback_manual" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Enable Manual Fallback</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="settingsManager.resetForm('rfid')">Reset</button>
                        <button type="submit" class="btn btn-primary">Save RFID Settings</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Notifications Settings Tab -->
        <div class="settings-tab" id="notifications-tab">
            <div class="settings-section">
                <div class="section-header">
                    <h3 class="section-title">Notification Settings</h3>
                    <p class="section-description">Configure system notifications and alerts.</p>
                </div>
                
                <form id="notifications-settings-form" class="settings-form">
                    <div class="form-group">
                        <label class="form-label">Admin Notifications</label>
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="notify_new_users" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">New User Registrations</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="notify_failed_logins" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Failed Login Attempts</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="notify_system_errors" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">System Errors</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="notify_device_offline" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Device Offline</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">User Notifications</label>
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="notify_event_reminders" class="checkbox-input">
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Event Reminders</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="notify_checkin_confirmation" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Check-in Confirmations</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="notify_profile_changes" class="checkbox-input">
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Profile Changes</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="notification-method" class="form-label">Primary Method</label>
                            <select id="notification-method" name="notification_method" class="form-select">
                                <option value="email" selected>Email</option>
                                <option value="sms">SMS</option>
                                <option value="push">Push Notification</option>
                                <option value="webhook">Webhook</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="notification-frequency" class="form-label">Frequency</label>
                            <select id="notification-frequency" name="notification_frequency" class="form-select">
                                <option value="immediate" selected>Immediate</option>
                                <option value="hourly">Hourly Digest</option>
                                <option value="daily">Daily Digest</option>
                                <option value="weekly">Weekly Summary</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="settingsManager.resetForm('notifications')">Reset</button>
                        <button type="submit" class="btn btn-primary">Save Notification Settings</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Backup Settings Tab -->
        <div class="settings-tab" id="backup-tab">
            <div class="settings-section">
                <div class="section-header">
                    <h3 class="section-title">Backup & Recovery</h3>
                    <p class="section-description">Configure automated backups and recovery options.</p>
                </div>
                
                <form id="backup-settings-form" class="settings-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="backup-frequency" class="form-label">Backup Frequency</label>
                            <select id="backup-frequency" name="backup_frequency" class="form-select">
                                <option value="manual">Manual Only</option>
                                <option value="daily" selected>Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="backup-time" class="form-label">Backup Time</label>
                            <input type="time" id="backup-time" name="backup_time" class="form-input" value="02:00">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="retention-period" class="form-label">Retention Period (days)</label>
                            <input type="number" id="retention-period" name="retention_period" class="form-input" value="30" min="7" max="365">
                        </div>
                        <div class="form-group">
                            <label for="max-backups" class="form-label">Maximum Backups</label>
                            <input type="number" id="max-backups" name="max_backups" class="form-input" value="10" min="3" max="50">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Backup Components</label>
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="backup_database" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Database</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="backup_files" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">System Files</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="backup_uploads" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">User Uploads</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="backup_logs" class="checkbox-input">
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Log Files</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="backup-status">
                        <h4>Recent Backups</h4>
                        <div id="recent-backups-list">
                            <!-- Backup list will be loaded here -->
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="settingsManager.createBackup()">Create Backup Now</button>
                        <button type="button" class="btn btn-outline" onclick="settingsManager.resetForm('backup')">Reset</button>
                        <button type="submit" class="btn btn-primary">Save Backup Settings</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Maintenance Settings Tab -->
        <div class="settings-tab" id="maintenance-tab">
            <div class="settings-section">
                <div class="section-header">
                    <h3 class="section-title">System Maintenance</h3>
                    <p class="section-description">System maintenance tools and cleanup operations.</p>
                </div>
                
                <div class="maintenance-tools">
                    <div class="tool-card">
                        <div class="tool-header">
                            <i class="fas fa-database"></i>
                            <h4>Database Optimization</h4>
                        </div>
                        <p>Optimize database tables and rebuild indexes for better performance.</p>
                        <div class="tool-actions">
                            <button type="button" class="btn btn-outline" onclick="settingsManager.optimizeDatabase()">
                                <i class="fas fa-play"></i>
                                Optimize Now
                            </button>
                        </div>
                    </div>
                    
                    <div class="tool-card">
                        <div class="tool-header">
                            <i class="fas fa-trash"></i>
                            <h4>Log Cleanup</h4>
                        </div>
                        <p>Remove old log files and free up disk space.</p>
                        <div class="tool-actions">
                            <button type="button" class="btn btn-outline" onclick="settingsManager.cleanupLogs()">
                                <i class="fas fa-play"></i>
                                Cleanup Logs
                            </button>
                        </div>
                    </div>
                    
                    <div class="tool-card">
                        <div class="tool-header">
                            <i class="fas fa-sync"></i>
                            <h4>Cache Clear</h4>
                        </div>
                        <p>Clear system cache and temporary files.</p>
                        <div class="tool-actions">
                            <button type="button" class="btn btn-outline" onclick="settingsManager.clearCache()">
                                <i class="fas fa-play"></i>
                                Clear Cache
                            </button>
                        </div>
                    </div>
                    
                    <div class="tool-card">
                        <div class="tool-header">
                            <i class="fas fa-heartbeat"></i>
                            <h4>System Health Check</h4>
                        </div>
                        <p>Run comprehensive system health diagnostics.</p>
                        <div class="tool-actions">
                            <button type="button" class="btn btn-outline" onclick="settingsManager.healthCheck()">
                                <i class="fas fa-play"></i>
                                Run Check
                            </button>
                        </div>
                    </div>
                </div>
                
                <form id="maintenance-settings-form" class="settings-form">
                    <div class="form-group">
                        <label class="form-label">Automatic Maintenance</label>
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="auto_optimize_db" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Auto Database Optimization (Weekly)</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="auto_cleanup_logs" class="checkbox-input" checked>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Auto Log Cleanup (Monthly)</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="auto_clear_cache" class="checkbox-input">
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Auto Cache Clear (Daily)</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Maintenance Settings</button>
                    </div>
                </form>
            </div>
        </div>
        
    </div>
    
</div>

<!-- Settings Management JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const settingsManager = new SettingsManager();
    settingsManager.init();
});

class SettingsManager {
    constructor() {
        this.currentTab = 'general';
        this.originalSettings = {};
        this.unsavedChanges = false;
    }
    
    init() {
        this.setupEventListeners();
        this.loadAllSettings();
        this.setupFormValidation();
    }
    
    setupEventListeners() {
        // Tab navigation
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                this.switchTab(e.target.dataset.tab);
            });
        });
        
        // Form submissions
        document.querySelectorAll('.settings-form').forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveSettings(form.id);
            });
        });
        
        // Track changes
        document.querySelectorAll('.settings-form input, .settings-form select, .settings-form textarea').forEach(input => {
            input.addEventListener('change', () => {
                this.markUnsavedChanges();
            });
        });
        
        // Warn about unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (this.unsavedChanges) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
    }
    
    switchTab(tab) {
        if (this.unsavedChanges) {
            if (!confirm('You have unsaved changes. Are you sure you want to switch tabs?')) {
                return;
            }
        }
        
        // Update active tab button
        document.querySelectorAll('.nav-tab').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
        
        // Hide all tab contents
        document.querySelectorAll('.settings-tab').forEach(content => {
            content.classList.remove('active');
        });
        
        // Show selected tab
        document.getElementById(`${tab}-tab`).classList.add('active');
        
        this.currentTab = tab;
        this.unsavedChanges = false;
    }
    
    async loadAllSettings() {
        try {
            const response = await fetch('/api/admin/settings', {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.originalSettings = data.data;
                this.populateAllForms(data.data);
                this.updateSystemInfo(data.data.system_info);
            } else {
                this.showError('Failed to load settings: ' + data.message);
            }
        } catch (error) {
            console.error('Error loading settings:', error);
            this.showError('Failed to load settings.');
        }
    }
    
    populateAllForms(settings) {
        // Populate general settings
        if (settings.general) {
            this.populateForm('general-settings-form', settings.general);
        }
        
        // Populate security settings
        if (settings.security) {
            this.populateForm('security-settings-form', settings.security);
        }
        
        // Populate email settings
        if (settings.email) {
            this.populateForm('email-settings-form', settings.email);
        }
        
        // Populate RFID settings
        if (settings.rfid) {
            this.populateForm('rfid-settings-form', settings.rfid);
        }
        
        // Populate notification settings
        if (settings.notifications) {
            this.populateForm('notifications-settings-form', settings.notifications);
        }
        
        // Populate backup settings
        if (settings.backup) {
            this.populateForm('backup-settings-form', settings.backup);
            this.loadRecentBackups();
        }
        
        // Populate maintenance settings
        if (settings.maintenance) {
            this.populateForm('maintenance-settings-form', settings.maintenance);
        }
    }
    
    populateForm(formId, data) {
        const form = document.getElementById(formId);
        if (!form) return;
        
        Object.keys(data).forEach(key => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
                if (input.type === 'checkbox') {
                    input.checked = Boolean(data[key]);
                } else {
                    input.value = data[key];
                }
            }
        });
    }
    
    updateSystemInfo(systemInfo) {
        if (!systemInfo) return;
        
        document.getElementById('system-status').textContent = systemInfo.status || 'Online';
        document.getElementById('last-backup').textContent = systemInfo.last_backup || 'Never';
        document.getElementById('config-version').textContent = systemInfo.version || 'Unknown';
        document.getElementById('last-updated-text').textContent = systemInfo.last_updated || 'Unknown';
    }
    
    async saveSettings(formId) {
        try {
            const form = document.getElementById(formId);
            const formData = new FormData(form);
            
            // Get settings category from form ID
            const category = formId.replace('-settings-form', '');
            
            const response = await fetch(`/api/admin/settings/${category}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                },
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(`${this.capitalizeFirst(category)} settings saved successfully.`);
                this.unsavedChanges = false;
                
                // Update last updated time
                document.getElementById('last-updated-text').textContent = new Date().toLocaleString();
            } else {
                this.showError('Failed to save settings: ' + data.message);
            }
        } catch (error) {
            console.error('Error saving settings:', error);
            this.showError('Failed to save settings.');
        }
    }
    
    resetForm(category) {
        if (!confirm('Are you sure you want to reset these settings to their original values?')) {
            return;
        }
        
        const originalData = this.originalSettings[category];
        if (originalData) {
            this.populateForm(`${category}-settings-form`, originalData);
            this.unsavedChanges = false;
            this.showSuccess('Settings reset to original values.');
        }
    }
    
    markUnsavedChanges() {
        this.unsavedChanges = true;
    }
    
    setupFormValidation() {
        // Add custom validation logic here
        const passwordLength = document.getElementById('min-password-length');
        if (passwordLength) {
            passwordLength.addEventListener('change', (e) => {
                const value = parseInt(e.target.value);
                if (value < 6 || value > 20) {
                    e.target.setCustomValidity('Password length must be between 6 and 20 characters.');
                } else {
                    e.target.setCustomValidity('');
                }
            });
        }
    }
    
    // Email testing
    async testEmail() {
        try {
            const form = document.getElementById('email-settings-form');
            const formData = new FormData(form);
            
            const response = await fetch('/api/admin/settings/test-email', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                },
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Test email sent successfully!');
            } else {
                this.showError('Failed to send test email: ' + data.message);
            }
        } catch (error) {
            console.error('Error testing email:', error);
            this.showError('Failed to test email.');
        }
    }
    
    // Backup operations
    async createBackup() {
        try {
            const response = await fetch('/api/admin/backup/create', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Backup created successfully!');
                this.loadRecentBackups();
            } else {
                this.showError('Failed to create backup: ' + data.message);
            }
        } catch (error) {
            console.error('Error creating backup:', error);
            this.showError('Failed to create backup.');
        }
    }
    
    async loadRecentBackups() {
        try {
            const response = await fetch('/api/admin/backup/recent', {
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.renderRecentBackups(data.data.backups);
            }
        } catch (error) {
            console.error('Error loading recent backups:', error);
        }
    }
    
    renderRecentBackups(backups) {
        const container = document.getElementById('recent-backups-list');
        
        if (backups.length === 0) {
            container.innerHTML = '<p class="no-backups">No recent backups found.</p>';
            return;
        }
        
        container.innerHTML = backups.map(backup => `
            <div class="backup-item">
                <div class="backup-info">
                    <div class="backup-name">${backup.filename}</div>
                    <div class="backup-meta">
                        <span class="backup-date">${this.formatDate(backup.created_at)}</span>
                        <span class="backup-size">${this.formatFileSize(backup.file_size)}</span>
                    </div>
                </div>
                <div class="backup-actions">
                    <button type="button" class="btn btn-sm btn-ghost" onclick="settingsManager.downloadBackup('${backup.backup_id}')">
                        <i class="fas fa-download"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-ghost" onclick="settingsManager.deleteBackup('${backup.backup_id}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');
    }
    
    // Maintenance operations
    async optimizeDatabase() {
        if (!confirm('This will optimize the database. Continue?')) return;
        
        try {
            const response = await fetch('/api/admin/maintenance/optimize-database', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Database optimized successfully!');
            } else {
                this.showError('Failed to optimize database: ' + data.message);
            }
        } catch (error) {
            console.error('Error optimizing database:', error);
            this.showError('Failed to optimize database.');
        }
    }
    
    async cleanupLogs() {
        if (!confirm('This will remove old log files. Continue?')) return;
        
        try {
            const response = await fetch('/api/admin/maintenance/cleanup-logs', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Log files cleaned up successfully!');
            } else {
                this.showError('Failed to cleanup logs: ' + data.message);
            }
        } catch (error) {
            console.error('Error cleaning up logs:', error);
            this.showError('Failed to cleanup logs.');
        }
    }
    
    async clearCache() {
        try {
            const response = await fetch('/api/admin/maintenance/clear-cache', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Cache cleared successfully!');
            } else {
                this.showError('Failed to clear cache: ' + data.message);
            }
        } catch (error) {
            console.error('Error clearing cache:', error);
            this.showError('Failed to clear cache.');
        }
    }
    
    async healthCheck() {
        try {
            const response = await fetch('/api/admin/maintenance/health-check', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.App.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showHealthCheckResults(data.data);
            } else {
                this.showError('Health check failed: ' + data.message);
            }
        } catch (error) {
            console.error('Error running health check:', error);
            this.showError('Failed to run health check.');
        }
    }
    
    showHealthCheckResults(results) {
        let message = 'Health Check Results:\n\n';
        
        Object.keys(results).forEach(check => {
            const result = results[check];
            message += `${check}: ${result.status}\n`;
            if (result.message) {
                message += `  ${result.message}\n`;
            }
        });
        
        alert(message);
    }
    
    // Utility methods
    capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString();
    }
    
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
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

// Expose settingsManager globally
window.settingsManager = null;
document.addEventListener('DOMContentLoaded', function() {
    window.settingsManager = new SettingsManager();
});
</script>
