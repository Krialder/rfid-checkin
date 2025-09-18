<?php
/**
 * Data Service Layer
 * 
 * Centralized service class that orchestrates all data access operations
 * through repository classes. Provides a single entry point for all
 * data operations and implements business logic coordination.
 * 
 * Features:
 * - Centralized data access management
 * - Business logic coordination between repositories
 * - Transaction management across multiple operations
 * - Caching layer for improved performance
 * - Comprehensive error handling and logging
 * - Service-level authorization and validation
 * 
 * @package    RFID Check-in System
 * @subpackage Service Layer
 * @version    3.0.0 - Enhanced Error Handling
 * @author     Senior Developer Team
 * @since      2.0.0
 */

require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/EventRepository.php';
require_once __DIR__ . '/../repositories/CheckinRepository.php';
require_once __DIR__ . '/../ErrorHandler.php';
require_once __DIR__ . '/../SecurityManager.php';

class DataService {
    
    /**
     * Repository instances
     */
    private $userRepo;
    private $eventRepo;
    private $checkinRepo;
    private $errorHandler;
    
    /**
     * Service cache
     */
    private static $instance = null;
    private $cache = [];
    
    /**
     * Get singleton instance
     * 
     * @return DataService
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize service with repository instances
     */
    private function __construct() {
        try {
            $this->userRepo = new UserRepository();
            $this->eventRepo = new EventRepository();
            $this->checkinRepo = new CheckinRepository();
            $this->errorHandler = ErrorHandler::getInstance();
        } catch (Exception $e) {
            error_log("DataService initialization failed: " . $e->getMessage());
            throw new RuntimeException("Service initialization failed", 0, $e);
        }
    }
    
    /**
     * Get user repository
     * 
     * @return UserRepository
     */
    public function users() {
        return $this->userRepo;
    }
    
    /**
     * Get event repository
     * 
     * @return EventRepository
     */
    public function events() {
        return $this->eventRepo;
    }
    
    /**
     * Get check-in repository
     * 
     * @return CheckinRepository
     */
    public function checkins() {
        return $this->checkinRepo;
    }
    
