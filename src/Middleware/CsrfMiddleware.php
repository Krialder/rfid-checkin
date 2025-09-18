<?php

declare(strict_types=1);

namespace RfidCheckin\Middleware;

use RfidCheckin\Services\SecurityService;
use RfidCheckin\Services\LoggingService;

/**
 * CSRF Protection Middleware
 * 
 * Protects against Cross-Site Request Forgery attacks by validating
 * CSRF tokens on state-changing HTTP requests.
 * 
 * Features:
 * - Token generation and validation
 * - Automatic token refresh
 * - Request method checking
 * - AJAX request support
 * - Token expiration handling
 * - Security logging
 * 
 * @package RfidCheckin\Middleware
 * @version 1.0.0
 * @author Senior Development Team
 */
class CsrfMiddleware implements MiddlewareInterface
{
    private SecurityService $security;
    private LoggingService $logger;
    
    private array $protectedMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
    private array $exemptRoutes = [
        '/api/rfid-poll',
        '/api/rfid-poll-noauth',
        '/api/rfid-checkin',
        '/auth/login',
        '/auth/logout'
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->security = SecurityService::getInstance();
        $this->logger = LoggingService::getInstance();
    }

    /**
     * Handle CSRF protection
     */
    public function handle(callable $next)
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $requestPath = parse_url($requestUri, PHP_URL_PATH);

        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Generate token for all requests (for forms and AJAX)
        $this->ensureCsrfToken();

        // Skip CSRF check for safe methods
        if (!in_array($requestMethod, $this->protectedMethods)) {
            return $next();
        }

        // Skip CSRF check for exempt routes
        if ($this->isExemptRoute($requestPath)) {
            return $next();
        }

        // Validate CSRF token
        if (!$this->validateCsrfToken()) {
            $this->handleCsrfViolation($requestPath, $requestMethod);
            return;
        }

        // Log successful CSRF validation
        $this->logCsrfSuccess($requestPath, $requestMethod);

