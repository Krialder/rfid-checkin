<?php
declare(strict_types=1);

namespace RfidCheckin\Services\View;

use RfidCheckin\Core\Session;
use RfidCheckin\Services\ConfigurationService;

/**
 * View Renderer Service
 * 
 * Handles view rendering with:
 * - Template rendering
 * - Layout support
 * - Partial includes
 * - Helper functions
 * - Data escaping
 * - Asset management
 */
class ViewRenderer
{
    private string $viewPath;
    private array $globalData = [];
    private array $sections = [];
    private string $currentSection = '';
    private ?string $layout = null;
    private ConfigurationService $config;
    private Session $session;
    
    public function __construct()
    {
        $this->viewPath = dirname(__DIR__, 2) . '/Views';
        $this->config = ConfigurationService::getInstance();
        $this->session = Session::getInstance();
        $this->setupGlobalData();
    }
    
    /**
     * Render a view template
     */
    public function render(string $template, array $data = [], ?string $layout = 'app'): string
    {
        $this->layout = $layout;
        $data = array_merge($this->globalData, $data);
        
        // Add renderer instance to data
        $data['renderer'] = $this;
        
        // Extract variables
        extract($data, EXTR_SKIP);
        
        // Start output buffering
        ob_start();
        
        // Include the view file
        $viewFile = $this->resolveViewPath($template);
        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View file not found: {$template}");
        }
        
        include $viewFile;
        
        $content = ob_get_clean();
        
        // If layout is specified, render with layout
        if ($this->layout) {
            return $this->renderWithLayout($content, $data);
        }
        
