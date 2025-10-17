<?php

declare(strict_types=1);

namespace RfidCheckin\Models;

use DateTime;

/**
 * Event Model
 * 
 * Represents an event entity with all associated data and behaviors.
 * Handles event validation, scheduling, and relationship management.
 * 
 * @package RfidCheckin\Models
 * @version 1.0.0
 * @author Senior Development Team
 */
class Event extends BaseModel
{
    private ?int $id = null;
    private string $name;
    private EventType $type;
    private DateTime $startDate;
    private DateTime $endDate;
    private ?string $description = null;
    private bool $affectsAttendance = true;
    private array $groupIds = [];
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;

    protected array $fillable = [
        'event_id', 'name', 'description', 'event_date', 'end_date',
        'location', 'capacity', 'registration_deadline', 'status',
        'created_by', 'allow_self_checkin', 'require_prereg',
        'is_recurring', 'recurrence_pattern', 'parent_event_id',
        'tags', 'metadata'
    ];

    protected array $casts = [
        'event_id' => 'int',
        'created_by' => 'int',
        'parent_event_id' => 'int',
        'capacity' => 'int',
        'allow_self_checkin' => 'bool',
        'require_prereg' => 'bool',
        'is_recurring' => 'bool',
        'event_date' => 'datetime',
        'end_date' => 'datetime',
        'registration_deadline' => 'datetime',
        'tags' => 'json',
        'metadata' => 'json',
        'recurrence_pattern' => 'json'
    ];

    protected array $dates = [
        'event_date', 'end_date', 'registration_deadline',
        'created_at', 'updated_at'
    ];

    /**
     * Get event duration in minutes
     * 
     * @return int|null Duration in minutes
     */
    public function getDurationMinutes(): ?int
    {
        $startDate = $this->getAttribute('event_date');
        $endDate = $this->getAttribute('end_date');

        if (!$startDate || !$endDate) {
            return null;
        }

        $start = is_string($startDate) ? new \DateTime($startDate) : $startDate;
        $end = is_string($endDate) ? new \DateTime($endDate) : $endDate;

        return (int) (($end->getTimestamp() - $start->getTimestamp()) / 60);
    }

    /**
     * Get event duration formatted
     * 
     * @return string Formatted duration
     */
    public function getFormattedDuration(): string
    {
        $minutes = $this->getDurationMinutes();

        if ($minutes === null) {
            return 'No end time';
        }

        if ($minutes < 60) {
            return "{$minutes} minutes";
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return "{$hours} " . ($hours === 1 ? 'hour' : 'hours');
        }

        return "{$hours}h {$remainingMinutes}m";
    }

    /**
     * Check if event is active
     * 
     * @return bool True if active
     */
    public function isActive(): bool
    {
        return $this->getAttribute('status') === 'active';
    }

    /**
     * Check if event is in the past
     * 
     * @return bool True if past event
     */
    public function isPast(): bool
    {
        $eventDate = $this->getAttribute('event_date');
        
        if (!$eventDate) {
            return false;
        }

        $date = is_string($eventDate) ? new \DateTime($eventDate) : $eventDate;
        return $date < new \DateTime();
    }

    /**
     * Check if event is upcoming
     * 
     * @return bool True if upcoming
     */
    public function isUpcoming(): bool
    {
        return !$this->isPast() && $this->isActive();
    }

    /**
     * Check if event is today
     * 
     * @return bool True if today
     */
    public function isToday(): bool
    {
        $eventDate = $this->getAttribute('event_date');
        
        if (!$eventDate) {
            return false;
        }

        $date = is_string($eventDate) ? new \DateTime($eventDate) : $eventDate;
        $today = new \DateTime();
        
        return $date->format('Y-m-d') === $today->format('Y-m-d');
    }

    /**
     * Check if registration is open
     * 
     * @return bool True if registration is open
     */
    public function isRegistrationOpen(): bool
    {
        if (!$this->isActive() || $this->isPast()) {
            return false;
        }

        $deadline = $this->getAttribute('registration_deadline');
        
        if (!$deadline) {
            return true; // No deadline set
        }

        $deadlineDate = is_string($deadline) ? new \DateTime($deadline) : $deadline;
        return $deadlineDate > new \DateTime();
    }

    /**
     * Check if event has capacity limit
     * 
     * @return bool True if has capacity limit
     */
    public function hasCapacityLimit(): bool
    {
        $capacity = $this->getAttribute('capacity');
        return $capacity !== null && $capacity > 0;
    }

    /**
     * Check if event is at capacity
     * 
     * @param int $currentRegistrations Current registration count
     * @return bool True if at capacity
     */
    public function isAtCapacity(int $currentRegistrations): bool
    {
        if (!$this->hasCapacityLimit()) {
            return false;
        }

        return $currentRegistrations >= $this->getAttribute('capacity');
    }

