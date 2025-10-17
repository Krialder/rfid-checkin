<?php
declare(strict_types=1);

namespace App\Models;

use DateTime;

class Group
{
    private ?int $id = null;
    private string $name;
    private GroupType $type;
    private ?string $description = null;
    private array $workDays = [];
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;
    
    public function __construct()
    {
        // Default work days (Monday to Friday)
        $this->workDays = [1, 2, 3, 4, 5];
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
    
    public function getType(): GroupType
    {
        return $this->type;
    }
    
    public function setType(GroupType $type): void
    {
        $this->type = $type;
    }
    
    public function getDescription(): ?string
    {
        return $this->description;
    }
    
    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }
    
    public function getWorkDays(): array
    {
        return $this->workDays;
    }
    
    public function setWorkDays(array $workDays): void
    {
        // Ensure days are integers between 0-6
        $this->workDays = array_filter($workDays, fn($day) => is_int($day) && $day >= 0 && $day <= 6);
    }
    
    public function isWorkDay(DateTime $date): bool
    {
        $dayOfWeek = (int) $date->format('w');
        return in_array($dayOfWeek, $this->workDays, true);
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

enum GroupType: string
{
    case YEAR = 'year';
    case SPECIALIZATION = 'specialization';
    case CUSTOM = 'custom';
}
