# Hardware Directory

This directory contains firmware and configuration files for ESP32-based RFID readers that integrate with the RFID Check-in System.

## 📁 Directory Structure

```
hardware/
├── ESP32-RFID-Reader.ino    # Main Arduino firmware for ESP32
└── config-example.h         # Configuration template file
```

## 🔌 Hardware Overview

The RFID Check-in System supports ESP32 microcontrollers with RC522 RFID modules for seamless hardware integration with the web-based system.

### Supported Hardware

**Primary Components:**
- **ESP32 DevKit** (or compatible ESP32 board)
- **RC522 RFID Module** (13.56MHz)
- **RFID Cards/Tags** (ISO14443A compatible)

**Optional Components:**
- **Status LEDs** (Green for success, Red for errors)
- **Buzzer** (Audio feedback)
- **OLED Display** (Status information)
- **External Antenna** (Extended range)

### Features

**Core Functionality:**
- ✅ Real-time RFID scanning and processing
- ✅ Dual API integration (direct check-in + web queue)
- ✅ WiFi connectivity with auto-reconnection
- ✅ Visual feedback with LED indicators
- ✅ Serial debugging and remote monitoring
- ✅ Registration mode for new RFID tags

**Advanced Features:**
- 🔄 Automatic WiFi reconnection and error recovery
- 📊 Device health monitoring and reporting
- 🛠️ Remote configuration and firmware updates
- 🔧 Serial command interface for maintenance
- ⚡ Performance optimization and memory management
- 🔒 Secure communication with the server

## 🔧 Hardware Setup

### Wiring Diagram

**ESP32 to RC522 Connection:**

| ESP32 Pin | RC522 Pin | Function | Wire Color (Suggested) |
|-----------|-----------|----------|------------------------|
| GPIO21    | SDA       | Slave Select | Purple |
| GPIO18    | SCK       | Serial Clock | Blue |
| GPIO23    | MOSI      | Master Out Slave In | Green |
| GPIO19    | MISO      | Master In Slave Out | Yellow |
| GPIO22    | RST       | Reset | Orange |
| 3.3V      | 3.3V      | Power (⚠️ NOT 5V!) | Red |
| GND       | GND       | Ground | Black |

**Status LEDs (Optional):**

| ESP32 Pin | Component | Purpose | Connection |
|-----------|-----------|---------|------------|
| GPIO2     | Green LED + 330Ω Resistor | Success/Ready | LED Anode → GPIO2, LED Cathode → 330Ω → GND |
| GPIO4     | Red LED + 330Ω Resistor   | Error/Failed  | LED Anode → GPIO4, LED Cathode → 330Ω → GND |

### Circuit Diagram

```
ESP32 DevKit                    RC522 Module
    ┌─────────────┐                ┌─────────────┐
    │         3V3 ├────────────────┤ 3.3V        │
    │         GND ├────────────────┤ GND         │
    │      GPIO21 ├────────────────┤ SDA         │
    │      GPIO18 ├────────────────┤ SCK         │
    │      GPIO23 ├────────────────┤ MOSI        │
    │      GPIO19 ├────────────────┤ MISO        │
    │      GPIO22 ├────────────────┤ RST         │
    │             │                │             │
    │       GPIO2 ├─────┐          └─────────────┘
    │       GPIO4 ├─────┼─┐
    └─────────────┘     │ │
                        │ │
              ┌─────────┘ │
              │   ┌───────┘
              │   │
              ▼   ▼
         Green LED Red LED
         + 330Ω   + 330Ω
         to GND   to GND
```

### Power Requirements

**Power Specifications:**
- **ESP32**: 3.3V, ~240mA (active), ~10μA (deep sleep)
- **RC522**: 3.3V, ~26mA (active), ~10μA (standby)
- **Total**: ~300mA (active operation)

**Power Options:**
- USB cable (development and testing)
- External 5V power adapter with voltage regulator
- Battery pack with voltage regulator (portable applications)
- PoE (Power over Ethernet) with ESP32-PoE boards

## 💻 Firmware Configuration

### Initial Setup

1. **Copy Configuration Template:**
```bash
cp config-example.h config.h
```

