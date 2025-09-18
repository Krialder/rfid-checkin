# RFID Check-in System

A modern, enterprise-grade RFID-based attendance and check-in system built with PHP 8.1+, featuring ESP32 hardware integration, service-oriented architecture, and comprehensive data management capabilities.

![Version](https://img.shields.io/badge/version-3.1.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg)
![Architecture](https://img.shields.io/badge/Architecture-Service--Based-green.svg)
![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)

## 📋 Table of Contents

- [Overview](#overview)
- [🚀 Modern Architecture](#-modern-architecture)
- [Features](#features)
- [System Requirements](#system-requirements)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Hardware Setup](#hardware-setup)
- [API Documentation](#api-documentation)
- [Development](#development)
- [Migration Guide](#migration-guide)
- [Testing](#testing)
- [Performance & Monitoring](#performance--monitoring)
- [Security](#security)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

## 🎯 Overview

The RFID Check-in System is an attendance management solution built with modern PHP patterns. The system has been completely refactored to use a clean service-based architecture with proper separation of concerns.

### 🔧 Recent Modernization (v3.1.0)

This system has undergone a complete architectural transformation:

- ✅ **Single Entry Point**: Eliminated dual entry point conflicts (`bootstrap.php` only)
- ✅ **Service Architecture**: Modern namespaced services (`RfidCheckin\Services\*`)
- ✅ **Environment Config**: Centralized configuration with environment variables
- ✅ **Legacy Removal**: Eliminated all legacy core files and dependencies
- ✅ **Modern Patterns**: Dependency injection, lazy loading, singleton patterns
- ✅ **Better Error Handling**: Environment-aware error display and structured logging

### Key Capabilities

- **🏗️ Service Architecture**: Clean service-based design with dependency injection
- **📡 RFID Hardware Integration**: ESP32 RFID reader support with real-time communication
- **🔧 Core Services**: ConfigurationService, DatabaseService, LoggingService, ErrorHandler
- **🛡️ Security Features**: Comprehensive input validation and session management
- **⚡ Performance Features**: Connection pooling, query optimization, and structured logging
- **📱 Mobile Support**: Responsive design with mobile-friendly interface

## 🚀 Architecture

The system uses a service-based architecture with clean separation of concerns and modern PHP patterns.

### Entry Point & Bootstrap

**Single Entry Point**: `bootstrap.php` (modern) with `index.php` as simple redirect

```php
// Modern entry flow
bootstrap.php → Application::class → ConfigurationService → DatabaseService → Router
```

### Service Layer Architecture

```
src/Services/
├── 📋 ConfigurationService     # Environment-based configuration management
├── �️ DatabaseService         # Connection pooling, transactions, query optimization
├── � LoggingService          # Structured logging with multiple levels
├── � ErrorHandler            # Environment-aware error handling
├── 🔐 AuthenticationService   # User authentication and session management
└── 🛡️ SecurityService         # Security operations and validation
```

### Modern Directory Structure

```
rfid-checkin/
├── 🚀 bootstrap.php           # Modern application entry point
├── 📄 index.php               # Redirect to bootstrap.php
├── �️ src/                    # Modern Service Architecture
│   ├── Application.php        # Application bootstrap and dependency injection
│   ├── Services/              # Business logic and data services
│   ├── Controllers/           # Request handling (API & Frontend)
│   ├── Models/                # Data models and entities
│   ├── Middleware/            # Request middleware pipeline
│   ├── Routing/               # URL routing system
│   └── Repositories/          # Data access layer
├── ⚙️ config/                 # Configuration files
│   └── routes.php             # Route definitions
├── 🎨 assets/                 # Frontend resources (CSS, JS)
├── 🔌 hardware/               # ESP32 integration code
├── �️ database/               # Database setup and migrations
├── 🧪 tests/                  # Testing framework
└── 📚 docs/                   # Documentation
```

### Removed Legacy Files

The following legacy files have been completely removed as part of the modernization:

- ❌ `core/config.php` → ✅ `ConfigurationService`
- ❌ `core/auth.php` → ✅ `AuthenticationService`
- ❌ `core/database.php` → ✅ `DatabaseService`
- ❌ `core/SecurityManager.php` → ✅ `SecurityService`
- ❌ `core/ErrorHandler.php` → ✅ `Services\ErrorHandler`

### Design Patterns

- **🏗️ Service Container**: Dependency injection and service management
- **🔄 Singleton Pattern**: Shared service instances with lazy loading
- **📊 Repository Pattern**: Data access abstraction
- **🎭 MVC Architecture**: Separation of concerns
- **🛡️ Middleware Pattern**: Request/response processing

## ✨ Features

### Core Functionality
- ✅ **RFID Check-in/Check-out**: Hardware-based attendance tracking with ESP32 integration
- ✅ **Event Management**: Create, schedule, and manage events with recurring support
- ✅ **User Management**: Role-based access control (Admin, Moderator, User)
- ✅ **Real-time Dashboard**: Live attendance monitoring and analytics
- ✅ **Mobile Support**: Responsive design with mobile check-in capabilities
- ✅ **Reporting System**: Attendance reports and analytics

### Service Features
- ⚙️ **Environment Configuration**: Centralized config with environment variables
- 🗄️ **Connection Pooling**: Efficient database connection management
- 📝 **Structured Logging**: Multi-level logging with context and performance tracking
- 🚨 **Smart Error Handling**: Development vs production error display modes
- � **Session Management**: Secure authentication with CSRF protection
- ⚡ **Performance Optimization**: Query optimization and caching strategies

### Advanced Features
- 🔄 **Recurring Events**: Support for daily, weekly, monthly recurring events
- 🏢 **User Groups**: Department and team-based organization
- 📱 **Alternative Check-in**: Mobile and manual check-in methods
- 🔔 **Notifications**: System notifications and alerts
- 📊 **Performance Monitoring**: System performance tracking
- 🛡️ **Security Monitoring**: Security logging and protection

### Hardware Integration
- 🏷️ **ESP32 RFID Readers**: Support for RC522 RFID modules
- 📡 **Real-time Communication**: Instant check-in processing with device status monitoring
- 🔧 **Device Management**: Monitor and configure RFID devices remotely
- 🚨 **Registration Mode**: Dynamic RFID tag registration system

## �📋 System Requirements

### System Requirements
- **PHP**: 8.1 or higher (with strict typing support)
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Database**: MySQL 8.0+ or MariaDB 10.5+
- **Memory**: 512MB RAM minimum (1GB recommended)
- **Storage**: 1GB available space

### PHP Extensions
- `pdo_mysql` - Database connectivity
- `curl` - HTTP requests
- `json` - JSON processing
- `mbstring` - String handling
- `openssl` - Security features
- `session` - Session management

### Hardware Requirements (Optional)
- **ESP32 Development Board** (DevKit or similar)
- **RC522 RFID Module** (13.56MHz)
- **RFID Tags/Cards** (ISO14443A compatible)
- **Jumper Wires and Breadboard**
- **Power Supply** (3.3V for RFID module)

### Development Tools
- **Composer** (for dependency management)
- **Git** (for version control)
- **Arduino IDE** (for hardware programming)

## 🚀 Quick Start

### 1. Clone Repository

```bash
git clone https://github.com/Krialder/rfid-checkin.git
cd rfid-checkin
```

### 2. Environment Setup

Create environment configuration:

```bash
# Copy environment template (if available)
cp .env.example .env

# Or create .env file with these variables:
cat > .env << EOF
# Database Configuration
DB_HOST=localhost
DB_NAME=rfid_checkin_system
DB_USER=your_db_user
DB_PASS=your_secure_password
DB_CHARSET=utf8mb4

# Application Configuration
APP_ENV=production
APP_DEBUG=false
APP_NAME="RFID Check-in System"
BASE_URL=http://your-domain.com/rfid-checkin

# Security Configuration
SESSION_LIFETIME=3600
PASSWORD_MIN_LENGTH=8
CSRF_PROTECTION=true

# Logging Configuration
LOG_LEVEL=INFO
LOG_PATH=./logs
EOF
```

### 3. Set Permissions

```bash
# Set proper permissions
chmod -R 755 .
mkdir -p logs uploads cache
chmod -R 777 logs/ uploads/ cache/
```

### 4. Configure Web Server

#### Apache Configuration
```apache
<VirtualHost *:80>
    ServerName rfid-checkin.local
    DocumentRoot /path/to/rfid-checkin
    DirectoryIndex bootstrap.php index.php
    
    <Directory /path/to/rfid-checkin>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/rfid-checkin-error.log
    CustomLog ${APACHE_LOG_DIR}/rfid-checkin-access.log combined
</VirtualHost>
```

#### Nginx Configuration
```nginx
server {
    listen 80;
    server_name rfid-checkin.local;
    root /path/to/rfid-checkin;
    index bootstrap.php index.php;
    
    location / {
        try_files $uri $uri/ /bootstrap.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index bootstrap.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 5. Database Setup

```bash
# Navigate to database directory
cd database/

# Run the setup script via web browser
http://your-domain.com/database/setup-database.php

# Or use command line
php setup-database.php
```

The setup script will:
- Create the database structure
- Insert default data
- Configure system settings
- Create the default admin account

## ⚙️ Configuration

The system uses environment variables for configuration through the `ConfigurationService`. All settings are centralized and loaded from environment variables.

### Environment Variables

#### Database Configuration
```bash
DB_HOST=localhost                    # Database host
DB_NAME=rfid_checkin_system         # Database name
DB_USER=your_db_user                # Database username
DB_PASS=your_secure_password        # Database password
DB_CHARSET=utf8mb4                  # Database charset
```

#### Application Settings
```bash
APP_ENV=production                   # Environment: development, production
APP_DEBUG=false                     # Debug mode (true for development)
APP_NAME="RFID Check-in System"     # Application name
BASE_URL=http://your-domain.com     # Base URL for the application
```

#### Security Settings
```bash
SESSION_LIFETIME=3600               # Session timeout in seconds
PASSWORD_MIN_LENGTH=8               # Minimum password length
CSRF_PROTECTION=true               # Enable CSRF protection
MAX_LOGIN_ATTEMPTS=5               # Maximum login attempts before lockout
LOCKOUT_DURATION=900              # Lockout duration in seconds
```

#### Logging Configuration
```bash
LOG_LEVEL=INFO                     # Log level: DEBUG, INFO, WARNING, ERROR
LOG_PATH=./logs                    # Log file directory
LOG_ENABLED=true                   # Enable/disable logging
```

### Migration from Legacy Configuration

If you're migrating from the old `core/config.php` approach:

1. **Remove legacy files**: The old `core/config.php` is no longer used
2. **Set environment variables**: Use the `.env` file or server environment variables
3. **Update references**: All configuration now goes through `ConfigurationService`

### Configuration Service Usage

```php
use RfidCheckin\Services\ConfigurationService;

$config = ConfigurationService::getInstance();

// Get configuration values
$dbHost = $config->get('database.host');
$appName = $config->get('app.name');
$debugMode = $config->get('app.debug_mode', false);

// Check configuration
if ($config->has('database.host')) {
    // Database configuration exists
}
```

## 🔌 Hardware Setup

### ESP32 RFID Reader Wiring

| ESP32 Pin | RC522 Pin | Function |
|-----------|-----------|----------|
| GPIO21    | SDA       | Slave Select |
| GPIO18    | SCK       | Serial Clock |
| GPIO23    | MOSI      | Master Out Slave In |
| GPIO19    | MISO      | Master In Slave Out |
| GPIO22    | RST       | Reset |
| 3.3V      | 3.3V      | Power (⚠️ NOT 5V!) |
| GND       | GND       | Ground |

### Optional Status LEDs

| ESP32 Pin | Component | Purpose |
|-----------|-----------|---------|
| GPIO2     | Green LED + 330Ω | Success/Ready |
| GPIO4     | Red LED + 330Ω   | Error/Failed |

### Hardware Configuration

1. Copy hardware configuration template:
```bash
cp hardware/config-example.h hardware/config.h
```

2. Edit configuration:
```cpp
// WiFi Configuration
#define WIFI_SSID "Your_WiFi_Network"
#define WIFI_PASSWORD "Your_WiFi_Password"

// Server Configuration  
#define SERVER_URL "http://your-domain.com/rfid-checkin"
#define DEVICE_ID "READER_001"
```

3. Upload firmware to ESP32 using Arduino IDE

### Hardware Features
- **Dual API Support**: Direct check-in + web queue
- **Registration Mode**: Dynamic RFID tag registration
- **Real-time Feedback**: LED and serial output
- **Network Reliability**: Automatic WiFi reconnection
- **Remote Monitoring**: Device status reporting

## 💻 Usage

### Default Login Credentials

After installation, use these credentials to access the admin panel:

- **Username**: `admin`
- **Email**: `admin@rfidcheckin.local`
- **Password**: `admin123`

⚠️ **Important**: Change the default password immediately after first login!

### Basic Workflow

1. **Admin Setup**:
   - Log in to admin panel
   - Create users and assign RFID tags
   - Set up events and schedules
   - Configure user groups and permissions

2. **RFID Registration**:
   - Enable Registration Mode in admin panel
   - Scan RFID tags to register them
   - Assign registered tags to users
   - Test check-in/check-out functionality

3. **Daily Operations**:
   - Users scan RFID tags to check in/out
   - Monitor real-time dashboard
   - Generate attendance reports
   - Manage events and schedules

### User Roles

- **Admin**: Full system access, user management, configuration
- **Moderator**: Event management, reporting, limited user access
- **User**: Basic check-in/out, personal dashboard, profile management

## 📡 API Documentation

### Core Endpoints

#### RFID Check-in
```http
POST /api/rfid-checkin.php
Content-Type: application/x-www-form-urlencoded

rfid=1234ABCD&device_id=READER_001
```

Response:
```json
{
  "success": true,
  "action": "checkin",
  "user": {
    "name": "John Doe",
    "id": 123
  },
  "event": {
    "name": "Daily Standup",
    "id": 1
  },
  "timestamp": "2025-09-18T09:00:00Z"
}
```

#### System Status
```http
GET /api/status
```

Response:
```json
{
  "status": "ok",
  "timestamp": "2025-09-18T09:00:00Z",
  "services": {
    "database": "connected",
    "logging": "active",
    "configuration": "loaded"
  }
}
```

### Authentication

API endpoints require proper authentication through the AuthenticationService.

## 🔄 Migration Guide

### From Legacy Architecture (v3.0 to v3.1)

If you're upgrading from the previous version, here are the key changes:

#### Entry Point Changes
- **OLD**: Mixed entry points (`index.php` + `bootstrap.php`)
- **NEW**: Single entry point (`bootstrap.php`) with `index.php` redirecting

#### Configuration Changes
- **OLD**: `core/config.php` with define() constants
- **NEW**: Environment variables through `ConfigurationService`

```bash
# Migration steps:
1. Backup your current configuration from core/config.php
2. Create .env file with equivalent environment variables
3. Remove or ignore core/config.php (no longer used)
4. Update any custom code to use ConfigurationService
```

#### Service Architecture
- **OLD**: Direct includes and global functions
- **NEW**: Namespaced services with dependency injection

#### Removed Files
The following files are no longer used and can be safely removed:
- `core/config.php` (replaced by ConfigurationService)
- `core/auth.php` (replaced by AuthenticationService)
- `core/database.php` (replaced by DatabaseService)
- `core/SecurityManager.php` (replaced by SecurityService)
- `core/ErrorHandler.php` (replaced by Services\ErrorHandler)

#### Code Updates
If you have custom code, update service calls:

```php
// OLD
require_once 'core/config.php';
require_once 'core/database.php';
$db = getDB();

// NEW
use RfidCheckin\Services\DatabaseService;
$db = DatabaseService::getInstance();
```

## 🛠️ Development

### Running in Development Mode

```bash
# Set debug mode in .env
APP_ENV=development
APP_DEBUG=true
LOG_LEVEL=DEBUG

# Check system status
php -r "require 'bootstrap.php'; echo 'Services loaded successfully';"
```

### Service Development

```php
// Example: Creating a new service
namespace RfidCheckin\Services;

class YourNewService
{
    private static ?YourNewService $instance = null;
    private ConfigurationService $config;
    private LoggingService $logger;
    
    private function __construct()
    {
        $this->config = ConfigurationService::getInstance();
        $this->logger = LoggingService::getInstance();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

## 🧪 Testing

### Running Tests

The system includes a comprehensive testing framework:

```bash
# Run all tests
php tests/run-tests.php

# Run specific test suite
php tests/run-tests.php --suite=unit
php tests/run-tests.php --suite=integration
php tests/run-tests.php --suite=performance
php tests/run-tests.php --suite=security
```

### Test Coverage

- **Unit Tests**: Core functionality, repositories, services
- **Integration Tests**: API endpoints, authentication flow, database operations
- **Performance Tests**: Query optimization, caching, asset loading
- **Security Tests**: SQL injection, CSRF protection, input validation

### Manual Testing

Test the RFID hardware:

```bash
# ESP32 Serial Monitor Commands
status          # Show system status
test           # Test server connection
regmode        # Check registration mode
sim 1234ABCD   # Simulate RFID scan
restart        # Restart device
```

## ⚡ Performance & Monitoring

### Built-in Optimization
- **Connection Pooling**: Efficient database connection management through DatabaseService
- **Query Optimization**: Performance monitoring and optimization suggestions
- **Structured Logging**: Performance tracking with LoggingService
- **Error Handling**: Environment-aware error display (dev vs production)
- **Service Caching**: Singleton patterns with lazy loading

### Performance Metrics
- **Service Response**: < 100ms for most service calls
- **Database Queries**: Optimized with connection pooling
- **Memory Usage**: Efficient with singleton pattern
- **Error Handling**: Fast development debugging, secure production display

### Monitoring
```php
// Check service performance
$config = ConfigurationService::getInstance();
$db = DatabaseService::getInstance();
$logger = LoggingService::getInstance();

// Services provide built-in monitoring
$queryCount = $db->getQueryCount();
$logger->info('Performance check', ['queries' => $queryCount]);
```

## 🛡️ Security

### Security Features
- **Environment Configuration**: Sensitive data in environment variables
- **Session Management**: Secure session handling through AuthenticationService
- **Input Validation**: Comprehensive input sanitization
- **Error Handling**: Secure error display in production mode
- **CSRF Protection**: Built-in CSRF token validation
- **SQL Injection Protection**: Prepared statements throughout DatabaseService

### Security Configuration
```bash
# .env security settings
CSRF_PROTECTION=true
SESSION_LIFETIME=3600
MAX_LOGIN_ATTEMPTS=5
LOCKOUT_DURATION=900
APP_DEBUG=false  # Never true in production
```

## 🔧 Troubleshooting

### Common Issues

#### Service Loading Issues
```bash
# Check if services can be loaded
php -r "require 'bootstrap.php'; echo 'OK';"

# Check specific service
php -r "
require 'bootstrap.php';
\$config = \RfidCheckin\Services\ConfigurationService::getInstance();
echo 'Config loaded: ' . (\$config ? 'OK' : 'FAIL');
"
```

#### Database Connection Issues
```bash
# Test database connection
php -r "
require 'bootstrap.php';
\$db = \RfidCheckin\Services\DatabaseService::getInstance();
echo 'DB connection: ' . (\$db->testConnection() ? 'OK' : 'FAIL');
"
```

#### Environment Variables
```bash
# Check if environment variables are loaded
php -r "
echo 'DB_HOST: ' . (\$_ENV['DB_HOST'] ?? 'Not set') . PHP_EOL;
echo 'APP_ENV: ' . (\$_ENV['APP_ENV'] ?? 'Not set') . PHP_EOL;
"
```

### Log Files
System logs are managed by LoggingService:
- **Application logs**: `logs/application.log`
- **Error logs**: `logs/error.log`
- **Debug logs**: Available when `APP_DEBUG=true`

## 🤝 Contributing

### Development Setup

1. Fork the repository
2. Create feature branch: `git checkout -b feature/new-feature`
3. Set up development environment with `APP_DEBUG=true`
4. Run tests: `php tests/run-tests.php`
5. Make changes following the service architecture patterns
6. Commit changes: `git commit -m 'Add new feature'`
7. Push to branch: `git push origin feature/new-feature`
8. Open a Pull Request

### Code Standards

- Follow PSR-12 coding standards
- Use strict typing: `declare(strict_types=1);`
- Create services following the singleton pattern
- Use environment variables for configuration
- Include proper error handling and logging

### Service Development Guidelines

When creating new services:
1. Extend the service pattern used by existing services
2. Use dependency injection and lazy loading
3. Implement proper logging with LoggingService
4. Follow the singleton pattern for shared resources
5. Use ConfigurationService for all configuration needs

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **PHP Community**: For modern PHP patterns and best practices
- **RC522 Library**: MFRC522 Arduino library contributors
- **Bootstrap**: Frontend framework
- **Open Source Libraries**: Various libraries that make this project possible

---

**Project Status**: ✅ Modernized Architecture Complete  
**Last Updated**: September 2025  
**Version**: 3.1.0  
**Architecture**: Service-Based with Modern PHP Patterns

For detailed technical documentation, see:
- [`src/README.md`](src/README.md) - Service architecture details
- [`database/README.md`](database/README.md) - Database setup and configuration
- [`hardware/README.md`](hardware/README.md) - ESP32 hardware integration
- [`docs/README.md`](docs/README.md) - Additional documentation