    /**
     * Get remaining capacity
     * 
     * @param int $currentRegistrations Current registration count
     * @return int|null Remaining spots
     */
    public function getRemainingCapacity(int $currentRegistrations): ?int
    {
        if (!$this->hasCapacityLimit()) {
            return null; // Unlimited
        }

        return max(0, $this->getAttribute('capacity') - $currentRegistrations);
    }

    /**
     * Check if event is recurring
     * 
     * @return bool True if recurring
     */
    public function isRecurring(): bool
    {
        return $this->getAttribute('is_recurring', false);
    }

    /**
     * Check if event is a recurring instance
     * 
     * @return bool True if recurring instance
     */
    public function isRecurringInstance(): bool
    {
        return $this->getAttribute('parent_event_id') !== null;
    }

    /**
     * Get event tags
     * 
     * @return array Event tags
     */
    public function getTags(): array
    {
        return $this->getAttribute('tags', []);
    }

    /**
     * Check if event has specific tag
     * 
     * @param string $tag Tag to check
     * @return bool True if has tag
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->getTags());
    }

    /**
     * Add tag to event
     * 
     * @param string $tag Tag to add
     * @return self
     */
    public function addTag(string $tag): self
    {
        $tags = $this->getTags();
        
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->setAttribute('tags', $tags);
        }

