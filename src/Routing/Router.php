<?php

declare(strict_types=1);

namespace RfidCheckin\Routing;

use RfidCheckin\Middleware\MiddlewareManager;
use RfidCheckin\Services\LoggingService;
use Exception;

/**
 * Router
 * 
 * Handles URL routing, request dispatching, and controller instantiation.
 * Provides clean URL support and RESTful routing patterns.
 * 
 * Features:
 * - RESTful routing patterns
 * - Route parameters and wildcards
 * - Route groups and prefixes
 * - Middleware integration
 * - Named routes and URL generation
 * - Route caching for performance
 * - Custom error handling
 * 
 * @package RfidCheckin\Routing
 * @version 1.0.0
 * @author Senior Development Team
 */
class Router
{
    private LoggingService $logger;
    private MiddlewareManager $middleware;
    
    private array $routes = [];
    private array $namedRoutes = [];
    private array $routeGroups = [];
    private string $currentGroupPrefix = '';
    private array $currentGroupMiddleware = [];
    
    private string $defaultController = 'RfidCheckin\\Controllers\\Frontend\\DashboardController';
    private string $defaultAction = 'index';

    /**
     * Constructor
     */
    public function __construct(MiddlewareManager $middleware = null)
    {
        $this->logger = LoggingService::getInstance();
        $this->middleware = $middleware ?? new MiddlewareManager();
        
        $this->registerDefaultRoutes();
    }

