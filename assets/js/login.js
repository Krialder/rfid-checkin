/*
 * Login Page JavaScript
 * Enhanced login form with validation and user experience improvements
 */

class LoginForm {
    constructor() {
        this.form = document.getElementById('loginForm');
        this.emailInput = document.getElementById('email');
        this.passwordInput = document.getElementById('password');
        this.rememberCheckbox = document.querySelector('input[name="remember_me"]');
        this.submitButton = document.querySelector('button[type="submit"]');
        
        this.init();
    }
    
    init() {
        this.initFormValidation();
        this.initPasswordToggle();
        this.initCapsLockDetection();
        this.initFormSubmission();
        this.initKeyboardShortcuts();
        this.restoreFormData();
    }
    
    initFormValidation() {
        // Less aggressive email validation - only on blur
        this.emailInput.addEventListener('blur', (e) => {
            this.validateEmail(e.target);
        });
        
        // Password validation - only show errors on blur, not while typing
        this.passwordInput.addEventListener('blur', (e) => {
            this.validatePassword(e.target);
        });
        
        // Clear errors when user starts typing again
        this.emailInput.addEventListener('input', () => {
            this.clearFieldError(this.emailInput);
        });
        
        this.passwordInput.addEventListener('input', () => {
            this.clearFieldError(this.passwordInput);
        });
        
        // Form submission validation - only basic checks
        this.form.addEventListener('submit', (e) => {
            // Clear any existing errors first
            this.clearAllErrors();
            
            // Only prevent submission if truly invalid
            if (!this.basicValidation()) {
                e.preventDefault();
                this.resetSubmitButton();
                return false;
            }
        });
    }
    
    validateEmail(input) {
        const email = input.value.trim();
        
        // Don't validate empty fields - let HTML5 required handle it
        if (email === '') {
            this.clearFieldError(input);
            return true;
        }
        
        // Simple email validation - more permissive
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (!emailRegex.test(email)) {
            this.showFieldError(input, 'Please enter a valid email address');
            return false;
        }
        
        this.showFieldSuccess(input);
        return true;
    }
    
    validatePassword(input) {
        const password = input.value;
        
        // Don't validate empty fields - let HTML5 required handle it
        if (password === '') {
            this.clearFieldError(input);
            return true;
        }
        
        // Very basic password validation
        if (password.length < 3) {
            this.showFieldError(input, 'Password is too short');
            return false;
        }
        
        this.showFieldSuccess(input);
        return true;
    }
    
    // Simplified validation for form submission
    basicValidation() {
        const email = this.emailInput.value.trim();
        const password = this.passwordInput.value;
        
        // Check required fields
        if (email === '') {
            this.showFieldError(this.emailInput, 'Email is required');
            this.emailInput.focus();
            return false;
        }
        
        if (password === '') {
            this.showFieldError(this.passwordInput, 'Password is required');
            this.passwordInput.focus();
            return false;
        }
        
        // Basic email format check
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            this.showFieldError(this.emailInput, 'Please enter a valid email address');
            this.emailInput.focus();
            return false;
        }
        
