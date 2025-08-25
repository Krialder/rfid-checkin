<?php
/**
 * RFID Scanner Test Page
 * Simple page to test the RFID scanning functionality
 */

require_once 'core/config.php';
require_once 'core/auth.php';

// Check if user is admin
if (!Auth::isLoggedIn() || !Auth::hasRole(['admin'])) {
    http_response_code(403);
    header('Location: auth/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFID Scanner Test - Electronic Check-in System</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/forms.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .test-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .rfid-input-group {
            display: flex;
            gap: 10px;
            align-items: stretch;
        }
        .rfid-input-group input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: 'Courier New', monospace;
            font-weight: bold;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        .rfid-input-group input:focus {
            border-color: #007bff;
            background: white;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
            outline: none;
        }
        .rfid-input-group input:not(:placeholder-shown) {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-color: #28a745;
            color: #155724;
        }
        .btn-scan-rfid {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
            white-space: nowrap;
            min-width: 160px;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);
            position: relative;
            overflow: hidden;
        }
        .btn-scan-rfid:hover {
            background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.4);
            transform: translateY(-1px);
        }
        .btn-scan-rfid:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(0, 123, 255, 0.3);
        }
        .btn-scan-rfid:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
        }
        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #a71e2a 100%);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
        }
        .scan-icon {
            display: inline-block;
            transition: transform 0.3s ease;
        }
        .btn-scan-rfid:hover .scan-icon {
            transform: scale(1.1) rotate(5deg);
        }
        .rfid-scanning {
            position: relative;
        }
        .rfid-scanning::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: scan-pulse 2s infinite;
        }
        .rfid-scanning .scan-icon {
            animation: pulse-icon 1.5s infinite;
        }
        @keyframes scan-pulse {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        @keyframes pulse-icon {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        .rfid-status-indicator {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #28a745;
            border: 2px solid white;
            display: none;
        }
        .rfid-scanning .rfid-status-indicator {
            display: block;
            background: #ffc107;
            animation: blink 1s infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        .status-area {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
            background: #f8f9fa;
            border-left: 4px solid #17a2b8;
        }
        .test-info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        .compatibility-check {
            margin-top: 30px;
            padding: 15px;
            border-radius: 4px;
            background: #f8f9fa;
        }
        .feature-supported {
            color: #28a745;
            font-weight: bold;
        }
        .feature-not-supported {
            color: #dc3545;
            font-weight: bold;
        }
        h1, h2 {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>📡 RFID Scanner Test Page</h1>
        
        <div class="test-info">
            <h3>How to use:</h3>
            <ol>
                <li>Click the "📡 Scan RFID" button below</li>
                <li>If you have a physical RFID reader connected via USB/Serial, it should be detected</li>
                <li>If not, the system will fall back to polling mode</li>
                <li>Scan an RFID card/tag when prompted</li>
                <li>The RFID value should appear in the text field</li>
            </ol>
            <p><strong>Keyboard shortcut:</strong> Focus the RFID field and press <kbd>Ctrl+R</kbd> to start scanning</p>
        </div>

        <form>
            <div class="form-group">
                <label for="test-rfid">Test RFID Field:</label>
                <div class="rfid-input-group">
                    <input type="text" id="test-rfid" name="rfid_tag" 
                           placeholder="RFID tag will appear here after scanning">
                    <button type="button" class="btn-scan-rfid" 
                            data-rfid-scan data-rfid-target="test-rfid">
                        <span class="scan-icon">📡</span> Scan RFID
                        <div class="rfid-status-indicator"></div>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="test-rfid-2">Second Test Field:</label>
                <div class="rfid-input-group">
                    <input type="text" id="test-rfid-2" name="rfid_tag_2" 
                           placeholder="Another test field">
                    <button type="button" class="btn-scan-rfid" 
                            data-rfid-scan data-rfid-target="test-rfid-2">
                        <span class="scan-icon">📡</span> Scan RFID
                        <div class="rfid-status-indicator"></div>
                    </button>
                </div>
            </div>

            <div class="status-area">
                <h3>Status:</h3>
                <div id="status-display">
                    Ready to test RFID scanning...
                </div>
            </div>
        </form>

        <div class="compatibility-check">
            <h2>Browser Compatibility Check</h2>
            <div id="web-serial-support"></div>
            <div id="browser-info"></div>
            
            <h3>Testing Methods Available:</h3>
            <ul>
                <li><strong>Web Serial API:</strong> Direct connection to USB/Serial RFID readers (Chrome/Edge)</li>
                <li><strong>Server Polling:</strong> Fallback method that polls server for RFID scans</li>
                <li><strong>Manual Entry:</strong> Type RFID values directly</li>
            </ul>

            <h3>Supported RFID Readers:</h3>
            <ul>
                <li>ESP32-based readers (like in this project)</li>
                <li>Arduino-based RFID readers</li>
                <li>USB HID RFID readers (keyboard emulation)</li>
                <li>Serial-based RFID readers</li>
            </ul>
        </div>
    </div>

    <script src="assets/js/rfid-scanner.js"></script>
    <script>
        // Display browser compatibility info
        document.addEventListener('DOMContentLoaded', function() {
            const webSerialSupport = document.getElementById('web-serial-support');
            const browserInfo = document.getElementById('browser-info');
            const statusDisplay = document.getElementById('status-display');

            // Check Web Serial API support
            if ('serial' in navigator) {
                webSerialSupport.innerHTML = '<span class="feature-supported">✓ Web Serial API supported</span> - Direct hardware connection available';
            } else {
                webSerialSupport.innerHTML = '<span class="feature-not-supported">✗ Web Serial API not supported</span> - Will use polling fallback';
            }

            // Display browser info
            browserInfo.innerHTML = `
                <strong>Browser:</strong> ${navigator.userAgent}<br>
                <strong>Platform:</strong> ${navigator.platform}<br>
                <strong>Language:</strong> ${navigator.language}
            `;

            // Update status when RFID is scanned
            if (window.rfidScanner) {
                window.rfidScanner.setScanCallback(function(rfidTag) {
                    statusDisplay.innerHTML = `
                        <strong>Last RFID Scanned:</strong> ${rfidTag}<br>
                        <strong>Time:</strong> ${new Date().toLocaleString()}<br>
                        <strong>Status:</strong> <span class="feature-supported">SUCCESS</span>
                    `;
                });
            }

            // Add input event listeners to show when values change
            document.getElementById('test-rfid').addEventListener('input', function() {
                if (this.value) {
                    statusDisplay.innerHTML = `
                        <strong>RFID Value:</strong> ${this.value}<br>
                        <strong>Field:</strong> test-rfid<br>
                        <strong>Status:</strong> <span class="feature-supported">RECEIVED</span>
                    `;
                }
            });

            document.getElementById('test-rfid-2').addEventListener('input', function() {
                if (this.value) {
                    statusDisplay.innerHTML = `
                        <strong>RFID Value:</strong> ${this.value}<br>
                        <strong>Field:</strong> test-rfid-2<br>
                        <strong>Status:</strong> <span class="feature-supported">RECEIVED</span>
                    `;
                }
            });
        });
    </script>
</body>
</html>
