<?php
require_once 'core/database.php';
$db = getDB();
$stmt = $db->prepare('DESCRIBE accesslogs');
$stmt->execute();
echo "accesslogs table columns:\n";
foreach($stmt->fetchAll() as $row) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
?>
