<?php

namespace App\Services;

use App\Core\Database;
use App\Core\LoggingService;
use App\Core\ConfigurationService;
use Exception;

/**
 * Advanced Security Service
 * 
 * Provides comprehensive security hardening including:
 * - Advanced input validation and sanitization
 * - SQL injection prevention
 * - XSS protection with Content Security Policy
 * - Audit logging and security monitoring
 * - Intrusion detection and prevention
 * - Security headers and configuration
 */
class SecurityHardeningService
{
    private static ?self $instance = null;
    private Database $database;
    private LoggingService $logger;
    private ConfigurationService $config;
    private array $auditEvents = [];
    private array $securityMetrics = [];
    
    private function __construct()
    {
        $this->database = Database::getInstance();
        $this->logger = LoggingService::getInstance();
        $this->config = ConfigurationService::getInstance();
        $this->initializeSecurityMetrics();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Apply comprehensive security headers
     */
    public function applySecurityHeaders(): void
    {
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Enable XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Enforce HTTPS
        if ($this->config->get('security.force_https', true)) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Content Security Policy
        $cspPolicy = $this->buildContentSecurityPolicy();
        header("Content-Security-Policy: {$cspPolicy}");
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Feature Policy / Permissions Policy
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        
        // Remove server information
        header_remove('X-Powered-By');
        header_remove('Server');
        
        $this->logger->debug('Security headers applied');
    }
    
    /**
     * Advanced input validation and sanitization
     */
    public function validateAndSanitizeInput(array $input, array $rules): array
    {
        $sanitized = [];
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $input[$field] ?? null;
            
            try {
                $sanitized[$field] = $this->processFieldValidation($field, $value, $rule);
            } catch (Exception $e) {
                $errors[$field] = $e->getMessage();
                
                // Log validation failure for security monitoring
                $this->logSecurityEvent('validation_failure', [
                    'field' => $field,
                    'error' => $e->getMessage(),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
        }
        
        if (!empty($errors)) {
            throw new Exception('Input validation failed: ' . json_encode($errors));
        }
        
        return $sanitized;
    }
    
    /**
     * SQL injection prevention with advanced detection
     */
    public function detectSqlInjectionAttempt(string $input): bool
    {
        // Common SQL injection patterns
        $patterns = [
            '/(\bunion\b.*\bselect\b)/i',
            '/(\bselect\b.*\bfrom\b)/i',
            '/(\binsert\b.*\binto\b)/i',
            '/(\bupdate\b.*\bset\b)/i',
            '/(\bdelete\b.*\bfrom\b)/i',
            '/(\bdrop\b.*\btable\b)/i',
            '/(\bcreate\b.*\btable\b)/i',
            '/(\balter\b.*\btable\b)/i',
            '/(\bexec\b|\bexecute\b)/i',
            '/(\bsp_\w+)/i',
            '/(\bxp_\w+)/i',
            '/(\'|\"|;|--|\*|\+|\||&|<|>|=|\(|\))/i',
            '/(\bhex\b|\bchar\b|\bcast\b|\bconvert\b)/i',
            '/(\bwaitfor\b|\bdelay\b)/i',
            '/(\bbenchmark\b|\bsleep\b)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $this->logSecurityEvent('sql_injection_attempt', [
                    'input' => substr($input, 0, 500), // Limit logged input
                    'pattern' => $pattern,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]);
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * XSS protection with advanced detection
     */
    public function detectXssAttempt(string $input): bool
    {
        // Common XSS patterns
        $patterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i',
            '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/i',
            '/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/i',
            '/<embed\b[^<]*>/i',
            '/<form\b[^<]*(?:(?!<\/form>)<[^<]*)*<\/form>/i',
            '/on\w+\s*=\s*["\']?[^"\'>\s]*["\']?/i',
            '/javascript\s*:/i',
            '/vbscript\s*:/i',
            '/data\s*:\s*text\/html/i',
            '/<\s*\/?\s*(script|iframe|object|embed|form|meta|link|style|img|svg|math|table|div|span|a|p|h[1-6]|ul|ol|li)\b[^>]*>/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $this->logSecurityEvent('xss_attempt', [
                    'input' => substr($input, 0, 500),
                    'pattern' => $pattern,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]);
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Advanced rate limiting with dynamic thresholds
     */
    public function checkAdvancedRateLimit(string $identifier, string $action, ?array $customLimits = null): bool
    {
        $limits = $customLimits ?? $this->getDefaultRateLimits();
        $actionLimits = $limits[$action] ?? $limits['default'];
        
        $timeWindows = [
            'minute' => 60,
            'hour' => 3600,
            'day' => 86400
        ];
        
        foreach ($timeWindows as $window => $seconds) {
            if (!isset($actionLimits[$window])) {
                continue;
            }
            
            $count = $this->getActionCount($identifier, $action, $seconds);
            $limit = $actionLimits[$window];
            
            if ($count >= $limit) {
                $this->logSecurityEvent('rate_limit_exceeded', [
                    'identifier' => $identifier,
                    'action' => $action,
                    'window' => $window,
                    'count' => $count,
                    'limit' => $limit,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                
                return false;
            }
        }
        
        // Record the action
        $this->recordAction($identifier, $action);
        
        return true;
    }
    
    /**
     * Audit logging with structured data
     */
    public function auditLog(string $action, array $data, ?int $userId = null): void
    {
        try {
            $auditData = [
                'action' => $action,
                'user_id' => $userId,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                'session_id' => session_id(),
                'timestamp' => date('c'),
                'data' => $data
            ];
            
            // Store in database
            $stmt = $this->database->prepare("
                INSERT INTO audit_log 
                (action, user_id, ip_address, user_agent, request_uri, request_method, 
                 session_id, data, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $action,
                $userId,
                $auditData['ip_address'],
                $auditData['user_agent'],
                $auditData['request_uri'],
                $auditData['request_method'],
                $auditData['session_id'],
                json_encode($data)
            ]);
            
            // Also log to file for backup
            $this->logger->info('Audit event', $auditData);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to write audit log', [
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * File upload security validation
     */
    public function validateFileUpload(array $file): array
    {
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'Invalid file upload';
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Check file size
        $maxSize = $this->config->get('security.max_upload_size', 5 * 1024 * 1024); // 5MB
        if ($file['size'] > $maxSize) {
            $errors[] = 'File size exceeds maximum allowed size';
        }
        
        // Validate file extension
        $allowedExtensions = $this->config->get('security.allowed_upload_extensions', [
            'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'
        ]);
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            $errors[] = 'File type not allowed';
        }
        
        // MIME type validation
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimeTypes = $this->config->get('security.allowed_mime_types', [
            'image/jpeg', 'image/png', 'image/gif', 'application/pdf',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ]);
        
        if (!in_array($mimeType, $allowedMimeTypes)) {
            $errors[] = 'File MIME type not allowed';
        }
        
        // Scan for malicious content
        if ($this->scanFileForMalware($file['tmp_name'])) {
            $errors[] = 'File contains potentially malicious content';
            
            $this->logSecurityEvent('malicious_file_upload', [
                'filename' => $file['name'],
                'mime_type' => $mimeType,
                'size' => $file['size'],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitized_filename' => $this->sanitizeFilename($file['name']),
            'mime_type' => $mimeType,
            'extension' => $extension
        ];
    }
    
    /**
     * Intrusion detection system
     */
    public function detectIntrusion(array $requestData): bool
    {
        $suspiciousPatterns = [
            // Directory traversal
            '/\.\.(\/|\\\\)/i',
            '/\.(\/|\\\\)\./i',
            
            // Command injection
            '/(\;|\||\&|\$|\`)/i',
            '/(cat|ls|pwd|id|whoami|uname|netstat|ps|kill|chmod|chown)/i',
            
            // Path disclosure
            '/(\/etc\/passwd|\/etc\/shadow|\/proc\/|\/sys\/)/i',
            
            // Protocol attacks
            '/(file:\/\/|ftp:\/\/|gopher:\/\/|dict:\/\/|ldap:\/\/)/i',
            
            // Header injection
            '/(\r|\n|%0d|%0a|%00)/i'
        ];
        
        $requestString = json_encode($requestData);
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $requestString)) {
                $this->logSecurityEvent('intrusion_attempt', [
                    'pattern' => $pattern,
                    'request_data' => substr($requestString, 0, 1000),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]);
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Session security hardening
     */
    public function hardenSession(): void
    {
        // Regenerate session ID on login
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        
        // Set secure session parameters
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $this->config->get('security.force_https', true) ? '1' : '0');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.entropy_length', '32');
        ini_set('session.entropy_file', '/dev/urandom');
        
        // Set session timeout
        $timeout = $this->config->get('security.session_timeout', 1800); // 30 minutes
        ini_set('session.gc_maxlifetime', $timeout);
        
        $this->logger->debug('Session security hardened');
    }
    
    /**
     * Password policy enforcement
     */
    public function validatePasswordPolicy(string $password, ?array $userData = null): array
    {
        $errors = [];
        $policy = $this->config->get('security.password_policy', []);
        
        // Minimum length
        $minLength = $policy['min_length'] ?? 8;
        if (strlen($password) < $minLength) {
            $errors[] = "Password must be at least {$minLength} characters long";
        }
        
        // Maximum length
        $maxLength = $policy['max_length'] ?? 128;
        if (strlen($password) > $maxLength) {
            $errors[] = "Password must not exceed {$maxLength} characters";
        }
        
        // Character requirements
        if ($policy['require_uppercase'] ?? true) {
            if (!preg_match('/[A-Z]/', $password)) {
                $errors[] = 'Password must contain at least one uppercase letter';
            }
        }
        
        if ($policy['require_lowercase'] ?? true) {
            if (!preg_match('/[a-z]/', $password)) {
                $errors[] = 'Password must contain at least one lowercase letter';
            }
        }
        
        if ($policy['require_numbers'] ?? true) {
            if (!preg_match('/[0-9]/', $password)) {
                $errors[] = 'Password must contain at least one number';
            }
        }
        
        if ($policy['require_special'] ?? false) {
            if (!preg_match('/[^A-Za-z0-9]/', $password)) {
                $errors[] = 'Password must contain at least one special character';
            }
        }
        
        // Common password check
        if ($this->isCommonPassword($password)) {
            $errors[] = 'Password is too common, please choose a more secure password';
        }
        
        // Personal information check
        if ($userData && $this->containsPersonalInfo($password, $userData)) {
            $errors[] = 'Password must not contain personal information';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'strength' => $this->calculatePasswordStrength($password)
        ];
    }
    
    /**
     * Get security metrics and statistics
     */
    public function getSecurityMetrics(): array
    {
        try {
            $metrics = [
                'today' => $this->getTodaySecurityMetrics(),
                'week' => $this->getWeekSecurityMetrics(),
                'month' => $this->getMonthSecurityMetrics(),
                'threats' => $this->getRecentThreats(),
                'blocked_ips' => $this->getBlockedIPs(),
                'system_health' => $this->getSystemSecurityHealth()
            ];
            
            return $metrics;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get security metrics', [
                'error' => $e->getMessage()
            ]);
            
            return ['error' => 'Failed to retrieve security metrics'];
        }
    }
    
    // Private helper methods
    
    private function initializeSecurityMetrics(): void
    {
        $this->securityMetrics = [
            'login_attempts' => 0,
            'failed_logins' => 0,
            'blocked_requests' => 0,
            'xss_attempts' => 0,
            'sql_injection_attempts' => 0,
            'intrusion_attempts' => 0
        ];
    }
    
    private function buildContentSecurityPolicy(): string
    {
        $policy = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: https:",
            "connect-src 'self' ws: wss:",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'"
        ];
        
        return implode('; ', $policy);
    }
    
    private function processFieldValidation(string $field, $value, array $rule)
    {
        // Handle required fields
        if (($rule['required'] ?? false) && ($value === null || $value === '')) {
            throw new Exception("Field {$field} is required");
        }
        
        if ($value === null || $value === '') {
            return $value;
        }
        
        // Type validation
        $type = $rule['type'] ?? 'string';
        
        switch ($type) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Invalid email format for field {$field}");
                }
                break;
                
            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    throw new Exception("Invalid URL format for field {$field}");
                }
                break;
                
            case 'int':
                if (!filter_var($value, FILTER_VALIDATE_INT)) {
                    throw new Exception("Field {$field} must be an integer");
                }
                $value = (int)$value;
                break;
                
            case 'float':
                if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                    throw new Exception("Field {$field} must be a number");
                }
                $value = (float)$value;
                break;
                
            case 'boolean':
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($value === null) {
                    throw new Exception("Field {$field} must be a boolean");
                }
                break;
        }
        
