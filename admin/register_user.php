<?php
/**
 * User Registration API
 * Optimized version of the broken registration.php with proper validation and security
 */

require_once '../core/config.php';
require_once '../core/database.php';
require_once '../core/auth.php';

// Only allow admin users to register new users
Auth::requireLogin();
$current_user = Auth::getCurrentUser();

if ($current_user['role'] !== 'admin') {
    http_response_code(403);
    header('Location: ' . BASE_URL . '/frontend/dashboard.php?error=Access denied');
    exit();
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
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
        
        // Check if email or username already exists
        $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            throw new Exception('Email address or username is already registered');
        }
        
        // Check if RFID tag already exists (if provided)
        if (!empty($rfid_tag)) {
            $stmt = $db->prepare("SELECT user_id FROM users WHERE rfid_tag = ?");
            $stmt->execute([$rfid_tag]);
            if ($stmt->fetch()) {
                throw new Exception('RFID tag is already assigned to another user');
            }
        }
        
        // Create user account
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("
            INSERT INTO users (username, first_name, last_name, email, phone, password, rfid_tag, role, department, is_active, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        
        $stmt->execute([
            $username,
            $forename,
            $surname,
            $email,
            $phone ?: null,
            $password_hash,
            $rfid_tag ?: null,
            $role,
            $department ?: null
        ]);
        
        $new_user_id = $db->lastInsertId();
        
        // Log the registration activity
        $stmt = $db->prepare("
            INSERT INTO activitylog (user_id, action, details, ip_address, timestamp) 
            VALUES (?, 'user_registration', ?, ?, NOW())
        ");
        $stmt->execute([
            $current_user['user_id'],
            "Registered new user: $forename $surname ($email) with role: $role",
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
        
        $db->commit();
        
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
        
        header('Location: ' . BASE_URL . '/admin/register_user.php?' . http_build_query($redirect_data));
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
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <h1>👥 Register New User</h1>
            <p class="subtitle">Add a new user to the system</p>
        </div>
        
        <div class="container" style="max-width: 600px;">
            <div class="card">
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-error">
                        ❌ <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="form">
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
                            <input type="text" id="rfid_tag" name="rfid_tag" 
                                   value="<?php echo htmlspecialchars($_GET['rfid_tag'] ?? ''); ?>"
                                   pattern="[A-Za-z0-9]{6,20}" 
                                   title="6-20 characters, letters and numbers only">
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
        
        // RFID tag validation
        document.getElementById('rfid_tag').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    </script>
</body>
</html>
