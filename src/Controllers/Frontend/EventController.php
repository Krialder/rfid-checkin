<?php

declare(strict_types=1);

namespace App\Controllers\Frontend;

use App\Services\EventService;
use App\Services\UserGroupService;
use App\Services\UserService;
use RfidCheckin\Services\LoggingService;
use App\Core\SecurityManager;
use App\Core\TemplateEngine;
use App\Services\PerformanceCacheService;
use Exception;

/**
 * Event Management Frontend Controller
 * 
 * Handles all event management interfaces:
 * - Event listing and management
 * - Event creation and editing forms
 * - Event calendar views
 * - Event analytics and reports
 * 
 * Migrated from legacy admin/events.php to enterprise architecture
 * with proper service layer, caching, and security integration.
 * 
 * @package App\Controllers\Frontend
 * @version 2.0.0 - Enterprise Architecture Migration
 * @author Enterprise Development Team
 */
class EventController
{
    private EventService $eventService;
    private UserGroupService $groupService;
    private UserService $userService;
    private LoggingService $logger;
    private SecurityManager $security;
    private TemplateEngine $template;
    private PerformanceCacheService $cache;
    
    public function __construct()
    {
        $this->eventService = new EventService();
        $this->groupService = new UserGroupService();
        $this->userService = new UserService();
        $this->logger = LoggingService::getInstance();
        $this->security = SecurityManager::getInstance();
        $this->template = new TemplateEngine();
        $this->cache = PerformanceCacheService::getInstance();
    }
    
