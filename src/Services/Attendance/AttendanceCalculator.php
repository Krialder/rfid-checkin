<?php
declare(strict_types=1);

namespace App\Services\Attendance;

use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceStatus;
use DateTime;

class AttendanceCalculator
{
    public function calculateStats(array $attendanceRecords, User $user, DateTime $startDate, DateTime $endDate): AttendanceStats
    {
        $stats = new AttendanceStats();
        
        // Calculate total work days
        $workDays = $this->calculateWorkDays($user, $startDate, $endDate);
        $stats->setTotalWorkDays($workDays);
        
        // Process attendance records
        $presentDays = 0;
        $lateDays = 0;
        $absentDays = 0;
        $excusedDays = 0;
        $totalLateMinutes = 0;
        
        foreach ($attendanceRecords as $record) {
            switch ($record->getStatus()) {
                case AttendanceStatus::PRESENT:
                case AttendanceStatus::CHECKED_IN:
                    $presentDays++;
                    if ($record->isLate()) {
                        $lateDays++;
                        $totalLateMinutes += $this->calculateLateMinutes($record);
                    }
                    break;
                case AttendanceStatus::ABSENT:
                    $absentDays++;
                    break;
                case AttendanceStatus::EXCUSED:
                case AttendanceStatus::SICK:
                    $excusedDays++;
                    break;
            }
        }
        
        $stats->setPresentDays($presentDays);
        $stats->setAbsentDays($absentDays);
        $stats->setLateDays($lateDays);
        $stats->setExcusedDays($excusedDays);
        $stats->setTotalLateMinutes($totalLateMinutes);
        
        // Calculate percentages
        if ($workDays > 0) {
            $stats->setAttendanceRate(($presentDays / $workDays) * 100);
            $stats->setAbsencePercentage((($absentDays + $excusedDays) / $workDays) * 100);
        }
        
        return $stats;
    }
    
    private function calculateWorkDays(User $user, DateTime $startDate, DateTime $endDate): int
    {
        $workDays = 0;
        $current = clone $startDate;
        
        while ($current <= $endDate) {
            // Check if it's a work day (simplified - should check groups and events)
            $dayOfWeek = (int) $current->format('w');
            if ($dayOfWeek >= 1 && $dayOfWeek <= 5) { // Monday to Friday
                $workDays++;
            }
            $current->modify('+1 day');
        }
        
        return $workDays;
    }
    
    private function calculateLateMinutes(Attendance $attendance): int
    {
        if (!$attendance->getCheckIn() || !$attendance->isLate()) {
            return 0;
        }
        
        $checkIn = $attendance->getCheckIn();
        $lateThreshold = new DateTime($checkIn->format('Y-m-d') . ' 07:30:00');
        
        return (int)(($checkIn->getTimestamp() - $lateThreshold->getTimestamp()) / 60);
    }
}

class AttendanceStats
{
    private int $totalWorkDays = 0;
    private int $presentDays = 0;
    private int $absentDays = 0;
    private int $lateDays = 0;
    private int $excusedDays = 0;
    private int $totalLateMinutes = 0;
    private float $attendanceRate = 0;
    private float $absencePercentage = 0;
    
    // Getters and setters
    public function getTotalWorkDays(): int { return $this->totalWorkDays; }
    public function setTotalWorkDays(int $days): void { $this->totalWorkDays = $days; }
    
    public function getPresentDays(): int { return $this->presentDays; }
    public function setPresentDays(int $days): void { $this->presentDays = $days; }
    
    public function getAbsentDays(): int { return $this->absentDays; }
    public function setAbsentDays(int $days): void { $this->absentDays = $days; }
    
    public function getLateDays(): int { return $this->lateDays; }
    public function setLateDays(int $days): void { $this->lateDays = $days; }
    
    public function getExcusedDays(): int { return $this->excusedDays; }
    public function setExcusedDays(int $days): void { $this->excusedDays = $days; }
    
    public function getTotalLateMinutes(): int { return $this->totalLateMinutes; }
    public function setTotalLateMinutes(int $minutes): void { $this->totalLateMinutes = $minutes; }
    
    public function getAttendanceRate(): float { return $this->attendanceRate; }
    public function setAttendanceRate(float $rate): void { $this->attendanceRate = $rate; }
    
    public function getAbsencePercentage(): float { return $this->absencePercentage; }
    public function setAbsencePercentage(float $percentage): void { $this->absencePercentage = $percentage; }
}