    /**
     * Register default system routes
     */
    private function registerDefaultRoutes(): void
    {
        // Authentication routes
        $this->group(['prefix' => 'auth'], function() {
            $this->get('/login', 'RfidCheckin\\Controllers\\Auth\\LoginController@showLoginForm', 'auth.login');
            $this->post('/login', 'RfidCheckin\\Controllers\\Auth\\LoginController@login', 'auth.login.post');
            $this->post('/logout', 'RfidCheckin\\Controllers\\Auth\\LoginController@logout', 'auth.logout');
            $this->get('/forgot-password', 'RfidCheckin\\Controllers\\Auth\\PasswordController@showForgotForm', 'auth.forgot');
            $this->post('/forgot-password', 'RfidCheckin\\Controllers\\Auth\\PasswordController@sendResetLink', 'auth.forgot.post');
            $this->get('/reset-password', 'RfidCheckin\\Controllers\\Auth\\PasswordController@showResetForm', 'auth.reset');
            $this->post('/reset-password', 'RfidCheckin\\Controllers\\Auth\\PasswordController@resetPassword', 'auth.reset.post');
        });

        // Dashboard routes
        $this->get('/', 'RfidCheckin\\Controllers\\Frontend\\DashboardController@index', 'home');
        $this->get('/dashboard', 'RfidCheckin\\Controllers\\Frontend\\DashboardController@index', 'dashboard');
        $this->get('/dashboard/stats', 'RfidCheckin\\Controllers\\Frontend\\DashboardController@ajaxStats', 'dashboard.stats');
        $this->post('/dashboard/quick-checkin', 'RfidCheckin\\Controllers\\Frontend\\DashboardController@ajaxQuickCheckin', 'dashboard.quick-checkin');

        // User routes
        $this->group(['prefix' => 'user'], function() {
            $this->get('/profile', 'RfidCheckin\\Controllers\\Frontend\\UserController@profile', 'user.profile');
            $this->get('/edit-profile', 'RfidCheckin\\Controllers\\Frontend\\UserController@editProfile', 'user.edit-profile');
            $this->post('/edit-profile', 'RfidCheckin\\Controllers\\Frontend\\UserController@editProfile', 'user.edit-profile.post');
            $this->get('/change-password', 'RfidCheckin\\Controllers\\Frontend\\UserController@changePassword', 'user.change-password');
            $this->post('/change-password', 'RfidCheckin\\Controllers\\Frontend\\UserController@changePassword', 'user.change-password.post');
            $this->get('/rfid-management', 'RfidCheckin\\Controllers\\Frontend\\UserController@rfidManagement', 'user.rfid');
            $this->post('/rfid-management', 'RfidCheckin\\Controllers\\Frontend\\UserController@rfidManagement', 'user.rfid.post');
            $this->get('/checkin-history', 'RfidCheckin\\Controllers\\Frontend\\UserController@checkinHistory', 'user.checkin-history');
            $this->get('/events', 'RfidCheckin\\Controllers\\Frontend\\UserController@events', 'user.events');
            $this->get('/settings', 'RfidCheckin\\Controllers\\Frontend\\UserController@settings', 'user.settings');
            $this->post('/settings', 'RfidCheckin\\Controllers\\Frontend\\UserController@settings', 'user.settings.post');
            $this->get('/security', 'RfidCheckin\\Controllers\\Frontend\\UserController@security', 'user.security');
            $this->post('/security', 'RfidCheckin\\Controllers\\Frontend\\UserController@security', 'user.security.post');
        });

        // Events routes
        $this->group(['prefix' => 'events'], function() {
            $this->get('/', 'RfidCheckin\\Controllers\\Frontend\\EventController@index', 'events.index');
            $this->get('/view', 'RfidCheckin\\Controllers\\Frontend\\EventController@view', 'events.view');
            $this->get('/create', 'RfidCheckin\\Controllers\\Frontend\\EventController@create', 'events.create');
            $this->post('/create', 'RfidCheckin\\Controllers\\Frontend\\EventController@create', 'events.create.post');
            $this->get('/edit', 'RfidCheckin\\Controllers\\Frontend\\EventController@edit', 'events.edit');
            $this->post('/edit', 'RfidCheckin\\Controllers\\Frontend\\EventController@edit', 'events.edit.post');
            $this->get('/checkins', 'RfidCheckin\\Controllers\\Frontend\\EventController@checkins', 'events.checkins');
            $this->get('/analytics', 'RfidCheckin\\Controllers\\Frontend\\EventController@analytics', 'events.analytics');
            $this->post('/register', 'RfidCheckin\\Controllers\\Frontend\\EventController@ajaxRegister', 'events.register');
            $this->post('/manual-checkin', 'RfidCheckin\\Controllers\\Frontend\\EventController@ajaxManualCheckin', 'events.manual-checkin');
            $this->delete('/delete', 'RfidCheckin\\Controllers\\Frontend\\EventController@ajaxDelete', 'events.delete');
        });

        // Admin routes
        $this->group(['prefix' => 'admin'], function() {
            $this->get('/', 'RfidCheckin\\Controllers\\Frontend\\AdminController@index', 'admin.index');
            $this->get('/users', 'RfidCheckin\\Controllers\\Frontend\\AdminController@users', 'admin.users');
            $this->get('/users/create', 'RfidCheckin\\Controllers\\Frontend\\AdminController@userForm', 'admin.users.create');
            $this->post('/users/create', 'RfidCheckin\\Controllers\\Frontend\\AdminController@userForm', 'admin.users.create.post');
            $this->get('/users/edit', 'RfidCheckin\\Controllers\\Frontend\\AdminController@userForm', 'admin.users.edit');
            $this->post('/users/edit', 'RfidCheckin\\Controllers\\Frontend\\AdminController@userForm', 'admin.users.edit.post');
            $this->get('/events', 'RfidCheckin\\Controllers\\Frontend\\AdminController@events', 'admin.events');
            $this->get('/rfid-devices', 'RfidCheckin\\Controllers\\Frontend\\AdminController@rfidDevices', 'admin.rfid-devices');
            $this->get('/reports', 'RfidCheckin\\Controllers\\Frontend\\AdminController@reports', 'admin.reports');
            $this->get('/security-logs', 'RfidCheckin\\Controllers\\Frontend\\AdminController@securityLogs', 'admin.security-logs');
            $this->get('/settings', 'RfidCheckin\\Controllers\\Frontend\\AdminController@settings', 'admin.settings');
            $this->post('/settings', 'RfidCheckin\\Controllers\\Frontend\\AdminController@settings', 'admin.settings.post');
            $this->get('/database', 'RfidCheckin\\Controllers\\Frontend\\AdminController@database', 'admin.database');
            $this->get('/stats', 'RfidCheckin\\Controllers\\Frontend\\AdminController@ajaxSystemStats', 'admin.stats');
            $this->delete('/users/delete', 'RfidCheckin\\Controllers\\Frontend\\AdminController@ajaxDeleteUser', 'admin.users.delete');
        });

        // API routes
        $this->group(['prefix' => 'api'], function() {
            $this->get('/dashboard', 'RfidCheckin\\Controllers\\Api\\DashboardApiController@getDashboardData', 'api.dashboard');
            $this->post('/rfid-checkin', 'RfidCheckin\\Controllers\\Api\\RfidApiController@processCheckin', 'api.rfid.checkin');
            $this->get('/rfid-poll', 'RfidCheckin\\Controllers\\Api\\RfidApiController@pollForTags', 'api.rfid.poll');
            $this->get('/rfid-poll-noauth', 'RfidCheckin\\Controllers\\Api\\RfidApiController@pollForTagsNoAuth', 'api.rfid.poll-noauth');
            $this->post('/rfid-test', 'RfidCheckin\\Controllers\\Api\\RfidApiController@testDevice', 'api.rfid.test');
            $this->get('/rfid-queue', 'RfidCheckin\\Controllers\\Api\\RfidApiController@getQueue', 'api.rfid.queue');
            
            $this->get('/events', 'RfidCheckin\\Controllers\\Api\\EventsApiController@getEvents', 'api.events.list');
            $this->post('/events', 'RfidCheckin\\Controllers\\Api\\EventsApiController@createEvent', 'api.events.create');
            $this->get('/events/{id}', 'RfidCheckin\\Controllers\\Api\\EventsApiController@getEvent', 'api.events.show');
            $this->put('/events/{id}', 'RfidCheckin\\Controllers\\Api\\EventsApiController@updateEvent', 'api.events.update');
            $this->delete('/events/{id}', 'RfidCheckin\\Controllers\\Api\\EventsApiController@deleteEvent', 'api.events.delete');
            $this->get('/event-details', 'RfidCheckin\\Controllers\\Api\\EventsApiController@getEventDetails', 'api.event-details');
            
            $this->get('/users', 'RfidCheckin\\Controllers\\Api\\UsersApiController@getUsers', 'api.users.list');
            $this->post('/users', 'RfidCheckin\\Controllers\\Api\\UsersApiController@createUser', 'api.users.create');
            $this->get('/users/{id}', 'RfidCheckin\\Controllers\\Api\\UsersApiController@getUser', 'api.users.show');
            $this->put('/users/{id}', 'RfidCheckin\\Controllers\\Api\\UsersApiController@updateUser', 'api.users.update');
            $this->delete('/users/{id}', 'RfidCheckin\\Controllers\\Api\\UsersApiController@deleteUser', 'api.users.delete');
            
            $this->get('/analytics', 'RfidCheckin\\Controllers\\Api\\AnalyticsApiController@getAnalytics', 'api.analytics');
            $this->post('/manual-checkin', 'RfidCheckin\\Controllers\\Api\\CheckinApiController@manualCheckin', 'api.manual-checkin');
            $this->get('/registration-mode', 'RfidCheckin\\Controllers\\Api\\RegistrationApiController@getMode', 'api.registration.mode');
            $this->post('/registration-mode', 'RfidCheckin\\Controllers\\Api\\RegistrationApiController@setMode', 'api.registration.mode.set');
        });

        // Error routes
        $this->get('/error/403', 'RfidCheckin\\Controllers\\ErrorController@forbidden', 'error.403');
        $this->get('/error/404', 'RfidCheckin\\Controllers\\ErrorController@notFound', 'error.404');
        $this->get('/error/500', 'RfidCheckin\\Controllers\\ErrorController@serverError', 'error.500');
    }

