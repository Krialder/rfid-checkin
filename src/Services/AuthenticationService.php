<?php

declare(strict_types=1);

namespace RfidCheckin\Services;

use RfidCheckin\Repositories\UserRepository;
use Exception;

/**
 * Authentication Service
 * 
 * Handles authentication and authorization operations including login,
 * logout, session management, and role-based access control.
 * 
 * @package RfidCheckin\Services
 * @author Kralder
 */
class AuthenticationService
{
    private static ?AuthenticationService $instance = null;
    private UserRepository $userRepository;
    private LoggingService $logger;
    private SecurityService $security;
    private ?array $currentUser = null;
    private array $config;

    /**
     * Check if we're in a context where sessions are available
     */
    private function isSessionAvailable(): bool
    {
        return php_sapi_name() !== 'cli' && php_sapi_name() !== 'cli-server';
    }

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->logger = LoggingService::getInstance();
        $this->security = SecurityService::getInstance();
        $this->loadConfiguration();
        $this->initializeSession();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load authentication configuration
     */
    private function loadConfiguration(): void
    {
        $this->config = [
            'session_lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 3600),
            'max_login_attempts' => (int) ($_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5),
            'lockout_duration' => (int) ($_ENV['LOCKOUT_DURATION'] ?? 1800),
            'password_min_length' => (int) ($_ENV['PASSWORD_MIN_LENGTH'] ?? 8),
            'session_regenerate_interval' => (int) ($_ENV['SESSION_REGENERATE_INTERVAL'] ?? 600),
        ];
    }

    /**
     * Initialize secure session
     */
    private function initializeSession(): void
    {
        // Skip session handling in CLI context
        if (!$this->isSessionAvailable()) {
            return;
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            // Configure secure session settings
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.gc_maxlifetime', (string) $this->config['session_lifetime']);
            
            session_start();
            
            // Regenerate session ID periodically
            $this->maintainSession();
        }
    }

    /**
     * Maintain session security
     */
    private function maintainSession(): void
    {
        // Check if session should be regenerated
        if (!isset($_SESSION['last_regenerate'])) {
            $this->regenerateSessionId();
        } elseif (time() - $_SESSION['last_regenerate'] > $this->config['session_regenerate_interval']) {
            $this->regenerateSessionId();
        }
        
        // Validate session integrity
        $this->validateSessionIntegrity();
        
        // Update last activity
        $_SESSION['last_activity'] = time();
    }

    /**
     * Regenerate session ID for security
     */
    private function regenerateSessionId(): void
    {
        // Skip session operations in CLI context
        if (!$this->isSessionAvailable()) {
            return;
        }
        
        session_regenerate_id(true);
        $_SESSION['last_regenerate'] = time();
        $_SESSION['fingerprint'] = $this->generateSessionFingerprint();
        
        $this->logger->debug('Session ID regenerated', [
            'session_id' => session_id(),
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
    }

    /**
     * Generate session fingerprint for integrity validation
     */
    private function generateSessionFingerprint(): string
    {
        $components = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 10)
        ];
        
        return hash('sha256', implode('|', $components));
    }

    /**
     * Validate session integrity
     */
    private function validateSessionIntegrity(): void
    {
        $expectedFingerprint = $this->generateSessionFingerprint();
        
        if (isset($_SESSION['fingerprint']) && $_SESSION['fingerprint'] !== $expectedFingerprint) {
            $this->logger->security('session_hijack_attempt', 'Session fingerprint mismatch detected', [
                'expected' => $expectedFingerprint,
                'actual' => $_SESSION['fingerprint'],
                'session_id' => session_id()
            ]);
            
            $this->destroySession();
            throw new Exception('Session security violation detected');
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity'])) {
            $inactive = time() - $_SESSION['last_activity'];
            if ($inactive > $this->config['session_lifetime']) {
                $this->logger->info('Session expired due to inactivity', [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'inactive_duration' => $inactive
                ]);
                
                $this->destroySession();
                throw new Exception('Session expired due to inactivity');
            }
        }
    }

    /**
     * Authenticate user with email/username and password
     * 
     * @param string $identifier Email or username
     * @param string $password Plain text password
     * @param bool $rememberMe Whether to extend session
     * @return array Authentication result
     * @throws Exception If authentication fails
     */
    public function authenticate(string $identifier, string $password, bool $rememberMe = false): array
    {
        return $this->login($identifier, $password, $rememberMe);
    }

