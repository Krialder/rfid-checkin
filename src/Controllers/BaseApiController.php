<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers;

use RfidCheckin\Services\LoggingService;
use RfidCheckin\Services\SecurityService;
use RfidCheckin\Services\ConfigurationService;
use Exception;

/**
 * Base API Controller
 * 
 * Provides common functionality for all API controllers including
 * response formatting, error handling, validation, and authentication.
 * 
 * Features:
 * - Standardized JSON API responses
 * - Error handling
 * - Request validation and sanitization
 * - Authentication and authorization
 * - Rate limiting and CORS
 * - Performance monitoring
 * 
 * @package RfidCheckin\Controllers
 * @version 1.0.0
 */
abstract class BaseApiController
{
    protected LoggingService $logger;
    protected SecurityService $security;
    protected ConfigurationService $config;
    
    protected array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
    protected bool $requiresAuth = true;
    protected array $permissions = [];
    protected int $rateLimitPerMinute = 60;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = LoggingService::getInstance();
        $this->security = SecurityService::getInstance();
        $this->config = ConfigurationService::getInstance();
        
        $this->initializeController();
    }

    /**
     * Initialize controller with common setup
     */
    protected function initializeController(): void
    {
        // Set JSON content type
        header('Content-Type: application/json; charset=utf-8');
        
        // Handle CORS
        $this->handleCors();
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->sendCorsHeaders();
            exit(0);
        }
        
        // Validate HTTP method
        if (!in_array($_SERVER['REQUEST_METHOD'], $this->allowedMethods)) {
            $this->respondError('Method not allowed', 405);
            exit;
        }
        
        // Rate limiting
        if ($this->config->get('api.rate_limit_enabled', true)) {
            $this->enforceRateLimit();
        }
        
        // Authentication check
        if ($this->requiresAuth && !$this->isAuthenticated()) {
            $this->respondError('Authentication required', 401);
            exit;
        }
        
        // Authorization check
        if (!empty($this->permissions) && !$this->hasPermissions($this->permissions)) {
            $this->respondError('Insufficient permissions', 403);
            exit;
        }
        
        // CSRF protection for state-changing methods
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            if (!$this->security->validateCsrfToken($this->getHeaderValue('X-CSRF-Token'))) {
                $this->respondError('Invalid CSRF token', 403);
                exit;
            }
        }
    }

    /**
     * Handle CORS headers
     */
    protected function handleCors(): void
    {
        $allowedOrigins = $this->config->get('api.allowed_origins', ['*']);
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
            header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
        }
        
        $this->sendCorsHeaders();
    }

    /**
     * Send CORS headers
     */
    protected function sendCorsHeaders(): void
    {
        header('Access-Control-Allow-Methods: ' . implode(', ', $this->allowedMethods));
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400'); // 24 hours
    }

    /**
     * Enforce rate limiting
     */
    protected function enforceRateLimit(): void
    {
        $clientId = $this->getClientIdentifier();
        $window = 60; // 1 minute
        $limit = $this->rateLimitPerMinute;
        
        if (!$this->security->checkRateLimit($clientId, $limit, $window)) {
            $this->respondError('Rate limit exceeded', 429, [
                'retry_after' => $window
            ]);
            exit;
        }
    }

    /**
     * Get client identifier for rate limiting
     */
    protected function getClientIdentifier(): string
    {
        // Use authenticated user ID if available
        if ($this->isAuthenticated()) {
            return 'user_' . $this->getCurrentUserId();
        }
        
        // Fallback to IP address
        return 'ip_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }

    /**
     * Check if request is authenticated
     */
    protected function isAuthenticated(): bool
    {
        // Check session authentication
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get current user ID
     */
    protected function getCurrentUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Check if user has required permissions
     */
    protected function hasPermissions(array $requiredPermissions): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $userPermissions = $_SESSION['permissions'] ?? [];
        
        foreach ($requiredPermissions as $permission) {
            if (!in_array($permission, $userPermissions)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get request input data
     */
    protected function getInput(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true);
            return $input ?? [];
        }
        
        return $_REQUEST;
    }

    /**
     * Get header value
     */
    protected function getHeaderValue(string $header): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
        return $_SERVER[$key] ?? null;
    }

    /**
     * Validate required fields
     */
    protected function validateRequired(array $data, array $required): array
    {
        $errors = [];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[$field] = "Field '{$field}' is required";
            }
        }
        
        return $errors;
    }

    /**
     * Sanitize input data
     */
    protected function sanitizeInput(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeInput($value);
            } else {
                $sanitized[$key] = $this->security->sanitizeInput($value);
            }
        }
        
        return $sanitized;
    }

    /**
     * Send successful JSON response
     */
    protected function respondSuccess($data = null, int $statusCode = 200, array $meta = []): void
    {
        $response = [
            'success' => true,
            'status_code' => $statusCode,
            'timestamp' => date('c'),
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        
        http_response_code($statusCode);
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $this->logApiRequest($statusCode, $response);
    }

    /**
     * Send error JSON response
     */
    protected function respondError(string $message, int $statusCode = 400, array $details = []): void
    {
        $response = [
            'success' => false,
            'status_code' => $statusCode,
            'error' => [
                'message' => $message,
                'code' => $this->getErrorCode($statusCode),
            ],
            'timestamp' => date('c'),
        ];
        
        if (!empty($details)) {
            $response['error']['details'] = $details;
        }
        
        // Add debug information in development
        if ($this->config->isDebugMode() && !empty($GLOBALS['last_error'])) {
            $response['debug'] = $GLOBALS['last_error'];
        }
        
        http_response_code($statusCode);
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $this->logApiRequest($statusCode, $response);
    }

    /**
     * Send validation error response
     */
    protected function respondValidationError(array $errors): void
    {
        $this->respondError('Validation failed', 422, [
            'validation_errors' => $errors
        ]);
    }

    /**
     * Send paginated response
     */
    protected function respondPaginated(array $data, array $pagination, array $meta = []): void
    {
        $response = [
            'success' => true,
            'status_code' => 200,
            'data' => $data,
            'pagination' => $pagination,
            'timestamp' => date('c'),
        ];
        
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $this->logApiRequest(200, $response);
    }

    /**
     * Handle exceptions
     */
    protected function handleException(Exception $e): void
    {
        $this->logger->error('API Exception', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'user_id' => $this->getCurrentUserId(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? ''
        ]);
        
        if ($this->config->isDebugMode()) {
            $this->respondError('Internal server error: ' . $e->getMessage(), 500, [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        } else {
            $this->respondError('Internal server error', 500);
        }
    }

    /**
     * Get error code from HTTP status
     */
    protected function getErrorCode(int $statusCode): string
    {
        $codes = [
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            422 => 'VALIDATION_ERROR',
            429 => 'RATE_LIMIT_EXCEEDED',
            500 => 'INTERNAL_ERROR',
            503 => 'SERVICE_UNAVAILABLE'
        ];
        
        return $codes[$statusCode] ?? 'UNKNOWN_ERROR';
    }

    /**
     * Log API request
     */
    protected function logApiRequest(int $statusCode, array $response): void
    {
        $logData = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_id' => $this->getCurrentUserId(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'status_code' => $statusCode,
            'response_size' => strlen(json_encode($response)),
            'execution_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))
        ];
        
        if ($statusCode >= 400) {
            $this->logger->warning('API Error Response', $logData);
        } else {
            $this->logger->info('API Success Response', $logData);
        }
    }

    /**
     * Get pagination parameters from request
     */
    protected function getPaginationParams(): array
    {
        $page = (int) ($_GET['page'] ?? 1);
        $limit = (int) ($_GET['limit'] ?? $this->config->get('api.pagination_default_limit', 20));
        $maxLimit = $this->config->get('api.pagination_max_limit', 100);
        
        // Ensure valid values
        $page = max(1, $page);
        $limit = max(1, min($limit, $maxLimit));
        
        return ['page' => $page, 'limit' => $limit];
    }

    /**
     * Get filter parameters from request
     */
    protected function getFilterParams(array $allowedFilters = []): array
    {
        $filters = [];
        
        foreach ($allowedFilters as $filter) {
            if (isset($_GET[$filter]) && $_GET[$filter] !== '') {
                $filters[$filter] = $this->security->sanitizeInput($_GET[$filter]);
            }
        }
        
        return $filters;
    }

    /**
     * Get sort parameters from request
     */
    protected function getSortParams(array $allowedFields = []): array
    {
        $sort = $_GET['sort'] ?? '';
        $direction = strtoupper($_GET['direction'] ?? 'ASC');
        
        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }
        
        if (!empty($sort) && in_array($sort, $allowedFields)) {
            return ['field' => $sort, 'direction' => $direction];
        }
        
        return [];
    }

    /**
     * Build pagination metadata
     */
    protected function buildPaginationMeta(int $total, int $page, int $limit): array
    {
        $totalPages = (int) ceil($total / $limit);
        
        return [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $total,
            'items_per_page' => $limit,
            'has_next_page' => $page < $totalPages,
            'has_prev_page' => $page > 1,
            'next_page' => $page < $totalPages ? $page + 1 : null,
            'prev_page' => $page > 1 ? $page - 1 : null
        ];
    }

    /**
     * Execute with error handling
     */
    protected function executeWithErrorHandling(callable $callback): void
    {
        try {
            $callback();
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }
}
