# Error Pages Directory

This directory contains custom error pages that provide user-friendly error handling with consistent branding and helpful guidance. These pages replace default server error responses with professional, informative interfaces.

## 📁 Directory Structure

```
error-pages/
├── 403.html                      # Access Forbidden page
├── 429.html                      # Rate Limit Exceeded page
└── csrf.html                     # CSRF Protection page
```

## 🚫 Error Page Overview

### 403.html - Access Forbidden

**Purpose**: Displayed when users attempt to access resources without proper permissions.

**Features:**
- Professional error presentation with security messaging
- Clear explanation of access restriction
- Action buttons for user navigation
- Security notice with logging information
- Responsive design with accessibility features

**Styling:**
```css
.error-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--error-bg) 0%, var(--error-light) 100%);
}

.error-card {
    background: var(--card-bg);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xl);
    padding: var(--space-8);
    max-width: 500px;
    text-align: center;
}
```

**User Actions:**
- Return to home page
- Go back to previous page
- Contact system administrator

### 429.html - Rate Limit Exceeded

**Purpose**: Shown when users exceed API rate limits or request thresholds.

**Features:**
- Rate limiting explanation
- Wait time guidance
- Retry instructions
- Professional error messaging
- Progressive enhancement

**Rate Limit Information:**
- Clear explanation of rate limiting
- Expected wait time before retry
- Best practices for API usage
- Contact information for support

### csrf.html - CSRF Protection

**Purpose**: Displayed when CSRF token validation fails.

**Features:**
- CSRF protection explanation
- Security education for users
- Form refresh instructions
- Session management guidance

**Security Messaging:**
- Explanation of CSRF attacks
- Why protection is important
- Steps to resolve the issue
- Prevention tips for users

## 🎨 Design System

### Visual Elements

**Color Scheme:**
```css
:root {
    --error-color: #dc3545;
    --error-bg: #f8d7da;
    --error-light: #fde2e4;
    --warning-color: #856404;
    --warning-bg: #fff3cd;
    --warning-border: #ffeaa7;
    --warning-text: #6c5604;
}
```

**Typography:**
- Clear, readable fonts
- Proper heading hierarchy
- Adequate contrast ratios
- Scalable text sizes

**Icons and Graphics:**
- Emoji-based icons for universal understanding
- Consistent visual language
- Accessible alternative text
- Scalable vector graphics

### Responsive Design

**Mobile Optimization:**
```css
@media (max-width: 768px) {
    .error-card {
        margin: var(--space-4);
        padding: var(--space-6);
    }
    
    .error-actions {
        flex-direction: column;
    }
    
    .btn-home, .btn-back {
        width: 100%;
    }
}
```

**Desktop Enhancement:**
- Hover effects on interactive elements
- Enhanced spacing and layout
- Advanced visual effects
- Better use of screen space

## 🔧 Implementation

### Server Configuration

**Apache (.htaccess):**
```apache
# Custom error pages
ErrorDocument 403 /rfid-checkin/error-pages/403.html
ErrorDocument 429 /rfid-checkin/error-pages/429.html

# Rate limiting
<IfModule mod_evasive.c>
    DOSPageCount        5
    DOSErrorDocument    /rfid-checkin/error-pages/429.html
</IfModule>
```

**Nginx Configuration:**
```nginx
# Custom error pages
error_page 403 /error-pages/403.html;
error_page 429 /error-pages/429.html;

# Rate limiting
limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
limit_req zone=api burst=20 nodelay;
error_page 429 /error-pages/429.html;
```

### PHP Integration

