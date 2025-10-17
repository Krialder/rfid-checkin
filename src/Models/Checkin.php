<?php

declare(strict_types=1);

namespace RfidCheckin\Models;

use DateTime;
use InvalidArgumentException;

/**
 * Checkin Model
 * 
 * Represents RFID check-in/check-out records with relationships
 * to users, events, and RFID devices. Handles business logic
 * for attendance tracking and validation.
 * 
 * @package RfidCheckin\Models
 * @version 2.0.0
 * @author Senior Development Team
 */
class Checkin extends BaseModel
{
    protected array $fillable = [
        'user_id',
        'event_id',
        'device_id',
        'rfid_tag',
        'checkin_time',
        'checkout_time',
        'status',
        'method',
        'location',
        'notes',
        'metadata'
    ];

    protected array $hidden = [
        'rfid_tag'
    ];

    protected array $casts = [
        'user_id' => 'int',
        'event_id' => 'int',
        'device_id' => 'int',
        'checkin_time' => 'datetime',
        'checkout_time' => 'datetime',
        'metadata' => 'json'
    ];

    protected array $dates = [
        'checkin_time',
        'checkout_time',
        'created_at',
        'updated_at'
    ];

    // Status constants
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_INCOMPLETE = 'incomplete';
    public const STATUS_CANCELLED = 'cancelled';

    // Method constants
    public const METHOD_RFID = 'rfid';
    public const METHOD_MANUAL = 'manual';
    public const METHOD_API = 'api';
    public const METHOD_MOBILE = 'mobile';

    /**
     * Validate checkin data
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
            if (empty($data['user_id'])) {
                $errors['user_id'] = 'User ID is required';
            }

            if (empty($data['method'])) {
                $errors['method'] = 'Check-in method is required';
            }
        }

        // Validate user_id
        if (isset($data['user_id'])) {
            if (!is_numeric($data['user_id']) || $data['user_id'] <= 0) {
                $errors['user_id'] = 'User ID must be a positive integer';
            }
        }

        // Validate event_id
        if (isset($data['event_id'])) {
            if (!is_numeric($data['event_id']) || $data['event_id'] <= 0) {
                $errors['event_id'] = 'Event ID must be a positive integer';
            }
        }

        // Validate device_id
        if (isset($data['device_id'])) {
            if (!is_numeric($data['device_id']) || $data['device_id'] <= 0) {
                $errors['device_id'] = 'Device ID must be a positive integer';
            }
        }

        // Validate RFID tag
        if (isset($data['rfid_tag'])) {
            if (empty($data['rfid_tag']) || !is_string($data['rfid_tag'])) {
                $errors['rfid_tag'] = 'RFID tag must be a non-empty string';
            } elseif (strlen($data['rfid_tag']) > 50) {
                $errors['rfid_tag'] = 'RFID tag cannot exceed 50 characters';
            }
        }

        // Validate status
        if (isset($data['status'])) {
            $validStatuses = [
                self::STATUS_CHECKED_IN,
                self::STATUS_CHECKED_OUT,
                self::STATUS_INCOMPLETE,
                self::STATUS_CANCELLED
            ];
            if (!in_array($data['status'], $validStatuses)) {
                $errors['status'] = 'Invalid status. Must be one of: ' . implode(', ', $validStatuses);
            }
        }

        // Validate method
        if (isset($data['method'])) {
            $validMethods = [
                self::METHOD_RFID,
                self::METHOD_MANUAL,
                self::METHOD_API,
                self::METHOD_MOBILE
            ];
            if (!in_array($data['method'], $validMethods)) {
                $errors['method'] = 'Invalid method. Must be one of: ' . implode(', ', $validMethods);
            }
        }

        // Validate datetime fields
        foreach (['checkin_time', 'checkout_time'] as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                try {
                    new DateTime($data[$field]);
                } catch (\Exception $e) {
                    $errors[$field] = "Invalid $field format. Use ISO 8601 format (Y-m-d H:i:s)";
                }
            }
        }

        // Validate location
        if (isset($data['location'])) {
            if (strlen($data['location']) > 100) {
                $errors['location'] = 'Location cannot exceed 100 characters';
            }
        }

        // Validate notes
        if (isset($data['notes'])) {
            if (strlen($data['notes']) > 1000) {
                $errors['notes'] = 'Notes cannot exceed 1000 characters';
            }
        }

        // Business logic validation
        if (isset($data['checkin_time']) && isset($data['checkout_time'])) {
            $checkinTime = new DateTime($data['checkin_time']);
            $checkoutTime = new DateTime($data['checkout_time']);
            
            if ($checkoutTime <= $checkinTime) {
                $errors['checkout_time'] = 'Checkout time must be after checkin time';
            }
        }

        return $errors;
    }

    /**
     * Get user relationship
     * 
     * @return array|null User data
     */
    public function user(): ?array
    {
        if (!$this->hasAttribute('user_id')) {
            return null;
        }

        // This would be implemented with proper repository injection
        return [
            'id' => $this->getAttribute('user_id'),
            'name' => 'User Name', // Placeholder
            'email' => 'user@example.com' // Placeholder
        ];
    }

    /**
     * Get event relationship
     * 
     * @return array|null Event data
     */
    public function event(): ?array
    {
        if (!$this->hasAttribute('event_id')) {
            return null;
        }

        // This would be implemented with proper repository injection
        return [
            'id' => $this->getAttribute('event_id'),
            'name' => 'Event Name', // Placeholder
            'start_time' => '2025-09-23 09:00:00' // Placeholder
        ];
    }

