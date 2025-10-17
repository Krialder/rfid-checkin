<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers;

use RfidCheckin\Services\DatabaseService;
use RfidCheckin\Services\LoggingService;
use Exception;

/**
 * Event Controller
 * 
 * Handles event management functionality:
 * - List, create, edit, delete events
 * - Event scheduling and recurring events
 * - Group assignments
 * - Event statistics
 * 
 * @package RfidCheckin\Controllers
 */
class EventController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * List all events
     */
    public function index(): void
    {
        $user = $this->requireAuth();
        if (!$user) return;

        $pagination = $this->getPaginationParams();
        $filters = $this->getEventFilters();

        try {
            // Build WHERE clause based on user role and filters
            $whereConditions = ['1=1'];
            $params = [];

            // Non-admin users can only see events they're assigned to
            if ($user['role'] !== 'admin' && $user['role'] !== 'manager') {
                $whereConditions[] = 'e.id IN (
                    SELECT DISTINCT eg.event_id 
                    FROM event_groups eg 
                    INNER JOIN user_groups ug ON eg.group_id = ug.group_id 
                    WHERE ug.user_id = ?
                )';
                $params[] = $user['id'];
            }

            // Apply filters
            if (!empty($filters['status'])) {
                $whereConditions[] = 'e.status = ?';
                $params[] = $filters['status'];
            }

            if (!empty($filters['date_from'])) {
                $whereConditions[] = 'e.start_date >= ?';
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $whereConditions[] = 'e.end_date <= ?';
                $params[] = $filters['date_to'];
            }

            if (!empty($filters['search'])) {
                $whereConditions[] = '(e.name LIKE ? OR e.description LIKE ?)';
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $whereClause = implode(' AND ', $whereConditions);

            // Get total count
            $totalCount = $this->db->selectValue(
                "SELECT COUNT(*) FROM events e WHERE {$whereClause}",
                $params
            );

            // Get events
            $events = $this->db->selectAll(
                "SELECT e.*, 
                        COUNT(DISTINCT a.user_id) as attendance_count,
                        COUNT(DISTINCT eg.group_id) as assigned_groups
                 FROM events e
                 LEFT JOIN attendance a ON e.id = a.event_id
                 LEFT JOIN event_groups eg ON e.id = eg.event_id
                 WHERE {$whereClause}
                 GROUP BY e.id
                 ORDER BY e.start_date DESC, e.start_time DESC
                 LIMIT ? OFFSET ?",
                array_merge($params, [$pagination['limit'], $pagination['offset']])
            );

            $paginationData = $this->buildPagination($totalCount, $pagination['page'], $pagination['limit']);

            if ($this->isAjaxRequest()) {
                $this->jsonSuccess([
                    'events' => $events,
                    'pagination' => $paginationData,
                    'total' => $totalCount
                ]);
            } else {
                echo $this->render('events/index', [
                    'title' => 'Events',
                    'events' => $events,
                    'pagination' => $paginationData,
                    'filters' => $filters,
                    'can_create' => in_array($user['role'], ['admin', 'manager'])
                ]);
            }

        } catch (Exception $e) {
            $this->logger->error('Event listing error', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);

            if ($this->isAjaxRequest()) {
                $this->jsonError('Error loading events', 500);
            } else {
                $this->renderError('Unable to load events');
            }
        }
    }

    /**
     * Show create event form
     */
    public function create(): void
    {
        $user = $this->requireAuth();
        if (!$user) return;

        // Only admins and managers can create events
        if (!in_array($user['role'], ['admin', 'manager'])) {
            $this->renderForbidden();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->store();
            return;
        }

        echo $this->render('events/create', [
            'title' => 'Create Event',
            'groups' => $this->getGroups(),
            'error' => $_SESSION['event_error'] ?? null
        ]);

        unset($_SESSION['event_error']);
    }

    /**
     * Store new event
     */
    public function store(): void
    {
        $user = $this->requireAuth();
        if (!$user) return;

        if (!in_array($user['role'], ['admin', 'manager'])) {
            $this->renderForbidden();
            return;
        }

        $input = $this->getInput();
        $validation = $this->validateEventData($input);

        if (!empty($validation['errors'])) {
            if ($this->isAjaxRequest()) {
                $this->jsonError('Validation failed', 422, $validation['errors']);
            } else {
                $_SESSION['event_error'] = implode('<br>', $validation['errors']);
                header('Location: /events/create');
                exit;
            }
            return;
        }

        try {
            $eventData = $validation['data'];
            $eventData['created_by'] = $user['id'];
            $eventData['created_at'] = date('Y-m-d H:i:s');

            $eventId = $this->db->insert('events', $eventData);

            // Assign groups if specified
            if (!empty($input['group_ids'])) {
                $this->assignGroupsToEvent($eventId, $input['group_ids']);
            }

            $this->logger->info('Event created', [
                'event_id' => $eventId,
                'created_by' => $user['id'],
                'event_name' => $eventData['name']
            ]);

            if ($this->isAjaxRequest()) {
                $this->jsonSuccess(['event_id' => $eventId], 'Event created successfully');
            } else {
                header('Location: /events');
                exit;
            }

        } catch (Exception $e) {
            $this->logger->error('Event creation error', [
                'user_id' => $user['id'],
                'event_data' => $eventData ?? [],
                'error' => $e->getMessage()
            ]);

            if ($this->isAjaxRequest()) {
                $this->jsonError('Error creating event', 500);
            } else {
                $_SESSION['event_error'] = 'Error creating event. Please try again.';
                header('Location: /events/create');
                exit;
            }
        }
    }

    /**
     * Show edit event form
     */
    public function edit(int $id): void
    {
        $user = $this->requireAuth();
        if (!$user) return;

        if (!in_array($user['role'], ['admin', 'manager'])) {
            $this->renderForbidden();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->update($id);
            return;
        }

        try {
            $event = $this->db->selectOne(
                "SELECT * FROM events WHERE id = ?",
                [$id]
            );

            if (!$event) {
                $this->renderNotFound();
                return;
            }

            // Get assigned groups
            $assignedGroups = $this->db->selectAll(
                "SELECT group_id FROM event_groups WHERE event_id = ?",
                [$id]
            );

            $event['assigned_groups'] = array_column($assignedGroups, 'group_id');

            echo $this->render('events/edit', [
                'title' => 'Edit Event',
                'event' => $event,
                'groups' => $this->getGroups(),
                'error' => $_SESSION['event_error'] ?? null
            ]);

            unset($_SESSION['event_error']);

        } catch (Exception $e) {
            $this->logger->error('Event edit form error', [
                'event_id' => $id,
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);

            $this->renderError('Unable to load event');
        }
    }

    /**
     * Update event
     */
    public function update(int $id): void
    {
        $user = $this->requireAuth();
        if (!$user) return;

        if (!in_array($user['role'], ['admin', 'manager'])) {
            $this->renderForbidden();
            return;
        }

        $input = $this->getInput();
        $validation = $this->validateEventData($input, $id);

        if (!empty($validation['errors'])) {
            if ($this->isAjaxRequest()) {
                $this->jsonError('Validation failed', 422, $validation['errors']);
            } else {
                $_SESSION['event_error'] = implode('<br>', $validation['errors']);
                header("Location: /events/{$id}/edit");
                exit;
            }
            return;
        }

        try {
            $eventData = $validation['data'];
            $eventData['updated_at'] = date('Y-m-d H:i:s');

            $this->db->update('events', $eventData, ['id' => $id]);

            // Update group assignments
            $this->db->delete('event_groups', ['event_id' => $id]);
            if (!empty($input['group_ids'])) {
                $this->assignGroupsToEvent($id, $input['group_ids']);
            }

            $this->logger->info('Event updated', [
                'event_id' => $id,
                'updated_by' => $user['id'],
                'event_name' => $eventData['name']
            ]);

            if ($this->isAjaxRequest()) {
                $this->jsonSuccess([], 'Event updated successfully');
            } else {
                header('Location: /events');
                exit;
            }

        } catch (Exception $e) {
            $this->logger->error('Event update error', [
                'event_id' => $id,
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);

            if ($this->isAjaxRequest()) {
                $this->jsonError('Error updating event', 500);
            } else {
                $_SESSION['event_error'] = 'Error updating event. Please try again.';
                header("Location: /events/{$id}/edit");
                exit;
            }
        }
    }

    /**
     * Delete event
     */
    public function delete(int $id): void
    {
        $user = $this->requireAuth();
        if (!$user) return;

        if (!in_array($user['role'], ['admin', 'manager'])) {
            $this->renderForbidden();
            return;
        }

        try {
            // Check if event has attendance records
            $attendanceCount = $this->db->selectValue(
                "SELECT COUNT(*) FROM attendance WHERE event_id = ?",
                [$id]
            );

            if ($attendanceCount > 0) {
                if ($this->isAjaxRequest()) {
                    $this->jsonError('Cannot delete event with attendance records', 400);
                } else {
                    $_SESSION['event_error'] = 'Cannot delete event with attendance records';
                    header('Location: /events');
                    exit;
                }
                return;
            }

            // Delete event and related records
            $this->db->delete('event_groups', ['event_id' => $id]);
            $this->db->delete('events', ['id' => $id]);

            $this->logger->info('Event deleted', [
                'event_id' => $id,
                'deleted_by' => $user['id']
            ]);

            if ($this->isAjaxRequest()) {
                $this->jsonSuccess([], 'Event deleted successfully');
            } else {
                header('Location: /events');
                exit;
            }

        } catch (Exception $e) {
            $this->logger->error('Event deletion error', [
                'event_id' => $id,
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);

            if ($this->isAjaxRequest()) {
                $this->jsonError('Error deleting event', 500);
            } else {
                $_SESSION['event_error'] = 'Error deleting event. Please try again.';
                header('Location: /events');
                exit;
            }
        }
    }

    /**
     * Get upcoming events (API)
     */
    public function getUpcoming(): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            $this->jsonError('Authentication required', 401);
            return;
        }

        $limit = min(50, (int)($_GET['limit'] ?? 10));

        try {
            $whereConditions = ['e.status = ?', 'e.start_date >= CURDATE()'];
            $params = ['active'];

            // Non-admin users can only see events they're assigned to
            if ($user['role'] !== 'admin' && $user['role'] !== 'manager') {
                $whereConditions[] = 'e.id IN (
                    SELECT DISTINCT eg.event_id 
                    FROM event_groups eg 
                    INNER JOIN user_groups ug ON eg.group_id = ug.group_id 
                    WHERE ug.user_id = ?
                )';
                $params[] = $user['id'];
            }

            $whereClause = implode(' AND ', $whereConditions);

            $events = $this->db->selectAll(
                "SELECT e.id, e.name, e.start_date, e.start_time, e.end_date, e.end_time,
                        e.location, e.description, e.capacity
                 FROM events e
                 WHERE {$whereClause}
                 ORDER BY e.start_date, e.start_time
                 LIMIT ?",
                array_merge($params, [$limit])
            );

            $this->jsonSuccess($events);

        } catch (Exception $e) {
            $this->logger->error('Upcoming events error', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);

            $this->jsonError('Error loading upcoming events', 500);
        }
    }

    /**
     * Assign groups to event
     */
    public function assignGroups(): void
    {
        $user = $this->requireAuth();
        if (!$user) return;

        if (!in_array($user['role'], ['admin', 'manager'])) {
            $this->renderForbidden();
            return;
        }

        $input = $this->getInput();
        $eventId = (int)($input['event_id'] ?? 0);
        $groupIds = $input['group_ids'] ?? [];

        if (!$eventId) {
            $this->jsonError('Event ID is required', 400);
            return;
        }

        try {
            // Verify event exists
            $event = $this->db->selectOne("SELECT id FROM events WHERE id = ?", [$eventId]);
            if (!$event) {
                $this->jsonError('Event not found', 404);
                return;
            }

            // Remove existing assignments
            $this->db->delete('event_groups', ['event_id' => $eventId]);

            // Add new assignments
            if (!empty($groupIds)) {
                $this->assignGroupsToEvent($eventId, $groupIds);
            }

            $this->logger->info('Event groups assigned', [
                'event_id' => $eventId,
                'group_ids' => $groupIds,
                'assigned_by' => $user['id']
            ]);

            $this->jsonSuccess([], 'Groups assigned successfully');

        } catch (Exception $e) {
            $this->logger->error('Group assignment error', [
                'event_id' => $eventId,
                'group_ids' => $groupIds,
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);

            $this->jsonError('Error assigning groups', 500);
        }
    }

    /**
     * Validate event data
     */
    private function validateEventData(array $input, int $eventId = null): array
    {
        $errors = [];
        $data = [];

        // Name
        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            $errors['name'] = 'Event name is required';
        } elseif (strlen($name) > 255) {
            $errors['name'] = 'Event name is too long';
        } else {
            $data['name'] = $name;
        }

        // Description
        $data['description'] = trim($input['description'] ?? '');

        // Location
        $data['location'] = trim($input['location'] ?? '');

        // Start date
        $startDate = $input['start_date'] ?? '';
        if (empty($startDate)) {
            $errors['start_date'] = 'Start date is required';
        } elseif (!strtotime($startDate)) {
            $errors['start_date'] = 'Invalid start date';
        } else {
            $data['start_date'] = $startDate;
        }

        // Start time
        $startTime = $input['start_time'] ?? '';
        if (empty($startTime)) {
            $errors['start_time'] = 'Start time is required';
        } else {
            $data['start_time'] = $startTime;
        }

        // End date (optional)
        $endDate = $input['end_date'] ?? '';
        if (!empty($endDate)) {
            if (!strtotime($endDate)) {
                $errors['end_date'] = 'Invalid end date';
            } elseif (!empty($startDate) && strtotime($endDate) < strtotime($startDate)) {
                $errors['end_date'] = 'End date must be after start date';
            } else {
                $data['end_date'] = $endDate;
            }
        }

        // End time
        $endTime = $input['end_time'] ?? '';
        if (!empty($endTime)) {
            $data['end_time'] = $endTime;
        }

        // Capacity
        $capacity = (int)($input['capacity'] ?? 0);
        if ($capacity > 0) {
            $data['capacity'] = $capacity;
        }

        // Status
        $status = $input['status'] ?? 'active';
        if (!in_array($status, ['active', 'inactive', 'cancelled'])) {
            $errors['status'] = 'Invalid status';
        } else {
            $data['status'] = $status;
        }

        return ['errors' => $errors, 'data' => $data];
    }

    /**
     * Assign groups to event
     */
    private function assignGroupsToEvent(int $eventId, array $groupIds): void
    {
        foreach ($groupIds as $groupId) {
            $this->db->insert('event_groups', [
                'event_id' => $eventId,
                'group_id' => (int)$groupId,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * Get available groups
     */
    private function getGroups(): array
    {
        return $this->db->selectAll(
            "SELECT id, name, description FROM groups WHERE is_active = 1 ORDER BY name"
        );
    }

    /**
     * Get event filters from request
     */
    private function getEventFilters(): array
    {
        return [
            'status' => $_GET['status'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
            'search' => trim($_GET['search'] ?? '')
        ];
    }
}