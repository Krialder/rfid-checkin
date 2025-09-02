<?php
require_once "../core/config.php";
require_once "../core/database.php";

header("Content-Type: application/json");

try {
    $db = getDB();
    
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $rfid = $_POST["rfid"] ?? "";
        
        // Just return success for now
        echo json_encode([
            "success" => true,
            "message" => "Test API working",
            "rfid" => $rfid,
            "timestamp" => date("Y-m-d H:i:s")
        ]);
    } else {
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "API Error",
        "message" => $e->getMessage()
    ]);
}
?>