<?php

namespace App\Middleware;

use App\Services\PerformanceCacheService;
use App\Services\AssetOptimizationService;
use App\Core\LoggingService;
use Exception;

/**
 * Performance Optimization Middleware
 * 
 * Handles automatic performance optimizations:
 * - Response caching with intelligent cache headers
 * - Asset optimization and delivery
 * - Compression (Gzip/Brotli) based on client support
 * - Performance monitoring and metrics collection
 * - Cache warming and optimization
 */
class PerformanceMiddleware
{
    private PerformanceCacheService $cacheService;
    private AssetOptimizationService $assetOptimizer;
    private LoggingService $logger;
    private array $config;
    private float $requestStartTime;
    
    public function __construct()
    {
        $this->cacheService = PerformanceCacheService::getInstance();
        $this->assetOptimizer = AssetOptimizationService::getInstance();
        $this->logger = LoggingService::getInstance();
        $this->requestStartTime = microtime(true);
        
        $this->config = [
            'cache_enabled' => true,
            'compression_enabled' => true,
            'asset_optimization_enabled' => true,
            'performance_monitoring_enabled' => true,
            'cache_static_content' => true,
            'cache_api_responses' => true,
            'compression_threshold' => 1024, // Minimum bytes to compress
            'cache_ttl' => [
                'static' => 86400,    // 24 hours
                'api' => 300,         // 5 minutes
                'dynamic' => 60       // 1 minute
            ]
        ];
    }
    
    /**
     * Process request before main application logic
     */
    public function before(): void
    {
        try {
            $this->startPerformanceMonitoring();
            $this->handleCachedResponse();
            $this->optimizeAssetRequest();
            $this->setupCompressionHeaders();
            
        } catch (Exception $e) {
            $this->logger->error('Performance middleware before error', [
                'error' => $e->getMessage(),
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ]);
        }
    }
    
    /**
     * Process response after main application logic
     */
    public function after(string $response): string
    {
        try {
            $response = $this->compressResponse($response);
            $response = $this->optimizeHtmlResponse($response);
            $this->cacheResponse($response);
            $this->setPerformanceHeaders();
            $this->collectPerformanceMetrics();
            
            return $response;
            
        } catch (Exception $e) {
            $this->logger->error('Performance middleware after error', [
                'error' => $e->getMessage(),
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ]);
            
            return $response;
        }
    }
    
    /**
     * Start performance monitoring for the request
     */
    private function startPerformanceMonitoring(): void
    {
        if (!$this->config['performance_monitoring_enabled']) {
            return;
        }
        
        // Set request start time in global state
        $GLOBALS['REQUEST_START_TIME'] = $this->requestStartTime;
        $GLOBALS['REQUEST_MEMORY_START'] = memory_get_usage(true);
        
        // Track request
        $_SESSION['performance_requests'] = ($_SESSION['performance_requests'] ?? 0) + 1;
    }
    
    /**
     * Handle cached responses
     */
    private function handleCachedResponse(): void
    {
        if (!$this->config['cache_enabled']) {
            return;
        }
        
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Only cache GET requests
        if ($method !== 'GET') {
            return;
        }
        
        // Skip caching for admin and auth pages
        if ($this->shouldSkipCache($requestUri)) {
            return;
        }
        
        $cacheKey = $this->generateCacheKey($requestUri);
        $cachedResponse = $this->cacheService->get($cacheKey, 'http_responses');
        
        if ($cachedResponse !== null) {
            $this->serveCachedResponse($cachedResponse);
        }
    }
    
    /**
     * Optimize asset requests
     */
    private function optimizeAssetRequest(): void
    {
        if (!$this->config['asset_optimization_enabled']) {
            return;
        }
        
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Check if this is an asset request
        if (!$this->isAssetRequest($requestUri)) {
            return;
        }
        
        // Handle optimized asset delivery
        $optimizedPath = $this->assetOptimizer->getOptimizedAssetPath($requestUri);
        
        if ($optimizedPath && file_exists($optimizedPath)) {
            $this->serveOptimizedAsset($optimizedPath);
        }
    }
    
