<?php
/**
 * Admin Development Tools
 * Combines and optimizes the old Data_Test.php and DBTest.php functionality
 * Only accessible by admin users
 */

require_once '../core/config.php';
require_once '../core/database.php';
require_once '../core/auth.php';

// Require admin access
Auth::requireLogin();
$user = Auth::getCurrentUser();

if ($user['role'] !== 'admin') 
{
    http_response_code(403);
    die('Access denied. Admin privileges required.');
}

$db = getDB();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dev Tools - Database Inspector</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .main-content 
        {
            /* Use standard main-content wrapper */
        }
        
        .dev-tools 
        {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .table-section 
        {
            margin: 30px 0;
            background: var(-- bg-secondary);
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(-- shadow-md);
        }
        
        .data-table 
        {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 0.9rem;
        }
        
        .data-table th,
        .data-table td 
        {
            border: 1px solid var(-- border-color);
            padding: 8px 12px;
            text-align: left;
        }
        
        .data-table th {
            background: var(-- accent-color);
            color: white;
            font-weight: 600;
        }
        
        .data-table tr:nth-child(even) {
            background: var(-- bg-primary);
        }
        
        .data-table tr:hover {
            background: var(-- hover-color);
        }
        
        .empty-table {
            text-align: center;
            padding: 40px;
            color: var(-- text-secondary);
            font-style: italic;
        }
        
        .table-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .stat-badge {
            background: var(-- accent-color);
            color: white;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .refresh-btn {
            float: right;
            margin-bottom: 15px;
        }
        
        .warning-banner {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .data-table {
                font-size: 0.8rem;
            }
            
            .data-table th,
            .data-table td {
                padding: 6px 8px;
            }
            
            .table-stats {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <h1>🛠️ Admin Database Inspector</h1>
            <p class="subtitle">Real-time database content viewer for development and debugging</p>
        </div>
        
        <div class="dev-tools">
            <div class="warning-banner">
                ⚠️ <strong>Development Tools</strong> - This page shows raw database contents and should only be used for development and debugging purposes.
            </div>
        
            <div class="refresh-btn">
                <button class="btn btn-secondary" onclick="window.location.reload()">🔄 Refresh All Data</button>
            </div>

        <?php
        /**
         * Enhanced function to fetch and display table data with statistics
         */
        function displayEnhancedTable($db, $tableName) {
            try {
                // Get table row count
                $countStmt = $db->prepare("SELECT COUNT(*) as total FROM `$tableName`");
                $countStmt->execute();
                $count = $countStmt->fetch()['total'];
                
                // Get table structure
                $structStmt = $db->prepare("DESCRIBE `$tableName`");
                $structStmt->execute();
                $columns = $structStmt->fetchAll();
                $columnCount = count($columns);
                
                echo "<div class='table-section'>";
                echo "<h2>📊 $tableName</h2>";
                
                echo "<div class='table-stats'>";
                echo "<span class='stat-badge'>$count Records</span>";
                echo "<span class='stat-badge'>$columnCount Columns</span>";
                
                // Show last updated info for tables with timestamps
                $hasTimestamp = false;
                foreach ($columns as $col) {
                    if (in_array($col['Field'], ['created_at', 'updated_at', 'timestamp'])) {
                        $hasTimestamp = true;
                        $timestampCol = $col['Field'];
                        break;
                    }
                }
                
                if ($hasTimestamp) {
                    try {
                        $lastUpdateStmt = $db->prepare("SELECT MAX(`$timestampCol`) as last_update FROM `$tableName`");
                        $lastUpdateStmt->execute();
                        $lastUpdate = $lastUpdateStmt->fetch()['last_update'];
                        if ($lastUpdate) {
                            $timeAgo = date('M j, H:i', strtotime($lastUpdate));
                            echo "<span class='stat-badge'>Last Update: $timeAgo</span>";
                        }
                    } catch (Exception $e) {
                        // Ignore timestamp errors
                    }
                }
                echo "</div>";
                
                if ($count > 0) {
                    // Limit results for performance
                    $limit = ($count > 100) ? 100 : $count;
                    $stmt = $db->prepare("SELECT * FROM `$tableName` ORDER BY 1 DESC LIMIT $limit");
                    $stmt->execute();
                    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if ($count > 100) {
                        echo "<p><em>Showing latest 100 of $count records</em></p>";
                    }
                    
                    echo "<div style='overflow-x: auto;'>";
                    echo "<table class='data-table'>";
                    echo "<thead><tr>";
                    
                    // Table headers
                    foreach (array_keys($data[0]) as $header) {
                        echo "<th>" . htmlspecialchars($header) . "</th>";
                    }
                    echo "</tr></thead><tbody>";
                    
                    // Table rows
                    foreach ($data as $row) {
                        echo "<tr>";
                        foreach ($row as $cell) {
                            $displayValue = $cell;
                            
                            // Format different data types
                            if (is_null($cell)) {
                                $displayValue = '<em style="color: #999;">NULL</em>';
                            } elseif (is_numeric($cell) && strlen($cell) > 10) {
                                // Truncate long numbers
                                $displayValue = substr($cell, 0, 20) . '...';
                            } elseif (strlen($cell) > 50) {
                                // Truncate long text
                                $displayValue = htmlspecialchars(substr($cell, 0, 50)) . '...';
                            } else {
                                $displayValue = htmlspecialchars($cell);
                            }
                            
                            echo "<td>$displayValue</td>";
                        }
                        echo "</tr>";
                    }
                    echo "</tbody></table>";
                    echo "</div>";
                } else {
                    echo "<div class='empty-table'>📭 No data found in this table</div>";
                }
                
                echo "</div>";
                
            } catch (PDOException $e) {
                echo "<div class='table-section'>";
                echo "<h2>❌ Error loading $tableName</h2>";
                echo "<p style='color: red;'>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "</div>";
            }
        }

        // Define tables to inspect (in logical order)
        $tables = [
            'Users',
            'Events', 
            'CheckIn',
            'RFIDDevices',
            'AccessLogs',
            'ActivityLog',
            'Reports'
        ];
        
        // Display each table
        foreach ($tables as $table) {
            displayEnhancedTable($db, $table);
        }
        ?>
        
        <div class="table-section">
            <h2>🔍 Database Schema Information</h2>
            <?php
            try {
                $stmt = $db->prepare("
                    SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, CREATE_TIME, UPDATE_TIME
                    FROM information_schema.TABLES 
                    WHERE TABLE_SCHEMA = ? 
                    ORDER BY TABLE_NAME
                ");
                $stmt->execute([DB_NAME]);
                $schema_info = $stmt->fetchAll();
                
                if ($schema_info) {
                    echo "<div style='overflow-x: auto;'>";
                    echo "<table class='data-table'>";
                    echo "<thead><tr>";
                    echo "<th>Table Name</th><th>Rows</th><th>Data Size</th><th>Index Size</th><th>Created</th><th>Updated</th>";
                    echo "</tr></thead><tbody>";
                    
                    foreach ($schema_info as $table) {
                        echo "<tr>";
                        echo "<td><strong>" . htmlspecialchars($table['TABLE_NAME']) . "</strong></td>";
                        echo "<td>" . number_format($table['TABLE_ROWS']) . "</td>";
                        echo "<td>" . number_format($table['DATA_LENGTH'] / 1024, 1) . " KB</td>";
                        echo "<td>" . number_format($table['INDEX_LENGTH'] / 1024, 1) . " KB</td>";
                        echo "<td>" . ($table['CREATE_TIME'] ? date('M j, Y', strtotime($table['CREATE_TIME'])) : 'N/A') . "</td>";
                        echo "<td>" . ($table['UPDATE_TIME'] ? date('M j, H:i', strtotime($table['UPDATE_TIME'])) : 'N/A') . "</td>";
                        echo "</tr>";
                    }
                    echo "</tbody></table>";
                    echo "</div>";
                }
            } catch (Exception $e) {
                echo "<p style='color: red;'>Error loading schema info: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
            ?>
        </div>
    </div>
    </div>

    <?php include '../includes/theme_script.php'; ?>
</body>
</html>
