<?php
/**
 * System Settings - Placeholder
 * System-wide configuration and settings management
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
                <div style="background: #e8f4fd; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h4>🚧 Coming Soon</h4>
                    <p>This section will include:</p>
                    <ul style="margin: 15px 0; padding-left: 25px;">
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
                <div style="background: #e8f4fd; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h4>🚧 Advanced Security Coming Soon</h4>
                    <p>This section will include:</p>
                    <ul style="margin: 15px 0; padding-left: 25px;">
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
                    <a href="../admin_dev_tools.php" class="btn btn-info">
                        🛠️ Database Inspector
                    </a>
                    <a href="../system_test.php" class="btn btn-primary">
                        🧪 System Test
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
    
    <style>
        .config-display {
            display: grid;
            gap: 15px;
            margin: 20px 0;
        }
        
        .config-item {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 6px;
            border-left: 4px solid #007cba;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .status-item.status-good {
            background: #f0f8f0;
            border-color: #28a745;
        }
        
        .status-item.status-warning {
            background: #fff8e1;
            border-color: #ffc107;
        }
        
        .status-icon {
            font-size: 1.5rem;
        }
        
        .status-item strong {
            display: block;
            margin-bottom: 2px;
        }
        
        .status-item small {
            color: #6c757d;
            font-size: 0.85rem;
        }
    </style>
    
    <?php include '../includes/theme_script.php'; ?>
</body>
</html>
