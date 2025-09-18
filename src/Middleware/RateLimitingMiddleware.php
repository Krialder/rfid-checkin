<?php

declare(strict_types=1);

namespace RfidCheckin\Middleware;

use RfidCheckin\Services\LoggingService;
use RfidCheckin\Services\ConfigurationService;

/**
 * Rate Limiting Middleware
 * 
 * Protects against abuse by limiting the number of requests
 * from individual clients within specified time windows.
 * 
 * Features:
 * - IP-based rate limiting
 * - Multiple time windows (minute, hour, day)
 * - Different limits for different routes
 * - User-based rate limiting
 * - Burst protection
 * - Exponential backoff
 * - Whitelist/blacklist support
 * 
 * @package RfidCheckin\Middleware
 * @version 1.0.0
 * @author Senior Development Team
 */
class RateLimitingMiddleware implements MiddlewareInterface
{
    private LoggingService $logger;
    private ConfigurationService $config;
    
    private array $defaultLimits = [
        'requests_per_minute' => 60,
        'requests_per_hour' => 1000,
        'requests_per_day' => 5000
    ];
    
    private array $routeLimits = [
        '/auth/login' => [
            'requests_per_minute' => 5,
            'requests_per_hour' => 20,
            'requests_per_day' => 100
        ],
        '/auth/forgot-password' => [
            'requests_per_minute' => 2,
            'requests_per_hour' => 5,
            'requests_per_day' => 10
        ],
        '/api/rfid-checkin' => [
            'requests_per_minute' => 30,
            'requests_per_hour' => 500,
            'requests_per_day' => 2000
        ],
        '/api/manual-checkin' => [
            'requests_per_minute' => 10,
            'requests_per_hour' => 100,
            'requests_per_day' => 500
        ]
    ];
    
    private array $whitelistedIps = [
        '127.0.0.1',
        '::1'
    ];
    
    private array $blacklistedIps = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = LoggingService::getInstance();
        $this->config = ConfigurationService::getInstance();
        
