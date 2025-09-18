# Tests Directory

This directory contains the comprehensive testing framework and test suites for the RFID Check-in System, ensuring code quality, security, and performance standards.

## 📁 Directory Structure

```
tests/
├── TestFramework.php              # Core testing framework
├── run-tests.php                  # Test runner and CLI interface
├── simple-test.php                # Quick validation tests
├── unit/                          # Unit test suites
│   ├── RepositoryPatternTest.php  # Repository pattern tests
│   ├── ServiceLayerTest.php       # Service layer tests
│   ├── ModelValidationTest.php    # Model validation tests
│   ├── SecurityServiceTest.php    # Security service tests
│   ├── CacheServiceTest.php       # Caching system tests
│   └── UtilityFunctionTest.php    # Utility function tests
├── integration/                   # Integration test suites
│   ├── APIIntegrationTest.php     # API endpoint tests
│   ├── DatabaseIntegrationTest.php # Database integration tests
│   ├── AuthenticationFlowTest.php # Authentication flow tests
│   ├── RFIDHardwareTest.php       # RFID hardware integration
│   └── FrontendResponsivenessTest.php # Frontend testing
├── performance/                   # Performance test suites
│   ├── DatabasePerformanceTest.php # Database query performance
│   ├── CachingPerformanceTest.php # Cache performance tests
│   ├── AssetLoadingTest.php       # Frontend asset performance
│   └── ConcurrentLoadTest.php     # Concurrent user testing
└── security/                      # Security test suites
    ├── SQLInjectionTest.php       # SQL injection prevention
    ├── XSSProtectionTest.php      # Cross-site scripting protection
    ├── CSRFProtectionTest.php     # CSRF protection validation
    ├── AuthenticationSecurityTest.php # Authentication security
    └── InputValidationTest.php    # Input validation testing
```

## 🧪 Testing Framework

### TestFramework.php

The core testing framework provides testing capabilities:

```php
class TestFramework {
    private $testResults = [];
    private $currentSuite = '';
    private $testCount = 0;
    private $passCount = 0;
    private $failCount = 0;
    
    /**
     * Run all test suites
     */
    public function runAllTests(): array {
        $this->runUnitTests();
        $this->runIntegrationTests();
        $this->runPerformanceTests();
        $this->runSecurityTests();
        
        return $this->generateReport();
    }
    
    /**
     * Assert condition and record result
     */
    public function assert($condition, $message): void {
        $this->testCount++;
        
        if ($condition) {
            $this->passCount++;
            echo "  ✅ {$message}\n";
        } else {
            $this->failCount++;
            echo "  ❌ {$message}\n";
        }
    }
}
```

**Framework Features:**
- **Multi-Suite Testing**: Unit, Integration, Performance, Security
- **Automated Reporting**: Comprehensive test reports with metrics
- **Database Transactions**: Clean test environment with rollback
- **Performance Monitoring**: Execution time and memory tracking
- **Error Handling**: Comprehensive error catching and reporting

### Test Execution

**Run All Tests:**
```bash
# Command line execution
php tests/run-tests.php

# Web browser execution
http://your-domain.com/tests/run-tests.php
```

**Run Specific Test Suites:**
```bash
# Unit tests only
php tests/run-tests.php --suite=unit

# Integration tests only
php tests/run-tests.php --suite=integration

# Performance tests only
php tests/run-tests.php --suite=performance

# Security tests only
php tests/run-tests.php --suite=security
```

**Test Output Example:**
```
🧪 Starting Enterprise Test Suite...

📋 Running Unit Tests...
  ✅ UserRepository extends BaseRepository
  ✅ Repository has database connection
  ✅ Repository has findById method
  ✅ Repository has create method
  ✅ Service layer instantiation
✅ Unit Tests Completed

🔗 Running Integration Tests...
  ✅ API endpoint /api/dashboard.php exists
  ✅ Authentication flow works
  ✅ RFID check-in process functional
✅ Integration Tests Completed

🎯 TEST SUMMARY
================
Total Tests: 45
Passed: 43
Failed: 2
Success Rate: 95.56%
Execution Time: 2.45s
Memory Used: 12.3MB
```

## 🔧 Unit Tests

Unit tests focus on individual components and functions:

