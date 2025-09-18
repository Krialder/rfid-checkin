<?php

namespace App\Controllers\Api;

use App\Services\PerformanceCacheService;
use App\Services\DatabaseOptimizationService;
use App\Services\AssetOptimizationService;
use RfidCheckin\Services\LoggingService;
use Exception;

/**
 * Performance Optimization API Controller
 * 
 * Provides endpoints for performance monitoring and optimization:
 * - Cache management and statistics
 * - Database performance analysis
 * - Asset optimization and delivery
 * - System performance metrics
 * - Optimization recommendations
 */
class PerformanceApiController
{
    private PerformanceCacheService $cacheService;
    private DatabaseOptimizationService $dbOptimizer;
    private AssetOptimizationService $assetOptimizer;
    private LoggingService $logger;
    
    public function __construct()
    {
        $this->cacheService = PerformanceCacheService::getInstance();
        $this->dbOptimizer = DatabaseOptimizationService::getInstance();
        $this->assetOptimizer = AssetOptimizationService::getInstance();
        $this->logger = LoggingService::getInstance();
    }
    
    /**
     * Get comprehensive performance dashboard
     */
    public function getPerformanceDashboard(): array
    {
        try {
            $this->requireAdminAccess();
            
            $dashboard = [
                'cache_performance' => $this->getCachePerformanceData(),
                'database_performance' => $this->getDatabasePerformanceData(),
                'asset_performance' => $this->getAssetPerformanceData(),
                'system_metrics' => $this->getSystemMetrics(),
                'recommendations' => $this->getPerformanceRecommendations(),
                'alerts' => $this->getPerformanceAlerts()
            ];
            
            return [
                'success' => true,
                'data' => $dashboard,
                'timestamp' => time()
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get performance dashboard', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve performance dashboard'
            ];
        }
    }
    
    /**
     * Get cache statistics and management
     */
    public function getCacheStats(): array
    {
        try {
            $this->requireAdminAccess();
            
            $stats = $this->cacheService->getStats();
            
            return [
                'success' => true,
                'data' => $stats
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get cache stats', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve cache statistics'
            ];
        }
    }
    
    /**
     * Clear cache by namespace
     */
    public function clearCache(): array
    {
        try {
            $this->requireAdminAccess();
            
            $input = json_decode(file_get_contents('php://input'), true);
            $namespace = $input['namespace'] ?? 'default';
            
            if ($namespace === 'all') {
                $namespaces = ['default', 'database', 'templates', 'api', 'config', 'sessions'];
                $results = [];
                
                foreach ($namespaces as $ns) {
                    $results[$ns] = $this->cacheService->clearNamespace($ns);
                }
                
                return [
                    'success' => true,
                    'message' => 'All cache namespaces cleared',
                    'results' => $results
                ];
            } else {
                $success = $this->cacheService->clearNamespace($namespace);
                
                return [
                    'success' => $success,
                    'message' => $success ? "Cache namespace '{$namespace}' cleared" : 'Failed to clear cache',
                    'namespace' => $namespace
                ];
            }
            
        } catch (Exception $e) {
            $this->logger->error('Failed to clear cache', [
                'error' => $e->getMessage(),
                'namespace' => $namespace ?? 'unknown'
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to clear cache'
            ];
        }
    }
    
    /**
     * Warm up cache
     */
    public function warmupCache(): array
    {
        try {
            $this->requireAdminAccess();
            
            $this->cacheService->warmup();
            
            return [
                'success' => true,
                'message' => 'Cache warmup completed'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Cache warmup failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Cache warmup failed'
            ];
        }
    }
    
    /**
     * Get database performance metrics
     */
    public function getDatabaseMetrics(): array
    {
        try {
            $this->requireAdminAccess();
            
            $metrics = $this->dbOptimizer->getPerformanceMetrics();
            
            return [
                'success' => true,
                'data' => $metrics
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get database metrics', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve database metrics'
            ];
        }
    }
    
    /**
     * Analyze specific query performance
     */
    public function analyzeQuery(): array
    {
        try {
            $this->requireAdminAccess();
            
            $input = json_decode(file_get_contents('php://input'), true);
            $sql = $input['sql'] ?? '';
            $params = $input['params'] ?? [];
            
            if (empty($sql)) {
                return [
                    'success' => false,
                    'error' => 'SQL query is required'
                ];
            }
            
            $analysis = $this->dbOptimizer->analyzeQuery($sql, $params);
            
            return [
                'success' => true,
                'data' => $analysis
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Query analysis failed', [
                'error' => $e->getMessage(),
                'sql' => $input['sql'] ?? 'unknown'
            ]);
            
            return [
                'success' => false,
                'error' => 'Query analysis failed'
            ];
        }
    }
    
