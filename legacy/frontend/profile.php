<?php
/**
 * User Profile Management
 * 
 * Profile settings interface with avatar upload, RFID tag management,
 * and privacy controls.
 * 
 * @package RfidCheckin\Frontend
 * @author Kralder
 */

require_once '../core/auth.php';
require_once '../core/database.php';
require_once '../core/user-group-manager.php';
require_once '../core/config.php';

// Use global shared utilities from config.php
global $sharedSecurity, $sharedDatabase, $sharedValidation;

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

class FileManager {
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private $maxSize = 5 * 1024 * 1024; // 5MB
    private $uploadDir = '../uploads/avatars';
    
    public function __construct() {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    public function validateUpload($file) {
        $errors = [];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed with error code: ' . $file['error'];
            return ['valid' => false, 'errors' => $errors];
        }
        
        if (!in_array($file['type'], $this->allowedTypes)) {
            $errors[] = 'Only JPEG, PNG, GIF, and WebP images are allowed';
        }
        
        if ($file['size'] > $this->maxSize) {
            $errors[] = 'File size must be less than 5MB';
        }
        
        // Additional security check
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            $errors[] = 'Invalid file type detected';
        }
        
        return ['valid' => empty($errors), 'errors' => $errors];
    }
    
    public function uploadAvatar($file, $userId) {
        $validation = $this->validateUpload($file);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => implode('; ', $validation['errors'])];
        }
        
        try {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'user_' . $userId . '_' . time() . '.' . $extension;
            $uploadPath = $this->uploadDir . '/' . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                return ['success' => false, 'message' => 'Failed to save uploaded file'];
            }
            
            return ['success' => true, 'path' => $uploadPath, 'filename' => $filename];
        } catch (Exception $e) {
            error_log("File upload error: " . $e->getMessage());
            return ['success' => false, 'message' => 'File upload failed'];
        }
    }
    
    public function deleteFile($path) {
        if ($path && file_exists($path) && strpos($path, $this->uploadDir) === 0) {
            return unlink($path);
        }
        return false;
    }
}

// Initialize enterprise components
Auth::requireLogin();
$user = Auth::getCurrentUser(true);
$db = getDB();
$groupManager = new UserGroupManager();
$security = $sharedSecurity; // Use shared security manager
$performance = PerformanceManager::getInstance();
$fileManager = new FileManager();

$performance->startTimer('profile_load');

// Ensure all required user fields are available with defaults
$user = array_merge([
    'user_id' => null,
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'bio' => '',
    'avatar' => null,
    'role' => 'user',
    'rfid_tag' => null,
    'created_at' => null,
    'updated_at' => null,
    'is_active' => 1,
    'last_login' => null,
    'login_count' => 0
], $user ?? []);

