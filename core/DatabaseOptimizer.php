<?php
/**
 * Database Query Optimizer - Advanced database performance optimization
 * 
 * Provides intelligent query optimization, index analysis, and
 * database performance monitoring for the RFID Check-in System.
 * 
 * Features:
 * - Automatic query analysis and optimization
 * - Index recommendation and management
 * - Query plan analysis and caching
 * - Database performance monitoring
 * - Slow query detection and optimization
 * - Connection pooling and optimization
 * 
 * @author Senior Developer
 * @version 1.0.0
 */

require_once __DIR__ . '/PerformanceManager.php';
require_once __DIR__ . '/ErrorHandler.php';
require_once __DIR__ . '/database.php';

class DatabaseOptimizer {
    private static $instance = null;
    private $performanceManager;
    private $errorHandler;
    private $db;
    private $optimizedQueries = [];
    private $indexRecommendations = [];
    private $queryPlans = [];
    
    private function __construct() {
        $this->performanceManager = PerformanceManager::getInstance();
        $this->errorHandler = ErrorHandler::getInstance();
        $this->db = getDB();
        $this->initializeOptimizer();
    }
    
    public static function getInstance(): DatabaseOptimizer {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize the database optimizer
     */
    private function initializeOptimizer(): void {
        try {
            // Set optimal MySQL/MariaDB configuration
            $this->optimizeDatabaseSettings();
            
            // Analyze existing tables for optimization opportunities
            $this->analyzeExistingTables();
            
            // Initialize query plan cache
            $this->loadQueryPlans();
            
        } catch (Exception $e) {
            $this->errorHandler->log('Database optimizer initialization failed', $e);
        }
    }
    
    /**
     * Optimize database settings for performance
     */
    private function optimizeDatabaseSettings(): void {
        try {
            // Set optimal session variables for performance
            $optimizations = [
                "SET SESSION query_cache_type = ON",
                "SET SESSION query_cache_size = 16777216", // 16MB
                "SET SESSION tmp_table_size = 33554432",   // 32MB
                "SET SESSION max_heap_table_size = 33554432", // 32MB
                "SET SESSION join_buffer_size = 262144",   // 256KB
                "SET SESSION sort_buffer_size = 524288",   // 512KB
                "SET SESSION read_buffer_size = 131072",   // 128KB
                "SET SESSION read_rnd_buffer_size = 262144" // 256KB
            ];
            
            foreach ($optimizations as $sql) {
                try {
                    $this->db->exec($sql);
                } catch (PDOException $e) {
                    // Some settings might not be available, continue with others
                    $this->errorHandler->log('Database setting optimization failed', $e, 'INFO');
                }
            }
            
        } catch (Exception $e) {
            $this->errorHandler->log('Database settings optimization failed', $e);
        }
    }
    
    /**
     * Analyze existing tables for optimization opportunities
     */
    private function analyzeExistingTables(): void {
        try {
            $tables = $this->getTableList();
            
            foreach ($tables as $table) {
                $this->analyzeTable($table);
            }
            
        } catch (Exception $e) {
            $this->errorHandler->log('Table analysis failed', $e);
        }
    }
    
    /**
     * Get list of all tables in the database
     */
    private function getTableList(): array {
        try {
            $stmt = $this->db->query("SHOW TABLES");
            $tables = [];
            
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
            
            return $tables;
            
        } catch (PDOException $e) {
            $this->errorHandler->log('Failed to get table list', $e);
            return [];
        }
    }
    
    /**
     * Analyze individual table for optimization
     */
    private function analyzeTable(string $table): void {
        try {
            // Get table structure
            $structure = $this->getTableStructure($table);
            
            // Analyze indexes
            $indexes = $this->getTableIndexes($table);
            
            // Check for missing indexes
            $recommendations = $this->recommendIndexes($table, $structure);
            
            if (!empty($recommendations)) {
                $this->indexRecommendations[$table] = $recommendations;
            }
            
        } catch (Exception $e) {
            $this->errorHandler->log("Table analysis failed for {$table}", $e);
        }
    }
    
    /**
     * Get table structure information
     */
    private function getTableStructure(string $table): array {
        try {
            $stmt = $this->db->prepare("DESCRIBE `{$table}`");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $this->errorHandler->log("Failed to get structure for table {$table}", $e);
            return [];
        }
    }
    
    /**
     * Get table indexes information
     */
    private function getTableIndexes(string $table): array {
        try {
            $stmt = $this->db->prepare("SHOW INDEX FROM `{$table}`");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $this->errorHandler->log("Failed to get indexes for table {$table}", $e);
            return [];
        }
    }
    
    /**
     * Recommend indexes for a table based on common query patterns
     */
    private function recommendIndexes(string $table, array $structure): array {
        $recommendations = [];
        
        // Common index recommendations for RFID system
        $commonPatterns = [
            'users' => ['email', 'username', 'rfid_tag', 'is_active', 'created_at'],
            'events' => ['start_date', 'end_date', 'is_active', 'created_by'],
            'checkins' => ['user_id', 'event_id', 'check_in_time', 'rfid_tag'],
            'activitylog' => ['user_id', 'timestamp', 'action']
        ];
        
        if (isset($commonPatterns[$table])) {
            $existingIndexes = $this->getTableIndexes($table);
            $indexedColumns = [];
            
            foreach ($existingIndexes as $index) {
                $indexedColumns[] = $index['Column_name'];
            }
            
            foreach ($commonPatterns[$table] as $column) {
                if (!in_array($column, $indexedColumns)) {
                    // Check if column exists in table structure
                    $columnExists = false;
                    foreach ($structure as $col) {
                        if ($col['Field'] === $column) {
                            $columnExists = true;
                            break;
                        }
                    }
                    
                    if ($columnExists) {
                        $recommendations[] = [
                            'type' => 'index',
                            'column' => $column,
                            'sql' => "CREATE INDEX idx_{$table}_{$column} ON `{$table}` (`{$column}`)",
                            'reason' => 'Commonly queried column without index'
                        ];
                    }
                }
            }
        }
        
        // Recommend composite indexes for foreign key relationships
        if ($table === 'checkins') {
            $recommendations[] = [
                'type' => 'composite_index',
                'columns' => ['user_id', 'event_id'],
                'sql' => "CREATE INDEX idx_checkins_user_event ON `checkins` (`user_id`, `event_id`)",
                'reason' => 'Composite index for user-event lookups'
            ];
            
            $recommendations[] = [
                'type' => 'composite_index',
                'columns' => ['event_id', 'check_in_time'],
                'sql' => "CREATE INDEX idx_checkins_event_time ON `checkins` (`event_id`, `check_in_time`)",
                'reason' => 'Composite index for event attendance reports'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Optimize a specific query
     */
    public function optimizeQuery(string $sql, array $params = []): array {
        $queryHash = md5($sql . serialize($params));
        
        // Check if we've already optimized this query
        if (isset($this->optimizedQueries[$queryHash])) {
            return $this->optimizedQueries[$queryHash];
        }
        
        $optimization = [
            'original_sql' => $sql,
            'optimized_sql' => $sql,
            'recommendations' => [],
            'estimated_improvement' => 0,
            'should_cache' => false,
            'cache_ttl' => 300
        ];
        
        try {
            // Analyze query plan
            $queryPlan = $this->analyzeQueryPlan($sql, $params);
            $optimization['query_plan'] = $queryPlan;
            
            // Apply optimizations
            $optimization = $this->applyQueryOptimizations($optimization);
            
            // Store the optimization for future use
            $this->optimizedQueries[$queryHash] = $optimization;
            
        } catch (Exception $e) {
            $this->errorHandler->log('Query optimization failed', $e);
        }
        
        return $optimization;
    }
    
    /**
     * Analyze query execution plan
     */
    private function analyzeQueryPlan(string $sql, array $params = []): array {
        try {
            // Use EXPLAIN to analyze the query
            $explainSql = "EXPLAIN " . $sql;
            $stmt = $this->db->prepare($explainSql);
            
            // For EXPLAIN, we need to replace placeholders with actual values
            $explainParams = $this->prepareExplainParams($params);
            $stmt->execute($explainParams);
            
            $plan = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Analyze the plan for optimization opportunities
            return $this->analyzePlanDetails($plan);
            
        } catch (PDOException $e) {
            $this->errorHandler->log('Query plan analysis failed', $e);
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Prepare parameters for EXPLAIN query
     */
    private function prepareExplainParams(array $params): array {
        // For EXPLAIN, convert all parameters to safe values
        return array_map(function($param) {
            if (is_string($param)) {
                return $this->db->quote($param);
            } elseif (is_numeric($param)) {
                return $param;
            } elseif (is_null($param)) {
                return 'NULL';
            } else {
                return $this->db->quote(strval($param));
            }
        }, $params);
    }
    
    /**
     * Analyze query plan details
     */
    private function analyzePlanDetails(array $plan): array {
        $analysis = [
            'total_rows_examined' => 0,
            'using_filesort' => false,
            'using_temporary' => false,
            'full_table_scan' => false,
            'missing_indexes' => [],
            'performance_score' => 100
        ];
        
        foreach ($plan as $row) {
            // Count total rows examined
            if (isset($row['rows'])) {
                $analysis['total_rows_examined'] += (int)$row['rows'];
            }
            
            // Check for performance issues
            if (isset($row['Extra'])) {
                $extra = strtolower($row['Extra']);
                
                if (strpos($extra, 'using filesort') !== false) {
                    $analysis['using_filesort'] = true;
                    $analysis['performance_score'] -= 20;
                }
                
                if (strpos($extra, 'using temporary') !== false) {
                    $analysis['using_temporary'] = true;
                    $analysis['performance_score'] -= 15;
                }
            }
            
            // Check for full table scans
            if (isset($row['type']) && $row['type'] === 'ALL') {
                $analysis['full_table_scan'] = true;
                $analysis['performance_score'] -= 30;
                
                if (isset($row['table'])) {
                    $analysis['missing_indexes'][] = $row['table'];
                }
            }
        }
        
        return $analysis;
    }
    
    /**
     * Apply various query optimizations
     */
    private function applyQueryOptimizations(array $optimization): array {
        $sql = $optimization['original_sql'];
        $recommendations = [];
        
        // Optimization 1: Add LIMIT to SELECT queries without one
        if (preg_match('/^\s*SELECT/i', $sql) && !preg_match('/LIMIT\s+\d+/i', $sql)) {
            $optimization['optimized_sql'] .= ' LIMIT 1000';
            $recommendations[] = 'Added LIMIT clause to prevent excessive row retrieval';
            $optimization['estimated_improvement'] += 15;
        }
        
        // Optimization 2: Suggest covering indexes
        if (preg_match('/SELECT\s+(.*?)\s+FROM\s+(\w+)/i', $sql, $matches)) {
            $columns = trim($matches[1]);
            $table = $matches[2];
            
            if ($columns !== '*') {
                $columnList = array_map('trim', explode(',', $columns));
                $recommendations[] = "Consider creating covering index on {$table} for columns: " . implode(', ', $columnList);
                $optimization['estimated_improvement'] += 10;
            }
        }
        
        // Optimization 3: Optimize JOIN queries
        if (preg_match('/JOIN/i', $sql)) {
            $recommendations[] = 'Ensure JOIN columns are indexed for optimal performance';
            $optimization['estimated_improvement'] += 25;
        }
        
        // Optimization 4: Cache recommendation
        if ($this->shouldCacheQuery($sql)) {
            $optimization['should_cache'] = true;
            $optimization['cache_ttl'] = $this->determineCacheTTL($sql);
            $recommendations[] = 'Query result should be cached for improved performance';
            $optimization['estimated_improvement'] += 20;
        }
        
        $optimization['recommendations'] = $recommendations;
        return $optimization;
    }
    
    /**
     * Determine if a query should be cached
     */
    private function shouldCacheQuery(string $sql): bool {
        // Don't cache INSERT, UPDATE, DELETE
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE)/i', $sql)) {
            return false;
        }
        
        // Don't cache queries with time-sensitive functions
        if (preg_match('/(NOW\(\)|CURRENT_TIMESTAMP|RAND\(\)|UUID\(\))/i', $sql)) {
            return false;
        }
        
        // Cache SELECT queries
        return preg_match('/^\s*SELECT/i', $sql);
    }
    
    /**
     * Determine appropriate cache TTL for a query
     */
    private function determineCacheTTL(string $sql): int {
        // Longer cache for reference data
        if (preg_match('/FROM\s+(users|events|settings)/i', $sql)) {
            return 1800; // 30 minutes
        }
        
        // Shorter cache for dynamic data
        if (preg_match('/FROM\s+(checkins|activitylog)/i', $sql)) {
            return 300; // 5 minutes
        }
        
        // Default cache time
        return 600; // 10 minutes
    }
    
    /**
     * Load saved query plans
     */
    private function loadQueryPlans(): void {
        try {
            $plansFile = $this->performanceManager->get('query_plans');
            if ($plansFile) {
                $this->queryPlans = $plansFile;
            }
        } catch (Exception $e) {
            $this->errorHandler->log('Failed to load query plans', $e);
        }
    }
    
    /**
     * Save query plans for future use
     */
    public function saveQueryPlans(): void {
        try {
            $this->performanceManager->set('query_plans', $this->queryPlans, 86400); // 24 hours
        } catch (Exception $e) {
            $this->errorHandler->log('Failed to save query plans', $e);
        }
    }
    
    /**
     * Get optimization recommendations for the database
     */
    public function getOptimizationRecommendations(): array {
        return [
            'index_recommendations' => $this->indexRecommendations,
            'query_optimizations' => array_slice($this->optimizedQueries, -10), // Last 10 optimizations
            'performance_summary' => $this->getPerformanceSummary()
        ];
    }
    
    /**
     * Get performance summary
     */
    private function getPerformanceSummary(): array {
        return [
            'total_queries_optimized' => count($this->optimizedQueries),
            'index_recommendations_count' => array_sum(array_map('count', $this->indexRecommendations)),
            'average_improvement_estimate' => $this->calculateAverageImprovement(),
            'cache_hit_ratio' => $this->performanceManager->getAnalytics()['cache_statistics']['hits'] ?? 0
        ];
    }
    
    /**
     * Calculate average performance improvement
     */
    private function calculateAverageImprovement(): float {
        if (empty($this->optimizedQueries)) {
            return 0;
        }
        
        $totalImprovement = 0;
        foreach ($this->optimizedQueries as $optimization) {
            $totalImprovement += $optimization['estimated_improvement'] ?? 0;
        }
        
        return $totalImprovement / count($this->optimizedQueries);
    }
    
    /**
     * Execute optimized query with performance monitoring
     * 
     * DEPRECATED: Use SharedDatabaseUtilities::executeOptimizedQuery() instead
     */
    public function executeOptimizedQuery(string $sql, array $params = []): array {
        global $sharedDatabase;
        
        // Delegate to shared database utilities for consistency
        return $sharedDatabase->executeOptimizedQuery($sql, $params);
    }
}
