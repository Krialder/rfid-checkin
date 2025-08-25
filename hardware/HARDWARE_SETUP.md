# ESP32 RFID Hardware Setup

## Quick Start

1. **Hardware Setup**
   - Connect RC522 RFID module to ESP32 as shown below
   - Add LEDs for visual feedback
   - Power via USB or external 5V supply

2. **Software Setup**
   - **Option A**: Edit the code directly - Change WiFi credentials and server URL in the #define statements at the top of ESP32_RFID_Reader.ino
   - **Option B**: Use config file - Copy `esp32_config.example.h` to `config.h` and uncomment the #include "config.h" line
   - Upload `ESP32_RFID_Reader.ino` to your ESP32 using Arduino IDE

3. **Testing**
   - Open Serial Monitor (115200 baud)
   - Type `status` to check connection
   - Test with RFID cards

## Wiring Diagram

```
ESP32        RC522
-----        -----
GPIO21   --> SDA
GPIO18   --> SCK
GPIO23   --> MOSI
GPIO19   --> MISO
GPIO22   --> RST
3.3V     --> 3.3V
GND      --> GND

LEDs:
GPIO2    --> Green LED (+ resistor) --> GND
GPIO4    --> Red LED (+ resistor) --> GND
```

## LED Feedback

- **Green LED**: Success (checkin/checkout worked)
- **Red LED**: Error (network issue, unknown card, etc.)
- **Both LEDs**: Activity (sending data to server)

## Serial Commands

Open Serial Monitor and type:
- `status` - Show device status
- `test` - Test server connection
- `restart` - Restart device
- `help` - Show available commands

## Arduino IDE Setup

### Install ESP32 Board Package
1. Open Arduino IDE
2. Go to File → Preferences
3. Add this URL to "Additional Board Manager URLs":
   ```
   https://espressif.github.io/arduino-esp32/package_esp32_index.json
   ```
4. Go to Tools → Board → Board Manager
5. Search for "ESP32" and install "esp32 by Espressif Systems"

### Install Required Libraries
1. Go to Tools → Manage Libraries
2. Search and install:
   - "ArduinoJson" by Benoit Blanchon
   - "MFRC522" by GithubCommunity

### Upload Process
1. Select Board: Tools → Board → ESP32 → "ESP32 Dev Module"
2. Select correct COM Port: Tools → Port → (your ESP32 port)
3. Click Upload button

## Configuration

Edit `config.h` to customize:
- WiFi credentials
- Server URL (your website's API endpoint)
- Device ID and name
- Timing settings

## Troubleshooting

### WiFi Issues
- Check SSID and password in config.h
- Verify 2.4GHz network (ESP32 doesn't support 5GHz)
- Check signal strength in Serial Monitor

### RFID Issues
- Verify wiring connections
- Try different RFID cards
- Check power supply (stable 3.3V required)

### Server Issues
- Verify server URL in config.h
- Check if your web server is running
- Test API endpoint manually in browser

## Hardware Requirements

- ESP32 development board
- RC522 RFID module
- 2x LEDs (green and red)
- 2x 330Ω resistors (for LEDs)
- Breadboard or PCB for connections
- RFID cards/tags

## Files

- `ESP32_RFID_Compact.ino` - Main Arduino sketch
- `config.example.h` - Configuration template
- `config.h` - Your actual config (gitignored)

## Support

Check the main project README for database setup and web interface configuration.
