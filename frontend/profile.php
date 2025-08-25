<?php
/**
 * User Profile Management
 * Profile settings, avatar upload, RFID tag management
 */

require_once '../core/auth.php';
require_once '../core/database.php';

Auth::requireLogin();
$user = Auth::getCurrentUser(true); // Force refresh to get complete user data
$db = getDB();

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
    'updated_at' => null
], $user ?? []);

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $result = updateProfile($db, $user['user_id'], $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                    // Refresh user data
                    $user = Auth::getCurrentUser(true);
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'upload_avatar':
                $result = uploadAvatar($user['user_id']);
                if ($result['success']) {
                    $message = $result['message'];
                    // Refresh user data
                    $user = Auth::getCurrentUser(true);
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'remove_avatar':
                $result = removeAvatar($db, $user['user_id']);
                if ($result['success']) {
                    $message = $result['message'];
                    // Refresh user data
                    $user = Auth::getCurrentUser(true);
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'add_rfid':
                $result = addRFIDTag($db, $user['user_id'], $_POST['rfid_tag']);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'remove_rfid':
                $result = removeRFIDTag($db, $user['user_id']);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
        }
    }
}

// Get user's RFID tag (stored in Users table)
try {
    $stmt = $db->prepare("SELECT rfid_tag FROM Users WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    $user_rfid = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("RFID fetch error: " . $e->getMessage());
    $user_rfid = ['rfid_tag' => null];
}

$rfid_tags = []; // Initialize empty array for compatibility

// If user has an RFID tag, format it for display
if (!empty($user_rfid['rfid_tag'])) {
    $rfid_tags[] = [
        'tag_id' => 1, // Dummy ID for compatibility
        'tag_value' => $user_rfid['rfid_tag'],
        'is_active' => 1,
        'created_at' => 'N/A' // We don't track creation date for RFID tags in Users table
    ];
}

// Get user statistics
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_checkins,
            COUNT(CASE WHEN MONTH(checkin_time) = MONTH(CURRENT_DATE()) 
                  AND YEAR(checkin_time) = YEAR(CURRENT_DATE()) THEN 1 END) as month_checkins,
            COUNT(DISTINCT event_id) as unique_events,
            MIN(checkin_time) as first_checkin,
            MAX(checkin_time) as last_checkin
        FROM CheckIn 
        WHERE user_id = ?
    ");
    $stmt->execute([$user['user_id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Statistics fetch error: " . $e->getMessage());
    $stats = [
        'total_checkins' => 0,
        'month_checkins' => 0,
        'unique_events' => 0,
        'first_checkin' => null,
        'last_checkin' => null
    ];
}

function updateProfile($db, $user_id, $data) {
    try {
        $first_name = trim($data['first_name'] ?? '');
        $last_name = trim($data['last_name'] ?? '');
        $email = trim($data['email'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $bio = trim($data['bio'] ?? '');
        
        // Validation
        if (empty($first_name) || empty($last_name) || empty($email)) {
            return ['success' => false, 'message' => 'First name, last name, and email are required.'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid email address.'];
        }
        
        if (!empty($phone) && !preg_match('/^[\d\s\-\+\(\)]+$/', $phone)) {
            return ['success' => false, 'message' => 'Please enter a valid phone number.'];
        }
        
        // Check if email is already taken by another user
        $stmt = $db->prepare("SELECT user_id FROM Users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email address is already in use by another account.'];
        }
        
        // Update profile
        $stmt = $db->prepare("
            UPDATE Users 
            SET first_name = ?, last_name = ?, email = ?, phone = ?, bio = ?, updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([$first_name, $last_name, $email, $phone, $bio, $user_id]);
        
        return ['success' => true, 'message' => 'Profile updated successfully!'];
        
    } catch (Exception $e) {
        error_log("Update profile error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update profile. Please try again.'];
    }
}

function uploadAvatar($user_id) {
    try {
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'No file uploaded or upload error occurred.'];
        }
        
        $file = $_FILES['avatar'];
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed_types)) {
            return ['success' => false, 'message' => 'Only JPEG, PNG, and GIF images are allowed.'];
        }
        
        // Validate file size (5MB max)
        if ($file['size'] > 5 * 1024 * 1024) {
            return ['success' => false, 'message' => 'File size must be less than 5MB.'];
        }
        
        // Create uploads directory if it doesn't exist
        $uploads_dir = 'uploads/avatars';
        if (!is_dir($uploads_dir)) {
            mkdir($uploads_dir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'user_' . $user_id . '_' . time() . '.' . $extension;
        $upload_path = $uploads_dir . '/' . $filename;
        
        // Remove old avatar if exists
        $db = getDB();
        $stmt = $db->prepare("SELECT avatar FROM Users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $current_avatar = $stmt->fetchColumn();
        
        if ($current_avatar && file_exists($current_avatar)) {
            unlink($current_avatar);
        }
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            return ['success' => false, 'message' => 'Failed to save uploaded file.'];
        }
        
        // Update database
        $stmt = $db->prepare("UPDATE Users SET avatar = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->execute([$upload_path, $user_id]);
        
        return ['success' => true, 'message' => 'Avatar updated successfully!'];
        
    } catch (Exception $e) {
        error_log("Upload avatar error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to upload avatar. Please try again.'];
    }
}

function removeAvatar($db, $user_id) {
    try {
        $stmt = $db->prepare("SELECT avatar FROM Users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $avatar = $stmt->fetchColumn();
        
        if ($avatar && file_exists($avatar)) {
            unlink($avatar);
        }
        
        $stmt = $db->prepare("UPDATE Users SET avatar = NULL, updated_at = NOW() WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        return ['success' => true, 'message' => 'Avatar removed successfully!'];
        
    } catch (Exception $e) {
        error_log("Remove avatar error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to remove avatar. Please try again.'];
    }
}

function addRFIDTag($db, $user_id, $tag_value) {
    try {
        $tag_value = trim($tag_value);
        
        if (empty($tag_value)) {
            return ['success' => false, 'message' => 'RFID tag value is required.'];
        }
        
        // Validate tag format (basic validation)
        if (!preg_match('/^[a-fA-F0-9]{8,16}$/', $tag_value)) {
            return ['success' => false, 'message' => 'RFID tag must be 8-16 hexadecimal characters.'];
        }
        
        // Check if tag already exists (in Users table)
        $stmt = $db->prepare("SELECT user_id, CONCAT(first_name, ' ', COALESCE(last_name, '')) as name FROM Users WHERE rfid_tag = ? AND user_id != ?");
        $stmt->execute([$tag_value, $user_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            return ['success' => false, 'message' => 'This RFID tag is already registered to another user: ' . $existing['name']];
        }
        
        // Check if user already has an RFID tag
        $stmt = $db->prepare("SELECT rfid_tag FROM Users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $current_tag = $stmt->fetch();
        
        if (!empty($current_tag['rfid_tag'])) {
            return ['success' => false, 'message' => 'You already have an RFID tag registered. Remove the current one first.'];
        }
        
        // Update user's RFID tag in Users table
        $stmt = $db->prepare("UPDATE Users SET rfid_tag = ? WHERE user_id = ?");
        $stmt->execute([$tag_value, $user_id]);
        
        return ['success' => true, 'message' => 'RFID tag added successfully!'];
        
    } catch (Exception $e) {
        error_log("Add RFID tag error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to add RFID tag. Please try again.'];
    }
}

function removeRFIDTag($db, $user_id) {
    try {
        // Remove RFID tag from Users table (set to NULL)
        $stmt = $db->prepare("UPDATE Users SET rfid_tag = NULL WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'RFID tag removed successfully!'];
        } else {
            return ['success' => false, 'message' => 'No RFID tag found to remove.'];
        }
        
    } catch (Exception $e) {
        error_log("Remove RFID tag error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to remove RFID tag. Please try again.'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Electronic Check-in System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/profile.css">
    <style>
        /* Page-specific overrides if needed */
    </style>
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="main-content">
        <div class="profile-container">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="avatar-section">
                    <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                        <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar" class="avatar">
                    <?php else: ?>
                        <div class="avatar avatar-placeholder">
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="avatar-controls">
                        <label for="avatar-upload" class="btn btn-secondary btn-sm">
                            📷 Change Avatar
                        </label>
                        <?php if (!empty($user['avatar'])): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="remove_avatar">
                                <button type="submit" class="btn btn-outline btn-sm" onclick="return confirm('Remove avatar?')">
                                    🗑️ Remove
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <h1><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></h1>
                <p class="text-secondary"><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                <?php if (!empty($user['bio'])): ?>
                    <p class="text-muted"><?php echo htmlspecialchars($user['bio']); ?></p>
                <?php endif; ?>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total_checkins'] ?? 0; ?></div>
                        <div class="stat-label">Total Check-ins</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['unique_events'] ?? 0; ?></div>
                        <div class="stat-label">Events Attended</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo ($stats['first_checkin'] ?? null) ? date('M j, Y', strtotime($stats['first_checkin'])) : 'N/A'; ?></div>
                        <div class="stat-label">Member Since</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo ($stats['last_checkin'] ?? null) ? date('M j, Y', strtotime($stats['last_checkin'])) : 'Never'; ?></div>
                        <div class="stat-label">Last Check-in</div>
                    </div>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- Avatar Upload Form -->
            <form method="POST" enctype="multipart/form-data" style="display: none;">
                <input type="hidden" name="action" value="upload_avatar">
                <input type="file" name="avatar" id="avatar-upload" accept="image/*" onchange="this.form.submit()">
            </form>
            
            <!-- Profile Tabs -->
            <div class="profile-tabs">
                <button class="tab-btn active" onclick="showTab(event, 'profile-tab')">
                    👤 Profile Information
                </button>
                <button class="tab-btn" onclick="showTab(event, 'rfid-tab')">
                    📟 RFID Tags
                </button>
                <button class="tab-btn" onclick="showTab(event, 'security-tab')">
                    🔒 Security
                </button>
            </div>
            
            <!-- Profile Information Tab -->
            <div id="profile-tab" class="tab-content active">
                <div class="section-card">
                    <h2 class="section-title">👤 Profile Information</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name *</label>
                                <input type="text" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name">Last Name *</label>
                                <input type="text" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                       placeholder="+1 (555) 123-4567">
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="bio">Bio</label>
                            <textarea id="bio" name="bio" rows="3" 
                                      placeholder="Tell us a bit about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <button type="submit" class="btn btn-primary">
                                💾 Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- RFID Tags Tab -->
            <div id="rfid-tab" class="tab-content">
                <div class="section-card">
                    <h2 class="section-title">📟 RFID Tags</h2>
                    <p class="text-secondary">Manage your RFID tags for quick check-ins</p>
                    
                    <!-- Add New Tag -->
                    <div style="margin-bottom: 2rem; padding: 1.5rem; background: var(-- bg-primary); border-radius: var(-- radius-sm);">
                        <h3>Add New RFID Tag</h3>
                        <form method="POST" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                            <input type="hidden" name="action" value="add_rfid">
                            <div class="form-group" style="flex: 1; min-width: 200px;">
                                <label for="rfid_tag">RFID Tag Value</label>
                                <input type="text" id="rfid_tag" name="rfid_tag" 
                                       placeholder="e.g., A1B2C3D4" 
                                       pattern="[a-fA-F0-9]{8,16}"
                                       title="8-16 hexadecimal characters"
                                       required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                ➕ Add Tag
                            </button>
                        </form>
                    </div>
                    
                    <!-- Existing Tags -->
                    <?php if (!empty($rfid_tags)): ?>
                        <div class="rfid-list">
                            <?php foreach ($rfid_tags as $tag): ?>
                                <div class="rfid-item">
                                    <div class="rfid-info">
                                        <div class="rfid-tag"><?php echo htmlspecialchars($tag['tag_value']); ?></div>
                                        <div class="rfid-meta">
                                            Added <?php echo date('M j, Y', strtotime($tag['created_at'])); ?>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                        <span class="status-badge <?php echo $tag['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $tag['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="remove_rfid">
                                            <input type="hidden" name="tag_id" value="<?php echo $tag['tag_id']; ?>">
                                            <button type="submit" class="btn btn-outline btn-sm" 
                                                    onclick="return confirm('Remove this RFID tag?')">
                                                🗑️ Remove
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="text-align: center; padding: 3rem; color: var(-- text-secondary);">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">📟</div>
                            <h3>No RFID Tags</h3>
                            <p>Add an RFID tag to enable quick check-ins without manual input.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Security Tab -->
            <div id="security-tab" class="tab-content">
                <div class="section-card">
                    <h2 class="section-title">🔒 Security Settings</h2>
                    
                    <div style="margin-bottom: 2rem;">
                        <h3>Change Password</h3>
                        <p class="text-secondary">Update your password to keep your account secure.</p>
                        <a href="account-settings.php" class="btn btn-primary">
                            🔑 Change Password
                        </a>
                    </div>
                    
                    <div style="margin-bottom: 2rem;">
                        <h3>Account Information</h3>
                        <div class="info-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <strong>Role:</strong> 
                                <span class="badge"><?php echo ucfirst($user['role'] ?? 'user'); ?></span>
                            </div>
                            <div>
                                <strong>Account Created:</strong>
                                <?php echo ($user['created_at'] ?? null) ? date('M j, Y', strtotime($user['created_at'])) : 'N/A'; ?>
                            </div>
                            <div>
                                <strong>Last Updated:</strong>
                                <?php echo ($user['updated_at'] ?? null) ? date('M j, Y', strtotime($user['updated_at'])) : 'Never'; ?>
                            </div>
                            <div>
                                <strong>Status:</strong>
                                <span class="status-badge status-active">Active</span>
                            </div>
                        </div>
                    </div>
                    
                    <div style="border-top: 1px solid var(-- border-color); padding-top: 2rem;">
                        <h3 style="color: var(-- error-color);">Danger Zone</h3>
                        <p class="text-secondary">Irreversible actions that affect your account.</p>
                        <button class="btn btn-error" onclick="alert('Account deletion is not implemented yet. Please contact an administrator.')">
                            🗑️ Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(event, tabId) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tab buttons
            const tabBtns = document.querySelectorAll('.tab-btn');
            tabBtns.forEach(btn => btn.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
        }
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const profileForm = document.querySelector('form[method="POST"]');
            if (profileForm) {
                profileForm.addEventListener('submit', function(e) {
                    const firstName = document.getElementById('first_name').value.trim();
                    const lastName = document.getElementById('last_name').value.trim();
                    const email = document.getElementById('email').value.trim();
                    
                    if (!firstName || !lastName || !email) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                        return false;
                    }
                    
                    if (!email.includes('@') || !email.includes('.')) {
                        e.preventDefault();
                        alert('Please enter a valid email address.');
                        return false;
                    }
                });
            }
            
            // RFID tag format validation
            const rfidInput = document.getElementById('rfid_tag');
            if (rfidInput) {
                rfidInput.addEventListener('input', function() {
                    this.value = this.value.toUpperCase().replace(/[^A-F0-9]/g, '');
                });
            }
        });
    </script>

    <?php include '../includes/theme_script.php'; ?>
</body>
</html>