2. **Edit Configuration:**
```cpp
// WiFi Settings
#define WIFI_SSID "Your_WiFi_Network"
#define WIFI_PASSWORD "your_password"

// Server Settings
#define SERVER_URL "http://your-domain.com/rfid-checkin"

// Device Settings
#define DEVICE_ID "READER_001"
#define DEVICE_NAME "Main Entrance Reader"
```

3. **Upload Firmware:**
   - Open `ESP32-RFID-Reader.ino` in Arduino IDE
   - Select ESP32 board and correct port
   - Click Upload

### Configuration Options

**WiFi Configuration:**
```cpp
#define WIFI_SSID "Your_Network_Name"        // Network name
#define WIFI_PASSWORD "your_secure_password" // Network password
#define WIFI_TIMEOUT 20000                   // Connection timeout (ms)
```

**Server Configuration:**
```cpp
#define SERVER_URL "http://192.168.1.100/rfid-checkin"  // Server URL
#define HTTP_TIMEOUT 10000                               // Request timeout
```

**Device Configuration:**
```cpp
#define DEVICE_ID "ESP32-MAIN"              // Unique device identifier
#define DEVICE_NAME "Main Entrance Reader"  // Human-readable name
#define SCAN_COOLDOWN 2000                  // Cooldown between scans (ms)
```

**Feature Toggles:**
```cpp
#define ENABLE_SERIAL_DEBUG true   // Enable debug output
#define ENABLE_HEARTBEAT false     // Periodic status messages
#define ENABLE_DEEP_SLEEP false    // Power saving mode
```

### Hardware Pin Configuration

**Default Pin Assignments:**
```cpp
// RFID Module Pins
#define SS_PIN    21    // SDA (Slave Select)
#define RST_PIN   22    // Reset
#define SCK_PIN   18    // Serial Clock (SPI default)
#define MOSI_PIN  23    // Master Out Slave In (SPI default)
#define MISO_PIN  19    // Master In Slave Out (SPI default)

// Status LEDs
#define GREEN_LED 2     // Success indicator
#define RED_LED   4     // Error indicator

// Optional Components
#define BUZZER_PIN 5    // Audio feedback
#define BUTTON_PIN 0    // Reset/config button
```

**Custom Pin Configuration:**
```cpp
// Uncomment and modify for different pin assignments
// #define SS_PIN 5      // Alternative SDA pin
// #define RST_PIN 27    // Alternative Reset pin
// #define GREEN_LED 12  // Alternative Green LED pin
// #define RED_LED 13    // Alternative Red LED pin
```

## 🔄 Firmware Features

### Core Functionality

**RFID Scanning:**
```cpp
void scanRFID() {
    if (!rfid.PICC_IsNewCardPresent() || !rfid.PICC_ReadCardSerial()) {
        return;
    }
    
    // Read RFID tag UID
    String rfidTag = "";
    for (byte i = 0; i < rfid.uid.size; i++) {
        if (rfid.uid.uidByte[i] < 0x10) rfidTag += "0";
        rfidTag += String(rfid.uid.uidByte[i], HEX);
    }
    rfidTag.toUpperCase();
    
    // Process scan with cooldown protection
    if (rfidTag != lastRFID || (millis() - lastScan > SCAN_COOLDOWN)) {
        processScan(rfidTag);
    }
}
```

**Dual API Integration:**
- **Direct Check-in**: Immediate processing via `/api/rfid-checkin.php`
- **Web Queue**: Adds scan to queue via `/api/rfid-queue.php` for web interface processing

**WiFi Management:**
```cpp
void connectWiFi() {
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
        delay(500);
        attempts++;
    }
    
    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("WiFi connected: " + WiFi.localIP().toString());
    } else {
        Serial.println("WiFi connection failed");
    }
}
```

### Registration Mode Support

**Dynamic RFID Registration:**
```cpp
void checkRegistrationMode() {
    String regModeUrl = String(SERVER_URL) + "/api/registration-mode.php";
    
    HTTPClient httpClient;
    httpClient.begin(regModeUrl);
    int httpCode = httpClient.GET();
    
    if (httpCode == 200) {
        String response = httpClient.getString();
        // Parse JSON response for registration mode status
        DynamicJsonDocument doc(512);
        deserializeJson(doc, response);
        
        bool regMode = doc["registration_mode_enabled"] | false;
        if (regMode) {
            Serial.println("📋 Registration Mode: ENABLED");
            flashLED(GREEN_LED, 6); // Special LED pattern
        }
    }
}
```

