<?php
// Simple application test - bypass complex routing
echo json_encode([
    'message' => 'RFID Check-in System API', 
    'status' => 'running',
    'timestamp' => date('c'),
    'php_version' => phpversion()
]);
?>