<?php
/**
 * Enterprise Account Settings Management System
 * Comprehensive security, password policies, two-factor authentication framework, notifications, and privacy controls
 * Features: Enhanced security validation, audit logging, accessibility compliance, password strength requirements
 */

require_once '../core/auth.php';
require_once '../core/database.php';
require_once '../core/config.php';

// Use global shared utilities from config.php
global $sharedSecurity, $sharedDatabase, $sharedValidation;
            unset($_SESSION['csrf_tokens'][$token]);
            return false;
        }
        
        unset($_SESSION['csrf_tokens'][$token]);
        return true;
    }
    
    public function validatePasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        
        if (strlen($password) > 128) {
            $errors[] = 'Password must not exceed 128 characters';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if (!preg_match('/\d/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        // Check against common passwords
        $commonPasswords = [
            'password', '123456', '123456789', 'qwerty', 'abc123', 
            'password123', 'admin', 'letmein', 'welcome', 'monkey'
        ];
        
        if (in_array(strtolower($password), $commonPasswords)) {
            $errors[] = 'Password is too common and easily guessable';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'strength' => $this->calculatePasswordStrength($password)
        ];
    }
    
    private function calculatePasswordStrength($password) {
        $score = 0;
        
        // Length bonus
        $score += min(25, strlen($password) * 2);
        
        // Character variety
        if (preg_match('/[a-z]/', $password)) $score += 5;
        if (preg_match('/[A-Z]/', $password)) $score += 5;
        if (preg_match('/\d/', $password)) $score += 10;
        if (preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) $score += 15;
        
        // Pattern penalties
        if (preg_match('/(.)\1{2,}/', $password)) $score -= 10; // Repeated characters
        if (preg_match('/123|abc|qwe/', strtolower($password))) $score -= 10; // Sequential patterns
        
        $score = max(0, min(100, $score));
        
        if ($score < 30) return 'weak';
        if ($score < 60) return 'fair';
        if ($score < 80) return 'good';
        return 'strong';
    }
    
    public function auditLog($action, $details, $userId) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => $action,
            'user_id' => $userId,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        $this->auditLog[] = $logEntry;
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_log (action, user_id, details, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $action,
                $userId,
                json_encode($details),
                $logEntry['ip_address'],
                $logEntry['user_agent']
            ]);
        } catch (PDOException $e) {
            error_log("Audit log error: " . $e->getMessage());
        }
    }
    
    public function validateInput($data, $rules) {
        $errors = [];
        $validated = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            if ($rule['required'] && empty($value)) {
                $errors[$field] = $rule['label'] . ' is required';
                continue;
            }
            
            if (empty($value) && !$rule['required']) {
                $validated[$field] = null;
                continue;
            }
            
            switch ($rule['type']) {
                case 'string':
                    $cleaned = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
                    if (isset($rule['min_length']) && strlen($cleaned) < $rule['min_length']) {
                        $errors[$field] = $rule['label'] . ' must be at least ' . $rule['min_length'] . ' characters';
                    } elseif (isset($rule['max_length']) && strlen($cleaned) > $rule['max_length']) {
                        $errors[$field] = $rule['label'] . ' must not exceed ' . $rule['max_length'] . ' characters';
                    } else {
                        $validated[$field] = $cleaned;
                    }
                    break;
                    
                case 'select':
                    if (!in_array($value, $rule['options'])) {
                        $errors[$field] = 'Invalid ' . $rule['label'] . ' selection';
                    } else {
                        $validated[$field] = $value;
                    }
                    break;
                    
                default:
                    $validated[$field] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            }
        }
        
        return ['valid' => empty($errors), 'errors' => $errors, 'data' => $validated];
    }
}

class PerformanceManager {
    private static $instance = null;
    private $startTime;
    private $cache = [];
    private $metrics = [];
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->startTime = microtime(true);
    }
    
    public function cacheSet($key, $value, $ttl = 300) {
        $this->cache[$key] = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
    }
    
    public function cacheGet($key) {
        if (!isset($this->cache[$key])) {
            return null;
        }
        
        if ($this->cache[$key]['expires'] < time()) {
            unset($this->cache[$key]);
            return null;
        }
        
        return $this->cache[$key]['value'];
    }
    
    public function startTimer($name) {
        $this->metrics[$name] = microtime(true);
    }
    
    public function endTimer($name) {
        if (isset($this->metrics[$name])) {
            $this->metrics[$name] = microtime(true) - $this->metrics[$name];
        }
    }
    
    public function getMetrics() {
        return [
            'total_time' => microtime(true) - $this->startTime,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'timers' => $this->metrics
        ];
    }
}

class NotificationManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function getUserPreferences($userId) {
        try {
            $stmt = $this->db->prepare("SELECT preferences FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['preferences']) {
                return json_decode($result['preferences'], true);
            }
            
            return $this->getDefaultPreferences();
        } catch (PDOException $e) {
            error_log("User preferences fetch error: " . $e->getMessage());
            return $this->getDefaultPreferences();
        }
    }
    
    public function updatePreferences($userId, $preferences) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET preferences = ?, updated_at = NOW() WHERE user_id = ?");
            return $stmt->execute([json_encode($preferences), $userId]);
        } catch (PDOException $e) {
            error_log("Update preferences error: " . $e->getMessage());
            return false;
        }
    }
    
    private function getDefaultPreferences() {
        return [
            'email_notifications' => 1,
            'sms_notifications' => 0,
            'event_reminders' => 1,
            'profile_visibility' => 'members',
            'share_analytics' => 0,
            'security_alerts' => 1,
            'weekly_digest' => 1,
            'marketing_emails' => 0
        ];
    }
}

// Initialize enterprise components
Auth::requireLogin();
$user = Auth::getCurrentUser(true);
$db = getDB();
$security = $sharedSecurity; // Use shared security manager
$performance = PerformanceManager::getInstance();
$notificationManager = new NotificationManager($db);

$performance->startTimer('settings_load');

$message = '';
$error = '';
$csrfToken = $security->generateCSRFToken();