### Error Handling and Recovery

**Automatic Recovery:**
```cpp
void handleError(String errorMsg) {
    Serial.println("Error: " + errorMsg);
    flashLED(RED_LED, 3);
    
    // Attempt recovery
    if (WiFi.status() != WL_CONNECTED) {
        connectWiFi();
    }
    
    // Reset RFID module if needed
    rfid.PCD_Reset();
    delay(50);
    rfid.PCD_Init();
}
```

**Watchdog Timer:**
```cpp
#include "esp_task_wdt.h"

void setup() {
    // Enable watchdog timer (8 seconds)
    esp_task_wdt_init(8, true);
    esp_task_wdt_add(NULL);
}

void loop() {
    // Reset watchdog timer
    esp_task_wdt_reset();
    
    // Main loop code...
}
```

## 🛠️ Maintenance and Debugging

### Serial Command Interface

The firmware includes a comprehensive command interface accessible via Serial Monitor:

**Available Commands:**
```
status          # Show system status and diagnostics
test           # Test server connection
regmode        # Check registration mode status
sim <rfid>     # Simulate RFID scan (e.g., "sim 1234ABCD")
restart        # Restart device
wifi           # Show WiFi status and signal strength
config         # Display current configuration
reset          # Reset to factory defaults
help           # Show available commands
```

**Command Examples:**
```bash
# Show system status
> status
=== STATUS ===
Device ID: READER_001
WiFi: Connected (192.168.1.101)
Signal: -45 dBm
Uptime: 3600 seconds
Last RFID: 1234ABCD
Check-in URL: http://server.com/api/rfid-checkin.php
=============

# Test server connectivity
> test
Testing server connection...
✓ Server reachable (200)

# Simulate RFID scan
> sim 1234ABCD
Simulating RFID: 1234ABCD
✓ SUCCESS: checkin - John Doe
```

### Debugging Features

**Serial Output Levels:**
```cpp
// Debug levels
#define DEBUG_LEVEL_NONE    0
#define DEBUG_LEVEL_ERROR   1
#define DEBUG_LEVEL_WARNING 2
#define DEBUG_LEVEL_INFO    3
#define DEBUG_LEVEL_DEBUG   4

#define DEBUG_LEVEL DEBUG_LEVEL_INFO  // Set desired level
```

**Performance Monitoring:**
```cpp
void logPerformance() {
    Serial.println("=== PERFORMANCE ===");
    Serial.println("Free Heap: " + String(ESP.getFreeHeap()) + " bytes");
    Serial.println("WiFi Signal: " + String(WiFi.RSSI()) + " dBm");
    Serial.println("Uptime: " + String(millis() / 1000) + " seconds");
    Serial.println("Scan Count: " + String(scanCount));
    Serial.println("=================");
}
```

**Memory Management:**
```cpp
void checkMemory() {
    size_t freeHeap = ESP.getFreeHeap();
    size_t minFreeHeap = ESP.getMinFreeHeap();
    
    if (freeHeap < 10000) {  // Less than 10KB free
        Serial.println("⚠️ Low memory warning: " + String(freeHeap));
        // Trigger cleanup or restart
    }
}
```

### LED Status Indicators

**LED Patterns:**
```cpp
// Success patterns
flashLED(GREEN_LED, 1);     // Single flash - general success
flashLED(GREEN_LED, 2);     // Double flash - check-out
flashLED(GREEN_LED, 3);     // Triple flash - check-in
flashLED(GREEN_LED, 6);     // Six flashes - registration mode

// Error patterns
flashLED(RED_LED, 1);       // Single flash - minor error
flashLED(RED_LED, 2);       // Double flash - RFID error
flashLED(RED_LED, 3);       // Triple flash - connection error
flashLED(RED_LED, 5);       // Five flashes - server error

// Status patterns
alternateFlash(GREEN_LED, RED_LED, 3);  // WiFi connecting
solidLED(GREEN_LED, 1000);              // System ready
solidLED(RED_LED, 2000);                // System error
```

## 🔧 Advanced Configuration

### Multiple Device Setup

**Device Configuration:**
```cpp
// Device 1 - Main Entrance
#define DEVICE_ID "READER_MAIN"
#define DEVICE_NAME "Main Entrance"

// Device 2 - Conference Room
#define DEVICE_ID "READER_CONF"
#define DEVICE_NAME "Conference Room A"

// Device 3 - Employee Entrance
#define DEVICE_ID "READER_STAFF"
#define DEVICE_NAME "Staff Entrance"
```

