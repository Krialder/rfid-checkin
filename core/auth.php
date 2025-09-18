<?php
/**
 * Authentication and Authorization System
 * 
 * Handles user authentication, session management, and role-based access control.
 * Includes password hashing, session protection, and audit logging.
 * 
 * @package RfidCheckin\Core
 * @author Kralder
 */

// Load required dependencies
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

class Auth {
    /**
     * Cached current user data to avoid repeated database queries
     * @var array|null
     */
    private static $currentUser = null;
    
    /**
     * Session initialization status flag
     * @var bool
     */
    private static $sessionStarted = false;
    
    /**
     * Logout protection flag to prevent recursion
     * @var bool
     */
    private static $logoutInProgress = false;
    
    /**
     * Enterprise component instances
     */
    private static $errorHandler = null;
    private static $securityManager = null;
    private static $performanceManager = null;
    
    /**
     * Initialize enterprise components
     */
    private static function initializeComponents() {
        if (self::$errorHandler === null) {
            self::$errorHandler = ErrorHandler::getInstance();
            self::$securityManager = SecurityManager::getInstance();
            self::$performanceManager = PerformanceManager::getInstance();
        }
    }
    
    /**
     * Initialize secure session with enhanced security configuration
     * 
     * Configures PHP session settings for maximum security including
     * HTTP-only cookies, same-site protection, and periodic session
     * ID regeneration to prevent session fixation attacks.
     * 
     * @return void
     * @since 1.0.0
     */
    public static function startSession() {
        self::initializeComponents();
        
        if (self::$sessionStarted) {
            return;
        }
        
        // Start performance monitoring
        self::$performanceManager->startTimer('session_start');
        
        try {
            // Configure session security parameters with enterprise standards
            ini_set('session.cookie_httponly', 1);  // Prevent JavaScript access
            ini_set('session.use_only_cookies', 1); // Prevent URL-based session IDs
            ini_set('session.cookie_secure', 0);    // Set to 1 for HTTPS in production
            ini_set('session.cookie_samesite', 'Lax'); // CSRF protection
            
            // Start session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
                self::$sessionStarted = true;
            }
            
            // Implement periodic session ID regeneration for security
            if (!isset($_SESSION['last_regenerate'])) {
                session_regenerate_id(true);
                $_SESSION['last_regenerate'] = time();
            } elseif (time() - $_SESSION['last_regenerate'] > 1800) { // 30 minutes
                session_regenerate_id(true);
                $_SESSION['last_regenerate'] = time();
            }
            
            // Additional security validation
            self::$securityManager->validateSession($_SESSION);
            
        } catch (Exception $e) {
            self::$errorHandler->log('Session start error', $e, 'ERROR');
            throw $e;
        } finally {
            self::$performanceManager->endTimer('session_start');
        }
    }
    
    /**
     * Authenticate user credentials and establish session
     * 
     * Comprehensive authentication with security validation,
     * performance monitoring, and repository pattern integration. Implements
     * protection against brute force attacks and comprehensive audit logging.
     * 
     * @param string $identifier User email address or username
     * @param string $password   Plain text password for verification
     * @return array Authentication result with success status and user data
     * @since 1.0.0
     */
    public static function login($identifier, $password) {
        self::initializeComponents();
        self::$performanceManager->startTimer('login_process');
        
        try {
            // Validate input using SecurityManager
            $validatedIdentifier = self::$securityManager->validateInput($identifier, 'email');
            $validatedPassword = self::$securityManager->validateInput($password, 'password');
            
            if (!$validatedIdentifier || !$validatedPassword) {
                self::logFailedLogin($identifier, 'Invalid input format');
                return ['success' => false, 'error' => 'Invalid credentials'];
            }
            
            // Use repository pattern for data access
            $userRepository = new UserRepository();
            $user = $userRepository->findByEmailOrUsername($validatedIdentifier);
            
            if (!$user) {
                self::logFailedLogin($identifier, 'User not found');
                return ['success' => false, 'error' => 'Invalid credentials'];
            }
            
            // Validate account lockout status
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $unlockTime = date('H:i', strtotime($user['locked_until']));
                return ['success' => false, 'error' => "Account locked until $unlockTime"];
            }
            
            // Verify password using secure hash comparison
            if (!password_verify($validatedPassword, $user['password'])) {
                self::handleFailedLogin($user['user_id'], $identifier);
                return ['success' => false, 'error' => 'Invalid credentials'];
            }
            
            // Clear failed login attempts after successful authentication
            $userRepository->resetFailedAttempts($user['user_id']);
            
            // Establish secure session with user context
            self::startSession();
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            // Update last login timestamp
            $userRepository->updateLastLogin($user['user_id']);
            
            // Log successful authentication for security monitoring
            self::logActivity($user['user_id'], 'login', 'User logged in successfully');
            
            // Record performance metrics
            self::$performanceManager->recordMetric('successful_login', 1);
            
            // Remove sensitive data before returning user information
            unset($user['password']);
            unset($user['failed_login_attempts']);
            unset($user['locked_until']);
            
            return [
                'success' => true, 
                'user' => $user,
                'redirect' => self::getRedirectUrl($user['role'])
            ];
            
        } catch (Exception $e) {
            self::$errorHandler->log('Login error', $e, 'ERROR');
            self::$performanceManager->recordMetric('failed_login_system_error', 1);
            return ['success' => false, 'error' => 'Login system error'];
        } finally {
            self::$performanceManager->endTimer('login_process');
        }
    }
    
    /**
     * Check if user is currently logged in
     * 
     * @return bool
     */
    public static function isLoggedIn() {
        self::startSession();
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
            return false;
        }
        
        // Check session timeout - but prevent recursion during logout
        if (time() - $_SESSION['login_time'] > SESSION_LIFETIME) {
            if (!self::$logoutInProgress) {
                self::$logoutInProgress = true;
                self::logout();
                self::$logoutInProgress = false;
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Get current logged-in user data using enterprise repository pattern
     * 
     * @return array|null User data or null if not logged in
     */
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        if (self::$currentUser !== null) {
            return self::$currentUser;
        }
        
        self::initializeComponents();
        
        try {
            self::$performanceManager->startTimer('get_current_user');
            
            // Use repository pattern for data access
            $userRepository = new UserRepository();
            self::$currentUser = $userRepository->findById($_SESSION['user_id']);
            
            return self::$currentUser;
            
        } catch (Exception $e) {
            self::$errorHandler->log('Get current user error', $e, 'ERROR');
            return null;
        } finally {
            self::$performanceManager->endTimer('get_current_user');
        }
    }
    
    /**
     * Check if current user has required role(s)
     * 
     * @param array $allowedRoles Array of allowed roles
     * @return bool
     */
    public static function hasRole($allowedRoles) {
        if (!self::isLoggedIn()) {
            return false;
        }
        
        $user = self::getCurrentUser();
        return $user && in_array($user['role'], $allowedRoles);
    }
    
    /**
     * Require user to be logged in, redirect if not
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: ' . BASE_URL . '/auth/login.php');
            exit;
        }
    }
    
    /**
     * Require specific role, show 403 if not authorized
     * 
     * @param array $allowedRoles
     */
    public static function requireRole($allowedRoles) {
        self::requireLogin();
        
        if (!self::hasRole($allowedRoles)) {
            http_response_code(403);
            die('Access denied. Insufficient privileges.');
        }
    }
    
    /**
     * Log out current user
     */
    public static function logout() {
        // Start session to access session data, but don't check isLoggedIn() to avoid recursion
        self::startSession();
        
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            self::logActivity($userId, 'logout', 'User logged out');
        }
        
        session_unset();
        session_destroy();
        self::$currentUser = null;
        self::$sessionStarted = false;
        self::$logoutInProgress = false; // Reset recursion protection flag
    }
    
    /**
     * Handle failed login attempts and account locking using repository pattern
     */
    private static function handleFailedLogin($userId, $identifier) {
        self::initializeComponents();
        
        try {
            $userRepository = new UserRepository();
            $userRepository->incrementFailedAttempts($userId);
            
            self::logFailedLogin($identifier, 'Invalid password');
            
        } catch (Exception $e) {
            self::$errorHandler->log('Handle failed login error', $e, 'ERROR');
        }
    }
    
    /**
     * Log failed login attempt using enterprise logging
     */
    private static function logFailedLogin($identifier, $reason) {
        self::initializeComponents();
        
        try {
            $details = "Failed login attempt for: $identifier - $reason";
            $userRepository = new UserRepository();
            $userRepository->logActivity(null, 'failed_login', $details);
            
            // Also log to enterprise error handler
            self::$errorHandler->log($details, null, 'WARNING');
            
        } catch (Exception $e) {
            self::$errorHandler->log('Log failed login error', $e, 'ERROR');
        }
    }
    
    /**
     * Log user activity using repository pattern
     */
    private static function logActivity($userId, $action, $details) {
        self::initializeComponents();
        
        try {
            $userRepository = new UserRepository();
            $userRepository->logActivity($userId, $action, $details);
            
        } catch (Exception $e) {
            self::$errorHandler->log('Log activity error', $e, 'ERROR');
        }
    }
    
    /**
     * Get appropriate redirect URL based on user role
     */
    private static function getRedirectUrl($role) {
        switch ($role) {
            case 'admin':
                return BASE_URL . '/frontend/dashboard.php';
            case 'moderator':
                return BASE_URL . '/frontend/dashboard.php';
            default:
                return BASE_URL . '/frontend/dashboard.php';
        }
    }
    
    /**
     * Generate secure password reset token
     * 
     * @param string $email User email
     * @return array Result with success status and token
     */
    public static function generatePasswordResetToken($email) {
        try {
            $db = getDB();
            
            // Find user
            $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'error' => 'Email not found'];
            }
            
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token
            $stmt = $db->prepare("
                INSERT INTO password_resets (user_id, token, expires, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user['user_id'],
                $token,
                $expires,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
            
            return ['success' => true, 'token' => $token];
            
        } catch (Exception $e) {
            error_log('Generate reset token error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'System error'];
        }
    }
    
    /**
     * Validate and use password reset token
     * 
     * @param string $token Reset token
     * @param string $newPassword New password
     * @return array Result with success status
     */
    public static function resetPassword($token, $newPassword) {
        try {
            $db = getDB();
            
            // Find valid token
            $stmt = $db->prepare("
                SELECT pr.user_id, pr.id as reset_id
                FROM password_resets pr
                WHERE pr.token = ? AND pr.expires > NOW() AND pr.used = 0
                LIMIT 1
            ");
            $stmt->execute([$token]);
            $reset = $stmt->fetch();
            
            if (!$reset) {
                return ['success' => false, 'error' => 'Invalid or expired token'];
            }
            
            // Validate password strength
            if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                return ['success' => false, 'error' => 'Password too short'];
            }
            
            $db->beginTransaction();
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                UPDATE users 
                SET password = ?, failed_login_attempts = 0, locked_until = NULL
                WHERE user_id = ?
            ");
            $stmt->execute([$hashedPassword, $reset['user_id']]);
            
            // Mark token as used
            $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
            $stmt->execute([$reset['reset_id']]);
            
            $db->commit();
            
            self::logActivity($reset['user_id'], 'password_reset', 'Password reset completed');
            
            return ['success' => true];
            
        } catch (Exception $e) {
            if (isset($db)) {
                $db->rollback();
            }
            error_log('Reset password error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'System error'];
        }
    }
}