    /**
     * Setup compression headers based on client support
     */
    private function setupCompressionHeaders(): void
    {
        if (!$this->config['compression_enabled']) {
            return;
        }
        
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        
        // Set compression preference
        if (strpos($acceptEncoding, 'br') !== false && function_exists('brotli_compress')) {
            $_SERVER['PREFERRED_COMPRESSION'] = 'br';
        } elseif (strpos($acceptEncoding, 'gzip') !== false && function_exists('gzencode')) {
            $_SERVER['PREFERRED_COMPRESSION'] = 'gzip';
        } else {
            $_SERVER['PREFERRED_COMPRESSION'] = 'none';
        }
    }
    
    /**
     * Compress response content
     */
    private function compressResponse(string $response): string
    {
        if (!$this->config['compression_enabled']) {
            return $response;
        }
        
        // Don't compress small responses
        if (strlen($response) < $this->config['compression_threshold']) {
            return $response;
        }
        
        // Don't compress binary content
        if ($this->isBinaryContent()) {
            return $response;
        }
        
        $compression = $_SERVER['PREFERRED_COMPRESSION'] ?? 'none';
        
        switch ($compression) {
            case 'br':
                if (function_exists('brotli_compress')) {
                    $compressed = brotli_compress($response, 4);
                    if ($compressed !== false) {
                        header('Content-Encoding: br');
                        header('Vary: Accept-Encoding');
                        return $compressed;
                    }
                }
                break;
                
            case 'gzip':
                if (function_exists('gzencode')) {
                    $compressed = gzencode($response, 6);
                    if ($compressed !== false) {
                        header('Content-Encoding: gzip');
                        header('Vary: Accept-Encoding');
                        return $compressed;
                    }
                }
                break;
        }
        
        return $response;
    }
    
    /**
     * Optimize HTML response
     */
    private function optimizeHtmlResponse(string $response): string
    {
        if (!$this->isHtmlResponse()) {
            return $response;
        }
        
        // Minify HTML (remove unnecessary whitespace)
        $response = $this->minifyHtml($response);
        
        // Optimize asset references
        $response = $this->optimizeAssetReferences($response);
        
        // Add performance hints
        $response = $this->addPerformanceHints($response);
        
        return $response;
    }
    
    /**
     * Cache response
     */
    private function cacheResponse(string $response): void
    {
        if (!$this->config['cache_enabled']) {
            return;
        }
        
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Only cache GET requests
        if ($method !== 'GET') {
            return;
        }
        
        // Skip caching for certain pages
        if ($this->shouldSkipCache($requestUri)) {
            return;
        }
        
        $cacheKey = $this->generateCacheKey($requestUri);
        $ttl = $this->getCacheTTL($requestUri);
        
        $cacheData = [
            'content' => $response,
            'headers' => $this->getResponseHeaders(),
            'timestamp' => time(),
            'ttl' => $ttl
        ];
        
        $this->cacheService->set($cacheKey, $cacheData, $ttl, 'http_responses');
    }
    
    /**
     * Set performance-related headers
     */
    private function setPerformanceHeaders(): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Set cache headers for static content
        if ($this->isStaticContent($requestUri)) {
            $maxAge = $this->config['cache_ttl']['static'];
            header("Cache-Control: public, max-age={$maxAge}");
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
        } else {
            // Dynamic content - prevent caching or use short cache
            $ttl = $this->getCacheTTL($requestUri);
            if ($ttl > 0) {
                header("Cache-Control: public, max-age={$ttl}");
            } else {
                header('Cache-Control: no-cache, must-revalidate');
                header('Pragma: no-cache');
            }
        }
        
        // Add performance timing header
        $executionTime = microtime(true) - $this->requestStartTime;
        header("X-Response-Time: " . round($executionTime * 1000, 2) . "ms");
        
        // Add memory usage header
        $memoryUsage = memory_get_peak_usage(true);
        header("X-Memory-Usage: " . round($memoryUsage / 1024 / 1024, 2) . "MB");
        