    /**
     * Add GET route
     */
    public function get(string $path, string $handler, string $name = null): void
    {
        $this->addRoute('GET', $path, $handler, $name);
    }

    /**
     * Add POST route
     */
    public function post(string $path, string $handler, string $name = null): void
    {
        $this->addRoute('POST', $path, $handler, $name);
    }

    /**
     * Add PUT route
     */
    public function put(string $path, string $handler, string $name = null): void
    {
        $this->addRoute('PUT', $path, $handler, $name);
    }

    /**
     * Add PATCH route
     */
    public function patch(string $path, string $handler, string $name = null): void
    {
        $this->addRoute('PATCH', $path, $handler, $name);
    }

    /**
     * Add DELETE route
     */
    public function delete(string $path, string $handler, string $name = null): void
    {
        $this->addRoute('DELETE', $path, $handler, $name);
    }

    /**
     * Add route with any HTTP method
     */
    public function any(string $path, string $handler, string $name = null): void
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        foreach ($methods as $method) {
            $this->addRoute($method, $path, $handler, $name);
        }
    }

    /**
     * Add route to collection
     */
    private function addRoute(string $method, string $path, string $handler, string $name = null): void
    {
        // Apply current group prefix
        $fullPath = $this->currentGroupPrefix . $path;
        
        $route = [
            'method' => $method,
            'path' => $fullPath,
            'handler' => $handler,
            'middleware' => $this->currentGroupMiddleware,
            'name' => $name,
            'parameters' => $this->extractParameters($fullPath)
        ];
        
        $this->routes[] = $route;
        
        // Store named route
        if ($name) {
            $this->namedRoutes[$name] = $route;
        }
    }

    /**
     * Create route group
     */
    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->currentGroupPrefix;
        $previousMiddleware = $this->currentGroupMiddleware;
        
        // Apply group attributes
        if (isset($attributes['prefix'])) {
            $this->currentGroupPrefix = $previousPrefix . '/' . trim($attributes['prefix'], '/');
        }
        
        if (isset($attributes['middleware'])) {
            $this->currentGroupMiddleware = array_merge(
                $previousMiddleware, 
                (array)$attributes['middleware']
            );
        }
        
        // Execute callback to register routes
        $callback();
        
        // Restore previous state
        $this->currentGroupPrefix = $previousPrefix;
        $this->currentGroupMiddleware = $previousMiddleware;
    }

    /**
     * Dispatch request to appropriate handler
     */
    public function dispatch(): void
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            $path = parse_url($uri, PHP_URL_PATH);
            
            // Clean path
            $path = '/' . trim($path, '/');
            if ($path === '/') {
                $path = '/';
            }

            // Find matching route
            $route = $this->findRoute($method, $path);
            
            if (!$route) {
                $this->handleNotFound($path);
                return;
            }

            // Extract route parameters
            $parameters = $this->extractRouteParameters($route, $path);
            
            // Process through middleware
            $this->middleware->process(function() use ($route, $parameters) {
                return $this->executeRoute($route, $parameters);
            });

        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Find matching route
     */
    private function findRoute(string $method, string $path): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $this->matchesPath($route['path'], $path)) {
                return $route;
            }
        }
        
        return null;
    }

    /**
     * Check if route path matches request path
     */
    private function matchesPath(string $routePath, string $requestPath): bool
    {
        // Exact match
        if ($routePath === $requestPath) {
            return true;
        }
        
        // Parameter matching
        $routeSegments = explode('/', trim($routePath, '/'));
        $requestSegments = explode('/', trim($requestPath, '/'));
        
        if (count($routeSegments) !== count($requestSegments)) {
            return false;
        }
        
        for ($i = 0; $i < count($routeSegments); $i++) {
            $routeSegment = $routeSegments[$i];
            $requestSegment = $requestSegments[$i];
            
            // Parameter segment (starts with {)
            if (strpos($routeSegment, '{') === 0 && strpos($routeSegment, '}') === strlen($routeSegment) - 1) {
                continue; // Parameters match anything
            }
            
            // Exact segment match required
            if ($routeSegment !== $requestSegment) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Extract parameters from route path
     */
    private function extractParameters(string $path): array
    {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Extract route parameter values
     */
    private function extractRouteParameters(array $route, string $path): array
    {
        $parameters = [];
        
        $routeSegments = explode('/', trim($route['path'], '/'));
        $pathSegments = explode('/', trim($path, '/'));
        
        for ($i = 0; $i < count($routeSegments); $i++) {
            $routeSegment = $routeSegments[$i];
            
            if (strpos($routeSegment, '{') === 0 && strpos($routeSegment, '}') === strlen($routeSegment) - 1) {
                $paramName = substr($routeSegment, 1, -1);
                $parameters[$paramName] = $pathSegments[$i] ?? null;
            }
        }
        
        return $parameters;
    }

    /**
     * Execute route handler
     */
    private function executeRoute(array $route, array $parameters): void
    {
        $handler = $route['handler'];
        
        // Parse handler (Controller@method format)
        if (strpos($handler, '@') !== false) {
            [$controllerClass, $method] = explode('@', $handler, 2);
        } else {
            $controllerClass = $handler;
            $method = $this->defaultAction;
        }
        
        // Instantiate controller
        if (!class_exists($controllerClass)) {
            throw new Exception("Controller class not found: {$controllerClass}");
        }
        
        $controller = new $controllerClass();
        
        if (!method_exists($controller, $method)) {
            throw new Exception("Method {$method} not found in {$controllerClass}");
        }
        
        // Set route parameters in controller if method exists
        if (method_exists($controller, 'setRouteParameters')) {
            $controller->setRouteParameters($parameters);
        }
        
        // Log route execution
        $this->logger->debug('Executing route', [
            'method' => $route['method'],
            'path' => $route['path'],
            'controller' => $controllerClass,
            'action' => $method,
            'parameters' => $parameters
        ]);
        
        // Execute controller method
        call_user_func([$controller, $method]);
    }

    /**
     * Handle 404 Not Found
     */
    private function handleNotFound(string $path): void
    {
        $this->logger->warning('Route not found', [
            'path' => $path,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        http_response_code(404);
        
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Route not found',
                'code' => 'NOT_FOUND'
            ]);
        } else {
            // Try to find 404 error controller
            try {
                $errorController = new \RfidCheckin\Controllers\ErrorController();
                $errorController->notFound();
            } catch (Exception $e) {
                // Fallback 404 page
                echo '<!DOCTYPE html>
<html><head><title>404 - Page Not Found</title></head>
<body>
<h1>404 - Page Not Found</h1>
<p>The requested page could not be found.</p>
<a href="/">Return to Home</a>
</body></html>';
            }
        }
    }

    /**
     * Handle exceptions
     */
    private function handleException(Exception $e): void
    {
        $this->logger->error('Route execution error', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        http_response_code(500);
        
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error',
                'code' => 'INTERNAL_ERROR'
            ]);
        } else {
            // Try to find 500 error controller
            try {
                $errorController = new \RfidCheckin\Controllers\ErrorController();
                $errorController->serverError();
            } catch (Exception $fallbackError) {
                // Fallback 500 page
                echo '<!DOCTYPE html>
<html><head><title>500 - Server Error</title></head>
<body>
<h1>500 - Internal Server Error</h1>
<p>An unexpected error occurred.</p>
<a href="/">Return to Home</a>
</body></html>';
            }
        }
    }

    /**
     * Generate URL for named route
     */
    public function url(string $name, array $parameters = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new Exception("Named route not found: {$name}");
        }
        
        $route = $this->namedRoutes[$name];
        $path = $route['path'];
        
        // Replace parameters in path
        foreach ($parameters as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
        }
        
        return $path;
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
     * Get all registered routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Get named routes
     */
    public function getNamedRoutes(): array
    {
        return $this->namedRoutes;
    }

    /**
     * Clear all routes
     */
    public function clearRoutes(): void
    {
        $this->routes = [];
        $this->namedRoutes = [];
    }
}
