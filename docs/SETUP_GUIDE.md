# RFID Hardware Setup Guide

## Overview

This guide covers setting up the RFID hardware components that integrate with the modern PHP check-in system. We provide multiple hardware configurations to suit different needs and budgets.

## Hardware Configurations

### Option 1: All-in-One NodeMCU Setup (Recommended)
**File:** `NodeMCU_Simple.ino`
- **Hardware:** NodeMCU ESP8266 + RC522 RFID Module + LEDs
- **Pros:** Simple, cost-effective, wireless
- **Cons:** Limited processing power
- **Best for:** Single reader deployments

### Option 2: Advanced NodeMCU Setup
**File:** `NodeMCU_Modern.ino`
- **Hardware:** NodeMCU ESP8266 + RC522 + LEDs + Buzzer + Web interface
- **Pros:** Feature-rich, web configuration, robust error handling
- **Cons:** More complex setup
- **Best for:** Production deployments, multiple readers

### Option 3: Dual-Device Setup
**Files:** `NodeMCU_Master.ino` + `Arduino_Mega_RFID.ino`
- **Hardware:** NodeMCU ESP8266 (WiFi) + Arduino Mega (RFID processing)
- **Pros:** Maximum reliability, dedicated RFID processing
- **Cons:** Higher cost, more wiring
- **Best for:** High-traffic locations, mission-critical applications

## Quick Start Setup

### For Simple NodeMCU Setup:

1. **Install Required Libraries:**
   ```
   Arduino IDE → Tools → Manage Libraries
   - ESP8266WiFi (built-in)
   - ArduinoJson (by Benoit Blanchon)
   - MFRC522 (by GithubCommunity)
   ```

2. **Hardware Wiring:**
   ```
   RC522 → NodeMCU
   SDA  → D4 (GPIO2)
   SCK  → D5 (GPIO14)
   MOSI → D7 (GPIO13)
   MISO → D6 (GPIO12)
   RST  → D3 (GPIO0)
   3.3V → 3.3V
   GND  → GND
   
   Green LED → D1 (GPIO5) + 220Ω resistor → GND
   Red LED   → D2 (GPIO4) + 220Ω resistor → GND
   ```

3. **Configure the Code:**
   Edit `NodeMCU_Simple.ino`:
   ```cpp
   const char* ssid = "YOUR_WIFI_SSID";
   const char* password = "YOUR_WIFI_PASSWORD";
   const char* serverURL = "http://YOUR_SERVER_IP/api_rfid_checkin.php";
   const char* deviceID = "RFID-001";
   const char* deviceName = "Main Entrance";
   ```

4. **Upload and Test:**
   - Upload the code to NodeMCU
   - Open Serial Monitor (115200 baud)
   - Watch for "System ready!" message
   - Test with RFID cards

## Advanced Configuration

### Web-Based Configuration (Modern NodeMCU)

The advanced NodeMCU setup includes a web interface for configuration:

1. **Initial Setup:**
   - Upload `NodeMCU_Modern.ino`
   - On first boot, device creates WiFi hotspot "RFID-Setup"
   - Connect with password "12345678"
   - Configure WiFi and server settings

2. **Ongoing Management:**
   - Access web interface at device IP address
   - View status: `http://device-ip/`
   - Configuration: `http://device-ip/config`
   - System test: `http://device-ip/test`

### Server Integration

Ensure your PHP server is configured to accept RFID data:

1. **Update Server URL** in Arduino code to point to `api_rfid_checkin.php`
2. **Test Server Response** - should return JSON:
   ```json
   {
     "success": true,
     "action": "checkin",
     "user": {"name": "John Doe"},
     "event": {"name": "Morning Meeting"}
   }
   ```

## Troubleshooting

### Common Issues:

**1. WiFi Connection Failed**
```
Solution: Check SSID/password, signal strength
Debug: Use Serial Monitor, check WiFi.status()
```

**2. RFID Not Reading**
```
Solution: Check wiring, power supply (3.3V only!)
Debug: Test with rfid.PCD_PerformSelfTest()
```

**3. Server Communication Error**
```
Solution: Check server URL, network connectivity
Debug: Test server URL in browser first
```

**4. Cards Not Recognized**
```
Solution: Check card type (Mifare compatible)
Debug: Use Serial Monitor to see raw RFID data
```

### Debug Commands

For Simple NodeMCU setup, use Serial Monitor commands:
- `debug` - Show system information
- `test` - Test server connection
- `wifi` - Check WiFi status
- `restart` - Restart device
- `help` - Show available commands

## Production Deployment

### Security Recommendations:

1. **Change Default Passwords** - Update WiFi setup password
2. **Use Static IPs** - Configure fixed IP addresses
3. **Enable API Keys** - Add authentication to server requests
4. **Secure Physical Access** - Enclose devices in protective cases
5. **Monitor Logs** - Check server logs for suspicious activity

### Performance Optimization:

1. **Placement:** Position readers away from metal objects
2. **Power:** Use quality power supplies (5V 2A minimum)
3. **Network:** Ensure strong WiFi signal at installation location
4. **Maintenance:** Regular cleaning of RFID readers

### Multiple Reader Setup:

For multiple readers:

1. **Unique Device IDs:** Each reader needs unique `deviceID`
2. **Load Balancing:** Consider server load with many readers
3. **Network Planning:** Ensure adequate WiFi bandwidth
4. **Central Management:** Use web interface for configuration

## Hardware Shopping List

### Basic Setup (1 Reader):
- NodeMCU ESP8266 Development Board × 1
- RC522 RFID Module × 1
- LEDs (Red, Green) × 2
- 220Ω Resistors × 2
- Breadboard and jumper wires
- RFID Cards/Tags for testing

**Estimated Cost: $15-20**

### Professional Setup (1 Reader):
- All basic components plus:
- Buzzer for audio feedback
- Enclosure case
- Quality power supply
- Professional mounting hardware

**Estimated Cost: $25-35**

### Enterprise Setup (Multiple Readers):
- Professional setup × N readers
- Network switch (if needed)
- Cable management
- Central monitoring system

**Cost per reader: $30-40**

## Next Steps

1. **Choose your hardware configuration** based on needs and budget
2. **Follow the wiring diagrams** carefully (double-check power connections!)
3. **Install required libraries** in Arduino IDE
4. **Configure and upload** the appropriate .ino file
5. **Test with server** to ensure integration works
6. **Deploy and monitor** in production environment

For support and advanced configurations, consult the main system documentation.
