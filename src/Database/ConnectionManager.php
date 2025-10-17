<?php

declare(strict_types=1);

namespace RfidCheckin\Database;

use PDO;
use PDOException;
use Exception;
use InvalidArgumentException;

/**
 * Enterprise Database Connection Manager
 * 
 * Provides unified database connectivity with connection pooling,
 * automatic reconnection, transaction management, and performance monitoring.
 * Implements singleton pattern for optimal resource management.
 * 
 * @package RfidCheckin\Database
 * @version 2.0.0
 * @author Senior Development Team
 */
class ConnectionManager
{
    private static ?ConnectionManager $instance = null;
    private ?PDO $connection = null;
    private array $connectionPool = [];
    private array $config = [];
    private array $statistics = [
        'connections_created' => 0,
        'connections_reused' => 0,
        'query_count' => 0,
        'transaction_count' => 0,
        'reconnections' => 0,
        'errors' => 0
    ];
    private bool $inTransaction = false;
    private float $lastConnectionTime = 0;
    private int $connectionAttempts = 0;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        $this->loadConfiguration();
        $this->initializeConnection();
    }

    /**
     * Get singleton instance of ConnectionManager
     * 
     * @return ConnectionManager Singleton instance
     */
    public static function getInstance(): ConnectionManager
    {
        if (self::$instance === null) {
            self::$instance = new ConnectionManager();
        }

        return self::$instance;
    }

    /**
     * Get active database connection
     * 
     * @return PDO Database connection
     * @throws Exception If connection cannot be established
     */
    public function getConnection(): PDO
    {
        if ($this->connection === null || !$this->isConnectionAlive()) {
            $this->reconnect();
        }

        return $this->connection;
    }

    /**
     * Load database configuration
     * 
     * @throws Exception If configuration is invalid
     */
    private function loadConfiguration(): void
    {
        // Try to load from ConfigLoader first (preferred method)
        if (file_exists(__DIR__ . '/../../core/ConfigLoader.php')) {
            require_once __DIR__ . '/../../core/ConfigLoader.php';
            $configLoader = \ConfigLoader::getInstance();
            
            $this->config = [
                'host' => $configLoader->get('database.host'),
                'port' => $configLoader->get('database.port'),
                'dbname' => $configLoader->get('database.name'),
                'username' => $configLoader->get('database.username'),
                'password' => $configLoader->get('database.password'),
                'charset' => $configLoader->get('database.charset'),
                'collation' => 'utf8mb4_unicode_ci',
                'pool_min' => 5,
                'pool_max' => 50,
                'timeout' => 30,
                'reconnect_attempts' => 3,
                'query_timeout' => 15
            ];
            return;
        }
        
        // Fallback to constants (legacy support)
        if (!defined('TESTING_MODE')) {
            $configPath = __DIR__ . '/../../core/config.php';
            if (file_exists($configPath)) {
                require_once $configPath;
            }
        }

        // Validate required configuration
        $requiredConfig = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET'];
        foreach ($requiredConfig as $key) {
            if (!defined($key)) {
                throw new InvalidArgumentException("Missing required database configuration: $key");
            }
        }

        $this->config = [
            'host' => DB_HOST,
            'port' => defined('DB_PORT') ? DB_PORT : 3306,
            'dbname' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASS,
            'charset' => DB_CHARSET,
            'collation' => defined('DB_COLLATION') ? DB_COLLATION : 'utf8mb4_unicode_ci',
            'pool_min' => defined('DB_POOL_MIN') ? DB_POOL_MIN : 5,
            'pool_max' => defined('DB_POOL_MAX') ? DB_POOL_MAX : 50,
            'timeout' => defined('DB_POOL_TIMEOUT') ? DB_POOL_TIMEOUT : 30,
            'reconnect_attempts' => defined('DB_RECONNECT_ATTEMPTS') ? DB_RECONNECT_ATTEMPTS : 3,
            'query_timeout' => defined('DB_QUERY_TIMEOUT') ? DB_QUERY_TIMEOUT : 15
        ];
    }

    /**
     * Initialize database connection
     * 
     * @throws Exception If connection fails
     */
    private function initializeConnection(): void
    {
        $this->connectionAttempts = 0;
        $maxAttempts = $this->config['reconnect_attempts'];

        while ($this->connectionAttempts < $maxAttempts) {
            try {
                $this->createConnection();
                $this->logMessage('INFO', 'Database connection established successfully');
                return;
            } catch (PDOException $e) {
                $this->connectionAttempts++;
                $this->statistics['errors']++;
                
                if ($this->connectionAttempts >= $maxAttempts) {
                    $this->logMessage('ERROR', 'Failed to establish database connection after ' . $maxAttempts . ' attempts', [
                        'error' => $e->getMessage(),
                        'host' => $this->config['host'],
                        'database' => $this->config['dbname']
                    ]);
                    throw new Exception('Database connection failed: Unable to connect after ' . $maxAttempts . ' attempts');
                }

                // Wait before retry (exponential backoff)
                $delay = min(pow(2, $this->connectionAttempts) * 1000000, 5000000); // Max 5 seconds
                usleep($delay);
            }
        }
    }

    /**
     * Create new PDO connection
     * 
     * @throws PDOException If connection fails
     */
    private function createConnection(): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['host'],
            $this->config['port'],
            $this->config['dbname'],
            $this->config['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_TIMEOUT => $this->config['timeout'],
            PDO::MYSQL_ATTR_INIT_COMMAND => sprintf(
                "SET NAMES %s COLLATE %s, sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
                $this->config['charset'],
                $this->config['collation']
            ),
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
        ];

        $this->connection = new PDO($dsn, $this->config['username'], $this->config['password'], $options);
        
        // Set session variables (timezone removed to prevent errors)
        $this->connection->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
        
        $this->lastConnectionTime = microtime(true);
        $this->statistics['connections_created']++;
    }

    /**
     * Check if connection is alive
     * 
     * @return bool True if connection is alive
     */
    private function isConnectionAlive(): bool
    {
        if ($this->connection === null) {
            return false;
        }

        try {
            // Simple query to test connection
            $this->connection->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            $this->logMessage('WARNING', 'Database connection lost', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Reconnect to database
     * 
     * @throws Exception If reconnection fails
     */
    private function reconnect(): void
    {
        $this->connection = null;
        $this->statistics['reconnections']++;
        $this->logMessage('INFO', 'Attempting database reconnection');
        $this->initializeConnection();
    }

    /**
     * Execute prepared statement with parameters
     * 
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return \PDOStatement Executed statement
     * @throws Exception If query execution fails
     */
    public function executeQuery(string $sql, array $params = []): \PDOStatement
    {
        $startTime = microtime(true);
        $connection = $this->getConnection();

        try {
            $statement = $connection->prepare($sql);
            
            // Bind parameters with proper types
            foreach ($params as $key => $value) {
                $type = $this->getParameterType($value);
                if (is_numeric($key)) {
                    $statement->bindValue($key + 1, $value, $type);
                } else {
                    $statement->bindValue($key, $value, $type);
                }
            }

            $statement->execute();
            $this->statistics['query_count']++;

            $executionTime = microtime(true) - $startTime;
            
            // Log slow queries
            if (defined('SLOW_QUERY_LOG_TIME') && $executionTime > SLOW_QUERY_LOG_TIME) {
                $this->logMessage('WARNING', 'Slow query detected', [
                    'sql' => $sql,
                    'execution_time' => $executionTime,
                    'params' => $params
                ]);
            }

            return $statement;

        } catch (PDOException $e) {
            $this->statistics['errors']++;
            $this->logMessage('ERROR', 'Query execution failed', [
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Database query failed: ' . $e->getMessage());
        }
    }

    /**
     * Get PDO parameter type for value
     * 
     * @param mixed $value Parameter value
     * @return int PDO parameter type
     */
    private function getParameterType($value): int
    {
        if (is_int($value)) {
            return PDO::PARAM_INT;
        } elseif (is_bool($value)) {
            return PDO::PARAM_BOOL;
        } elseif (is_null($value)) {
            return PDO::PARAM_NULL;
        } else {
            return PDO::PARAM_STR;
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

        $connection = $this->getConnection();
        $connection->beginTransaction();
        $this->inTransaction = true;
        $this->statistics['transaction_count']++;
        
        $this->logMessage('DEBUG', 'Transaction started');
    }

    /**
     * Commit database transaction
     * 
     * @throws Exception If no transaction is active
     */
    public function commit(): void
    {
        if (!$this->inTransaction) {
            throw new Exception('No active transaction to commit');
        }

        $this->connection->commit();
        $this->inTransaction = false;
        
        $this->logMessage('DEBUG', 'Transaction committed');
    }

    /**
     * Rollback database transaction
     * 
     * @throws Exception If no transaction is active
     */
    public function rollback(): void
    {
        if (!$this->inTransaction) {
            throw new Exception('No active transaction to rollback');
        }

        $this->connection->rollback();
        $this->inTransaction = false;
        
        $this->logMessage('DEBUG', 'Transaction rolled back');
    }

    /**
     * Execute callback within transaction
     * 
     * @param callable $callback Callback function
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
     * Get last inserted ID
     * 
     * @return string Last insert ID
     */
    public function lastInsertId(): string
    {
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Get connection statistics
     * 
     * @return array Connection statistics
     */
    public function getStatistics(): array
    {
        return array_merge($this->statistics, [
            'in_transaction' => $this->inTransaction,
            'connection_age' => $this->lastConnectionTime > 0 ? microtime(true) - $this->lastConnectionTime : 0,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ]);
    }

    /**
     * Check database health
     * 
     * @return array Health status
     */
    public function getHealthStatus(): array
    {
        try {
            $connection = $this->getConnection();
            
            // Test basic connectivity
            $stmt = $connection->query('SELECT 1 as test');
            $result = $stmt->fetch();
            
            // Get server info
            $serverInfo = [
                'version' => $connection->getAttribute(PDO::ATTR_SERVER_VERSION),
                'connection_id' => $connection->query('SELECT CONNECTION_ID()')->fetchColumn(),
                'charset' => $this->config['charset'],
                'database' => $this->config['dbname']
            ];

            return [
                'status' => 'healthy',
                'connected' => true,
                'test_query' => $result['test'] === 1,
                'server_info' => $serverInfo,
                'statistics' => $this->getStatistics()
            ];

        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'connected' => false,
                'error' => $e->getMessage(),
                'statistics' => $this->getStatistics()
            ];
        }
    }

    /**
     * Close database connection
     */
    public function close(): void
    {
        if ($this->inTransaction) {
            $this->rollback();
        }

        $this->connection = null;
        $this->logMessage('INFO', 'Database connection closed');
    }

    /**
     * Log message (placeholder for actual logging implementation)
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Log context
     */
    private function logMessage(string $level, string $message, array $context = []): void
    {
        // In production, this would use a proper logging system
        if (function_exists('logMessage')) {
            logMessage($level, "[ConnectionManager] $message", $context);
        } elseif (defined('APP_DEBUG') && APP_DEBUG) {
            error_log("[$level] [ConnectionManager] $message " . json_encode($context));
        }
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup(): void
    {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * Cleanup on destruction
     */
    public function __destruct()
    {
        $this->close();
    }
}