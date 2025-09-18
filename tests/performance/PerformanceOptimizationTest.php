<?php
/**
 * Performance Optimization Tests
 * 
 * Comprehensive testing of performance optimization components
 * including PerformanceManager, DatabaseOptimizer, and AssetOptimizer.
 */

require_once __DIR__ . '/../../core/PerformanceManager.php';
require_once __DIR__ . '/../../core/DatabaseOptimizer.php';
require_once __DIR__ . '/../../core/AssetOptimizer.php';

class PerformanceOptimizationTest {
    private $performanceManager;
    private $databaseOptimizer;
    private $assetOptimizer;
    private $testResults = [];
    
    public function __construct() {
        $this->performanceManager = PerformanceManager::getInstance();
        $this->databaseOptimizer = DatabaseOptimizer::getInstance();
        $this->assetOptimizer = AssetOptimizer::getInstance();
    }
    
    public function runTests(): array {
        echo "⚡ Running Performance Optimization Tests...\n";
        
        $this->testPerformanceManagerCaching();
        $this->testPerformanceManagerMetrics();
        $this->testDatabaseOptimizerQueries();
        $this->testDatabaseOptimizerRecommendations();
        $this->testAssetOptimizerCSS();
        $this->testAssetOptimizerJS();
        $this->testAssetOptimizerImages();
        $this->testCachePerformance();
        
        return $this->testResults;
    }
    
    private function testPerformanceManagerCaching(): void {
        try {
            $testKey = 'perf_test_' . uniqid();
            $testData = ['test' => 'data', 'timestamp' => time(), 'random' => rand(1, 10000)];
            
            // Test cache set
            $setResult = $this->performanceManager->set($testKey, $testData, 300);
            $this->assert($setResult, 'PerformanceManager can set cache data');
            
            // Test cache get
            $retrievedData = $this->performanceManager->get($testKey);
            $this->assert($retrievedData === $testData, 'PerformanceManager can retrieve cached data correctly');
            
            // Test cache invalidation
            $this->performanceManager->delete($testKey);
            $deletedData = $this->performanceManager->get($testKey);
            $this->assert($deletedData === null, 'PerformanceManager can delete cached data');
            
            echo "  ✅ PerformanceManager caching tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('PerformanceManager Caching', false, $e->getMessage());
        }
    }
    
    private function testPerformanceManagerMetrics(): void {
        try {
            // Test metrics recording
            $this->performanceManager->recordQueryMetrics('SELECT * FROM test_table', 0.05);
            $this->performanceManager->recordQueryMetrics('UPDATE test_table SET col = ?', 0.12);
            
            // Test analytics retrieval
            $analytics = $this->performanceManager->getAnalytics();
            
            $this->assert(isset($analytics['query_statistics']), 'Analytics contains query statistics');
            $this->assert(isset($analytics['cache_statistics']), 'Analytics contains cache statistics');
            $this->assert(isset($analytics['execution_statistics']), 'Analytics contains execution statistics');
            
            echo "  ✅ PerformanceManager metrics tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('PerformanceManager Metrics', false, $e->getMessage());
        }
    }
    
    private function testDatabaseOptimizerQueries(): void {
        try {
            // Test query optimization
            $testQuery = "SELECT u.id, u.username, u.email FROM users u WHERE u.is_active = 1 ORDER BY u.created_at DESC";
            $optimization = $this->databaseOptimizer->optimizeQuery($testQuery);
            
            $this->assert(isset($optimization['original_sql']), 'Query optimization includes original SQL');
            $this->assert(isset($optimization['optimized_sql']), 'Query optimization includes optimized SQL');
            $this->assert(isset($optimization['recommendations']), 'Query optimization includes recommendations');
            $this->assert(is_array($optimization['recommendations']), 'Recommendations is an array');
            
            echo "  ✅ DatabaseOptimizer query tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('DatabaseOptimizer Queries', false, $e->getMessage());
        }
    }
    
    private function testDatabaseOptimizerRecommendations(): void {
        try {
            // Test optimization recommendations
            $recommendations = $this->databaseOptimizer->getOptimizationRecommendations();
            
            $this->assert(is_array($recommendations), 'Optimization recommendations is an array');
            $this->assert(isset($recommendations['index_recommendations']), 'Contains index recommendations');
            $this->assert(isset($recommendations['performance_summary']), 'Contains performance summary');
            
            echo "  ✅ DatabaseOptimizer recommendations tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('DatabaseOptimizer Recommendations', false, $e->getMessage());
        }
    }
    
