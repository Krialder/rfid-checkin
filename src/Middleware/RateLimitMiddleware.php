<?php

declare(strict_types=1);

namespace RfidCheckin\Middleware;

use RfidCheckin\Services\LoggingService;

/**
 * Rate Limiting Middleware
 * 
 * Prevents abuse by limiting requests per IP address and user.
 * Implements sliding window rate limiting with configurable limits.
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private LoggingService $logger;
    private string $storageDir;
    
    // Default rate limits (requests per window)
    private array $defaultLimits = [
        'global' => ['requests' => 1000, 'window' => 3600], // 1000 per hour
        'api' => ['requests' => 300, 'window' => 3600],     // 300 per hour for API
        'auth' => ['requests' => 10, 'window' => 900],      // 10 per 15 minutes for auth
        'admin' => ['requests' => 100, 'window' => 3600]    // 100 per hour for admin
    ];
    
    public function __construct()
    {
        $this->logger = LoggingService::getInstance();
        $this->storageDir = dirname(__DIR__, 2) . '/storage/rate_limits';
        
        // Ensure storage directory exists
        if (!file_exists($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }
    
    public function handle(array $request, callable $next): array
    {
        $identifier = $this->getIdentifier($request);
        $rateLimitType = $this->getRateLimitType();
        
        if ($this->isRateLimited($identifier, $rateLimitType)) {
            $this->logger->warning('Rate limit exceeded', [
                'identifier' => $identifier,
                'type' => $rateLimitType,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'user_id' => $request['user']['id'] ?? null
            ]);
            
            return [
                'status' => 429,
                'headers' => $this->getRateLimitHeaders($identifier, $rateLimitType),
                'message' => 'Rate limit exceeded. Please try again later.',
                'data' => [
                    'retry_after' => $this->getRetryAfter($identifier, $rateLimitType)
                ]
            ];
        }
        
        // Record the request
        $this->recordRequest($identifier, $rateLimitType);
        
        // Add rate limit headers to response
        $response = $next($request);
        $response['headers'] = array_merge(
            $response['headers'] ?? [],
            $this->getRateLimitHeaders($identifier, $rateLimitType)
        );
        
        return $response;
    }
    
    private function getIdentifier(array $request): string
    {
        // Use user ID if authenticated, otherwise IP address
        if (isset($request['user']['id'])) {
            return 'user_' . $request['user']['id'];
        }
        
        return 'ip_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
    
    private function getRateLimitType(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        if (strpos($uri, '/api/') === 0) {
            return 'api';
        } elseif (strpos($uri, '/auth/') === 0) {
            return 'auth';
        } elseif (strpos($uri, '/admin/') === 0) {
            return 'admin';
        }
        
        return 'global';
    }
    
    private function isRateLimited(string $identifier, string $type): bool
    {
        $limits = $this->defaultLimits[$type] ?? $this->defaultLimits['global'];
        $requests = $this->getRequestCount($identifier, $type, $limits['window']);
        
        return $requests >= $limits['requests'];
    }
    
    private function recordRequest(string $identifier, string $type): void
    {
        $now = time();
        $file = $this->getStorageFile($identifier, $type);
        
        // Load existing requests
        $requests = $this->loadRequests($file);
        
        // Add current request
        $requests[] = $now;
        
        // Clean old requests
        $window = $this->defaultLimits[$type]['window'] ?? $this->defaultLimits['global']['window'];
        $requests = array_filter($requests, fn($time) => ($now - $time) < $window);
        
        // Save updated requests
        $this->saveRequests($file, $requests);
    }
    
    private function getRequestCount(string $identifier, string $type, int $window): int
    {
        $file = $this->getStorageFile($identifier, $type);
        $requests = $this->loadRequests($file);
        $now = time();
        
        // Count requests within the window
        return count(array_filter($requests, fn($time) => ($now - $time) < $window));
    }
    
    private function getStorageFile(string $identifier, string $type): string
    {
        $filename = md5($identifier . '_' . $type) . '.json';
        return $this->storageDir . '/' . $filename;
    }
    
    private function loadRequests(string $file): array
    {
        if (!file_exists($file)) {
            return [];
        }
        
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        
        return $data['requests'] ?? [];
    }
    
    private function saveRequests(string $file, array $requests): void
    {
        $data = [
            'requests' => array_values($requests),
            'updated_at' => time()
        ];
        
        file_put_contents($file, json_encode($data), LOCK_EX);
    }
    
    private function getRateLimitHeaders(string $identifier, string $type): array
    {
        $limits = $this->defaultLimits[$type] ?? $this->defaultLimits['global'];
        $requests = $this->getRequestCount($identifier, $type, $limits['window']);
        $remaining = max(0, $limits['requests'] - $requests);
        $resetTime = time() + $limits['window'];
        
        return [
            'X-RateLimit-Limit' => (string)$limits['requests'],
            'X-RateLimit-Remaining' => (string)$remaining,
            'X-RateLimit-Reset' => (string)$resetTime,
            'X-RateLimit-Window' => (string)$limits['window']
        ];
    }
    
    private function getRetryAfter(string $identifier, string $type): int
    {
        $limits = $this->defaultLimits[$type] ?? $this->defaultLimits['global'];
        return $limits['window'];
    }
    
    /**
     * Clean old rate limit files
     */
    public function cleanup(): void
    {
        $files = glob($this->storageDir . '/*.json');
        $now = time();
        $maxAge = 86400; // 24 hours
        
        foreach ($files as $file) {
            if (($now - filemtime($file)) > $maxAge) {
                unlink($file);
            }
        }
    }
}