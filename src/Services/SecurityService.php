<?php

declare(strict_types=1);

namespace RfidCheckin\Services;

use Exception;

/**
 * Security Service
 * 
 * Provides comprehensive security features including input validation,
 * CSRF protection, XSS prevention, and security monitoring.
 * Centralizes all security-related functionality.
 * 
 * Features:
 * - Input validation and sanitization
 * - CSRF token generation and validation
 * - XSS protection with output encoding
 * - Rate limiting for API endpoints
 * - Security header management
 * - Intrusion detection logging
 * 
 * @package RfidCheckin\Services
 * @version 1.0.0
 * @author Senior Development Team
 */
class SecurityService
{
    private static ?SecurityService $instance = null;
    private LoggingService $logger;
    private array $csrfTokens = [];
    private array $rateLimits = [];
    private array $config;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        $this->logger = LoggingService::getInstance();
        $this->loadConfiguration();
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
     * Load security configuration
     */
    private function loadConfiguration(): void
    {
        $this->config = [
            'csrf_token_lifetime' => (int) ($_ENV['CSRF_TOKEN_LIFETIME'] ?? 3600),
            'rate_limit_window' => (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 60),
            'max_requests_per_window' => (int) ($_ENV['MAX_REQUESTS_PER_WINDOW'] ?? 100),
            'password_min_length' => (int) ($_ENV['PASSWORD_MIN_LENGTH'] ?? 8),
            'allowed_file_types' => explode(',', $_ENV['ALLOWED_FILE_TYPES'] ?? 'jpg,jpeg,png,gif,pdf,doc,docx'),
            'max_file_size' => (int) ($_ENV['MAX_FILE_SIZE'] ?? 5242880), // 5MB
        ];
    }

    /**
     * Generate CSRF token
     * 
     * @param string $action Action name for token scope
     * @return string CSRF token
     */
    public function generateCSRFToken(string $action = 'general'): string
    {
        $token = bin2hex(random_bytes(32));
        
        $_SESSION['csrf_tokens'][$action] = [
            'token' => $token,
            'created' => time()
        ];
        
        // Clean up old tokens
        $this->cleanupExpiredTokens();
        
        $this->logger->debug('CSRF token generated', ['action' => $action]);
        
        return $token;
    }

    /**
     * Validate CSRF token
     * 
     * @param string $token Token to validate
     * @param string $action Action name for token scope
     * @return bool True if valid
     */
    public function validateCSRFToken(string $token, string $action = 'general'): bool
    {
        if (!isset($_SESSION['csrf_tokens'][$action])) {
            $this->logger->security('csrf_validation_failed', 'No CSRF token found for action', [
                'action' => $action,
                'provided_token' => substr($token, 0, 8) . '***'
            ]);
            return false;
        }
        
        $storedToken = $_SESSION['csrf_tokens'][$action];
        
        // Check if token is expired
        if (time() - $storedToken['created'] > $this->config['csrf_token_lifetime']) {
            unset($_SESSION['csrf_tokens'][$action]);
            $this->logger->security('csrf_token_expired', 'CSRF token expired', [
                'action' => $action,
                'age' => time() - $storedToken['created']
            ]);
            return false;
        }
        
        // Use timing-safe comparison
        $isValid = hash_equals($storedToken['token'], $token);
        
        if (!$isValid) {
            $this->logger->security('csrf_token_invalid', 'Invalid CSRF token provided', [
                'action' => $action,
                'expected' => substr($storedToken['token'], 0, 8) . '***',
                'provided' => substr($token, 0, 8) . '***'
            ]);
        }
        
        return $isValid;
    }

    /**
     * Clean up expired CSRF tokens
     */
    private function cleanupExpiredTokens(): void
    {
        if (!isset($_SESSION['csrf_tokens'])) {
            return;
        }
        
        $now = time();
        foreach ($_SESSION['csrf_tokens'] as $action => $tokenData) {
            if ($now - $tokenData['created'] > $this->config['csrf_token_lifetime']) {
                unset($_SESSION['csrf_tokens'][$action]);
            }
        }
    }

