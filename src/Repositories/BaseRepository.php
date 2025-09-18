<?php

declare(strict_types=1);

namespace RfidCheckin\Repositories;

use RfidCheckin\Services\DatabaseService;
use RfidCheckin\Services\LoggingService;
use Exception;
use PDOStatement;

/**
 * Base Repository Class
 * 
 * Abstract foundation for all repository classes providing common database
 * operations, transaction management, and standardized error handling.
 * Implements the Repository pattern for clean separation of data access logic.
 * 
 * Features:
 * - Centralized database access through DatabaseService
 * - Standardized CRUD operations
 * - Transaction support with automatic rollback
 * - Query performance monitoring
 * - Comprehensive error handling and logging
 * - Input validation and sanitization
 * - Caching support for frequent queries
 * 
 * @package RfidCheckin\Repositories
 * @version 1.0.0
 * @author Senior Development Team
 */
abstract class BaseRepository
{
    protected DatabaseService $db;
    protected LoggingService $logger;
    protected string $tableName;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $hidden = [];
    protected array $queryCache = [];
    protected int $cacheTimeout = 300; // 5 minutes

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = DatabaseService::getInstance();
        $this->logger = LoggingService::getInstance();
    }

    /**
     * Find record by primary key
     * 
     * @param mixed $id Primary key value
     * @param array $columns Columns to select
     * @return array|null Record data or null if not found
     * @throws Exception If query fails
     */
    public function find($id, array $columns = ['*']): ?array
    {
        $columnList = implode(', ', $columns);
        $query = "SELECT {$columnList} FROM {$this->tableName} WHERE {$this->primaryKey} = ?";
        
        $result = $this->db->selectOne($query, [$id]);
        
        if ($result) {
            return $this->filterHiddenFields($result);
        }
        
        return null;
    }

    /**
     * Find multiple records by criteria
     * 
     * @param array $criteria Search criteria
     * @param array $columns Columns to select
     * @param string $orderBy Order by clause
     * @param int|null $limit Limit number of results
     * @param int $offset Offset for pagination
     * @return array Array of records
     * @throws Exception If query fails
     */
    public function findBy(
        array $criteria = [], 
        array $columns = ['*'], 
        string $orderBy = '', 
        ?int $limit = null, 
        int $offset = 0
    ): array {
        $columnList = implode(', ', $columns);
        $query = "SELECT {$columnList} FROM {$this->tableName}";
        $params = [];
        
        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $field => $value) {
                if (is_array($value)) {
                    $placeholders = str_repeat('?,', count($value) - 1) . '?';
                    $conditions[] = "{$field} IN ({$placeholders})";
                    $params = array_merge($params, $value);
                } else {
                    $conditions[] = "{$field} = ?";
                    $params[] = $value;
                }
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        if ($orderBy) {
            $query .= " ORDER BY {$orderBy}";
        }
        
        if ($limit !== null) {
            $query .= " LIMIT {$limit}";
            if ($offset > 0) {
                $query .= " OFFSET {$offset}";
            }
        }
        
        $results = $this->db->select($query, $params);
        
        return array_map([$this, 'filterHiddenFields'], $results);
    }

    /**
     * Find single record by criteria
     * 
     * @param array $criteria Search criteria
     * @param array $columns Columns to select
     * @return array|null Record data or null if not found
     * @throws Exception If query fails
     */
    public function findOneBy(array $criteria, array $columns = ['*']): ?array
    {
        $results = $this->findBy($criteria, $columns, '', 1);
        return $results[0] ?? null;
    }

    /**
     * Create new record
     * 
     * @param array $data Record data
     * @return string Last insert ID
     * @throws Exception If insert fails
     */
    public function create(array $data): string
    {
        $data = $this->filterFillableFields($data);
        $data = $this->addTimestamps($data, true);
        
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        
        $query = "INSERT INTO {$this->tableName} (" . implode(', ', $fields) . ") VALUES ({$placeholders})";
        
        $this->logger->debug("Creating record in {$this->tableName}", ['data' => $data]);
        
        return $this->db->insert($query, array_values($data));
    }

    /**
     * Update record by primary key
     * 
     * @param mixed $id Primary key value
     * @param array $data Update data
     * @return int Number of affected rows
     * @throws Exception If update fails
     */
    public function update($id, array $data): int
    {
        $data = $this->filterFillableFields($data);
        $data = $this->addTimestamps($data, false);
        
        $fields = array_keys($data);
        $setClause = implode(' = ?, ', $fields) . ' = ?';
        
        $query = "UPDATE {$this->tableName} SET {$setClause} WHERE {$this->primaryKey} = ?";
        $params = array_merge(array_values($data), [$id]);
        
        $this->logger->debug("Updating record in {$this->tableName}", ['id' => $id, 'data' => $data]);
        
        return $this->db->update($query, $params);
    }

    /**
     * Update records by criteria
     * 
     * @param array $criteria Update criteria
     * @param array $data Update data
     * @return int Number of affected rows
     * @throws Exception If update fails
     */
    public function updateBy(array $criteria, array $data): int
    {
        $data = $this->filterFillableFields($data);
        $data = $this->addTimestamps($data, false);
        
        $fields = array_keys($data);
        $setClause = implode(' = ?, ', $fields) . ' = ?';
        
        $query = "UPDATE {$this->tableName} SET {$setClause}";
        $params = array_values($data);
        
        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $field => $value) {
                $conditions[] = "{$field} = ?";
                $params[] = $value;
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $this->logger->debug("Updating records in {$this->tableName}", ['criteria' => $criteria, 'data' => $data]);
        
        return $this->db->update($query, $params);
    }

    /**
     * Delete record by primary key
     * 
     * @param mixed $id Primary key value
     * @return int Number of affected rows
     * @throws Exception If delete fails
     */
    public function delete($id): int
    {
        $query = "DELETE FROM {$this->tableName} WHERE {$this->primaryKey} = ?";
        
        $this->logger->debug("Deleting record from {$this->tableName}", ['id' => $id]);
        
        return $this->db->delete($query, [$id]);
    }

    /**
     * Delete records by criteria
     * 
     * @param array $criteria Delete criteria
     * @return int Number of affected rows
     * @throws Exception If delete fails
     */
    public function deleteBy(array $criteria): int
    {
        $query = "DELETE FROM {$this->tableName}";
        $params = [];
        
        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $field => $value) {
                $conditions[] = "{$field} = ?";
                $params[] = $value;
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $this->logger->debug("Deleting records from {$this->tableName}", ['criteria' => $criteria]);
        
        return $this->db->delete($query, $params);
    }

    /**
     * Count records by criteria
     * 
     * @param array $criteria Count criteria
     * @return int Number of records
     * @throws Exception If query fails
     */
    public function count(array $criteria = []): int
    {
        $query = "SELECT COUNT(*) as count FROM {$this->tableName}";
        $params = [];
        
        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $field => $value) {
                $conditions[] = "{$field} = ?";
                $params[] = $value;
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $result = $this->db->selectOne($query, $params);
        return (int) $result['count'];
    }

    /**
     * Check if record exists
     * 
     * @param array $criteria Search criteria
     * @return bool True if record exists
     * @throws Exception If query fails
     */
    public function exists(array $criteria): bool
    {
        return $this->count($criteria) > 0;
    }

    /**
     * Get paginated results
     * 
     * @param int $page Page number (1-based)
     * @param int $perPage Records per page
     * @param array $criteria Search criteria
     * @param string $orderBy Order by clause
     * @return array Paginated results with metadata
     * @throws Exception If query fails
     */
    public function paginate(int $page = 1, int $perPage = 20, array $criteria = [], string $orderBy = ''): array
    {
        $offset = ($page - 1) * $perPage;
        $total = $this->count($criteria);
        $totalPages = ceil($total / $perPage);
        
        $records = $this->findBy($criteria, ['*'], $orderBy, $perPage, $offset);
        
        return [
            'data' => $records,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ];
    }

    /**
     * Execute raw SQL query
     * 
     * @param string $query SQL query
     * @param array $params Query parameters
     * @return array Query results
     * @throws Exception If query fails
     */
    protected function query(string $query, array $params = []): array
    {
        return $this->db->select($query, $params);
    }

    /**
     * Execute raw SQL query and return single result
     * 
     * @param string $query SQL query
     * @param array $params Query parameters
     * @return array|null Single result or null
     * @throws Exception If query fails
     */
    protected function queryOne(string $query, array $params = []): ?array
    {
        return $this->db->selectOne($query, $params);
    }

    /**
     * Filter fields to only include fillable ones
     * 
     * @param array $data Input data
     * @return array Filtered data
     */
    protected function filterFillableFields(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Filter out hidden fields from result
     * 
     * @param array $data Result data
     * @return array Filtered data
     */
    protected function filterHiddenFields(array $data): array
    {
        if (empty($this->hidden)) {
            return $data;
        }
        
        return array_diff_key($data, array_flip($this->hidden));
    }

    /**
     * Add timestamps to data
     * 
     * @param array $data Input data
     * @param bool $isCreate Whether this is a create operation
     * @return array Data with timestamps
     */
    protected function addTimestamps(array $data, bool $isCreate): array
    {
        $now = date('Y-m-d H:i:s');
        
        if ($isCreate && !isset($data['created_at'])) {
            $data['created_at'] = $now;
        }
        
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = $now;
        }
        
        return $data;
    }

    /**
     * Begin transaction
     * 
     * @throws Exception If transaction cannot be started
     */
    protected function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    /**
     * Commit transaction
     * 
     * @throws Exception If transaction cannot be committed
     */
    protected function commit(): void
    {
        $this->db->commit();
    }

    /**
     * Rollback transaction
     * 
     * @throws Exception If transaction cannot be rolled back
     */
    protected function rollback(): void
    {
        $this->db->rollback();
    }

    /**
     * Execute callback within transaction
     * 
     * @param callable $callback Function to execute
     * @return mixed Return value from callback
     * @throws Exception If transaction fails
     */
    protected function transaction(callable $callback)
    {
        return $this->db->transaction($callback);
    }

    /**
     * Cache query result
     * 
     * @param string $key Cache key
     * @param mixed $data Data to cache
     */
    protected function cacheSet(string $key, $data): void
    {
        $this->queryCache[$key] = [
            'data' => $data,
            'timestamp' => time()
        ];
    }

    /**
     * Get cached query result
     * 
     * @param string $key Cache key
     * @return mixed Cached data or null if not found/expired
     */
    protected function cacheGet(string $key)
    {
        if (!isset($this->queryCache[$key])) {
            return null;
        }
        
        $cached = $this->queryCache[$key];
        if (time() - $cached['timestamp'] > $this->cacheTimeout) {
            unset($this->queryCache[$key]);
            return null;
        }
        
        return $cached['data'];
    }

    /**
     * Clear query cache
     */
    protected function cacheClear(): void
    {
        $this->queryCache = [];
    }

    /**
     * Validate required fields
     * 
     * @param array $data Input data
     * @param array $required Required field names
     * @throws Exception If required fields are missing
     */
    protected function validateRequired(array $data, array $required): void
    {
        $missing = array_diff($required, array_keys($data));
        
        if (!empty($missing)) {
            throw new Exception('Missing required fields: ' . implode(', ', $missing));
        }
    }

    /**
     * Sanitize input data
     * 
     * @param array $data Input data
     * @return array Sanitized data
     */
    protected function sanitizeInput(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = trim($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
}
