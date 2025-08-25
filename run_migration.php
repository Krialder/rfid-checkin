<?php
/**
 * Run RFID Queue Migration
 * Simple script to execute the migration
 */

require_once 'core/database.php';

try {
    $db = getDB();
    $connection = $db->getConnection();
    $migration = file_get_contents('database/migrations/002_add_rfid_scan_queue.sql');
    $statements = array_filter(array_map('trim', explode(';', $migration)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $connection->exec($statement);
                echo 'Executed: ' . substr($statement, 0, 50) . '...' . PHP_EOL;
            } catch (Exception $e) {
                echo 'Error: ' . $e->getMessage() . PHP_EOL;
            }
        }
    }
    
    echo 'Migration completed successfully!' . PHP_EOL;
    
} catch (Exception $e) {
    echo 'Migration failed: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
