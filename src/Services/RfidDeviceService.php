<?php

namespace App\Services;

use App\Core\Database;
use App\Core\LoggingService;
use App\Core\SecurityService;
use Exception;

/**
 * RFID Device Management Service
 * 
 * Handles RFID device registration, communication, and monitoring
 * with proper security measures and error handling.
 */
class RfidDeviceService
{
    private static ?self $instance = null;
    private Database $database;
    private LoggingService $logger;
    private SecurityService $security;
    private array $activeDevices = [];
    private array $deviceStats = [];
    
    private function __construct()
    {
        $this->database = Database::getInstance();
        $this->logger = LoggingService::getInstance();
        $this->security = SecurityService::getInstance();
        $this->loadActiveDevices();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Register a new RFID device
     */
    public function registerDevice(array $deviceData): array
    {
        try {
            // Validate device data
            $validatedData = $this->validateDeviceData($deviceData);
            
            // Check if device already exists
            $existingDevice = $this->getDeviceByMac($validatedData['mac_address']);
            if ($existingDevice) {
                throw new Exception('Device with this MAC address already exists');
            }
            
            // Generate device credentials
            $deviceId = $this->generateDeviceId();
            $apiKey = $this->generateApiKey();
            $apiSecret = $this->generateApiSecret();
            
            $stmt = $this->database->prepare("
                INSERT INTO rfid_devices 
                (device_id, name, mac_address, ip_address, firmware_version, 
                 api_key, api_secret_hash, status, registered_at, last_seen) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
            ");
            
            $stmt->execute([
                $deviceId,
                $validatedData['name'],
                $validatedData['mac_address'],
                $validatedData['ip_address'],
                $validatedData['firmware_version'],
                $apiKey,
                password_hash($apiSecret, PASSWORD_DEFAULT)
            ]);
            
            $this->logger->info('RFID device registered', [
                'device_id' => $deviceId,
                'mac_address' => $validatedData['mac_address'],
                'ip_address' => $validatedData['ip_address']
            ]);
            
            return [
                'success' => true,
                'device_id' => $deviceId,
                'api_key' => $apiKey,
                'api_secret' => $apiSecret // Only returned once during registration
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Device registration failed', [
                'error' => $e->getMessage(),
                'data' => $deviceData
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Authenticate device API request
     */
    public function authenticateDevice(string $apiKey, string $signature, string $payload): ?array
    {
        try {
            $stmt = $this->database->prepare("
                SELECT device_id, name, api_secret_hash, status 
                FROM rfid_devices 
                WHERE api_key = ? AND status = 'active'
            ");
            $stmt->execute([$apiKey]);
            $device = $stmt->fetch();
            
            if (!$device) {
                $this->logger->warning('Device authentication failed - invalid API key', [
                    'api_key' => substr($apiKey, 0, 8) . '...'
                ]);
                return null;
            }
            
            // Verify signature
            $expectedSignature = hash_hmac('sha256', $payload, $device['api_secret_hash']);
            if (!hash_equals($expectedSignature, $signature)) {
                $this->logger->warning('Device authentication failed - invalid signature', [
                    'device_id' => $device['device_id']
                ]);
                return null;
            }
            
            // Update last seen
            $this->updateDeviceLastSeen($device['device_id']);
            
            return $device;
            
        } catch (Exception $e) {
            $this->logger->error('Device authentication error', [
                'error' => $e->getMessage(),
                'api_key' => substr($apiKey, 0, 8) . '...'
            ]);
            return null;
        }
    }
    
    /**
     * Process RFID scan from device
     */
    public function processScan(string $deviceId, array $scanData): array
    {
        try {
            // Validate scan data
            $validatedScan = $this->validateScanData($scanData);
            
            // Check for duplicate scan within time window
            if ($this->isDuplicateScan($deviceId, $validatedScan['tag_id'])) {
                return [
                    'success' => false,
                    'error' => 'Duplicate scan ignored',
                    'code' => 'DUPLICATE_SCAN'
                ];
            }
            
            // Look up user by tag
            $user = $this->getUserByTag($validatedScan['tag_id']);
            if (!$user) {
                $this->logUnknownTag($deviceId, $validatedScan['tag_id']);
                return [
                    'success' => false,
                    'error' => 'Unknown tag',
                    'code' => 'UNKNOWN_TAG'
                ];
            }
            
            // Check if user is active
            if ($user['status'] !== 'active') {
                return [
                    'success' => false,
                    'error' => 'User account inactive',
                    'code' => 'INACTIVE_USER'
                ];
            }
            
            // Check for active event
            $activeEvent = $this->getActiveEvent();
            if (!$activeEvent) {
                return [
                    'success' => false,
                    'error' => 'No active event',
                    'code' => 'NO_ACTIVE_EVENT'
                ];
            }
            
            // Process check-in
            $checkinResult = $this->processCheckin($user, $activeEvent, $deviceId, $validatedScan);
            
            if ($checkinResult['success']) {
                $this->updateDeviceStats($deviceId, 'successful_scans');
                
                return [
                    'success' => true,
                    'user' => [
                        'name' => $user['first_name'] . ' ' . $user['last_name'],
                        'email' => $user['email']
                    ],
                    'event' => [
                        'name' => $activeEvent['name']
                    ],
                    'checkin_id' => $checkinResult['checkin_id'],
                    'timestamp' => $checkinResult['timestamp']
                ];
            } else {
                return $checkinResult;
            }
            
        } catch (Exception $e) {
            $this->logger->error('Scan processing failed', [
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
                'scan_data' => $scanData
            ]);
            
            $this->updateDeviceStats($deviceId, 'failed_scans');
            
            return [
                'success' => false,
                'error' => 'Scan processing failed',
                'code' => 'PROCESSING_ERROR'
            ];
        }
    }
    
    /**
     * Get device status and statistics
     */
    public function getDeviceStatus(string $deviceId): ?array
    {
        try {
            $stmt = $this->database->prepare("
                SELECT d.*, 
                       COUNT(DISTINCT DATE(s.scanned_at)) as active_days,
                       COUNT(s.scan_id) as total_scans,
                       MAX(s.scanned_at) as last_scan
                FROM rfid_devices d
                LEFT JOIN rfid_scans s ON d.device_id = s.device_id
                WHERE d.device_id = ?
                GROUP BY d.device_id
            ");
            $stmt->execute([$deviceId]);
            $device = $stmt->fetch();
            
            if (!$device) {
                return null;
            }
            
            // Get recent scan statistics
            $recentStats = $this->getRecentScanStats($deviceId);
            
            return [
                'device_id' => $device['device_id'],
                'name' => $device['name'],
                'mac_address' => $device['mac_address'],
                'ip_address' => $device['ip_address'],
                'firmware_version' => $device['firmware_version'],
                'status' => $device['status'],
                'registered_at' => $device['registered_at'],
                'last_seen' => $device['last_seen'],
                'is_online' => $this->isDeviceOnline($device['last_seen']),
                'statistics' => [
                    'total_scans' => (int)$device['total_scans'],
                    'active_days' => (int)$device['active_days'],
                    'last_scan' => $device['last_scan'],
                    'recent_stats' => $recentStats
                ]
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get device status', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get all active devices
     */
    public function getAllDevices(): array
    {
        try {
            $stmt = $this->database->prepare("
                SELECT d.*, 
                       COUNT(s.scan_id) as total_scans,
                       MAX(s.scanned_at) as last_scan
                FROM rfid_devices d
                LEFT JOIN rfid_scans s ON d.device_id = s.device_id 
                WHERE d.status != 'deleted'
                GROUP BY d.device_id
                ORDER BY d.name
            ");
            $stmt->execute();
            $devices = $stmt->fetchAll();
            
            return array_map(function($device) {
                return [
                    'device_id' => $device['device_id'],
                    'name' => $device['name'],
                    'mac_address' => $device['mac_address'],
                    'ip_address' => $device['ip_address'],
                    'firmware_version' => $device['firmware_version'],
                    'status' => $device['status'],
                    'registered_at' => $device['registered_at'],
                    'last_seen' => $device['last_seen'],
                    'is_online' => $this->isDeviceOnline($device['last_seen']),
                    'total_scans' => (int)$device['total_scans'],
                    'last_scan' => $device['last_scan']
                ];
            }, $devices);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get devices list', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Update device configuration
     */
    public function updateDeviceConfig(string $deviceId, array $config): bool
    {
        try {
            $allowedFields = ['name', 'scan_timeout', 'duplicate_window', 'heartbeat_interval'];
            $updateFields = [];
            $updateValues = [];
            
            foreach ($config as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    $updateFields[] = "{$field} = ?";
                    $updateValues[] = $value;
                }
            }
            
            if (empty($updateFields)) {
                return false;
            }
            
            $updateValues[] = $deviceId;
            
            $stmt = $this->database->prepare("
                UPDATE rfid_devices 
                SET " . implode(', ', $updateFields) . ", updated_at = NOW()
                WHERE device_id = ?
            ");
            
            $result = $stmt->execute($updateValues);
            
            if ($result) {
                $this->logger->info('Device configuration updated', [
                    'device_id' => $deviceId,
                    'updated_fields' => array_keys($config)
                ]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to update device config', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Deactivate device
     */
    public function deactivateDevice(string $deviceId): bool
    {
        try {
            $stmt = $this->database->prepare("
                UPDATE rfid_devices 
                SET status = 'inactive', updated_at = NOW()
                WHERE device_id = ?
            ");
            
            $result = $stmt->execute([$deviceId]);
            
            if ($result) {
                unset($this->activeDevices[$deviceId]);
                $this->logger->info('Device deactivated', ['device_id' => $deviceId]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to deactivate device', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    // Private helper methods
    
    private function validateDeviceData(array $data): array
    {
        $required = ['name', 'mac_address', 'ip_address', 'firmware_version'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
        
        // Validate MAC address format
        if (!filter_var($data['mac_address'], FILTER_VALIDATE_MAC)) {
            throw new Exception('Invalid MAC address format');
        }
        
        // Validate IP address
        if (!filter_var($data['ip_address'], FILTER_VALIDATE_IP)) {
            throw new Exception('Invalid IP address format');
        }
        
        return [
            'name' => $this->security->sanitizeInput($data['name']),
            'mac_address' => strtoupper($data['mac_address']),
            'ip_address' => $data['ip_address'],
            'firmware_version' => $this->security->sanitizeInput($data['firmware_version'])
        ];
    }
    
    private function validateScanData(array $data): array
    {
        if (empty($data['tag_id'])) {
            throw new Exception('Missing tag ID');
        }
        
        // Validate tag ID format (hex string)
        if (!preg_match('/^[0-9A-Fa-f]{8,16}$/', $data['tag_id'])) {
            throw new Exception('Invalid tag ID format');
        }
        
        return [
            'tag_id' => strtoupper($data['tag_id']),
            'signal_strength' => isset($data['signal_strength']) ? (int)$data['signal_strength'] : null,
            'antenna' => isset($data['antenna']) ? (int)$data['antenna'] : null
        ];
    }
    
    private function generateDeviceId(): string
    {
        return 'DEV_' . bin2hex(random_bytes(8));
    }
    
    private function generateApiKey(): string
    {
        return bin2hex(random_bytes(16));
    }
    
    private function generateApiSecret(): string
    {
        return bin2hex(random_bytes(32));
    }
    
    private function getDeviceByMac(string $macAddress): ?array
    {
        $stmt = $this->database->prepare("
            SELECT * FROM rfid_devices WHERE mac_address = ? AND status != 'deleted'
        ");
        $stmt->execute([$macAddress]);
        return $stmt->fetch() ?: null;
    }
    
    private function updateDeviceLastSeen(string $deviceId): void
    {
        $stmt = $this->database->prepare("
            UPDATE rfid_devices SET last_seen = NOW() WHERE device_id = ?
        ");
        $stmt->execute([$deviceId]);
    }
    
    private function isDuplicateScan(string $deviceId, string $tagId): bool
    {
        $stmt = $this->database->prepare("
            SELECT COUNT(*) as count 
            FROM rfid_scans 
            WHERE device_id = ? AND tag_id = ? 
            AND scanned_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
        ");
        $stmt->execute([$deviceId, $tagId]);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    }
    
    private function getUserByTag(string $tagId): ?array
    {
        $stmt = $this->database->prepare("
            SELECT u.* FROM users u 
            INNER JOIN user_rfid_tags rt ON u.user_id = rt.user_id 
            WHERE rt.tag_id = ? AND rt.status = 'active' AND u.status = 'active'
        ");
        $stmt->execute([$tagId]);
        return $stmt->fetch() ?: null;
    }
    
    private function getActiveEvent(): ?array
    {
        $stmt = $this->database->prepare("
            SELECT * FROM events 
            WHERE status = 'active' 
            AND start_time <= NOW() 
            AND end_time >= NOW() 
            ORDER BY start_time ASC 
            LIMIT 1
        ");
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }
    
    private function processCheckin(array $user, array $event, string $deviceId, array $scanData): array
    {
        try {
            // Check if already checked in
            $stmt = $this->database->prepare("
                SELECT checkin_id FROM checkins 
                WHERE user_id = ? AND event_id = ? AND DATE(checked_in_at) = CURDATE()
            ");
            $stmt->execute([$user['user_id'], $event['event_id']]);
            $existingCheckin = $stmt->fetch();
            
            if ($existingCheckin) {
                return [
                    'success' => false,
                    'error' => 'Already checked in today',
                    'code' => 'ALREADY_CHECKED_IN'
                ];
            }
            
            // Create check-in record
            $checkinId = bin2hex(random_bytes(16));
            $timestamp = date('Y-m-d H:i:s');
            
            $stmt = $this->database->prepare("
                INSERT INTO checkins 
                (checkin_id, user_id, event_id, device_id, tag_id, checked_in_at, checkin_method) 
                VALUES (?, ?, ?, ?, ?, ?, 'rfid')
            ");
            
            $stmt->execute([
                $checkinId,
                $user['user_id'],
                $event['event_id'],
                $deviceId,
                $scanData['tag_id'],
                $timestamp
            ]);
            
            // Log the scan
            $this->logSuccessfulScan($deviceId, $scanData, $user['user_id'], $checkinId);
            
            return [
                'success' => true,
                'checkin_id' => $checkinId,
                'timestamp' => $timestamp
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Check-in processing failed', [
                'user_id' => $user['user_id'],
                'event_id' => $event['event_id'],
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Check-in processing failed',
                'code' => 'CHECKIN_ERROR'
            ];
        }
    }
    
    private function logSuccessfulScan(string $deviceId, array $scanData, string $userId, string $checkinId): void
    {
        try {
            $stmt = $this->database->prepare("
                INSERT INTO rfid_scans 
                (device_id, tag_id, user_id, checkin_id, signal_strength, antenna, scanned_at, status) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), 'success')
            ");
            
            $stmt->execute([
                $deviceId,
                $scanData['tag_id'],
                $userId,
                $checkinId,
                $scanData['signal_strength'],
                $scanData['antenna']
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to log successful scan', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function logUnknownTag(string $deviceId, string $tagId): void
    {
        try {
            $stmt = $this->database->prepare("
                INSERT INTO rfid_scans 
                (device_id, tag_id, scanned_at, status) 
                VALUES (?, ?, NOW(), 'unknown_tag')
            ");
            
            $stmt->execute([$deviceId, $tagId]);
        } catch (Exception $e) {
            $this->logger->error('Failed to log unknown tag scan', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function updateDeviceStats(string $deviceId, string $statType): void
    {
        if (!isset($this->deviceStats[$deviceId])) {
            $this->deviceStats[$deviceId] = [];
        }
        
        if (!isset($this->deviceStats[$deviceId][$statType])) {
            $this->deviceStats[$deviceId][$statType] = 0;
        }
        
        $this->deviceStats[$deviceId][$statType]++;
    }
    
    private function isDeviceOnline(string $lastSeen): bool
    {
        $lastSeenTime = strtotime($lastSeen);
        $offlineThreshold = time() - (5 * 60); // 5 minutes
        
        return $lastSeenTime > $offlineThreshold;
    }
    
    private function getRecentScanStats(string $deviceId): array
    {
        try {
            $stmt = $this->database->prepare("
                SELECT 
                    COUNT(*) as total_scans,
                    COUNT(CASE WHEN status = 'success' THEN 1 END) as successful_scans,
                    COUNT(CASE WHEN status = 'unknown_tag' THEN 1 END) as unknown_tags,
                    COUNT(CASE WHEN status = 'error' THEN 1 END) as errors
                FROM rfid_scans 
                WHERE device_id = ? AND scanned_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute([$deviceId]);
            return $stmt->fetch() ?: [];
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function loadActiveDevices(): void
    {
        try {
            $stmt = $this->database->prepare("
                SELECT device_id, name FROM rfid_devices WHERE status = 'active'
            ");
            $stmt->execute();
            $devices = $stmt->fetchAll();
            
            foreach ($devices as $device) {
                $this->activeDevices[$device['device_id']] = $device['name'];
            }
        } catch (Exception $e) {
            $this->logger->error('Failed to load active devices', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