**Error Handler Integration:**
```php
<?php
// In core/ErrorHandler.php

class ErrorHandler {
    public function handleForbiddenAccess($reason = 'insufficient_privileges') {
        http_response_code(403);
        
        // Log security event
        $this->logSecurityEvent('403_access_denied', [
            'reason' => $reason,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'requested_url' => $_SERVER['REQUEST_URI']
        ]);
        
        // Show custom error page
        include __DIR__ . '/../error-pages/403.html';
        exit;
    }
    
    public function handleRateLimitExceeded($retryAfter = 60) {
        http_response_code(429);
        header('Retry-After: ' . $retryAfter);
        
        // Log rate limit event
        $this->logEvent('rate_limit_exceeded', [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'retry_after' => $retryAfter,
            'endpoint' => $_SERVER['REQUEST_URI']
        ]);
        
        include __DIR__ . '/../error-pages/429.html';
        exit;
    }
    
    public function handleCSRFFailure() {
        http_response_code(403);
        
        // Log CSRF attempt
        $this->logSecurityEvent('csrf_validation_failed', [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ]);
        
        include __DIR__ . '/../error-pages/csrf.html';
        exit;
    }
}
?>
```

### JavaScript Enhancement

**Progressive Enhancement:**
```javascript
// Error page enhancements
document.addEventListener('DOMContentLoaded', function() {
    // Prevent back button caching for security pages
    if (window.history && window.history.pushState) {
        window.history.pushState('forward', null, window.location.href);
        window.addEventListener('popstate', function() {
            window.history.pushState('forward', null, window.location.href);
        });
    }
    
    // Auto-refresh for rate limit pages
    const isRateLimitPage = document.querySelector('.rate-limit-error');
    if (isRateLimitPage) {
        const retryAfter = parseInt(document.querySelector('.retry-time')?.textContent) || 60;
        
        // Countdown timer
        let timeLeft = retryAfter;
        const timer = setInterval(() => {
            timeLeft--;
            const timerElement = document.querySelector('.countdown-timer');
            if (timerElement) {
                timerElement.textContent = timeLeft;
            }
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                // Show retry button
                const retryButton = document.querySelector('.retry-button');
                if (retryButton) {
                    retryButton.style.display = 'block';
                    retryButton.disabled = false;
                }
            }
        }, 1000);
    }
    
    // CSRF token refresh for CSRF pages
    const isCSRFPage = document.querySelector('.csrf-error');
    if (isCSRFPage) {
        const refreshButton = document.querySelector('.refresh-form');
        if (refreshButton) {
            refreshButton.addEventListener('click', function() {
                // Refresh the referring page
                if (document.referrer) {
                    window.location.href = document.referrer;
                } else {
                    window.location.href = '/rfid-checkin/';
                }
            });
        }
    }
});
```

## 🔒 Security Considerations

### Information Disclosure

**Safe Error Messages:**
- No sensitive system information exposed
- Generic error descriptions
- No stack traces or debug information
- Limited server configuration details

**Security Headers:**
```html
<meta http-equiv="X-Content-Type-Options" content="nosniff">
<meta http-equiv="X-Frame-Options" content="DENY">
<meta http-equiv="X-XSS-Protection" content="1; mode=block">
```

### Logging and Monitoring

**Security Event Logging:**
- 403 errors logged with user context
- Rate limit violations tracked
- CSRF attempts recorded
- IP address patterns monitored

**Alert Thresholds:**
- Multiple 403 errors from same IP
- Rapid rate limit violations
- CSRF attack patterns
- Automated scanning detection

## 📊 Monitoring and Analytics

### Error Page Analytics

**Metrics Tracked:**
- Error page view counts
- User flow after error pages
- Most common error types
- Geographic distribution of errors

**Performance Monitoring:**
- Error page load times
- User engagement with error pages
- Resolution success rates
- Support ticket correlation

### Improvement Opportunities

**User Experience:**
- Error page effectiveness
- User understanding of errors
- Recovery action success rates
- Support contact rates

**Technical Optimization:**
- Error page performance
- Cache efficiency
- Mobile responsiveness
- Accessibility compliance

## 🚀 Future Enhancements

### Advanced Features

**Dynamic Error Pages:**
- Personalized error messages
- Context-aware suggestions
- Multi-language support
- Real-time status updates

**Enhanced User Guidance:**
- Interactive troubleshooting
- Suggested alternative actions
- Related resource recommendations
- Live chat integration

**Developer Tools:**
- Error debugging information (dev mode)
- Performance insights
- Security analysis
- Automated testing

---

**Error Pages**: Production Ready  
**Last Updated**: January 2025  
**Security**: Comprehensive protection against information disclosure