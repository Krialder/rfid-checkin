<?php

declare(strict_types=1);

namespace RfidCheckin\Middleware;

use RfidCheckin\Services\AuthenticationService;
use RfidCheckin\Services\LoggingService;

/**
 * Authentication Middleware
 * 
 * Ensures that only authenticated users can access protected routes.
 * Redirects unauthenticated users to login page and logs access attempts.
 * 
 * Features:
 * - Session validation
 * - User authentication checking
 * - Automatic redirects for unauthenticated access
 * - Security logging and monitoring
 * - Remember me functionality
 * - Session timeout handling
 * 
 * @package RfidCheckin\Middleware
 * @version 1.0.0
 * @author Senior Development Team
 */
class AuthenticationMiddleware implements MiddlewareInterface
{
    private AuthenticationService $auth;
    private LoggingService $logger;
    private array $publicRoutes;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->auth = AuthenticationService::getInstance();
        $this->logger = LoggingService::getInstance();
        
        // Define routes that don't require authentication
        $this->publicRoutes = [
            '/auth/login',
            '/auth/forgot-password',
            '/auth/reset-password',
            '/auth/register',
            '/api/rfid-poll-noauth',
            '/assets/',
            '/css/',
            '/js/',
            '/images/',
            '/favicon.ico'
        ];
    }

    /**
     * Handle authentication check
     */
    public function handle(callable $next)
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $requestPath = parse_url($requestUri, PHP_URL_PATH);

        // Check if route is public
        if ($this->isPublicRoute($requestPath)) {
            return $next();
        }

        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check authentication
        if (!$this->auth->isAuthenticated()) {
            $this->handleUnauthenticatedAccess($requestUri);
            return;
        }

        // Validate session security
        if (!$this->validateSessionSecurity()) {
            $this->handleInvalidSession();
            return;
        }

        // Check session timeout
        if ($this->isSessionExpired()) {
            $this->handleSessionTimeout();
            return;
        }

        // Update last activity
        $this->updateLastActivity();

        // Log successful authentication
        $this->logAuthenticationSuccess();

        return $next();
    }

    /**
     * Check if route is public (doesn't require authentication)
     */
    private function isPublicRoute(string $path): bool
    {
        foreach ($this->publicRoutes as $publicRoute) {
            if (strpos($path, $publicRoute) === 0) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Handle unauthenticated access
     */
    private function handleUnauthenticatedAccess(string $requestUri): void
    {
        $this->logger->warning('Unauthenticated access attempt', [
            'requested_uri' => $requestUri,
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? ''
        ]);

        // Store return URL for after login
        $_SESSION['return_url'] = $requestUri;

        // Check if it's an AJAX request
        if ($this->isAjaxRequest()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Authentication required',
                'redirect' => '/auth/login'
            ]);
            exit;
        }

        // Redirect to login page
        header('Location: /auth/login?return=' . urlencode($requestUri));
        exit;
    }

    /**
     * Validate session security
     */
    private function validateSessionSecurity(): bool
    {
        // Check session fingerprint
        $currentFingerprint = $this->generateSessionFingerprint();
        $sessionFingerprint = $_SESSION['security_fingerprint'] ?? null;

        if ($sessionFingerprint && $sessionFingerprint !== $currentFingerprint) {
            $this->logger->warning('Session fingerprint mismatch', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'expected' => $sessionFingerprint,
                'actual' => $currentFingerprint,
                'ip_address' => $this->getClientIp()
            ]);
            return false;
        }

        // Update fingerprint if not set
        if (!$sessionFingerprint) {
            $_SESSION['security_fingerprint'] = $currentFingerprint;
        }

        return true;
    }

    /**
     * Check if session has expired
     */
    private function isSessionExpired(): bool
    {
        $lastActivity = $_SESSION['last_activity'] ?? 0;
        $timeout = 7200; // 2 hours default

        return (time() - $lastActivity) > $timeout;
    }

    /**
     * Handle invalid session
     */
    private function handleInvalidSession(): void
    {
        $this->logger->warning('Invalid session detected', [
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip_address' => $this->getClientIp(),
            'session_id' => session_id()
        ]);

        $this->destroySession();
        $this->redirectToLogin('Invalid session detected. Please log in again.');
    }

    /**
     * Handle session timeout
     */
    private function handleSessionTimeout(): void
    {
        $this->logger->info('Session timeout', [
            'user_id' => $_SESSION['user_id'] ?? null,
            'last_activity' => $_SESSION['last_activity'] ?? 0,
            'ip_address' => $this->getClientIp()
        ]);

        $this->destroySession();
        $this->redirectToLogin('Your session has expired. Please log in again.');
    }

    /**
     * Update last activity timestamp
     */
    private function updateLastActivity(): void
    {
        $_SESSION['last_activity'] = time();
    }

    /**
     * Log successful authentication
     */
    private function logAuthenticationSuccess(): void
    {
        $this->logger->debug('Authenticated request', [
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip_address' => $this->getClientIp(),
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? ''
        ]);
    }

    /**
     * Generate session fingerprint for security
     */
    private function generateSessionFingerprint(): string
    {
        return hash('sha256', 
            ($this->getClientIp()) . 
            ($_SERVER['HTTP_USER_AGENT'] ?? '') . 
            ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')
        );
    }

    /**
     * Destroy session securely
     */
    private function destroySession(): void
    {
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }

    /**
     * Redirect to login with message
     */
    private function redirectToLogin(string $message = ''): void
    {
        if ($message) {
            session_start();
            $_SESSION['auth_message'] = $message;
        }

        if ($this->isAjaxRequest()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $message ?: 'Authentication required',
                'redirect' => '/auth/login'
            ]);
            exit;
        }

        header('Location: /auth/login');
        exit;
    }

    /**
     * Check if request is AJAX
     */
    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
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
