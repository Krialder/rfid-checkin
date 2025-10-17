<!-- Application Footer -->
<footer class="app-footer" role="contentinfo">
    <div class="footer-container">
        
        <!-- Footer Main Content -->
        <div class="footer-main">
            
            <!-- Company/Application Info -->
            <div class="footer-section footer-brand">
                <div class="footer-logo">
                    <img src="/assets/images/logo-small.png" alt="RFID Check-in System" class="footer-logo-image">
                    <span class="footer-brand-text"><?= htmlspecialchars($app_name ?? 'RFID Check-in') ?></span>
                </div>
                <p class="footer-description">
                    Secure and efficient RFID-based attendance tracking system for modern organizations.
                </p>
                
                <!-- Version Information -->
                <?php if (!empty($app_version)): ?>
                    <div class="footer-version">
                        <span class="version-label">Version:</span>
                        <span class="version-number"><?= htmlspecialchars($app_version) ?></span>
                        
                        <?php if (!empty($build_date)): ?>
                            <span class="build-date">Built: <?= htmlspecialchars($build_date) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Quick Links -->
            <div class="footer-section footer-links">
                <h3 class="footer-title">Quick Links</h3>
                <ul class="footer-nav">
                    <li><a href="/dashboard" class="footer-link">Dashboard</a></li>
                    <li><a href="/events" class="footer-link">Events</a></li>
                    <li><a href="/profile" class="footer-link">My Profile</a></li>
                    <li><a href="/help" class="footer-link">Help & Support</a></li>
                    
                    <?php if (isset($current_user['group_id']) && $current_user['group_id'] <= 2): ?>
                        <li><a href="/admin" class="footer-link">Admin Panel</a></li>
                        <li><a href="/analytics" class="footer-link">Analytics</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Support Information -->
            <div class="footer-section footer-support">
                <h3 class="footer-title">Support</h3>
                <ul class="footer-nav">
                    <li><a href="/help/getting-started" class="footer-link">Getting Started</a></li>
                    <li><a href="/help/troubleshooting" class="footer-link">Troubleshooting</a></li>
                    <li><a href="/help/contact" class="footer-link">Contact Support</a></li>
                    
                    <?php if (!empty($support_email)): ?>
                        <li>
                            <a href="mailto:<?= htmlspecialchars($support_email) ?>" class="footer-link">
                                <i class="fas fa-envelope"></i>
                                <?= htmlspecialchars($support_email) ?>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- System Status -->
            <div class="footer-section footer-status">
                <h3 class="footer-title">System Status</h3>
                <div class="status-items">
                    <div class="status-item">
                        <div class="status-indicator status-online" title="System Online"></div>
                        <span class="status-text">System Online</span>
                    </div>
                    
                    <div class="status-item">
                        <div class="status-indicator rfid-status" id="rfid-status" title="RFID Reader Status"></div>
                        <span class="status-text">RFID Reader</span>
                    </div>
                    
                    <div class="status-item">
                        <div class="status-indicator db-status" id="db-status" title="Database Status"></div>
                        <span class="status-text">Database</span>
                    </div>
                </div>
                
                <!-- Last Update Time -->
                <div class="last-update">
                    <small>Last updated: <span id="last-update-time">--:--</span></small>
                </div>
            </div>
            
        </div>
        
        <!-- Footer Bottom -->
        <div class="footer-bottom">
            
            <!-- Copyright -->
            <div class="footer-copyright">
                <p>&copy; <?= date('Y') ?> RFID Check-in System. All rights reserved.</p>
                
                <?php if (!empty($organization_name)): ?>
                    <p>Deployed for: <strong><?= htmlspecialchars($organization_name) ?></strong></p>
                <?php endif; ?>
            </div>
            
            <!-- Legal Links -->
            <div class="footer-legal">
                <a href="/privacy" class="footer-link">Privacy Policy</a>
                <span class="footer-separator">|</span>
                <a href="/terms" class="footer-link">Terms of Service</a>
                <span class="footer-separator">|</span>
                <a href="/security" class="footer-link">Security</a>
            </div>
            
            <!-- Environment Indicator (for development) -->
            <?php if ($environment !== 'production'): ?>
                <div class="environment-indicator">
                    <span class="env-badge env-<?= htmlspecialchars($environment) ?>">
                        <?= strtoupper(htmlspecialchars($environment)) ?> ENVIRONMENT
                    </span>
                </div>
            <?php endif; ?>
            
        </div>
        
    </div>
</footer>

