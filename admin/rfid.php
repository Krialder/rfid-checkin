<?php
/**
 * RFID Device Management - Placeholder
 * This page will manage RFID devices when hardware is deployed
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
    <title>RFID Device Management - Electronic Check-in System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <h1>📟 RFID Device Management</h1>
            <p class="subtitle">Manage and monitor RFID scanning devices</p>
        </div>
        
        <div class="dashboard-grid">
            <!-- Device Status Overview -->
            <div class="card stats-card">
                <h3>📊 Device Status Overview</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-number">2</span>
                        <span class="stat-label">Active Devices</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">0</span>
                        <span class="stat-label">Offline Devices</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">1</span>
                        <span class="stat-label">Maintenance Required</span>
                    </div>
                </div>
            </div>
            
            <!-- Device List -->
            <div class="card">
                <h3>🔧 Device Management</h3>
                <div style="background: #e8f4fd; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h4>🚧 Coming Soon</h4>
                    <p>This section will include:</p>
                    <ul style="margin: 15px 0; padding-left: 25px;">
                        <li>Real-time device monitoring and status</li>
                        <li>Device configuration and settings management</li>
                        <li>Hardware diagnostics and troubleshooting</li>
                        <li>Firmware update management</li>
                        <li>Device location and assignment tracking</li>
                        <li>Usage statistics and maintenance scheduling</li>
                    </ul>
                    <p><strong>Hardware Integration Status:</strong> The system supports Arduino/NodeMCU RFID devices. See the <code>hardware/</code> folder for setup instructions.</p>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <h3>⚡ Quick Actions</h3>
                <div class="quick-actions">
                    <button class="btn btn-secondary" onclick="alert('Feature coming soon!')">
                        📱 Add New Device
                    </button>
                    <button class="btn btn-secondary" onclick="alert('Feature coming soon!')">
                        🔄 Refresh All Devices
                    </button>
                    <button class="btn btn-secondary" onclick="alert('Feature coming soon!')">
                        📊 Generate Device Report
                    </button>
                    <a href="../hardware/SETUP_GUIDE.md" class="btn btn-info">
                        📖 Hardware Setup Guide
                    </a>
                </div>
            </div>
            
            <!-- Current Implementation -->
            <div class="card">
                <h3>💡 Current Implementation</h3>
                <div style="background: #f0f8f0; padding: 15px; border-radius: 8px;">
                    <p><strong>Hardware Ready:</strong> The system includes complete Arduino/NodeMCU integration:</p>
                    <ul style="margin: 10px 0; padding-left: 25px;">
                        <li><code>hardware/NodeMCU_Simple.ino</code> - Basic RFID setup</li>
                        <li><code>hardware/NodeMCU_Modern.ino</code> - Advanced with web interface</li>
                        <li><code>hardware/Arduino_Mega_RFID.ino</code> - Enterprise dual-device setup</li>
                    </ul>
                    <p>RFID check-ins are processed through <code>api/rfid_checkin.php</code> and logged automatically.</p>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/theme_script.php'; ?>
</body>
</html>
