# Includes Directory

This directory contains shared PHP components and reusable elements that are included across multiple pages. These components provide consistent functionality and UI elements throughout the application.

## 📁 Directory Structure

```
includes/
├── navigation.php                 # Main navigation component
└── theme_script.php               # Theme management script
```

## 🧩 Component Overview

### navigation.php - Main Navigation Component

**Purpose**: Provides a responsive, role-based navigation system for the entire application.

**Key Features:**
- **Role-based Menu Items**: Dynamic menu generation based on user permissions
- **Mobile-first Design**: Responsive navigation with hamburger menu
- **Accessibility Compliance**: ARIA labels and keyboard navigation
- **Active Page Highlighting**: Automatic detection of current page
- **User Dropdown**: Profile management and logout functionality
- **Theme Toggle**: Dark/light mode switching
- **Progressive Enhancement**: JavaScript-enhanced with graceful degradation

**Architecture:**
```php
<?php
// Authentication integration
require_once __DIR__ . '/../core/auth.php';

// User data and permissions
$user = Auth::getCurrentUser();
$userRole = $user['role'] ?? 'user';
$isAdmin = Auth::hasRole(['admin']);

// Dynamic menu generation
$menuItems = [
    'dashboard' => [
        'label' => 'Dashboard',
        'icon' => '📊',
        'url' => $basePath . 'frontend/dashboard.php',
        'roles' => ['user', 'admin']
    ],
    // ... additional menu items
];
?>
```

**Menu Structure:**

| Menu Item | Icon | Access Level | Description |
|-----------|------|--------------|-------------|
| **Dashboard** | 📊 | User, Admin | Main dashboard view |
| **Events** | 📅 | User, Admin | Event listings and details |
| **My Check-ins** | 📝 | User, Admin | Personal check-in history |
| **Analytics** | 📈 | User, Admin | Data visualization and reports |
| **Manage Events** | ⚙️ | Admin Only | Event administration |
| **Users** | 👥 | Admin Only | User management |
| **Reports** | 📊 | Admin Only | Administrative reports |
| **Performance** | ⚡ | Admin Only | System performance monitoring |

**Responsive Design:**
```css
/* Mobile-first approach */
.navbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 1rem;
    background: var(--navbar-bg);
    border-bottom: 1px solid var(--border-color);
}

/* Mobile menu toggle */
.navbar-toggle {
    display: block;
    background: none;
    border: none;
    cursor: pointer;
}

@media (min-width: 768px) {
    .navbar-toggle {
        display: none;
    }
    
    .navbar-menu {
        display: flex !important;
    }
}
```

**JavaScript Enhancement:**
```javascript
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const navbarToggle = document.getElementById('navbarToggle');
    const navbarMenu = document.getElementById('navbarMenu');
    
    navbarToggle.addEventListener('click', function() {
        const isExpanded = this.getAttribute('aria-expanded') === 'true';
        this.setAttribute('aria-expanded', !isExpanded);
        navbarMenu.classList.toggle('active');
    });
    
    // Theme toggle functionality
    const themeToggle = document.getElementById('themeToggle');
    const savedTheme = localStorage.getItem('theme') || 'light';
    
    document.documentElement.setAttribute('data-theme', savedTheme);
    
    themeToggle.addEventListener('click', function() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
    });
});
```

### theme_script.php - Theme Management

**Purpose**: Provides theme switching functionality and dark/light mode management.

**Features:**
- **Theme Persistence**: Saves user theme preference
- **System Theme Detection**: Respects user's OS theme preference
- **Smooth Transitions**: CSS transition effects for theme changes
- **Performance Optimized**: Minimal JavaScript footprint