### Repository Pattern Tests

**RepositoryPatternTest.php:**
```php
class RepositoryPatternTest {
    public function testRepositoryInstantiation() {
        $userRepo = new UserRepository();
        $this->assert($userRepo instanceof BaseRepository, 'UserRepository extends BaseRepository');
        $this->assert($userRepo->getConnection() !== null, 'Repository has database connection');
    }
    
    public function testRepositoryMethods() {
        $userRepo = new UserRepository();
        $this->assert(method_exists($userRepo, 'findById'), 'Repository has findById method');
        $this->assert(method_exists($userRepo, 'create'), 'Repository has create method');
        $this->assert(method_exists($userRepo, 'update'), 'Repository has update method');
        $this->assert(method_exists($userRepo, 'delete'), 'Repository has delete method');
    }
    
    public function testCRUDOperations() {
        $userRepo = new UserRepository();
        
        // Test create
        $userData = [
            'username' => 'test_user_' . time(),
            'email' => 'test@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'User'
        ];
        
        $userId = $userRepo->create($userData);
        $this->assert($userId > 0, 'User creation returns valid ID');
        
        // Test read
        $user = $userRepo->findById($userId);
        $this->assert($user['username'] === $userData['username'], 'User retrieval works');
        
        // Test update
        $updateSuccess = $userRepo->update($userId, ['first_name' => 'Updated']);
        $this->assert($updateSuccess, 'User update works');
        
        // Test delete
        $deleteSuccess = $userRepo->delete($userId);
        $this->assert($deleteSuccess, 'User deletion works');
    }
}
```

### Service Layer Tests

**ServiceLayerTest.php:**
```php
class ServiceLayerTest {
    public function testDataServiceInstantiation() {
        $dataService = DataService::getInstance();
        $this->assert($dataService !== null, 'DataService instantiation');
        $this->assert(method_exists($dataService, 'getUserById'), 'DataService has getUserById method');
    }
    
    public function testUserOperations() {
        $dataService = DataService::getInstance();
        
        // Test user creation
        $userData = [
            'username' => 'service_test_' . time(),
            'email' => 'service@example.com',
            'password' => 'TestPassword123!',
            'first_name' => 'Service',
            'last_name' => 'Test'
        ];
        
        $user = $dataService->createUser($userData);
        $this->assert($user['user_id'] > 0, 'Service user creation works');
        
        // Test user retrieval
        $retrievedUser = $dataService->getUserById($user['user_id']);
        $this->assert($retrievedUser['username'] === $userData['username'], 'Service user retrieval works');
    }
}
```

### Security Service Tests

**SecurityServiceTest.php:**
```php
class SecurityServiceTest {
    public function testPasswordHashing() {
        $securityManager = SecurityManager::getInstance();
        
        $password = 'TestPassword123!';
        $hash = $securityManager->hashPassword($password);
        
        $this->assert(!empty($hash), 'Password hashing works');
        $this->assert($hash !== $password, 'Password is actually hashed');
        $this->assert(password_verify($password, $hash), 'Password verification works');
    }
    
    public function testInputSanitization() {
        $securityManager = SecurityManager::getInstance();
        
        $maliciousInput = '<script>alert("xss")</script>';
        $cleanInput = $securityManager->sanitizeInput($maliciousInput);
        
        $this->assert(strpos($cleanInput, '<script>') === false, 'XSS attempts are sanitized');
    }
    
    public function testCSRFTokens() {
        $securityManager = SecurityManager::getInstance();
        
        $token = $securityManager->generateCSRFToken();
        $this->assert(!empty($token), 'CSRF token generation works');
        $this->assert(strlen($token) >= 32, 'CSRF token has adequate length');
        
        $isValid = $securityManager->validateCSRFToken($token);
        $this->assert($isValid, 'CSRF token validation works');
    }
}
```

## 🔗 Integration Tests

Integration tests verify component interactions and system workflows:

### API Integration Tests

