<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers\Api;

use RfidCheckin\Controllers\BaseApiController;
use RfidCheckin\Repositories\EventRepository;
use RfidCheckin\Repositories\UserRepository;
use RfidCheckin\Models\Event;
use Exception;

/**
 * Events API Controller
 * 
 * Handles all event-related API operations including CRUD operations,
 * event management, registration handling, and analytics.
 * Replaces scattered event functionality across multiple files.
 * 
 * Endpoints:
 * - GET /api/events - List events with filtering
 * - POST /api/events - Create new event
 * - GET /api/events/{id} - Get event details
 * - PUT /api/events/{id} - Update event
 * - DELETE /api/events/{id} - Delete event
 * - POST /api/events/{id}/register - Register user for event
 * - DELETE /api/events/{id}/register - Cancel registration
 * - GET /api/events/{id}/registrations - Get event registrations
 * - GET /api/events/{id}/checkins - Get event check-ins
 * - GET /api/events/{id}/analytics - Get event analytics
 * 
 * @package RfidCheckin\Controllers\Api
 * @version 1.0.0
 * @author Senior Development Team
 */
class EventsApiController extends BaseApiController
{
    private EventRepository $eventRepo;
    private UserRepository $userRepo;
    
    protected array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE'];
    protected bool $requiresAuth = true;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->eventRepo = new EventRepository();
        $this->userRepo = new UserRepository();
    }

    /**
     * Route handler - determines which method to call
     */
    public function handleRequest(): void
    {
        $this->executeWithErrorHandling(function() {
            $path = $_SERVER['PATH_INFO'] ?? '';
            $method = $_SERVER['REQUEST_METHOD'];
            
            // Parse path for event ID and sub-resource
            if (preg_match('/^\/(\d+)(?:\/(.+))?$/', $path, $matches)) {
                $eventId = (int) $matches[1];
                $subResource = $matches[2] ?? null;
                
                $this->handleEventSpecificRequest($eventId, $subResource, $method);
            } else {
                $this->handleGeneralRequest($method);
            }
        });
    }

    /**
     * Handle general event requests (no specific event ID)
     */
    private function handleGeneralRequest(string $method): void
    {
        switch ($method) {
            case 'GET':
                $this->listEvents();
                break;
            case 'POST':
                $this->createEvent();
                break;
            default:
                $this->respondError('Method not allowed', 405);
        }
    }

    /**
     * Handle event-specific requests
     */
    private function handleEventSpecificRequest(int $eventId, ?string $subResource, string $method): void
    {
        if ($subResource === null) {
            // Direct event operations
            switch ($method) {
                case 'GET':
                    $this->getEvent($eventId);
                    break;
                case 'PUT':
                    $this->updateEvent($eventId);
                    break;
                case 'DELETE':
                    $this->deleteEvent($eventId);
                    break;
                default:
                    $this->respondError('Method not allowed', 405);
            }
        } else {
            // Sub-resource operations
            switch ($subResource) {
                case 'register':
                    $this->handleRegistration($eventId, $method);
                    break;
                case 'registrations':
                    if ($method === 'GET') {
                        $this->getEventRegistrations($eventId);
                    } else {
                        $this->respondError('Method not allowed', 405);
                    }
                    break;
                case 'checkins':
                    if ($method === 'GET') {
                        $this->getEventCheckins($eventId);
                    } else {
                        $this->respondError('Method not allowed', 405);
                    }
                    break;
                case 'analytics':
                    if ($method === 'GET') {
                        $this->getEventAnalytics($eventId);
                    } else {
                        $this->respondError('Method not allowed', 405);
                    }
                    break;
                default:
                    $this->respondError('Endpoint not found', 404);
            }
        }
    }

    /**
     * List events with filtering and pagination
     * 
     * GET /api/events?page=1&limit=20&status=active&search=conference
     */
    private function listEvents(): void
    {
        $input = $this->sanitizeInput($this->getInput());
        $pagination = $this->validatePagination($input);
        
        // Build filters
        $filters = [];
        if (!empty($input['status'])) {
            $filters['status'] = $input['status'];
        }
        if (!empty($input['search'])) {
            $filters['search'] = $input['search'];
        }
        if (!empty($input['date_from'])) {
            $filters['date_from'] = $input['date_from'];
        }
        if (!empty($input['date_to'])) {
            $filters['date_to'] = $input['date_to'];
        }
        if (!empty($input['location'])) {
            $filters['location'] = $input['location'];
        }
        if (!empty($input['created_by'])) {
            $filters['created_by'] = (int) $input['created_by'];
        }
        if (!empty($input['tags'])) {
            $filters['tags'] = is_array($input['tags']) ? $input['tags'] : [$input['tags']];
        }

        // Check permissions
        if (!$this->hasPermissions(['admin', 'manage_events', 'view_reports'])) {
            // Regular users can only see active events or events they created
            $filters['status'] = 'active';
            if (!$this->hasPermissions(['view_reports'])) {
                $filters['created_by'] = $this->getCurrentUserId();
            }
        }

        $result = $this->eventRepo->findMany($filters, $pagination['page'], $pagination['limit']);

        $this->respondPaginated($result['data'], $result['pagination'], [
            'filters_applied' => array_keys($filters),
            'total_events' => $result['pagination']['total_items']
        ]);
    }

    /**
     * Create new event
     * 
     * POST /api/events
     */
    private function createEvent(): void
    {
        // Check permissions
        if (!$this->hasPermissions(['admin', 'manage_events'])) {
            $this->respondForbidden('Insufficient permissions to create events');
            return;
        }

        $input = $this->sanitizeInput($this->getInput());
        
        // Validate input
        $errors = Event::validate($input);
        if (!empty($errors)) {
            $this->respondValidationError($errors);
            return;
        }

        // Add creator
        $input['created_by'] = $this->getCurrentUserId();

        try {
            $eventId = $this->eventRepo->create($input);
            $event = $this->eventRepo->find($eventId, true);

            $this->respondSuccess([
                'message' => 'Event created successfully',
                'event' => $event
            ], 201);

        } catch (Exception $e) {
            $this->respondError('Failed to create event: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Get event details
     * 
     * GET /api/events/{id}
     */
    private function getEvent(int $eventId): void
    {
        $event = $this->eventRepo->find($eventId, true);
        
        if (!$event) {
            $this->respondNotFound('Event');
            return;
        }

        // Check permissions
        if (!$this->canViewEvent($event)) {
            $this->respondForbidden('Insufficient permissions to view this event');
            return;
        }

        $this->respondSuccess($event);
    }

    /**
     * Update event
     * 
     * PUT /api/events/{id}
     */
    private function updateEvent(int $eventId): void
    {
        $event = $this->eventRepo->find($eventId);
        
        if (!$event) {
            $this->respondNotFound('Event');
            return;
        }

        // Check permissions
        if (!$this->canEditEvent($event)) {
            $this->respondForbidden('Insufficient permissions to edit this event');
            return;
        }

        $input = $this->sanitizeInput($this->getInput());
        
        // Validate input
        $errors = Event::validate($input, true);
        if (!empty($errors)) {
            $this->respondValidationError($errors);
            return;
        }

        try {
            $success = $this->eventRepo->update($eventId, $input);
            
            if ($success) {
                $updatedEvent = $this->eventRepo->find($eventId, true);
                $this->respondSuccess([
                    'message' => 'Event updated successfully',
                    'event' => $updatedEvent
                ]);
            } else {
                $this->respondError('Failed to update event', 400);
            }

        } catch (Exception $e) {
            $this->respondError('Failed to update event: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Delete event
     * 
     * DELETE /api/events/{id}
     */
    private function deleteEvent(int $eventId): void
    {
        $event = $this->eventRepo->find($eventId);
        
        if (!$event) {
            $this->respondNotFound('Event');
            return;
        }

        // Check permissions
        if (!$this->hasPermissions(['admin']) && $event['created_by'] !== $this->getCurrentUserId()) {
            $this->respondForbidden('Insufficient permissions to delete this event');
            return;
        }

        try {
            $success = $this->eventRepo->delete($eventId);
            
            if ($success) {
                $this->respondSuccess([
                    'message' => 'Event deleted successfully',
                    'event_id' => $eventId
                ]);
            } else {
                $this->respondError('Failed to delete event', 400);
            }

        } catch (Exception $e) {
            $this->respondError('Failed to delete event: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Handle event registration
     */
    private function handleRegistration(int $eventId, string $method): void
    {
        $event = $this->eventRepo->find($eventId);
        
        if (!$event) {
            $this->respondNotFound('Event');
            return;
        }

        switch ($method) {
            case 'POST':
                $this->registerForEvent($eventId, $event);
                break;
            case 'DELETE':
                $this->cancelRegistration($eventId);
                break;
            default:
                $this->respondError('Method not allowed', 405);
        }
    }

    /**
     * Register user for event
     * 
     * POST /api/events/{id}/register
     */
    private function registerForEvent(int $eventId, array $event): void
    {
        $input = $this->sanitizeInput($this->getInput());
        
        // Determine user ID (admin can register others, users register themselves)
        $userId = $this->getCurrentUserId();
        if (!empty($input['user_id']) && $this->hasPermissions(['admin', 'manage_events'])) {
            $userId = (int) $input['user_id'];
        }

        try {
            $success = $this->eventRepo->registerUser($eventId, $userId, $input);
            
            if ($success) {
                $this->respondSuccess([
                    'message' => 'Successfully registered for event',
                    'event_id' => $eventId,
                    'user_id' => $userId
                ]);
            } else {
                $this->respondError('Registration failed', 400);
            }

        } catch (Exception $e) {
            $this->respondError('Registration failed: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Cancel event registration
     * 
     * DELETE /api/events/{id}/register
     */
    private function cancelRegistration(int $eventId): void
    {
        $input = $this->sanitizeInput($this->getInput());
        
        // Determine user ID
        $userId = $this->getCurrentUserId();
        if (!empty($input['user_id']) && $this->hasPermissions(['admin', 'manage_events'])) {
            $userId = (int) $input['user_id'];
        }

        try {
            $success = $this->eventRepo->cancelRegistration($eventId, $userId);
            
            if ($success) {
                $this->respondSuccess([
                    'message' => 'Registration cancelled successfully',
                    'event_id' => $eventId,
                    'user_id' => $userId
                ]);
            } else {
                $this->respondError('Cancellation failed', 400);
            }

        } catch (Exception $e) {
            $this->respondError('Cancellation failed: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Get event registrations
     * 
     * GET /api/events/{id}/registrations
     */
    private function getEventRegistrations(int $eventId): void
    {
        $event = $this->eventRepo->find($eventId);
        
        if (!$event) {
            $this->respondNotFound('Event');
            return;
        }

        // Check permissions
        if (!$this->canViewEventRegistrations($event)) {
            $this->respondForbidden('Insufficient permissions to view registrations');
            return;
        }

        $input = $this->sanitizeInput($this->getInput());
        $filters = [];
        
        if (!empty($input['status'])) {
            $filters['status'] = $input['status'];
        }
        if (isset($input['has_checked_in'])) {
            $filters['has_checked_in'] = (bool) $input['has_checked_in'];
        }

        $registrations = $this->eventRepo->getRegistrations($eventId, $filters);

        $this->respondSuccess([
            'event_id' => $eventId,
            'registrations' => $registrations,
            'summary' => [
                'total_registrations' => count($registrations),
                'confirmed' => count(array_filter($registrations, fn($r) => $r['status'] === 'confirmed')),
                'checked_in' => count(array_filter($registrations, fn($r) => $r['checkin_count'] > 0))
            ]
        ]);
    }

    /**
     * Get event check-ins
     * 
     * GET /api/events/{id}/checkins
     */
    private function getEventCheckins(int $eventId): void
    {
        $event = $this->eventRepo->find($eventId);
        
        if (!$event) {
            $this->respondNotFound('Event');
            return;
        }

        // Check permissions
        if (!$this->canViewEventCheckins($event)) {
            $this->respondForbidden('Insufficient permissions to view check-ins');
            return;
        }

        $input = $this->sanitizeInput($this->getInput());
        $filters = [];
        
        if (!empty($input['date_from'])) {
            $filters['date_from'] = $input['date_from'];
        }
        if (!empty($input['date_to'])) {
            $filters['date_to'] = $input['date_to'];
        }
        if (!empty($input['method'])) {
            $filters['method'] = $input['method'];
        }

        // This would be implemented in CheckinRepository
        $checkins = []; // Placeholder

        $this->respondSuccess([
            'event_id' => $eventId,
            'checkins' => $checkins,
            'summary' => [
                'total_checkins' => count($checkins),
                'unique_users' => 0 // Would be calculated
            ]
        ]);
    }

    /**
     * Get event analytics
     * 
     * GET /api/events/{id}/analytics
     */
    private function getEventAnalytics(int $eventId): void
    {
        $event = $this->eventRepo->find($eventId);
        
        if (!$event) {
            $this->respondNotFound('Event');
            return;
        }

        // Check permissions
        if (!$this->hasPermissions(['admin', 'view_reports']) && $event['created_by'] !== $this->getCurrentUserId()) {
            $this->respondForbidden('Insufficient permissions to view analytics');
            return;
        }

        $statistics = $this->eventRepo->getEventStatistics($eventId);

        $this->respondSuccess([
            'event_id' => $eventId,
            'event_name' => $event['name'],
            'analytics' => $statistics,
            'capacity_info' => [
                'capacity' => $event['capacity'],
                'registered' => $statistics['confirmed_registered'],
                'utilization_rate' => $event['capacity'] ? 
                    round(($statistics['confirmed_registered'] / $event['capacity']) * 100, 2) : null
            ]
        ]);
    }

    /**
     * Permission helper methods
     */
    private function canViewEvent(array $event): bool
    {
        // Admins and event managers can view all events
        if ($this->hasPermissions(['admin', 'manage_events', 'view_reports'])) {
            return true;
        }
        
        // Users can view active events or events they created
        return $event['status'] === 'active' || $event['created_by'] === $this->getCurrentUserId();
    }

    private function canEditEvent(array $event): bool
    {
        // Admins can edit all events
        if ($this->hasPermissions(['admin'])) {
            return true;
        }
        
        // Event managers can edit events they created
        if ($this->hasPermissions(['manage_events']) && $event['created_by'] === $this->getCurrentUserId()) {
            return true;
        }
        
        return false;
    }

    private function canViewEventRegistrations(array $event): bool
    {
        return $this->hasPermissions(['admin', 'manage_events', 'view_reports']) || 
               $event['created_by'] === $this->getCurrentUserId();
    }

    private function canViewEventCheckins(array $event): bool
    {
        return $this->hasPermissions(['admin', 'manage_events', 'view_reports']) || 
               $event['created_by'] === $this->getCurrentUserId();
    }
}
