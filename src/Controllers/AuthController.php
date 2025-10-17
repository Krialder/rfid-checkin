<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers;

use RfidCheckin\Services\DatabaseService;
use RfidCheckin\Services\LoggingService;
use Exception;

/**
 * Authentication Controller
 * 
 * Handles all authentication-related functionality:
 * - Login/logout
 * - Password reset
 * - Email verification
 * - Session management
 * 
 * @package RfidCheckin\Controllers
 */
class AuthController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Show login form
     */
    public function showLoginForm(): void
    {
        // If already authenticated, redirect to dashboard
        if ($this->getCurrentUser()) {
            header('Location: /dashboard');
            exit;
        }

        echo $this->render('auth/login', [
            'title' => 'Login - RFID Check-in System',
            'error' => $_SESSION['login_error'] ?? null
        ]);

        // Clear error message
        unset($_SESSION['login_error']);
    }

    /**
     * Process login authentication
     */
    public function login(): void
    {
        $input = $this->getInput();
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        // Validate input
        if (empty($username) || empty($password)) {
            $this->handleLoginError('Please enter both username and password');
            return;
        }

        try {
            // Find user by username or email
            $user = $this->db->selectOne(
                "SELECT id, username, email, first_name, last_name, password_hash, role, is_active, groups 
                 FROM users WHERE (username = ? OR email = ?) AND is_active = 1",
                [$username, $username]
            );

            if (!$user) {
                $this->handleLoginError('Invalid username or password');
                return;
            }

            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                $this->handleLoginError('Invalid username or password');
                return;
            }

            // Create session
            $this->createUserSession($user);

            // Update last login
            $this->db->update('users', 
                ['last_login' => date('Y-m-d H:i:s')], 
                ['id' => $user['id']]
            );

            // Log successful login
            $this->logger->info('User logged in', [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            // Redirect based on request type
            if ($this->isAjaxRequest()) {
                $this->jsonSuccess([
                    'redirect' => '/dashboard',
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'name' => trim($user['first_name'] . ' ' . $user['last_name']),
                        'role' => $user['role']
                    ]
                ], 'Login successful');
            } else {
                header('Location: /dashboard');
                exit;
            }

        } catch (Exception $e) {
            $this->logger->error('Login error', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            
            $this->handleLoginError('An error occurred during login. Please try again.');
        }
    }

    /**
     * Process logout
     */
    public function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;

        // Log logout
        if ($userId) {
            $this->logger->info('User logged out', [
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }

        // Destroy session
        session_destroy();
        session_start(); // Start a new clean session

        if ($this->isAjaxRequest()) {
            $this->jsonSuccess(['redirect' => '/login'], 'Logged out successfully');
        } else {
            header('Location: /login');
            exit;
        }
    }

    /**
     * Show forgot password form
     */
    public function showForgotForm(): void
    {
        if ($this->getCurrentUser()) {
            header('Location: /dashboard');
            exit;
        }

        echo $this->render('auth/forgot-password', [
            'title' => 'Reset Password - RFID Check-in System',
            'error' => $_SESSION['forgot_error'] ?? null,
            'success' => $_SESSION['forgot_success'] ?? null
        ]);

        // Clear messages
        unset($_SESSION['forgot_error'], $_SESSION['forgot_success']);
    }

    /**
     * Send password reset link
     */
    public function forgotPassword(): void
    {
        $input = $this->getInput();
        $email = trim($input['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->handleForgotError('Please enter a valid email address');
            return;
        }

        try {
            // Find user by email
            $user = $this->db->selectOne(
                "SELECT id, email, first_name, last_name FROM users WHERE email = ? AND is_active = 1",
                [$email]
            );

            if (!$user) {
                // Don't reveal if email exists - always show success message
                $this->handleForgotSuccess('If the email address exists in our system, you will receive a password reset link shortly.');
                return;
            }

            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store reset token
            $this->db->insertOrUpdate('password_resets', [
                'email' => $email,
                'token' => hash('sha256', $token),
                'expires_at' => $expires,
                'created_at' => date('Y-m-d H:i:s')
            ], ['email']);

            // TODO: Send email with reset link
            // For now, just log the token (in production, implement email sending)
            $this->logger->info('Password reset requested', [
                'user_id' => $user['id'],
                'email' => $email,
                'token' => $token // Remove this in production
            ]);

            $this->handleForgotSuccess('If the email address exists in our system, you will receive a password reset link shortly.');

        } catch (Exception $e) {
            $this->logger->error('Forgot password error', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            
            $this->handleForgotError('An error occurred. Please try again.');
        }
    }

    /**
     * Show password reset form
     */
    public function showResetForm(): void
    {
        $token = $_GET['token'] ?? '';

        if (empty($token)) {
            header('Location: /forgot-password');
            exit;
        }

        // Verify token exists and is not expired
        $reset = $this->db->selectOne(
            "SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()",
            [hash('sha256', $token)]
        );

        if (!$reset) {
            $_SESSION['forgot_error'] = 'Invalid or expired reset token';
            header('Location: /forgot-password');
            exit;
        }

        echo $this->render('auth/reset-password', [
            'title' => 'Reset Password - RFID Check-in System',
            'token' => $token,
            'error' => $_SESSION['reset_error'] ?? null
        ]);

        unset($_SESSION['reset_error']);
    }

    /**
     * Process password reset
     */
    public function resetPassword(): void
    {
        $input = $this->getInput();
        $token = $input['token'] ?? '';
        $password = $input['password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';

        // Validate input
        if (empty($token) || empty($password) || empty($confirmPassword)) {
            $this->handleResetError('All fields are required');
            return;
        }

        if ($password !== $confirmPassword) {
            $this->handleResetError('Passwords do not match');
            return;
        }

        if (strlen($password) < 8) {
            $this->handleResetError('Password must be at least 8 characters long');
            return;
        }

        try {
            // Verify token
            $reset = $this->db->selectOne(
                "SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()",
                [hash('sha256', $token)]
            );

            if (!$reset) {
                $this->handleResetError('Invalid or expired reset token');
                return;
            }

            // Update user password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $this->db->update('users', 
                ['password_hash' => $hashedPassword], 
                ['email' => $reset['email']]
            );

            // Delete used reset token
            $this->db->delete('password_resets', ['email' => $reset['email']]);

            $this->logger->info('Password reset completed', ['email' => $reset['email']]);

            $_SESSION['login_success'] = 'Password reset successfully. Please log in with your new password.';
            header('Location: /login');
            exit;

        } catch (Exception $e) {
            $this->logger->error('Reset password error', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);
            
            $this->handleResetError('An error occurred. Please try again.');
        }
    }

    /**
     * Verify email address (placeholder for future implementation)
     */
    public function verifyEmail(): void
    {
        $token = $_GET['token'] ?? '';

        if (empty($token)) {
            $this->renderError('Invalid verification token');
            return;
        }

        // TODO: Implement email verification logic
        // For now, just redirect to login
        $_SESSION['login_success'] = 'Email verified successfully. Please log in.';
        header('Location: /login');
        exit;
    }

    /**
     * Create user session
     */
    private function createUserSession(array $user): void
    {
        // Regenerate session ID for security
        session_regenerate_id(true);

        // Set session data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_groups'] = $user['groups'] ? explode(',', $user['groups']) : [];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
    }

    /**
     * Handle login error
     */
    private function handleLoginError(string $message): void
    {
        // Log failed login attempt
        $this->logger->warning('Failed login attempt', [
            'username' => $_POST['username'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);

        if ($this->isAjaxRequest()) {
            $this->jsonError($message, 401);
        } else {
            $_SESSION['login_error'] = $message;
            header('Location: /login');
            exit;
        }
    }

    /**
     * Handle forgot password error
     */
    private function handleForgotError(string $message): void
    {
        if ($this->isAjaxRequest()) {
            $this->jsonError($message, 400);
        } else {
            $_SESSION['forgot_error'] = $message;
            header('Location: /forgot-password');
            exit;
        }
    }

    /**
     * Handle forgot password success
     */
    private function handleForgotSuccess(string $message): void
    {
        if ($this->isAjaxRequest()) {
            $this->jsonSuccess([], $message);
        } else {
            $_SESSION['forgot_success'] = $message;
            header('Location: /forgot-password');
            exit;
        }
    }

    /**
     * Handle reset password error
     */
    private function handleResetError(string $message): void
    {
        $token = $_POST['token'] ?? $_GET['token'] ?? '';
        
        if ($this->isAjaxRequest()) {
            $this->jsonError($message, 400);
        } else {
            $_SESSION['reset_error'] = $message;
            header("Location: /reset-password?token=" . urlencode($token));
            exit;
        }
    }
}