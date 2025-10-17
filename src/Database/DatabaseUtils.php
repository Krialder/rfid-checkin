<?php

declare(strict_types=1);

namespace RfidCheckin\Database;

use PDO;
use Exception;
use InvalidArgumentException;

/**
 * Database Utilities Class
 * 
 * Provides common database operations with proper error handling,
 * parameter binding, and performance optimization.
 * 
 * @package RfidCheckin\Database
 * @version 2.0.0
 * @author Senior Development Team
 */
class DatabaseUtils
{
    private ConnectionManager $connectionManager;

    /**
     * Constructor
     * 
     * @param ConnectionManager|null $connectionManager Connection manager instance
     */
    public function __construct(?ConnectionManager $connectionManager = null)
    {
        $this->connectionManager = $connectionManager ?? ConnectionManager::getInstance();
    }

    /**
     * Execute query and return single row
     * 
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return array|null Single row or null
     * @throws Exception If query fails
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $statement = $this->connectionManager->executeQuery($sql, $params);
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        
        return $result === false ? null : $result;
    }

    /**
     * Execute query and return all rows
     * 
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return array Array of rows
     * @throws Exception If query fails
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->connectionManager->executeQuery($sql, $params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute query and return single value
     * 
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return mixed Single value
     * @throws Exception If query fails
     */
    public function fetchValue(string $sql, array $params = [])
    {
        $statement = $this->connectionManager->executeQuery($sql, $params);
        return $statement->fetchColumn();
    }

    /**
     * Insert data into table
     * 
     * @param string $table Table name
     * @param array $data Data to insert
     * @return string Last insert ID
     * @throws Exception If insert fails
     */
    public function insert(string $table, array $data): string
    {
        if (empty($data)) {
            throw new InvalidArgumentException('Data array cannot be empty');
        }

        $this->validateTableName($table);
        
        $columns = array_keys($data);
        $placeholders = ':' . implode(', :', $columns);
        $columnList = '`' . implode('`, `', $columns) . '`';
        
        $sql = "INSERT INTO `$table` ($columnList) VALUES ($placeholders)";
        
        $params = [];
        foreach ($data as $key => $value) {
            $params[":$key"] = $value;
        }
        
        $this->connectionManager->executeQuery($sql, $params);
        
        return $this->connectionManager->lastInsertId();
    }

    /**
     * Update data in table
     * 
     * @param string $table Table name
     * @param array $data Data to update
     * @param array $where WHERE conditions
     * @return int Number of affected rows
     * @throws Exception If update fails
     */
    public function update(string $table, array $data, array $where): int
    {
        if (empty($data)) {
            throw new InvalidArgumentException('Data array cannot be empty');
        }

        if (empty($where)) {
            throw new InvalidArgumentException('WHERE conditions cannot be empty for safety');
        }

        $this->validateTableName($table);
        
        $setParts = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $setParts[] = "`$key` = :set_$key";
            $params[":set_$key"] = $value;
        }
        
        $whereParts = [];
        foreach ($where as $key => $value) {
            $whereParts[] = "`$key` = :where_$key";
            $params[":where_$key"] = $value;
        }
        
        $sql = "UPDATE `$table` SET " . implode(', ', $setParts) . 
               " WHERE " . implode(' AND ', $whereParts);
        
