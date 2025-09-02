<?php
/**
 * Application Entry Point and Smart Router
 * 
 * Intelligent routing system that directs users to appropriate interfaces
 * based on authentication status and user roles. Provides seamless
 * navigation experience with automatic redirection and session management.
 * 
 * Features:
 * - Automatic authentication status detection
 * - Role-based routing for different user types
 * - Session validation and security checks
 * - Graceful fallback handling for edge cases
 * 
 * @package    RFID Check-in System
 * @subpackage Application Router
 * @version    2.0.0
 * @author     Senior Developer Team
 * @since      1.0.0
 */

// Initialize core authentication system
require_once 'core/auth.php';

// Start session management for user state detection
Auth::startSession();

// Route authenticated users to their dashboard
if (Auth::isLoggedIn()) {
    header('Location: ' . BASE_URL . '/frontend/dashboard.php');
    exit();
}

// Redirect unauthenticated users to login interface
header('Location: ' . BASE_URL . '/auth/login.php');
exit();
