# Source Directory (src/)

This directory contains the modern service-based architecture implementation of the RFID Check-in System, built using contemporary PHP patterns and clean separation of concerns.

## 📁 Directory Structure

```
src/
├── Application.php              # Main application bootstrap and service container
├── Controllers/                 # Request controllers (MVC pattern)
│   ├── BaseApiController.php    # Base API controller foundation
│   ├── BaseFrontendController.php # Base frontend controller foundation
│   ├── Api/                     # API controllers (planned)
│   └── Frontend/                # Web interface controllers (planned)
├── Models/                      # Data models (planned)
│   ├── BaseModel.php           # Abstract base model
│   ├── User.php                # User entity model
│   └── Event.php               # Event entity model
├── Services/                    # Business logic and core services
│   ├── ConfigurationService.php # Environment-based configuration management
│   ├── DatabaseService.php     # Database operations and connection pooling
│   ├── LoggingService.php      # Structured logging with multiple levels
│   ├── ErrorHandler.php        # Environment-aware error handling
│   ├── AuthenticationService.php # User authentication (planned)
│   └── SecurityService.php     # Security operations (planned)
├── Repositories/                # Data access layer (planned)
│   ├── BaseRepository.php      # Abstract repository pattern
│   ├── UserRepository.php      # User data access
│   └── EventRepository.php     # Event data access
├── Middleware/                  # Request middleware (planned)
│   ├── MiddlewareManager.php   # Middleware orchestration
│   ├── AuthMiddleware.php      # Authentication middleware
│   └── SecurityMiddleware.php  # Security enforcement
├── Routing/                     # URL routing system
│   └── Router.php              # Main router class (basic implementation)
├── Views/                       # Template system (planned)
└── Exceptions/                  # Custom exceptions (planned)
```

## 🏗️ Implemented Architecture

The current implementation focuses on core services that replace the legacy infrastructure:

### ✅ Core Services (Implemented)

#### ConfigurationService
- **Purpose**: Centralized configuration management with environment variables
- **Features**: 
  - Environment-based configuration loading
  - Lazy loading to prevent circular dependencies
  - Support for nested configuration keys
  - Default value handling
- **Replaces**: `core/config.php` and all define() constants

#### DatabaseService  
- **Purpose**: Modern database connectivity and operations
- **Features**:
  - Connection pooling and reuse
  - Prepared statement management
  - Transaction support with automatic rollback
  - Query performance monitoring
  - Lazy loading configuration
- **Replaces**: `core/database.php` and global database functions

#### LoggingService
- **Purpose**: Structured logging with multiple levels and contexts
- **Features**:
  - PSR-3 compliant log levels (DEBUG, INFO, WARNING, ERROR)
  - Structured logging with context data
  - File-based logging with rotation
  - Performance logging capabilities
  - Lazy configuration loading
- **Replaces**: Scattered error_log() calls throughout the system

#### ErrorHandler
- **Purpose**: Environment-aware error handling and display
- **Features**:
  - Development vs production error display modes
  - Structured error logging
  - Exception handling with sanitized stack traces
  - Integration with LoggingService
  - Security-conscious error reporting
- **Replaces**: `core/ErrorHandler.php` and basic error handling

#### Application (Bootstrap)
- **Purpose**: Application initialization and service orchestration
- **Features**:
  - Service container and dependency injection
  - Centralized application lifecycle management
  - Error handling setup
  - Configuration initialization
  - Routing system initialization
- **Replaces**: Mixed initialization patterns and bootstrap conflicts

### 🚧 Controllers Layer (Foundation Laid)

#### BaseApiController & BaseFrontendController
- **Purpose**: Foundation classes for API and web interface controllers
- **Features**: 
  - Request handling patterns
  - Response formatting standards
  - Service dependency management
  - Error handling integration
  - Input validation frameworks
- **Status**: Base classes implemented, specific controllers planned

