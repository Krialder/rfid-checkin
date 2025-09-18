<?php
$page_title = 'Reset Password';
$page_subtitle = 'Create a new password for your account.';
$page_class = 'reset-password-page';
$show_login_link = true;

// Token validation
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';
$is_valid_token = !empty($token) && !empty($email);
?>

<!-- Reset Password Form -->
<div class="auth-form-container">
    
    <?php if ($is_valid_token): ?>
        <!-- Valid Token - Show Reset Form -->
        
        <!-- Form Header -->
        <div class="form-header">
            <h2 class="form-title">Create New Password</h2>
            <p class="form-description">Enter a new password for your account: <strong><?= htmlspecialchars($email) ?></strong></p>
        </div>
        
        <!-- Reset Password Form -->
        <form id="reset-password-form" class="auth-form" action="/auth/reset-password-process" method="POST" novalidate>
            
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
            
            <!-- New Password -->
            <div class="form-group">
                <label for="password" class="form-label required">
                    <i class="fas fa-lock form-label-icon"></i>
                    New Password
                </label>
                <div class="form-input-group">
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-input" 
                           placeholder="Enter your new password"
                           required
                           autocomplete="new-password"
                           minlength="8">
                    <div class="form-input-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <button type="button" class="password-toggle" aria-label="Toggle password visibility">
                        <i class="fas fa-eye" data-show="fas fa-eye" data-hide="fas fa-eye-slash"></i>
                    </button>
                </div>
                <div class="form-feedback" id="password-feedback"></div>
                
                <!-- Password Strength Indicator -->
                <div class="password-strength" id="password-strength">
                    <div class="strength-meter">
                        <div class="strength-bar" id="strength-bar"></div>
                    </div>
                    <div class="strength-text" id="strength-text">Enter a password</div>
                    <div class="strength-requirements">
                        <ul class="requirements-list">
                            <li class="requirement" data-requirement="length">
                                <i class="fas fa-times"></i>
                                At least 8 characters
                            </li>
                            <li class="requirement" data-requirement="uppercase">
                                <i class="fas fa-times"></i>
                                One uppercase letter
                            </li>
                            <li class="requirement" data-requirement="lowercase">
                                <i class="fas fa-times"></i>
                                One lowercase letter
                            </li>
                            <li class="requirement" data-requirement="number">
                                <i class="fas fa-times"></i>
                                One number
                            </li>
                            <li class="requirement" data-requirement="special">
                                <i class="fas fa-times"></i>
                                One special character (!@#$%^&*)
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Confirm Password -->
            <div class="form-group">
                <label for="password_confirm" class="form-label required">
                    <i class="fas fa-lock form-label-icon"></i>
                    Confirm New Password
                </label>
                <div class="form-input-group">
                    <input type="password" 
                           id="password_confirm" 
                           name="password_confirm" 
                           class="form-input" 
                           placeholder="Confirm your new password"
                           required
                           autocomplete="new-password">
                    <div class="form-input-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                </div>
                <div class="form-feedback" id="password_confirm-feedback"></div>
            </div>
            
            <!-- Security Information -->
            <div class="security-notice">
                <div class="notice-content">
                    <i class="fas fa-info-circle notice-icon"></i>
                    <div class="notice-text">
                        <strong>Security Notice:</strong>
                        <p>After resetting your password, you'll be logged out of all devices for security.</p>
                    </div>
                </div>
            </div>
            
            <!-- Submit Button -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block btn-lg" id="reset-btn">
                    <span class="btn-text">
                        <i class="fas fa-key"></i>
                        Reset Password
                    </span>
                    <span class="btn-loading" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i>
                        Resetting Password...
                    </span>
                </button>
            </div>
            
            <!-- Alternative Actions -->
            <div class="form-alternatives">
                <p class="alternative-text">
                    Remember your password?
                    <a href="/auth/login" class="alternative-link">Sign in here</a>
                </p>
            </div>
            
        </form>
        
    <?php else: ?>
        <!-- Invalid Token - Show Error -->
        
        <div class="error-message">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3 class="error-title">Invalid Reset Link</h3>
            <p class="error-description">
                This password reset link is invalid or has expired. Reset links are only valid for 1 hour.
            </p>
            
            <div class="error-actions">
                <a href="/auth/forgot-password" class="btn btn-primary btn-block">
                    <i class="fas fa-redo"></i>
                    Request New Reset Link
                </a>
                
                <a href="/auth/login" class="btn btn-outline btn-block">
                    <i class="fas fa-arrow-left"></i>
                    Back to Sign In
                </a>
            </div>
            
            <div class="error-help">
                <h4>Common Issues:</h4>
                <ul>
                    <li>The link has expired (links are valid for 1 hour)</li>
                    <li>The link has already been used</li>
                    <li>The link was copied incorrectly</li>
                    <li>Multiple reset requests were made</li>
                </ul>
                
                <?php if (!empty($support_email)): ?>
                <p class="support-contact">
                    Still having trouble? Contact support at 
                    <a href="mailto:<?= htmlspecialchars($support_email) ?>"><?= htmlspecialchars($support_email) ?></a>
                </p>
                <?php endif; ?>
            </div>
        </div>
        
    <?php endif; ?>
    
    <!-- Success Message (Hidden by default) -->
    <div id="success-message" class="success-message" style="display: none;">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h3 class="success-title">Password Reset Successfully</h3>
        <p class="success-description">
            Your password has been reset successfully. You can now sign in with your new password.
        </p>
        
        <div class="success-actions">
            <a href="/auth/login" class="btn btn-primary btn-block">
                <i class="fas fa-sign-in-alt"></i>
                Sign In Now
            </a>
        </div>
    </div>
    
    <?php if ($is_valid_token): ?>
    <!-- Additional Information -->
    <div class="auth-info" id="auth-info">
        <div class="info-item">
            <i class="fas fa-shield-alt info-icon"></i>
            <div class="info-content">
                <strong>Secure Reset</strong>
                <p>This reset link is single-use and expires after 1 hour for your security.</p>
            </div>
        </div>
        
        <div class="info-item">
            <i class="fas fa-history info-icon"></i>
            <div class="info-content">
                <strong>Password History</strong>
                <p>Your new password cannot be the same as your last 5 passwords.</p>
            </div>
        </div>
        
        <div class="info-item">
            <i class="fas fa-user-lock info-icon"></i>
            <div class="info-content">
                <strong>Account Security</strong>
                <p>You'll be logged out of all devices after the password reset.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
</div>

<?php if ($is_valid_token): ?>
<!-- Reset Password JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('reset-password-form');
    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password_confirm');
    const submitBtn = document.getElementById('reset-btn');
    const passwordToggle = document.querySelector('.password-toggle');
    const successMessage = document.getElementById('success-message');
    const authInfo = document.getElementById('auth-info');
    
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
    passwordInput.addEventListener('blur', function() {
        validatePassword();
    });
    
    passwordConfirmInput.addEventListener('blur', function() {
        validatePasswordConfirm();
    });
    
    passwordInput.addEventListener('input', function() {
        clearFieldValidation('password');
        updatePasswordStrength(this.value);
    });
    
    passwordConfirmInput.addEventListener('input', function() {
        clearFieldValidation('password_confirm');
    });
    
    function validateForm() {
        let isValid = true;
        
        if (!validatePassword()) {
            isValid = false;
        }
        
        if (!validatePasswordConfirm()) {
            isValid = false;
        }
        
        return isValid;
    }
    
    function validatePassword() {
        const value = passwordInput.value;
        
        if (!value) {
            showFieldError('password', 'Password is required');
            return false;
        }
        
        if (value.length < 8) {
            showFieldError('password', 'Password must be at least 8 characters');
            return false;
        }
        
        const strength = calculatePasswordStrength(value);
        if (strength < 3) {
            showFieldError('password', 'Password is too weak. Please meet all requirements.');
            return false;
        }
        
        showFieldSuccess('password');
        return true;
    }
    
    function validatePasswordConfirm() {
        const password = passwordInput.value;
        const confirm = passwordConfirmInput.value;
        
        if (!confirm) {
            showFieldError('password_confirm', 'Password confirmation is required');
            return false;
        }
        
        if (password !== confirm) {
            showFieldError('password_confirm', 'Passwords do not match');
            return false;
        }
        
        showFieldSuccess('password_confirm');
        return true;
    }
    
    function updatePasswordStrength(password) {
        const strength = calculatePasswordStrength(password);
        const strengthBar = document.getElementById('strength-bar');
        const strengthText = document.getElementById('strength-text');
        const requirements = document.querySelectorAll('.requirement');
        
        // Update strength bar
        const strengthClasses = ['', 'strength-weak', 'strength-fair', 'strength-good', 'strength-strong'];
        const strengthTexts = ['Enter a password', 'Weak', 'Fair', 'Good', 'Strong'];
        
        strengthBar.className = 'strength-bar ' + (strengthClasses[strength] || '');
        strengthBar.style.width = (strength * 25) + '%';
        strengthText.textContent = strengthTexts[strength] || 'Enter a password';
        
        // Update requirements
        const checks = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /\d/.test(password),
            special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
        };
        
        requirements.forEach(req => {
            const requirement = req.dataset.requirement;
            const icon = req.querySelector('i');
            
            if (checks[requirement]) {
                req.classList.add('met');
                icon.className = 'fas fa-check';
            } else {
                req.classList.remove('met');
                icon.className = 'fas fa-times';
            }
        });
    }
    
    function calculatePasswordStrength(password) {
        let strength = 0;
        
        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/\d/.test(password)) strength++;
        if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) strength++;
        
        return strength;
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
                // Show success message
                showSuccessMessage();
                
                // Redirect after 3 seconds
                setTimeout(() => {
                    window.location.href = data.redirect || '/auth/login';
                }, 3000);
                
            } else {
                // Show errors
                if (data.field_errors) {
                    Object.keys(data.field_errors).forEach(field => {
                        showFieldError(field, data.field_errors[field]);
                    });
                } else {
                    window.addFlashMessage('error', data.message || 'Password reset failed. Please try again.');
                }
                
                resetSubmitButton();
            }
        })
        .catch(error => {
            console.error('Reset password error:', error);
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
    
    function showSuccessMessage() {
        // Hide form and info
        form.style.display = 'none';
        authInfo.style.display = 'none';
        
        // Show success message
        successMessage.style.display = 'block';
        
        // Animate in
        successMessage.style.animation = 'slideInDown 0.5s ease-out';
    }
    
    // Auto-focus password field
    passwordInput.focus();
    
    // Handle Enter key
    [passwordInput, passwordConfirmInput].forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                form.dispatchEvent(new Event('submit'));
            }
        });
    });
    
    // Token expiration check
    const tokenExpiry = <?= json_encode($token_expires_at ?? null) ?>;
    if (tokenExpiry) {
        const expiryTime = new Date(tokenExpiry).getTime();
        
        const checkExpiry = () => {
            if (Date.now() >= expiryTime) {
                window.addFlashMessage('error', 'This reset link has expired. Please request a new one.');
                setTimeout(() => {
                    window.location.href = '/auth/forgot-password';
                }, 3000);
            }
        };
        
        // Check every minute
        setInterval(checkExpiry, 60000);
    }
    
});
</script>
<?php endif; ?>
