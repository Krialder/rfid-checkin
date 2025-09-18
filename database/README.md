# Database Directory - Modern Integration

This directory contains database setup scripts, diagnostic tools, and maintenance utilities for the RFID Check-in System, now integrated with the modern DatabaseService architecture.

## 📁 Directory Structure

```
database/
├── setup-database.php        # Complete database setup and initialization
├── validate-database.php     # Database structure validation and repair
├── diagnose.php              # Database diagnostic and troubleshooting
├── check-accesslogs.php      # Access log validation and analysis
└── fix-accesslogs.php        # Access log repair and maintenance
```

## 🏗️ Modern Database Architecture

### DatabaseService Integration

The database layer now uses the modern **DatabaseService** class instead of legacy global functions:

**Modern Approach:**
```php
use RfidCheckin\Services\{DatabaseService, ConfigurationService, LoggingService};

// Service-based database access
$config = new ConfigurationService();
$database = new DatabaseService($config);
$logger = new LoggingService($config);

// Connection with pooling and monitoring
$connection = $database->getConnection();
$result = $database->query('SELECT * FROM users WHERE active = ?', [1]);
```

**Legacy Approach (Deprecated):**
```php
// Old global function approach (no longer used)
require_once 'core/database.php';
$db = getDB(); // Global function - replaced by DatabaseService
```

### Service Features

**DatabaseService Benefits:**
- **Connection Pooling**: Efficient connection reuse and management
- **Query Performance Monitoring**: Automatic slow query detection and logging
- **Prepared Statement Management**: Enhanced security and performance
- **Transaction Support**: Comprehensive transaction handling with rollback
- **Error Handling**: Structured exception handling with logging integration
- **Configuration Integration**: Environment-based database configuration

### Environment Configuration

Database settings are now managed through environment variables:

```env
# .env file configuration
DB_HOST=localhost
DB_NAME=rfid_checkin
DB_USER=rfid_user
DB_PASS=secure_password
DB_PORT=3306
DB_CHARSET=utf8mb4

# Connection pool settings
DB_POOL_SIZE=10
DB_CONNECTION_TIMEOUT=30
DB_QUERY_TIMEOUT=60
```

**Configuration Loading:**
```php
$config = new ConfigurationService();
$host = $config->get('DB_HOST', 'localhost');
$name = $config->get('DB_NAME', 'rfid_checkin');
```

## 🗄️ Database Schema Overview

The RFID Check-in System uses a comprehensive MySQL database schema designed for high performance, scalability, and data integrity, now optimized for the modern service architecture.

### Core Tables

**Users Management:**
- `users` - User accounts and profiles with RBAC support
- `password_resets` - Password reset tokens and tracking
- `usergroupmemberships` - User-to-group relationship mapping
- `usergroups` - Department and team organization

**Event Management:**
- `events` - Event definitions with recurring support
- `eventinstances` - Individual occurrences of recurring events
- `eventregistration` - User event registrations
- `eventgroupassignments` - Group-to-event assignments
- `holidays` - Holiday calendar integration

**Check-in System:**
- `checkin` - Check-in/check-out records with timing analytics
- `rfiddevices` - RFID hardware device management
- `rfid_scan_queue` - Real-time RFID scan processing queue

**System Management:**
- `system_settings` - Application configuration storage
- `notifications` - User notification system
- `reports` - Generated report management
- `activitylog` - User activity audit trail
- `accesslogs` - System access and security logging

### Advanced Features

**DatabaseService Integration:**
```php
// Modern repository pattern with DatabaseService
class UserRepository {
    private DatabaseService $database;
    private LoggingService $logger;
    
    public function findById(int $id): ?array {
        return $this->database->query(
            'SELECT * FROM users WHERE user_id = ? AND is_active = 1',
            [$id]
        )->fetch();
    }
    
    public function createUser(array $data): int {
        return $this->database->transaction(function() use ($data) {
            $stmt = $this->database->query(
                'INSERT INTO users (username, email, password_hash, created_at) VALUES (?, ?, ?, NOW())',
                [$data['username'], $data['email'], $data['password_hash']]
            );
            return $this->database->getConnection()->lastInsertId();
        });
    }
}
```

**Performance Monitoring:**
```php
// Automatic query performance tracking
$database = new DatabaseService($config);

// Slow queries are automatically logged
$result = $database->query('SELECT * FROM users u JOIN checkin c ON u.user_id = c.user_id');

// Performance metrics available
$queryCount = $database->getQueryCount();
$slowQueries = $database->getSlowQueries();
```