**Network Configuration:**
```cpp
// Static IP configuration (optional)
IPAddress local_IP(192, 168, 1, 101);
IPAddress gateway(192, 168, 1, 1);
IPAddress subnet(255, 255, 255, 0);

void setupStaticIP() {
    if (!WiFi.config(local_IP, gateway, subnet)) {
        Serial.println("Static IP configuration failed");
    }
}
```

### Power Management

**Deep Sleep Mode:**
```cpp
#include "esp_sleep.h"

void enterDeepSleep(uint64_t sleepTimeUs) {
    Serial.println("Entering deep sleep for " + String(sleepTimeUs / 1000000) + " seconds");
    
    // Configure wake-up source
    esp_sleep_enable_timer_wakeup(sleepTimeUs);
    esp_sleep_enable_ext0_wakeup(GPIO_NUM_0, 0); // Wake on button press
    
    // Enter deep sleep
    esp_deep_sleep_start();
}
```

**Battery Monitoring:**
```cpp
#define BATTERY_PIN 36  // ADC pin for battery voltage

float readBatteryVoltage() {
    int rawValue = analogRead(BATTERY_PIN);
    float voltage = (rawValue / 4095.0) * 3.3 * 2; // Voltage divider
    return voltage;
}

void checkBatteryLevel() {
    float voltage = readBatteryVoltage();
    
    if (voltage < 3.2) {  // Low battery threshold
        Serial.println("⚠️ Low battery: " + String(voltage) + "V");
        flashLED(RED_LED, 10);  // Warning pattern
    }
}
```

### Firmware Updates

**OTA (Over-The-Air) Updates:**
```cpp
#include <ArduinoOTA.h>

void setupOTA() {
    ArduinoOTA.setHostname(DEVICE_ID);
    ArduinoOTA.setPassword("your_ota_password");
    
    ArduinoOTA.onStart([]() {
        Serial.println("OTA Update Starting...");
    });
    
    ArduinoOTA.onEnd([]() {
        Serial.println("OTA Update Complete");
    });
    
    ArduinoOTA.onError([](ota_error_t error) {
        Serial.println("OTA Error: " + String(error));
    });
    
    ArduinoOTA.begin();
}

void loop() {
    ArduinoOTA.handle();
    // ... rest of loop code
}
```

## 📊 Performance Optimization

### RFID Reading Optimization

**Fast Scanning:**
```cpp
void optimizedRFIDScan() {
    // Check for new card presence quickly
    if (!rfid.PICC_IsNewCardPresent()) {
        return;
    }
    
    // Read card serial quickly
    if (!rfid.PICC_ReadCardSerial()) {
        return;
    }
    
    // Process immediately to reduce scan time
    processRFIDScan();
    
    // Halt card to prevent multiple reads
    rfid.PICC_HaltA();
    rfid.PCD_StopCrypto1();
}
```

**Memory Optimization:**
```cpp
// Use const for strings to save RAM
const char* ERROR_WIFI = "WiFi connection failed";
const char* ERROR_SERVER = "Server connection failed";
const char* SUCCESS_CHECKIN = "Check-in successful";

// Use F() macro for flash storage
Serial.println(F("System starting..."));
```

### Network Optimization

**HTTP Keep-Alive:**
```cpp
WiFiClient wifiClient;
HTTPClient httpClient;

void setupPersistentConnection() {
    httpClient.setReuse(true);  // Enable connection reuse
    httpClient.setTimeout(5000); // 5 second timeout
    httpClient.setUserAgent("ESP32-RFID/" + String(DEVICE_ID));
}
```

**Request Queuing:**
```cpp
#include <queue>

std::queue<String> scanQueue;

void addToQueue(String rfidTag) {
    if (scanQueue.size() < 10) {  // Limit queue size
        scanQueue.push(rfidTag);
    }
}

void processQueue() {
    if (!scanQueue.empty() && WiFi.status() == WL_CONNECTED) {
        String rfidTag = scanQueue.front();
        scanQueue.pop();
        sendToServer(rfidTag);
    }
}
```

## 🚨 Troubleshooting

### Common Hardware Issues

