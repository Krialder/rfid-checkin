<?php
/**
 * RFID Device Management
 * This page manages RFID devices and hardware integration
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
            <!-- Registration Mode Control -->
            <div class="card">
                <h3>📋 Registration Mode</h3>
                <div class="info-box info">
                    <p><strong>Registration Mode</strong> allows ESP32 devices to accept any RFID tag (even unregistered ones) for user registration purposes.</p>
                    <p>When enabled, administrators can scan unregistered RFID tags which will be queued for assignment to users.</p>
                </div>
                
                <div id="registration-mode-status" class="registration-mode-container">
                    <div class="loading">Loading registration mode status...</div>
                </div>
                
                <div class="form-actions" style="margin-top: 1rem;">
                    <button id="toggle-registration-mode" class="btn btn-primary" disabled>
                        Loading...
                    </button>
                    <button id="refresh-status" class="btn btn-secondary">
                        🔄 Refresh Status
                    </button>
                </div>
            </div>
            
            <!-- RFID Scan Queue -->
            <div class="card full-width">
                <h3>📋 RFID Scan Queue</h3>
                <div class="info-box info">
                    <p>Recent RFID scans from hardware devices. In registration mode, unregistered tags can be assigned to users.</p>
                </div>
                
                <div class="queue-controls">
                    <button id="refresh-queue" class="btn btn-secondary">
                        🔄 Refresh Queue
                    </button>
                    <button id="clear-old-items" class="btn btn-outline">
                        🗑️ Clear Old Items
                    </button>
                </div>
                
                <div id="rfid-queue-container">
                    <div class="loading">Loading RFID queue...</div>
                </div>
            </div>
            
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
                <div class="info-box primary">
                    <h4>🚧 Coming Soon</h4>
                    <p>This section will include:</p>
                    <ul>
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
                <div class="info-box success">
                    <p><strong>Hardware Ready:</strong> The system includes complete Arduino/NodeMCU integration:</p>
                    <ul>
                        <li><code>hardware/NodeMCU_Simple.ino</code> - Basic RFID setup</li>
                        <li><code>hardware/NodeMCU_Modern.ino</code> - Advanced with web interface</li>
                        <li><code>hardware/Arduino_Mega_RFID.ino</code> - Enterprise dual-device setup</li>
                    </ul>
                    <p>RFID check-ins are processed through <code>api/rfid-checkin.php</code> and logged automatically.</p>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/theme_script.php'; ?>
    
    <script>
        // Registration Mode Management
        class RegistrationModeManager {
            constructor() {
                this.init();
            }
            
            init() {
                this.loadStatus();
                this.attachEventListeners();
                
                // Auto-refresh every 30 seconds
                setInterval(() => this.loadStatus(), 30000);
            }
            
            attachEventListeners() {
                document.getElementById('toggle-registration-mode').addEventListener('click', () => {
                    this.toggleMode();
                });
                
                document.getElementById('refresh-status').addEventListener('click', () => {
                    this.loadStatus();
                });
            }
            
            async loadStatus() {
                try {
                    console.log('Loading registration mode status...');
                    const response = await fetch('../api/registration-mode.php');
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        const text = await response.text();
                        console.error('Non-JSON response:', text);
                        throw new Error('Server returned non-JSON response: ' + text.substring(0, 100));
                    }
                    
                    const data = await response.json();
                    console.log('Registration mode status loaded:', data);
                    
                    if (response.ok) {
                        this.updateUI(data);
                    } else {
                        this.showError(data.error || 'Failed to load status');
                    }
                } catch (error) {
                    console.error('Load status error:', error);
                    this.showError('Network error: ' + error.message);
                }
            }
            
            async toggleMode() {
                const button = document.getElementById('toggle-registration-mode');
                const isCurrentlyEnabled = button.dataset.enabled === 'true';
                const action = isCurrentlyEnabled ? 'disable' : 'enable';
                
                button.disabled = true;
                button.textContent = isCurrentlyEnabled ? 'Disabling...' : 'Enabling...';
                
                try {
                    const formData = new FormData();
                    formData.append('action', action);
                    
                    const response = await fetch('../api/registration-mode.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (response.ok) {
                        this.showSuccess(data.message);
                        this.loadStatus(); // Reload to get updated status
                        // Also refresh the queue to show updated registration mode status
                        if (window.queueManager) {
                            window.queueManager.loadQueue();
                        }
                    } else {
                        this.showError(data.error || 'Failed to toggle mode');
                        button.disabled = false;
                    }
                } catch (error) {
                    this.showError('Network error: ' + error.message);
                    button.disabled = false;
                }
            }
            
            updateUI(data) {
                const statusContainer = document.getElementById('registration-mode-status');
                const toggleButton = document.getElementById('toggle-registration-mode');
                
                if (data.registration_mode_enabled) {
                    statusContainer.innerHTML = `
                        <div class="status-active">
                            <div class="status-indicator"></div>
                            <div class="status-info">
                                <h4>🟢 Registration Mode ACTIVE</h4>
                                <p>ESP32 devices will accept any RFID tag for registration</p>
                                ${data.session_info ? `
                                    <div class="session-details">
                                        <small>Started by: ${data.session_info.admin_name}</small><br>
                                        <small>Time: ${new Date(data.session_info.started_at).toLocaleString()}</small>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                    
                    toggleButton.textContent = '🔴 Disable Registration Mode';
                    toggleButton.className = 'btn btn-danger';
                    toggleButton.dataset.enabled = 'true';
                } else {
                    statusContainer.innerHTML = `
                        <div class="status-inactive">
                            <div class="status-indicator"></div>
                            <div class="status-info">
                                <h4>🔴 Registration Mode DISABLED</h4>
                                <p>Only registered RFID tags will be accepted</p>
                            </div>
                        </div>
                    `;
                    
                    toggleButton.textContent = '🟢 Enable Registration Mode';
                    toggleButton.className = 'btn btn-success';
                    toggleButton.dataset.enabled = 'false';
                }
                
                toggleButton.disabled = false;
            }
            
            showSuccess(message) {
                this.showMessage(message, 'success');
            }
            
            showError(message) {
                this.showMessage(message, 'error');
            }
            
            showMessage(message, type) {
                // Create notification element
                const notification = document.createElement('div');
                notification.className = `notification notification-${type}`;
                notification.textContent = message;
                
                // Add to page
                document.body.appendChild(notification);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 5000);
            }
        }
        
        // RFID Queue Manager
        class RFIDQueueManager {
            constructor() {
                this.init();
            }
            
            init() {
                this.loadQueue();
                this.attachEventListeners();
                
                // Auto-refresh every 15 seconds
                setInterval(() => this.loadQueue(), 15000);
            }
            
            attachEventListeners() {
                document.getElementById('refresh-queue').addEventListener('click', () => {
                    this.loadQueue();
                });
                
                document.getElementById('clear-old-items').addEventListener('click', () => {
                    this.clearOldItems();
                });
            }
            
            async loadQueue() {
                try {
                    console.log('Loading RFID queue...');
                    const response = await fetch('../api/rfid-queue-manager.php');
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        const text = await response.text();
                        console.error('Non-JSON response from queue API:', text);
                        throw new Error('Server returned non-JSON response: ' + text.substring(0, 100));
                    }
                    
                    const data = await response.json();
                    console.log('RFID queue loaded:', data);
                    
                    if (response.ok) {
                        this.updateQueueUI(data);
                    } else {
                        this.showError(data.error || 'Failed to load queue');
                    }
                } catch (error) {
                    console.error('Load queue error:', error);
                    this.showError('Network error: ' + error.message);
                }
            }
            
            async clearOldItems() {
                try {
                    const formData = new FormData();
                    formData.append('action', 'clear_queue');
                    
                    const response = await fetch('../api/rfid-queue-manager.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (response.ok) {
                        this.showSuccess(data.message);
                        this.loadQueue();
                    } else {
                        this.showError(data.error || 'Failed to clear queue');
                    }
                } catch (error) {
                    this.showError('Network error: ' + error.message);
                }
            }
            
            updateQueueUI(data) {
                const container = document.getElementById('rfid-queue-container');
                
                if (!data.queue_items || data.queue_items.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">📱</div>
                            <h3>No RFID Scans</h3>
                            <p>Recent RFID scans from ESP32 devices will appear here.</p>
                            ${data.registration_mode_enabled ? 
                                '<p class="text-success"><strong>Registration mode is active</strong> - any RFID tag will be accepted.</p>' :
                                '<p class="text-info">Enable registration mode to accept unregistered RFID tags.</p>'
                            }
                        </div>
                    `;
                    return;
                }
                
                let html = `
                    <div class="queue-stats">
                        <div class="stat-item">
                            <span class="stat-number">${data.stats.total_scans || 0}</span>
                            <span class="stat-label">Total Scans (1h)</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">${data.stats.unique_tags || 0}</span>
                            <span class="stat-label">Unique Tags</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">${data.stats.unregistered_tags || 0}</span>
                            <span class="stat-label">Unregistered</span>
                        </div>
                        <div class="stat-item ${data.registration_mode_enabled ? 'text-success' : 'text-danger'}">
                            <span class="stat-label">Registration Mode: ${data.registration_mode_enabled ? 'ON' : 'OFF'}</span>
                        </div>
                    </div>
                    
                    <div class="queue-list">
                `;
                
                data.queue_items.forEach(item => {
                    const isRegistered = item.user_id !== null;
                    const timeAgo = this.timeAgo(new Date(item.created_at));
                    
                    html += `
                        <div class="queue-item ${isRegistered ? 'registered' : 'unregistered'}">
                            <div class="queue-item-header">
                                <div class="rfid-tag">
                                    <span class="tag-value">${item.tag_value}</span>
                                    ${isRegistered ? 
                                        `<span class="tag-status registered">✓ Registered</span>` :
                                        `<span class="tag-status unregistered">⚠ Unregistered</span>`
                                    }
                                </div>
                                <div class="scan-time">${timeAgo}</div>
                            </div>
                            
                            <div class="queue-item-body">
                                <div class="item-details">
                                    <small>Device: ${item.device_id} | Source: ${item.source} | IP: ${item.source_ip}</small>
                                    ${isRegistered ? 
                                        `<br><small>Assigned to: <strong>${item.assigned_user}</strong></small>` :
                                        ''
                                    }
                                </div>
                                
                                <div class="item-actions">
                                    ${!isRegistered && data.registration_mode_enabled ? `
                                        <button class="btn btn-sm btn-primary" onclick="window.queueManager.showAssignDialog('${item.tag_value}')">
                                            👤 Assign to User
                                        </button>
                                    ` : ''}
                                    <button class="btn btn-sm btn-outline" onclick="window.queueManager.removeFromQueue(${item.queue_id})">
                                        🗑️ Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                html += '</div>';
                container.innerHTML = html;
            }
            
            timeAgo(date) {
                const seconds = Math.floor((new Date() - date) / 1000);
                
                let interval = seconds / 31536000;
                if (interval > 1) return Math.floor(interval) + "y ago";
                
                interval = seconds / 2592000;
                if (interval > 1) return Math.floor(interval) + "mo ago";
                
                interval = seconds / 86400;
                if (interval > 1) return Math.floor(interval) + "d ago";
                
                interval = seconds / 3600;
                if (interval > 1) return Math.floor(interval) + "h ago";
                
                interval = seconds / 60;
                if (interval > 1) return Math.floor(interval) + "m ago";
                
                return Math.floor(seconds) + "s ago";
            }
            
            showAssignDialog(tagValue) {
                const userId = prompt('Enter User ID to assign this RFID tag to:');
                if (userId && userId.trim()) {
                    this.assignToUser(tagValue, userId.trim());
                }
            }
            
            async assignToUser(tagValue, userId) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'assign_to_user');
                    formData.append('tag_value', tagValue);
                    formData.append('user_id', userId);
                    
                    const response = await fetch('../api/rfid-queue-manager.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (response.ok) {
                        this.showSuccess(data.message);
                        this.loadQueue();
                    } else {
                        this.showError(data.error || 'Failed to assign tag');
                    }
                } catch (error) {
                    this.showError('Network error: ' + error.message);
                }
            }
            
            async removeFromQueue(queueId) {
                if (!confirm('Remove this item from the queue?')) {
                    return;
                }
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'remove_from_queue');
                    formData.append('queue_id', queueId);
                    
                    const response = await fetch('../api/rfid-queue-manager.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (response.ok) {
                        this.showSuccess(data.message);
                        this.loadQueue();
                    } else {
                        this.showError(data.error || 'Failed to remove item');
                    }
                } catch (error) {
                    this.showError('Network error: ' + error.message);
                }
            }
            
            showSuccess(message) {
                this.showMessage(message, 'success');
            }
            
            showError(message) {
                this.showMessage(message, 'error');
            }
            
            showMessage(message, type) {
                // Create notification element
                const notification = document.createElement('div');
                notification.className = `notification notification-${type}`;
                notification.textContent = message;
                
                // Add to page
                document.body.appendChild(notification);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 5000);
            }
        }
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', () => {
            // Test API first
            testAPI();
            
            new RegistrationModeManager();
            window.queueManager = new RFIDQueueManager();
        });
        
        // Test API function
        async function testAPI() {
            try {
                const response = await fetch('../api/test-api.php');
                const data = await response.json();
                console.log('API Test Result:', data);
                
                if (!data.success || !data.logged_in || !data.is_admin) {
                    console.error('API Test Failed:', data);
                    showMessage('API or authentication issue detected. Check console for details.', 'error');
                }
            } catch (error) {
                console.error('API Test Error:', error);
                showMessage('API test failed: ' + error.message, 'error');
            }
        }
        
        // Global message function
        function showMessage(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 5000);
        }
    </script>
    
    <style>
        .registration-mode-container {
            margin: 1rem 0;
        }
        
        .status-active, .status-inactive {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: var(--radius-sm);
            border: 2px solid;
        }
        
        .status-active {
            background: rgba(34, 197, 94, 0.1);
            border-color: #22c55e;
            color: #166534;
        }
        
        .status-inactive {
            background: rgba(239, 68, 68, 0.1);
            border-color: #ef4444;
            color: #991b1b;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .status-active .status-indicator {
            background: #22c55e;
            box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.3);
            animation: pulse 2s infinite;
        }
        
        .status-inactive .status-indicator {
            background: #ef4444;
        }
        
        .status-info h4 {
            margin: 0 0 0.5rem 0;
            font-size: 1.1rem;
        }
        
        .status-info p {
            margin: 0;
            opacity: 0.8;
        }
        
        .session-details {
            margin-top: 0.5rem;
            opacity: 0.7;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: var(--radius-sm);
            color: white;
            font-weight: 500;
            z-index: 1000;
            min-width: 300px;
            animation: slideIn 0.3s ease;
        }
        
        .notification-success {
            background: #22c55e;
        }
        
        .notification-error {
            background: #ef4444;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }
        
        .btn-success {
            background: #22c55e;
            color: white;
            border: none;
        }
        
        .btn-success:hover {
            background: #16a34a;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
            border: none;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .loading {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .queue-controls {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .queue-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-sm);
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            display: block;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--accent-color);
        }
        
        .stat-label {
            display: block;
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }
        
        .queue-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .queue-item {
            border: 2px solid var(--border-color);
            border-radius: var(--radius-sm);
            overflow: hidden;
            transition: all 0.2s ease;
        }
        
        .queue-item.registered {
            border-color: #22c55e;
            background: rgba(34, 197, 94, 0.05);
        }
        
        .queue-item.unregistered {
            border-color: #f59e0b;
            background: rgba(245, 158, 11, 0.05);
        }
        
        .queue-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .queue-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--bg-secondary);
        }
        
        .rfid-tag {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .tag-value {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            font-size: 1.1rem;
            color: var(--text-primary);
        }
        
        .tag-status {
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .tag-status.registered {
            background: #22c55e;
            color: white;
        }
        
        .tag-status.unregistered {
            background: #f59e0b;
            color: white;
        }
        
        .scan-time {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .queue-item-body {
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .item-details {
            flex: 1;
            min-width: 0;
        }
        
        .item-details small {
            color: var(--text-secondary);
            line-height: 1.4;
        }
        
        .item-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }
        
        .btn-outline:hover {
            background: var(--bg-secondary);
            border-color: var(--accent-color);
            color: var(--accent-color);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }
        
        .empty-state h3 {
            margin: 0.5rem 0;
            color: var(--text-primary);
        }
        
        .text-success {
            color: #22c55e !important;
        }
        
        .text-info {
            color: #3b82f6 !important;
        }
        
        .text-danger {
            color: #ef4444 !important;
        }
    </style>
</body>
</html>
