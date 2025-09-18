<?php

declare(strict_types=1);

namespace RfidCheckin\Services;

use Exception;

/**
 * Configuration Service
 * 
 * Manages all application configuration including environment variables,
 * database settings, security options, and feature flags.
 * 
 * Features:
 * - Environment-specific configurations
 * - Configuration validation
 * - Secure credential management
 * - Runtime configuration updates
 * - Configuration caching
 * - Default value handling
 * 
 * @package RfidCheckin\Services
 * @version 1.0.0
 */
class ConfigurationService
{
    private static ?ConfigurationService $instance = null;
    private array $config = [];
    private array $environmentDefaults = [];
    private string $environment;
    private ?LoggingService $logger = null;
    private ?string $configPath;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct(string $configPath = null)
    {
        // Store config path for potential future use, but don't require legacy config file
        $this->configPath = $configPath;
        $this->environment = $_ENV['APP_ENV'] ?? 'production';
        $this->loadEnvironmentDefaults();
        $this->loadConfiguration();
        $this->validateConfiguration();
        
        // Initialize logger after configuration is loaded to avoid circular dependency
        $this->initializeLogger();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(string $configPath = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($configPath);
        }
        return self::$instance;
    }
    
    /**
     * Initialize logger after configuration is ready
     */
    private function initializeLogger(): void
    {
        try {
            $this->logger = LoggingService::getInstance();
            $this->logger->debug('Configuration loaded', [
                'environment' => $this->environment,
                'config_keys' => array_keys($this->config)
            ]);
            $this->logger->info('Configuration validation successful');
        } catch (Exception $e) {
            // Logger not available yet, skip logging
            error_log("ConfigurationService: Could not initialize logger: " . $e->getMessage());
        }
    }

    /**
     * Load environment-specific defaults
     */
    private function loadEnvironmentDefaults(): void
    {
        $this->environmentDefaults = [
            'development' => [
                'DEBUG_MODE' => 'true',
                'LOG_LEVEL' => 'DEBUG',
                'CACHE_ENABLED' => 'false',
                'SESSION_LIFETIME' => '7200', // 2 hours
                'CSRF_TOKEN_LIFETIME' => '3600',
                'RATE_LIMIT_ENABLED' => 'false',
            ],
            'testing' => [
                'DEBUG_MODE' => 'true',
                'LOG_LEVEL' => 'INFO',
                'CACHE_ENABLED' => 'false',
                'SESSION_LIFETIME' => '1800', // 30 minutes
                'DATABASE_NAME_SUFFIX' => '_test',
            ],
            'staging' => [
                'DEBUG_MODE' => 'false',
                'LOG_LEVEL' => 'INFO',
                'CACHE_ENABLED' => 'true',
                'SESSION_LIFETIME' => '3600',
                'RATE_LIMIT_ENABLED' => 'true',
            ],
            'production' => [
                'DEBUG_MODE' => 'false',
                'LOG_LEVEL' => 'WARNING',
                'CACHE_ENABLED' => 'true',
                'SESSION_LIFETIME' => '3600',
                'RATE_LIMIT_ENABLED' => 'true',
                'SECURITY_HEADERS' => 'true',
            ]
        ];
    }

