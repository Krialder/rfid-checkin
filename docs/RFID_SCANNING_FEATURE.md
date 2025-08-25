# RFID Scanning Feature Documentation

## Overview

The RFID scanning feature allows administrators to scan RFID tags directly from the web interface when creating or editing users. This provides a seamless way to assign RFID tags to users without manually typing the tag values.

## Features

### 1. **Multiple Scanning Methods**
- **Web Serial API**: Direct connection to USB/Serial RFID readers (Chrome/Edge browsers)
- **Server Polling**: Fallback method that polls the server for RFID scans from hardware devices
- **Manual Entry**: Traditional text input for RFID tags

### 2. **Browser Compatibility**
- **Modern Browsers**: Full functionality with Web Serial API support (Chrome 89+, Edge 89+)
- **Legacy Browsers**: Automatic fallback to server polling method
- **Cross-Platform**: Works on Windows, macOS, and Linux

### 3. **Hardware Support**
- ESP32-based RFID readers (included in project)
- Arduino-based RFID readers
- USB HID RFID readers (keyboard emulation)
- Serial-based RFID readers
- Any device that outputs RFID data via serial communication

## How to Use

### For User Registration

1. Navigate to **Admin → Users → Add User** or **Admin → Register User**
2. Fill in the required user information
3. In the RFID Tag field, click the **📡 Scan RFID** button
4. When prompted, scan an RFID card/tag with your connected reader
5. The RFID value will automatically appear in the text field
6. Complete the user registration

### For User Editing

1. Navigate to **Admin → Users**
2. Click **Edit** on any user
3. In the RFID Tag field, click the **📡 Scan RFID** button
4. Scan the RFID card/tag
5. Save the changes

### Keyboard Shortcut

- Focus any RFID tag input field and press **Ctrl+R** (or **Cmd+R** on Mac) to start scanning

## Technical Implementation

### Client-Side Components

1. **rfid-scanner.js** - Main JavaScript module that handles:
   - Web Serial API communication
   - Server polling fallback
   - UI updates and notifications
   - Cross-browser compatibility

2. **CSS Styling** - Visual enhancements for:
   - Scan button styling
   - Input field grouping
   - Scanning animations
   - Status notifications

### Server-Side Components

1. **rfid_poll.php** - API endpoint for polling RFID scans
2. **rfid_queue.php** - API endpoint for hardware to push RFID scans
3. **rfid_scan_queue** - Database table for temporary RFID storage

### Hardware Integration

1. **Enhanced ESP32 Code** - Updated Arduino sketch that:
   - Sends RFID data to both check-in and queue endpoints
   - Supports web interface integration
   - Provides better debugging capabilities

## Database Schema

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

## API Endpoints

### POST /api/rfid_poll.php
**Purpose**: Poll for new RFID scans (used by web interface)

**Request**:
```json
{
  "last_tag": "optional_last_received_tag",
  "timeout": 1000
}
```

**Response**:
```json
{
  "success": true,
  "rfid_tag": "1234ABCD",
  "timestamp": "2025-08-25 10:30:00"
}
```

### POST /api/rfid_queue.php
**Purpose**: Add RFID scan to queue (used by hardware)

**Request**:
```
rfid=1234ABCD&device_id=1&source=hardware
```

**Response**:
```json
{
  "success": true,
  "message": "RFID scan queued successfully",
  "rfid": "1234ABCD",
  "queue_id": 123
}
```

## Configuration

### Web Serial API Settings
The scanner automatically detects common RFID reader USB identifiers:
- CH340 (Vendor: 0x1A86, Product: 0x7523)
- FTDI (Vendor: 0x0403, Product: 0x6001)
- Arduino Uno (Vendor: 0x2341, Product: 0x0043)
- ESP32 (Vendor: 0x239A, Product: 0x8014)

### RFID Pattern Recognition
The scanner recognizes common RFID output formats:
- `RFID: 1234ABCD`
- `Card ID: 1234ABCD`
- `UID: 12 34 AB CD`
- `Tag: 1234ABCD`
- Raw hex strings (6-20 characters)

## Security Considerations

1. **Admin-Only Access**: RFID scanning is restricted to admin users
2. **Input Validation**: All RFID tags are validated for proper format
3. **Duplicate Prevention**: System prevents duplicate scans within 10 seconds
4. **Auto-Cleanup**: Queue entries are automatically cleaned up after 5 minutes
5. **Error Logging**: All scanning activities are logged for audit purposes

## Troubleshooting

### Common Issues

1. **"Web Serial API not supported"**
   - Use Chrome/Edge browser (version 89+)
   - Enable experimental features if needed
   - Fallback polling will be used automatically

2. **"No RFID reader detected"**
   - Check USB connection
   - Verify driver installation
   - Try different USB port
   - Check reader power supply

3. **"Scan timeout"**
   - Check RFID reader functionality
   - Verify card/tag is working
   - Try manual entry as fallback

4. **"Permission denied"**
   - Grant serial port access when prompted
   - Check browser security settings
   - Try refreshing the page

### Testing

Use the test page at `/test-rfid.php` to verify RFID scanning functionality:
- Check browser compatibility
- Test different RFID readers
- Verify scanning methods
- Debug connection issues

## Browser Requirements

### Minimum Requirements
- **Chrome 89+** or **Edge 89+** for full Web Serial API support
- **Firefox, Safari**: Limited to polling fallback method
- **Mobile browsers**: Manual entry only

### Recommended Setup
- **Chrome/Edge** latest version
- **USB RFID reader** connected directly to computer
- **Stable internet connection** for server polling fallback

## Performance

- **Web Serial API**: Real-time scanning (< 100ms response)
- **Server Polling**: 1-2 second polling interval
- **Queue Cleanup**: Automatic every minute
- **Timeout**: 30 seconds maximum scan time

## Future Enhancements

1. **Bluetooth RFID Support**: Add support for Bluetooth RFID readers
2. **Batch Scanning**: Scan multiple RFID tags in sequence
3. **RFID History**: Track scanning history per user session
4. **Custom Patterns**: Allow custom RFID format patterns
5. **Mobile App**: Dedicated mobile app for RFID scanning
