<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers;

use RfidCheckin\Services\DatabaseService;
use RfidCheckin\Services\LoggingService;
use RfidCheckin\Services\SecurityService;
use RfidCheckin\Services\ConfigurationService;
use RfidCheckin\Services\AuthenticationService;
use Exception;

/**
 * Base Frontend Controller
 * 
 * Provides common functionality for all frontend (web interface) controllers
 * including authentication, view rendering, form handling, and session management.
 * 
 * Features:
 * - View rendering with template system
 * - Authentication and authorization
 * - Form validation and CSRF protection
 * - Flash messages and redirects
 * - Asset management
 * - SEO and meta tag handling
 * 
 * @package RfidCheckin\Controllers
 * @version 1.0.0
 * @author Senior Development Team
 */
abstract class BaseFrontendController
{
    protected DatabaseService $db;
    protected LoggingService $logger;
    protected SecurityService $security;
    protected ConfigurationService $config;
    protected AuthenticationService $auth;
    
    protected array $viewData = [];
    protected array $assets = ['css' => [], 'js' => []];
    protected string $layout = 'default';
    protected array $breadcrumbs = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = DatabaseService::getInstance();
        $this->logger = LoggingService::getInstance();
        $this->security = SecurityService::getInstance();
        $this->config = ConfigurationService::getInstance();
        $this->auth = AuthenticationService::getInstance();
        