    /**
     * Load complete configuration
     */
    private function loadConfiguration(): void
    {
        // Start with environment defaults
        $defaults = $this->environmentDefaults[$this->environment] ?? $this->environmentDefaults['production'];
        
        // Load from environment variables
        $this->config = [
            // Application Settings
            'app' => [
                'name' => $this->getEnv('APP_NAME', 'RFID Check-in System'),
                'version' => $this->getEnv('APP_VERSION', '2.0.0'),
                'environment' => $this->environment,
                'debug_mode' => $this->getBoolEnv('DEBUG_MODE', $defaults['DEBUG_MODE'] ?? 'false'),
                'base_url' => $this->getEnv('BASE_URL', 'http://localhost/rfid-checkin'),
                'timezone' => $this->getEnv('APP_TIMEZONE', 'UTC'),
            ],

            // Database Configuration
            'database' => [
                'host' => $this->getEnv('DB_HOST', 'localhost'),
                'name' => $this->getEnv('DB_NAME', 'rfid_checkin_system') . ($defaults['DATABASE_NAME_SUFFIX'] ?? ''),
                'username' => $this->getEnv('DB_USER', 'root'),
                'password' => $this->getEnv('DB_PASS', ''),
                'charset' => $this->getEnv('DB_CHARSET', 'utf8mb4'),
                'port' => $this->getIntEnv('DB_PORT', 3306),
                'options' => [
                    'timeout' => $this->getIntEnv('DB_TIMEOUT', 10),
                    'persistent' => $this->getBoolEnv('DB_PERSISTENT', 'false'),
                ]
            ],

            // Security Configuration
            'security' => [
                'session_lifetime' => $this->getIntEnv('SESSION_LIFETIME', (int)($defaults['SESSION_LIFETIME'] ?? 3600)),
                'session_regenerate_interval' => $this->getIntEnv('SESSION_REGENERATE_INTERVAL', 600),
                'csrf_token_lifetime' => $this->getIntEnv('CSRF_TOKEN_LIFETIME', (int)($defaults['CSRF_TOKEN_LIFETIME'] ?? 3600)),
                'password_min_length' => $this->getIntEnv('PASSWORD_MIN_LENGTH', 8),
                'max_login_attempts' => $this->getIntEnv('MAX_LOGIN_ATTEMPTS', 5),
                'lockout_duration' => $this->getIntEnv('LOCKOUT_DURATION', 1800),
                'rate_limit_enabled' => $this->getBoolEnv('RATE_LIMIT_ENABLED', $defaults['RATE_LIMIT_ENABLED'] ?? 'true'),
                'rate_limit_window' => $this->getIntEnv('RATE_LIMIT_WINDOW', 60),
                'max_requests_per_window' => $this->getIntEnv('MAX_REQUESTS_PER_WINDOW', 100),
                'security_headers' => $this->getBoolEnv('SECURITY_HEADERS', $defaults['SECURITY_HEADERS'] ?? 'true'),
            ],

            // Logging Configuration
            'logging' => [
                'level' => $this->getEnv('LOG_LEVEL', $defaults['LOG_LEVEL'] ?? 'INFO'),
                'file_path' => $this->getEnv('LOG_FILE_PATH', dirname(__DIR__, 2) . '/logs'),
                'max_file_size' => $this->getIntEnv('LOG_MAX_FILE_SIZE', 10485760), // 10MB
                'retention_days' => $this->getIntEnv('LOG_RETENTION_DAYS', 30),
            ],

            // Cache Configuration
            'cache' => [
                'enabled' => $this->getBoolEnv('CACHE_ENABLED', $defaults['CACHE_ENABLED'] ?? 'true'),
                'type' => $this->getEnv('CACHE_TYPE', 'file'),
                'file_path' => $this->getEnv('CACHE_FILE_PATH', dirname(__DIR__, 2) . '/cache'),
                'default_ttl' => $this->getIntEnv('CACHE_DEFAULT_TTL', 300),
            ],

            // File Upload Configuration
            'upload' => [
                'max_file_size' => $this->getIntEnv('MAX_FILE_SIZE', 5242880), // 5MB
                'allowed_types' => explode(',', $this->getEnv('ALLOWED_FILE_TYPES', 'jpg,jpeg,png,gif,pdf,doc,docx')),
                'upload_path' => $this->getEnv('UPLOAD_PATH', dirname(__DIR__, 2) . '/uploads'),
                'temp_path' => $this->getEnv('TEMP_PATH', sys_get_temp_dir()),
            ],

            // Email Configuration
            'email' => [
                'enabled' => $this->getBoolEnv('EMAIL_ENABLED', 'false'),
                'smtp_host' => $this->getEnv('SMTP_HOST', ''),
                'smtp_port' => $this->getIntEnv('SMTP_PORT', 587),
                'smtp_username' => $this->getEnv('SMTP_USERNAME', ''),
                'smtp_password' => $this->getEnv('SMTP_PASSWORD', ''),
                'smtp_encryption' => $this->getEnv('SMTP_ENCRYPTION', 'tls'),
                'from_address' => $this->getEnv('EMAIL_FROM_ADDRESS', 'noreply@example.com'),
                'from_name' => $this->getEnv('EMAIL_FROM_NAME', 'RFID Check-in System'),
            ],

            // API Configuration
            'api' => [
                'rate_limit_enabled' => $this->getBoolEnv('API_RATE_LIMIT_ENABLED', 'true'),
                'rate_limit_per_minute' => $this->getIntEnv('API_RATE_LIMIT_PER_MINUTE', 60),
                'pagination_default_limit' => $this->getIntEnv('API_PAGINATION_DEFAULT_LIMIT', 20),
                'pagination_max_limit' => $this->getIntEnv('API_PAGINATION_MAX_LIMIT', 100),
            ],

            // Hardware Configuration
            'hardware' => [
                'rfid_enabled' => $this->getBoolEnv('RFID_ENABLED', 'true'),
                'device_timeout' => $this->getIntEnv('DEVICE_TIMEOUT', 30),
                'max_devices' => $this->getIntEnv('MAX_DEVICES', 50),
            ],

            // Feature Flags
            'features' => [
                'user_registration' => $this->getBoolEnv('FEATURE_USER_REGISTRATION', 'true'),
                'password_reset' => $this->getBoolEnv('FEATURE_PASSWORD_RESET', 'true'),
                'analytics' => $this->getBoolEnv('FEATURE_ANALYTICS', 'true'),
                'notifications' => $this->getBoolEnv('FEATURE_NOTIFICATIONS', 'true'),
                'reporting' => $this->getBoolEnv('FEATURE_REPORTING', 'true'),
                'bulk_operations' => $this->getBoolEnv('FEATURE_BULK_OPERATIONS', 'true'),
            ]
        ];

        // Initialize logger after configuration is loaded to avoid circular dependency
        $this->initializeLogger();
    }

