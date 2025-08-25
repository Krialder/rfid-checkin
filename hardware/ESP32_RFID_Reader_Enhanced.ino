/*
 * Enhanced ESP32 RFID Check-in System
 * Now supports both direct check-in and web interface queue
 * 
 * Features:
 * - ESP32 with built-in WiFi
 * - RC522 RFID reader
 * - Simple LED feedback
 * - Dual API support (checkin + queue)
 * - Robust error handling
 * 
 * Hardware:
 * ESP32 -> RC522
 * GPIO21 -> SDA
 * GPIO18 -> SCK
 * GPIO23 -> MOSI
 * GPIO19 -> MISO
 * GPIO22 -> RST
 * 3.3V -> 3.3V
 * GND -> GND
 * 
 * GPIO2 -> Green LED (Success)
 * GPIO4 -> Red LED (Error)
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <SPI.h>
#include <MFRC522.h>
#include "config.h"

// Hardware pins
#define SS_PIN    21
#define RST_PIN   22
#define GREEN_LED 2
#define RED_LED   4

// Global objects
MFRC522 rfid(SS_PIN, RST_PIN);

// Runtime variables
String lastRFID = "";
unsigned long lastScan = 0;
bool wifiConnected = false;

// API endpoints
const String CHECKIN_URL = String(SERVER_URL) + "/api/rfid_checkin.php";
const String QUEUE_URL = String(SERVER_URL) + "/api/rfid_queue.php";

void setup() {
  Serial.begin(115200);
  delay(1000);
  
  Serial.println("=== ESP32 RFID Check-in Enhanced ===");
  Serial.println("Device ID: " + String(DEVICE_ID));
  
  initHardware();
  connectWiFi();
  initRFID();
  testConnection();
  
  Serial.println("System ready!");
  Serial.println("Supports: Direct check-in + Web interface queue");
  flashLED(GREEN_LED, 2);
}

void loop() {
  checkWiFi();
  scanRFID();
  delay(100);
}

void initHardware() {
  pinMode(GREEN_LED, OUTPUT);
  pinMode(RED_LED, OUTPUT);
  digitalWrite(GREEN_LED, LOW);
  digitalWrite(RED_LED, HIGH); // Red on during setup
  
  SPI.begin();
  Serial.println("Hardware initialized");
}

void connectWiFi() {
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print("Connecting to WiFi");
  
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    wifiConnected = true;
    Serial.println("\nWiFi connected!");
    Serial.println("IP: " + WiFi.localIP().toString());
    Serial.println("Signal: " + String(WiFi.RSSI()) + " dBm");
    flashLED(GREEN_LED, 3);
  } else {
    wifiConnected = false;
    Serial.println("\nWiFi failed!");
    flashLED(RED_LED, 5);
  }
}

void initRFID() {
  rfid.PCD_Init();
  
  // Test RFID module
  byte version = rfid.PCD_ReadRegister(MFRC522::VersionReg);
  if (version == 0x91 || version == 0x92) {
    Serial.println("RFID module detected (v" + String(version, HEX) + ")");
  } else {
    Serial.println("RFID module error!");
    flashLED(RED_LED, 3);
  }
}

void testConnection() {
  if (!wifiConnected) return;
  
  Serial.println("Testing server connection...");
  
  HTTPClient httpClient;
  httpClient.begin(CHECKIN_URL);
  httpClient.setTimeout(5000);
  httpClient.addHeader("User-Agent", "ESP32-RFID/Device-" + String(DEVICE_ID));
  
  int httpCode = httpClient.GET();
  httpClient.end();
  
  if (httpCode > 0) {
    Serial.println("✓ Server reachable (" + String(httpCode) + ")");
  } else {
    Serial.println("✗ Server error (" + String(httpCode) + ")");
  }
}

void checkWiFi() {
  static unsigned long lastCheck = 0;
  
  if (millis() - lastCheck > 30000) { // Check every 30s
    if (WiFi.status() != WL_CONNECTED) {
      Serial.println("WiFi disconnected, reconnecting...");
      wifiConnected = false;
      connectWiFi();
    }
    lastCheck = millis();
  }
}

void scanRFID() {
  if (!rfid.PICC_IsNewCardPresent() || !rfid.PICC_ReadCardSerial()) {
    return;
  }
  
  // Read RFID tag
  String rfidTag = "";
  for (byte i = 0; i < rfid.uid.size; i++) {
    if (rfid.uid.uidByte[i] < 0x10) rfidTag += "0";
    rfidTag += String(rfid.uid.uidByte[i], HEX);
  }
  rfidTag.toUpperCase();
  
  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();
  
  // Prevent rapid duplicate scans (same card within 3 seconds)
  if (rfidTag == lastRFID && (millis() - lastScan < 3000)) {
    return;
  }
  
  lastRFID = rfidTag;
  lastScan = millis();
  
  Serial.println("RFID: " + rfidTag);
  
  // Send to both endpoints
  sendToCheckin(rfidTag);
  sendToQueue(rfidTag);
}

void sendToCheckin(String rfidTag) {
  if (!wifiConnected) {
    Serial.println("✗ No WiFi connection for check-in");
    return;
  }
  
  Serial.println("Sending to check-in endpoint...");
  
  HTTPClient httpClient;
  httpClient.begin(CHECKIN_URL);
  httpClient.setTimeout(10000);
  httpClient.addHeader("Content-Type", "application/x-www-form-urlencoded");
  httpClient.addHeader("User-Agent", "ESP32-RFID/Device-" + String(DEVICE_ID));
  
  String postData = "rfid=" + rfidTag + 
                   "&device_id=" + String(DEVICE_ID);
  
  int httpCode = httpClient.POST(postData);
  String response = "";
  
  if (httpCode > 0) {
    response = httpClient.getString();
  }
  
  httpClient.end();
  
  Serial.println("Check-in response (" + String(httpCode) + "): " + response);
  
  if (httpCode == 200) {
    parseCheckinResponse(response);
  } else {
    Serial.println("✗ Check-in failed: " + String(httpCode));
  }
}

void sendToQueue(String rfidTag) {
  if (!wifiConnected) {
    return;
  }
  
  Serial.println("Adding to web queue...");
  
  HTTPClient httpClient;
  httpClient.begin(QUEUE_URL);
  httpClient.setTimeout(5000);
  httpClient.addHeader("Content-Type", "application/x-www-form-urlencoded");
  httpClient.addHeader("User-Agent", "ESP32-RFID/Device-" + String(DEVICE_ID));
  
  String postData = "rfid=" + rfidTag + 
                   "&device_id=" + String(DEVICE_ID) +
                   "&source=hardware";
  
  int httpCode = httpClient.POST(postData);
  String response = "";
  
  if (httpCode > 0) {
    response = httpClient.getString();
  }
  
  httpClient.end();
  
  Serial.println("Queue response (" + String(httpCode) + "): " + response);
  
  if (httpCode != 200) {
    Serial.println("✗ Queue failed: " + String(httpCode));
  }
}

void parseCheckinResponse(String response) {
  // Visual feedback
  digitalWrite(GREEN_LED, HIGH);
  digitalWrite(RED_LED, HIGH);
  delay(100);
  digitalWrite(GREEN_LED, LOW);
  digitalWrite(RED_LED, LOW);
  
  DynamicJsonDocument doc(1024);
  DeserializationError error = deserializeJson(doc, response);
  
  if (error) {
    Serial.println("✗ JSON Error: " + String(error.c_str()));
    flashLED(RED_LED, 2);
    return;
  }
  
  if (doc["success"] == true) {
    String action = doc["action"] | "unknown";
    String userName = doc["user"]["name"] | "Unknown";
    String eventName = doc["event"]["name"] | "";
    
    Serial.println("✓ SUCCESS: " + action + " - " + userName);
    if (eventName != "") {
      Serial.println("  Event: " + eventName);
    }
    
    // Different LED patterns for check-in vs check-out
    if (action == "checkin") {
      flashLED(GREEN_LED, 3); // 3 green flashes for check-in
    } else if (action == "checkout") {
      flashLED(GREEN_LED, 2); // 2 green flashes for check-out
      flashLED(RED_LED, 1);   // 1 red flash
    } else {
      flashLED(GREEN_LED, 1);
    }
    
  } else if (doc["warning"]) {
    String warning = doc["warning"];
    String userName = doc["user"] | "Unknown";
    
    Serial.println("⚠ WARNING: " + warning + " - " + userName);
    flashLED(GREEN_LED, 1);
    flashLED(RED_LED, 1);
    
  } else {
    String errorMsg = doc["error"] | doc["message"] | "Unknown error";
    Serial.println("✗ ERROR: " + errorMsg);
    flashLED(RED_LED, 4);
  }
}

void flashLED(int pin, int count) {
  for (int i = 0; i < count; i++) {
    digitalWrite(pin, HIGH);
    delay(150);
    digitalWrite(pin, LOW);
    delay(150);
  }
}

// Enhanced debug commands via Serial Monitor
void serialEvent() {
  if (Serial.available()) {
    String cmd = Serial.readStringUntil('\n');
    cmd.trim();
    cmd.toLowerCase();
    
    if (cmd == "status") {
      Serial.println("=== STATUS ===");
      Serial.println("Device ID: " + String(DEVICE_ID));
      Serial.println("WiFi: " + String(wifiConnected ? "Connected" : "Disconnected"));
      Serial.println("IP: " + WiFi.localIP().toString());
      Serial.println("RSSI: " + String(WiFi.RSSI()) + " dBm");
      Serial.println("Uptime: " + String(millis() / 1000) + "s");
      Serial.println("Last RFID: " + lastRFID);
      Serial.println("Check-in URL: " + CHECKIN_URL);
      Serial.println("Queue URL: " + QUEUE_URL);
      Serial.println("=============");
      
    } else if (cmd == "test") {
      testConnection();
      
    } else if (cmd.startsWith("sim ")) {
      // Simulate RFID scan: "sim 1234ABCD"
      String testRFID = cmd.substring(4);
      testRFID.toUpperCase();
      if (testRFID.length() >= 6) {
        Serial.println("Simulating RFID: " + testRFID);
        sendToCheckin(testRFID);
        sendToQueue(testRFID);
      } else {
        Serial.println("Invalid RFID format. Use: sim 1234ABCD");
      }
      
    } else if (cmd == "restart") {
      Serial.println("Restarting...");
      ESP.restart();
      
    } else if (cmd == "help") {
      Serial.println("Commands:");
      Serial.println("  status     - Show system status");
      Serial.println("  test       - Test server connection");
      Serial.println("  sim <rfid> - Simulate RFID scan");
      Serial.println("  restart    - Restart device");
      Serial.println("  help       - Show this help");
      
    } else if (cmd != "") {
      Serial.println("Unknown command. Type 'help' for available commands.");
    }
  }
}
