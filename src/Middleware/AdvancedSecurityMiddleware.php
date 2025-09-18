<?php

namespace App\Middleware;

use App\Services\SecurityHardeningService;
use App\Core\LoggingService;
use Exception;

/**
 * Advanced Security Middleware
 * 
 * Provides comprehensive security enforcement including:
 * - Advanced input validation and sanitization
 * - Intrusion detection and prevention
 * - Rate limiting with dynamic thresholds
 * - Security header enforcement
 * - Request monitoring and analysis
 */
class AdvancedSecurityMiddleware
{
    private SecurityHardeningService $securityService;
    private LoggingService $logger;
    private array $securityConfig;
    private bool $enabled = true;
    
    public function __construct()
    {
        $this->securityService = SecurityHardeningService::getInstance();
        $this->logger = LoggingService::getInstance();
        $this->loadSecurityConfig();
    }
    
    /**
     * Process incoming request through security filters
     */
    public function handle(array $request, callable $next): array
    {
        if (!$this->enabled) {
            return $next($request);
        }
        
        try {
            // Apply security headers first
            $this->securityService->applySecurityHeaders();
            
            // Pre-request security checks
            $this->performPreRequestChecks($request);
            
            // Input validation and sanitization
            $sanitizedRequest = $this->validateAndSanitizeRequest($request);
            
            // Rate limiting
            $this->enforceRateLimiting($request);
            
            // Intrusion detection
            $this->detectIntrusion($sanitizedRequest);
            
            // Session security
            $this->securityService->hardenSession();
            
            // Pass through to next middleware/controller
            $response = $next($sanitizedRequest);
            
            // Post-request security checks
            $this->performPostRequestChecks($request, $response);
            
            return $response;
            
        } catch (Exception $e) {
            return $this->handleSecurityException($e, $request);
        }
    }
    
    /**
     * Configure security middleware
     */
    public function configure(array $config): self
    {
        $this->securityConfig = array_merge($this->securityConfig, $config);
        return $this;
    }
    
    /**
     * Enable or disable security middleware
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }
    
    /**
     * Pre-request security checks
     */
    private function performPreRequestChecks(array $request): void
    {
        // Check for blocked IPs
        $this->checkBlockedIP();
        
        // Validate request structure
        $this->validateRequestStructure($request);
        
        // Check request size limits
        $this->validateRequestSize($request);
        
        // Validate HTTP method
        $this->validateHttpMethod($request);
        
        // Check for suspicious user agents
        $this->checkUserAgent();
        
        // Validate referrer if required
        $this->validateReferrer($request);
    }
    
