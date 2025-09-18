<?php
/**
 * Performance Dashboard Admin View
 * 
 * Main interface for monitoring system performance, database optimization,
 * and asset optimization metrics.
 */

session_start();
require_once '../core/auth.php';
require_once '../core/PerformanceDashboard.php';

// Check if user is authenticated and has admin privileges
if (!isLoggedIn() || !hasPermission('admin')) {
    header("Location: ../auth/login.php");
    exit();
}

$dashboard = PerformanceDashboard::getInstance();

// Handle AJAX requests for real-time updates
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['ajax']) {
        case 'metrics':
            echo json_encode($dashboard->getPerformanceMetrics());
            break;
        case 'db_stats':
            echo json_encode($dashboard->getDatabaseStats());
            break;
        case 'asset_stats':
            echo json_encode($dashboard->getAssetStats());
            break;
        case 'system_stats':
            echo json_encode($dashboard->getSystemStats());
            break;
        default:
            echo json_encode(['error' => 'Invalid request']);
    }
    exit();
}

// Render the full dashboard
echo $dashboard->renderDashboard();
?>
