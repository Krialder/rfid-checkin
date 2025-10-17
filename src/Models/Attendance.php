<?php
declare(strict_types=1);

namespace App\Models;

use DateTime;

class Attendance
{
    private ?int $id = null;
    private int $userId;
    private DateTime $date;
    private ?DateTime $checkIn = null;
    private ?DateTime $checkOut = null;
    private AttendanceStatus $status;
    private ?string $checkInLocation = null;
    private ?string $checkOutLocation = null;
    private bool $isLate = false;
    private bool $isManualEntry = false;
    private ?string $notes = null;
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;
    
    public function __construct()
    {
        $this->date = new DateTime();
        $this->status = AttendanceStatus::ABSENT;
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
    
    public function getCheckIn(): ?DateTime
    {
        return $this->checkIn;
    }
    
    public function setCheckIn(?DateTime $checkIn): void
    {
        $this->checkIn = $checkIn;
        
        if ($checkIn !== null) {
            $this->updateStatus();
            $this->checkIfLate();
        }
    }
    
    public function getCheckOut(): ?DateTime
    {
        return $this->checkOut;
    }
    
    public function setCheckOut(?DateTime $checkOut): void
    {
        $this->checkOut = $checkOut;
        $this->updateStatus();
    }
    
    public function getStatus(): AttendanceStatus
    {
        return $this->status;
    }
    
    public function setStatus(AttendanceStatus $status): void
    {
        $this->status = $status;
    }
    
    public function getCheckInLocation(): ?string
    {
        return $this->checkInLocation;
    }
    
    public function setCheckInLocation(?string $location): void
    {
        $this->checkInLocation = $location;
    }
    
    public function getCheckOutLocation(): ?string
    {
        return $this->checkOutLocation;
    }
    
    public function setCheckOutLocation(?string $location): void
    {
        $this->checkOutLocation = $location;
    }
    
    public function isLate(): bool
    {
        return $this->isLate;
    }
    
    public function setIsLate(bool $isLate): void
    {
        $this->isLate = $isLate;
    }
    
    public function isManualEntry(): bool
    {
        return $this->isManualEntry;
    }
    
    public function setIsManualEntry(bool $isManualEntry): void
    {
        $this->isManualEntry = $isManualEntry;
    }
    
    public function getNotes(): ?string
    {
        return $this->notes;
    }
    
    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }
    
    public function getWorkDuration(): ?int
    {
        if ($this->checkIn === null || $this->checkOut === null) {
            return null;
        }
        
        return $this->checkOut->getTimestamp() - $this->checkIn->getTimestamp();
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
    
    private function updateStatus(): void
    {
        if ($this->checkIn !== null && $this->checkOut !== null) {
            $this->status = AttendanceStatus::PRESENT;
        } elseif ($this->checkIn !== null) {
            $this->status = AttendanceStatus::CHECKED_IN;
        }
    }
    
    private function checkIfLate(): void
    {
        if ($this->checkIn === null) {
            return;
        }
        
        $lateThreshold = new DateTime($this->checkIn->format('Y-m-d') . ' 07:30:00');
        $this->isLate = $this->checkIn > $lateThreshold;
    }
}

enum AttendanceStatus: string
{
    case PRESENT = 'present';
    case ABSENT = 'absent';
    case CHECKED_IN = 'checked_in';
    case EXCUSED = 'excused';
    case SICK = 'sick';
}