**Implementation:**
```php
<?php
/**
 * Theme Management Script
 * Provides client-side theme switching with server-side preference storage
 */
?>
<script>
(function() {
    'use strict';
    
    // Theme management
    const ThemeManager = {
        // Get saved theme or default to system preference
        getTheme: function() {
            const saved = localStorage.getItem('theme');
            if (saved) return saved;
            
            // Check system preference
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                return 'dark';
            }
            return 'light';
        },
        
        // Apply theme to document
        applyTheme: function(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            
            // Update theme toggle icons
            this.updateThemeControls(theme);
        },
        
        // Update theme control elements
        updateThemeControls: function(theme) {
            const themeToggles = document.querySelectorAll('.theme-toggle');
            themeToggles.forEach(toggle => {
                const icon = toggle.querySelector('.theme-icon');
                if (icon) {
                    icon.textContent = theme === 'light' ? '🌙' : '☀️';
                }
                toggle.setAttribute('aria-label', `Switch to ${theme === 'light' ? 'dark' : 'light'} theme`);
            });
        },
        
        // Initialize theme system
        init: function() {
            const theme = this.getTheme();
            this.applyTheme(theme);
            
            // Listen for system theme changes
            if (window.matchMedia) {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                    if (!localStorage.getItem('theme')) {
                        this.applyTheme(e.matches ? 'dark' : 'light');
                    }
                });
            }
        }
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => ThemeManager.init());
    } else {
        ThemeManager.init();
    }
    
    // Make theme manager available globally
    window.ThemeManager = ThemeManager;
})();
</script>
```

## 🎨 CSS Integration

### Custom Properties Support

**Theme Variables:**
```css
:root {
    /* Light theme (default) */
    --navbar-bg: #ffffff;
    --navbar-text: #333333;
    --navbar-hover: #f5f5f5;
    --primary-color: #007bff;
    --primary-contrast: #ffffff;
    --border-color: #e9ecef;
    --text-primary: #212529;
    --text-secondary: #6c757d;
    --bg-primary: #ffffff;
    --bg-secondary: #f8f9fa;
}

[data-theme="dark"] {
    /* Dark theme overrides */
    --navbar-bg: #1a1a1a;
    --navbar-text: #ffffff;
    --navbar-hover: #2d2d2d;
    --primary-color: #0d6efd;
    --border-color: #495057;
    --text-primary: #ffffff;
    --text-secondary: #adb5bd;
    --bg-primary: #212529;
    --bg-secondary: #343a40;
}
```

**Responsive Breakpoints:**
```css
/* Mobile-first responsive design */
@media (max-width: 767px) {
    .navbar-menu {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--navbar-bg);
        border-top: 1px solid var(--border-color);
        transform: translateY(-100%);
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }
    
    .navbar-menu.active {
        transform: translateY(0);
        opacity: 1;
        visibility: visible;
    }
}

@media (min-width: 768px) {
    .navbar-menu {
        position: static;
        transform: none;
        opacity: 1;
        visibility: visible;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
}
```

## 🔧 Usage Guidelines

### Including Navigation

**Standard Implementation:**
```php
<?php
// At the top of any page requiring navigation
require_once __DIR__ . '/includes/navigation.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Title</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <!-- Navigation is automatically rendered -->
    
    <main class="main-content">
        <!-- Page content -->
    </main>
    
    <?php require_once __DIR__ . '/includes/theme_script.php'; ?>
</body>
</html>
```

### Path Resolution

**Automatic Path Detection:**
```php
// The navigation component automatically detects the current directory
// and adjusts paths accordingly

// From root directory (/)
$basePath = './';

// From subdirectory (/admin/, /frontend/, /auth/)
$basePath = '../';

// Active page detection
function isActiveMenuItem($itemKey, $currentPage, $currentDir) {
    // Logic to determine if menu item should be highlighted
    return $itemKey === $currentPage;
}
```

### Custom Menu Items

**Adding New Menu Items:**
```php
// In navigation.php, add to $menuItems array
$menuItems['new_feature'] = [
    'label' => 'New Feature',
    'icon' => '⭐',
    'url' => $basePath . 'frontend/new-feature.php',
    'roles' => ['user', 'admin'] // Access control
];
```

## ♿ Accessibility Features

### ARIA Support

