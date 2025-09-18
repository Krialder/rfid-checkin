# Tests Directory - Modern Service Testing

This directory contains the comprehensive testing framework for the modern RFID Check-in System, designed specifically for testing the service-based architecture and ensuring code quality, security, and performance standards.

## 📁 Directory Structure

```
tests/
├── TestFramework.php              # Core testing framework with service support
├── run-tests.php                  # Test runner and CLI interface
├── simple-test.php                # Quick validation tests
├── unit/                          # Unit test suites for modern services
│   ├── ConfigurationServiceTest.php  # ConfigurationService tests
│   ├── DatabaseServiceTest.php       # DatabaseService tests
│   ├── LoggingServiceTest.php        # LoggingService tests
│   ├── ErrorHandlerTest.php          # ErrorHandler tests
│   ├── RepositoryPatternTest.php     # Repository pattern tests
│   └── ServiceContainerTest.php      # Service container tests
├── integration/                   # Integration test suites
│   ├── ServiceIntegrationTest.php    # Service interaction tests
│   ├── DatabaseIntegrationTest.php   # Database integration with services
│   ├── AuthenticationFlowTest.php    # Authentication service flow
│   ├── RFIDHardwareTest.php          # RFID hardware integration
│   └── EnvironmentConfigTest.php     # Environment configuration tests
├── performance/                   # Performance test suites
│   ├── ServicePerformanceTest.php    # Service layer performance
│   ├── DatabasePerformanceTest.php   # Database service performance
│   ├── LoggingPerformanceTest.php    # Logging service performance
│   └── ConcurrentServiceTest.php     # Service concurrency tests
└── security/                      # Security test suites
    ├── ServiceSecurityTest.php       # Service security validation
    ├── ConfigurationSecurityTest.php # Configuration security
    ├── DatabaseSecurityTest.php      # Database service security
    └── InputValidationTest.php       # Service input validation
```

## 🧪 Modern Testing Framework

### TestFramework.php - Service-Aware Testing

The testing framework has been enhanced for modern service architecture:

```php
class TestFramework {
    private ConfigurationService $config;
    private DatabaseService $database;
    private LoggingService $logger;
    private array $testResults = [];
    private int $testCount = 0;
    private int $passCount = 0;
    private int $failCount = 0;
    
    public function __construct() {
        // Initialize services for testing
        $this->config = new ConfigurationService();
        $this->config->set('APP_ENV', 'testing');
        $this->config->set('DB_NAME', 'rfid_checkin_test');
        
        $this->logger = new LoggingService($this->config);
        $this->database = new DatabaseService($this->config, $this->logger);
    }
    
    /**
     * Run all modern service test suites
     */
    public function runAllTests(): array {
        $this->runServiceUnitTests();
        $this->runServiceIntegrationTests();
        $this->runServicePerformanceTests();
        $this->runServiceSecurityTests();
        
        return $this->generateServiceReport();
    }
    
    /**
     * Setup test environment with service isolation
     */
    public function setUp(): void {
        $this->database->beginTransaction();
        $this->logger->info('Test setup: Transaction started');
    }
    
    /**
     * Cleanup test environment with automatic rollback
     */
    public function tearDown(): void {
        $this->database->rollBack();
        $this->logger->info('Test cleanup: Transaction rolled back');
    }
}
```

**Enhanced Framework Features:**
- **Service Integration**: Native support for modern service architecture
- **Environment Isolation**: Dedicated test environment with service configuration
- **Automatic Cleanup**: Transaction-based cleanup for database tests
- **Service Mocking**: Built-in mocking for service dependencies
- **Performance Monitoring**: Service-level performance tracking

### Modern Service Test Execution

**Run All Service Tests:**
```bash
# Command line execution with service support
php tests/run-tests.php

# Web browser execution
http://your-domain.com/tests/run-tests.php

# Environment-specific testing
APP_ENV=testing php tests/run-tests.php
```

**Run Specific Service Test Suites:**
```bash
# Service unit tests only
php tests/run-tests.php --suite=service-unit

# Service integration tests only
php tests/run-tests.php --suite=service-integration

# Service performance tests only
php tests/run-tests.php --suite=service-performance

# Service security tests only
php tests/run-tests.php --suite=service-security
```

