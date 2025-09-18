# Core Directory

This directory contains the core application logic, services, and infrastructure components that power the RFID Check-in System.

## 📁 Directory Structure

```
core/
├── config.php                 # Application configuration
├── config.template.php        # Configuration template
├── database.php               # Database connection and utilities
├── auth.php                   # Authentication and authorization
├── utils.php                  # Utility functions and helpers
├── holidays.php               # Holiday management system
├── event-manager.php          # Event management logic
├── user-group-manager.php     # User group management
├── PerformanceManager.php     # Performance optimization
├── SecurityManager.php        # Security features and validation
├── SecurityMiddleware.php     # Security middleware layer
├── DatabaseOptimizer.php      # Database optimization tools
├── AssetOptimizer.php         # Frontend asset optimization
├── AssetHelper.php            # Asset loading and management
├── AssetConsolidator.php      # Asset bundling system
├── ErrorHandler.php           # Error handling and logging
├── SharedUtilities.php        # Shared utility functions
├── PerformanceDashboard.php   # Performance monitoring interface
├── repositories/              # Data access layer
│   ├── BaseRepository.php     # Abstract repository base class
│   ├── UserRepository.php     # User data management
│   ├── EventRepository.php    # Event data management
│   └── CheckinRepository.php  # Check-in data management
├── services/                  # Business logic services
│   └── DataService.php        # Centralized data operations
└── error-pages/               # Error page templates
    ├── 404.php                # Not found error page
    ├── 500.php                # Server error page
    └── fatal.php              # Fatal error page
```

## 🏗️ Core Architecture

### Configuration Management

**config.php** - Central configuration system with environment-specific settings:

```php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'rfid_checkin_system');
define('DB_USER', 'username');
define('DB_PASS', 'password');

// Application Settings
define('APP_NAME', 'RFID Check-in System');
define('BASE_URL', 'http://your-domain.com');
define('DEBUG_MODE', false);

// Security Configuration
define('SESSION_LIFETIME', 3600);
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
```

**Features:**
- Environment-specific configuration
- Security settings and constants
- Database connection parameters
- Application-wide settings
- Debug and development modes

### Database Layer

**database.php** - Centralized database connection and query management:

```php
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO($dsn, $user, $pass, $options);
    }
    return $pdo;
}
```

**Features:**
- Singleton database connections
- Connection pooling and optimization
- Prepared statement helpers
- Transaction management
- Error handling and logging

### Authentication System

**auth.php** - Comprehensive authentication and authorization:

```php
class Auth {
    public static function login($username, $password): bool
    public static function logout(): void
    public static function isLoggedIn(): bool
    public static function hasPermission($permission): bool
    public static function getCurrentUser(): ?array
    public static function requireLogin(): void
    public static function requirePermission($permission): void
}
```

**Features:**
- Session management and security
- Role-based access control (RBAC)
- Password hashing and verification
- Login attempt limiting
- Audit logging and monitoring
- CSRF protection integration

## 🚀 Performance Management

### PerformanceManager

Comprehensive performance optimization system:

```php
class PerformanceManager {
    // Caching System
    public function get($key): mixed
    public function set($key, $value, $ttl = 3600): bool
    public function delete($key): bool
    public function flush(): bool
    
    // Query Optimization
    public function optimizeQuery($sql, $params = []): array
    public function analyzeSlowQueries(): array
    public function getQueryStatistics(): array
    
    // Performance Monitoring
    public function startTimer($name): void
    public function endTimer($name): float
    public function recordMetric($name, $value): void
    public function getAnalytics(): array
}
```

**Features:**
- **Multi-level Caching**: Memory, file, and database caching
- **Query Optimization**: Automatic query analysis and suggestions
- **Performance Monitoring**: Real-time metrics and analytics
- **Bottleneck Detection**: Identify and report performance issues
- **Resource Monitoring**: Memory, CPU, and database usage tracking

### Caching Strategy

The system implements a sophisticated caching hierarchy:

1. **L1 Cache (Memory)**: In-memory array cache for frequently accessed data
2. **L2 Cache (File)**: Persistent file-based cache for larger datasets
3. **L3 Cache (Database)**: Database-level caching for complex queries

```php
// Cache usage examples
$performanceManager = PerformanceManager::getInstance();

// Store data with TTL
$performanceManager->set('user_stats_123', $userStats, 300); // 5 minutes

// Retrieve cached data
$cachedStats = $performanceManager->get('user_stats_123');

// Cache with callback for automatic regeneration
$stats = $performanceManager->remember('dashboard_stats', 600, function() {
    return $this->generateDashboardStats();
});
```

## 🛡️ Security Management

### SecurityManager

Comprehensive security features and validation:

```php
class SecurityManager {
    // Input Validation and Sanitization
    public function sanitizeInput($input, $type = 'string'): string
    public function validateInput($input, $rules): array
    public function escapeOutput($output): string
    
    // CSRF Protection
    public function generateCSRFToken(): string
    public function validateCSRFToken($token): bool
    
    // Security Monitoring
    public function logSecurityEvent($event, $details = []): void
    public function detectSuspiciousActivity($ip): bool
    public function rateLimitCheck($identifier, $limit, $window): bool
    
    // Password Security
    public function hashPassword($password): string
    public function verifyPassword($password, $hash): bool
    public function generateSecureToken($length = 32): string
}
```

