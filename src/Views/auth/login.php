<?php
$page_title = 'Sign In';
$page_subtitle = 'Welcome back! Please sign in to your account.';
$page_class = 'login-page';
$show_register_link = true;
$show_forgot_link = true;
?>

<!-- Login Form -->
<div class="auth-form-container">
    
    <!-- Form Header -->
    <div class="form-header">
        <h2 class="form-title">Sign In</h2>
        <p class="form-description">Enter your credentials to access your account</p>
    </div>
    
    <!-- Login Form -->
    <form id="login-form" class="auth-form" action="/auth/login-process" method="POST" novalidate>
        
        <!-- CSRF Token -->
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        
        <!-- Username/Email Field -->
        <div class="form-group">
            <label for="username" class="form-label required">
                <i class="fas fa-user form-label-icon"></i>
                Username or Email
            </label>
            <div class="form-input-group">
                <input type="text" 
                       id="username" 
                       name="username" 
                       class="form-input" 
                       placeholder="Enter your username or email"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       required
                       autocomplete="username"
                       autocapitalize="none"
                       spellcheck="false">
                <div class="form-input-icon">
                    <i class="fas fa-user"></i>
                </div>
            </div>
            <div class="form-feedback" id="username-feedback"></div>
        </div>
        
        <!-- Password Field -->
        <div class="form-group">
            <label for="password" class="form-label required">
                <i class="fas fa-lock form-label-icon"></i>
                Password
            </label>
            <div class="form-input-group">
                <input type="password" 
                       id="password" 
                       name="password" 
                       class="form-input" 
                       placeholder="Enter your password"
                       required
                       autocomplete="current-password">
                <div class="form-input-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <button type="button" class="password-toggle" aria-label="Toggle password visibility">
                    <i class="fas fa-eye" data-show="fas fa-eye" data-hide="fas fa-eye-slash"></i>
                </button>
            </div>
            <div class="form-feedback" id="password-feedback"></div>
        </div>
        
        <!-- Remember Me & Forgot Password -->
        <div class="form-options">
            <label class="checkbox-label">
                <input type="checkbox" name="remember_me" value="1" class="checkbox-input">
                <span class="checkbox-custom"></span>
                <span class="checkbox-text">Remember me for 30 days</span>
            </label>
            
            <a href="/auth/forgot-password" class="forgot-link">
                Forgot your password?
            </a>
        </div>
        
        <!-- Submit Button -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-block btn-lg" id="login-btn">
                <span class="btn-text">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </span>
                <span class="btn-loading" style="display: none;">
                    <i class="fas fa-spinner fa-spin"></i>
                    Signing In...
                </span>
            </button>
        </div>
        
        <!-- Alternative Actions -->
        <div class="form-alternatives">
            <p class="alternative-text">
                Don't have an account?
                <a href="/auth/register" class="alternative-link">Create one here</a>
            </p>
        </div>
        
    </form>
    
    <!-- Additional Information -->
    <div class="auth-info">
        <div class="info-item">
            <i class="fas fa-shield-alt info-icon"></i>
            <div class="info-content">
                <strong>Secure Login</strong>
                <p>Your connection is encrypted and your data is protected.</p>
            </div>
        </div>
        
        <div class="info-item">
            <i class="fas fa-clock info-icon"></i>
            <div class="info-content">
                <strong>Session Timeout</strong>
                <p>For security, you'll be logged out after 24 hours of inactivity.</p>
            </div>
        </div>
        
        <?php if (!empty($support_email)): ?>
        <div class="info-item">
            <i class="fas fa-question-circle info-icon"></i>
            <div class="info-content">
                <strong>Need Help?</strong>
                <p>Contact support at <a href="mailto:<?= htmlspecialchars($support_email) ?>"><?= htmlspecialchars($support_email) ?></a></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
</div>