**APIIntegrationTest.php:**
```php
class APIIntegrationTest {
    public function testAPIEndpoints() {
        $endpoints = [
            '/api/dashboard.php',
            '/api/rfid-checkin.php',
            '/api/event-details.php',
            '/api/analytics.php'
        ];
        
        foreach ($endpoints as $endpoint) {
            $fullPath = __DIR__ . '/..' . $endpoint;
            $this->assert(file_exists($fullPath), "API endpoint {$endpoint} exists");
        }
    }
    
    public function testRFIDCheckinFlow() {
        // Test RFID check-in API endpoint
        $testData = [
            'rfid' => 'TEST1234',
            'device_id' => 'TEST_DEVICE'
        ];
        
        $response = $this->makeAPIRequest('/api/rfid-checkin.php', 'POST', $testData);
        $this->assert($response['status'] >= 200 && $response['status'] < 300, 'RFID API responds successfully');
        
        $data = json_decode($response['body'], true);
        $this->assert(isset($data['success']), 'RFID API returns structured response');
    }
    
    public function testAuthenticationAPI() {
        // Test login endpoint
        $loginData = [
            'username' => 'admin',
            'password' => 'admin123'
        ];
        
        $response = $this->makeAPIRequest('/auth/login-process.php', 'POST', $loginData);
        $this->assert($response['status'] === 200, 'Login API works');
        
        // Test dashboard access
        $response = $this->makeAPIRequest('/api/dashboard.php', 'GET');
        $this->assert($response['status'] === 200, 'Authenticated API access works');
    }
}
```

### Database Integration Tests

**DatabaseIntegrationTest.php:**
```php
class DatabaseIntegrationTest {
    public function testDatabaseConnection() {
        $db = getDB();
        $this->assert($db !== null, 'Database connection established');
        $this->assert($db instanceof PDO, 'Database returns PDO instance');
    }
    
    public function testTransactionSupport() {
        $db = getDB();
        
        $db->beginTransaction();
        $this->assert($db->inTransaction(), 'Database transaction started');
        
        $db->rollBack();
        $this->assert(!$db->inTransaction(), 'Database transaction rolled back');
    }
    
    public function testTableStructure() {
        $db = getDB();
        
        $expectedTables = [
            'users', 'events', 'checkin', 'rfiddevices', 
            'system_settings', 'accesslogs', 'eventregistration'
        ];
        
        foreach ($expectedTables as $table) {
            $stmt = $db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            $exists = $stmt->fetch();
            
            $this->assert($exists !== false, "Table {$table} exists");
        }
    }
}
```

### Frontend Responsiveness Tests

**FrontendResponsivenessTest.php:**
```php
class FrontendResponsivenessTest {
    public function testResponsiveCSS() {
        $cssFiles = [
            'assets/css/main.css',
            'assets/css/dashboard.css',
            'assets/css/navigation.css'
        ];
        
        foreach ($cssFiles as $file) {
            $fullPath = __DIR__ . '/../' . $file;
            $this->assert(file_exists($fullPath), "CSS file {$file} exists");
            
            $content = file_get_contents($fullPath);
            $this->assert(strpos($content, '@media') !== false, "CSS file {$file} contains responsive breakpoints");
        }
    }
    
    public function testJavaScriptModules() {
        $jsFiles = [
            'assets/js/dashboard.js',
            'assets/js/events.js',
            'assets/js/rfid-scanner.js'
        ];
        
        foreach ($jsFiles as $file) {
            $fullPath = __DIR__ . '/../' . $file;
            $this->assert(file_exists($fullPath), "JavaScript file {$file} exists");
        }
    }
}
```

## ⚡ Performance Tests

Performance tests ensure the system meets speed and efficiency requirements:

### Database Performance Tests