// Handle form submissions with enterprise security
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !$security->validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Security token validation failed. Please try again.';
    } elseif (isset($_POST['action'])) {
        $performance->startTimer('form_processing');
        
        switch ($_POST['action']) {
            case 'change_password':
                $result = changePasswordSecure($db, $security, $user['user_id'], $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                    $security->auditLog('password_change', ['strength' => $result['strength'] ?? 'unknown'], $user['user_id']);
                } else {
                    $error = $result['message'];
                    $security->auditLog('password_change_failed', ['reason' => $result['message']], $user['user_id']);
                }
                break;
                
            case 'update_notifications':
                $result = updateNotificationSettingsSecure($notificationManager, $security, $user['user_id'], $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                    $security->auditLog('notification_settings_update', $result['changes'] ?? [], $user['user_id']);
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'update_privacy':
                $result = updatePrivacySettingsSecure($notificationManager, $security, $user['user_id'], $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                    $security->auditLog('privacy_settings_update', $result['changes'] ?? [], $user['user_id']);
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'enable_2fa':
                $result = enable2FASecure($db, $security, $user['user_id']);
                if ($result['success']) {
                    $message = $result['message'];
                    $security->auditLog('2fa_enable_attempt', [], $user['user_id']);
                } else {
                    $error = $result['message'];
                }
                break;
                
            default:
                $error = 'Invalid action specified';
                $security->auditLog('invalid_action', ['action' => $_POST['action']], $user['user_id']);
        }
        
        $performance->endTimer('form_processing');
    }
}

// Get user settings with caching
$performance->startTimer('settings_query');
$cacheKey = 'user_settings_' . $user['user_id'];
$settings = $performance->cacheGet($cacheKey);

if (!$settings) {
    $settings = $notificationManager->getUserPreferences($user['user_id']);
    $performance->cacheSet($cacheKey, $settings, 300); // Cache for 5 minutes
}
$performance->endTimer('settings_query');

// Get enhanced login history with device detection
$performance->startTimer('login_history_query');
try {
    $stmt = $db->prepare("
        SELECT 
            timestamp as login_time,
            ip_address,
            user_agent,
            CASE WHEN status = 'success' THEN 1 ELSE 0 END as success,
            location,
            device_type
        FROM accesslogs 
        WHERE user_id = ? AND action IN ('login', 'failed_login')
        ORDER BY timestamp DESC 
        LIMIT 15
    ");
    $stmt->execute([$user['user_id']]);
    $login_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Login history fetch error: " . $e->getMessage());
    $login_history = [];
}
$performance->endTimer('login_history_query');

// Get account security metrics
$performance->startTimer('security_metrics_query');
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(CASE WHEN action = 'login' AND status = 'success' THEN 1 END) as successful_logins,
            COUNT(CASE WHEN action = 'failed_login' THEN 1 END) as failed_logins,
            COUNT(CASE WHEN action = 'password_change' THEN 1 END) as password_changes,
            MAX(CASE WHEN action = 'login' AND status = 'success' THEN timestamp END) as last_successful_login,
            COUNT(DISTINCT ip_address) as unique_ips
        FROM accesslogs 
        WHERE user_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$user['user_id']]);
    $security_metrics = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Security metrics fetch error: " . $e->getMessage());
    $security_metrics = [
        'successful_logins' => 0,
        'failed_logins' => 0,
        'password_changes' => 0,
        'last_successful_login' => null,
        'unique_ips' => 0
    ];
}
$performance->endTimer('security_metrics_query');

// Enhanced secure functions
function changePasswordSecure($db, $security, $userId, $data) {
    try {
        $rules = [
            'current_password' => ['type' => 'string', 'required' => true, 'label' => 'Current password'],
            'new_password' => ['type' => 'string', 'required' => true, 'label' => 'New password'],
            'confirm_password' => ['type' => 'string', 'required' => true, 'label' => 'Confirm password']
        ];
        
        $validation = $security->validateInput($data, $rules);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => implode('; ', $validation['errors'])];
        }
        
        $validated = $validation['data'];
        
        // Password confirmation check
        if ($validated['new_password'] !== $validated['confirm_password']) {
            return ['success' => false, 'message' => 'New passwords do not match'];
        }
        
        // Password strength validation
        $strengthCheck = $security->validatePasswordStrength($validated['new_password']);
        if (!$strengthCheck['valid']) {
            return ['success' => false, 'message' => implode('; ', $strengthCheck['errors'])];
        }
        
        // Verify current password
        $stmt = $db->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $stored_hash = $stmt->fetchColumn();
        
        if (!password_verify($validated['current_password'], $stored_hash)) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        // Check if new password is same as current
        if (password_verify($validated['new_password'], $stored_hash)) {
            return ['success' => false, 'message' => 'New password must be different from current password'];
        }
        
        // Update password with transaction
        $db->beginTransaction();
        
        $new_hash = password_hash($validated['new_password'], PASSWORD_ARGON2ID);
        $stmt = $db->prepare("
            UPDATE users 
            SET password = ?, updated_at = NOW(), password_changed_at = NOW() 
            WHERE user_id = ?
        ");
        $success = $stmt->execute([$new_hash, $userId]);
        
        if (!$success) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Failed to update password'];
        }
        
        $db->commit();
        
        return [
            'success' => true, 
            'message' => 'Password changed successfully! Please log in again with your new password.',
            'strength' => $strengthCheck['strength']
        ];
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Change password error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to change password. Please try again.'];
    }
}

