<?php
$page_title = 'Forgot Password';
$page_subtitle = 'Enter your email address and we\'ll send you a link to reset your password.';
$page_class = 'forgot-password-page';
$show_login_link = true;
?>

<!-- Forgot Password Form -->
<div class="auth-form-container">
    
    <!-- Form Header -->
    <div class="form-header">
        <h2 class="form-title">Reset Your Password</h2>
        <p class="form-description">Enter your email address and we'll send you a secure link to reset your password</p>
    </div>
    
    <!-- Forgot Password Form -->
    <form id="forgot-password-form" class="auth-form" action="/auth/forgot-password-process" method="POST" novalidate>
        
        <!-- CSRF Token -->
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        
        <!-- Email Field -->
        <div class="form-group">
            <label for="email" class="form-label required">
                <i class="fas fa-envelope form-label-icon"></i>
                Email Address
            </label>
            <div class="form-input-group">
                <input type="email" 
                       id="email" 
                       name="email" 
                       class="form-input" 
                       placeholder="Enter your email address"
                       value="<?= htmlspecialchars($_POST['email'] ?? $_GET['email'] ?? '') ?>"
                       required
                       autocomplete="email"
                       maxlength="100">
                <div class="form-input-icon">
                    <i class="fas fa-envelope"></i>
                </div>
            </div>
            <div class="form-feedback" id="email-feedback"></div>
            <div class="form-help">
                Enter the email address associated with your account. We'll send you a password reset link.
            </div>
        </div>
        
        <!-- Submit Button -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-block btn-lg" id="forgot-btn">
                <span class="btn-text">
                    <i class="fas fa-paper-plane"></i>
                    Send Reset Link
                </span>
                <span class="btn-loading" style="display: none;">
                    <i class="fas fa-spinner fa-spin"></i>
                    Sending...
                </span>
            </button>
        </div>
        
        <!-- Alternative Actions -->
        <div class="form-alternatives">
            <p class="alternative-text">
                Remember your password?
                <a href="/auth/login" class="alternative-link">Sign in here</a>
            </p>
            
            <p class="alternative-text">
                Don't have an account?
                <a href="/auth/register" class="alternative-link">Create one here</a>
            </p>
        </div>
        
    </form>
    
    <!-- Success Message (Hidden by default) -->
    <div id="success-message" class="success-message" style="display: none;">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h3 class="success-title">Check Your Email</h3>
        <p class="success-description">
            We've sent a password reset link to your email address. 
            Please check your inbox and follow the instructions to reset your password.
        </p>
        
        <div class="success-actions">
            <a href="/auth/login" class="btn btn-outline btn-block">
                <i class="fas fa-arrow-left"></i>
                Back to Sign In
            </a>
        </div>
        
        <div class="success-help">
            <p>Didn't receive an email?</p>
            <ul>
                <li>Check your spam/junk folder</li>
                <li>Make sure the email address is correct</li>
                <li>Wait a few minutes for the email to arrive</li>
                <li>Contact support if you still don't receive it</li>
            </ul>
            
            <button type="button" class="resend-link" id="resend-btn" disabled>
                Resend Email <span class="countdown">(60s)</span>
            </button>
        </div>
    </div>
    
    <!-- Additional Information -->
    <div class="auth-info" id="auth-info">
        <div class="info-item">
            <i class="fas fa-shield-alt info-icon"></i>
            <div class="info-content">
                <strong>Secure Process</strong>
                <p>Password reset links are valid for 1 hour and can only be used once for security.</p>
            </div>
        </div>
        
        <div class="info-item">
            <i class="fas fa-clock info-icon"></i>
            <div class="info-content">
                <strong>Response Time</strong>
                <p>Reset emails are typically delivered within 2-5 minutes.</p>
            </div>
        </div>
        
        <div class="info-item">
            <i class="fas fa-user-lock info-icon"></i>
            <div class="info-content">
                <strong>Account Security</strong>
                <p>If you suspect unauthorized access, contact support immediately.</p>
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

