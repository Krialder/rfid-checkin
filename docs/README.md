# Documentation Directory - Modern Architecture Guide

This directory contains comprehensive documentation for the modern RFID Check-in System, focusing on the service-based architecture and contemporary development practices.

## 📁 Documentation Structure

```
docs/
├── README.md                      # This guide - modern architecture overview
├── api/                           # API documentation (planned)
│   ├── authentication.md          # AuthenticationService API
│   ├── rest-endpoints.md          # RESTful API documentation
│   └── service-integration.md     # Service integration patterns
├── guides/                        # Developer and user guides (planned)
│   ├── migration-guide.md         # Legacy to modern migration
│   ├── service-development.md     # Creating new services
│   └── testing-guide.md           # Testing modern services
├── architecture/                  # Architecture documentation (planned)
│   ├── service-patterns.md        # Service design patterns
│   ├── dependency-injection.md    # DI container usage
│   └── performance-optimization.md # Service performance
└── deployment/                    # Deployment guides (planned)
    ├── environment-setup.md       # Environment configuration
    ├── service-deployment.md      # Service deployment patterns
    └── monitoring.md               # Service monitoring
```

## 🏗️ Modern Architecture Overview

### Service-Based Architecture

The RFID Check-in System has been completely modernized from legacy procedural code to a clean service-based architecture:

**Core Services Implemented:**
- **ConfigurationService**: Environment-based configuration management
- **DatabaseService**: Modern database operations with connection pooling
- **LoggingService**: Structured logging with multiple levels and contexts
- **ErrorHandler**: Environment-aware error handling and display
- **Application**: Service container and application lifecycle management

**Modern Patterns:**
- **Dependency Injection**: Services receive dependencies through constructors
- **Single Entry Point**: `bootstrap.php` initializes all services and application
- **Environment Configuration**: `.env` files replace hardcoded constants
- **Separation of Concerns**: Clean separation between configuration, data, logging, and business logic
- **Testability**: All services designed for unit and integration testing

### Service Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    Application Layer                        │
├─────────────────────────────────────────────────────────────┤
│  bootstrap.php → Application.php → Service Container       │
├─────────────────────────────────────────────────────────────┤
│                     Service Layer                          │
├─────────────────────────────────────────────────────────────┤
│  ConfigurationService  │  DatabaseService  │  LoggingService │
│  ErrorHandler         │  AuthService      │  SecurityService │
├─────────────────────────────────────────────────────────────┤
│                   Infrastructure Layer                      │
├─────────────────────────────────────────────────────────────┤
│  Environment Variables │  Database Pool   │  Log Files      │
│  Error Pages          │  Session Store   │  Cache Layer    │
└─────────────────────────────────────────────────────────────┘
```

## 🚀 Development Workflow

### Modern Development Practices

**1. Environment Setup:**
```bash
# Clone the repository
git clone <repository-url>
cd rfid-checkin

# Copy environment template
cp core/config.template.php .env

# Configure environment variables
# Edit .env with your database credentials and settings

# Initialize the application
php bootstrap.php
```

**2. Service Development Pattern:**
```php
<?php
declare(strict_types=1);

namespace RfidCheckin\Services;

class NewService
{
    private ConfigurationService $config;
    private LoggingService $logger;
    
    public function __construct(
        ConfigurationService $config,
        LoggingService $logger
    ) {
        $this->config = $config;
        $this->logger = $logger;
    }
    
