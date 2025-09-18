<?php
/**
 * Security Middleware - Request protection and validation
 * 
 * Provides comprehensive request filtering and security validation
 * to be used across all application entry points
 * 
 * @author Senior Developer
 * @version 1.0.0
 */

require_once __DIR__ . '/SecurityManager.php';
require_once __DIR__ . '/ErrorHandler.php';

class SecurityMiddleware {
    private $security;
    private $errorHandler;
    private $config;
    
    public function __construct() {
        $this->security = SecurityManager::getInstance();
        $this->errorHandler = ErrorHandler::getInstance();
        $this->config = $this->loadSecurityConfig();
    }
    
    /**
     * Load security configuration
     */
    private function loadSecurityConfig(): array {
        return [
            'csrf_protected_methods' => ['POST', 'PUT', 'DELETE', 'PATCH'],
            'rate_limited_endpoints' => [
                '/auth/login-process.php' => 'login',
                '/api/' => 'api'
            ],
            'input_validation_rules' => [
                'email' => [
                    'required' => true,
                    'email' => true,
                    'max_length' => 255
                ],
                'password' => [
                    'required' => true,
                    'min_length' => 8,
                    'max_length' => 255
                ],
                'name' => [
                    'required' => true,
                    'min_length' => 2,
                    'max_length' => 100,
                    'regex' => '/^[a-zA-Z\s\-\'\.]+$/'
                ],
                'username' => [
                    'required' => true,
                    'min_length' => 3,
                    'max_length' => 50,
                    'regex' => '/^[a-zA-Z0-9_-]+$/'
                ],
                'rfid' => [
                    'required' => true,
                    'regex' => '/^[a-fA-F0-9]+$/',
                    'min_length' => 8,
                    'max_length' => 32
                ]
            ]
        ];
    }
    
    /**
     * Process incoming request with security checks
     */
    public function handleRequest(): bool {
        try {
            // 1. Validate request origin for AJAX/form requests
            if ($this->isAjaxOrFormRequest() && !$this->security->validateRequestOrigin()) {
                $this->handleSecurityViolation('Invalid request origin');
                return false;
            }
            
            // 2. Check rate limiting
            if (!$this->checkRateLimit()) {
                $this->handleRateLimit();
                return false;
            }
            
            // 3. Validate CSRF token for protected methods
            if ($this->requiresCSRFProtection() && !$this->validateCSRFToken()) {
                $this->handleCSRFViolation();
                return false;
            }
            
            // 4. Sanitize and validate input data
            $this->sanitizeInputData();
            
            // 5. Additional security checks
            $this->performAdditionalSecurityChecks();
            
            return true;
            
        } catch (Exception $e) {
            $this->errorHandler->logError('Security middleware error', $e);
            $this->handleSecurityError();
            return false;
        }
    }
    
    /**
     * Check if request is AJAX or form submission
     */
    private function isAjaxOrFormRequest(): bool {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
               $_SERVER['REQUEST_METHOD'] !== 'GET' ||
               !empty($_POST) || !empty($_FILES);
    }
    
    /**
     * Check rate limiting for current request
     */
    private function checkRateLimit(): bool {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        foreach ($this->config['rate_limited_endpoints'] as $endpoint => $action) {
            if (strpos($requestUri, $endpoint) === 0) {
                return $this->security->checkRateLimit($action);
            }
        }
        
        return true;
    }
    
    /**
     * Check if current request requires CSRF protection
     */
    private function requiresCSRFProtection(): bool {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        return in_array($method, $this->config['csrf_protected_methods']);
    }
    
    /**
     * Validate CSRF token
     */
    private function validateCSRFToken(): bool {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? 
                $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        
        if (empty($token)) {
            return false;
        }
        
        $form = $_POST['csrf_form'] ?? 'default';
        return $this->security->validateCSRFToken($token, $form);
    }
    
    /**
     * Sanitize input data
     */
    private function sanitizeInputData(): void {
        if (!empty($_POST)) {
            foreach ($_POST as $key => $value) {
                $_POST[$key] = $this->security->sanitizeInput($value);
            }
        }
        
        if (!empty($_GET)) {
            foreach ($_GET as $key => $value) {
                $_GET[$key] = $this->security->sanitizeInput($value);
            }
        }
    }
    
    /**
     * Perform additional security checks
     */
    private function performAdditionalSecurityChecks(): void {
        // Check for common attack patterns in request
        $this->checkForMaliciousPatterns();
        
        // Validate file uploads if present
        if (!empty($_FILES)) {
            $this->validateFileUploads();
        }
        
        // Check request size
        $this->validateRequestSize();
        
        // Validate user agent
        $this->validateUserAgent();
    }
    
