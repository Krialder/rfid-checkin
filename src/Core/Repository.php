<?php
namespace App\Core;

use PDO;

/**
 * Base repository class for database operations
 */
abstract class Repository
{
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    public function findAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll();
    }
    
    public function create(array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $stmt = $this->db->prepare("INSERT INTO {$this->table} ($columns) VALUES ($placeholders)");
        $stmt->execute($data);
        
        return (int) $this->db->lastInsertId();
    }
    
    public function update(int $id, array $data): bool
    {
        $sets = [];
        foreach ($data as $column => $value) {
            $sets[] = "$column = :$column";
        }
        $setString = implode(', ', $sets);
        
        $data[$this->primaryKey] = $id;
        $stmt = $this->db->prepare("UPDATE {$this->table} SET $setString WHERE {$this->primaryKey} = :{$this->primaryKey}");
        
        return $stmt->execute($data);
    }
    
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        return $stmt->execute([$id]);
    }
}