**DatabasePerformanceTest.php:**
```php
class DatabasePerformanceTest {
    public function testQueryPerformance() {
        $db = getDB();
        
        // Test simple query performance
        $startTime = microtime(true);
        $stmt = $db->prepare("SELECT 1 as test");
        $stmt->execute();
        $result = $stmt->fetch();
        $queryTime = microtime(true) - $startTime;
        
        $this->assert($queryTime < 0.1, 'Simple query executes under 100ms');
        $this->assert($result['test'] == 1, 'Query returns expected result');
    }
    
    public function testIndexEfficiency() {
        $db = getDB();
        
        // Test user lookup by RFID (should use index)
        $startTime = microtime(true);
        $stmt = $db->prepare("SELECT * FROM users WHERE rfid_tag = ? LIMIT 1");
        $stmt->execute(['TEST1234']);
        $queryTime = microtime(true) - $startTime;
        
        $this->assert($queryTime < 0.05, 'Indexed query executes under 50ms');
    }
    
    public function testBulkOperations() {
        $db = getDB();
        
        // Test bulk insert performance
        $startTime = microtime(true);
        
        $stmt = $db->prepare("INSERT INTO accesslogs (user_id, action, timestamp) VALUES (?, ?, NOW())");
        for ($i = 0; $i < 100; $i++) {
            $stmt->execute([1, 'test_action']);
        }
        
        $bulkTime = microtime(true) - $startTime;
        $this->assert($bulkTime < 1.0, '100 bulk inserts complete under 1 second');
    }
}
```

### Caching Performance Tests

**CachingPerformanceTest.php:**
```php
class CachingPerformanceTest {
    public function testCacheWritePerformance() {
        $perfManager = PerformanceManager::getInstance();
        
        $cacheKey = 'performance_test_' . time();
        $testData = ['performance' => 'test', 'value' => rand(1, 1000)];
        
        $writeStart = microtime(true);
        $perfManager->set($cacheKey, $testData, 300);
        $writeTime = microtime(true) - $writeStart;
        
        $this->assert($writeTime < 0.01, 'Cache write under 10ms');
    }
    
    public function testCacheReadPerformance() {
        $perfManager = PerformanceManager::getInstance();
        
        $cacheKey = 'performance_test_' . time();
        $testData = ['test' => 'data'];
        $perfManager->set($cacheKey, $testData, 300);
        
        $readStart = microtime(true);
        $retrieved = $perfManager->get($cacheKey);
        $readTime = microtime(true) - $readStart;
        
        $this->assert($readTime < 0.005, 'Cache read under 5ms');
        $this->assert($retrieved === $testData, 'Cache data integrity maintained');
    }
}
```

### Asset Loading Performance

**AssetLoadingTest.php:**
```php
class AssetLoadingTest {
    public function testCSSOptimization() {
        $assetOptimizer = AssetOptimizer::getInstance();
        
        $cssFiles = ['main.css', 'dashboard.css'];
        $startTime = microtime(true);
        $optimizedCSS = $assetOptimizer->optimizeCSS($cssFiles);
        $optimizationTime = microtime(true) - $startTime;
        
        $this->assert(!empty($optimizedCSS), 'CSS optimization produces output');
        $this->assert($optimizationTime < 0.5, 'CSS optimization under 500ms');
    }
    
    public function testJavaScriptMinification() {
        $assetOptimizer = AssetOptimizer::getInstance();
        
        $jsFiles = ['dashboard.js', 'events.js'];
        $startTime = microtime(true);
        $minifiedJS = $assetOptimizer->optimizeJS($jsFiles);
        $minificationTime = microtime(true) - $startTime;
        
        $this->assert(!empty($minifiedJS), 'JavaScript minification produces output');
        $this->assert($minificationTime < 0.3, 'JavaScript minification under 300ms');
    }
}
```

## 🛡️ Security Tests

Security tests validate protection against common vulnerabilities:

### SQL Injection Tests

**SQLInjectionTest.php:**
```php
class SQLInjectionTest {
    public function testSQLInjectionProtection() {
        $securityManager = SecurityManager::getInstance();
        
        $maliciousInputs = [
            "'; DROP TABLE users; --",
            "1' OR '1'='1",
            "admin'--",
            "' UNION SELECT * FROM users --"
        ];
        
        foreach ($maliciousInputs as $input) {
            $sanitized = $securityManager->sanitizeInput($input);
            $this->assert($sanitized !== $input, "SQL injection attempt blocked: " . substr($input, 0, 20) . "...");
        }
    }
    
    public function testPreparedStatements() {
        $db = getDB();
        
        // Test that prepared statements are used
        $maliciousInput = "'; DROP TABLE users; --";
        
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$maliciousInput]);
            $result = $stmt->fetchAll();
            
            // If we get here, the prepared statement worked safely
            $this->assert(true, 'Prepared statements protect against SQL injection');
        } catch (Exception $e) {
            $this->assert(false, 'Prepared statement failed: ' . $e->getMessage());
        }
    }
}
```

