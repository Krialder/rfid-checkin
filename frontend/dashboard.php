<?php
require_once '../core/auth.php';
Auth::requireLogin();
$user = Auth::getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Electronic Check-in System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <h1>Welcome back, <?php echo htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>! 👋</h1>
            <p class="subtitle">Here's what's happening with your check-ins</p>
        </div>
        
        <div class="dashboard-grid">
            <!-- Quick Stats -->
            <div class="card stats-card">
                <h3>📊 Quick Stats</h3>
                <div class="stats-grid" id="quickStats">
                    <div class="stat-item">
                        <span class="stat-number" id="totalCheckins">-</span>
                        <span class="stat-label">Total Check-ins</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" id="thisMonth">-</span>
                        <span class="stat-label">This Month</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" id="avgTime">-</span>
                        <span class="stat-label">Avg. Check-in Time</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" id="uniqueEvents">-</span>
                        <span class="stat-label">Unique Events</span>
                    </div>
                </div>
            </div>
            
            <!-- Recent Check-ins -->
            <div class="card">
                <h3>🕒 Recent Check-ins</h3>
                <div class="recent-checkins" id="recentCheckins">
                    <div class="loading">Loading...</div>
                </div>
            </div>
            
            <!-- Upcoming Events -->
            <div class="card">
                <h3>📅 Upcoming Events</h3>
                <div class="upcoming-events" id="upcomingEvents">
                    <div class="loading">Loading...</div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <h3>⚡ Quick Actions</h3>
                <div class="quick-actions">
                    <button class="btn btn-secondary" onclick="showCheckInModal()">
                        📟 Manual Check-in
                    </button>
                    <a href="events.php" class="btn btn-secondary">
                        📅 Browse Events
                    </a>
                    <a href="analytics.php" class="btn btn-secondary">
                        📈 View Analytics
                    </a>
                    <a href="profile.php" class="btn btn-secondary">
                        ⚙️ Account Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Manual Check-in Modal -->
    <div id="checkInModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Manual Check-in</h3>
            <form id="manualCheckInForm">
                <div class="form-group">
                    <label for="eventSelect">Select Event</label>
                    <select id="eventSelect" name="event_id" required>
                        <option value="">Choose an event...</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Check In</button>
            </form>
        </div>
    </div>
    
    <script src="../assets/js/dashboard.js"></script>
    <?php include '../includes/theme_script.php'; ?>
</body>
</html>
