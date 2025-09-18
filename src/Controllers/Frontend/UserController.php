<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers\Frontend;

use RfidCheckin\Controllers\BaseFrontendController;
use RfidCheckin\Repositories\UserRepository;
use RfidCheckin\Repositories\EventRepository;
use RfidCheckin\Repositories\CheckinRepository;
use Exception;

/**
 * User Controller
 * 
 * Handles user-facing functionality including profile management,
 * event registration, check-in history, and personal settings.
 * 
 * Features:
 * - User profile management
 * - Password change functionality
 * - RFID tag management
 * - Check-in history and statistics
 * - Event registration and management
 * - Personal preferences
 * - Account security settings
 * - Two-factor authentication
 * 
 * @package RfidCheckin\Controllers\Frontend
 * @version 1.0.0
 * @author Senior Development Team
 */
class UserController extends BaseFrontendController
{
    private UserRepository $userRepository;
    private EventRepository $eventRepository;
    private CheckinRepository $checkinRepository;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->userRepository = new UserRepository($this->db);
        $this->eventRepository = new EventRepository($this->db);
        $this->checkinRepository = new CheckinRepository($this->db);
        
        // Require authentication for all user actions
        $this->requireAuth();
        
        // Set common breadcrumbs
        $this->addBreadcrumb('Dashboard', '/dashboard');
    }

    /**
     * User profile page
     */
    public function profile(): void
    {
        try {
            $user = $this->auth->getCurrentUser();
            $userStats = $this->getUserStatistics($user['user_id']);
            $recentActivity = $this->getRecentUserActivity($user['user_id']);

            $data = [
                'user' => $user,
                'user_stats' => $userStats,
                'recent_activity' => $recentActivity,
                'available_groups' => $this->getAvailableGroups(),
                'security_settings' => $this->getSecuritySettings($user['user_id'])
            ];

            $this->addBreadcrumb('My Profile');

            $this->render('user.profile', $data, [
                'title' => 'My Profile',
                'description' => 'View and manage your profile information',
                'page_class' => 'user-profile'
            ]);

            $this->logUserAction('profile_viewed');

        } catch (Exception $e) {
            $this->logger->error('User profile error', [
                'message' => $e->getMessage(),
                'user_id' => $this->auth->getCurrentUser()['user_id'] ?? null
            ]);

            $this->redirectWithError('/dashboard', 'Unable to load profile');
        }
    }

    /**
     * Edit profile form
     */
    public function editProfile(): void
    {
        if ($this->isMethod('POST')) {
            $this->handleProfileUpdate();
            return;
        }

        try {
            $user = $this->auth->getCurrentUser();
            $fullUser = $this->userRepository->getUserById($user['user_id']);

            if (!$fullUser) {
                $this->redirectWithError('/dashboard', 'User not found');
                return;
            }

            $data = [
                'user' => $fullUser,
                'timezones' => $this->getTimezones(),
                'countries' => $this->getCountries()
            ];

            $this->addBreadcrumb('My Profile', '/user/profile');
            $this->addBreadcrumb('Edit Profile');

            $this->render('user.edit-profile', $data, [
                'title' => 'Edit Profile',
                'description' => 'Update your profile information',
                'page_class' => 'user-edit-profile'
            ]);

        } catch (Exception $e) {
            $this->logger->error('Edit profile form error', [
                'message' => $e->getMessage(),
                'user_id' => $this->auth->getCurrentUser()['user_id'] ?? null
            ]);

            $this->redirectWithError('/user/profile', 'Unable to load edit form');
        }
    }

    /**
     * Change password form
     */
    public function changePassword(): void
    {
        if ($this->isMethod('POST')) {
            $this->handlePasswordChange();
            return;
        }

        try {
            $this->addBreadcrumb('My Profile', '/user/profile');
            $this->addBreadcrumb('Change Password');

            $this->render('user.change-password', [], [
                'title' => 'Change Password',
                'description' => 'Update your account password',
                'page_class' => 'user-change-password'
            ]);

        } catch (Exception $e) {
            $this->logger->error('Change password form error', [
                'message' => $e->getMessage()
            ]);

            $this->redirectWithError('/user/profile', 'Unable to load password change form');
        }
    }

    /**
     * RFID tag management
     */
    public function rfidManagement(): void
    {
        if ($this->isMethod('POST')) {
            $this->handleRfidUpdate();
            return;
        }

        try {
            $user = $this->auth->getCurrentUser();
            $rfidHistory = $this->getRfidTagHistory($user['user_id']);

            $data = [
                'current_rfid' => $user['rfid_tag'] ?? null,
                'rfid_history' => $rfidHistory,
                'can_change_rfid' => $this->canUserChangeRfid($user),
                'rfid_requirements' => $this->getRfidRequirements()
            ];

            $this->addBreadcrumb('My Profile', '/user/profile');
            $this->addBreadcrumb('RFID Management');

            $this->render('user.rfid-management', $data, [
                'title' => 'RFID Tag Management',
                'description' => 'Manage your RFID tag assignments',
                'page_class' => 'user-rfid-management'
            ]);

        } catch (Exception $e) {
            $this->logger->error('RFID management error', [
                'message' => $e->getMessage(),
                'user_id' => $this->auth->getCurrentUser()['user_id'] ?? null
            ]);

            $this->redirectWithError('/user/profile', 'Unable to load RFID management');
        }
    }

    /**
     * Check-in history
     */
    public function checkinHistory(): void
    {
        try {
            $user = $this->auth->getCurrentUser();
            $page = (int)($_GET['page'] ?? 1);
            $dateRange = $_GET['range'] ?? '30days';
            $eventFilter = $_GET['event'] ?? '';

            $filters = ['user_id' => $user['user_id']];
            if ($dateRange !== 'all') {
                $filters['date_range'] = $dateRange;
            }
            if ($eventFilter) {
                $filters['event_id'] = $eventFilter;
            }

            $checkins = $this->checkinRepository->getCheckins($filters, $page, 25);
            $totalCheckins = $this->checkinRepository->getCheckinCount($filters);
            $checkinStats = $this->getCheckinStatistics($user['user_id'], $dateRange);
            $userEvents = $this->getUserEvents($user['user_id']);

            $data = [
                'checkins' => $checkins,
                'pagination' => $this->calculatePagination($totalCheckins, $page, 25),
                'checkin_stats' => $checkinStats,
                'date_range' => $dateRange,
                'event_filter' => $eventFilter,
                'user_events' => $userEvents,
                'chart_data' => $this->getCheckinChartData($user['user_id'], $dateRange)
            ];

            $this->addBreadcrumb('Check-in History');

            $this->render('user.checkin-history', $data, [
                'title' => 'My Check-in History',
                'description' => 'View your check-in history and statistics',
                'page_class' => 'user-checkin-history',
                'require_charts' => true,
                'require_datatables' => true
            ]);

            $this->logUserAction('checkin_history_viewed', ['filters' => $filters]);

        } catch (Exception $e) {
            $this->logger->error('Check-in history error', [
                'message' => $e->getMessage(),
                'user_id' => $this->auth->getCurrentUser()['user_id'] ?? null
            ]);

            $this->redirectWithError('/dashboard', 'Unable to load check-in history');
        }
    }

    /**
     * Events management (user registration)
     */
    public function events(): void
    {
        try {
            $user = $this->auth->getCurrentUser();
            $filter = $_GET['filter'] ?? 'upcoming';

            $availableEvents = $this->getAvailableEvents($user['user_id'], $filter);
            $registeredEvents = $this->getRegisteredEvents($user['user_id']);
            $eventStats = $this->getUserEventStats($user['user_id']);

            $data = [
                'available_events' => $availableEvents,
                'registered_events' => $registeredEvents,
                'event_stats' => $eventStats,
                'filter' => $filter,
                'can_register' => $this->canUserRegisterForEvents($user)
            ];

            $this->addBreadcrumb('My Events');

            $this->render('user.events', $data, [
                'title' => 'My Events',
                'description' => 'Manage your event registrations',
                'page_class' => 'user-events'
            ]);

        } catch (Exception $e) {
            $this->logger->error('User events error', [
                'message' => $e->getMessage(),
                'user_id' => $this->auth->getCurrentUser()['user_id'] ?? null
            ]);

            $this->redirectWithError('/dashboard', 'Unable to load events');
        }
    }

    /**
     * Account settings
     */
    public function settings(): void
    {
        if ($this->isMethod('POST')) {
            $this->handleSettingsUpdate();
            return;
        }

        try {
            $user = $this->auth->getCurrentUser();
            $userSettings = $this->getUserSettings($user['user_id']);
            $notificationSettings = $this->getNotificationSettings($user['user_id']);

            $data = [
                'user_settings' => $userSettings,
                'notification_settings' => $notificationSettings,
                'available_themes' => $this->getAvailableThemes(),
                'available_languages' => $this->getAvailableLanguages(),
                'timezone_list' => $this->getTimezones()
            ];

            $this->addBreadcrumb('Account Settings');

            $this->render('user.settings', $data, [
                'title' => 'Account Settings',
                'description' => 'Manage your account preferences',
                'page_class' => 'user-settings'
            ]);

        } catch (Exception $e) {
            $this->logger->error('User settings error', [
                'message' => $e->getMessage(),
                'user_id' => $this->auth->getCurrentUser()['user_id'] ?? null
            ]);

            $this->redirectWithError('/dashboard', 'Unable to load settings');
        }
    }

    /**
     * Security settings
     */
    public function security(): void
    {
        if ($this->isMethod('POST')) {
            $this->handleSecurityUpdate();
            return;
        }

        try {
            $user = $this->auth->getCurrentUser();
            $securityInfo = $this->getSecurityInfo($user['user_id']);
            $loginHistory = $this->getLoginHistory($user['user_id']);

            $data = [
                'security_info' => $securityInfo,
                'login_history' => $loginHistory,
                'two_factor_enabled' => $this->isTwoFactorEnabled($user['user_id']),
                'recovery_codes' => $this->getRecoveryCodes($user['user_id']),
                'active_sessions' => $this->getActiveSessions($user['user_id'])
            ];

            $this->addBreadcrumb('Security Settings');

            $this->render('user.security', $data, [
                'title' => 'Security Settings',
                'description' => 'Manage your account security',
                'page_class' => 'user-security'
            ]);

        } catch (Exception $e) {
            $this->logger->error('User security error', [
                'message' => $e->getMessage(),
                'user_id' => $this->auth->getCurrentUser()['user_id'] ?? null
            ]);

            $this->redirectWithError('/user/profile', 'Unable to load security settings');
        }
    }

    /**
     * AJAX: Register for event
     */
    public function ajaxRegisterEvent(): void
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

            // Check if event exists and is available for registration
            $event = $this->eventRepository->getEventById($eventId);
            if (!$event) {
                $this->renderJson(['success' => false, 'error' => 'Event not found'], 404);
                return;
            }

            // Check registration eligibility
            $canRegister = $this->canUserRegisterForEvent($user['user_id'], $eventId);
            if (!$canRegister['allowed']) {
                $this->renderJson(['success' => false, 'error' => $canRegister['reason']], 400);
                return;
            }

            // Register user for event
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
                'user_id' => $this->auth->getCurrentUser()['user_id'] ?? null,
                'event_id' => $_POST['event_id'] ?? null
            ]);

            $this->renderJson(['success' => false, 'error' => 'Registration failed'], 500);
        }
    }

    /**
     * AJAX: Unregister from event
     */
    public function ajaxUnregisterEvent(): void
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

            // Check if user can unregister
            $canUnregister = $this->canUserUnregisterFromEvent($user['user_id'], $eventId);
            if (!$canUnregister['allowed']) {
                $this->renderJson(['success' => false, 'error' => $canUnregister['reason']], 400);
                return;
            }

            // Unregister user from event
            $result = $this->eventRepository->unregisterUserFromEvent($user['user_id'], $eventId);

            if ($result) {
                $this->logUserAction('event_unregistered', ['event_id' => $eventId]);
                $this->renderJson(['success' => true, 'message' => 'Successfully unregistered from event']);
            } else {
                $this->renderJson(['success' => false, 'error' => 'Unregistration failed'], 500);
            }

        } catch (Exception $e) {
            $this->logger->error('AJAX event unregistration error', [
                'message' => $e->getMessage(),
                'user_id' => $this->auth->getCurrentUser()['user_id'] ?? null,
                'event_id' => $_POST['event_id'] ?? null
            ]);

            $this->renderJson(['success' => false, 'error' => 'Unregistration failed'], 500);
        }
    }

    /**
     * AJAX: Test RFID tag
     */
    public function ajaxTestRfid(): void
    {
        $this->requireAjax();

        if (!$this->validateCsrfToken()) {
            $this->renderJson(['success' => false, 'error' => 'Invalid security token'], 403);
            return;
        }

        try {
            $rfidTag = $_POST['rfid_tag'] ?? '';

            if (!$rfidTag) {
                $this->renderJson(['success' => false, 'error' => 'RFID tag required'], 400);
                return;
            }

            // Validate RFID tag format
            if (!$this->isValidRfidTag($rfidTag)) {
                $this->renderJson(['success' => false, 'error' => 'Invalid RFID tag format'], 400);
                return;
            }

            // Check if tag is already in use
            $existingUser = $this->userRepository->getUserByRfidTag($rfidTag);
            $currentUser = $this->auth->getCurrentUser();

            if ($existingUser && $existingUser['user_id'] !== $currentUser['user_id']) {
                $this->renderJson([
                    'success' => false, 
                    'error' => 'RFID tag is already assigned to another user'
                ], 400);
                return;
            }

            // Test RFID connectivity
            $testResult = $this->testRfidConnectivity($rfidTag);

            $this->renderJson([
                'success' => true,
                'test_result' => $testResult,
                'message' => 'RFID tag test completed'
            ]);

        } catch (Exception $e) {
            $this->logger->error('AJAX RFID test error', [
                'message' => $e->getMessage(),
                'rfid_tag' => $_POST['rfid_tag'] ?? null
            ]);

            $this->renderJson(['success' => false, 'error' => 'RFID test failed'], 500);
        }
    }

    /**
     * Handle profile update
     */
    private function handleProfileUpdate(): void
    {
        if (!$this->validateCsrfToken()) {
            $this->redirectWithError('/user/edit-profile', 'Invalid security token');
            return;
        }

        try {
            $user = $this->auth->getCurrentUser();
            $input = $this->sanitizeInput($_POST);

            // Validate required fields
            $required = ['first_name', 'last_name', 'email'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    $this->redirectWithError('/user/edit-profile', "Field '{$field}' is required");
                    return;
                }
            }

            // Check if email is already taken (excluding current user)
            if ($input['email'] !== $user['email']) {
                $existingUser = $this->userRepository->getUserByEmail($input['email']);
                if ($existingUser && $existingUser['user_id'] !== $user['user_id']) {
                    $this->redirectWithError('/user/edit-profile', 'Email address is already in use');
                    return;
                }
            }

            // Prepare update data
            $updateData = [
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
                'email' => $input['email'],
                'phone' => $input['phone'] ?? null,
                'timezone' => $input['timezone'] ?? null,
                'bio' => $input['bio'] ?? null
            ];

            $result = $this->userRepository->updateUser($user['user_id'], $updateData);

            if ($result) {
                // Update session data
                $this->auth->refreshUserSession();
                
                $this->logUserAction('profile_updated', ['updated_fields' => array_keys($updateData)]);
                $this->redirectWithSuccess('/user/profile', 'Profile updated successfully');
            } else {
                $this->redirectWithError('/user/edit-profile', 'Failed to update profile');
            }

        } catch (Exception $e) {
            $this->logger->error('Profile update error', [
                'message' => $e->getMessage(),
                'user_id' => $this->auth->getCurrentUser()['user_id'] ?? null
            ]);

            $this->redirectWithError('/user/edit-profile', 'An error occurred while updating profile');
        }
    }

    /**
     * Handle password change
     */
    private function handlePasswordChange(): void
    {
        if (!$this->validateCsrfToken()) {
            $this->redirectWithError('/user/change-password', 'Invalid security token');
            return;
        }

        try {
            $user = $this->auth->getCurrentUser();
            $input = $this->sanitizeInput($_POST);

            // Validate required fields
            if (empty($input['current_password']) || empty($input['new_password']) || empty($input['confirm_password'])) {
                $this->redirectWithError('/user/change-password', 'All password fields are required');
                return;
            }

            // Verify current password
            $fullUser = $this->userRepository->getUserById($user['user_id']);
            if (!password_verify($input['current_password'], $fullUser['password'])) {
                $this->redirectWithError('/user/change-password', 'Current password is incorrect');
                return;
            }

            // Validate new password
            if ($input['new_password'] !== $input['confirm_password']) {
                $this->redirectWithError('/user/change-password', 'New passwords do not match');
                return;
            }

            if (strlen($input['new_password']) < 8) {
                $this->redirectWithError('/user/change-password', 'New password must be at least 8 characters');
                return;
            }

            // Update password
            $hashedPassword = password_hash($input['new_password'], PASSWORD_DEFAULT);
            $result = $this->userRepository->updateUser($user['user_id'], ['password' => $hashedPassword]);

            if ($result) {
                $this->logUserAction('password_changed');
                $this->redirectWithSuccess('/user/profile', 'Password changed successfully');
            } else {
                $this->redirectWithError('/user/change-password', 'Failed to change password');
            }

        } catch (Exception $e) {
            $this->logger->error('Password change error', [
                'message' => $e->getMessage(),
                'user_id' => $this->auth->getCurrentUser()['user_id'] ?? null
            ]);

            $this->redirectWithError('/user/change-password', 'An error occurred while changing password');
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
    // These would handle specific user functions like:
    // - getUserStatistics()
    // - getRecentUserActivity()
    // - getCheckinStatistics()
    // - canUserRegisterForEvent()
    // - isValidRfidTag()
    // - etc.
}