        $statement = $this->connectionManager->executeQuery($sql, $params);
        return $statement->rowCount();
    }

    /**
     * Delete data from table
     * 
     * @param string $table Table name
     * @param array $where WHERE conditions
     * @return int Number of affected rows
     * @throws Exception If delete fails
     */
    public function delete(string $table, array $where): int
    {
        if (empty($where)) {
            throw new InvalidArgumentException('WHERE conditions cannot be empty for safety');
        }

        $this->validateTableName($table);
        
        $whereParts = [];
        $params = [];
        
        foreach ($where as $key => $value) {
            $whereParts[] = "`$key` = :$key";
            $params[":$key"] = $value;
        }
        
        $sql = "DELETE FROM `$table` WHERE " . implode(' AND ', $whereParts);
        
        $statement = $this->connectionManager->executeQuery($sql, $params);
        return $statement->rowCount();
    }

    /**
     * Check if record exists
     * 
     * @param string $table Table name
     * @param array $where WHERE conditions
     * @return bool True if exists
     * @throws Exception If query fails
     */
    public function exists(string $table, array $where): bool
    {
        $this->validateTableName($table);
        
        $whereParts = [];
        $params = [];
        
        foreach ($where as $key => $value) {
            $whereParts[] = "`$key` = :$key";
            $params[":$key"] = $value;
        }
        
        $sql = "SELECT 1 FROM `$table` WHERE " . implode(' AND ', $whereParts) . " LIMIT 1";
        
        return $this->fetchValue($sql, $params) !== false;
    }

    /**
     * Count records
     * 
     * @param string $table Table name
     * @param array $where WHERE conditions (optional)
     * @return int Record count
     * @throws Exception If query fails
     */
    public function count(string $table, array $where = []): int
    {
        $this->validateTableName($table);
        
        $sql = "SELECT COUNT(*) FROM `$table`";
        $params = [];
        
        if (!empty($where)) {
            $whereParts = [];
            foreach ($where as $key => $value) {
                $whereParts[] = "`$key` = :$key";
                $params[":$key"] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereParts);
        }
        
        return (int) $this->fetchValue($sql, $params);
    }

    /**
     * Get paginated results
     * 
     * @param string $sql Base SQL query
     * @param array $params Query parameters
     * @param int $page Page number (1-based)
     * @param int $perPage Results per page
     * @return array Paginated results with metadata
     * @throws Exception If query fails
     */
    public function paginate(string $sql, array $params = [], int $page = 1, int $perPage = 20): array
    {
        if ($page < 1) {
            throw new InvalidArgumentException('Page number must be greater than 0');
        }

        if ($perPage < 1 || $perPage > 1000) {
            throw new InvalidArgumentException('Per page must be between 1 and 1000');
        }

        // Get total count
        $countSql = "SELECT COUNT(*) FROM ($sql) as count_query";
        $totalRecords = (int) $this->fetchValue($countSql, $params);
        
        // Calculate pagination
        $totalPages = (int) ceil($totalRecords / $perPage);
        $offset = ($page - 1) * $perPage;
        
        // Get paginated results
        $paginatedSql = $sql . " LIMIT :limit OFFSET :offset";
        $paginatedParams = array_merge($params, [
            ':limit' => $perPage,
            ':offset' => $offset
        ]);
        
        $results = $this->fetchAll($paginatedSql, $paginatedParams);
        
        return [
            'data' => $results,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $totalRecords,
                'total_pages' => $totalPages,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
                'previous_page' => $page > 1 ? $page - 1 : null,
                'next_page' => $page < $totalPages ? $page + 1 : null
            ]
        ];
    }

    /**
     * Execute bulk insert
     * 
     * @param string $table Table name
     * @param array $data Array of data arrays
     * @param int $batchSize Batch size for processing
     * @return int Number of inserted records
     * @throws Exception If insert fails
     */
    public function bulkInsert(string $table, array $data, int $batchSize = 1000): int
    {
        if (empty($data)) {
            return 0;
        }

        $this->validateTableName($table);
        
        $totalInserted = 0;
        $chunks = array_chunk($data, $batchSize);
        
        foreach ($chunks as $chunk) {
            $inserted = $this->connectionManager->transaction(function() use ($table, $chunk) {
                $count = 0;
                foreach ($chunk as $row) {
                    $this->insert($table, $row);
                    $count++;
                }
                return $count;
            });
            
            $totalInserted += $inserted;
        }
        
        return $totalInserted;
    }

    /**
     * Upsert (Insert or Update) operation
     * 
     * @param string $table Table name
     * @param array $data Data to insert/update
     * @param array $uniqueKeys Unique keys for conflict resolution
     * @return string Insert ID or 0 for update
     * @throws Exception If operation fails
     */
    public function upsert(string $table, array $data, array $uniqueKeys): string
    {
        if (empty($data) || empty($uniqueKeys)) {
            throw new InvalidArgumentException('Data and unique keys cannot be empty');
        }

        $this->validateTableName($table);
        
        // Check if record exists
        $whereClause = [];
        foreach ($uniqueKeys as $key) {
            if (!isset($data[$key])) {
                throw new InvalidArgumentException("Unique key '$key' not found in data");
            }
            $whereClause[$key] = $data[$key];
        }
        
        if ($this->exists($table, $whereClause)) {
            // Update existing record
            $updateData = array_diff_key($data, $whereClause);
            if (!empty($updateData)) {
                $this->update($table, $updateData, $whereClause);
            }
            return '0';
        } else {
            // Insert new record
            return $this->insert($table, $data);
        }
    }

    /**
     * Get table information
     * 
     * @param string $table Table name
     * @return array Table structure
     * @throws Exception If query fails
     */
    public function getTableInfo(string $table): array
    {
        $this->validateTableName($table);
        
        $sql = "DESCRIBE `$table`";
        return $this->fetchAll($sql);
    }

    /**
     * Check if table exists
     * 
     * @param string $table Table name
     * @return bool True if table exists
     * @throws Exception If query fails
     */
    public function tableExists(string $table): bool
    {
        $this->validateTableName($table);
        
        $sql = "SELECT COUNT(*) FROM information_schema.tables 
                WHERE table_schema = DATABASE() AND table_name = :table";
        
        return $this->fetchValue($sql, [':table' => $table]) > 0;
    }

    /**
     * Validate table name to prevent SQL injection
     * 
     * @param string $table Table name
     * @throws InvalidArgumentException If table name is invalid
     */
    private function validateTableName(string $table): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new InvalidArgumentException("Invalid table name: $table");
        }
    }

    /**
     * Get connection manager
     * 
     * @return ConnectionManager Connection manager instance
     */
    public function getConnectionManager(): ConnectionManager
    {
        return $this->connectionManager;
    }
}