    /**
     * Check for malicious patterns in request
     */
    private function checkForMaliciousPatterns(): void {
        $maliciousPatterns = [
            '/(<|%3C)script/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/\bunion\b.*\bselect\b/i',
            '/\bselect\b.*\bfrom\b/i',
            '/\binsert\b.*\binto\b/i',
            '/\bdelete\b.*\bfrom\b/i',
            '/\bdrop\b.*\btable\b/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i'
        ];
        
        $requestData = array_merge($_GET, $_POST);
        $requestString = serialize($requestData);
        
        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $requestString)) {
                $this->handleSecurityViolation('Malicious pattern detected');
                break;
            }
        }
    }
    
    /**
     * Validate file uploads
     */
    private function validateFileUploads(): void {
        foreach ($_FILES as $fileKey => $file) {
            if (is_array($file['name'])) {
                // Handle multiple file uploads
                for ($i = 0; $i < count($file['name']); $i++) {
                    $singleFile = [
                        'name' => $file['name'][$i],
                        'type' => $file['type'][$i],
                        'tmp_name' => $file['tmp_name'][$i],
                        'error' => $file['error'][$i],
                        'size' => $file['size'][$i]
                    ];
                    
                    $errors = $this->security->validateFileUpload($singleFile);
                    if (!empty($errors)) {
                        $this->handleFileUploadError($errors);
                    }
                }
            } else {
                // Handle single file upload
                $errors = $this->security->validateFileUpload($file);
                if (!empty($errors)) {
                    $this->handleFileUploadError($errors);
                }
            }
        }
    }
    
    /**
     * Validate request size
     */
    private function validateRequestSize(): void {
        $maxSize = 10 * 1024 * 1024; // 10MB
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
        
        if ($contentLength > $maxSize) {
            $this->handleSecurityViolation('Request too large');
        }
    }
    
    /**
     * Validate user agent
     */
    private function validateUserAgent(): void {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Block empty user agents (potential bots)
        if (empty($userAgent)) {
            $this->handleSecurityViolation('Empty user agent');
            return;
        }
        
        // Block known malicious user agents
        $blockedPatterns = [
            '/sqlmap/i',
            '/nikto/i',
            '/nessus/i',
            '/nmap/i',
            '/masscan/i',
            '/zgrab/i'
        ];
        
        foreach ($blockedPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                $this->handleSecurityViolation('Blocked user agent: ' . $userAgent);
                break;
            }
        }
    }
    
    /**
     * Validate specific input fields
     */
    public function validateFields(array $data, array $fieldTypes): array {
        $errors = [];
        
        foreach ($fieldTypes as $field => $type) {
            if (isset($data[$field]) && isset($this->config['input_validation_rules'][$type])) {
                $fieldErrors = $this->security->validateInput(
                    [$field => $data[$field]], 
                    [$field => $this->config['input_validation_rules'][$type]]
                );
                
                if (!empty($fieldErrors)) {
                    $errors = array_merge($errors, $fieldErrors);
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Handle security violation
     */
    private function handleSecurityViolation(string $message): void {
        $this->errorHandler->logError('Security violation: ' . $message, [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'post_data' => $_POST,
            'get_data' => $_GET
        ]);
        
        http_response_code(403);
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Security violation detected']);
        } else {
            include __DIR__ . '/../error-pages/403.html';
        }
        exit;
    }
    
    /**
     * Handle rate limit exceeded
     */
    private function handleRateLimit(): void {
        http_response_code(429);
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Too many requests. Please try again later.']);
        } else {
            include __DIR__ . '/../error-pages/429.html';
        }
        exit;
    }
    
    /**
     * Handle CSRF violation
     */
    private function handleCSRFViolation(): void {
        $this->errorHandler->logError('CSRF token validation failed', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown'
        ]);
        
        http_response_code(403);
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid security token. Please refresh and try again.']);
        } else {
            include __DIR__ . '/../error-pages/csrf.html';
        }
        exit;
    }
    
    /**
     * Handle file upload errors
     */
    private function handleFileUploadError(array $errors): void {
        http_response_code(400);
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'File upload error', 'details' => $errors]);
        } else {
            $_SESSION['upload_errors'] = $errors;
            header('Location: ' . $_SERVER['HTTP_REFERER']);
        }
        exit;
    }
    
    /**
     * Handle general security errors
     */
    private function handleSecurityError(): void {
        http_response_code(500);
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Security system error']);
        } else {
            include __DIR__ . '/../error-pages/500.html';
        }
        exit;
    }
    
    /**
     * Check if request is AJAX
     */
    private function isAjaxRequest(): bool {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Get CSRF token for forms
     */
    public function getCSRFToken(string $form = 'default'): string {
        return $this->security->generateCSRFToken($form);
    }
    
    /**
     * Get CSRF hidden input
     */
    public function getCSRFInput(string $form = 'default'): string {
        return $this->security->getCSRFTokenInput($form);
    }
}
