<?php

declare(strict_types=1);

namespace RfidCheckin\Models;

/**
 * User Model
 * 
 * Represents a user entity with all associated data and behaviors.
 * Provides data validation, relationship management, and business logic.
 * 
 * @package RfidCheckin\Models
 * @version 1.0.0
 * @author Senior Development Team
 */
class User extends BaseModel
{
    protected array $fillable = [
        'user_id', 'firstname', 'lastname', 'email', 'password', 
        'rfid_tag', 'status', 'group_id', 'created_by', 'avatar',
        'last_access', 'failed_login_attempts', 'locked_until',
        'email_verified_at', 'phone', 'department', 'metadata'
    ];

    protected array $hidden = [
        'password'
    ];

    protected array $casts = [
        'user_id' => 'int',
        'group_id' => 'int',
        'created_by' => 'int',
        'failed_login_attempts' => 'int',
        'status' => 'string',
        'email_verified_at' => 'datetime',
        'last_access' => 'datetime',
        'locked_until' => 'datetime',
        'metadata' => 'json'
    ];

    protected array $dates = [
        'created_at', 'updated_at', 'email_verified_at', 
        'last_access', 'locked_until'
    ];

    /**
     * Get user's full name
     * 
     * @return string Full name
     */
    public function getFullName(): string
    {
        return trim($this->getAttribute('firstname', '') . ' ' . $this->getAttribute('lastname', ''));
    }

    /**
     * Get user's initials
     * 
     * @return string Initials
     */
    public function getInitials(): string
    {
        $firstname = $this->getAttribute('firstname', '');
        $lastname = $this->getAttribute('lastname', '');
        
        return strtoupper(substr($firstname, 0, 1) . substr($lastname, 0, 1));
    }

    /**
     * Check if user is active
     * 
     * @return bool True if active
     */
    public function isActive(): bool
    {
        return $this->getAttribute('status') === 'active';
    }

    /**
     * Check if user is locked
     * 
     * @return bool True if locked
     */
    public function isLocked(): bool
    {
        $lockedUntil = $this->getAttribute('locked_until');
        
        if (!$lockedUntil) {
            return false;
        }

        if ($lockedUntil instanceof \DateTime) {
            return $lockedUntil > new \DateTime();
        }

        return strtotime($lockedUntil) > time();
    }

    /**
     * Check if email is verified
     * 
     * @return bool True if verified
     */
    public function isEmailVerified(): bool
    {
        return $this->getAttribute('email_verified_at') !== null;
    }

    /**
     * Check if user has RFID tag
     * 
     * @return bool True if has tag
     */
    public function hasRfidTag(): bool
    {
        return !empty($this->getAttribute('rfid_tag'));
    }

    /**
     * Get avatar URL or initials
     * 
     * @return string Avatar URL or initials
     */
    public function getAvatar(): string
    {
        $avatar = $this->getAttribute('avatar');
        
        if ($avatar) {
            return $avatar;
        }

        // Return initials as fallback
        return $this->getInitials();
    }

    /**
     * Get user permissions based on group
     * 
     * @return array User permissions
     */
    public function getPermissions(): array
    {
        $groupId = $this->getAttribute('group_id');
        
        // Default permissions for different groups
        $permissions = [
            1 => ['admin', 'manage_users', 'manage_events', 'view_reports', 'manage_settings'],
            2 => ['manage_events', 'view_reports', 'check_in_users'],
            3 => ['view_own_data', 'self_checkin']
        ];

        return $permissions[$groupId] ?? ['view_own_data'];
    }

    /**
     * Check if user has specific permission
     * 
     * @param string $permission Permission to check
     * @return bool True if has permission
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getPermissions());
    }

    /**
     * Check if user is admin
     * 
     * @return bool True if admin
     */
    public function isAdmin(): bool
    {
        return $this->hasPermission('admin');
    }

    /**
     * Get user's department info
     * 
     * @return array Department information
     */
    public function getDepartmentInfo(): array
    {
        $department = $this->getAttribute('department');
        
        if (!$department) {
            return ['name' => 'Unassigned', 'code' => null];
        }

        // If department is stored as JSON
        if (is_array($department)) {
            return $department;
        }

        return ['name' => $department, 'code' => null];
    }