**Recurring Events Support:**
```sql
-- Events table supports complex recurring patterns
CREATE TABLE events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    is_recurring BOOLEAN DEFAULT FALSE,
    recurrence_type ENUM('daily', 'weekly', 'monthly', 'yearly', 'custom'),
    recurrence_interval INT DEFAULT 1,
    recurrence_days JSON, -- [1,2,3,4,5] for weekdays
    recurrence_end_date DATE,
    exclude_holidays BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_recurring_events (is_recurring, recurrence_type, active)
);
```

**Break/Pause Tracking:**
```sql
-- Check-in table includes break management
CREATE TABLE checkin (
    checkin_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id INT,
    checkin_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    checkout_time TIMESTAMP NULL,
    break_checkins JSON, -- Track break check-ins/outs
    total_break_minutes INT DEFAULT 0,
    duration_minutes INT GENERATED ALWAYS AS (
        CASE 
            WHEN checkout_time IS NULL THEN NULL
            ELSE TIMESTAMPDIFF(MINUTE, checkin_time, checkout_time) - total_break_minutes
        END
    ) STORED,
    status ENUM('checked_in', 'checked_out', 'on_break') DEFAULT 'checked_in',
    method ENUM('rfid', 'manual', 'api') DEFAULT 'rfid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (event_id) REFERENCES events(event_id),
    INDEX idx_checkin_user_time (user_id, checkin_time DESC),
    INDEX idx_checkin_status (status, checkin_time)
);
```

## 🚀 Database Setup with Modern Services

### Initial Installation

The database setup now integrates with the modern service architecture:

```bash
# Via web browser (recommended)
http://your-domain.com/database/setup-database.php

# Via command line with service integration
cd database/
php setup-database.php
```

### Modern Setup Process

The setup script now uses the modern service architecture:

```php
// setup-database.php now uses modern services
use RfidCheckin\Services\{ConfigurationService, DatabaseService, LoggingService};

$config = new ConfigurationService();
$database = new DatabaseService($config);
$logger = new LoggingService($config);

// Database setup with service integration
try {
    $database->createDatabase();
    $database->createTables();
    $database->insertDefaultData();
    $database->optimizeStructure();
    
    $logger->info('Database setup completed successfully');
} catch (Exception $e) {
    $logger->error('Database setup failed', ['error' => $e->getMessage()]);
    throw $e;
}
```

The setup script performs:

1. **Environment Validation**: Validates configuration through ConfigurationService
2. **Database Creation**: Creates the database if it doesn't exist
3. **Table Structure**: Creates all tables with proper indexes and constraints
4. **Default Data**: Inserts system settings and sample data
5. **User Creation**: Creates default admin account
6. **Service Integration**: Configures services for optimal performance
7. **Validation**: Verifies installation integrity through DatabaseService

### Setup Features

**Modern Service Integration:**
- ConfigurationService for environment-based settings
- DatabaseService for connection management and query optimization
- LoggingService for comprehensive setup logging
- ErrorHandler for graceful error management
- Performance monitoring during setup process

**Comprehensive Table Creation:**
- All 15+ core tables with proper relationships
- Foreign key constraints for data integrity
- Optimized indexes for DatabaseService performance
- Generated columns for calculated fields
- JSON fields for flexible configuration storage

**Default Data Insertion:**
- System configuration settings loaded via ConfigurationService
- Holiday calendar (German holidays included)
- Sample user groups and events
- Default RFID device configuration
- Admin user account (username: `admin`, password: `admin123`)

**Performance Optimization:**
- Strategic database indexes optimized for DatabaseService queries
- Database views for common repository patterns
- Query optimization recommendations
- Connection pooling configuration for DatabaseService

### Post-Installation Steps

After running the setup script:

1. **Update Environment Configuration**: Configure `.env` file with proper database credentials
2. **Change Default Password**: Update the admin account password via modern AuthenticationService
3. **Configure Services**: Update system settings via ConfigurationService
4. **Add Users**: Create user accounts using modern UserRepository
5. **Create Events**: Set up events using EventService
6. **Test Hardware**: Verify RFID device connectivity through modern hardware services

## 🔍 Database Diagnostics with Modern Services

### Diagnostic Tools

**diagnose.php** - Enhanced health check with service integration:

