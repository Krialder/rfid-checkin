# Database Directory

This directory contains database setup scripts, diagnostic tools, and maintenance utilities for the RFID Check-in System.

## 📁 Directory Structure

```
database/
├── setup-database.php        # Complete database setup and initialization
├── validate-database.php     # Database structure validation and repair
├── diagnose.php              # Database diagnostic and troubleshooting
├── check-accesslogs.php      # Access log validation and analysis
└── fix-accesslogs.php        # Access log repair and maintenance
```

## 🗄️ Database Schema Overview

The RFID Check-in System uses a comprehensive MySQL database schema designed for high performance, scalability, and data integrity.

### Core Tables

**Users Management:**
- `users` - User accounts and profiles
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
    -- ... additional fields
);
```

**Break/Pause Tracking:**
```sql
-- Check-in table includes break management
CREATE TABLE checkin (
    checkin_id INT AUTO_INCREMENT PRIMARY KEY,
    break_checkins JSON, -- Track break check-ins/outs
    total_break_minutes INT DEFAULT 0,
    duration_minutes INT GENERATED ALWAYS AS (
        TIMESTAMPDIFF(MINUTE, checkin_time, checkout_time) - total_break_minutes
    ) STORED,
    -- ... additional fields
);
```

**Holiday Integration:**
```sql
-- Comprehensive holiday management
CREATE TABLE holidays (
    holiday_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    date DATE NOT NULL,
    type ENUM('national', 'regional', 'religious', 'custom'),
    state_codes JSON, -- Regional holiday support
    -- ... additional fields
);
```

## 🚀 Database Setup

### Initial Installation

Run the complete database setup script:

```bash
# Via web browser (recommended)
http://your-domain.com/database/setup-database.php

# Via command line
cd database/
php setup-database.php
```

The setup script performs:

1. **Database Creation**: Creates the database if it doesn't exist
2. **Table Structure**: Creates all tables with proper indexes and constraints
3. **Default Data**: Inserts system settings and sample data
4. **User Creation**: Creates default admin account
5. **Optimization**: Applies performance indexes and views
6. **Validation**: Verifies installation integrity

### Setup Features

**Comprehensive Table Creation:**
- All 15+ core tables with proper relationships
- Foreign key constraints for data integrity
- Optimized indexes for query performance
- Generated columns for calculated fields
- JSON fields for flexible configuration storage

**Default Data Insertion:**
- System configuration settings
- Holiday calendar (German holidays included)
- Sample user groups and events
- Default RFID device configuration
- Admin user account (username: `admin`, password: `admin123`)

**Performance Optimization:**
- Strategic database indexes
- Database views for common queries
- Query optimization recommendations
- Connection pooling configuration

### Post-Installation Steps

After running the setup script:

1. **Change Default Password**: Update the admin account password
2. **Configure Settings**: Update system settings via admin panel
3. **Add Users**: Create user accounts and assign RFID tags
4. **Create Events**: Set up events and schedules
5. **Test Hardware**: Verify RFID device connectivity

## 🔍 Database Diagnostics

### Diagnostic Tools

**diagnose.php** - Basic database health check:

```bash
# Run diagnostic check
http://your-domain.com/database/diagnose.php

# Command line usage
php diagnose.php
```

**Diagnostic Features:**
- Database connectivity verification
- Table existence checking
- Record count analysis
- Missing table identification
- Configuration validation

**Sample Output:**
```
=== DATABASE DIAGNOSTIC ===

✓ Database connection successful
Host: localhost
User: rfid_user

✓ Database 'rfid_checkin_system' exists

EXISTING TABLES:
✓ users (5 records)
✓ events (3 records)
✓ checkin (0 records)
✓ rfiddevices (1 records)
✓ system_settings (15 records)

EXPECTED TABLES:
✓ users - OK
✓ events - OK
✓ checkin - OK
✓ rfiddevices - OK
✓ system_settings - OK
✓ activitylog - OK
✓ accesslogs - OK

Database structure is complete!
```

### Validation and Repair

**validate-database.php** - Comprehensive validation and repair:

```bash
# Run validation and repair
http://your-domain.com/database/validate-database.php
```

**Validation Features:**
- **Structure Validation**: Verify table structure matches expectations
- **Data Integrity**: Check foreign key relationships and constraints
- **Performance Analysis**: Identify slow queries and missing indexes
- **Automatic Repair**: Fix common database issues automatically
- **Detailed Reporting**: Generate comprehensive validation reports

**Validation Categories:**

1. **Table Structure**:
   - Column existence and types
   - Index presence and optimization
   - Foreign key constraints
   - Generated column definitions

2. **Data Integrity**:
   - Orphaned records detection
   - Constraint violation identification
   - Data type consistency
   - Required field validation

3. **Performance Optimization**:
   - Missing index identification
   - Slow query analysis
   - Table optimization recommendations
   - Query execution plan analysis

### Access Log Management

**check-accesslogs.php** - Access log analysis:

```bash
# Analyze access logs
http://your-domain.com/database/check-accesslogs.php
```

**Analysis Features:**
- Failed login attempt tracking
- Suspicious activity detection
- IP address analysis and blocking recommendations
- User activity pattern analysis
- Security event correlation

**fix-accesslogs.php** - Access log maintenance:

```bash
# Clean and optimize access logs
http://your-domain.com/database/fix-accesslogs.php
```

**Maintenance Features:**
- Log rotation and cleanup
- Performance optimization
- Data archival
- Statistical summarization

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

## ⚡ Performance Optimization

### Database Indexes

Strategic indexes for optimal query performance:

```sql
-- User lookup optimization
CREATE INDEX idx_users_role_active ON users(role, is_active);
CREATE INDEX idx_users_rfid_tag ON users(rfid_tag);

-- Check-in performance
CREATE INDEX idx_checkin_user_time ON checkin(user_id, checkin_time DESC);
CREATE INDEX idx_checkin_status_time ON checkin(status, checkin_time);

-- Event queries
CREATE INDEX idx_events_time_active ON events(start_time, active);
CREATE INDEX idx_events_type_time ON events(event_type, start_time);

-- System logging
CREATE INDEX idx_accesslog_time_action ON accesslogs(timestamp DESC, action);
```

### Query Optimization

**Slow Query Analysis:**
```sql
-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 0.1; -- 100ms threshold

-- Analyze slow queries
SELECT * FROM mysql.slow_log 
WHERE start_time > DATE_SUB(NOW(), INTERVAL 1 DAY)
ORDER BY query_time DESC;
```

**Query Performance Tips:**
1. Use prepared statements for security and performance
2. Limit result sets with appropriate WHERE clauses
3. Use indexes for frequently queried columns
4. Avoid SELECT * in production queries
5. Use EXPLAIN to analyze query execution plans

### Connection Optimization

**Connection Pooling Configuration:**
```php
// database.php connection optimization
$options = [
    PDO::ATTR_PERSISTENT => true, // Connection pooling
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];
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

## 🔗 Integration Points

### Application Integration

**Core Integration:**
- `core/database.php` - Primary database connection
- `core/repositories/` - Data access layer
- `core/PerformanceManager.php` - Query optimization
- `core/DatabaseOptimizer.php` - Performance tuning

**API Integration:**
- RESTful API endpoints use repository pattern
- Standardized error handling and logging
- Transaction management for data consistency
- Caching layer for performance optimization

---

**Database System**: Production Ready  
**Last Updated**: January 2025  
**Schema Version**: 3.0.0  
**Compatibility**: MySQL 8.0+, MariaDB 10.5+