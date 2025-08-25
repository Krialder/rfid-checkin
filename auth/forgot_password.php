<?php
require_once '../core/config.php';
require_once '../core/database.php';
require_once '../core/auth.php';

// Initialize database connection
$db = getDB();

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $message = 'Please enter your email address.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } else {
        try {
            // Check if user exists
            $stmt = $db->prepare("SELECT user_id, username, first_name FROM Users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Generate secure reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store reset token
                $stmt = $db->prepare("
                    INSERT INTO password_resets (user_id, token, expires, created_at) 
                    VALUES (?, ?, ?, NOW()) 
                    ON DUPLICATE KEY UPDATE 
                    token = VALUES(token), 
                    expires = VALUES(expires), 
                    created_at = NOW()
                ");
                $stmt->execute([$user['user_id'], $token, $expires]);
                
                // Create reset URL
                $resetUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/auth/reset_password.php?token=" . $token;
                
                // Email content
                $subject = "Password Reset Request - Electronic Check-in System";
                $emailBody = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
                        .content { padding: 20px; background: #f9f9f9; }
                        .button { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                        .footer { text-align: center; color: #666; font-size: 12px; padding: 10px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>Password Reset Request</h1>
                        </div>
                        <div class='content'>
                            <p>Hello " . htmlspecialchars($user['first_name']) . ",</p>
                            <p>We received a request to reset your password for the Electronic Check-in System.</p>
                            <p>Click the button below to reset your password:</p>
                            <p><a href='" . htmlspecialchars($resetUrl) . "' class='button'>Reset Password</a></p>
                            <p>Or copy and paste this link into your browser:</p>
                            <p><code>" . htmlspecialchars($resetUrl) . "</code></p>
                            <p><strong>This link will expire in 1 hour.</strong></p>
                            <p>If you didn't request this password reset, please ignore this email.</p>
                        </div>
                        <div class='footer'>
                            <p>Electronic Check-in System &copy; " . date('Y') . "</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                // Email headers
                $headers = [
                    'MIME-Version: 1.0',
                    'Content-Type: text/html; charset=UTF-8',
                    'From: Electronic Check-in System <noreply@' . $_SERVER['HTTP_HOST'] . '>',
                    'Reply-To: noreply@' . $_SERVER['HTTP_HOST'],
                    'X-Mailer: PHP/' . phpversion()
                ];
                
                // Send email
                if (mail($email, $subject, $emailBody, implode("\r\n", $headers))) {
                    $message = 'Password reset instructions have been sent to your email address.';
                    $messageType = 'success';
                    
                    // Log the password reset request
                    $stmt = $db->prepare("
                        INSERT INTO ActivityLog (user_id, action, details, ip_address) 
                        VALUES (?, 'password_reset_request', 'Password reset requested', ?)
                    ");
                    $stmt->execute([
                        $user['user_id'],
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                } else {
                    $message = 'Failed to send password reset email. Please try again later.';
                    $messageType = 'error';
                }
            } else {
                // Don't reveal whether email exists - security best practice
                $message = 'If an account with that email exists, password reset instructions have been sent.';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            $message = 'An error occurred. Please try again later.';
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
    <title>Forgot Password - Electronic Check-in System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <style>
        .forgot-password-container {
            max-width: 400px;
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
        
        .security-notice {
            background: var(-- info-bg, #e3f2fd);
            color: var(-- info-text, #0d47a1);
            border: 1px solid var(-- info-border, #bbdefb);
            padding: 1rem;
            border-radius: 6px;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }
        
        .security-notice h4 {
            margin: 0 0 0.5rem 0;
            color: var(-- info-text, #0d47a1);
        }
        
        @media (max-width: 480px) {
            .forgot-password-container {
                margin: 20px;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-password-container">
        <div class="header">
            <h1>Forgot Password</h1>
            <p>Enter your email address to receive password reset instructions</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($messageType !== 'success'): ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        required 
                        placeholder="Enter your email address"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        autocomplete="email"
                    >
                </div>
                
                <button type="submit" class="btn-primary">Send Reset Instructions</button>
            </form>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="auth/login.php">&larr; Back to Login</a>
        </div>
        
        <div class="security-notice">
            <h4>Security Notice</h4>
            <ul style="margin: 0; padding-left: 1.2rem;">
                <li>Reset links expire after 1 hour</li>
                <li>Only one active reset link per account</li>
                <li>Check your spam folder if email doesn't arrive</li>
                <li>Contact support if you continue having issues</li>
            </ul>
        </div>
    </div>
    
    <script>
        // Focus on email input
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            if (emailInput) {
                emailInput.focus();
            }
        });
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const email = document.getElementById('email').value.trim();
                    
                    if (!email) {
                        e.preventDefault();
                        alert('Please enter your email address.');
                        return;
                    }
                    
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        e.preventDefault();
                        alert('Please enter a valid email address.');
                        return;
                    }
                    
                    // Disable button to prevent double submission
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Sending...';
                    }
                });
            }
        });
    </script>
</body>
</html>