<!-- Footer JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Update system status
    updateSystemStatus();
    
    // Update status every 60 seconds
    setInterval(updateSystemStatus, 60000);
    
    // Update last update time every second
    updateLastUpdateTime();
    setInterval(updateLastUpdateTime, 1000);
    
    function updateSystemStatus() {
        fetch('/api/system/status')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateStatusIndicator('rfid-status', data.data.rfid_status);
                    updateStatusIndicator('db-status', data.data.database_status);
                    
                    // Update last check time
                    const now = new Date();
                    localStorage.setItem('lastStatusCheck', now.toISOString());
                }
            })
            .catch(error => {
                console.error('Error checking system status:', error);
                updateStatusIndicator('rfid-status', 'unknown');
                updateStatusIndicator('db-status', 'unknown');
            });
    }
    
    function updateStatusIndicator(elementId, status) {
        const indicator = document.getElementById(elementId);
        if (indicator) {
            indicator.className = `status-indicator status-${status}`;
            
            const statusTexts = {
                'online': 'Online',
                'offline': 'Offline',
                'warning': 'Warning',
                'error': 'Error',
                'unknown': 'Unknown'
            };
            
            indicator.title = statusTexts[status] || 'Unknown';
        }
    }
    
    function updateLastUpdateTime() {
        const lastCheck = localStorage.getItem('lastStatusCheck');
        const timeElement = document.getElementById('last-update-time');
        
        if (lastCheck && timeElement) {
            const checkTime = new Date(lastCheck);
            const now = new Date();
            const diffMs = now - checkTime;
            const diffSecs = Math.floor(diffMs / 1000);
            const diffMins = Math.floor(diffSecs / 60);
            
            let timeText = '';
            if (diffSecs < 60) {
                timeText = `${diffSecs}s ago`;
            } else if (diffMins < 60) {
                timeText = `${diffMins}m ago`;
            } else {
                timeText = checkTime.toLocaleTimeString();
            }
            
            timeElement.textContent = timeText;
        }
    }
    
    // Add smooth scroll to footer links
    const footerLinks = document.querySelectorAll('.footer-link[href^="#"]');
    footerLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const target = document.getElementById(targetId);
            
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
});
</script>

<!-- Footer CSS (inline for immediate loading) -->
<style>
/* Footer Styles */
.app-footer {
    background: #1a202c;
    color: #e2e8f0;
    margin-top: auto;
    padding: 2rem 0 1rem;
    border-top: 1px solid #2d3748;
}

.footer-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
}

/* Footer Main Content */
.footer-main {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid #2d3748;
}

.footer-section {
    min-width: 0;
}

/* Footer Brand */
.footer-logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.footer-logo-image {
    width: 32px;
    height: 32px;
    object-fit: contain;
}

.footer-brand-text {
    font-size: 1.125rem;
    font-weight: 600;
    color: #ffffff;
}

.footer-description {
    color: #a0aec0;
    line-height: 1.6;
    margin-bottom: 1rem;
    font-size: 0.875rem;
}

.footer-version {
    font-size: 0.75rem;
    color: #718096;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.version-label {
    font-weight: 500;
}

.version-number {
    color: #e2e8f0;
    font-family: 'Courier New', monospace;
}

/* Footer Sections */
.footer-title {
    color: #ffffff;
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.footer-nav {
    list-style: none;
    margin: 0;
    padding: 0;
}

.footer-nav li {
    margin-bottom: 0.5rem;
}

.footer-link {
    color: #a0aec0;
    text-decoration: none;
    font-size: 0.875rem;
    transition: color 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.footer-link:hover {
    color: #63b3ed;
    text-decoration: none;
}

.footer-link i {
    font-size: 0.75rem;
}

/* Status Section */
.status-items {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
}

.status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.status-online {
    background: #48bb78;
    box-shadow: 0 0 6px rgba(72, 187, 120, 0.6);
}

.status-offline {
    background: #f56565;
    box-shadow: 0 0 6px rgba(245, 101, 101, 0.6);
}

.status-warning {
    background: #ed8936;
    box-shadow: 0 0 6px rgba(237, 137, 54, 0.6);
}

.status-error {
    background: #e53e3e;
    box-shadow: 0 0 6px rgba(229, 62, 62, 0.6);
}

.status-unknown {
    background: #718096;
    box-shadow: 0 0 6px rgba(113, 128, 150, 0.6);
}

.last-update {
    color: #718096;
    font-size: 0.75rem;
}

/* Footer Bottom */
.footer-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    padding-top: 1rem;
}

.footer-copyright {
    font-size: 0.875rem;
    color: #a0aec0;
}

.footer-copyright p {
    margin: 0;
}

.footer-legal {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.875rem;
}

.footer-separator {
    color: #4a5568;
}

/* Environment Indicator */
.environment-indicator {
    margin-left: auto;
}

.env-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.env-development {
    background: #fed7d7;
    color: #c53030;
}

.env-staging {
    background: #fefcbf;
    color: #d69e2e;
}

.env-testing {
    background: #c6f6d5;
    color: #2f855a;
}

/* Responsive Design */
@media (max-width: 768px) {
    .footer-main {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .footer-bottom {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .footer-legal {
        justify-content: center;
    }
    
    .environment-indicator {
        margin-left: 0;
    }
}

@media (max-width: 480px) {
    .app-footer {
        padding: 1.5rem 0 1rem;
    }
    
    .footer-main {
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1.5rem;
    }
    
    .footer-logo {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
    
    .status-items {
        gap: 0.5rem;
    }
}

/* Dark Theme Adjustments */
[data-theme="dark"] .app-footer {
    background: #0f1419;
    border-top-color: #1a1a1a;
}

[data-theme="dark"] .footer-main {
    border-bottom-color: #1a1a1a;
}

/* High Contrast Mode */
@media (prefers-contrast: high) {
    .app-footer {
        border-top-width: 2px;
    }
    
    .footer-link:focus {
        outline: 2px solid #63b3ed;
        outline-offset: 2px;
    }
}

/* Print Styles */
@media print {
    .app-footer {
        background: none;
        color: #000;
        border-top: 1px solid #000;
        padding: 1rem 0;
    }
    
    .footer-main {
        display: block;
    }
    
    .footer-section:not(.footer-brand) {
        display: none;
    }
    
    .footer-bottom {
        justify-content: center;
    }
    
    .footer-legal,
    .environment-indicator {
        display: none;
    }
}
</style>
