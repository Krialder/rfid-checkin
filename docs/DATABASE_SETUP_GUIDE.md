# Database Setup and Repair Guide

This document provides comprehensive instructions for setting up and repairing the RFID Check-in System database.

## 🚀 Quick Start

### Option 1: Fresh Installation
If you're setting up the system for the first time:

1. Navigate to: `http://localhost/rfid-checkin/database/setup-database.php`
2. Follow the on-screen instructions
3. Use the default admin credentials to log in
4. Change the admin password immediately

### Option 2: Existing Database Repair
If you have an existing database with issues:

1. **Backup your database first!**
2. Navigate to: `http://localhost/rfid-checkin/database/validate-database.php`
3. Review the repair results
4. Test the application functionality

### Option 3: Database Validation Only
To check your database without making changes:

1. Navigate to: `http://localhost/rfid-checkin/database/validate-database.php`
2. Review the validation report
3. Follow the recommendations provided

## 📋 Database Requirements

### System Requirements
- MySQL 5.7+ or MariaDB 10.2+
- PHP 7.4+ with PDO MySQL extension
- At least 50MB database storage space
- Database user with CREATE, ALTER, DROP, INDEX privileges

### Configuration
Update `core/config.php` with your database settings:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'rfid_checkin_system');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_CHARSET', 'utf8mb4');
```

## 🏗️ Database Structure

### Core Tables

#### Users
Primary user management table
- **Primary Key**: `user_id`
- **Unique Fields**: `username`, `email`, `rfid_tag`
- **Important Fields**: `role`, `is_active`, `password`

#### Events  
Event management and scheduling
- **Primary Key**: `event_id`
- **Foreign Keys**: `created_by` → Users(user_id)
- **Important Fields**: `name`, `active`, `start_time`, `end_time`

#### CheckIn
Check-in and attendance records
- **Primary Key**: `checkin_id` 
- **Foreign Keys**: 
  - `user_id` → Users(user_id)
  - `event_id` → Events(event_id)
  - `device_id` → RFIDDevices(device_id)

#### RFIDDevices
RFID hardware management
- **Primary Key**: `device_id`
- **Unique Fields**: `device_serial`

### Management Tables

#### ActivityLog
User activity tracking
- **Primary Key**: `log_id`
- **Foreign Keys**: `user_id` → Users(user_id)

#### AccessLogs  
System access logging
- **Primary Key**: `log_id`
- **Foreign Keys**: 
  - `user_id` → Users(user_id)
  - `device_id` → RFIDDevices(device_id)

#### SystemSettings
Application configuration
- **Primary Key**: `setting_id`
- **Unique Fields**: `setting_key`

#### EventRegistration
Event participant management
- **Primary Key**: `registration_id`
- **Foreign Keys**:
  - `user_id` → Users(user_id) 
  - `event_id` → Events(event_id)

## 🔧 Common Issues and Solutions

### Issue: Table Name Inconsistencies
**Symptoms**: Errors about missing tables, queries failing
**Solution**: Run the repair script to rename tables consistently

### Issue: Missing Columns
**Symptoms**: SQL errors about unknown columns
**Solution**: Use repair script to add missing columns with proper structure

### Issue: Foreign Key Violations  
**Symptoms**: Cannot insert/update records, constraint errors
**Solution**: Repair script fixes foreign key relationships

### Issue: No Admin Users
**Symptoms**: Cannot access admin functions
**Solution**: Repair script creates default admin user (admin/admin123)

### Issue: Orphaned Data
**Symptoms**: Data inconsistencies, broken relationships
**Solution**: Validation script identifies and repair script cleans orphaned records

## 🛠️ Manual Database Commands

### Create Database Manually
```sql
CREATE DATABASE rfid_checkin_system 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;
```

### Create Admin User Manually
```sql
INSERT INTO Users (username, email, password, first_name, last_name, role, is_active, email_verified)
VALUES ('admin', 'admin@rfidcheckin.local', '$2y$10$...hash...', 'Admin', 'User', 'admin', 1, 1);
```

### Check Table Status
```sql
-- Show all tables
SHOW TABLES;

-- Check table structure  
DESCRIBE Users;
DESCRIBE Events;
DESCRIBE CheckIn;

-- Count records
SELECT 'Users' as table_name, COUNT(*) as count FROM Users
UNION ALL
SELECT 'Events', COUNT(*) FROM Events  
UNION ALL
SELECT 'CheckIn', COUNT(*) FROM CheckIn;
```

### Fix Common Issues
```sql
-- Reset admin password
UPDATE Users SET password = '$2y$10$...new_hash...' WHERE username = 'admin';

-- Activate all users
UPDATE Users SET is_active = 1 WHERE is_active = 0;

-- Clean orphaned check-ins
DELETE c FROM CheckIn c 
LEFT JOIN Users u ON c.user_id = u.user_id 
WHERE u.user_id IS NULL;
```

## 📊 Database Indexes

Key indexes for performance:

```sql
-- Users table
CREATE INDEX idx_users_username ON Users(username);
CREATE INDEX idx_users_email ON Users(email); 
CREATE INDEX idx_users_rfid ON Users(rfid_tag);
CREATE INDEX idx_users_active ON Users(is_active);

-- Events table  
CREATE INDEX idx_events_active ON Events(active);
CREATE INDEX idx_events_start_time ON Events(start_time);

-- CheckIn table
CREATE INDEX idx_checkin_user_time ON CheckIn(user_id, checkin_time);
CREATE INDEX idx_checkin_event ON CheckIn(event_id);
CREATE INDEX idx_checkin_status ON CheckIn(status);
```

## 🔐 Security Considerations

### Database Security
1. Use strong database passwords
2. Limit database user privileges to minimum required
3. Enable MySQL/MariaDB security features
4. Regular database backups

### Application Security  
1. Change default admin password immediately
2. Set DEBUG_MODE to false in production
3. Use HTTPS in production environments
4. Regular security updates

## 📈 Maintenance

### Regular Tasks
1. **Weekly**: Check validation script for issues
2. **Monthly**: Review access logs for suspicious activity  
3. **Quarterly**: Database optimization and cleanup
4. **Annually**: Full database backup and restore test

### Performance Monitoring
```sql
-- Check table sizes
SELECT table_name, 
       ROUND(((data_length + index_length) / 1024 / 1024), 2) AS "Size (MB)"
FROM information_schema.TABLES 
WHERE table_schema = 'rfid_checkin_system'
ORDER BY (data_length + index_length) DESC;

-- Check slow queries (if slow query log enabled)
SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;
```

## 🆘 Troubleshooting

### Database Connection Issues
1. Verify MySQL service is running
2. Check database credentials in config.php
3. Ensure database user has proper privileges
4. Test connection with MySQL client

### Application Errors
1. Check PHP error logs
2. Enable DEBUG_MODE temporarily for detailed errors
3. Verify all required tables exist
4. Run validation script for detailed analysis

### Performance Issues
1. Check database indexes are present
2. Analyze slow query log
3. Consider table optimization
4. Review application query patterns

## 📞 Support

If you encounter issues not covered in this guide:

1. Run the validation script for detailed analysis
2. Check the application error logs
3. Review the database setup requirements
4. Consider running the repair script with caution

Remember to always backup your database before making any structural changes!