```bash
# Run diagnostic check
http://your-domain.com/database/diagnose.php

# Command line usage
php diagnose.php
```

**Modern Diagnostic Features:**
- DatabaseService connectivity verification
- ConfigurationService validation
- Table existence checking through repository patterns
- Service integration health checks
- Performance metrics from DatabaseService
- LoggingService functionality validation

**Sample Output:**
```
=== DATABASE DIAGNOSTIC ===

✓ ConfigurationService: Environment variables loaded
✓ DatabaseService: Connection pool initialized
✓ LoggingService: Structured logging active

Database Connection:
✓ Host: localhost (via ConfigurationService)
✓ Database: rfid_checkin (connection pool: 5/10 active)
✓ User: rfid_user (permissions verified)

Service Integration:
✓ DatabaseService query monitoring: Active
✓ Transaction support: Available
✓ Connection pooling: 5 connections active
✓ Query performance logging: Enabled

EXISTING TABLES:
✓ users (5 records, 3 indexes optimized)
✓ events (3 records, recurring events support)
✓ checkin (0 records, break tracking ready)
✓ rfiddevices (1 record, hardware integration active)
✓ system_settings (15 records, service configuration ready)

REPOSITORY INTEGRATION:
✓ UserRepository: Database service integration complete
✓ EventRepository: Ready for modern service calls
✓ CheckinRepository: Break tracking and monitoring active

Database structure is complete and service-ready!
Query Performance: Average 2.3ms, 0 slow queries detected
```

### Validation and Repair

**validate-database.php** - Enhanced validation with service monitoring:

```bash
# Run validation and repair with service integration
http://your-domain.com/database/validate-database.php
```

**Modern Validation Features:**
- **Service Integration Validation**: Verify all services can access database correctly
- **Repository Pattern Validation**: Test repository classes with DatabaseService
- **Performance Monitoring**: Validate query performance through DatabaseService
- **Configuration Validation**: Ensure environment variables are properly configured
- **Automatic Service Repair**: Fix service integration issues automatically
- **Comprehensive Service Reporting**: Generate reports on service performance

**Validation Categories:**

1. **Service Integration**:
   - DatabaseService connection pooling functionality
   - ConfigurationService database configuration loading
   - LoggingService database query logging
   - Repository pattern integration with services
   - Transaction support through DatabaseService

2. **Modern Architecture Compatibility**:
   - Environment variable configuration validation
   - Service dependency validation
   - Performance monitoring integration
   - Error handling service integration
   - Logging service database integration

3. **Performance with Services**:
   - DatabaseService query optimization validation
   - Connection pool efficiency testing
   - Service-level caching verification
   - Query performance monitoring accuracy
   - Service integration overhead analysis

### Access Log Management

**check-accesslogs.php** - Modern log analysis with LoggingService:

```bash
# Analyze access logs with service integration
http://your-domain.com/database/check-accesslogs.php
```

**Enhanced Analysis Features:**
- LoggingService integration for structured log analysis
- Service-level security event correlation
- Performance impact analysis on DatabaseService
- Modern authentication service log integration
- Real-time monitoring through service metrics

**fix-accesslogs.php** - Service-integrated maintenance:

```bash
# Clean and optimize with service coordination
http://your-domain.com/database/fix-accesslogs.php
```

**Modern Maintenance Features:**
- LoggingService coordinated log rotation
- DatabaseService performance optimization during maintenance
- Service-aware data archival
- Statistical summarization through modern services
- Integration with PerformanceMonitoring service

## 📊 Database Views

The system includes optimized database views for common queries:

### Active Users View
```sql
CREATE VIEW view_active_users AS
SELECT 
    user_id, 
    CONCAT(first_name, ' ', COALESCE(last_name, '')) as full_name,
    username, email, role, department,
    last_login, created_at, rfid_tag
FROM users 
WHERE is_active = TRUE;
```

### Current Events View
```sql
CREATE VIEW view_current_events AS
SELECT 
    e.*, 
    CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as created_by_name,
    (SELECT COUNT(*) FROM checkin c 
     WHERE c.event_id = e.event_id AND c.status = 'checked_in') as current_checkins
FROM events e
LEFT JOIN users u ON e.created_by = u.user_id
WHERE e.active = TRUE;
```

