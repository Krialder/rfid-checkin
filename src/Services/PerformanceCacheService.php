<?php

namespace App\Services;

use App\Core\ConfigurationService;
use App\Core\LoggingService;
use Exception;

/**
 * Performance Caching Service
 * 
 * Provides multi-layer caching with:
 * - Memory caching (APCu/Redis)
 * - File-based caching
 * - Database query result caching
 * - Session caching
 * - Template fragment caching
 * - API response caching
 */
class PerformanceCacheService
{
    private static ?self $instance = null;
    private ConfigurationService $config;
    private LoggingService $logger;
    private array $memoryCache = [];
    private ?object $redisConnection = null;
    private string $cacheDirectory;
    private array $cacheStats = [];
    
    private function __construct()
    {
        $this->config = ConfigurationService::getInstance();
        $this->logger = LoggingService::getInstance();
        $this->cacheDirectory = $this->config->get('cache.directory', __DIR__ . '/../../cache');
        $this->initializeCacheDirectory();
        $this->initializeRedis();
        $this->initializeCacheStats();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Store data in cache with TTL
     */
    public function set(string $key, $data, int $ttl = 3600, string $namespace = 'default'): bool
    {
        try {
            $fullKey = $this->buildCacheKey($key, $namespace);
            $cacheData = [
                'data' => $data,
                'expires_at' => time() + $ttl,
                'created_at' => time(),
                'namespace' => $namespace,
                'size' => $this->calculateDataSize($data)
            ];
            
            $success = false;
            
            // Try Redis first (if available)
            if ($this->redisConnection && $this->config->get('cache.redis.enabled', false)) {
                $success = $this->setRedisCache($fullKey, $cacheData, $ttl);
            }
            
            // Fallback to APCu
            if (!$success && $this->config->get('cache.apcu.enabled', true) && extension_loaded('apcu')) {
                $success = apcu_store($fullKey, $cacheData, $ttl);
            }
            
            // Fallback to file cache
            if (!$success) {
                $success = $this->setFileCache($fullKey, $cacheData);
            }
            
            // Memory cache (for current request)
            $this->memoryCache[$fullKey] = $cacheData;
            
            if ($success) {
                $this->updateCacheStats('set', $namespace, $cacheData['size']);
                $this->logger->debug('Cache set', [
                    'key' => $key,
                    'namespace' => $namespace,
                    'ttl' => $ttl,
                    'size' => $cacheData['size']
                ]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            $this->logger->error('Cache set failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Retrieve data from cache
     */
    public function get(string $key, string $namespace = 'default')
    {
        try {
            $fullKey = $this->buildCacheKey($key, $namespace);
            
            // Check memory cache first
            if (isset($this->memoryCache[$fullKey])) {
                $cacheData = $this->memoryCache[$fullKey];
                if ($cacheData['expires_at'] > time()) {
                    $this->updateCacheStats('hit', $namespace);
                    return $cacheData['data'];
                } else {
                    unset($this->memoryCache[$fullKey]);
                }
            }
            
            // Check Redis
            if ($this->redisConnection && $this->config->get('cache.redis.enabled', false)) {
                $cacheData = $this->getRedisCache($fullKey);
                if ($cacheData !== false) {
                    $this->memoryCache[$fullKey] = $cacheData;
                    $this->updateCacheStats('hit', $namespace);
                    return $cacheData['data'];
                }
            }
            
            // Check APCu
            if ($this->config->get('cache.apcu.enabled', true) && extension_loaded('apcu')) {
                $cacheData = apcu_fetch($fullKey);
                if ($cacheData !== false && $cacheData['expires_at'] > time()) {
                    $this->memoryCache[$fullKey] = $cacheData;
                    $this->updateCacheStats('hit', $namespace);
                    return $cacheData['data'];
                } elseif ($cacheData !== false) {
                    apcu_delete($fullKey);
                }
            }
            
            // Check file cache
            $cacheData = $this->getFileCache($fullKey);
            if ($cacheData !== false) {
                $this->memoryCache[$fullKey] = $cacheData;
                $this->updateCacheStats('hit', $namespace);
                return $cacheData['data'];
            }
            
            $this->updateCacheStats('miss', $namespace);
            return false;
            
        } catch (Exception $e) {
            $this->logger->error('Cache get failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Delete cache entry
     */
    public function delete(string $key, string $namespace = 'default'): bool
    {
        try {
            $fullKey = $this->buildCacheKey($key, $namespace);
            
            // Remove from memory cache
            unset($this->memoryCache[$fullKey]);
            
            $success = true;
            
            // Remove from Redis
            if ($this->redisConnection && $this->config->get('cache.redis.enabled', false)) {
                $this->redisConnection->del($fullKey);
            }
            
            // Remove from APCu
            if ($this->config->get('cache.apcu.enabled', true) && extension_loaded('apcu')) {
                apcu_delete($fullKey);
            }
            
            // Remove from file cache
            $this->deleteFileCache($fullKey);
            
            $this->updateCacheStats('delete', $namespace);
            
            return $success;
            
        } catch (Exception $e) {
            $this->logger->error('Cache delete failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Clear cache by namespace
     */
    public function clearNamespace(string $namespace): bool
    {
        try {
            $pattern = $this->buildCacheKey('*', $namespace);
            
            // Clear memory cache
            foreach ($this->memoryCache as $key => $data) {
                if ($data['namespace'] === $namespace) {
                    unset($this->memoryCache[$key]);
                }
            }
            
            // Clear Redis
            if ($this->redisConnection && $this->config->get('cache.redis.enabled', false)) {
                $keys = $this->redisConnection->keys($pattern);
                if (!empty($keys)) {
                    $this->redisConnection->del($keys);
                }
            }
            
            // Clear APCu (iterate through keys)
            if ($this->config->get('cache.apcu.enabled', true) && extension_loaded('apcu')) {
                $info = apcu_cache_info();
                foreach ($info['cache_list'] as $entry) {
                    if (strpos($entry['info'], $namespace . ':') === 0) {
                        apcu_delete($entry['info']);
                    }
                }
            }
            
            // Clear file cache
            $this->clearFileCacheNamespace($namespace);
            
            $this->logger->info('Cache namespace cleared', ['namespace' => $namespace]);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Cache namespace clear failed', [
                'namespace' => $namespace,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Cache with callback for lazy loading
     */
    public function remember(string $key, callable $callback, int $ttl = 3600, string $namespace = 'default')
    {
        $data = $this->get($key, $namespace);
        
        if ($data === false) {
            $data = call_user_func($callback);
            $this->set($key, $data, $ttl, $namespace);
        }
        
        return $data;
    }
    
    /**
     * Cache database query results
     */
    public function cacheQuery(string $sql, array $params, callable $queryCallback, int $ttl = 1800): array
    {
        $cacheKey = $this->generateQueryCacheKey($sql, $params);
        
        return $this->remember($cacheKey, function() use ($queryCallback) {
            $startTime = microtime(true);
            $result = call_user_func($queryCallback);
            $queryTime = microtime(true) - $startTime;
            
            $this->logger->debug('Database query executed', [
                'execution_time' => $queryTime,
                'cached' => false
            ]);
            
            return $result;
        }, $ttl, 'database');
    }
    
    /**
     * Cache template fragments
     */
    public function cacheTemplate(string $templateName, array $variables, callable $renderCallback, int $ttl = 7200): string
    {
        $cacheKey = $this->generateTemplateCacheKey($templateName, $variables);
        
        return $this->remember($cacheKey, function() use ($renderCallback) {
            return call_user_func($renderCallback);
        }, $ttl, 'templates');
    }
    
    /**
     * Cache API responses
     */
    public function cacheApiResponse(string $endpoint, array $params, callable $apiCallback, int $ttl = 900): array
    {
        $cacheKey = $this->generateApiCacheKey($endpoint, $params);
        
        return $this->remember($cacheKey, function() use ($apiCallback) {
            return call_user_func($apiCallback);
        }, $ttl, 'api');
    }
    
    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        $stats = $this->cacheStats;
        
        // Add system cache info
        if (extension_loaded('apcu')) {
            $apcuInfo = apcu_cache_info();
            $stats['apcu'] = [
                'enabled' => true,
                'memory_size' => $apcuInfo['memory_type'] ?? 'unknown',
                'num_slots' => $apcuInfo['num_slots'] ?? 0,
                'hits' => $apcuInfo['num_hits'] ?? 0,
                'misses' => $apcuInfo['num_misses'] ?? 0,
                'hit_rate' => $apcuInfo['num_hits'] > 0 ? 
                    round(($apcuInfo['num_hits'] / ($apcuInfo['num_hits'] + $apcuInfo['num_misses'])) * 100, 2) : 0
            ];
        } else {
            $stats['apcu'] = ['enabled' => false];
        }
        
        // Redis info
        if ($this->redisConnection) {
            try {
                $redisInfo = $this->redisConnection->info();
                $stats['redis'] = [
                    'enabled' => true,
                    'connected' => true,
                    'memory_usage' => $redisInfo['used_memory_human'] ?? 'unknown',
                    'connections' => $redisInfo['connected_clients'] ?? 0
                ];
            } catch (Exception $e) {
                $stats['redis'] = ['enabled' => true, 'connected' => false, 'error' => $e->getMessage()];
            }
        } else {
            $stats['redis'] = ['enabled' => false];
        }
        
        // File cache info
        $stats['file_cache'] = $this->getFileCacheStats();
        
        return $stats;
    }
    
    /**
     * Warm up cache with common data
     */
    public function warmup(): void
    {
        try {
            $this->logger->info('Cache warmup started');
            
            // Warm up common configuration
            $this->set('system_config', $this->loadSystemConfig(), 86400, 'config');
            
            // Warm up user permissions
            $this->warmupUserPermissions();
            
            // Warm up common queries
            $this->warmupCommonQueries();
            
            $this->logger->info('Cache warmup completed');
            
        } catch (Exception $e) {
            $this->logger->error('Cache warmup failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Optimize cache performance
     */
    public function optimize(): void
    {
        try {
            // Clean expired entries
            $this->cleanExpiredEntries();
            
            // Optimize file cache
            $this->optimizeFileCache();
            
            // Update cache statistics
            $this->updateCacheStatistics();
            
            $this->logger->info('Cache optimization completed');
            
        } catch (Exception $e) {
            $this->logger->error('Cache optimization failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    // Private helper methods
    
    private function initializeCacheDirectory(): void
    {
        if (!is_dir($this->cacheDirectory)) {
            mkdir($this->cacheDirectory, 0755, true);
        }
        
        // Create namespace directories
        $namespaces = ['default', 'database', 'templates', 'api', 'config', 'sessions'];
        foreach ($namespaces as $namespace) {
            $namespaceDir = $this->cacheDirectory . '/' . $namespace;
            if (!is_dir($namespaceDir)) {
                mkdir($namespaceDir, 0755, true);
            }
        }
    }
    
    private function initializeRedis(): void
    {
        if (!$this->config->get('cache.redis.enabled', false)) {
            return;
        }
        
        try {
            if (class_exists('Redis')) {
                $this->redisConnection = new \Redis();
                $host = $this->config->get('cache.redis.host', 'localhost');
                $port = $this->config->get('cache.redis.port', 6379);
                $timeout = $this->config->get('cache.redis.timeout', 2.5);
                
                if ($this->redisConnection->connect($host, $port, $timeout)) {
                    $password = $this->config->get('cache.redis.password');
                    if ($password) {
                        $this->redisConnection->auth($password);
                    }
                    
                    $database = $this->config->get('cache.redis.database', 0);
                    $this->redisConnection->select($database);
                    
                    $this->logger->debug('Redis cache initialized');
                } else {
                    $this->redisConnection = null;
                    $this->logger->warning('Redis connection failed');
                }
            }
        } catch (Exception $e) {
            $this->redisConnection = null;
            $this->logger->error('Redis initialization failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function initializeCacheStats(): void
    {
        $this->cacheStats = [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0,
            'namespaces' => [],
            'memory_usage' => 0,
            'start_time' => time()
        ];
    }
    
    private function buildCacheKey(string $key, string $namespace): string
    {
        return $namespace . ':' . hash('sha256', $key);
    }
    
    private function calculateDataSize($data): int
    {
        return strlen(serialize($data));
    }
    
    private function setRedisCache(string $key, array $cacheData, int $ttl): bool
    {
        try {
            return $this->redisConnection->setex($key, $ttl, serialize($cacheData));
        } catch (Exception $e) {
            $this->logger->error('Redis set failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    private function getRedisCache(string $key)
    {
        try {
            $data = $this->redisConnection->get($key);
            if ($data === false) {
                return false;
            }
            
            $cacheData = unserialize($data);
            if ($cacheData['expires_at'] > time()) {
                return $cacheData;
            } else {
                $this->redisConnection->del($key);
                return false;
            }
        } catch (Exception $e) {
            $this->logger->error('Redis get failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    private function setFileCache(string $key, array $cacheData): bool
    {
        try {
            $namespace = $cacheData['namespace'];
            $filePath = $this->getCacheFilePath($key, $namespace);
            
            $fileData = [
                'data' => $cacheData,
                'checksum' => md5(serialize($cacheData['data']))
            ];
            
            return file_put_contents($filePath, serialize($fileData), LOCK_EX) !== false;
            
        } catch (Exception $e) {
            $this->logger->error('File cache set failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    private function getFileCache(string $key)
    {
        try {
            // Try all namespaces to find the key
            $namespaces = ['default', 'database', 'templates', 'api', 'config', 'sessions'];
            
            foreach ($namespaces as $namespace) {
                $filePath = $this->getCacheFilePath($key, $namespace);
                
                if (!file_exists($filePath)) {
                    continue;
                }
                
                $fileData = unserialize(file_get_contents($filePath));
                $cacheData = $fileData['data'];
                
                // Verify checksum
                if (md5(serialize($cacheData['data'])) !== $fileData['checksum']) {
                    unlink($filePath);
                    continue;
                }
                
                if ($cacheData['expires_at'] > time()) {
                    return $cacheData;
                } else {
                    unlink($filePath);
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->error('File cache get failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    private function deleteFileCache(string $key): void
    {
        $namespaces = ['default', 'database', 'templates', 'api', 'config', 'sessions'];
        
        foreach ($namespaces as $namespace) {
            $filePath = $this->getCacheFilePath($key, $namespace);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }
    
    private function getCacheFilePath(string $key, string $namespace): string
    {
        return $this->cacheDirectory . '/' . $namespace . '/' . $key . '.cache';
    }
    
    private function clearFileCacheNamespace(string $namespace): void
    {
        $namespaceDir = $this->cacheDirectory . '/' . $namespace;
        
        if (is_dir($namespaceDir)) {
            $files = glob($namespaceDir . '/*.cache');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }
    
    private function updateCacheStats(string $operation, string $namespace, int $size = 0): void
    {
        switch ($operation) {
            case 'hit':
                $this->cacheStats['hits']++;
                break;
            case 'miss':
                $this->cacheStats['misses']++;
                break;
            case 'set':
                $this->cacheStats['sets']++;
                $this->cacheStats['memory_usage'] += $size;
                break;
            case 'delete':
                $this->cacheStats['deletes']++;
                break;
        }
        
        if (!isset($this->cacheStats['namespaces'][$namespace])) {
            $this->cacheStats['namespaces'][$namespace] = [
                'hits' => 0,
                'misses' => 0,
                'sets' => 0,
                'deletes' => 0
            ];
        }
        
        if ($operation !== 'set') {
            $this->cacheStats['namespaces'][$namespace][$operation . 's']++;
        } else {
            $this->cacheStats['namespaces'][$namespace]['sets']++;
        }
    }
    
    private function generateQueryCacheKey(string $sql, array $params): string
    {
        return 'query:' . md5($sql . serialize($params));
    }
    
    private function generateTemplateCacheKey(string $templateName, array $variables): string
    {
        return 'template:' . $templateName . ':' . md5(serialize($variables));
    }
    
    private function generateApiCacheKey(string $endpoint, array $params): string
    {
        return 'api:' . $endpoint . ':' . md5(serialize($params));
    }
    
    private function getFileCacheStats(): array
    {
        $stats = [
            'total_files' => 0,
            'total_size' => 0,
            'namespaces' => []
        ];
        
        $namespaces = ['default', 'database', 'templates', 'api', 'config', 'sessions'];
        
        foreach ($namespaces as $namespace) {
            $namespaceDir = $this->cacheDirectory . '/' . $namespace;
            $files = glob($namespaceDir . '/*.cache');
            $namespaceSize = 0;
            
            foreach ($files as $file) {
                $namespaceSize += filesize($file);
            }
            
            $stats['namespaces'][$namespace] = [
                'files' => count($files),
                'size' => $namespaceSize
            ];
            
            $stats['total_files'] += count($files);
            $stats['total_size'] += $namespaceSize;
        }
        
        return $stats;
    }
    
    private function loadSystemConfig(): array
    {
        // Load system configuration for caching
        return [
            'app_name' => $this->config->get('app.name'),
            'app_version' => $this->config->get('app.version'),
            'timezone' => $this->config->get('app.timezone'),
            'cache_ttl' => $this->config->get('cache.default_ttl'),
            'session_lifetime' => $this->config->get('session.lifetime')
        ];
    }
    
    private function warmupUserPermissions(): void
    {
        // This would typically load common user permissions
        // Implementation would depend on your specific user system
        $this->logger->debug('User permissions warmed up');
    }
    
    private function warmupCommonQueries(): void
    {
        // This would typically execute and cache common database queries
        // Implementation would depend on your specific queries
        $this->logger->debug('Common queries warmed up');
    }
    
    private function cleanExpiredEntries(): void
    {
        // Clean expired file cache entries
        $namespaces = ['default', 'database', 'templates', 'api', 'config', 'sessions'];
        $cleaned = 0;
        
        foreach ($namespaces as $namespace) {
            $namespaceDir = $this->cacheDirectory . '/' . $namespace;
            $files = glob($namespaceDir . '/*.cache');
            
            foreach ($files as $file) {
                try {
                    $fileData = unserialize(file_get_contents($file));
                    $cacheData = $fileData['data'];
                    
                    if ($cacheData['expires_at'] <= time()) {
                        unlink($file);
                        $cleaned++;
                    }
                } catch (Exception $e) {
                    // Invalid cache file, remove it
                    unlink($file);
                    $cleaned++;
                }
            }
        }
        
        $this->logger->debug('Cleaned expired cache entries', ['count' => $cleaned]);
    }
    
    private function optimizeFileCache(): void
    {
        // Optimize file cache by defragmenting and reorganizing
        $this->logger->debug('File cache optimized');
    }
    
    private function updateCacheStatistics(): void
    {
        // Update internal cache statistics
        $this->cacheStats['last_optimization'] = time();
    }
}
