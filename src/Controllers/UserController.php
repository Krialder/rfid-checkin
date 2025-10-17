<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers;

use RfidCheckin\Services\DatabaseService;
use RfidCheckin\Services\LoggingService;
use Exception;

/**
 * User Controller
 * 
 * Handles user management functionality:
 * - User profile management
 * - Password changes
 * - User notifications
 * - User settings
 * 
 * @package RfidCheckin\Controllers
 */
class UserController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * List all users (admin only)
     */
    public function index(): void
    {
        $user = $this->requireAuth();
        if (!$user) return;

        // Only admins can view all users
        if (!$this->requireRole('admin')) {
            return;
        }

        $pagination = $this->getPaginationParams();
        $filters = $this->getUserFilters();

        try {
            // Build WHERE clause
            $whereConditions = ['1=1'];
            $params = [];

            if (!empty($filters['role'])) {
                $whereConditions[] = 'role = ?';
                $params[] = $filters['role'];
            }

            if (!empty($filters['status'])) {
                $whereConditions[] = 'is_active = ?';
                $params[] = $filters['status'] === 'active' ? 1 : 0;
            }

            if (!empty($filters['search'])) {
                $whereConditions[] = '(first_name LIKE ? OR last_name LIKE ? OR username LIKE ? OR email LIKE ?)';
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $whereClause = implode(' AND ', $whereConditions);

            // Get total count
            $totalCount = $this->db->selectValue(
                "SELECT COUNT(*) FROM users WHERE {$whereClause}",
                $params
            );

            // Get users
            $users = $this->db->selectAll(
                "SELECT id, username, email, first_name, last_name, role, is_active, 
                        last_login, created_at
                 FROM users 
                 WHERE {$whereClause}
                 ORDER BY created_at DESC
                 LIMIT ? OFFSET ?",
                array_merge($params, [$pagination['limit'], $pagination['offset']])
            );

            $paginationData = $this->buildPagination($totalCount, $pagination['page'], $pagination['limit']);

            if ($this->isAjaxRequest()) {
                $this->jsonSuccess([
                    'users' => $users,
                    'pagination' => $paginationData,
                    'total' => $totalCount
                ]);
            } else {
                echo $this->render('users/index', [
                    'title' => 'User Management',
                    'users' => $users,
                    'pagination' => $paginationData,
                    'filters' => $filters
                ]);
            }

        } catch (Exception $e) {
            $this->logger->error('User listing error', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);

            if ($this->isAjaxRequest()) {
                $this->jsonError('Error loading users', 500);
            } else {
                $this->renderError('Unable to load users');
            }
        }
    }

    /**
     * Show/edit user profile
     */
    public function profile(): void
    {
        $user = $this->requireAuth();
        if (!$user) return;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->updateProfile();
            return;
        }

        try {
            // Get full user data
            $userData = $this->db->selectOne(
                "SELECT id, username, email, first_name, last_name, phone, role, 
                        last_login, created_at
                 FROM users WHERE id = ?",
                [$user['id']]
            );

            if (!$userData) {
                $this->renderError('User not found');
                return;
            }

            // Get user's RFID cards
            $rfidCards = $this->db->selectAll(
                "SELECT id, rfid_tag, name, is_active, created_at
                 FROM user_rfid_cards 
                 WHERE user_id = ? 
                 ORDER BY created_at DESC",
                [$user['id']]
            );

            // Get user's groups
            $userGroups = $this->db->selectAll(
                "SELECT g.id, g.name, g.description
                 FROM groups g
                 INNER JOIN user_groups ug ON g.id = ug.group_id
                 WHERE ug.user_id = ? AND g.is_active = 1
                 ORDER BY g.name",
                [$user['id']]
            );

            echo $this->render('users/profile', [
                'title' => 'My Profile',
                'user' => $userData,
                'rfid_cards' => $rfidCards,
                'groups' => $userGroups,
                'error' => $_SESSION['profile_error'] ?? null,
                'success' => $_SESSION['profile_success'] ?? null
            ]);

            unset($_SESSION['profile_error'], $_SESSION['profile_success']);

        } catch (Exception $e) {
            $this->logger->error('Profile view error', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);

            $this->renderError('Unable to load profile');
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(): void
    {
        $user = $this->requireAuth();
        if (!$user) return;

        $input = $this->getInput();
        $validation = $this->validateProfileData($input, $user['id']);

        if (!empty($validation['errors'])) {
            if ($this->isAjaxRequest()) {
                $this->jsonError('Validation failed', 422, $validation['errors']);
            } else {
                $_SESSION['profile_error'] = implode('<br>', $validation['errors']);
                header('Location: /user/profile');
                exit;
            }
            return;
        }

        try {
            $profileData = $validation['data'];
            $profileData['updated_at'] = date('Y-m-d H:i:s');

            $this->db->update('users', $profileData, ['id' => $user['id']]);

            // Update session data
            $_SESSION['user_name'] = trim($profileData['first_name'] . ' ' . $profileData['last_name']);

            $this->logger->info('Profile updated', [
                'user_id' => $user['id'],
                'updated_fields' => array_keys($profileData)
            ]);

            if ($this->isAjaxRequest()) {
                $this->jsonSuccess([], 'Profile updated successfully');
            } else {
                $_SESSION['profile_success'] = 'Profile updated successfully';
                header('Location: /user/profile');
                exit;
            }

        } catch (Exception $e) {
            $this->logger->error('Profile update error', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);

            if ($this->isAjaxRequest()) {
                $this->jsonError('Error updating profile', 500);
            } else {
                $_SESSION['profile_error'] = 'Error updating profile. Please try again.';
                header('Location: /user/profile');
                exit;
            }
        }
    }

    /**
     * Change password
     */
    public function changePassword(): void
    {
        $user = $this->requireAuth();
        if (!$user) return;

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            echo $this->render('users/change-password', [
                'title' => 'Change Password',
                'error' => $_SESSION['password_error'] ?? null,
                'success' => $_SESSION['password_success'] ?? null
            ]);

            unset($_SESSION['password_error'], $_SESSION['password_success']);
            return;
        }

        $input = $this->getInput();
        $currentPassword = $input['current_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';

        // Validate input
        $errors = [];

        if (empty($currentPassword)) {
            $errors[] = 'Current password is required';
        }

        if (empty($newPassword)) {
            $errors[] = 'New password is required';
        } elseif (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters long';
        }

        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New passwords do not match';
        }

        if (!empty($errors)) {
            if ($this->isAjaxRequest()) {
                $this->jsonError('Validation failed', 422, $errors);
            } else {
                $_SESSION['password_error'] = implode('<br>', $errors);
                header('Location: /user/change-password');
                exit;
            }
            return;
        }

        try {
            // Get user's current password hash
            $userData = $this->db->selectOne(
                "SELECT password_hash FROM users WHERE id = ?",
                [$user['id']]
            );

            if (!$userData || !password_verify($currentPassword, $userData['password_hash'])) {
                if ($this->isAjaxRequest()) {
                    $this->jsonError('Current password is incorrect', 400);
                } else {
                    $_SESSION['password_error'] = 'Current password is incorrect';
                    header('Location: /user/change-password');
                    exit;
                }
                return;
            }

            // Update password
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $this->db->update('users', 
                ['password_hash' => $newPasswordHash, 'updated_at' => date('Y-m-d H:i:s')], 
                ['id' => $user['id']]
            );

            $this->logger->info('Password changed', ['user_id' => $user['id']]);

            if ($this->isAjaxRequest()) {
                $this->jsonSuccess([], 'Password changed successfully');
            } else {
                $_SESSION['password_success'] = 'Password changed successfully';
                header('Location: /user/change-password');
                exit;
            }

        } catch (Exception $e) {
            $this->logger->error('Password change error', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);

            if ($this->isAjaxRequest()) {
                $this->jsonError('Error changing password', 500);
            } else {
                $_SESSION['password_error'] = 'Error changing password. Please try again.';
                header('Location: /user/change-password');
                exit;
            }
        }
    }

    /**
     * Get user notifications
     */
    public function getNotifications(): void
    {
        $user = $this->requireAuth();
        if (!$user) return;

        $pagination = $this->getPaginationParams();
        $unreadOnly = $_GET['unread_only'] === '1';

        try {
            $whereConditions = ['user_id = ?'];
            $params = [$user['id']];

            if ($unreadOnly) {
                $whereConditions[] = 'is_read = 0';
            }

            $whereClause = implode(' AND ', $whereConditions);

            // Get total count
            $totalCount = $this->db->selectValue(
                "SELECT COUNT(*) FROM notifications WHERE {$whereClause}",
                $params
            );

            // Get notifications
            $notifications = $this->db->selectAll(
                "SELECT id, title, message, type, is_read, created_at
                 FROM notifications 
                 WHERE {$whereClause}
                 ORDER BY created_at DESC
                 LIMIT ? OFFSET ?",
                array_merge($params, [$pagination['limit'], $pagination['offset']])
            );

            // Get unread count
            $unreadCount = $this->db->selectValue(
                "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0",
                [$user['id']]
            );

            $paginationData = $this->buildPagination($totalCount, $pagination['page'], $pagination['limit']);

            if ($this->isAjaxRequest()) {
                $this->jsonSuccess([
                    'notifications' => $notifications,
                    'pagination' => $paginationData,
                    'total' => $totalCount,
                    'unread_count' => $unreadCount
                ]);
            } else {
                echo $this->render('users/notifications', [
                    'title' => 'Notifications',
                    'notifications' => $notifications,
                    'pagination' => $paginationData,
                    'unread_count' => $unreadCount,
                    'unread_only' => $unreadOnly
                ]);
            }

        } catch (Exception $e) {
            $this->logger->error('Notifications error', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);

            if ($this->isAjaxRequest()) {
                $this->jsonError('Error loading notifications', 500);
            } else {
                $this->renderError('Unable to load notifications');
            }
        }
    }

    /**
     * Mark notification as read
     */
    public function markNotificationRead(): void
    {
        $user = $this->requireAuth();
        if (!$user) return;

        $input = $this->getInput();
        $notificationId = (int)($input['notification_id'] ?? 0);

        if (!$notificationId) {
            $this->jsonError('Notification ID is required', 400);
            return;
        }

        try {
            // Verify notification belongs to user
            $notification = $this->db->selectOne(
                "SELECT id FROM notifications WHERE id = ? AND user_id = ?",
                [$notificationId, $user['id']]
            );

            if (!$notification) {
                $this->jsonError('Notification not found', 404);
                return;
            }

            // Mark as read
            $this->db->update('notifications', 
                ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')], 
                ['id' => $notificationId]
            );

            $this->jsonSuccess([], 'Notification marked as read');

        } catch (Exception $e) {
            $this->logger->error('Mark notification read error', [
                'user_id' => $user['id'],
                'notification_id' => $notificationId,
                'error' => $e->getMessage()
            ]);

            $this->jsonError('Error updating notification', 500);
        }
    }

    /**
     * Validate profile data
     */
    private function validateProfileData(array $input, int $userId): array
    {
        $errors = [];
        $data = [];

        // First name
        $firstName = trim($input['first_name'] ?? '');
        if (empty($firstName)) {
            $errors['first_name'] = 'First name is required';
        } elseif (strlen($firstName) > 50) {
            $errors['first_name'] = 'First name is too long';
        } else {
            $data['first_name'] = $firstName;
        }

        // Last name
        $lastName = trim($input['last_name'] ?? '');
        if (empty($lastName)) {
            $errors['last_name'] = 'Last name is required';
        } elseif (strlen($lastName) > 50) {
            $errors['last_name'] = 'Last name is too long';
        } else {
            $data['last_name'] = $lastName;
        }

        // Email
        $email = trim($input['email'] ?? '');
        if (empty($email)) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        } else {
            // Check if email is already taken by another user
            $existingUser = $this->db->selectOne(
                "SELECT id FROM users WHERE email = ? AND id != ?",
                [$email, $userId]
            );

            if ($existingUser) {
                $errors['email'] = 'Email is already taken';
            } else {
                $data['email'] = $email;
            }
        }

        // Phone (optional)
        $phone = trim($input['phone'] ?? '');
        if (!empty($phone)) {
            if (strlen($phone) > 20) {
                $errors['phone'] = 'Phone number is too long';
            } else {
                $data['phone'] = $phone;
            }
        }

        return ['errors' => $errors, 'data' => $data];
    }

    /**
     * Get user filters from request
     */
    private function getUserFilters(): array
    {
        return [
            'role' => $_GET['role'] ?? '',
            'status' => $_GET['status'] ?? '',
            'search' => trim($_GET['search'] ?? '')
        ];
    }
}