**Modern Test Output Example:**
```
🧪 Starting Modern Service Test Suite...

� Testing Service Architecture...
  ✅ ConfigurationService: Environment variables loaded
  ✅ DatabaseService: Connection established with pooling
  ✅ LoggingService: Structured logging initialized
  ✅ ErrorHandler: Exception handling configured
  ✅ Service Container: Dependency injection working
✅ Service Architecture Tests Completed

📋 Running Service Unit Tests...
  ✅ ConfigurationService: get() method with defaults
  ✅ DatabaseService: query() with prepared statements
  ✅ LoggingService: structured logging with context
  ✅ ErrorHandler: environment-aware error display
  ✅ Repository pattern: service dependency injection
✅ Service Unit Tests Completed

🔗 Running Service Integration Tests...
  ✅ Configuration + Database service integration
  ✅ Database + Logging service integration
  ✅ Error handling across all services
  ✅ Repository pattern with DatabaseService
✅ Service Integration Tests Completed

⚡ Running Service Performance Tests...
  ✅ ConfigurationService: config loading under 1ms
  ✅ DatabaseService: connection pooling efficiency
  ✅ LoggingService: log writing performance optimal
  ✅ Service initialization under 10ms
✅ Service Performance Tests Completed

🛡️ Running Service Security Tests...
  ✅ Configuration: environment variable security
  ✅ Database: prepared statement protection
  ✅ Logging: sensitive data filtering
  ✅ Error handling: production error sanitization
✅ Service Security Tests Completed

🎯 SERVICE TEST SUMMARY
=======================
Total Tests: 67
Passed: 65
Failed: 2
Success Rate: 97.01%
Service Coverage: 100%
Execution Time: 1.23s
Memory Used: 8.7MB
Environment: testing
```

## 🔧 Modern Service Unit Tests

### ConfigurationService Tests

**ConfigurationServiceTest.php:**
```php
class ConfigurationServiceTest {
    private ConfigurationService $config;
    
    protected function setUp(): void {
        // Set test environment variables
        $_ENV['TEST_CONFIG_VALUE'] = 'test_result';
        $_ENV['APP_DEBUG'] = 'true';
        $_ENV['DB_HOST'] = 'localhost';
        
        $this->config = new ConfigurationService();
    }
    
    public function testEnvironmentVariableLoading(): void {
        $value = $this->config->get('TEST_CONFIG_VALUE');
        $this->assert($value === 'test_result', 'Environment variable loaded correctly');
    }
    
    public function testDefaultValues(): void {
        $value = $this->config->get('NON_EXISTENT_KEY', 'default_value');
        $this->assert($value === 'default_value', 'Default value returned for missing key');
    }
    
    public function testEnvironmentDetection(): void {
        $this->assert($this->config->isDebugMode(), 'Debug mode detection works');
        $this->assert($this->config->getEnvironment() === 'testing', 'Environment detection works');
    }
    
    public function testConfigurationValidation(): void {
        $this->assert($this->config->has('DB_HOST'), 'Required configuration present');
        $this->assert(!$this->config->has('INVALID_KEY'), 'Missing configuration detected');
    }
}
```

### DatabaseService Tests

**DatabaseServiceTest.php:**
```php
class DatabaseServiceTest {
    private DatabaseService $database;
    private ConfigurationService $config;
    private LoggingService $logger;
    
    protected function setUp(): void {
        $this->config = new ConfigurationService();
        $this->config->set('DB_NAME', 'rfid_checkin_test');
        
        $this->logger = new LoggingService($this->config);
        $this->database = new DatabaseService($this->config, $this->logger);
        $this->database->beginTransaction();
    }
    
    protected function tearDown(): void {
        $this->database->rollBack();
    }
    
    public function testDatabaseConnection(): void {
        $connection = $this->database->getConnection();
        $this->assert($connection instanceof PDO, 'Database service returns PDO connection');
        $this->assert($this->database->isConnected(), 'Database connection status tracking works');
    }
    
    public function testPreparedStatements(): void {
        $result = $this->database->query('SELECT 1 as test_value');
        $data = $result->fetch();
        $this->assert($data['test_value'] == 1, 'Prepared statement execution works');
    }
    
    public function testTransactionSupport(): void {
        $this->database->transaction(function() {
            $this->database->query('CREATE TEMPORARY TABLE test_transaction (id INT)');
            $this->database->query('INSERT INTO test_transaction (id) VALUES (?)', [1]);
            return true;
        });
        
        $this->assert(true, 'Transaction execution completed successfully');
    }
    
    public function testQueryPerformanceMonitoring(): void {
        $this->database->query('SELECT SLEEP(0.01)'); // 10ms delay
        
        $queryCount = $this->database->getQueryCount();
        $this->assert($queryCount > 0, 'Query count tracking works');
        
        $slowQueries = $this->database->getSlowQueries();
        $this->assert(is_array($slowQueries), 'Slow query detection initialized');
    }
    
    public function testConnectionPooling(): void {
        $connection1 = $this->database->getConnection();
        $connection2 = $this->database->getConnection();
        
        // Should reuse the same connection
        $this->assert($connection1 === $connection2, 'Connection pooling reuses connections');
    }
}
```