    /**
     * Validate and sanitize input
     * 
     * @param mixed $input Input value
     * @param string $type Input type (email, username, password, etc.)
     * @param array $options Validation options
     * @return mixed Sanitized input or false if invalid
     */
    public function validateInput($input, string $type, array $options = [])
    {
        if ($input === null || $input === '') {
            return $options['allow_empty'] ?? false ? '' : false;
        }

        $input = is_string($input) ? trim($input) : $input;

        switch ($type) {
            case 'email':
                return $this->validateEmail($input);
                
            case 'username':
                return $this->validateUsername($input);
                
            case 'password':
                return $this->validatePassword($input, $options);
                
            case 'rfid':
                return $this->validateRfidTag($input);
                
            case 'text':
                return $this->validateText($input, $options);
                
            case 'integer':
                return $this->validateInteger($input, $options);
                
            case 'float':
                return $this->validateFloat($input, $options);
                
            case 'boolean':
                return $this->validateBoolean($input);
                
            case 'url':
                return $this->validateUrl($input);
                
            case 'phone':
                return $this->validatePhone($input);
                
            case 'date':
                return $this->validateDate($input);
                
            case 'json':
                return $this->validateJson($input);
                
            default:
                $this->logger->warning('Unknown validation type', ['type' => $type]);
                return htmlspecialchars((string) $input, ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * Validate email address
     */
    private function validateEmail(string $email): string|false
    {
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);
        if ($email === false) {
            return false;
        }
        
        // Additional checks
        if (strlen($email) > 254) { // RFC 5321 limit
            return false;
        }
        
        return $email;
    }

    /**
     * Validate username
     */
    private function validateUsername(string $username): string|false
    {
        // Username: 3-50 chars, alphanumeric + underscore/hyphen
        if (!preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $username)) {
            return false;
        }
        
        return $username;
    }

    /**
     * Validate password
     */
    private function validatePassword(string $password, array $options = []): string|false
    {
        $minLength = $options['min_length'] ?? $this->config['password_min_length'];
        
        if (strlen($password) < $minLength) {
            return false;
        }
        
        // Additional password strength requirements can be added here
        if ($options['require_complexity'] ?? false) {
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password)) {
                return false;
            }
        }
        
