<!-- Flash Messages Container -->
<div class="flash-messages-container">
    <?php foreach ($flash_messages as $type => $messages): ?>
        <?php foreach ($messages as $message): ?>
            <div class="alert alert-<?= htmlspecialchars($type) ?> flash-message" 
                 role="alert" 
                 data-type="<?= htmlspecialchars($type) ?>">
                
                <!-- Alert Icon -->
                <div class="alert-icon">
                    <?php
                    $icons = [
                        'success' => 'check-circle',
                        'error' => 'exclamation-circle',
                        'warning' => 'exclamation-triangle',
                        'info' => 'info-circle'
                    ];
                    $icon = $icons[$type] ?? 'info-circle';
                    ?>
                    <i class="fas fa-<?= $icon ?>"></i>
                </div>
                
                <!-- Alert Content -->
                <div class="alert-content">
                    <div class="alert-message"><?= htmlspecialchars($message) ?></div>
                    
                    <!-- Additional context for specific message types -->
                    <?php if ($type === 'error' && isset($error_details)): ?>
                        <div class="alert-details">
                            <button type="button" class="toggle-details" aria-label="Show error details">
                                Show Details <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="error-details" style="display: none;">
                                <pre><?= htmlspecialchars($error_details) ?></pre>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Progress Bar (for success/info messages) -->
                <?php if (in_array($type, ['success', 'info'])): ?>
                    <div class="alert-progress">
                        <div class="progress-bar"></div>
                    </div>
                <?php endif; ?>
                
                <!-- Close Button -->
                <button type="button" class="alert-close" aria-label="Dismiss alert">
                    <i class="fas fa-times"></i>
                </button>
                
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>

<!-- Flash Messages JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const flashMessages = document.querySelectorAll('.flash-message');
    
    flashMessages.forEach(function(message) {
        const type = message.dataset.type;
        const closeBtn = message.querySelector('.alert-close');
        const toggleDetailsBtn = message.querySelector('.toggle-details');
        const errorDetails = message.querySelector('.error-details');
        
        // Close button functionality
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                dismissMessage(message);
            });
        }
        
        // Toggle error details
        if (toggleDetailsBtn && errorDetails) {
            toggleDetailsBtn.addEventListener('click', function() {
                const isVisible = errorDetails.style.display !== 'none';
                errorDetails.style.display = isVisible ? 'none' : 'block';
                
                const icon = toggleDetailsBtn.querySelector('i');
                icon.className = isVisible ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
                
                toggleDetailsBtn.innerHTML = isVisible ? 
                    'Show Details <i class="fas fa-chevron-down"></i>' : 
                    'Hide Details <i class="fas fa-chevron-up"></i>';
            });
        }
        
        // Auto-dismiss based on type
        if (type === 'success') {
            // Success messages auto-dismiss after 5 seconds
            setTimeout(() => {
                if (message.parentNode) {
                    dismissMessage(message);
                }
            }, 5000);
            
            // Add progress bar animation
            const progressBar = message.querySelector('.progress-bar');
            if (progressBar) {
                progressBar.style.animation = 'progressBarCountdown 5s linear forwards';
            }
        } else if (type === 'info') {
            // Info messages auto-dismiss after 7 seconds
            setTimeout(() => {
                if (message.parentNode) {
                    dismissMessage(message);
                }
            }, 7000);
            
            // Add progress bar animation
            const progressBar = message.querySelector('.progress-bar');
            if (progressBar) {
                progressBar.style.animation = 'progressBarCountdown 7s linear forwards';
            }
        }
        
        // Pause auto-dismiss on hover
        message.addEventListener('mouseenter', function() {
            const progressBar = message.querySelector('.progress-bar');
            if (progressBar) {
                progressBar.style.animationPlayState = 'paused';
            }
        });
        
        message.addEventListener('mouseleave', function() {
            const progressBar = message.querySelector('.progress-bar');
            if (progressBar) {
                progressBar.style.animationPlayState = 'running';
            }
        });
        
        // Add entrance animation
        message.style.animation = 'slideInDown 0.3s ease-out';
    });
    
    function dismissMessage(message) {
        message.style.animation = 'slideOutUp 0.3s ease-out';
        setTimeout(() => {
            if (message.parentNode) {
                message.remove();
                
                // Remove container if no more messages
                const container = document.querySelector('.flash-messages-container');
                if (container && container.children.length === 0) {
                    container.style.display = 'none';
                }
            }
        }, 300);
    }
    
    // Keyboard accessibility
    document.addEventListener('keydown', function(e) {
        // Press Escape to dismiss all flash messages
        if (e.key === 'Escape') {
            const visibleMessages = document.querySelectorAll('.flash-message');
            visibleMessages.forEach(message => {
                dismissMessage(message);
            });
        }
    });
});

