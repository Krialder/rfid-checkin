<?php
/**
 * Database Connection and Management Layer
 * 
 * Provides secure PDO-based database connectivity with comprehensive
 * error handling, query optimization, and transaction management.
 * Implements the singleton pattern for efficient connection reuse.
 * 
 * Features:
 * - Automatic connection health monitoring and recovery
 * - Prepared statement helpers for SQL injection prevention
 * - Transaction management with automatic rollback
 * - Query performance optimization and caching
 * - Database maintenance and optimization utilities
 * 
 * @package    RFID Check-in System
 * @subpackage Database Layer
 * @version    2.0.0
 * @author     Senior Developer Team
 * @since      1.0.0
 * @security   HIGH - Handles all database operations
 */

// Ensure config is loaded
if (!defined('CONFIG_LOADED')) {
    require_once __DIR__ . '/config.php';
}

/**
 * Global database connection instance (singleton pattern)
 * Maintains a single database connection throughout the application lifecycle
 * @var PDO|null
 */
$globalDB = null;

/**
 * Retrieve database connection using singleton pattern
 * 
 * Establishes and maintains a single database connection with automatic
 * health monitoring and reconnection capabilities. Implements secure
 * connection settings and proper error handling.
 * 
 * Connection Features:
 * - Automatic connection health monitoring
 * - UTF-8 character set enforcement
 * - Prepared statement emulation disabled for security
 * - Timezone normalization to UTC
 * - Connection timeout protection
 * 
 * @return PDO Active database connection instance
 * @throws Exception If database connection cannot be established
 * @since 1.0.0
 */
function getDB() {
    global $globalDB;
    
    if ($globalDB !== null) {
        try {
            // Verify existing connection is still alive
            $globalDB->query('SELECT 1');
            return $globalDB;
        } catch (PDOException $e) {
            // Connection lost, reset and reconnect below
            $globalDB = null;
        }
    }
    
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            // Enable exception mode for proper error handling
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Use associative arrays as default fetch mode
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Disable prepared statement emulation for security
            PDO::ATTR_EMULATE_PREPARES => false,
            // Disable persistent connections for shared hosting compatibility
            PDO::ATTR_PERSISTENT => false,
            // Set character set and collation for proper UTF-8 handling
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci",
            // Set connection timeout to prevent hanging connections
            PDO::ATTR_TIMEOUT => 10,
        ];
        
        $globalDB = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        // Normalize timezone to UTC for consistent timestamp handling
        $globalDB->exec("SET time_zone = '+00:00'");
        
        logMessage('DEBUG', 'Database connection established');
        
        return $globalDB;
        
    } catch (PDOException $e) {
        logMessage('ERROR', 'Database connection failed', [
            'error' => $e->getMessage(),
            'host' => DB_HOST,
            'database' => DB_NAME
        ]);
        
        // Don't expose database details in production
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        } else {
            throw new Exception('Database connection failed. Please contact system administrator.');
        }
    }
}

/**
 * Execute prepared statement with comprehensive error handling
 * 
 * Safely executes SQL queries using prepared statements to prevent
 * SQL injection attacks. Provides detailed logging and error reporting
 * for debugging and monitoring purposes.
 * 
 * @param string $sql    SQL query with named or positional placeholders
 * @param array  $params Parameter values for query placeholders
 * @return PDOStatement Executed statement ready for data retrieval
 * @throws Exception If query preparation or execution fails
 * @since 1.0.0
 */
function executeQuery($sql, $params = []) {
    try {
        $db = getDB();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        logMessage('DEBUG', 'Query executed successfully', [
            'sql' => $sql,
            'params' => $params
        ]);
        
        return $stmt;
        
    } catch (PDOException $e) {
        logMessage('ERROR', 'Query execution failed', [
            'sql' => $sql,
            'params' => $params,
            'error' => $e->getMessage()
        ]);
        
        throw new Exception('Database query failed: ' . $e->getMessage());
    }
}

/**
 * Execute query and return single row result
 * 
 * Convenience method for queries that expect exactly one row.
 * Commonly used for user lookups, configuration retrieval,
 * and detail views.
 * 
 * @param string $sql    SQL query string
 * @param array  $params Query parameters (optional)
 * @return array|null Single row as associative array, or null if no results
 * @since 1.0.0
 */
function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetch();
}

/**
 * Execute query and return all matching rows
 * 
 * Retrieves complete result set for list operations, reports,
 * and bulk data processing. Use with caution for large datasets.
 * 
 * @param string $sql    SQL query string
 * @param array  $params Query parameters (optional)
 * @return array Array of associative arrays representing all rows
 * @since 1.0.0
 */
function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Execute query and return single scalar value
 * 
 * Optimized for COUNT(), SUM(), MAX() and other aggregate queries
 * that return a single value. Returns the first column of the first row.
 * 
 * @param string $sql    SQL query string
 * @param array  $params Query parameters (optional)
 * @return mixed Single value from first column of first row
 * @since 1.0.0
 */