        return $password;
    }

    /**
     * Validate RFID tag
     */
    private function validateRfidTag(string $rfid): string|false
    {
        // RFID tag: 6-20 alphanumeric characters
        if (!preg_match('/^[a-zA-Z0-9]{6,20}$/', $rfid)) {
            return false;
        }
        
        return strtoupper($rfid);
    }

    /**
     * Validate text input
     */
    private function validateText(string $text, array $options = []): string|false
    {
        $maxLength = $options['max_length'] ?? 1000;
        $minLength = $options['min_length'] ?? 0;
        
        if (strlen($text) > $maxLength || strlen($text) < $minLength) {
            return false;
        }
        
        // Remove potentially dangerous characters
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        return $text;
    }

    /**
     * Validate integer
     */
    private function validateInteger($value, array $options = []): int|false
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        if ($int === false) {
            return false;
        }
        
        $min = $options['min'] ?? PHP_INT_MIN;
        $max = $options['max'] ?? PHP_INT_MAX;
        
        if ($int < $min || $int > $max) {
            return false;
        }
        
        return $int;
    }

    /**
     * Validate float
     */
    private function validateFloat($value, array $options = []): float|false
    {
        $float = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($float === false) {
            return false;
        }
        
        $min = $options['min'] ?? -PHP_FLOAT_MAX;
        $max = $options['max'] ?? PHP_FLOAT_MAX;
        
        if ($float < $min || $float > $max) {
            return false;
        }
        
        return $float;
    }

    /**
     * Validate boolean
     */
    private function validateBoolean($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null;
    }

    /**
     * Validate URL
     */
    private function validateUrl(string $url): string|false
    {
        $url = filter_var($url, FILTER_VALIDATE_URL);
        if ($url === false) {
            return false;
        }
        
        // Only allow HTTP/HTTPS
        if (!in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'])) {
            return false;
        }
        
        return $url;
    }

    /**
     * Validate phone number
     */
    private function validatePhone(string $phone): string|false
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Basic phone validation (10-15 digits, optional + prefix)
        if (!preg_match('/^\+?[0-9]{10,15}$/', $phone)) {
            return false;
        }
        
        return $phone;
    }

    /**
     * Validate date
     */
    private function validateDate(string $date): string|false
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return false;
        }
        
        // Return in standardized format
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Validate JSON
     */
    private function validateJson(string $json): array|false
    {
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        return $decoded;
    }

    /**
     * Escape output for safe HTML display
     * 
     * @param string $value Value to escape
     * @param string $context Escape context (html, attr, js, css, url)
     * @return string Escaped value
     */
    public function escapeOutput(string $value, string $context = 'html'): string
    {
        switch ($context) {
            case 'html':
                return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                
            case 'attr':
                return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                
            case 'js':
                return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                
            case 'css':
                return preg_replace('/[^a-zA-Z0-9\-_]/', '', $value);
                
            case 'url':
                return urlencode($value);
                
            default:
                return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    /**
     * Check rate limiting
     * 
     * @param string $identifier Rate limit identifier (IP, user ID, etc.)
     * @param string $action Action being rate limited
     * @return bool True if within limits
     */
    public function checkRateLimit(string $identifier, string $action = 'general'): bool
    {
        $key = $action . ':' . $identifier;
        $now = time();
        $window = $this->config['rate_limit_window'];
        
        if (!isset($this->rateLimits[$key])) {
            $this->rateLimits[$key] = [];
        }
        
        // Remove old requests outside the window
        $this->rateLimits[$key] = array_filter(
            $this->rateLimits[$key],
            fn($timestamp) => $now - $timestamp < $window
        );
        
        // Check if limit exceeded
        if (count($this->rateLimits[$key]) >= $this->config['max_requests_per_window']) {
            $this->logger->security('rate_limit_exceeded', 'Rate limit exceeded', [
                'identifier' => $identifier,
                'action' => $action,
                'request_count' => count($this->rateLimits[$key])
            ]);
            return false;
        }
        
        // Add current request
        $this->rateLimits[$key][] = $now;
        
        return true;
    }

    /**
     * Validate file upload
     * 
     * @param array $file $_FILES entry
     * @return array Validation result
     */
    public function validateFileUpload(array $file): array
    {
        $result = ['valid' => false, 'errors' => []];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $result['errors'][] = 'File upload error: ' . $this->getUploadErrorMessage($file['error']);
            return $result;
        }
        
        // Check file size
        if ($file['size'] > $this->config['max_file_size']) {
            $result['errors'][] = 'File size exceeds maximum allowed size';
            return $result;
        }
        
        // Check file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->config['allowed_file_types'])) {
            $result['errors'][] = 'File type not allowed';
            return $result;
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        if (!isset($allowedMimeTypes[$extension]) || $mimeType !== $allowedMimeTypes[$extension]) {
            $result['errors'][] = 'File type does not match content';
            return $result;
        }
        
        $result['valid'] = true;
        return $result;
    }

    /**
     * Get upload error message
     */
    private function getUploadErrorMessage(int $error): string
    {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds upload_max_filesize directive';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds MAX_FILE_SIZE directive';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }

    /**
     * Set security headers
     */
    public function setSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        
        // Prevent XSS attacks
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        
        // HTTPS enforcement
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Content Security Policy
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "connect-src 'self'",
            "font-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'"
        ];
        header('Content-Security-Policy: ' . implode('; ', $csp));
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    /**
     * Generate secure random token
     * 
     * @param int $length Token length
     * @return string Random token
     */
    public function generateSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Hash sensitive data
     * 
     * @param string $data Data to hash
     * @return string Hashed data
     */
    public function hashData(string $data): string
    {
        return hash('sha256', $data);
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