        return true;
    }
    
    initPasswordToggle() {
        // Create password visibility toggle
        const passwordGroup = this.passwordInput.parentElement;
        const toggleButton = document.createElement('button');
        toggleButton.type = 'button';
        toggleButton.className = 'password-toggle';
        toggleButton.innerHTML = '👁️';
        toggleButton.title = 'Show password';
        
        // Style the toggle button
        toggleButton.style.cssText = `
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: var(--text-secondary);
            z-index: 1;
        `;
        
        // Make password group relative for positioning
        passwordGroup.style.position = 'relative';
        this.passwordInput.style.paddingRight = '40px';
        
        passwordGroup.appendChild(toggleButton);
        
        toggleButton.addEventListener('click', () => {
            const type = this.passwordInput.getAttribute('type');
            if (type === 'password') {
                this.passwordInput.setAttribute('type', 'text');
                toggleButton.innerHTML = '🙈';
                toggleButton.title = 'Hide password';
            } else {
                this.passwordInput.setAttribute('type', 'password');
                toggleButton.innerHTML = '👁️';
                toggleButton.title = 'Show password';
            }
        });
    }
    
    initCapsLockDetection() {
        const capsLockWarning = document.createElement('div');
        capsLockWarning.className = 'caps-lock-warning';
        capsLockWarning.innerHTML = '⚠️ Caps Lock is ON';
        capsLockWarning.style.cssText = `
            display: none;
            color: var(--warning-color);
            font-size: 0.875rem;
            margin-top: 5px;
            padding: 5px 10px;
            background: rgba(217, 119, 6, 0.1);
            border-radius: 4px;
        `;
        
        this.passwordInput.parentElement.appendChild(capsLockWarning);
        
        this.passwordInput.addEventListener('keydown', (e) => {
            // Detect caps lock
            if (e.getModifierState && e.getModifierState('CapsLock')) {
                capsLockWarning.style.display = 'block';
            } else {
                capsLockWarning.style.display = 'none';
            }
        });
        
        this.passwordInput.addEventListener('keyup', (e) => {
            if (e.getModifierState && e.getModifierState('CapsLock')) {
                capsLockWarning.style.display = 'block';
            } else {
                capsLockWarning.style.display = 'none';
            }
        });
    }
    
    initFormSubmission() {
        this.form.addEventListener('submit', (e) => {
            // Prevent double submissions
            if (this.submitButton.disabled) {
                e.preventDefault();
                return;
            }
            
            // Show loading state
            this.setLoadingState();
            
            // Save form data for restoration if login fails
            this.saveFormData();
            
            // Set a timeout to reset the button if something goes wrong
            this.submitTimeout = setTimeout(() => {
                this.resetSubmitButton();
            }, 10000); // 10 seconds timeout
        });
    }
    
    setLoadingState() {
        this.submitButton.disabled = true;
        this.submitButton.innerHTML = `
            <span class="loading-spinner"></span>
            Signing In...
        `;
    }
    
    resetSubmitButton() {
        if (this.submitTimeout) {
            clearTimeout(this.submitTimeout);
        }
        this.submitButton.disabled = false;
        this.submitButton.innerHTML = 'Sign In';
    }
    
    clearAllErrors() {
        this.clearFieldError(this.emailInput);
        this.clearFieldError(this.passwordInput);
        
        // Remove any existing alert messages
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (alert.classList.contains('alert-error')) {
                alert.style.display = 'none';
            }
        });
    }
    
    initKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Enter key to submit - but only if form is valid
            if (e.key === 'Enter' && (e.target === this.emailInput || e.target === this.passwordInput)) {
                e.preventDefault();
                
                // Clear any existing errors
                this.clearAllErrors();
                
                // Only submit if basic validation passes
                if (this.basicValidation() && !this.submitButton.disabled) {
                    this.form.submit();
                }
            }
            
            // Escape key to clear errors
            if (e.key === 'Escape') {
                this.clearAllErrors();
                this.resetSubmitButton();
            }
        });
    }
    
    saveFormData() {
        if (this.rememberCheckbox.checked) {
            localStorage.setItem('remembered_email', this.emailInput.value);
        } else {
            localStorage.removeItem('remembered_email');
        }
    }
    
    restoreFormData() {
        const rememberedEmail = localStorage.getItem('remembered_email');
        if (rememberedEmail && !this.emailInput.value) {
            this.emailInput.value = rememberedEmail;
            this.rememberCheckbox.checked = true;
        }
    }
    
    showFieldError(input, message) {
        this.clearFieldError(input);
        
        input.classList.add('has-error');
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.textContent = message;
        errorDiv.style.cssText = `
            color: var(--error-color);
            font-size: 0.875rem;
            margin-top: 5px;
            padding: 5px 10px;
            background: rgba(220, 38, 38, 0.1);
            border-radius: 4px;
            border-left: 3px solid var(--error-color);
        `;
        
        input.parentElement.appendChild(errorDiv);
    }
    
    showFieldSuccess(input) {
        this.clearFieldError(input);
        input.classList.remove('has-error');
        input.classList.add('has-success');
    }
    
    clearFieldError(input) {
        input.classList.remove('has-error', 'has-success');
        const existingError = input.parentElement.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
    }
    
    showNotification(message, type = 'info') {
        // Remove existing notifications
        document.querySelectorAll('.login-notification').forEach(n => n.remove());
        
        const notification = document.createElement('div');
        notification.className = `login-notification alert alert-${type}`;
        notification.innerHTML = `
            <span>${this.escapeHtml(message)}</span>
            <button class="notification-close" onclick="this.parentElement.remove()">&times;</button>
        `;
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 500px;
            padding: 15px 20px;
            border-radius: 6px;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: slideInRight 0.3s ease-out;
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
    
    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
}

// Enhanced CSS styles
const loginStyles = `
    .has-error {
        border-color: var(--error-color) !important;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1) !important;
    }
    
    .has-success {
        border-color: var(--success-color) !important;
        box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1) !important;
    }
    
    .loading-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255,255,255,0.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 1s ease-in-out infinite;
        margin-right: 8px;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    .password-toggle:hover {
        color: var(--text-primary) !important;
    }
    
    .notification-close {
        background: none;
        border: none;
        color: inherit;
        font-size: 1.2rem;
        cursor: pointer;
        margin-left: 10px;
        padding: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        opacity: 0.7;
    }
    
    .notification-close:hover {
        opacity: 1;
        background: rgba(0,0,0,0.1);
    }
`;

// Add styles to document
const styleSheet = document.createElement('style');
styleSheet.textContent = loginStyles;
document.head.appendChild(styleSheet);

// Initialize login form when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    new LoginForm();
});

// Handle browser back button and form restoration
window.addEventListener('pageshow', function(event) {
    // Reset form state if coming back from navigation
    const form = document.getElementById('loginForm');
    const submitButton = document.querySelector('button[type="submit"]');
    
    if (submitButton && submitButton.disabled) {
        submitButton.disabled = false;
        submitButton.innerHTML = 'Sign In';
    }
    
    // Clear any loading states
    const loadingSpinners = document.querySelectorAll('.loading-spinner');
    loadingSpinners.forEach(spinner => spinner.remove());
});

// Reset form state on page load
window.addEventListener('load', function() {
    const submitButton = document.querySelector('button[type="submit"]');
    if (submitButton && submitButton.disabled) {
        submitButton.disabled = false;
        submitButton.innerHTML = 'Sign In';
    }
});

// Handle network errors and reset button
window.addEventListener('beforeunload', function() {
    const submitButton = document.querySelector('button[type="submit"]');
    if (submitButton && submitButton.disabled) {
        // Don't reset here - let the page load handle it
    }
});

// Auto-reset button after a reasonable time
document.addEventListener('DOMContentLoaded', function() {
    // If the page loads and we're still showing loading, something went wrong
    setTimeout(function() {
        const submitButton = document.querySelector('button[type="submit"]');
        if (submitButton && submitButton.innerHTML.includes('Signing In')) {
            submitButton.disabled = false;
            submitButton.innerHTML = 'Sign In';
        }
    }, 2000); // Reset after 2 seconds if still loading
});