**RFID Module Not Detected:**
```cpp
void testRFIDModule() {
    byte version = rfid.PCD_ReadRegister(MFRC522::VersionReg);
    
    if (version == 0x91 || version == 0x92) {
        Serial.println("✓ RC522 module detected (v" + String(version, HEX) + ")");
    } else {
        Serial.println("✗ RC522 module not found (got: 0x" + String(version, HEX) + ")");
        Serial.println("Check wiring and power connections");
    }
}
```

**WiFi Connection Issues:**
```cpp
void diagnosWiFi() {
    Serial.println("WiFi Diagnostics:");
    Serial.println("Status: " + String(WiFi.status()));
    Serial.println("SSID: " + WiFi.SSID());
    Serial.println("IP: " + WiFi.localIP().toString());
    Serial.println("Signal: " + String(WiFi.RSSI()) + " dBm");
    Serial.println("MAC: " + WiFi.macAddress());
}
```

**Power Issues:**
```cpp
void checkPowerSupply() {
    float vcc = ESP.getVcc() / 1000.0;  // Convert to volts
    
    if (vcc < 3.0) {
        Serial.println("⚠️ Low voltage detected: " + String(vcc) + "V");
        Serial.println("Check power supply and connections");
    } else {
        Serial.println("✓ Power supply OK: " + String(vcc) + "V");
    }
}
```

### Software Issues

**Memory Leaks:**
```cpp
void checkMemoryLeak() {
    static size_t lastFreeHeap = 0;
    size_t currentFreeHeap = ESP.getFreeHeap();
    
    if (lastFreeHeap > 0) {
        int difference = currentFreeHeap - lastFreeHeap;
        if (difference < -1000) {  // More than 1KB lost
            Serial.println("⚠️ Possible memory leak: " + String(difference) + " bytes");
        }
    }
    
    lastFreeHeap = currentFreeHeap;
}
```

**Watchdog Reset Issues:**
```cpp
void preventWatchdogReset() {
    // Call this in long-running functions
    yield();  // Allow ESP32 to handle background tasks
    esp_task_wdt_reset();  // Reset watchdog timer
}
```

### Error Recovery

**Automatic System Recovery:**
```cpp
void systemRecovery() {
    Serial.println("Initiating system recovery...");
    
    // Reset WiFi
    WiFi.disconnect();
    delay(1000);
    connectWiFi();
    
    // Reset RFID module
    rfid.PCD_Reset();
    delay(100);
    rfid.PCD_Init();
    
    // Clear any queued data
    while (!scanQueue.empty()) {
        scanQueue.pop();
    }
    
    Serial.println("System recovery complete");
}
```

## 📚 Resources and References

### Libraries Required

**Core Libraries:**
```cpp
#include <WiFi.h>           // ESP32 WiFi functionality
#include <HTTPClient.h>     // HTTP requests
#include <ArduinoJson.h>    // JSON parsing
#include <SPI.h>            // SPI communication
#include <MFRC522.h>        // RC522 RFID module
```

**Optional Libraries:**
```cpp
#include <ArduinoOTA.h>     // Over-the-air updates
#include <WebServer.h>      // Web configuration interface
#include <EEPROM.h>         // Non-volatile storage
#include <esp_task_wdt.h>   // Watchdog timer
```

### Installation Dependencies

**Arduino IDE Setup:**
1. Install ESP32 board support package
2. Install required libraries via Library Manager
3. Select appropriate ESP32 board and port
4. Configure upload settings

**Library Versions:**
- **MFRC522**: 1.4.10 or later
- **ArduinoJson**: 6.19.0 or later
- **ESP32 Core**: 2.0.0 or later

### Documentation Links

- [ESP32 Official Documentation](https://docs.espressif.com/projects/esp-idf/)
- [RC522 RFID Module Datasheet](https://www.nxp.com/docs/en/data-sheet/MFRC522.pdf)
- [Arduino ESP32 Guide](https://randomnerdtutorials.com/getting-started-with-esp32/)
- [RFID Card Standards](https://en.wikipedia.org/wiki/ISO/IEC_14443)

---

**Hardware Integration**: Production Ready  
**Last Updated**: January 2025  
**Firmware Version**: 2.1.0  
**Compatibility**: ESP32 DevKit, RC522 Module, Arduino IDE 2.0+