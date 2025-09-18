<?php

namespace App\Services;

use App\Core\Database;
use App\Core\LoggingService;
use App\Core\ConfigurationService;
use Exception;

/**
 * Real-time Communication Service
 * 
 * Handles WebSocket connections, real-time notifications, live updates,
 * and dynamic content refresh for enhanced user experience.
 */
class RealtimeService
{
    private static ?self $instance = null;
    private Database $database;
    private LoggingService $logger;
    private ConfigurationService $config;
    private array $activeConnections = [];
    private array $channels = [];
    private array $eventSubscribers = [];
    
    private function __construct()
    {
        $this->database = Database::getInstance();
        $this->logger = LoggingService::getInstance();
        $this->config = ConfigurationService::getInstance();
        $this->initializeChannels();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize WebSocket server
     */
    public function startWebSocketServer(): void
    {
        try {
            $host = $this->config->get('realtime.websocket_host', '0.0.0.0');
            $port = $this->config->get('realtime.websocket_port', 8080);
            
            $this->logger->info('Starting WebSocket server', [
                'host' => $host,
                'port' => $port
            ]);
            
            // Create WebSocket server (simplified implementation)
            $this->createWebSocketServer($host, $port);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to start WebSocket server', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Broadcast message to all connections in a channel
     */
    public function broadcast(string $channel, array $data, ?array $excludeConnections = null): bool
    {
        try {
            if (!isset($this->channels[$channel])) {
                $this->logger->warning('Attempted to broadcast to non-existent channel', [
                    'channel' => $channel
                ]);
                return false;
            }
            
            $message = json_encode([
                'type' => 'broadcast',
                'channel' => $channel,
                'data' => $data,
                'timestamp' => date('c')
            ]);
            
            $successCount = 0;
            $excludeList = $excludeConnections ?? [];
            
            foreach ($this->channels[$channel] as $connectionId) {
                if (in_array($connectionId, $excludeList)) {
                    continue;
                }
                
                if (isset($this->activeConnections[$connectionId])) {
                    if ($this->sendToConnection($connectionId, $message)) {
                        $successCount++;
                    }
                }
            }
            
            $this->logger->debug('Broadcast completed', [
                'channel' => $channel,
                'sent_to' => $successCount,
                'total_subscribers' => count($this->channels[$channel])
            ]);
            
            return $successCount > 0;
            
        } catch (Exception $e) {
            $this->logger->error('Broadcast failed', [
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Send notification to specific user
     */
    public function sendUserNotification(int $userId, array $notification): bool
    {
        try {
            // Get user's active connections
            $userConnections = $this->getUserConnections($userId);
            
            if (empty($userConnections)) {
                // Store notification for later delivery
                $this->storeOfflineNotification($userId, $notification);
                return false;
            }
            
            $message = json_encode([
                'type' => 'notification',
                'data' => $notification,
                'timestamp' => date('c')
            ]);
            
            $delivered = false;
            foreach ($userConnections as $connectionId) {
                if ($this->sendToConnection($connectionId, $message)) {
                    $delivered = true;
                }
            }
            
            if ($delivered) {
                $this->logger->debug('User notification sent', [
                    'user_id' => $userId,
                    'notification_type' => $notification['type'] ?? 'unknown'
                ]);
            }
            
            return $delivered;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to send user notification', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Push real-time data update
     */
    public function pushDataUpdate(string $dataType, array $data, ?string $target = null): bool
    {
        try {
            $updateMessage = [
                'type' => 'data_update',
                'data_type' => $dataType,
                'data' => $data,
                'timestamp' => date('c')
            ];
            
            if ($target) {
                // Send to specific target (user, role, or connection)
                return $this->sendTargetedUpdate($target, $updateMessage);
            } else {
                // Broadcast to relevant channels
                $channels = $this->getChannelsForDataType($dataType);
                $success = false;
                
                foreach ($channels as $channel) {
                    if ($this->broadcast($channel, $updateMessage)) {
                        $success = true;
                    }
                }
                
                return $success;
            }
            
        } catch (Exception $e) {
            $this->logger->error('Failed to push data update', [
                'data_type' => $dataType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Register connection to channel
     */
    public function subscribeToChannel(string $connectionId, string $channel): bool
    {
        try {
            if (!isset($this->channels[$channel])) {
                $this->channels[$channel] = [];
            }
            
            if (!in_array($connectionId, $this->channels[$channel])) {
                $this->channels[$channel][] = $connectionId;
                
                $this->logger->debug('Connection subscribed to channel', [
                    'connection_id' => $connectionId,
                    'channel' => $channel
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to subscribe to channel', [
                'connection_id' => $connectionId,
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Unregister connection from channel
     */
    public function unsubscribeFromChannel(string $connectionId, string $channel): bool
    {
        try {
            if (isset($this->channels[$channel])) {
                $index = array_search($connectionId, $this->channels[$channel]);
                if ($index !== false) {
                    unset($this->channels[$channel][$index]);
                    $this->channels[$channel] = array_values($this->channels[$channel]);
                    
                    $this->logger->debug('Connection unsubscribed from channel', [
                        'connection_id' => $connectionId,
                        'channel' => $channel
                    ]);
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to unsubscribe from channel', [
                'connection_id' => $connectionId,
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Handle new WebSocket connection
     */
    public function handleNewConnection(string $connectionId, array $handshakeData): bool
    {
        try {
            // Validate authentication token from handshake
            $authToken = $this->extractAuthToken($handshakeData);
            $user = $this->validateAuthToken($authToken);
            
            if (!$user) {
                $this->logger->warning('WebSocket connection rejected - invalid auth', [
                    'connection_id' => $connectionId
                ]);
                return false;
            }
            
            // Store connection info
            $this->activeConnections[$connectionId] = [
                'user_id' => $user['user_id'],
                'connected_at' => time(),
                'last_ping' => time(),
                'channels' => [],
                'user_agent' => $handshakeData['user_agent'] ?? 'unknown'
            ];
            
            // Auto-subscribe to user's default channels
            $this->subscribeToDefaultChannels($connectionId, $user);
            
            // Send pending offline notifications
            $this->deliverOfflineNotifications($user['user_id'], $connectionId);
            
            $this->logger->info('WebSocket connection established', [
                'connection_id' => $connectionId,
                'user_id' => $user['user_id']
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to handle new connection', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Handle connection disconnect
     */
    public function handleDisconnection(string $connectionId): void
    {
        try {
            if (isset($this->activeConnections[$connectionId])) {
                $connection = $this->activeConnections[$connectionId];
                
                // Remove from all channels
                foreach (array_keys($this->channels) as $channel) {
                    $this->unsubscribeFromChannel($connectionId, $channel);
                }
                
                // Remove connection
                unset($this->activeConnections[$connectionId]);
                
                $this->logger->info('WebSocket connection closed', [
                    'connection_id' => $connectionId,
                    'user_id' => $connection['user_id'],
                    'duration' => time() - $connection['connected_at']
                ]);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Failed to handle disconnection', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Process incoming WebSocket message
     */
    public function handleMessage(string $connectionId, string $message): void
    {
        try {
            $data = json_decode($message, true);
            
            if (!$data || !isset($data['type'])) {
                $this->sendError($connectionId, 'Invalid message format');
                return;
            }
            
            switch ($data['type']) {
                case 'ping':
                    $this->handlePing($connectionId);
                    break;
                    
                case 'subscribe':
                    $this->handleSubscribe($connectionId, $data);
                    break;
                    
                case 'unsubscribe':
                    $this->handleUnsubscribe($connectionId, $data);
                    break;
                    
                case 'request_data':
                    $this->handleDataRequest($connectionId, $data);
                    break;
                    
                default:
                    $this->sendError($connectionId, 'Unknown message type: ' . $data['type']);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Failed to handle message', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
            $this->sendError($connectionId, 'Message processing failed');
        }
    }
    
    /**
     * Get real-time statistics
     */
    public function getRealtimeStats(): array
    {
        return [
            'active_connections' => count($this->activeConnections),
            'channels' => array_map(fn($subscribers) => count($subscribers), $this->channels),
            'uptime' => $this->getServerUptime(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }
    
    // Private helper methods
    
    private function initializeChannels(): void
    {
        $this->channels = [
            'dashboard' => [],
            'notifications' => [],
            'check_ins' => [],
            'events' => [],
            'rfid_scans' => [],
            'admin' => [],
            'system_status' => []
        ];
    }
    
    private function createWebSocketServer(string $host, int $port): void
    {
        // Simplified WebSocket server implementation
        // In production, use a proper WebSocket library like Ratchet
        
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($socket, $host, $port);
        socket_listen($socket);
        
        $this->logger->info('WebSocket server listening', [
            'host' => $host,
            'port' => $port
        ]);
        
        while (true) {
            $client = socket_accept($socket);
            if ($client !== false) {
                $this->handleWebSocketHandshake($client);
            }
            
            // Handle existing connections
            $this->processActiveConnections();
            
            usleep(10000); // 10ms sleep to prevent busy waiting
        }
    }
    
    private function handleWebSocketHandshake($client): void
    {
        // Simplified WebSocket handshake
        // In production, use proper WebSocket protocol implementation
        
        $request = socket_read($client, 2048);
        $response = $this->buildWebSocketResponse($request);
        
        socket_write($client, $response);
        
        $connectionId = uniqid('ws_');
        $this->handleNewConnection($connectionId, ['client' => $client]);
    }
    
    private function buildWebSocketResponse(string $request): string
    {
        // Simplified WebSocket response
        // In production, implement proper WebSocket protocol
        
        preg_match('/Sec-WebSocket-Key: (.*)\\r\\n/', $request, $matches);
        $key = $matches[1] ?? '';
        
        $acceptKey = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        
        return "HTTP/1.1 101 Switching Protocols\r\n" .
               "Upgrade: websocket\r\n" .
               "Connection: Upgrade\r\n" .
               "Sec-WebSocket-Accept: $acceptKey\r\n\r\n";
    }
    
    private function processActiveConnections(): void
    {
        foreach ($this->activeConnections as $connectionId => $connection) {
            // Check for timeouts
            if (time() - $connection['last_ping'] > 60) {
                $this->handleDisconnection($connectionId);
            }
        }
    }
    
    private function sendToConnection(string $connectionId, string $message): bool
    {
        if (!isset($this->activeConnections[$connectionId])) {
            return false;
        }
        
        try {
            // Encode message for WebSocket frame
            $frame = $this->encodeWebSocketFrame($message);
            
            // Send to connection (simplified)
            // In production, use proper socket handling
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to send to connection', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    private function encodeWebSocketFrame(string $message): string
    {
        // Simplified WebSocket frame encoding
        // In production, implement full WebSocket protocol
        
        $length = strlen($message);
        $frame = chr(0x81); // Text frame
        
        if ($length < 126) {
            $frame .= chr($length);
        } elseif ($length < 65536) {
            $frame .= chr(126) . pack('n', $length);
        } else {
            $frame .= chr(127) . pack('J', $length);
        }
        
        return $frame . $message;
    }
    
    private function getUserConnections(int $userId): array
    {
        $connections = [];
        
        foreach ($this->activeConnections as $connectionId => $connection) {
            if ($connection['user_id'] === $userId) {
                $connections[] = $connectionId;
            }
        }
        
        return $connections;
    }
    
    private function storeOfflineNotification(int $userId, array $notification): void
    {
        try {
            $stmt = $this->database->prepare("
                INSERT INTO offline_notifications 
                (user_id, type, data, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $userId,
                $notification['type'] ?? 'general',
                json_encode($notification)
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to store offline notification', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function deliverOfflineNotifications(int $userId, string $connectionId): void
    {
        try {
            $stmt = $this->database->prepare("
                SELECT * FROM offline_notifications 
                WHERE user_id = ? 
                ORDER BY created_at ASC
            ");
            $stmt->execute([$userId]);
            $notifications = $stmt->fetchAll();
            
            foreach ($notifications as $notification) {
                $data = json_decode($notification['data'], true);
                $message = json_encode([
                    'type' => 'notification',
                    'data' => $data,
                    'timestamp' => $notification['created_at']
                ]);
                
                $this->sendToConnection($connectionId, $message);
            }
            
            // Delete delivered notifications
            if (!empty($notifications)) {
                $stmt = $this->database->prepare("
                    DELETE FROM offline_notifications WHERE user_id = ?
                ");
                $stmt->execute([$userId]);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Failed to deliver offline notifications', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function getChannelsForDataType(string $dataType): array
    {
        $channelMap = [
            'check_in' => ['dashboard', 'check_ins'],
            'event' => ['dashboard', 'events'],
            'user' => ['admin'],
            'rfid_scan' => ['rfid_scans', 'dashboard'],
            'system_alert' => ['system_status', 'admin'],
            'notification' => ['notifications']
        ];
        
        return $channelMap[$dataType] ?? ['dashboard'];
    }
    
    private function sendTargetedUpdate(string $target, array $message): bool
    {
        // Parse target format (user:123, role:admin, etc.)
        if (strpos($target, 'user:') === 0) {
            $userId = (int)substr($target, 5);
            return $this->sendUserNotification($userId, $message);
        } elseif (strpos($target, 'role:') === 0) {
            $role = substr($target, 5);
            return $this->broadcastToRole($role, $message);
        } elseif (strpos($target, 'connection:') === 0) {
            $connectionId = substr($target, 11);
            return $this->sendToConnection($connectionId, json_encode($message));
        }
        
        return false;
    }
    
    private function broadcastToRole(string $role, array $message): bool
    {
        // Get users with specific role and send to their connections
        try {
            $stmt = $this->database->prepare("
                SELECT user_id FROM users WHERE role = ? AND status = 'active'
            ");
            $stmt->execute([$role]);
            $users = $stmt->fetchAll();
            
            $success = false;
            foreach ($users as $user) {
                if ($this->sendUserNotification($user['user_id'], $message)) {
                    $success = true;
                }
            }
            
            return $success;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to broadcast to role', [
                'role' => $role,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    private function extractAuthToken(array $handshakeData): ?string
    {
        // Extract auth token from handshake headers or URL parameters
        // Simplified implementation
        return $handshakeData['auth_token'] ?? null;
    }
    
    private function validateAuthToken(?string $token): ?array
    {
        if (!$token) {
            return null;
        }
        
        try {
            // Validate JWT token or session token
            // Simplified implementation - in production, use proper JWT validation
            
            $stmt = $this->database->prepare("
                SELECT u.* FROM users u 
                INNER JOIN user_sessions s ON u.user_id = s.user_id 
                WHERE s.session_token = ? AND s.expires_at > NOW()
            ");
            $stmt->execute([$token]);
            return $stmt->fetch() ?: null;
            
        } catch (Exception $e) {
            $this->logger->error('Auth token validation failed', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    private function subscribeToDefaultChannels(string $connectionId, array $user): void
    {
        // Subscribe to channels based on user role and permissions
        $defaultChannels = ['notifications'];
        
        if ($user['role'] === 'admin') {
            $defaultChannels = array_merge($defaultChannels, ['admin', 'system_status']);
        }
        
        if (in_array($user['role'], ['admin', 'staff'])) {
            $defaultChannels = array_merge($defaultChannels, ['dashboard', 'check_ins']);
        }
        
        foreach ($defaultChannels as $channel) {
            $this->subscribeToChannel($connectionId, $channel);
        }
    }
    
    private function handlePing(string $connectionId): void
    {
        if (isset($this->activeConnections[$connectionId])) {
            $this->activeConnections[$connectionId]['last_ping'] = time();
            
            $pongMessage = json_encode([
                'type' => 'pong',
                'timestamp' => date('c')
            ]);
            
            $this->sendToConnection($connectionId, $pongMessage);
        }
    }
    
    private function handleSubscribe(string $connectionId, array $data): void
    {
        $channel = $data['channel'] ?? null;
        
        if ($channel && $this->isChannelAllowed($connectionId, $channel)) {
            $this->subscribeToChannel($connectionId, $channel);
            
            $response = json_encode([
                'type' => 'subscribed',
                'channel' => $channel,
                'timestamp' => date('c')
            ]);
            
            $this->sendToConnection($connectionId, $response);
        } else {
            $this->sendError($connectionId, 'Channel subscription not allowed');
        }
    }
    
    private function handleUnsubscribe(string $connectionId, array $data): void
    {
        $channel = $data['channel'] ?? null;
        
        if ($channel) {
            $this->unsubscribeFromChannel($connectionId, $channel);
            
            $response = json_encode([
                'type' => 'unsubscribed',
                'channel' => $channel,
                'timestamp' => date('c')
            ]);
            
            $this->sendToConnection($connectionId, $response);
        }
    }
    
    private function handleDataRequest(string $connectionId, array $data): void
    {
        $dataType = $data['data_type'] ?? null;
        
        if ($dataType) {
            $responseData = $this->getRequestedData($connectionId, $dataType);
            
            $response = json_encode([
                'type' => 'data_response',
                'data_type' => $dataType,
                'data' => $responseData,
                'timestamp' => date('c')
            ]);
            
            $this->sendToConnection($connectionId, $response);
        }
    }
    
    private function isChannelAllowed(string $connectionId, string $channel): bool
    {
        if (!isset($this->activeConnections[$connectionId])) {
            return false;
        }
        
        $connection = $this->activeConnections[$connectionId];
        $userId = $connection['user_id'];
        
        // Get user permissions
        try {
            $stmt = $this->database->prepare("
                SELECT role FROM users WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return false;
            }
            
            // Check channel permissions
            $allowedChannels = $this->getChannelsForRole($user['role']);
            return in_array($channel, $allowedChannels);
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function getChannelsForRole(string $role): array
    {
        $roleChannels = [
            'admin' => ['dashboard', 'notifications', 'check_ins', 'events', 'rfid_scans', 'admin', 'system_status'],
            'staff' => ['dashboard', 'notifications', 'check_ins', 'events'],
            'user' => ['notifications']
        ];
        
        return $roleChannels[$role] ?? ['notifications'];
    }
    
    private function getRequestedData(string $connectionId, string $dataType): array
    {
        // Return appropriate data based on data type and user permissions
        switch ($dataType) {
            case 'dashboard_stats':
                return $this->getDashboardStats();
            case 'recent_checkins':
                return $this->getRecentCheckins();
            case 'active_events':
                return $this->getActiveEvents();
            default:
                return ['error' => 'Unknown data type'];
        }
    }
    
    private function getDashboardStats(): array
    {
        // Implementation would fetch current dashboard statistics
        return [
            'total_checkins_today' => 0,
            'active_events' => 0,
            'online_users' => count($this->activeConnections)
        ];
    }
    
    private function getRecentCheckins(): array
    {
        // Implementation would fetch recent check-ins
        return [];
    }
    
    private function getActiveEvents(): array
    {
        // Implementation would fetch active events
        return [];
    }
    
    private function sendError(string $connectionId, string $error): void
    {
        $errorMessage = json_encode([
            'type' => 'error',
            'error' => $error,
            'timestamp' => date('c')
        ]);
        
        $this->sendToConnection($connectionId, $errorMessage);
    }
    
    private function getServerUptime(): int
    {
        // Return server uptime in seconds
        // Simplified implementation
        return time() - ($_SERVER['REQUEST_TIME'] ?? time());
    }
}