    /**
     * Authenticate user with email/username and password
     * 
     * @param string $identifier Email or username
     * @param string $password Plain text password
     * @param bool $rememberMe Whether to extend session
     * @return array Authentication result
     * @throws Exception If authentication fails
     */
    public function login(string $identifier, string $password, bool $rememberMe = false): array
    {
        $this->logger->info('Login attempt', [
            'identifier' => $identifier,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);

        try {
            // Validate input
            $identifier = $this->security->validateInput($identifier, 'email');
            $password = $this->security->validateInput($password, 'password');
            
            if (!$identifier || !$password) {
                $this->logFailedLogin($identifier, 'Invalid input format');
                return ['success' => false, 'error' => 'Invalid credentials'];
            }

            // Attempt to verify password
            $user = $this->userRepository->verifyPassword($identifier, $password);
            
            if (!$user) {
                $this->logFailedLogin($identifier, 'Invalid credentials');
                return ['success' => false, 'error' => 'Invalid credentials'];
            }

            // Check if user is active
            if (!$user['is_active']) {
                $this->logFailedLogin($identifier, 'Account inactive');
                return ['success' => false, 'error' => 'Account is not active'];
            }

            // Successful login
            $this->establishUserSession($user, $rememberMe);
            $this->userRepository->updateLastLogin($user['user_id']);
            
            $this->logger->info('Login successful', [
                'user_id' => $user['user_id'],
                'username' => $user['username'],
                'role' => $user['role']
            ]);

            return [
                'success' => true,
                'user' => $this->sanitizeUserData($user),
                'session_expires' => date('c', time() + $this->config['session_lifetime'])
            ];

        } catch (Exception $e) {
            $this->logFailedLogin($identifier, $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Authenticate user with RFID tag
     * 
     * @param string $rfidTag RFID tag value
     * @return array Authentication result
     * @throws Exception If authentication fails
     */
    public function loginWithRfid(string $rfidTag): array
    {
        $this->logger->info('RFID login attempt', [
            'rfid_tag' => substr($rfidTag, 0, 6) . '***', // Partially mask for security
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        try {
            // Validate RFID tag format
            $rfidTag = $this->security->validateInput($rfidTag, 'rfid');
            if (!$rfidTag) {
                return ['success' => false, 'error' => 'Invalid RFID tag format'];
            }

            // Find user by RFID tag
            $user = $this->userRepository->findByRfidTag($rfidTag);
            
            if (!$user) {
                $this->logger->security('rfid_not_found', 'Unknown RFID tag scanned', [
                    'rfid_tag' => substr($rfidTag, 0, 6) . '***'
                ]);
                return ['success' => false, 'error' => 'RFID tag not recognized'];
            }

            // Check if user is active
            if (!$user['is_active']) {
                $this->logger->security('rfid_inactive_user', 'Inactive user attempted RFID login', [
                    'user_id' => $user['user_id'],
                    'rfid_tag' => substr($rfidTag, 0, 6) . '***'
                ]);
                return ['success' => false, 'error' => 'Account is not active'];
            }

            $this->logger->info('RFID login successful', [
                'user_id' => $user['user_id'],
                'username' => $user['username']
            ]);

            return [
                'success' => true,
                'user' => $this->sanitizeUserData($user)
            ];

        } catch (Exception $e) {
            $this->logger->error('RFID login error', [
                'error' => $e->getMessage(),
                'rfid_tag' => substr($rfidTag, 0, 6) . '***'
            ]);
            return ['success' => false, 'error' => 'Authentication failed'];
        }
    }

    /**
     * Establish user session after successful authentication
     * 
     * @param array $user User data
     * @param bool $rememberMe Whether to extend session
     */
    private function establishUserSession(array $user, bool $rememberMe = false): void
    {
        // Regenerate session ID for security
        $this->regenerateSessionId();
        
        // Store user data in session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Extend session for remember me
        if ($rememberMe) {
            $extendedLifetime = $this->config['session_lifetime'] * 24; // 24x normal
            ini_set('session.gc_maxlifetime', (string) $extendedLifetime);
            $_SESSION['extended_session'] = true;
        }
        
        // Store current user for quick access
        $this->currentUser = $user;
    }

    /**
     * Log user out and destroy session
     */
    public function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        
        if ($userId) {
            $this->logger->info('User logged out', [
                'user_id' => $userId,
                'session_duration' => time() - ($_SESSION['login_time'] ?? time())
            ]);
        }
        
        $this->destroySession();
    }

    /**
     * Destroy session completely
     */
    private function destroySession(): void
    {
        $_SESSION = [];
        $this->currentUser = null;
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        session_destroy();
    }

    /**
     * Check if user is authenticated
     * 
     * @return bool True if authenticated
     */
    public function isAuthenticated(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        try {
            $this->maintainSession();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get current authenticated user
     * 
     * @return array|null User data or null if not authenticated
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        if ($this->currentUser === null) {
            $this->currentUser = $this->userRepository->find($_SESSION['user_id']);
        }
        
        return $this->currentUser ? $this->sanitizeUserData($this->currentUser) : null;
    }

    /**
     * Get current authenticated user ID
     * 
     * @return int|null User ID or null if not authenticated
     */
    public function getCurrentUserId(): ?int
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Check if current user has specific role
     * 
     * @param string $role Required role
     * @return bool True if user has role
     */
    public function hasRole(string $role): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user['role'] === $role;
    }

    /**
     * Check if current user has any of the specified roles
     * 
     * @param array $roles Array of acceptable roles
     * @return bool True if user has any of the roles
     */
    public function hasAnyRole(array $roles): bool
    {
        $user = $this->getCurrentUser();
        return $user && in_array($user['role'], $roles);
    }

    /**
     * Require authentication and optionally specific role
     * 
     * @param string|array|null $requiredRole Required role(s)
     * @throws Exception If not authenticated or insufficient permissions
     */
    public function requireAuthentication($requiredRole = null): void
    {
        if (!$this->isAuthenticated()) {
            throw new Exception('Authentication required');
        }
        
        if ($requiredRole !== null) {
            if (is_string($requiredRole)) {
                if (!$this->hasRole($requiredRole)) {
                    $this->logger->security('access_denied', 'Insufficient permissions', [
                        'user_id' => $_SESSION['user_id'],
                        'required_role' => $requiredRole,
                        'user_role' => $_SESSION['role'] ?? 'unknown'
                    ]);
                    throw new Exception('Insufficient permissions');
                }
            } elseif (is_array($requiredRole)) {
                if (!$this->hasAnyRole($requiredRole)) {
                    $this->logger->security('access_denied', 'Insufficient permissions', [
                        'user_id' => $_SESSION['user_id'],
                        'required_roles' => $requiredRole,
                        'user_role' => $_SESSION['role'] ?? 'unknown'
                    ]);
                    throw new Exception('Insufficient permissions');
                }
            }
        }
    }

    /**
     * Log failed login attempt
     * 
     * @param string $identifier Login identifier
     * @param string $reason Failure reason
     */
    private function logFailedLogin(string $identifier, string $reason): void
    {
        $this->logger->security('login_failed', 'Failed login attempt', [
            'identifier' => $identifier,
            'reason' => $reason,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }

    /**
     * Sanitize user data for safe transmission
     * 
     * @param array $user Raw user data
     * @return array Sanitized user data
     */
    private function sanitizeUserData(array $user): array
    {
        // Remove sensitive fields
        unset($user['password'], $user['password_reset_token'], $user['failed_login_attempts']);
        
        // Ensure proper data types
        $user['user_id'] = (int) $user['user_id'];
        $user['is_active'] = (bool) $user['is_active'];
        
        // Parse JSON fields
        if (isset($user['preferences']) && is_string($user['preferences'])) {
            $user['preferences'] = json_decode($user['preferences'], true) ?: [];
        }
        
        return $user;
    }

    /**
     * Change user password
     * 
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @return bool True if successful
     * @throws Exception If validation fails
     */
    public function changePassword(string $currentPassword, string $newPassword): bool
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new Exception('User not authenticated');
        }
        
        // Validate current password
        $userWithPassword = $this->userRepository->verifyPassword($user['email'], $currentPassword);
        if (!$userWithPassword) {
            throw new Exception('Current password is incorrect');
        }
        
        // Validate new password
        if (strlen($newPassword) < $this->config['password_min_length']) {
            throw new Exception("Password must be at least {$this->config['password_min_length']} characters long");
        }
        
        // Update password
        $success = $this->userRepository->updatePassword($user['user_id'], $newPassword);
        
        if ($success) {
            $this->logger->info('Password changed', ['user_id' => $user['user_id']]);
        }
        
        return $success;
    }

    /**
     * Get session information
     * 
     * @return array Session information
     */
    public function getSessionInfo(): array
    {
        if (!$this->isAuthenticated()) {
            return ['authenticated' => false];
        }
        
        return [
            'authenticated' => true,
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role'],
            'login_time' => $_SESSION['login_time'] ?? null,
            'last_activity' => $_SESSION['last_activity'] ?? null,
            'session_id' => session_id(),
            'expires_at' => $_SESSION['last_activity'] + $this->config['session_lifetime']
        ];
    }

    /**
     * Prevent cloning of singleton
     */
    private function __clone() {}

    /**
     * Prevent unserialization of singleton
     */
    public function __wakeup() {}
}
