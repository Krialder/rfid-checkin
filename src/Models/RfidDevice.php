<?php

declare(strict_types=1);

namespace RfidCheckin\Models;

use DateTime;
use InvalidArgumentException;

/**
 * RFID Device Model
 * 
 * Represents RFID reading devices with status monitoring,
 * heartbeat tracking, and configuration management.
 * 
 * @package RfidCheckin\Models
 * @version 2.0.0
 * @author Senior Development Team
 */
class RfidDevice extends BaseModel
{
    protected array $fillable = [
        'device_id',
        'name',
        'location',
        'description',
        'status',
        'ip_address',
        'mac_address',
        'firmware_version',
        'hardware_version',
        'last_heartbeat',
        'last_scan',
        'configuration',
        'capabilities',
        'error_count',
        'total_scans',
        'uptime_seconds',
        'battery_level',
        'signal_strength',
        'temperature',
        'memory_usage',
        'is_active'
    ];

    protected array $hidden = [
        'ip_address',
        'mac_address'
    ];

    protected array $casts = [
        'last_heartbeat' => 'datetime',
        'last_scan' => 'datetime',
        'configuration' => 'json',
        'capabilities' => 'json',
        'error_count' => 'int',
        'total_scans' => 'int',
        'uptime_seconds' => 'int',
        'battery_level' => 'int',
        'signal_strength' => 'int',
        'temperature' => 'float',
        'memory_usage' => 'float',
        'is_active' => 'bool'
    ];

    protected array $dates = [
        'last_heartbeat',
        'last_scan',
        'created_at',
        'updated_at'
    ];

    // Status constants
    public const STATUS_ONLINE = 'online';
    public const STATUS_OFFLINE = 'offline';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_ERROR = 'error';
    public const STATUS_DISABLED = 'disabled';

    // Device type constants
    public const TYPE_RFID_READER = 'rfid_reader';
    public const TYPE_HYBRID = 'hybrid';
    public const TYPE_MOBILE = 'mobile';

    // Alert threshold constants
    public const OFFLINE_THRESHOLD = 300; // 5 minutes
    public const LOW_BATTERY_THRESHOLD = 20; // 20%
    public const HIGH_TEMPERATURE_THRESHOLD = 70.0; // 70°C
    public const HIGH_MEMORY_THRESHOLD = 80.0; // 80%

    /**
     * Validate device data
     * 
     * @param array $data Data to validate
     * @param bool $isUpdate Whether this is an update operation
     * @return array Validation errors
     */
    public static function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        // Required fields for creation
        if (!$isUpdate) {
            if (empty($data['device_id'])) {
                $errors['device_id'] = 'Device ID is required';
            }

            if (empty($data['name'])) {
                $errors['name'] = 'Device name is required';
            }
        }

