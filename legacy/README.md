# Legacy Directory

This directory contains the original PHP application structure that predates the modern MVC architecture in the `src/` directory. These files represent the evolution of the system and provide backward compatibility during the migration to enterprise patterns.

## 📁 Directory Structure

```
legacy/
├── admin/                         # Administrative interfaces
│   ├── activate-user.php          # User activation management
│   ├── analytics.php              # Analytics dashboard
│   ├── events.php                 # Event management interface
│   ├── performance.php            # Performance monitoring
│   ├── register-user.php          # User registration tools
│   ├── reports.php                # Report generation
│   ├── rfid-test.php              # RFID testing utilities
│   ├── rfid.php                   # RFID device management
│   ├── settings.php               # System settings
│   ├── user-groups.php            # User group management
│   └── users.php                  # User management
├── api/                           # Legacy API endpoints
│   ├── analytics.php              # Analytics data API
│   ├── dashboard.php              # Dashboard data API
│   ├── event-details.php          # Event information API
│   ├── manual-checkin.php         # Manual check-in API
│   ├── registration-mode.php      # Registration mode API
│   ├── rfid-checkin.php           # RFID check-in processing
│   ├── rfid-poll-noauth.php       # Unauthenticated RFID polling
│   ├── rfid-poll.php              # Authenticated RFID polling
│   ├── rfid-queue.php             # RFID queue management
│   └── rfid-test.php              # RFID testing API
├── auth/                          # Authentication system
│   ├── forgot-password.php        # Password recovery
│   ├── login-process.php          # Login processing
│   ├── login.php                  # Login interface
│   ├── logout.php                 # Logout handler
│   └── reset-password.php         # Password reset
└── frontend/                      # Legacy frontend pages
    ├── account-settings.php       # User account settings
    ├── analytics.php              # Analytics views
    ├── check-ins.php              # Check-in history
    ├── dashboard.php              # Main dashboard
    ├── events.php                 # Event listings
    ├── help.php                   # Help documentation
    └── profile.php                # User profile management
```

## 🔄 Migration Status

### Legacy vs Modern Architecture

**Legacy Architecture (legacy/):**
- **Procedural PHP**: Direct database queries and procedural logic
- **Mixed Concerns**: HTML, PHP, and business logic in single files
- **File-based Routing**: Direct file access for pages and APIs
- **Inline SQL**: Database queries embedded throughout code
- **Session-based Auth**: Simple session management

**Modern Architecture (src/):**
- **MVC Pattern**: Separated controllers, models, and views
- **Repository Pattern**: Abstracted data access layer
- **Service Layer**: Business logic separation
- **Dependency Injection**: Container-based component management
- **Enterprise Security**: Comprehensive security middleware

### Migration Progress

| Component | Legacy Status | Modern Equivalent | Migration Status |
|-----------|---------------|-------------------|------------------|
| **Authentication** | ✅ Functional | `src/Middleware/Auth.php` | 🔄 In Progress |
| **User Management** | ✅ Functional | `src/Controllers/UserController.php` | 🔄 In Progress |
| **RFID Processing** | ✅ Functional | `src/Services/RfidService.php` | 🔄 In Progress |
| **Event Management** | ✅ Functional | `src/Controllers/EventController.php` | 🔄 In Progress |
| **Analytics** | ✅ Functional | `src/Services/AnalyticsService.php` | 🔄 In Progress |
| **API Endpoints** | ✅ Functional | `src/Routing/ApiRoutes.php` | 🔄 In Progress |
| **Admin Interface** | ✅ Functional | `src/Controllers/AdminController.php` | 📋 Planned |
| **Frontend Pages** | ✅ Functional | `src/Views/` | 📋 Planned |

## 🏗️ Legacy Code Architecture

### Administrative Interface

**admin/** - Administrative tools and interfaces:

```php
<?php
/**
 * Example: legacy/admin/users.php
 * Administrative interface for user management
 */