    /**
     * Optimize database indexes
     */
    public function optimizeIndexes(): array
    {
        try {
            $this->requireAdminAccess();
            
            $results = $this->dbOptimizer->optimizeIndexes();
            
            return [
                'success' => true,
                'data' => $results,
                'message' => 'Index optimization completed'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Index optimization failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Index optimization failed'
            ];
        }
    }
    
    /**
     * Perform database maintenance
     */
    public function performDatabaseMaintenance(): array
    {
        try {
            $this->requireAdminAccess();
            
            $results = $this->dbOptimizer->performMaintenance();
            
            return [
                'success' => $results['maintenance_completed'],
                'data' => $results,
                'message' => $results['maintenance_completed'] ? 'Database maintenance completed' : 'Database maintenance failed'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Database maintenance failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Database maintenance failed'
            ];
        }
    }
    
    /**
     * Get asset optimization status
     */
    public function getAssetOptimizationStatus(): array
    {
        try {
            $this->requireAdminAccess();
            
            $stats = $this->assetOptimizer->getOptimizationStats();
            
            return [
                'success' => true,
                'data' => $stats
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get asset optimization status', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve asset optimization status'
            ];
        }
    }
    
    /**
     * Optimize all assets
     */
    public function optimizeAssets(): array
    {
        try {
            $this->requireAdminAccess();
            
            $results = $this->assetOptimizer->optimizeAllAssets();
            
            return [
                'success' => $results['success'],
                'data' => $results,
                'message' => $results['success'] ? 'Asset optimization completed' : 'Asset optimization failed'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Asset optimization failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Asset optimization failed'
            ];
        }
    }
    
    /**
     * Clear optimized assets
     */
    public function clearOptimizedAssets(): array
    {
        try {
            $this->requireAdminAccess();
            
            $success = $this->assetOptimizer->clearOptimizedAssets();
            
            return [
                'success' => $success,
                'message' => $success ? 'Optimized assets cleared' : 'Failed to clear optimized assets'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to clear optimized assets', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to clear optimized assets'
            ];
        }
    }
    
    /**
     * Get system performance metrics
     */
    public function getSystemMetrics(): array
    {
        try {
            $this->requireAdminAccess();
            
            $metrics = [
                'server_info' => $this->getServerInfo(),
                'php_performance' => $this->getPHPPerformanceInfo(),
                'memory_usage' => $this->getMemoryUsage(),
                'disk_usage' => $this->getDiskUsage(),
                'load_average' => $this->getLoadAverage(),
                'process_info' => $this->getProcessInfo()
            ];
            
            return [
                'success' => true,
                'data' => $metrics
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get system metrics', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve system metrics'
            ];
        }
    }
    
    /**
     * Run performance optimization suite
     */
    public function runOptimizationSuite(): array
    {
        try {
            $this->requireAdminAccess();
            
            $results = [];
            
            // Cache optimization
            $results['cache_warmup'] = $this->cacheService->warmup();
            $results['cache_optimization'] = $this->cacheService->optimize();
            
            // Database optimization
            $results['database_maintenance'] = $this->dbOptimizer->performMaintenance();
            $results['index_optimization'] = $this->dbOptimizer->optimizeIndexes();
            
            // Asset optimization
            $results['asset_optimization'] = $this->assetOptimizer->optimizeAllAssets();
            
            return [
                'success' => true,
                'data' => $results,
                'message' => 'Performance optimization suite completed',
                'timestamp' => time()
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Performance optimization suite failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Performance optimization suite failed'
            ];
        }
    }
    
    /**
     * Get performance recommendations
     */
    public function getRecommendations(): array
    {
        try {
            $this->requireAdminAccess();
            
            $recommendations = $this->getPerformanceRecommendations();
            
            return [
                'success' => true,
                'data' => $recommendations
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get performance recommendations', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve performance recommendations'
            ];
        }
    }
    
    /**
     * Monitor real-time performance
     */
    public function getRealtimeMetrics(): array
    {
        try {
            $this->requireAdminAccess();
            
            $metrics = [
                'current_memory_usage' => memory_get_usage(true),
                'peak_memory_usage' => memory_get_peak_usage(true),
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
                'active_connections' => $this->getActiveConnections(),
                'cache_hit_rate' => $this->getCurrentCacheHitRate(),
                'current_queries' => $this->getCurrentQueryCount(),
                'server_load' => $this->getLoadAverage(),
                'timestamp' => microtime(true)
            ];
            
            return [
                'success' => true,
                'data' => $metrics
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get realtime metrics', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve realtime metrics'
            ];
        }
    }
    