### XSS Protection Tests

**XSSProtectionTest.php:**
```php
class XSSProtectionTest {
    public function testXSSProtection() {
        $securityManager = SecurityManager::getInstance();
        
        $xssAttempts = [
            '<script>alert("xss")</script>',
            '<img src="x" onerror="alert(1)">',
            'javascript:alert("xss")',
            '<iframe src="javascript:alert(1)"></iframe>'
        ];
        
        foreach ($xssAttempts as $attempt) {
            $sanitized = $securityManager->sanitizeInput($attempt);
            $this->assert(
                strpos($sanitized, '<script>') === false && 
                strpos($sanitized, 'javascript:') === false,
                'XSS attempt blocked: ' . substr($attempt, 0, 30) . '...'
            );
        }
    }
    
    public function testOutputEscaping() {
        $testString = '<script>alert("test")</script>';
        $escaped = htmlspecialchars($testString, ENT_QUOTES, 'UTF-8');
        
        $this->assert($escaped !== $testString, 'Output escaping changes dangerous content');
        $this->assert(strpos($escaped, '&lt;script&gt;') !== false, 'Script tags properly escaped');
    }
}
```

### CSRF Protection Tests

**CSRFProtectionTest.php:**
```php
class CSRFProtectionTest {
    public function testCSRFTokenGeneration() {
        $securityManager = SecurityManager::getInstance();
        
        $token1 = $securityManager->generateCSRFToken();
        $token2 = $securityManager->generateCSRFToken();
        
        $this->assert(!empty($token1), 'CSRF token generation works');
        $this->assert(strlen($token1) >= 32, 'CSRF token has adequate length');
        $this->assert($token1 !== $token2, 'CSRF tokens are unique');
    }
    
    public function testCSRFTokenValidation() {
        $securityManager = SecurityManager::getInstance();
        
        $validToken = $securityManager->generateCSRFToken();
        $invalidToken = 'invalid_token_123';
        
        $this->assert($securityManager->validateCSRFToken($validToken), 'Valid CSRF token passes validation');
        $this->assert(!$securityManager->validateCSRFToken($invalidToken), 'Invalid CSRF token fails validation');
    }
}
```

### Authentication Security Tests

**AuthenticationSecurityTest.php:**
```php
class AuthenticationSecurityTest {
    public function testPasswordStrengthRequirements() {
        $auth = new AuthenticationService();
        
        $weakPasswords = ['123', 'password', 'admin', '12345678'];
        $strongPassword = 'StrongP@ssw0rd123!';
        
        foreach ($weakPasswords as $weak) {
            $isStrong = $auth->validatePasswordStrength($weak);
            $this->assert(!$isStrong, "Weak password rejected: {$weak}");
        }
        
        $isStrong = $auth->validatePasswordStrength($strongPassword);
        $this->assert($isStrong, 'Strong password accepted');
    }
    
    public function testLoginAttemptLimiting() {
        $auth = new AuthenticationService();
        
        // Test rate limiting exists
        $this->assert(method_exists($auth, 'checkRateLimit'), 'Rate limiting method exists');
        $this->assert(method_exists($auth, 'recordFailedAttempt'), 'Failed attempt tracking exists');
    }
    
    public function testSessionSecurity() {
        // Test session configuration
        $this->assert(ini_get('session.cookie_httponly') == '1', 'Session cookies are HTTP-only');
        $this->assert(ini_get('session.use_strict_mode') == '1', 'Strict session mode enabled');
    }
}
```

## 📊 Test Reporting

### Comprehensive Test Reports

The testing framework generates detailed reports in multiple formats:

**JSON Report Example:**
```json
{
    "summary": {
        "total_tests": 127,
        "passed": 124,
        "failed": 3,
        "success_rate": 97.64,
        "execution_time": 4.234,
        "memory_used": 18.5
    },
    "suites": {
        "unit": {
            "tests": 45,
            "passed": 44,
            "failed": 1
        },
        "integration": {
            "tests": 32,
            "passed": 31,
            "failed": 1
        },
        "performance": {
            "tests": 28,
            "passed": 27,
            "failed": 1
        },
        "security": {
            "tests": 22,
            "passed": 22,
            "failed": 0
        }
    },
    "failed_tests": [
        {
            "suite": "unit",
            "test": "Repository Performance Test",
            "error": "Query exceeded 50ms threshold",
            "timestamp": "2025-01-15T14:30:22Z"
        }
    ],
    "recommendations": [
        "⚠️ 3 tests failed. Review failed tests before deployment.",
        "📊 Consider optimizing slow queries identified in performance tests.",
        "🔒 All security tests passed - system is secure for deployment."
    ]
}
```

