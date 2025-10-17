<?php

declare(strict_types=1);

namespace RfidCheckin\Middleware;

use RfidCheckin\Core\Session;

/**
 * Role Middleware
 * 
 * Checks if authenticated user has required role(s)
 */
class RoleMiddleware
{
    private array $allowedRoles;

    /**
     * Constructor
     */
    public function __construct(string $roles = '')
    {
        $this->allowedRoles = $roles ? explode(',', $roles) : [];
    }

    /**
     * Handle the request
     */
    public function handle($request, callable $next)
    {
        $session = new Session();
        
        // Make sure user is authenticated first
        if (!$session->isLoggedIn()) {
            http_response_code(401);
            
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Authentication required',
                    'redirect' => '/login'
                ]);
                exit;
            }
            
            header('Location: /login');
            exit;
        }
        
        // Check if user has required role
        $userRole = $session->getUserRole();
        
        if (!empty($this->allowedRoles) && !in_array($userRole, $this->allowedRoles)) {
            http_response_code(403);
            
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Access denied. Insufficient permissions.',
                    'required_roles' => $this->allowedRoles,
                    'user_role' => $userRole
                ]);
                exit;
            }
            
            // Show 403 forbidden page
            include __DIR__ . '/../../views/errors/403.php';
            exit;
        }
        
        // Continue to next middleware/controller
        return $next($request);
    }

    /**
     * Check if request is AJAX
     */
    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}