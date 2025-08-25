<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Electronic Check-in System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header">
            <h1>📟 Electronic Check-in</h1>
            <p>Please sign in to your account</p>
        </div>
        
        <form id="loginForm" method="POST" action="login_process.php" class="login-form">
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group checkbox-group">
                <label>
                    <input type="checkbox" name="remember_me" value="1">
                    Remember me for 30 days
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary btn-full" id="loginButton">
                Sign In
            </button>
            
            <!-- Debug info for development -->
            <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
            <div style="margin-top: 15px; padding: 10px; background: #f0f8ff; border: 1px solid #ccc; border-radius: 4px; font-size: 0.9em;">
                <strong>Debug Info:</strong><br>
                Try: <code>admin@example.com</code> / <code>admin123</code><br>
                Or: <code>admin@localhost</code> / <code>admin123</code><br>
                <a href="debug_admin.php" style="color: #007cba;">Check Admin User</a> | 
                <a href="simple_auth/login.php" style="color: #007cba;">Simple Login</a>
            </div>
            <?php endif; ?>
        </form>
        
        <!-- Fallback script for JavaScript issues -->
        <script>
            // Fallback mechanism in case the main JavaScript fails
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('loginForm');
                const button = document.getElementById('loginButton');
                let submitted = false;
                
                // Backup form submission handler
                form.addEventListener('submit', function(e) {
                    if (submitted) {
                        e.preventDefault();
                        return false;
                    }
                    
                    submitted = true;
                    button.disabled = true;
                    button.innerHTML = 'Signing In...';
                    
                    // Reset after 10 seconds as failsafe
                    setTimeout(function() {
                        submitted = false;
                        button.disabled = false;
                        button.innerHTML = 'Sign In';
                    }, 10000);
                });
                
                // Reset button state on page show (back button, etc.)
                window.addEventListener('pageshow', function() {
                    submitted = false;
                    button.disabled = false;
                    button.innerHTML = 'Sign In';
                });
            });
        </script>
        
        <div class="login-footer">
            <a href="forgot_password.php">Forgot your password?</a>
            <div class="divider"></div>
            <p>Don't have an account? Contact your administrator.</p>
        </div>
    </div>
    
    <script src="../assets/js/login.js"></script>
</body>
</html>
