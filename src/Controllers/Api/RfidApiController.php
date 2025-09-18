<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers\Api;

use RfidCheckin\Controllers\BaseApiController;
use RfidCheckin\Repositories\CheckinRepository;
use RfidCheckin\Repositories\UserRepository;
use RfidCheckin\Repositories\EventRepository;
use RfidCheckin\Services\ConfigurationService;
use App\Services\RfidDeviceService;
use Exception;

/**
 * RFID API Controller
 * 
 * Handles all RFID-related API operations including tag scanning,
 * check-in processing, device management, and real-time updates.
 * Replaces scattered RFID functionality across multiple files.
 * 
 * Endpoints:
 * - POST /api/rfid/checkin - Process RFID check-in
 * - POST /api/rfid/poll - Poll for RFID tags (device communication)
 * - GET /api/rfid/status - Get RFID system status
 * - POST /api/rfid/register - Register RFID tag to user
 * - DELETE /api/rfid/unregister - Remove RFID tag from user
 * 
 * @package RfidCheckin\Controllers\Api
 * @version 1.0.0
 * @author Senior Development Team
 */
class RfidApiController extends BaseApiController
{
    private CheckinRepository $checkinRepo;
    private UserRepository $userRepo;
    private EventRepository $eventRepo;
    private RfidDeviceService $rfidDeviceService;
    
