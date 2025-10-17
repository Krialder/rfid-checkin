<?php

declare(strict_types=1);

namespace RfidCheckin\Models;

use App\Core\Database;
use DateTime;
use PDO;

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
    private ?int $id = null;
    private string $firstName;
    private string $lastName;
    private string $email;
    private string $passwordHash;
    private UserRole $role;
    private ?string $rfidCard = null;
    private UserStatus $status;
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;
    
    // Apprentice-specific fields
    private ?int $yearLevel = null;
    private ?string $specialization = null;
    private ?DateTime $enrollmentDate = null;
    
    private $db;
    
    public function __construct()
    {
        $this->status = UserStatus::ACTIVE;
        $this->role = UserRole::APPRENTICE;
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function setId(int $id): void
    {
        $this->id = $id;
    }
    
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }
    
    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }
    
    public function getFirstName(): string
    {
        return $this->firstName;
    }
    
    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }
    
    public function getLastName(): string
    {
        return $this->lastName;
    }
    
    public function setEmail(string $email): void
    {
        $this->email = $email;
    }
    
    public function getEmail(): string
    {
        return $this->email;
    }
    
    public function setPassword(string $password): void
    {
        $this->passwordHash = password_hash($password, PASSWORD_DEFAULT);
    }
    
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }
    
    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }
    
    public function setPasswordHash(string $hash): void
    {
        $this->passwordHash = $hash;
    }
    
    public function setRole(UserRole $role): void
    {
        $this->role = $role;
    }
    
    public function getRole(): UserRole
    {
        return $this->role;
    }
    
    public function isTeacher(): bool
    {
        return $this->role === UserRole::TEACHER;
    }
    
    public function isApprentice(): bool
    {
        return $this->role === UserRole::APPRENTICE;
    }
    
    public function setRfidCard(?string $rfidCard): void
    {
        $this->rfidCard = $rfidCard;
    }
    
    public function getRfidCard(): ?string
    {
        return $this->rfidCard;
    }
    
    public function hasRfidCard(): bool
    {
        return $this->rfidCard !== null;
    }
    
    public function setStatus(UserStatus $status): void
    {
        $this->status = $status;
    }
    
    public function getStatus(): UserStatus
    {
        return $this->status;
    }
    
    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }
    
    public function setYearLevel(?int $yearLevel): void
    {
        $this->yearLevel = $yearLevel;
    }
    
    public function getYearLevel(): ?int
    {
        return $this->yearLevel;
    }
    
    public function setSpecialization(?string $specialization): void
    {
        $this->specialization = $specialization;
    }
    
    public function getSpecialization(): ?string
    {
        return $this->specialization;
    }
    
    public function setEnrollmentDate(?DateTime $date): void
    {
        $this->enrollmentDate = $date;
    }
    
    public function getEnrollmentDate(): ?DateTime
    {
        return $this->enrollmentDate;
    }
    
    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
    
    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }
    
    public function setUpdatedAt(DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
    
    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }
    
    public function findByRfid($rfidTag)
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE rfid_tag = :rfid_tag AND active = 1");
        $stmt->execute(['rfid_tag' => $rfidTag]);
        return $stmt->fetch();
    }
    
    public function getAll()
    {
        $stmt = $this->db->query("SELECT * FROM users ORDER BY name");
        return $stmt->fetchAll();
    }
    
    public function create($data)
    {
        $stmt = $this->db->prepare("INSERT INTO users (name, rfid_tag, active) VALUES (:name, :rfid_tag, :active)");
        $stmt->execute([
            'name' => $data['name'],
            'rfid_tag' => $data['rfid_tag'],
            'active' => $data['active'] ?? 1
        ]);
        return $this->db->lastInsertId();
    }
}

enum UserRole: string
{
    case APPRENTICE = 'apprentice';
    case TEACHER = 'teacher';
}

enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
}
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