// Function to add new flash message dynamically
window.addFlashMessage = function(type, message, options = {}) {
    const container = document.querySelector('.flash-messages-container') || createFlashContainer();
    
    const messageElement = document.createElement('div');
    messageElement.className = `alert alert-${type} flash-message`;
    messageElement.setAttribute('role', 'alert');
    messageElement.setAttribute('data-type', type);
    
    const icons = {
        'success': 'check-circle',
        'error': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    
    const icon = icons[type] || 'info-circle';
    
    messageElement.innerHTML = `
        <div class="alert-icon">
            <i class="fas fa-${icon}"></i>
        </div>
        <div class="alert-content">
            <div class="alert-message">${escapeHtml(message)}</div>
        </div>
        ${['success', 'info'].includes(type) ? '<div class="alert-progress"><div class="progress-bar"></div></div>' : ''}
        <button type="button" class="alert-close" aria-label="Dismiss alert">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(messageElement);
    container.style.display = 'block';
    
    // Initialize the new message
    const closeBtn = messageElement.querySelector('.alert-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            dismissMessage(messageElement);
        });
    }
    
    // Auto-dismiss
    const autoDismiss = options.autoDismiss !== false;
    if (autoDismiss) {
        const delay = options.delay || (type === 'success' ? 5000 : (type === 'info' ? 7000 : 0));
        if (delay > 0) {
            setTimeout(() => {
                if (messageElement.parentNode) {
                    dismissMessage(messageElement);
                }
            }, delay);
            
            // Add progress bar animation
            if (['success', 'info'].includes(type)) {
                const progressBar = messageElement.querySelector('.progress-bar');
                if (progressBar) {
                    progressBar.style.animation = `progressBarCountdown ${delay}ms linear forwards`;
                }
            }
        }
    }
    
    // Add entrance animation
    messageElement.style.animation = 'slideInDown 0.3s ease-out';
    
    function dismissMessage(message) {
        message.style.animation = 'slideOutUp 0.3s ease-out';
        setTimeout(() => {
            if (message.parentNode) {
                message.remove();
                
                // Remove container if no more messages
                if (container.children.length === 0) {
                    container.style.display = 'none';
                }
            }
        }, 300);
    }
};

function createFlashContainer() {
    const container = document.createElement('div');
    container.className = 'flash-messages-container';
    
    // Insert after breadcrumbs or at the beginning of main content
    const breadcrumbs = document.querySelector('.breadcrumbs');
    const mainContent = document.querySelector('.main-content');
    
    if (breadcrumbs) {
        breadcrumbs.parentNode.insertBefore(container, breadcrumbs.nextSibling);
    } else if (mainContent) {
        mainContent.insertBefore(container, mainContent.firstChild);
    } else {
        document.body.appendChild(container);
    }
    
    return container;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<!-- Flash Messages CSS (inline for immediate loading) -->
<style>
/* Flash Messages Animations */
@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideOutUp {
    from {
        opacity: 1;
        transform: translateY(0);
        max-height: 200px;
        margin-bottom: 1rem;
    }
    to {
        opacity: 0;
        transform: translateY(-20px);
        max-height: 0;
        margin-bottom: 0;
    }
}

@keyframes progressBarCountdown {
    from {
        width: 100%;
    }
    to {
        width: 0%;
    }
}

/* Flash Messages Container */
.flash-messages-container {
    position: relative;
    z-index: 1000;
    margin-bottom: 1rem;
}

/* Flash Message Styles */
.flash-message {
    display: flex;
    align-items: flex-start;
    margin-bottom: 1rem;
    padding: 1rem;
    border-radius: 0.5rem;
    border: 1px solid;
    background: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    position: relative;
    overflow: hidden;
}

.flash-message .alert-icon {
    flex-shrink: 0;
    margin-right: 0.75rem;
    font-size: 1.25rem;
}

.flash-message .alert-content {
    flex: 1;
    min-width: 0;
}

.flash-message .alert-message {
    font-weight: 500;
    line-height: 1.5;
    margin: 0;
}

.flash-message .alert-close {
    flex-shrink: 0;
    background: none;
    border: none;
    font-size: 1rem;
    cursor: pointer;
    padding: 0.25rem;
    margin-left: 0.75rem;
    opacity: 0.7;
    transition: opacity 0.2s ease;
}

.flash-message .alert-close:hover {
    opacity: 1;
}

/* Progress Bar */
.flash-message .alert-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: rgba(255, 255, 255, 0.3);
}

.flash-message .progress-bar {
    height: 100%;
    background: currentColor;
    opacity: 0.8;
}

/* Message Type Styles */
.alert-success {
    background: #f0f9f0;
    border-color: #28a745;
    color: #155724;
}

.alert-error {
    background: #fdf2f2;
    border-color: #dc3545;
    color: #721c24;
}

.alert-warning {
    background: #fffbf0;
    border-color: #ffc107;
    color: #856404;
}

.alert-info {
    background: #f0f8ff;
    border-color: #17a2b8;
    color: #0c5460;
}

/* Error Details */
.alert-details {
    margin-top: 0.5rem;
}

.toggle-details {
    background: none;
    border: none;
    color: inherit;
    text-decoration: underline;
    cursor: pointer;
    font-size: 0.875rem;
    padding: 0;
}

.error-details {
    margin-top: 0.5rem;
    padding: 0.5rem;
    background: rgba(0, 0, 0, 0.05);
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

.error-details pre {
    margin: 0;
    white-space: pre-wrap;
    word-wrap: break-word;
}

/* Responsive Design */
@media (max-width: 768px) {
    .flash-message {
        margin-left: -1rem;
        margin-right: -1rem;
        border-radius: 0;
    }
}

/* Dark Theme Support */
[data-theme="dark"] .flash-message {
    background: #2d3748;
    border-color: currentColor;
}

[data-theme="dark"] .alert-success {
    background: #1a2e1a;
    color: #68d391;
}

[data-theme="dark"] .alert-error {
    background: #2e1a1a;
    color: #fc8181;
}

[data-theme="dark"] .alert-warning {
    background: #2e2a1a;
    color: #f6e05e;
}

[data-theme="dark"] .alert-info {
    background: #1a252e;
    color: #63b3ed;
}

/* High Contrast Mode */
@media (prefers-contrast: high) {
    .flash-message {
        border-width: 2px;
        font-weight: 600;
    }
}

/* Reduced Motion */
@media (prefers-reduced-motion: reduce) {
    .flash-message {
        animation: none !important;
    }
    
    .progress-bar {
        animation: none !important;
    }
}
</style>
