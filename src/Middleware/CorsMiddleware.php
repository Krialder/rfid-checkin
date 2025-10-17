<?php

declare(strict_types=1);

namespace RfidCheckin\Middleware;

/**
 * CORS (Cross-Origin Resource Sharing) Middleware
 * 
 * Handles CORS headers for API endpoints to allow cross-origin requests
 * from approved domains. Essential for frontend-backend separation.
 */
class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins = [
        'http://localhost:3000',  // Development frontend
        'http://localhost:8080',  // Alternative dev port
        'https://rfid.yourdomain.com' // Production domain
    ];
    
    private array $allowedMethods = [
        'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'
    ];
    
    private array $allowedHeaders = [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'Accept',
        'Origin',
        'Cache-Control'
    ];
    
    private int $maxAge = 86400; // 24 hours
    
    public function handle(array $request, callable $next): array
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            return $this->handlePreflightRequest($origin);
        }
        
        // Process the actual request
        $response = $next($request);
        
        // Add CORS headers to response
        $response['headers'] = array_merge(
            $response['headers'] ?? [],
            $this->getCorsHeaders($origin)
        );
        
        return $response;
    }
    
    private function handlePreflightRequest(string $origin): array
    {
        return [
            'status' => 204, // No Content
            'headers' => $this->getCorsHeaders($origin, true),
            'data' => null
        ];
    }
    
    private function getCorsHeaders(string $origin, bool $isPreflight = false): array
    {
        $headers = [];
        
        // Set Access-Control-Allow-Origin
        if ($this->isOriginAllowed($origin)) {
            $headers['Access-Control-Allow-Origin'] = $origin;
            $headers['Access-Control-Allow-Credentials'] = 'true';
        } else {
            // Allow all origins for API endpoints (remove in production)
            $headers['Access-Control-Allow-Origin'] = '*';
        }
        
        // Preflight-specific headers
        if ($isPreflight) {
            $headers['Access-Control-Allow-Methods'] = implode(', ', $this->allowedMethods);
            $headers['Access-Control-Allow-Headers'] = implode(', ', $this->allowedHeaders);
            $headers['Access-Control-Max-Age'] = (string)$this->maxAge;
        }
        
        // Headers for all CORS requests
        $headers['Access-Control-Expose-Headers'] = 'X-Total-Count, X-Page-Count, X-Per-Page';
        $headers['Vary'] = 'Origin';
        
        return $headers;
    }
    
    private function isOriginAllowed(string $origin): bool
    {
        return in_array($origin, $this->allowedOrigins);
    }
    
    /**
     * Add allowed origin
     */
    public function addAllowedOrigin(string $origin): void
    {
        if (!in_array($origin, $this->allowedOrigins)) {
            $this->allowedOrigins[] = $origin;
        }
    }
    
    /**
     * Set allowed origins
     */
    public function setAllowedOrigins(array $origins): void
    {
        $this->allowedOrigins = $origins;
    }
}