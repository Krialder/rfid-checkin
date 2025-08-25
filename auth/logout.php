<?php
/**
 * Logout Handler
 */

require_once '../core/auth.php';

Auth::logout();

// Redirect to login page with success message
header('Location: ' . BASE_URL . '/auth/login.php?success=' . urlencode('You have been successfully logged out'));
exit();
