<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers\Api;

use RfidCheckin\Controllers\BaseApiController;
use RfidCheckin\Repositories\UserRepository;
use RfidCheckin\Repositories\CheckinRepository;
use RfidCheckin\Models\User;
use RfidCheckin\Services\AuthenticationService;
use Exception;

/**
 * Users API Controller
 * 
 * Handles all user-related API operations including user management,
 * profile operations, authentication, and user analytics.
 * Replaces scattered user functionality across multiple files.
 * 
 * Endpoints:
 * - GET /api/users - List users with filtering
 * - POST /api/users - Create new user
 * - GET /api/users/{id} - Get user details
 * - PUT /api/users/{id} - Update user
 * - DELETE /api/users/{id} - Delete user
 * - GET /api/users/{id}/checkins - Get user check-in history
 * - GET /api/users/{id}/events - Get user events
 * - PUT /api/users/{id}/password - Change user password
 * - PUT /api/users/{id}/status - Update user status
 * - POST /api/users/{id}/rfid - Assign RFID tag
 * - DELETE /api/users/{id}/rfid - Remove RFID tag
 * 
 * @package RfidCheckin\Controllers\Api
 * @version 1.0.0
 * @author Senior Development Team
 */
class UsersApiController extends BaseApiController
{
    private UserRepository $userRepo;
    private CheckinRepository $checkinRepo;
    
