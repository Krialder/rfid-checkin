<?php

declare(strict_types=1);

namespace RfidCheckin\Services;

use RfidCheckin\Services\ConfigurationService;
use RfidCheckin\Services\LoggingService;
use Exception;
use Throwable;

/**
 * Error Handler Service
 * 
 * Handles error logging, user-friendly error displays, and debugging.
 * Provides environment-aware error reporting with sanitized output.
 * 
 * @package RfidCheckin\Services
 * @version 2.0.0
 */
class ErrorHandler
{
    private ConfigurationService $config;
    private LoggingService $logger;
    private bool $isDevelopment;
    private bool $registered = false;
    
    /**
     * Constructor
     */
    public function __construct(ConfigurationService $config)
    {
        $this->config = $config;
        $this->logger = LoggingService::getInstance();
        $this->isDevelopment = $config->isDevelopment();
    }
    
    /**
     * Register error handlers
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }
        
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
        
        $this->registered = true;
        
        $this->logger->debug('Error handlers registered');
    }
    
    /**
     * Handle PHP errors
     */
    public function handleError(int $severity, string $message, string $file, int $line): bool
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
        
        if ($this->isDevelopment) {
            $this->displayDevelopmentException($exception);
        } else {
            $this->displayProductionError();
        }
        
        exit(1);
    }
    
    /**
     * Handle fatal errors on shutdown
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        
        if ($error !== null && $error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR)) {
            $errorData = [
                'type' => 'Fatal Error',
                'severity' => $this->getSeverityName($error['type']),
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'timestamp' => date('Y-m-d H:i:s'),
                'user_id' => $this->getCurrentUserId(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ];
            
            $this->logError($errorData);
            
            if (!headers_sent()) {
                http_response_code(500);
            }
        }
    }
    
    /**
     * Log error data
     */
    private function logError(array $errorData): void
    {
        $logLevel = $this->getLogLevel($errorData);
        
        $this->logger->log($logLevel, $errorData['message'], [
            'error_type' => $errorData['type'],
            'severity' => $errorData['severity'] ?? 'unknown',
            'file' => $errorData['file'],
            'line' => $errorData['line'],
            'user_id' => $errorData['user_id'],
            'ip_address' => $errorData['ip_address'],
            'user_agent' => $errorData['user_agent'],
            'request_uri' => $errorData['request_uri'],
            'trace' => $errorData['trace'] ?? null
        ]);
    }
    
    /**
     * Get log level based on error data
     */
    private function getLogLevel(array $errorData): string
    {
        if ($errorData['type'] === 'Exception' || $errorData['type'] === 'Fatal Error') {
            return 'ERROR';
        }
        
        $severity = $errorData['severity'] ?? '';
        
        switch ($severity) {
            case 'E_ERROR':
            case 'E_PARSE':
            case 'E_CORE_ERROR':
            case 'E_COMPILE_ERROR':
                return 'ERROR';
            case 'E_WARNING':
            case 'E_CORE_WARNING':
            case 'E_COMPILE_WARNING':
                return 'WARNING';
            default:
                return 'INFO';
        }
    }
    
    /**
     * Get severity name from error level
     */
    private function getSeverityName(int $severity): string
    {
        $severityNames = [
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
        
        return $severityNames[$severity] ?? 'UNKNOWN';
    }
    
    /**
     * Sanitize stack trace for logging
     */
    private function sanitizeTrace(array $trace): array
    {
        $sanitized = [];
        
        foreach ($trace as $frame) {
            $sanitized[] = [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
                'class' => $frame['class'] ?? null,
                'type' => $frame['type'] ?? null
            ];
        }
        
        return $sanitized;
    }
    
    /**
     * Get current user ID for error context
     */
    private function getCurrentUserId(): ?int
    {
        if (isset($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }
        
        return null;
    }
    
    /**
     * Display development error (detailed)
     */
    private function displayDevelopmentError(array $errorData): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        
        echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 10px; border-radius: 5px;'>";
        echo "<h4 style='color: #856404; margin: 0 0 10px 0;'>{$errorData['type']}: {$errorData['severity']}</h4>";
        echo "<p style='margin: 5px 0; font-family: monospace;'><strong>Message:</strong> " . htmlspecialchars($errorData['message']) . "</p>";
        echo "<p style='margin: 5px 0; font-family: monospace;'><strong>File:</strong> " . htmlspecialchars($errorData['file']) . "</p>";
        echo "<p style='margin: 5px 0; font-family: monospace;'><strong>Line:</strong> {$errorData['line']}</p>";
        echo "</div>";
    }
    
    /**
     * Display development exception (detailed)
     */
    private function displayDevelopmentException(Throwable $exception): void
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }
        
        echo '<!DOCTYPE html>
<html>
<head>
    <title>Application Error</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .error-container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .error-header { background: #dc3545; color: white; padding: 15px; margin: -20px -20px 20px -20px; border-radius: 8px 8px 0 0; }
        .error-details { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0; }
        .stack-trace { background: #343a40; color: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
        pre { margin: 0; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-header">
            <h1>🚨 Application Error</h1>
            <p>An uncaught exception occurred in development mode</p>
        </div>
        
        <div class="error-details">
            <p><strong>Exception:</strong> ' . htmlspecialchars(get_class($exception)) . '</p>
            <p><strong>Message:</strong> ' . htmlspecialchars($exception->getMessage()) . '</p>
            <p><strong>File:</strong> ' . htmlspecialchars($exception->getFile()) . '</p>
            <p><strong>Line:</strong> ' . $exception->getLine() . '</p>
        </div>
        
        <div class="stack-trace">
            <h3>Stack Trace:</h3>
            <pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Display production error (generic)
     */
    private function displayProductionError(): void
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }
        
        echo '<!DOCTYPE html>
<html>
<head>
    <title>Server Error</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f8f9fa; text-align: center; }
        .error-container { max-width: 600px; margin: 100px auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .error-icon { font-size: 64px; margin-bottom: 20px; }
        h1 { color: #dc3545; margin-bottom: 20px; }
        .error-message { color: #6c757d; margin-bottom: 30px; }
        .btn { background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; display: inline-block; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h1>500 - Internal Server Error</h1>
        <p class="error-message">
            An unexpected error occurred. Our team has been notified and is working to resolve the issue.
        </p>
        <a href="/" class="btn">Return to Home</a>
    </div>
</body>
</html>';
    }
}