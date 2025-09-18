<?php
/**
 * User Registration API - Secured with comprehensive protection
 * Optimized version with proper validation, security, and CSRF protection
 */

require_once '../core/config.php';
require_once '../core/database.php';
require_once '../core/auth.php';
require_once '../core/SecurityMiddleware.php';
require_once '../core/services/DataService.php';

// Initialize security middleware
$security = new SecurityMiddleware();

// Only allow admin users to register new users
Auth::requireLogin();
$current_user = Auth::getCurrentUser();

if ($current_user['role'] !== 'admin') {
    http_response_code(403);
    header('Location: ' . BASE_URL . '/frontend/dashboard.php?error=Access denied');
    exit();
}

$dataService = DataService::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process security checks
    if (!$security->handleRequest()) {
        exit(); // Security middleware handles the response
    }
    
    try {
        // Validate and sanitize input with enhanced security
        $fieldValidation = $security->validateFields($_POST, [
            'forename' => 'name',
            'surname' => 'name',
            'email' => 'email',
            'username' => 'username',
            'password' => 'password'
        ]);
        
        if (!empty($fieldValidation)) {
            throw new Exception('Validation errors: ' . implode(', ', array_merge(...array_values($fieldValidation))));
        }
        
        // Get and validate input
        $forename = trim($_POST['forename'] ?? '');
        $surname = trim($_POST['surname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $rfid_tag = trim($_POST['rfid_tag'] ?? '');
        $role = trim($_POST['role'] ?? 'user');
        $department = trim($_POST['department'] ?? '');
        
        // Generate username from email if not provided
        if (empty($username)) {
            $username = explode('@', $email)[0];
        }
        
        // Validation
        $errors = [];
        
        if (empty($forename)) {
            $errors[] = 'First name is required';
        } elseif (!preg_match('/^[A-Za-zÄÖÜäöüß\s-]{2,50}$/', $forename)) {
            $errors[] = 'First name must be 2-50 characters and contain only letters';
        }
        
        if (!empty($username) && !preg_match('/^[A-Za-z0-9_-]{3,50}$/', $username)) {
            $errors[] = 'Username must be 3-50 characters and contain only letters, numbers, hyphens and underscores';
        }
        
        if (empty($surname)) {
            $errors[] = 'Last name is required';
        } elseif (!preg_match('/^[A-Za-zÄÖÜäöüß\s-]{2,50}$/', $surname)) {
            $errors[] = 'Last name must be 2-50 characters and contain only letters';
        }
        
        if (empty($email)) {
            $errors[] = 'Email address is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address';
        }
        
        if (empty($password)) {
            $errors[] = 'Password is required';
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long';
        } elseif ($password !== $password2) {
            $errors[] = 'Passwords do not match';
        }
        
        if (!empty($phone) && !preg_match('/^[\+]?[0-9\s\-\(\)]{6,20}$/', $phone)) {
            $errors[] = 'Please enter a valid phone number';
        }
        
        if (!empty($rfid_tag) && !preg_match('/^[A-Za-z0-9]{6,20}$/', $rfid_tag)) {
            $errors[] = 'RFID tag must be 6-20 characters (letters and numbers only)';
        }
        
        if (!in_array($role, ['user', 'admin', 'moderator'])) {
            $errors[] = 'Invalid role selected';
        }
        
        if ($errors) {
            throw new Exception(implode('; ', $errors));
        }
        
        $db->beginTransaction();
        
        // Use DataService to create user with enhanced security
        $userData = [
            'username' => $username,
            'first_name' => $forename,
            'last_name' => $surname,
            'email' => $email,
            'phone' => $phone ?: null,
            'password' => $password, // DataService will hash this securely
            'rfid_tag' => $rfid_tag ?: null,
            'role' => $role,
            'department' => $department ?: null,
            'is_active' => 1
        ];
        
        // Check for duplicates using DataService
        $existingUser = $dataService->getUserByEmail($email);
        if ($existingUser) {
            throw new Exception('Email address is already registered');
        }
        
        if (!empty($username)) {
            $existingUsername = $dataService->getUserByUsername($username);
            if ($existingUsername) {
                throw new Exception('Username is already taken');
            }
        }
        
        if (!empty($rfid_tag)) {
            $existingRfid = $dataService->getUserByRfid($rfid_tag);
            if ($existingRfid) {
                throw new Exception('RFID tag is already assigned to another user');
            }
        }
        
        // Create user with DataService
        $newUserId = $dataService->createUser($userData);
        
        if (!$newUserId) {
            throw new Exception('Failed to create user account');
        }
        
        // Log the registration activity
        $dataService->logActivity(
            $current_user['user_id'],
            'user_registration',
            "Registered new user: $forename $surname ($email) with role: $role",
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        );
        
        $name = $forename . ' ' . $surname;
        header('Location: ' . BASE_URL . '/admin/users.php?success=' . urlencode('User registered successfully: ' . $name));
        exit();
        
    } catch (Exception $e) {
        if (isset($db)) {
            $db->rollback();
        }
        
        error_log('Registration Error: ' . $e->getMessage());
        $error_message = $e->getMessage();
        
        // Redirect back with error and form data (except passwords)
        $redirect_data = [
            'error' => $error_message,
            'username' => $username ?? '',
            'forename' => $forename ?? '',
            'surname' => $surname ?? '',
            'email' => $email ?? '',
            'phone' => $phone ?? '',
            'role' => $role ?? '',
            'department' => $department ?? ''
        ];
        
        header('Location: ' . BASE_URL . '/admin/register-user.php?' . http_build_query($redirect_data));
        exit();
    }
}

// If not POST request, show the registration form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register New User - Electronic Check-in System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <link rel="stylesheet" href="../assets/css/admin-tools.css">
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <h1>👥 Register New User</h1>
            <p class="subtitle">Add a new user to the system</p>
        </div>
        
        <div class="container form-container">
            <div class="card">
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-error" role="alert" aria-live="polite">
                        ❌ <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="form" id="registerForm">
                    <!-- CSRF Protection -->
                    <?php echo $security->getCSRFInput('register-user'); ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="forename">First Name *</label>
                            <input type="text" id="forename" name="forename" required 
                                   value="<?php echo htmlspecialchars($_GET['forename'] ?? ''); ?>"
                                   pattern="[A-Za-zÄÖÜäöüß\s-]{2,50}" 
                                   title="2-50 characters, letters only">
                        </div>
                        
                        <div class="form-group">
                            <label for="surname">Last Name *</label>
                            <input type="text" id="surname" name="surname" required 
                                   value="<?php echo htmlspecialchars($_GET['surname'] ?? ''); ?>"
                                   pattern="[A-Za-zÄÖÜäöüß\s-]{2,50}" 
                                   title="2-50 characters, letters only">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" 
                               value="<?php echo htmlspecialchars($_GET['username'] ?? ''); ?>"
                               placeholder="Leave blank to auto-generate from email">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo htmlspecialchars($_GET['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" required 
                                   minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="password2">Confirm Password *</label>
                            <input type="password" id="password2" name="password2" required 
                                   minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($_GET['phone'] ?? ''); ?>"
                                   pattern="[\+]?[0-9\s\-\(\)]{6,20}">
                        </div>
                        
                        <div class="form-group">
                            <label for="rfid_tag">RFID Tag</label>
                            <div class="rfid-input-group">
                                <input type="text" 
                                       id="rfid_tag" 
                                       name="rfid_tag" 
                                       value="<?php echo htmlspecialchars($_GET['rfid_tag'] ?? ''); ?>"
                                       placeholder="Enter RFID tag (optional)"
                                       maxlength="20">
                                <button type="button" 
                                        id="scan-rfid-btn" 
                                        class="btn btn-primary"
                                        title="Scan RFID tag">
                                    📡 Scan RFID
                                </button>
                            </div>
                            <small class="form-help">Enter RFID tag manually or click scan button.</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="role">Role *</label>
                            <select id="role" name="role" required>
                                <option value="user" <?php echo ($_GET['role'] ?? '') === 'user' ? 'selected' : ''; ?>>User</option>
                                <option value="moderator" <?php echo ($_GET['role'] ?? '') === 'moderator' ? 'selected' : ''; ?>>Moderator</option>
                                <option value="admin" <?php echo ($_GET['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="department">Department</label>
                            <input type="text" id="department" name="department" 
                                   value="<?php echo htmlspecialchars($_GET['department'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="register" class="btn btn-primary btn-full">
                            👤 Register User
                        </button>
                        <a href="admin/users.php" class="btn btn-secondary btn-full">
                            ← Back to User Management
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/theme_script.php'; ?>
    
    <script>
        // Password confirmation validation
        document.getElementById('password2').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const password2 = this.value;
            
            if (password !== password2) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Simple RFID input handling
        document.getElementById('rfid_tag').addEventListener('input', function() {
            // Convert to uppercase for consistency
            this.value = this.value.toUpperCase();
        });
        
        // Simple RFID scanning functionality
        class SimpleRFIDScanner {
            constructor() {
                this.isScanning = false;
                this.init();
            }
            
            init() {
                document.getElementById('scan-rfid-btn').addEventListener('click', () => {
                    this.toggleScanning();
                });
            }
            
            toggleScanning() {
                const button = document.getElementById('scan-rfid-btn');
                const input = document.getElementById('rfid_tag');
                
                if (!this.isScanning) {
                    // Start scanning
                    this.isScanning = true;
                    button.textContent = '⏹️ Stop Scanning';
                    button.classList.add('btn-danger');
                    button.classList.remove('btn-primary');
                    
                    this.showMessage('RFID Scanner ready. Scan a tag or click "Stop Scanning" to enter manually.', 'info');
                    this.startPolling();
                } else {
                    // Stop scanning and allow manual entry
                    this.stopScanning();
                    const manualRFID = prompt('Enter RFID tag manually:');
                    if (manualRFID && manualRFID.trim()) {
                        input.value = manualRFID.trim().toUpperCase();
                        this.showMessage(`RFID entered: ${input.value}`, 'success');
                    }
                }
            }
            
            startPolling() {
                // Simple polling for RFID scans
                this.pollInterval = setInterval(async () => {
                    try {
                        const response = await fetch('../api/rfid-poll-noauth.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ last_tag: '', timeout: 1000 })
                        });
                        
                        if (response.ok) {
                            const data = await response.json();
                            if (data.success && data.rfid_tag) {
                                document.getElementById('rfid_tag').value = data.rfid_tag;
                                this.showMessage(`RFID scanned: ${data.rfid_tag}`, 'success');
                                this.stopScanning();
                            }
                        }
                    } catch (error) {
                        // Ignore polling errors
                    }
                }, 1000);
                
                // Auto-stop after 30 seconds
                setTimeout(() => {
                    if (this.isScanning) {
                        this.stopScanning();
                        this.showMessage('Scan timeout. You can enter RFID manually or try scanning again.', 'warning');
                    }
                }, 30000);
            }
            
            stopScanning() {
                this.isScanning = false;
                const button = document.getElementById('scan-rfid-btn');
                button.textContent = '📡 Scan RFID';
                button.classList.remove('btn-danger');
                button.classList.add('btn-primary');
                
                if (this.pollInterval) {
                    clearInterval(this.pollInterval);
                    this.pollInterval = null;
                }
            }
            
            showMessage(message, type) {
                // Create simple notification
                const notification = document.createElement('div');
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 12px 16px;
                    border-radius: 4px;
                    color: white;
                    font-weight: bold;
                    z-index: 10000;
                    max-width: 300px;
                    ${type === 'success' ? 'background: #28a745;' : ''}
                    ${type === 'error' ? 'background: #dc3545;' : ''}
                    ${type === 'warning' ? 'background: #ffc107; color: black;' : ''}
                    ${type === 'info' ? 'background: #17a2b8;' : ''}
                `;
                notification.textContent = message;
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 5000);
            }
        }
        
        // Initialize RFID scanner when page loads
        window.addEventListener('load', () => {
            new SimpleRFIDScanner();
        });
    </script>
</body>
</html>
