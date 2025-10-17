<?php
declare(strict_types=1);

namespace App\Models;

use DateTime;

class Absence
{
    private ?int $id = null;
    private int $userId;
    private DateTime $date;
    private AbsenceType $type;
    private ?string $reason = null;
    private ?string $documentation = null;
    private bool $countsTowardLimit = true;
    private AbsenceStatus $status;
    private ?int $approvedBy = null;
    private ?DateTime $approvedAt = null;
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;
    
    public function __construct()
    {
        $this->status = AbsenceStatus::PENDING;
    }
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function setId(int $id): void
    {
        $this->id = $id;
    }
    
    public function getUserId(): int
    {
        return $this->userId;
    }
    
    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }
    
    public function getDate(): DateTime
    {
        return $this->date;
    }
    
    public function setDate(DateTime $date): void
    {
        $this->date = $date;
    }
    
    public function getType(): AbsenceType
    {
        return $this->type;
    }
    
    public function setType(AbsenceType $type): void
    {
        $this->type = $type;
        
        // Automatically set if it counts toward limit based on type
        $this->countsTowardLimit = match($type) {
            AbsenceType::SICK_WITH_CERT, 
            AbsenceType::FAMILY_EMERGENCY, 
            AbsenceType::OFFICIAL_LEAVE => false,
            default => true
        };
    }
    
    public function getReason(): ?string
    {
        return $this->reason;
    }
    
    public function setReason(?string $reason): void
    {
        $this->reason = $reason;
    }
    
    public function getDocumentation(): ?string
    {
        return $this->documentation;
    }
    
    public function setDocumentation(?string $documentation): void
    {
        $this->documentation = $documentation;
    }
    
    public function countsTowardLimit(): bool
    {
        return $this->countsTowardLimit;
    }
    
    public function setCountsTowardLimit(bool $counts): void
    {
        $this->countsTowardLimit = $counts;
    }
    
    public function getStatus(): AbsenceStatus
    {
        return $this->status;
    }
    
    public function setStatus(AbsenceStatus $status): void
    {
        $this->status = $status;
    }
    
    public function getApprovedBy(): ?int
    {
        return $this->approvedBy;
    }
    
    public function setApprovedBy(?int $approvedBy): void
    {
        $this->approvedBy = $approvedBy;
    }
    
    public function getApprovedAt(): ?DateTime
    {
        return $this->approvedAt;
    }
    
    public function setApprovedAt(?DateTime $approvedAt): void
    {
        $this->approvedAt = $approvedAt;
    }
    
    public function approve(int $teacherId): void
    {
        $this->status = AbsenceStatus::APPROVED;
        $this->approvedBy = $teacherId;
        $this->approvedAt = new DateTime();
    }
    
    public function reject(int $teacherId): void
    {
        $this->status = AbsenceStatus::REJECTED;
        $this->approvedBy = $teacherId;
        $this->approvedAt = new DateTime();
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

enum AbsenceType: string
{
    case OVERSLEPT = 'overslept';
    case FORGOT = 'forgot';
    case TRANSPORTATION = 'transportation';
    case SICK_NO_CERT = 'sick_no_cert';
    case SICK_WITH_CERT = 'sick_with_cert';
    case FAMILY_EMERGENCY = 'family_emergency';
    case OFFICIAL_LEAVE = 'official_leave';
    case OTHER = 'other';
}

enum AbsenceStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
