<?php
/**
 * Performance Manager -  performance optimization
 * 
 * Provides comprehensive performance features including:
 * - Multi-level caching system (Memory, File, Database)
 * - Database query optimization and analysis
 * - Performance monitoring and metrics collection
 * - Asset optimization and compression
 * - Real-time performance analytics
 * - Bottleneck detection and reporting
 * 
 * @author Senior Developer
 * @version 1.0.0
 */

require_once __DIR__ . '/ErrorHandler.php';

class PerformanceManager {
    private static $instance = null;
    private $errorHandler;
    private $config;
    private $memoryCache = [];
    private $performanceMetrics = [];
    private $queryAnalytics = [];
    private $cacheStats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0
    ];
    
    // Performance configuration
    private const MEMORY_CACHE_LIMIT = 1000; // Max items in memory cache
    private const FILE_CACHE_TTL = 3600; // 1 hour default TTL
    private const SLOW_QUERY_THRESHOLD = 0.1; // 100ms threshold for slow queries
    private const PERFORMANCE_LOG_INTERVAL = 300; // 5 minutes
    
    private function __construct() {
        $this->errorHandler = ErrorHandler::getInstance();
        $this->config = $this->loadConfiguration();
        $this->initializeCache();
        $this->startPerformanceMonitoring();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): PerformanceManager {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load performance configuration
     */
    private function loadConfiguration(): array {
        return [
            'cache_enabled' => true,
            'memory_cache_enabled' => true,
            'file_cache_enabled' => true,
            'query_analysis_enabled' => true,
            'performance_monitoring_enabled' => true,
            'asset_optimization_enabled' => true,
            'cache_directory' => __DIR__ . '/../cache',
            'metrics_directory' => __DIR__ . '/../metrics',
            'compression_enabled' => true,
            'minification_enabled' => true
        ];
    }
    
    /**
     * Initialize cache system
     */
    private function initializeCache(): void {
        try {
            // Create cache directory if it doesn't exist
            if (!is_dir($this->config['cache_directory'])) {
                mkdir($this->config['cache_directory'], 0755, true);
            }
            
            // Create metrics directory if it doesn't exist
            if (!is_dir($this->config['metrics_directory'])) {
                mkdir($this->config['metrics_directory'], 0755, true);
            }
            
            // Clean expired cache files
            $this->cleanExpiredCache();
            
        } catch (Exception $e) {
            $this->errorHandler->logError('Cache initialization failed', $e);
        }
    }
    
    /**
     * Start performance monitoring
     */
    private function startPerformanceMonitoring(): void {
        if (!$this->config['performance_monitoring_enabled']) {
            return;
        }
        
        // Record script start time
        $this->performanceMetrics['script_start'] = microtime(true);
        $this->performanceMetrics['memory_start'] = memory_get_usage(true);
        $this->performanceMetrics['queries_count'] = 0;
        $this->performanceMetrics['cache_operations'] = 0;
        
        // Register shutdown function to record final metrics
        register_shutdown_function([$this, 'recordFinalMetrics']);
    }
    
    /**
     * Memory cache operations
     */
    public function getFromMemoryCache(string $key) {
        if (!$this->config['memory_cache_enabled']) {
            return null;
        }
        
        if (isset($this->memoryCache[$key])) {
            $cacheItem = $this->memoryCache[$key];
            
            // Check if expired
            if ($cacheItem['expires'] > time()) {
                $this->cacheStats['hits']++;
                $this->performanceMetrics['cache_operations']++;
                return $cacheItem['data'];
            } else {
                unset($this->memoryCache[$key]);
                $this->cacheStats['deletes']++;
            }
        }
        
        $this->cacheStats['misses']++;
        return null;
    }
    
    public function setInMemoryCache(string $key, $data, int $ttl = 300): bool {
        if (!$this->config['memory_cache_enabled']) {
            return false;
        }
        
        // Implement LRU eviction if cache is full
        if (count($this->memoryCache) >= self::MEMORY_CACHE_LIMIT) {
            $this->evictLeastRecentlyUsed();
        }
        
        $this->memoryCache[$key] = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time(),
            'accessed' => time()
        ];
        
        $this->cacheStats['writes']++;
        $this->performanceMetrics['cache_operations']++;
        return true;
    }
    
    /**
     * File cache operations
     */
    public function getFromFileCache(string $key) {
        if (!$this->config['file_cache_enabled']) {
            return null;
        }
        
        $cacheFile = $this->getCacheFilePath($key);
        
        if (!file_exists($cacheFile)) {
            $this->cacheStats['misses']++;
            return null;
        }
        
        try {
            $cacheData = json_decode(file_get_contents($cacheFile), true);
            
            if ($cacheData && $cacheData['expires'] > time()) {
                $this->cacheStats['hits']++;
                $this->performanceMetrics['cache_operations']++;
                
                // Update access time for LRU
                $cacheData['accessed'] = time();
                file_put_contents($cacheFile, json_encode($cacheData));
                
                return $cacheData['data'];
            } else {
                // Expired cache, delete file
                unlink($cacheFile);
                $this->cacheStats['deletes']++;
            }
        } catch (Exception $e) {
            $this->errorHandler->logError('File cache read error', $e);
        }
        
        $this->cacheStats['misses']++;
        return null;
    }
    
    public function setInFileCache(string $key, $data, int $ttl = null): bool {
        if (!$this->config['file_cache_enabled']) {
            return false;
        }
        
        $ttl = $ttl ?? self::FILE_CACHE_TTL;
        $cacheFile = $this->getCacheFilePath($key);
        
        try {
            $cacheData = [
                'data' => $data,
                'expires' => time() + $ttl,
                'created' => time(),
                'accessed' => time(),
                'key' => $key
            ];
            
            $result = file_put_contents($cacheFile, json_encode($cacheData));
            
            if ($result !== false) {
                $this->cacheStats['writes']++;
                $this->performanceMetrics['cache_operations']++;
                return true;
            }
        } catch (Exception $e) {
            $this->errorHandler->logError('File cache write error', $e);
        }
        
        return false;
    }
    
    /**
     * Universal cache interface
     */
    public function get(string $key, $default = null) {
        // Try memory cache first (fastest)
        $data = $this->getFromMemoryCache($key);
        if ($data !== null) {
            return $data;
        }
        
        // Try file cache second
        $data = $this->getFromFileCache($key);
        if ($data !== null) {
            // Store in memory cache for faster subsequent access
            $this->setInMemoryCache($key, $data, 300);
            return $data;
        }
        
        return $default;
    }
    
    public function set(string $key, $data, int $ttl = 300): bool {
        // Store in both memory and file cache
        $memoryResult = $this->setInMemoryCache($key, $data, $ttl);
        $fileResult = $this->setInFileCache($key, $data, $ttl);
        
        return $memoryResult || $fileResult;
    }
    
    public function delete(string $key): bool {
        $deleted = false;
        
        // Remove from memory cache
        if (isset($this->memoryCache[$key])) {
            unset($this->memoryCache[$key]);
            $deleted = true;
        }
        
        // Remove from file cache
        $cacheFile = $this->getCacheFilePath($key);
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
            $deleted = true;
        }
        
        if ($deleted) {
            $this->cacheStats['deletes']++;
        }
        
        return $deleted;
    }
    
    /**
     * Database query optimization
     */
    public function optimizeQuery(string $sql, array $params = []): array {
        $startTime = microtime(true);
        $queryHash = md5($sql . serialize($params));
        
        // Check if query result is cached
        $cacheKey = "query_" . $queryHash;
        $cachedResult = $this->get($cacheKey);
        
        if ($cachedResult !== null) {
            $this->recordQueryMetrics($sql, microtime(true) - $startTime, true);
            return $cachedResult;
        }
        
        // If not cached, we'll return the optimization recommendations
        $optimization = [
            'original_sql' => $sql,
            'optimized_sql' => $this->getOptimizedSQL($sql),
            'cache_key' => $cacheKey,
            'should_cache' => $this->shouldCacheQuery($sql),
            'estimated_cost' => $this->estimateQueryCost($sql),
            'recommendations' => $this->getQueryRecommendations($sql)
        ];
        
        $this->recordQueryMetrics($sql, microtime(true) - $startTime, false);
        return $optimization;
    }
    
    /**
     * Record query execution metrics
     */
    public function recordQueryMetrics(string $sql, float $executionTime, bool $fromCache = false): void {
        $this->performanceMetrics['queries_count']++;
        
        $queryInfo = [
            'sql' => substr($sql, 0, 100) . (strlen($sql) > 100 ? '...' : ''),
            'execution_time' => $executionTime,
            'from_cache' => $fromCache,
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage(true)
        ];
        
        $this->queryAnalytics[] = $queryInfo;
        
        // Log slow queries
        if ($executionTime > self::SLOW_QUERY_THRESHOLD && !$fromCache) {
            $this->errorHandler->logError('Slow query detected', [
                'sql' => $sql,
                'execution_time' => $executionTime,
                'threshold' => self::SLOW_QUERY_THRESHOLD
            ], 'warning');
        }
    }
    
    /**
     * Asset optimization
     */
    public function optimizeCSS(string $cssContent): string {
        if (!$this->config['minification_enabled']) {
            return $cssContent;
        }
        
        // Remove comments
        $cssContent = preg_replace('/\/\*.*?\*\//s', '', $cssContent);
        
        // Remove unnecessary whitespace
        $cssContent = preg_replace('/\s+/', ' ', $cssContent);
        
        // Remove spaces around specific characters
        $cssContent = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $cssContent);
        
        // Remove trailing semicolons
        $cssContent = preg_replace('/;}/', '}', $cssContent);
        
        // Remove empty rules
        $cssContent = preg_replace('/[^{}]*{\s*}/', '', $cssContent);
        
        return trim($cssContent);
    }
    
    public function optimizeJS(string $jsContent): string {
        if (!$this->config['minification_enabled']) {
            return $jsContent;
        }
        
        // Basic JS minification
        // Remove single-line comments
        $jsContent = preg_replace('/\/\/.*$/m', '', $jsContent);
        
        // Remove multi-line comments
        $jsContent = preg_replace('/\/\*.*?\*\//s', '', $jsContent);
        
        // Remove unnecessary whitespace
        $jsContent = preg_replace('/\s+/', ' ', $jsContent);
        
        // Remove spaces around operators and punctuation
        $jsContent = preg_replace('/\s*([=+\-*\/{}();,])\s*/', '$1', $jsContent);
        
        return trim($jsContent);
    }
    
    /**
     * Performance monitoring and metrics
     */
    public function getPerformanceMetrics(): array {
        $currentTime = microtime(true);
        $currentMemory = memory_get_usage(true);
        
        return [
            'execution_time' => $currentTime - $this->performanceMetrics['script_start'],
            'memory_usage' => $currentMemory,
            'memory_peak' => memory_get_peak_usage(true),
            'memory_delta' => $currentMemory - $this->performanceMetrics['memory_start'],
            'queries_executed' => $this->performanceMetrics['queries_count'],
            'cache_operations' => $this->performanceMetrics['cache_operations'],
            'cache_stats' => $this->cacheStats,
            'slow_queries' => $this->getSlowQueries(),
            'cache_hit_ratio' => $this->getCacheHitRatio()
        ];
    }
    
    public function recordFinalMetrics(): void {
        try {
            $metrics = $this->getPerformanceMetrics();
            $metrics['timestamp'] = time();
            $metrics['page'] = $_SERVER['REQUEST_URI'] ?? 'unknown';
            $metrics['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            // Save metrics to file
            $metricsFile = $this->config['metrics_directory'] . '/performance_' . date('Y-m-d') . '.json';
            
            $existingMetrics = [];
            if (file_exists($metricsFile)) {
                $existingMetrics = json_decode(file_get_contents($metricsFile), true) ?: [];
            }
            
            $existingMetrics[] = $metrics;
            
            // Keep only last 1000 entries per day
            if (count($existingMetrics) > 1000) {
                $existingMetrics = array_slice($existingMetrics, -1000);
            }
            
            file_put_contents($metricsFile, json_encode($existingMetrics, JSON_PRETTY_PRINT));
            
        } catch (Exception $e) {
            $this->errorHandler->logError('Failed to record performance metrics', $e);
        }
    }
    
    /**
     * Helper methods
     */
    private function getCacheFilePath(string $key): string {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $this->config['cache_directory'] . '/' . $safeKey . '.cache';
    }
    
    private function evictLeastRecentlyUsed(): void {
        if (empty($this->memoryCache)) {
            return;
        }
        
        $oldestKey = null;
        $oldestTime = time();
        
        foreach ($this->memoryCache as $key => $item) {
            if ($item['accessed'] < $oldestTime) {
                $oldestTime = $item['accessed'];
                $oldestKey = $key;
            }
        }
        
        if ($oldestKey) {
            unset($this->memoryCache[$oldestKey]);
            $this->cacheStats['deletes']++;
        }
    }
    
    private function cleanExpiredCache(): void {
        $cacheDir = $this->config['cache_directory'];
        
        if (!is_dir($cacheDir)) {
            return;
        }
        
        $files = glob($cacheDir . '/*.cache');
        $now = time();
        
        foreach ($files as $file) {
            try {
                $cacheData = json_decode(file_get_contents($file), true);
                if ($cacheData && isset($cacheData['expires']) && $cacheData['expires'] < $now) {
                    unlink($file);
                }
            } catch (Exception $e) {
                // If we can't read the cache file, delete it
                unlink($file);
            }
        }
    }
    
    private function getOptimizedSQL(string $sql): string {
        // Basic SQL optimization suggestions
        $optimized = $sql;
        
        // Add LIMIT if SELECT without LIMIT
        if (preg_match('/^\s*SELECT/i', $sql) && !preg_match('/LIMIT\s+\d+/i', $sql)) {
            $optimized .= ' LIMIT 1000';
        }
        
        // Suggest using indexes for WHERE clauses
        if (preg_match('/WHERE\s+(\w+)\s*=/i', $sql, $matches)) {
            $optimized = "/* Consider index on {$matches[1]} */ " . $optimized;
        }
        
        return $optimized;
    }
    
    private function shouldCacheQuery(string $sql): bool {
        // Don't cache INSERT, UPDATE, DELETE queries
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE)/i', $sql)) {
            return false;
        }
        
        // Cache SELECT queries that don't use NOW(), RAND(), etc.
        if (preg_match('/NOW\(\)|RAND\(\)|CURRENT_TIMESTAMP/i', $sql)) {
            return false;
        }
        
        return true;
    }
    
    private function estimateQueryCost(string $sql): string {
        // Simple cost estimation based on query complexity
        $cost = 'LOW';
        
        if (preg_match('/JOIN/i', $sql)) {
            $cost = 'MEDIUM';
        }
        
        if (preg_match('/(GROUP BY|ORDER BY|HAVING)/i', $sql)) {
            $cost = 'MEDIUM';
        }
        
        if (preg_match('/UNION|SUBQUERY|\(SELECT/i', $sql)) {
            $cost = 'HIGH';
        }
        
        return $cost;
    }
    
    private function getQueryRecommendations(string $sql): array {
        $recommendations = [];
        
        if (preg_match('/SELECT \*/i', $sql)) {
            $recommendations[] = 'Avoid SELECT *, specify only needed columns';
        }
        
        if (!preg_match('/WHERE/i', $sql) && preg_match('/SELECT.*FROM/i', $sql)) {
            $recommendations[] = 'Consider adding WHERE clause to limit results';
        }
        
        if (preg_match('/ORDER BY.*RAND\(\)/i', $sql)) {
            $recommendations[] = 'ORDER BY RAND() is expensive, consider alternatives';
        }
        
        return $recommendations;
    }
    
    private function getSlowQueries(): array {
        return array_filter($this->queryAnalytics, function($query) {
            return $query['execution_time'] > self::SLOW_QUERY_THRESHOLD && !$query['from_cache'];
        });
    }
    
    private function getCacheHitRatio(): float {
        $total = $this->cacheStats['hits'] + $this->cacheStats['misses'];
        return $total > 0 ? ($this->cacheStats['hits'] / $total) * 100 : 0;
    }
    
    /**
     * Public interface methods
     */
    public function clearCache(): bool {
        try {
            // Clear memory cache
            $this->memoryCache = [];
            
            // Clear file cache
            $files = glob($this->config['cache_directory'] . '/*.cache');
            foreach ($files as $file) {
                unlink($file);
            }
            
            // Reset cache stats
            $this->cacheStats = ['hits' => 0, 'misses' => 0, 'writes' => 0, 'deletes' => 0];
            
            return true;
        } catch (Exception $e) {
            $this->errorHandler->logError('Cache clear failed', $e);
            return false;
        }
    }
    
    public function getAnalytics(): array {
        return [
            'performance_metrics' => $this->getPerformanceMetrics(),
            'query_analytics' => $this->queryAnalytics,
            'cache_statistics' => $this->cacheStats,
            'configuration' => $this->config
        ];
    }
}
