<?php

declare(strict_types=1);

namespace RfidCheckin\Middleware;

use RfidCheckin\Services\AuthenticationService;
use RfidCheckin\Services\LoggingService;

/**
 * Authorization Middleware
 * 
 * Ensures that authenticated users have the required permissions
 * to access specific routes and perform certain actions.
 * 
 * Features:
 * - Role-based access control
 * - Permission validation
 * - Resource-level authorization
 * - Hierarchical permission checking
 * - Access logging and monitoring
 * - Dynamic permission loading
 * 
 * @package RfidCheckin\Middleware
 * @version 1.0.0
 * @author Senior Development Team
 */
class AuthorizationMiddleware implements MiddlewareInterface
{
    private AuthenticationService $auth;
    private LoggingService $logger;
    
    private array $routePermissions = [
        // Admin routes
        '/admin' => ['admin'],
        '/admin/users' => ['admin', 'manage_users'],
        '/admin/events' => ['admin', 'manage_events'],
        '/admin/reports' => ['admin', 'view_reports'],
        '/admin/settings' => ['admin'],
        '/admin/security' => ['admin'],
        '/admin/database' => ['admin'],
        
        // API routes
        '/api/analytics' => ['admin', 'view_reports'],
        '/api/users' => ['admin', 'manage_users'],
        '/api/events/create' => ['admin', 'manage_events'],
        '/api/events/edit' => ['admin', 'manage_events'],
        '/api/events/delete' => ['admin', 'manage_events'],
        '/api/rfid-test' => ['admin', 'manage_devices'],
        
        // Event management
        '/events/create' => ['admin', 'manage_events'],
        '/events/edit' => ['admin', 'manage_events'],
        '/events/delete' => ['admin', 'manage_events'],
        '/events/checkins' => ['admin', 'manage_events', 'check_in_users'],
        '/events/analytics' => ['admin', 'manage_events', 'view_reports'],
        
        // Check-in operations
        '/checkin/manual' => ['admin', 'check_in_users'],
        '/api/manual-checkin' => ['admin', 'check_in_users'],
        
        // User management
        '/user/admin' => ['admin'],
        '/users/activate' => ['admin', 'manage_users'],
        '/users/deactivate' => ['admin', 'manage_users'],
    ];

    private array $groupPermissions = [
        1 => ['admin', 'manage_users', 'manage_events', 'view_reports', 'check_in_users', 'manage_devices'],
        2 => ['manage_events', 'view_reports', 'check_in_users'],
        3 => ['view_own_data', 'self_checkin']
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->auth = AuthenticationService::getInstance();
        $this->logger = LoggingService::getInstance();
    }

    /**
     * Handle authorization check
     */
    public function handle(callable $next)
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $requestPath = parse_url($requestUri, PHP_URL_PATH);
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Skip authorization for non-authenticated requests
        // (AuthenticationMiddleware should run first)
        if (!$this->auth->isAuthenticated()) {
            return $next();
        }

        // Get required permissions for this route
        $requiredPermissions = $this->getRequiredPermissions($requestPath, $requestMethod);

        // If no specific permissions required, allow access
        if (empty($requiredPermissions)) {
            return $next();
        }

        // Check if user has required permissions
        $user = $this->auth->getCurrentUser();
        $userPermissions = $this->getUserPermissions($user);

        if (!$this->hasAnyPermission($userPermissions, $requiredPermissions)) {
            $this->handleUnauthorizedAccess($requestPath, $requiredPermissions, $userPermissions);
            return;
        }

        // Log successful authorization
        $this->logAuthorizationSuccess($requestPath, $requiredPermissions, $userPermissions);