    // Private helper methods
    
    private function requireAdminAccess(): void
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            throw new Exception('Admin access required');
        }
    }
    
    private function getCachePerformanceData(): array
    {
        $stats = $this->cacheService->getStats();
        
        $hitRate = 0;
        if (isset($stats['hits']) && isset($stats['misses'])) {
            $total = $stats['hits'] + $stats['misses'];
            $hitRate = $total > 0 ? round(($stats['hits'] / $total) * 100, 2) : 0;
        }
        
        return [
            'hit_rate' => $hitRate,
            'total_requests' => ($stats['hits'] ?? 0) + ($stats['misses'] ?? 0),
            'memory_usage' => $stats['memory_usage'] ?? 0,
            'namespaces' => $stats['namespaces'] ?? [],
            'backends' => [
                'redis' => $stats['redis'] ?? ['enabled' => false],
                'apcu' => $stats['apcu'] ?? ['enabled' => false],
                'file_cache' => $stats['file_cache'] ?? []
            ]
        ];
    }
    
    private function getDatabasePerformanceData(): array
    {
        $metrics = $this->dbOptimizer->getPerformanceMetrics();
        
        return [
            'query_stats' => $metrics['query_stats'] ?? [],
            'slow_queries' => $metrics['slow_queries'] ?? [],
            'connection_stats' => $metrics['connection_stats'] ?? [],
            'table_statistics' => $metrics['table_statistics'] ?? [],
            'database_size' => $metrics['database_size'] ?? [],
            'recommendations' => $metrics['recommendations'] ?? []
        ];
    }
    
    private function getAssetPerformanceData(): array
    {
        $stats = $this->assetOptimizer->getOptimizationStats();
        
        return [
            'optimization_stats' => $stats['stats'] ?? [],
            'cache_info' => $stats['cache_info'] ?? [],
            'cdn_info' => $stats['cdn_info'] ?? [],
            'performance_metrics' => $stats['performance_metrics'] ?? []
        ];
    }
    
    private function getPerformanceRecommendations(): array
    {
        $recommendations = [];
        
        // Cache recommendations
        $cacheStats = $this->cacheService->getStats();
        $hitRate = 0;
        if (isset($cacheStats['hits']) && isset($cacheStats['misses'])) {
            $total = $cacheStats['hits'] + $cacheStats['misses'];
            $hitRate = $total > 0 ? ($cacheStats['hits'] / $total) * 100 : 0;
        }
        
        if ($hitRate < 70) {
            $recommendations[] = [
                'category' => 'cache',
                'priority' => 'high',
                'title' => 'Low Cache Hit Rate',
                'description' => 'Cache hit rate is below 70%. Consider optimizing cache strategy.',
                'current_value' => round($hitRate, 2) . '%',
                'recommended_value' => '> 80%'
            ];
        }
        
        // Memory recommendations
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        if ($memoryUsage > ($memoryLimit * 0.8)) {
            $recommendations[] = [
                'category' => 'memory',
                'priority' => 'medium',
                'title' => 'High Memory Usage',
                'description' => 'Memory usage is above 80% of limit. Consider optimizing or increasing memory limit.',
                'current_value' => $this->formatBytes($memoryUsage),
                'recommended_value' => 'Optimize memory usage or increase limit'
            ];
        }
        
        // OPcache recommendation
        if (!extension_loaded('opcache') || !ini_get('opcache.enable')) {
            $recommendations[] = [
                'category' => 'php',
                'priority' => 'high',
                'title' => 'OPcache Disabled',
                'description' => 'OPcache is not enabled. Enabling it can significantly improve PHP performance.',
                'current_value' => 'Disabled',
                'recommended_value' => 'Enable OPcache'
            ];
        }
        
        // Database recommendations
        $dbMetrics = $this->dbOptimizer->getPerformanceMetrics();
        if (isset($dbMetrics['recommendations'])) {
            foreach ($dbMetrics['recommendations'] as $dbRec) {
                $recommendations[] = array_merge($dbRec, ['category' => 'database']);
            }
        }
        
        return $recommendations;
    }
    
    private function getPerformanceAlerts(): array
    {
        $alerts = [];
        
        // Check for critical performance issues
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        if ($memoryUsage > ($memoryLimit * 0.9)) {
            $alerts[] = [
                'level' => 'critical',
                'message' => 'Memory usage is critically high (> 90%)',
                'value' => round(($memoryUsage / $memoryLimit) * 100, 2) . '%'
            ];
        }
        
        // Check slow queries
        $dbMetrics = $this->dbOptimizer->getPerformanceMetrics();
        $slowQueries = $dbMetrics['slow_queries']['count'] ?? 0;
        
        if ($slowQueries > 10) {
            $alerts[] = [
                'level' => 'warning',
                'message' => 'High number of slow queries detected',
                'value' => $slowQueries . ' slow queries'
            ];
        }
        
        // Check disk space
        $freeSpace = disk_free_space('.');
        $totalSpace = disk_total_space('.');
        $usagePercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
        
        if ($usagePercent > 90) {
            $alerts[] = [
                'level' => 'critical',
                'message' => 'Disk space is critically low',
                'value' => round($usagePercent, 2) . '% used'
            ];
        } elseif ($usagePercent > 80) {
            $alerts[] = [
                'level' => 'warning',
                'message' => 'Disk space is running low',
                'value' => round($usagePercent, 2) . '% used'
            ];
        }
        
        return $alerts;
    }
    
    private function getServerInfo(): array
    {
        return [
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'php_version' => PHP_VERSION,
            'php_sapi' => php_sapi_name(),
            'os' => PHP_OS,
            'architecture' => php_uname('m')
        ];
    }
    
    private function getPHPPerformanceInfo(): array
    {
        return [
            'opcache' => [
                'enabled' => extension_loaded('opcache') && ini_get('opcache.enable'),
                'version' => extension_loaded('opcache') ? phpversion('opcache') : null,
                'memory_usage' => extension_loaded('opcache') ? opcache_get_status()['memory_usage'] ?? [] : []
            ],
            'jit' => [
                'enabled' => ini_get('opcache.jit') !== 'disable' && ini_get('opcache.jit') !== '',
                'buffer_size' => ini_get('opcache.jit_buffer_size')
            ]
        ];
    }
    
    private function getMemoryUsage(): array
    {
        return [
            'current_usage' => memory_get_usage(true),
            'peak_usage' => memory_get_peak_usage(true),
            'limit' => $this->parseMemoryLimit(ini_get('memory_limit')),
            'usage_percent' => round((memory_get_usage(true) / $this->parseMemoryLimit(ini_get('memory_limit'))) * 100, 2)
        ];
    }
    
    private function getDiskUsage(): array
    {
        $free = disk_free_space('.');
        $total = disk_total_space('.');
        $used = $total - $free;
        
        return [
            'free_space' => $free,
            'total_space' => $total,
            'used_space' => $used,
            'usage_percent' => round(($used / $total) * 100, 2)
        ];
    }
    
    private function getLoadAverage(): array
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                '1_minute' => $load[0] ?? 0,
                '5_minute' => $load[1] ?? 0,
                '15_minute' => $load[2] ?? 0
            ];
        }
        
        return ['1_minute' => 0, '5_minute' => 0, '15_minute' => 0];
    }
    
    private function getProcessInfo(): array
    {
        return [
            'process_id' => getmypid(),
            'user_id' => getmyuid(),
            'group_id' => getmygid(),
            'current_working_directory' => getcwd()
        ];
    }
    
    private function getLoadedExtensions(): array
    {
        $importantExtensions = [
            'mysqli', 'pdo', 'pdo_mysql', 'curl', 'json', 'mbstring',
            'openssl', 'zip', 'gd', 'imagick', 'redis', 'memcached',
            'opcache', 'apcu', 'xdebug'
        ];
        
        $loaded = [];
        foreach ($importantExtensions as $ext) {
            $loaded[$ext] = extension_loaded($ext);
        }
        
        return $loaded;
    }
    
    private function getActiveConnections(): int
    {
        // This would typically query the database for active connections
        // Simplified implementation
        return 1;
    }
    
    private function getCurrentCacheHitRate(): float
    {
        $stats = $this->cacheService->getStats();
        $hits = $stats['hits'] ?? 0;
        $misses = $stats['misses'] ?? 0;
        $total = $hits + $misses;
        
        return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
    }
    
    private function getCurrentQueryCount(): int
    {
        // This would typically track current queries
        // Simplified implementation
        return 0;
    }
    
    private function parseMemoryLimit(string $memoryLimit): int
    {
        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int)substr($memoryLimit, 0, -1);
        
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return (int)$memoryLimit;
        }
    }
    
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        
        return sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor] ?? 'GB');
    }
}