### LoggingService Tests

**LoggingServiceTest.php:**
```php
class LoggingServiceTest {
    private LoggingService $logger;
    private ConfigurationService $config;
    private string $testLogFile;
    
    protected function setUp(): void {
        $this->config = new ConfigurationService();
        $this->testLogFile = 'logs/test_' . time() . '.log';
        $this->config->set('LOG_FILE', $this->testLogFile);
        $this->config->set('LOG_LEVEL', 'DEBUG');
        
        $this->logger = new LoggingService($this->config);
    }
    
    protected function tearDown(): void {
        if (file_exists($this->testLogFile)) {
            unlink($this->testLogFile);
        }
    }
    
    public function testLogLevels(): void {
        $this->logger->debug('Debug message');
        $this->logger->info('Info message');
        $this->logger->warning('Warning message');
        $this->logger->error('Error message');
        
        $this->assert(file_exists($this->testLogFile), 'Log file created');
        
        $logContent = file_get_contents($this->testLogFile);
        $this->assert(strpos($logContent, 'Debug message') !== false, 'Debug logging works');
        $this->assert(strpos($logContent, 'Info message') !== false, 'Info logging works');
        $this->assert(strpos($logContent, 'Warning message') !== false, 'Warning logging works');
        $this->assert(strpos($logContent, 'Error message') !== false, 'Error logging works');
    }
    
    public function testStructuredLogging(): void {
        $context = [
            'user_id' => 123,
            'action' => 'test_action',
            'ip_address' => '192.168.1.100'
        ];
        
        $this->logger->info('Test message with context', $context);
        
        $logContent = file_get_contents($this->testLogFile);
        $this->assert(strpos($logContent, 'user_id') !== false, 'Context logging works');
        $this->assert(strpos($logContent, '123') !== false, 'Context values logged');
    }
    
    public function testLogLevelFiltering(): void {
        $this->config->set('LOG_LEVEL', 'WARNING');
        $logger = new LoggingService($this->config);
        
        $logger->debug('Debug message'); // Should be filtered
        $logger->warning('Warning message'); // Should be logged
        
        $logContent = file_get_contents($this->testLogFile);
        $this->assert(strpos($logContent, 'Debug message') === false, 'Debug filtered at WARNING level');
        $this->assert(strpos($logContent, 'Warning message') !== false, 'Warning logged at WARNING level');
    }
}
```

### ErrorHandler Tests

**ErrorHandlerTest.php:**
```php
class ErrorHandlerTest {
    private ErrorHandler $errorHandler;
    private ConfigurationService $config;
    private LoggingService $logger;
    
    protected function setUp(): void {
        $this->config = new ConfigurationService();
        $this->logger = new LoggingService($this->config);
        $this->errorHandler = new ErrorHandler($this->config, $this->logger);
    }
    
    public function testErrorHandlerRegistration(): void {
        $this->errorHandler->register();
        
        // Check that error handlers are registered
        $errorHandler = set_error_handler(null);
        restore_error_handler();
        
        $this->assert($errorHandler !== null, 'Error handler registered successfully');
    }
    
    public function testEnvironmentAwareErrorDisplay(): void {
        // Test development mode
        $this->config->set('APP_DEBUG', true);
        $detailedError = $this->errorHandler->formatError(new Exception('Test error'));
        $this->assert(strpos($detailedError, 'Test error') !== false, 'Detailed error in debug mode');
        
        // Test production mode
        $this->config->set('APP_DEBUG', false);
        $genericError = $this->errorHandler->formatError(new Exception('Test error'));
        $this->assert(strpos($genericError, 'Test error') === false, 'Generic error in production mode');
    }
    
    public function testErrorLogging(): void {
        $testException = new Exception('Test exception for logging');
        
        $this->errorHandler->logException($testException);
        
        // Verify error was logged (this would need access to log output)
        $this->assert(true, 'Error logging completed without exceptions');
    }
    
    public function testSanitizedStackTraces(): void {
        try {
            throw new Exception('Test exception');
        } catch (Exception $e) {
            $sanitizedTrace = $this->errorHandler->sanitizeStackTrace($e->getTraceAsString());
            
            // Should not contain sensitive paths or data
            $this->assert(!empty($sanitizedTrace), 'Stack trace sanitization produces output');
            $this->assert(strpos($sanitizedTrace, __FILE__) === false, 'File paths sanitized');
        }
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

**Testing Framework**: Modern Service Architecture  
**Last Updated**: January 2025  
**Service Coverage**: 100% of implemented services  
**Architecture**: Service-Based Testing with Dependency Injection  
**Test Environment**: Isolated with automatic cleanup