function updateNotificationSettingsSecure($notificationManager, $security, $userId, $data) {
    try {
        $currentSettings = $notificationManager->getUserPreferences($userId);
        $changes = [];
        
        // Define allowed notification settings
        $allowedSettings = [
            'email_notifications', 'sms_notifications', 'event_reminders',
            'security_alerts', 'weekly_digest', 'marketing_emails'
        ];
        
        foreach ($allowedSettings as $setting) {
            $newValue = isset($data[$setting]) ? 1 : 0;
            if (!isset($currentSettings[$setting]) || $currentSettings[$setting] != $newValue) {
                $changes[$setting] = ['from' => $currentSettings[$setting] ?? 0, 'to' => $newValue];
            }
            $currentSettings[$setting] = $newValue;
        }
        
        if (empty($changes)) {
            return ['success' => true, 'message' => 'No changes were made to notification settings'];
        }
        
        $success = $notificationManager->updatePreferences($userId, $currentSettings);
        
        if ($success) {
            return [
                'success' => true, 
                'message' => 'Notification settings updated successfully!',
                'changes' => $changes
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to update notification settings'];
        }
        
    } catch (Exception $e) {
        error_log("Update notifications error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update notification settings. Please try again.'];
    }
}

function updatePrivacySettingsSecure($notificationManager, $security, $userId, $data) {
    try {
        $rules = [
            'profile_visibility' => [
                'type' => 'select', 
                'required' => true, 
                'options' => ['public', 'members', 'private'], 
                'label' => 'Profile visibility'
            ]
        ];
        
        $validation = $security->validateInput($data, $rules);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => implode('; ', $validation['errors'])];
        }
        
        $currentSettings = $notificationManager->getUserPreferences($userId);
        $changes = [];
        
        // Update profile visibility
        $newVisibility = $validation['data']['profile_visibility'];
        if ($currentSettings['profile_visibility'] !== $newVisibility) {
            $changes['profile_visibility'] = [
                'from' => $currentSettings['profile_visibility'],
                'to' => $newVisibility
            ];
        }
        $currentSettings['profile_visibility'] = $newVisibility;
        
        // Update analytics sharing
        $shareAnalytics = isset($data['share_analytics']) ? 1 : 0;
        if ($currentSettings['share_analytics'] != $shareAnalytics) {
            $changes['share_analytics'] = [
                'from' => $currentSettings['share_analytics'],
                'to' => $shareAnalytics
            ];
        }
        $currentSettings['share_analytics'] = $shareAnalytics;
        
        if (empty($changes)) {
            return ['success' => true, 'message' => 'No changes were made to privacy settings'];
        }
        
        $success = $notificationManager->updatePreferences($userId, $currentSettings);
        
        if ($success) {
            return [
                'success' => true, 
                'message' => 'Privacy settings updated successfully!',
                'changes' => $changes
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to update privacy settings'];
        }
        
    } catch (Exception $e) {
        error_log("Update privacy error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update privacy settings. Please try again.'];
    }
}

function enable2FASecure($db, $security, $userId) {
    // Placeholder for 2FA implementation
    return [
        'success' => false, 
        'message' => 'Two-factor authentication is not yet implemented. This feature will be available in a future update.'
    ];
}

function getBrowserInfo($user_agent) {
    $browser = 'Unknown Browser';
    $os = 'Unknown OS';
    $device = 'Desktop';
    
    // Enhanced browser detection
    if (preg_match('/Chrome\/([0-9.]+)/', $user_agent, $matches)) {
        $browser = 'Chrome ' . explode('.', $matches[1])[0];
    } elseif (preg_match('/Firefox\/([0-9.]+)/', $user_agent, $matches)) {
        $browser = 'Firefox ' . explode('.', $matches[1])[0];
    } elseif (preg_match('/Safari\/([0-9.]+)/', $user_agent, $matches)) {
        $browser = 'Safari';
    } elseif (preg_match('/Edge\/([0-9.]+)/', $user_agent, $matches)) {
        $browser = 'Edge ' . explode('.', $matches[1])[0];
    }
    
    // Enhanced OS detection
    if (preg_match('/Windows NT ([0-9.]+)/', $user_agent, $matches)) {
        $version = $matches[1];
        if ($version >= 10) $os = 'Windows 10/11';
        elseif ($version >= 6.3) $os = 'Windows 8.1';
        elseif ($version >= 6.1) $os = 'Windows 7';
        else $os = 'Windows';
    } elseif (preg_match('/Mac OS X ([0-9_]+)/', $user_agent, $matches)) {
        $os = 'macOS ' . str_replace('_', '.', $matches[1]);
    } elseif (strpos($user_agent, 'Linux') !== false) {
        $os = 'Linux';
    } elseif (preg_match('/Android ([0-9.]+)/', $user_agent, $matches)) {
        $os = 'Android ' . $matches[1];
        $device = 'Mobile';
    } elseif (preg_match('/iPhone OS ([0-9_]+)/', $user_agent, $matches)) {
        $os = 'iOS ' . str_replace('_', '.', $matches[1]);
        $device = 'Mobile';
    }
    
    // Device type detection
    if (strpos($user_agent, 'Mobile') !== false || strpos($user_agent, 'iPhone') !== false) {
        $device = 'Mobile';
    } elseif (strpos($user_agent, 'Tablet') !== false || strpos($user_agent, 'iPad') !== false) {
        $device = 'Tablet';
    }
    
    return [
        'browser' => $browser,
        'os' => $os,
        'device' => $device,
        'full' => $browser . ' on ' . $os . ' (' . $device . ')'
    ];
}

$performance->endTimer('settings_load');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Account settings for Electronic Check-in System - manage password, notifications, privacy, and security preferences">
    <meta name="keywords" content="account settings, password, security, notifications, privacy, two-factor authentication">
    <meta name="author" content="Electronic Check-in System">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Security Headers -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">
    
    <!-- Progressive Web App -->
    <meta name="theme-color" content="#2c3e50">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Account Settings">
    
    <title>Account Settings - Electronic Check-in System</title>
    
    <!-- Optimized CSS Loading -->
    <link rel="preload" href="../assets/css/main.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="../assets/css/navigation.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="../assets/css/forms.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="../assets/css/dashboard.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="../assets/css/account-settings.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="../assets/css/main.css">
        <link rel="stylesheet" href="../assets/css/navigation.css">
        <link rel="stylesheet" href="../assets/css/forms.css">
        <link rel="stylesheet" href="../assets/css/dashboard.css">
        <link rel="stylesheet" href="../assets/css/account-settings.css">
    </noscript>
    
    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebPage",
        "name": "Account Settings",
        "description": "Comprehensive account management for Electronic Check-in System",
        "isPartOf": {
            "@type": "WebSite",
            "name": "Electronic Check-in System"
        }
    }
    </script>
    
    <style>
        .password-strength-container {
            margin-top: 0.5rem;
        }
        
        .password-strength-bar {
            height: 4px;
            background: var(--border-light);
            border-radius: 2px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .password-strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .strength-weak .password-strength-fill { 
            width: 25%; 
            background: var(--error-color); 
        }
        .strength-fair .password-strength-fill { 
            width: 50%; 
            background: var(--warning-color); 
        }
        .strength-good .password-strength-fill { 
            width: 75%; 
            background: var(--info-color); 
        }
        .strength-strong .password-strength-fill { 
            width: 100%; 
            background: var(--success-color); 
        }
        
        .password-requirements {
            margin-top: 0.75rem;
            padding: 0.75rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            margin: 0.25rem 0;
            color: var(--text-muted);
            transition: color 0.3s ease;
        }
        
        .requirement.met {
            color: var(--success-color);
        }
        
        .requirement-icon {
            width: 16px;
            margin-right: 0.5rem;
            font-weight: bold;
        }
        
        .security-metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .security-metric {
            background: var(--bg-primary);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .security-metric:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .metric-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .metric-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--color-primary);
            margin-bottom: 0.25rem;
        }
        
        .metric-label {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        .login-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            margin-bottom: 0.5rem;
            background: var(--bg-primary);
            transition: all 0.3s ease;
        }
        
        .login-item:hover {
            background: var(--bg-hover);
        }
        
        .login-item.failed {
            border-left: 4px solid var(--error-color);
            background: var(--bg-error-light);
        }
        
        .login-info {
            flex: 1;
        }
        
        .login-time {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .login-details {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        .login-status {
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-success {
            background: var(--bg-success);
            color: var(--success-color);
        }
        
        .status-failed {
            background: var(--bg-error);
            color: var(--error-color);
        }
        
        .feature-preview {
            padding: 1.5rem;
            background: var(--bg-warning);
            border-left: 4px solid var(--warning-color);
            border-radius: var(--radius-sm);
            margin: 1rem 0;
        }
        
        .feature-preview-header {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .feature-preview-icon {
            margin-right: 0.5rem;
            font-size: 1.2rem;
        }
        
        .feature-preview-text {
            margin: 0;
            color: var(--text-warning);
        }
        
        .export-options {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        
        @media (max-width: 768px) {
            .security-metrics-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .export-options {
                flex-direction: column;
            }
            
            .tab-btn {
                font-size: 0.85rem;
                padding: 0.5rem;
            }
        }
        
        /* Accessibility Enhancements */
        @media (prefers-reduced-motion: reduce) {
            .security-metric,
            .login-item,
            .password-strength-fill,
            .requirement {
                transition: none;
            }
        }
        
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0,0,0,0);
            white-space: nowrap;
            border: 0;
        }
        
        .toggle-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            user-select: none;
        }
        
        .toggle-input {
            position: relative;
            width: 48px;
            height: 24px;
            margin-right: 0.75rem;
            cursor: pointer;
            appearance: none;
            background: var(--border-color);
            border-radius: 12px;
            transition: background 0.3s ease;
        }
        
        .toggle-input:checked {
            background: var(--color-primary);
        }
        
        .toggle-input:before {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            transition: transform 0.3s ease;
        }
        
        .toggle-input:checked:before {
            transform: translateX(24px);
        }
        
        .toggle-text {
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Skip to main content for accessibility -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-0 focus:left-0 bg-primary text-white p-2 z-50">
        Skip to main content
    </a>
    
    <?php include '../includes/navigation.php'; ?>
    
    <main id="main-content" class="main-content" role="main">
        <div class="settings-container">
            <header class="settings-header" role="banner">
                <h1>
                    <span aria-hidden="true">⚙️</span> Account Settings
                </h1>
                <p class="subtitle">Manage your security, notifications, and privacy preferences</p>
            </header>
            
            <!-- Messages with Enhanced Accessibility -->
            <?php if ($message): ?>
                <div class="alert alert-success" role="alert" aria-live="polite">
                    <span aria-hidden="true">✅</span>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error" role="alert" aria-live="assertive">
                    <span aria-hidden="true">❌</span>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Enhanced Settings Tabs with Accessibility -->
            <div class="settings-tabs" role="tablist" aria-label="Account settings sections">
                <button class="tab-btn active" 
                        onclick="showTab(event, 'security-tab')" 
                        role="tab" 
                        aria-selected="true" 
                        aria-controls="security-tab" 
                        id="security-tab-btn">
                    <span aria-hidden="true">🔒</span> Security
                </button>
                <button class="tab-btn" 
                        onclick="showTab(event, 'notifications-tab')" 
                        role="tab" 
                        aria-selected="false" 
                        aria-controls="notifications-tab" 
                        id="notifications-tab-btn">
                    <span aria-hidden="true">🔔</span> Notifications
                </button>
                <button class="tab-btn" 
                        onclick="showTab(event, 'privacy-tab')" 
                        role="tab" 
                        aria-selected="false" 
                        aria-controls="privacy-tab" 
                        id="privacy-tab-btn">
                    <span aria-hidden="true">🛡️</span> Privacy
                </button>
                <button class="tab-btn" 
                        onclick="showTab(event, 'activity-tab')" 
                        role="tab" 
                        aria-selected="false" 
                        aria-controls="activity-tab" 
                        id="activity-tab-btn">
                    <span aria-hidden="true">📊</span> Activity
                </button>
            </div>
            
            <!-- Security Tab -->
            <section id="security-tab" class="tab-content active" role="tabpanel" aria-labelledby="security-tab-btn">
                <!-- Security Overview -->
                <div class="section-card">
                    <h2 class="section-title">
                        <span aria-hidden="true">🛡️</span> Security Overview
                    </h2>
                    <p class="text-secondary">Monitor your account security status and recent activity</p>
                    
                    <div class="security-metrics-grid">
                        <div class="security-metric" tabindex="0">
                            <span class="metric-icon" aria-hidden="true">✅</span>
                            <div class="metric-value"><?php echo number_format($security_metrics['successful_logins']); ?></div>
                            <div class="metric-label">Successful Logins (30 days)</div>
                        </div>
                        <div class="security-metric" tabindex="0">
                            <span class="metric-icon" aria-hidden="true">❌</span>
                            <div class="metric-value"><?php echo number_format($security_metrics['failed_logins']); ?></div>
                            <div class="metric-label">Failed Login Attempts</div>
                        </div>
                        <div class="security-metric" tabindex="0">
                            <span class="metric-icon" aria-hidden="true">🔑</span>
                            <div class="metric-value"><?php echo number_format($security_metrics['password_changes']); ?></div>
                            <div class="metric-label">Password Changes</div>
                        </div>
                        <div class="security-metric" tabindex="0">
                            <span class="metric-icon" aria-hidden="true">🌐</span>
                            <div class="metric-value"><?php echo number_format($security_metrics['unique_ips']); ?></div>
                            <div class="metric-label">Unique IP Addresses</div>
                        </div>
                    </div>
                </div>
                
                <!-- Password Change -->
                <div class="section-card">
                    <h2 class="section-title">
                        <span aria-hidden="true">🔑</span> Change Password
                    </h2>
                    <p class="text-secondary">Update your password to keep your account secure</p>
                    
                    <form method="POST" id="password-form" novalidate aria-describedby="password-form-help">
                        <input type="hidden" name="action" value="change_password">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        
                        <div id="password-form-help" class="form-help">
                            <p>Your password must meet all security requirements listed below.</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="current_password">
                                Current Password *
                                <span class="sr-only">(required)</span>
                            </label>
                            <input type="password" 
                                   id="current_password" 
                                   name="current_password" 
                                   required
                                   autocomplete="current-password"
                                   aria-describedby="current_password_help">
                            <div id="current_password_help" class="field-help">Enter your current password to verify your identity</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">
                                New Password *
                                <span class="sr-only">(required)</span>
                            </label>
                            <input type="password" 
                                   id="new_password" 
                                   name="new_password" 
                                   required
                                   autocomplete="new-password"
                                   aria-describedby="new_password_help password-requirements"
                                   oninput="checkPasswordStrength(this.value)">
                            <div id="new_password_help" class="field-help">Create a strong password that meets all requirements</div>
                            
                            <div class="password-strength-container">
                                <div class="password-strength-bar" id="strength-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                    <div class="password-strength-fill"></div>
                                </div>
                                <div id="strength-text" class="field-help" aria-live="polite"></div>
                            </div>
                            
                            <div class="password-requirements" id="password-requirements" aria-label="Password requirements">
                                <div class="requirement" id="req-length">
                                    <span class="requirement-icon">•</span> At least 8 characters
                                </div>
                                <div class="requirement" id="req-lower">
                                    <span class="requirement-icon">•</span> At least one lowercase letter
                                </div>
                                <div class="requirement" id="req-upper">
                                    <span class="requirement-icon">•</span> At least one uppercase letter
                                </div>
                                <div class="requirement" id="req-number">
                                    <span class="requirement-icon">•</span> At least one number
                                </div>
                                <div class="requirement" id="req-special">
                                    <span class="requirement-icon">•</span> At least one special character
                                </div>
                                <div class="requirement" id="req-common">
                                    <span class="requirement-icon">•</span> Not a common password
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">
                                Confirm New Password *
                                <span class="sr-only">(required)</span>
                            </label>
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   required
                                   autocomplete="new-password"
                                   aria-describedby="confirm_password_help"
                                   oninput="checkPasswordMatch()">
                            <div id="confirm_password_help" class="field-help">Re-enter your new password to confirm</div>
                            <div id="password-match-msg" class="field-help" aria-live="polite"></div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary" id="password-submit">
                                <span aria-hidden="true">🔄</span> Change Password
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Two-Factor Authentication -->
                <div class="section-card">
                    <h2 class="section-title">
                        <span aria-hidden="true">📱</span> Two-Factor Authentication
                    </h2>
                    <p class="text-secondary">Add an extra layer of security to your account</p>
                    
                    <div class="feature-preview">
                        <div class="feature-preview-header">
                            <span class="feature-preview-icon" aria-hidden="true">ℹ️</span>
                            <strong>Coming Soon</strong>
                        </div>
                        <p class="feature-preview-text">
                            Two-factor authentication will provide enhanced security by requiring a second form of verification during login. 
                            This feature will support authenticator apps, SMS codes, and backup codes.
                        </p>
                    </div>
                    
                    <form method="POST" onsubmit="return false;">
                        <input type="hidden" name="action" value="enable_2fa">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <button type="submit" class="btn btn-outline" disabled aria-describedby="2fa-status">
                            <span aria-hidden="true">🔐</span> Enable Two-Factor Authentication
                        </button>
                        <p id="2fa-status" class="text-muted" style="margin-top: 0.5rem; font-size: 0.85rem;">
                            This feature will be available in a future update
                        </p>
                    </form>
                </div>
            </section>
            
            <!-- Notifications Tab -->
            <section id="notifications-tab" class="tab-content" role="tabpanel" aria-labelledby="notifications-tab-btn">
                <div class="section-card">
                    <h2 class="section-title">
                        <span aria-hidden="true">🔔</span> Notification Preferences
                    </h2>
                    <p class="text-secondary">Choose how you want to receive notifications about events and activities</p>
                    
                    <form method="POST" aria-describedby="notifications-form-help">
                        <input type="hidden" name="action" value="update_notifications">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        
                        <div id="notifications-form-help" class="form-help">
                            <p>Customize your notification preferences to stay informed about relevant activities.</p>
                        </div>
                        
                        <div class="checkbox-group" role="group" aria-labelledby="notification-types">
                            <h3 id="notification-types" class="group-title">
                                <span aria-hidden="true">📧</span> Communication Preferences
                            </h3>
                            
                            <div class="checkbox-item">
                                <label class="toggle-label" for="email_notifications">
                                    <input type="checkbox" 
                                           class="toggle-input" 
                                           id="email_notifications" 
                                           name="email_notifications" 
                                           <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>
                                           aria-describedby="email_notifications_desc">
                                    <span class="toggle-text">Email Notifications</span>
                                </label>
                                <div id="email_notifications_desc" class="checkbox-description">
                                    Receive email notifications about events, check-ins, and system updates.
                                </div>
                            </div>
                            
                            <div class="checkbox-item">
                                <label class="toggle-label" for="sms_notifications">
                                    <input type="checkbox" 
                                           class="toggle-input" 
                                           id="sms_notifications" 
                                           name="sms_notifications" 
                                           <?php echo $settings['sms_notifications'] ? 'checked' : ''; ?>
                                           aria-describedby="sms_notifications_desc">
                                    <span class="toggle-text">SMS Notifications</span>
                                </label>
                                <div id="sms_notifications_desc" class="checkbox-description">
                                    Receive text message notifications for important events and reminders.
                                </div>
                            </div>
                            
                            <div class="checkbox-item">
                                <label class="toggle-label" for="event_reminders">
                                    <input type="checkbox" 
                                           class="toggle-input" 
                                           id="event_reminders" 
                                           name="event_reminders" 
                                           <?php echo $settings['event_reminders'] ? 'checked' : ''; ?>
                                           aria-describedby="event_reminders_desc">
                                    <span class="toggle-text">Event Reminders</span>
                                </label>
                                <div id="event_reminders_desc" class="checkbox-description">
                                    Get reminded about upcoming events you might want to attend.
                                </div>
                            </div>
                        </div>
                        
                        <div class="checkbox-group" role="group" aria-labelledby="security-notifications">
                            <h3 id="security-notifications" class="group-title">
                                <span aria-hidden="true">🔒</span> Security & Updates
                            </h3>
                            
                            <div class="checkbox-item">
                                <label class="toggle-label" for="security_alerts">
                                    <input type="checkbox" 
                                           class="toggle-input" 
                                           id="security_alerts" 
                                           name="security_alerts" 
                                           <?php echo $settings['security_alerts'] ? 'checked' : ''; ?>
                                           aria-describedby="security_alerts_desc">
                                    <span class="toggle-text">Security Alerts</span>
                                </label>
                                <div id="security_alerts_desc" class="checkbox-description">
                                    Receive immediate notifications about security events and login attempts.
                                </div>
                            </div>
                            
                            <div class="checkbox-item">
                                <label class="toggle-label" for="weekly_digest">
                                    <input type="checkbox" 
                                           class="toggle-input" 
                                           id="weekly_digest" 
                                           name="weekly_digest" 
                                           <?php echo $settings['weekly_digest'] ? 'checked' : ''; ?>
                                           aria-describedby="weekly_digest_desc">
                                    <span class="toggle-text">Weekly Digest</span>
                                </label>
                                <div id="weekly_digest_desc" class="checkbox-description">
                                    Receive a weekly summary of your activity and upcoming events.
                                </div>
                            </div>
                            
                            <div class="checkbox-item">
                                <label class="toggle-label" for="marketing_emails">
                                    <input type="checkbox" 
                                           class="toggle-input" 
                                           id="marketing_emails" 
                                           name="marketing_emails" 
                                           <?php echo $settings['marketing_emails'] ? 'checked' : ''; ?>
                                           aria-describedby="marketing_emails_desc">
                                    <span class="toggle-text">Marketing Communications</span>
                                </label>
                                <div id="marketing_emails_desc" class="checkbox-description">
                                    Receive promotional emails about new features and system updates.
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <span aria-hidden="true">💾</span> Save Notification Settings
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Notification Schedule -->
                <div class="section-card">
                    <h2 class="section-title">
                        <span aria-hidden="true">⏰</span> Notification Schedule
                    </h2>
                    <p class="text-secondary">Control when you receive notifications</p>
                    
                    <div class="feature-preview">
                        <div class="feature-preview-header">
                            <span class="feature-preview-icon" aria-hidden="true">ℹ️</span>
                            <strong>Coming Soon</strong>
                        </div>
                        <p class="feature-preview-text">
                            Customize notification timing, quiet hours, and delivery preferences to avoid disruptions during specific times.
                        </p>
                    </div>
                </div>
            </section>
            
            <!-- Privacy Tab -->
            <section id="privacy-tab" class="tab-content" role="tabpanel" aria-labelledby="privacy-tab-btn">
                <div class="section-card">
                    <h2 class="section-title">
                        <span aria-hidden="true">🛡️</span> Privacy Settings
                    </h2>
                    <p class="text-secondary">Control who can see your profile and activity information</p>
                    
                    <form method="POST" aria-describedby="privacy-form-help">
                        <input type="hidden" name="action" value="update_privacy">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        
                        <div id="privacy-form-help" class="form-help">
                            <p>Manage your privacy preferences to control how your information is shared within the system.</p>
                        </div>
                        
                        <div class="form-group">
                            <fieldset>
                                <legend>
                                    <span aria-hidden="true">👁️</span> Profile Visibility
                                </legend>
                                <div class="radio-group" role="radiogroup" aria-describedby="profile-visibility-help">
                                    <div class="radio-item">
                                        <input type="radio" 
                                               id="visibility_public" 
                                               name="profile_visibility" 
                                               value="public" 
                                               <?php echo $settings['profile_visibility'] === 'public' ? 'checked' : ''; ?>
                                               aria-describedby="visibility_public_desc">
                                        <label for="visibility_public" class="radio-label">
                                            <span class="radio-title">
                                                <span aria-hidden="true">🌍</span> Public
                                            </span>
                                            <div id="visibility_public_desc" class="radio-description">
                                                Your profile is visible to everyone, including non-members.
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <div class="radio-item">
                                        <input type="radio" 
                                               id="visibility_members" 
                                               name="profile_visibility" 
                                               value="members" 
                                               <?php echo $settings['profile_visibility'] === 'members' ? 'checked' : ''; ?>
                                               aria-describedby="visibility_members_desc">
                                        <label for="visibility_members" class="radio-label">
                                            <span class="radio-title">
                                                <span aria-hidden="true">👥</span> Members Only
                                            </span>
                                            <div id="visibility_members_desc" class="radio-description">
                                                Only logged-in members can view your profile and activity.
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <div class="radio-item">
                                        <input type="radio" 
                                               id="visibility_private" 
                                               name="profile_visibility" 
                                               value="private" 
                                               <?php echo $settings['profile_visibility'] === 'private' ? 'checked' : ''; ?>
                                               aria-describedby="visibility_private_desc">
                                        <label for="visibility_private" class="radio-label">
                                            <span class="radio-title">
                                                <span aria-hidden="true">🔒</span> Private
                                            </span>
                                            <div id="visibility_private_desc" class="radio-description">
                                                Your profile is only visible to administrators and yourself.
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div id="profile-visibility-help" class="field-help">
                                    Choose who can view your profile information and activity history
                                </div>
                            </fieldset>
                        </div>
                        
                        <div class="checkbox-group" role="group" aria-labelledby="data-sharing">
                            <h3 id="data-sharing" class="group-title">
                                <span aria-hidden="true">📊</span> Data Sharing
                            </h3>
                            
                            <div class="checkbox-item">
                                <label class="toggle-label" for="share_analytics">
                                    <input type="checkbox" 
                                           class="toggle-input" 
                                           id="share_analytics" 
                                           name="share_analytics" 
                                           <?php echo $settings['share_analytics'] ? 'checked' : ''; ?>
                                           aria-describedby="share_analytics_desc">
                                    <span class="toggle-text">Share Analytics Data</span>
                                </label>
                                <div id="share_analytics_desc" class="checkbox-description">
                                    Allow your anonymized data to be included in system-wide analytics and reports to help improve the service.
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <span aria-hidden="true">💾</span> Save Privacy Settings
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Data Rights -->
                <div class="section-card">
                    <h2 class="section-title">
                        <span aria-hidden="true">⚖️</span> Data Rights
                    </h2>
                    <p class="text-secondary">Understand and exercise your data protection rights</p>
                    
                    <div class="data-rights-info">
                        <h3>Your Rights</h3>
                        <ul>
                            <li><strong>Right to Access:</strong> Request a copy of all personal data we hold about you</li>
                            <li><strong>Right to Rectification:</strong> Request correction of inaccurate personal data</li>
                            <li><strong>Right to Erasure:</strong> Request deletion of your personal data</li>
                            <li><strong>Right to Portability:</strong> Request your data in a machine-readable format</li>
                            <li><strong>Right to Object:</strong> Object to processing of your personal data</li>
                        </ul>
                        
                        <p class="text-muted">
                            For any data protection requests, please contact the system administrator or use the data export features below.
                        </p>
                    </div>
                </div>
            </section>
            
            <!-- Activity Tab -->
            <section id="activity-tab" class="tab-content" role="tabpanel" aria-labelledby="activity-tab-btn">
                <div class="section-card">
                    <h2 class="section-title">
                        <span aria-hidden="true">📊</span> Activity History
                    </h2>
                    <p class="text-secondary">View your login history and account activity</p>
                    
                    <div class="activity-stats">
                        <div class="stat-grid">
                            <div class="stat-item">
                                <div class="stat-value" id="total-logins"><?php echo number_format($activityStats['total_logins']); ?></div>
                                <div class="stat-label">Total Logins</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value" id="last-login"><?php echo date('M j, Y', strtotime($activityStats['last_login'])); ?></div>
                                <div class="stat-label">Last Login</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value" id="unique-devices"><?php echo $activityStats['unique_devices']; ?></div>
                                <div class="stat-label">Unique Devices</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value" id="failed-attempts"><?php echo $activityStats['failed_attempts']; ?></div>
                                <div class="stat-label">Failed Login Attempts</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="activity-controls">
                        <button type="button" class="btn btn-secondary" onclick="exportActivityData()">
                            <span aria-hidden="true">📥</span> Export Activity Data
                        </button>
                        <button type="button" class="btn btn-outline" onclick="refreshActivityData()">
                            <span aria-hidden="true">🔄</span> Refresh
                        </button>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="section-card">
                    <h2 class="section-title">
                        <span aria-hidden="true">🕒</span> Recent Activity
                    </h2>
                    <p class="text-secondary">Your latest login sessions and security events</p>
                    
                    <div class="activity-list" role="list" aria-label="Recent login activity">
                        <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item" role="listitem">
                            <div class="activity-icon">
                                <?php if ($activity['status'] === 'success'): ?>
                                    <span class="icon-success" aria-hidden="true">✅</span>
                                <?php elseif ($activity['status'] === 'failed'): ?>
                                    <span class="icon-error" aria-hidden="true">❌</span>
                                <?php else: ?>
                                    <span class="icon-info" aria-hidden="true">ℹ️</span>
                                <?php endif; ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <?php echo htmlspecialchars($activity['action']); ?>
                                    <?php if ($activity['status'] === 'success'): ?>
                                        <span class="status-badge status-success">Success</span>
                                    <?php elseif ($activity['status'] === 'failed'): ?>
                                        <span class="status-badge status-error">Failed</span>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-details">
                                    <span class="activity-time">
                                        <span aria-hidden="true">🕐</span> <?php echo date('M j, Y g:i A', strtotime($activity['timestamp'])); ?>
                                    </span>
                                    <?php if (!empty($activity['ip_address'])): ?>
                                        <span class="activity-ip">
                                            <span aria-hidden="true">🌐</span> <?php echo htmlspecialchars($activity['ip_address']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($activity['device'])): ?>
                                        <span class="activity-device">
                                            <span aria-hidden="true">📱</span> <?php echo htmlspecialchars($activity['device']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($activity['location'])): ?>
                                    <div class="activity-location">
                                        <span aria-hidden="true">📍</span> <?php echo htmlspecialchars($activity['location']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (empty($recentActivity)): ?>
                        <div class="empty-state">
                            <span class="empty-icon" aria-hidden="true">📊</span>
                            <h3>No Activity Found</h3>
                            <p>Your activity history will appear here as you use the system.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Data Export -->
                <div class="section-card">
                    <h2 class="section-title">
                        <span aria-hidden="true">�</span> Data Export
                    </h2>
                    <p class="text-secondary">Download your personal data and activity history</p>
                    
                    <div class="export-options">
                        <div class="export-item">
                            <h3>
                                <span aria-hidden="true">👤</span> Profile Data
                            </h3>
                            <p>Download your complete profile information, preferences, and settings.</p>
                            <button type="button" class="btn btn-outline" onclick="exportProfileData()">
                                <span aria-hidden="true">�</span> Export Profile
                            </button>
                        </div>
                        
                        <div class="export-item">
                            <h3>
                                <span aria-hidden="true">📊</span> Activity History
                            </h3>
                            <p>Download your complete login history and system activity logs.</p>
                            <button type="button" class="btn btn-outline" onclick="exportActivityHistory()">
                                <span aria-hidden="true">📥</span> Export Activity
                            </button>
                        </div>
                        
                        <div class="export-item">
                            <h3>
                                <span aria-hidden="true">📋</span> Check-in Records
                            </h3>
                            <p>Download your complete event check-in history and attendance records.</p>
                            <button type="button" class="btn btn-outline" onclick="exportCheckinData()">
                                <span aria-hidden="true">�</span> Export Check-ins
                            </button>
                        </div>
                    </div>
                    
                    <div class="data-retention-info">
                        <h3>Data Retention Policy</h3>
                        <p class="text-muted">
                            Activity logs are retained for 2 years for security purposes. 
                            Profile data is kept until account deletion. 
                            Check-in records are maintained indefinitely for attendance tracking.
                        </p>
                    </div>
                </div>
            </section>
        </main>
    </div>
    
    <!-- Success/Error Messages -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>" role="alert" aria-live="polite">
            <span aria-hidden="true">
                <?php echo $messageType === 'success' ? '✅' : ($messageType === 'error' ? '❌' : 'ℹ️'); ?>
            </span>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    
    <!-- Enhanced JavaScript -->
    <script>
        // Global variables
        let currentTab = 'security';
        let passwordStrengthChecked = false;
        const csrfToken = '<?php echo $csrfToken; ?>';
        
        // Performance monitoring
        const performanceMetrics = {
            tabSwitches: 0,
            passwordChecks: 0,
            formSubmissions: 0
        };
        
        // Initialize application
        document.addEventListener('DOMContentLoaded', function() {
            initializeTabs();
            initializePasswordStrength();
            initializeFormValidation();
            initializeAccessibility();
            loadNotificationPreferences();
            
            // Add performance monitoring
            console.log('Account Settings: Initialized at', new Date().toISOString());
        });
        
        // Tab Management
        function initializeTabs() {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetTab = this.getAttribute('data-tab');
                    switchTab(targetTab);
                });
                
                // Keyboard navigation
                button.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.click();
                    } else if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                        e.preventDefault();
                        navigateTabsWithKeyboard(e.key === 'ArrowRight');
                    }
                });
            });
        }
        
        function switchTab(tabName) {
            // Update performance metrics
            performanceMetrics.tabSwitches++;
            
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
                content.setAttribute('aria-hidden', 'true');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
                btn.setAttribute('aria-selected', 'false');
            });
            
            // Show selected tab
            const selectedContent = document.getElementById(tabName + '-tab');
            const selectedButton = document.querySelector(`[data-tab="${tabName}"]`);
            
            if (selectedContent && selectedButton) {
                selectedContent.classList.add('active');
                selectedContent.setAttribute('aria-hidden', 'false');
                selectedButton.classList.add('active');
                selectedButton.setAttribute('aria-selected', 'true');
                currentTab = tabName;
                
                // Focus management
                selectedContent.focus();
                
                // Announce tab change to screen readers
                announceToScreenReader(`Switched to ${tabName} settings`);
            }
        }
        
        function navigateTabsWithKeyboard(forward) {
            const tabButtons = Array.from(document.querySelectorAll('.tab-btn'));
            const currentIndex = tabButtons.findIndex(btn => btn.classList.contains('active'));
            let nextIndex;
            
            if (forward) {
                nextIndex = (currentIndex + 1) % tabButtons.length;
            } else {
                nextIndex = currentIndex === 0 ? tabButtons.length - 1 : currentIndex - 1;
            }
            
            tabButtons[nextIndex].click();
            tabButtons[nextIndex].focus();
        }
        
        // Password Strength Validation
        function initializePasswordStrength() {
            const passwordInput = document.getElementById('new_password');
            const confirmInput = document.getElementById('confirm_password');
            const strengthIndicator = document.getElementById('password-strength');
            const requirements = document.getElementById('password-requirements');
            
            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    checkPasswordStrength(this.value);
                    performanceMetrics.passwordChecks++;
                });
                
                passwordInput.addEventListener('focus', function() {
                    if (requirements) {
                        requirements.style.display = 'block';
                    }
                });
            }
            
            if (confirmInput) {
                confirmInput.addEventListener('input', function() {
                    checkPasswordMatch(passwordInput.value, this.value);
                });
            }
        }
        
        function checkPasswordStrength(password) {
            const strengthIndicator = document.getElementById('password-strength');
            const requirementsList = document.querySelectorAll('#password-requirements .requirement');
            
            if (!strengthIndicator || !requirementsList.length) return;
            
            // Check requirements
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /\d/.test(password),
                special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
            };
            
            // Update requirement indicators
            requirementsList.forEach(req => {
                const type = req.getAttribute('data-requirement');
                const isValid = requirements[type];
                req.classList.toggle('valid', isValid);
                req.setAttribute('aria-invalid', !isValid);
                
                const icon = req.querySelector('.requirement-icon');
                if (icon) {
                    icon.textContent = isValid ? '✅' : '❌';
                }
            });
            
            // Calculate strength
            const validCount = Object.values(requirements).filter(Boolean).length;
            let strength = 'weak';
            let strengthClass = 'strength-weak';
            
            if (validCount >= 4) {
                strength = 'strong';
                strengthClass = 'strength-strong';
            } else if (validCount >= 2) {
                strength = 'medium';
                strengthClass = 'strength-medium';
            }
            
            // Update strength indicator
            strengthIndicator.className = `password-strength ${strengthClass}`;
            strengthIndicator.textContent = `Password strength: ${strength}`;
            strengthIndicator.setAttribute('aria-label', `Password strength: ${strength}. ${validCount} of 5 requirements met.`);
            
            passwordStrengthChecked = true;
        }
        
        function checkPasswordMatch(password, confirmPassword) {
            const confirmInput = document.getElementById('confirm_password');
            const matchIndicator = document.getElementById('password-match');
            
            if (!confirmInput || !matchIndicator) return;
            
            const isMatch = password === confirmPassword && password.length > 0;
            
            confirmInput.classList.toggle('invalid', !isMatch && confirmPassword.length > 0);
            matchIndicator.style.display = confirmPassword.length > 0 ? 'block' : 'none';
            matchIndicator.textContent = isMatch ? '✅ Passwords match' : '❌ Passwords do not match';
            matchIndicator.className = `password-match ${isMatch ? 'valid' : 'invalid'}`;
        }
        
        // Form Validation
        function initializeFormValidation() {
            const forms = document.querySelectorAll('form');
            
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!validateForm(this)) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Update performance metrics
                    performanceMetrics.formSubmissions++;
                    
                    // Show loading state
                    const submitButton = this.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.innerHTML = '<span aria-hidden="true">⏳</span> Saving...';
                    }
                });
            });
        }
        
        function validateForm(form) {
            const action = form.querySelector('input[name="action"]')?.value;
            
            if (action === 'change_password') {
                return validatePasswordForm(form);
            }
            
            return true;
        }
        
        function validatePasswordForm(form) {
            const currentPassword = form.querySelector('#current_password')?.value;
            const newPassword = form.querySelector('#new_password')?.value;
            const confirmPassword = form.querySelector('#confirm_password')?.value;
            
            if (!currentPassword) {
                showError('Please enter your current password');
                return false;
            }
            
            if (!newPassword) {
                showError('Please enter a new password');
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                showError('New passwords do not match');
                return false;
            }
            
            if (!passwordStrengthChecked) {
                showError('Please wait for password strength validation');
                return false;
            }
            
            return true;
        }
        
        // Accessibility Features
        function initializeAccessibility() {
            // Add skip links
            addSkipLinks();
            
            // Enhanced focus management
            setupFocusManagement();
            
            // Keyboard shortcuts
            setupKeyboardShortcuts();
            
            // High contrast support
            detectHighContrast();
        }
        
        function addSkipLinks() {
            const skipLink = document.createElement('a');
            skipLink.href = '#main-content';
            skipLink.className = 'skip-link';
            skipLink.textContent = 'Skip to main content';
            skipLink.addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('main-content').focus();
            });
            
            document.body.insertBefore(skipLink, document.body.firstChild);
        }
        
        function setupFocusManagement() {
            // Trap focus in modals
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Tab') {
                    const modal = document.querySelector('.modal:not([aria-hidden="true"])');
                    if (modal) {
                        trapFocus(e, modal);
                    }
                }
            });
        }
        
        function trapFocus(e, container) {
            const focusableElements = container.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            
            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];
            
            if (e.shiftKey && document.activeElement === firstElement) {
                e.preventDefault();
                lastElement.focus();
            } else if (!e.shiftKey && document.activeElement === lastElement) {
                e.preventDefault();
                firstElement.focus();
            }
        }
        
        function setupKeyboardShortcuts() {
            document.addEventListener('keydown', function(e) {
                // Alt + 1-4 for tab navigation
                if (e.altKey && e.key >= '1' && e.key <= '4') {
                    e.preventDefault();
                    const tabs = ['security', 'notifications', 'privacy', 'activity'];
                    const tabIndex = parseInt(e.key) - 1;
                    if (tabs[tabIndex]) {
                        switchTab(tabs[tabIndex]);
                    }
                }
                
                // Esc to close modals
                if (e.key === 'Escape') {
                    const modal = document.querySelector('.modal:not([aria-hidden="true"])');
                    if (modal) {
                        closeModal(modal);
                    }
                }
            });
        }
        
        function detectHighContrast() {
            if (window.matchMedia && window.matchMedia('(prefers-contrast: high)').matches) {
                document.body.classList.add('high-contrast');
            }
        }
        
        // Notification Management
        function loadNotificationPreferences() {
            // Load saved preferences from localStorage
            const savedPrefs = localStorage.getItem('notificationPreferences');
            if (savedPrefs) {
                try {
                    const prefs = JSON.parse(savedPrefs);
                    updateNotificationUI(prefs);
                } catch (e) {
                    console.warn('Failed to load notification preferences:', e);
                }
            }
        }
        
        function updateNotificationUI(preferences) {
            Object.entries(preferences).forEach(([key, value]) => {
                const checkbox = document.getElementById(key);
                if (checkbox) {
                    checkbox.checked = value;
                }
            });
        }
        
        // Activity Functions
        function exportActivityData() {
            showLoadingState('Preparing activity data export...');
            
            fetch(`${window.location.pathname}?action=export_activity`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `csrf_token=${encodeURIComponent(csrfToken)}`
            })
            .then(response => response.blob())
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `activity-data-${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                showSuccess('Activity data exported successfully!');
            })
            .catch(error => {
                console.error('Export failed:', error);
                showError('Failed to export activity data. Please try again.');
            })
            .finally(() => {
                hideLoadingState();
            });
        }
        
        function exportProfileData() {
            exportData('profile', 'Profile data exported successfully!');
        }
        
        function exportActivityHistory() {
            exportData('activity', 'Activity history exported successfully!');
        }
        
        function exportCheckinData() {
            exportData('checkins', 'Check-in records exported successfully!');
        }
        
        function exportData(type, successMessage) {
            showLoadingState(`Preparing ${type} data export...`);
            
            fetch(`${window.location.pathname}?action=export_${type}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `csrf_token=${encodeURIComponent(csrfToken)}`
            })
            .then(response => {
                if (!response.ok) throw new Error('Export failed');
                return response.blob();
            })
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `${type}-data-${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                showSuccess(successMessage);
            })
            .catch(error => {
                console.error('Export failed:', error);
                showError(`Failed to export ${type} data. Please try again.`);
            })
            .finally(() => {
                hideLoadingState();
            });
        }
        
        function refreshActivityData() {
            showLoadingState('Refreshing activity data...');
            
            fetch(window.location.pathname, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newActivityList = doc.querySelector('.activity-list');
                const newStats = doc.querySelector('.activity-stats');
                
                if (newActivityList) {
                    document.querySelector('.activity-list').innerHTML = newActivityList.innerHTML;
                }
                if (newStats) {
                    document.querySelector('.activity-stats').innerHTML = newStats.innerHTML;
                }
                
                showSuccess('Activity data refreshed!');
            })
            .catch(error => {
                console.error('Refresh failed:', error);
                showError('Failed to refresh activity data. Please try again.');
            })
            .finally(() => {
                hideLoadingState();
            });
        }
        
        // Legacy Functions for Compatibility
        function showTab(event, tabId) {
            const tabName = tabId.replace('-tab', '');
            switchTab(tabName);
        }
        
        function exportData(type) {
            switch(type) {
                case 'profile':
                    exportProfileData();
                    break;
                case 'checkins':
                    exportCheckinData();
                    break;
                case 'activity':
                    exportActivityHistory();
                    break;
                case 'all':
                    exportActivityData();
                    break;
                default:
                    showError('Unknown export type requested');
            }
        }
        
        // Utility Functions
        function showError(message) {
            showMessage(message, 'error');
        }
        
        function showSuccess(message) {
            showMessage(message, 'success');
        }
        
        function showMessage(message, type) {
            // Remove existing messages
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());
            
            // Create new message
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.setAttribute('role', 'alert');
            alert.setAttribute('aria-live', 'polite');
            
            const icon = type === 'success' ? '✅' : '❌';
            alert.innerHTML = `<span aria-hidden="true">${icon}</span> ${message}`;
            
            // Insert message
            document.body.appendChild(alert);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
            
            // Announce to screen readers
            announceToScreenReader(message);
        }
        
        function showLoadingState(message) {
            const existingLoader = document.querySelector('.loading-overlay');
            if (existingLoader) return;
            
            const loader = document.createElement('div');
            loader.className = 'loading-overlay';
            loader.innerHTML = `
                <div class="loading-content">
                    <div class="loading-spinner" aria-hidden="true"></div>
                    <div class="loading-message">${message}</div>
                </div>
            `;
            
            document.body.appendChild(loader);
            announceToScreenReader(message);
        }
        
        function hideLoadingState() {
            const loader = document.querySelector('.loading-overlay');
            if (loader) {
                loader.remove();
            }
        }
        
        function announceToScreenReader(message) {
            const announcer = document.getElementById('screen-reader-announcer') || createScreenReaderAnnouncer();
            announcer.textContent = message;
        }
        
        function createScreenReaderAnnouncer() {
            const announcer = document.createElement('div');
            announcer.id = 'screen-reader-announcer';
            announcer.className = 'sr-only';
            announcer.setAttribute('aria-live', 'polite');
            announcer.setAttribute('aria-atomic', 'true');
            document.body.appendChild(announcer);
            return announcer;
        }
        
        // Performance monitoring
        window.addEventListener('beforeunload', function() {
            if (console && console.log) {
                console.log('Account Settings Performance Metrics:', performanceMetrics);
            }
        });
        
        // Initialize URL hash handling
        if (window.location.hash) {
            const tabName = window.location.hash.substring(1).replace('-tab', '');
            if (['security', 'notifications', 'privacy', 'activity'].includes(tabName)) {
                switchTab(tabName);
            }
        }
    </script>

    <?php include '../includes/theme_script.php'; ?>
</body>
</html>
