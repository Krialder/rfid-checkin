# API Endpoints

This directory contains the RESTful API endpoints that power the RFID Check-in System's backend functionality, providing secure and efficient data access for frontend interfaces and hardware integration.

## Core API Endpoints

### Authentication & Session Management
- **`dashboard.php`** - Dashboard data aggregation endpoint providing user statistics and activity summaries
- **`manual_checkin.php`** - Manual check-in processing for administrative overrides

### RFID Hardware Integration
- **`rfid_checkin.php`** - Primary RFID check-in handler for hardware devices
- **`rfid_poll.php`** - Real-time RFID scanning support for web interface integration
- **`rfid_queue.php`** - RFID scan queue management for hardware-to-web communication

### Data Retrieval
- **`analytics.php`** - Advanced analytics data provider with filtering and aggregation
- **`event_details.php`** - Detailed event information and user check-in status

## API Standards

### Response Format
All endpoints return JSON responses with consistent structure:
```json
{
  "success": true|false,
  "data": {...},
  "error": "Error message if applicable",
  "timestamp": "ISO 8601 timestamp"
}
```

### Authentication
- Session-based authentication for web interface endpoints
- Device authentication for hardware integration endpoints
- CORS support for cross-origin requests where appropriate

### Error Handling
- Standardized HTTP status codes
- Comprehensive error messages
- Security-conscious error disclosure

## Security Features

- Input validation and sanitization
- SQL injection prevention through prepared statements
- Rate limiting on sensitive endpoints
- Audit logging for all API interactions
- CSRF protection where applicable

## Performance Optimization

- Database query optimization
- Response caching where appropriate
- Minimal data transfer through selective field inclusion
- Connection pooling and resource management

## Integration Points

APIs integrate seamlessly with:
- Frontend dashboard and user interfaces
- Hardware RFID readers (ESP32/Arduino)
- Administrative management interfaces
- Analytics and reporting systems
- External systems via webhook support