        $this->initializeSession();
        $this->setGlobalViewData();
    }

    /**
     * Initialize session
     */
    private function initializeSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerate session ID periodically for security
        $regenerateInterval = $this->config->get('security.session_regenerate_interval', 600);
        $lastRegeneration = $_SESSION['last_regeneration'] ?? 0;
        
        if (time() - $lastRegeneration > $regenerateInterval) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }

    /**
     * Set global view data available to all templates
     */
    private function setGlobalViewData(): void
    {
        $this->viewData = [
            'app_name' => $this->config->get('app.name'),
            'app_version' => $this->config->get('app.version'),
            'base_url' => $this->config->get('app.base_url'),
            'current_user' => $this->auth->getCurrentUser(),
            'is_authenticated' => $this->auth->isAuthenticated(),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_messages' => $this->getFlashMessages(),
            'environment' => $this->config->getEnvironment(),
            'debug_mode' => $this->config->isDebugMode()
        ];
    }

    /**
     * Require authentication
     */
    protected function requireAuth(): void
    {
        if (!$this->auth->isAuthenticated()) {
            $this->redirectToLogin();
            exit;
        }
    }

    /**
     * Check if user has required permissions
     */
    protected function hasPermissions(array $permissions): bool
    {
        if (!$this->auth->isAuthenticated()) {
            return false;
        }

        $user = $this->auth->getCurrentUser();
        $userPermissions = $this->getUserPermissions($user);

        foreach ($permissions as $permission) {
            if (in_array($permission, $userPermissions)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Require specific permissions
     */
    protected function requirePermissions(array $permissions, string $redirectUrl = '/'): void
    {
        $this->requireAuth();
        
        if (!$this->hasPermissions($permissions)) {
            $this->redirectWithError($redirectUrl, 'Insufficient permissions');
            exit;
        }
    }

    /**
     * Get user permissions
     */
    private function getUserPermissions(array $user): array
    {
        $groupPermissions = [
            1 => ['admin', 'manage_users', 'manage_events', 'view_reports', 'check_in_users'],
            2 => ['manage_events', 'view_reports', 'check_in_users'],
            3 => ['view_own_data', 'self_checkin']
        ];

        $groupId = $user['group_id'] ?? 3;
        return $groupPermissions[$groupId] ?? ['view_own_data'];
    }

    /**
     * Render view template
     */
    protected function render(string $view, array $data = [], array $options = []): void
    {
        // Merge data
        $data = array_merge($this->viewData, $data);
        
        // Set page-specific options
        if (isset($options['title'])) {
            $data['page_title'] = $options['title'];
        }
        
        if (isset($options['description'])) {
            $data['page_description'] = $options['description'];
        }
        
        $data['page_class'] = $options['page_class'] ?? '';
        $data['breadcrumbs'] = $this->breadcrumbs;
        $data['assets'] = $this->assets;
        
        // Add required assets
        if ($options['require_charts'] ?? false) {
            $this->addAsset('js', 'chart.js');
        }
        
        if ($options['require_datatables'] ?? false) {
            $this->addAsset('css', 'datatables.css');
            $this->addAsset('js', 'datatables.js');
        }

        // Render view
        $this->renderTemplate($view, $data, $options['layout'] ?? $this->layout);
    }

    /**
     * Render template file
     */
    private function renderTemplate(string $view, array $data, string $layout): void
    {
        // Extract data for template
        extract($data);
        
        // Start output buffering
        ob_start();
        
        try {
            // Include view file
            $viewFile = $this->getViewPath($view);
            if (!file_exists($viewFile)) {
                throw new Exception("View file not found: {$view}");
            }
            
            include $viewFile;
            $content = ob_get_clean();
            
            // Include layout
            $layoutFile = $this->getLayoutPath($layout);
            if (!file_exists($layoutFile)) {
                throw new Exception("Layout file not found: {$layout}");
            }
            
            include $layoutFile;
            
        } catch (Exception $e) {
            ob_end_clean();
            $this->handleViewError($e);
        }
    }

    /**
     * Render JSON response
     */
    protected function renderJson(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Require AJAX request
     */
    protected function requireAjax(): void
    {
        if (!$this->isAjaxRequest()) {
            http_response_code(400);
            echo json_encode(['error' => 'AJAX request required']);
            exit;
        }
    }

    /**
     * Check if request is AJAX
     */
    protected function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Sanitize input data
     */
    protected function sanitizeInput(array $input): array
    {
        return $this->security->sanitizeArray($input);
    }

    /**
     * Validate CSRF token
     */
    protected function validateCsrfToken(): bool
    {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
        return $this->security->validateCsrfToken($token);
    }

    /**
     * Generate CSRF token
     */
    protected function generateCsrfToken(): string
    {
        return $this->security->generateCsrfToken();
    }

    /**
     * Add breadcrumb
     */
    protected function addBreadcrumb(string $title, ?string $url = null): void
    {
        $this->breadcrumbs[] = [
            'title' => $title,
            'url' => $url
        ];
    }

    /**
     * Add CSS/JS asset
     */
    protected function addAsset(string $type, string $asset): void
    {
        if (!in_array($type, ['css', 'js'])) {
            return;
        }
        
        if (!in_array($asset, $this->assets[$type])) {
            $this->assets[$type][] = $asset;
        }
    }

    /**
     * Redirect with success message
     */
    protected function redirectWithSuccess(string $url, string $message): void
    {
        $this->setFlashMessage('success', $message);
        $this->redirect($url);
    }

    /**
     * Redirect with error message
     */
    protected function redirectWithError(string $url, string $message): void
    {
        $this->setFlashMessage('error', $message);
        $this->redirect($url);
    }

    /**
     * Redirect to URL
     */
    protected function redirect(string $url): void
    {
        // Ensure URL is safe
        if (!$this->isValidRedirectUrl($url)) {
            $url = '/';
        }
        
        header("Location: {$url}");
        exit;
    }

    /**
     * Redirect to login page
     */
    protected function redirectToLogin(): void
    {
        $returnUrl = $_SERVER['REQUEST_URI'] ?? '/';
        $this->redirect('/auth/login?return=' . urlencode($returnUrl));
    }

    /**
     * Set flash message
     */
    protected function setFlashMessage(string $type, string $message): void
    {
        if (!isset($_SESSION['flash_messages'])) {
            $_SESSION['flash_messages'] = [];
        }
        
        $_SESSION['flash_messages'][] = [
            'type' => $type,
            'message' => $message,
            'timestamp' => time()
        ];
    }

    /**
     * Get flash messages
     */
    protected function getFlashMessages(): array
    {
        $messages = $_SESSION['flash_messages'] ?? [];
        unset($_SESSION['flash_messages']);
        
        // Remove old messages (older than 5 minutes)
        $messages = array_filter($messages, function($msg) {
            return (time() - $msg['timestamp']) < 300;
        });
        
        return $messages;
    }

    /**
     * Get view file path
     */
    private function getViewPath(string $view): string
    {
        return dirname(__DIR__, 2) . '/src/Views/' . str_replace('.', '/', $view) . '.php';
    }

    /**
     * Get layout file path
     */
    private function getLayoutPath(string $layout): string
    {
        return dirname(__DIR__, 2) . '/src/Views/layouts/' . $layout . '.php';
    }

    /**
     * Handle view rendering errors
     */
    private function handleViewError(Exception $e): void
    {
        $this->logger->error('View rendering error', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        
        if ($this->config->isDebugMode()) {
            echo '<h1>View Error</h1>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        } else {
            http_response_code(500);
            echo '<h1>Server Error</h1><p>An error occurred while rendering the page.</p>';
        }
        
        exit;
    }

    /**
     * Validate redirect URL to prevent open redirects
     */
    private function isValidRedirectUrl(string $url): bool
    {
        // Allow relative URLs
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            return true;
        }
        
        // Allow URLs from same domain
        $baseUrl = $this->config->get('app.base_url');
        if (strpos($url, $baseUrl) === 0) {
            return true;
        }
        
        return false;
    }

    /**
     * Get current URL
     */
    protected function getCurrentUrl(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * Get request method
     */
    protected function getRequestMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Check if request method matches
     */
    protected function isMethod(string $method): bool
    {
        return strtoupper($this->getRequestMethod()) === strtoupper($method);
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
     * Log user action
     */
    protected function logUserAction(string $action, array $data = []): void
    {
        $user = $this->auth->getCurrentUser();
        
        $this->logger->info('User action', [
            'action' => $action,
            'user_id' => $user['user_id'] ?? null,
            'user_email' => $user['email'] ?? null,
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'url' => $this->getCurrentUrl(),
            'data' => $data
        ]);
    }
}