    /**
     * Event management dashboard
     */
    public function dashboard(): void
    {
        try {
            $this->requireAdminAccess();
            
            // Cache key for dashboard data
            $cacheKey = "event_dashboard_" . $_SESSION['user_id'];
            $dashboardData = $this->cache->get($cacheKey);
            
            if (!$dashboardData) {
                // Get dashboard statistics
                $stats = $this->eventService->getEventStatistics();
                
                // Get recent events
                $recentEvents = $this->eventService->getEvents([], 1, 10);
                
                // Get upcoming events
                $upcomingEvents = $this->eventService->getEvents([
                    'start_date_from' => date('Y-m-d'),
                    'status' => 'active'
                ], 1, 10);
                
                $dashboardData = [
                    'stats' => $stats,
                    'recent_events' => $recentEvents['events'] ?? [],
                    'upcoming_events' => $upcomingEvents['events'] ?? []
                ];
                
                // Cache for 5 minutes
                $this->cache->set($cacheKey, $dashboardData, 300);
            }
            
            $this->template->render('admin/events/dashboard', [
                'title' => 'Event Management',
                'stats' => $dashboardData['stats'],
                'recent_events' => $dashboardData['recent_events'],
                'upcoming_events' => $dashboardData['upcoming_events'],
                'csrf_token' => $this->security->generateCsrfToken()
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Event dashboard error', [
                'error' => $e->getMessage(),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            $this->template->render('error', [
                'title' => 'Error',
                'message' => 'Failed to load event dashboard'
            ]);
        }
    }

    /**
     * Events listing page
     */
    public function index(): void
    {
        try {
            $this->requireAuthentication();
            
            // Get filter parameters
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
            
            // Cache key based on filters and pagination
            $cacheKey = "events_list_" . md5(serialize($filters) . "_p{$page}_l{$limit}");
            $eventsData = $this->cache->get($cacheKey);
            
            if (!$eventsData) {
                $eventsData = $this->eventService->getEvents($filters, $page, $limit);
                
                // Cache for 2 minutes
                $this->cache->set($cacheKey, $eventsData, 120);
            }
            
            // Get filter options
            $filterOptions = $this->getFilterOptions();
            
            $this->template->render('admin/events/index', [
                'title' => 'Events',
                'events' => $eventsData['events'] ?? [],
                'pagination' => $eventsData['pagination'] ?? [],
                'filters' => $filters,
                'filter_options' => $filterOptions,
                'csrf_token' => $this->security->generateCsrfToken()
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Events listing error', [
                'error' => $e->getMessage(),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            $this->template->render('error', [
                'title' => 'Error',
                'message' => 'Failed to load events'
            ]);
        }
    }

    /**
     * Event details page
     */
    public function view(): void
    {
        try {
            $eventId = (int)($_GET['id'] ?? 0);

            if (!$eventId) {
                $this->redirectWithError('/events', 'Invalid event ID');
                return;
            }

            $event = $this->eventRepository->getEventById($eventId);

            if (!$event) {
                $this->redirectWithError('/events', 'Event not found');
                return;
            }

            $user = $this->auth->getCurrentUser();
            $canManageEvents = $this->hasPermissions(['admin', 'manage_events']);

            // Check if user can view this event
            if (!$canManageEvents && !$this->canUserViewEvent($user['user_id'], $eventId)) {
                $this->redirectWithError('/events', 'Access denied');
                return;
            }

            $registrations = $this->getEventRegistrations($eventId);
            $checkins = $this->getEventCheckins($eventId);
            $userRegistration = $this->getUserEventRegistration($user['user_id'], $eventId);
            $eventStats = $this->getEventStatistics($eventId);

            $data = [
                'event' => $event,
                'registrations' => $registrations,
                'checkins' => $checkins,
                'user_registration' => $userRegistration,
                'event_stats' => $eventStats,
                'can_manage_events' => $canManageEvents,
                'can_register' => $this->canUserRegisterForEvent($user['user_id'], $eventId),
                'qr_code_url' => $this->generateEventQrCode($eventId),
                'related_events' => $this->getRelatedEvents($eventId)
            ];

            $this->addBreadcrumb($event['title']);

            $this->render('events.view', $data, [
                'title' => $event['title'],
                'description' => $event['description'],
                'page_class' => 'event-details',
                'require_charts' => true
            ]);

            $this->logUserAction('event_viewed', ['event_id' => $eventId]);

        } catch (Exception $e) {
            $this->logger->error('Event view error', [
                'message' => $e->getMessage(),
                'event_id' => $_GET['id'] ?? null
            ]);

            $this->redirectWithError('/events', 'Unable to load event details');
        }
    }

    /**
     * Create event form
     */
    public function create(): void
    {
        $this->requirePermissions(['admin', 'manage_events']);

        if ($this->isMethod('POST')) {
            $this->handleEventCreation();
            return;
        }

        try {
            $data = [
                'event_categories' => $this->getEventCategories(),
                'user_groups' => $this->getUserGroups(),
                'default_duration' => $this->config->get('events.default_duration', 120),
                'max_capacity' => $this->config->get('events.max_capacity', 1000),
                'timezones' => $this->getTimezones()
            ];

            $this->addBreadcrumb('Create Event');

            $this->render('events.create', $data, [
                'title' => 'Create Event',
                'description' => 'Create a new event',
                'page_class' => 'event-create'
            ]);

        } catch (Exception $e) {
            $this->logger->error('Event create form error', [
                'message' => $e->getMessage()
            ]);

            $this->redirectWithError('/events', 'Unable to load create form');
        }
    }

    /**
     * Edit event form
     */
    public function edit(): void
    {
        $this->requirePermissions(['admin', 'manage_events']);

        $eventId = (int)($_GET['id'] ?? 0);

        if (!$eventId) {
            $this->redirectWithError('/events', 'Invalid event ID');
            return;
        }

        if ($this->isMethod('POST')) {
            $this->handleEventUpdate($eventId);
            return;
        }

        try {
            $event = $this->eventRepository->getEventById($eventId);

            if (!$event) {
                $this->redirectWithError('/events', 'Event not found');
                return;
            }

            $data = [
                'event' => $event,
                'event_categories' => $this->getEventCategories(),
                'user_groups' => $this->getUserGroups(),
                'timezones' => $this->getTimezones(),
                'registration_count' => $this->getEventRegistrationCount($eventId),
                'checkin_count' => $this->getEventCheckinCount($eventId)
            ];

            $this->addBreadcrumb('Edit Event');

            $this->render('events.edit', $data, [
                'title' => 'Edit Event',
                'description' => 'Edit event details',
                'page_class' => 'event-edit'
            ]);

        } catch (Exception $e) {
            $this->logger->error('Event edit form error', [
                'message' => $e->getMessage(),
                'event_id' => $eventId
            ]);

            $this->redirectWithError('/events', 'Unable to load edit form');
        }
    }

    /**
     * Event check-in management
     */
    public function checkins(): void
    {
        $this->requirePermissions(['admin', 'manage_events', 'check_in_users']);

        try {
            $eventId = (int)($_GET['id'] ?? 0);

            if (!$eventId) {
                $this->redirectWithError('/events', 'Invalid event ID');
                return;
            }

            $event = $this->eventRepository->getEventById($eventId);

            if (!$event) {
                $this->redirectWithError('/events', 'Event not found');
                return;
            }

            $page = (int)($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? '';
            $statusFilter = $_GET['status'] ?? '';

            $filters = ['event_id' => $eventId];
            if ($search) $filters['search'] = $search;
            if ($statusFilter) $filters['status'] = $statusFilter;

            $checkins = $this->checkinRepository->getCheckins($filters, $page, 25);
            $totalCheckins = $this->checkinRepository->getCheckinCount($filters);
            $checkinStats = $this->getEventCheckinStatistics($eventId);

            $data = [
                'event' => $event,
                'checkins' => $checkins,
                'pagination' => $this->calculatePagination($totalCheckins, $page, 25),
                'checkin_stats' => $checkinStats,
                'search' => $search,
                'status_filter' => $statusFilter,
                'can_manual_checkin' => $this->hasPermissions(['admin', 'check_in_users']),
                'registered_users' => $this->getEventRegisteredUsers($eventId)
            ];

            $this->addBreadcrumb($event['title'], "/events/view?id={$eventId}");
            $this->addBreadcrumb('Check-ins');

            $this->render('events.checkins', $data, [
                'title' => $event['title'] . ' - Check-ins',
                'description' => 'Manage event check-ins',
                'page_class' => 'event-checkins',
                'require_datatables' => true
            ]);

            $this->logUserAction('event_checkins_viewed', ['event_id' => $eventId]);

        } catch (Exception $e) {
            $this->logger->error('Event checkins error', [
                'message' => $e->getMessage(),
                'event_id' => $_GET['id'] ?? null
            ]);

            $this->redirectWithError('/events', 'Unable to load event check-ins');
        }
    }

    /**
     * Event analytics page
     */
    public function analytics(): void
    {
        $this->requirePermissions(['admin', 'manage_events', 'view_reports']);

        try {
            $eventId = (int)($_GET['id'] ?? 0);

            if (!$eventId) {
                $this->redirectWithError('/events', 'Invalid event ID');
                return;
            }

            $event = $this->eventRepository->getEventById($eventId);

            if (!$event) {
                $this->redirectWithError('/events', 'Event not found');
                return;
            }

            $timeframe = $_GET['timeframe'] ?? 'event';
            $analytics = $this->generateEventAnalytics($eventId, $timeframe);

            $data = [
                'event' => $event,
                'analytics' => $analytics,
                'timeframe' => $timeframe,
                'chart_data' => $this->getEventChartData($eventId, $timeframe),
                'comparison_data' => $this->getEventComparisonData($eventId)
            ];

            $this->addBreadcrumb($event['title'], "/events/view?id={$eventId}");
            $this->addBreadcrumb('Analytics');

            $this->render('events.analytics', $data, [
                'title' => $event['title'] . ' - Analytics',
                'description' => 'Event analytics and insights',
                'page_class' => 'event-analytics',
                'require_charts' => true
            ]);

            $this->logUserAction('event_analytics_viewed', [
                'event_id' => $eventId,
                'timeframe' => $timeframe
            ]);

        } catch (Exception $e) {
            $this->logger->error('Event analytics error', [
                'message' => $e->getMessage(),
                'event_id' => $_GET['id'] ?? null
            ]);

            $this->redirectWithError('/events', 'Unable to load event analytics');
        }
    }

    /**
     * AJAX: Register for event
     */
    public function ajaxRegister(): void
    {
        $this->requireAjax();

        if (!$this->validateCsrfToken()) {
            $this->renderJson(['success' => false, 'error' => 'Invalid security token'], 403);
            return;
        }

        try {
            $user = $this->auth->getCurrentUser();
            $eventId = (int)($_POST['event_id'] ?? 0);

            if (!$eventId) {
                $this->renderJson(['success' => false, 'error' => 'Invalid event ID'], 400);
                return;
            }

            // Check registration eligibility
            $canRegister = $this->canUserRegisterForEvent($user['user_id'], $eventId);
            if (!$canRegister['allowed']) {
                $this->renderJson(['success' => false, 'error' => $canRegister['reason']], 400);
                return;
            }

            $result = $this->eventRepository->registerUserForEvent($user['user_id'], $eventId);

            if ($result) {
                $this->logUserAction('event_registered', ['event_id' => $eventId]);
                $this->renderJson(['success' => true, 'message' => 'Successfully registered for event']);
            } else {
                $this->renderJson(['success' => false, 'error' => 'Registration failed'], 500);
            }

        } catch (Exception $e) {
            $this->logger->error('AJAX event registration error', [
                'message' => $e->getMessage(),
                'event_id' => $_POST['event_id'] ?? null
            ]);

            $this->renderJson(['success' => false, 'error' => 'Registration failed'], 500);
        }
    }

    /**
     * AJAX: Manual check-in
     */
    public function ajaxManualCheckin(): void
    {
        $this->requireAjax();
        $this->requirePermissions(['admin', 'check_in_users']);

        if (!$this->validateCsrfToken()) {
            $this->renderJson(['success' => false, 'error' => 'Invalid security token'], 403);
            return;
        }

        try {
            $eventId = (int)($_POST['event_id'] ?? 0);
            $userId = (int)($_POST['user_id'] ?? 0);
            $notes = $_POST['notes'] ?? '';

            if (!$eventId || !$userId) {
                $this->renderJson(['success' => false, 'error' => 'Invalid event or user ID'], 400);
                return;
            }

            // Verify event and user exist
            $event = $this->eventRepository->getEventById($eventId);
            $user = $this->userRepository->getUserById($userId);

            if (!$event || !$user) {
                $this->renderJson(['success' => false, 'error' => 'Event or user not found'], 404);
                return;
            }

            // Process manual check-in
            $result = $this->checkinRepository->createManualCheckin($userId, $eventId, [
                'created_by' => $this->auth->getCurrentUser()['user_id'],
                'notes' => $notes,
                'checkin_type' => 'manual'
            ]);

            if ($result) {
                $this->logUserAction('manual_checkin_created', [
                    'event_id' => $eventId,
                    'checked_in_user_id' => $userId,
                    'notes' => $notes
                ]);

                $this->renderJson(['success' => true, 'message' => 'Manual check-in successful']);
            } else {
                $this->renderJson(['success' => false, 'error' => 'Check-in failed'], 500);
            }

        } catch (Exception $e) {
            $this->logger->error('AJAX manual checkin error', [
                'message' => $e->getMessage(),
                'event_id' => $_POST['event_id'] ?? null,
                'user_id' => $_POST['user_id'] ?? null
            ]);

            $this->renderJson(['success' => false, 'error' => 'Check-in failed'], 500);
        }
    }

    /**
     * AJAX: Delete event
     */
    public function ajaxDelete(): void
    {
        $this->requireAjax();
        $this->requirePermissions(['admin', 'manage_events']);

        if (!$this->validateCsrfToken()) {
            $this->renderJson(['success' => false, 'error' => 'Invalid security token'], 403);
            return;
        }

        try {
            $eventId = (int)($_POST['event_id'] ?? 0);

            if (!$eventId) {
                $this->renderJson(['success' => false, 'error' => 'Invalid event ID'], 400);
                return;
            }

            // Check if event can be deleted
            $checkinCount = $this->getEventCheckinCount($eventId);
            if ($checkinCount > 0) {
                $this->renderJson([
                    'success' => false, 
                    'error' => 'Cannot delete event with existing check-ins'
                ], 400);
                return;
            }

            $result = $this->eventRepository->deleteEvent($eventId);

            if ($result) {
                $this->logUserAction('event_deleted', ['event_id' => $eventId]);
                $this->renderJson(['success' => true, 'message' => 'Event deleted successfully']);
            } else {
                $this->renderJson(['success' => false, 'error' => 'Failed to delete event'], 500);
            }

        } catch (Exception $e) {
            $this->logger->error('AJAX delete event error', [
                'message' => $e->getMessage(),
                'event_id' => $_POST['event_id'] ?? null
            ]);

            $this->renderJson(['success' => false, 'error' => 'Failed to delete event'], 500);
        }
    }

    /**
     * Handle event creation
     */
    private function handleEventCreation(): void
    {
        if (!$this->validateCsrfToken()) {
            $this->redirectWithError('/events/create', 'Invalid security token');
            return;
        }

        try {
            $input = $this->sanitizeInput($_POST);

            // Validate required fields
            $required = ['title', 'start_date', 'end_date', 'capacity'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    $this->redirectWithError('/events/create', "Field '{$field}' is required");
                    return;
                }
            }

            // Validate dates
            $startDate = new \DateTime($input['start_date']);
            $endDate = new \DateTime($input['end_date']);

            if ($endDate <= $startDate) {
                $this->redirectWithError('/events/create', 'End date must be after start date');
                return;
            }

            // Prepare event data
            $eventData = [
                'title' => $input['title'],
                'description' => $input['description'] ?? '',
                'start_date' => $startDate->format('Y-m-d H:i:s'),
                'end_date' => $endDate->format('Y-m-d H:i:s'),
                'capacity' => (int)$input['capacity'],
                'category' => $input['category'] ?? 'general',
                'location' => $input['location'] ?? '',
                'is_public' => isset($input['is_public']) ? 1 : 0,
                'requires_registration' => isset($input['requires_registration']) ? 1 : 0,
                'created_by' => $this->auth->getCurrentUser()['user_id'],
                'status' => 'active'
            ];

            $eventId = $this->eventRepository->createEvent($eventData);

            if ($eventId) {
                $this->logUserAction('event_created', ['event_id' => $eventId, 'event_data' => $eventData]);
                $this->redirectWithSuccess('/events/view?id=' . $eventId, 'Event created successfully');
            } else {
                $this->redirectWithError('/events/create', 'Failed to create event');
            }

        } catch (Exception $e) {
            $this->logger->error('Event creation error', [
                'message' => $e->getMessage(),
                'input' => $_POST
            ]);

            $this->redirectWithError('/events/create', 'An error occurred while creating event');
        }
    }

    /**
     * Calculate pagination data
     */
    private function calculatePagination(int $total, int $currentPage, int $perPage): array
    {
        $totalPages = (int)ceil($total / $perPage);
        
        return [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'per_page' => $perPage,
            'total_items' => $total,
            'has_previous' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages,
            'previous_page' => max(1, $currentPage - 1),
            'next_page' => min($totalPages, $currentPage + 1)
        ];
    }

    // Additional helper methods would be implemented here...
    // These would handle specific event functions like:
    // - getEventStats()
    // - canUserViewEvent()
    // - canUserRegisterForEvent()
    // - generateEventQrCode()
    // - getEventAnalytics()
    // - etc.
}
