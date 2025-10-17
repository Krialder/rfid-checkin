<?php

declare(strict_types=1);

namespace RfidCheckin\Services;

use PDO;
use PDOException;
use PDOStatement;
use Exception;

/**
 * Centralized Database Service
 * 
 * Provides secure, efficient database connectivity with connection pooling,
 * query optimization, transaction management, and comprehensive error handling.
 * Replaces all scattered database connection patterns throughout the application.
 * 
 * Features:
 * - Singleton pattern for connection reuse
 * - Automatic connection health monitoring
 * - Prepared statement management
 * - Transaction support with automatic rollback
 * - Query performance monitoring
 * - Connection pooling simulation
 * - Comprehensive error logging
 * 
 * @package RfidCheckin\Services
 * @version 1.0.0
 * @author Senior Development Team
 */
class DatabaseService
{
    private static ?DatabaseService $instance = null;
    private ?PDO $connection = null;
    private array $connectionConfig;
    private bool $inTransaction = false;
    private array $queryStats = [];
    private ?LoggingService $logger;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        // Try to load logging service, but don't fail if not available
        try {
            if (class_exists('RfidCheckin\\Services\\LoggingService')) {
                $this->logger = LoggingService::getInstance();
            } else {
                $this->logger = null;
            }
        } catch (Exception $e) {
            // Logging service not available, continue without it
            $this->logger = null;
        }
        $this->loadConfiguration();
    }

    /**
     * Safe logging method that won't fail if logger is not available
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->$level($message, $context);
        }
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load database configuration
     */
    private function loadConfiguration(): void
    {
        $config = ConfigurationService::getInstance();
        
        $this->connectionConfig = [
            'host' => $config->get('database.host', 'localhost'),
            'dbname' => $config->get('database.name', 'rfid_checking'),
            'username' => $config->get('database.username', 'root'),
            'password' => $config->get('database.password', ''),
            'charset' => $config->get('database.charset', 'utf8mb4'),
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                PDO::ATTR_TIMEOUT => 10,
            ]
        ];
    }

    /**
     * Get active database connection
     * 
     * @throws Exception If connection cannot be established
     */
    public function getConnection(): PDO
    {
        if ($this->connection === null || !$this->isConnectionAlive()) {
            $this->connect();
        }
        return $this->connection;
    }

    /**
     * Establish database connection
     * 
     * @throws Exception If connection fails
     */
    private function connect(): void
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $this->connectionConfig['host'],
                $this->connectionConfig['dbname'],
                $this->connectionConfig['charset']
            );

            $this->connection = new PDO(
                $dsn,
                $this->connectionConfig['username'],
                $this->connectionConfig['password'],
                $this->connectionConfig['options']
            );

            // Set timezone to UTC for consistent timestamps
            $this->connection->exec("SET time_zone = '+00:00'");

            $this->log('info', 'Database connection established', [
                'host' => $this->connectionConfig['host'],
                'database' => $this->connectionConfig['dbname']
            ]);

        } catch (PDOException $e) {
            $this->log('error', 'Database connection failed', [
                'error' => $e->getMessage(),
                'host' => $this->connectionConfig['host'],
                'database' => $this->connectionConfig['dbname']
            ]);
            
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if connection is still alive
     */
    private function isConnectionAlive(): bool
    {
        try {
            $this->connection->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            $this->logger->warning('Database connection lost', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Execute SELECT query with parameters
     * 
     * @param string $query SQL query with parameter placeholders
     * @param array $params Query parameters
     * @return array Query results
     * @throws Exception If query execution fails
     */
    public function select(string $query, array $params = []): array
    {
        $startTime = microtime(true);
        
        try {
            $statement = $this->getConnection()->prepare($query);
            $statement->execute($params);
            $result = $statement->fetchAll();
            
            $this->logQueryPerformance($query, $params, $startTime);
            
            return $result;
            
        } catch (PDOException $e) {
            $this->logger->error('SELECT query failed', [
                'query' => $query,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Query execution failed: ' . $e->getMessage());
        }
    }

    /**
     * Execute SELECT query and return single row
     * 
     * @param string $query SQL query with parameter placeholders
     * @param array $params Query parameters
     * @return array|null Single row or null if not found
     * @throws Exception If query execution fails
     */
    public function selectOne(string $query, array $params = []): ?array
    {
        $result = $this->select($query, $params);
        return $result[0] ?? null;
    }

    /**
     * Execute INSERT query and return last insert ID
     * 
     * @param string $query SQL INSERT query
     * @param array $params Query parameters
     * @return string Last insert ID
     * @throws Exception If query execution fails
     */
    public function insert(string $query, array $params = []): string
    {
        $startTime = microtime(true);
        
        try {
            $statement = $this->getConnection()->prepare($query);
            $statement->execute($params);
            $lastId = $this->getConnection()->lastInsertId();
            
            $this->logQueryPerformance($query, $params, $startTime);
            
            return $lastId;
            
        } catch (PDOException $e) {
            $this->logger->error('INSERT query failed', [
                'query' => $query,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Insert operation failed: ' . $e->getMessage());
        }
    }

    /**
     * Execute UPDATE query and return affected rows
     * 
     * @param string $query SQL UPDATE query
     * @param array $params Query parameters
     * @return int Number of affected rows
     * @throws Exception If query execution fails
     */
    public function update(string $query, array $params = []): int
    {
        return $this->executeModifyingQuery($query, $params, 'UPDATE');
    }

    /**
     * Execute DELETE query and return affected rows
     * 
     * @param string $query SQL DELETE query
     * @param array $params Query parameters
     * @return int Number of affected rows
     * @throws Exception If query execution fails
     */
    public function delete(string $query, array $params = []): int
    {
        return $this->executeModifyingQuery($query, $params, 'DELETE');
    }

    /**
     * Execute modifying query (UPDATE/DELETE)
     * 
     * @param string $query SQL query
     * @param array $params Query parameters
     * @param string $type Query type for logging
     * @return int Number of affected rows
     * @throws Exception If query execution fails
     */
    private function executeModifyingQuery(string $query, array $params, string $type): int
    {
        $startTime = microtime(true);
        
        try {
            $statement = $this->getConnection()->prepare($query);
            $statement->execute($params);
            $affectedRows = $statement->rowCount();
            
            $this->logQueryPerformance($query, $params, $startTime);
            
            return $affectedRows;
            
        } catch (PDOException $e) {
            $this->logger->error("$type query failed", [
                'query' => $query,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw new Exception("$type operation failed: " . $e->getMessage());
        }
    }

    /**
     * Begin database transaction
     * 
     * @throws Exception If transaction cannot be started
     */
    public function beginTransaction(): void
    {
        if ($this->inTransaction) {
            throw new Exception('Transaction already in progress');
        }

        try {
            $this->getConnection()->beginTransaction();
            $this->inTransaction = true;
            $this->logger->debug('Database transaction started');
        } catch (PDOException $e) {
            throw new Exception('Failed to start transaction: ' . $e->getMessage());
        }
    }

    /**
     * Commit database transaction
     * 
     * @throws Exception If transaction cannot be committed
     */
    public function commit(): void
    {
        if (!$this->inTransaction) {
            throw new Exception('No active transaction to commit');
        }

        try {
            $this->getConnection()->commit();
            $this->inTransaction = false;
            $this->logger->debug('Database transaction committed');
        } catch (PDOException $e) {
            throw new Exception('Failed to commit transaction: ' . $e->getMessage());
        }
    }

    /**
     * Rollback database transaction
     * 
     * @throws Exception If transaction cannot be rolled back
     */
    public function rollback(): void
    {
        if (!$this->inTransaction) {
            throw new Exception('No active transaction to rollback');
        }

        try {
            $this->getConnection()->rollback();
            $this->inTransaction = false;
            $this->logger->debug('Database transaction rolled back');
        } catch (PDOException $e) {
            throw new Exception('Failed to rollback transaction: ' . $e->getMessage());
        }
    }

    /**
     * Execute callback within transaction with automatic rollback on error
     * 
     * @param callable $callback Function to execute within transaction
     * @return mixed Return value from callback
     * @throws Exception If transaction fails
     */
    public function transaction(callable $callback)
    {
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
     * Get prepared statement for complex operations
     * 
     * @param string $query SQL query
     * @return PDOStatement Prepared statement
     * @throws Exception If statement preparation fails
     */
    public function prepare(string $query): PDOStatement
    {
        try {
            return $this->getConnection()->prepare($query);
        } catch (PDOException $e) {
            $this->logger->error('Failed to prepare statement', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Failed to prepare statement: ' . $e->getMessage());
        }
    }

    /**
     * Check if table exists
     * 
     * @param string $tableName Table name to check
     * @return bool True if table exists
     */
    public function tableExists(string $tableName): bool
    {
        try {
            $result = $this->selectOne(
                "SELECT 1 FROM information_schema.tables 
                 WHERE table_schema = ? AND table_name = ?",
                [$this->connectionConfig['dbname'], $tableName]
            );
            return $result !== null;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get database statistics
     * 
     * @return array Database statistics
     */
    public function getStats(): array
    {
        $connection = $this->getConnection();
        
        return [
            'connected' => $connection !== null,
            'server_version' => $connection->getAttribute(PDO::ATTR_SERVER_VERSION),
            'connection_status' => $connection->getAttribute(PDO::ATTR_CONNECTION_STATUS),
            'database' => $this->connectionConfig['dbname'],
            'charset' => $this->connectionConfig['charset'],
            'query_count' => count($this->queryStats),
            'in_transaction' => $this->inTransaction
        ];
    }

    /**
     * Log query performance for monitoring
     * 
     * @param string $query SQL query
     * @param array $params Query parameters
     * @param float $startTime Query start time
     */
    private function logQueryPerformance(string $query, array $params, float $startTime): void
    {
        $duration = microtime(true) - $startTime;
        
        $this->queryStats[] = [
            'query' => substr($query, 0, 100) . (strlen($query) > 100 ? '...' : ''),
            'duration' => $duration,
            'timestamp' => time()
        ];

        // Log slow queries (> 1 second)
        if ($duration > 1.0) {
            $this->logger->warning('Slow query detected', [
                'query' => $query,
                'params' => $params,
                'duration' => $duration
            ]);
        }
    }

    /**
     * Optimize database tables
     * 
     * @return bool True if optimization successful
     */
    public function optimizeTables(): bool
    {
        try {
            $tables = $this->select("SHOW TABLES");
            $tableColumn = 'Tables_in_' . $this->connectionConfig['dbname'];
            
            foreach ($tables as $table) {
                $tableName = $table[$tableColumn];
                $this->getConnection()->exec("OPTIMIZE TABLE `$tableName`");
                $this->logger->debug("Optimized table: $tableName");
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Table optimization failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Test database connection
     * 
     * @return bool True if connection successful
     */
    public function testConnection(): bool
    {
        try {
            $connection = $this->getConnection();
            $connection->query('SELECT 1');
            return true;
        } catch (Exception $e) {
            $this->logger->error('Database connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get query count for performance monitoring
     */
    public function getQueryCount(): int
    {
        return count($this->queryStats);
    }

    /**
     * Close database connection
     */
    public function closeConnection(): void
    {
        $this->connection = null;
    }
}
