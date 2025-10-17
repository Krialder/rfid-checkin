<?php

declare(strict_types=1);

namespace RfidCheckin\Services;

use Exception;

/**
 * Configuration Service
 * 
 * Manages application configuration with environment support
 * 
 * @package RfidCheckin\Services
 */
class ConfigurationService
{
    private static ?ConfigurationService $instance = null;
    private array $config = [];
    private string $environment;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct(string $configPath = null)
    {
        $this->environment = $_ENV['APP_ENV'] ?? 'development';
        $this->loadConfiguration($configPath);
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
     * Load configuration from file
     */
    private function loadConfiguration(string $configPath = null): void
    {
        // Default configuration
        $this->config = [
            'app' => [
                'name' => 'RFID Check-in System',
                'version' => '2.0.0',
                'environment' => $this->environment,
                'debug_mode' => $this->environment === 'development',
                'base_url' => 'http://localhost/rfid-checkin',
                'timezone' => 'UTC',
                'memory_limit' => '256M',
                'execution_limit' => 30,
                'https_only' => false
            ],
            'database' => [
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'name' => $_ENV['DB_NAME'] ?? 'rfid_checking',
                'username' => $_ENV['DB_USER'] ?? 'root',
                'password' => $_ENV['DB_PASS'] ?? '',
                'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
                'port' => (int)($_ENV['DB_PORT'] ?? 3306)
            ],
            'session' => [
                'name' => 'RFID_SESSION',
                'lifetime' => 7200,
                'domain' => '',
            ],
            'middleware' => []
        ];

        // Load from config file if exists
        if ($configPath && file_exists($configPath)) {
            $fileConfig = require $configPath;
            if (is_array($fileConfig)) {
                // Use array_replace_recursive to properly override defaults
                $this->config = array_replace_recursive($this->config, $fileConfig);
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
     * Set configuration value
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
    }

    /**
     * Get current environment
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Check if debug mode is enabled
     */
    public function isDebugMode(): bool
    {
        return $this->get('app.debug_mode', false);
    }

    /**
     * Get all configuration
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {}
}
