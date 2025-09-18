<?php
/**
 * Enhanced Navigation Component
 * 
 * Modern, responsive navigation bar with role-based menu generation,
 * mobile-first design, and comprehensive accessibility features.
 * 
 * Features:
 * - Role-based menu items with permission checking
 * - Responsive design with mobile hamburger menu
 * - ARIA accessibility compliance
 * - Active page highlighting
 * - User dropdown with profile and logout options
 * - Real-time notification indicators
 * - Progressive enhancement with JavaScript
 * 
 * @package    RFID Check-in System
 * @subpackage UI Components
 * @version    3.0.0 - Enhanced Mobile Navigation
 * @author     Senior Developer Team
 * @since      1.0.0
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

// Format user display name
$displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if (empty($displayName)) {
    $displayName = $user['username'] ?? 'User';
}

// Get user role for menu customization
$userRole = $user['role'] ?? 'user';
$isAdmin = Auth::hasRole(['admin']);

// Determine base path for links based on current directory
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$basePath = '';

// Calculate proper base path
switch ($currentDir) {
    case 'admin':
    case 'frontend':
    case 'auth':
        $basePath = '../';
        break;
    case 'api':
        $basePath = '../';
        break;
    default:
        $basePath = './';
        break;
}

// Define navigation menu items with permissions
$menuItems = [
    'dashboard' => [
        'label' => 'Dashboard',
        'icon' => '📊',
        'url' => $basePath . 'frontend/dashboard.php',
        'roles' => ['user', 'admin']
    ],
    'events' => [
        'label' => 'Events',
        'icon' => '📅',
        'url' => $basePath . 'frontend/events.php',
        'roles' => ['user', 'admin']
    ],
    'checkins' => [
        'label' => 'My Check-ins',
        'icon' => '📝',
        'url' => $basePath . 'frontend/check-ins.php',
        'roles' => ['user', 'admin']
    ],
    'analytics' => [
        'label' => 'Analytics',
        'icon' => '📈',
        'url' => $basePath . 'frontend/analytics.php',
        'roles' => ['user', 'admin']
    ]
];

// Admin-only menu items
if ($isAdmin) {
    $menuItems['admin_events'] = [
        'label' => 'Manage Events',
        'icon' => '⚙️',
        'url' => $basePath . 'admin/events.php',
        'roles' => ['admin']
    ];
    $menuItems['admin_users'] = [
        'label' => 'Users',
        'icon' => '👥',
        'url' => $basePath . 'admin/users.php',
        'roles' => ['admin']
    ];
    $menuItems['admin_reports'] = [
        'label' => 'Reports',
        'icon' => '📊',
        'url' => $basePath . 'admin/reports.php',
        'roles' => ['admin']
    ];
    $menuItems['admin_performance'] = [
        'label' => 'Performance',
        'icon' => '⚡',
        'url' => $basePath . 'admin/performance.php',
        'roles' => ['admin']
    ];
}

// Helper function to check if menu item is active
function isActiveMenuItem($itemKey, $currentPage, $currentDir) {
    if ($itemKey === 'dashboard' && $currentPage === 'dashboard') return true;
    if ($itemKey === 'events' && $currentPage === 'events' && $currentDir === 'frontend') return true;
    if ($itemKey === 'checkins' && $currentPage === 'check-ins') return true;
    if ($itemKey === 'analytics' && $currentPage === 'analytics') return true;
    if ($itemKey === 'admin_events' && $currentPage === 'events' && $currentDir === 'admin') return true;
    if ($itemKey === 'admin_users' && $currentPage === 'users') return true;
    if ($itemKey === 'admin_reports' && $currentPage === 'reports') return true;
    if ($itemKey === 'admin_performance' && $currentPage === 'performance') return true;
    if ($itemKey === 'admin_reports' && $currentPage === 'reports') return true;
    return false;
}
?>
<nav class="navbar" role="navigation" aria-label="Main navigation">
    <div class="navbar-brand">
        <a href="<?php echo $basePath; ?>frontend/dashboard.php" class="navbar-brand" aria-label="RFID Check-in System Home">
            <span class="logo" aria-hidden="true">📟</span>
            <span class="brand-text">Check-in System</span>
        </a>
    </div>
    
    <!-- Mobile Menu Toggle -->
    <button class="navbar-toggle" id="navbarToggle" aria-label="Toggle navigation menu" aria-expanded="false">
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
    </button>
    
    <!-- Main Navigation Menu -->
    <div class="navbar-menu" id="navbarMenu">
        <div class="navbar-items">
            <?php foreach ($menuItems as $key => $item): ?>
                <?php if (in_array($userRole, $item['roles'])): ?>
                    <a href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>" 
                       class="navbar-item <?php echo isActiveMenuItem($key, $currentPage, $currentDir) ? 'active' : ''; ?>"
                       <?php if (isActiveMenuItem($key, $currentPage, $currentDir)): ?>aria-current="page"<?php endif; ?>>
                        <span aria-hidden="true"><?php echo $item['icon']; ?></span>
                        <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
            
        </div>
        
        <!-- User Controls -->
        <div class="navbar-user">
            <!-- Theme Toggle -->
            <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark/light theme" title="Toggle Theme">
                <span class="theme-icon" aria-hidden="true">🌙</span>
            </button>
            
            <!-- User Dropdown -->
            <div class="dropdown user-dropdown">
                <button class="dropdown-trigger user-btn" id="userMenuBtn" aria-label="User menu" aria-expanded="false">
                    <span class="user-avatar" aria-hidden="true">👤</span>
                    <span class="user-name"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="dropdown-arrow" aria-hidden="true">▼</span>
                </button>
                <div class="dropdown-content user-menu" id="userMenu" role="menu">
                    <a href="<?php echo $basePath; ?>frontend/profile.php" class="dropdown-item" role="menuitem">
                        <span aria-hidden="true">👤</span> Profile
                    </a>
                    <a href="<?php echo $basePath; ?>frontend/account-settings.php" class="dropdown-item" role="menuitem">
                        <span aria-hidden="true">⚙️</span> Account Settings
                    </a>
                    <a href="<?php echo $basePath; ?>frontend/help.php" class="dropdown-item" role="menuitem">
                        <span aria-hidden="true">❓</span> Help & Support
                    </a>
                    <div class="dropdown-divider" role="separator"></div>
                    <a href="<?php echo $basePath; ?>auth/logout.php">🔓 Sign Out</a>
                </div>
            </div>
        </div>
                    <a href="<?php echo $basePath; ?>auth/logout.php" class="dropdown-item logout-link" role="menuitem">
                        <span aria-hidden="true">🚪</span> Sign Out
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Navigation Enhancement Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle functionality
    const navbarToggle = document.getElementById('navbarToggle');
    const navbarMenu = document.getElementById('navbarMenu');
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userMenu = document.getElementById('userMenu');
    const themeToggle = document.getElementById('themeToggle');

    // Mobile menu toggle
    if (navbarToggle && navbarMenu) {
        navbarToggle.addEventListener('click', function() {
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', !isExpanded);
            navbarMenu.classList.toggle('active');
            
            // Update hamburger animation
            this.classList.toggle('active');
        });
    }

    // User dropdown menu
    if (userMenuBtn && userMenu) {
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', !isExpanded);
            
            // Close other dropdowns
            document.querySelectorAll('.dropdown.active').forEach(dropdown => {
                if (dropdown.contains(this)) return;
                dropdown.classList.remove('active');
                dropdown.querySelector('.dropdown-trigger').setAttribute('aria-expanded', 'false');
            });
            
            // Toggle current dropdown
            this.closest('.dropdown').classList.toggle('active');
        });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown.active').forEach(dropdown => {
                dropdown.classList.remove('active');
                dropdown.querySelector('.dropdown-trigger').setAttribute('aria-expanded', 'false');
            });
        }
    });

    // Theme toggle functionality
    if (themeToggle) {
        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);

        themeToggle.addEventListener('click', function() {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });
    }

    function updateThemeIcon(theme) {
        const themeIcon = themeToggle?.querySelector('.theme-icon');
        if (themeIcon) {
            themeIcon.textContent = theme === 'light' ? '🌙' : '☀️';
            themeToggle.setAttribute('aria-label', `Switch to ${theme === 'light' ? 'dark' : 'light'} theme`);
        }
    }

    // Close mobile menu when clicking on menu items
    document.querySelectorAll('.navbar-item').forEach(item => {
        item.addEventListener('click', function() {
            if (navbarMenu.classList.contains('active')) {
                navbarMenu.classList.remove('active');
                navbarToggle.classList.remove('active');
                navbarToggle.setAttribute('aria-expanded', 'false');
            }
        });
    });

    // Keyboard navigation support
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            // Close all dropdowns
            document.querySelectorAll('.dropdown.active').forEach(dropdown => {
                dropdown.classList.remove('active');
                dropdown.querySelector('.dropdown-trigger').setAttribute('aria-expanded', 'false');
            });
            
            // Close mobile menu
            if (navbarMenu.classList.contains('active')) {
                navbarMenu.classList.remove('active');
                navbarToggle.classList.remove('active');
                navbarToggle.setAttribute('aria-expanded', 'false');
            }
        }
    });

    // Add active state tracking for better UX
    const currentPath = window.location.pathname;
    document.querySelectorAll('.navbar-item').forEach(item => {
        if (item.href && currentPath.includes(item.getAttribute('href').split('/').pop())) {
            item.classList.add('active');
        }
    });
});
</script>

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