    /**
     * Validate and sanitize request data
     */
    private function validateAndSanitizeRequest(array $request): array
    {
        $sanitized = $request;
        
        // Define validation rules based on request type
        $rules = $this->getValidationRules($request);
        
        if (!empty($rules) && isset($request['data'])) {
            try {
                $sanitized['data'] = $this->securityService->validateAndSanitizeInput(
                    $request['data'], 
                    $rules
                );
            } catch (Exception $e) {
                $this->logger->warning('Input validation failed', [
                    'error' => $e->getMessage(),
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                
                throw new Exception('Invalid input data provided');
            }
        }
        
        // Sanitize all string inputs
        $sanitized = $this->deepSanitize($sanitized);
        
        return $sanitized;
    }
    
    /**
     * Enforce rate limiting
     */
    private function enforceRateLimiting(array $request): void
    {
        if (!($this->securityConfig['rate_limiting']['enabled'] ?? true)) {
            return;
        }
        
        $identifier = $this->getRateLimitIdentifier($request);
        $action = $this->getRateLimitAction($request);
        
        if (!$this->securityService->checkAdvancedRateLimit($identifier, $action)) {
            $this->logger->warning('Rate limit exceeded', [
                'identifier' => $identifier,
                'action' => $action,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ]);
            
            // Return rate limit response
            http_response_code(429);
            header('Retry-After: ' . ($this->securityConfig['rate_limiting']['retry_after'] ?? 60));
            
            echo json_encode([
                'success' => false,
                'error' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => ($this->securityConfig['rate_limiting']['retry_after'] ?? 60)
            ]);
            
            exit;
        }
    }
    
    /**
     * Detect intrusion attempts
     */
    private function detectIntrusion(array $request): void
    {
        if (!($this->securityConfig['intrusion_detection']['enabled'] ?? true)) {
            return;
        }
        
        if ($this->securityService->detectIntrusion($request)) {
            $this->handleIntrusionAttempt($request);
        }
        
        // Additional checks for specific attack patterns
        $this->checkForAttackPatterns($request);
    }
    
    /**
     * Post-request security checks
     */
    private function performPostRequestChecks(array $request, array $response): void
    {
        // Log successful operations for audit trail
        if ($response['success'] ?? false) {
            $this->auditSuccessfulOperation($request, $response);
        }
        
        // Check response for sensitive data leakage
        $this->checkResponseSecurity($response);
        
        // Update security metrics
        $this->updateSecurityMetrics($request, $response);
    }
    
    /**
     * Handle security exceptions
     */
    private function handleSecurityException(Exception $e, array $request): array
    {
        $this->logger->error('Security middleware exception', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        // Log security event
        $this->securityService->auditLog('security_exception', [
            'error' => $e->getMessage(),
            'request_data' => $this->sanitizeLogData($request)
        ]);
        
        // Return secure error response
        http_response_code(400);
        return [
            'success' => false,
            'error' => 'Security validation failed',
            'details' => $this->securityConfig['debug_mode'] ?? false ? $e->getMessage() : null
        ];
    }
    
    /**
     * Load security configuration
     */
    private function loadSecurityConfig(): void
    {
        $this->securityConfig = [
            'rate_limiting' => [
                'enabled' => true,
                'retry_after' => 60
            ],
            'intrusion_detection' => [
                'enabled' => true,
                'sensitivity' => 'medium'
            ],
            'input_validation' => [
                'enabled' => true,
                'strict_mode' => true
            ],
            'audit_logging' => [
                'enabled' => true,
                'log_level' => 'info'
            ],
            'blocked_ips' => [
                'enabled' => true,
                'auto_block' => true,
                'block_duration' => 3600 // 1 hour
            ],
            'debug_mode' => false
        ];
    }
    
    /**
     * Check for blocked IP addresses
     */
    private function checkBlockedIP(): void
    {
        if (!($this->securityConfig['blocked_ips']['enabled'] ?? true)) {
            return;
        }
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        
        if ($this->isIPBlocked($ipAddress)) {
            $this->logger->warning('Blocked IP access attempt', [
                'ip_address' => $ipAddress,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Access denied'
            ]);
            exit;
        }
    }
    
    /**
     * Validate request structure
     */
    private function validateRequestStructure(array $request): void
    {
        // Check for required request components
        $requiredFields = ['method', 'uri'];
        
        foreach ($requiredFields as $field) {
            if (!isset($request[$field])) {
                throw new Exception("Missing required request field: {$field}");
            }
        }
        
        // Validate request method
        $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        if (!in_array($request['method'] ?? '', $allowedMethods)) {
            throw new Exception('Invalid HTTP method');
        }
    }
    
    /**
     * Validate request size
     */
    private function validateRequestSize(array $request): void
    {
        $maxSize = $this->securityConfig['max_request_size'] ?? (10 * 1024 * 1024); // 10MB
        
        // Check content length
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
        if ($contentLength > $maxSize) {
            throw new Exception('Request size exceeds maximum allowed size');
        }
        
        // Check serialized request size
        $requestSize = strlen(serialize($request));
        if ($requestSize > $maxSize) {
            throw new Exception('Request data too large');
        }
    }
    
    /**
     * Validate HTTP method
     */
    private function validateHttpMethod(array $request): void
    {
        $method = $request['method'] ?? '';
        $uri = $request['uri'] ?? '';
        
        // Check method overrides
        if ($method === 'POST' && isset($_POST['_method'])) {
            $overrideMethod = strtoupper($_POST['_method']);
            if (in_array($overrideMethod, ['PUT', 'DELETE', 'PATCH'])) {
                $request['method'] = $overrideMethod;
            }
        }
        
        // Validate method for specific endpoints
        $this->validateMethodForEndpoint($method, $uri);
    }
    
    /**
     * Check user agent for suspicious patterns
     */
    private function checkUserAgent(): void
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Suspicious user agent patterns
        $suspiciousPatterns = [
            '/bot|crawler|spider|scraper/i',
            '/curl|wget|python|perl|java/i',
            '/sqlmap|nikto|nmap|masscan/i',
            '/benchmark|sleep|waitfor/i'
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                $this->logger->warning('Suspicious user agent detected', [
                    'user_agent' => $userAgent,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'pattern' => $pattern
                ]);
                
                // Optionally block or flag
                if ($this->securityConfig['block_suspicious_agents'] ?? false) {
                    throw new Exception('Access denied');
                }
                
                break;
            }
        }
    }
    
    /**
     * Validate referrer if required
     */
    private function validateReferrer(array $request): void
    {
        if (!($this->securityConfig['validate_referrer'] ?? false)) {
            return;
        }
        
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        // Check if referrer is from same domain for sensitive operations
        $sensitiveActions = ['login', 'register', 'password_reset', 'admin'];
        $action = $this->extractActionFromRequest($request);
        
        if (in_array($action, $sensitiveActions)) {
            if (empty($referrer) || !$this->isValidReferrer($referrer, $host)) {
                $this->logger->warning('Invalid referrer for sensitive action', [
                    'action' => $action,
                    'referrer' => $referrer,
                    'expected_host' => $host,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                
                throw new Exception('Invalid request origin');
            }
        }
    }
    
    /**
     * Get validation rules based on request
     */
    private function getValidationRules(array $request): array
    {
        $action = $this->extractActionFromRequest($request);
        
        // Define validation rules for different actions
        $rules = [
            'login' => [
                'email' => ['required' => true, 'type' => 'email', 'max_length' => 255],
                'password' => ['required' => true, 'type' => 'string', 'min_length' => 1, 'max_length' => 255]
            ],
            'register' => [
                'first_name' => ['required' => true, 'type' => 'string', 'max_length' => 50],
                'last_name' => ['required' => true, 'type' => 'string', 'max_length' => 50],
                'email' => ['required' => true, 'type' => 'email', 'max_length' => 255],
                'password' => ['required' => true, 'type' => 'string', 'min_length' => 8, 'max_length' => 255]
            ],
            'update_profile' => [
                'first_name' => ['required' => false, 'type' => 'string', 'max_length' => 50],
                'last_name' => ['required' => false, 'type' => 'string', 'max_length' => 50],
                'phone' => ['required' => false, 'type' => 'string', 'max_length' => 20]
            ],
            'rfid_scan' => [
                'rfid_code' => ['required' => true, 'type' => 'string', 'max_length' => 50],
                'device_id' => ['required' => true, 'type' => 'string', 'max_length' => 100]
            ]
        ];
        
        return $rules[$action] ?? [];
    }
    
    /**
     * Deep sanitize array data
     */
    private function deepSanitize($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'deepSanitize'], $data);
        }
        
        if (is_string($data)) {
            // Remove null bytes
            $data = str_replace("\0", '', $data);
            
            // Trim whitespace
            $data = trim($data);
            
            // Basic HTML encoding for output safety
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        }
        
        return $data;
    }
    
