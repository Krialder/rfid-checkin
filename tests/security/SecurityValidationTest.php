<?php
/**
 * Security Validation Tests
 * 
 * Comprehensive security testing including CSRF protection,
 * input validation, SQL injection prevention, and authentication security.
 */

require_once __DIR__ . '/../../core/SecurityManager.php';
require_once __DIR__ . '/../../core/SecurityMiddleware.php';
require_once __DIR__ . '/../../core/auth.php';

class SecurityValidationTest {
    private $securityManager;
    private $testResults = [];
    
    public function __construct() {
        $this->securityManager = SecurityManager::getInstance();
    }
    
    public function runTests(): array {
        echo "🔒 Running Security Validation Tests...\n";
        
        $this->testCSRFProtection();
        $this->testInputValidation();
        $this->testSQLInjectionPrevention();
        $this->testXSSPrevention();
        $this->testPasswordSecurity();
        $this->testSessionSecurity();
        $this->testRateLimiting();
        $this->testAuthenticationSecurity();
        
        return $this->testResults;
    }
    
    private function testCSRFProtection(): void {
        try {
            // Test CSRF token generation
            $token1 = $this->securityManager->generateCSRFToken();
            $token2 = $this->securityManager->generateCSRFToken();
            
            $this->assert(!empty($token1), 'CSRF token is generated');
            $this->assert(strlen($token1) >= 32, 'CSRF token has sufficient length');
            $this->assert($token1 !== $token2, 'CSRF tokens are unique');
            
            // Test CSRF token validation
            $isValid = $this->securityManager->validateCSRFToken($token1);
            $this->assert($isValid, 'Valid CSRF token passes validation');
            
            // Test invalid CSRF token
            $isInvalid = $this->securityManager->validateCSRFToken('invalid_token_12345');
            $this->assert(!$isInvalid, 'Invalid CSRF token fails validation');
            
            echo "  ✅ CSRF protection tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('CSRF Protection', false, $e->getMessage());
        }
    }
    
    private function testInputValidation(): void {
        try {
            // Test email validation
            $validEmails = ['test@example.com', 'user.name@domain.co.uk', 'admin+tag@site.org'];
            $invalidEmails = ['invalid-email', '@domain.com', 'user@', 'user@domain'];
            
            foreach ($validEmails as $email) {
                $result = $this->securityManager->validateInput($email, 'email');
                $this->assert($result, "Valid email passes validation: {$email}");
            }
            
            foreach ($invalidEmails as $email) {
                $result = $this->securityManager->validateInput($email, 'email');
                $this->assert(!$result, "Invalid email fails validation: {$email}");
            }
            
            // Test URL validation
            $validURLs = ['https://example.com', 'http://site.org/path', 'https://sub.domain.com/page?param=value'];
            $invalidURLs = ['not-a-url', 'ftp://invalid', 'javascript:alert("xss")'];
            
            foreach ($validURLs as $url) {
                $result = $this->securityManager->validateInput($url, 'url');
                $this->assert($result, "Valid URL passes validation: {$url}");
            }
            
            foreach ($invalidURLs as $url) {
                $result = $this->securityManager->validateInput($url, 'url');
                $this->assert(!$result, "Invalid URL fails validation: {$url}");
            }
            
            echo "  ✅ Input validation tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('Input Validation', false, $e->getMessage());
        }
    }
    
    private function testSQLInjectionPrevention(): void {
        try {
            $maliciousInputs = [
                "'; DROP TABLE users; --",
                "1' OR '1'='1",
                "admin'/**/OR/**/1=1--",
                "' UNION SELECT * FROM users --",
                "1; DELETE FROM users WHERE 1=1; --",
                "'; INSERT INTO users (username) VALUES ('hacker'); --"
            ];
            
            foreach ($maliciousInputs as $input) {
                $sanitized = $this->securityManager->sanitizeInput($input);
                
                // Should not contain dangerous SQL keywords
                $this->assert(
                    !preg_match('/\b(DROP|DELETE|INSERT|UPDATE|UNION|SELECT)\b/i', $sanitized),
                    "SQL injection attempt neutralized: " . substr($input, 0, 30) . "..."
                );
                
                // Should not contain SQL comment markers
                $this->assert(
                    strpos($sanitized, '--') === false,
                    "SQL comment markers removed from: " . substr($input, 0, 30) . "..."
                );
            }
            
            echo "  ✅ SQL injection prevention tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('SQL Injection Prevention', false, $e->getMessage());
        }
    }
    