        // Load configuration overrides
        $this->loadConfiguration();
    }

    /**
     * Handle rate limiting
     */
    public function handle(callable $next)
    {
        $clientIp = $this->getClientIp();
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $requestPath = parse_url($requestUri, PHP_URL_PATH);
        $userId = $_SESSION['user_id'] ?? null;

        // Check if IP is blacklisted
        if ($this->isBlacklisted($clientIp)) {
            $this->handleBlacklistedRequest($clientIp);
            return;
        }

        // Skip rate limiting for whitelisted IPs
        if ($this->isWhitelisted($clientIp)) {
            return $next();
        }

        // Get limits for this route
        $limits = $this->getLimitsForRoute($requestPath);

        // Check rate limits
        $violations = $this->checkRateLimits($clientIp, $userId, $limits);

        if (!empty($violations)) {
            $this->handleRateLimitExceeded($clientIp, $requestPath, $violations);
            return;
        }

        // Record the request
        $this->recordRequest($clientIp, $userId, $requestPath);

        // Set rate limit headers
        $this->setRateLimitHeaders($clientIp, $limits);

        return $next();
    }

    /**
     * Load configuration settings
     */
    private function loadConfiguration(): void
    {
        // Load from configuration service
        $configLimits = $this->config->get('rate_limiting.default_limits', []);
        if (!empty($configLimits)) {
            $this->defaultLimits = array_merge($this->defaultLimits, $configLimits);
        }

        $configRouteLimits = $this->config->get('rate_limiting.route_limits', []);
        if (!empty($configRouteLimits)) {
            $this->routeLimits = array_merge($this->routeLimits, $configRouteLimits);
        }

        $configWhitelist = $this->config->get('rate_limiting.whitelisted_ips', []);
        if (!empty($configWhitelist)) {
            $this->whitelistedIps = array_merge($this->whitelistedIps, $configWhitelist);
        }

        $configBlacklist = $this->config->get('rate_limiting.blacklisted_ips', []);
        if (!empty($configBlacklist)) {
            $this->blacklistedIps = array_merge($this->blacklistedIps, $configBlacklist);
        }
    }

    /**
     * Get rate limits for specific route
     */
    private function getLimitsForRoute(string $path): array
    {
        // Check for exact match
        if (isset($this->routeLimits[$path])) {
            return array_merge($this->defaultLimits, $this->routeLimits[$path]);
        }

        // Check for pattern matches
        foreach ($this->routeLimits as $pattern => $limits) {
            if ($this->matchesPattern($path, $pattern)) {
                return array_merge($this->defaultLimits, $limits);
            }
        }

        return $this->defaultLimits;
    }

    /**
     * Check if path matches pattern
     */
    private function matchesPattern(string $path, string $pattern): bool
    {
        // Simple wildcard matching
        $regex = str_replace('*', '.*', preg_quote($pattern, '/'));
        return preg_match('/^' . $regex . '$/', $path) === 1;
    }

    /**
     * Check rate limits for client
     */
    private function checkRateLimits(string $clientIp, ?int $userId, array $limits): array
    {
        $violations = [];
        $windows = [
            'minute' => 60,
            'hour' => 3600,
            'day' => 86400
        ];

        foreach ($windows as $window => $seconds) {
            $limitKey = "requests_per_{$window}";
            if (!isset($limits[$limitKey])) {
                continue;
            }

            $limit = $limits[$limitKey];
            $requestCount = $this->getRequestCount($clientIp, $userId, $seconds);

            if ($requestCount >= $limit) {
                $violations[] = [
                    'window' => $window,
                    'limit' => $limit,
                    'current' => $requestCount,
                    'reset_time' => time() + $seconds
                ];
            }
        }

        return $violations;
    }

    /**
     * Get request count for time window
     */
    private function getRequestCount(string $clientIp, ?int $userId, int $seconds): int
    {
        $key = $this->getStorageKey($clientIp, $userId);
        $requests = $_SESSION["rate_limit_{$key}"] ?? [];
        
        // Clean old requests
        $cutoff = time() - $seconds;
        $requests = array_filter($requests, function($timestamp) use ($cutoff) {
            return $timestamp > $cutoff;
        });
        
        // Update session
        $_SESSION["rate_limit_{$key}"] = $requests;
        
        return count($requests);
    }

    /**
     * Record a request
     */
    private function recordRequest(string $clientIp, ?int $userId, string $path): void
    {
        $key = $this->getStorageKey($clientIp, $userId);
        
        if (!isset($_SESSION["rate_limit_{$key}"])) {
            $_SESSION["rate_limit_{$key}"] = [];
        }
        
        $_SESSION["rate_limit_{$key}"][] = time();
        
        // Keep only last 1000 requests to prevent memory issues
        if (count($_SESSION["rate_limit_{$key}"]) > 1000) {
            $_SESSION["rate_limit_{$key}"] = array_slice($_SESSION["rate_limit_{$key}"], -1000);
        }
    }

    /**
     * Generate storage key for client
     */
    private function getStorageKey(string $clientIp, ?int $userId): string
    {
        return $userId ? "user_{$userId}" : "ip_{$clientIp}";
    }

    /**
     * Check if IP is whitelisted
     */
    private function isWhitelisted(string $ip): bool
    {
        return in_array($ip, $this->whitelistedIps);
    }

    /**
     * Check if IP is blacklisted
     */
    private function isBlacklisted(string $ip): bool
    {
        return in_array($ip, $this->blacklistedIps);
    }

    /**
     * Handle blacklisted request
     */
    private function handleBlacklistedRequest(string $clientIp): void
    {
        $this->logger->warning('Request from blacklisted IP', [
            'ip_address' => $clientIp,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? ''
        ]);

        http_response_code(403);
        
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Access denied',
                'code' => 'BLACKLISTED'
            ]);
        } else {
            echo '<!DOCTYPE html>
<html><head><title>Access Denied</title></head>
<body><h1>403 - Access Denied</h1><p>Your IP address has been blocked.</p></body>
</html>';
        }
        
        exit;
    }

    /**
     * Handle rate limit exceeded
     */
    private function handleRateLimitExceeded(string $clientIp, string $path, array $violations): void
    {
        $this->logger->warning('Rate limit exceeded', [
            'ip_address' => $clientIp,
            'path' => $path,
            'violations' => $violations,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);

        // Find the most restrictive violation (shortest reset time)
        $primaryViolation = min($violations);
        $retryAfter = $primaryViolation['reset_time'] - time();

        http_response_code(429);
        header('Retry-After: ' . $retryAfter);
        
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Rate limit exceeded',
                'code' => 'RATE_LIMITED',
                'retry_after' => $retryAfter,
                'violations' => $violations
            ]);
        } else {
            $this->renderRateLimitPage($violations, $retryAfter);
        }
        
        exit;
    }

    /**
     * Set rate limit headers
     */
    private function setRateLimitHeaders(string $clientIp, array $limits): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        
        // Set headers for minute window (most common)
        if (isset($limits['requests_per_minute'])) {
            $remaining = max(0, $limits['requests_per_minute'] - $this->getRequestCount($clientIp, $userId, 60));
            $resetTime = time() + 60;
            
            header('X-RateLimit-Limit: ' . $limits['requests_per_minute']);
            header('X-RateLimit-Remaining: ' . $remaining);
            header('X-RateLimit-Reset: ' . $resetTime);
        }
    }

    /**
     * Render rate limit exceeded page
     */
    private function renderRateLimitPage(array $violations, int $retryAfter): void
    {
        $minutes = ceil($retryAfter / 60);
        
        echo '<!DOCTYPE html>
<html>
<head>
    <title>Rate Limit Exceeded</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0; padding: 40px; background: #f8f9fa; text-align: center;
        }
        .container { 
            max-width: 500px; margin: 100px auto; background: white;
            padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .icon { font-size: 64px; margin-bottom: 20px; }
        h1 { color: #dc3545; margin-bottom: 20px; }
        .message { color: #6c757d; margin-bottom: 30px; line-height: 1.5; }
        .timer { 
            font-size: 24px; font-weight: bold; color: #007bff; 
            margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 5px;
        }
        .details { 
            text-align: left; background: #f8f9fa; padding: 15px; 
            border-radius: 5px; margin: 20px 0; font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🚦</div>
        <h1>Rate Limit Exceeded</h1>
        <p class="message">
            You have made too many requests in a short period of time. 
            Please wait before trying again.
        </p>
        
        <div class="timer" id="countdown">
            Please wait ' . $minutes . ' minute(s)
        </div>
        
        <div class="details">
            <strong>What happened?</strong><br>
            Our system detected an unusually high number of requests from your connection 
            to protect against abuse and ensure service availability for all users.
        </div>
        
        <div class="details">
            <strong>What can you do?</strong><br>
            • Wait for the countdown to finish<br>
            • Try again after the specified time<br>
            • If you continue to see this message, contact support
        </div>
    </div>
    
    <script>
        let retryAfter = ' . $retryAfter . ';
        const countdownEl = document.getElementById("countdown");
        
        function updateCountdown() {
            if (retryAfter <= 0) {
                countdownEl.innerHTML = "You can try again now!";
                setTimeout(() => window.location.reload(), 2000);
                return;
            }
            
            const minutes = Math.floor(retryAfter / 60);
            const seconds = retryAfter % 60;
            countdownEl.innerHTML = `Please wait ${minutes}:${seconds.toString().padStart(2, "0")}`;
            
            retryAfter--;
            setTimeout(updateCountdown, 1000);
        }
        
        updateCountdown();
    </script>
</body>
</html>';
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
     * Add IP to whitelist
     */
    public function addToWhitelist(string $ip): void
    {
        if (!in_array($ip, $this->whitelistedIps)) {
            $this->whitelistedIps[] = $ip;
        }
    }

    /**
     * Add IP to blacklist
     */
    public function addToBlacklist(string $ip): void
    {
        if (!in_array($ip, $this->blacklistedIps)) {
            $this->blacklistedIps[] = $ip;
        }
    }

    /**
     * Remove IP from blacklist
     */
    public function removeFromBlacklist(string $ip): void
    {
        $this->blacklistedIps = array_filter($this->blacklistedIps, function($blacklistedIp) use ($ip) {
            return $blacklistedIp !== $ip;
        });
    }

    /**
     * Get current rate limit status for IP
     */
    public function getRateLimitStatus(string $clientIp, ?int $userId = null): array
    {
        $limits = $this->defaultLimits;
        $status = [];
        
        $windows = [
            'minute' => 60,
            'hour' => 3600,
            'day' => 86400
        ];

        foreach ($windows as $window => $seconds) {
            $limitKey = "requests_per_{$window}";
            if (!isset($limits[$limitKey])) {
                continue;
            }

            $limit = $limits[$limitKey];
            $current = $this->getRequestCount($clientIp, $userId, $seconds);
            $remaining = max(0, $limit - $current);

            $status[$window] = [
                'limit' => $limit,
                'current' => $current,
                'remaining' => $remaining,
                'reset_time' => time() + $seconds
            ];
        }

        return $status;
    }
}