### Today's Events View
```sql
CREATE VIEW view_today_events AS
SELECT 
    e.event_id, e.name, e.location, e.start_time, e.end_time,
    ei.instance_id, ei.start_datetime, ei.end_datetime,
    ei.status as instance_status
FROM events e
LEFT JOIN eventinstances ei ON e.event_id = ei.parent_event_id 
    AND ei.instance_date = CURDATE()
WHERE e.active = TRUE 
AND (
    (e.is_recurring = 0 AND DATE(e.start_date) = CURDATE())
    OR 
    (e.is_recurring = 1 AND ei.instance_id IS NOT NULL)
)
ORDER BY COALESCE(ei.start_datetime, TIMESTAMP(e.start_date, e.start_time));
```

## ⚡ Performance Optimization with Modern Services

### DatabaseService Performance Features

The DatabaseService provides built-in performance optimization:

```php
// Modern performance optimization
$database = new DatabaseService($config);

// Automatic connection pooling
$connection = $database->getConnection(); // Reuses existing connections

// Query performance monitoring
$result = $database->query('SELECT * FROM users WHERE role = ?', ['admin']);
// Automatically logs slow queries and performance metrics

// Transaction optimization
$userId = $database->transaction(function() use ($userData) {
    $stmt = $this->query('INSERT INTO users (username, email) VALUES (?, ?)', 
                        [$userData['username'], $userData['email']]);
    return $this->getConnection()->lastInsertId();
});

// Performance metrics
$queryCount = $database->getQueryCount();
$slowQueries = $database->getSlowQueries();
$avgQueryTime = $database->getAverageQueryTime();
```

### Strategic Database Indexes

Indexes optimized for DatabaseService and repository patterns:

```sql
-- User lookup optimization (for AuthenticationService)
CREATE INDEX idx_users_username_active ON users(username, is_active);
CREATE INDEX idx_users_email_active ON users(email, is_active);
CREATE INDEX idx_users_rfid_active ON users(rfid_tag, is_active);

-- Check-in performance (for CheckinRepository)
CREATE INDEX idx_checkin_user_status ON checkin(user_id, status, checkin_time DESC);
CREATE INDEX idx_checkin_event_time ON checkin(event_id, checkin_time DESC);
CREATE INDEX idx_checkin_method_time ON checkin(method, checkin_time DESC);

-- Event queries (for EventRepository)
CREATE INDEX idx_events_active_time ON events(active, start_time, end_time);
CREATE INDEX idx_events_recurring ON events(is_recurring, recurrence_type, active);

-- System logging (for LoggingService integration)
CREATE INDEX idx_accesslog_time_level ON accesslogs(timestamp DESC, log_level);
CREATE INDEX idx_accesslog_user_action ON accesslogs(user_id, action, timestamp DESC);
```

### Query Optimization with Services

**Repository Pattern with DatabaseService:**
```php
class UserRepository {
    private DatabaseService $database;
    
    public function findActiveUsers(): array {
        // Optimized query with proper indexing
        return $this->database->query(
            'SELECT user_id, username, email, role 
             FROM users 
             WHERE is_active = 1 
             ORDER BY last_login DESC 
             LIMIT 100'
        )->fetchAll();
    }
    
    public function findByRfidTag(string $rfidTag): ?array {
        // Uses idx_users_rfid_active index
        return $this->database->query(
            'SELECT * FROM users 
             WHERE rfid_tag = ? AND is_active = 1',
            [$rfidTag]
        )->fetch();
    }
}
```

**Connection Pool Optimization:**
```php
// DatabaseService connection pool configuration
$config = new ConfigurationService();
$config->set('DB_POOL_SIZE', 15);        // Maximum connections
$config->set('DB_POOL_MIN', 5);          // Minimum connections
$config->set('DB_CONNECTION_TIMEOUT', 30); // Connection timeout
$config->set('DB_QUERY_TIMEOUT', 60);    // Query timeout

$database = new DatabaseService($config);
// Connection pool automatically manages connections
```

## 🔧 Maintenance Scripts

### Automated Maintenance

**Daily Maintenance Tasks:**
```sql
-- Optimize tables
OPTIMIZE TABLE users, events, checkin, accesslogs;

-- Update table statistics
ANALYZE TABLE users, events, checkin;

-- Clean old sessions
DELETE FROM php_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR);
```

**Weekly Maintenance Tasks:**
```sql
-- Archive old access logs (older than 90 days)
INSERT INTO accesslogs_archive 
SELECT * FROM accesslogs 
WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY);

DELETE FROM accesslogs 
WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Check and repair tables
CHECK TABLE users, events, checkin, accesslogs;
REPAIR TABLE users, events, checkin, accesslogs;
```

