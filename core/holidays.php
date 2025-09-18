<?php
/**
 * Holiday Management System
 * 
 * Comprehensive holiday calculation and management including:
 * - National holidays (same across all states)
 * - Regional holidays (specific to states/regions)
 * - Easter-based calculations
 * - Holiday conflict detection for events
 * 
 * @author Senior Developer
 * @version 1.0 - Holiday Management
 */

require_once __DIR__ . '/database.php';

class HolidayManager {
    
    private $db;
    
    // State codes mapping
    private const STATES = [
        'BW' => 'Baden-Württemberg',
        'BY' => 'Bayern (Bavaria)',
        'BE' => 'Berlin',
        'BB' => 'Brandenburg',
        'HB' => 'Bremen',
        'HH' => 'Hamburg',
        'HE' => 'Hessen (Hesse)',
        'MV' => 'Mecklenburg-Vorpommern',
        'NI' => 'Niedersachsen (Lower Saxony)',
        'NW' => 'Nordrhein-Westfalen (North Rhine-Westphalia)',
        'RP' => 'Rheinland-Pfalz (Rhineland-Palatinate)',
        'SL' => 'Saarland',
        'SN' => 'Sachsen (Saxony)',
        'ST' => 'Sachsen-Anhalt (Saxony-Anhalt)',
        'SH' => 'Schleswig-Holstein',
        'TH' => 'Thüringen (Thuringia)'
    ];
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Generate and insert holidays for a specific year
     */
    public function generateHolidaysForYear($year) {
        $this->db->beginTransaction();
        
        try {
            // Remove existing holidays for this year
            $stmt = $this->db->prepare("DELETE FROM holidays WHERE year = ?");
            $stmt->execute([$year]);
            
            $holidays = $this->calculateHolidays($year);
            
            $sql = "INSERT INTO holidays (name, date, year, type, state_codes, description) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            
            foreach ($holidays as $holiday) {
                $stmt->execute([
                    $holiday['name'],
                    $holiday['date'],
                    $year,
                    $holiday['type'],
                    $holiday['state_codes'] ? json_encode($holiday['state_codes']) : null,
                    $holiday['description']
                ]);
            }
            
            $this->db->commit();
            return count($holidays);
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Calculate all holidays for a given year
     */
    private function calculateHolidays($year) {
        $holidays = [];
        
        // Calculate Easter Sunday first (basis for many other holidays)
        $easter = $this->calculateEaster($year);
        
        // Fixed national holidays
        $holidays = array_merge($holidays, [
            [
                'name' => 'Neujahr',
                'date' => "$year-01-01",
                'type' => 'national',
                'state_codes' => null,
                'description' => 'New Year\'s Day'
            ],
            [
                'name' => 'Tag der Arbeit',
                'date' => "$year-05-01",
                'type' => 'national',
                'state_codes' => null,
                'description' => 'Labour Day / May Day'
            ],
            [
                'name' => 'Tag der Deutschen Einheit',
                'date' => "$year-10-03",
                'type' => 'national',
                'state_codes' => null,
                'description' => 'German Unity Day'
            ],
            [
                'name' => '1. Weihnachtstag',
                'date' => "$year-12-25",
                'type' => 'national',
                'state_codes' => null,
                'description' => 'Christmas Day'
            ],
            [
                'name' => '2. Weihnachtstag',
                'date' => "$year-12-26",
                'type' => 'national',
                'state_codes' => null,
                'description' => 'Boxing Day'
            ]
        ]);
        
        // Easter-based national holidays
        $holidays = array_merge($holidays, [
            [
                'name' => 'Karfreitag',
                'date' => date('Y-m-d', strtotime('-2 days', $easter)),
                'type' => 'national',
                'state_codes' => null,
                'description' => 'Good Friday'
            ],
            [
                'name' => 'Ostermontag',
                'date' => date('Y-m-d', strtotime('+1 day', $easter)),
                'type' => 'national',
                'state_codes' => null,
                'description' => 'Easter Monday'
            ],
            [
                'name' => 'Christi Himmelfahrt',
                'date' => date('Y-m-d', strtotime('+39 days', $easter)),
                'type' => 'national',
                'state_codes' => null,
                'description' => 'Ascension Day'
            ],
            [
                'name' => 'Pfingstmontag',
                'date' => date('Y-m-d', strtotime('+50 days', $easter)),
                'type' => 'national',
                'state_codes' => null,
                'description' => 'Whit Monday'
            ]
        ]);
        
        // Regional holidays - fixed dates
        $holidays = array_merge($holidays, [
            [
                'name' => 'Heilige Drei Könige',
                'date' => "$year-01-06",
                'type' => 'regional',
                'state_codes' => ['BW', 'BY', 'ST'],
                'description' => 'Epiphany'
            ],
            [
                'name' => 'Internationaler Frauentag',
                'date' => "$year-03-08",
                'type' => 'regional',
                'state_codes' => ['BE'],
                'description' => 'International Women\'s Day (Berlin only)'
            ],
            [
                'name' => 'Mariä Himmelfahrt',
                'date' => "$year-08-15",
                'type' => 'regional',
                'state_codes' => ['BY', 'SL'],
                'description' => 'Assumption of Mary'
            ],
            [
                'name' => 'Weltkindertag',
                'date' => "$year-09-20",
                'type' => 'regional',
                'state_codes' => ['TH'],
                'description' => 'World Children\'s Day (Thuringia only)'
            ],
            [
                'name' => 'Reformationstag',
                'date' => "$year-10-31",
                'type' => 'regional',
                'state_codes' => ['BB', 'MV', 'SN', 'ST', 'TH'],
                'description' => 'Reformation Day'
            ],
            [
                'name' => 'Allerheiligen',
                'date' => "$year-11-01",
                'type' => 'regional',
                'state_codes' => ['BW', 'BY', 'NW', 'RP', 'SL'],
                'description' => 'All Saints\' Day'
            ]
        ]);
        
        // Regional Easter-based holidays
        $holidays = array_merge($holidays, [
            [
                'name' => 'Fronleichnam',
                'date' => date('Y-m-d', strtotime('+60 days', $easter)),
                'type' => 'regional',
                'state_codes' => ['BW', 'BY', 'HE', 'NW', 'RP', 'SL'],
                'description' => 'Corpus Christi'
            ]
        ]);
        
        // Special regional holidays
        $holidays = array_merge($holidays, [
            [
                'name' => 'Buß- und Bettag',
                'date' => $this->calculateBussUndBettag($year),
                'type' => 'regional',
                'state_codes' => ['SN'],
                'description' => 'Day of Repentance and Prayer (Saxony only)'
            ]
        ]);
        
        // Sort holidays by date
        usort($holidays, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        
        return $holidays;
    }
    
    /**
     * Calculate Easter Sunday for a given year using the Gregorian calendar
     */
    private function calculateEaster($year) {
        // Using the algorithm for Gregorian calendar
        $a = $year % 19;
        $b = intval($year / 100);
        $c = $year % 100;
        $d = intval($b / 4);
        $e = $b % 4;
        $f = intval(($b + 8) / 25);
        $g = intval(($b - $f + 1) / 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intval($c / 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intval(($a + 11 * $h + 22 * $l) / 451);
        $n = intval(($h + $l - 7 * $m + 114) / 31);
        $p = ($h + $l - 7 * $m + 114) % 31;
        
        return mktime(0, 0, 0, $n, $p + 1, $year);
    }
    
    /**
     * Calculate Buß- und Bettag (Day of Repentance and Prayer)
     * Falls on the Wednesday before the 23rd of November
     */
    private function calculateBussUndBettag($year) {
        $date = new DateTime("$year-11-23");
        
        // Find the Wednesday before November 23
        while ($date->format('N') != 3) { // 3 = Wednesday
            $date->modify('-1 day');
        }
        
        return $date->format('Y-m-d');
    }
    
    /**
     * Check if a specific date is a holiday
     */
    public function isHoliday($date, $stateCode = null) {
        $where = ['date = ?', 'is_active = 1'];
        $params = [$date];
        
        if ($stateCode) {
            $where[] = "(state_codes IS NULL OR JSON_CONTAINS(state_codes, ?))";
            $params[] = json_encode($stateCode);
        }
        
        $whereClause = implode(' AND ', $where);
        
        $stmt = $this->db->prepare("
            SELECT name, type, state_codes, description
            FROM holidays 
            WHERE $whereClause
        ");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all holidays for a specific year
     */
    public function getHolidaysForYear($year, $stateCode = null) {
        $where = ['year = ?', 'is_active = 1'];
        $params = [$year];
        
        if ($stateCode) {
            $where[] = "(state_codes IS NULL OR JSON_CONTAINS(state_codes, ?))";
            $params[] = json_encode($stateCode);
        }
        
        $whereClause = implode(' AND ', $where);
        
        $stmt = $this->db->prepare("
            SELECT * FROM holidays 
            WHERE $whereClause
            ORDER BY date
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get holidays in a date range
     */
    public function getHolidaysInRange($startDate, $endDate, $stateCode = null) {
        $where = ['date BETWEEN ? AND ?', 'is_active = 1'];
        $params = [$startDate, $endDate];
        
        if ($stateCode) {
            $where[] = "(state_codes IS NULL OR JSON_CONTAINS(state_codes, ?))";
            $params[] = json_encode($stateCode);
        }
        
        $whereClause = implode(' AND ', $where);
        
        $stmt = $this->db->prepare("
            SELECT * FROM holidays 
            WHERE $whereClause
            ORDER BY date
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Add custom holiday
     */
    public function addCustomHoliday($name, $date, $description = '', $stateCode = null) {
        $year = date('Y', strtotime($date));
        
        $sql = "INSERT INTO holidays (name, date, year, type, state_codes, description) 
                VALUES (?, ?, ?, 'custom', ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $name,
            $date,
            $year,
            $stateCode ? json_encode([$stateCode]) : null,
            $description
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Update holiday status
     */
    public function updateHolidayStatus($holidayId, $isActive) {
        $stmt = $this->db->prepare("UPDATE holidays SET is_active = ? WHERE holiday_id = ?");
        $stmt->execute([$isActive, $holidayId]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Delete custom holiday
     */
    public function deleteCustomHoliday($holidayId) {
        $stmt = $this->db->prepare("DELETE FROM holidays WHERE holiday_id = ? AND type = 'custom'");
        $stmt->execute([$holidayId]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get state names
     */
    public static function getStates() {
        return self::STATES;
    }
    
    /**
     * Auto-generate holidays for multiple years
     */
    public function generateHolidaysForYearRange($startYear, $endYear) {
        $totalGenerated = 0;
        
        for ($year = $startYear; $year <= $endYear; $year++) {
            $count = $this->generateHolidaysForYear($year);
            $totalGenerated += $count;
        }
        
        return $totalGenerated;
    }
    
    /**
     * Check for upcoming holidays (useful for event planning)
     */
    public function getUpcomingHolidays($days = 30, $stateCode = null) {
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime("+{$days} days"));
        
        return $this->getHolidaysInRange($startDate, $endDate, $stateCode);
    }
    
    /**
     * Generate holiday statistics
     */
    public function getHolidayStats($year) {
        $stmt = $this->db->prepare("
            SELECT 
                type,
                COUNT(*) as count,
                GROUP_CONCAT(name ORDER BY date SEPARATOR ', ') as holidays
            FROM holidays 
            WHERE year = ? AND is_active = 1
            GROUP BY type
        ");
        $stmt->execute([$year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
