<?php
$page_title = 'Create Account';
$page_subtitle = 'Join our RFID check-in system. Please fill out the form below.';
$page_class = 'register-page';
$show_login_link = true;
?>

<!-- Registration Form -->
<div class="auth-form-container">
    
    <!-- Form Header -->
    <div class="form-header">
        <h2 class="form-title">Create Account</h2>
        <p class="form-description">Fill out the information below to create your account</p>
    </div>
    
    <!-- Registration Form -->
    <form id="register-form" class="auth-form" action="/auth/register-process" method="POST" novalidate>
        
        <!-- CSRF Token -->
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        
        <!-- Personal Information Section -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-user"></i>
                Personal Information
            </h3>
            
            <!-- First Name -->
            <div class="form-group">
                <label for="first_name" class="form-label required">
                    <i class="fas fa-user form-label-icon"></i>
                    First Name
                </label>
                <div class="form-input-group">
                    <input type="text" 
                           id="first_name" 
                           name="first_name" 
                           class="form-input" 
                           placeholder="Enter your first name"
                           value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                           required
                           autocomplete="given-name"
                           maxlength="50">
                    <div class="form-input-icon">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                <div class="form-feedback" id="first_name-feedback"></div>
            </div>
            
            <!-- Last Name -->
            <div class="form-group">
                <label for="last_name" class="form-label required">
                    <i class="fas fa-user form-label-icon"></i>
                    Last Name
                </label>
                <div class="form-input-group">
                    <input type="text" 
                           id="last_name" 
                           name="last_name" 
                           class="form-input" 
                           placeholder="Enter your last name"
                           value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                           required
                           autocomplete="family-name"
                           maxlength="50">
                    <div class="form-input-icon">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                <div class="form-feedback" id="last_name-feedback"></div>
            </div>
            
            <!-- Email -->
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
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required
                           autocomplete="email"
                           maxlength="100">
                    <div class="form-input-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                </div>
                <div class="form-feedback" id="email-feedback"></div>
                <div class="form-help">
                    We'll use this email for account notifications and password resets.
                </div>
            </div>
        </div>
        
        <!-- Account Information Section -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-key"></i>
                Account Information
            </h3>
            
            <!-- Username -->
            <div class="form-group">
                <label for="username" class="form-label required">
                    <i class="fas fa-at form-label-icon"></i>
                    Username
                </label>
                <div class="form-input-group">
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="form-input" 
                           placeholder="Choose a username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           required
                           autocomplete="username"
                           autocapitalize="none"
                           spellcheck="false"
                           maxlength="30"
                           pattern="[a-zA-Z0-9_-]+">
                    <div class="form-input-icon">
                        <i class="fas fa-at"></i>
                    </div>
                    <div class="availability-indicator" id="username-availability">
                        <i class="fas fa-spinner fa-spin" style="display: none;"></i>
                    </div>
                </div>
                <div class="form-feedback" id="username-feedback"></div>
                <div class="form-help">
                    3-30 characters. Letters, numbers, underscore, and dash only.
                </div>
            </div>
            
            <!-- Password -->
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
                           placeholder="Create a strong password"
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
                    Confirm Password
                </label>
                <div class="form-input-group">
                    <input type="password" 
                           id="password_confirm" 
                           name="password_confirm" 
                           class="form-input" 
                           placeholder="Confirm your password"
                           required
                           autocomplete="new-password">
                    <div class="form-input-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                </div>
                <div class="form-feedback" id="password_confirm-feedback"></div>
            </div>
        </div>
        
        <!-- Terms and Privacy -->
        <div class="form-section">
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="agree_terms" value="1" class="checkbox-input" required>
                    <span class="checkbox-custom"></span>
                    <span class="checkbox-text">
                        I agree to the <a href="/terms" target="_blank">Terms of Service</a> 
                        and <a href="/privacy" target="_blank">Privacy Policy</a>
                    </span>
                </label>
                <div class="form-feedback" id="agree_terms-feedback"></div>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="email_notifications" value="1" class="checkbox-input" checked>
                    <span class="checkbox-custom"></span>
                    <span class="checkbox-text">
                        Send me email notifications about account activity and events
                    </span>
                </label>
            </div>
        </div>
        
        <!-- Submit Button -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-block btn-lg" id="register-btn">
                <span class="btn-text">
                    <i class="fas fa-user-plus"></i>
                    Create Account
                </span>
                <span class="btn-loading" style="display: none;">
                    <i class="fas fa-spinner fa-spin"></i>
                    Creating Account...
                </span>
            </button>
        </div>
        
        <!-- Alternative Actions -->
        <div class="form-alternatives">
            <p class="alternative-text">
                Already have an account?
                <a href="/auth/login" class="alternative-link">Sign in here</a>
            </p>
        </div>
        
    </form>
    
    <!-- Registration Information -->
    <div class="auth-info">
        <div class="info-item">
            <i class="fas fa-shield-alt info-icon"></i>
            <div class="info-content">
                <strong>Account Security</strong>
                <p>Your account will be secured with industry-standard encryption and security measures.</p>
            </div>
        </div>
        
        <div class="info-item">
            <i class="fas fa-user-check info-icon"></i>
            <div class="info-content">
                <strong>Account Approval</strong>
                <p>New accounts require administrator approval before gaining access to the system.</p>
            </div>
        </div>
        
        <div class="info-item">
            <i class="fas fa-envelope info-icon"></i>
            <div class="info-content">
                <strong>Email Verification</strong>
                <p>You'll receive a verification email to confirm your email address after registration.</p>
            </div>
        </div>
    </div>
    
</div>

