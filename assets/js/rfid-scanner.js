/**
 * RFID Scanner Module
 * Modern implementation with Web Serial API support
 * Provides RFID scanning functionality for user forms
 */

class RFIDScanner {
    constructor() {
        this.isScanning = false;
        this.port = null;
        this.reader = null;
        this.scanButton = null;
        this.targetInput = null;
        this.onScanCallback = null;
        this.scanTimeout = null;
        
        // Check for Web Serial API support
        this.supportsWebSerial = 'serial' in navigator;
        
        // Fallback polling for manual entry or existing hardware
        this.pollingInterval = null;
        this.lastPolledTag = null;
        
        this.init();
    }

    init() {
        // Add event listeners for scan buttons when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.attachEventListeners());
        } else {
            this.attachEventListeners();
        }
    }

    attachEventListeners() {
        // Use event delegation to handle dynamically created buttons
        // Remove any existing delegated listeners first
        document.removeEventListener('click', this.delegatedClickHandler);
        
        // Create bound handler
        this.delegatedClickHandler = (e) => {
            // Check if clicked element has data-rfid-scan attribute
            const button = e.target.closest('[data-rfid-scan]');
            if (button) {
                e.preventDefault();
                const targetInputId = button.getAttribute('data-rfid-target');
                const targetInput = document.getElementById(targetInputId);
                if (targetInput) {
                    this.startScanning(button, targetInput);
                } else {
                    console.error('RFID Scanner: Target input not found:', targetInputId);
                }
            }
        };
        
        // Add delegated event listener to document
        document.addEventListener('click', this.delegatedClickHandler);

        // Add keyboard shortcut (Ctrl/Cmd + R) to start scanning
        document.removeEventListener('keydown', this.handleKeyboardShortcut);
        this.handleKeyboardShortcut = (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'r' && !e.shiftKey) {
                e.preventDefault();
                const activeElement = document.activeElement;
                if (activeElement && activeElement.type === 'text' && activeElement.name === 'rfid_tag') {
                    const scanButton = document.querySelector(`[data-rfid-target="${activeElement.id}"]`);
                    if (scanButton) {
                        this.startScanning(scanButton, activeElement);
                    }
                }
            }
        };
        document.addEventListener('keydown', this.handleKeyboardShortcut);
    }

    async startScanning(button, targetInput) {
        if (this.isScanning) {
            // If already scanning, offer manual entry
            if (confirm('Stop scanning and enter RFID manually?')) {
                this.stopScanning();
                setTimeout(() => this.handleManualEntry(), 100);
            }
            return;
        }

        this.scanButton = button;
        this.targetInput = targetInput;
        this.isScanning = true;

        // Update button state
        this.updateButtonState('scanning');

        // Simplified approach - try polling method (most reliable)
        this.showMessage('Starting RFID scanner... Scan with hardware or click button again for manual entry.', 'info');
        this.startPollingMethod();
    }

    async startWebSerialScanning() {
        try {
            // Request access to serial port (without filters to allow any device)
            this.port = await navigator.serial.requestPort();

            // Open the port
            await this.port.open({ 
                baudRate: 115200,
                dataBits: 8,
                parity: 'none',
                stopBits: 1,
                flowControl: 'none'
            });

            // Create reader
            this.reader = this.port.readable.getReader();

            // Start reading
            this.readSerialData();

            // Set timeout
            this.scanTimeout = setTimeout(() => {
                this.stopScanning();
                this.showMessage('Scan timeout - no RFID detected', 'warning');
            }, 30000); // 30 second timeout

            this.showMessage('Ready to scan RFID tag...', 'info');

        } catch (error) {
            throw new Error('Web Serial setup failed: ' + error.message);
        }
    }

    async readSerialData() {
        const decoder = new TextDecoder();
        let buffer = '';

        try {
            while (this.isScanning && this.reader) {
                const { value, done } = await this.reader.read();
                
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                
                // Look for RFID patterns in the buffer
                const lines = buffer.split('\n');
                buffer = lines.pop() || ''; // Keep incomplete line in buffer

                for (const line of lines) {
                    const rfidMatch = this.extractRFIDFromLine(line.trim());
                    if (rfidMatch) {
                        this.onRFIDScanned(rfidMatch);
                        return;
                    }
                }
            }
        } catch (error) {
            if (this.isScanning) {
                console.error('Serial reading error:', error);
                this.showMessage('Serial communication error', 'error');
            }
        }
    }

    extractRFIDFromLine(line) {
        // Common RFID patterns from different readers
        const patterns = [
            /RFID:\s*([A-Fa-f0-9]{6,20})/i,           // "RFID: 1234ABCD"
            /Card\s*(?:ID|UID):\s*([A-Fa-f0-9]{6,20})/i, // "Card ID: 1234ABCD"
            /UID:\s*([A-Fa-f0-9\s]{6,30})/i,          // "UID: 12 34 AB CD"
            /^([A-Fa-f0-9]{6,20})$/,                  // Just the hex string
            /Tag:\s*([A-Fa-f0-9]{6,20})/i,           // "Tag: 1234ABCD"
        ];

        for (const pattern of patterns) {
            const match = line.match(pattern);
            if (match) {
                // Clean up the RFID (remove spaces, convert to uppercase)
                return match[1].replace(/\s/g, '').toUpperCase();
            }
        }

        return null;
    }

    startPollingMethod() {
        this.showMessage('Polling server for RFID scans...', 'info');
        
        // Poll the server for new RFID scans
        this.pollingInterval = setInterval(async () => {
            try {
                const response = await fetch('../api/rfid_poll.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        last_tag: this.lastPolledTag,
                        timeout: 1000
                    })
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.success && data.rfid_tag && data.rfid_tag !== this.lastPolledTag) {
                        this.lastPolledTag = data.rfid_tag;
                        this.onRFIDScanned(data.rfid_tag);
                    }
                }
            } catch (error) {
                console.debug('Polling error:', error);
                // Don't show errors for polling, it's expected to fail sometimes
            }
        }, 1000); // Poll every second

        // Set timeout
        this.scanTimeout = setTimeout(() => {
            this.stopScanning();
            this.showMessage('Scan timeout - try using manual entry', 'warning');
        }, 30000);
    }

    onRFIDScanned(rfidTag) {
        if (!this.isScanning || !this.targetInput) return;

        // Clean and validate RFID
        const cleanRFID = this.cleanRFID(rfidTag);
        
        if (cleanRFID.length < 6) {
            this.showMessage('Invalid RFID format', 'error');
            return;
        }

        // Set the value in the target input
        this.targetInput.value = cleanRFID;
        
        // Trigger input events for form validation
        this.targetInput.dispatchEvent(new Event('input', { bubbles: true }));
        this.targetInput.dispatchEvent(new Event('change', { bubbles: true }));

        // Focus the input briefly to show the value was set
        this.targetInput.focus();
        this.targetInput.select();

        // Stop scanning
        this.stopScanning();
        
        // Show success message
        this.showMessage(`RFID scanned successfully: ${cleanRFID}`, 'success');

        // Call callback if provided
        if (this.onScanCallback) {
            this.onScanCallback(cleanRFID);
        }
    }

    cleanRFID(rfid) {
        // Remove any whitespace and convert to uppercase
        return rfid.replace(/\s/g, '').toUpperCase();
    }

    async stopScanning() {
        this.isScanning = false;

        // Clear timeout
        if (this.scanTimeout) {
            clearTimeout(this.scanTimeout);
            this.scanTimeout = null;
        }

        // Clear polling
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }

        // Close serial connection
        try {
            if (this.reader) {
                await this.reader.cancel();
                this.reader.releaseLock();
                this.reader = null;
            }
            
            if (this.port && this.port.readable) {
                await this.port.close();
                this.port = null;
            }
        } catch (error) {
            console.warn('Error closing serial connection:', error);
        }

        // Update button state
        this.updateButtonState('idle');
    }

    updateButtonState(state) {
        if (!this.scanButton) return;

        const button = this.scanButton;
        
        switch (state) {
            case 'scanning':
                button.textContent = 'Stop Scanning';
                button.classList.remove('btn-scan-rfid');
                button.classList.add('btn-stop-scanning');
                button.disabled = false;
                break;
                
            case 'idle':
            default:
                button.textContent = 'Scan RFID';
                button.classList.remove('btn-stop-scanning');
                button.classList.add('btn-scan-rfid');
                button.disabled = false;
                break;
        }
    }

    showMessage(message, type = 'info') {
        // Try to find existing notification area
        let notificationArea = document.getElementById('rfid-notifications');
        
        if (!notificationArea) {
            // Create notification area if it doesn't exist
            notificationArea = document.createElement('div');
            notificationArea.id = 'rfid-notifications';
            notificationArea.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                max-width: 400px;
            `;
            document.body.appendChild(notificationArea);
        }

        // Create notification element
        const notification = document.createElement('div');
        const typeClasses = {
            success: 'alert-success',
            error: 'alert-error',
            warning: 'alert-warning',
            info: 'alert-info'
        };
        
        notification.className = `alert ${typeClasses[type] || 'alert-info'}`;
        notification.style.cssText = `
            padding: 12px 16px;
            margin-bottom: 10px;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            animation: slideIn 0.3s ease-out;
        `;
        notification.innerHTML = `
            <strong>${type.charAt(0).toUpperCase() + type.slice(1)}:</strong> ${message}
            <button onclick="this.parentElement.remove()" style="float: right; background: none; border: none; font-size: 18px; cursor: pointer;">&times;</button>
        `;

        notificationArea.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);

        // Add CSS animation if not exists
        if (!document.getElementById('rfid-scanner-styles')) {
            const style = document.createElement('style');
            style.id = 'rfid-scanner-styles';
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                .alert {
                    border: 1px solid transparent;
                    border-radius: 4px;
                }
                .alert-success {
                    color: #155724;
                    background-color: #d4edda;
                    border-color: #c3e6cb;
                }
                .alert-error {
                    color: #721c24;
                    background-color: #f8d7da;
                    border-color: #f5c6cb;
                }
                .alert-warning {
                    color: #856404;
                    background-color: #fff3cd;
                    border-color: #ffeaa7;
                }
                .alert-info {
                    color: #0c5460;
                    background-color: #d1ecf1;
                    border-color: #bee5eb;
                }
            `;
            document.head.appendChild(style);
        }
    }

    // Public method to set scan callback
    setScanCallback(callback) {
        this.onScanCallback = callback;
    }

    // Public method to check if scanning is supported
    isSupported() {
        return this.supportsWebSerial || true; // Always return true due to fallback method
    }

    // Method to manually trigger scanning (for external use)
    async scanRFID(targetInputId) {
        const targetInput = document.getElementById(targetInputId);
        const scanButton = document.querySelector(`[data-rfid-target="${targetInputId}"]`);
        
        if (targetInput && scanButton) {
            await this.startScanning(scanButton, targetInput);
        } else {
            console.warn('RFID Scanner: Target input or button not found');
        }
    }

    // Handle manual RFID entry when scanning is stopped
    handleManualEntry() {
        if (!this.targetInput) return;
        
        const rfidValue = prompt('Enter RFID tag manually:');
        if (rfidValue && rfidValue.trim()) {
            const cleanRFID = this.cleanRFID(rfidValue.trim());
            if (cleanRFID.length >= 6) {
                this.targetInput.value = cleanRFID;
                this.targetInput.dispatchEvent(new Event('input', { bubbles: true }));
                this.targetInput.dispatchEvent(new Event('change', { bubbles: true }));
                this.showMessage(`RFID entered manually: ${cleanRFID}`, 'success');
            } else {
                this.showMessage('Invalid RFID format. Please enter at least 6 characters.', 'error');
            }
        }
    }
}

// Initialize global RFID scanner instance
let rfidScanner = null;

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        rfidScanner = new RFIDScanner();
    });
} else {
    rfidScanner = new RFIDScanner();
}

// Export for external use
window.RFIDScanner = RFIDScanner;
window.rfidScanner = rfidScanner;