<!-- Forgot Password JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('forgot-password-form');
    const emailInput = document.getElementById('email');
    const submitBtn = document.getElementById('forgot-btn');
    const successMessage = document.getElementById('success-message');
    const authInfo = document.getElementById('auth-info');
    const resendBtn = document.getElementById('resend-btn');
    
    let resendTimeout;
    let resendCountdown;
    
    // Form validation
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (validateForm()) {
            submitForm();
        }
    });
    
    // Real-time validation
    emailInput.addEventListener('blur', function() {
        validateEmail();
    });
    
    emailInput.addEventListener('input', function() {
        clearFieldValidation('email');
    });
    
    function validateForm() {
        return validateEmail();
    }
    
    function validateEmail() {
        const value = emailInput.value.trim();
        
        if (!value) {
            showFieldError('email', 'Email address is required');
            return false;
        }
        
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            showFieldError('email', 'Please enter a valid email address');
            return false;
        }
        
        showFieldSuccess('email');
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
                // Show success message
                showSuccessMessage(data.message);
                
                // Start resend countdown
                startResendCountdown();
                
            } else {
                // Show error
                if (data.field_errors && data.field_errors.email) {
                    showFieldError('email', data.field_errors.email);
                } else {
                    window.addFlashMessage('error', data.message || 'Failed to send reset email. Please try again.');
                }
                
                resetSubmitButton();
            }
        })
        .catch(error => {
            console.error('Forgot password error:', error);
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
    
    function showSuccessMessage(message) {
        // Hide form and info
        form.style.display = 'none';
        authInfo.style.display = 'none';
        
        // Show success message
        successMessage.style.display = 'block';
        
        // Update message if provided
        if (message) {
            const description = successMessage.querySelector('.success-description');
            description.textContent = message;
        }
        
        // Animate in
        successMessage.style.animation = 'slideInDown 0.5s ease-out';
    }
    
    function startResendCountdown() {
        let countdown = 60;
        
        const updateCountdown = () => {
            const countdownSpan = resendBtn.querySelector('.countdown');
            countdownSpan.textContent = `(${countdown}s)`;
            countdown--;
            
            if (countdown < 0) {
                clearInterval(resendCountdown);
                resendBtn.disabled = false;
                resendBtn.innerHTML = 'Resend Email';
                
                // Add resend functionality
                resendBtn.addEventListener('click', function() {
                    resendResetEmail();
                });
            }
        };
        
        updateCountdown();
        resendCountdown = setInterval(updateCountdown, 1000);
    }
    
    function resendResetEmail() {
        resendBtn.disabled = true;
        resendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        
        const formData = new FormData();
        formData.append('email', emailInput.value);
        formData.append('csrf_token', window.App.csrfToken);
        
        fetch('/auth/forgot-password-process', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.addFlashMessage('success', 'Reset email sent again. Please check your inbox.');
                startResendCountdown();
            } else {
                window.addFlashMessage('error', data.message || 'Failed to resend email. Please try again.');
                resendBtn.disabled = false;
                resendBtn.innerHTML = 'Resend Email';
            }
        })
        .catch(error => {
            console.error('Resend error:', error);
            window.addFlashMessage('error', 'An error occurred. Please try again.');
            resendBtn.disabled = false;
            resendBtn.innerHTML = 'Resend Email';
        });
    }
    
    // Auto-focus email field
    emailInput.focus();
    
    // Handle Enter key
    emailInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            form.dispatchEvent(new Event('submit'));
        }
    });
    
    // Rate limiting protection
    let attemptCount = 0;
    const maxAttempts = 3;
    const lockoutTime = 15 * 60 * 1000; // 15 minutes
    
    form.addEventListener('submit', function(e) {
        const lastAttempt = localStorage.getItem('lastForgotAttempt');
        const attempts = parseInt(localStorage.getItem('forgotAttempts') || '0');
        
        if (attempts >= maxAttempts && lastAttempt) {
            const timeSinceLastAttempt = Date.now() - parseInt(lastAttempt);
            if (timeSinceLastAttempt < lockoutTime) {
                e.preventDefault();
                const remainingTime = Math.ceil((lockoutTime - timeSinceLastAttempt) / 60000);
                window.addFlashMessage('error', `Too many reset requests. Please try again in ${remainingTime} minutes.`);
                return;
            } else {
                // Reset attempts after lockout period
                localStorage.removeItem('forgotAttempts');
                localStorage.removeItem('lastForgotAttempt');
            }
        }
    });
    
    // Track attempts on error
    window.addEventListener('forgotPasswordError', function() {
        attemptCount++;
        localStorage.setItem('forgotAttempts', attemptCount.toString());
        localStorage.setItem('lastForgotAttempt', Date.now().toString());
    });
    
});
</script>