function fetchValue($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchColumn();
}

/**
 * Insert new record and return auto-generated ID
 * 
 * Provides a simplified interface for INSERT operations with
 * automatic parameter binding and primary key retrieval.
 * 
 * @param string $table Database table name
 * @param array  $data  Associative array of column => value pairs
 * @return int Auto-generated primary key value
 * @throws Exception If insertion fails or table doesn't exist
 * @since 1.0.0
 */
function insertData($table, $data) {
    $columns = array_keys($data);
    $placeholders = ':' . implode(', :', $columns);
    $columnList = implode(', ', $columns);
    
    $sql = "INSERT INTO `$table` ($columnList) VALUES ($placeholders)";
    
    $params = [];
    foreach ($data as $key => $value) {
        $params[":$key"] = $value;
    }
    
    executeQuery($sql, $params);
    
    return getDB()->lastInsertId();
}

/**
 * Update existing records with WHERE conditions
 * 
 * Safely updates database records using prepared statements.
 * Automatically handles parameter binding and prevents SQL injection.
 * 
 * @param string $table Database table name
 * @param array  $data  Column => value pairs to update
 * @param array  $where WHERE condition column => value pairs
 * @return int Number of affected rows
 * @throws Exception If update operation fails
 * @since 1.0.0
 */
function updateData($table, $data, $where) {
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
    
    $stmt = executeQuery($sql, $params);
    return $stmt->rowCount();
}

/**
 * Delete records matching WHERE conditions
 * 
 * Safely removes database records using prepared statements.
 * Use with caution as this operation is irreversible.
 * 
 * @param string $table Database table name
 * @param array  $where WHERE condition column => value pairs
 * @return int Number of deleted rows
 * @throws Exception If delete operation fails
 * @since 1.0.0
 */
function deleteData($table, $where) {
    $whereParts = [];
    $params = [];
    
    foreach ($where as $key => $value) {
        $whereParts[] = "`$key` = :$key";
        $params[":$key"] = $value;
    }
    
    $sql = "DELETE FROM `$table` WHERE " . implode(' AND ', $whereParts);
    
    $stmt = executeQuery($sql, $params);
    return $stmt->rowCount();
}

/**
 * Check if table exists
 * 
 * @param string $tableName Table name to check
 * @return bool True if table exists
 */
function tableExists($tableName) {
    try {
        $sql = "SELECT 1 FROM `$tableName` LIMIT 1";
        executeQuery($sql);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get table structure
 * 
 * @param string $tableName Table name
 * @return array Table structure
 */
function getTableStructure($tableName) {
    $sql = "DESCRIBE `$tableName`";
    return fetchAll($sql);
}

/**
 * Start database transaction
 */
function beginTransaction() {
    $db = getDB();
    $db->beginTransaction();
    logMessage('DEBUG', 'Transaction started');
}

/**
 * Commit database transaction
 */
function commitTransaction() {
    $db = getDB();
    $db->commit();
    logMessage('DEBUG', 'Transaction committed');
}

/**
 * Rollback database transaction
 */
function rollbackTransaction() {
    $db = getDB();
    $db->rollback();
    logMessage('DEBUG', 'Transaction rolled back');
}

/**
 * Execute transaction with automatic rollback on error
 * 
 * @param callable $callback Function to execute in transaction
 * @return mixed Return value from callback
 * @throws Exception If transaction fails
 */
function executeTransaction(callable $callback) {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        $result = $callback($db);
        $db->commit();
        
        logMessage('DEBUG', 'Transaction completed successfully');
        return $result;
        
    } catch (Exception $e) {
        $db->rollback();
        logMessage('ERROR', 'Transaction failed and rolled back', [
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}

/**
 * Optimize database tables
 */
function optimizeTables() {
    try {
        $db = getDB();
        
        // Get all tables
        $tables = fetchAll("SHOW TABLES");
        $tableColumn = 'Tables_in_' . DB_NAME;
        
        foreach ($tables as $table) {
            $tableName = $table[$tableColumn];
            $db->exec("OPTIMIZE TABLE `$tableName`");
            logMessage('DEBUG', "Optimized table: $tableName");
        }
        
        return true;
        
    } catch (Exception $e) {
        logMessage('ERROR', 'Table optimization failed', ['error' => $e->getMessage()]);
        return false;
    }
}

/**
 * Get database connection statistics
 * 
 * @return array Connection statistics
 */
function getConnectionStats() {
    try {
        $stats = [
            'connected' => $globalDB !== null,
            'server_version' => null,
            'connection_id' => null,
            'charset' => DB_CHARSET,
            'database' => DB_NAME
        ];
        
        if ($globalDB) {
            $stats['server_version'] = $globalDB->getAttribute(PDO::ATTR_SERVER_VERSION);
            $stats['connection_id'] = fetchValue("SELECT CONNECTION_ID()");
        }
        
        return $stats;
        
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}