### Backup Strategy

**Database Backup Script:**
```bash
#!/bin/bash
# backup-database.sh

DB_NAME="rfid_checkin_system"
DB_USER="backup_user"
DB_PASS="backup_password"
BACKUP_DIR="/backups/database"
DATE=$(date +%Y%m%d_%H%M%S)

# Full database backup
mysqldump -u $DB_USER -p$DB_PASS \
    --single-transaction \
    --routines \
    --triggers \
    $DB_NAME > $BACKUP_DIR/rfid_checkin_$DATE.sql

# Compress backup
gzip $BACKUP_DIR/rfid_checkin_$DATE.sql

# Clean old backups (keep last 30 days)
find $BACKUP_DIR -name "rfid_checkin_*.sql.gz" -mtime +30 -delete
```

**Restoration Script:**
```bash
#!/bin/bash
# restore-database.sh

BACKUP_FILE=$1
DB_NAME="rfid_checkin_system"
DB_USER="restore_user"
DB_PASS="restore_password"

if [ -z "$BACKUP_FILE" ]; then
    echo "Usage: $0 <backup_file.sql.gz>"
    exit 1
fi

# Restore database
gunzip -c $BACKUP_FILE | mysql -u $DB_USER -p$DB_PASS $DB_NAME
```

## 🚨 Troubleshooting

### Common Issues

**Connection Problems:**
```bash
# Test MySQL connection
mysql -u username -p -h localhost

# Check MySQL service status
systemctl status mysql

# Review MySQL error log
tail -f /var/log/mysql/error.log
```

**Permission Issues:**
```sql
-- Grant necessary permissions
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER 
ON rfid_checkin_system.* TO 'rfid_user'@'localhost';

FLUSH PRIVILEGES;
```

**Performance Issues:**
```sql
-- Check running processes
SHOW PROCESSLIST;

-- Analyze table sizes
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS "Size (MB)"
FROM information_schema.TABLES 
WHERE table_schema = 'rfid_checkin_system'
ORDER BY (data_length + index_length) DESC;
```

**Data Corruption:**
```sql
-- Check table integrity
CHECK TABLE users, events, checkin;

-- Repair corrupted tables
REPAIR TABLE table_name;

-- Rebuild indexes
ALTER TABLE table_name ENGINE=InnoDB;
```

### Error Codes

**Common MySQL Errors:**
- **1045**: Access denied - Check username/password
- **1049**: Unknown database - Database doesn't exist
- **1146**: Table doesn't exist - Run setup script
- **1062**: Duplicate entry - Check unique constraints
- **1054**: Unknown column - Check table structure

### Recovery Procedures

**Database Recovery Steps:**
1. **Stop Application**: Prevent further data modification
2. **Assess Damage**: Run diagnostic scripts
3. **Restore from Backup**: Use most recent clean backup
4. **Validate Data**: Run validation scripts
5. **Test Functionality**: Verify system operation
6. **Resume Service**: Bring application back online

## 📈 Monitoring and Alerts

### Database Monitoring

**Key Metrics to Monitor:**
- Connection count and availability
- Query execution time and frequency
- Table sizes and growth rates
- Index usage and efficiency
- Error rates and types
- Backup success and timing

**Monitoring Queries:**
```sql
-- Connection monitoring
SHOW STATUS LIKE 'Threads_connected';
SHOW STATUS LIKE 'Max_used_connections';

-- Query performance
SHOW STATUS LIKE 'Slow_queries';
SHOW STATUS LIKE 'Questions';

-- Table statistics
SHOW TABLE STATUS FROM rfid_checkin_system;
```

### Automated Alerts

Set up alerts for:
- High connection count (>80% of max)
- Slow query threshold exceeded
- Database size approaching limits
- Backup failures
- Critical errors in error log

## 🔗 Modern Service Integration

### Application Integration

**Modern Service Architecture:**
- `src/Services/DatabaseService.php` - Primary database connection and query management
- `src/Services/ConfigurationService.php` - Environment-based database configuration
- `src/Services/LoggingService.php` - Database query and performance logging
- `src/Repositories/` - Data access layer using DatabaseService
- `src/Services/ErrorHandler.php` - Database error handling and recovery

