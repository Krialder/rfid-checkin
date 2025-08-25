<?php
/**
 * Navigation Component
 * Requires user to be logged in and provides user context
 */

// Ensure auth is loaded
if (!class_exists('Auth')) {
    require_once __DIR__ . '/../core/auth.php';
}

// Get current user data
$user = Auth::getCurrentUser();

// If no user data, redirect to login
if (!$user) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit();
}

// Determine base path for links
$basePath = '';
if (basename(dirname($_SERVER['PHP_SELF'])) === 'admin') {
    $basePath = '../';
} elseif (basename(dirname($_SERVER['PHP_SELF'])) === 'frontend') {
    $basePath = '../';
}
?>
<nav class="navbar">
    <div class="navbar-brand">
        <a href="<?php echo $basePath; ?>frontend/dashboard.php">
            <span class="logo">📟</span>
            <span class="brand-text">Check-in System</span>
        </a>
    </div>
    
    <div class="navbar-menu" id="navbarMenu">
        <div class="navbar-items">
            <a href="<?php echo $basePath; ?>frontend/dashboard.php" class="navbar-item <?php echo basename($_SERVER['PHP_SELF']) === 'frontend/dashboard.php' ? 'active' : ''; ?>">
                📊 Dashboard
            </a>
            
            <a href="<?php echo $basePath; ?>frontend/my-checkins.php" class="navbar-item <?php echo basename($_SERVER['PHP_SELF']) === 'frontend/my-checkins.php' ? 'active' : ''; ?>">
                🕒 My Check-ins
            </a>
            
            <a href="<?php echo $basePath; ?>frontend/events.php" class="navbar-item <?php echo basename($_SERVER['PHP_SELF']) === 'frontend/events.php' ? 'active' : ''; ?>">
                📅 Events
            </a>
            
            <a href="<?php echo $basePath; ?>frontend/analytics.php" class="navbar-item <?php echo basename($_SERVER['PHP_SELF']) === 'frontend/analytics.php' ? 'active' : ''; ?>">
                📈 Analytics
            </a>
            
            <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
                <div class="navbar-dropdown">
                    <div class="dropdown-trigger">
                        ⚙️ Admin <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="dropdown-content">
                        <a href="<?php echo $basePath; ?>admin/register_user.php">👤 Register User</a>
                        <a href="<?php echo $basePath; ?>admin/dev_tools.php">🛠️ Database Inspector</a>
                        <a href="<?php echo $basePath; ?>admin/users.php">👥 Manage Users</a>
                        <a href="<?php echo $basePath; ?>admin/events.php">📅 Manage Events</a>
                        <a href="<?php echo $basePath; ?>admin/rfid.php">📟 RFID Devices</a>
                        <a href="<?php echo $basePath; ?>admin/reports.php">📊 Reports</a>
                        <a href="<?php echo $basePath; ?>admin/settings.php">⚙️ System Settings</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="navbar-user">
            <div class="theme-toggle">
                <button id="themeToggle" class="theme-btn" title="Toggle Theme">
                    <span class="theme-icon">🌙</span>
                </button>
            </div>
            
            <div class="user-dropdown">
                <button class="user-btn" id="userMenuBtn">
                    <span class="user-avatar">👤</span>
                    <span class="user-name"><?php echo htmlspecialchars($user['name'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="dropdown-arrow">▼</span>
                </button>
                <div class="user-menu" id="userMenu">
                    <a href="<?php echo $basePath; ?>frontend/profile.php">👤 Profile</a>
                    <a href="<?php echo $basePath; ?>frontend/account-settings.php">⚙️ Settings</a>
                    <a href="<?php echo $basePath; ?>frontend/help.php">❓ Help</a>
                    <div class="menu-divider"></div>
                    <a href="<?php echo $basePath; ?>auth/logout.php">🔓 Sign Out</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="navbar-toggle" id="navbarToggle">
        <span></span>
        <span></span>
        <span></span>
    </div>
</nav>

<script>
// Navigation functionality
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const navbarToggle = document.getElementById('navbarToggle');
    const navbarMenu = document.getElementById('navbarMenu');
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userMenu = document.getElementById('userMenu');

    if (navbarToggle && navbarMenu) {
        navbarToggle.addEventListener('click', function() {
            navbarMenu.classList.toggle('active');
        });
    }

    // User menu toggle
    if (userMenuBtn && userMenu) {
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenu.classList.toggle('show');
        });
    }

    // Close user menu when clicking outside
    document.addEventListener('click', function(event) {
        if (userMenu && !event.target.closest('.user-dropdown')) {
            userMenu.classList.remove('show');
        }
    });

    // Admin dropdown functionality
    const dropdownTrigger = document.querySelector('.dropdown-trigger');
    const adminDropdown = document.querySelector('.navbar-dropdown');

    if (dropdownTrigger && adminDropdown) {
        // Handle clicks for mobile and desktop
        dropdownTrigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            adminDropdown.classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.navbar-dropdown')) {
                adminDropdown.classList.remove('active');
            }
        });

        // Handle window resize - remove active class on desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                adminDropdown.classList.remove('active');
            }
        });
    }

    // Close mobile menu when clicking on nav items
    const navLinks = document.querySelectorAll('.navbar-item, .dropdown-content a');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                navbarMenu.classList.remove('active');
            }
        });
    });
});
</script>
