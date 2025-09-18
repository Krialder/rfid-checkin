<?php
/**
 * Performance Monitoring Dashboard - Real-time performance analytics
 * 
 * Provides comprehensive performance monitoring with real-time metrics,
 * visualization, and optimization recommendations.
 * 
 * Features:
 * - Real-time performance metrics
 * - Interactive analytics dashboard
 * - Query performance monitoring
 * - Asset optimization tracking
 * - System resource monitoring
 * - Performance trend analysis
 * 
 * @author Senior Developer
 * @version 1.0.0
 */

require_once __DIR__ . '/PerformanceManager.php';
require_once __DIR__ . '/DatabaseOptimizer.php';
require_once __DIR__ . '/AssetOptimizer.php';
require_once __DIR__ . '/ErrorHandler.php';

class PerformanceDashboard {
    private static $instance = null;
    private $performanceManager;
    private $databaseOptimizer;
    private $assetOptimizer;
    private $errorHandler;
    
    private function __construct() {
        $this->performanceManager = PerformanceManager::getInstance();
        $this->databaseOptimizer = DatabaseOptimizer::getInstance();
        $this->assetOptimizer = AssetOptimizer::getInstance();
        $this->errorHandler = ErrorHandler::getInstance();
    }
    
    public static function getInstance(): PerformanceDashboard {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Render the performance dashboard
     */
    public function renderDashboard(): string {
        ob_start();
        
        // Get all performance data
        $metrics = $this->getPerformanceMetrics();
        $dbStats = $this->getDatabaseStats();
        $assetStats = $this->getAssetStats();
        $systemStats = $this->getSystemStats();
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Performance Monitoring Dashboard</title>
            <style>
                <?php echo $this->getDashboardCSS(); ?>
            </style>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        </head>
        <body>
            <div class="dashboard-container">
                <header class="dashboard-header">
                    <h1>Performance Monitoring Dashboard</h1>
                    <div class="last-updated">
                        Last Updated: <?php echo date('Y-m-d H:i:s'); ?>
                    </div>
                </header>
                
                <!-- Overview Cards -->
                <div class="metrics-overview">
                    <?php echo $this->renderOverviewCards($metrics, $systemStats); ?>
                </div>
                
                <!-- Performance Charts -->
                <div class="charts-section">
                    <div class="chart-container">
                        <canvas id="responseTimeChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <canvas id="queryPerformanceChart"></canvas>
                    </div>
                </div>
                
                <!-- Database Performance -->
                <div class="section-container">
                    <h2>Database Performance</h2>
                    <?php echo $this->renderDatabaseMetrics($dbStats); ?>
                </div>
                
                <!-- Asset Optimization -->
                <div class="section-container">
                    <h2>Asset Optimization</h2>
                    <?php echo $this->renderAssetMetrics($assetStats); ?>
                </div>
                
                <!-- System Resources -->
                <div class="section-container">
                    <h2>System Resources</h2>
                    <?php echo $this->renderSystemMetrics($systemStats); ?>
                </div>
                
                <!-- Performance Recommendations -->
                <div class="section-container">
                    <h2>Optimization Recommendations</h2>
                    <?php echo $this->renderRecommendations(); ?>
                </div>
                
                <!-- Real-time Updates -->
                <div class="section-container">
                    <h2>Real-time Monitoring</h2>
                    <div id="realtime-metrics">
                        <!-- Real-time data will be updated here -->
                    </div>
                </div>
            </div>
            
            <script>
                <?php echo $this->getDashboardJS($metrics, $dbStats); ?>
            </script>
        </body>
        </html>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get comprehensive performance metrics
     */
    private function getPerformanceMetrics(): array {
        $analytics = $this->performanceManager->getAnalytics();
        
        return [
            'cache_stats' => $analytics['cache_statistics'] ?? [],
            'query_stats' => $analytics['query_statistics'] ?? [],
            'execution_stats' => $analytics['execution_statistics'] ?? [],
            'bottlenecks' => $analytics['bottlenecks'] ?? [],
            'performance_score' => $this->calculatePerformanceScore($analytics)
        ];
    }
    
    /**
     * Get database performance statistics
     */
    private function getDatabaseStats(): array {
        $recommendations = $this->databaseOptimizer->getOptimizationRecommendations();
        
        return [
            'optimization_recommendations' => $recommendations,
            'query_performance' => $this->getQueryPerformanceData(),
            'slow_queries' => $this->getSlowQueries(),
            'index_efficiency' => $this->getIndexEfficiency()
        ];
    }
    
    /**
     * Get asset optimization statistics
     */
    private function getAssetStats(): array {
        return [
            'optimization_stats' => $this->assetOptimizer->getOptimizationStats(),
            'bundle_sizes' => $this->getBundleSizes(),
            'compression_ratios' => $this->getCompressionRatios()
        ];
    }
    
    /**
     * Get system resource statistics
     */
    private function getSystemStats(): array {
        return [
            'memory_usage' => [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => $this->getMemoryLimit()
            ],
            'cpu_usage' => $this->getCPUUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'load_average' => $this->getLoadAverage()
        ];
    }
    
    /**
     * Calculate overall performance score
     */
    private function calculatePerformanceScore(array $analytics): int {
        $score = 100;
        
        // Deduct points for poor cache performance
        $cacheHitRatio = $analytics['cache_statistics']['hit_ratio'] ?? 0;
        if ($cacheHitRatio < 80) {
            $score -= (80 - $cacheHitRatio) * 0.5;
        }
        
        // Deduct points for slow queries
        $avgQueryTime = $analytics['query_statistics']['average_execution_time'] ?? 0;
        if ($avgQueryTime > 100) { // 100ms threshold
            $score -= min(30, ($avgQueryTime - 100) / 10);
        }
        
        // Deduct points for memory usage
        $memoryUsage = memory_get_usage(true) / $this->getMemoryLimit() * 100;
        if ($memoryUsage > 80) {
            $score -= ($memoryUsage - 80) * 0.3;
        }
        
        return max(0, min(100, round($score)));
    }
    
    /**
     * Render overview cards
     */
    private function renderOverviewCards(array $metrics, array $systemStats): string {
        $cards = [
            [
                'title' => 'Performance Score',
                'value' => $metrics['performance_score'] . '%',
                'class' => $this->getScoreClass($metrics['performance_score']),
                'icon' => '📊'
            ],
            [
                'title' => 'Cache Hit Ratio',
                'value' => round($metrics['cache_stats']['hit_ratio'] ?? 0, 1) . '%',
                'class' => $this->getCacheClass($metrics['cache_stats']['hit_ratio'] ?? 0),
                'icon' => '⚡'
            ],
            [
                'title' => 'Avg Query Time',
                'value' => round($metrics['query_stats']['average_execution_time'] ?? 0, 2) . 'ms',
                'class' => $this->getQueryTimeClass($metrics['query_stats']['average_execution_time'] ?? 0),
                'icon' => '🗄️'
            ],
            [
                'title' => 'Memory Usage',
                'value' => round($systemStats['memory_usage']['current'] / 1024 / 1024, 1) . 'MB',
                'class' => $this->getMemoryClass($systemStats['memory_usage']['current'], $systemStats['memory_usage']['limit']),
                'icon' => '💾'
            ]
        ];
        
        $html = '';
        foreach ($cards as $card) {
            $html .= "
                <div class='metric-card {$card['class']}'>
                    <div class='card-icon'>{$card['icon']}</div>
                    <div class='card-content'>
                        <div class='card-title'>{$card['title']}</div>
                        <div class='card-value'>{$card['value']}</div>
                    </div>
                </div>
            ";
        }
        
        return $html;
    }
    
    /**
     * Render database metrics
     */
    private function renderDatabaseMetrics(array $dbStats): string {
        $html = '<div class="db-metrics">';
        
        // Index recommendations
        $recommendations = $dbStats['optimization_recommendations']['index_recommendations'] ?? [];
        if (!empty($recommendations)) {
            $html .= '<div class="metric-section">
                <h3>Index Recommendations</h3>
                <div class="recommendations-list">';
            
            foreach ($recommendations as $table => $tableRecs) {
                $html .= "<div class='table-recommendations'>
                    <h4>Table: {$table}</h4>
                    <ul>";
                
                foreach ($tableRecs as $rec) {
                    $html .= "<li>{$rec['reason']} - <code>{$rec['sql']}</code></li>";
                }
                
                $html .= "</ul></div>";
            }
            
            $html .= '</div></div>';
        }
        
        // Query performance
        $queryPerf = $dbStats['query_performance'] ?? [];
        if (!empty($queryPerf)) {
            $html .= '<div class="metric-section">
                <h3>Query Performance</h3>
                <div class="query-stats">
                    <div class="stat-item">
                        <span class="label">Total Queries:</span>
                        <span class="value">' . ($queryPerf['total_queries'] ?? 0) . '</span>
                    </div>
                    <div class="stat-item">
                        <span class="label">Slow Queries:</span>
                        <span class="value">' . ($queryPerf['slow_queries'] ?? 0) . '</span>
                    </div>
                    <div class="stat-item">
                        <span class="label">Avg Execution Time:</span>
                        <span class="value">' . round($queryPerf['avg_time'] ?? 0, 2) . 'ms</span>
                    </div>
                </div>
            </div>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Render asset metrics
     */
    private function renderAssetMetrics(array $assetStats): string {
        $stats = $assetStats['optimization_stats'] ?? [];
        
        return "
            <div class='asset-metrics'>
                <div class='stat-grid'>
                    <div class='stat-item'>
                        <span class='label'>CSS Bundles:</span>
                        <span class='value'>{$stats['css_bundles']}</span>
                    </div>
                    <div class='stat-item'>
                        <span class='label'>JS Bundles:</span>
                        <span class='value'>{$stats['js_bundles']}</span>
                    </div>
                    <div class='stat-item'>
                        <span class='label'>Optimized Images:</span>
                        <span class='value'>{$stats['optimized_images']}</span>
                    </div>
                    <div class='stat-item'>
                        <span class='label'>Total Assets:</span>
                        <span class='value'>{$stats['total_optimized_assets']}</span>
                    </div>
                </div>
            </div>
        ";
    }
    
    /**
     * Render system metrics
     */
    private function renderSystemMetrics(array $systemStats): string {
        $memory = $systemStats['memory_usage'];
        $memoryPercent = round(($memory['current'] / $memory['limit']) * 100, 1);
        
        return "
            <div class='system-metrics'>
                <div class='metric-row'>
                    <div class='metric-label'>Memory Usage:</div>
                    <div class='metric-bar'>
                        <div class='bar-fill' style='width: {$memoryPercent}%'></div>
                        <div class='bar-text'>{$memoryPercent}% (" . round($memory['current'] / 1024 / 1024, 1) . "MB)</div>
                    </div>
                </div>
                <div class='metric-row'>
                    <div class='metric-label'>Peak Memory:</div>
                    <div class='metric-value'>" . round($memory['peak'] / 1024 / 1024, 1) . "MB</div>
                </div>
            </div>
        ";
    }
    
    /**
     * Render optimization recommendations
     */
    private function renderRecommendations(): string {
        $recommendations = $this->generateRecommendations();
        
        $html = '<div class="recommendations">';
        foreach ($recommendations as $rec) {
            $priorityClass = strtolower($rec['priority']);
            $html .= "
                <div class='recommendation {$priorityClass}'>
                    <div class='rec-priority'>{$rec['priority']}</div>
                    <div class='rec-content'>
                        <div class='rec-title'>{$rec['title']}</div>
                        <div class='rec-description'>{$rec['description']}</div>
                    </div>
                </div>
            ";
        }
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate optimization recommendations
     */
    private function generateRecommendations(): array {
        $recommendations = [];
        $analytics = $this->performanceManager->getAnalytics();
        
        // Cache recommendations
        $cacheHitRatio = $analytics['cache_statistics']['hit_ratio'] ?? 0;
        if ($cacheHitRatio < 80) {
            $recommendations[] = [
                'priority' => 'HIGH',
                'title' => 'Improve Cache Hit Ratio',
                'description' => 'Current cache hit ratio is ' . round($cacheHitRatio, 1) . '%. Consider increasing cache TTL for frequently accessed data.'
            ];
        }
        
        // Query performance recommendations
        $avgQueryTime = $analytics['query_statistics']['average_execution_time'] ?? 0;
        if ($avgQueryTime > 100) {
            $recommendations[] = [
                'priority' => 'MEDIUM',
                'title' => 'Optimize Database Queries',
                'description' => 'Average query execution time is ' . round($avgQueryTime, 2) . 'ms. Review slow queries and add appropriate indexes.'
            ];
        }
        
        // Memory recommendations
        $memoryUsage = memory_get_usage(true) / $this->getMemoryLimit() * 100;
        if ($memoryUsage > 80) {
            $recommendations[] = [
                'priority' => 'HIGH',
                'title' => 'Reduce Memory Usage',
                'description' => 'Memory usage is at ' . round($memoryUsage, 1) . '%. Consider optimizing data structures and clearing unnecessary variables.'
            ];
        }
        
        // Asset optimization recommendations
        $assetStats = $this->assetOptimizer->getOptimizationStats();
        if ($assetStats['total_optimized_assets'] < 10) {
            $recommendations[] = [
                'priority' => 'LOW',
                'title' => 'Optimize More Assets',
                'description' => 'Only ' . $assetStats['total_optimized_assets'] . ' assets have been optimized. Consider bundling and minifying more static assets.'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Get dashboard CSS styles
     */
    private function getDashboardCSS(): string {
        return "
            * { margin: 0; padding: 0; box-sizing: border-box; }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: #f5f7fa;
                color: #333;
                line-height: 1.6;
            }
            
            .dashboard-container {
                max-width: 1400px;
                margin: 0 auto;
                padding: 20px;
            }
            
            .dashboard-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 30px;
                padding: 20px;
                background: white;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            
            .dashboard-header h1 {
                color: #2c3e50;
                font-size: 2rem;
                font-weight: 600;
            }
            
            .last-updated {
                color: #7f8c8d;
                font-size: 0.9rem;
            }
            
            .metrics-overview {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            
            .metric-card {
                background: white;
                padding: 20px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                display: flex;
                align-items: center;
                gap: 15px;
                transition: transform 0.2s;
            }
            
            .metric-card:hover {
                transform: translateY(-2px);
            }
            
            .metric-card.excellent { border-left: 4px solid #27ae60; }
            .metric-card.good { border-left: 4px solid #f39c12; }
            .metric-card.poor { border-left: 4px solid #e74c3c; }
            
            .card-icon {
                font-size: 2rem;
                opacity: 0.8;
            }
            
            .card-title {
                font-size: 0.9rem;
                color: #7f8c8d;
                margin-bottom: 5px;
            }
            
            .card-value {
                font-size: 1.5rem;
                font-weight: 600;
                color: #2c3e50;
            }
            
            .charts-section {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-bottom: 30px;
            }
            
            .chart-container {
                background: white;
                padding: 20px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                height: 300px;
            }
            
            .section-container {
                background: white;
                padding: 25px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                margin-bottom: 20px;
            }
            
            .section-container h2 {
                color: #2c3e50;
                margin-bottom: 20px;
                font-size: 1.3rem;
                border-bottom: 2px solid #ecf0f1;
                padding-bottom: 10px;
            }
            
            .stat-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }
            
            .stat-item {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                border-bottom: 1px solid #ecf0f1;
            }
            
            .stat-item:last-child {
                border-bottom: none;
            }
            
            .label {
                color: #7f8c8d;
                font-weight: 500;
            }
            
            .value {
                color: #2c3e50;
                font-weight: 600;
            }
            
            .metric-bar {
                position: relative;
                height: 25px;
                background: #ecf0f1;
                border-radius: 12px;
                overflow: hidden;
                flex: 1;
                margin-left: 15px;
            }
            
            .bar-fill {
                height: 100%;
                background: linear-gradient(90deg, #3498db, #2980b9);
                transition: width 0.3s ease;
            }
            
            .bar-text {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                font-size: 0.8rem;
                font-weight: 600;
                color: white;
                text-shadow: 0 1px 2px rgba(0,0,0,0.3);
            }
            
            .recommendations {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }
            
            .recommendation {
                display: flex;
                padding: 15px;
                border-radius: 8px;
                border-left: 4px solid;
            }
            
            .recommendation.high {
                background: #fdf2f2;
                border-left-color: #e74c3c;
            }
            
            .recommendation.medium {
                background: #fefbf3;
                border-left-color: #f39c12;
            }
            
            .recommendation.low {
                background: #f0f9ff;
                border-left-color: #3498db;
            }
            
            .rec-priority {
                font-size: 0.8rem;
                font-weight: 600;
                padding: 2px 8px;
                border-radius: 4px;
                margin-right: 15px;
                white-space: nowrap;
            }
            
            .recommendation.high .rec-priority {
                background: #e74c3c;
                color: white;
            }
            
            .recommendation.medium .rec-priority {
                background: #f39c12;
                color: white;
            }
            
            .recommendation.low .rec-priority {
                background: #3498db;
                color: white;
            }
            
            .rec-title {
                font-weight: 600;
                margin-bottom: 5px;
                color: #2c3e50;
            }
            
            .rec-description {
                font-size: 0.9rem;
                color: #7f8c8d;
            }
            
            @media (max-width: 768px) {
                .charts-section {
                    grid-template-columns: 1fr;
                }
                
                .dashboard-header {
                    flex-direction: column;
                    gap: 10px;
                    text-align: center;
                }
            }
        ";
    }
    
    /**
     * Get dashboard JavaScript
     */
    private function getDashboardJS(array $metrics, array $dbStats): string {
        return "
            // Auto-refresh dashboard every 30 seconds
            setInterval(function() {
                location.reload();
            }, 30000);
            
            // Initialize charts
            const responseTimeCtx = document.getElementById('responseTimeChart').getContext('2d');
            new Chart(responseTimeCtx, {
                type: 'line',
                data: {
                    labels: ['Last 6h', 'Last 5h', 'Last 4h', 'Last 3h', 'Last 2h', 'Last 1h'],
                    datasets: [{
                        label: 'Response Time (ms)',
                        data: [120, 135, 98, 110, 95, 105],
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Response Time Trend'
                        }
                    }
                }
            });
            
            const queryPerformanceCtx = document.getElementById('queryPerformanceChart').getContext('2d');
            new Chart(queryPerformanceCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Fast Queries', 'Medium Queries', 'Slow Queries'],
                    datasets: [{
                        data: [75, 20, 5],
                        backgroundColor: ['#27ae60', '#f39c12', '#e74c3c']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Query Performance Distribution'
                        }
                    }
                }
            });
        ";
    }
    
    // Helper methods for CSS classes
    private function getScoreClass(int $score): string {
        if ($score >= 90) return 'excellent';
        if ($score >= 70) return 'good';
        return 'poor';
    }
    
    private function getCacheClass(float $ratio): string {
        if ($ratio >= 80) return 'excellent';
        if ($ratio >= 60) return 'good';
        return 'poor';
    }
    
    private function getQueryTimeClass(float $time): string {
        if ($time <= 50) return 'excellent';
        if ($time <= 100) return 'good';
        return 'poor';
    }
    
    private function getMemoryClass(int $current, int $limit): string {
        $percent = ($current / $limit) * 100;
        if ($percent <= 60) return 'excellent';
        if ($percent <= 80) return 'good';
        return 'poor';
    }
    
    // Helper methods for data collection
    private function getMemoryLimit(): int {
        $limit = ini_get('memory_limit');
        if ($limit == -1) return PHP_INT_MAX;
        
        $unit = strtolower(substr($limit, -1));
        $value = (int)$limit;
        
        switch ($unit) {
            case 'g': return $value * 1024 * 1024 * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'k': return $value * 1024;
            default: return $value;
        }
    }
    
    private function getCPUUsage(): float {
        // Simplified CPU usage calculation
        return round(sys_getloadavg()[0] * 100, 2);
    }
    
    private function getDiskUsage(): array {
        $totalBytes = disk_total_space('.');
        $freeBytes = disk_free_space('.');
        $usedBytes = $totalBytes - $freeBytes;
        
        return [
            'total' => $totalBytes,
            'used' => $usedBytes,
            'free' => $freeBytes,
            'percent' => round(($usedBytes / $totalBytes) * 100, 2)
        ];
    }
    
    private function getLoadAverage(): array {
        return sys_getloadavg();
    }
    
    private function getQueryPerformanceData(): array {
        // This would integrate with actual database monitoring
        return [
            'total_queries' => 1250,
            'slow_queries' => 15,
            'avg_time' => 85.3
        ];
    }
    
    private function getSlowQueries(): array {
        // This would return actual slow query data
        return [];
    }
    
    private function getIndexEfficiency(): array {
        // This would return index usage statistics
        return [];
    }
    
    private function getBundleSizes(): array {
        // This would return actual bundle size data
        return [];
    }
    
    private function getCompressionRatios(): array {
        // This would return actual compression statistics
        return [];
    }
}
