<?php

declare(strict_types=1);

namespace RfidCheckin\Middleware;

use RfidCheckin\Core\Session;

/**
 * Authentication Middleware
 * 
 * Simple middleware that ensures user is logged in before accessing protected routes
 */
class AuthenticationMiddleware
{
    /**
     * Handle the request
     */
    public function handle($request, callable $next)
    {
        $session = new Session();
        
        // Check if user is logged in
        if (!$session->isLoggedIn()) {
            // Store the requested URL for redirect after login
            $requestedUrl = $_SERVER['REQUEST_URI'] ?? '/';
            $session->set('redirect_after_login', $requestedUrl);
            
            // Check if this is an AJAX request
            if ($this->isAjaxRequest()) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Authentication required',
                    'redirect' => '/login'
                ]);
                exit;
            }
            
            // Redirect to login page
            header('Location: /login');
            exit;
        }
        
        // Check if session has expired
        if ($session->isExpired()) {
            $session->logout();
            $session->flash('error', 'Your session has expired. Please log in again.');
            
            if ($this->isAjaxRequest()) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Session expired',
                    'redirect' => '/login'
                ]);
                exit;
            }
            
            header('Location: /login');
            exit;
        }
        
        // Update last activity
        $session->updateActivity();
        
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
