<?php
/**
 * Utility Functions and Helper Classes
 * Common functionality used throughout the application
 * 
 * @author Senior Developer
 * @version 2.0 - Comprehensive utility collection
 */

class Utilities {
    
    /**
     * Log user activity to database
     * 
     * @param int $userId User ID
     * @param string $action Action performed
     * @param string $details Action details
     * @param array $metadata Additional metadata
     */
    public static function logActivity($userId, $action, $details, $metadata = []) {
        try {
            $db = getDB();
            
            $stmt = $db->prepare("
                INSERT INTO activitylog (user_id, action, details, ip_address, user_agent, metadata, timestamp)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $userId,
                $action,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                !empty($metadata) ? json_encode($metadata) : null
            ]);
            
            logMessage('DEBUG', 'Activity logged', [
                'user_id' => $userId,
                'action' => $action
            ]);
            
        } catch (Exception $e) {
            logMessage('ERROR', 'Failed to log activity', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'action' => $action
            ]);
        }
    }
    
    /**
     * Validate email address format
     * 
     * @param string $email Email to validate
     * @return bool True if valid
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate RFID tag format
     * 
     * @param string $rfid RFID tag to validate
     * @return bool True if valid
     */
    public static function validateRFID($rfid) {
        if (empty($rfid)) {
            return true; // RFID is optional
        }
        
        $length = strlen($rfid);
        return $length >= RFID_TAG_MIN_LENGTH && 
               $length <= RFID_TAG_MAX_LENGTH && 
               preg_match(REGEX_RFID, $rfid);
    }
    
    /**
     * Validate username format
     * 
     * @param string $username Username to validate
     * @return bool True if valid
     */
    public static function validateUsername($username) {
        return preg_match(REGEX_USERNAME, $username);
    }
    
    /**
     * Validate name format (first/last name)
     * 
     * @param string $name Name to validate
     * @return bool True if valid
     */
    public static function validateName($name) {
        return preg_match(REGEX_NAME, $name);
    }
    
    /**
     * Validate phone number format
     * 
     * @param string $phone Phone number to validate
     * @return bool True if valid
     */
    public static function validatePhone($phone) {
        if (empty($phone)) {
            return true; // Phone is optional
        }
        
        return preg_match(REGEX_PHONE, $phone);
    }
    
    /**
     * Sanitize input for database storage
     * 
     * @param mixed $input Input to sanitize
     * @return mixed Sanitized input
     */
    public static function sanitizeInput($input) {
        if (is_string($input)) {
            return trim($input);
        }
        
        return $input;
    }
    
    /**
     * Format date for display
     * 
     * @param string $date Date string
     * @param string $format Output format
     * @return string Formatted date
     */
    public static function formatDate($date, $format = 'M j, Y') {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return 'N/A';
        }
        
        try {
            return date($format, strtotime($date));
        } catch (Exception $e) {
            return 'Invalid Date';
        }
    }
    
    /**
     * Format datetime for display
     * 
     * @param string $datetime Datetime string
     * @param string $format Output format
     * @return string Formatted datetime
     */
    public static function formatDateTime($datetime, $format = 'M j, Y g:i A') {
        return self::formatDate($datetime, $format);
    }
    
    /**
     * Calculate time difference in human readable format
     * 
     * @param string $datetime1 Start datetime
     * @param string $datetime2 End datetime (default: now)
     * @return string Human readable time difference
     */
    public static function timeDifference($datetime1, $datetime2 = null) {
        if (empty($datetime1)) {
            return 'N/A';
        }
        
        $time1 = strtotime($datetime1);
        $time2 = $datetime2 ? strtotime($datetime2) : time();
        
        $diff = abs($time2 - $time1);
        
        if ($diff < 60) {
            return $diff . ' second' . ($diff !== 1 ? 's' : '');
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes !== 1 ? 's' : '');
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours !== 1 ? 's' : '');
        } else {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days !== 1 ? 's' : '');
        }
    }
    
    /**
     * Generate secure random password
     * 
     * @param int $length Password length
     * @param bool $includeSymbols Include symbols
     * @return string Generated password
     */
    public static function generatePassword($length = 12, $includeSymbols = true) {
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        
        $chars = $lowercase . $uppercase . $numbers;
        if ($includeSymbols) {
            $chars .= $symbols;
        }
        
        $password = '';
        $charsLength = strlen($chars);
        
        // Ensure at least one character from each set
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        
        if ($includeSymbols) {
            $password .= $symbols[random_int(0, strlen($symbols) - 1)];
        }
        
        // Fill the rest randomly
        for ($i = strlen($password); $i < $length; $i++) {
            $password .= $chars[random_int(0, $charsLength - 1)];
        }
        
        // Shuffle the password
        return str_shuffle($password);
    }
    
    /**
     * Send email notification
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param string $fromEmail Sender email (optional)
     * @param string $fromName Sender name (optional)
     * @return bool True if sent successfully
     */
    public static function sendEmail($to, $subject, $body, $fromEmail = null, $fromName = null) {
        if (!FEATURE_EMAIL_NOTIFICATIONS) {
            logMessage('DEBUG', 'Email notifications disabled');
            return false;
        }
        
        try {
            $fromEmail = $fromEmail ?: FROM_EMAIL;
            $fromName = $fromName ?: FROM_NAME;
            
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                "From: $fromName <$fromEmail>",
                "Reply-To: $fromEmail",
                'X-Mailer: ' . APP_NAME
            ];
            
            $success = mail($to, $subject, $body, implode("\r\n", $headers));
            
            if ($success) {
                logMessage('DEBUG', 'Email sent successfully', [
                    'to' => $to,
                    'subject' => $subject
                ]);
            } else {
                logMessage('ERROR', 'Failed to send email', [
                    'to' => $to,
                    'subject' => $subject
                ]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            logMessage('ERROR', 'Email sending error', [
                'error' => $e->getMessage(),
                'to' => $to
            ]);
            return false;
        }
    }
    
    /**
     * Create email template for password reset
     * 
     * @param string $userName User name
     * @param string $resetUrl Reset URL
     * @return string HTML email template
     */
    public static function getPasswordResetEmailTemplate($userName, $resetUrl) {
        $appName = APP_NAME;
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #3498db; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .button { display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>$appName</h1>
                </div>
                <div class='content'>
                    <h2>Password Reset Request</h2>
                    <p>Hello $userName,</p>
                    <p>We received a request to reset your password. Click the button below to create a new password:</p>
                    <p><a href='$resetUrl' class='button'>Reset Password</a></p>
                    <p>If you didn't request this password reset, please ignore this email.</p>
                    <p>This link will expire in 1 hour for security reasons.</p>
                </div>
                <div class='footer'>
                    <p>This email was sent by $appName<br>
                    If you need help, please contact support.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Upload file with validation
     * 
     * @param array $file $_FILES array element
     * @param string $uploadDir Upload directory
     * @param string $prefix Filename prefix
     * @return array Result with success status and filename
     */
    public static function uploadFile($file, $uploadDir = null, $prefix = '') {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'No file uploaded or upload error'];
        }
        
        $uploadDir = $uploadDir ?: UPLOADS_DIR;
        
        // Validate file size
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            return ['success' => false, 'error' => 'File too large'];
        }
        
        // Validate file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, UPLOAD_ALLOWED_TYPES)) {
            return ['success' => false, 'error' => 'File type not allowed'];
        }
        
        // Generate unique filename
        $filename = $prefix . uniqid() . '.' . $extension;
        $filepath = $uploadDir . '/' . $filename;
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
        } else {
            return ['success' => false, 'error' => 'Failed to save file'];
        }
    }
    
    /**
     * Get user's gravatar URL
     * 
     * @param string $email User email
     * @param int $size Image size
     * @param string $default Default image
     * @return string Gravatar URL
     */
    public static function getGravatarUrl($email, $size = 80, $default = 'identicon') {
        $hash = md5(strtolower(trim($email)));
        return "https://www.gravatar.com/avatar/$hash?s=$size&d=$default&r=pg";
    }
    
    /**
     * Create pagination data
     * 
     * @param int $currentPage Current page number
     * @param int $totalItems Total number of items
     * @param int $itemsPerPage Items per page
     * @return array Pagination data
     */
    public static function createPagination($currentPage, $totalItems, $itemsPerPage = null) {
        $itemsPerPage = $itemsPerPage ?: PAGINATION_DEFAULT_LIMIT;
        $totalPages = ceil($totalItems / $itemsPerPage);
        
        return [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'total_items' => $totalItems,
            'items_per_page' => $itemsPerPage,
            'has_previous' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages,
            'offset' => ($currentPage - 1) * $itemsPerPage
        ];
    }
    
    /**
     * Generate API response in JSON format
     * 
     * @param bool $success Success status
     * @param mixed $data Response data
     * @param string $message Response message
     * @param int $code HTTP status code
     * @return string JSON response
     */
    public static function jsonResponse($success, $data = null, $message = '', $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        
        $response = ['success' => $success];
        
        if ($message) {
            $response['message'] = $message;
        }
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if (!$success && !$message) {
            $response['message'] = 'An error occurred';
        }
        
        return json_encode($response, JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Check if current user can access admin features
     * 
     * @return bool True if user is admin
     */
    public static function isAdmin() {
        $user = Auth::getCurrentUser();
        return $user && $user['role'] === 'admin';
    }
    
    /**
     * Check if current user can access moderator features
     * 
     * @return bool True if user is moderator or admin
     */
    public static function isModerator() {
        $user = Auth::getCurrentUser();
        return $user && in_array($user['role'], ['admin', 'moderator']);
    }
    
    /**
     * Get system status information
     * 
     * @return array System status data
     */
    public static function getSystemStatus() {
        try {
            $db = getDB();
            
            return [
                'database' => 'connected',
                'version' => APP_VERSION,
                'php_version' => PHP_VERSION,
                'debug_mode' => DEBUG_MODE,
                'maintenance_mode' => MAINTENANCE_MODE,
                'features' => [
                    'email_notifications' => FEATURE_EMAIL_NOTIFICATIONS,
                    'qr_codes' => FEATURE_QR_CODES,
                    'analytics' => FEATURE_ANALYTICS,
                    'reporting' => FEATURE_REPORTING
                ],
                'last_check' => date(DATETIME_FORMAT)
            ];
            
        } catch (Exception $e) {
            return [
                'database' => 'error',
                'error' => $e->getMessage(),
                'last_check' => date(DATETIME_FORMAT)
            ];
        }
    }
}

/**
 * Quick access functions for common operations
 */

function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}

function redirect($url, $permanent = false) {
    $code = $permanent ? 301 : 302;
    http_response_code($code);
    header("Location: $url");
    exit;
}

function currentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

function isPost() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function isAjax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}
