<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers\Auth;

use RfidCheckin\Controllers\BaseFrontendController;
use RfidCheckin\Services\AuthenticationService;
use RfidCheckin\Services\SecurityService;
use RfidCheckin\Repositories\UserRepository;

/**
 * Login Controller
 * 
 * Handles user authentication including login, logout, and related security operations.
 * Implements secure authentication with rate limiting and security logging.
 * 
 * @package RfidCheckin\Controllers\Auth
 */
class LoginController extends BaseFrontendController
{
    private SecurityService $security;
    private UserRepository $userRepo;

    public function __construct()
    {
        parent::__construct();
        $this->security = SecurityService::getInstance();
        $this->userRepo = new UserRepository();
    }

    /**
     * Show login form
     */
    public function showLoginForm(): void
    {
        // Redirect if already authenticated
        if ($this->auth->isAuthenticated()) {
            $this->redirect('/dashboard');
            return;
        }

        // Check for rate limiting
        $clientIp = $this->getClientIp();
        if ($this->security->isRateLimited($clientIp, 'login_attempts')) {
            $this->render('auth/login', [
                'error' => 'Too many login attempts. Please try again later.',
                'rate_limited' => true
            ], ['title' => 'Login - Rate Limited']);
            return;
        }

        $data = [
            'csrf_token' => $this->security->generateCsrfToken(),
            'redirect_after' => $_GET['redirect'] ?? '/dashboard',
            'login_attempts' => $this->security->getFailedAttempts($clientIp)
        ];

        $this->render('auth/login', $data, [
            'title' => 'Login',
            'page_class' => 'login-page',
            'no_header' => true,
            'no_footer' => true
        ]);
    }

    /**
     * Process login attempt
     */
    public function login(): void
    {
        $this->requireMethod('POST');
        
        $clientIp = $this->getClientIp();
        
        // Check rate limiting
        if ($this->security->isRateLimited($clientIp, 'login_attempts')) {
            $this->logger->security('login_rate_limited', 'Login attempt blocked due to rate limiting', [
                'ip_address' => $clientIp
            ]);
            
            $this->renderJson(['error' => 'Too many login attempts'], 429);
            return;
        }

        // Validate CSRF token
        if (!$this->security->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->logger->security('csrf_validation_failed', 'CSRF token validation failed on login', [
                'ip_address' => $clientIp
            ]);
            
            $this->renderJson(['error' => 'Invalid request'], 400);
            return;
        }

        $input = $this->sanitizeInput($_POST);
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        $rememberMe = !empty($input['remember_me']);

        // Validate input
        if (empty($email) || empty($password)) {
            $this->security->recordFailedAttempt($clientIp);
            $this->renderJson(['error' => 'Email and password are required'], 400);
            return;
        }

        try {
            // Attempt authentication
            $result = $this->auth->authenticate($email, $password, $rememberMe);

            if ($result['success']) {
                $user = $result['user'];
                
                // Clear failed attempts
                $this->security->clearFailedAttempts($clientIp);
                
                // Log successful login
                $this->logger->security('login_success', 'User logged in successfully', [
                    'user_id' => $user['user_id'],
                    'email' => $user['email'],
                    'ip_address' => $clientIp,
                    'remember_me' => $rememberMe
                ]);

                // Update last login
                $this->userRepo->updateLastLogin($user['user_id'], $clientIp);

                $redirectUrl = $input['redirect_after'] ?? '/dashboard';
                
                if ($this->isAjaxRequest()) {
                    $this->renderJson([
                        'success' => true,
                        'message' => 'Login successful',
                        'redirect' => $redirectUrl,
                        'user' => [
                            'id' => $user['user_id'],
                            'name' => $user['full_name'],
                            'role' => $user['role']
                        ]
                    ]);
                } else {
                    $this->redirect($redirectUrl);
                }
                
            } else {
                // Record failed attempt
                $this->security->recordFailedAttempt($clientIp, $email);
                
                $this->logger->security('login_failed', 'Login attempt failed', [
                    'email' => $email,
                    'ip_address' => $clientIp,
                    'reason' => $result['message']
                ]);

                if ($this->isAjaxRequest()) {
                    $this->renderJson(['error' => $result['message']], 401);
                } else {
                    $this->redirectWithError('/auth/login', $result['message']);
                }
            }

        } catch (\Exception $e) {
            $this->logger->error('Login system error', [
                'email' => $email,
                'ip_address' => $clientIp,
                'error' => $e->getMessage()
            ]);

            $this->security->recordFailedAttempt($clientIp);

            if ($this->isAjaxRequest()) {
                $this->renderJson(['error' => 'System error occurred'], 500);
            } else {
                $this->redirectWithError('/auth/login', 'System error occurred');
            }
        }
    }

    /**
     * Logout user
     */
    public function logout(): void
    {
        $this->requireMethod('POST');

        if ($this->auth->isAuthenticated()) {
            $user = $this->auth->getCurrentUser();
            
            $this->logger->security('logout', 'User logged out', [
                'user_id' => $user['user_id'],
                'email' => $user['email'],
                'ip_address' => $this->getClientIp()
            ]);

            $this->auth->logout();
        }

        if ($this->isAjaxRequest()) {
            $this->renderJson(['success' => true, 'redirect' => '/auth/login']);
        } else {
            $this->redirect('/auth/login');
        }
    }

    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
    }
}