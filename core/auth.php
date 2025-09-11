<?php
/**
 * Authentication and Authorization System
 * 
 * Comprehensive security system providing user authentication, session management,
 * and role-based access control. Implements industry-standard security practices
 * including secure password hashing, session protection, and audit logging.
 * 
 * Security Features:
 * - BCrypt password hashing with configurable cost
 * - Session hijacking protection with ID regeneration
 * - Account lockout protection against brute force attacks
 * - Role-based access control (RBAC) system
 * - Comprehensive activity logging and audit trails
 * - Secure password reset with time-limited tokens
 * - CSRF protection integration
 * 
 * @package    RFID Check-in System
 * @subpackage Authentication System
 * @version    2.0.0
 * @author     Senior Developer Team
 * @since      1.0.0
 * @security   CRITICAL - Handles all authentication operations
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
        if (self::$sessionStarted) {
            return;
        }
        
        // Configure session security parameters
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
    }
    
    /**
     * Authenticate user credentials and establish session
     * 
     * Performs comprehensive user authentication including password verification,
     * account status validation, and security checks. Implements protection
     * against brute force attacks through account lockout mechanisms.
     * 
     * @param string $identifier User email address or username
     * @param string $password   Plain text password for verification
     * @return array Authentication result with success status and user data
     * @since 1.0.0
     */
    public static function login($identifier, $password) {
        try {
            $db = getDB();
            
            // Locate user account by email or username
            $stmt = $db->prepare("
                SELECT user_id, username, email, password, first_name, last_name, 
                       role, is_active, failed_login_attempts, locked_until
                FROM users 
                WHERE (email = ? OR username = ?) AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
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
            if (!password_verify($password, $user['password'])) {
                self::handleFailedLogin($user['user_id'], $identifier);
                return ['success' => false, 'error' => 'Invalid credentials'];
            }
            
            // Clear failed login attempts after successful authentication
            self::resetFailedAttempts($user['user_id']);
            
            // Establish secure session with user context
            self::startSession();
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            // Record last login timestamp for security auditing
            $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $updateStmt->execute([$user['user_id']]);
            
            // Log successful authentication for security monitoring
            self::logActivity($user['user_id'], 'login', 'User logged in successfully');
            
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
            error_log('Login error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Login system error'];
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
     * Get current logged-in user data
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
        
        try {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT user_id, username, email, first_name, last_name, role, 
                       department, phone, created_at, last_login
                FROM users 
                WHERE user_id = ? AND is_active = 1
            ");
            $stmt->execute([$_SESSION['user_id']]);
            self::$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return self::$currentUser;
            
        } catch (Exception $e) {
            error_log('Get current user error: ' . $e->getMessage());
            return null;
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
     * Handle failed login attempts and account locking
     */
    private static function handleFailedLogin($userId, $identifier) {
        try {
            $db = getDB();
            
            // Increment failed attempts
            $stmt = $db->prepare("
                UPDATE users 
                SET failed_login_attempts = failed_login_attempts + 1,
                    locked_until = CASE 
                        WHEN failed_login_attempts + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                        ELSE NULL 
                    END
                WHERE user_id = ?
            ");
            $stmt->execute([MAX_LOGIN_ATTEMPTS, $userId]);
            
            self::logFailedLogin($identifier, 'Invalid password');
            
        } catch (Exception $e) {
            error_log('Handle failed login error: ' . $e->getMessage());
        }
    }
    
    /**
     * Reset failed login attempts after successful login
     */
    private static function resetFailedAttempts($userId) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                UPDATE users 
                SET failed_login_attempts = 0, locked_until = NULL 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
        } catch (Exception $e) {
            error_log('Reset failed attempts error: ' . $e->getMessage());
        }
    }
    
    /**
     * Log failed login attempt
     */
    private static function logFailedLogin($identifier, $reason) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO activitylog (user_id, action, details, ip_address, timestamp)
                VALUES (NULL, 'failed_login', ?, ?, NOW())
            ");
            $stmt->execute([
                "Failed login attempt for: $identifier - $reason",
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
            
        } catch (Exception $e) {
            error_log('Log failed login error: ' . $e->getMessage());
        }
    }
    
    /**
     * Log user activity
     */
    private static function logActivity($userId, $action, $details) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO activitylog (user_id, action, details, ip_address, timestamp)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $userId,
                $action,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
            
        } catch (Exception $e) {
            error_log('Log activity error: ' . $e->getMessage());
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