    protected array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE'];
    protected bool $requiresAuth = true;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->userRepo = new UserRepository();
        $this->checkinRepo = new CheckinRepository();
    }

    /**
     * Route handler - determines which method to call
     */
    public function handleRequest(): void
    {
        $this->executeWithErrorHandling(function() {
            $path = $_SERVER['PATH_INFO'] ?? '';
            $method = $_SERVER['REQUEST_METHOD'];
            
            // Parse path for user ID and sub-resource
            if (preg_match('/^\/(\d+)(?:\/(.+))?$/', $path, $matches)) {
                $userId = (int) $matches[1];
                $subResource = $matches[2] ?? null;
                
                $this->handleUserSpecificRequest($userId, $subResource, $method);
            } elseif ($path === '/me') {
                $this->handleCurrentUserRequest($method);
            } else {
                $this->handleGeneralRequest($method);
            }
        });
    }

    /**
     * Handle general user requests (no specific user ID)
     */
    private function handleGeneralRequest(string $method): void
    {
        switch ($method) {
            case 'GET':
                $this->listUsers();
                break;
            case 'POST':
                $this->createUser();
                break;
            default:
                $this->respondError('Method not allowed', 405);
        }
    }

    /**
     * Handle current user requests (/me endpoint)
     */
    private function handleCurrentUserRequest(string $method): void
    {
        $userId = $this->getCurrentUserId();
        
        if (!$userId) {
            $this->respondUnauthorized();
            return;
        }

        switch ($method) {
            case 'GET':
                $this->getUser($userId, true);
                break;
            case 'PUT':
                $this->updateUser($userId, true);
                break;
            default:
                $this->respondError('Method not allowed', 405);
        }
    }

    /**
     * Handle user-specific requests
     */
    private function handleUserSpecificRequest(int $userId, ?string $subResource, string $method): void
    {
        if ($subResource === null) {
            // Direct user operations
            switch ($method) {
                case 'GET':
                    $this->getUser($userId);
                    break;
                case 'PUT':
                    $this->updateUser($userId);
                    break;
                case 'DELETE':
                    $this->deleteUser($userId);
                    break;
                default:
                    $this->respondError('Method not allowed', 405);
            }
        } else {
            // Sub-resource operations
            switch ($subResource) {
                case 'checkins':
                    if ($method === 'GET') {
                        $this->getUserCheckins($userId);
                    } else {
                        $this->respondError('Method not allowed', 405);
                    }
                    break;
                case 'events':
                    if ($method === 'GET') {
                        $this->getUserEvents($userId);
                    } else {
                        $this->respondError('Method not allowed', 405);
                    }
                    break;
                case 'password':
                    if ($method === 'PUT') {
                        $this->changePassword($userId);
                    } else {
                        $this->respondError('Method not allowed', 405);
                    }
                    break;
                case 'status':
                    if ($method === 'PUT') {
                        $this->updateUserStatus($userId);
                    } else {
                        $this->respondError('Method not allowed', 405);
                    }
                    break;
                case 'rfid':
                    if ($method === 'POST' || $method === 'PUT') {
                        $this->assignRfidTag($userId);
                    } elseif ($method === 'DELETE') {
                        $this->removeRfidTag($userId);
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
     * List users with filtering and pagination
     * 
     * GET /api/users?page=1&limit=20&status=active&search=john
     */
    private function listUsers(): void
    {
        // Check permissions
        if (!$this->hasPermissions(['admin', 'manage_users', 'view_reports'])) {
            $this->respondForbidden('Insufficient permissions to list users');
            return;
        }

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
        if (!empty($input['group_id'])) {
            $filters['group_id'] = (int) $input['group_id'];
        }
        if (!empty($input['has_rfid'])) {
            $filters['has_rfid'] = filter_var($input['has_rfid'], FILTER_VALIDATE_BOOLEAN);
        }
        if (!empty($input['department'])) {
            $filters['department'] = $input['department'];
        }

        $result = $this->userRepo->findMany($filters, $pagination['page'], $pagination['limit']);

        // Remove sensitive data from response
        foreach ($result['data'] as &$user) {
            unset($user['password']);
            if (!$this->hasPermissions(['admin'])) {
                unset($user['rfid_tag']); // Only admins can see RFID tags in list
            }
        }

        $this->respondPaginated($result['data'], $result['pagination'], [
            'filters_applied' => array_keys($filters),
            'total_users' => $result['pagination']['total_items']
        ]);
    }

    /**
     * Create new user
     * 
     * POST /api/users
     */
    private function createUser(): void
    {
        // Check permissions
        if (!$this->hasPermissions(['admin', 'manage_users'])) {
            $this->respondForbidden('Insufficient permissions to create users');
            return;
        }

        $input = $this->sanitizeInput($this->getInput());
        
        // Validate input
        $errors = User::validate($input);
        if (!empty($errors)) {
            $this->respondValidationError($errors);
            return;
        }

        // Check for duplicate email
        if ($this->userRepo->findByEmail($input['email'])) {
            $this->respondValidationError(['email' => 'Email address is already in use']);
            return;
        }

        // Check for duplicate RFID tag
        if (!empty($input['rfid_tag']) && $this->userRepo->findByRfidTag($input['rfid_tag'])) {
            $this->respondValidationError(['rfid_tag' => 'RFID tag is already assigned']);
            return;
        }

        // Add creator
        $input['created_by'] = $this->getCurrentUserId();

        try {
            $userId = $this->userRepo->create($input);
            $user = $this->userRepo->find($userId);
            
            // Remove password from response
            unset($user['password']);

            $this->respondSuccess([
                'message' => 'User created successfully',
                'user' => $user
            ], 201);

        } catch (Exception $e) {
            $this->respondError('Failed to create user: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Get user details
     * 
     * GET /api/users/{id}
     */
    private function getUser(int $userId, bool $isCurrentUser = false): void
    {
        $user = $this->userRepo->find($userId);
        
        if (!$user) {
            $this->respondNotFound('User');
            return;
        }

        // Check permissions
        if (!$isCurrentUser && !$this->canViewUser($user)) {
            $this->respondForbidden('Insufficient permissions to view this user');
            return;
        }

        // Remove sensitive data based on permissions
        unset($user['password']);
        
        if (!$isCurrentUser && !$this->hasPermissions(['admin', 'manage_users'])) {
            unset($user['rfid_tag'], $user['failed_login_attempts'], $user['locked_until']);
        }

        // Add additional data for current user or if permitted
        if ($isCurrentUser || $this->hasPermissions(['admin', 'view_reports'])) {
            $user['statistics'] = $this->getUserStatistics($userId);
        }

        $this->respondSuccess($user);
    }

    /**
     * Update user
     * 
     * PUT /api/users/{id}
     */
    private function updateUser(int $userId, bool $isCurrentUser = false): void
    {
        $user = $this->userRepo->find($userId);
        
        if (!$user) {
            $this->respondNotFound('User');
            return;
        }

        // Check permissions
        if (!$isCurrentUser && !$this->canEditUser($user)) {
            $this->respondForbidden('Insufficient permissions to edit this user');
            return;
        }

        $input = $this->sanitizeInput($this->getInput());
        
        // Validate input
        $errors = User::validate($input, true);
        if (!empty($errors)) {
            $this->respondValidationError($errors);
            return;
        }

        // Restrict what current user can update about themselves
        if ($isCurrentUser) {
            $allowedFields = ['firstname', 'lastname', 'phone', 'department'];
            $input = array_intersect_key($input, array_flip($allowedFields));
        }

        // Check for duplicate email (excluding current user)
        if (!empty($input['email']) && $input['email'] !== $user['email']) {
            $existingUser = $this->userRepo->findByEmail($input['email']);
            if ($existingUser && $existingUser['user_id'] !== $userId) {
                $this->respondValidationError(['email' => 'Email address is already in use']);
                return;
            }
        }

        // Check for duplicate RFID tag (excluding current user)
        if (!empty($input['rfid_tag']) && $input['rfid_tag'] !== $user['rfid_tag']) {
            $existingUser = $this->userRepo->findByRfidTag($input['rfid_tag']);
            if ($existingUser && $existingUser['user_id'] !== $userId) {
                $this->respondValidationError(['rfid_tag' => 'RFID tag is already assigned']);
                return;
            }
        }

        try {
            $success = $this->userRepo->update($userId, $input);
            
            if ($success) {
                $updatedUser = $this->userRepo->find($userId);
                unset($updatedUser['password']);
                
                $this->respondSuccess([
                    'message' => 'User updated successfully',
                    'user' => $updatedUser
                ]);
            } else {
                $this->respondError('Failed to update user', 400);
            }

        } catch (Exception $e) {
            $this->respondError('Failed to update user: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Delete user
     * 
     * DELETE /api/users/{id}
     */
    private function deleteUser(int $userId): void
    {
        // Check permissions - only admins can delete users
        if (!$this->hasPermissions(['admin'])) {
            $this->respondForbidden('Insufficient permissions to delete users');
            return;
        }

        $user = $this->userRepo->find($userId);
        
        if (!$user) {
            $this->respondNotFound('User');
            return;
        }

        // Prevent deletion of self
        if ($userId === $this->getCurrentUserId()) {
            $this->respondError('Cannot delete your own account', 400);
            return;
        }

        try {
            $success = $this->userRepo->delete($userId);
            
            if ($success) {
                $this->respondSuccess([
                    'message' => 'User deleted successfully',
                    'user_id' => $userId
                ]);
            } else {
                $this->respondError('Failed to delete user', 400);
            }

        } catch (Exception $e) {
            $this->respondError('Failed to delete user: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Get user check-in history
     * 
     * GET /api/users/{id}/checkins
     */
    private function getUserCheckins(int $userId): void
    {
        $user = $this->userRepo->find($userId);
        
        if (!$user) {
            $this->respondNotFound('User');
            return;
        }

        // Check permissions
        if (!$this->canViewUserCheckins($userId)) {
            $this->respondForbidden('Insufficient permissions to view check-ins');
            return;
        }

        $input = $this->sanitizeInput($this->getInput());
        $pagination = $this->validatePagination($input);
        
        $filters = [];
        if (!empty($input['date_from'])) {
            $filters['date_from'] = $input['date_from'];
        }
        if (!empty($input['date_to'])) {
            $filters['date_to'] = $input['date_to'];
        }
        if (!empty($input['event_id'])) {
            $filters['event_id'] = (int) $input['event_id'];
        }
        if (!empty($input['method'])) {
            $filters['method'] = $input['method'];
        }

        $result = $this->checkinRepo->getUserCheckinHistory($userId, $filters, $pagination['page'], $pagination['limit']);

        $this->respondPaginated($result['data'], $result['pagination'], [
            'user_id' => $userId,
            'filters_applied' => array_keys($filters)
        ]);
    }

    /**
     * Get user events (registered events)
     * 
     * GET /api/users/{id}/events
     */
    private function getUserEvents(int $userId): void
    {
        $user = $this->userRepo->find($userId);
        
        if (!$user) {
            $this->respondNotFound('User');
            return;
        }

        // Check permissions
        if (!$this->canViewUserEvents($userId)) {
            $this->respondForbidden('Insufficient permissions to view user events');
            return;
        }

        $input = $this->sanitizeInput($this->getInput());
        
        $filters = ['user_id' => $userId];
        if (!empty($input['status'])) {
            $filters['event_status'] = $input['status'];
        }
        if (!empty($input['registration_status'])) {
            $filters['registration_status'] = $input['registration_status'];
        }

        // This would be implemented to get user's registered events
        $events = $this->userRepo->getUserEvents($userId, $filters);

        $this->respondSuccess([
            'user_id' => $userId,
            'events' => $events,
            'summary' => [
                'total_events' => count($events),
                'upcoming_events' => count(array_filter($events, fn($e) => $e['event_date'] > date('Y-m-d H:i:s')))
            ]
        ]);
    }

    /**
     * Change user password
     * 
     * PUT /api/users/{id}/password
     */
    private function changePassword(int $userId): void
    {
        $user = $this->userRepo->find($userId);
        
        if (!$user) {
            $this->respondNotFound('User');
            return;
        }

        $isCurrentUser = $userId === $this->getCurrentUserId();
        
        // Check permissions
        if (!$isCurrentUser && !$this->hasPermissions(['admin'])) {
            $this->respondForbidden('Insufficient permissions to change password');
            return;
        }

        $input = $this->sanitizeInput($this->getInput());
        
        // Validate required fields
        $requiredFields = ['new_password'];
        if ($isCurrentUser) {
            $requiredFields[] = 'current_password';
        }
        
        $errors = $this->validateRequired($input, $requiredFields);
        if (!empty($errors)) {
            $this->respondValidationError($errors);
            return;
        }

        // Validate current password for current user
        if ($isCurrentUser) {
            if (!$this->userRepo->verifyPassword($userId, $input['current_password'])) {
                $this->respondValidationError(['current_password' => 'Current password is incorrect']);
                return;
            }
        }

        // Validate new password
        if (strlen($input['new_password']) < 8) {
            $this->respondValidationError(['new_password' => 'Password must be at least 8 characters']);
            return;
        }

        try {
            $success = $this->userRepo->updatePassword($userId, $input['new_password']);
            
            if ($success) {
                $this->respondSuccess([
                    'message' => 'Password updated successfully',
                    'user_id' => $userId
                ]);
            } else {
                $this->respondError('Failed to update password', 400);
            }

        } catch (Exception $e) {
            $this->respondError('Failed to update password: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Update user status
     * 
     * PUT /api/users/{id}/status
     */
    private function updateUserStatus(int $userId): void
    {
        // Check permissions - only admins can change status
        if (!$this->hasPermissions(['admin'])) {
            $this->respondForbidden('Insufficient permissions to change user status');
            return;
        }

        $user = $this->userRepo->find($userId);
        
        if (!$user) {
            $this->respondNotFound('User');
            return;
        }

        $input = $this->sanitizeInput($this->getInput());
        
        $errors = $this->validateRequired($input, ['status']);
        if (!empty($errors)) {
            $this->respondValidationError($errors);
            return;
        }

        $validStatuses = ['active', 'inactive', 'suspended', 'pending'];
        if (!in_array($input['status'], $validStatuses)) {
            $this->respondValidationError(['status' => 'Invalid status']);
            return;
        }

        try {
            $success = $this->userRepo->update($userId, ['status' => $input['status']]);
            
            if ($success) {
                $this->respondSuccess([
                    'message' => 'User status updated successfully',
                    'user_id' => $userId,
                    'new_status' => $input['status']
                ]);
            } else {
                $this->respondError('Failed to update status', 400);
            }

        } catch (Exception $e) {
            $this->respondError('Failed to update status: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Assign RFID tag to user
     * 
     * POST /api/users/{id}/rfid
     */
    private function assignRfidTag(int $userId): void
    {
        // Check permissions
        if (!$this->hasPermissions(['admin', 'manage_users'])) {
            $this->respondForbidden('Insufficient permissions to assign RFID tags');
            return;
        }

        $user = $this->userRepo->find($userId);
        
        if (!$user) {
            $this->respondNotFound('User');
            return;
        }

        $input = $this->sanitizeInput($this->getInput());
        
        $errors = $this->validateRequired($input, ['rfid_tag']);
        if (!empty($errors)) {
            $this->respondValidationError($errors);
            return;
        }

        $rfidTag = strtoupper(trim($input['rfid_tag']));

        // Validate RFID tag format
        if (!preg_match('/^[A-Fa-f0-9]{8,16}$/', $rfidTag)) {
            $this->respondValidationError(['rfid_tag' => 'Invalid RFID tag format']);
            return;
        }

        // Check if tag is already assigned
        $existingUser = $this->userRepo->findByRfidTag($rfidTag);
        if ($existingUser && $existingUser['user_id'] !== $userId) {
            $this->respondError('RFID tag is already assigned to another user', 409, [
                'assigned_to' => $existingUser['firstname'] . ' ' . $existingUser['lastname']
            ]);
            return;
        }

        try {
            $success = $this->userRepo->updateRfidTag($userId, $rfidTag);
            
            if ($success) {
                $this->respondSuccess([
                    'message' => 'RFID tag assigned successfully',
                    'user_id' => $userId,
                    'rfid_tag' => $rfidTag
                ]);
            } else {
                $this->respondError('Failed to assign RFID tag', 400);
            }

        } catch (Exception $e) {
            $this->respondError('Failed to assign RFID tag: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Remove RFID tag from user
     * 
     * DELETE /api/users/{id}/rfid
     */
    private function removeRfidTag(int $userId): void
    {
        // Check permissions
        if (!$this->hasPermissions(['admin', 'manage_users'])) {
            $this->respondForbidden('Insufficient permissions to remove RFID tags');
            return;
        }

        $user = $this->userRepo->find($userId);
        
        if (!$user) {
            $this->respondNotFound('User');
            return;
        }

        try {
            $success = $this->userRepo->updateRfidTag($userId, null);
            
            if ($success) {
                $this->respondSuccess([
                    'message' => 'RFID tag removed successfully',
                    'user_id' => $userId
                ]);
            } else {
                $this->respondError('Failed to remove RFID tag', 400);
            }

        } catch (Exception $e) {
            $this->respondError('Failed to remove RFID tag: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Helper methods
     */
    private function canViewUser(array $user): bool
    {
        return $this->hasPermissions(['admin', 'manage_users', 'view_reports']) || 
               $user['user_id'] === $this->getCurrentUserId();
    }

    private function canEditUser(array $user): bool
    {
        return $this->hasPermissions(['admin', 'manage_users']) || 
               $user['user_id'] === $this->getCurrentUserId();
    }

    private function canViewUserCheckins(int $userId): bool
    {
        return $this->hasPermissions(['admin', 'view_reports']) || 
               $userId === $this->getCurrentUserId();
    }

    private function canViewUserEvents(int $userId): bool
    {
        return $this->hasPermissions(['admin', 'manage_events', 'view_reports']) || 
               $userId === $this->getCurrentUserId();
    }

    private function getUserStatistics(int $userId): array
    {
        // This would calculate actual statistics
        return [
            'total_checkins' => 0,
            'events_registered' => 0,
            'events_attended' => 0,
            'last_checkin' => null,
            'account_created' => null
        ];
    }
}