        return $next();
    }

    /**
     * Ensure CSRF token exists in session
     */
    private function ensureCsrfToken(): void
    {
        if (!isset($_SESSION['csrf_token']) || $this->isTokenExpired()) {
            $_SESSION['csrf_token'] = $this->security->generateCsrfToken();
            $_SESSION['csrf_token_time'] = time();
        }
    }

    /**
     * Check if CSRF token has expired
     */
    private function isTokenExpired(): bool
    {
        $tokenTime = $_SESSION['csrf_token_time'] ?? 0;
        $maxAge = 3600; // 1 hour
        
        return (time() - $tokenTime) > $maxAge;
    }

    /**
     * Validate CSRF token from request
     */
    private function validateCsrfToken(): bool
    {
        $token = $this->getTokenFromRequest();
        
        if (!$token) {
            $this->logger->warning('CSRF token missing from request', [
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'ip_address' => $this->getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            return false;
        }

        $sessionToken = $_SESSION['csrf_token'] ?? null;
        
        if (!$sessionToken) {
            $this->logger->warning('CSRF token missing from session', [
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip_address' => $this->getClientIp()
            ]);
            return false;
        }

        // Use hash_equals to prevent timing attacks
        if (!hash_equals($sessionToken, $token)) {
            $this->logger->warning('CSRF token mismatch', [
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'ip_address' => $this->getClientIp(),
                'expected_token' => substr($sessionToken, 0, 10) . '...',
                'received_token' => substr($token, 0, 10) . '...'
            ]);
            return false;
        }

        return true;
    }

    /**
     * Get CSRF token from request
     */
    private function getTokenFromRequest(): ?string
    {
        // Check POST data first
        if (!empty($_POST['csrf_token'])) {
            return $_POST['csrf_token'];
        }

        // Check headers (for AJAX requests)
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        // Check custom header
        if (!empty($_SERVER['HTTP_X_XSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_XSRF_TOKEN'];
        }

        // Check query parameters (less secure, only for specific cases)
        if (!empty($_GET['csrf_token'])) {
            return $_GET['csrf_token'];
        }

        return null;
    }

    /**
     * Check if route is exempt from CSRF protection
     */
    private function isExemptRoute(string $path): bool
    {
        foreach ($this->exemptRoutes as $exemptRoute) {
            if (strpos($path, $exemptRoute) === 0) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Handle CSRF violation
     */
    private function handleCsrfViolation(string $path, string $method): void
    {
        $this->logger->error('CSRF violation detected', [
            'path' => $path,
            'method' => $method,
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'session_id' => session_id(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'post_data' => $this->sanitizeLogData($_POST),
            'token_in_request' => !empty($this->getTokenFromRequest()),
            'token_in_session' => !empty($_SESSION['csrf_token'])
        ]);

        // Increment security violation counter
        $this->incrementSecurityViolations();

        // Check if request is AJAX
        if ($this->isAjaxRequest()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'CSRF token validation failed',
                'code' => 'CSRF_VIOLATION',
                'new_token' => $_SESSION['csrf_token'] ?? null
            ]);
            exit;
        }

        // Render CSRF error page
        $this->renderCsrfErrorPage();
    }

    /**
     * Log successful CSRF validation
     */
    private function logCsrfSuccess(string $path, string $method): void
    {
        $this->logger->debug('CSRF validation successful', [
            'path' => $path,
            'method' => $method,
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
    }

    /**
     * Increment security violations counter
     */
    private function incrementSecurityViolations(): void
    {
        $ip = $this->getClientIp();
        $key = "csrf_violations_{$ip}";
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = 0;
        }
        
        $_SESSION[$key]++;
        
        // Log excessive violations
        if ($_SESSION[$key] >= 5) {
            $this->logger->critical('Excessive CSRF violations', [
                'ip_address' => $ip,
                'violation_count' => $_SESSION[$key],
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
        }
    }

    /**
     * Render CSRF error page
     */
    private function renderCsrfErrorPage(): void
    {
        http_response_code(403);
        
        // Include the current CSRF token for retry
        $csrfToken = $_SESSION['csrf_token'] ?? '';
        
        echo '<!DOCTYPE html>
<html>
<head>
    <title>Security Error</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0; padding: 40px; background: #f8f9fa; 
        }
        .error-container { 
            max-width: 600px; margin: 0 auto; background: white;
            padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .error-icon { font-size: 48px; color: #dc3545; text-align: center; }
        .error-title { color: #dc3545; font-size: 24px; margin: 20px 0 10px; text-align: center; }
        .error-message { color: #6c757d; margin: 20px 0; line-height: 1.5; }
        .error-details { 
            background: #f8f9fa; padding: 20px; border-radius: 5px; 
            border-left: 4px solid #dc3545; margin: 20px 0;
        }
        .retry-form { margin: 20px 0; text-align: center; }
        .btn { 
            background: #007bff; color: white; padding: 10px 20px; 
            border: none; border-radius: 4px; cursor: pointer; text-decoration: none;
            display: inline-block;
        }
        .btn:hover { background: #0056b3; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #6c757d; text-decoration: none; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">🛡️</div>
        <h1 class="error-title">Security Verification Failed</h1>
        
        <p class="error-message">
            Your request could not be processed due to a security verification failure. 
            This typically happens when:
        </p>
        
        <div class="error-details">
            <ul>
                <li>Your session has expired</li>
                <li>You have been inactive for too long</li>
                <li>There was a network interruption</li>
                <li>Multiple browser tabs are open</li>
            </ul>
        </div>
        
        <div class="retry-form">
            <button class="btn" onclick="window.history.back()">← Go Back and Retry</button>
            <br><br>
            <a href="/dashboard" class="btn">Return to Dashboard</a>
        </div>
        
        <div class="back-link">
            <small>
                If this problem persists, please contact support.<br>
                Reference ID: ' . substr(session_id(), 0, 8) . '
            </small>
        </div>
    </div>
    
    <script>
        // Auto-refresh CSRF token for retry
        if (typeof window.csrfToken !== "undefined") {
            window.csrfToken = "' . htmlspecialchars($csrfToken) . '";
        }
    </script>
</body>
</html>';
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

    /**
     * Sanitize data for logging (remove sensitive information)
     */
    private function sanitizeLogData(array $data): array
    {
        $sanitized = [];
        $sensitiveFields = ['password', 'password_confirm', 'current_password', 'new_password'];
        
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = is_string($value) ? substr($value, 0, 100) : $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Add exempt route
     */
    public function addExemptRoute(string $route): void
    {
        if (!in_array($route, $this->exemptRoutes)) {
            $this->exemptRoutes[] = $route;
        }
    }

    /**
     * Remove exempt route
     */
    public function removeExemptRoute(string $route): void
    {
        $this->exemptRoutes = array_filter($this->exemptRoutes, function($r) use ($route) {
            return $r !== $route;
        });
    }

    /**
     * Get current CSRF token
     */
    public function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->ensureCsrfToken();
        return $_SESSION['csrf_token'];
    }
}
