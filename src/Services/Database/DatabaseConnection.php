<?php
declare(strict_types=1);

namespace App\Services\Database;

use PDO;
use PDOException;

class DatabaseConnection
{
    private ?PDO $connection = null;
    
    public function __construct(
        private string $host,
        private string $database,
        private string $username,
        private string $password,
        private string $charset = 'utf8mb4'
    ) {}
    
    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $this->connect();
        }
        
        return $this->connection;
    }
    
    private function connect(): void
    {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset={$this->charset}";
            
            $this->connection = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }
    
    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }
    
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }
    
    public function rollBack(): bool
    {
        return $this->getConnection()->rollBack();
    }
    
    public function lastInsertId(): string
    {
        return $this->getConnection()->lastInsertId();
    }
}