**Security Features:**
- **Input Validation**: Comprehensive input sanitization and validation
- **CSRF Protection**: Token-based cross-site request forgery protection
- **SQL Injection Prevention**: Prepared statements and input escaping
- **XSS Protection**: Output escaping and content security policies
- **Rate Limiting**: Request throttling and abuse prevention
- **Security Monitoring**: Activity logging and threat detection

### SecurityMiddleware

Request-level security enforcement:

```php
class SecurityMiddleware {
    public function validateRequest($request): bool
    public function enforceRateLimit($ip): bool
    public function checkCSRFToken($request): bool
    public function sanitizeRequestData($data): array
    public function logSecurityViolation($violation): void
}
```

## 📊 Database Optimization

### DatabaseOptimizer

Advanced database performance tuning:

```php
class DatabaseOptimizer {
    // Query Analysis
    public function analyzeQuery($sql, $params = []): array
    public function explainQuery($sql, $params = []): array
    public function getSlowQueries($threshold = 0.1): array
    
    // Index Optimization
    public function analyzeIndexUsage(): array
    public function suggestIndexes(): array
    public function getUnusedIndexes(): array
    
    // Table Optimization
    public function optimizeTables(): array
    public function analyzeTableStructure(): array
    public function getTableStatistics(): array
}
```

**Optimization Features:**
- **Query Analysis**: Detailed query performance analysis
- **Index Recommendations**: Automatic index optimization suggestions
- **Slow Query Detection**: Identify and analyze performance bottlenecks
- **Table Optimization**: Database table maintenance and optimization
- **Statistics Gathering**: Comprehensive database performance metrics

## 🎨 Asset Management

### AssetOptimizer

Frontend asset optimization system:

```php
class AssetOptimizer {
    // CSS Optimization
    public function optimizeCSS($files): string
    public function minifyCSS($css): string
    public function autoprefixCSS($css): string
    
    // JavaScript Optimization
    public function optimizeJS($files): string
    public function minifyJS($js): string
    public function bundleJS($files): string
    
    // Image Optimization
    public function optimizeImages($path): bool
    public function generateWebP($imagePath): string
    public function createThumbnails($imagePath, $sizes): array
}
```

**Asset Features:**
- **CSS/JS Minification**: Remove whitespace and optimize code
- **File Bundling**: Combine multiple files to reduce HTTP requests
- **Image Optimization**: Compress and convert images for web delivery
- **Caching Integration**: Browser and CDN caching optimization
- **Version Management**: Asset versioning for cache busting

### AssetHelper

Utility functions for asset loading:

```php
function loadCSS($files, $media = 'all'): string
function loadJS($files, $defer = true): string
function getAssetVersion($file): string
function getCriticalCSS($page): string
```

## 📦 Repository Pattern

### BaseRepository

Abstract base class for all data repositories:

```php
abstract class BaseRepository {
    protected $db;
    protected $table;
    protected $performanceManager;
    
    // CRUD Operations
    public function findById($id): ?array
    public function findWhere($conditions): array
    public function create($data): int
    public function update($id, $data): bool
    public function delete($id): bool
    
    // Advanced Queries
    public function findWithPagination($page, $limit, $conditions = []): array
    public function findWithRelations($id, $relations = []): ?array
    public function search($query, $fields = []): array
    
    // Performance Integration
    public function executeOptimizedQuery($sql, $params = []): array
    public function getCachedResult($key, $callback, $ttl = 3600): mixed
}
```

### Specialized Repositories

**UserRepository** - User data management:
```php
class UserRepository extends BaseRepository {
    public function findByUsername($username): ?array
    public function findByEmail($email): ?array
    public function findByRFID($rfidTag): ?array
    public function updateLastLogin($userId): bool
    public function incrementLoginAttempts($userId): bool
    public function resetLoginAttempts($userId): bool
    public function getUserPermissions($userId): array
}
```

**EventRepository** - Event data management:
```php
class EventRepository extends BaseRepository {
    public function findActiveEvents(): array
    public function findUpcomingEvents($days = 7): array
    public function findRecurringEvents(): array
    public function generateEventInstances($eventId, $startDate, $endDate): array
    public function getEventStatistics($eventId): array
}
```

**CheckinRepository** - Check-in data management:
```php
class CheckinRepository extends BaseRepository {
    public function recordCheckin($userId, $eventId, $method = 'rfid'): int
    public function recordCheckout($checkinId): bool
    public function findActiveCheckins($userId): array
    public function getAttendanceReport($eventId, $startDate, $endDate): array
    public function calculateAttendanceStatistics($userId, $period): array
}
```

## 🔧 Error Handling

### ErrorHandler

Comprehensive error handling and logging system:

