<?php
/**
 * Account Settings
 * Password change, notification preferences, and security settings
 */

require_once '../core/auth.php';
require_once '../core/database.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();
$db = getDB();

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'change_password':
                $result = changePassword($db, $user['user_id'], $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'update_notifications':
                $result = updateNotificationSettings($db, $user['user_id'], $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'update_privacy':
                $result = updatePrivacySettings($db, $user['user_id'], $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
        }
    }
}

// Get user settings
try {
    $stmt = $db->prepare("
        SELECT 
            email_notifications,
            sms_notifications,
            event_reminders,
            profile_visibility,
            share_analytics
        FROM user_settings 
        WHERE user_id = ?
    ");
    $stmt->execute([$user['user_id']]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("User settings fetch error: " . $e->getMessage());
    $settings = null;
}

// Create default settings if they don't exist
if (!$settings) {
    try {
        $stmt = $db->prepare("
            INSERT INTO user_settings (
                user_id, email_notifications, sms_notifications, 
                event_reminders, profile_visibility, share_analytics
            ) VALUES (?, 1, 0, 1, 'public', 0)
        ");
        $stmt->execute([$user['user_id']]);
        
        $settings = [
            'email_notifications' => 1,
            'sms_notifications' => 0,
            'event_reminders' => 1,
            'profile_visibility' => 'public',
            'share_analytics' => 0
        ];
    } catch (PDOException $e) {
        error_log("User settings creation error: " . $e->getMessage());
        // Use default values if table doesn't exist
        $settings = [
            'email_notifications' => 1,
            'sms_notifications' => 0,
            'event_reminders' => 1,
            'profile_visibility' => 'public',
            'share_analytics' => 0
        ];
    }
}

// Get recent login history
$stmt = $db->prepare("
    SELECT 
        timestamp as login_time,
        ip_address,
        user_agent,
        CASE WHEN status = 'success' THEN 1 ELSE 0 END as success
    FROM AccessLogs 
    WHERE user_id = ? AND action IN ('login', 'failed_login')
    ORDER BY timestamp DESC 
    LIMIT 10
");
$stmt->execute([$user['user_id']]);
$login_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

function changePassword($db, $user_id, $data) {
    try {
        $current_password = $data['current_password'] ?? '';
        $new_password = $data['new_password'] ?? '';
        $confirm_password = $data['confirm_password'] ?? '';
        
        // Validation
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            return ['success' => false, 'message' => 'All password fields are required.'];
        }
        
        if ($new_password !== $confirm_password) {
            return ['success' => false, 'message' => 'New passwords do not match.'];
        }
        
        if (strlen($new_password) < 8) {
            return ['success' => false, 'message' => 'New password must be at least 8 characters long.'];
        }
        
        // Check current password
        $stmt = $db->prepare("SELECT password FROM Users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $stored_hash = $stmt->fetchColumn();
        
        if (!password_verify($current_password, $stored_hash)) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }
        
        // Update password
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE Users SET password = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->execute([$new_hash, $user_id]);
        
        // Log password change
        $stmt = $db->prepare("
            INSERT INTO ActivityLog (user_id, action, details, timestamp) 
            VALUES (?, 'password_change', 'Password changed successfully', NOW())
        ");
        $stmt->execute([$user_id]);
        
        return ['success' => true, 'message' => 'Password changed successfully!'];
        
    } catch (Exception $e) {
        error_log("Change password error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to change password. Please try again.'];
    }
}

function updateNotificationSettings($db, $user_id, $data) {
    try {
        $email_notifications = isset($data['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($data['sms_notifications']) ? 1 : 0;
        $event_reminders = isset($data['event_reminders']) ? 1 : 0;
        
        $stmt = $db->prepare("
            UPDATE user_settings 
            SET email_notifications = ?, sms_notifications = ?, event_reminders = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$email_notifications, $sms_notifications, $event_reminders, $user_id]);
        
        return ['success' => true, 'message' => 'Notification settings updated successfully!'];
        
    } catch (Exception $e) {
        error_log("Update notifications error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update notification settings. Please try again.'];
    }
}

function updatePrivacySettings($db, $user_id, $data) {
    try {
        $profile_visibility = $data['profile_visibility'] ?? 'public';
        $share_analytics = isset($data['share_analytics']) ? 1 : 0;
        
        // Validate profile visibility
        if (!in_array($profile_visibility, ['public', 'members', 'private'])) {
            return ['success' => false, 'message' => 'Invalid profile visibility setting.'];
        }
        
        $stmt = $db->prepare("
            UPDATE user_settings 
            SET profile_visibility = ?, share_analytics = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$profile_visibility, $share_analytics, $user_id]);
        
        return ['success' => true, 'message' => 'Privacy settings updated successfully!'];
        
    } catch (Exception $e) {
        error_log("Update privacy error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update privacy settings. Please try again.'];
    }
}

function getBrowserInfo($user_agent) {
    $browser = 'Unknown Browser';
    $os = 'Unknown OS';
    
    // Simple browser detection
    if (strpos($user_agent, 'Chrome') !== false) {
        $browser = 'Chrome';
    } elseif (strpos($user_agent, 'Firefox') !== false) {
        $browser = 'Firefox';
    } elseif (strpos($user_agent, 'Safari') !== false) {
        $browser = 'Safari';
    } elseif (strpos($user_agent, 'Edge') !== false) {
        $browser = 'Edge';
    }
    
    // Simple OS detection
    if (strpos($user_agent, 'Windows') !== false) {
        $os = 'Windows';
    } elseif (strpos($user_agent, 'Mac') !== false) {
        $os = 'macOS';
    } elseif (strpos($user_agent, 'Linux') !== false) {
        $os = 'Linux';
    } elseif (strpos($user_agent, 'Android') !== false) {
        $os = 'Android';
    } elseif (strpos($user_agent, 'iOS') !== false) {
        $os = 'iOS';
    }
    
    return $browser . ' on ' . $os;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Electronic Check-in System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/account-settings.css">
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="main-content">
        <div class="settings-container">



            <div class="settings-header">
                <h1>⚙️ Account Settings</h1>
                <p class="subtitle">Manage your security, notifications, and privacy preferences</p>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- Settings Tabs -->
            <div class="settings-tabs">
                <button class="tab-btn active" onclick="showTab(event, 'security-tab')">
                    🔒 Security
                </button>
                <button class="tab-btn" onclick="showTab(event, 'notifications-tab')">
                    🔔 Notifications
                </button>
                <button class="tab-btn" onclick="showTab(event, 'privacy-tab')">
                    🛡️ Privacy
                </button>
                <button class="tab-btn" onclick="showTab(event, 'activity-tab')">
                    📊 Activity
                </button>
            </div>
            
            <!-- Security Tab -->
            <div id="security-tab" class="tab-content active">
                <div class="section-card">
                    <h2 class="section-title">🔑 Change Password</h2>
                    
                    <form method="POST" id="password-form">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required
                                   oninput="checkPasswordStrength(this.value)">
                            <div class="password-strength">
                                <div class="password-strength-bar" id="strength-bar"></div>
                            </div>
                            <div class="password-requirements" id="password-requirements">
                                <div class="requirement" id="req-length">
                                    <span>•</span> At least 8 characters
                                </div>
                                <div class="requirement" id="req-letter">
                                    <span>•</span> At least one letter
                                </div>
                                <div class="requirement" id="req-number">
                                    <span>•</span> At least one number
                                </div>
                                <div class="requirement" id="req-special">
                                    <span>•</span> At least one special character
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required
                                   oninput="checkPasswordMatch()">
                            <div id="password-match-msg" style="font-size: 0.875rem; margin-top: 0.5rem;"></div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary" id="password-submit">
                                🔄 Change Password
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="section-card">
                    <h2 class="section-title">📱 Two-Factor Authentication</h2>
                    <p class="text-secondary">Add an extra layer of security to your account.</p>
                    
                    <div class="feature-preview">
                        <div class="feature-preview-header">
                            <span class="feature-preview-icon">ℹ️</span>
                            <strong>Coming Soon</strong>
                        </div>
                        <p class="feature-preview-text">Two-factor authentication will be available in a future update.</p>
                    </div>
                </div>
            </div>
            
            <!-- Notifications Tab -->
            <div id="notifications-tab" class="tab-content">
                <div class="section-card">
                    <h2 class="section-title">🔔 Notification Preferences</h2>
                    <p class="text-secondary">Choose how you want to receive notifications about events and activities.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_notifications">
                        
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="email_notifications" name="email_notifications" 
                                       <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                                <div class="checkbox-label">
                                    <label for="email_notifications">📧 Email Notifications</label>
                                    <div class="checkbox-description">
                                        Receive email notifications about events, check-ins, and system updates.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="checkbox-item">
                                <input type="checkbox" id="sms_notifications" name="sms_notifications" 
                                       <?php echo $settings['sms_notifications'] ? 'checked' : ''; ?>>
                                <div class="checkbox-label">
                                    <label for="sms_notifications">📱 SMS Notifications</label>
                                    <div class="checkbox-description">
                                        Receive text message notifications for important events and reminders.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="checkbox-item">
                                <input type="checkbox" id="event_reminders" name="event_reminders" 
                                       <?php echo $settings['event_reminders'] ? 'checked' : ''; ?>>
                                <div class="checkbox-label">
                                    <label for="event_reminders">⏰ Event Reminders</label>
                                    <div class="checkbox-description">
                                        Get reminded about upcoming events you might want to attend.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                💾 Save Notification Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Privacy Tab -->
            <div id="privacy-tab" class="tab-content">
                <div class="section-card">
                    <h2 class="section-title">🛡️ Privacy Settings</h2>
                    <p class="text-secondary">Control who can see your profile and activity information.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_privacy">
                        
                        <div class="form-group">
                            <label>👁️ Profile Visibility</label>
                            <div class="radio-group">
                                <div class="radio-item">
                                    <input type="radio" id="visibility_public" name="profile_visibility" value="public" 
                                           <?php echo $settings['profile_visibility'] === 'public' ? 'checked' : ''; ?>>
                                    <div class="radio-label">
                                        <label for="visibility_public">🌍 Public</label>
                                        <div class="radio-description">
                                            Your profile is visible to everyone, including non-members.
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="radio-item">
                                    <input type="radio" id="visibility_members" name="profile_visibility" value="members" 
                                           <?php echo $settings['profile_visibility'] === 'members' ? 'checked' : ''; ?>>
                                    <div class="radio-label">
                                        <label for="visibility_members">👥 Members Only</label>
                                        <div class="radio-description">
                                            Only logged-in members can view your profile and activity.
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="radio-item">
                                    <input type="radio" id="visibility_private" name="profile_visibility" value="private" 
                                           <?php echo $settings['profile_visibility'] === 'private' ? 'checked' : ''; ?>>
                                    <div class="radio-label">
                                        <label for="visibility_private">🔒 Private</label>
                                        <div class="radio-description">
                                            Your profile is only visible to administrators and yourself.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="share_analytics" name="share_analytics" 
                                       <?php echo $settings['share_analytics'] ? 'checked' : ''; ?>>
                                <div class="checkbox-label">
                                    <label for="share_analytics">📊 Share Analytics</label>
                                    <div class="checkbox-description">
                                        Allow your anonymized data to be included in system-wide analytics and reports.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                💾 Save Privacy Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Activity Tab -->
            <div id="activity-tab" class="tab-content">
                <div class="section-card">
                    <h2 class="section-title">📊 Recent Login Activity</h2>
                    <p class="text-secondary">Review your recent login attempts and sessions.</p>
                    
                    <?php if (!empty($login_history)): ?>
                        <div class="login-history">
                            <?php foreach ($login_history as $login): ?>
                                <div class="login-item <?php echo $login['success'] ? '' : 'failed'; ?>">
                                    <div class="login-info">
                                        <div class="login-time">
                                            <?php echo date('M j, Y \a\t g:i A', strtotime($login['login_time'])); ?>
                                        </div>
                                        <div class="login-details">
                                            <?php echo htmlspecialchars($login['ip_address']); ?> • 
                                            <?php echo getBrowserInfo($login['user_agent']); ?>
                                        </div>
                                    </div>
                                    <div class="login-status <?php echo $login['success'] ? 'status-success' : 'status-failed'; ?>">
                                        <?php echo $login['success'] ? 'Success' : 'Failed'; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="stats-preview-empty">
                            <div class="stats-preview-icon">📊</div>
                            <h3>No Login History</h3>
                            <p>Your login activity will appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="section-card">
                    <h2 class="section-title">🔄 Data Export</h2>
                    <p class="text-secondary">Download a copy of your personal data.</p>
                    
                    <div class="export-options">
                        <button class="btn btn-secondary" onclick="exportData('profile')">
                            👤 Export Profile Data
                        </button>
                        <button class="btn btn-secondary" onclick="exportData('checkins')">
                            ✅ Export Check-in History
                        </button>
                        <button class="btn btn-secondary" onclick="exportData('all')">
                            📦 Export All Data
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
        
        function checkPasswordStrength(password) {
            const requirements = {
                length: password.length >= 8,
                letter: /[a-zA-Z]/.test(password),
                number: /\d/.test(password),
                special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
            };
            
            // Update requirement indicators
            Object.keys(requirements).forEach(req => {
                const element = document.getElementById('req-' + req);
                if (requirements[req]) {
                    element.classList.add('met');
                    element.querySelector('span').textContent = '✓';
                } else {
                    element.classList.remove('met');
                    element.querySelector('span').textContent = '•';
                }
            });
            
            // Calculate strength
            const metRequirements = Object.values(requirements).filter(Boolean).length;
            const strengthBar = document.getElementById('strength-bar');
            
            strengthBar.className = 'password-strength-bar';
            
            if (metRequirements === 0) {
                strengthBar.style.width = '0%';
            } else if (metRequirements === 1) {
                strengthBar.classList.add('strength-weak');
            } else if (metRequirements === 2) {
                strengthBar.classList.add('strength-fair');
            } else if (metRequirements === 3) {
                strengthBar.classList.add('strength-good');
            } else {
                strengthBar.classList.add('strength-strong');
            }
            
            return metRequirements >= 3;
        }
        
        function checkPasswordMatch() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const messageElement = document.getElementById('password-match-msg');
            
            if (confirmPassword === '') {
                messageElement.textContent = '';
                messageElement.style.color = '';
                return false;
            }
            
            if (newPassword === confirmPassword) {
                messageElement.textContent = '✓ Passwords match';
                messageElement.style.color = 'var(-- success-color)';
                return true;
            } else {
                messageElement.textContent = '✗ Passwords do not match';
                messageElement.style.color = 'var(-- error-color)';
                return false;
            }
        }
        
        function exportData(type) {
            // Show loading state
            event.target.textContent = 'Exporting...';
            event.target.disabled = true;
            
            // Simulate data export (would normally be an API call)
            setTimeout(() => {
                alert(`Data export for "${type}" would be implemented here. This would generate a downloadable file with your requested data.`);
                
                // Reset button
                event.target.disabled = false;
                switch(type) {
                    case 'profile':
                        event.target.textContent = '👤 Export Profile Data';
                        break;
                    case 'checkins':
                        event.target.textContent = '✅ Export Check-in History';
                        break;
                    case 'all':
                        event.target.textContent = '📦 Export All Data';
                        break;
                }
            }, 2000);
        }
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const passwordForm = document.getElementById('password-form');
            
            passwordForm.addEventListener('submit', function(e) {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (!checkPasswordStrength(newPassword)) {
                    e.preventDefault();
                    alert('Please ensure your password meets all requirements.');
                    return false;
                }
                
                if (!checkPasswordMatch()) {
                    e.preventDefault();
                    alert('Passwords do not match.');
                    return false;
                }
            });
        });
    </script>

    <?php include '../includes/theme_script.php'; ?>
</body>
</html>