    /**
     * Get rate limit identifier
     */
    private function getRateLimitIdentifier(array $request): string
    {
        // Use IP address as primary identifier
        $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // For authenticated requests, use user ID
        if (isset($request['user_id'])) {
            $identifier = 'user_' . $request['user_id'];
        }
        
        return $identifier;
    }
    
    /**
     * Get rate limit action
     */
    private function getRateLimitAction(array $request): string
    {
        $action = $this->extractActionFromRequest($request);
        
        // Map specific actions to rate limit categories
        $actionMap = [
            'login' => 'login',
            'register' => 'register',
            'forgot_password' => 'forgot_password',
            'reset_password' => 'forgot_password'
        ];
        
        return $actionMap[$action] ?? 'default';
    }
    
    /**
     * Check for specific attack patterns
     */
    private function checkForAttackPatterns(array $request): void
    {
        $requestString = json_encode($request);
        
        // Check for common attack patterns
        $attackPatterns = [
            'directory_traversal' => '/\.\.(\/|\\\\)/',
            'command_injection' => '/(\;|\||&|\$|\`)/',
            'ldap_injection' => '/(\*|\(|\)|\\|\/|null|\\x00)/',
            'xpath_injection' => '/(\[|\]|\'|"|or|and|\+|\-|\=)/i',
            'template_injection' => '/(\{\{|\}\}|\{%|%\}|<\?|<script)/i'
        ];
        
        foreach ($attackPatterns as $type => $pattern) {
            if (preg_match($pattern, $requestString)) {
                $this->securityService->auditLog('attack_pattern_detected', [
                    'attack_type' => $type,
                    'pattern' => $pattern,
                    'request_sample' => substr($requestString, 0, 500)
                ]);
                
                if ($this->securityConfig['block_attack_patterns'] ?? true) {
                    throw new Exception('Malicious request detected');
                }
            }
        }
    }
    
    /**
     * Handle intrusion attempts
     */
    private function handleIntrusionAttempt(array $request): void
    {
        $this->logger->warning('Intrusion attempt detected', [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'request_sample' => substr(json_encode($request), 0, 500)
        ]);
        
        // Auto-block IP if enabled
        if ($this->securityConfig['blocked_ips']['auto_block'] ?? true) {
            $this->blockIP($_SERVER['REMOTE_ADDR'] ?? '');
        }
        
        // Audit log the intrusion
        $this->securityService->auditLog('intrusion_attempt', [
            'detection_method' => 'pattern_matching',
            'threat_level' => 'high',
            'request_data' => $this->sanitizeLogData($request)
        ]);
        
        throw new Exception('Security violation detected');
    }
    