**Service Integration Pattern:**
```php
// Modern service integration
use RfidCheckin\Services\{
    ConfigurationService,
    DatabaseService,
    LoggingService,
    ErrorHandler
};

// Service initialization
$config = new ConfigurationService();
$logger = new LoggingService($config);
$database = new DatabaseService($config, $logger);
$errorHandler = new ErrorHandler($config, $logger);

// Repository pattern with service injection
$userRepository = new UserRepository($database, $logger);
$eventRepository = new EventRepository($database, $logger);
```

### API Integration

**RESTful API with Modern Services:**
```php
// API controller using modern database services
class ApiController {
    private DatabaseService $database;
    private LoggingService $logger;
    private AuthenticationService $auth;
    
    public function getUserData(int $userId): array {
        try {
            $user = $this->database->query(
                'SELECT * FROM users WHERE user_id = ? AND is_active = 1',
                [$userId]
            )->fetch();
            
            if (!$user) {
                throw new UserNotFoundException("User {$userId} not found");
            }
            
            $this->logger->info('User data retrieved', ['user_id' => $userId]);
            return $user;
            
        } catch (Exception $e) {
            $this->logger->error('User retrieval failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
```

### Legacy Integration

**Transition Support:**
The database utilities maintain compatibility with legacy code while providing modern service integration:

```php
// Legacy support (temporary during transition)
if (class_exists('DatabaseService')) {
    // Use modern service
    $database = new DatabaseService($config);
    $result = $database->query($sql, $params);
} else {
    // Fallback to legacy function (deprecated)
    $db = getDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $result = $stmt;
}
```

### Performance Monitoring Integration

**Service-Level Monitoring:**
```php
// DatabaseService provides built-in monitoring
$database = new DatabaseService($config);

// Query performance tracking
$startTime = microtime(true);
$result = $database->query('SELECT COUNT(*) FROM users');
$queryTime = microtime(true) - $startTime;

// Automatic slow query detection
if ($queryTime > 0.1) {
    $logger->warning('Slow query detected', [
        'query_time' => $queryTime,
        'sql' => 'SELECT COUNT(*) FROM users',
        'threshold' => 0.1
    ]);
}

// Performance metrics
$metrics = [
    'total_queries' => $database->getQueryCount(),
    'avg_query_time' => $database->getAverageQueryTime(),
    'slow_queries' => count($database->getSlowQueries()),
    'active_connections' => $database->getActiveConnectionCount()
];
```

## 🧪 Testing with Modern Services

### Service-Integrated Testing

**Database testing with service mocking:**
```php
class DatabaseServiceTest extends TestCase {
    private DatabaseService $database;
    private ConfigurationService $config;
    
    protected function setUp(): void {
        $this->config = new ConfigurationService();
        $this->config->set('DB_NAME', 'rfid_checkin_test');
        
        $this->database = new DatabaseService($this->config);
        $this->database->beginTransaction();
    }
    
    protected function tearDown(): void {
        $this->database->rollBack();
    }
    
    public function testUserCreation(): void {
        $userData = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password_hash' => password_hash('password', PASSWORD_DEFAULT)
        ];
        
        $userId = $this->database->transaction(function() use ($userData) {
            $stmt = $this->database->query(
                'INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)',
                [$userData['username'], $userData['email'], $userData['password_hash']]
            );
            return $this->database->getConnection()->lastInsertId();
        });
        
        $this->assertIsInt($userId);
        $this->assertGreaterThan(0, $userId);
    }
}
```

**Repository Testing:**
```php
class UserRepositoryTest extends TestCase {
    private UserRepository $userRepository;
    private DatabaseService $database;
    
    protected function setUp(): void {
        $config = new ConfigurationService();
        $logger = new LoggingService($config);
        $this->database = new DatabaseService($config, $logger);
        $this->userRepository = new UserRepository($this->database, $logger);
        
        $this->database->beginTransaction();
    }
    
    public function testFindActiveUsers(): void {
        $activeUsers = $this->userRepository->findActiveUsers();
        
        $this->assertIsArray($activeUsers);
        foreach ($activeUsers as $user) {
            $this->assertEquals(1, $user['is_active']);
        }
    }
}
```

---

**Database System**: Modern Service Integration Complete  
**Last Updated**: January 2025  
**Service Architecture**: ConfigurationService + DatabaseService + LoggingService  
**Compatibility**: MySQL 8.0+, MariaDB 10.5+  
**Migration Status**: Legacy Support Maintained