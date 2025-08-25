<?php
/**
 * Entry Point - Smart Landing Page
 * Routes users to appropriate page based on authentication status
 */

// Load core authentication system
require_once 'core/auth.php';

// Start session to check login status
Auth::startSession();

// If user is already logged in, redirect to dashboard
if (Auth::isLoggedIn()) {
    header('Location: ' . BASE_URL . '/frontend/dashboard.php');
    exit();
}

// If not logged in, redirect to login page
header('Location: ' . BASE_URL . '/auth/login.php');
exit();