```php
class ErrorHandler {
    // Error Registration
    public function register(): void
    public function handleError($errno, $errstr, $errfile, $errline): void
    public function handleException($exception): void
    public function handleShutdown(): void
    
    // Logging System
    public function log($message, $context = [], $level = 'info'): void
    public function logError($message, $exception = null): void
    public function logSecurity($event, $details = []): void
    public function logPerformance($metric, $value): void
    
    // Error Reporting
    public function getErrorReport($startDate, $endDate): array
    public function getErrorStatistics(): array
    public function cleanOldLogs($days = 30): int
}
```

**Error Handling Features:**
- **Global Error Handler**: Catches all PHP errors and exceptions
- **Structured Logging**: JSON-formatted logs with context
- **Error Classification**: Categorize errors by severity and type
- **Error Reporting**: Generate error reports and statistics
- **Log Rotation**: Automatic log file management and cleanup

## 🛠️ Utility Functions

### SharedUtilities

Common utility functions used throughout the application:

```php
class SharedUtilities {
    // Data Transformation
    public static function sanitizeString($input): string
    public static function formatDateTime($datetime, $format = null): string
    public static function formatFileSize($bytes): string
    public static function generateUUID(): string
    
    // Validation Helpers
    public static function isValidEmail($email): bool
    public static function isValidPhone($phone): bool
    public static function isValidRFID($rfid): bool
    
    // Array Utilities
    public static function arrayGet($array, $key, $default = null): mixed
    public static function arrayOnly($array, $keys): array
    public static function arrayExcept($array, $keys): array
    
    // Security Helpers
    public static function generateRandomString($length = 32): string
    public static function hashData($data): string
    public static function verifyHash($data, $hash): bool
}
```

## 📈 Performance Dashboard

### PerformanceDashboard

Web interface for monitoring system performance:

```php
class PerformanceDashboard {
    public function getSystemMetrics(): array
    public function getQueryAnalytics(): array
    public function getCacheStatistics(): array
    public function getErrorSummary(): array
    public function getSecurityEvents(): array
    public function generatePerformanceReport(): array
}
```

**Dashboard Features:**
- **Real-time Metrics**: Live system performance monitoring
- **Query Analytics**: Database query performance analysis
- **Cache Statistics**: Cache hit rates and performance
- **Error Tracking**: Error frequency and patterns
- **Security Monitoring**: Security events and threats
- **Historical Reports**: Performance trends over time

## 🧪 Testing Integration

### Test Support

Core components include comprehensive testing support:

```php
// Example test integration
class CoreTestCase {
    protected function setUp(): void {
        $this->performanceManager = PerformanceManager::getInstance();
        $this->securityManager = SecurityManager::getInstance();
        $this->db = getDB();
        $this->db->beginTransaction();
    }
    
    protected function tearDown(): void {
        $this->db->rollBack();
    }
}
```

## 🔄 Event Management

### Event Manager

Sophisticated event scheduling and management:

```php
// Holiday integration
$holidays = new HolidayManager();
$holidays->loadGermanHolidays(2025);
$isHoliday = $holidays->isHoliday('2025-12-25');

// Event scheduling with recurring support
$eventManager = new EventManager();
$eventManager->createRecurringEvent([
    'name' => 'Daily Standup',
    'recurrence' => 'daily',
    'exclude_holidays' => true,
    'end_date' => '2025-12-31'
]);
```

## 🚀 Best Practices

### Code Organization

1. **Single Responsibility**: Each class has a focused purpose
2. **Dependency Injection**: Dependencies passed via constructor
3. **Interface Segregation**: Use interfaces for abstraction
4. **Error Handling**: Comprehensive error checking and logging
5. **Performance Awareness**: Built-in performance monitoring

### Security Guidelines

1. **Input Validation**: Always validate and sanitize input
2. **Output Escaping**: Escape all output to prevent XSS
3. **Prepared Statements**: Use prepared statements for database queries
4. **HTTPS Only**: Enforce HTTPS in production
5. **Regular Updates**: Keep dependencies updated

### Performance Optimization

1. **Caching Strategy**: Implement appropriate caching levels
2. **Query Optimization**: Monitor and optimize database queries
3. **Asset Optimization**: Minimize and compress frontend assets
4. **Connection Pooling**: Reuse database connections
5. **Monitoring**: Continuous performance monitoring

## 📚 Configuration Examples

### Production Configuration

```php
// config.php - Production Settings
define('DEBUG_MODE', false);
define('LOG_LEVEL', 'ERROR');
define('CACHE_ENABLED', true);
define('ASSET_OPTIMIZATION', true);
define('SESSION_SECURE', true);
define('CSRF_PROTECTION', true);
```

### Development Configuration

```php
// config.php - Development Settings
define('DEBUG_MODE', true);
define('LOG_LEVEL', 'DEBUG');
define('CACHE_ENABLED', false);
define('ASSET_OPTIMIZATION', false);
define('QUERY_LOGGING', true);
```

---

**Core System**: Production Ready  
**Last Updated**: January 2025  
**Architecture**: Enterprise MVC + Repository Pattern