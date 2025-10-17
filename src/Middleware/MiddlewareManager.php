<?php

declare(strict_types=1);

namespace RfidCheckin\Middleware;

use RfidCheckin\Services\LoggingService;
use Exception;

/**
 * Middleware Manager
 * 
 * Manages the middleware pipeline for request processing.
 * Handles middleware registration, execution order, and error handling.
 * 
 * Features:
 * - Middleware registration and ordering
 * - Route-specific middleware
 * - Global middleware application
 * - Error handling and recovery
 * - Performance monitoring
 * - Conditional middleware execution
 * 
 * @package RfidCheckin\Middleware
 * @version 1.0.0
 * @author Senior Development Team
 */
class MiddlewareManager
{
    private LoggingService $logger;
    private array $globalMiddleware = [];
    private array $routeMiddleware = [];
    private array $middlewareAliases = [];
    private array $excludedRoutes = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = LoggingService::getInstance();
        $this->registerDefaultMiddleware();
    }

    /**
     * Register default middleware and aliases
     */
    private function registerDefaultMiddleware(): void
    {
        // Register middleware aliases for easier configuration
        $this->middlewareAliases = [
            'auth' => SimpleAuthMiddleware::class,
            'authorize' => SimpleAuthorizationMiddleware::class,
            'csrf' => SimpleCsrfMiddleware::class,
            'rate-limit' => SimpleRateLimitMiddleware::class
        ];

        // Define global middleware stack (order matters!)
        $this->globalMiddleware = [
            'rate-limit',
            'csrf',
            'auth'
        ];

        // Define route-specific middleware
        $this->routeMiddleware = [
            '/admin/*' => ['auth', 'authorize'],
            '/api/admin/*' => ['auth', 'authorize'],
            '/user/*' => ['auth'],
            '/events/create' => ['auth', 'authorize'],
            '/events/edit' => ['auth', 'authorize'],
            '/events/delete' => ['auth', 'authorize']
        ];

        // Routes that should exclude certain middleware
        $this->excludedRoutes = [
            '/auth/login' => ['auth'],
            '/auth/register' => ['auth'],
            '/auth/forgot-password' => ['auth'],
            '/auth/reset-password' => ['auth'],
            '/api/rfid-poll-noauth' => ['auth', 'csrf'],
            '/assets/*' => ['auth', 'csrf', 'rate-limit'],
            '/css/*' => ['auth', 'csrf', 'rate-limit'],
            '/js/*' => ['auth', 'csrf', 'rate-limit'],
            '/images/*' => ['auth', 'csrf', 'rate-limit']
        ];
    }

    /**
     * Process request through middleware pipeline
     */
    public function process(callable $finalHandler): void
    {
        try {
            $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            
            // Get middleware stack for this request
            $middlewareStack = $this->buildMiddlewareStack($requestPath);
            
            // Log middleware execution
            $this->logger->debug('Processing middleware stack', [
                'path' => $requestPath,
                'middleware' => $middlewareStack
            ]);

            // Execute middleware pipeline
            $this->executeMiddlewareStack($middlewareStack, $finalHandler);

        } catch (Exception $e) {
            $this->handleMiddlewareError($e);
        }
    }

    /**
     * Build middleware stack for specific route
     */
    private function buildMiddlewareStack(string $path): array
    {
        $stack = [];
        
        // Start with global middleware
        foreach ($this->globalMiddleware as $middleware) {
            if (!$this->isMiddlewareExcluded($path, $middleware)) {
                $stack[] = $middleware;
            }
        }
        
        // Add route-specific middleware
        $routeMiddleware = $this->getRouteMiddleware($path);
        foreach ($routeMiddleware as $middleware) {
            if (!in_array($middleware, $stack) && !$this->isMiddlewareExcluded($path, $middleware)) {
                $stack[] = $middleware;
            }
        }
        
        return $stack;
    }

    /**
     * Get route-specific middleware
     */
    private function getRouteMiddleware(string $path): array
    {
        $middleware = [];
        
        foreach ($this->routeMiddleware as $pattern => $middlewareList) {
            if ($this->matchesRoutePattern($path, $pattern)) {
                $middleware = array_merge($middleware, $middlewareList);
            }
        }
        
        return array_unique($middleware);
    }

    /**
     * Check if middleware is excluded for route
     */
    private function isMiddlewareExcluded(string $path, string $middleware): bool
    {
        foreach ($this->excludedRoutes as $pattern => $excludedMiddleware) {
            if ($this->matchesRoutePattern($path, $pattern)) {
                return in_array($middleware, $excludedMiddleware);
            }
        }
        
        return false;
    }

    /**
     * Check if path matches route pattern
     */
    private function matchesRoutePattern(string $path, string $pattern): bool
    {
        // Convert pattern to regex
        $regex = str_replace(['*', '?'], ['.*', '.'], preg_quote($pattern, '/'));
        return preg_match('/^' . $regex . '$/', $path) === 1;
    }

    /**
     * Execute middleware stack
     */
    private function executeMiddlewareStack(array $middlewareStack, callable $finalHandler): void
    {
        $index = 0;
        
        $next = function() use (&$middlewareStack, &$index, &$next, $finalHandler) {
            if ($index >= count($middlewareStack)) {
                // All middleware processed, call final handler
                return $finalHandler();
            }
            
            $middlewareAlias = $middlewareStack[$index++];
            $middleware = $this->createMiddleware($middlewareAlias);
            
            if ($middleware) {
                return $middleware->handle($next);
            } else {
                // Skip invalid middleware and continue
                return $next();
            }
        };
        
        $next();
    }

    /**
     * Create middleware instance
     */
    private function createMiddleware(string $alias): ?MiddlewareInterface
    {
        try {
            $className = $this->middlewareAliases[$alias] ?? $alias;
            
            if (!class_exists($className)) {
                $this->logger->error('Middleware class not found', [
                    'alias' => $alias,
                    'class' => $className
                ]);
                return null;
            }
            
            $middleware = new $className();
            
            if (!$middleware instanceof MiddlewareInterface) {
                $this->logger->error('Invalid middleware class', [
                    'alias' => $alias,
                    'class' => $className
                ]);
                return null;
            }
            
            return $middleware;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to create middleware', [
                'alias' => $alias,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Handle middleware errors
     */
    private function handleMiddlewareError(Exception $e): void
    {
        $this->logger->error('Middleware pipeline error', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        // Return appropriate error response
        http_response_code(500);
        
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error',
                'code' => 'MIDDLEWARE_ERROR'
            ]);
        } else {
            echo '<!DOCTYPE html>
<html>
<head>
    <title>Server Error</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .error-container { max-width: 600px; }
        .error-code { color: #dc3545; font-size: 48px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">500</div>
        <h1>Internal Server Error</h1>
        <p>An error occurred while processing your request. Please try again later.</p>
    </div>
</body>
</html>';
        }
        
        exit;
    }

    /**
     * Register middleware alias
     */
    public function registerMiddleware(string $alias, string $className): void
    {
        $this->middlewareAliases[$alias] = $className;
    }

    /**
     * Add global middleware
     */
    public function addGlobalMiddleware(string $middleware): void
    {
        if (!in_array($middleware, $this->globalMiddleware)) {
            $this->globalMiddleware[] = $middleware;
        }
    }

    /**
     * Remove global middleware
     */
    public function removeGlobalMiddleware(string $middleware): void
    {
        $this->globalMiddleware = array_filter($this->globalMiddleware, function($m) use ($middleware) {
            return $m !== $middleware;
        });
    }

    /**
     * Add route-specific middleware
     */
    public function addRouteMiddleware(string $pattern, array $middleware): void
    {
        if (!isset($this->routeMiddleware[$pattern])) {
            $this->routeMiddleware[$pattern] = [];
        }
        
        $this->routeMiddleware[$pattern] = array_unique(
            array_merge($this->routeMiddleware[$pattern], $middleware)
        );
    }

    /**
     * Remove route middleware
     */
    public function removeRouteMiddleware(string $pattern): void
    {
        unset($this->routeMiddleware[$pattern]);
    }

    /**
     * Exclude middleware for specific routes
     */
    public function excludeMiddleware(string $pattern, array $middleware): void
    {
        if (!isset($this->excludedRoutes[$pattern])) {
            $this->excludedRoutes[$pattern] = [];
        }
        
        $this->excludedRoutes[$pattern] = array_unique(
            array_merge($this->excludedRoutes[$pattern], $middleware)
        );
    }

    /**
     * Get middleware configuration
     */
    public function getConfiguration(): array
    {
        return [
            'global_middleware' => $this->globalMiddleware,
            'route_middleware' => $this->routeMiddleware,
            'excluded_routes' => $this->excludedRoutes,
            'aliases' => $this->middlewareAliases
        ];
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
     * Create middleware manager with custom configuration
     */
    public static function createWithConfig(array $config): self
    {
        $manager = new self();
        
        if (isset($config['global_middleware'])) {
            $manager->globalMiddleware = $config['global_middleware'];
        }
        
        if (isset($config['route_middleware'])) {
            $manager->routeMiddleware = array_merge($manager->routeMiddleware, $config['route_middleware']);
        }
        
        if (isset($config['excluded_routes'])) {
            $manager->excludedRoutes = array_merge($manager->excludedRoutes, $config['excluded_routes']);
        }
        
        if (isset($config['aliases'])) {
            $manager->middlewareAliases = array_merge($manager->middlewareAliases, $config['aliases']);
        }
        
        return $manager;
    }

    /**
     * Profile middleware performance
     */
    public function profileMiddleware(callable $finalHandler): array
    {
        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $middlewareStack = $this->buildMiddlewareStack($requestPath);
        
        $profile = [];
        $totalStart = microtime(true);
        
        $index = 0;
        $next = function() use (&$middlewareStack, &$index, &$next, $finalHandler, &$profile) {
            if ($index >= count($middlewareStack)) {
                $start = microtime(true);
                $result = $finalHandler();
                $profile['final_handler'] = microtime(true) - $start;
                return $result;
            }
            
            $middlewareAlias = $middlewareStack[$index++];
            $middleware = $this->createMiddleware($middlewareAlias);
            
            if ($middleware) {
                $start = microtime(true);
                $result = $middleware->handle($next);
                $profile[$middlewareAlias] = microtime(true) - $start;
                return $result;
            } else {
                return $next();
            }
        };
        
        $next();
        
        $profile['total_time'] = microtime(true) - $totalStart;
        $profile['middleware_stack'] = $middlewareStack;
        
        return $profile;
    }
}