    /**
     * Get user metadata value
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
     * Set user metadata value
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
     * Get user's contact information
     * 
     * @return array Contact information
     */
    public function getContactInfo(): array
    {
        return [
            'email' => $this->getAttribute('email'),
            'phone' => $this->getAttribute('phone'),
            'department' => $this->getDepartmentInfo()
        ];
    }

    /**
     * Get user statistics
     * 
     * @return array User statistics
     */
    public function getStatistics(): array
    {
        // This would typically be populated by a service
        return [
            'total_checkins' => 0,
            'events_attended' => 0,
            'last_activity' => $this->getAttribute('last_access'),
            'account_age_days' => $this->getAccountAgeDays()
        ];
    }

    /**
     * Get account age in days
     * 
     * @return int Days since account creation
     */
    public function getAccountAgeDays(): int
    {
        $createdAt = $this->getAttribute('created_at');
        
        if (!$createdAt) {
            return 0;
        }

        $created = is_string($createdAt) ? new \DateTime($createdAt) : $createdAt;
        $now = new \DateTime();
        
        return $now->diff($created)->days;
    }

    /**
     * Format user for API response
     * 
     * @param bool $includePrivate Include private data
     * @return array Formatted user data
     */
    public function toApiArray(bool $includePrivate = false): array
    {
        $data = [
            'id' => $this->getAttribute('user_id'),
            'name' => $this->getFullName(),
            'firstname' => $this->getAttribute('firstname'),
            'lastname' => $this->getAttribute('lastname'),
            'email' => $this->getAttribute('email'),
            'initials' => $this->getInitials(),
            'avatar' => $this->getAvatar(),
            'status' => $this->getAttribute('status'),
            'department' => $this->getDepartmentInfo(),
            'has_rfid' => $this->hasRfidTag(),
            'email_verified' => $this->isEmailVerified(),
            'last_access' => $this->getAttribute('last_access'),
            'created_at' => $this->getAttribute('created_at')
        ];

        if ($includePrivate) {
            $data['rfid_tag'] = $this->getAttribute('rfid_tag');
            $data['phone'] = $this->getAttribute('phone');
            $data['group_id'] = $this->getAttribute('group_id');
            $data['permissions'] = $this->getPermissions();
            $data['metadata'] = $this->getAttribute('metadata', []);
        }

        return $data;
    }

    /**
     * Validate user data
     * 
     * @param array $data Data to validate
     * @param bool $isUpdate Whether this is an update operation
     * @return array Validation errors
     */
    public static function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        // Email validation
        if (!$isUpdate || isset($data['email'])) {
            if (empty($data['email'])) {
                $errors['email'] = 'Email is required';
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format';
            }
        }

        // Name validation
        if (!$isUpdate || isset($data['firstname'])) {
            if (empty($data['firstname'])) {
                $errors['firstname'] = 'First name is required';
            } elseif (strlen($data['firstname']) > 100) {
                $errors['firstname'] = 'First name is too long';
            }
        }

        if (!$isUpdate || isset($data['lastname'])) {
            if (empty($data['lastname'])) {
                $errors['lastname'] = 'Last name is required';
            } elseif (strlen($data['lastname']) > 100) {
                $errors['lastname'] = 'Last name is too long';
            }
        }

        // Password validation (for creation or password change)
        if (!$isUpdate || isset($data['password'])) {
            if (!$isUpdate && empty($data['password'])) {
                $errors['password'] = 'Password is required';
            } elseif (!empty($data['password'])) {
                if (strlen($data['password']) < 8) {
                    $errors['password'] = 'Password must be at least 8 characters';
                } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $data['password'])) {
                    $errors['password'] = 'Password must contain uppercase, lowercase, and number';
                }
            }
        }

        // RFID tag validation
        if (isset($data['rfid_tag']) && !empty($data['rfid_tag'])) {
            if (!preg_match('/^[A-Fa-f0-9]{8,16}$/', $data['rfid_tag'])) {
                $errors['rfid_tag'] = 'Invalid RFID tag format';
            }
        }

        // Status validation
        if (isset($data['status'])) {
            $validStatuses = ['active', 'inactive', 'suspended', 'pending'];
            if (!in_array($data['status'], $validStatuses)) {
                $errors['status'] = 'Invalid status';
            }
        }

        // Phone validation
        if (isset($data['phone']) && !empty($data['phone'])) {
            if (!preg_match('/^\+?[\d\s\-\(\)]+$/', $data['phone'])) {
                $errors['phone'] = 'Invalid phone number format';
            }
        }

        return $errors;
    }
}