    /**
     * Validate configuration for required values
     */
    private function validateConfiguration(): void
    {
        $required = [
            'database.host',
            'database.name',
            'database.username',
            'app.base_url'
        ];

        $missing = [];
        foreach ($required as $key) {
            if ($this->get($key) === null) {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            throw new Exception('Missing required configuration: ' . implode(', ', $missing));
        }

        // Validate URL format
        if (!filter_var($this->get('app.base_url'), FILTER_VALIDATE_URL)) {
            throw new Exception('Invalid base URL format');
        }

        // Validate email configuration if enabled
        if ($this->get('email.enabled')) {
            $emailRequired = ['email.smtp_host', 'email.smtp_username', 'email.from_address'];
            $emailMissing = [];
            
            foreach ($emailRequired as $key) {
                if (empty($this->get($key))) {
                    $emailMissing[] = $key;
                }
            }
            
            if (!empty($emailMissing)) {
                throw new Exception('Email enabled but missing configuration: ' . implode(', ', $emailMissing));
            }
        }
    }

    /**
     * Get configuration value using dot notation
     * 
     * @param string $key Configuration key (e.g., 'database.host')
     * @param mixed $default Default value if not found
     * @return mixed Configuration value
     */
    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Set configuration value using dot notation
     * 
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     */
    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!is_array($config)) {
                $config = [];
            }
            if (!array_key_exists($k, $config)) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;

        if ($this->logger) {
            $this->logger->debug('Configuration value updated', [
                'key' => $key,
                'value' => is_array($value) ? '[array]' : $value
            ]);
        }
    }

    /**
     * Check if configuration key exists
     * 
     * @param string $key Configuration key
     * @return bool True if exists
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Get all configuration
     * 
     * @return array Complete configuration array
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Get configuration section
     * 
     * @param string $section Section name
     * @return array Section configuration
     */
    public function getSection(string $section): array
    {
        return $this->get($section, []);
    }

    /**
     * Get database configuration formatted for PDO
     * 
     * @return array Database configuration
     */
    public function getDatabaseConfig(): array
    {
        $dbConfig = $this->getSection('database');
        
        return [
            'dsn' => sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['name'],
                $dbConfig['charset']
            ),
            'username' => $dbConfig['username'],
            'password' => $dbConfig['password'],
            'options' => $dbConfig['options']
        ];
    }

    /**
     * Get environment variable with default
     * 
     * @param string $key Environment variable name
     * @param string $default Default value
     * @return string Environment variable value
     */
    private function getEnv(string $key, string $default = ''): string
    {
        return $_ENV[$key] ?? $default;
    }

    /**
     * Get environment variable as integer
     * 
     * @param string $key Environment variable name
     * @param int $default Default value
     * @return int Environment variable value as integer
     */
    private function getIntEnv(string $key, int $default = 0): int
    {
        $value = $_ENV[$key] ?? $default;
        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Get environment variable as boolean
     * 
     * @param string $key Environment variable name
     * @param string $default Default value ('true' or 'false')
     * @return bool Environment variable value as boolean
     */
    private function getBoolEnv(string $key, string $default = 'false'): bool
    {
        $value = strtolower($_ENV[$key] ?? $default);
        return in_array($value, ['true', '1', 'yes', 'on']);
    }

    /**
     * Get current environment
     * 
     * @return string Current environment
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Check if in development environment
     * 
     * @return bool True if development
     */
    public function isDevelopment(): bool
    {
        return $this->environment === 'development';
    }

    /**
     * Check if in production environment
     * 
     * @return bool True if production
     */
    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    /**
     * Check if debug mode is enabled
     * 
     * @return bool True if debug mode
     */
    public function isDebugMode(): bool
    {
        return $this->get('app.debug_mode', false);
    }

    /**
     * Get feature flag status
     * 
     * @param string $feature Feature name
     * @return bool True if feature is enabled
     */
    public function isFeatureEnabled(string $feature): bool
    {
        return $this->get("features.{$feature}", false);
    }

    /**
     * Export configuration for debugging (without sensitive data)
     * 
     * @return array Safe configuration for export
     */
    public function exportSafe(): array
    {
        $safe = $this->config;
        
        // Remove sensitive data
        unset(
            $safe['database']['password'],
            $safe['email']['smtp_password']
        );
        
        // Mask partial sensitive data
        if (isset($safe['database']['username'])) {
            $safe['database']['username'] = $this->maskValue($safe['database']['username']);
        }
        
        if (isset($safe['email']['smtp_username'])) {
            $safe['email']['smtp_username'] = $this->maskValue($safe['email']['smtp_username']);
        }
        
        return $safe;
    }

    /**
     * Mask sensitive value for logging
     * 
     * @param string $value Value to mask
     * @return string Masked value
     */
    private function maskValue(string $value): string
    {
        if (strlen($value) <= 3) {
            return str_repeat('*', strlen($value));
        }
        
        return substr($value, 0, 2) . str_repeat('*', strlen($value) - 4) . substr($value, -2);
    }

    /**
     * Prevent cloning of singleton
     */
    private function __clone() {}

    /**
     * Prevent unserialization of singleton
     */
    public function __wakeup() {}
}
