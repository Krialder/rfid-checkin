<?php

namespace RfidCheckin\Controllers;

use RfidCheckin\Services\ConfigurationService;
use RfidCheckin\Services\DatabaseService;
use RfidCheckin\Services\LoggingService;

/**
 * Base Controller
 * 
 * Provides common functionality for all controllers:
 * - View rendering
 * - Authentication handling
 * - Common response methods
 * - Error handling
 * - CSRF validation
 */
class BaseController
{
    protected ConfigurationService $config;
    protected DatabaseService $db;
    protected LoggingService $logger;
    
    public function __construct()
    {
        $this->config = ConfigurationService::getInstance();
        $this->db = DatabaseService::getInstance();
        $this->logger = LoggingService::getInstance();
    }
    
    /**
     * Render a view template
     */
    protected function render(string $view, array $data = [], string $layout = 'app'): string
    {
        // Simple view rendering - to be implemented with proper view system
        ob_start();
        extract($data);
        
        $viewFile = dirname(__DIR__) . "/Views/{$view}.php";
        if (file_exists($viewFile)) {
            include $viewFile;
        } else {
            throw new \Exception("View not found: {$view}");
        }
        
        return ob_get_clean();
    }
    
    /**
     * Get the current authenticated user
     */
    protected function getCurrentUser(): ?array
    {
        if (isset($_SESSION['user_id'])) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'] ?? '',
                'name' => $_SESSION['user_name'] ?? '',
                'role' => $_SESSION['user_role'] ?? 'user',
                'groups' => $_SESSION['user_groups'] ?? []
            ];
        }
        return null;
    }
    
    /**
     * Check if user is authenticated
     */
    protected function requireAuth(): ?array
    {
        $user = $this->getCurrentUser();
        
        if (!$user) {
            $this->redirectToLogin();
            return null;
        }
        
        return $user;
    }
    
    /**
     * Check if user has specific role
     */
    protected function requireRole(string $role): bool
    {
        $user = $this->requireAuth();
        
        if (!$user || $user['role'] !== $role) {
            $this->renderForbidden();
            return false;
        }
        
        return true;
    }
    
    /**
     * Redirect to login page
     */
    protected function redirectToLogin(): void
    {
        if ($this->isApiRequest()) {
            $this->jsonResponse(['error' => 'Authentication required'], 401);
        } else {
            header('Location: /login');
            exit();
        }
    }
    
    /**
     * Render 403 Forbidden page
     */
    protected function renderForbidden(): void
    {
        if ($this->isApiRequest()) {
            $this->jsonResponse(['error' => 'Access forbidden'], 403);
        } else {
            http_response_code(403);
            echo $this->render('error/403', [
                'pageTitle' => 'Access Forbidden',
                'error' => 'You do not have permission to access this resource.'
            ], 'app');
            exit();
        }
    }
    
    /**
     * Render 404 Not Found page
     */
    protected function renderNotFound(): void
    {
        if ($this->isApiRequest()) {
            $this->jsonResponse(['error' => 'Resource not found'], 404);
        } else {
            http_response_code(404);
            echo $this->render('error/404', [
                'pageTitle' => 'Page Not Found',
                'error' => 'The requested page could not be found.'
            ], 'app');
            exit();
        }
    }
    
    /**
     * Render 500 Internal Server Error page
     */
    protected function renderError(string $message = 'An internal error occurred'): void
    {
        if ($this->isApiRequest()) {
            $this->jsonResponse(['error' => $message], 500);
        } else {
            http_response_code(500);
            echo $this->render('error/500', [
                'pageTitle' => 'Server Error',
                'error' => $message
            ], 'app');
            exit();
        }
    }
    
    /**
     * Send JSON response
     */
    protected function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
    
    /**
     * Send success JSON response
     */
    protected function jsonSuccess(array $data = [], string $message = ''): void
    {
        $response = ['success' => true];
        
        if (!empty($data)) {
            $response['data'] = $data;
        }
        
        if (!empty($message)) {
            $response['message'] = $message;
        }
        
        $this->jsonResponse($response);
    }
    
    /**
     * Send error JSON response
     */
    protected function jsonError(string $message, int $statusCode = 400, array $errors = []): void
    {
        $response = [
            'success' => false,
            'message' => $message
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        $this->jsonResponse($response, $statusCode);
    }
    
    /**
     * Check if current request is an API request
     */
    protected function isApiRequest(): bool
    {
        $path = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($path, '/api/') === 0 || 
               (isset($_SERVER['HTTP_CONTENT_TYPE']) && strpos($_SERVER['HTTP_CONTENT_TYPE'], 'application/json') !== false) ||
               (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    }
    
    /**
     * Get request input data
     */
    protected function getInput(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true);
            return $input ?: [];
        }
        
        return array_merge($_GET, $_POST);
    }
    
    /**
     * Validate CSRF token
     */
    protected function validateCsrf(): bool
    {
        $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return $this->security->validateCsrfToken($token);
    }
    
    /**
     * Require CSRF token validation
     */
    protected function requireCsrf(): bool
    {
        if (!$this->validateCsrf()) {
            if ($this->isApiRequest()) {
                $this->jsonError('Invalid CSRF token', 422);
            } else {
                echo $this->render('error/csrf', [
                    'pageTitle' => 'Security Error',
                    'error' => 'Invalid security token. Please try again.'
                ], 'app');
                exit();
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Log controller action
     */
    protected function logAction(string $action, array $context = []): void
    {
        $user = $this->getCurrentUser();
        
        $this->logger->info("Controller action: {$action}", array_merge([
            'controller' => static::class,
            'user_id' => $user['id'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ], $context));
    }
    
    /**
     * Handle controller exceptions
     */
    protected function handleException(\Exception $e, string $action = ''): void
    {
        $this->logger->error('Controller exception', [
            'controller' => static::class,
            'action' => $action,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        $this->renderError('An unexpected error occurred. Please try again.');
    }
    
    /**
     * Validate required fields
     */
    protected function validateRequired(array $data, array $required): array
    {
        $errors = [];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
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
            if (is_string($value)) {
                $sanitized[$key] = trim(strip_tags($value));
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeInput($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Get pagination parameters
     */
    protected function getPaginationParams(): array
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        
        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    
    /**
     * Build pagination data
     */
    protected function buildPagination(int $total, int $page, int $limit): array
    {
        $totalPages = ceil($total / $limit);
        
        return [
            'current_page' => $page,
            'per_page' => $limit,
            'total_items' => $total,
            'total_pages' => $totalPages,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages,
            'query_string' => $this->buildQueryString(['page'])
        ];
    }
    
    /**
     * Build query string excluding specified parameters
     */
    protected function buildQueryString(array $exclude = []): string
    {
        $params = $_GET;
        
        foreach ($exclude as $key) {
            unset($params[$key]);
        }
        
        return empty($params) ? '' : '&' . http_build_query($params);
    }
}