**Navigation Landmarks:**
```html
<nav class="navbar" role="navigation" aria-label="Main navigation">
    <div class="navbar-menu" id="navbarMenu">
        <div class="navbar-items">
            <a href="..." class="navbar-item" aria-current="page">Dashboard</a>
        </div>
    </div>
</nav>
```

**Dropdown Menus:**
```html
<button class="dropdown-trigger" 
        id="userMenuBtn" 
        aria-label="User menu" 
        aria-expanded="false">
    User Menu
</button>
<div class="dropdown-content" 
     id="userMenu" 
     role="menu">
    <a href="..." class="dropdown-item" role="menuitem">Profile</a>
</div>
```

### Keyboard Navigation

**Keyboard Support:**
- **Tab**: Navigate through menu items
- **Enter/Space**: Activate menu items and toggles
- **Escape**: Close dropdowns and mobile menu
- **Arrow Keys**: Navigate within dropdowns

**Implementation:**
```javascript
// Keyboard event handling
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        // Close all dropdowns and mobile menu
        closeAllMenus();
    }
});

// Focus management
function trapFocus(container) {
    const focusableElements = container.querySelectorAll(
        'a[href], button, textarea, input[type="text"], input[type="radio"], input[type="checkbox"], select'
    );
    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];
    
    container.addEventListener('keydown', function(e) {
        if (e.key === 'Tab') {
            if (e.shiftKey && document.activeElement === firstElement) {
                e.preventDefault();
                lastElement.focus();
            } else if (!e.shiftKey && document.activeElement === lastElement) {
                e.preventDefault();
                firstElement.focus();
            }
        }
    });
}
```

## 📱 Mobile Optimization

### Touch-Friendly Design

**Touch Targets:**
```css
.navbar-item,
.dropdown-trigger,
.theme-toggle {
    min-height: 44px; /* iOS recommended minimum */
    min-width: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 1rem;
}
```

**Gesture Support:**
```javascript
// Swipe gesture for mobile menu
let touchStartX = 0;
let touchEndX = 0;

document.addEventListener('touchstart', function(e) {
    touchStartX = e.changedTouches[0].screenX;
});

document.addEventListener('touchend', function(e) {
    touchEndX = e.changedTouches[0].screenX;
    handleSwipeGesture();
});

function handleSwipeGesture() {
    const swipeThreshold = 50;
    const diff = touchStartX - touchEndX;
    
    if (Math.abs(diff) > swipeThreshold) {
        if (diff > 0) {
            // Swipe left - close menu
            closeMobileMenu();
        } else {
            // Swipe right - open menu (if near edge)
            if (touchStartX < 20) {
                openMobileMenu();
            }
        }
    }
}
```

## 🔒 Security Considerations

### Authentication Integration

**Secure User Data Handling:**
```php
// Safe user data display
$displayName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
$userRole = htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8');

// Role-based access control
if (in_array($userRole, $item['roles'])) {
    // Show menu item
}
```

### XSS Prevention

**Output Encoding:**
```php
echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8');
```

**CSP Headers:**
```html
<meta http-equiv="Content-Security-Policy" 
      content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';">
```

## 🚀 Performance Optimization

### Lazy Loading

**Conditional Component Loading:**
```php
// Only load navigation if user is authenticated
if (Auth::isLoggedIn()) {
    require_once __DIR__ . '/includes/navigation.php';
} else {
    // Show minimal navigation for unauthenticated users
}
```

### Caching Strategy

**Navigation Cache:**
```php
// Cache menu structure for performance
$cacheKey = 'navigation_menu_' . $userRole;
$menuItems = PerformanceManager::getInstance()->get($cacheKey);

if (!$menuItems) {
    $menuItems = generateMenuItems($userRole);
    PerformanceManager::getInstance()->set($cacheKey, $menuItems, 3600);
}
```

---

**Components**: Production Ready  
**Last Updated**: January 2025  
**Accessibility**: WCAG 2.1 AA Compliant  
**Mobile**: Optimized for all devices