        return $this;
    }

    /**
     * Remove tag from event
     * 
     * @param string $tag Tag to remove
     * @return self
     */
    public function removeTag(string $tag): self
    {
        $tags = $this->getTags();
        $tags = array_filter($tags, fn($t) => $t !== $tag);
        $this->setAttribute('tags', array_values($tags));

        return $this;
    }

    /**
     * Get event metadata value
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
     * Set event metadata value
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
     * Get recurrence information
     * 
     * @return array|null Recurrence pattern
     */
    public function getRecurrencePattern(): ?array
    {
        return $this->getAttribute('recurrence_pattern');
    }

    /**
     * Get formatted event date
     * 
     * @param string $format Date format
     * @return string Formatted date
     */
    public function getFormattedDate(string $format = 'Y-m-d H:i'): string
    {
        $eventDate = $this->getAttribute('event_date');
        
        if (!$eventDate) {
            return 'No date set';
        }

        $date = is_string($eventDate) ? new \DateTime($eventDate) : $eventDate;
        return $date->format($format);
    }

    /**
     * Get time until event
     * 
     * @return string Human readable time
     */
    public function getTimeUntilEvent(): string
    {
        $eventDate = $this->getAttribute('event_date');
        
        if (!$eventDate) {
            return 'No date set';
        }

        $date = is_string($eventDate) ? new \DateTime($eventDate) : $eventDate;
        $now = new \DateTime();

        if ($date < $now) {
            return 'Event has passed';
        }

        $diff = $now->diff($date);

        if ($diff->days > 0) {
            return $diff->days . ' days';
        } elseif ($diff->h > 0) {
            return $diff->h . ' hours';
        } elseif ($diff->i > 0) {
            return $diff->i . ' minutes';
        } else {
            return 'Starting soon';
        }
    }

    /**
     * Get event status badge color
     * 
     * @return string Badge color class
     */
    public function getStatusBadgeColor(): string
    {
        switch ($this->getAttribute('status')) {
            case 'active':
                return $this->isPast() ? 'success' : 'primary';
            case 'cancelled':
                return 'danger';
            case 'completed':
                return 'success';
            case 'draft':
                return 'secondary';
            default:
                return 'secondary';
        }
    }

    /**
     * Format event for API response
     * 
     * @param bool $includeStats Include statistics
     * @return array Formatted event data
     */
    public function toApiArray(bool $includeStats = false): array
    {
        $data = [
            'id' => $this->getAttribute('event_id'),
            'name' => $this->getAttribute('name'),
            'description' => $this->getAttribute('description'),
            'date' => $this->getFormattedDate(),
            'end_date' => $this->getAttribute('end_date') ? 
                $this->getAttribute('end_date')->format('Y-m-d H:i') : null,
            'location' => $this->getAttribute('location'),
            'capacity' => $this->getAttribute('capacity'),
            'status' => $this->getAttribute('status'),
            'duration' => $this->getFormattedDuration(),
            'tags' => $this->getTags(),
            'is_upcoming' => $this->isUpcoming(),
            'is_today' => $this->isToday(),
            'registration_open' => $this->isRegistrationOpen(),
            'allows_self_checkin' => $this->getAttribute('allow_self_checkin'),
            'requires_registration' => $this->getAttribute('require_prereg'),
            'is_recurring' => $this->isRecurring(),
            'created_at' => $this->getAttribute('created_at')
        ];

        if ($includeStats) {
            // These would be populated by the service layer
            $data['statistics'] = [
                'registered_count' => 0,
                'checked_in_count' => 0,
                'attendance_rate' => 0
            ];
        }

        return $data;
    }

    /**
     * Validate event data
     * 
     * @param array $data Data to validate
     * @param bool $isUpdate Whether this is an update operation
     * @return array Validation errors
     */
    public static function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        // Name validation
        if (!$isUpdate || isset($data['name'])) {
            if (empty($data['name'])) {
                $errors['name'] = 'Event name is required';
            } elseif (strlen($data['name']) > 200) {
                $errors['name'] = 'Event name is too long';
            }
        }

        // Date validation
        if (!$isUpdate || isset($data['event_date'])) {
            if (!$isUpdate && empty($data['event_date'])) {
                $errors['event_date'] = 'Event date is required';
            } elseif (!empty($data['event_date'])) {
                if (!strtotime($data['event_date'])) {
                    $errors['event_date'] = 'Invalid event date format';
                }
            }
        }

        // End date validation
        if (isset($data['end_date']) && !empty($data['end_date'])) {
            if (!strtotime($data['end_date'])) {
                $errors['end_date'] = 'Invalid end date format';
            } elseif (isset($data['event_date']) && 
                     strtotime($data['end_date']) <= strtotime($data['event_date'])) {
                $errors['end_date'] = 'End date must be after event date';
            }
        }

        // Capacity validation
        if (isset($data['capacity']) && $data['capacity'] !== null) {
            if (!is_numeric($data['capacity']) || $data['capacity'] < 0) {
                $errors['capacity'] = 'Capacity must be a positive number';
            }
        }

        // Status validation
        if (isset($data['status'])) {
            $validStatuses = ['active', 'cancelled', 'completed', 'draft'];
            if (!in_array($data['status'], $validStatuses)) {
                $errors['status'] = 'Invalid status';
            }
        }

        // Registration deadline validation
        if (isset($data['registration_deadline']) && !empty($data['registration_deadline'])) {
            if (!strtotime($data['registration_deadline'])) {
                $errors['registration_deadline'] = 'Invalid registration deadline format';
            } elseif (isset($data['event_date']) && 
                     strtotime($data['registration_deadline']) > strtotime($data['event_date'])) {
                $errors['registration_deadline'] = 'Registration deadline must be before event date';
            }
        }

        // Location validation
        if (isset($data['location']) && strlen($data['location']) > 255) {
            $errors['location'] = 'Location is too long';
        }

        // Description validation
        if (isset($data['description']) && strlen($data['description']) > 2000) {
            $errors['description'] = 'Description is too long';
        }

        return $errors;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function setId(int $id): void
    {
        $this->id = $id;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function setName(string $name): void
    {
        $this->name = $name;
    }
    
    public function getType(): EventType
    {
        return $this->type;
    }
    
    public function setType(EventType $type): void
    {
        $this->type = $type;
    }
    
    public function getStartDate(): DateTime
    {
        return $this->startDate;
    }
    
    public function setStartDate(DateTime $startDate): void
    {
        $this->startDate = $startDate;
    }
    
    public function getEndDate(): DateTime
    {
        return $this->endDate;
    }
    
    public function setEndDate(DateTime $endDate): void
    {
        $this->endDate = $endDate;
    }
    
    public function getDescription(): ?string
    {
        return $this->description;
    }
    
    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }
    
    public function affectsAttendance(): bool
    {
        return $this->affectsAttendance;
    }
    
    public function setAffectsAttendance(bool $affects): void
    {
        $this->affectsAttendance = $affects;
    }
    
    public function getGroupIds(): array
    {
        return $this->groupIds;
    }
    
    public function setGroupIds(array $groupIds): void
    {
        $this->groupIds = array_map('intval', $groupIds);
    }
    
    public function addGroup(int $groupId): void
    {
        if (!in_array($groupId, $this->groupIds, true)) {
            $this->groupIds[] = $groupId;
        }
    }
    
    public function removeGroup(int $groupId): void
    {
        $this->groupIds = array_filter($this->groupIds, fn($id) => $id !== $groupId);
    }
    
    public function isActive(DateTime $date = null): bool
    {
        $date = $date ?? new DateTime();
        return $date >= $this->startDate && $date <= $this->endDate;
    }
    
    public function getDuration(): int
    {
        return $this->endDate->diff($this->startDate)->days + 1;
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
}

enum EventType: string
{
    case HOLIDAY = 'holiday';
    case FIELD_TRIP = 'field_trip';
    case WORKSHOP = 'workshop';
    case COMPANY_VISIT = 'company_visit';
    case EXAM = 'exam';
    case SCHOOL_EVENT = 'school_event';
    case OTHER = 'other';
}
