<?php

namespace App\Controllers\Api;

use App\Services\EventService;
use App\Services\UserGroupService;
use App\Core\LoggingService;
use App\Core\SecurityManager;
use Exception;

/**
 * Event API Controller
 * 
 * Handles all event-related API endpoints:
 * - Event CRUD operations
 * - Event listing with filtering
 * - Event instance management
 * - Group assignment to events
 * - Event statistics and analytics
 */
class EventApiController
{
    private EventService $eventService;
    private UserGroupService $groupService;
    private LoggingService $logger;
    private SecurityManager $security;
    
    public function __construct()
    {
        $this->eventService = new EventService();
        $this->groupService = new UserGroupService();
        $this->logger = LoggingService::getInstance();
        $this->security = SecurityManager::getInstance();
    }
    
    /**
     * Create new event
     */
    public function createEvent(): array
    {
        try {
            $this->requireAdminAccess();
            
            $input = $this->getJsonInput();
            $userId = $_SESSION['user_id'];
            
            // Validate CSRF token
            if (!$this->security->validateCsrfToken($input['csrf_token'] ?? '')) {
                return [
                    'success' => false,
                    'error' => 'Invalid CSRF token'
                ];
            }
            
            // Sanitize and validate input
            $eventData = $this->sanitizeEventData($input);
            
            $result = $this->eventService->createEvent($eventData, $userId);
            
            if ($result['success'] && !empty($input['assigned_groups'])) {
                // Assign groups to the event
                $groupResult = $this->eventService->assignGroupsToEvent(
                    $result['event_id'],
                    $input['assigned_groups'],
                    $userId
                );
                
                if (!$groupResult['success']) {
                    $this->logger->warning('Failed to assign groups after event creation', [
                        'event_id' => $result['event_id'],
                        'error' => $groupResult['error']
                    ]);
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Event creation API error', [
                'error' => $e->getMessage(),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to create event'
            ];
        }
    }
    
    /**
     * Update existing event
     */
    public function updateEvent(): array
    {
        try {
            $this->requireAdminAccess();
            
            $input = $this->getJsonInput();
            $userId = $_SESSION['user_id'];
            $eventId = (int)($input['event_id'] ?? 0);
            
            if ($eventId <= 0) {
                return [
                    'success' => false,
                    'error' => 'Invalid event ID'
                ];
            }
            
            // Validate CSRF token
            if (!$this->security->validateCsrfToken($input['csrf_token'] ?? '')) {
                return [
                    'success' => false,
                    'error' => 'Invalid CSRF token'
                ];
            }
            
            // Sanitize and validate input
            $eventData = $this->sanitizeEventData($input);
            
            $result = $this->eventService->updateEvent($eventId, $eventData, $userId);
            
            if ($result['success'] && isset($input['assigned_groups'])) {
                // Update group assignments
                $groupResult = $this->eventService->assignGroupsToEvent(
                    $eventId,
                    $input['assigned_groups'],
                    $userId
                );
                
                if (!$groupResult['success']) {
                    $this->logger->warning('Failed to update group assignments', [
                        'event_id' => $eventId,
                        'error' => $groupResult['error']
                    ]);
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Event update API error', [
                'error' => $e->getMessage(),
                'event_id' => $eventId ?? null,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to update event'
            ];
        }
    }
    
    /**
     * Delete event
     */
    public function deleteEvent(): array
    {
        try {
            $this->requireAdminAccess();
            
            $input = $this->getJsonInput();
            $eventId = (int)($input['event_id'] ?? 0);
            
            if ($eventId <= 0) {
                return [
                    'success' => false,
                    'error' => 'Invalid event ID'
                ];
            }
            
            // Validate CSRF token
            if (!$this->security->validateCsrfToken($input['csrf_token'] ?? '')) {
                return [
                    'success' => false,
                    'error' => 'Invalid CSRF token'
                ];
            }
            
            return $this->eventService->deleteEvent($eventId);
            
        } catch (Exception $e) {
            $this->logger->error('Event deletion API error', [
                'error' => $e->getMessage(),
                'event_id' => $eventId ?? null,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to delete event'
            ];
        }
    }
    
    /**
     * Get event details
     */
    public function getEvent(): array
    {
        try {
            $this->requireAuthentication();
            
            $eventId = (int)($_GET['event_id'] ?? 0);
            
            if ($eventId <= 0) {
                return [
                    'success' => false,
                    'error' => 'Invalid event ID'
                ];
            }
            
            $event = $this->eventService->getEvent($eventId);
            
            if ($event) {
                return [
                    'success' => true,
                    'event' => $event
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Event not found'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Get event API error', [
                'error' => $e->getMessage(),
                'event_id' => $eventId ?? null,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve event'
            ];
        }
    }
    
    /**
     * Get events list with filtering
     */
    public function getEvents(): array
    {
        try {
            $this->requireAuthentication();
            
            $filters = [
                'search' => trim($_GET['search'] ?? ''),
                'status' => $_GET['status'] ?? '',
                'event_type' => $_GET['event_type'] ?? '',
                'created_by' => $_GET['created_by'] ?? ''
            ];
            
            // Remove empty filters
            $filters = array_filter($filters, function($value) {
                return $value !== '';
            });
            
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(10, min(100, (int)($_GET['limit'] ?? 25)));
            
            return $this->eventService->getEvents($filters, $page, $limit);
            
        } catch (Exception $e) {
            $this->logger->error('Get events API error', [
                'error' => $e->getMessage(),
                'filters' => $filters ?? [],
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve events'
            ];
        }
    }
    
    /**
     * Get event statistics
     */
    public function getEventStats(): array
    {
        try {
            $this->requireAuthentication();
            
            return $this->eventService->getEventStatistics();
            
        } catch (Exception $e) {
            $this->logger->error('Get event stats API error', [
                'error' => $e->getMessage(),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve statistics'
            ];
        }
    }
    
    /**
     * Regenerate event instances
     */
    public function regenerateInstances(): array
    {
        try {
            $this->requireAdminAccess();
            
            $input = $this->getJsonInput();
            $eventId = (int)($input['event_id'] ?? 0);
            
            if ($eventId <= 0) {
                return [
                    'success' => false,
                    'error' => 'Invalid event ID'
                ];
            }
            
            // Validate CSRF token
            if (!$this->security->validateCsrfToken($input['csrf_token'] ?? '')) {
                return [
                    'success' => false,
                    'error' => 'Invalid CSRF token'
                ];
            }
            
            return $this->eventService->regenerateEventInstances($eventId);
            
        } catch (Exception $e) {
            $this->logger->error('Regenerate instances API error', [
                'error' => $e->getMessage(),
                'event_id' => $eventId ?? null,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to regenerate instances'
            ];
        }
    }
    
    /**
     * Get event groups
     */
    public function getEventGroups(): array
    {
        try {
            $this->requireAuthentication();
            
            $eventId = (int)($_GET['event_id'] ?? 0);
            
            if ($eventId <= 0) {
                return [
                    'success' => false,
                    'error' => 'Invalid event ID'
                ];
            }
            
            return $this->eventService->getEventGroups($eventId);
            
        } catch (Exception $e) {
            $this->logger->error('Get event groups API error', [
                'error' => $e->getMessage(),
                'event_id' => $eventId ?? null,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve event groups'
            ];
        }
    }
    
    /**
     * Calculate unique participants
     */
    public function calculateUniqueParticipants(): array
    {
        try {
            $this->requireAuthentication();
            
            $input = $this->getJsonInput();
            $groupIds = $input['group_ids'] ?? [];
            
            if (!is_array($groupIds)) {
                $groupIds = explode(',', (string)$groupIds);
            }
            
            $groupIds = array_filter(array_map('intval', $groupIds));
            
            return $this->eventService->calculateUniqueParticipants($groupIds);
            
        } catch (Exception $e) {
            $this->logger->error('Calculate participants API error', [
                'error' => $e->getMessage(),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to calculate participants'
            ];
        }
    }
    
    /**
     * Get available groups for assignment
     */
    public function getAvailableGroups(): array
    {
        try {
            $this->requireAuthentication();
            
            $search = trim($_GET['search'] ?? '');
            $limit = min(50, (int)($_GET['limit'] ?? 20));
            
            $filters = [];
            if ($search) {
                $filters['search'] = $search;
            }
            
            $result = $this->groupService->getGroups($filters, 1, $limit);
            
            return [
                'success' => true,
                'groups' => $result['groups'] ?? []
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Get available groups API error', [
                'error' => $e->getMessage(),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve groups'
            ];
        }
    }
    
    /**
     * Get break templates
     */
    public function getBreakTemplates(): array
    {
        try {
            $this->requireAuthentication();
            
            // Common break templates for events
            $templates = [
                [
                    'name' => 'Standard Office Breaks',
                    'description' => 'Typical office environment breaks',
                    'breaks' => [
                        ['name' => 'Morning Coffee', 'start' => '10:00', 'end' => '10:15', 'duration' => 15],
                        ['name' => 'Lunch Break', 'start' => '12:00', 'end' => '13:00', 'duration' => 60],
                        ['name' => 'Afternoon Coffee', 'start' => '15:00', 'end' => '15:15', 'duration' => 15]
                    ]
                ],
                [
                    'name' => 'Half-Day Workshop',
                    'description' => 'Short workshop format',
                    'breaks' => [
                        ['name' => 'Coffee Break', 'start' => '10:30', 'end' => '10:45', 'duration' => 15]
                    ]
                ],
                [
                    'name' => 'Full-Day Conference',
                    'description' => 'Conference or seminar format',
                    'breaks' => [
                        ['name' => 'Morning Coffee', 'start' => '10:00', 'end' => '10:30', 'duration' => 30],
                        ['name' => 'Lunch Break', 'start' => '12:30', 'end' => '13:30', 'duration' => 60],
                        ['name' => 'Afternoon Coffee', 'start' => '15:30', 'end' => '15:45', 'duration' => 15]
                    ]
                ],
                [
                    'name' => 'Training Session',
                    'description' => 'Training or learning format',
                    'breaks' => [
                        ['name' => 'Break 1', 'start' => '10:15', 'end' => '10:30', 'duration' => 15],
                        ['name' => 'Lunch', 'start' => '12:00', 'end' => '13:00', 'duration' => 60],
                        ['name' => 'Break 2', 'start' => '15:00', 'end' => '15:15', 'duration' => 15]
                    ]
                ]
            ];
            
            return [
                'success' => true,
                'templates' => $templates
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Get break templates API error', [
                'error' => $e->getMessage(),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve break templates'
            ];
        }
    }
    
    // Private helper methods
    
    private function requireAuthentication(): void
    {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            throw new Exception('Authentication required');
        }
    }
    
    private function requireAdminAccess(): void
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            throw new Exception('Admin access required');
        }
    }
    
    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Fallback to POST data
            $data = $_POST;
        }
        
        return $data ?? [];
    }
    
    private function sanitizeEventData(array $input): array
    {
        return [
            'name' => $this->security->sanitizeInput($input['name'] ?? ''),
            'description' => $this->security->sanitizeInput($input['description'] ?? ''),
            'location' => $this->security->sanitizeInput($input['location'] ?? ''),
            'event_type' => $this->security->sanitizeInput($input['event_type'] ?? 'general'),
            'capacity' => !empty($input['capacity']) ? (int)$input['capacity'] : null,
            'start_date' => $this->security->sanitizeInput($input['start_date'] ?? ''),
            'end_date' => $this->security->sanitizeInput($input['end_date'] ?? ''),
            'start_time' => $this->security->sanitizeInput($input['start_time'] ?? ''),
            'end_time' => $this->security->sanitizeInput($input['end_time'] ?? ''),
            'is_recurring' => !empty($input['is_recurring']) || ($input['recurrence_type'] ?? 'one_time') !== 'one_time',
            'recurrence_type' => $input['recurrence_type'] === 'one_time' ? null : $this->security->sanitizeInput($input['recurrence_type'] ?? ''),
            'recurrence_interval' => (int)($input['recurrence_interval'] ?? 1),
            'recurrence_days' => !empty($input['recurrence_days']) && is_array($input['recurrence_days']) ? 
                array_map('intval', $input['recurrence_days']) : null,
            'recurrence_end_date' => $this->security->sanitizeInput($input['recurrence_end_date'] ?? ''),
            'max_occurrences' => !empty($input['max_occurrences']) ? (int)$input['max_occurrences'] : null,
            'exclude_holidays' => !empty($input['exclude_holidays']),
            'has_breaks' => !empty($input['has_breaks']),
            'break_schedule' => !empty($input['break_schedule']) && is_array($input['break_schedule']) ? 
                $this->sanitizeBreakSchedule($input['break_schedule']) : null,
            'require_checkin' => !isset($input['require_checkin']) || !empty($input['require_checkin']),
            'allow_manual_checkin' => !isset($input['allow_manual_checkin']) || !empty($input['allow_manual_checkin']),
            'auto_checkout' => !empty($input['auto_checkout']),
            'auto_checkout_minutes' => (int)($input['auto_checkout_minutes'] ?? 480)
        ];
    }
    
    private function sanitizeBreakSchedule(array $breakSchedule): array
    {
        $sanitized = [];
        
        foreach ($breakSchedule as $break) {
            if (is_array($break)) {
                $sanitized[] = [
                    'name' => $this->security->sanitizeInput($break['name'] ?? ''),
                    'start' => $this->security->sanitizeInput($break['start'] ?? ''),
                    'end' => $this->security->sanitizeInput($break['end'] ?? ''),
                    'duration' => (int)($break['duration'] ?? 0)
                ];
            }
        }
        
        return $sanitized;
    }
}
