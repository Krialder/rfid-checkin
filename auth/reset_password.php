<?php
require_once '../core/config.php';
require_once '../core/database.php';
r            // Update password
            $stmt = $db->prepare("UPDATE Users SET password = ? WHERE user_id = ?");
            $stmt->execute([$passwordHash, $user['user_id']]);
            
            // Mark token as used
            $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            // Log the password reset
            $stmt = $db->prepare("e 'auth.php';

// Initialize database connection
$db = getDB();

$message = '';
$messageType = '';
$token = $_GET['token'] ?? '';
$validToken = false;
$user = null;

// Validate token
if ($token) {
    try {
        // Check if token is valid and not expired
        $stmt = $db->prepare("
            SELECT pr.user_id, pr.expires, u.username, u.first_name, u.email 
            FROM password_resets pr
            JOIN Users u ON pr.user_id = u.user_id
            WHERE pr.token = ? AND pr.expires > NOW() AND pr.used = 0
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $validToken = true;
        } else {
            $message = 'This password reset link is invalid or has expired. Please request a new one.';
            $messageType = 'error';
        }
    } catch (PDOException $e) {
        error_log("Token validation error: " . $e->getMessage());
        $message = 'An error occurred. Please try again later.';
        $messageType = 'error';
    }
} else {
    $message = 'No reset token provided.';
    $messageType = 'error';
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($newPassword) || empty($confirmPassword)) {
        $message = 'Please fill in all fields.';
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'Passwords do not match.';
        $messageType = 'error';
    } elseif (strlen($newPassword) < 8) {
        $message = 'Password must be at least 8 characters long.';
        $messageType = 'error';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $newPassword)) {
        $message = 'Password must contain at least one uppercase letter, one lowercase letter, and one number.';
        $messageType = 'error';
    } else {
        try {
            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update user password
            $stmt = $pdo->prepare("UPDATE Users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashedPassword, $user['user_id']]);
            
            // Mark token as used
            $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            // Log the password reset
            $stmt = $pdo->prepare("
                INSERT INTO ActivityLog (user_id, action, details, ip_address) 
                VALUES (?, 'password_reset_complete', 'Password successfully reset', ?)
            ");
            $stmt->execute([
                $user['user_id'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            $message = 'Your password has been successfully reset. You can now log in with your new password.';
            $messageType = 'success';
            $validToken = false; // Prevent showing form again
            
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            $message = 'An error occurred while resetting your password. Please try again.';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Electronic Check-in System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <style>
        .reset-password-container {
            max-width: 450px;
            margin: 50px auto;
            padding: 2rem;
            background: var(-- card-bg);
            border-radius: 12px;
            box-shadow: var(-- shadow);
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .header h1 {
            color: var(-- primary-color);
            margin-bottom: 0.5rem;
        }
        
        .header p {
            color: var(-- text-secondary);
            font-size: 0.95rem;
        }
        
        .user-info {
            background: var(-- info-bg, #e3f2fd);
            color: var(-- info-text, #0d47a1);
            border: 1px solid var(-- info-border, #bbdefb);
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .message {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .message.success {
            background: var(-- success-bg, #d4edda);
            color: var(-- success-text, #155724);
            border: 1px solid var(-- success-border, #c3e6cb);
        }
        
        .message.error {
            background: var(-- error-bg, #f8d7da);
            color: var(-- error-text, #721c24);
            border: 1px solid var(-- error-border, #f5c6cb);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .password-input-container {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(-- text-secondary);
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }
        
        .strength-indicator {
            height: 4px;
            background: var(-- border-color);
            border-radius: 2px;
            margin: 0.5rem 0;
            overflow: hidden;
        }
        
        .strength-fill {
            height: 100%;
            transition: width 0.3s ease, background-color 0.3s ease;
            width: 0;
        }
        
        .strength-weak { background: #ff4444; width: 25%; }
        .strength-fair { background: #ffaa00; width: 50%; }
        .strength-good { background: #00aa00; width: 75%; }
        .strength-strong { background: #008800; width: 100%; }
        
        .btn-primary {
            width: 100%;
            padding: 12px;
            background: var(-- primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .btn-primary:hover {
            background: var(-- primary-hover);
        }
        
        .btn-primary:disabled {
            background: var(-- disabled-color, #ccc);
            cursor: not-allowed;
        }
        
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .back-link a {
            color: var(-- primary-color);
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .password-requirements {
            background: var(-- card-bg);
            border: 1px solid var(-- border-color);
            padding: 1rem;
            border-radius: 6px;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            margin: 0.3rem 0;
        }
        
        .requirement.met {
            color: var(-- success-text, #155724);
        }
        
        .requirement.not-met {
            color: var(-- error-text, #721c24);
        }
        
        .requirement::before {
            content: '✓';
            margin-right: 0.5rem;
            font-weight: bold;
        }
        
        .requirement.not-met::before {
            content: '✗';
        }
        
        @media (max-width: 480px) {
            .reset-password-container {
                margin: 20px;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="reset-password-container">
        <div class="header">
            <h1>Reset Password</h1>
            <p>Create a new password for your account</p>
        </div>
        
        <?php if ($validToken && $user): ?>
            <div class="user-info">
                <strong>Resetting password for:</strong><br>
                <?php echo htmlspecialchars($user['first_name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
            </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($validToken): ?>
            <form method="POST" action="" id="resetForm">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <div class="password-input-container">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required 
                            placeholder="Enter your new password"
                            autocomplete="new-password"
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">Show</button>
                    </div>
                    <div class="password-strength">
                        <div class="strength-indicator">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <div id="strengthText">Password strength will appear here</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="password-input-container">
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            required 
                            placeholder="Confirm your new password"
                            autocomplete="new-password"
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">Show</button>
                    </div>
                </div>
                
                <div class="password-requirements">
                    <h4>Password Requirements:</h4>
                    <div class="requirement not-met" id="req-length">At least 8 characters long</div>
                    <div class="requirement not-met" id="req-upper">Contains uppercase letter</div>
                    <div class="requirement not-met" id="req-lower">Contains lowercase letter</div>
                    <div class="requirement not-met" id="req-number">Contains number</div>
                    <div class="requirement not-met" id="req-match">Passwords match</div>
                </div>
                
                <button type="submit" class="btn-primary" id="submitBtn" disabled>Reset Password</button>
            </form>
        <?php endif; ?>
        
        <div class="back-link">
            <?php if ($messageType === 'success'): ?>
                <a href="auth/login.php">&larr; Go to Login</a>
            <?php else: ?>
                <a href="auth/forgot_password.php">&larr; Request New Reset Link</a>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                button.textContent = 'Hide';
            } else {
                input.type = 'password';
                button.textContent = 'Show';
            }
        }
        
        function checkPasswordStrength(password) {
            let score = 0;
            let feedback = '';
            
            // Length check
            if (password.length >= 8) score += 25;
            if (password.length >= 12) score += 10;
            
            // Character variety
            if (/[a-z]/.test(password)) score += 20;
            if (/[A-Z]/.test(password)) score += 20;
            if (/\d/.test(password)) score += 20;
            if (/[^A-Za-z0-9]/.test(password)) score += 15;
            
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            if (score < 30) {
                strengthFill.className = 'strength-fill strength-weak';
                feedback = 'Weak password';
            } else if (score < 60) {
                strengthFill.className = 'strength-fill strength-fair';
                feedback = 'Fair password';
            } else if (score < 90) {
                strengthFill.className = 'strength-fill strength-good';
                feedback = 'Good password';
            } else {
                strengthFill.className = 'strength-fill strength-strong';
                feedback = 'Strong password';
            }
            
            strengthText.textContent = feedback;
        }
        
        function checkRequirements() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Check each requirement
            const requirements = {
                'req-length': password.length >= 8,
                'req-upper': /[A-Z]/.test(password),
                'req-lower': /[a-z]/.test(password),
                'req-number': /\d/.test(password),
                'req-match': password && confirmPassword && password === confirmPassword
            };
            
            let allMet = true;
            
            for (const [reqId, met] of Object.entries(requirements)) {
                const element = document.getElementById(reqId);
                if (met) {
                    element.className = 'requirement met';
                } else {
                    element.className = 'requirement not-met';
                    allMet = false;
                }
            }
            
            // Enable/disable submit button
            document.getElementById('submitBtn').disabled = !allMet;
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('confirm_password');
            
            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    checkPasswordStrength(this.value);
                    checkRequirements();
                });
                
                passwordInput.focus();
            }
            
            if (confirmInput) {
                confirmInput.addEventListener('input', checkRequirements);
            }
            
            // Form submission
            const form = document.getElementById('resetForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const submitBtn = document.getElementById('submitBtn');
                    if (submitBtn.disabled) {
                        e.preventDefault();
                        return;
                    }
                    
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Resetting...';
                });
            }
        });
    </script>
</body>
</html>
