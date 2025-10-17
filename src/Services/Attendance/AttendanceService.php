<?php
declare(strict_types=1);

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\AttendanceStatus;
use App\Models\User;
use App\Repositories\AttendanceRepository;
use App\Repositories\GroupRepository;
use App\Repositories\EventRepository;
use DateTime;

class AttendanceService
{
    public function __construct(
        private AttendanceRepository $attendanceRepo,
        private GroupRepository $groupRepo,
        private EventRepository $eventRepo,
        private AttendanceCalculator $calculator
    ) {}
    
    public function checkIn(User $user, string $location): CheckInResult
    {
        $now = new DateTime();
        
        // Check if today is a work day for user
        if (!$this->isWorkDay($user, $now)) {
            return new CheckInResult(false, 'Check-in not allowed on non-work days');
        }
        
        // Check for existing attendance record
        $attendance = $this->attendanceRepo->findByUserAndDate($user->getId(), $now);
        
        if ($attendance && $attendance->getCheckIn() !== null) {
            return new CheckInResult(false, 'Already checked in today');
        }
        
        // Create or update attendance record
        if (!$attendance) {
            $attendance = new Attendance();
            $attendance->setUserId($user->getId());
            $attendance->setDate($now);
        }
        
        $attendance->setCheckIn($now);
        $attendance->setCheckInLocation($location);
        $attendance->setStatus(AttendanceStatus::CHECKED_IN);
        
        // Check if late (after 7:30)
        $lateThreshold = new DateTime($now->format('Y-m-d') . ' 07:30:00');
        $isLate = $now > $lateThreshold;
        $attendance->setIsLate($isLate);
        
        $this->attendanceRepo->save($attendance);
        
        $message = $isLate 
            ? sprintf('Checked in at %s - You are %d minutes late', 
                $now->format('H:i'), 
                (int)(($now->getTimestamp() - $lateThreshold->getTimestamp()) / 60))
            : sprintf('Good morning %s! Checked in at %s', 
                $user->getFirstName(), 
                $now->format('H:i'));
        
        return new CheckInResult(true, $message, $attendance, $isLate);
    }
    
    public function checkOut(User $user, string $location): CheckOutResult
    {
        $now = new DateTime();
        
        // Find today's attendance record
        $attendance = $this->attendanceRepo->findByUserAndDate($user->getId(), $now);
        
        if (!$attendance || $attendance->getCheckIn() === null) {
            return new CheckOutResult(false, 'No check-in found for today');
        }
        
        if ($attendance->getCheckOut() !== null) {
            return new CheckOutResult(false, 'Already checked out today');
        }
        
        $attendance->setCheckOut($now);
        $attendance->setCheckOutLocation($location);
        $attendance->setStatus(AttendanceStatus::PRESENT);
        
        $this->attendanceRepo->save($attendance);
        
        $duration = $attendance->getWorkDuration();
        $hours = floor($duration / 3600);
        $minutes = floor(($duration % 3600) / 60);
        
        $message = sprintf('Checked out at %s - Total time: %d hours %d minutes', 
            $now->format('H:i'), 
            $hours, 
            $minutes);
        
        return new CheckOutResult(true, $message, $attendance);
    }
    
    public function manualCheckIn(User $user, DateTime $checkInTime, string $notes, int $addedBy): Attendance
    {
        $attendance = $this->attendanceRepo->findByUserAndDate($user->getId(), $checkInTime) 
            ?? new Attendance();
        
        $attendance->setUserId($user->getId());
        $attendance->setDate($checkInTime);
        $attendance->setCheckIn($checkInTime);
        $attendance->setIsManualEntry(true);
        $attendance->setNotes($notes . " [Manual entry by user {$addedBy}]");
        
        return $this->attendanceRepo->save($attendance);
    }
    
    public function getAttendanceStats(User $user, DateTime $startDate, DateTime $endDate): AttendanceStats
    {
        $records = $this->attendanceRepo->findByUserAndDateRange(
            $user->getId(), 
            $startDate, 
            $endDate
        );
        
        return $this->calculator->calculateStats($records, $user, $startDate, $endDate);
    }
    
    public function getAbsencePercentage(User $user): float
    {
        // Calculate for current semester/term
        $startDate = $this->getCurrentTermStart();
        $endDate = new DateTime();
        
        $stats = $this->getAttendanceStats($user, $startDate, $endDate);
        
        return $stats->getAbsencePercentage();
    }
    
    public function isApproaching10PercentLimit(User $user): bool
    {
        $percentage = $this->getAbsencePercentage($user);
        return $percentage >= 7.5 && $percentage < 10;
    }
    
    public function hasExceeded10PercentLimit(User $user): bool
    {
        return $this->getAbsencePercentage($user) >= 10;
    }
    
    private function isWorkDay(User $user, DateTime $date): bool
    {
        // Check if there's a holiday or special event
        $events = $this->eventRepo->findActiveByDate($date);
        foreach ($events as $event) {
            if ($event->affectsAttendance() && in_array($user->getId(), $event->getGroupIds())) {
                return false;
            }
        }
        
        // Check user's groups for work days
        $groupIds = $this->groupRepo->findIdsByUserId($user->getId());
        $groups = array_map([$this->groupRepo, 'find'], $groupIds);
        
        foreach ($groups as $group) {
            if ($group && !$group->isWorkDay($date)) {
                return false;
            }
        }
        
        return true;
    }
    
    private function getCurrentTermStart(): DateTime
    {
        // Simplified logic - should be configurable
        $now = new DateTime();
        $year = (int) $now->format('Y');
        $month = (int) $now->format('n');
        
        // Assume term starts in September or February
        if ($month >= 9) {
            return new DateTime("{$year}-09-01");
        } elseif ($month >= 2) {
            return new DateTime("{$year}-02-01");
        } else {
            return new DateTime(($year - 1) . "-09-01");
        }
    }
}

class CheckInResult
{
    public function __construct(
        private bool $success,
        private string $message,
        private ?Attendance $attendance = null,
        private bool $isLate = false
    ) {}
    
    public function isSuccess(): bool { return $this->success; }
    public function getMessage(): string { return $this->message; }
    public function getAttendance(): ?Attendance { return $this->attendance; }
    public function isLate(): bool { return $this->isLate; }
}

class CheckOutResult
{
    public function __construct(
        private bool $success,
        private string $message,
        private ?Attendance $attendance = null
    ) {}
    
    public function isSuccess(): bool { return $this->success; }
    public function getMessage(): string { return $this->message; }
    public function getAttendance(): ?Attendance { return $this->attendance; }
}