<!-- Login Form JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('login-form');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const submitBtn = document.getElementById('login-btn');
    const passwordToggle = document.querySelector('.password-toggle');
    
    // Password visibility toggle
    if (passwordToggle) {
        passwordToggle.addEventListener('click', function() {
            const input = passwordInput;
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = icon.dataset.hide;
                this.setAttribute('aria-label', 'Hide password');
            } else {
                input.type = 'password';
                icon.className = icon.dataset.show;
                this.setAttribute('aria-label', 'Show password');
            }
        });
    }
    
    // Form validation
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (validateForm()) {
            submitForm();
        }
    });
    
    // Real-time validation
    usernameInput.addEventListener('blur', function() {
        validateUsername();
    });
    
    passwordInput.addEventListener('blur', function() {
        validatePassword();
    });
    
    // Clear validation on input
    usernameInput.addEventListener('input', function() {
        clearFieldValidation('username');
    });
    
    passwordInput.addEventListener('input', function() {
        clearFieldValidation('password');
    });
    
    function validateForm() {
        let isValid = true;
        
        if (!validateUsername()) {
            isValid = false;
        }
        
        if (!validatePassword()) {
            isValid = false;
        }
        
        return isValid;
    }
    
    function validateUsername() {
        const value = usernameInput.value.trim();
        
        if (!value) {
            showFieldError('username', 'Username or email is required');
            return false;
        }
        
        if (value.length < 3) {
            showFieldError('username', 'Username must be at least 3 characters');
            return false;
        }
        
        // Email validation if it looks like an email
        if (value.includes('@')) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                showFieldError('username', 'Please enter a valid email address');
                return false;
            }
        }
        
        showFieldSuccess('username');
        return true;
    }
    
    function validatePassword() {
        const value = passwordInput.value;
        
        if (!value) {
            showFieldError('password', 'Password is required');
            return false;
        }
        
        if (value.length < 6) {
            showFieldError('password', 'Password must be at least 6 characters');
            return false;
        }
        
        showFieldSuccess('password');
        return true;
    }
    
    function showFieldError(fieldName, message) {
        const input = document.getElementById(fieldName);
        const feedback = document.getElementById(fieldName + '-feedback');
        const group = input.closest('.form-group');
        
        group.classList.add('has-error');
        group.classList.remove('has-success');
        feedback.textContent = message;
        feedback.className = 'form-feedback error';
        
        input.setAttribute('aria-invalid', 'true');
        input.setAttribute('aria-describedby', fieldName + '-feedback');
    }
    
    function showFieldSuccess(fieldName) {
        const input = document.getElementById(fieldName);
        const feedback = document.getElementById(fieldName + '-feedback');
        const group = input.closest('.form-group');
        
        group.classList.add('has-success');
        group.classList.remove('has-error');
        feedback.textContent = '';
        feedback.className = 'form-feedback';
        
        input.setAttribute('aria-invalid', 'false');
        input.removeAttribute('aria-describedby');
    }
    
    function clearFieldValidation(fieldName) {
        const input = document.getElementById(fieldName);
        const feedback = document.getElementById(fieldName + '-feedback');
        const group = input.closest('.form-group');
        
        group.classList.remove('has-error', 'has-success');
        feedback.textContent = '';
        feedback.className = 'form-feedback';
        
        input.removeAttribute('aria-invalid');
        input.removeAttribute('aria-describedby');
    }
    
    function submitForm() {
        // Show loading state
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline-flex';
        submitBtn.disabled = true;
        
        // Prepare form data
        const formData = new FormData(form);
        
        // Submit via AJAX
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Success - redirect
                if (data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    window.location.href = '/dashboard';
                }
            } else {
                // Show error
                if (data.field_errors) {
                    // Show field-specific errors
                    Object.keys(data.field_errors).forEach(field => {
                        showFieldError(field, data.field_errors[field]);
                    });
                } else {
                    // Show general error
                    window.addFlashMessage('error', data.message || 'Login failed. Please check your credentials.');
                }
                
                resetSubmitButton();
            }
        })
        .catch(error => {
            console.error('Login error:', error);
            window.addFlashMessage('error', 'An error occurred. Please try again.');
            resetSubmitButton();
        });
    }
    
    function resetSubmitButton() {
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        
        btnText.style.display = 'inline-flex';
        btnLoading.style.display = 'none';
        submitBtn.disabled = false;
    }
    
    // Auto-focus username field
    usernameInput.focus();
    
    // Handle Enter key in form fields
    [usernameInput, passwordInput].forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                form.dispatchEvent(new Event('submit'));
            }
        });
    });
    
    // Rate limiting protection
    let attemptCount = 0;
    const maxAttempts = 5;
    const lockoutTime = 15 * 60 * 1000; // 15 minutes
    
    form.addEventListener('submit', function(e) {
        const lastAttempt = localStorage.getItem('lastLoginAttempt');
        const attempts = parseInt(localStorage.getItem('loginAttempts') || '0');
        
        if (attempts >= maxAttempts && lastAttempt) {
            const timeSinceLastAttempt = Date.now() - parseInt(lastAttempt);
            if (timeSinceLastAttempt < lockoutTime) {
                e.preventDefault();
                const remainingTime = Math.ceil((lockoutTime - timeSinceLastAttempt) / 60000);
                window.addFlashMessage('error', `Too many failed attempts. Please try again in ${remainingTime} minutes.`);
                return;
            } else {
                // Reset attempts after lockout period
                localStorage.removeItem('loginAttempts');
                localStorage.removeItem('lastLoginAttempt');
            }
        }
    });
    
    // Track failed attempts
    window.addEventListener('beforeunload', function() {
        // This will be called by the AJAX error handler
    });
    
    window.trackFailedLogin = function() {
        attemptCount++;
        localStorage.setItem('loginAttempts', attemptCount.toString());
        localStorage.setItem('lastLoginAttempt', Date.now().toString());
    };
    
});
</script>