<!-- Registration Form JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('register-form');
    const inputs = {
        firstName: document.getElementById('first_name'),
        lastName: document.getElementById('last_name'),
        email: document.getElementById('email'),
        username: document.getElementById('username'),
        password: document.getElementById('password'),
        passwordConfirm: document.getElementById('password_confirm')
    };
    const submitBtn = document.getElementById('register-btn');
    const passwordToggle = document.querySelector('.password-toggle');
    
    let usernameCheckTimeout;
    
    // Password visibility toggle
    if (passwordToggle) {
        passwordToggle.addEventListener('click', function() {
            const input = inputs.password;
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
    Object.keys(inputs).forEach(key => {
        const input = inputs[key];
        input.addEventListener('blur', function() {
            validateField(key);
        });
        
        input.addEventListener('input', function() {
            clearFieldValidation(key);
            
            // Special handling for password strength
            if (key === 'password') {
                updatePasswordStrength(this.value);
            }
            
            // Username availability check
            if (key === 'username') {
                clearTimeout(usernameCheckTimeout);
                usernameCheckTimeout = setTimeout(() => {
                    checkUsernameAvailability(this.value);
                }, 500);
            }
        });
    });
    
    function validateForm() {
        let isValid = true;
        
        Object.keys(inputs).forEach(key => {
            if (!validateField(key)) {
                isValid = false;
            }
        });
        
        // Check terms agreement
        const agreeTerms = document.querySelector('input[name="agree_terms"]');
        if (!agreeTerms.checked) {
            showFieldError('agree_terms', 'You must agree to the terms of service');
            isValid = false;
        }
        
        return isValid;
    }
    
    function validateField(fieldName) {
        const validators = {
            firstName: validateFirstName,
            lastName: validateLastName,
            email: validateEmail,
            username: validateUsername,
            password: validatePassword,
            passwordConfirm: validatePasswordConfirm
        };
        
        return validators[fieldName] ? validators[fieldName]() : true;
    }
    
    function validateFirstName() {
        const value = inputs.firstName.value.trim();
        
        if (!value) {
            showFieldError('first_name', 'First name is required');
            return false;
        }
        
        if (value.length < 2) {
            showFieldError('first_name', 'First name must be at least 2 characters');
            return false;
        }
        
        if (!/^[a-zA-Z\s-']+$/.test(value)) {
            showFieldError('first_name', 'First name can only contain letters, spaces, hyphens, and apostrophes');
            return false;
        }
        
        showFieldSuccess('first_name');
        return true;
    }
    
    function validateLastName() {
        const value = inputs.lastName.value.trim();
        
        if (!value) {
            showFieldError('last_name', 'Last name is required');
            return false;
        }
        
        if (value.length < 2) {
            showFieldError('last_name', 'Last name must be at least 2 characters');
            return false;
        }
        
        if (!/^[a-zA-Z\s-']+$/.test(value)) {
            showFieldError('last_name', 'Last name can only contain letters, spaces, hyphens, and apostrophes');
            return false;
        }
        
        showFieldSuccess('last_name');
        return true;
    }
    
    function validateEmail() {
        const value = inputs.email.value.trim();
        
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
    
    function validateUsername() {
        const value = inputs.username.value.trim();
        
        if (!value) {
            showFieldError('username', 'Username is required');
            return false;
        }
        
        if (value.length < 3 || value.length > 30) {
            showFieldError('username', 'Username must be between 3 and 30 characters');
            return false;
        }
        
        if (!/^[a-zA-Z0-9_-]+$/.test(value)) {
            showFieldError('username', 'Username can only contain letters, numbers, underscore, and dash');
            return false;
        }
        
        showFieldSuccess('username');
        return true;
    }
    
    function validatePassword() {
        const value = inputs.password.value;
        
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
        const password = inputs.password.value;
        const confirm = inputs.passwordConfirm.value;
        
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
    
    function checkUsernameAvailability(username) {
        if (username.length < 3) return;
        
        const indicator = document.getElementById('username-availability');
        const spinner = indicator.querySelector('.fa-spinner');
        
        spinner.style.display = 'inline';
        
        fetch('/api/auth/check-username', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.App.csrfToken
            },
            body: JSON.stringify({ username: username })
        })
        .then(response => response.json())
        .then(data => {
            spinner.style.display = 'none';
            
            if (data.available) {
                indicator.innerHTML = '<i class="fas fa-check text-success"></i>';
                indicator.title = 'Username is available';
            } else {
                indicator.innerHTML = '<i class="fas fa-times text-error"></i>';
                indicator.title = 'Username is not available';
                showFieldError('username', 'This username is already taken');
            }
        })
        .catch(error => {
            spinner.style.display = 'none';
            console.error('Username check error:', error);
        });
    }
    
    function showFieldError(fieldName, message) {
        const input = document.getElementById(fieldName) || document.querySelector(`[name="${fieldName}"]`);
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
                // Success - show message and redirect
                window.addFlashMessage('success', data.message || 'Account created successfully! Please check your email for verification instructions.');
                
                setTimeout(() => {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        window.location.href = '/auth/login';
                    }
                }, 2000);
            } else {
                // Show errors
                if (data.field_errors) {
                    Object.keys(data.field_errors).forEach(field => {
                        showFieldError(field, data.field_errors[field]);
                    });
                } else {
                    window.addFlashMessage('error', data.message || 'Registration failed. Please check your information and try again.');
                }
                
                resetSubmitButton();
            }
        })
        .catch(error => {
            console.error('Registration error:', error);
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
    
    // Auto-focus first field
    inputs.firstName.focus();
    
});
</script>