        // Add security headers that also improve performance
        header('X-DNS-Prefetch-Control: on');
        header('X-Content-Type-Options: nosniff');
    }
    
    /**
     * Collect performance metrics
     */
    private function collectPerformanceMetrics(): void
    {
        if (!$this->config['performance_monitoring_enabled']) {
            return;
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $this->requestStartTime;
        $memoryUsage = memory_get_peak_usage(true);
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        $metrics = [
            'uri' => $requestUri,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'execution_time' => $executionTime,
            'memory_usage' => $memoryUsage,
            'timestamp' => time(),
            'response_size' => ob_get_length() ?: 0,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];
        
        // Store metrics for analysis
        $this->storePerformanceMetrics($metrics);
        
        // Update real-time performance counters
        $this->updatePerformanceCounters($metrics);
    }
    
    /**
     * Serve cached response
     */
    private function serveCachedResponse(array $cachedResponse): void
    {
        // Set headers from cache
        if (isset($cachedResponse['headers'])) {
            foreach ($cachedResponse['headers'] as $header) {
                header($header);
            }
        }
        
        // Add cache hit header
        header('X-Cache: HIT');
        header('X-Cache-Time: ' . ($cachedResponse['timestamp'] ?? time()));
        
        // Output cached content and exit
        echo $cachedResponse['content'];
        exit;
    }
    
    /**
     * Serve optimized asset
     */
    private function serveOptimizedAsset(string $assetPath): void
    {
        $mimeType = $this->getMimeType($assetPath);
        $fileSize = filesize($assetPath);
        $lastModified = filemtime($assetPath);
        
        // Set headers
        header("Content-Type: {$mimeType}");
        header("Content-Length: {$fileSize}");
        header("Last-Modified: " . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
        header("Cache-Control: public, max-age=" . $this->config['cache_ttl']['static']);
        header('X-Optimized: true');
        
        // Check if client has cached version
        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
        if ($ifModifiedSince && strtotime($ifModifiedSince) >= $lastModified) {
            http_response_code(304);
            exit;
        }
        
        // Serve file
        readfile($assetPath);
        exit;
    }
    
    /**
     * Check if cache should be skipped
     */
    private function shouldSkipCache(string $uri): bool
    {
        $skipPatterns = [
            '/admin/',
            '/auth/',
            '/api/rfid-poll',
            '/api/realtime',
            '?nocache',
            '/performance/'
        ];
        
        foreach ($skipPatterns as $pattern) {
            if (strpos($uri, $pattern) !== false) {
                return true;
            }
        }
        
        // Skip if user is logged in and viewing dynamic content
        if (isset($_SESSION['user_id']) && !$this->isStaticContent($uri)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Generate cache key for request
     */
    private function generateCacheKey(string $uri): string
    {
        $key = 'http_response:' . md5($uri);
        
        // Include user role in cache key for role-specific content
        if (isset($_SESSION['role'])) {
            $key .= ':' . $_SESSION['role'];
        }
        
        // Include query parameters that affect content
        $relevantParams = ['page', 'limit', 'sort', 'filter'];
        $queryParams = [];
        
        foreach ($relevantParams as $param) {
            if (isset($_GET[$param])) {
                $queryParams[$param] = $_GET[$param];
            }
        }
        
        if (!empty($queryParams)) {
            $key .= ':' . md5(serialize($queryParams));
        }
        
        return $key;
    }
    
    /**
     * Check if request is for an asset
     */
    private function isAssetRequest(string $uri): bool
    {
        $assetExtensions = ['.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico', '.woff', '.woff2', '.ttf'];
        
        foreach ($assetExtensions as $ext) {
            if (substr($uri, -strlen($ext)) === $ext) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if content is binary
     */
    private function isBinaryContent(): bool
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        $binaryTypes = [
            'image/',
            'video/',
            'audio/',
            'application/octet-stream',
            'application/pdf',
            'application/zip'
        ];
        
        foreach ($binaryTypes as $type) {
            if (strpos($contentType, $type) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if response is HTML
     */
    private function isHtmlResponse(): bool
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        return strpos($contentType, 'text/html') === 0 || empty($contentType);
    }
    
    /**
     * Check if content is static
     */
    private function isStaticContent(string $uri): bool
    {
        return $this->isAssetRequest($uri) || 
               strpos($uri, '/assets/') !== false ||
               strpos($uri, '/static/') !== false;
    }
    
    /**
     * Get cache TTL based on content type
     */
    private function getCacheTTL(string $uri): int
    {
        if ($this->isStaticContent($uri)) {
            return $this->config['cache_ttl']['static'];
        }
        
        if (strpos($uri, '/api/') !== false) {
            return $this->config['cache_ttl']['api'];
        }
        
        return $this->config['cache_ttl']['dynamic'];
    }
    
    /**
     * Get current response headers
     */
    private function getResponseHeaders(): array
    {
        if (function_exists('headers_list')) {
            return headers_list();
        }
        
        return [];
    }
    
    /**
     * Minify HTML content
     */
    private function minifyHtml(string $html): string
    {
        // Remove comments (but keep IE conditional comments)
        $html = preg_replace('/<!--(?!\s*(?:\[if [^\]]+\]|<!|>))(?:(?!-->).)*-->/s', '', $html);
        
        // Remove extra whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        
        // Remove whitespace around block elements
        $html = preg_replace('/>\s+</', '><', $html);
        
        return trim($html);
    }
    
    /**
     * Optimize asset references in HTML
     */
    private function optimizeAssetReferences(string $html): string
    {
        // Replace asset URLs with optimized versions
        $html = preg_replace_callback(
            '/<link[^>]+href=["\']([^"\']+\.css)["\'][^>]*>/',
            [$this, 'optimizeCssReference'],
            $html
        );
        
        $html = preg_replace_callback(
            '/<script[^>]+src=["\']([^"\']+\.js)["\'][^>]*>/',
            [$this, 'optimizeJsReference'],
            $html
        );
        
        return $html;
    }
    
    /**
     * Add performance hints to HTML
     */
    private function addPerformanceHints(string $html): string
    {
        // Add preload hints for critical resources
        $hints = [
            '<link rel="preload" href="/assets/css/critical.css" as="style">',
            '<link rel="dns-prefetch" href="//fonts.googleapis.com">',
            '<link rel="preconnect" href="//fonts.gstatic.com" crossorigin>'
        ];
        
        // Insert hints before closing head tag
        $html = str_replace('</head>', implode("\n", $hints) . "\n</head>", $html);
        
        return $html;
    }
    
    /**
     * Optimize CSS reference
     */
    private function optimizeCssReference(array $matches): string
    {
        $originalUrl = $matches[1];
        $optimizedUrl = $this->assetOptimizer->getOptimizedAssetUrl($originalUrl);
        
        if ($optimizedUrl !== $originalUrl) {
            return str_replace($originalUrl, $optimizedUrl, $matches[0]);
        }
        
        return $matches[0];
    }
    
    /**
     * Optimize JS reference
     */
    private function optimizeJsReference(array $matches): string
    {
        $originalUrl = $matches[1];
        $optimizedUrl = $this->assetOptimizer->getOptimizedAssetUrl($originalUrl);
        
        if ($optimizedUrl !== $originalUrl) {
            return str_replace($originalUrl, $optimizedUrl, $matches[0]);
        }
        
        return $matches[0];
    }
    
    /**
     * Get MIME type for file
     */
    private function getMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf'
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
    
    /**
     * Store performance metrics
     */
    private function storePerformanceMetrics(array $metrics): void
    {
        try {
            // Store in cache for real-time access
            $metricsKey = 'performance_metrics:' . date('Y-m-d-H');
            $existingMetrics = $this->cacheService->get($metricsKey, 'performance') ?? [];
            $existingMetrics[] = $metrics;
            
            // Keep only last 1000 entries per hour
            if (count($existingMetrics) > 1000) {
                $existingMetrics = array_slice($existingMetrics, -1000);
            }
            
            $this->cacheService->set($metricsKey, $existingMetrics, 3600, 'performance');
            
            // Also log slow requests
            if ($metrics['execution_time'] > 1.0) {
                $this->logger->warning('Slow request detected', $metrics);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Failed to store performance metrics', [
                'error' => $e->getMessage(),
                'metrics' => $metrics
            ]);
        }
    }
    
    /**
     * Update performance counters
     */
    private function updatePerformanceCounters(array $metrics): void
    {
        try {
            $countersKey = 'performance_counters';
            $counters = $this->cacheService->get($countersKey, 'performance') ?? [
                'total_requests' => 0,
                'total_execution_time' => 0,
                'total_memory_usage' => 0,
                'slow_requests' => 0,
                'last_updated' => time()
            ];
            
            $counters['total_requests']++;
            $counters['total_execution_time'] += $metrics['execution_time'];
            $counters['total_memory_usage'] += $metrics['memory_usage'];
            
            if ($metrics['execution_time'] > 1.0) {
                $counters['slow_requests']++;
            }
            
            $counters['last_updated'] = time();
            $counters['average_execution_time'] = $counters['total_execution_time'] / $counters['total_requests'];
            $counters['average_memory_usage'] = $counters['total_memory_usage'] / $counters['total_requests'];
            
            $this->cacheService->set($countersKey, $counters, 86400, 'performance');
            
        } catch (Exception $e) {
            $this->logger->error('Failed to update performance counters', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
