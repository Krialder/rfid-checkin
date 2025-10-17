<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers\Auth;

use RfidCheckin\Controllers\SimpleBaseController;
use RfidCheckin\Services\DatabaseService;
use RfidCheckin\Services\LoggingService;

/**
 * Login Controller
 * 
 * Handles user authentication
 * 
 * @package RfidCheckin\Controllers\Auth
 */
class LoginController extends SimpleBaseController
{
    private DatabaseService $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = DatabaseService::getInstance();
    }

    /**
     * Show login form
     */
    public function showLoginForm(): void
    {
        // If already authenticated, redirect to dashboard
        if ($this->isAuthenticated()) {
            header('Location: /dashboard');
            exit;
        }

        $this->render('auth/login', [
            'title' => 'Login - RFID Check-in System',
            'error' => $_SESSION['login_error'] ?? null
        ]);

        // Clear error message
        unset($_SESSION['login_error']);
    }

    /**
     * Process login
     */
    public function login(): void
    {
        $input = $this->getInput();
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';

        // Validate input
        if (empty($username) || empty($password)) {
            $this->handleLoginError('Please enter both username and password');
            return;
        }

        try {
            // Find user
            $user = $this->db->selectOne(
                "SELECT id, username, first_name, last_name, password_hash, role, is_active, groups 
                 FROM users WHERE username = ? AND is_active = 1",
                [$username]
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

            // Log successful login
            $this->logger->info('User logged in', [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            // Redirect based on request type
            if ($this->isAjaxRequest()) {
                $this->success([
                    'redirect' => '/dashboard',
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'name' => $user['first_name'] . ' ' . $user['last_name'],
                        'role' => $user['role']
                    ]
                ], 'Login successful');
            } else {
                header('Location: /dashboard');
                exit;
            }

        } catch (\Exception $e) {
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
        $userId = $this->getCurrentUserId();

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
            $this->success(['redirect' => '/auth/login'], 'Logged out successfully');
        } else {
            header('Location: /auth/login');
            exit;
        }
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
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
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
            $this->error($message, 401);
        } else {
            $_SESSION['login_error'] = $message;
            header('Location: /auth/login');
            exit;
        }
    }
}