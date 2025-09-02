# RFID Registration Mode Feature

## Overview

The RFID Registration Mode is a powerful feature that allows administrators to temporarily enable the ESP32 RFID readers to accept **any RFID tag**, including unregistered ones. This solves the common problem of wanting to register new users by scanning their RFID tags without having to manually type the tag values.

## How It Works

### Normal Mode (Default)
- ESP32 devices only accept RFID tags that are already registered in the database
- Unregistered RFID tags are rejected with an error message
- Suitable for day-to-day check-in operations

### Registration Mode (Temporary)
- ESP32 devices accept ANY RFID tag, registered or unregistered
- Unregistered tags are added to a queue for administrator assignment
- LED feedback shows special pattern for registration mode scans
- Automatically logs all scans for audit purposes

## Usage Instructions

### For Administrators

#### 1. Enable Registration Mode
1. Navigate to **Admin → RFID Device Management**
2. Click the **🟢 Enable Registration Mode** button
3. Registration mode is now active system-wide

#### 2. Scan Unregistered RFID Tags
1. Use any ESP32 RFID reader to scan the new user's RFID tag
2. The device will show a special LED pattern (6 green flashes)
3. The tag is added to the scan queue automatically

#### 3. Assign Tags to Users
1. In the RFID management page, view the **RFID Scan Queue**
2. Find unregistered tags and click **👤 Assign to User**
3. Enter the User ID of the person who should own this tag
4. The tag is immediately assigned and activated

#### 4. Disable Registration Mode
1. Click **🔴 Disable Registration Mode** when done
2. System returns to normal operation

### For Hardware Devices (ESP32)

#### Visual Feedback
- **Normal Check-in**: 3 green flashes
- **Normal Check-out**: 2 green flashes + 1 red flash
- **Registration Mode Scan**: 6 green flashes (alternating pattern)
- **Error/Unknown**: 4 red flashes

#### Serial Commands
Open the Serial Monitor (115200 baud) and use:
- `regmode` - Check current registration mode status
- `status` - Show device status including mode information
- `sim <RFID>` - Simulate scanning an RFID tag for testing

## Security Considerations

### Access Control
- Only users with **Admin** role can enable/disable registration mode
- All registration mode activities are logged in the system
- Session information tracks who enabled the mode and when

### Audit Trail
- Every RFID scan is logged with timestamp, device, and IP address
- Registration mode state changes are recorded in activity log
- Queue items show scan source and device information

### Automatic Cleanup
- Queue items older than 5 minutes are automatically cleaned up
- Duplicate scans within 10 seconds are ignored
- Old items can be manually cleared by administrators

## API Endpoints

### Registration Mode Management
**GET/POST** `/api/registration-mode.php`

**GET**: Check current status
```json
{
  "registration_mode_enabled": true,
  "last_updated": "2025-08-27 10:30:00",
  "session_info": {
    "admin_name": "John Doe",
    "started_at": "2025-08-27 10:30:00"
  }
}
```

**POST**: Toggle mode
```bash
# Enable
curl -X POST -d "action=enable" /api/registration-mode.php

# Disable  
curl -X POST -d "action=disable" /api/registration-mode.php
```

### Queue Management
**GET/POST** `/api/rfid-queue-manager.php`

**GET**: View queue items
```json
{
  "registration_mode_enabled": true,
  "queue_items": [
    {
      "queue_id": 1,
      "tag_value": "A1B2C3D4",
      "device_id": "ESP32-MAIN",
      "created_at": "2025-08-27 10:35:00",
      "assigned_user": null
    }
  ],
  "stats": {
    "total_scans": 5,
    "unique_tags": 3,
    "unregistered_tags": 2
  }
}
```

**POST**: Assign tag to user
```bash
curl -X POST \
  -d "action=assign_to_user" \
  -d "tag_value=A1B2C3D4" \
  -d "user_id=123" \
  /api/rfid-queue-manager.php
```

## ESP32 Firmware Integration

### Modified Response Handling
The ESP32 firmware has been updated to handle registration mode responses:

```cpp
if (registrationMode && action == "registration") {
  Serial.println("📋 REGISTRATION MODE: RFID ready for assignment");
  Serial.println("  RFID: " + doc["rfid"].as<String>());
  
  // Special LED pattern for registration mode
  for (int i = 0; i < 6; i++) {
    digitalWrite(GREEN_LED, HIGH);
    delay(100);
    digitalWrite(GREEN_LED, LOW);
    delay(100);
  }
}
```