        // Length validation
        if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
            throw new Exception("Field {$field} must be at least {$rule['min_length']} characters");
        }
        
        if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
            throw new Exception("Field {$field} must not exceed {$rule['max_length']} characters");
        }
        
        // Pattern validation
        if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
            throw new Exception("Field {$field} format is invalid");
        }
        
        // Check for malicious content
        if ($this->detectSqlInjectionAttempt($value)) {
            throw new Exception("Field {$field} contains suspicious content");
        }
        
        if ($this->detectXssAttempt($value)) {
            throw new Exception("Field {$field} contains suspicious content");
        }
        
        // Sanitize based on type
        return $this->sanitizeValue($value, $type);
    }
    
    private function sanitizeValue($value, string $type)
    {
        switch ($type) {
            case 'string':
                return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            case 'email':
                return filter_var(trim($value), FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var(trim($value), FILTER_SANITIZE_URL);
            default:
                return $value;
        }
    }
    
    private function getDefaultRateLimits(): array
    {
        return [
            'login' => ['minute' => 5, 'hour' => 20, 'day' => 100],
            'register' => ['minute' => 2, 'hour' => 10, 'day' => 20],
            'forgot_password' => ['minute' => 2, 'hour' => 5, 'day' => 10],
            'api' => ['minute' => 60, 'hour' => 1000, 'day' => 10000],
            'default' => ['minute' => 30, 'hour' => 300, 'day' => 1000]
        ];
    }
    
    private function getActionCount(string $identifier, string $action, int $seconds): int
    {
        try {
            $stmt = $this->database->prepare("
                SELECT COUNT(*) as count 
                FROM rate_limit_log 
                WHERE identifier = ? AND action = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$identifier, $action, $seconds]);
            $result = $stmt->fetch();
            
            return (int)($result['count'] ?? 0);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get action count', [
                'identifier' => $identifier,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    private function recordAction(string $identifier, string $action): void
    {
        try {
            $stmt = $this->database->prepare("
                INSERT INTO rate_limit_log (identifier, action, ip_address, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([
                $identifier,
                $action,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to record action', [
                'identifier' => $identifier,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function logSecurityEvent(string $event, array $data): void
    {
        try {
            $eventData = array_merge($data, [
                'event' => $event,
                'timestamp' => date('c'),
                'server_info' => [
                    'php_version' => PHP_VERSION,
                    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'
                ]
            ]);
            
            $stmt = $this->database->prepare("
                INSERT INTO security_events 
                (event_type, data, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $event,
                json_encode($eventData),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            // Log to file as well
            $this->logger->warning('Security event', $eventData);
            
            // Update metrics
            $this->updateSecurityMetrics($event);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to log security event', [
                'event' => $event,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function updateSecurityMetrics(string $event): void
    {
        $metricMap = [
            'sql_injection_attempt' => 'sql_injection_attempts',
            'xss_attempt' => 'xss_attempts',
            'intrusion_attempt' => 'intrusion_attempts',
            'rate_limit_exceeded' => 'blocked_requests'
        ];
        
        if (isset($metricMap[$event])) {
            $this->securityMetrics[$metricMap[$event]]++;
        }
    }
    
    private function scanFileForMalware(string $filePath): bool
    {
        // Simple malware detection - in production, use proper antivirus
        $suspiciousPatterns = [
            '/<\?php.*system\s*\(/i',
            '/<\?php.*exec\s*\(/i',
            '/<\?php.*shell_exec\s*\(/i',
            '/<\?php.*passthru\s*\(/i',
            '/<script.*src.*http/i',
            '/eval\s*\(/i',
            '/base64_decode\s*\(/i'
        ];
        
        $content = file_get_contents($filePath, false, null, 0, 8192); // Read first 8KB
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function sanitizeFilename(string $filename): string
    {
        // Remove path information
        $filename = basename($filename);
        
        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Limit length
        if (strlen($filename) > 255) {
            $filename = substr($filename, 0, 255);
        }
        
        return $filename;
    }
    
    private function isCommonPassword(string $password): bool
    {
        $commonPasswords = [
            'password', '123456', '12345678', 'qwerty', 'abc123',
            'password123', 'admin', 'letmein', 'welcome', 'monkey',
            'dragon', 'password1', 'admin123', 'root', 'guest'
        ];
        
        return in_array(strtolower($password), $commonPasswords);
    }
    
    private function containsPersonalInfo(string $password, array $userData): bool
    {
        $checkFields = ['first_name', 'last_name', 'email', 'username'];
        $passwordLower = strtolower($password);
        
        foreach ($checkFields as $field) {
            if (isset($userData[$field])) {
                $value = strtolower($userData[$field]);
                if (strlen($value) > 3 && strpos($passwordLower, $value) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    private function calculatePasswordStrength(string $password): int
    {
        $score = 0;
        
        // Length bonus
        $score += min(strlen($password) * 2, 20);
        
        // Character variety
        if (preg_match('/[a-z]/', $password)) $score += 5;
        if (preg_match('/[A-Z]/', $password)) $score += 5;
        if (preg_match('/[0-9]/', $password)) $score += 5;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score += 10;
        
        // Deductions
        if (preg_match('/(.)\1{2,}/', $password)) $score -= 10; // Repeated characters
        if (preg_match('/123|abc|qwe/i', $password)) $score -= 10; // Sequential characters
        
        return max(0, min(100, $score));
    }
    
    private function getTodaySecurityMetrics(): array
    {
        try {
            $stmt = $this->database->prepare("
                SELECT 
                    event_type,
                    COUNT(*) as count
                FROM security_events 
                WHERE DATE(created_at) = CURDATE()
                GROUP BY event_type
            ");
            $stmt->execute();
            $events = $stmt->fetchAll();
            
            $metrics = [];
            foreach ($events as $event) {
                $metrics[$event['event_type']] = (int)$event['count'];
            }
            
            return $metrics;
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getWeekSecurityMetrics(): array
    {
        try {
            $stmt = $this->database->prepare("
                SELECT 
                    DATE(created_at) as date,
                    event_type,
                    COUNT(*) as count
                FROM security_events 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at), event_type
                ORDER BY date DESC
            ");
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getMonthSecurityMetrics(): array
    {
        try {
            $stmt = $this->database->prepare("
                SELECT 
                    WEEK(created_at) as week,
                    event_type,
                    COUNT(*) as count
                FROM security_events 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY WEEK(created_at), event_type
                ORDER BY week DESC
            ");
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getRecentThreats(): array
    {
        try {
            $stmt = $this->database->prepare("
                SELECT * FROM security_events 
                WHERE event_type IN ('sql_injection_attempt', 'xss_attempt', 'intrusion_attempt')
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getBlockedIPs(): array
    {
        try {
            $stmt = $this->database->prepare("
                SELECT 
                    ip_address,
                    COUNT(*) as incident_count,
                    MAX(created_at) as last_incident
                FROM security_events 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY ip_address
                HAVING incident_count >= 5
                ORDER BY incident_count DESC
            ");
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getSystemSecurityHealth(): array
    {
        return [
            'ssl_enabled' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'session_secure' => ini_get('session.cookie_secure') === '1',
            'security_headers' => $this->checkSecurityHeaders(),
            'php_version_secure' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'error_reporting_disabled' => ini_get('display_errors') === '0'
        ];
    }
    
    private function checkSecurityHeaders(): bool
    {
        $headers = headers_list();
        $requiredHeaders = ['X-Frame-Options', 'X-XSS-Protection', 'X-Content-Type-Options'];
        
        foreach ($requiredHeaders as $header) {
            $found = false;
            foreach ($headers as $sentHeader) {
                if (strpos($sentHeader, $header) === 0) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }
        
        return true;
    }
}
