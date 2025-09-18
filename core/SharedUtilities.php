<?php
/**
 * Shared Utilities Library
 * 
 * Consolidated common functions and utilities used across the RFID Check-in System.
 * This eliminates code duplication and provides a centralized location for shared functionality.
 * 
 * @package    RFID Check-in System
 * @subpackage Core Utilities
 * @version    1.0.0
 * @author     Senior Developer Team
 */

// Ensure config is loaded
if (!defined('CONFIG_LOADED')) {
    require_once __DIR__ . '/config.php';
}

/**
 * Shared Security Manager
 * Consolidates all security-related functions used across the application
 */
class SharedSecurityManager {
    private static $instance = null;
    private $csrfTokens = [];
    
    public static function getInstance(): SharedSecurityManager {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken(): string {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_time'] = time();
        return $token;
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCSRFToken($token): bool {
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_time'])) {
            return false;
        }
        
        // Check token age (expire after 1 hour)
        if (time() - $_SESSION['csrf_time'] > 3600) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_time']);
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Unified input validation
     */
    public function validateInput($data, array $rules): array {
        $result = ['valid' => true, 'errors' => [], 'data' => []];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            // Required validation
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $result['errors'][] = ucfirst($field) . ' is required';
                $result['valid'] = false;
                continue;
            }
            
            // Skip further validation if field is empty and not required
            if (empty($value) && (!isset($rule['required']) || !$rule['required'])) {
                $result['data'][$field] = $value;
                continue;
            }
            
            // Type validation
            switch ($rule['type'] ?? 'string') {
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $result['errors'][] = ucfirst($field) . ' must be a valid email address';
                        $result['valid'] = false;
                    }
                    break;
                    
                case 'int':
                case 'integer':
                    if (!filter_var($value, FILTER_VALIDATE_INT)) {
                        $result['errors'][] = ucfirst($field) . ' must be a valid integer';
                        $result['valid'] = false;
                    } else {
                        $value = (int)$value;
                    }
                    break;
                    
                case 'float':
                    if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                        $result['errors'][] = ucfirst($field) . ' must be a valid number';
                        $result['valid'] = false;
                    } else {
                        $value = (float)$value;
                    }
                    break;
                    
                case 'url':
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        $result['errors'][] = ucfirst($field) . ' must be a valid URL';
                        $result['valid'] = false;
                    }
                    break;
                    
                case 'select':
                    if (isset($rule['options']) && !in_array($value, $rule['options'])) {
                        $result['errors'][] = ucfirst($field) . ' contains an invalid option';
                        $result['valid'] = false;
                    }
                    break;
            }
            
            // Length validation
            if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                $result['errors'][] = ucfirst($field) . ' must be at least ' . $rule['min_length'] . ' characters';
                $result['valid'] = false;
            }
            
            if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                $result['errors'][] = ucfirst($field) . ' must not exceed ' . $rule['max_length'] . ' characters';
                $result['valid'] = false;
            }
            
            // Pattern validation
            if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                $message = $rule['message'] ?? ucfirst($field) . ' format is invalid';
                $result['errors'][] = $message;
                $result['valid'] = false;
            }
            
            // Custom validation
            if (isset($rule['custom']) && is_callable($rule['custom'])) {
                $customResult = $rule['custom']($value);
                if ($customResult !== true) {
                    $result['errors'][] = $customResult;
                    $result['valid'] = false;
                }
            }
            
            $result['data'][$field] = $this->sanitizeInput($value);
        }
        
        return $result;
    }
    
    /**
     * Sanitize input data
     */
    public function sanitizeInput($value): string {
        if (is_array($value)) {
            return array_map([$this, 'sanitizeInput'], $value);
        }
        
        // Remove null bytes
        $value = str_replace(chr(0), '', $value);
        
        // Trim whitespace
        $value = trim($value);
        
        // Basic XSS protection
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $value;
    }
    
    /**
     * Check password strength
     */
    public function checkPasswordStrength($password): array {
        $score = 0;
        $feedback = [];
        
        $checks = [
            'length' => strlen($password) >= 8,
            'uppercase' => preg_match('/[A-Z]/', $password),
            'lowercase' => preg_match('/[a-z]/', $password),
            'number' => preg_match('/\d/', $password),
            'special' => preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)
        ];
        
        foreach ($checks as $check => $passed) {
            if ($passed) {
                $score++;
            } else {
                switch ($check) {
                    case 'length':
                        $feedback[] = 'Password must be at least 8 characters long';
                        break;
                    case 'uppercase':
                        $feedback[] = 'Password must contain at least one uppercase letter';
                        break;
                    case 'lowercase':
                        $feedback[] = 'Password must contain at least one lowercase letter';
                        break;
                    case 'number':
                        $feedback[] = 'Password must contain at least one number';
                        break;
                    case 'special':
                        $feedback[] = 'Password must contain at least one special character';
                        break;
                }
            }
        }
        
        $strength = 'weak';
        if ($score >= 4) {
            $strength = 'strong';
        } elseif ($score >= 3) {
            $strength = 'medium';
        }
        
        return [
            'score' => $score,
            'strength' => $strength,
            'feedback' => $feedback,
            'checks' => $checks
        ];
    }
    
    /**
     * Hash password securely
     */
    public function hashPassword($password): string {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3          // 3 threads
        ]);
    }
    
    /**
     * Verify password
     */
    public function verifyPassword($password, $hash): bool {
        return password_verify($password, $hash);
    }
}

