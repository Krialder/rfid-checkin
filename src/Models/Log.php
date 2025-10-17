<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Log
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function getLastLogByUserId($userId)
    {
        $stmt = $this->db->prepare("SELECT * FROM logs WHERE user_id = :user_id ORDER BY check_time DESC LIMIT 1");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch();
    }
    
    public function create($data)
    {
        $stmt = $this->db->prepare("INSERT INTO logs (user_id, check_type, check_time) VALUES (:user_id, :check_type, :check_time)");
        $stmt->execute($data);
        return $this->db->lastInsertId();
    }
    
    public function getRecentLogs($limit = 20)
    {
        $stmt = $this->db->prepare("
            SELECT l.*, u.name, u.rfid_tag 
            FROM logs l 
            JOIN users u ON l.user_id = u.id 
            ORDER BY l.check_time DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
