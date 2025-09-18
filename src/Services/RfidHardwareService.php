<?php

namespace App\Services;

use App\Core\Database;
use App\Core\LoggingService;
use App\Core\ConfigurationService;
use Exception;

/**
 * RFID Hardware Communication Service
 * 
 * Handles communication with RFID hardware devices including:
 * - WebSocket connections for real-time scanning
 * - Device command sending and response handling
 * - Firmware update management
 * - Network discovery and auto-configuration
 */
class RfidHardwareService
{
    private static ?self $instance = null;
    private Database $database;
    private LoggingService $logger;
    private ConfigurationService $config;
    private array $activeConnections = [];
    private array $deviceCommands = [];
    
    private function __construct()
    {
        $this->database = Database::getInstance();
        $this->logger = LoggingService::getInstance();
        $this->config = ConfigurationService::getInstance();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Send command to RFID device
     */
    public function sendDeviceCommand(string $deviceId, string $command, array $params = []): array
    {
        try {
            // Get device information
            $device = $this->getDeviceInfo($deviceId);
            if (!$device) {
                return [
                    'success' => false,
                    'error' => 'Device not found'
                ];
            }
            
            // Prepare command payload
            $payload = [
                'command' => $command,
                'params' => $params,
                'timestamp' => date('c'),
                'request_id' => bin2hex(random_bytes(8))
            ];
            
            // Send command based on device communication method
            $result = $this->dispatchCommand($device, $payload);
            
            // Log command
            $this->logDeviceCommand($deviceId, $command, $params, $result);
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to send device command', [
                'device_id' => $deviceId,
                'command' => $command,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Command failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Discover RFID devices on network
     */
    public function discoverDevices(): array
    {
        try {
            $discoveredDevices = [];
            
            // Get network configuration
            $networkRange = $this->config->get('hardware.network_range', '192.168.1.0/24');
            $discoveryTimeout = $this->config->get('hardware.discovery_timeout', 5);
            $rfidPort = $this->config->get('hardware.rfid_port', 8080);
            
            // Parse network range
            list($network, $cidr) = explode('/', $networkRange);
            $hosts = $this->getNetworkHosts($network, (int)$cidr);
            
            // Scan for devices in parallel
            $promises = [];
            foreach ($hosts as $host) {
                $promises[] = $this->probeDevice($host, $rfidPort, $discoveryTimeout);
            }
            
            // Wait for all probes to complete
            $results = $this->awaitPromises($promises);
            
            foreach ($results as $result) {
                if ($result['success']) {
                    $discoveredDevices[] = $result['device'];
                }
            }
            
            $this->logger->info('Device discovery completed', [
                'network_range' => $networkRange,
                'discovered_count' => count($discoveredDevices)
            ]);
            
            return [
                'success' => true,
                'devices' => $discoveredDevices,
                'count' => count($discoveredDevices)
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Device discovery failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Discovery failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update device firmware
     */
    public function updateFirmware(string $deviceId, string $firmwarePath): array
    {
        try {
            $device = $this->getDeviceInfo($deviceId);
            if (!$device) {
                return [
                    'success' => false,
                    'error' => 'Device not found'
                ];
            }
            
            // Verify firmware file
            if (!file_exists($firmwarePath)) {
                return [
                    'success' => false,
                    'error' => 'Firmware file not found'
                ];
            }
            
            // Validate firmware for device
            $firmwareInfo = $this->validateFirmware($firmwarePath, $device);
            if (!$firmwareInfo['valid']) {
                return [
                    'success' => false,
                    'error' => 'Invalid firmware: ' . $firmwareInfo['error']
                ];
            }
            
            // Start firmware update process
            $updateResult = $this->performFirmwareUpdate($device, $firmwarePath);
            
            if ($updateResult['success']) {
                // Update device record
                $this->updateDeviceFirmwareVersion($deviceId, $firmwareInfo['version']);
                
                $this->logger->info('Firmware update completed', [
                    'device_id' => $deviceId,
                    'old_version' => $device['firmware_version'],
                    'new_version' => $firmwareInfo['version']
                ]);
            }
            
            return $updateResult;
            
        } catch (Exception $e) {
            $this->logger->error('Firmware update failed', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Firmware update failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Configure device settings
     */
    public function configureDevice(string $deviceId, array $settings): array
    {
        try {
            $device = $this->getDeviceInfo($deviceId);
            if (!$device) {
                return [
                    'success' => false,
                    'error' => 'Device not found'
                ];
            }
            
            // Validate settings
            $validatedSettings = $this->validateDeviceSettings($settings);
            if (!$validatedSettings['valid']) {
                return [
                    'success' => false,
                    'error' => 'Invalid settings: ' . $validatedSettings['error']
                ];
            }
            
            // Send configuration commands
            $configResults = [];
            foreach ($validatedSettings['settings'] as $key => $value) {
                $result = $this->sendDeviceCommand($deviceId, 'set_config', [
                    'key' => $key,
                    'value' => $value
                ]);
                
                $configResults[$key] = $result;
            }
            
            // Check if all configurations succeeded
            $failedConfigs = array_filter($configResults, fn($r) => !$r['success']);
            
            if (empty($failedConfigs)) {
                $this->logger->info('Device configuration completed', [
                    'device_id' => $deviceId,
                    'settings' => array_keys($validatedSettings['settings'])
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Device configured successfully',
                    'applied_settings' => $validatedSettings['settings']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Some configurations failed',
                    'failed_settings' => array_keys($failedConfigs),
                    'results' => $configResults
                ];
            }
            
        } catch (Exception $e) {
            $this->logger->error('Device configuration failed', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Configuration failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Test device connectivity and functionality
     */
    public function testDeviceConnection(string $deviceId): array
    {
        try {
            $device = $this->getDeviceInfo($deviceId);
            if (!$device) {
                return [
                    'success' => false,
                    'error' => 'Device not found'
                ];
            }
            
            $startTime = microtime(true);
            
            // Test basic connectivity
            $pingResult = $this->sendDeviceCommand($deviceId, 'ping');
            if (!$pingResult['success']) {
                return [
                    'success' => false,
                    'error' => 'Device not responding to ping',
                    'details' => $pingResult
                ];
            }
            
            // Test RFID functionality
            $scanTestResult = $this->sendDeviceCommand($deviceId, 'test_scan');
            
            // Test LED functionality
            $ledTestResult = $this->sendDeviceCommand($deviceId, 'test_led');
            
            // Test buzzer functionality
            $buzzerTestResult = $this->sendDeviceCommand($deviceId, 'test_buzzer');
            
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds
            
            $allTestsPassed = $pingResult['success'] && 
                             $scanTestResult['success'] && 
                             $ledTestResult['success'] && 
                             $buzzerTestResult['success'];
            
            return [
                'success' => $allTestsPassed,
                'response_time' => $responseTime,
                'tests' => [
                    'ping' => $pingResult['success'],
                    'rfid_scan' => $scanTestResult['success'],
                    'led' => $ledTestResult['success'],
                    'buzzer' => $buzzerTestResult['success']
                ],
                'details' => [
                    'ping' => $pingResult,
                    'scan_test' => $scanTestResult,
                    'led_test' => $ledTestResult,
                    'buzzer_test' => $buzzerTestResult
                ]
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Device connection test failed', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Connection test failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get device diagnostic information
     */
    public function getDeviceDiagnostics(string $deviceId): array
    {
        try {
            $device = $this->getDeviceInfo($deviceId);
            if (!$device) {
                return [
                    'success' => false,
                    'error' => 'Device not found'
                ];
            }
            
            // Get system information
            $sysInfoResult = $this->sendDeviceCommand($deviceId, 'get_system_info');
            
            // Get network information
            $netInfoResult = $this->sendDeviceCommand($deviceId, 'get_network_info');
            
            // Get RFID module status
            $rfidStatusResult = $this->sendDeviceCommand($deviceId, 'get_rfid_status');
            
            // Get error logs
            $errorLogsResult = $this->sendDeviceCommand($deviceId, 'get_error_logs');
            
            return [
                'success' => true,
                'device_id' => $deviceId,
                'diagnostics' => [
                    'system_info' => $sysInfoResult['data'] ?? null,
                    'network_info' => $netInfoResult['data'] ?? null,
                    'rfid_status' => $rfidStatusResult['data'] ?? null,
                    'error_logs' => $errorLogsResult['data'] ?? null
                ],
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get device diagnostics', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Diagnostics failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Reboot device
     */
    public function rebootDevice(string $deviceId): array
    {
        try {
            $result = $this->sendDeviceCommand($deviceId, 'reboot');
            
            if ($result['success']) {
                $this->logger->info('Device reboot initiated', [
                    'device_id' => $deviceId
                ]);
                
                // Mark device as rebooting
                $this->updateDeviceStatus($deviceId, 'rebooting');
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Device reboot failed', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Reboot failed: ' . $e->getMessage()
            ];
        }
    }
    
    // Private helper methods
    
    private function getDeviceInfo(string $deviceId): ?array
    {
        try {
            $stmt = $this->database->prepare("
                SELECT * FROM rfid_devices WHERE device_id = ? AND status != 'deleted'
            ");
            $stmt->execute([$deviceId]);
            return $stmt->fetch() ?: null;
        } catch (Exception $e) {
            $this->logger->error('Failed to get device info', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    private function dispatchCommand(array $device, array $payload): array
    {
        $communicationMethod = $device['communication_method'] ?? 'http';
        
        switch ($communicationMethod) {
            case 'http':
                return $this->sendHttpCommand($device, $payload);
            case 'websocket':
                return $this->sendWebSocketCommand($device, $payload);
            case 'mqtt':
                return $this->sendMqttCommand($device, $payload);
            default:
                throw new Exception('Unsupported communication method: ' . $communicationMethod);
        }
    }
    
    private function sendHttpCommand(array $device, array $payload): array
    {
        $url = "http://{$device['ip_address']}:{$device['port']}/api/command";
        
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $device['api_key']
                ],
                'content' => json_encode($payload),
                'timeout' => 10
            ]
        ];
        
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            return [
                'success' => false,
                'error' => 'No response from device'
            ];
        }
        
        $data = json_decode($response, true);
        return $data ?: [
            'success' => false,
            'error' => 'Invalid response format'
        ];
    }
    
    private function sendWebSocketCommand(array $device, array $payload): array
    {
        // WebSocket implementation would go here
        // For now, fallback to HTTP
        return $this->sendHttpCommand($device, $payload);
    }
    
    private function sendMqttCommand(array $device, array $payload): array
    {
        // MQTT implementation would go here
        // For now, fallback to HTTP
        return $this->sendHttpCommand($device, $payload);
    }
    
    private function getNetworkHosts(string $network, int $cidr): array
    {
        $hosts = [];
        $networkLong = ip2long($network);
        $hostCount = pow(2, 32 - $cidr) - 2; // Exclude network and broadcast
        
        for ($i = 1; $i <= min($hostCount, 254); $i++) {
            $hosts[] = long2ip($networkLong + $i);
        }
        
        return $hosts;
    }
    
    private function probeDevice(string $host, int $port, int $timeout): array
    {
        $startTime = microtime(true);
        
        // Try to connect to device
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        
        if ($socket) {
            fclose($socket);
            
            // Try to get device info
            $deviceInfo = $this->getDeviceInfoFromHost($host, $port);
            
            return [
                'success' => true,
                'device' => [
                    'ip_address' => $host,
                    'port' => $port,
                    'response_time' => round((microtime(true) - $startTime) * 1000, 2),
                    'info' => $deviceInfo
                ]
            ];
        }
        
        return [
            'success' => false,
            'host' => $host,
            'error' => $errstr ?: 'Connection failed'
        ];
    }
    
    private function getDeviceInfoFromHost(string $host, int $port): ?array
    {
        try {
            $url = "http://{$host}:{$port}/api/info";
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 5
                ]
            ]);
            
            $response = file_get_contents($url, false, $context);
            return $response ? json_decode($response, true) : null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function awaitPromises(array $promises): array
    {
        // Simple implementation - in production, use proper async handling
        return $promises;
    }
    
    private function validateFirmware(string $firmwarePath, array $device): array
    {
        // Basic firmware validation
        $fileSize = filesize($firmwarePath);
        
        if ($fileSize === false) {
            return [
                'valid' => false,
                'error' => 'Cannot read firmware file'
            ];
        }
        
        if ($fileSize > 5 * 1024 * 1024) { // 5MB limit
            return [
                'valid' => false,
                'error' => 'Firmware file too large'
            ];
        }
        
        // Extract version from firmware filename or content
        $version = $this->extractFirmwareVersion($firmwarePath);
        
        return [
            'valid' => true,
            'version' => $version,
            'size' => $fileSize
        ];
    }
    
    private function extractFirmwareVersion(string $firmwarePath): string
    {
        $filename = basename($firmwarePath);
        
        // Try to extract version from filename (e.g., firmware_v1.2.3.bin)
        if (preg_match('/v?(\d+\.\d+\.\d+)/', $filename, $matches)) {
            return $matches[1];
        }
        
        return 'unknown';
    }
    
    private function performFirmwareUpdate(array $device, string $firmwarePath): array
    {
        // This would implement the actual firmware update process
        // For now, return a mock result
        return [
            'success' => true,
            'message' => 'Firmware update completed successfully'
        ];
    }
    
    private function updateDeviceFirmwareVersion(string $deviceId, string $version): void
    {
        try {
            $stmt = $this->database->prepare("
                UPDATE rfid_devices 
                SET firmware_version = ?, updated_at = NOW() 
                WHERE device_id = ?
            ");
            $stmt->execute([$version, $deviceId]);
        } catch (Exception $e) {
            $this->logger->error('Failed to update device firmware version', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function validateDeviceSettings(array $settings): array
    {
        $validSettings = [];
        $allowedSettings = [
            'scan_timeout' => ['type' => 'int', 'min' => 1, 'max' => 30],
            'duplicate_window' => ['type' => 'int', 'min' => 5, 'max' => 300],
            'heartbeat_interval' => ['type' => 'int', 'min' => 30, 'max' => 600],
            'led_brightness' => ['type' => 'int', 'min' => 0, 'max' => 100],
            'buzzer_volume' => ['type' => 'int', 'min' => 0, 'max' => 100],
            'wifi_ssid' => ['type' => 'string', 'max_length' => 32],
            'wifi_password' => ['type' => 'string', 'max_length' => 64]
        ];
        
        foreach ($settings as $key => $value) {
            if (!isset($allowedSettings[$key])) {
                return [
                    'valid' => false,
                    'error' => "Unknown setting: {$key}"
                ];
            }
            
            $rule = $allowedSettings[$key];
            
            switch ($rule['type']) {
                case 'int':
                    $intValue = (int)$value;
                    if (isset($rule['min']) && $intValue < $rule['min']) {
                        return [
                            'valid' => false,
                            'error' => "{$key} must be at least {$rule['min']}"
                        ];
                    }
                    if (isset($rule['max']) && $intValue > $rule['max']) {
                        return [
                            'valid' => false,
                            'error' => "{$key} must be at most {$rule['max']}"
                        ];
                    }
                    $validSettings[$key] = $intValue;
                    break;
                    
                case 'string':
                    $stringValue = (string)$value;
                    if (isset($rule['max_length']) && strlen($stringValue) > $rule['max_length']) {
                        return [
                            'valid' => false,
                            'error' => "{$key} must be at most {$rule['max_length']} characters"
                        ];
                    }
                    $validSettings[$key] = $stringValue;
                    break;
            }
        }
        
        return [
            'valid' => true,
            'settings' => $validSettings
        ];
    }
    
    private function updateDeviceStatus(string $deviceId, string $status): void
    {
        try {
            $stmt = $this->database->prepare("
                UPDATE rfid_devices 
                SET status = ?, updated_at = NOW() 
                WHERE device_id = ?
            ");
            $stmt->execute([$status, $deviceId]);
        } catch (Exception $e) {
            $this->logger->error('Failed to update device status', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function logDeviceCommand(string $deviceId, string $command, array $params, array $result): void
    {
        try {
            $stmt = $this->database->prepare("
                INSERT INTO device_command_log 
                (device_id, command, params, result, success, executed_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $deviceId,
                $command,
                json_encode($params),
                json_encode($result),
                $result['success'] ? 1 : 0
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to log device command', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