// Enterprise components initialization
require_once '../core/config.php';
require_once '../core/auth.php';

$container = EnterpriseContainer::getInstance();
$userRepository = new UserRepository();
$dataService = new DataService();

// Access control enforcement
if (!Auth::isLoggedIn() || !Auth::hasRole(['admin'])) {
    http_response_code(403);
    header('Location: ../auth/login.php');
    exit;
}

// Performance monitoring
$performanceManager->startTimer('admin_users_page');

// User management operations
if ($_POST['action'] ?? false) {
    switch ($_POST['action']) {
        case 'create_user':
            $result = $userRepository->create($_POST);
            break;
        case 'update_user':
            $result = $userRepository->update($_POST['user_id'], $_POST);
            break;
        case 'delete_user':
            $result = $userRepository->delete($_POST['user_id']);
            break;
    }
}
?>
```

**Key Features:**
- Enterprise component integration
- Repository pattern usage
- Performance monitoring
- Comprehensive access control
- Audit logging

### API Endpoints

**api/** - REST-like API endpoints:

```php
<?php
/**
 * Example: legacy/api/rfid-checkin.php
 * Enterprise RFID check-in processing
 */

// Enterprise security and performance
$securityManager = $container->get('securityManager');
$performanceManager = $container->get('performanceManager');

// Rate limiting and validation
$isRateLimited = $securityManager->checkRateLimit(
    $_SERVER['REMOTE_ADDR'], 'rfid_checkin', 100, 3600
);

if ($isRateLimited) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded']);
    exit;
}

// RFID processing logic
$rfidTag = $securityManager->sanitizeInput($_POST['rfid'] ?? '');
$deviceId = $securityManager->sanitizeInput($_POST['device_id'] ?? '');

// User lookup and event matching
$user = $userRepository->findByRfidTag($rfidTag);
$activeEvent = $eventRepository->getActiveEvent();