### 🎯 Models Layer (Planned)

The model layer follows active record patterns for data entities:

- **BaseModel**: Abstract foundation with common CRUD operations
- **User**: User entity with authentication and profile management
- **Event**: Event entities for check-in sessions and monitoring

### 📁 Repository Pattern (Planned)

Data access abstraction using repository pattern:

- **BaseRepository**: Standard CRUD operations and query building
- **UserRepository**: User-specific data access with security features
- **EventRepository**: Event data management with performance optimization

### 🔧 Middleware System (Planned)

Request processing pipeline for cross-cutting concerns:

- **MiddlewareManager**: Middleware orchestration and execution order
- **Security Middleware**: Authentication, authorization, and security enforcement
- **Performance Middleware**: Request timing and optimization

### 🧩 Router Implementation

Current implementation provides basic routing capabilities:

- **Router.php**: Simple URL routing with parameter extraction
- **Status**: Basic implementation present, advanced features planned

## 🔧 Service Configuration

Services are configured through environment variables loaded by ConfigurationService:

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=rfid_checkin
DB_USER=username
DB_PASS=password

# Application Configuration  
APP_ENV=development
APP_DEBUG=true
APP_LOG_LEVEL=DEBUG

# Security Configuration
SESSION_SECRET=your-secret-key
CSRF_TOKEN_NAME=csrf_token
```

## 💡 Development Patterns

### Service Dependencies

Services use lazy loading to prevent circular dependencies:

```php
// ConfigurationService loads first
$config = new ConfigurationService();

// DatabaseService loads configuration when needed
$database = new DatabaseService($config);

// LoggingService integrates with both
$logger = new LoggingService($config);

// ErrorHandler orchestrates all error handling
$errorHandler = new ErrorHandler($config, $logger);
```

### Error Handling Flow

```php
// ErrorHandler catches all exceptions
try {
    // Application logic
} catch (Exception $e) {
    // Structured logging with context
    $logger->error('Operation failed', [
        'exception' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    // Environment-aware display
    if ($config->get('APP_DEBUG')) {
        // Show detailed error in development
    } else {
        // Show generic error in production
    }
}
```

## 🔄 Migration from Legacy

The src/ directory implements clean replacements for legacy core files:

| Legacy File | Modern Replacement | Status |
|-------------|-------------------|---------|
| `core/config.php` | `ConfigurationService` | ✅ Complete |
| `core/database.php` | `DatabaseService` | ✅ Complete |
| `core/ErrorHandler.php` | `ErrorHandler` | ✅ Complete |
| `core/auth.php` | `AuthenticationService` | 🚧 Planned |
| `core/utils.php` | Various Services | 🚧 Planned |

## 🧪 Testing Strategy

Each service is designed for testability:

- **Unit Tests**: Individual service testing with mocked dependencies
- **Integration Tests**: Service interaction testing
- **Performance Tests**: Service performance benchmarking

## 📊 Performance Considerations

Services include built-in performance optimizations:

- **Lazy Loading**: Services load only when needed
- **Connection Pooling**: Database connections are reused
- **Query Optimization**: DatabaseService includes query performance monitoring
- **Logging Performance**: LoggingService tracks operation timing

## 🔒 Security Implementation

Security is built into each service layer:

- **Configuration**: Environment variables prevent credential exposure
- **Database**: Prepared statements prevent SQL injection
- **Logging**: Sensitive data filtering in log output
- **Error Handling**: Production error sanitization

## 🚀 Future Roadmap

Planned service implementations (priority order):

1. **AuthenticationService**: User authentication and session management
2. **SecurityService**: Advanced security enforcement
3. **Full MVC Controllers**: Complete API and frontend controllers
4. **Repository Layer**: Data access abstraction
5. **Middleware System**: Request processing pipeline
6. **Template System**: Modern view rendering
7. **Advanced Router**: Route groups, middleware integration, parameter validation