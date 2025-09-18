<?php
/**
 * Centralized Error Handler
 * 
 * Handles error logging, user-friendly error displays, and debugging.
 * Provides environment-aware error reporting with sanitized output.
 * 
 * @package RfidCheckin\Core
 * @author Kralder
 */

class ErrorHandler
{
    private static $instance = null;
    private $logFile;
    private $isDevelopment;
    
    /**
     * Singleton pattern implementation
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize error handler
     */
    private function __construct()
    {
        $this->logFile = __DIR__ . '/../logs/system.log';
        $this->isDevelopment = (getenv('APP_ENV') === 'development') || 
                              (defined('APP_ENV') && APP_ENV === 'development');
        
        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Set up error handlers
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }
    
    /**
     * Handle PHP errors
     */
    public function handleError($severity, $message, $file, $line, $context = []): bool
    {
        // Don't handle suppressed errors
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $errorData = [
            'type' => 'PHP Error',
            'severity' => $this->getSeverityName($severity),
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'context' => $this->sanitizeContext($context),
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $this->getCurrentUserId(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
        
        $this->logError($errorData);
        
        // Only display error in development
        if ($this->isDevelopment && $severity & (E_ERROR | E_WARNING | E_PARSE)) {
            $this->displayDevelopmentError($errorData);
        }
        
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public function handleException(Throwable $exception): void
    {
        $errorData = [
            'type' => 'Exception',
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $this->sanitizeTrace($exception->getTrace()),
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $this->getCurrentUserId(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
        
        $this->logError($errorData);
        
        // Handle different response types
        if ($this->isAjaxRequest()) {
            $this->sendJsonErrorResponse($exception);
        } else {
            $this->displayErrorPage($exception);
        }
    }
    
    /**
     * Handle fatal errors during shutdown
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $errorData = [
                'type' => 'Fatal Error',
                'severity' => $this->getSeverityName($error['type']),
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'timestamp' => date('Y-m-d H:i:s'),
                'user_id' => $this->getCurrentUserId(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ];
            
            $this->logError($errorData);
            
            if (!headers_sent()) {
                if ($this->isAjaxRequest()) {
                    http_response_code(500);
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'error' => 'A system error occurred. Please try again.',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    $this->displayFatalErrorPage();
                }
            }
        }
    }
    
    /**
     * Log application-specific errors
     */
    public function logApplicationError(string $message, array $context = [], string $level = 'ERROR'): void
    {
        $errorData = [
            'type' => 'Application Error',
            'level' => $level,
            'message' => $message,
            'context' => $this->sanitizeContext($context),
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $this->getCurrentUserId(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
        
        $this->logError($errorData);
    }
    
    /**
     * Log database-specific errors
     */
    public function logDatabaseError(string $query, string $error, array $params = []): void
    {
        $errorData = [
            'type' => 'Database Error',
            'query' => $query,
            'error' => $error,
            'params' => $this->sanitizeContext($params),
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $this->getCurrentUserId(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
        
        $this->logError($errorData);
    }
    
    /**
     * Write error to log file
     */
    private function logError(array $errorData): void
    {
        $logEntry = date('Y-m-d H:i:s') . ' ' . json_encode($errorData, JSON_UNESCAPED_SLASHES) . PHP_EOL;
        
        // Attempt to write to log file
        if (!@file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX)) {
            // Fallback to system error log
            error_log("Failed to write to application log: " . json_encode($errorData));
        }
    }
    
    /**
     * Public method to log errors from other classes
     * 
     * @param string $message Error message
     * @param Exception|Throwable $exception Exception object
     * @param string $level Error level (ERROR, WARNING, INFO)
     */
    public function log(string $message, $exception = null, string $level = 'ERROR'): void
    {
        $errorData = [
            'level' => $level,
            'message' => $message,
            'timestamp' => date('c'),
            'request_id' => uniqid('req_'),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI'
        ];
        
        if ($exception) {
            $errorData['exception'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }
        
        $this->logError($errorData);
    }
    
    /**
     * Display error page for web requests
     */
    private function displayErrorPage(Throwable $exception): void
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }
        
        $isDev = $this->isDevelopment;
        $errorId = uniqid('err_');
        
        // Include error page template
        include __DIR__ . '/error-pages/500.php';
    }
    
    /**
     * Display fatal error page
     */
    private function displayFatalErrorPage(): void
    {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        
        $errorId = uniqid('err_');
        
        include __DIR__ . '/error-pages/fatal.php';
    }
    
    /**
     * Send JSON error response for AJAX requests
     */
    private function sendJsonErrorResponse(Throwable $exception): void
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        
        $response = [
            'success' => false,
            'error' => 'An unexpected error occurred. Please try again.',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Add debug info in development
        if ($this->isDevelopment) {
            $response['debug'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }
        
        echo json_encode($response);
    }
    
    /**
     * Display development error for debugging
     */
    private function displayDevelopmentError(array $errorData): void
    {
        if (!$this->isAjaxRequest() && !headers_sent()) {
            echo "<div style='background: #ff6b6b; color: white; padding: 10px; margin: 10px; border-radius: 5px;'>";
            echo "<strong>{$errorData['type']}</strong>: {$errorData['message']}<br>";
            echo "<small>{$errorData['file']}:{$errorData['line']}</small>";
            echo "</div>";
        }
    }
    
    /**
     * Get severity name from error constant
     */
    private function getSeverityName(int $severity): string
    {
        $severities = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED'
        ];
        
        return $severities[$severity] ?? 'UNKNOWN';
    }
    
    /**
     * Sanitize context data for logging
     */
    private function sanitizeContext(array $context): array
    {
        // Remove sensitive data
        $sensitive = ['password', 'token', 'secret', 'key', 'auth'];
        
        array_walk_recursive($context, function (&$value, $key) use ($sensitive) {
            if (is_string($key) && in_array(strtolower($key), $sensitive)) {
                $value = '[REDACTED]';
            }
        });
        
        return $context;
    }
    
    /**
     * Sanitize stack trace for logging
     */
    private function sanitizeTrace(array $trace): array
    {
        // Limit trace depth and sanitize arguments
        $sanitized = [];
        $maxDepth = 10;
        
        foreach (array_slice($trace, 0, $maxDepth) as $frame) {
            $sanitized[] = [
                'file' => $frame['file'] ?? '[internal]',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
                'class' => $frame['class'] ?? null,
                'args' => isset($frame['args']) ? $this->sanitizeArguments($frame['args']) : []
            ];
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize function arguments for logging
     */
    private function sanitizeArguments(array $args): array
    {
        return array_map(function ($arg) {
            if (is_object($arg)) {
                return '[object:' . get_class($arg) . ']';
            } elseif (is_array($arg)) {
                return '[array:' . count($arg) . ']';
            } elseif (is_resource($arg)) {
                return '[resource]';
            } elseif (is_string($arg) && strlen($arg) > 100) {
                return substr($arg, 0, 100) . '...';
            }
            return $arg;
        }, $args);
    }
    
    /**
     * Check if current request is AJAX
     */
    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Get current user ID for logging
     */
    private function getCurrentUserId(): ?int
    {
        try {
            if (class_exists('Auth') && method_exists('Auth', 'getCurrentUser')) {
                $user = Auth::getCurrentUser();
                return $user['id'] ?? $user['user_id'] ?? null;
            }
        } catch (Exception $e) {
            // Ignore errors when getting user info
        }
        
        return null;
    }
    
    /**
     * Create user-friendly error messages
     */
    public static function createUserFriendlyMessage(string $type, string $context = ''): string
    {
        $messages = [
            'database' => 'We\'re experiencing technical difficulties. Please try again in a moment.',
            'validation' => 'Please check your input and try again.',
            'authentication' => 'Please log in to access this feature.',
            'authorization' => 'You don\'t have permission to perform this action.',
            'not_found' => 'The requested resource was not found.',
            'rate_limit' => 'Too many requests. Please wait a moment and try again.',
            'maintenance' => 'The system is temporarily unavailable for maintenance.',
            'file_upload' => 'There was a problem uploading your file. Please try again.',
            'external_service' => 'An external service is temporarily unavailable.',
            'default' => 'An unexpected error occurred. Please try again.'
        ];
        
        $message = $messages[$type] ?? $messages['default'];
        
        if ($context) {
            $message .= " ($context)";
        }
        
        return $message;
    }
}

// Initialize error handler
ErrorHandler::getInstance();
?>