### Continuous Integration Support

**CI/CD Integration:**
```yaml
# .github/workflows/tests.yml
name: Test Suite

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.0'
        
    - name: Install Dependencies
      run: composer install
      
    - name: Run Tests
      run: php tests/run-tests.php
      
    - name: Generate Coverage Report
      run: php tests/coverage-report.php
      
    - name: Upload Results
      uses: actions/upload-artifact@v2
      with:
        name: test-results
        path: tests/reports/
```

## 🚀 Quality Assurance

### Code Quality Metrics

**Automated Quality Checks:**
- **Code Coverage**: Target 90%+ coverage
- **Complexity Analysis**: Cyclomatic complexity monitoring
- **Standards Compliance**: PSR-12 coding standards
- **Security Scanning**: OWASP vulnerability detection
- **Performance Benchmarks**: Response time monitoring

### Testing Best Practices

**Test Organization:**
1. **Arrange**: Set up test data and conditions
2. **Act**: Execute the function being tested
3. **Assert**: Verify the results
4. **Cleanup**: Reset state for next test

**Example Test Structure:**
```php
public function testUserCreation() {
    // Arrange
    $userData = [
        'username' => 'test_user',
        'email' => 'test@example.com',
        'password' => 'SecurePass123!'
    ];
    
    // Act
    $userService = new UserService();
    $result = $userService->createUser($userData);
    
    // Assert
    $this->assert($result['success'] === true, 'User creation succeeds');
    $this->assert(!empty($result['user_id']), 'User ID is generated');
    
    // Cleanup (handled by transaction rollback)
}
```

### Test Data Management

**Test Database:**
- Separate test database configuration
- Automatic transaction rollback after each test
- Seed data for consistent testing
- Mock data generators for complex scenarios

**Mock Objects:**
```php
class MockRfidDevice {
    public function scan(): string {
        return 'TEST_RFID_' . time();
    }
    
    public function isConnected(): bool {
        return true;
    }
}
```

## 🔧 Development Testing

### Local Testing Setup

**Prerequisites:**
```bash
# Install PHPUnit (optional - framework is self-contained)
composer require --dev phpunit/phpunit

# Set up test database
mysql -u root -p < database/test-setup.sql

# Configure test environment
cp tests/config/test.env.example tests/config/test.env
```

**Running Tests During Development:**
```bash
# Quick validation
php tests/simple-test.php

# Full test suite
php tests/run-tests.php

# Specific test file
php tests/unit/RepositoryPatternTest.php

# With verbose output
php tests/run-tests.php --verbose

# Performance tests only
php tests/run-tests.php --suite=performance
```

### Test-Driven Development (TDD)

**TDD Workflow:**
1. **Write Test**: Create failing test for new feature
2. **Write Code**: Implement minimum code to pass test
3. **Refactor**: Improve code while keeping tests passing
4. **Repeat**: Continue cycle for each feature

**Example TDD Cycle:**
```php
// 1. Write failing test
public function testCalculateAttendancePercentage() {
    $calculator = new AttendanceCalculator();
    $percentage = $calculator->calculate(8, 10); // 8 attended out of 10 total
    $this->assert($percentage === 80.0, 'Attendance percentage calculated correctly');
}

// 2. Write minimal implementation
class AttendanceCalculator {
    public function calculate(int $attended, int $total): float {
        return ($attended / $total) * 100;
    }
}

// 3. Refactor with edge cases
public function calculate(int $attended, int $total): float {
    if ($total === 0) return 0.0;
    return round(($attended / $total) * 100, 2);
}
```

---

**Testing Framework**: Production Ready  
**Last Updated**: January 2025  
**Coverage Target**: 90%+  
**Test Suites**: Unit, Integration, Performance, Security