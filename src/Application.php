<?php

declare(strict_types=1);

namespace RfidCheckin;

// Load autoloader if not already loaded
if (!class_exists('RfidCheckin\Services\ConfigurationService')) {
    require_once __DIR__ . '/autoload.php';
}

use RfidCheckin\Services\ConfigurationService;
use RfidCheckin\Services\DatabaseService;
use RfidCheckin\Services\LoggingService;
use RfidCheckin\Services\ErrorHandler;
use RfidCheckin\Middleware\MiddlewareManager;
use RfidCheckin\Routing\Router;
use Exception;

/**
 * Application Bootstrap
 * 
 * Main application class that initializes services, configures routing,
 * and handles the request/response lifecycle.
 * 
 * @package RfidCheckin
 * @author Kralder
 */
class Application
{
    private ConfigurationService $config;
    private DatabaseService $database;
    private LoggingService $logger;
    private ErrorHandler $errorHandler;
    private MiddlewareManager $middleware;
    private Router $router;
    
    private bool $initialized = false;
    private float $startTime;

    /**
     * Constructor
     */
    public function __construct(string $configPath = null)
    {
        $this->startTime = microtime(true);
        
        try {
            $this->initializeConfiguration($configPath);
            $this->initializeErrorHandling();
            $this->initializeServices();
            $this->initializeMiddleware();
            $this->initializeRouting();
            
            $this->initialized = true;
            
            $this->logger->info('Application initialized', [
                'initialization_time' => microtime(true) - $this->startTime,
                'environment' => $this->config->getEnvironment(),
                'php_version' => PHP_VERSION
            ]);
            
        } catch (Exception $e) {
            $this->handleInitializationError($e);
        }
    }

    /**
     * Initialize configuration service
     */
    private function initializeConfiguration(string $configPath = null): void
    {
        $configPath = $configPath ?? dirname(__DIR__) . '/config/config.php';
        $this->config = ConfigurationService::getInstance($configPath);
        
        // Set error reporting based on environment
        if ($this->config->isDebugMode()) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
            ini_set('display_errors', '0');
        }
        
        // Set timezone
        $timezone = $this->config->get('app.timezone', 'UTC');
        date_default_timezone_set($timezone);
        
        // Set memory and execution limits
        $memoryLimit = $this->config->get('app.memory_limit', '256M');
        $executionLimit = $this->config->get('app.execution_limit', 30);
        