$message = '';
$error = '';
$csrfToken = $security->generateCSRFToken();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !$security->validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Security token validation failed. Please try again.';
    } elseif (isset($_POST['action'])) {
        $performance->startTimer('form_processing');
        
        switch ($_POST['action']) {
            case 'update_profile':
                $result = updateProfileSecure($db, $security, $user['user_id'], $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                    $user = Auth::getCurrentUser(true);
                    $security->auditLog('profile_update', ['fields' => array_keys($result['updated_fields'] ?? [])], $user['user_id']);
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'upload_avatar':
                $result = uploadAvatarSecure($db, $fileManager, $security, $user['user_id']);
                if ($result['success']) {
                    $message = $result['message'];
                    $user = Auth::getCurrentUser(true);
                    $security->auditLog('avatar_upload', ['filename' => $result['filename'] ?? 'unknown'], $user['user_id']);
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'remove_avatar':
                $result = removeAvatarSecure($db, $fileManager, $security, $user);
                if ($result['success']) {
                    $message = $result['message'];
                    $user = Auth::getCurrentUser(true);
                    $security->auditLog('avatar_remove', [], $user['user_id']);
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'add_rfid':
                $result = addRFIDTagSecure($db, $security, $user['user_id'], $_POST['rfid_tag']);
                if ($result['success']) {
                    $message = $result['message'];
                    $user = Auth::getCurrentUser(true);
                    $security->auditLog('rfid_add', ['tag' => substr($result['tag'], 0, 4) . '****'], $user['user_id']);
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'remove_rfid':
                $result = removeRFIDTagSecure($db, $security, $user['user_id']);
                if ($result['success']) {
                    $message = $result['message'];
                    $user = Auth::getCurrentUser(true);
                    $security->auditLog('rfid_remove', [], $user['user_id']);
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

// Get enhanced user statistics and activity data
$performance->startTimer('statistics_query');
$cacheKey = 'user_stats_' . $user['user_id'];
$stats = $performance->cacheGet($cacheKey);

if (!$stats) {
    try {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_checkins,
                COUNT(CASE WHEN MONTH(checkin_time) = MONTH(CURRENT_DATE()) 
                      AND YEAR(checkin_time) = YEAR(CURRENT_DATE()) THEN 1 END) as month_checkins,
                COUNT(CASE WHEN WEEK(checkin_time) = WEEK(CURRENT_DATE()) 
                      AND YEAR(checkin_time) = YEAR(CURRENT_DATE()) THEN 1 END) as week_checkins,
                COUNT(CASE WHEN DATE(checkin_time) = CURRENT_DATE() THEN 1 END) as today_checkins,
                COUNT(DISTINCT event_id) as unique_events,
                MIN(checkin_time) as first_checkin,
                MAX(checkin_time) as last_checkin,
                AVG(CASE WHEN checkin_time >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as avg_monthly_activity
            FROM checkin 
            WHERE user_id = ?
        ");
        $stmt->execute([$user['user_id']]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get activity trend data for last 7 days
        $stmt = $db->prepare("
            SELECT 
                DATE(checkin_time) as date,
                COUNT(*) as checkins,
                COUNT(DISTINCT event_id) as events
            FROM checkin 
            WHERE user_id = ? AND checkin_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(checkin_time)
            ORDER BY date
        ");
        $stmt->execute([$user['user_id']]);
        $stats['activity_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get favorite events
        $stmt = $db->prepare("
            SELECT 
                e.event_name,
                COUNT(c.checkin_id) as checkin_count
            FROM checkin c
            JOIN events e ON c.event_id = e.event_id
            WHERE c.user_id = ?
            GROUP BY e.event_id, e.event_name
            ORDER BY checkin_count DESC
            LIMIT 5
        ");
        $stmt->execute([$user['user_id']]);
        $stats['favorite_events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $performance->cacheSet($cacheKey, $stats, 300); // Cache for 5 minutes
    } catch (PDOException $e) {
        error_log("Statistics fetch error: " . $e->getMessage());
        $stats = [
            'total_checkins' => 0,
            'month_checkins' => 0,
            'week_checkins' => 0,
            'today_checkins' => 0,
            'unique_events' => 0,
            'first_checkin' => null,
            'last_checkin' => null,
            'avg_monthly_activity' => 0,
            'activity_trend' => [],
            'favorite_events' => []
        ];
    }
}
$performance->endTimer('statistics_query');

// Get user's RFID tag and enhanced information
$performance->startTimer('rfid_query');
try {
    $stmt = $db->prepare("
        SELECT 
            rfid_tag,
            updated_at,
            last_login,
            login_count
        FROM users 
        WHERE user_id = ?
    ");
    $stmt->execute([$user['user_id']]);
    $user_extended = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $user = array_merge($user, $user_extended ?: []);
} catch (PDOException $e) {
    error_log("RFID fetch error: " . $e->getMessage());
}
$performance->endTimer('rfid_query');

$rfid_tags = [];
if (!empty($user['rfid_tag'])) {
    $rfid_tags[] = [
        'tag_id' => 1,
        'tag_value' => $user['rfid_tag'],
        'is_active' => 1,
        'created_at' => $user['updated_at'] ?? 'N/A'
    ];
}

// Enhanced secure functions
function updateProfileSecure($db, $security, $userId, $data) {
    try {
        $rules = [
            'first_name' => ['type' => 'string', 'required' => true, 'min_length' => 1, 'max_length' => 50, 'label' => 'First name'],
            'last_name' => ['type' => 'string', 'required' => true, 'min_length' => 1, 'max_length' => 50, 'label' => 'Last name'],
            'email' => ['type' => 'email', 'required' => true, 'label' => 'Email'],
            'phone' => ['type' => 'phone', 'required' => false, 'label' => 'Phone number'],
            'bio' => ['type' => 'string', 'required' => false, 'max_length' => 500, 'label' => 'Bio']
        ];
        
        $validation = $security->validateInput($data, $rules);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => implode('; ', $validation['errors'])];
        }
        
        $validated = $validation['data'];
        
        // Check email uniqueness
        $existingUser = $security->checkDuplicateEmail($validated['email'], $userId);
        if ($existingUser) {
            return ['success' => false, 'message' => 'Email address is already in use by ' . $existingUser['name']];
        }
        
        // Update profile with transaction
        $db->beginTransaction();
        
        $stmt = $db->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, email = ?, phone = ?, bio = ?, updated_at = NOW()
            WHERE user_id = ?
        ");
        
        $success = $stmt->execute([
            $validated['first_name'],
            $validated['last_name'],
            $validated['email'],
            $validated['phone'],
            $validated['bio'],
            $userId
        ]);
        
        if (!$success) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Failed to update profile'];
        }
        
        $db->commit();
        
        return [
            'success' => true, 
            'message' => 'Profile updated successfully!',
            'updated_fields' => array_keys($validated)
        ];
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Update profile error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update profile. Please try again.'];
    }
}

function uploadAvatarSecure($db, $fileManager, $security, $userId) {
    try {
        if (!isset($_FILES['avatar'])) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }
        
        $uploadResult = $fileManager->uploadAvatar($_FILES['avatar'], $userId);
        if (!$uploadResult['success']) {
            return $uploadResult;
        }
        
        // Get current avatar to remove
        $stmt = $db->prepare("SELECT avatar FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $currentAvatar = $stmt->fetchColumn();
        
        // Update database
        $db->beginTransaction();
        
        $stmt = $db->prepare("UPDATE users SET avatar = ?, updated_at = NOW() WHERE user_id = ?");
        $success = $stmt->execute([$uploadResult['path'], $userId]);
        
        if (!$success) {
            $db->rollBack();
            $fileManager->deleteFile($uploadResult['path']);
            return ['success' => false, 'message' => 'Failed to update avatar in database'];
        }
        
        // Remove old avatar after successful update
        if ($currentAvatar) {
            $fileManager->deleteFile($currentAvatar);
        }
        
        $db->commit();
        
        return [
            'success' => true, 
            'message' => 'Avatar updated successfully!',
            'filename' => $uploadResult['filename']
        ];
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Upload avatar error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to upload avatar. Please try again.'];
    }
}

function removeAvatarSecure($db, $fileManager, $security, $user) {
    try {
        if (empty($user['avatar'])) {
            return ['success' => false, 'message' => 'No avatar to remove'];
        }
        
        $db->beginTransaction();
        
        $stmt = $db->prepare("UPDATE users SET avatar = NULL, updated_at = NOW() WHERE user_id = ?");
        $success = $stmt->execute([$user['user_id']]);
        
        if (!$success) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Failed to remove avatar from database'];
        }
        
        // Remove file after successful database update
        $fileManager->deleteFile($user['avatar']);
        
        $db->commit();
        
        return ['success' => true, 'message' => 'Avatar removed successfully!'];
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Remove avatar error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to remove avatar. Please try again.'];
    }
}

function addRFIDTagSecure($db, $security, $userId, $tagValue) {
    try {
        $rules = [
            'rfid_tag' => ['type' => 'rfid', 'required' => true, 'label' => 'RFID tag']
        ];
        
        $validation = $security->validateInput(['rfid_tag' => $tagValue], $rules);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => implode('; ', $validation['errors'])];
        }
        
        $tagValue = $validation['data']['rfid_tag'];
        
        // Check for existing tag
        $existingUser = $security->checkDuplicateRFID($tagValue, $userId);
        if ($existingUser) {
            return ['success' => false, 'message' => 'This RFID tag is already registered to ' . $existingUser['name']];
        }
        
        // Check if user already has a tag
        $stmt = $db->prepare("SELECT rfid_tag FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $currentTag = $stmt->fetchColumn();
        
        if (!empty($currentTag)) {
            return ['success' => false, 'message' => 'You already have an RFID tag registered. Remove the current one first.'];
        }
        
        // Add tag
        $db->beginTransaction();
        
        $stmt = $db->prepare("UPDATE users SET rfid_tag = ?, updated_at = NOW() WHERE user_id = ?");
        $success = $stmt->execute([$tagValue, $userId]);
        
        if (!$success) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Failed to add RFID tag'];
        }
        
        $db->commit();
        
        return [
            'success' => true, 
            'message' => 'RFID tag added successfully!',
            'tag' => $tagValue
        ];
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Add RFID tag error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to add RFID tag. Please try again.'];
    }
}

function removeRFIDTagSecure($db, $security, $userId) {
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("UPDATE users SET rfid_tag = NULL, updated_at = NOW() WHERE user_id = ?");
        $success = $stmt->execute([$userId]);
        
        if (!$success || $stmt->rowCount() === 0) {
            $db->rollBack();
            return ['success' => false, 'message' => 'No RFID tag found to remove'];
        }
        
        $db->commit();
        
        return ['success' => true, 'message' => 'RFID tag removed successfully!'];
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Remove RFID tag error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to remove RFID tag. Please try again.'];
    }
}

$performance->endTimer('profile_load');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="User profile management for Electronic Check-in System - manage personal information, avatar, RFID tags, and privacy settings">
    <meta name="keywords" content="profile, user management, RFID, avatar, settings, privacy">
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
    <meta name="apple-mobile-web-app-title" content="Profile">
    
    <title>Profile - <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?> | Electronic Check-in System</title>
    
    <!-- Optimized CSS Loading -->
    <link rel="preload" href="../assets/css/main.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="../assets/css/navigation.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="../assets/css/forms.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="../assets/css/dashboard.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="../assets/css/profile.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="../assets/css/main.css">
        <link rel="stylesheet" href="../assets/css/navigation.css">
        <link rel="stylesheet" href="../assets/css/forms.css">
        <link rel="stylesheet" href="../assets/css/dashboard.css">
        <link rel="stylesheet" href="../assets/css/profile.css">
    </noscript>
    
    <!-- Chart.js for Activity Visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js" defer></script>
    
    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "ProfilePage",
        "mainEntity": {
            "@type": "Person",
            "name": "<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>",
            "email": "<?php echo htmlspecialchars($user['email']); ?>",
            "memberOf": {
                "@type": "Organization",
                "name": "Electronic Check-in System"
            }
        }
    }
    </script>
    
    <style>
        .activity-chart-container {
            position: relative;
            height: 300px;
            margin: 1.5rem 0;
        }
        
        .enhanced-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .stat-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .privacy-controls {
            background: var(--bg-info);
            border-left: 4px solid var(--color-info);
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin: 1rem 0;
        }
        
        .activity-timeline {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            padding: 1rem;
        }
        
        .timeline-item {
            display: flex;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-light);
        }
        
        .timeline-item:last-child {
            border-bottom: none;
        }
        
        .upload-zone {
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-md);
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .upload-zone:hover {
            border-color: var(--color-primary);
            background: var(--bg-hover);
        }
        
        .upload-zone.dragover {
            border-color: var(--color-success);
            background: var(--bg-success-light);
        }
        
        @media (max-width: 768px) {
            .enhanced-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .tab-btn {
                font-size: 0.85rem;
                padding: 0.5rem;
            }
        }
        
        /* Accessibility Enhancements */
        @media (prefers-reduced-motion: reduce) {
            .stat-card {
                transition: none;
            }
            
            .upload-zone {
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
    </style>
</head>
<body>
    <!-- Skip to main content for accessibility -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-0 focus:left-0 bg-primary text-white p-2 z-50">
        Skip to main content
    </a>
    
    <?php include '../includes/navigation.php'; ?>
    
    <main id="main-content" class="main-content" role="main">
        <div class="profile-container">
            <!-- Profile Header with Enhanced Information -->
            <header class="profile-header" role="banner">
                <div class="avatar-section">
                    <div class="avatar-wrapper">
                        <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($user['avatar']); ?>" 
                                 alt="Profile avatar for <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>" 
                                 class="avatar"
                                 loading="lazy">
                        <?php else: ?>
                            <div class="avatar avatar-placeholder" 
                                 role="img" 
                                 aria-label="Default avatar with initials">
                                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="avatar-upload-zone upload-zone" onclick="document.getElementById('avatar-upload').click()" tabindex="0" role="button" aria-label="Upload new avatar">
                            <span class="upload-icon">📷</span>
                            <span class="upload-text">Click or drag to change avatar</span>
                        </div>
                    </div>
                    
                    <div class="avatar-controls">
                        <label for="avatar-upload" class="btn btn-secondary btn-sm">
                            <span aria-hidden="true">📷</span> Change Avatar
                        </label>
                        <?php if (!empty($user['avatar'])): ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove your avatar?')">
                                <input type="hidden" name="action" value="remove_avatar">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <button type="submit" class="btn btn-outline btn-sm">
                                    <span aria-hidden="true">🗑️</span> Remove
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></h1>
                    <p class="text-secondary">
                        <span class="sr-only">Email address: </span>
                        <?php echo htmlspecialchars($user['email'] ?? ''); ?>
                    </p>
                    <p class="text-muted">
                        <span class="sr-only">Role: </span>
                        <span class="badge badge-<?php echo strtolower($user['role']); ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </p>
                    <?php if (!empty($user['bio'])): ?>
                        <p class="text-muted user-bio"><?php echo htmlspecialchars($user['bio']); ?></p>
                    <?php endif; ?>
                    
                    <!-- Enhanced Statistics Grid -->
                    <div class="enhanced-stats-grid">
                        <div class="stat-card" tabindex="0">
                            <span class="stat-icon" aria-hidden="true">📊</span>
                            <div class="stat-number"><?php echo number_format($stats['total_checkins'] ?? 0); ?></div>
                            <div class="stat-label">Total Check-ins</div>
                        </div>
                        <div class="stat-card" tabindex="0">
                            <span class="stat-icon" aria-hidden="true">🎯</span>
                            <div class="stat-number"><?php echo number_format($stats['unique_events'] ?? 0); ?></div>
                            <div class="stat-label">Events Attended</div>
                        </div>
                        <div class="stat-card" tabindex="0">
                            <span class="stat-icon" aria-hidden="true">📅</span>
                            <div class="stat-number"><?php echo $stats['month_checkins'] ?? 0; ?></div>
                            <div class="stat-label">This Month</div>
                        </div>
                        <div class="stat-card" tabindex="0">
                            <span class="stat-icon" aria-hidden="true">⭐</span>
                            <div class="stat-number"><?php echo ($stats['first_checkin'] ?? null) ? date('M j, Y', strtotime($stats['first_checkin'])) : 'N/A'; ?></div>
                            <div class="stat-label">Member Since</div>
                        </div>
                        <div class="stat-card" tabindex="0">
                            <span class="stat-icon" aria-hidden="true">🕐</span>
                            <div class="stat-number"><?php echo ($stats['last_checkin'] ?? null) ? date('M j, Y', strtotime($stats['last_checkin'])) : 'Never'; ?></div>
                            <div class="stat-label">Last Check-in</div>
                        </div>
                        <div class="stat-card" tabindex="0">
                            <span class="stat-icon" aria-hidden="true">🔥</span>
                            <div class="stat-number"><?php echo $stats['week_checkins'] ?? 0; ?></div>
                            <div class="stat-label">This Week</div>
                        </div>
                    </div>
                </div>
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
            
            <!-- Avatar Upload Form -->
            <form method="POST" enctype="multipart/form-data" style="display: none;" aria-hidden="true">
                <input type="hidden" name="action" value="upload_avatar">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="file" name="avatar" id="avatar-upload" accept="image/*" onchange="this.form.submit()">
            </form>
            
            <!-- Enhanced Profile Tabs with Accessibility -->
            <div class="profile-tabs" role="tablist" aria-label="Profile sections">
                <button class="tab-btn active" 
                        onclick="showTab(event, 'profile-tab')" 
                        role="tab" 
                        aria-selected="true" 
                        aria-controls="profile-tab" 
                        id="profile-tab-btn">
                    <span aria-hidden="true">👤</span> Profile Information
                </button>
                <button class="tab-btn" 
                        onclick="showTab(event, 'activity-tab')" 
                        role="tab" 
                        aria-selected="false" 
                        aria-controls="activity-tab" 
                        id="activity-tab-btn">
                    <span aria-hidden="true">📊</span> Activity Analytics
                </button>
                <button class="tab-btn" 
                        onclick="showTab(event, 'groups-tab')" 
                        role="tab" 
                        aria-selected="false" 
                        aria-controls="groups-tab" 
                        id="groups-tab-btn">
                    <span aria-hidden="true">🏢</span> My Groups
                </button>
                <button class="tab-btn" 
                        onclick="showTab(event, 'rfid-tab')" 
                        role="tab" 
                        aria-selected="false" 
                        aria-controls="rfid-tab" 
                        id="rfid-tab-btn">
                    <span aria-hidden="true">📟</span> RFID Tags
                </button>
                <button class="tab-btn" 
                        onclick="showTab(event, 'privacy-tab')" 
                        role="tab" 
                        aria-selected="false" 
                        aria-controls="privacy-tab" 
                        id="privacy-tab-btn">
                    <span aria-hidden="true">🔒</span> Privacy & Security
                </button>
            </div>
            
            <!-- Profile Information Tab -->
            <section id="profile-tab" class="tab-content active" role="tabpanel" aria-labelledby="profile-tab-btn">
                <div class="section-card">
                    <h2 class="section-title">
                        <span aria-hidden="true">👤</span> Profile Information
                    </h2>
                    
                    <form method="POST" novalidate aria-describedby="profile-form-help">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        
                        <div id="profile-form-help" class="form-help">
                            <p>Update your personal information. Fields marked with * are required.</p>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">
                                    First Name *
                                    <span class="sr-only">(required)</span>
                                </label>
                                <input type="text" 
                                       id="first_name" 
                                       name="first_name" 
                                       value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" 
                                       required
                                       minlength="1"
                                       maxlength="50"
                                       aria-describedby="first_name_help">
                                <div id="first_name_help" class="field-help">Enter your first name</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name">
                                    Last Name *
                                    <span class="sr-only">(required)</span>
                                </label>
                                <input type="text" 
                                       id="last_name" 
                                       name="last_name" 
                                       value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" 
                                       required
                                       minlength="1"
                                       maxlength="50"
                                       aria-describedby="last_name_help">
                                <div id="last_name_help" class="field-help">Enter your last name</div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">
                                    Email Address *
                                    <span class="sr-only">(required)</span>
                                </label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                       required
                                       aria-describedby="email_help">
                                <div id="email_help" class="field-help">Your email address for notifications and login</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" 
                                       id="phone" 
                                       name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                       placeholder="+1 (555) 123-4567"
                                       pattern="[\d\s\-\+\(\)]+"
                                       aria-describedby="phone_help">
                                <div id="phone_help" class="field-help">Optional: Your contact phone number</div>
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="bio">Bio</label>
                            <textarea id="bio" 
                                      name="bio" 
                                      rows="3" 
                                      maxlength="500"
                                      placeholder="Tell us a bit about yourself..."
                                      aria-describedby="bio_help"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            <div id="bio_help" class="field-help">Optional: A brief description about yourself (max 500 characters)</div>
                        </div>
                        
                        <div class="form-group full-width">
                            <button type="submit" class="btn btn-primary">
                                <span aria-hidden="true">💾</span> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </section>
            
            <!-- Activity Analytics Tab -->
            <section id="activity-tab" class="tab-content" role="tabpanel" aria-labelledby="activity-tab-btn">
                <div class="section-card">
                    <h2 class="section-title">
                        <span aria-hidden="true">📊</span> Activity Analytics
                    </h2>
                    
                    <!-- Activity Chart -->
                    <div class="activity-chart-container">
                        <canvas id="activityChart" aria-label="7-day activity chart showing check-ins over time"></canvas>
                    </div>
                    
                    <!-- Favorite Events -->
                    <?php if (!empty($stats['favorite_events'])): ?>
                        <div class="favorite-events-section">
                            <h3>
                                <span aria-hidden="true">⭐</span> Most Attended Events
                            </h3>
                            <div class="events-list">
                                <?php foreach ($stats['favorite_events'] as $event): ?>
                                    <div class="event-item">
                                        <span class="event-name"><?php echo htmlspecialchars($event['event_name']); ?></span>
                                        <span class="event-count">
                                            <?php echo number_format($event['checkin_count']); ?> check-ins
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Activity Summary -->
                    <div class="activity-summary">
                        <h3>
                            <span aria-hidden="true">📈</span> Activity Summary
                        </h3>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <strong>Daily Average:</strong>
                                <?php echo number_format(($stats['total_checkins'] ?? 0) / max(1, date_diff(date_create($stats['first_checkin'] ?? 'now'), date_create('now'))->days ?: 1), 2); ?>
                            </div>
                            <div class="summary-item">
                                <strong>Most Active Day:</strong>
                                <?php 
                                $maxActivity = 0;
                                $maxDay = 'N/A';
                                foreach ($stats['activity_trend'] as $day) {
                                    if ($day['checkins'] > $maxActivity) {
                                        $maxActivity = $day['checkins'];
                                        $maxDay = date('M j', strtotime($day['date']));
                                    }
                                }
                                echo htmlspecialchars($maxDay);
                                ?>
                            </div>
                            <div class="summary-item">
                                <strong>Streak:</strong>
                                Coming Soon
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- User Groups Tab -->
            <section id="groups-tab" class="tab-content" role="tabpanel" aria-labelledby="groups-tab-btn">
                <div class="section-card">
                    <h2 class="section-title">
                        <span aria-hidden="true">🏢</span> My Groups
                    </h2>
                    <p class="text-secondary">Groups you belong to and their details</p>
                    
                    <?php 
                    $userGroups = $groupManager->getUserGroups($user['user_id']);
                    if (empty($userGroups)): 
                    ?>
                        <div class="empty-state" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            <div style="font-size: 3rem; margin-bottom: 1rem;" aria-hidden="true">👥</div>
                            <h3>No Group Memberships</h3>
                            <p>You are not currently a member of any groups.</p>
                            <p class="text-muted">Contact your administrator to be added to relevant groups.</p>
                        </div>
                    <?php else: ?>
                        <div class="groups-grid" style="display: grid; gap: 1rem; margin-top: 1.5rem;">
                            <?php foreach ($userGroups as $index => $group): ?>
                                <article class="group-item" 
                                         style="padding: 1rem; border: 1px solid var(--border-light); border-radius: var(--radius-sm); background: var(--bg-primary);"
                                         tabindex="0"
                                         role="article"
                                         aria-label="Group: <?php echo htmlspecialchars($group['group_name']); ?>">
                                    <header style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                        <h4 style="margin: 0; color: var(--text-primary);">
                                            <?php echo htmlspecialchars($group['group_name']); ?>
                                        </h4>
                                        <span class="badge" 
                                              style="background: var(--bg-accent); color: var(--text-accent); padding: 0.25rem 0.5rem; border-radius: var(--radius-xs); font-size: 0.75rem;"
                                              role="status"
                                              aria-label="Role: <?php echo ucfirst($group['role'] ?? 'member'); ?>">
                                            <?php echo ucfirst($group['role'] ?? 'member'); ?>
                                        </span>
                                    </header>
                                    
                                    <?php if (!empty($group['description'])): ?>
                                        <p style="margin: 0.5rem 0; color: var(--text-secondary); font-size: 0.9rem;">
                                            <?php echo htmlspecialchars($group['description']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <footer style="display: flex; gap: 1rem; margin-top: 0.75rem; font-size: 0.85rem; color: var(--text-muted);">
                                        <span>
                                            <span aria-hidden="true">📂</span> 
                                            <span class="sr-only">Type: </span>
                                            <?php echo ucfirst($group['group_type'] ?? 'custom'); ?>
                                        </span>
                                        <span>
                                            <span aria-hidden="true">👥</span> 
                                            <span class="sr-only">Members: </span>
                                            <?php echo number_format($group['member_count'] ?? 0); ?> members
                                        </span>
                                        <span>
                                            <span aria-hidden="true">📅</span> 
                                            <span class="sr-only">Joined: </span>
                                            Joined <?php echo date('M j, Y', strtotime($group['joined_at'])); ?>
                                        </span>
                                    </footer>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="groups-statistics" style="margin-top: 2rem; padding: 1rem; background: var(--bg-info); border-left: 4px solid var(--color-info); border-radius: var(--radius-sm);">
                            <h4 style="margin: 0 0 0.5rem 0; color: var(--text-info);">
                                <span aria-hidden="true">📊</span> Group Statistics
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 1rem; margin-top: 1rem;">
                                <div style="text-align: center;">
                                    <div style="font-size: 1.5rem; font-weight: bold; color: var(--color-primary);">
                                        <?php echo count($userGroups); ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--text-muted);">Total Groups</div>
                                </div>
                                <div style="text-align: center;">
                                    <div style="font-size: 1.5rem; font-weight: bold; color: var(--color-success);">
                                        <?php echo count(array_filter($userGroups, fn($g) => $g['role'] === 'admin')); ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--text-muted);">Admin Roles</div>
                                </div>
                                <div style="text-align: center;">
                                    <div style="font-size: 1.5rem; font-weight: bold; color: var(--color-warning);">
                                        <?php echo count(array_filter($userGroups, fn($g) => $g['role'] === 'leader')); ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--text-muted);">Leader Roles</div>
                                </div>
                                <div style="text-align: center;">
                                    <div style="font-size: 1.5rem; font-weight: bold; color: var(--color-info);">
                                        <?php echo count(array_filter($userGroups, fn($g) => $g['role'] === 'member')); ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--text-muted);">Member Roles</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            
            <!-- RFID Tags Tab -->
            <section id="rfid-tab" class="tab-content" role="tabpanel" aria-labelledby="rfid-tab-btn">
                <div class="section-card">
                    <h2 class="section-title">
                        <span aria-hidden="true">📟</span> RFID Tags
                    </h2>
                    <p class="text-secondary">Manage your RFID tags for quick check-ins</p>
                    
                    <!-- Add New Tag -->
                    <div class="rfid-add-section" style="margin-bottom: 2rem; padding: 1.5rem; background: var(--bg-primary); border-radius: var(--radius-sm);">
                        <h3>
                            <span aria-hidden="true">➕</span> Add New RFID Tag
                        </h3>
                        <form method="POST" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;" aria-describedby="rfid-form-help">
                            <input type="hidden" name="action" value="add_rfid">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            
                            <div id="rfid-form-help" class="form-help">
                                <p>Enter your RFID tag value (8-16 hexadecimal characters)</p>
                            </div>
                            
                            <div class="form-group" style="flex: 1; min-width: 200px;">
                                <label for="rfid_tag">
                                    RFID Tag Value
                                    <span class="sr-only">(8-16 hexadecimal characters)</span>
                                </label>
                                <input type="text" 
                                       id="rfid_tag" 
                                       name="rfid_tag" 
                                       placeholder="e.g., A1B2C3D4E5F6" 
                                       pattern="[A-Fa-f0-9]{8,16}"
                                       title="8-16 hexadecimal characters (0-9, A-F)"
                                       maxlength="16"
                                       required
                                       aria-describedby="rfid_tag_help"
                                       style="text-transform: uppercase;">
                                <div id="rfid_tag_help" class="field-help">
                                    Enter the hexadecimal value from your RFID tag
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <span aria-hidden="true">➕</span> Add Tag
                            </button>
                        </form>
                    </div>
                    
                    <!-- Existing Tags -->
                    <?php if (!empty($rfid_tags)): ?>
                        <div class="rfid-list" role="list">
                            <h3>
                                <span aria-hidden="true">📋</span> Current RFID Tags
                            </h3>
                            <?php foreach ($rfid_tags as $tag): ?>
                                <article class="rfid-item" 
                                         style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border: 1px solid var(--border-light); border-radius: var(--radius-sm); margin-bottom: 0.5rem; background: var(--bg-primary);"
                                         role="listitem"
                                         tabindex="0"
                                         aria-label="RFID Tag: <?php echo htmlspecialchars($tag['tag_value']); ?>">
                                    <div class="rfid-info">
                                        <div class="rfid-tag" style="font-family: monospace; font-size: 1.1rem; font-weight: bold; color: var(--color-primary);">
                                            <?php echo htmlspecialchars($tag['tag_value']); ?>
                                        </div>
                                        <div class="rfid-meta" style="font-size: 0.85rem; color: var(--text-muted);">
                                            <span class="sr-only">Added: </span>
                                            Added <?php echo ($tag['created_at'] !== 'N/A') ? date('M j, Y', strtotime($tag['created_at'])) : 'N/A'; ?>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                        <span class="status-badge <?php echo $tag['is_active'] ? 'status-active' : 'status-inactive'; ?>"
                                              role="status"
                                              aria-label="Status: <?php echo $tag['is_active'] ? 'Active' : 'Inactive'; ?>">
                                            <?php echo $tag['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                        
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this RFID tag?')">
                                            <input type="hidden" name="action" value="remove_rfid">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <button type="submit" 
                                                    class="btn btn-outline btn-sm"
                                                    aria-label="Remove RFID tag <?php echo htmlspecialchars($tag['tag_value']); ?>">
                                                <span aria-hidden="true">🗑️</span> Remove
                                            </button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                            <div style="font-size: 3rem; margin-bottom: 1rem;" aria-hidden="true">📟</div>
                            <h3>No RFID Tags</h3>
                            <p>Add an RFID tag to enable quick check-ins without manual input.</p>
                            <p class="text-muted">RFID tags provide convenient, contactless check-in experience.</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="rfid-help" style="margin-top: 2rem; padding: 1rem; background: var(--bg-warning); border-left: 4px solid var(--color-warning); border-radius: var(--radius-sm);">
                        <h4 style="margin: 0 0 0.5rem 0; color: var(--text-warning);">
                            <span aria-hidden="true">💡</span> RFID Tag Information
                        </h4>
                        <ul style="margin: 0; padding-left: 1.5rem;">
                            <li>RFID tags allow quick check-ins by simply scanning your tag</li>
                            <li>Each user can have only one active RFID tag at a time</li>
                            <li>Tag values must be 8-16 hexadecimal characters (0-9, A-F)</li>
                            <li>Contact system administrators if you need help locating your tag value</li>
                            <li>Keep your RFID tag secure - it provides access to your account</li>
                        </ul>
                    </div>
                </div>
            </section>
            
            <!-- Privacy & Security Tab -->
            <section id="privacy-tab" class="tab-content" role="tabpanel" aria-labelledby="privacy-tab-btn">
                <div class="section-card">
                    <h2 class="section-title">
                        <span aria-hidden="true">🔒</span> Privacy & Security Settings
                    </h2>
                    
                    <!-- Privacy Controls -->
                    <div class="privacy-controls">
                        <h3>
                            <span aria-hidden="true">🛡️</span> Privacy Preferences
                        </h3>
                        <p>Control how your information is shared and displayed in the system.</p>
                        
                        <div class="privacy-options">
                            <div class="privacy-option">
                                <label class="toggle-label">
                                    <input type="checkbox" class="toggle-input" checked disabled>
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-text">Profile visibility to group members</span>
                                </label>
                                <p class="option-description">Allow other members in your groups to see your basic profile information.</p>
                            </div>
                            
                            <div class="privacy-option">
                                <label class="toggle-label">
                                    <input type="checkbox" class="toggle-input" checked disabled>
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-text">Check-in activity visibility</span>
                                </label>
                                <p class="option-description">Show your check-in activity in group statistics and leaderboards.</p>
                            </div>
                            
                            <div class="privacy-option">
                                <label class="toggle-label">
                                    <input type="checkbox" class="toggle-input" disabled>
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-text">Email notifications</span>
                                </label>
                                <p class="option-description">Receive email notifications about events and system updates.</p>
                            </div>
                        </div>
                        
                        <p class="text-muted" style="margin-top: 1rem;">
                            <em>Privacy controls are currently in development. Contact administrators for specific privacy requests.</em>
                        </p>
                    </div>
                    
                    <!-- Password Change -->
                    <div class="security-section" style="margin-top: 2rem;">
                        <h3>
                            <span aria-hidden="true">🔑</span> Password Security
                        </h3>
                        <p class="text-secondary">Update your password to keep your account secure.</p>
                        <a href="../frontend/account-settings.php" class="btn btn-primary">
                            <span aria-hidden="true">🔑</span> Change Password
                        </a>
                    </div>
                    
                    <!-- Account Information -->
                    <div class="account-info-section" style="margin-top: 2rem;">
                        <h3>
                            <span aria-hidden="true">ℹ️</span> Account Information
                        </h3>
                        <div class="info-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem;">
                            <div class="info-item">
                                <strong>User ID:</strong>
                                <span class="monospace">#<?php echo htmlspecialchars($user['user_id']); ?></span>
                            </div>
                            <div class="info-item">
                                <strong>Role:</strong> 
                                <span class="badge badge-<?php echo strtolower($user['role']); ?>">
                                    <?php echo ucfirst($user['role'] ?? 'user'); ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <strong>Account Created:</strong>
                                <time datetime="<?php echo $user['created_at']; ?>">
                                    <?php echo ($user['created_at'] ?? null) ? date('M j, Y \a\t g:i A', strtotime($user['created_at'])) : 'N/A'; ?>
                                </time>
                            </div>
                            <div class="info-item">
                                <strong>Last Updated:</strong>
                                <time datetime="<?php echo $user['updated_at']; ?>">
                                    <?php echo ($user['updated_at'] ?? null) ? date('M j, Y \a\t g:i A', strtotime($user['updated_at'])) : 'Never'; ?>
                                </time>
                            </div>
                            <div class="info-item">
                                <strong>Last Login:</strong>
                                <time datetime="<?php echo $user['last_login']; ?>">
                                    <?php echo ($user['last_login'] ?? null) ? date('M j, Y \a\t g:i A', strtotime($user['last_login'])) : 'N/A'; ?>
                                </time>
                            </div>
                            <div class="info-item">
                                <strong>Login Count:</strong>
                                <span><?php echo number_format($user['login_count'] ?? 0); ?> times</span>
                            </div>
                            <div class="info-item">
                                <strong>Account Status:</strong>
                                <span class="status-badge <?php echo ($user['is_active'] ?? 1) ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo ($user['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <strong>Two-Factor Auth:</strong>
                                <span class="status-badge status-inactive">Not Enabled</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Session Information -->
                    <div class="session-info-section" style="margin-top: 2rem;">
                        <h3>
                            <span aria-hidden="true">💻</span> Current Session
                        </h3>
                        <div class="session-details" style="background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-sm); margin-top: 1rem;">
                            <div class="session-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                                <div>
                                    <strong>IP Address:</strong>
                                    <span class="monospace"><?php echo htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'unknown'); ?></span>
                                </div>
                                <div>
                                    <strong>Session Started:</strong>
                                    <time><?php echo date('M j, Y \a\t g:i A'); ?></time>
                                </div>
                                <div>
                                    <strong>Browser:</strong>
                                    <span style="font-size: 0.9rem;"><?php echo htmlspecialchars(substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 50)); ?>...</span>
                                </div>
                                <div>
                                    <strong>Security:</strong>
                                    <span class="status-badge status-active">Secure</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Data Export -->
                    <div class="data-export-section" style="margin-top: 2rem;">
                        <h3>
                            <span aria-hidden="true">📤</span> Data Export
                        </h3>
                        <p class="text-secondary">Download your personal data and activity history.</p>
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1rem;">
                            <button class="btn btn-outline" onclick="alert('Data export feature coming soon!')">
                                <span aria-hidden="true">📊</span> Export Profile Data
                            </button>
                            <button class="btn btn-outline" onclick="alert('Activity export feature coming soon!')">
                                <span aria-hidden="true">📈</span> Export Activity History
                            </button>
                        </div>
                    </div>
                    
                    <!-- Danger Zone -->
                    <div class="danger-zone" style="border-top: 2px solid var(--error-color); padding-top: 2rem; margin-top: 2rem;">
                        <h3 style="color: var(--error-color);">
                            <span aria-hidden="true">⚠️</span> Danger Zone
                        </h3>
                        <p class="text-secondary">Irreversible actions that affect your account.</p>
                        
                        <div class="danger-actions" style="margin-top: 1rem;">
                            <button class="btn btn-error" 
                                    onclick="showDeleteAccountModal()"
                                    aria-describedby="delete-account-warning">
                                <span aria-hidden="true">🗑️</span> Delete Account
                            </button>
                            <p id="delete-account-warning" class="text-muted" style="margin-top: 0.5rem; font-size: 0.85rem;">
                                This will permanently delete your account and all associated data. This action cannot be undone.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- Performance Metrics (Debug Mode) -->
    <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
        <div class="debug-panel" style="position: fixed; bottom: 0; right: 0; background: var(--bg-dark); color: var(--text-light); padding: 1rem; border-radius: var(--radius-sm) 0 0 0; font-size: 0.75rem; z-index: 9999;">
            <h4>Performance Metrics</h4>
            <?php 
            $metrics = $performance->getMetrics();
            foreach ($metrics as $key => $value): 
            ?>
                <div><?php echo htmlspecialchars($key); ?>: <?php echo is_numeric($value) ? number_format($value, 4) : htmlspecialchars(print_r($value, true)); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Enhanced JavaScript with Accessibility and Functionality -->
    <script>
        // Global variables
        let activityChart = null;
        
        // Tab Management with Accessibility
        function showTab(event, tabId) {
            // Update ARIA attributes for all tabs
            const tabBtns = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabBtns.forEach(btn => {
                btn.setAttribute('aria-selected', 'false');
                btn.classList.remove('active');
            });
            
            tabContents.forEach(content => {
                content.classList.remove('active');
            });
            
            // Activate selected tab
            event.target.setAttribute('aria-selected', 'true');
            event.target.classList.add('active');
            document.getElementById(tabId).classList.add('active');
            
            // Load tab-specific content
            if (tabId === 'activity-tab' && !activityChart) {
                initializeActivityChart();
            }
            
            // Focus management for accessibility
            document.getElementById(tabId).focus();
        }
        
        // Activity Chart Initialization
        function initializeActivityChart() {
            const ctx = document.getElementById('activityChart');
            if (!ctx) return;
            
            const activityData = <?php echo json_encode($stats['activity_trend']); ?>;
            
            const labels = [];
            const checkinData = [];
            const eventData = [];
            
            // Prepare data for last 7 days
            for (let i = 6; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                const dateStr = date.toISOString().split('T')[0];
                
                labels.push(date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }));
                
                const dayData = activityData.find(d => d.date === dateStr);
                checkinData.push(dayData ? parseInt(dayData.checkins) : 0);
                eventData.push(dayData ? parseInt(dayData.events) : 0);
            }
            
            activityChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Check-ins',
                        data: checkinData,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1,
                        fill: true
                    }, {
                        label: 'Events',
                        data: eventData,
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: '7-Day Activity Overview'
                        },
                        legend: {
                            display: true
                        }
                    },
                    accessibility: {
                        enabled: true
                    }
                }
            });
        }
        
        // Enhanced form validation
        function validateForm(form) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('error');
                    field.setAttribute('aria-invalid', 'true');
                    isValid = false;
                } else {
                    field.classList.remove('error');
                    field.setAttribute('aria-invalid', 'false');
                }
            });
            
            return isValid;
        }
        
        // RFID input formatting
        function formatRFIDInput(input) {
            input.addEventListener('input', function() {
                this.value = this.value.toUpperCase().replace(/[^A-F0-9]/g, '');
                
                if (this.value.length > 16) {
                    this.value = this.value.substring(0, 16);
                }
                
                // Real-time validation feedback
                const isValid = /^[A-F0-9]{8,16}$/.test(this.value);
                this.classList.toggle('valid', isValid && this.value.length >= 8);
                this.classList.toggle('error', this.value.length > 0 && !isValid);
            });
        }
        
        // Avatar upload with drag & drop
        function initializeAvatarUpload() {
            const uploadZone = document.querySelector('.upload-zone');
            const fileInput = document.getElementById('avatar-upload');
            
            if (!uploadZone || !fileInput) return;
            
            uploadZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            
            uploadZone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });
            
            uploadZone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    fileInput.form.submit();
                }
            });
            
            // Keyboard accessibility
            uploadZone.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    fileInput.click();
                }
            });
        }
        
        // Delete account modal
        function showDeleteAccountModal() {
            const confirmed = confirm(
                'Are you absolutely sure you want to delete your account?\n\n' +
                'This action will:\n' +
                '• Permanently delete all your personal data\n' +
                '• Remove all your check-in history\n' +
                '• Remove you from all groups\n' +
                '• Cannot be undone\n\n' +
                'Type "DELETE" in the next prompt to confirm.'
            );
            
            if (confirmed) {
                const confirmation = prompt('Type "DELETE" to confirm account deletion:');
                if (confirmation === 'DELETE') {
                    alert('Account deletion is not implemented yet. Please contact an administrator.');
                } else {
                    alert('Account deletion cancelled - confirmation text did not match.');
                }
            }
        }
        
        // Initialize on DOM ready
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize form validation
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!validateForm(this)) {
                        e.preventDefault();
                        // Focus first invalid field
                        const firstError = this.querySelector('.error, [aria-invalid="true"]');
                        if (firstError) {
                            firstError.focus();
                        }
                    }
                });
            });
            
            // Initialize RFID input formatting
            const rfidInput = document.getElementById('rfid_tag');
            if (rfidInput) {
                formatRFIDInput(rfidInput);
            }
            
            // Initialize avatar upload
            initializeAvatarUpload();
            
            // Initialize activity chart if activity tab is visible
            if (document.getElementById('activity-tab').classList.contains('active')) {
                initializeActivityChart();
            }
            
            // Keyboard navigation for tabs
            const tabBtns = document.querySelectorAll('.tab-btn');
            tabBtns.forEach((btn, index) => {
                btn.addEventListener('keydown', function(e) {
                    let targetIndex = index;
                    
                    switch (e.key) {
                        case 'ArrowRight':
                            targetIndex = (index + 1) % tabBtns.length;
                            break;
                        case 'ArrowLeft':
                            targetIndex = (index - 1 + tabBtns.length) % tabBtns.length;
                            break;
                        case 'Home':
                            targetIndex = 0;
                            break;
                        case 'End':
                            targetIndex = tabBtns.length - 1;
                            break;
                        default:
                            return;
                    }
                    
                    e.preventDefault();
                    tabBtns[targetIndex].click();
                    tabBtns[targetIndex].focus();
                });
            });
            
            // Auto-save form data
            const profileForm = document.querySelector('form[method="POST"]');
            if (profileForm) {
                const inputs = profileForm.querySelectorAll('input, textarea');
                inputs.forEach(input => {
                    input.addEventListener('input', function() {
                        // Save to localStorage for recovery
                        localStorage.setItem('profile_' + this.name, this.value);
                    });
                    
                    // Restore saved data
                    const saved = localStorage.getItem('profile_' + input.name);
                    if (saved && !input.value) {
                        input.value = saved;
                    }
                });
                
                // Clear saved data on successful submit
                profileForm.addEventListener('submit', function() {
                    inputs.forEach(input => {
                        localStorage.removeItem('profile_' + input.name);
                    });
                });
            }
            
            console.log('Profile page initialized with enhanced accessibility and functionality');
        });
        
        // Performance monitoring
        window.addEventListener('load', function() {
            if (performance.timing) {
                const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
                console.log('Page load time:', loadTime + 'ms');
            }
        });
    </script>

    <?php include '../includes/theme_script.php'; ?>
</body>
</html>
