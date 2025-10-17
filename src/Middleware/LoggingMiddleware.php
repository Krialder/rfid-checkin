<?php

declare(strict_types=1);

namespace RfidCheckin\Middleware;

use RfidCheckin\Services\LoggingService;

/**
 * Request Logging Middleware
 * 
 * Logs all incoming requests with timing, user information, and response status.
 * Provides audit trail and debugging information.
 */
class LoggingMiddleware implements MiddlewareInterface
{
    private LoggingService $logger;
    private float $startTime;
    
    public function __construct()
    {
        $this->logger = LoggingService::getInstance();
        $this->startTime = microtime(true);
    }
    
    public function handle(array $request, callable $next): array
    {
        // Log request start
        $this->logRequest($request);
        
        // Process request
        $response = $next($request);
        
        // Log response
        $this->logResponse($request, $response);
        
        return $response;
    }
    
    private function logRequest(array $request): void
    {
        $logData = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'ip' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'user_id' => $request['user']['id'] ?? null,
            'username' => $request['user']['username'] ?? null,
            'session_id' => session_id(),
            'timestamp' => date('c'),
            'memory_usage' => memory_get_usage(true),
            'request_id' => $this->generateRequestId()
        ];
        
        // Store request ID for correlation
        $_SERVER['REQUEST_ID'] = $logData['request_id'];
        
        $this->logger->info('Request started', $logData);
    }
    
    private function logResponse(array $request, array $response): void
    {
        $duration = microtime(true) - $this->startTime;
        
        $logData = [
            'request_id' => $_SERVER['REQUEST_ID'] ?? null,
            'status' => $response['status'] ?? 200,
            'duration_ms' => round($duration * 1000, 2),
            'memory_peak' => memory_get_peak_usage(true),
            'redirect' => $response['redirect'] ?? null,
            'user_id' => $request['user']['id'] ?? null
        ];
        
        $level = $this->getLogLevel($response['status'] ?? 200);
        $this->logger->log($level, 'Request completed', $logData);
    }
    
    private function getClientIp(): string
    {
        // Check for shared internet/proxy
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        // Check for pass from proxy
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        // Check for remote address
        elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        
        return 'unknown';
    }
    
    private function generateRequestId(): string
    {
        return uniqid('req_', true);
    }
    
    private function getLogLevel(int $status): string
    {
        if ($status >= 500) {
            return 'error';
        } elseif ($status >= 400) {
            return 'warning';
        } elseif ($status >= 300) {
            return 'info';
        }
        
        return 'info';
    }
}