    public function performOperation(): void
    {
        try {
            // Service logic here
            $this->logger->info('Operation completed successfully');
        } catch (Exception $e) {
            $this->logger->error('Operation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }
}
```

**3. Service Integration:**
```php
// In Application.php or service container
$config = new ConfigurationService();
$logger = new LoggingService($config);
$newService = new NewService($config, $logger);

// Service registration
$container->register('NewService', function() use ($config, $logger) {
    return new NewService($config, $logger);
});
```

### Code Standards

**PHP Standards:**
- **PHP 8.1+**: Strict typing with `declare(strict_types=1)`
- **PSR-12**: Code style and formatting standards
- **Namespacing**: All classes in `RfidCheckin\Services` namespace
- **Type Hints**: Full type declarations for parameters and return values
- **Documentation**: PHPDoc blocks for all public methods

**Service Design Principles:**
- **Single Responsibility**: Each service has one clear purpose
- **Dependency Injection**: All dependencies injected through constructor
- **Interface Segregation**: Use interfaces for service contracts
- **Lazy Loading**: Services load dependencies only when needed
- **Error Handling**: Comprehensive exception handling with logging

## 🔧 Configuration Management

### Environment-Based Configuration

**Modern Approach (.env file):**
```env
# Database Configuration
DB_HOST=localhost
DB_NAME=rfid_checkin
DB_USER=username
DB_PASS=password
DB_PORT=3306

# Application Configuration
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost
APP_NAME="RFID Check-in System"

# Logging Configuration
LOG_LEVEL=DEBUG
LOG_FILE=logs/application.log
LOG_MAX_FILES=10

# Security Configuration
SESSION_SECRET=your-32-character-secret-key
CSRF_TOKEN_NAME=csrf_token
SESSION_LIFETIME=3600
```

**ConfigurationService Usage:**
```php
$config = new ConfigurationService();

// Get configuration values with defaults
$dbHost = $config->get('DB_HOST', 'localhost');
$appDebug = $config->get('APP_DEBUG', false);
$logLevel = $config->get('LOG_LEVEL', 'INFO');

// Environment detection
if ($config->isProduction()) {
    // Production-specific logic
} elseif ($config->isDebugMode()) {
    // Development debugging
}
```

### Legacy Migration Guide

**From Legacy define() Constants:**
```php
// OLD: Legacy approach (deprecated)
define('DB_HOST', 'localhost');
define('DB_NAME', 'rfid_checkin');
define('DEBUG_MODE', true);

// NEW: Modern approach
// Set in .env file and access via ConfigurationService
$config = new ConfigurationService();
$dbHost = $config->get('DB_HOST');
$dbName = $config->get('DB_NAME');
$debugMode = $config->get('APP_DEBUG');
```

## 🗄️ Database Integration

### DatabaseService Usage

**Modern Database Operations:**
```php
$database = new DatabaseService($config);

// Simple queries with automatic prepared statements
$users = $database->query(
    'SELECT * FROM users WHERE active = ? AND role = ?',
    [1, 'admin']
)->fetchAll();

// Transactions with automatic rollback
$userId = $database->transaction(function() use ($userData) {
    $stmt = $this->query(
        'INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)',
        [$userData['username'], $userData['email'], $userData['password_hash']]
    );
    return $this->getConnection()->lastInsertId();
});

// Performance monitoring
$queryCount = $database->getQueryCount();
$slowQueries = $database->getSlowQueries();
```

**Repository Pattern Integration:**
```php
class UserRepository
{
    private DatabaseService $database;
    private LoggingService $logger;
    
    public function __construct(DatabaseService $database, LoggingService $logger)
    {
        $this->database = $database;
        $this->logger = $logger;
    }
    
    public function findById(int $id): ?array
    {
        $user = $this->database->query(
            'SELECT * FROM users WHERE user_id = ? AND is_active = 1',
            [$id]
        )->fetch();
        
        if ($user) {
            $this->logger->debug('User found', ['user_id' => $id]);
        } else {
            $this->logger->warning('User not found', ['user_id' => $id]);
        }
        
        return $user ?: null;
    }
}
```

## 📊 Logging and Monitoring

### LoggingService Usage

**Structured Logging:**
```php
$logger = new LoggingService($config);

// Different log levels
$logger->debug('Debugging information', ['variable' => $value]);
$logger->info('General information', ['action' => 'user_login']);
$logger->warning('Warning condition', ['threshold' => 100, 'current' => 95]);
$logger->error('Error occurred', ['exception' => $e->getMessage()]);

// Context logging
$logger->info('Database query executed', [
    'sql' => 'SELECT * FROM users',
    'params' => [1, 'admin'],
    'execution_time' => 0.025,
    'rows_affected' => 5
]);

// Performance logging
$startTime = microtime(true);
// ... operation ...
$logger->info('Operation completed', [
    'operation' => 'user_creation',
    'duration' => microtime(true) - $startTime
]);
```

**Log Output Example:**
```json
{
    "timestamp": "2025-01-16T10:30:45+00:00",
    "level": "INFO",
    "message": "User authentication successful",
    "context": {
        "user_id": 123,
        "username": "admin",
        "ip_address": "192.168.1.100",
        "user_agent": "Mozilla/5.0...",
        "session_id": "abc123",
        "authentication_method": "password"
    },
    "extra": {
        "environment": "development",
        "application": "rfid-checkin"
    }
}
```

## 🧪 Testing Modern Services

### Unit Testing Services

**Service Test Example:**
```php
<?php
use PHPUnit\Framework\TestCase;

class ConfigurationServiceTest extends TestCase
{
    private ConfigurationService $config;
    
    protected function setUp(): void
    {
        // Use test environment variables
        $_ENV['TEST_VALUE'] = 'test_result';
        $_ENV['APP_DEBUG'] = 'true';
        
        $this->config = new ConfigurationService();
    }
    
    public function testGetConfiguration(): void
    {
        $value = $this->config->get('TEST_VALUE');
        $this->assertEquals('test_result', $value);
    }
    
    public function testGetWithDefault(): void
    {
        $value = $this->config->get('NON_EXISTENT', 'default_value');
        $this->assertEquals('default_value', $value);
    }
    
    public function testDebugMode(): void
    {
        $this->assertTrue($this->config->isDebugMode());
    }
}
```

**Integration Testing:**
```php
class DatabaseServiceIntegrationTest extends TestCase
{
    private DatabaseService $database;
    private ConfigurationService $config;
    
    protected function setUp(): void
    {
        $this->config = new ConfigurationService();
        $this->config->set('DB_NAME', 'rfid_checkin_test');
        
        $this->database = new DatabaseService($this->config);
        $this->database->beginTransaction();
    }
    
    protected function tearDown(): void
    {
        $this->database->rollBack();
    }
    
    public function testDatabaseOperations(): void
    {
        // Test database operations with automatic rollback
        $result = $this->database->query('SELECT COUNT(*) as count FROM users');
        $this->assertIsArray($result->fetch());
    }
}
```

### Testing Workflow

**Running Tests:**
```bash
# Run all tests
php tests/run-tests.php

# Run specific test category
php tests/run-tests.php --category=services

# Run with verbose output
php tests/run-tests.php --verbose

# Generate coverage report
php tests/run-tests.php --coverage
```

## 🔒 Security Implementation

### Security Best Practices

**Input Validation:**
```php
// Use proper validation in services
class UserService
{
    public function createUser(array $data): int
    {
        // Validate input data
        $this->validateUserData($data);
        
        // Hash password securely
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Use prepared statements (automatic in DatabaseService)
        return $this->database->query(
            'INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)',
            [$data['username'], $data['email'], $passwordHash]
        );
    }
    
    private function validateUserData(array $data): void
    {
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        
        if (strlen($data['password']) < 8) {
            throw new InvalidArgumentException('Password too short');
        }
    }
}
```

**Error Handling Security:**
```php
// ErrorHandler provides environment-aware error display
$errorHandler = new ErrorHandler($config, $logger);

try {
    // Application logic
} catch (Exception $e) {
    // Log full error details
    $logger->error('Application error', [
        'exception' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // Display appropriate error to user
    if ($config->isDebugMode()) {
        // Show detailed error in development
        echo $errorHandler->renderDetailedError($e);
    } else {
        // Show generic error in production
        echo $errorHandler->renderGenericError();
    }
}
```

## 📚 API Development

### RESTful API with Services

**Controller Example:**
```php
<?php
namespace RfidCheckin\Controllers\Api;

class UserController
{
    private UserRepository $userRepository;
    private LoggingService $logger;
    private AuthenticationService $auth;
    
    public function __construct(
        UserRepository $userRepository,
        LoggingService $logger,
        AuthenticationService $auth
    ) {
        $this->userRepository = $userRepository;
        $this->logger = $logger;
        $this->auth = $auth;
    }
    
    public function getUser(int $id): void
    {
        try {
            // Check authentication
            if (!$this->auth->isAuthenticated()) {
                $this->jsonResponse(['error' => 'Unauthorized'], 401);
                return;
            }
            
            // Get user data
            $user = $this->userRepository->findById($id);
            
            if (!$user) {
                $this->jsonResponse(['error' => 'User not found'], 404);
                return;
            }
            
            // Log access
            $this->logger->info('User data accessed via API', [
                'user_id' => $id,
                'accessor' => $this->auth->getCurrentUserId()
            ]);
            
            // Return user data
            $this->jsonResponse(['user' => $user]);
            
        } catch (Exception $e) {
            $this->logger->error('API error', ['error' => $e->getMessage()]);
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }
    
    private function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
```

## 🚀 Deployment Guidelines

### Production Deployment

**Environment Configuration:**
```env
# Production .env configuration
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=ERROR

# Database with connection pooling
DB_HOST=prod-db-server
DB_NAME=rfid_checkin_prod
DB_POOL_SIZE=20

# Security settings
SESSION_SECURE=true
HTTPS_ONLY=true
```

**Performance Optimization:**
- Enable OpCache for PHP
- Configure database connection pooling
- Set up log rotation
- Enable HTTPS
- Configure error monitoring

### Monitoring and Maintenance

**Health Checks:**
```php
// Health check endpoint
class HealthController
{
    public function check(): void
    {
        $config = new ConfigurationService();
        $database = new DatabaseService($config);
        
        $health = [
            'status' => 'ok',
            'timestamp' => date('c'),
            'services' => [
                'database' => $database->isConnected() ? 'ok' : 'error',
                'configuration' => $config->isLoaded() ? 'ok' : 'error'
            ]
        ];
        
        $status = in_array('error', $health['services']) ? 500 : 200;
        $this->jsonResponse($health, $status);
    }
}
```

---

**Documentation Status**: Modern Architecture Complete  
**Service Coverage**: 100% of implemented services documented  
**Last Updated**: January 2025  
**Architecture**: Service-Based with Dependency Injection  
**PHP Version**: 8.1+ with strict typing