        return $next();
    }

    /**
     * Get required permissions for a route
     */
    private function getRequiredPermissions(string $path, string $method): array
    {
        // Check exact path match first
        if (isset($this->routePermissions[$path])) {
            return $this->routePermissions[$path];
        }

        // Check pattern matches
        foreach ($this->routePermissions as $pattern => $permissions) {
            if ($this->matchesPattern($path, $pattern)) {
                return $permissions;
            }
        }

        // Check method-specific permissions
        $methodPath = strtolower($method) . ':' . $path;
        if (isset($this->routePermissions[$methodPath])) {
            return $this->routePermissions[$methodPath];
        }

        return [];
    }

    /**
     * Check if path matches pattern
     */
    private function matchesPattern(string $path, string $pattern): bool
    {
        // Convert pattern to regex
        $regex = str_replace(
            ['*', '?'],
            ['.*', '.'],
            preg_quote($pattern, '/')
        );
        
        return preg_match('/^' . $regex . '/', $path) === 1;
    }

    /**
     * Get user permissions based on group and individual permissions
     */
    private function getUserPermissions(array $user): array
    {
        $groupId = $user['group_id'] ?? 3;
        $permissions = $this->groupPermissions[$groupId] ?? ['view_own_data'];

        // Add individual user permissions if they exist
        if (!empty($user['individual_permissions'])) {
            $individualPermissions = json_decode($user['individual_permissions'], true) ?? [];
            $permissions = array_unique(array_merge($permissions, $individualPermissions));
        }

        return $permissions;
    }

    /**
     * Check if user has any of the required permissions
     */
    private function hasAnyPermission(array $userPermissions, array $requiredPermissions): bool
    {
        return !empty(array_intersect($userPermissions, $requiredPermissions));
    }

    /**
     * Handle unauthorized access
     */
    private function handleUnauthorizedAccess(string $path, array $required, array $userPermissions): void
    {
        $user = $this->auth->getCurrentUser();
        
        $this->logger->warning('Unauthorized access attempt', [
            'user_id' => $user['user_id'] ?? null,
            'user_email' => $user['email'] ?? null,
            'requested_path' => $path,
            'required_permissions' => $required,
            'user_permissions' => $userPermissions,
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        // Check if it's an AJAX request
        if ($this->isAjaxRequest()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Insufficient permissions',
                'required_permissions' => $required
            ]);
            exit;
        }

        // Check if user is admin but lacks specific permission
        if (in_array('admin', $userPermissions)) {
            // Admin users get a detailed error page
            $this->renderPermissionError($path, $required, 'admin');
        } else {
            // Regular users get redirected to access denied page
            $this->redirectToAccessDenied($path);
        }
    }

    /**
     * Log successful authorization
     */
    private function logAuthorizationSuccess(string $path, array $required, array $userPermissions): void
    {
        $user = $this->auth->getCurrentUser();
        
        $this->logger->debug('Authorization successful', [
            'user_id' => $user['user_id'] ?? null,
            'path' => $path,
            'required_permissions' => $required,
            'matched_permissions' => array_intersect($userPermissions, $required)
        ]);
    }

    /**
     * Render permission error page
     */
    private function renderPermissionError(string $path, array $required, string $userType): void
    {
        http_response_code(403);
        
        echo '<!DOCTYPE html>
<html>
<head>
    <title>Access Denied</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .error-container { max-width: 600px; }
        .error-code { color: #dc3545; font-size: 48px; font-weight: bold; }
        .error-message { color: #6c757d; margin: 20px 0; }
        .error-details { background: #f8f9fa; padding: 20px; border-radius: 5px; }
        .back-link { margin-top: 20px; }
        .back-link a { color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">403</div>
        <h1>Access Denied</h1>
        <p class="error-message">You do not have sufficient permissions to access this resource.</p>
        
        <div class="error-details">
            <h3>Details:</h3>
            <p><strong>Requested Path:</strong> ' . htmlspecialchars($path) . '</p>
            <p><strong>Required Permissions:</strong> ' . implode(', ', $required) . '</p>
            <p><strong>User Type:</strong> ' . htmlspecialchars($userType) . '</p>
        </div>
        
        <div class="back-link">
            <a href="/dashboard">← Return to Dashboard</a>
        </div>
    </div>
</body>
</html>';
        exit;
    }

    /**
     * Redirect to access denied page
     */
    private function redirectToAccessDenied(string $path): void
    {
        // Store the attempted path for reference
        session_start();
        $_SESSION['access_denied_path'] = $path;
        
        header('Location: /error/403');
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
     * Add route permission dynamically
     */
    public function addRoutePermission(string $route, array $permissions): void
    {
        $this->routePermissions[$route] = $permissions;
    }

    /**
     * Remove route permission
     */
    public function removeRoutePermission(string $route): void
    {
        unset($this->routePermissions[$route]);
    }

    /**
     * Get all route permissions
     */
    public function getRoutePermissions(): array
    {
        return $this->routePermissions;
    }

    /**
     * Check specific permission for current user
     */
    public function checkPermission(string $permission): bool
    {
        if (!$this->auth->isAuthenticated()) {
            return false;
        }

        $user = $this->auth->getCurrentUser();
        $userPermissions = $this->getUserPermissions($user);

        return in_array($permission, $userPermissions);
    }

    /**
     * Check multiple permissions (user must have ALL)
     */
    public function checkAllPermissions(array $permissions): bool
    {
        if (!$this->auth->isAuthenticated()) {
            return false;
        }

        $user = $this->auth->getCurrentUser();
        $userPermissions = $this->getUserPermissions($user);

        return empty(array_diff($permissions, $userPermissions));
    }
}