if ($user && $activeEvent) {
    $checkin = $checkinRepository->processCheckin([
        'user_id' => $user['user_id'],
        'event_id' => $activeEvent['event_id'],
        'device_id' => $deviceId,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    echo json_encode([
        'success' => true,
        'action' => $checkin['action'],
        'user' => $user,
        'timestamp' => $checkin['timestamp']
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid RFID or no active event']);
}
?>
```

**API Features:**
- Enterprise security validation
- Rate limiting protection
- Repository pattern integration
- Real-time processing
- Comprehensive error handling

### Authentication System

**auth/** - User authentication and session management:

```php
<?php
/**
 * Example: legacy/auth/login-process.php
 * Authentication processing with enterprise security
 */

// Security validation
$securityManager = SecurityManager::getInstance();
$username = $securityManager->sanitizeInput($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Rate limiting check
if ($securityManager->checkRateLimit($_SERVER['REMOTE_ADDR'], 'login', 5, 300)) {
    http_response_code(429);
    header('Location: login.php?error=rate_limit');
    exit;
}

// Authentication attempt
$user = $userRepository->authenticateUser($username, $password);

if ($user) {
    // Successful authentication
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    
    // Audit logging
    $auditLog = [
        'user_id' => $user['user_id'],
        'action' => 'login',
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ];
    $checkinRepository->logActivity($auditLog);
    
    header('Location: ../frontend/dashboard.php');
} else {
    // Failed authentication
    $securityManager->recordFailedAttempt($_SERVER['REMOTE_ADDR'], 'login');
    header('Location: login.php?error=invalid_credentials');
}
?>
```

**Authentication Features:**
- Rate limiting protection
- Session security
- Comprehensive audit logging
- Brute force protection
- Enterprise security integration

### Frontend Pages

**frontend/** - User-facing interface pages:

```php
<?php
/**
 * Example: legacy/frontend/dashboard.php
 * Main user dashboard with real-time data
 */

// Authentication and authorization
require_once '../core/auth.php';
if (!Auth::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

// Data retrieval
$dataService = DataService::getInstance();
$dashboardData = $dataService->getDashboardData($_SESSION['user_id']);

// Asset optimization
$assetOptimizer = AssetOptimizer::getInstance();
$optimizedCSS = $assetOptimizer->optimizeCSS(['main.css', 'dashboard.css']);
$optimizedJS = $assetOptimizer->optimizeJS(['dashboard.js']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - RFID Check-in System</title>
    <style><?= $optimizedCSS ?></style>
</head>
<body>
    <div class="dashboard-container">
        <h1>Welcome, <?= htmlspecialchars($_SESSION['username']) ?></h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Today's Check-ins</h3>
                <span class="stat-number"><?= $dashboardData['today_checkins'] ?></span>
            </div>
            <div class="stat-card">
                <h3>Active Events</h3>
                <span class="stat-number"><?= $dashboardData['active_events'] ?></span>
            </div>
        </div>
        
        <div class="recent-activity">
            <h2>Recent Activity</h2>
            <ul class="activity-list">
                <?php foreach ($dashboardData['recent_activity'] as $activity): ?>
                    <li><?= htmlspecialchars($activity['description']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    
    <script><?= $optimizedJS ?></script>
</body>
</html>
```

**Frontend Features:**
- Authentication integration
- Asset optimization
- Real-time data display
- Responsive design
- Security-conscious output

## 🔧 Enterprise Integration

### Modern Component Usage

Even though these are "legacy" files, they've been enhanced with enterprise components:

**Component Integration:**
```php
// Enterprise container usage
$container = EnterpriseContainer::getInstance();
$errorHandler = $container->get('errorHandler');
$securityManager = $container->get('securityManager');
$performanceManager = $container->get('performanceManager');

// Repository pattern usage
$userRepository = new UserRepository();
$eventRepository = new EventRepository();
$checkinRepository = new CheckinRepository();

// Service layer integration
$dataService = new DataService();
```

**Security Enhancements:**
```php
// CSRF protection
$csrfToken = $securityManager->generateCSRFToken();
$securityManager->validateCSRFToken($_POST['csrf_token']);

// Input sanitization
$cleanInput = $securityManager->sanitizeInput($_POST['data']);

// Rate limiting
$securityManager->checkRateLimit($identifier, $action, $limit, $window);
```

**Performance Monitoring:**
```php
// Performance tracking
$performanceManager->startTimer('page_load');
$performanceManager->endTimer('page_load');

// Memory monitoring
$performanceManager->trackMemoryUsage('dashboard_load');

// Cache integration
$cachedData = $performanceManager->get('dashboard_data_' . $userId);
```

## 📋 Maintenance Guidelines

### File Modification Rules

**DO:**
- ✅ Add enterprise component integration
- ✅ Implement security enhancements
- ✅ Add performance monitoring
- ✅ Use repository patterns for data access
- ✅ Add comprehensive error handling

**DON'T:**
- ❌ Remove existing functionality
- ❌ Change file structure dramatically
- ❌ Break backward compatibility
- ❌ Remove security features
- ❌ Ignore performance implications

### Security Considerations

**Legacy Security Enhancements:**
1. **Input Validation**: All user input sanitized through SecurityManager
2. **CSRF Protection**: All forms protected with CSRF tokens
3. **Rate Limiting**: API endpoints protected against abuse
4. **Audit Logging**: All actions logged for security monitoring
5. **Session Security**: Secure session management practices

### Performance Optimization

**Legacy Performance Improvements:**
1. **Asset Optimization**: CSS/JS minimization and combination
2. **Database Optimization**: Query optimization and connection pooling
3. **Caching**: Strategic caching of frequently accessed data
4. **Memory Management**: Memory usage monitoring and optimization
5. **Load Balancing**: Support for multiple server deployment

## 🚀 Migration Roadmap

### Phase 1: Core Services (In Progress)
- ✅ Repository pattern implementation
- ✅ Service layer creation
- 🔄 Authentication service migration
- 🔄 RFID processing service migration

### Phase 2: API Modernization (Planned)
- 📋 RESTful API restructuring
- 📋 OpenAPI documentation
- 📋 GraphQL endpoint consideration
- 📋 API versioning strategy

### Phase 3: Frontend Modernization (Future)
- 📋 Component-based frontend architecture
- 📋 Single Page Application (SPA) consideration
- 📋 Progressive Web App (PWA) features
- 📋 Mobile responsiveness improvements

### Phase 4: Complete Migration (Future)
- 📋 Legacy code deprecation
- 📋 Complete modern architecture adoption
- 📋 Performance optimization
- 📋 Documentation updates

## 🔍 Legacy File Reference

### Admin Files
| File | Purpose | Modern Equivalent | Status |
|------|---------|-------------------|--------|
| `activate-user.php` | User activation management | `UserController::activate()` | Active |
| `analytics.php` | Analytics dashboard | `AnalyticsController::dashboard()` | Active |
| `events.php` | Event management | `EventController::index()` | Active |
| `performance.php` | Performance monitoring | `PerformanceController::dashboard()` | Active |
| `register-user.php` | User registration | `UserController::register()` | Active |
| `reports.php` | Report generation | `ReportController::index()` | Active |
| `rfid-test.php` | RFID testing | `RfidController::test()` | Active |
| `rfid.php` | RFID management | `RfidController::index()` | Active |
| `settings.php` | System settings | `SettingsController::index()` | Active |
| `user-groups.php` | Group management | `GroupController::index()` | Active |
| `users.php` | User management | `UserController::index()` | Active |

### API Files
| File | Purpose | Modern Equivalent | Status |
|------|---------|-------------------|--------|
| `analytics.php` | Analytics data | `AnalyticsApi::getData()` | Active |
| `dashboard.php` | Dashboard data | `DashboardApi::getData()` | Active |
| `event-details.php` | Event information | `EventApi::getDetails()` | Active |
| `manual-checkin.php` | Manual check-ins | `CheckinApi::manual()` | Active |
| `registration-mode.php` | Registration mode | `RegistrationApi::mode()` | Active |
| `rfid-checkin.php` | RFID processing | `RfidApi::checkin()` | Active |
| `rfid-poll.php` | RFID polling | `RfidApi::poll()` | Active |
| `rfid-queue.php` | RFID queue | `RfidApi::queue()` | Active |
| `rfid-test.php` | RFID testing | `RfidApi::test()` | Active |

### Auth Files
| File | Purpose | Modern Equivalent | Status |
|------|---------|-------------------|--------|
| `forgot-password.php` | Password recovery | `AuthController::forgotPassword()` | Active |
| `login-process.php` | Login processing | `AuthController::login()` | Active |
| `login.php` | Login interface | `AuthController::loginForm()` | Active |
| `logout.php` | Logout handler | `AuthController::logout()` | Active |
| `reset-password.php` | Password reset | `AuthController::resetPassword()` | Active |

### Frontend Files
| File | Purpose | Modern Equivalent | Status |
|------|---------|-------------------|--------|
| `account-settings.php` | Account settings | `UserController::settings()` | Active |
| `analytics.php` | Analytics view | `AnalyticsController::view()` | Active |
| `check-ins.php` | Check-in history | `CheckinController::history()` | Active |
| `dashboard.php` | Main dashboard | `DashboardController::index()` | Active |
| `events.php` | Event listings | `EventController::list()` | Active |
| `help.php` | Help documentation | `HelpController::index()` | Active |
| `profile.php` | User profile | `UserController::profile()` | Active |

---

**Legacy Status**: Active with Enterprise Enhancements  
**Migration Target**: Modern MVC Architecture in `src/`  
**Compatibility**: Maintained during transition  
**Last Updated**: January 2025