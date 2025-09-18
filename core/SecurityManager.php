<?php
/**
 * Security Manager - security hardening
 * 
 * Provides comprehensive security features including:
 * - CSRF protection
 * - Input validation and sanitization
 * - SQL injection prevention
 * - Session security
 * - Rate limiting
 * - Security headers
 * 
 * @author Senior Developer
 * @version 1.0.0
 */

class SecurityManager {
    private static $instance = null;
    private $errorHandler;
    private $csrfTokens = [];
    private $rateLimits = [];
    
    // Security configuration
    private const CSRF_TOKEN_LENGTH = 32;
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = 300; // 5 minutes
    private const SESSION_TIMEOUT = 3600; // 1 hour
    
    private function __construct() {
        $this->errorHandler = ErrorHandler::getInstance();
        $this->initializeSecureSession();
        $this->setSecurityHeaders();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): SecurityManager {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize secure session configuration
     */
    private function initializeSecureSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            // Configure secure session settings
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', 1);
            ini_set('session.gc_maxlifetime', self::SESSION_TIMEOUT);
            
            // Regenerate session ID periodically
            session_start();
            if (!isset($_SESSION['last_regeneration'])) {
                $this->regenerateSessionId();
            } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
                $this->regenerateSessionId();
            }
        }
        
        // Validate session timeout
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > self::SESSION_TIMEOUT)) {
            $this->destroySession();
        }
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Set security headers
     */
    private function setSecurityHeaders(): void {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('X-XSS-Protection: 1; mode=block');
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:; font-src \'self\'');
        }
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken(string $form = 'default'): string {
        try {
            $token = bin2hex(random_bytes(self::CSRF_TOKEN_LENGTH));
            $_SESSION['csrf_tokens'][$form] = $token;
            $this->csrfTokens[$form] = $token;
            return $token;
        } catch (Exception $e) {
            $this->errorHandler->logError('CSRF token generation failed', $e);
            throw new SecurityException('Unable to generate security token');
        }
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCSRFToken(string $token, string $form = 'default'): bool {
        if (!isset($_SESSION['csrf_tokens'][$form])) {
            return false;
        }
        
        $isValid = hash_equals($_SESSION['csrf_tokens'][$form], $token);
        
        // Token is single-use, remove after validation
        unset($_SESSION['csrf_tokens'][$form]);
        unset($this->csrfTokens[$form]);
        
        if (!$isValid) {
            $this->errorHandler->logError('CSRF token validation failed', [
                'form' => $form,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        }
        
        return $isValid;
    }
    
    /**
     * Sanitize input data
     */
    public function sanitizeInput($data, string $type = 'string') {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return $this->sanitizeInput($item, $type);
            }, $data);
        }
        
        switch ($type) {
            case 'email':
                return filter_var(trim($data), FILTER_SANITIZE_EMAIL);
                
            case 'int':
                return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
                
            case 'float':
                return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                
            case 'url':
                return filter_var(trim($data), FILTER_SANITIZE_URL);
                
            case 'html':
                return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
                
            case 'string':
            default:
                return trim(strip_tags($data));
        }
    }
    
    /**
     * Validate input data
     */
    public function validateInput($data, array $rules): array {
        $errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            
            foreach ($fieldRules as $rule => $params) {
                switch ($rule) {
                    case 'required':
                        if ($params && (empty($value) && $value !== '0')) {
                            $errors[$field][] = 'This field is required';
                        }
                        break;
                        
                    case 'min_length':
                        if (!empty($value) && strlen($value) < $params) {
                            $errors[$field][] = "Minimum length is $params characters";
                        }
                        break;
                        
                    case 'max_length':
                        if (!empty($value) && strlen($value) > $params) {
                            $errors[$field][] = "Maximum length is $params characters";
                        }
                        break;
                        
                    case 'email':
                        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field][] = 'Invalid email format';
                        }
                        break;
                        
                    case 'numeric':
                        if (!empty($value) && !is_numeric($value)) {
                            $errors[$field][] = 'Must be numeric';
                        }
                        break;
                        
                    case 'regex':
                        if (!empty($value) && !preg_match($params, $value)) {
                            $errors[$field][] = 'Invalid format';
                        }
                        break;
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Check rate limiting
     */
    public function checkRateLimit(string $action, string $identifier = null): bool {
        $identifier = $identifier ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = $action . '_' . $identifier;
        $now = time();
        
        if (!isset($this->rateLimits[$key])) {
            $this->rateLimits[$key] = [];
        }
        
        // Clean old attempts (older than window)
        $this->rateLimits[$key] = array_filter(
            $this->rateLimits[$key],
            function($timestamp) use ($now) {
                return ($now - $timestamp) < self::LOCKOUT_DURATION;
            }
        );
        
        // Check if limit exceeded
        switch ($action) {
            case 'login':
                if (count($this->rateLimits[$key]) >= self::MAX_LOGIN_ATTEMPTS) {
                    $this->errorHandler->logError('Rate limit exceeded for login', [
                        'identifier' => $identifier,
                        'attempts' => count($this->rateLimits[$key])
                    ]);
                    return false;
                }
                break;
                
            case 'api':
                if (count($this->rateLimits[$key]) >= 100) { // 100 requests per 5 minutes
                    return false;
                }
                break;
        }
        
        // Record this attempt
        $this->rateLimits[$key][] = $now;
        return true;
    }
    
    /**
     * Hash password securely
     */
    public function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3          // 3 threads
        ]);
    }
    
    /**
     * Verify password
     */
    public function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate secure random token
     */
    public function generateSecureToken(int $length = 32): string {
        try {
            return bin2hex(random_bytes($length));
        } catch (Exception $e) {
            $this->errorHandler->logError('Secure token generation failed', $e);
            throw new SecurityException('Unable to generate secure token');
        }
    }
    
    /**
     * Regenerate session ID
     */
    public function regenerateSessionId(): void {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
    
    /**
     * Destroy session securely
     */
    public function destroySession(): void {
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    /**
     * Validate file upload security
     */
    public function validateFileUpload(array $file): array {
        $errors = [];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed';
            return $errors;
        }
        
        // Check file size (5MB limit)
        if ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'File size too large (max 5MB)';
        }
        
        // Check file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            $errors[] = 'Invalid file type';
        }
        
        // Check filename for security
        $filename = basename($file['name']);
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
            $errors[] = 'Invalid filename characters';
        }
        
        return $errors;
    }
    
    /**
     * Get CSRF token for forms
     */
    public function getCSRFTokenInput(string $form = 'default'): string {
        $token = $this->generateCSRFToken($form);
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Validate request origin
     */
    public function validateRequestOrigin(): bool {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        if (empty($origin) || empty($host)) {
            return false;
        }
        
        $allowedOrigins = [
            'http://' . $host,
            'https://' . $host
        ];
        
        return in_array($origin, $allowedOrigins) || 
               strpos($origin, 'http://' . $host) === 0 ||
               strpos($origin, 'https://' . $host) === 0;
    }
}

/**
 * Security Exception class
 */
class SecurityException extends Exception {}