    /**
     * Get device relationship
     * 
     * @return array|null Device data
     */
    public function device(): ?array
    {
        if (!$this->hasAttribute('device_id')) {
            return null;
        }

        // This would be implemented with proper repository injection
        return [
            'id' => $this->getAttribute('device_id'),
            'name' => 'Device Name', // Placeholder
            'location' => 'Device Location' // Placeholder
        ];
    }

    /**
     * Check if checkin is complete (has both checkin and checkout times)
     * 
     * @return bool True if complete
     */
    public function isComplete(): bool
    {
        return $this->hasAttribute('checkin_time') && 
               $this->hasAttribute('checkout_time') &&
               $this->getAttribute('checkin_time') !== null &&
               $this->getAttribute('checkout_time') !== null;
    }

    /**
     * Check if checkin is currently active (checked in but not out)
     * 
     * @return bool True if active
     */
    public function isActive(): bool
    {
        return $this->hasAttribute('checkin_time') && 
               $this->getAttribute('checkin_time') !== null &&
               (
                   !$this->hasAttribute('checkout_time') ||
                   $this->getAttribute('checkout_time') === null
               );
    }

    /**
     * Get duration of checkin session in seconds
     * 
     * @return int|null Duration in seconds or null if incomplete
     */
    public function getDuration(): ?int
    {
        if (!$this->isComplete()) {
            return null;
        }

        $checkinTime = new DateTime($this->getAttribute('checkin_time'));
        $checkoutTime = new DateTime($this->getAttribute('checkout_time'));
        
        return $checkoutTime->getTimestamp() - $checkinTime->getTimestamp();
    }

    /**
     * Get formatted duration string
     * 
     * @return string|null Formatted duration or null if incomplete
     */
    public function getFormattedDuration(): ?string
    {
        $duration = $this->getDuration();
        
        if ($duration === null) {
            return null;
        }

        $hours = floor($duration / 3600);
        $minutes = floor(($duration % 3600) / 60);
        $seconds = $duration % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $seconds);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $seconds);
        } else {
            return sprintf('%ds', $seconds);
        }
    }

    /**
     * Check if checkin was late (for events with start times)
     * 
     * @return bool|null True if late, false if on time, null if cannot determine
     */
    public function isLate(): ?bool
    {
        $event = $this->event();
        
        if (!$event || !isset($event['start_time']) || !$this->hasAttribute('checkin_time')) {
            return null;
        }

        $eventStartTime = new DateTime($event['start_time']);
        $checkinTime = new DateTime($this->getAttribute('checkin_time'));
        
        return $checkinTime > $eventStartTime;
    }

    /**
     * Get metadata value
     * 
     * @param string $key Metadata key
     * @param mixed $default Default value
     * @return mixed Metadata value
     */
    public function getMetadata(string $key, $default = null)
    {
        $metadata = $this->getAttribute('metadata', []);
        return $metadata[$key] ?? $default;
    }

    /**
     * Set metadata value
     * 
     * @param string $key Metadata key
     * @param mixed $value Metadata value
     * @return self
     */
    public function setMetadata(string $key, $value): self
    {
        $metadata = $this->getAttribute('metadata', []);
        $metadata[$key] = $value;
        $this->setAttribute('metadata', $metadata);
        
        return $this;
    }

    /**
     * Scope: Get today's checkins
     * 
     * @param array $query Query parameters
     * @return array Modified query
     */
    public static function scopeToday(array $query = []): array
    {
        $today = date('Y-m-d');
        $query['checkin_time_gte'] = $today . ' 00:00:00';
        $query['checkin_time_lt'] = $today . ' 23:59:59';
        
        return $query;
    }

    /**
     * Scope: Get checkins by event
     * 
     * @param array $query Query parameters
     * @param int $eventId Event ID
     * @return array Modified query
     */
    public static function scopeByEvent(array $query, int $eventId): array
    {
        $query['event_id'] = $eventId;
        return $query;
    }

    /**
     * Scope: Get checkins by user
     * 
     * @param array $query Query parameters
     * @param int $userId User ID
     * @return array Modified query
     */
    public static function scopeByUser(array $query, int $userId): array
    {
        $query['user_id'] = $userId;
        return $query;
    }

    /**
     * Scope: Get active checkins (checked in but not out)
     * 
     * @param array $query Query parameters
     * @return array Modified query
     */
    public static function scopeActive(array $query = []): array
    {
        $query['status'] = self::STATUS_CHECKED_IN;
        $query['checkout_time'] = null;
        
        return $query;
    }

    /**
     * Scope: Get checkins by method
     * 
     * @param array $query Query parameters
     * @param string $method Check-in method
     * @return array Modified query
     */
    public static function scopeByMethod(array $query, string $method): array
    {
        $query['method'] = $method;
        return $query;
    }

    /**
     * Get statistics for checkins
     * 
     * @param array $conditions Optional conditions
     * @return array Statistics
     */
    public static function getStatistics(array $conditions = []): array
    {
        // This would be implemented with proper repository
        return [
            'total_checkins' => 0,
            'active_checkins' => 0,
            'average_duration' => 0,
            'most_used_method' => self::METHOD_RFID
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
        $data['is_complete'] = $this->isComplete();
        $data['is_active'] = $this->isActive();
        $data['duration_seconds'] = $this->getDuration();
        $data['formatted_duration'] = $this->getFormattedDuration();
        $data['is_late'] = $this->isLate();
        
        return $data;
    }
}