    /**
     * Audit successful operations
     */
    private function auditSuccessfulOperation(array $request, array $response): void
    {
        if (!($this->securityConfig['audit_logging']['enabled'] ?? true)) {
            return;
        }
        
        $action = $this->extractActionFromRequest($request);
        
        // Only audit significant operations
        $auditableActions = [
            'login', 'logout', 'register', 'password_change', 'profile_update',
            'admin_action', 'rfid_scan', 'user_management', 'system_config'
        ];
        
        if (in_array($action, $auditableActions)) {
            $this->securityService->auditLog($action, [
                'result' => 'success',
                'response_code' => $response['code'] ?? 200,
                'user_id' => $request['user_id'] ?? null
            ], $request['user_id'] ?? null);
        }
    }
    
    /**
     * Check response for security issues
     */
    private function checkResponseSecurity(array $response): void
    {
        // Check for sensitive data in response
        $sensitivePatterns = [
            '/password/i',
            '/secret/i',
            '/token/i',
            '/key/i',
            '/hash/i'
        ];
        
        $responseString = json_encode($response);
        
        foreach ($sensitivePatterns as $pattern) {
            if (preg_match($pattern, $responseString)) {
                $this->logger->warning('Potential sensitive data in response', [
                    'pattern' => $pattern,
                    'response_sample' => substr($responseString, 0, 200)
                ]);
                break;
            }
        }
    }
    
    /**
     * Update security metrics
     */
    private function updateSecurityMetrics(array $request, array $response): void
    {
        // Track request statistics
        $metrics = [
            'total_requests' => 1,
            'method_' . strtolower($request['method'] ?? 'unknown') => 1
        ];
        
        if (!($response['success'] ?? true)) {
            $metrics['failed_requests'] = 1;
        }
        
        // Store metrics (simplified version - in production, use proper metrics storage)
        $this->logger->debug('Security metrics', $metrics);
    }
    
    /**
     * Extract action from request
     */
    private function extractActionFromRequest(array $request): string
    {
        $uri = $request['uri'] ?? '';
        
        // Extract action from URI
        if (preg_match('/\/api\/([^\/]+)/', $uri, $matches)) {
            return $matches[1];
        }
        
        if (preg_match('/\/([^\/]+)\.php/', $uri, $matches)) {
            return $matches[1];
        }
        
        return 'unknown';
    }
    
    /**
     * Check if IP is blocked
     */
    private function isIPBlocked(string $ipAddress): bool
    {
        try {
            $stmt = $this->securityService->database->prepare("
                SELECT id FROM blocked_ips 
                WHERE ip_address = ? 
                AND (expires_at IS NULL OR expires_at > NOW())
                AND is_active = 1
            ");
            $stmt->execute([$ipAddress]);
            
            return $stmt->fetch() !== false;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to check blocked IP', [
                'ip_address' => $ipAddress,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Block an IP address
     */
    private function blockIP(string $ipAddress): void
    {
        if (empty($ipAddress)) {
            return;
        }
        
        try {
            $duration = $this->securityConfig['blocked_ips']['block_duration'] ?? 3600;
            $expiresAt = date('Y-m-d H:i:s', time() + $duration);
            
            $stmt = $this->securityService->database->prepare("
                INSERT INTO blocked_ips (ip_address, reason, expires_at, created_at, is_active)
                VALUES (?, 'Automatic block - intrusion detected', ?, NOW(), 1)
                ON DUPLICATE KEY UPDATE 
                expires_at = VALUES(expires_at),
                updated_at = NOW()
            ");
            
            $stmt->execute([$ipAddress, $expiresAt]);
            
            $this->logger->info('IP address blocked', [
                'ip_address' => $ipAddress,
                'expires_at' => $expiresAt,
                'reason' => 'intrusion_detection'
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to block IP', [
                'ip_address' => $ipAddress,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Validate method for specific endpoint
     */
    private function validateMethodForEndpoint(string $method, string $uri): void
    {
        $restrictions = [
            '/api/login' => ['POST'],
            '/api/register' => ['POST'],
            '/api/logout' => ['POST'],
            '/admin/' => ['GET', 'POST']
        ];
        
        foreach ($restrictions as $pattern => $allowedMethods) {
            if (strpos($uri, $pattern) === 0) {
                if (!in_array($method, $allowedMethods)) {
                    throw new Exception("Method {$method} not allowed for endpoint {$uri}");
                }
                break;
            }
        }
    }
    
    /**
     * Check if referrer is valid
     */
    private function isValidReferrer(string $referrer, string $expectedHost): bool
    {
        $referrerHost = parse_url($referrer, PHP_URL_HOST);
        return $referrerHost === $expectedHost;
    }
    
    /**
     * Sanitize data for logging
     */
    private function sanitizeLogData(array $data): array
    {
        $sanitized = $data;
        
        // Remove sensitive fields
        $sensitiveFields = ['password', 'token', 'secret', 'key', 'hash'];
        
        array_walk_recursive($sanitized, function(&$value, $key) use ($sensitiveFields) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                $value = '[REDACTED]';
            }
        });
        
        return $sanitized;
    }
}