### Status Checking
New command to check registration mode status:
```cpp
void checkRegistrationMode() {
  // Queries the server for current registration mode status
  // Shows admin who enabled it and when
  // Provides visual feedback via LEDs
}
```

## Database Schema

### System Settings Table
```sql
CREATE TABLE `system_settings` (
  `setting_id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `description` VARCHAR(255),
  `updated_by` INT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_setting_key` (`setting_key`),
  FOREIGN KEY (`updated_by`) REFERENCES `Users`(`user_id`) ON DELETE SET NULL
);
```

### RFID Scan Queue Table
```sql
CREATE TABLE `rfid_scan_queue` (
  `queue_id` INT AUTO_INCREMENT PRIMARY KEY,
  `tag_value` VARCHAR(50) NOT NULL,
  `device_id` INT DEFAULT 1,
  `source_ip` VARCHAR(45),
  `source` VARCHAR(50) DEFAULT 'hardware',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_created_at` (`created_at`),
  KEY `idx_tag_value` (`tag_value`)
);
```

## Troubleshooting

### Common Issues

#### Registration Mode Not Working
1. Check admin permissions - only admins can enable the mode
2. Verify ESP32 firmware is updated with registration mode support
3. Check server connectivity from ESP32 device
4. Look at ESP32 serial output for error messages

#### Tags Not Appearing in Queue
1. Verify ESP32 is connected to WiFi
2. Check server URL configuration in ESP32 config.h
3. Ensure rfid-queue.php endpoint is accessible
4. Check database table creation (automatic but verify)

#### Assignment Failures
1. Verify user ID exists in the system
2. Check if RFID tag is already assigned to another user
3. Ensure admin permissions for the assignment operation
4. Check activity logs for detailed error information

### ESP32 Diagnostics
Use these serial commands for troubleshooting:
- `status` - Complete device status
- `regmode` - Registration mode status  
- `test` - Server connectivity test
- `sim A1B2C3D4` - Simulate RFID scan

### Server Logs
Check these for registration mode issues:
- `/logs/system.log` - General system errors
- PHP error logs - API endpoint errors
- Database logs - Query execution issues
- Activity logs via admin interface

## Best Practices

### Workflow Recommendation
1. **Prepare for Registration Day**: Enable registration mode just before user registration begins
2. **Scan in Batches**: Have users scan their RFID tags in groups
3. **Immediate Assignment**: Assign tags to users immediately after scanning for best organization
4. **Disable Promptly**: Turn off registration mode as soon as registration is complete
5. **Verify Assignments**: Test a few assigned tags to ensure they work for check-in

### Security Best Practices
1. **Limit Time**: Only enable registration mode when actively registering users
2. **Monitor Queue**: Regularly check the scan queue for suspicious activity
3. **Clear Old Items**: Clean up old queue items to prevent confusion
4. **Log Review**: Periodically review activity logs for registration mode usage
5. **Access Control**: Ensure only trusted administrators have system access

### Performance Tips
1. **Queue Cleanup**: Set up automated cleanup of old queue items
2. **Batch Operations**: Process multiple tag assignments in quick succession
3. **Network Optimization**: Ensure strong WiFi signal for ESP32 devices
4. **Database Indexing**: Monitor database performance with large queue volumes

## Integration Examples

### Bulk User Registration Workflow
```bash
# 1. Enable registration mode
curl -X POST -d "action=enable" /api/registration-mode.php

# 2. Have users scan their RFID tags (physical ESP32 scanning)

# 3. Get queue of scanned tags  
curl /api/rfid-queue-manager.php

# 4. Assign tags to users (repeat for each)
curl -X POST \
  -d "action=assign_to_user" \
  -d "tag_value=A1B2C3D4" \
  -d "user_id=123" \
  /api/rfid-queue-manager.php

# 5. Disable registration mode
curl -X POST -d "action=disable" /api/registration-mode.php
```

### Event Registration Integration
This feature is perfect for:
- **New Employee Onboarding**: Scan badges during orientation
- **Conference Registration**: Quick tag assignment for attendees  
- **School Enrollment**: Student ID card activation
- **Access Card Replacement**: Re-assign lost or damaged cards

The registration mode feature transforms the RFID system from a check-in only tool into a complete user registration and management solution.
