<?php

/**
 * Application Routes Configuration
 * 
 * Defines all application routes for the modern RFID check-in system.
 * Uses the Router instance to define RESTful routes and middleware.
 * 
 * @package RfidCheckin
 * @author Senior Development Team
 */

use RfidCheckin\Controllers\Frontend\DashboardController;
use RfidCheckin\Controllers\Frontend\UserController;
use RfidCheckin\Controllers\Frontend\EventController;
use RfidCheckin\Controllers\Frontend\AdminController;
use RfidCheckin\Controllers\Api\AuthController;
use RfidCheckin\Controllers\Api\UserApiController;
use RfidCheckin\Controllers\Api\EventApiController;

// This file should be loaded by passing a $router variable
if (!isset($router) || !($router instanceof RfidCheckin\Routing\Router)) {
    return; // Skip route loading if router not available
}

// Frontend Routes (HTML pages) - Basic routes for testing
try {
    $router->get('/', function() {
        echo json_encode(['message' => 'RFID Check-in System API', 'status' => 'running']);
    });
    
    $router->get('/api/status', function() {
        echo json_encode(['status' => 'ok', 'timestamp' => date('c')]);
    });
    
} catch (Exception $e) {
    // Routes couldn't be loaded, log the error
    error_log("Routes loading error: " . $e->getMessage());
}