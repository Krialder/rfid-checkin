<?php if (!empty($messages)): ?>
    <div class="flash-messages" role="alert">
        <?php foreach ($messages as $message): ?>
            <div class="alert alert-<?= $renderer->helper('e', $message['type']) ?>" role="alert">
                <div class="alert-content">
                    <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <?php if ($message['type'] === 'success'): ?>
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22,4 12,14.01 9,11.01"></polyline>
                        <?php elseif ($message['type'] === 'error'): ?>
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                        <?php elseif ($message['type'] === 'warning'): ?>
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                            <line x1="12" y1="9" x2="12" y2="13"></line>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        <?php else: ?>
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        <?php endif; ?>
                    </svg>
                    <span class="alert-message"><?= $renderer->helper('e', $message['message']) ?></span>
                </div>
                <button type="button" class="alert-close" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        <?php endforeach; ?>
    </div>
    
    <script>
        // Auto-dismiss flash messages
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            
            alerts.forEach(function(alert) {
                const closeBtn = alert.querySelector('.alert-close');
                
                // Handle close button click
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        alert.style.animation = 'fadeOut 0.3s ease-out';
                        setTimeout(() => alert.remove(), 300);
                    });
                }
                
                // Auto-dismiss success messages after 5 seconds
                if (alert.classList.contains('alert-success')) {
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.style.animation = 'fadeOut 0.3s ease-out';
                            setTimeout(() => alert.remove(), 300);
                        }
                    }, 5000);
                }
            });
        });
    </script>
<?php endif; ?>