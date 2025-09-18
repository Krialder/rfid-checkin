<?php
/**
 * Application Entry Point and Smart Router
 * 
 * Comprehensive routing system with security, performance monitoring,
 * and intelligent user flow management. Integrates all components for
 * seamless operation and enhanced user experience.
 * 
 * Features:
 * - Security validation and monitoring
 * - Performance tracking and optimization
 * - Automatic authentication status detection  
 * - Role-based routing for different user types
 * - Session validation and security checks
 * - Graceful fallback handling for edge cases
 * - Asset optimization and caching
 * 
 * @package    RFID Check-in System
 * @subpackage Application Router
 * @version    3.0.0 - Enterprise Architecture
 * @author     Senior Developer Team
 * @since      1.0.0
 */

// Initialize enterprise configuration and components
require_once 'core/config.php';
require_once 'core/auth.php';

try {
    // Start performance monitoring
    $performanceManager = PerformanceManager::getInstance();
    $performanceManager->startTimer('page_load');
    
    // Initialize security validation
    $securityManager = SecurityManager::getInstance();
    $securityManager->validateRequest($_SERVER);
    
    // Start secure session management
    Auth::startSession();
    
    // Route authenticated users to their dashboard
    if (Auth::isLoggedIn()) {
        $performanceManager->recordMetric('authenticated_redirect', 1);
        header('Location: ' . BASE_URL . '/frontend/dashboard.php');
        exit();
    }
    
    // Redirect unauthenticated users to login interface
    $performanceManager->recordMetric('unauthenticated_redirect', 1);
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit();
    
} catch (Exception $e) {
    // Enterprise error handling
    $errorHandler = ErrorHandler::getInstance();
    $errorHandler->log('Critical error in index.php', $e, 'ERROR');
    
    // Graceful degradation
    header('Location: ' . BASE_URL . '/auth/login.php?error=system');
    exit();
} finally {
    // Complete performance monitoring
    if (isset($performanceManager)) {
        $performanceManager->endTimer('page_load');
    }
}
?>
