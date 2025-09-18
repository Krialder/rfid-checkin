<?php
/**
 * System Settings
 * 
 * System-wide configuration and settings management interface.
 * 
 * @package RfidCheckin\Admin
 * @author Kralder
 */

require_once '../core/config.php';
require_once '../core/database.php';
require_once '../core/auth.php';

// Check if user is admin
if (!Auth::isLoggedIn() || !Auth::hasRole(['admin'])) {
    http_response_code(403);
    header('Location: ../auth/login.php');
    exit;
}

$user = Auth::getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Electronic Check-in System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <link rel="stylesheet" href="../assets/css/admin-tools.css">
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <h1>⚙️ System Settings</h1>
            <p class="subtitle">Configure system-wide settings and preferences</p>
        </div>
        
        <div class="dashboard-grid">
            <!-- General Settings -->
            <div class="card">
                <h3>🔧 General Settings</h3>
                <div class="info-box">
                    <h4>🚧 Coming Soon</h4>
                    <p>This section will include:</p>
                    <ul>
                        <li>System name and branding configuration</li>
                        <li>Default timezone and localization settings</li>
                        <li>Session timeout and security policies</li>
                        <li>Email server configuration</li>
                        <li>Notification templates and settings</li>
                    </ul>
                </div>
            </div>
            
            <!-- Security Settings -->
            <div class="card">
                <h3>🔒 Security Settings</h3>
                <div class="info-box">
                    <h4>🚧 Advanced Security Coming Soon</h4>
                    <p>This section will include:</p>
                    <ul>
                        <li>Password policy configuration</li>
                        <li>Two-factor authentication settings</li>
                        <li>Login attempt limits and lockout policies</li>
                        <li>API rate limiting configuration</li>
                        <li>SSL/TLS certificate management</li>
                    </ul>
                </div>
            </div>
            
            <!-- Current Configuration -->
            <div class="card">
                <h3>📋 Current Configuration</h3>
                <div class="config-display">
                    <div class="config-item">
                        <strong>Database Host:</strong> <?php echo DB_HOST; ?>
                    </div>
                    <div class="config-item">
                        <strong>Database Name:</strong> <?php echo DB_NAME; ?>
                    </div>
                    <div class="config-item">
                        <strong>Debug Mode:</strong> <?php echo DEBUG_MODE ? 'Enabled' : 'Disabled'; ?>
                    </div>
                    <div class="config-item">
                        <strong>Session Lifetime:</strong> <?php echo SESSION_LIFETIME; ?> seconds
                    </div>
                    <div class="config-item">
                        <strong>Min Password Length:</strong> <?php echo PASSWORD_MIN_LENGTH; ?> characters
                    </div>
                    <div class="config-item">
                        <strong>PHP Version:</strong> <?php echo PHP_VERSION; ?>
                    </div>
                    <div class="config-item">
                        <strong>Timezone:</strong> <?php echo date_default_timezone_get(); ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <h3>⚡ Quick Actions</h3>
                <div class="quick-actions">
                    <button class="btn btn-secondary" onclick="alert('Feature coming soon!')">
                        🔄 Clear All Caches
                    </button>
                    <button class="btn btn-secondary" onclick="alert('Feature coming soon!')">
                        📧 Test Email Settings
                    </button>
                    <button class="btn btn-secondary" onclick="alert('Feature coming soon!')">
                        🗄️ Database Maintenance
                    </button>
                    <a href="../database/validate-database.php" class="btn btn-primary">
                        🔍 Database Validator
                    </a>
                </div>
            </div>
            
            <!-- System Status -->
            <div class="card">
                <h3>📊 System Status</h3>
                <div class="status-grid">
                    <div class="status-item status-good">
                        <span class="status-icon">✅</span>
                        <div>
                            <strong>Database</strong>
                            <small>Connected</small>
                        </div>
                    </div>
                    <div class="status-item status-good">
                        <span class="status-icon">✅</span>
                        <div>
                            <strong>Session System</strong>
                            <small>Working</small>
                        </div>
                    </div>
                    <div class="status-item status-good">
                        <span class="status-icon">✅</span>
                        <div>
                            <strong>File Permissions</strong>
                            <small>Correct</small>
                        </div>
                    </div>
                    <div class="status-item status-warning">
                        <span class="status-icon">⚠️</span>
                        <div>
                            <strong>Debug Mode</strong>
                            <small><?php echo DEBUG_MODE ? 'Enabled (Dev)' : 'Disabled (Prod)'; ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    
    <?php include '../includes/theme_script.php'; ?>
</body>
</html>
