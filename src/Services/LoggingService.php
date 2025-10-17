<?php

declare(strict_types=1);

namespace RfidCheckin\Services;

use Exception;

/**
 * Centralized Logging Service
 * 
 * Provides comprehensive logging functionality with multiple log levels,
 * file rotation, and structured logging for debugging and monitoring.
 * Replaces scattered logging patterns throughout the application.
 * 
 * Features:
 * - PSR-3 compliant log levels
 * - Structured logging with context
 * - File rotation and size management
 * - Performance logging
 * - Security event logging
 * - Error tracking and aggregation
 * 
 * @package RfidCheckin\Services
 * @version 1.0.0
 * @author Senior Development Team
 */
class LoggingService
{
    private static ?LoggingService $instance = null;
    private string $logDirectory;
    private string $logLevel;
    private bool $debugMode;
    private array $logLevels = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4
    ];

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        $this->logDirectory = dirname(__DIR__, 2) . '/logs';
        $this->logLevel = $_ENV['LOG_LEVEL'] ?? 'INFO';
        $this->debugMode = ($_ENV['DEBUG_MODE'] ?? 'false') === 'true';
        
        $this->ensureLogDirectory();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log debug message
     * 
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * Log info message
     * 
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * Log warning message
     * 
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    /**
     * Log error message
     * 
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Log critical message
     * 
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    /**
     * Log security event
     * 
     * @param string $event Security event type
     * @param string $message Event message
     * @param array $context Event context
     */
    public function security(string $event, string $message, array $context = []): void
    {
        $context['security_event'] = $event;
        $context['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $context['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $context['request_uri'] = $_SERVER['REQUEST_URI'] ?? 'unknown';
        
        $this->log('WARNING', "[SECURITY] $message", $context);
        
        // Also write to dedicated security log
        $this->writeToFile('security.log', $this->formatLogEntry('WARNING', "[SECURITY] $message", $context));
    }

    /**
     * Log performance metric
     * 
     * @param string $operation Operation name
     * @param float $duration Operation duration in seconds
     * @param array $context Additional context
     */
    public function performance(string $operation, float $duration, array $context = []): void
    {
        $context['operation'] = $operation;
        $context['duration'] = $duration;
        $context['memory_usage'] = memory_get_usage(true);
        $context['peak_memory'] = memory_get_peak_usage(true);
        
        $this->log('INFO', "Performance: $operation completed in " . number_format($duration * 1000, 2) . "ms", $context);
        
        // Write to performance log for analysis
        $this->writeToFile('performance.log', $this->formatLogEntry('INFO', "Performance: $operation", $context));
    }

    /**
     * Log database query
     * 
     * @param string $query SQL query
     * @param array $params Query parameters
     * @param float $duration Query duration
     */
    public function query(string $query, array $params, float $duration): void
    {
        if (!$this->debugMode) {
            return;
        }

        $context = [
            'query' => $query,
            'params' => $params,
            'duration' => $duration
        ];

        $this->debug("Database query executed", $context);
        
        if ($duration > 1.0) {
            $this->warning("Slow query detected", $context);
        }
    }

    /**
     * Core logging method
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $logEntry = $this->formatLogEntry($level, $message, $context);
        
        // Write to main log file
        $this->writeToFile('application.log', $logEntry);
        
        // Write errors to separate error log
        if (in_array($level, ['ERROR', 'CRITICAL'])) {
            $this->writeToFile('error.log', $logEntry);
        }
        
        // In debug mode, also output to error log for immediate visibility
        if ($this->debugMode && in_array($level, ['ERROR', 'CRITICAL', 'WARNING'])) {
            error_log($logEntry);
        }
    }

    /**
     * Check if message should be logged based on configured log level
     * 
     * @param string $level Message log level
     * @return bool True if should log
     */
    private function shouldLog(string $level): bool
    {
        $messageLevel = $this->logLevels[$level] ?? 0;
        $configuredLevel = $this->logLevels[$this->logLevel] ?? 1;
        
        return $messageLevel >= $configuredLevel;
    }

    /**
     * Format log entry with timestamp and context
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     * @return string Formatted log entry
     */
    private function formatLogEntry(string $level, string $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextString = empty($context) ? '' : ' | Context: ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        
        return "[$timestamp] [$level] $message$contextString" . PHP_EOL;
    }

    /**
     * Write log entry to file
     * 
     * @param string $filename Log file name
     * @param string $entry Log entry
     */
    private function writeToFile(string $filename, string $entry): void
    {
        $filepath = $this->logDirectory . '/' . $filename;
        
        // Rotate log file if it gets too large (10MB)
        if (file_exists($filepath) && filesize($filepath) > 10 * 1024 * 1024) {
            $this->rotateLogFile($filepath);
        }
        
        file_put_contents($filepath, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Rotate log file when it gets too large
     * 
     * @param string $filepath Current log file path
     */
    private function rotateLogFile(string $filepath): void
    {
        $rotatedPath = $filepath . '.' . date('Y-m-d-H-i-s');
        rename($filepath, $rotatedPath);
        
        // Compress old log file
        if (function_exists('gzencode')) {
            $content = file_get_contents($rotatedPath);
            file_put_contents($rotatedPath . '.gz', gzencode($content));
            unlink($rotatedPath);
        }
        
        // Clean up old log files (keep only last 30 days)
        $this->cleanupOldLogs();
    }

    /**
     * Clean up old log files
     */
    private function cleanupOldLogs(): void
    {
        $files = glob($this->logDirectory . '/*.log.*');
        $cutoffTime = time() - (30 * 24 * 60 * 60); // 30 days ago
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }
    }

    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory(): void
    {
        if (!is_dir($this->logDirectory)) {
            mkdir($this->logDirectory, 0755, true);
        }
    }

    /**
     * Get log statistics
     * 
     * @return array Log statistics
     */
    public function getStats(): array
    {
        $stats = [
            'log_directory' => $this->logDirectory,
            'log_level' => $this->logLevel,
            'debug_mode' => $this->debugMode,
            'log_files' => []
        ];
        
        $logFiles = glob($this->logDirectory . '/*.log');
        foreach ($logFiles as $file) {
            $stats['log_files'][basename($file)] = [
                'size' => filesize($file),
                'modified' => filemtime($file),
                'lines' => $this->countLines($file)
            ];
        }
        
        return $stats;
    }

    /**
     * Count lines in log file
     * 
     * @param string $filepath Log file path
     * @return int Number of lines
     */
    private function countLines(string $filepath): int
    {
        $lineCount = 0;
        $handle = fopen($filepath, 'r');
        
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $lineCount++;
            }
            fclose($handle);
        }
        
        return $lineCount;
    }

    /**
     * Get recent log entries
     * 
     * @param string $logFile Log file name
     * @param int $lines Number of lines to retrieve
     * @return array Recent log entries
     */
    public function getRecentEntries(string $logFile = 'application.log', int $lines = 100): array
    {
        $filepath = $this->logDirectory . '/' . $logFile;
        
        if (!file_exists($filepath)) {
            return [];
        }
        
        $file = new \SplFileObject($filepath);
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        
        $startLine = max(0, $totalLines - $lines);
        $file->seek($startLine);
        
        $entries = [];
        while (!$file->eof()) {
            $line = trim($file->current());
            if (!empty($line)) {
                $entries[] = $line;
            }
            $file->next();
        }
        
        return $entries;
    }

    /**
     * Prevent cloning of singleton
     */
    private function __clone() {}

    /**
     * Prevent unserialization of singleton
     */
    public function __wakeup() {}
}