        return $content;
    }
    
    /**
     * Render view with layout
     */
    private function renderWithLayout(string $content, array $data): string
    {
        $data['content'] = $content;
        $data['renderer'] = $this;
        
        extract($data, EXTR_SKIP);
        
        ob_start();
        $layoutFile = $this->viewPath . '/layouts/' . $this->layout . '.php';
        
        if (!file_exists($layoutFile)) {
            return $content; // Return content without layout if not found
        }
        
        include $layoutFile;
        
        return ob_get_clean();
    }
    
    /**
     * Include a partial view
     */
    public function include(string $partial, array $data = []): string
    {
        // Convert dot notation to path
        $partialPath = str_replace('.', '/', $partial);
        $partialFile = $this->viewPath . '/' . $partialPath . '.php';
        
        if (!file_exists($partialFile)) {
            return "<!-- Partial not found: {$partial} -->";
        }
        
        $data = array_merge($this->globalData, $data);
        $data['renderer'] = $this;
        extract($data, EXTR_SKIP);
        
        ob_start();
        include $partialFile;
        return ob_get_clean();
    }
    
    /**
     * Start a section
     */
    public function startSection(string $name): void
    {
        $this->currentSection = $name;
        ob_start();
    }
    
    /**
     * End current section
     */
    public function endSection(): void
    {
        if ($this->currentSection) {
            $this->sections[$this->currentSection] = ob_get_clean();
            $this->currentSection = '';
        }
    }
    
    /**
     * Yield section content
     */
    public function yieldSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }
    
    /**
     * Helper functions accessible in views
     */
    public function helper(string $name, ...$args)
    {
        switch ($name) {
            case 'asset':
                return $this->asset($args[0] ?? '');
                
            case 'url':
                return $this->url($args[0] ?? '/');
                
            case 'route':
                return $this->route($args[0] ?? '', $args[1] ?? []);
                
            case 'e':
            case 'escape':
                return $this->escape($args[0] ?? '');
                
            case 'csrf_token':
                return $this->csrfToken();
                
            case 'csrf_field':
                return $this->csrfField();
                
            case 'old':
                return $this->old($args[0] ?? '', $args[1] ?? '');
                
            case 'user':
                return $this->getCurrentUser();
                
            case 'config':
                return $this->config($args[0] ?? '', $args[1] ?? null);
                
            case 'flash':
                return $this->flash($args[0] ?? '', $args[1] ?? '');
                
            case 'json':
                return json_encode($args[0] ?? null);
                
            case 'number':
                return number_format($args[0] ?? 0);
                
            case 'can':
                return $this->can($args[0] ?? '');
                
            case 'auth':
                return $this->isAuthenticated();
                
            default:
                return null;
        }
    }
    
    /**
     * Get asset URL
     */
    public function asset(string $path): string
    {
        $baseUrl = $this->config->get('app.base_url', '');
        return rtrim($baseUrl, '/') . '/public/assets/' . ltrim($path, '/');
    }
    
    /**
     * Get URL
     */
    public function url(string $path = '/'): string
    {
        $baseUrl = $this->config->get('app.base_url', '');
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }
    
    /**
     * Get route URL
     */
    public function route(string $name, array $params = []): string
    {
        // Placeholder - integrate with router
        return $this->url($name);
    }
    
    /**
     * Escape HTML
     */
    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Get CSRF token
     */
    public function csrfToken(): string
    {
        return $this->session->getCsrfToken();
    }
    
    /**
     * Generate CSRF field
     */
    public function csrfField(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . $this->csrfToken() . '">';
    }
    
    /**
     * Get old input value
     */
    public function old(string $key, string $default = ''): string
    {
        return $this->session->getFlash('old_' . $key, $default);
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->session->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $this->session->getUserId(),
            'name' => $this->session->get('user_name'),
            'username' => $this->session->get('username'),
            'role' => $this->session->getUserRole(),
            'theme' => $this->session->get('theme', 'light')
        ];
    }
    
    /**
     * Get config value
     */
    public function config(string $key, $default = null)
    {
        return $this->config->get($key, $default);
    }
    
    /**
     * Get flash message
     */
    public function flash(string $key, string $default = ''): string
    {
        return $this->session->getFlash($key, $default);
    }
    
    /**
     * Check if user can perform action (basic permission check)
     */
    public function can(string $permission): bool
    {
        $user = $this->getCurrentUser();
        
        if (!$user) {
            return false;
        }
        
        // Admin (group_id 1) can do everything
        if (isset($user['group_id']) && $user['group_id'] == 1) {
            return true;
        }
        
        // Check specific permissions
        $role = $user['role'] ?? 'user';
        
        switch ($permission) {
            case 'admin':
                return $role === 'admin';
            case 'manager':
                return in_array($role, ['admin', 'manager']);
            case 'edit_users':
            case 'delete_users':
                return $role === 'admin';
            case 'create_events':
            case 'edit_events':
                return in_array($role, ['admin', 'manager']);
            default:
                return false;
        }
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        return $this->session->isLoggedIn();
    }
    
    /**
     * Setup global data available in all views
     */
    private function setupGlobalData(): void
    {
        $user = $this->getCurrentUser();
        
        $this->globalData = [
            'app' => [
                'name' => $this->config->get('app.name', 'RFID Check-in'),
                'version' => $this->config->get('app.version', '2.0.0'),
                'environment' => $this->config->get('app.environment', 'production')
            ],
            'app_name' => $this->config->get('app.name', 'RFID Check-in'),
            'app_version' => $this->config->get('app.version', '2.0.0'),
            'environment' => $this->config->get('app.environment', 'production'),
            'current_user' => $user,
            'user' => $user,
            'csrf_token' => $this->csrfToken(),
            'flash_messages' => $this->session->getAllFlash(),
            'current_page' => $this->getCurrentPage()
        ];
    }
    
    /**
     * Get current page identifier
     */
    private function getCurrentPage(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $path = trim($path, '/');
        
        if (empty($path)) {
            return 'home';
        }
        
        // Convert path to page identifier
        return str_replace('/', '-', $path);
    }
    
    /**
     * Resolve view path
     */
    private function resolveViewPath(string $template): string
    {
        // Convert dot notation to path
        $path = str_replace('.', '/', $template);
        return $this->viewPath . '/' . $path . '.php';
    }
    
    /**
     * Set global data
     */
    public function share(string $key, $value): void
    {
        $this->globalData[$key] = $value;
    }
    
    /**
     * Add multiple global data
     */
    public function shareMultiple(array $data): void
    {
        $this->globalData = array_merge($this->globalData, $data);
    }
}
