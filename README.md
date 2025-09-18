# RFID Check-in System

A RFID-based attendance and check-in system built with PHP, featuring ESP32 hardware integration, modern web interfaces, and robust data management capabilities.

![Version](https://img.shields.io/badge/version-3.0.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4.svg)
![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [System Architecture](#system-architecture)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Hardware Setup](#hardware-setup)
- [Usage](#usage)
- [API Documentation](#api-documentation)
- [Testing](#testing)
- [Performance](#performance)
- [Security](#security)
- [Contributing](#contributing)
- [Troubleshooting](#troubleshooting)
- [License](#license)

## 🎯 Overview

The RFID Check-in System is a attendance management solution designed for organizations that need reliable, scalable, and secure check-in/check-out functionality. The system combines RFID hardware integration with a modern web interface to provide seamless user experiences for both administrators and end-users.

### Key Capabilities

- **RFID Hardware Integration**: Native ESP32 RFID reader support with real-time communication
- **Dual Architecture**: Modern MVC architecture alongside legacy code for migration flexibility
- **Enterprise Features**: User management, event scheduling, reporting, and analytics
- **Security-First**: Comprehensive security measures including CSRF protection, input validation, and secure authentication
- **Performance Optimized**: Built-in caching, query optimization, and asset compression
- **Mobile Responsive**: Full mobile support with progressive web app capabilities

## ✨ Features

### Core Functionality
- ✅ **RFID Check-in/Check-out**: Hardware-based attendance tracking
- ✅ **Event Management**: Create, schedule, and manage events with recurring support
- ✅ **User Management**: Role-based access control (Admin, User, Moderator)
- ✅ **Real-time Dashboard**: Live attendance monitoring and analytics
- ✅ **Mobile Support**: Responsive design with mobile check-in capabilities
- ✅ **Reporting System**: Comprehensive attendance reports and analytics

### Advanced Features
- 🔄 **Recurring Events**: Support for daily, weekly, monthly recurring events
- 🏢 **User Groups**: Department and team-based organization
- 📱 **Mobile Check-in**: Alternative check-in methods for mobile devices
- 🔔 **Notifications**: Real-time system notifications and alerts
- 📊 **Performance Analytics**: System performance monitoring and optimization
- 🛡️ **Security Monitoring**: Comprehensive security logging and protection

### Hardware Integration
- 🏷️ **ESP32 RFID Readers**: Native support for RC522 RFID modules
- 📡 **Real-time Communication**: Instant check-in processing
- 🔧 **Device Management**: Monitor and configure RFID devices remotely
- 🚨 **Registration Mode**: Dynamic RFID tag registration system

## 🏗️ System Architecture

The system uses a hybrid architecture combining modern and legacy approaches:

```
RFID-Checking/
├── 🆕 src/                     # Modern MVC Architecture
│   ├── Application.php         # Application Bootstrap
│   ├── Controllers/            # Request Controllers
│   ├── Models/                 # Data Models
│   ├── Services/               # Business Logic
│   ├── Repositories/           # Data Access Layer
│   ├── Middleware/             # Request Middleware
│   ├── Routing/                # URL Routing
│   └── Views/                  # Template Views
├── 🔧 core/                    # Core Application Logic
│   ├── config.php              # Configuration Management
│   ├── auth.php                # Authentication System
│   ├── database.php            # Database Layer
│   ├── PerformanceManager.php  # Performance Optimization
│   ├── SecurityManager.php     # Security Features
│   └── repositories/           # Data Repositories
├── 📚 legacy/                  # Legacy Code Structure
│   ├── admin/                  # Administrative Interface
│   ├── api/                    # API Endpoints
│   ├── auth/                   # Authentication Pages
│   └── frontend/               # User Interface
├── 🎨 assets/                  # Frontend Resources
│   ├── css/                    # Stylesheets
│   └── js/                     # JavaScript Files
├── 🔌 hardware/                # ESP32 Integration
│   ├── ESP32-RFID-Reader.ino   # Arduino Firmware
│   └── config-example.h        # Hardware Configuration
├── 🗄️ database/                # Database Management
│   ├── setup-database.php      # Database Setup Script
│   └── diagnostic tools        # Maintenance Scripts
└── 🧪 tests/                   # Testing Framework
    ├── TestFramework.php       # Test Suite
    └── test suites             # Unit/Integration Tests
```

### Architecture Patterns

- **Repository Pattern**: Abstracted data access layer
- **Service Layer**: Business logic separation
- **MVC Architecture**: Clean separation of concerns
- **Middleware Pattern**: Request/response processing
- **Singleton Pattern**: Shared resource management

## 📋 Requirements

### System Requirements
- **PHP**: 8.0 or higher
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Database**: MySQL 8.0+ or MariaDB 10.5+
- **Memory**: 512MB RAM minimum (2GB recommended)
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

## 🚀 Installation

### 1. Clone Repository

```bash
git clone https://github.com/your-org/rfid-checking.git
cd rfid-checking
```

### 2. Install Dependencies

```bash
# Install PHP dependencies (if using Composer)
composer install

# Set proper permissions
chmod -R 755 .
chmod -R 777 cache/ logs/ uploads/
```

### 3. Configure Web Server

#### Apache Configuration
```apache
<VirtualHost *:80>
    ServerName rfid-checkin.local
    DocumentRoot /path/to/rfid-checking
    
    <Directory /path/to/rfid-checking>
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
    root /path/to/rfid-checking;
    index index.php index.html;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 4. Database Setup

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

### 5. Configuration

```bash
# Copy configuration template
cp core/config.template.php core/config.php

# Edit configuration with your settings
nano core/config.php
```

## ⚙️ Configuration

### Database Configuration

Edit `core/config.php`:

```php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'rfid_checkin_system');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_secure_password');
define('DB_CHARSET', 'utf8mb4');
```

### Application Settings

```php
// Application Configuration
define('APP_NAME', 'Your Organization Check-in System');
define('BASE_URL', 'http://your-domain.com/rfid-checkin');
define('DEBUG_MODE', false); // Set to false in production

// Security Configuration
define('SESSION_LIFETIME', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 8);
```

### System Settings

Access the admin panel to configure:

- **Company Information**: Name, logo, contact details
- **RFID Settings**: Device configuration, registration mode
- **Security Settings**: Login attempts, session timeout
- **Event Settings**: Default durations, recurring patterns
- **Notification Settings**: Email alerts, system notifications

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

### Mobile Access

The system is fully responsive and supports:
- Mobile web browsers
- Progressive web app features
- QR code check-in (alternative to RFID)
- Touch-optimized interface

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
  "timestamp": "2025-01-15T09:00:00Z"
}
```

#### Dashboard Data
```http
GET /api/dashboard.php
Authorization: Bearer {session_token}
```

#### Event Details
```http
GET /api/event-details.php?event_id=1
Authorization: Bearer {session_token}
```

### Authentication

All API endpoints (except RFID check-in) require authentication:

```http
POST /auth/login-process.php
Content-Type: application/x-www-form-urlencoded

username=admin&password=admin123
```

### Error Handling

API responses include standardized error codes:

```json
{
  "success": false,
  "error": "User not found",
  "code": "USER_NOT_FOUND",
  "timestamp": "2025-01-15T09:00:00Z"
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

## ⚡ Performance

### Optimization Features

- **Query Optimization**: Automated query analysis and recommendations
- **Caching System**: Multi-layer caching with Redis/APCu support
- **Asset Optimization**: CSS/JS minification and compression
- **Database Indexing**: Optimized indexes for common queries
- **Connection Pooling**: Efficient database connection management

### Performance Metrics

- **Page Load Time**: < 200ms (cached)
- **API Response Time**: < 100ms average
- **RFID Processing**: < 500ms end-to-end
- **Database Queries**: < 50ms average
- **Memory Usage**: < 64MB per request

### Monitoring

Access performance dashboard at:
```
http://your-domain.com/admin/performance.php
```

Features:
- Real-time performance metrics
- Query analysis and optimization suggestions
- System resource monitoring
- Error tracking and alerts

## 🛡️ Security

### Security Features

- **Authentication**: Secure session management with CSRF protection
- **Password Security**: bcrypt hashing with salt
- **Input Validation**: Comprehensive input sanitization
- **SQL Injection Protection**: Prepared statements throughout
- **Rate Limiting**: Login attempt limiting and IP blocking
- **Security Headers**: XSS protection, content security policy

### Security Configuration

```php
// Security Settings in config.php
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes
define('SESSION_SECURE', true);   // HTTPS only
define('CSRF_PROTECTION', true);
```

### Security Monitoring

- **Access Logging**: All user actions logged
- **Security Events**: Failed logins, suspicious activity
- **Audit Trail**: Complete change history
- **Alert System**: Real-time security notifications

### Best Practices

1. **Regular Updates**: Keep system and dependencies updated
2. **Strong Passwords**: Enforce password complexity requirements
3. **HTTPS**: Always use SSL/TLS in production
4. **Backup Strategy**: Regular database and file backups
5. **Access Control**: Implement principle of least privilege

## 🤝 Contributing

### Development Setup

1. Fork the repository
2. Create feature branch: `git checkout -b feature/amazing-feature`
3. Install development dependencies: `composer install --dev`
4. Run tests: `php tests/run-tests.php`
5. Make changes and commit: `git commit -m 'Add amazing feature'`
6. Push to branch: `git push origin feature/amazing-feature`
7. Open a Pull Request

### Code Standards

- Follow PSR-12 coding standards
- Include comprehensive tests for new features
- Update documentation for API changes
- Use meaningful commit messages

### Testing Requirements

All contributions must include:
- Unit tests for new functionality
- Integration tests for API changes
- Security tests for authentication/authorization changes
- Performance tests for database queries

## 🔧 Troubleshooting

### Common Issues

#### Database Connection Failed
```bash
# Check database server status
systemctl status mysql

# Verify credentials in config.php
# Test connection manually
mysql -u username -p database_name
```

#### RFID Hardware Not Responding
```bash
# Check ESP32 serial monitor
# Verify WiFi connection
# Test API endpoints manually
curl -X POST http://your-domain.com/api/rfid-checkin.php \
     -d "rfid=TEST1234&device_id=READER_001"
```

#### Performance Issues
```bash
# Enable query logging
# Check server resources
# Run performance tests
php tests/run-tests.php --suite=performance
```

### Log Files

System logs are located in:
- Application logs: `logs/application.log`
- Error logs: `logs/error.log`
- Access logs: `logs/access.log`
- Security logs: `logs/security.log`

### Support

For technical support:
1. Check the troubleshooting guide
2. Review system logs
3. Run diagnostic tests
4. Check GitHub issues
5. Contact system administrator

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **RC522 Library**: MFRC522 Arduino library contributors
- **Bootstrap**: Frontend framework
- **Chart.js**: Data visualization library
- **Font Awesome**: Icon library
- **PHP Community**: Ongoing support and development

---

**Project Status**: Production Ready  
**Last Updated**: January 2025  
**Version**: 3.0.0

For more detailed documentation, visit the individual module README files in each directory.