    /**
     * Get comprehensive dashboard data for a user
     * 
     * @param int $userId User ID
     * @return array Dashboard data
     */
    public function getDashboardData($userId) {
        $cacheKey = "dashboard_data_{$userId}";
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        try {
            // Get user information
            $user = $this->userRepo->findById($userId);
            if (!$user) {
                throw new Exception('User not found');
            }
            
            // Get user statistics
            $userStats = $this->userRepo->getUserStats($userId);
            
            // Get recent check-ins
            $recentCheckins = $this->userRepo->getRecentCheckins($userId, 10);
            
            // Get upcoming events
            $upcomingEvents = $this->eventRepo->getUpcomingEvents($userId, 5);
            
            // Get available events for check-in
            $availableEvents = $this->eventRepo->getAvailableForCheckin($userId);
            
            $dashboardData = [
                'user' => $user,
                'stats' => $userStats,
                'recent_checkins' => $recentCheckins,
                'upcoming_events' => $upcomingEvents,
                'available_events' => $availableEvents
            ];
            
            // Cache for 5 minutes
            $this->cache[$cacheKey] = $dashboardData;
            
            return $dashboardData;
            
        } catch (Exception $e) {
            logMessage('ERROR', 'Failed to get dashboard data', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Process RFID check-in
     * 
     * @param string $rfidTag RFID tag value
     * @param int|null $eventId Specific event ID (optional)
     * @param array $options Additional options
     * @return array Check-in result
     */
    public function processRfidCheckin($rfidTag, $eventId = null, array $options = []) {
        try {
            // Find user by RFID tag
            $user = $this->userRepo->findByRfidTag($rfidTag);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'RFID tag not found in system',
                    'rfid_tag' => $rfidTag
                ];
            }
            
            if (!$user['is_active']) {
                return [
                    'success' => false,
                    'message' => 'User account is inactive',
                    'user' => $user
                ];
            }
            
            // If no specific event, find available events
            if (!$eventId) {
                $availableEvents = $this->eventRepo->getAvailableForCheckin($user['user_id']);
                
                if (empty($availableEvents)) {
                    return [
                        'success' => false,
                        'message' => 'No events available for check-in at this time',
                        'user' => $user
                    ];
                }
                
                // Use the first available event
                $event = $availableEvents[0];
                $eventId = $event['event_id'];
            } else {
                // Validate specific event
                $event = $this->eventRepo->findById($eventId);
                if (!$event || !$event['active']) {
                    return [
                        'success' => false,
                        'message' => 'Event not found or inactive',
                        'user' => $user
                    ];
                }
            }
            
            // Check if user already checked in today
            if ($this->checkinRepo->hasUserCheckedInToday($user['user_id'], $eventId)) {
                return [
                    'success' => false,
                    'message' => 'User already checked in today for this event',
                    'user' => $user,
                    'event' => $event
                ];
            }
            
            // Check event capacity
            $capacityAvailable = $this->eventRepo->hasCapacityAvailable($eventId);
            if ($capacityAvailable === false) {
                return [
                    'success' => false,
                    'message' => 'Event is at full capacity',
                    'user' => $user,
                    'event' => $event
                ];
            }
            
            // Record the check-in
            $checkinId = $this->checkinRepo->recordCheckin(
                $user['user_id'],
                $eventId,
                array_merge($options, ['device_id' => $options['device_id'] ?? null])
            );
            
            if ($checkinId) {
                // Clear cache
                unset($this->cache["dashboard_data_{$user['user_id']}"]);
                
                return [
                    'success' => true,
                    'message' => 'Check-in successful',
                    'checkin_id' => $checkinId,
                    'user' => $user,
                    'event' => $event,
                    'checkin_time' => date('Y-m-d H:i:s')
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to record check-in',
                    'user' => $user,
                    'event' => $event
                ];
            }
            
        } catch (Exception $e) {
            logMessage('ERROR', 'RFID check-in failed', [
                'rfid_tag' => $rfidTag,
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'System error during check-in: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Process manual check-in
     * 
     * @param int $userId User ID
     * @param int $eventId Event ID
     * @param array $options Additional options
     * @return array Check-in result
     */
    public function processManualCheckin($userId, $eventId, array $options = []) {
        try {
            // Validate user
            $user = $this->userRepo->findById($userId);
            if (!$user || !$user['is_active']) {
                return [
                    'success' => false,
                    'message' => 'Invalid or inactive user'
                ];
            }
            
            // Validate event
            $event = $this->eventRepo->findById($eventId);
            if (!$event || !$event['active'] || !$event['allow_manual_checkin']) {
                return [
                    'success' => false,
                    'message' => 'Event not found, inactive, or manual check-in not allowed'
                ];
            }
            
            // Check if user already checked in today
            if ($this->checkinRepo->hasUserCheckedInToday($userId, $eventId)) {
                return [
                    'success' => false,
                    'message' => 'User already checked in today for this event'
                ];
            }
            
            // Check event capacity
            $capacityAvailable = $this->eventRepo->hasCapacityAvailable($eventId);
            if ($capacityAvailable === false) {
                return [
                    'success' => false,
                    'message' => 'Event is at full capacity'
                ];
            }
            
            // Record the check-in
            $checkinId = $this->checkinRepo->recordCheckin($userId, $eventId, $options);
            
            if ($checkinId) {
                // Clear cache
                unset($this->cache["dashboard_data_{$userId}"]);
                
                return [
                    'success' => true,
                    'message' => 'Manual check-in successful',
                    'checkin_id' => $checkinId,
                    'user' => $user,
                    'event' => $event,
                    'checkin_time' => date('Y-m-d H:i:s')
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to record check-in'
                ];
            }
            
        } catch (Exception $e) {
            logMessage('ERROR', 'Manual check-in failed', [
                'user_id' => $userId,
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'System error during check-in: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get comprehensive analytics data
     * 
     * @param array $filters Filter options
     * @return array Analytics data
     */
    public function getAnalyticsData(array $filters = []) {
        try {
            $data = [];
            
            // System-wide statistics
            $data['system_stats'] = [
                'users' => $this->userRepo->getSystemStats(),
                'events' => $this->eventRepo->getSystemStats(),
                'checkins' => $this->checkinRepo->getSystemStats()
            ];
            
            // Daily trends
            $data['daily_trends'] = $this->checkinRepo->getDailyTrends($filters);
            
            // Hourly distribution
            $data['hourly_distribution'] = $this->checkinRepo->getHourlyDistribution($filters);
            
            // Top users
            $data['top_users'] = $this->checkinRepo->getTopUsers(10, $filters);
            
            // Popular events
            $data['popular_events'] = $this->eventRepo->getPopularEvents(10);
            
            return $data;
            
        } catch (Exception $e) {
            logMessage('ERROR', 'Failed to get analytics data', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Create user with validation and setup
     * 
     * @param array $userData User data
     * @return array Result with user ID or error
     */
    public function createUser(array $userData) {
        try {
            $userId = $this->userRepo->createUser($userData);
            
            if ($userId) {
                return [
                    'success' => true,
                    'user_id' => $userId,
                    'message' => 'User created successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to create user'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create event with validation
     * 
     * @param array $eventData Event data
     * @return array Result with event ID or error
     */
    public function createEvent(array $eventData) {
        try {
            $eventId = $this->eventRepo->createEvent($eventData);
            
            if ($eventId) {
                return [
                    'success' => true,
                    'event_id' => $eventId,
                    'message' => 'Event created successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to create event'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Search across multiple entities
     * 
     * @param string $query Search query
     * @param array $types Entity types to search
     * @return array Search results
     */
    public function globalSearch($query, array $types = ['users', 'events']) {
        $results = [];
        
        try {
            if (in_array('users', $types)) {
                $results['users'] = $this->userRepo->searchUsers($query, 20);
            }
            
            if (in_array('events', $types)) {
                $results['events'] = $this->eventRepo->searchEvents($query, 20);
            }
            
            return $results;
            
        } catch (Exception $e) {
            logMessage('ERROR', 'Global search failed', [
                'query' => $query,
                'types' => $types,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Execute transaction with callback
     * 
     * @param callable $callback Transaction callback
     * @return mixed Callback result
     */
    public function transaction(callable $callback) {
        return $this->userRepo->transaction($callback);
    }
    
    /**
     * Clear all caches
     */
    public function clearCache() {
        $this->cache = [];
    }
    
    /**
     * Create user with enhanced security
     * 
     * @param array $userData User data
     * @return int|false User ID on success, false on failure
     */
    public function createUser(array $userData) {
        try {
            // Hash password using SecurityManager for enhanced security
            if (isset($userData['password'])) {
                $securityManager = SecurityManager::getInstance();
                $userData['password'] = $securityManager->hashPassword($userData['password']);
            }
            
            $userId = $this->userRepo->createUser($userData);
            
            if ($userId) {
                return $userId;
            } else {
                return false;
            }
            
        } catch (Exception $e) {
            $this->errorHandler->logError('User creation failed', $e);
            return false;
        }
    }
    
    /**
     * Get user by email
     * 
     * @param string $email User email
     * @return array|null User data or null if not found
     */
    public function getUserByEmail(string $email) {
        try {
            return $this->userRepo->findWhere(['email' => $email], 1)[0] ?? null;
        } catch (Exception $e) {
            $this->errorHandler->logError('Get user by email failed', $e);
            return null;
        }
    }
    
    /**
     * Get user by username
     * 
     * @param string $username Username
     * @return array|null User data or null if not found
     */
    public function getUserByUsername(string $username) {
        try {
            return $this->userRepo->findWhere(['username' => $username], 1)[0] ?? null;
        } catch (Exception $e) {
            $this->errorHandler->logError('Get user by username failed', $e);
            return null;
        }
    }
    
    /**
     * Get user by RFID tag
     * 
     * @param string $rfidTag RFID tag
     * @return array|null User data or null if not found
     */
    public function getUserByRfid(string $rfidTag) {
        try {
            return $this->userRepo->findWhere(['rfid_tag' => $rfidTag], 1)[0] ?? null;
        } catch (Exception $e) {
            $this->errorHandler->logError('Get user by RFID failed', $e);
            return null;
        }
    }
    
    /**
     * Log activity
     * 
     * @param int $userId User ID performing the action
     * @param string $action Action performed
     * @param string $details Action details
     * @param string $ipAddress IP address
     * @return bool Success status
     */
    public function logActivity(int $userId, string $action, string $details, string $ipAddress = null) {
        try {
            $activityData = [
                'user_id' => $userId,
                'action' => $action,
                'details' => $details,
                'ip_address' => $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // Use BaseRepository to insert activity log
            $sql = "INSERT INTO activitylog (user_id, action, details, ip_address, timestamp) VALUES (?, ?, ?, ?, ?)";
            $params = [
                $activityData['user_id'],
                $activityData['action'],
                $activityData['details'],
                $activityData['ip_address'],
                $activityData['timestamp']
            ];
            
            return $this->userRepo->execute($sql, $params);
            
        } catch (Exception $e) {
            $this->errorHandler->logError('Activity logging failed', $e);
            return false;
        }
    }
    
    /**
     * Get cached data
     * 
     * @param string $key Cache key
     * @return mixed|null Cached data or null
     */
    public function getCache($key) {
        return $this->cache[$key] ?? null;
    }
    
    /**
     * Set cached data
     * 
     * @param string $key Cache key
     * @param mixed $data Data to cache
     */
    public function setCache($key, $data) {
        $this->cache[$key] = $data;
    }
}