/**
 * Shared Performance Manager
 * Consolidates performance monitoring and optimization functions
 */
class SharedPerformanceManager {
    private static $instance = null;
    private $timers = [];
    private $metrics = [];
    private $memorySnapshots = [];
    
    public static function getInstance(): SharedPerformanceManager {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Start performance timer
     */
    public function startTimer(string $operation): void {
        $this->timers[$operation] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true)
        ];
    }
    
    /**
     * End performance timer
     */
    public function endTimer(string $operation): array {
        if (!isset($this->timers[$operation])) {
            return ['error' => 'Timer not started'];
        }
        
        $timer = $this->timers[$operation];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $metrics = [
            'operation' => $operation,
            'duration_ms' => round(($endTime - $timer['start']) * 1000, 2),
            'memory_used' => $endMemory - $timer['memory_start'],
            'memory_peak' => memory_get_peak_usage(true),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $this->metrics[$operation] = $metrics;
        unset($this->timers[$operation]);
        
        return $metrics;
    }
    
    /**
     * Get all performance metrics
     */
    public function getMetrics(): array {
        return $this->metrics;
    }
    
    /**
     * Clear metrics
     */
    public function clearMetrics(): void {
        $this->metrics = [];
        $this->timers = [];
    }
    
    /**
     * Get memory usage information
     */
    public function getMemoryInfo(): array {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit'),
            'formatted' => [
                'current' => $this->formatBytes(memory_get_usage(true)),
                'peak' => $this->formatBytes(memory_get_peak_usage(true))
            ]
        ];
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

/**
 * Shared Database Utilities
 * Consolidates common database operations
 */
class SharedDatabaseUtilities {
    private static $instance = null;
    private $queryCache = [];
    private $performanceManager;
    
    public static function getInstance(): SharedDatabaseUtilities {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->performanceManager = SharedPerformanceManager::getInstance();
    }
    
    /**
     * Execute optimized query with performance monitoring
     */
    public function executeOptimizedQuery(string $sql, array $params = []): array {
        $this->performanceManager->startTimer('db_query');
        
        try {
            $db = getDB();
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->performanceManager->endTimer('db_query');
            return $result;
            
        } catch (Exception $e) {
            $this->performanceManager->endTimer('db_query');
            logMessage('ERROR', 'Database query failed', [
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Execute transaction with automatic rollback
     */
    public function executeTransaction(callable $callback): mixed {
        $db = getDB();
        
        try {
            $db->beginTransaction();
            $result = $callback($db);
            $db->commit();
            return $result;
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Cache query results
     */
    public function getCachedQuery(string $key, callable $queryCallback, int $ttl = 300): array {
        if (isset($this->queryCache[$key])) {
            $cached = $this->queryCache[$key];
            if (time() - $cached['timestamp'] < $ttl) {
                return $cached['data'];
            }
        }
        
        $data = $queryCallback();
        $this->queryCache[$key] = [
            'data' => $data,
            'timestamp' => time()
        ];
        
        return $data;
    }
    
    /**
     * Clear query cache
     */
    public function clearCache(): void {
        $this->queryCache = [];
    }
}

/**
 * Shared Validation Utilities
 * Common validation functions used across the application
 */
class SharedValidationUtilities {
    /**
     * Validate RFID tag format
     */
    public static function validateRFIDTag($tag): bool {
        return preg_match('/^[A-F0-9]{8,14}$/i', $tag);
    }
    
    /**
     * Validate date format
     */
    public static function validateDate($date, $format = 'Y-m-d'): bool {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    /**
     * Validate time format
     */
    public static function validateTime($time): bool {
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $time);
    }
    
    /**
     * Validate phone number
     */
    public static function validatePhone($phone): bool {
        return preg_match('/^[\+]?[1-9][\d]{0,15}$/', preg_replace('/[^\d+]/', '', $phone));
    }
    
    /**
     * Sanitize HTML while preserving safe tags
     */
    public static function sanitizeHTML($html, array $allowedTags = []): string {
        if (empty($allowedTags)) {
            return strip_tags($html);
        }
        
        return strip_tags($html, '<' . implode('><', $allowedTags) . '>');
    }
}

/**
 * Shared Response Utilities
 * Standardized response formats for API and AJAX endpoints
 */
class SharedResponseUtilities {
    /**
     * Send JSON response
     */
    public static function sendJSON(array $data, int $httpCode = 200): void {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Send success response
     */
    public static function sendSuccess($data = null, string $message = 'Success'): void {
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => date('c')
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        self::sendJSON($response);
    }
    
    /**
     * Send error response
     */
    public static function sendError(string $message = 'An error occurred', int $code = 400, $details = null): void {
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code,
                'timestamp' => date('c')
            ]
        ];
        
        if ($details !== null) {
            $response['error']['details'] = $details;
        }
        
        self::sendJSON($response, $code);
    }
    
    /**
     * Send validation error response
     */
    public static function sendValidationError(array $errors): void {
        self::sendError('Validation failed', 422, ['validation_errors' => $errors]);
    }
}
