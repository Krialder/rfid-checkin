<?php

declare(strict_types=1);

namespace RfidCheckin\Services;

use RfidCheckin\Services\ConfigurationService;
use RfidCheckin\Services\LoggingService;
use Exception;

/**
 * Error Handler Service
 * 
 * Centralized error handling with logging and user-friendly responses
 * 
 * @package RfidCheckin\Services
 */
class ErrorHandler
{
    private ConfigurationService $config;
    private LoggingService $logger;

    public function __construct(ConfigurationService $config)
    {
        $this->config = $config;
        $this->logger = LoggingService::getInstance();
    }

    /**
     * Register error handlers
     */
    public function register(): void
    {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Handle PHP errors
     */
    public function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $this->logger->error('PHP Error', [
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line
        ]);

        if ($this->config->isDebugMode()) {
            echo "Error: $message in $file on line $line\n";
        }

        return true;
    }

    /**
     * Handle uncaught exceptions
     */
    public function handleException(\Throwable $exception): void
    {
        $this->logger->error('Uncaught Exception', [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);

        if ($this->config->isDebugMode()) {
            echo "Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine() . "\n";
            echo $exception->getTraceAsString();
        } else {
            echo "An error occurred. Please try again later.";
        }
    }

    /**
     * Handle shutdown errors
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING])) {
            $this->logger->critical('Fatal Error', $error);
        }
    }
}