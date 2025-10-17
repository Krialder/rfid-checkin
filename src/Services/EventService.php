<?php

namespace RfidCheckin\Services;

use RfidCheckin\Repositories\EventRepository;
use RfidCheckin\Services\PerformanceCacheService;
use RfidCheckin\Core\LoggingService;
use RfidCheckin\Core\ValidationService;
use Exception;

/**
 * Event Service
 * 
 * Business logic layer for event management:
 * - Event lifecycle management
 * - Recurring event generation
 * - Holiday conflict detection
 * - Group assignment and participant management
 * - Break schedule management
 * - Event validation and business rules
 */
class EventService
{
    private EventRepository $eventRepository;
    private PerformanceCacheService $cache;
    private LoggingService $logger;
    private ValidationService $validator;
    
    public function __construct()
    {
        $this->eventRepository = new EventRepository();
        $this->cache = PerformanceCacheService::getInstance();
        $this->logger = LoggingService::getInstance();
        $this->validator = ValidationService::getInstance();
    }
    
    /**
     * Create new event with validation and instance generation
     */
    public function createEvent(array $eventData, int $userId): array
    {
        try {
            // Validate event data
            $validation = $this->validateEventData($eventData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'validation_errors' => $validation['errors']
                ];
            }
            
            // Prepare event data
            $eventData['created_by'] = $userId;
            
            // Create event
            $result = $this->eventRepository->create($eventData);
            
            if ($result['success']) {
                $eventId = $result['event_id'];
                
                // Generate instances for recurring events
                if ($eventData['is_recurring']) {
                    $instanceCount = $this->generateEventInstances($eventId, $eventData);
                    $this->logger->info('Generated event instances', [
                        'event_id' => $eventId,
                        'instance_count' => $instanceCount
                    ]);
                }
                
                // Clear event-related cache
                $this->cache->clearNamespace('events');
                
                return [
                    'success' => true,
                    'event_id' => $eventId,
                    'message' => 'Event created successfully'
                ];
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to create event', [
                'error' => $e->getMessage(),
                'event_data' => $eventData,
                'user_id' => $userId
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to create event: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update existing event
     */
    public function updateEvent(int $eventId, array $eventData, int $userId): array
    {
        try {
            // Validate event data
            $validation = $this->validateEventData($eventData, $eventId);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'validation_errors' => $validation['errors']
                ];
            }
            
            // Get existing event for comparison
            $existingEvent = $this->eventRepository->findById($eventId);
            if (!$existingEvent) {
                return [
                    'success' => false,
                    'error' => 'Event not found'
                ];
            }
            
            // Update event
            $result = $this->eventRepository->update($eventId, $eventData);
            
            if ($result) {
                // Check if recurrence settings changed
                $recurrenceChanged = $this->hasRecurrenceChanged($existingEvent, $eventData);
                
                if ($recurrenceChanged && $eventData['is_recurring']) {
                    // Regenerate instances
                    $this->eventRepository->deleteFutureInstancesWithoutCheckins($eventId);
                    $instanceCount = $this->generateEventInstances($eventId, $eventData);
                    
                    $this->logger->info('Regenerated event instances after update', [
                        'event_id' => $eventId,
                        'instance_count' => $instanceCount
                    ]);
                }
                
                // Clear event-related cache
                $this->cache->clearNamespace('events');
                $this->cache->delete("event_{$eventId}");
                
                return [
                    'success' => true,
                    'message' => 'Event updated successfully'
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Failed to update event'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to update event', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
                'event_data' => $eventData,
                'user_id' => $userId
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to update event: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete event
     */
    public function deleteEvent(int $eventId): array
    {
        try {
            $result = $this->eventRepository->delete($eventId);
            
            if ($result['success']) {
                // Clear event-related cache
                $this->cache->clearNamespace('events');
                $this->cache->delete("event_{$eventId}");
                
                $this->logger->info('Event deleted/deactivated', [
                    'event_id' => $eventId,
                    'action' => $result['action']
                ]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to delete event', [
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to delete event: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get event by ID with caching
     */
    public function getEvent(int $eventId): ?array
    {
        try {
            $cacheKey = "event_{$eventId}";
            $event = $this->cache->get($cacheKey, 'events');
            
            if ($event === null) {
                $event = $this->eventRepository->findById($eventId);
                
                if ($event) {
                    // Get additional data
                    $event['participants'] = $this->eventRepository->getEventParticipants($eventId);
                    $event['groups'] = $this->eventRepository->getEventGroups($eventId);
                    $event['instances'] = $this->eventRepository->getInstances($eventId, 50);
                    
                    // Cache for 5 minutes
                    $this->cache->set($cacheKey, $event, 300, 'events');
                }
            }
            
            return $event;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get event', [
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    /**
     * Get events with filtering and pagination
     */
    public function getEvents(array $filters = [], int $page = 1, int $limit = 25): array
    {
        try {
            $cacheKey = 'events_' . md5(serialize($filters) . "_{$page}_{$limit}");
            $result = $this->cache->get($cacheKey, 'events');
            
            if ($result === null) {
                $result = $this->eventRepository->findWithFilters($filters, $page, $limit);
                
                // Cache for 2 minutes
                $this->cache->set($cacheKey, $result, 120, 'events');
            }
            
            return [
                'success' => true,
                'events' => $result['events'],
                'pagination' => $result['pagination']
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get events', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve events: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get event statistics with caching
     */
    public function getEventStatistics(): array
    {
        try {
            $cacheKey = 'event_statistics';
            $stats = $this->cache->get($cacheKey, 'events');
            
            if ($stats === null) {
                $stats = $this->eventRepository->getStatistics();
                
                // Cache for 10 minutes
                $this->cache->set($cacheKey, $stats, 600, 'events');
            }
            
            return [
                'success' => true,
                'stats' => $stats
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get event statistics', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve statistics: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Assign groups to event
     */
    public function assignGroupsToEvent(int $eventId, array $groupIds, int $userId): array
    {
        try {
            $result = $this->eventRepository->assignGroups($eventId, $groupIds);
            
            if ($result) {
                // Clear event-related cache
                $this->cache->delete("event_{$eventId}");
                $this->cache->clearNamespace('events');
                
                $this->logger->info('Groups assigned to event', [
                    'event_id' => $eventId,
                    'group_ids' => $groupIds,
                    'assigned_by' => $userId
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Groups assigned successfully'
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Failed to assign groups'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to assign groups to event', [
                'event_id' => $eventId,
                'group_ids' => $groupIds,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to assign groups: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get event groups
     */
    public function getEventGroups(int $eventId): array
    {
        try {
            $groups = $this->eventRepository->getEventGroups($eventId);
            
            return [
                'success' => true,
                'groups' => $groups
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get event groups', [
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve event groups: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Calculate unique participants from groups
     */
    public function calculateUniqueParticipants(array $groupIds): array
    {
        try {
            $result = $this->eventRepository->getUniqueParticipantCount($groupIds);
            
            return [
                'success' => true,
                'unique_count' => $result['unique_count'],
                'total_memberships' => $result['total_memberships'],
                'deduplication_savings' => $result['deduplication_savings']
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to calculate unique participants', [
                'group_ids' => $groupIds,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to calculate participants: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate event instances for recurring events
     */
    public function generateEventInstances(int $eventId, array $eventData): int
    {
        try {
            if (!$eventData['is_recurring']) {
                return 0;
            }
            
            $instances = $this->calculateRecurringInstances($eventData);
            $generatedCount = 0;
            
            foreach ($instances as $instance) {
                $this->eventRepository->createInstance($eventId, $instance);
                $generatedCount++;
            }
            
            $this->logger->info('Generated event instances', [
                'event_id' => $eventId,
                'count' => $generatedCount
            ]);
            
            return $generatedCount;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to generate event instances', [
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Regenerate instances for existing event
     */
    public function regenerateEventInstances(int $eventId): array
    {
        try {
            $event = $this->eventRepository->findById($eventId);
            
            if (!$event || !$event['is_recurring']) {
                return [
                    'success' => false,
                    'error' => 'Event not found or not recurring'
                ];
            }
            
            // Delete future instances without check-ins
            $deletedCount = $this->eventRepository->deleteFutureInstancesWithoutCheckins($eventId);
            
            // Generate new instances
            $generatedCount = $this->generateEventInstances($eventId, $event);
            
            // Clear cache
            $this->cache->delete("event_{$eventId}");
            $this->cache->clearNamespace('events');
            
            return [
                'success' => true,
                'message' => "Regenerated {$generatedCount} instances (deleted {$deletedCount} old instances)",
                'instances_generated' => $generatedCount,
                'instances_deleted' => $deletedCount
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to regenerate event instances', [
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to regenerate instances: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate event data
     */
    private function validateEventData(array $eventData, ?int $eventId = null): array
    {
        $errors = [];
        
        // Required fields
        if (empty($eventData['name'])) {
            $errors['name'] = 'Event name is required';
        }
        
        if (empty($eventData['start_date'])) {
            $errors['start_date'] = 'Start date is required';
        }
        
        if (empty($eventData['start_time'])) {
            $errors['start_time'] = 'Start time is required';
        }
        
        // Date validation
        if (!empty($eventData['start_date']) && !empty($eventData['end_date'])) {
            if (strtotime($eventData['end_date']) < strtotime($eventData['start_date'])) {
                $errors['end_date'] = 'End date must be after start date';
            }
        }
        
        // Time validation
        if (!empty($eventData['start_time']) && !empty($eventData['end_time'])) {
            if (strtotime($eventData['end_time']) <= strtotime($eventData['start_time'])) {
                $errors['end_time'] = 'End time must be after start time';
            }
        }
        
        // Capacity validation
        if (!empty($eventData['capacity']) && $eventData['capacity'] < 1) {
            $errors['capacity'] = 'Capacity must be at least 1';
        }
        
        // Recurrence validation
        if (!empty($eventData['is_recurring'])) {
            if (empty($eventData['recurrence_type'])) {
                $errors['recurrence_type'] = 'Recurrence type is required for recurring events';
            }
            
            if (!empty($eventData['recurrence_interval']) && $eventData['recurrence_interval'] < 1) {
                $errors['recurrence_interval'] = 'Recurrence interval must be at least 1';
            }
            
            if ($eventData['recurrence_type'] === 'weekly' && empty($eventData['recurrence_days'])) {
                $errors['recurrence_days'] = 'Days of week are required for weekly recurrence';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Check if recurrence settings changed
     */
    private function hasRecurrenceChanged(array $existingEvent, array $newEventData): bool
    {
        $recurrenceFields = [
            'is_recurring',
            'recurrence_type',
            'recurrence_interval',
            'recurrence_days',
            'recurrence_end_date',
            'max_occurrences',
            'exclude_holidays'
        ];
        
        foreach ($recurrenceFields as $field) {
            $existing = $existingEvent[$field] ?? null;
            $new = $newEventData[$field] ?? null;
            
            // Handle array comparison for recurrence_days
            if ($field === 'recurrence_days') {
                $existing = is_array($existing) ? $existing : [];
                $new = is_array($new) ? $new : [];
                
                if (array_diff($existing, $new) || array_diff($new, $existing)) {
                    return true;
                }
            } else {
                if ($existing != $new) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Calculate recurring instances
     */
    private function calculateRecurringInstances(array $eventData): array
    {
        $instances = [];
        $startDate = new \DateTime($eventData['start_date']);
        $endDate = $eventData['recurrence_end_date'] ? new \DateTime($eventData['recurrence_end_date']) : null;
        $maxOccurrences = $eventData['max_occurrences'] ?? null;
        $interval = $eventData['recurrence_interval'] ?? 1;
        $type = $eventData['recurrence_type'];
        $excludeHolidays = $eventData['exclude_holidays'] ?? false;
        
        $currentDate = clone $startDate;
        $count = 0;
        $maxCount = $maxOccurrences ?? 365; // Limit to prevent infinite loops
        
        while ($count < $maxCount) {
            // Check end date limit
            if ($endDate && $currentDate > $endDate) {
                break;
            }
            
            // Check if date should be included
            $includeDate = true;
            
            // Check for weekly recurrence day restrictions
            if ($type === 'weekly' && !empty($eventData['recurrence_days'])) {
                $dayOfWeek = (int)$currentDate->format('w'); // 0 = Sunday
                if (!in_array($dayOfWeek, $eventData['recurrence_days'])) {
                    $includeDate = false;
                }
            }
            
            // Check for holiday conflicts
            if ($includeDate && $excludeHolidays) {
                $holidayCheck = $this->checkHolidayConflict($currentDate);
                if ($holidayCheck['is_holiday']) {
                    $includeDate = false;
                }
            }
            
            if ($includeDate) {
                $instances[] = [
                    'instance_date' => $currentDate->format('Y-m-d'),
                    'start_time' => $eventData['start_time'],
                    'end_time' => $eventData['end_time'],
                    'status' => 'scheduled',
                    'is_holiday_conflict' => false,
                    'holiday_name' => null
                ];
                $count++;
            }
            
            // Advance to next occurrence
            switch ($type) {
                case 'daily':
                    $currentDate->add(new \DateInterval("P{$interval}D"));
                    break;
                case 'weekly':
                    $currentDate->add(new \DateInterval("P{$interval}W"));
                    break;
                case 'monthly':
                    $currentDate->add(new \DateInterval("P{$interval}M"));
                    break;
                case 'yearly':
                    $currentDate->add(new \DateInterval("P{$interval}Y"));
                    break;
                default:
                    break 2; // Break out of while loop
            }
        }
        
        return $instances;
    }
    
    /**
     * Check for holiday conflicts
     */
    private function checkHolidayConflict(\DateTime $date): array
    {
        // This would integrate with a holiday service
        // For now, return no conflict
        return [
            'is_holiday' => false,
            'holiday_name' => null
        ];
    }
}
