<?php
/**
 * Base Repository Class
 * 
 * Abstract foundation for all repository classes providing common database
 * operations, transaction management, and standardized error handling.
 * Implements the Repository pattern for clean separation of data access logic.
 * 
 * Features:
 * - Singleton database connection management
 * - Transaction support with automatic rollback
 * - Prepared statement helpers for security
 * - Standardized error handling and logging
 * - Query performance monitoring
 * - Result caching capabilities
 * 
 * @package    RFID Check-in System
 * @subpackage Data Access Layer
 * @version    2.0.0
 * @author     Senior Developer Team
 * @since      2.0.0
 * @security   HIGH - Handles all database operations
 */

// Ensure dependencies are loaded
if (!defined('CONFIG_LOADED')) {
    require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../PerformanceManager.php';

abstract class BaseRepository {
    
    /**
     * Database connection instance
     * @var PDO
     */
    protected $db;
    
    /**
     * Performance manager instance
     * @var PerformanceManager
     */
    protected $performanceManager;
    
    /**
     * Table name for this repository
     * @var string
     */
    protected $table;
    
    /**
     * Database optimizer instance
     * @var DatabaseOptimizer
     */
    protected $databaseOptimizer;
    
    /**
     * Primary key field name
     * @var string
     */
    protected $primaryKey = 'id';
    
    /**
     * Result cache for expensive queries
     * @var array
     */
    private static $cache = [];
    
    /**
     * Cache TTL in seconds
     * @var int
     */
    protected $cacheTtl = 300; // 5 minutes
    
    /**
     * Initialize repository with database connection
     */
    public function __construct() {
        $this->db = getDB();
        if (!$this->db) {
            throw new Exception('Database connection failed in repository');
        }
        
        // Initialize performance manager for query optimization
        $this->performanceManager = PerformanceManager::getInstance();
        
        // Initialize database optimizer for enhanced query performance
        require_once __DIR__ . '/../DatabaseOptimizer.php';
        $this->databaseOptimizer = DatabaseOptimizer::getInstance();
    }
    
    /**
     * Find record by primary key with performance optimization
     * 
     * @param mixed $id Primary key value
     * @param array $columns Columns to select (default: all)
     * @return array|null Record data or null if not found
     */
    public function findById($id, array $columns = ['*']) {
        $columnList = implode(', ', $columns);
        $cacheKey = "repo_{$this->table}_findById_{$id}_" . md5($columnList);
        
        // Try performance manager cache first
        $cached = $this->performanceManager->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        try {
            $sql = "SELECT {$columnList} FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1";
            $startTime = microtime(true);
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            
            $executionTime = microtime(true) - $startTime;
            $this->performanceManager->recordQueryMetrics($sql, $executionTime);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Cache the result for future use
            if ($result) {
                $this->performanceManager->set($cacheKey, $result, $this->cacheTtl);
            }
            
            return $result ?: null;
            
        } catch (PDOException $e) {
            $this->logError('findById failed', $e, ['id' => $id, 'table' => $this->table]);
            throw new Exception('Database query failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Find multiple records with conditions and performance optimization
     * 
     * @param array $conditions WHERE conditions ['column' => 'value']
     * @param array $options Query options (orderBy, limit, offset, columns)
     * @return array Array of records
     */
    public function findWhere(array $conditions = [], array $options = []) {
        $columns = $options['columns'] ?? ['*'];
        $columnList = implode(', ', $columns);
        
        // Create cache key for this query
        $cacheKey = "repo_{$this->table}_findWhere_" . md5(serialize([$conditions, $options]));
        
        // Try performance manager cache first
        $cached = $this->performanceManager->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $sql = "SELECT {$columnList} FROM {$this->table}";
        $params = [];
        
        // Build WHERE clause
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $column => $value) {
                if (is_array($value)) {
                    $placeholders = implode(',', array_fill(0, count($value), '?'));
                    $whereClause[] = "{$column} IN ({$placeholders})";
                    $params = array_merge($params, $value);
                } elseif ($value === null) {
                    $whereClause[] = "{$column} IS NULL";
                } else {
                    $whereClause[] = "{$column} = ?";
                    $params[] = $value;
                }
            }
            $sql .= ' WHERE ' . implode(' AND ', $whereClause);
        }
        
        // Add ORDER BY
        if (isset($options['orderBy'])) {
            $sql .= ' ORDER BY ' . $options['orderBy'];
        }
        
        // Add LIMIT and OFFSET
        if (isset($options['limit'])) {
            $sql .= ' LIMIT ' . (int)$options['limit'];
            if (isset($options['offset'])) {
                $sql .= ' OFFSET ' . (int)$options['offset'];
            }
        }
        
        try {
            $startTime = microtime(true);
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $executionTime = microtime(true) - $startTime;
            $this->performanceManager->recordQueryMetrics($sql, $executionTime);
            
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Cache the result for future use
            $this->performanceManager->set($cacheKey, $result, $this->cacheTtl);
            
            return $result;
            
        } catch (PDOException $e) {
            $this->logError('findWhere failed', $e, [
                'conditions' => $conditions,
                'options' => $options,
                'table' => $this->table
            ]);
            throw new Exception('Database query failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Insert new record
     * 
     * @param array $data Column-value pairs
     * @return int|bool Last insert ID or false on failure
     */
    public function insert(array $data) {
        if (empty($data)) {
            throw new InvalidArgumentException('Insert data cannot be empty');
        }
        
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        try {
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute(array_values($data));
            
            if ($result) {
                $this->clearRelatedCache();
                return $this->db->lastInsertId();
            }
            
            return false;
            
        } catch (PDOException $e) {
            $this->logError('insert failed', $e, ['data' => $data, 'table' => $this->table]);
            throw new Exception('Database insert failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Update existing record
     * 
     * @param mixed $id Primary key value
     * @param array $data Column-value pairs to update
     * @return bool Success status
     */
    public function update($id, array $data) {
        if (empty($data)) {
            throw new InvalidArgumentException('Update data cannot be empty');
        }
        
        $setParts = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $setParts[] = "{$column} = ?";
            $params[] = $value;
        }
        
        $params[] = $id; // Add ID for WHERE clause
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts) . " WHERE {$this->primaryKey} = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                $this->clearRelatedCache();
            }
            
            return $result;
            
        } catch (PDOException $e) {
            $this->logError('update failed', $e, [
                'id' => $id,
                'data' => $data,
                'table' => $this->table
            ]);
            throw new Exception('Database update failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete record by primary key
     * 
     * @param mixed $id Primary key value
     * @return bool Success status
     */
    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$id]);
            
            if ($result) {
                $this->clearRelatedCache();
            }
            
            return $result;
            
        } catch (PDOException $e) {
            $this->logError('delete failed', $e, ['id' => $id, 'table' => $this->table]);
            throw new Exception('Database delete failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Count records with conditions
     * 
     * @param array $conditions WHERE conditions
     * @return int Record count
     */
    public function count(array $conditions = []) {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $column => $value) {
                if (is_array($value)) {
                    $placeholders = implode(',', array_fill(0, count($value), '?'));
                    $whereClause[] = "{$column} IN ({$placeholders})";
                    $params = array_merge($params, $value);
                } else {
                    $whereClause[] = "{$column} = ?";
                    $params[] = $value;
                }
            }
            $sql .= ' WHERE ' . implode(' AND ', $whereClause);
        }
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
            
        } catch (PDOException $e) {
            $this->logError('count failed', $e, ['conditions' => $conditions, 'table' => $this->table]);
            throw new Exception('Database count failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Execute raw SQL query with parameters
     * 
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return PDOStatement
     */
    protected function query($sql, array $params = []) {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt;
            
        } catch (PDOException $e) {
            $this->logError('raw query failed', $e, ['sql' => $sql, 'params' => $params]);
            throw new Exception('Database query failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Begin database transaction
     */
    public function beginTransaction() {
        return $this->db->beginTransaction();
    }
    
    /**
     * Commit database transaction
     */
    public function commit() {
        return $this->db->commit();
    }
    
    /**
     * Rollback database transaction
     */
    public function rollback() {
        return $this->db->rollback();
    }
    
    /**
     * Execute callback within transaction
     * 
     * @param callable $callback Function to execute
     * @return mixed Callback result
     * @throws Exception If transaction fails
     */
    public function transaction(callable $callback) {
        $this->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Generate cache key
     * 
     * @param string $method Method name
     * @param array $params Parameters
     * @return string Cache key
     */
    private function getCacheKey($method, array $params = []) {
        return $this->table . ':' . $method . ':' . md5(serialize($params));
    }
    
    /**
     * Get data from cache
     * 
     * @param string $key Cache key
     * @return mixed|null Cached data or null
     */
    private function getFromCache($key) {
        if (!isset(self::$cache[$key])) {
            return null;
        }
        
        $cached = self::$cache[$key];
        if (time() > $cached['expires']) {
            unset(self::$cache[$key]);
            return null;
        }
        
        return $cached['data'];
    }
    
    /**
     * Set data in cache
     * 
     * @param string $key Cache key
     * @param mixed $data Data to cache
     */
    private function setCache($key, $data) {
        self::$cache[$key] = [
            'data' => $data,
            'expires' => time() + $this->cacheTtl
        ];
    }
    
    /**
     * Clear cache related to this table
     */
    private function clearRelatedCache() {
        foreach (array_keys(self::$cache) as $key) {
            if (strpos($key, $this->table . ':') === 0) {
                unset(self::$cache[$key]);
            }
        }
    }
    
    /**
     * Log error with context
     * 
     * @param string $message Error message
     * @param Exception $exception Exception object
     * @param array $context Additional context
     */
    private function logError($message, Exception $exception, array $context = []) {
        if (function_exists('logMessage')) {
            logMessage('ERROR', $message . ': ' . $exception->getMessage(), $context);
        } else {
            error_log($message . ': ' . $exception->getMessage() . ' Context: ' . json_encode($context));
        }
    }
    
    /**
     * Execute optimized query with automatic optimization and caching
     * 
     * @param string $sql SQL query to execute
     * @param array $params Query parameters
     * @param bool $returnArray Whether to return results as array
     * @return array Query results
     */
    protected function executeOptimizedQuery(string $sql, array $params = [], bool $returnArray = true): array {
        global $sharedDatabase;
        
        try {
            // Use shared database utilities for consistency
            return $sharedDatabase->executeOptimizedQuery($sql, $params);
            
        } catch (Exception $e) {
            // Fallback to regular query execution
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $returnArray ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [$stmt->fetch(PDO::FETCH_ASSOC)];
        }
    }
    
    /**
     * Get optimization recommendations for this repository's table
     * 
     * @return array Optimization recommendations
     */
    public function getOptimizationRecommendations(): array {
        $recommendations = $this->databaseOptimizer->getOptimizationRecommendations();
        return $recommendations['index_recommendations'][$this->table] ?? [];
    }
    
    /**
     * Analyze query performance for this table
     * 
     * @param string $sql SQL query to analyze
     * @param array $params Query parameters
     * @return array Performance analysis
     */
    public function analyzeQueryPerformance(string $sql, array $params = []): array {
        return $this->databaseOptimizer->optimizeQuery($sql, $params);
    }
}