    protected array $allowedMethods = ['GET', 'POST', 'DELETE', 'PUT'];
    protected bool $requiresAuth = true;
    protected int $rateLimitPerMinute = 120; // Higher limit for RFID operations

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->checkinRepo = new CheckinRepository();
        $this->userRepo = new UserRepository();
        $this->eventRepo = new EventRepository();
        $this->rfidDeviceService = RfidDeviceService::getInstance();
    }

    /**
     * Route handler - determines which method to call
     */
    public function handleRequest(): void
    {
        $this->executeWithErrorHandling(function() {
            $path = $_SERVER['PATH_INFO'] ?? '';
            $method = $_SERVER['REQUEST_METHOD'];
            
            switch ($path) {
                case '/checkin':
                    if ($method === 'POST') {
                        $this->processCheckin();
                    } else {
                        $this->respondError('Method not allowed', 405);
                    }
                    break;
                    
                case '/poll':
                    if ($method === 'POST') {
                        $this->pollForTags();
                    } else {
                        $this->respondError('Method not allowed', 405);
                    }
                    break;
                    
                case '/heartbeat':
                    if ($method === 'POST') {
                        $this->deviceHeartbeat();
                    } else {
                        $this->respondError('Method not allowed', 405);
                    }
                    break;
                    
                case '/scan':
                    if ($method === 'POST') {
                        $this->processDeviceScan();
                    } else {
                        $this->respondError('Method not allowed', 405);
                    }
                    break;
                    
                case '/status':
                    if ($method === 'GET') {
                        $this->getSystemStatus();
                    } else {
                        $this->respondError('Method not allowed', 405);
                    }
                    break;
                    
                case '/register':
                    if ($method === 'POST') {
                        $this->registerTag();
                    } else {
                        $this->respondError('Method not allowed', 405);
                    }
                    break;
                    
                case '/device/register':
                    if ($method === 'POST') {
                        $this->registerDevice();
                    } else {
                        $this->respondError('Method not allowed', 405);
                    }
                    break;
                    
                case '/devices':
                    if ($method === 'GET') {
                        $this->getAllDevices();
                    } else {
                        $this->respondError('Method not allowed', 405);
                    }
                    break;
                    
                case '/unregister':
                    if ($method === 'DELETE') {
                        $this->unregisterTag();
                    } else {
                        $this->respondError('Method not allowed', 405);
                    }
                    break;
                    
                default:
                    // Handle dynamic routes like /device/{id}/status
                    if (preg_match('/^\/device\/([^\/]+)\/status$/', $path, $matches)) {
                        if ($method === 'GET') {
                            $this->getDeviceStatus($matches[1]);
                        } else {
                            $this->respondError('Method not allowed', 405);
                        }
                    } elseif (preg_match('/^\/device\/([^\/]+)\/config$/', $path, $matches)) {
                        if ($method === 'PUT') {
                            $this->updateDeviceConfig($matches[1]);
                        } else {
                            $this->respondError('Method not allowed', 405);
                        }
                    } elseif (preg_match('/^\/device\/([^\/]+)\/test$/', $path, $matches)) {
                        if ($method === 'POST') {
                            $this->testDevice($matches[1]);
                        } else {
                            $this->respondError('Method not allowed', 405);
                        }
                    } elseif (preg_match('/^\/device\/([^\/]+)$/', $path, $matches)) {
                        if ($method === 'DELETE') {
                            $this->deactivateDevice($matches[1]);
                        } else {
                            $this->respondError('Method not allowed', 405);
                        }
                    } else {
                        $this->respondError('Endpoint not found', 404);
                    }
            }
        });
    }

    /**
     * Process RFID check-in
     * 
     * POST /api/rfid/checkin
     * Body: {
     *   "rfid_tag": "ABC123",
     *   "event_id": 1,
     *   "device_id": "scanner_01"
     * }
     */
    private function processCheckin(): void
    {
        $input = $this->sanitizeInput($this->getInput());
        
        // Validate required fields
        $errors = $this->validateRequired($input, ['rfid_tag']);
        if (!empty($errors)) {
            $this->respondValidationError($errors);
            return;
        }

        // Validate RFID tag format
        if (!$this->isValidRfidTag($input['rfid_tag'])) {
            $this->respondValidationError(['rfid_tag' => 'Invalid RFID tag format']);
            return;
        }

        $rfidTag = strtoupper(trim($input['rfid_tag']));
        $eventId = !empty($input['event_id']) ? (int) $input['event_id'] : null;
        $deviceId = $input['device_id'] ?? 'unknown';

        // Additional metadata
        $metadata = [
            'api_version' => '2.0',
            'device_timestamp' => $input['timestamp'] ?? date('c'),
            'device_info' => $input['device_info'] ?? null,
            'signal_strength' => $input['signal_strength'] ?? null,
        ];

        // Process the check-in
        $result = $this->checkinRepo->processRfidCheckin($rfidTag, $eventId, $deviceId, $metadata);

        if ($result['success']) {
            $this->respondSuccess($result, 200, [
                'checkin_method' => 'rfid',
                'device_id' => $deviceId
            ]);
        } else {
            $statusCode = $this->getCheckinErrorStatusCode($result['error_code']);
            $this->respondError($result['message'], $statusCode, [
                'error_code' => $result['error_code'],
                'rfid_tag' => $rfidTag
            ]);
        }
    }

    /**
     * Device heartbeat endpoint (for hardware devices)
     * 
     * POST /api/rfid/heartbeat
     * Headers: X-API-Key, X-Signature
     * Body: {
     *   "firmware_version": "1.0.0",
     *   "uptime": 3600,
     *   "free_memory": 2048,
     *   "wifi_signal": -45
     * }
     */
    private function deviceHeartbeat(): void
    {
        // This endpoint doesn't require session auth, uses device auth instead
        $this->requiresAuth = false;
        
        try {
            // Authenticate device
            $device = $this->authenticateDevice();
            if (!$device) {
                $this->respondError('Device authentication failed', 401);
                return;
            }
            
            $input = $this->getInput();
            
            // Validate heartbeat data
            $errors = $this->validateRequired($input, ['firmware_version', 'uptime', 'free_memory']);
            if (!empty($errors)) {
                $this->respondValidationError($errors);
                return;
            }
            
            $heartbeatData = [
                'firmware_version' => $input['firmware_version'],
                'uptime' => (int)$input['uptime'],
                'free_memory' => (int)$input['free_memory'],
                'wifi_signal' => isset($input['wifi_signal']) ? (int)$input['wifi_signal'] : null,
                'last_scan' => $input['last_scan'] ?? null
            ];
            
            // Update device status (would be implemented in RfidDeviceService)
            $result = $this->rfidDeviceService->updateDeviceStatus($device['device_id'], $heartbeatData);
            
            if ($result) {
                $this->respondSuccess([
                    'device_id' => $device['device_id'],
                    'status' => 'online',
                    'timestamp' => date('c'),
                    'next_heartbeat' => date('c', time() + 60)
                ]);
            } else {
                $this->respondError('Failed to update device status', 500);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Device heartbeat error', [
                'error' => $e->getMessage()
            ]);
            $this->respondError('Heartbeat processing failed', 500);
        }
    }

    /**
     * Process RFID scan from hardware device
     * 
     * POST /api/rfid/scan
     * Headers: X-API-Key, X-Signature
     * Body: {
     *   "tag_id": "ABC123DEF456",
     *   "signal_strength": -35,
     *   "antenna": 1,
     *   "timestamp": "2024-01-01T12:00:00Z"
     * }
     */
    private function processDeviceScan(): void
    {
        // This endpoint doesn't require session auth, uses device auth instead
        $this->requiresAuth = false;
        
        try {
            // Authenticate device
            $device = $this->authenticateDevice();
            if (!$device) {
                $this->respondError('Device authentication failed', 401);
                return;
            }
            
            $input = $this->getInput();
            
            // Validate scan data
            $errors = $this->validateRequired($input, ['tag_id']);
            if (!empty($errors)) {
                $this->respondValidationError($errors);
                return;
            }
            
            if (!$this->isValidRfidTag($input['tag_id'])) {
                $this->respondValidationError(['tag_id' => 'Invalid RFID tag format']);
                return;
            }
            
            $scanData = [
                'tag_id' => strtoupper($input['tag_id']),
                'signal_strength' => isset($input['signal_strength']) ? (int)$input['signal_strength'] : null,
                'antenna' => isset($input['antenna']) ? (int)$input['antenna'] : null,
                'timestamp' => $input['timestamp'] ?? date('c')
            ];
            
            // Process the scan through device service
            $result = $this->rfidDeviceService->processScan($device['device_id'], $scanData);
            
            if ($result['success']) {
                $this->respondSuccess([
                    'scan_result' => 'success',
                    'user' => $result['user'],
                    'event' => $result['event'],
                    'checkin_id' => $result['checkin_id'],
                    'timestamp' => $result['timestamp'],
                    'message' => 'Check-in successful'
                ]);
            } else {
                // Send appropriate error response based on error code
                switch ($result['code']) {
                    case 'DUPLICATE_SCAN':
                        $this->respondError($result['error'], 409, ['code' => $result['code']]);
                        break;
                    case 'UNKNOWN_TAG':
                        $this->respondError($result['error'], 404, ['code' => $result['code']]);
                        break;
                    case 'INACTIVE_USER':
                        $this->respondError($result['error'], 403, ['code' => $result['code']]);
                        break;
                    case 'NO_ACTIVE_EVENT':
                        $this->respondError($result['error'], 412, ['code' => $result['code']]);
                        break;
                    case 'ALREADY_CHECKED_IN':
                        $this->respondError($result['error'], 409, ['code' => $result['code']]);
                        break;
                    default:
                        $this->respondError($result['error'], 500, ['code' => $result['code']]);
                }
            }
            
        } catch (Exception $e) {
            $this->logger->error('Device scan error', [
                'error' => $e->getMessage()
            ]);
            $this->respondError('Scan processing failed', 500);
        }
    }

    /**
     * Register a new RFID device
     * 
     * POST /api/rfid/device/register
     * Body: {
     *   "name": "Scanner 01",
     *   "mac_address": "AA:BB:CC:DD:EE:FF",
     *   "ip_address": "192.168.1.100",
     *   "firmware_version": "1.0.0",
     *   "location": "Main Entrance"
     * }
     */
    private function registerDevice(): void
    {
        // Check permissions
        if (!$this->hasPermissions(['admin', 'manage_devices'])) {
            $this->respondError('Insufficient permissions', 403);
            return;
        }
        
        $input = $this->sanitizeInput($this->getInput());
        
        $errors = $this->validateRequired($input, ['name', 'mac_address', 'ip_address', 'firmware_version']);
        if (!empty($errors)) {
            $this->respondValidationError($errors);
            return;
        }
        
        // Validate MAC address
        if (!filter_var($input['mac_address'], FILTER_VALIDATE_MAC)) {
            $this->respondValidationError(['mac_address' => 'Invalid MAC address format']);
            return;
        }
        
        // Validate IP address
        if (!filter_var($input['ip_address'], FILTER_VALIDATE_IP)) {
            $this->respondValidationError(['ip_address' => 'Invalid IP address format']);
            return;
        }
        
        $deviceData = [
            'name' => $input['name'],
            'mac_address' => strtoupper($input['mac_address']),
            'ip_address' => $input['ip_address'],
            'firmware_version' => $input['firmware_version'],
            'location' => $input['location'] ?? null
        ];
        
        $result = $this->rfidDeviceService->registerDevice($deviceData);
        
        if ($result['success']) {
            $this->logger->info('RFID device registered', [
                'device_id' => $result['device_id'],
                'mac_address' => $deviceData['mac_address'],
                'registered_by' => $this->getCurrentUserId()
            ]);
            
            $this->respondSuccess([
                'device_id' => $result['device_id'],
                'api_key' => $result['api_key'],
                'api_secret' => $result['api_secret'],
                'message' => 'Device registered successfully'
            ]);
        } else {
            $this->respondError($result['error'], 400);
        }
    }

    /**
     * Get device status
     * 
     * GET /api/rfid/device/{device_id}/status
     */
    private function getDeviceStatus(string $deviceId): void
    {
        if (!$this->hasPermissions(['admin', 'view_devices'])) {
            $this->respondError('Insufficient permissions', 403);
            return;
        }
        
        $status = $this->rfidDeviceService->getDeviceStatus($deviceId);
        
        if ($status) {
            $this->respondSuccess($status);
        } else {
            $this->respondError('Device not found', 404);
        }
    }

    /**
     * Get all devices
     * 
     * GET /api/rfid/devices
     */
    private function getAllDevices(): void
    {
        if (!$this->hasPermissions(['admin', 'view_devices'])) {
            $this->respondError('Insufficient permissions', 403);
            return;
        }
        
        $devices = $this->rfidDeviceService->getAllDevices();
        
        $this->respondSuccess([
            'devices' => $devices,
            'total_count' => count($devices),
            'online_count' => count(array_filter($devices, fn($d) => $d['is_online']))
        ]);
    }

    /**
     * Update device configuration
     * 
     * PUT /api/rfid/device/{device_id}/config
     */
    private function updateDeviceConfig(string $deviceId): void
    {
        if (!$this->hasPermissions(['admin', 'manage_devices'])) {
            $this->respondError('Insufficient permissions', 403);
            return;
        }
        
        $input = $this->sanitizeInput($this->getInput());
        
        $allowedFields = ['name', 'location', 'scan_timeout', 'duplicate_window', 'heartbeat_interval'];
        $config = [];
        
        foreach ($input as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $config[$field] = $value;
            }
        }
        
        if (empty($config)) {
            $this->respondError('No valid configuration fields provided', 400);
            return;
        }
        
        $result = $this->rfidDeviceService->updateDeviceConfig($deviceId, $config);
        
        if ($result) {
            $this->respondSuccess([
                'device_id' => $deviceId,
                'message' => 'Device configuration updated successfully'
            ]);
        } else {
            $this->respondError('Failed to update device configuration', 400);
        }
    }

    /**
     * Test device connection
     * 
     * POST /api/rfid/device/{device_id}/test
     */
    private function testDevice(string $deviceId): void
    {
        if (!$this->hasPermissions(['admin', 'manage_devices'])) {
            $this->respondError('Insufficient permissions', 403);
            return;
        }
        
        // For now, just check if device exists and is online
        $status = $this->rfidDeviceService->getDeviceStatus($deviceId);
        
        if (!$status) {
            $this->respondError('Device not found', 404);
            return;
        }
        
        $this->respondSuccess([
            'device_id' => $deviceId,
            'test_result' => $status['is_online'] ? 'success' : 'offline',
            'status' => $status['status'],
            'last_seen' => $status['last_seen'],
            'message' => $status['is_online'] ? 'Device is online and responding' : 'Device appears to be offline'
        ]);
    }

    /**
     * Deactivate device
     * 
     * DELETE /api/rfid/device/{device_id}
     */
    private function deactivateDevice(string $deviceId): void
    {
        if (!$this->hasPermissions(['admin', 'manage_devices'])) {
            $this->respondError('Insufficient permissions', 403);
            return;
        }
        
        $result = $this->rfidDeviceService->deactivateDevice($deviceId);
        
        if ($result) {
            $this->logger->info('RFID device deactivated', [
                'device_id' => $deviceId,
                'deactivated_by' => $this->getCurrentUserId()
            ]);
            
            $this->respondSuccess([
                'device_id' => $deviceId,
                'message' => 'Device deactivated successfully'
            ]);
        } else {
            $this->respondError('Failed to deactivate device', 400);
        }
    }
     * 
     * POST /api/rfid/poll
     * Body: {
     *   "device_id": "scanner_01",
     *   "tags": ["ABC123", "DEF456"],
     *   "timestamp": "2024-01-01T12:00:00Z"
     * }
     */
    private function pollForTags(): void
    {
        // This endpoint has relaxed authentication for device communication
        $this->requiresAuth = false;
        
        $input = $this->sanitizeInput($this->getInput());
        
        // Validate device authentication
        if (!$this->isValidDevice($input['device_id'] ?? '')) {
            $this->respondError('Invalid device', 401);
            return;
        }

        $deviceId = $input['device_id'];
        $tags = $input['tags'] ?? [];
        $timestamp = $input['timestamp'] ?? date('c');

        $results = [];
        
        // Process each tag
        foreach ($tags as $tag) {
            if (!$this->isValidRfidTag($tag)) {
                $results[] = [
                    'tag' => $tag,
                    'success' => false,
                    'message' => 'Invalid tag format'
                ];
                continue;
            }

            // Get default event for device
            $eventId = $this->getDeviceDefaultEvent($deviceId);
            
            $result = $this->checkinRepo->processRfidCheckin($tag, $eventId, $deviceId, [
                'batch_operation' => true,
                'device_timestamp' => $timestamp,
                'api_version' => '2.0'
            ]);

            $results[] = [
                'tag' => $tag,
                'success' => $result['success'],
                'message' => $result['message'],
                'user' => $result['user'] ?? null,
                'event' => $result['event'] ?? null
            ];
        }

        $this->respondSuccess([
            'device_id' => $deviceId,
            'processed_count' => count($tags),
            'successful_count' => count(array_filter($results, fn($r) => $r['success'])),
            'results' => $results,
            'timestamp' => date('c')
        ]);
    }

    /**
     * Get RFID system status
     * 
     * GET /api/rfid/status
     */
    private function getSystemStatus(): void
    {
        // Check if user has permission to view system status
        if (!$this->hasPermissions(['admin', 'view_reports'])) {
            $this->respondError('Insufficient permissions', 403);
            return;
        }

        $status = [
            'system_enabled' => $this->config->get('hardware.rfid_enabled', true),
            'active_devices' => $this->getActiveDevices(),
            'recent_activity' => $this->getRecentActivity(),
            'statistics' => $this->getRfidStatistics(),
            'device_status' => $this->getDeviceStatuses()
        ];

        $this->respondSuccess($status);
    }

    /**
     * Register RFID tag to user
     * 
     * POST /api/rfid/register
     * Body: {
     *   "user_id": 123,
     *   "rfid_tag": "ABC123"
     * }
     */
    private function registerTag(): void
    {
        // Check permissions
        if (!$this->hasPermissions(['admin', 'manage_users'])) {
            $this->respondError('Insufficient permissions', 403);
            return;
        }

        $input = $this->sanitizeInput($this->getInput());
        
        $errors = $this->validateRequired($input, ['user_id', 'rfid_tag']);
        if (!empty($errors)) {
            $this->respondValidationError($errors);
            return;
        }

        $userId = (int) $input['user_id'];
        $rfidTag = strtoupper(trim($input['rfid_tag']));

        // Validate RFID tag format
        if (!$this->isValidRfidTag($rfidTag)) {
            $this->respondValidationError(['rfid_tag' => 'Invalid RFID tag format']);
            return;
        }

        // Check if user exists
        $user = $this->userRepo->find($userId);
        if (!$user) {
            $this->respondError('User not found', 404);
            return;
        }

        // Check if tag is already registered
        $existingUser = $this->userRepo->findByRfidTag($rfidTag);
        if ($existingUser && $existingUser['user_id'] !== $userId) {
            $this->respondError('RFID tag is already registered to another user', 409, [
                'existing_user' => $existingUser['firstname'] . ' ' . $existingUser['lastname']
            ]);
            return;
        }

        // Register the tag
        $success = $this->userRepo->updateRfidTag($userId, $rfidTag);

        if ($success) {
            $this->logger->info('RFID tag registered', [
                'user_id' => $userId,
                'rfid_tag' => $rfidTag,
                'registered_by' => $this->getCurrentUserId()
            ]);

            $this->respondSuccess([
                'message' => 'RFID tag registered successfully',
                'user' => [
                    'id' => $userId,
                    'name' => $user['firstname'] . ' ' . $user['lastname'],
                    'email' => $user['email']
                ],
                'rfid_tag' => $rfidTag
            ]);
        } else {
            $this->respondError('Failed to register RFID tag', 500);
        }
    }

    /**
     * Unregister RFID tag from user
     * 
     * DELETE /api/rfid/unregister
     * Body: {
     *   "user_id": 123
     * } OR {
     *   "rfid_tag": "ABC123"
     * }
     */
    private function unregisterTag(): void
    {
        // Check permissions
        if (!$this->hasPermissions(['admin', 'manage_users'])) {
            $this->respondError('Insufficient permissions', 403);
            return;
        }

        $input = $this->sanitizeInput($this->getInput());
        
        $userId = null;
        $rfidTag = null;

        if (!empty($input['user_id'])) {
            $userId = (int) $input['user_id'];
        } elseif (!empty($input['rfid_tag'])) {
            $rfidTag = strtoupper(trim($input['rfid_tag']));
            
            // Find user by RFID tag
            $user = $this->userRepo->findByRfidTag($rfidTag);
            if ($user) {
                $userId = $user['user_id'];
            }
        }

        if (!$userId) {
            $this->respondError('User ID or RFID tag is required', 400);
            return;
        }

        // Remove the tag
        $success = $this->userRepo->updateRfidTag($userId, null);

        if ($success) {
            $this->logger->info('RFID tag unregistered', [
                'user_id' => $userId,
                'rfid_tag' => $rfidTag,
                'unregistered_by' => $this->getCurrentUserId()
            ]);

            $this->respondSuccess([
                'message' => 'RFID tag unregistered successfully',
                'user_id' => $userId
            ]);
        } else {
            $this->respondError('Failed to unregister RFID tag', 500);
        }
    }

    /**
     * Helper Methods
     */
    
    /**
     * Authenticate RFID device
     */
    private function authenticateDevice(): ?array
    {
        $headers = getallheaders();
        
        $apiKey = $headers['X-API-Key'] ?? null;
        $signature = $headers['X-Signature'] ?? null;
        
        if (!$apiKey || !$signature) {
            return null;
        }
        
        $payload = file_get_contents('php://input');
        
        return $this->rfidDeviceService->authenticateDevice($apiKey, $signature, $payload);
    }

    /**
     * Validate RFID tag format
     */
    private function isValidRfidTag(string $tag): bool
    {
        // RFID tags are typically 8-16 hex characters
        return preg_match('/^[A-Fa-f0-9]{8,16}$/', $tag);
    }

    /**
     * Validate device credentials
     */
    private function isValidDevice(string $deviceId): bool
    {
        // In production, this would check against a device registry
        if (empty($deviceId)) {
            return false;
        }

        // For now, accept any device that follows naming convention
        return preg_match('/^[a-zA-Z0-9_-]+$/', $deviceId);
    }

    /**
     * Get default event for device
     */
    private function getDeviceDefaultEvent(string $deviceId): ?int
    {
        // This could be configured per device
        // For now, return null for general access
        return null;
    }

    /**
     * Get appropriate status code for check-in errors
     */
    private function getCheckinErrorStatusCode(string $errorCode): int
    {
        switch ($errorCode) {
            case 'UNKNOWN_TAG':
                return 404;
            case 'USER_NOT_FOUND':
                return 404;
            case 'USER_INACTIVE':
                return 403;
            case 'EVENT_NOT_FOUND':
                return 404;
            case 'EVENT_INACTIVE':
                return 403;
            case 'NOT_REGISTERED':
                return 403;
            case 'DUPLICATE_CHECKIN':
                return 409;
            default:
                return 400;
        }
    }

    /**
     * Get active devices
     */
    private function getActiveDevices(): array
    {
        // This would query a devices table or cache
        // For now, return mock data
        return [
            'total' => 5,
            'online' => 4,
            'offline' => 1,
            'last_updated' => date('c')
        ];
    }

    /**
     * Get recent RFID activity
     */
    private function getRecentActivity(): array
    {
        return $this->checkinRepo->getRealtimeFeed(10);
    }

    /**
     * Get RFID statistics
     */
    private function getRfidStatistics(): array
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        return [
            'today_checkins' => $this->getRfidCheckinsCount($today),
            'yesterday_checkins' => $this->getRfidCheckinsCount($yesterday),
            'total_registered_tags' => $this->userRepo->getRfidTagCount(),
            'unique_users_today' => $this->getUniqueRfidUsersCount($today)
        ];
    }

    /**
     * Get device statuses
     */
    private function getDeviceStatuses(): array
    {
        // This would check actual device health
        // For now, return mock data
        return [
            [
                'device_id' => 'scanner_01',
                'status' => 'online',
                'last_seen' => date('c', strtotime('-2 minutes')),
                'location' => 'Main Entrance'
            ],
            [
                'device_id' => 'scanner_02', 
                'status' => 'online',
                'last_seen' => date('c', strtotime('-1 minute')),
                'location' => 'Conference Room A'
            ]
        ];
    }

    /**
     * Get RFID check-ins count for date
     */
    private function getRfidCheckinsCount(string $date): int
    {
        // This would be implemented in the repository
        return 0; // Placeholder
    }

    /**
     * Get unique RFID users count for date
     */
    private function getUniqueRfidUsersCount(string $date): int
    {
        // This would be implemented in the repository
        return 0; // Placeholder
    }
}
