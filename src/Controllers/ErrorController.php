<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers;

use RfidCheckin\Controllers\BaseFrontendController;

/**
 * Error Controller
 * 
 * Handles HTTP error responses with appropriate error pages and logging.
 * Provides user-friendly error messages while maintaining security.
 * 
 * @package RfidCheckin\Controllers
 */
class ErrorController extends BaseFrontendController
{
    /**
     * Handle 403 Forbidden errors
     */
    public function forbidden(): void
    {
        http_response_code(403);

        $this->logger->warning('403 Forbidden access attempt', [
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'user_id' => $this->auth->getCurrentUserId(),
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        if ($this->isAjaxRequest()) {
            $this->renderJson([
                'error' => 'Access forbidden',
                'code' => 'FORBIDDEN',
                'message' => 'You do not have permission to access this resource'
            ], 403);
            return;
        }

        $data = [
            'title' => '403 - Access Forbidden',
            'heading' => 'Access Forbidden',
            'message' => 'You do not have permission to access this resource.',
            'suggestion' => 'Please contact your administrator if you believe this is an error.',
            'show_login' => !$this->auth->isAuthenticated(),
            'error_code' => '403'
        ];

        $this->render('errors/403', $data, [
            'title' => '403 - Access Forbidden',
            'page_class' => 'error-page error-403',
            'no_sidebar' => true
        ]);
    }

    /**
     * Handle 404 Not Found errors
     */
    public function notFound(): void
    {
        http_response_code(404);

        $requestedUrl = $_SERVER['REQUEST_URI'] ?? '';
        
        $this->logger->info('404 Not Found', [
            'url' => $requestedUrl,
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'user_id' => $this->auth->getCurrentUserId(),
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        if ($this->isAjaxRequest()) {
            $this->renderJson([
                'error' => 'Resource not found',
                'code' => 'NOT_FOUND',
                'message' => 'The requested resource could not be found'
            ], 404);
            return;
        }

        $data = [
            'title' => '404 - Page Not Found',
            'heading' => 'Page Not Found',
            'message' => 'The page you are looking for could not be found.',
            'suggestion' => 'Please check the URL or navigate back to the homepage.',
            'requested_url' => $this->sanitizeOutput($requestedUrl),
            'popular_pages' => $this->getPopularPages(),
            'error_code' => '404'
        ];

        $this->render('errors/404', $data, [
            'title' => '404 - Page Not Found',
            'page_class' => 'error-page error-404',
            'no_sidebar' => true
        ]);
    }

    /**
     * Handle 500 Internal Server Error
     */
    public function serverError(): void
    {
        http_response_code(500);

        // Generate unique error ID for tracking
        $errorId = uniqid('ERR_');

        $this->logger->error('500 Internal Server Error displayed', [
            'error_id' => $errorId,
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'user_id' => $this->auth->getCurrentUserId(),
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        if ($this->isAjaxRequest()) {
            $response = [
                'error' => 'Internal server error',
                'code' => 'INTERNAL_ERROR',
                'message' => 'An unexpected error occurred'
            ];

            if ($this->config->isDebugMode()) {
                $response['error_id'] = $errorId;
            }

            $this->renderJson($response, 500);
            return;
        }

        $data = [
            'title' => '500 - Server Error',
            'heading' => 'Internal Server Error',
            'message' => 'An unexpected error occurred while processing your request.',
            'suggestion' => 'Please try again in a few moments. If the problem persists, contact support.',
            'error_id' => $errorId,
            'show_debug' => $this->config->isDebugMode(),
            'error_code' => '500'
        ];

        $this->render('errors/500', $data, [
            'title' => '500 - Internal Server Error',
            'page_class' => 'error-page error-500',
            'no_sidebar' => true
        ]);
    }

    /**
     * Handle 429 Too Many Requests
     */
    public function tooManyRequests(): void
    {
        http_response_code(429);

        $this->logger->warning('429 Too Many Requests', [
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'user_id' => $this->auth->getCurrentUserId(),
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        if ($this->isAjaxRequest()) {
            $this->renderJson([
                'error' => 'Too many requests',
                'code' => 'RATE_LIMITED',
                'message' => 'You have made too many requests. Please try again later.'
            ], 429);
            return;
        }

        $data = [
            'title' => '429 - Too Many Requests',
            'heading' => 'Too Many Requests',
            'message' => 'You have made too many requests in a short period.',
            'suggestion' => 'Please wait a moment before trying again.',
            'error_code' => '429'
        ];

        $this->render('errors/429', $data, [
            'title' => '429 - Too Many Requests',
            'page_class' => 'error-page error-429',
            'no_sidebar' => true
        ]);
    }

    /**
     * Handle 503 Service Unavailable
     */
    public function serviceUnavailable(): void
    {
        http_response_code(503);

        $this->logger->critical('503 Service Unavailable displayed', [
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'user_id' => $this->auth->getCurrentUserId(),
            'ip_address' => $this->getClientIp()
        ]);

        if ($this->isAjaxRequest()) {
            $this->renderJson([
                'error' => 'Service unavailable',
                'code' => 'SERVICE_UNAVAILABLE',
                'message' => 'The service is temporarily unavailable'
            ], 503);
            return;
        }

        $data = [
            'title' => '503 - Service Unavailable',
            'heading' => 'Service Temporarily Unavailable',
            'message' => 'The service is temporarily unavailable for maintenance.',
            'suggestion' => 'Please try again in a few minutes.',
            'error_code' => '503'
        ];

        $this->render('errors/503', $data, [
            'title' => '503 - Service Unavailable',
            'page_class' => 'error-page error-503',
            'no_sidebar' => true
        ]);
    }

    /**
     * Generic error handler
     */
    public function genericError(int $statusCode, string $message = ''): void
    {
        http_response_code($statusCode);

        $this->logger->warning("HTTP $statusCode error", [
            'status_code' => $statusCode,
            'message' => $message,
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'user_id' => $this->auth->getCurrentUserId(),
            'ip_address' => $this->getClientIp()
        ]);

        if ($this->isAjaxRequest()) {
            $this->renderJson([
                'error' => $message ?: 'An error occurred',
                'code' => "HTTP_$statusCode"
            ], $statusCode);
            return;
        }

        $data = [
            'title' => "$statusCode - Error",
            'heading' => 'Error',
            'message' => $message ?: 'An error occurred while processing your request.',
            'suggestion' => 'Please try again or contact support if the problem persists.',
            'error_code' => (string) $statusCode
        ];

        $this->render('errors/generic', $data, [
            'title' => "$statusCode - Error",
            'page_class' => "error-page error-$statusCode",
            'no_sidebar' => true
        ]);
    }

    /**
     * Get popular pages for 404 suggestions
     */
    private function getPopularPages(): array
    {
        return [
            [
                'title' => 'Dashboard',
                'url' => '/dashboard',
                'description' => 'Main dashboard and overview'
            ],
            [
                'title' => 'Events',
                'url' => '/events',
                'description' => 'View and manage events'
            ],
            [
                'title' => 'My Profile',
                'url' => '/user/profile',
                'description' => 'View and edit your profile'
            ],
            [
                'title' => 'Help',
                'url' => '/help',
                'description' => 'Documentation and support'
            ]
        ];
    }

    /**
     * Get client IP address
     */
    protected function getClientIp(): string
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
     * Sanitize output for display
     */
    private function sanitizeOutput(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}