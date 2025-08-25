/*
 * Example configuration for ESP32 RFID Check-in System
 * 
 * SETUP INSTRUCTIONS:
 * 1. Copy this file to config.h in the same directory
 * 2. Update all values with your actual settings
 * 3. Upload to your ESP32
 * 
 * NOTE: config.h is gitignored for security
 */

#ifndef CONFIG_H
#define CONFIG_H

// ===== WIFI SETTINGS =====
#define WIFI_SSID "YOUR_WIFI_NETWORK_NAME"
#define WIFI_PASSWORD "your_wifi_password"

// ===== SERVER SETTINGS =====
// Update with your server's IP address or domain
#define SERVER_URL "http://192.168.1.100/rfid-checkin/api/rfid_checkin.php"

// ===== DEVICE SETTINGS =====
#define DEVICE_ID "ESP32-MAIN"
#define DEVICE_NAME "Main Entrance Reader"

// ===== TIMING SETTINGS =====
#define SCAN_COOLDOWN 2000    // Milliseconds between RFID scans
#define WIFI_TIMEOUT 20000    // WiFi connection timeout
#define HTTP_TIMEOUT 10000    // HTTP request timeout

// ===== FEATURES =====
#define ENABLE_SERIAL_DEBUG true   // Set to false to disable debug output
#define ENABLE_HEARTBEAT false     // Periodic status messages

// ===== HARDWARE PINS (ESP32) =====
// You can uncomment and modify these if you use different pins
// #define SS_PIN 21     // RFID SDA
// #define RST_PIN 22    // RFID RST
// #define GREEN_LED 2   // Success indicator
// #define RED_LED 4     // Error indicator

#endif