        // Validate device_id
        if (isset($data['device_id'])) {
            if (empty($data['device_id']) || !is_string($data['device_id'])) {
                $errors['device_id'] = 'Device ID must be a non-empty string';
            } elseif (strlen($data['device_id']) > 50) {
                $errors['device_id'] = 'Device ID cannot exceed 50 characters';
            } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $data['device_id'])) {
                $errors['device_id'] = 'Device ID can only contain alphanumeric characters, hyphens, and underscores';
            }
        }

        // Validate name
        if (isset($data['name'])) {
            if (empty($data['name']) || !is_string($data['name'])) {
                $errors['name'] = 'Device name must be a non-empty string';
            } elseif (strlen($data['name']) > 100) {
                $errors['name'] = 'Device name cannot exceed 100 characters';
            }
        }

        // Validate location
        if (isset($data['location'])) {
            if (strlen($data['location']) > 200) {
                $errors['location'] = 'Location cannot exceed 200 characters';
            }
        }

        // Validate description
        if (isset($data['description'])) {
            if (strlen($data['description']) > 500) {
                $errors['description'] = 'Description cannot exceed 500 characters';
            }
        }

        // Validate status
        if (isset($data['status'])) {
            $validStatuses = [
                self::STATUS_ONLINE,
                self::STATUS_OFFLINE,
                self::STATUS_MAINTENANCE,
                self::STATUS_ERROR,
                self::STATUS_DISABLED
            ];
            if (!in_array($data['status'], $validStatuses)) {
                $errors['status'] = 'Invalid status. Must be one of: ' . implode(', ', $validStatuses);
            }
        }

        // Validate IP address
        if (isset($data['ip_address']) && !empty($data['ip_address'])) {
            if (!filter_var($data['ip_address'], FILTER_VALIDATE_IP)) {
                $errors['ip_address'] = 'Invalid IP address format';
            }
        }

        // Validate MAC address
        if (isset($data['mac_address']) && !empty($data['mac_address'])) {
            if (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $data['mac_address'])) {
                $errors['mac_address'] = 'Invalid MAC address format';
            }
        }

        // Validate version strings
        foreach (['firmware_version', 'hardware_version'] as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                if (strlen($data[$field]) > 20) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' cannot exceed 20 characters';
                }
            }
        }

        // Validate numeric fields
        $numericFields = [
            'error_count' => ['min' => 0, 'max' => 999999],
            'total_scans' => ['min' => 0, 'max' => 999999999],
            'uptime_seconds' => ['min' => 0, 'max' => 999999999],
            'battery_level' => ['min' => 0, 'max' => 100],
            'signal_strength' => ['min' => -100, 'max' => 0],
            'temperature' => ['min' => -40.0, 'max' => 100.0],
            'memory_usage' => ['min' => 0.0, 'max' => 100.0]
        ];

        foreach ($numericFields as $field => $constraints) {
            if (isset($data[$field])) {
                $value = $data[$field];
                if (!is_numeric($value)) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be numeric';
                } elseif ($value < $constraints['min'] || $value > $constraints['max']) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . 
                        " must be between {$constraints['min']} and {$constraints['max']}";
                }
            }
        }

        // Validate datetime fields
        foreach (['last_heartbeat', 'last_scan'] as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                try {
                    new DateTime($data[$field]);
                } catch (\Exception $e) {
                    $errors[$field] = "Invalid $field format. Use ISO 8601 format (Y-m-d H:i:s)";
                }
            }
        }

        return $errors;
    }

    /**
     * Get checkins relationship
     * 
     * @return array Checkins for this device
     */
    public function checkins(): array
    {
        // This would be implemented with proper repository injection
        return [];
    }

    /**
     * Get last heartbeat as DateTime object
     * 
     * @return DateTime|null Last heartbeat or null
     */
    public function lastHeartbeat(): ?DateTime
    {
        $lastHeartbeat = $this->getAttribute('last_heartbeat');
        
        if ($lastHeartbeat === null) {
            return null;
        }

        return $lastHeartbeat instanceof DateTime ? $lastHeartbeat : new DateTime($lastHeartbeat);
    }

    /**
     * Check if device is online based on heartbeat
     * 
     * @param int $thresholdSeconds Offline threshold in seconds
     * @return bool True if online
     */
    public function isOnline(int $thresholdSeconds = self::OFFLINE_THRESHOLD): bool
    {
        $lastHeartbeat = $this->lastHeartbeat();
        
        if ($lastHeartbeat === null) {
            return false;
        }

        $now = new DateTime();
        $timeDiff = $now->getTimestamp() - $lastHeartbeat->getTimestamp();
        
        return $timeDiff <= $thresholdSeconds && $this->getAttribute('status') === self::STATUS_ONLINE;
    }

    /**
     * Get time since last heartbeat in seconds
     * 
     * @return int|null Seconds since last heartbeat or null
     */
    public function getTimeSinceLastHeartbeat(): ?int
    {
        $lastHeartbeat = $this->lastHeartbeat();
        
        if ($lastHeartbeat === null) {
            return null;
        }

        $now = new DateTime();
        return $now->getTimestamp() - $lastHeartbeat->getTimestamp();
    }

    /**
     * Get uptime in human readable format
     * 
     * @return string Formatted uptime
     */
    public function getFormattedUptime(): string
    {
        $seconds = $this->getAttribute('uptime_seconds', 0);
        
        if ($seconds < 60) {
            return "{$seconds}s";
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            return "{$minutes}m";
        } elseif ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return "{$hours}h {$minutes}m";
        } else {
            $days = floor($seconds / 86400);
            $hours = floor(($seconds % 86400) / 3600);
            return "{$days}d {$hours}h";
        }
    }

    /**
     * Check if device has low battery
     * 
     * @param int $threshold Battery threshold percentage
     * @return bool True if battery is low
     */
    public function hasLowBattery(int $threshold = self::LOW_BATTERY_THRESHOLD): bool
    {
        $batteryLevel = $this->getAttribute('battery_level');
        
        return $batteryLevel !== null && $batteryLevel <= $threshold;
    }

    /**
     * Check if device temperature is high
     * 
     * @param float $threshold Temperature threshold in Celsius
     * @return bool True if temperature is high
     */
    public function hasHighTemperature(float $threshold = self::HIGH_TEMPERATURE_THRESHOLD): bool
    {
        $temperature = $this->getAttribute('temperature');
        
        return $temperature !== null && $temperature >= $threshold;
    }

    /**
     * Check if device memory usage is high
     * 
     * @param float $threshold Memory usage threshold percentage
     * @return bool True if memory usage is high
     */
    public function hasHighMemoryUsage(float $threshold = self::HIGH_MEMORY_THRESHOLD): bool
    {
        $memoryUsage = $this->getAttribute('memory_usage');
        
        return $memoryUsage !== null && $memoryUsage >= $threshold;
    }

    /**
     * Get device health status
     * 
     * @return array Health status with issues
     */
    public function getHealthStatus(): array
    {
        $issues = [];
        $status = 'healthy';
        
        // Check if online
        if (!$this->isOnline()) {
            $issues[] = 'Device is offline';
            $status = 'critical';
        }
        
        // Check battery
        if ($this->hasLowBattery()) {
            $issues[] = "Low battery: {$this->getAttribute('battery_level')}%";
            $status = $status === 'healthy' ? 'warning' : $status;
        }
        
        // Check temperature
        if ($this->hasHighTemperature()) {
            $issues[] = "High temperature: {$this->getAttribute('temperature')}°C";
            $status = $status === 'healthy' ? 'warning' : $status;
        }
        
        // Check memory usage
        if ($this->hasHighMemoryUsage()) {
            $issues[] = "High memory usage: {$this->getAttribute('memory_usage')}%";
            $status = $status === 'healthy' ? 'warning' : $status;
        }
        
        // Check error count
        $errorCount = $this->getAttribute('error_count', 0);
        if ($errorCount > 10) {
            $issues[] = "High error count: $errorCount";
            $status = $status === 'healthy' ? 'warning' : $status;
        }
        
        return [
            'status' => $status,
            'issues' => $issues,
            'last_check' => date('Y-m-d H:i:s'),
            'metrics' => [
                'battery_level' => $this->getAttribute('battery_level'),
                'temperature' => $this->getAttribute('temperature'),
                'memory_usage' => $this->getAttribute('memory_usage'),
                'error_count' => $this->getAttribute('error_count'),
                'signal_strength' => $this->getAttribute('signal_strength')
            ]
        ];
    }

    /**
     * Get configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value
     * @return mixed Configuration value
     */
    public function getConfiguration(string $key, $default = null)
    {
        $configuration = $this->getAttribute('configuration', []);
        return $configuration[$key] ?? $default;
    }

    /**
     * Set configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return self
     */
    public function setConfiguration(string $key, $value): self
    {
        $configuration = $this->getAttribute('configuration', []);
        $configuration[$key] = $value;
        $this->setAttribute('configuration', $configuration);
        
        return $this;
    }

    /**
     * Get capability value
     * 
     * @param string $capability Capability name
     * @return bool True if device has capability
     */
    public function hasCapability(string $capability): bool
    {
        $capabilities = $this->getAttribute('capabilities', []);
        return in_array($capability, $capabilities) || isset($capabilities[$capability]);
    }

    /**
     * Add capability
     * 
     * @param string $capability Capability name
     * @param mixed $value Capability value (optional)
     * @return self
     */
    public function addCapability(string $capability, $value = true): self
    {
        $capabilities = $this->getAttribute('capabilities', []);
        $capabilities[$capability] = $value;
        $this->setAttribute('capabilities', $capabilities);
        
        return $this;
    }

    /**
     * Update device statistics
     * 
     * @param array $stats Statistics to update
     * @return self
     */
    public function updateStatistics(array $stats): self
    {
        foreach ($stats as $key => $value) {
            if (in_array($key, ['error_count', 'total_scans', 'uptime_seconds'])) {
                $this->setAttribute($key, $value);
            }
        }
        
        return $this;
    }

    /**
     * Record heartbeat
     * 
     * @param array $data Heartbeat data
     * @return self
     */
    public function recordHeartbeat(array $data = []): self
    {
        $this->setAttribute('last_heartbeat', date('Y-m-d H:i:s'));
        $this->setAttribute('status', self::STATUS_ONLINE);
        
        // Update metrics if provided
        $metrics = ['battery_level', 'signal_strength', 'temperature', 'memory_usage', 'uptime_seconds'];
        foreach ($metrics as $metric) {
            if (isset($data[$metric])) {
                $this->setAttribute($metric, $data[$metric]);
            }
        }
        
        return $this;
    }

    /**
     * Record scan activity
     * 
     * @return self
     */
    public function recordScan(): self
    {
        $this->setAttribute('last_scan', date('Y-m-d H:i:s'));
        $this->setAttribute('total_scans', $this->getAttribute('total_scans', 0) + 1);
        
        return $this;
    }

    /**
     * Record error
     * 
     * @return self
     */
    public function recordError(): self
    {
        $this->setAttribute('error_count', $this->getAttribute('error_count', 0) + 1);
        
        // Set status to error if error count is high
        if ($this->getAttribute('error_count') > 5) {
            $this->setAttribute('status', self::STATUS_ERROR);
        }
        
        return $this;
    }

    /**
     * Reset error count
     * 
     * @return self
     */
    public function resetErrorCount(): self
    {
        $this->setAttribute('error_count', 0);
        
        // Reset status if it was error
        if ($this->getAttribute('status') === self::STATUS_ERROR) {
            $this->setAttribute('status', self::STATUS_ONLINE);
        }
        
        return $this;
    }

    /**
     * Get device performance metrics
     * 
     * @return array Performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        $totalScans = $this->getAttribute('total_scans', 0);
        $errorCount = $this->getAttribute('error_count', 0);
        $uptime = $this->getAttribute('uptime_seconds', 0);
        
        $successRate = $totalScans > 0 ? (($totalScans - $errorCount) / $totalScans) * 100 : 0;
        $avgScansPerHour = $uptime > 0 ? ($totalScans / ($uptime / 3600)) : 0;
        
        return [
            'total_scans' => $totalScans,
            'error_count' => $errorCount,
            'success_rate' => round($successRate, 2),
            'uptime_hours' => round($uptime / 3600, 2),
            'avg_scans_per_hour' => round($avgScansPerHour, 2),
            'last_heartbeat' => $this->getAttribute('last_heartbeat'),
            'last_scan' => $this->getAttribute('last_scan')
        ];
    }

    /**
     * Convert to array with computed fields
     * 
     * @param bool $includeHidden Include hidden attributes
     * @return array Model as array with computed fields
     */
    public function toArray(bool $includeHidden = false): array
    {
        $data = parent::toArray($includeHidden);
        
        // Add computed fields
        $data['is_online'] = $this->isOnline();
        $data['formatted_uptime'] = $this->getFormattedUptime();
        $data['health_status'] = $this->getHealthStatus();
        $data['performance_metrics'] = $this->getPerformanceMetrics();
        $data['time_since_heartbeat'] = $this->getTimeSinceLastHeartbeat();
        
        return $data;
    }
}