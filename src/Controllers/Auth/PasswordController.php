<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers\Auth;

use RfidCheckin\Controllers\BaseFrontendController;
use RfidCheckin\Services\SecurityService;
use RfidCheckin\Repositories\UserRepository;

/**
 * Password Controller
 * 
 * Handles password reset functionality including token generation,
 * email sending, and password updates.
 * 
 * @package RfidCheckin\Controllers\Auth
 */
class PasswordController extends BaseFrontendController
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
     * Show forgot password form
     */
    public function showForgotForm(): void
    {
        // Redirect if already authenticated
        if ($this->auth->isAuthenticated()) {
            $this->redirect('/dashboard');
            return;
        }

        $data = [
            'csrf_token' => $this->security->generateCsrfToken()
        ];

        $this->render('auth/forgot-password', $data, [
            'title' => 'Forgot Password',
            'page_class' => 'forgot-password-page',
            'no_header' => true,
            'no_footer' => true
        ]);
    }

    /**
     * Send password reset link
     */
    public function sendResetLink(): void
    {
        $this->requireMethod('POST');

        // Validate CSRF token
        if (!$this->security->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->renderJson(['error' => 'Invalid request'], 400);
            return;
        }

        $input = $this->sanitizeInput($_POST);
        $email = $input['email'] ?? '';

        // Validate email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->renderJson(['error' => 'Valid email address is required'], 400);
            return;
        }

        try {
            // Check if user exists
            $user = $this->userRepo->findByEmail($email);
            
            if ($user) {
                // Generate reset token
                $token = $this->security->generatePasswordResetToken($user['user_id']);
                
                // Store token (in a real implementation, this would be saved to database)
                $this->storeResetToken($user['user_id'], $token);
                
                // Log password reset request
                $this->logger->security('password_reset_requested', 'Password reset requested', [
                    'user_id' => $user['user_id'],
                    'email' => $email,
                    'ip_address' => $this->getClientIp()
                ]);

                // In a real implementation, send email here
                $this->sendPasswordResetEmail($user, $token);
            } else {
                // Log attempted reset for non-existent user
                $this->logger->security('password_reset_invalid_email', 'Password reset attempted for non-existent email', [
                    'email' => $email,
                    'ip_address' => $this->getClientIp()
                ]);
            }

            // Always return success message (security best practice)
            $message = 'If an account with that email exists, a password reset link has been sent.';
            
            if ($this->isAjaxRequest()) {
                $this->renderJson(['success' => true, 'message' => $message]);
            } else {
                $this->redirectWithSuccess('/auth/login', $message);
            }

        } catch (\Exception $e) {
            $this->logger->error('Password reset system error', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            if ($this->isAjaxRequest()) {
                $this->renderJson(['error' => 'System error occurred'], 500);
            } else {
                $this->redirectWithError('/auth/forgot-password', 'System error occurred');
            }
        }
    }

    /**
     * Show password reset form
     */
    public function showResetForm(): void
    {
        // Redirect if already authenticated
        if ($this->auth->isAuthenticated()) {
            $this->redirect('/dashboard');
            return;
        }

        $token = $_GET['token'] ?? '';
        
        if (empty($token)) {
            $this->redirectWithError('/auth/login', 'Invalid reset link');
            return;
        }

        // Validate token
        if (!$this->isValidResetToken($token)) {
            $this->redirectWithError('/auth/login', 'Invalid or expired reset link');
            return;
        }

        $data = [
            'csrf_token' => $this->security->generateCsrfToken(),
            'reset_token' => $token
        ];

        $this->render('auth/reset-password', $data, [
            'title' => 'Reset Password',
            'page_class' => 'reset-password-page',
            'no_header' => true,
            'no_footer' => true
        ]);
    }

    /**
     * Process password reset
     */
    public function resetPassword(): void
    {
        $this->requireMethod('POST');

        // Validate CSRF token
        if (!$this->security->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->renderJson(['error' => 'Invalid request'], 400);
            return;
        }

        $input = $this->sanitizeInput($_POST);
        $token = $input['token'] ?? '';
        $password = $input['password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';

        // Validate input
        if (empty($token) || empty($password) || empty($confirmPassword)) {
            $this->renderJson(['error' => 'All fields are required'], 400);
            return;
        }

        if ($password !== $confirmPassword) {
            $this->renderJson(['error' => 'Passwords do not match'], 400);
            return;
        }

        // Validate password strength
        $passwordValidation = $this->security->validatePasswordStrength($password);
        if (!$passwordValidation['valid']) {
            $this->renderJson(['error' => $passwordValidation['message']], 400);
            return;
        }

        // Validate token
        if (!$this->isValidResetToken($token)) {
            $this->renderJson(['error' => 'Invalid or expired reset link'], 400);
            return;
        }

        try {
            $userId = $this->getUserIdFromToken($token);
            
            if (!$userId) {
                $this->renderJson(['error' => 'Invalid reset token'], 400);
                return;
            }

            // Update password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $this->userRepo->updatePassword($userId, $hashedPassword);
            
            // Invalidate reset token
            $this->invalidateResetToken($token);
            
            // Log password reset completion
            $this->logger->security('password_reset_completed', 'Password successfully reset', [
                'user_id' => $userId,
                'ip_address' => $this->getClientIp()
            ]);

            $message = 'Password has been reset successfully. You can now log in.';
            
            if ($this->isAjaxRequest()) {
                $this->renderJson(['success' => true, 'message' => $message, 'redirect' => '/auth/login']);
            } else {
                $this->redirectWithSuccess('/auth/login', $message);
            }

        } catch (\Exception $e) {
            $this->logger->error('Password reset completion error', [
                'token' => substr($token, 0, 10) . '...',
                'error' => $e->getMessage()
            ]);

            if ($this->isAjaxRequest()) {
                $this->renderJson(['error' => 'System error occurred'], 500);
            } else {
                $this->redirectWithError('/auth/reset-password?token=' . urlencode($token), 'System error occurred');
            }
        }
    }

    /**
     * Store reset token (in real implementation, this would be in database)
     */
    private function storeResetToken(int $userId, string $token): void
    {
        // In a real implementation, store in database with expiration time
        // For now, we'll use session storage (not secure for production)
        if (!isset($_SESSION['password_reset_tokens'])) {
            $_SESSION['password_reset_tokens'] = [];
        }
        
        $_SESSION['password_reset_tokens'][$token] = [
            'user_id' => $userId,
            'expires' => time() + 3600 // 1 hour
        ];
    }

    /**
     * Check if reset token is valid
     */
    private function isValidResetToken(string $token): bool
    {
        if (!isset($_SESSION['password_reset_tokens'][$token])) {
            return false;
        }

        $tokenData = $_SESSION['password_reset_tokens'][$token];
        
        if ($tokenData['expires'] < time()) {
            unset($_SESSION['password_reset_tokens'][$token]);
            return false;
        }

        return true;
    }

    /**
     * Get user ID from reset token
     */
    private function getUserIdFromToken(string $token): ?int
    {
        if (!isset($_SESSION['password_reset_tokens'][$token])) {
            return null;
        }

        return $_SESSION['password_reset_tokens'][$token]['user_id'];
    }

    /**
     * Invalidate reset token
     */
    private function invalidateResetToken(string $token): void
    {
        unset($_SESSION['password_reset_tokens'][$token]);
    }

    /**
     * Send password reset email (placeholder implementation)
     */
    private function sendPasswordResetEmail(array $user, string $token): void
    {
        // In a real implementation, this would send an email
        // For now, we'll just log it
        $resetUrl = $this->config->get('app.base_url') . '/auth/reset-password?token=' . urlencode($token);
        
        $this->logger->info('Password reset email would be sent', [
            'user_id' => $user['user_id'],
            'email' => $user['email'],
            'reset_url' => $resetUrl
        ]);
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