        ini_set('memory_limit', $memoryLimit);
        set_time_limit($executionLimit);
    }

    /**
     * Initialize error handling
     */
    private function initializeErrorHandling(): void
    {
        $this->errorHandler = new ErrorHandler($this->config);
        $this->errorHandler->register();
    }

    /**
     * Initialize core services
     */
    private function initializeServices(): void
    {
        // Initialize logging first
        $this->logger = LoggingService::getInstance();
        
        // Initialize database
        $this->database = DatabaseService::getInstance();
        
        // Test database connection
        if (!$this->database->testConnection()) {
            throw new Exception('Database connection failed');
        }
        
        // Initialize other services as singletons
        // They will be available throughout the application
    }

    /**
     * Initialize middleware system
     */
    private function initializeMiddleware(): void
    {
        $middlewareConfig = $this->config->get('middleware', []);
        $this->middleware = MiddlewareManager::createWithConfig($middlewareConfig);
    }

    /**
     * Initialize routing system
     */
    private function initializeRouting(): void
    {
        $this->router = new Router($this->middleware);
        
        // Load routes from routes.php file
        $this->router->loadRoutes();
    }

    /**
     * Run the application
     */
    public function run(): void
    {
        if (!$this->initialized) {
            throw new Exception('Application not properly initialized');
        }

        try {
            error_log("APPLICATION: Starting run() method");
            
            // Start session
            $this->startSession();
            error_log("APPLICATION: Session started");
            
            // Log request
            $this->logRequest();
            error_log("APPLICATION: Request logged, calling router dispatch");
            
            // Dispatch request through router
            $this->router->dispatch();
            error_log("APPLICATION: Router dispatch completed");
            
            // Log response
            $this->logResponse();
            
        } catch (Exception $e) {
            error_log("APPLICATION: Exception in run(): " . $e->getMessage());
            $this->handleApplicationError($e);
        }
    }

    /**
     * Start secure session
     */
    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Configure session security
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', $this->config->get('app.https_only', false) ? '1' : '0');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', '1');
            
            // Set session name and lifetime
            session_name($this->config->get('session.name', 'RFID_SESSION'));
            session_set_cookie_params([
                'lifetime' => $this->config->get('session.lifetime', 7200),
                'path' => '/',
                'domain' => $this->config->get('session.domain', ''),
                'secure' => $this->config->get('app.https_only', false),
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            
            session_start();
        }
    }

    /**
     * Log incoming request
     */
    private function logRequest(): void
    {
        $this->logger->info('Request received', [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'uri' => $_SERVER['REQUEST_URI'] ?? '/',
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
    }

    /**
     * Log response
     */
    private function logResponse(): void
    {
        $responseCode = http_response_code();
        $executionTime = microtime(true) - $this->startTime;
        $memoryUsage = memory_get_peak_usage(true);
        
        $logData = [
            'response_code' => $responseCode,
            'execution_time' => round($executionTime, 4),
            'memory_usage' => round($memoryUsage / 1024 / 1024, 2) . 'MB',
            'queries_executed' => $this->database->getQueryCount()
        ];
        
        if ($responseCode >= 400) {
            $this->logger->warning('Request completed', $logData);
        } else {
            $this->logger->info('Request completed', $logData);
        }
    }

    /**
     * Handle initialization errors
     */
    private function handleInitializationError(Exception $e): void
    {
        // Log to system log if logger not available
        error_log("Application initialization failed: " . $e->getMessage());
        
        http_response_code(500);
        
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Application initialization failed',
                'code' => 'INIT_ERROR'
            ]);
        } else {
            echo '<!DOCTYPE html>
<html>
<head>
    <title>System Error</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f8f9fa; }
        .error-container { 
            max-width: 600px; margin: 100px auto; background: white;
            padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .error-icon { font-size: 64px; color: #dc3545; margin-bottom: 20px; }
        h1 { color: #dc3545; margin-bottom: 20px; }
        .error-message { color: #6c757d; margin-bottom: 30px; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h1>System Initialization Error</h1>
        <p class="error-message">
            The application could not be started due to a system error. 
            Please contact the administrator.
        </p>
        <p><small>Error ID: ' . uniqid() . '</small></p>
    </div>
</body>
</html>';
        }
        
        exit;
    }

    /**
     * Handle application runtime errors
     */
    private function handleApplicationError(Exception $e): void
    {
        $this->logger->error('Application error', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'uri' => $_SERVER['REQUEST_URI'] ?? '/',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET'
        ]);

        http_response_code(500);
        
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $this->config->isDebugMode() ? $e->getMessage() : 'Internal server error',
                'code' => 'APP_ERROR'
            ]);
        } else {
            if ($this->config->isDebugMode()) {
                // Show detailed error in debug mode
                echo '<h1>Application Error</h1>';
                echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
                echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
                echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            } else {
                // Generic error page in production
                echo '<!DOCTYPE html>
<html>
<head><title>Server Error</title></head>
<body>
<h1>500 - Internal Server Error</h1>
<p>An unexpected error occurred. Please try again later.</p>
<a href="/">Return to Home</a>
</body>
</html>';
            }
        }
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
     * Check if request is AJAX
     */
    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Get configuration service
     */
    public function getConfig(): ConfigurationService
    {
        return $this->config;
    }

    /**
     * Get database service
     */
    public function getDatabase(): DatabaseService
    {
        return $this->database;
    }

    /**
     * Get logger service
     */
    public function getLogger(): LoggingService
    {
        return $this->logger;
    }

    /**
     * Get router
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Get middleware manager
     */
    public function getMiddleware(): MiddlewareManager
    {
        return $this->middleware;
    }

    /**
     * Check if application is initialized
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Get application uptime
     */
    public function getUptime(): float
    {
        return microtime(true) - $this->startTime;
    }

    /**
     * Shutdown application gracefully
     */
    public function shutdown(): void
    {
        $this->logger->info('Application shutdown', [
            'uptime' => $this->getUptime(),
            'peak_memory' => memory_get_peak_usage(true)
        ]);
        
        // Close database connections
        $this->database->closeConnection();
    }
}