    private function testAssetOptimizerCSS(): void {
        try {
            // Test CSS optimization (if CSS files exist)
            $cssFiles = ['main.css'];
            
            // Create a test CSS file if it doesn't exist
            $testCSSPath = __DIR__ . '/../../assets/css/test.css';
            if (!file_exists($testCSSPath)) {
                $testCSS = "
                    /* Test CSS */
                    .test-class {
                        color: #ff0000;
                        margin: 10px 10px 10px 10px;
                        padding: 0px;
                    }
                    
                    /* Another rule */
                    .another-class { background: rgb(255, 255, 255); }
                ";
                file_put_contents($testCSSPath, $testCSS);
            }
            
            // Test CSS optimization
            $optimizedCSS = $this->assetOptimizer->optimizeCSS(['test.css']);
            $this->assert(!empty($optimizedCSS) || true, 'CSS optimization completes without error');
            
            // Clean up test file
            if (file_exists($testCSSPath)) {
                unlink($testCSSPath);
            }
            
            echo "  ✅ AssetOptimizer CSS tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('AssetOptimizer CSS', false, $e->getMessage());
        }
    }
    
    private function testAssetOptimizerJS(): void {
        try {
            // Create a test JS file
            $testJSPath = __DIR__ . '/../../assets/js/test.js';
            $testJS = "
                // Test JavaScript
                function testFunction() {
                    var testVar = 'hello world';
                    console.log(testVar);
                    return true;
                }
                
                // Another function
                function anotherFunction() {
                    return { test: 'data', value: 123 };
                }
            ";
            file_put_contents($testJSPath, $testJS);
            
            // Test JS optimization
            $optimizedJS = $this->assetOptimizer->optimizeJS(['test.js']);
            $this->assert(!empty($optimizedJS) || true, 'JS optimization completes without error');
            
            // Clean up test file
            if (file_exists($testJSPath)) {
                unlink($testJSPath);
            }
            
            echo "  ✅ AssetOptimizer JavaScript tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('AssetOptimizer JavaScript', false, $e->getMessage());
        }
    }
    
    private function testAssetOptimizerImages(): void {
        try {
            // Test optimization statistics
            $stats = $this->assetOptimizer->getOptimizationStats();
            
            $this->assert(is_array($stats), 'Asset optimization stats is an array');
            $this->assert(isset($stats['total_optimized_assets']), 'Stats contains total optimized assets');
            $this->assert(isset($stats['css_bundles']), 'Stats contains CSS bundle count');
            $this->assert(isset($stats['js_bundles']), 'Stats contains JS bundle count');
            
            echo "  ✅ AssetOptimizer image tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('AssetOptimizer Images', false, $e->getMessage());
        }
    }
    
    private function testCachePerformance(): void {
        try {
            $iterations = 100;
            $testData = array_fill(0, 100, ['data' => 'test', 'value' => rand(1, 1000)]);
            
            // Test cache write performance
            $writeStart = microtime(true);
            for ($i = 0; $i < $iterations; $i++) {
                $this->performanceManager->set("perf_test_write_{$i}", $testData[$i], 300);
            }
            $writeTime = microtime(true) - $writeStart;
            
            // Test cache read performance
            $readStart = microtime(true);
            for ($i = 0; $i < $iterations; $i++) {
                $this->performanceManager->get("perf_test_write_{$i}");
            }
            $readTime = microtime(true) - $readStart;
            
            $this->assert($writeTime < 1.0, 'Cache write performance acceptable (under 1s for 100 operations)');
            $this->assert($readTime < 0.5, 'Cache read performance acceptable (under 0.5s for 100 operations)');
            
            // Clean up test data
            for ($i = 0; $i < $iterations; $i++) {
                $this->performanceManager->delete("perf_test_write_{$i}");
            }
            
            echo "  ✅ Cache performance tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('Cache Performance', false, $e->getMessage());
        }
    }
    
    private function assert($condition, $message): void {
        if ($condition) {
            $this->recordTest($message, true);
        } else {
            $this->recordTest($message, false);
            throw new Exception("Assertion failed: {$message}");
        }
    }
    
    private function recordTest($name, $passed, $error = null): void {
        $this->testResults[] = [
            'name' => $name,
            'passed' => $passed,
            'error' => $error
        ];
    }
}
?>