    private function testXSSPrevention(): void {
        try {
            $xssAttempts = [
                '<script>alert("xss")</script>',
                '<img src="x" onerror="alert(1)">',
                '<iframe src="javascript:alert(1)"></iframe>',
                '<svg onload="alert(1)">',
                '<body onload="alert(1)">',
                'javascript:alert("xss")',
                '<script src="http://evil.com/xss.js"></script>'
            ];
            
            foreach ($xssAttempts as $attempt) {
                $sanitized = $this->securityManager->sanitizeInput($attempt);
                
                // Should not contain script tags
                $this->assert(
                    strpos($sanitized, '<script') === false,
                    "Script tags removed from XSS attempt: " . substr($attempt, 0, 30) . "..."
                );
                
                // Should not contain javascript: protocol
                $this->assert(
                    strpos(strtolower($sanitized), 'javascript:') === false,
                    "JavaScript protocol removed from: " . substr($attempt, 0, 30) . "..."
                );
                
                // Should not contain event handlers
                $this->assert(
                    !preg_match('/\bon\w+\s*=/i', $sanitized),
                    "Event handlers removed from: " . substr($attempt, 0, 30) . "..."
                );
            }
            
            echo "  ✅ XSS prevention tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('XSS Prevention', false, $e->getMessage());
        }
    }
    
    private function testPasswordSecurity(): void {
        try {
            $testPasswords = [
                'password123',
                'StrongPassword123!',
                'Very$ecure&P@ssw0rd2024',
                'short',
                '12345678'
            ];
            
            foreach ($testPasswords as $password) {
                // Test password hashing
                $hash = $this->securityManager->hashPassword($password);
                
                $this->assert(!empty($hash), "Password hashing works for: {$password}");
                $this->assert($hash !== $password, "Password is actually hashed for: {$password}");
                $this->assert(password_verify($password, $hash), "Password verification works for: {$password}");
                
                // Test password strength validation
                $strength = $this->securityManager->validatePasswordStrength($password);
                $this->assert(is_array($strength), "Password strength validation returns array for: {$password}");
                $this->assert(isset($strength['score']), "Password strength has score for: {$password}");
            }
            
            echo "  ✅ Password security tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('Password Security', false, $e->getMessage());
        }
    }
    
    private function testSessionSecurity(): void {
        try {
            // Test session configuration
            $this->assert(ini_get('session.cookie_httponly') == '1', 'Sessions use HttpOnly cookies');
            $this->assert(ini_get('session.use_strict_mode') == '1', 'Sessions use strict mode');
            
            // Test session token generation
            $sessionToken = $this->securityManager->generateSecureToken();
            $this->assert(!empty($sessionToken), 'Secure session token generated');
            $this->assert(strlen($sessionToken) >= 32, 'Session token has sufficient entropy');
            
            echo "  ✅ Session security tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('Session Security', false, $e->getMessage());
        }
    }
    
    private function testRateLimiting(): void {
        try {
            $testIP = '192.168.1.100';
            $testAction = 'test_login';
            
            // Test rate limiting
            for ($i = 0; $i < 5; $i++) {
                $isAllowed = $this->securityManager->checkRateLimit($testIP, $testAction);
                if ($i < 3) {
                    $this->assert($isAllowed, "Rate limiting allows request {$i}");
                } else {
                    $this->assert(!$isAllowed, "Rate limiting blocks request {$i}");
                }
            }
            
            echo "  ✅ Rate limiting tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('Rate Limiting', false, $e->getMessage());
        }
    }
    
    private function testAuthenticationSecurity(): void {
        try {
            // Test auth functions exist
            $this->assert(function_exists('isLoggedIn'), 'isLoggedIn function exists');
            $this->assert(function_exists('hasPermission'), 'hasPermission function exists');
            
            // Test Auth class exists
            $this->assert(class_exists('Auth'), 'Auth class exists');
            
            // Test security manager methods
            $this->assert(method_exists($this->securityManager, 'validateSession'), 'SecurityManager has validateSession method');
            $this->assert(method_exists($this->securityManager, 'regenerateSessionId'), 'SecurityManager has regenerateSessionId method');
            
            echo "  ✅ Authentication security tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('Authentication Security', false, $e->getMessage());
        }
    }
    
    private function assert($condition, $message): void {
        if ($condition) {
            $this->recordTest($message, true);
        } else {
            $this->recordTest($message, false);
            throw new Exception("Assertion failed: {$message}");
        }
    }
    
    private function recordTest($name, $passed, $error = null): void {
        $this->testResults[] = [
            'name' => $name,
            'passed' => $passed,
            'error' => $error
        ];
    }
}
?>
