<?php
/**
 * Common Utilities and Helper Functions
 * Consolidated helper functions used across the application
 */

class Utilities {
    
    /**
     * Sanitize and validate input data
     */
    public static function sanitizeInput($input, $type = 'string') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return self::sanitizeInput($item, $type);
            }, $input);
        }
        
        $input = trim($input);
        
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            case 'int':
                return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return (float) filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            case 'string':
            default:
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Validate email address
     */
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Generate secure random token
     */
    public static function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Format date for display
     */
    public static function formatDate($date, $format = 'Y-m-d H:i:s') {
        if (empty($date)) return '';
        
        try {
            $dateTime = new DateTime($date);
            return $dateTime->format($format);
        } catch (Exception $e) {
            error_log('Date formatting error: ' . $e->getMessage());
            return $date;
        }
    }
    
    /**
     * Format time ago (e.g., "2 hours ago")
     */
    public static function timeAgo($datetime) {
        if (empty($datetime)) return '';
        
        try {
            $time = time() - strtotime($datetime);
            
            if ($time < 60) return 'just now';
            if ($time < 3600) return floor($time/60) . ' minutes ago';
            if ($time < 86400) return floor($time/3600) . ' hours ago';
            if ($time < 2592000) return floor($time/86400) . ' days ago';
            
            return self::formatDate($datetime, 'M j, Y');
        } catch (Exception $e) {
            return $datetime;
        }
    }
    
    /**
     * Log activity with consistent formatting
     */
    public static function logActivity($userId, $action, $details = '') {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO ActivityLog (user_id, action, details, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $userId,
                $action,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
        } catch (Exception $e) {
            error_log('Activity logging error: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate pagination HTML
     */
    public static function generatePagination($currentPage, $totalPages, $baseUrl) {
        if ($totalPages <= 1) return '';
        
        $html = '<div class="pagination">';
        
        // Previous button
        if ($currentPage > 1) {
            $prevPage = $currentPage - 1;
            $html .= "<a href=\"{$baseUrl}?page={$prevPage}\" class=\"pagination-btn\">← Previous</a>";
        }
        
        // Page numbers
        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);
        
        for ($i = $start; $i <= $end; $i++) {
            $active = ($i == $currentPage) ? ' active' : '';
            $html .= "<a href=\"{$baseUrl}?page={$i}\" class=\"pagination-btn{$active}\">{$i}</a>";
        }
        
        // Next button
        if ($currentPage < $totalPages) {
            $nextPage = $currentPage + 1;
            $html .= "<a href=\"{$baseUrl}?page={$nextPage}\" class=\"pagination-btn\">Next →</a>";
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Validate and process file upload
     */
    public static function handleFileUpload($fileField, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'], $maxSize = 2097152) {
        if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'No file uploaded or upload error'];
        }
        
        $file = $_FILES[$fileField];
        $fileSize = $file['size'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Check file size
        if ($fileSize > $maxSize) {
            return ['success' => false, 'error' => 'File size too large (max ' . number_format($maxSize/1024/1024, 1) . 'MB)'];
        }
        
        // Check file type
        if (!in_array($fileExt, $allowedTypes)) {
            return ['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes)];
        }
        
        // Generate unique filename
        $newFileName = self::generateSecureToken(16) . '.' . $fileExt;
        
        return [
            'success' => true,
            'originalName' => $fileName,
            'newName' => $newFileName,
            'extension' => $fileExt,
            'size' => $fileSize,
            'tmpName' => $fileTmpName
        ];
    }
    
    /**
     * Send JSON response
     */
    public static function jsonResponse($data, $httpCode = 200) {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
    
    /**
     * Redirect with message
     */
    public static function redirect($url, $message = '', $type = 'success') {
        if (!empty($message)) {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query([$type => $message]);
        }
        header('Location: ' . $url);
        exit();
    }
    
    /**
     * Get user's real IP address
     */
    public static function getRealIP() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
    }
    
    /**
     * Check if request is AJAX
     */
    public static function isAjax() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::generateSecureToken(32);
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

/**
 * Global helper functions
 */

/**
 * Get database instance (backwards compatibility)
 */
function getDB() {
    return Database::getInstance();
}

/**
 * Escape HTML output
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Get asset URL with versioning
 */
function asset($path) {
    $fullPath = __DIR__ . '/../assets/' . $path;
    $timestamp = file_exists($fullPath) ? filemtime($fullPath) : time();
    return BASE_URL . '/assets/' . $path . '?v=' . $timestamp;
}

/**
 * Debug helper
 */
function dd($data) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo '<pre>';
        var_dump($data);
        echo '</pre>';
        die();
    }
}
