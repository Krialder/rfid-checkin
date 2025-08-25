<?php
/**
 * Authentication and Session Management
 */

require_once 'database.php';

class Auth {
    
    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
            self::logout();
        }
        
        $_SESSION['last_activity'] = time();
    }
    
    public static function login($email, $password) {
        $db = getDB();
        
        try {
            $stmt = $db->prepare("SELECT user_id, CONCAT(first_name, ' ', COALESCE(last_name, '')) as name, email, password, role FROM Users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                self::startSession();
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                // Log successful login
                self::logActivity($user['user_id'], 'login', 'Successful login');
                
                return true;
            }
            
            // Log failed login attempt
            self::logActivity(null, 'login_failed', "Failed login attempt for email: $email");
            return false;
            
        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            return false;
        }
    }
    
    public static function logout() {
        self::startSession();
        
        if (isset($_SESSION['user_id'])) {
            self::logActivity($_SESSION['user_id'], 'logout', 'User logged out');
        }
        
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    public static function isLoggedIn() {
        self::startSession();
        return isset($_SESSION['user_id']);
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: ' . BASE_URL . '/auth/login.php');
            exit();
        }
    }
    
    public static function requireAdmin() {
        self::requireLogin();
        if ($_SESSION['role'] !== 'admin') {
            header('Location: ' . BASE_URL . '/frontend/dashboard.php');
            exit();
        }
    }
    
    public static function hasRole($roles) {
        if (!self::isLoggedIn()) {
            return false;
        }
        
        if (is_string($roles)) {
            $roles = [$roles];
        }
        
        return in_array($_SESSION['role'], $roles);
    }
    
    public static function getCurrentUser($refresh = false) {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        if ($refresh || !isset($_SESSION['full_user_data'])) {
            // Fetch complete user data from database
            $db = getDB();
            try {
                $stmt = $db->prepare("
                    SELECT user_id, first_name, last_name, email, phone, bio, 
                           avatar, role, is_active, rfid_tag, created_at, updated_at
                    FROM Users 
                    WHERE user_id = ?
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($userData) {
                    // Store in session for future use and add computed 'name' field for compatibility
                    $userData['name'] = trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''));
                    $_SESSION['full_user_data'] = $userData;
                    return $userData;
                }
            } catch (PDOException $e) {
                error_log('Get current user error: ' . $e->getMessage());
            }
        }
        
        // Return cached data or fallback to session data
        if (isset($_SESSION['full_user_data'])) {
            // Ensure 'name' field exists for compatibility
            if (!isset($_SESSION['full_user_data']['name'])) {
                $_SESSION['full_user_data']['name'] = trim(($_SESSION['full_user_data']['first_name'] ?? '') . ' ' . ($_SESSION['full_user_data']['last_name'] ?? ''));
            }
            return $_SESSION['full_user_data'];
        }
        
        return [
            'user_id' => $_SESSION['user_id'],
            'first_name' => explode(' ', $_SESSION['name'])[0] ?? '',
            'last_name' => explode(' ', $_SESSION['name'], 2)[1] ?? '',
            'name' => $_SESSION['name'], // Keep for compatibility
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role'],
            'phone' => '',
            'bio' => '',
            'avatar' => null,
            'rfid_tag' => null,
            'created_at' => null,
            'updated_at' => null
        ];
    }
    
    private static function logActivity($user_id, $action, $details = '') {
        $db = getDB();
        try {
            $stmt = $db->prepare("INSERT INTO ActivityLog (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $action, $details, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        } catch (PDOException $e) {
            error_log('Activity log error: ' . $e->getMessage());
        }
    }
}
