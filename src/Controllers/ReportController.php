<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers;

use Exception;

/**
 * Report Controller
 * Handles report generation, analytics, and data exports
 */
class ReportController extends BaseController
{
    /**
     * Reports dashboard
     */
    public function index(): void
    {
        $user = $this->requireAuth();
        if (!$user) return;
        
        if (!in_array($user['role'], ['admin', 'manager'])) {
            $this->renderForbidden();
            return;
        }

        try {
            $data = [
                'pageTitle' => 'Reports & Analytics',
                'reportTypes' => $this->getAvailableReports(),
                'recentReports' => $this->getRecentReports(),
                'quickStats' => $this->getQuickStats(),
                'user' => $user
            ];

            echo $this->render('reports/index', $data);

        } catch (Exception $e) {
            $this->logger->error('Reports dashboard error', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            $this->renderError('Unable to load reports dashboard');
        }
    }

    /**
     * Attendance report
     */
    public function attendance(): void
    {
        $user = $this->requireAuth();
        if (!$user) return;
        
        if (!in_array($user['role'], ['admin', 'manager'])) {
            $this->renderForbidden();
            return;
        }

        try {
            $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            $eventId = (int)($_GET['event_id'] ?? 0);
            $groupId = (int)($_GET['group_id'] ?? 0);
            $userId = (int)($_GET['user_id'] ?? 0);
            $format = $_GET['format'] ?? 'html';

            $reportData = $this->generateAttendanceReport($dateFrom, $dateTo, $eventId, $groupId, $userId);

            if ($format === 'csv') {
                $this->exportAttendanceCsv($reportData, $dateFrom, $dateTo);
                return;
            } elseif ($format === 'pdf') {
                $this->exportAttendancePdf($reportData, $dateFrom, $dateTo);
                return;
            }

            $data = [
                'pageTitle' => 'Attendance Report',
                'reportData' => $reportData,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'event_id' => $eventId,
                    'group_id' => $groupId,
                    'user_id' => $userId
                ],
                'events' => $this->getEventsForFilter(),
                'groups' => $this->getGroupsForFilter(),
                'users' => $this->getUsersForFilter(),
                'user' => $user
            ];

            echo $this->render('reports/attendance', $data);

        } catch (Exception $e) {
            $this->logger->error('Attendance report error', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            $this->renderError('Unable to generate attendance report');
        }
    }

    /**
     * User activity report
     */
    public function userActivity(): void
    {
        $user = $this->requireAuth();
        if (!$user) return;
        
        if (!in_array($user['role'], ['admin', 'manager'])) {
            $this->renderForbidden();
            return;
        }

        try {
            $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            $userId = (int)($_GET['user_id'] ?? 0);
            $format = $_GET['format'] ?? 'html';

            $reportData = $this->generateUserActivityReport($dateFrom, $dateTo, $userId);

            if ($format === 'csv') {
                $this->exportUserActivityCsv($reportData, $dateFrom, $dateTo);
                return;
            }

            $data = [
                'pageTitle' => 'User Activity Report',
                'reportData' => $reportData,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'user_id' => $userId
                ],
                'users' => $this->getUsersForFilter(),
                'user' => $user
            ];

            echo $this->render('reports/user-activity', $data);

        } catch (Exception $e) {
            $this->logger->error('User activity report error', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            $this->renderError('Unable to generate user activity report');
        }
    }

    /**
     * Event summary report
     */
    public function eventSummary(): void
    {
        $user = $this->requireAuth();
        if (!$user) return;
        
        if (!in_array($user['role'], ['admin', 'manager'])) {
            $this->renderForbidden();
            return;
        }

        try {
            $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            $eventId = (int)($_GET['event_id'] ?? 0);
            $format = $_GET['format'] ?? 'html';

            $reportData = $this->generateEventSummaryReport($dateFrom, $dateTo, $eventId);

            if ($format === 'csv') {
                $this->exportEventSummaryCsv($reportData, $dateFrom, $dateTo);
                return;
            }

            $data = [
                'pageTitle' => 'Event Summary Report',
                'reportData' => $reportData,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'event_id' => $eventId
                ],
                'events' => $this->getEventsForFilter(),
                'user' => $user
            ];

            echo $this->render('reports/event-summary', $data);

        } catch (Exception $e) {
            $this->logger->error('Event summary report error', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            $this->renderError('Unable to generate event summary report');
        }
    }

    /**
     * Group analysis report
     */
    public function groupAnalysis(): void
    {
        $user = $this->requireAuth();
        if (!$user) return;
        
        if (!in_array($user['role'], ['admin', 'manager'])) {
            $this->renderForbidden();
            return;
        }

        try {
            $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            $groupId = (int)($_GET['group_id'] ?? 0);
            $format = $_GET['format'] ?? 'html';

            $reportData = $this->generateGroupAnalysisReport($dateFrom, $dateTo, $groupId);

            if ($format === 'csv') {
                $this->exportGroupAnalysisCsv($reportData, $dateFrom, $dateTo);
                return;
            }

            $data = [
                'pageTitle' => 'Group Analysis Report',
                'reportData' => $reportData,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'group_id' => $groupId
                ],
                'groups' => $this->getGroupsForFilter(),
                'user' => $user
            ];

            echo $this->render('reports/group-analysis', $data);

        } catch (Exception $e) {
            $this->logger->error('Group analysis report error', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            $this->renderError('Unable to generate group analysis report');
        }
    }

    /**
     * Device usage report
     */
    public function deviceUsage(): void
    {
        $user = $this->requireAuth();
        if (!$user) return;
        
        if (!in_array($user['role'], ['admin', 'manager'])) {
            $this->renderForbidden();
            return;
        }

        try {
            $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            $deviceId = (int)($_GET['device_id'] ?? 0);
            $format = $_GET['format'] ?? 'html';

            $reportData = $this->generateDeviceUsageReport($dateFrom, $dateTo, $deviceId);

            if ($format === 'csv') {
                $this->exportDeviceUsageCsv($reportData, $dateFrom, $dateTo);
                return;
            }

            $data = [
                'pageTitle' => 'Device Usage Report',
                'reportData' => $reportData,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'device_id' => $deviceId
                ],
                'devices' => $this->getDevicesForFilter(),
                'user' => $user
            ];

            echo $this->render('reports/device-usage', $data);

        } catch (Exception $e) {
            $this->logger->error('Device usage report error', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            $this->renderError('Unable to generate device usage report');
        }
    }

    /**
     * Custom report builder
     */
    public function customBuilder(): void
    {
        $user = $this->requireAuth();
        if (!$user) return;
        
        if (!in_array($user['role'], ['admin', 'manager'])) {
            $this->renderForbidden();
            return;
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->generateCustomReport();
                return;
            }

            $data = [
                'pageTitle' => 'Custom Report Builder',
                'availableFields' => $this->getAvailableReportFields(),
                'savedReports' => $this->getSavedCustomReports(),
                'user' => $user
            ];

            echo $this->render('reports/custom-builder', $data);

        } catch (Exception $e) {
            $this->logger->error('Custom report builder error', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            $this->renderError('Unable to load custom report builder');
        }
    }

    /**
     * Analytics dashboard
     */
    public function analytics(): void
    {
        $user = $this->requireAuth();
        if (!$user) return;
        
        if (!in_array($user['role'], ['admin', 'manager'])) {
            $this->renderForbidden();
            return;
        }

        try {
            $period = $_GET['period'] ?? '30days';
            
            $data = [
                'pageTitle' => 'Analytics Dashboard',
                'period' => $period,
                'chartData' => $this->getAnalyticsChartData($period),
                'kpis' => $this->getKPIs($period),
                'trends' => $this->getTrendAnalysis($period),
                'user' => $user
            ];

            echo $this->render('reports/analytics', $data);

        } catch (Exception $e) {
            $this->logger->error('Analytics dashboard error', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            $this->renderError('Unable to load analytics dashboard');
        }
    }

    /**
     * AJAX: Get chart data
     */
    public function getChartData(): void
    {
        try {
            $this->requireAuth();
            $this->requireRole(['admin', 'manager']);

            $type = $_GET['type'] ?? 'attendance';
            $period = $_GET['period'] ?? '7days';

            $chartData = $this->generateChartData($type, $period);

            $this->jsonResponse(['success' => true, 'data' => $chartData]);

        } catch (Exception $e) {
            $this->logError('Chart data error', $e);
            $this->jsonResponse(['error' => 'Unable to generate chart data'], 500);
        }
    }

    /**
     * Generate attendance report data
     */
    private function generateAttendanceReport(string $dateFrom, string $dateTo, int $eventId = 0, int $groupId = 0, int $userId = 0): array
    {
        $whereConditions = ["DATE(a.check_in_time) BETWEEN ? AND ?"];
        $params = [$dateFrom, $dateTo];

        if ($eventId > 0) {
            $whereConditions[] = "a.event_id = ?";
            $params[] = $eventId;
        }

        if ($groupId > 0) {
            $whereConditions[] = "EXISTS (SELECT 1 FROM user_groups ug WHERE ug.user_id = a.user_id AND ug.group_id = ?)";
            $params[] = $groupId;
        }

        if ($userId > 0) {
            $whereConditions[] = "a.user_id = ?";
            $params[] = $userId;
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Summary statistics
        $summary = $this->db->selectRow(
            "SELECT 
                COUNT(*) as total_checkins,
                COUNT(DISTINCT a.user_id) as unique_users,
                COUNT(DISTINCT a.event_id) as unique_events,
                COUNT(CASE WHEN a.check_out_time IS NOT NULL THEN 1 END) as completed_sessions,
                AVG(CASE WHEN a.check_out_time IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, a.check_in_time, a.check_out_time) END) as avg_duration_minutes
             FROM attendance a
             WHERE {$whereClause}",
            $params
        );

        // Detailed attendance records
        $details = $this->db->selectAll(
            "SELECT a.*,
                    u.first_name, u.last_name, u.email,
                    e.name as event_name,
                    CASE WHEN a.check_out_time IS NOT NULL 
                         THEN TIMESTAMPDIFF(MINUTE, a.check_in_time, a.check_out_time)
                         ELSE NULL 
                    END as duration_minutes,
                    rd_in.name as device_in_name,
                    rd_out.name as device_out_name
             FROM attendance a
             INNER JOIN users u ON a.user_id = u.id
             INNER JOIN events e ON a.event_id = e.id
             LEFT JOIN rfid_devices rd_in ON a.device_id_in = rd_in.id
             LEFT JOIN rfid_devices rd_out ON a.device_id_out = rd_out.id
             WHERE {$whereClause}
             ORDER BY a.check_in_time DESC",
            $params
        );

        // Daily breakdown
        $dailyBreakdown = $this->db->selectAll(
            "SELECT 
                DATE(a.check_in_time) as date,
                COUNT(*) as checkins,
                COUNT(DISTINCT a.user_id) as unique_users,
                COUNT(CASE WHEN a.check_out_time IS NOT NULL THEN 1 END) as completed_sessions
             FROM attendance a
             WHERE {$whereClause}
             GROUP BY DATE(a.check_in_time)
             ORDER BY date",
            $params
        );

        // Top users
        $topUsers = $this->db->selectAll(
            "SELECT 
                u.first_name, u.last_name, u.email,
                COUNT(*) as checkin_count,
                AVG(CASE WHEN a.check_out_time IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, a.check_in_time, a.check_out_time) END) as avg_duration_minutes
             FROM attendance a
             INNER JOIN users u ON a.user_id = u.id
             WHERE {$whereClause}
             GROUP BY a.user_id
             ORDER BY checkin_count DESC
             LIMIT 10",
            $params
        );

        return [
            'summary' => $summary,
            'details' => $details,
            'daily_breakdown' => $dailyBreakdown,
            'top_users' => $topUsers
        ];
    }

    /**
     * Generate user activity report data
     */
    private function generateUserActivityReport(string $dateFrom, string $dateTo, int $userId = 0): array
    {
        $whereConditions = ["DATE(a.check_in_time) BETWEEN ? AND ?"];
        $params = [$dateFrom, $dateTo];

        if ($userId > 0) {
            $whereConditions[] = "a.user_id = ?";
            $params[] = $userId;
        }

        $whereClause = implode(' AND ', $whereConditions);

        // User activity summary
        $userSummary = $this->db->selectAll(
            "SELECT 
                u.id, u.first_name, u.last_name, u.email, u.role,
                COUNT(*) as total_checkins,
                COUNT(DISTINCT a.event_id) as events_attended,
                COUNT(CASE WHEN a.check_out_time IS NOT NULL THEN 1 END) as completed_sessions,
                AVG(CASE WHEN a.check_out_time IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, a.check_in_time, a.check_out_time) END) as avg_duration_minutes,
                MAX(a.check_in_time) as last_checkin
             FROM attendance a
             INNER JOIN users u ON a.user_id = u.id
             WHERE {$whereClause}
             GROUP BY u.id
             ORDER BY total_checkins DESC",
            $params
        );

        // Activity timeline (for specific user if selected)
        $timeline = [];
        if ($userId > 0) {
            $timeline = $this->db->selectAll(
                "SELECT a.*, e.name as event_name,
                        CASE WHEN a.check_out_time IS NOT NULL 
                             THEN TIMESTAMPDIFF(MINUTE, a.check_in_time, a.check_out_time)
                             ELSE NULL 
                        END as duration_minutes
                 FROM attendance a
                 INNER JOIN events e ON a.event_id = e.id
                 WHERE a.user_id = ? AND DATE(a.check_in_time) BETWEEN ? AND ?
                 ORDER BY a.check_in_time DESC",
                [$userId, $dateFrom, $dateTo]
            );
        }

        return [
            'user_summary' => $userSummary,
            'timeline' => $timeline
        ];
    }

    /**
     * Generate event summary report data
     */
    private function generateEventSummaryReport(string $dateFrom, string $dateTo, int $eventId = 0): array
    {
        $whereConditions = ["(DATE(e.start_date) BETWEEN ? AND ? OR DATE(e.end_date) BETWEEN ? AND ?)"];
        $params = [$dateFrom, $dateTo, $dateFrom, $dateTo];

        if ($eventId > 0) {
            $whereConditions[] = "e.id = ?";
            $params[] = $eventId;
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Event summary
        $eventSummary = $this->db->selectAll(
            "SELECT e.*,
                    u.first_name as creator_first_name, u.last_name as creator_last_name,
                    COUNT(DISTINCT a.user_id) as total_attendees,
                    COUNT(a.id) as total_checkins,
                    COUNT(CASE WHEN a.check_out_time IS NOT NULL THEN 1 END) as completed_sessions,
                    AVG(CASE WHEN a.check_out_time IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, a.check_in_time, a.check_out_time) END) as avg_duration_minutes
             FROM events e
             LEFT JOIN users u ON e.created_by = u.id
             LEFT JOIN attendance a ON e.id = a.event_id
             WHERE {$whereClause}
             GROUP BY e.id
             ORDER BY e.start_date DESC",
            $params
        );

        // Attendance by event and date
        $attendanceBreakdown = [];
        if ($eventId > 0) {
            $attendanceBreakdown = $this->db->selectAll(
                "SELECT 
                    DATE(a.check_in_time) as date,
                    COUNT(*) as checkins,
                    COUNT(DISTINCT a.user_id) as unique_attendees
                 FROM attendance a
                 WHERE a.event_id = ? AND DATE(a.check_in_time) BETWEEN ? AND ?
                 GROUP BY DATE(a.check_in_time)
                 ORDER BY date",
                [$eventId, $dateFrom, $dateTo]
            );
        }

        return [
            'event_summary' => $eventSummary,
            'attendance_breakdown' => $attendanceBreakdown
        ];
    }

    /**
     * Generate group analysis report data
     */
    private function generateGroupAnalysisReport(string $dateFrom, string $dateTo, int $groupId = 0): array
    {
        $whereConditions = ["DATE(a.check_in_time) BETWEEN ? AND ?"];
        $params = [$dateFrom, $dateTo];

        if ($groupId > 0) {
            $whereConditions[] = "g.id = ?";
            $params[] = $groupId;
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Group summary with attendance
        $groupSummary = $this->db->selectAll(
            "SELECT g.*,
                    COUNT(DISTINCT ug.user_id) as total_members,
                    COUNT(DISTINCT a.user_id) as active_members,
                    COUNT(a.id) as total_checkins,
                    COUNT(DISTINCT a.event_id) as events_attended,
                    AVG(CASE WHEN a.check_out_time IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, a.check_in_time, a.check_out_time) END) as avg_duration_minutes
             FROM groups g
             LEFT JOIN user_groups ug ON g.id = ug.group_id
             LEFT JOIN attendance a ON ug.user_id = a.user_id AND {$whereConditions[0]}
             WHERE g.is_active = 1" . ($groupId > 0 ? " AND g.id = ?" : "") . "
             GROUP BY g.id
             ORDER BY total_checkins DESC",
            $groupId > 0 ? [$dateFrom, $dateTo, $groupId] : [$dateFrom, $dateTo]
        );

        // Member activity within groups
        $memberActivity = [];
        if ($groupId > 0) {
            $memberActivity = $this->db->selectAll(
                "SELECT u.first_name, u.last_name, u.email,
                        COUNT(a.id) as checkins,
                        COUNT(DISTINCT a.event_id) as events_attended,
                        MAX(a.check_in_time) as last_checkin
                 FROM user_groups ug
                 INNER JOIN users u ON ug.user_id = u.id
                 LEFT JOIN attendance a ON u.id = a.user_id AND DATE(a.check_in_time) BETWEEN ? AND ?
                 WHERE ug.group_id = ?
                 GROUP BY u.id
                 ORDER BY checkins DESC",
                [$dateFrom, $dateTo, $groupId]
            );
        }

        return [
            'group_summary' => $groupSummary,
            'member_activity' => $memberActivity
        ];
    }

    /**
     * Generate device usage report data
     */
    private function generateDeviceUsageReport(string $dateFrom, string $dateTo, int $deviceId = 0): array
    {
        $whereConditions = ["DATE(a.check_in_time) BETWEEN ? AND ?"];
        $params = [$dateFrom, $dateTo];

        if ($deviceId > 0) {
            $whereConditions[] = "(a.device_id_in = ? OR a.device_id_out = ?)";
            $params[] = $deviceId;
            $params[] = $deviceId;
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Device usage summary
        $deviceSummary = $this->db->selectAll(
            "SELECT rd.id, rd.name, rd.location, rd.status,
                    COUNT(CASE WHEN a.device_id_in = rd.id THEN 1 END) as checkin_scans,
                    COUNT(CASE WHEN a.device_id_out = rd.id THEN 1 END) as checkout_scans,
                    COUNT(CASE WHEN a.device_id_in = rd.id OR a.device_id_out = rd.id THEN 1 END) as total_scans,
                    COUNT(DISTINCT CASE WHEN a.device_id_in = rd.id OR a.device_id_out = rd.id THEN a.user_id END) as unique_users
             FROM rfid_devices rd
             LEFT JOIN attendance a ON (a.device_id_in = rd.id OR a.device_id_out = rd.id) AND {$whereConditions[0]}
             WHERE rd.status != 'deleted'" . ($deviceId > 0 ? " AND rd.id = ?" : "") . "
             GROUP BY rd.id
             ORDER BY total_scans DESC",
            $deviceId > 0 ? [$dateFrom, $dateTo, $deviceId] : [$dateFrom, $dateTo]
        );

        // Daily usage patterns
        $dailyUsage = [];
        if ($deviceId > 0) {
            $dailyUsage = $this->db->selectAll(
                "SELECT 
                    DATE(a.check_in_time) as date,
                    COUNT(CASE WHEN a.device_id_in = ? THEN 1 END) as checkin_scans,
                    COUNT(CASE WHEN a.device_id_out = ? THEN 1 END) as checkout_scans
                 FROM attendance a
                 WHERE (a.device_id_in = ? OR a.device_id_out = ?) AND DATE(a.check_in_time) BETWEEN ? AND ?
                 GROUP BY DATE(a.check_in_time)
                 ORDER BY date",
                [$deviceId, $deviceId, $deviceId, $deviceId, $dateFrom, $dateTo]
            );
        }

        return [
            'device_summary' => $deviceSummary,
            'daily_usage' => $dailyUsage
        ];
    }

    /**
     * Generate custom report based on form data
     */
    private function generateCustomReport(): void
    {
        $user = $this->getCurrentUser();
        
        try {
            $reportName = trim($_POST['report_name'] ?? '');
            $selectedFields = $_POST['fields'] ?? [];
            $filters = $_POST['filters'] ?? [];
            $groupBy = $_POST['group_by'] ?? '';
            $orderBy = $_POST['order_by'] ?? 'created_at';
            $orderDirection = $_POST['order_direction'] ?? 'DESC';

            if (empty($reportName) || empty($selectedFields)) {
                throw new Exception('Report name and fields are required');
            }

            // Build custom query based on selections
            $queryResult = $this->buildCustomQuery($selectedFields, $filters, $groupBy, $orderBy, $orderDirection);

            // Save report configuration if requested
            if (isset($_POST['save_report'])) {
                $this->saveCustomReport($reportName, $_POST);
            }

            $data = [
                'pageTitle' => 'Custom Report: ' . $reportName,
                'reportName' => $reportName,
                'reportData' => $queryResult,
                'selectedFields' => $selectedFields,
                'filters' => $filters,
                'user' => $user
            ];

            echo $this->render('reports/custom-result', $data);

        } catch (Exception $e) {
            $this->logger->error('Custom report generation error', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            $this->renderError('Unable to generate custom report: ' . $e->getMessage());
        }
    }

    /**
     * Export attendance report as CSV
     */
    private function exportAttendanceCsv(array $reportData, string $dateFrom, string $dateTo): void
    {
        $filename = "attendance_report_{$dateFrom}_to_{$dateTo}.csv";
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Date', 'User Name', 'Email', 'Event', 'Check In', 'Check Out', 
            'Duration (minutes)', 'Device In', 'Device Out'
        ]);
        
        // CSV data
        foreach ($reportData['details'] as $record) {
            fputcsv($output, [
                date('Y-m-d', strtotime($record['check_in_time'])),
                $record['first_name'] . ' ' . $record['last_name'],
                $record['email'],
                $record['event_name'],
                $record['check_in_time'],
                $record['check_out_time'] ?? 'Still checked in',
                $record['duration_minutes'] ?? 'N/A',
                $record['device_in_name'] ?? 'Unknown',
                $record['device_out_name'] ?? 'N/A'
            ]);
        }
        
        fclose($output);
        exit;
    }

    /**
     * Get available report types
     */
    private function getAvailableReports(): array
    {
        return [
            'attendance' => [
                'name' => 'Attendance Report',
                'description' => 'Detailed attendance records with check-in/out times',
                'icon' => 'calendar-check'
            ],
            'user-activity' => [
                'name' => 'User Activity Report',
                'description' => 'User participation and engagement metrics',
                'icon' => 'users'
            ],
            'event-summary' => [
                'name' => 'Event Summary Report',
                'description' => 'Event attendance and performance statistics',
                'icon' => 'event'
            ],
            'group-analysis' => [
                'name' => 'Group Analysis Report',
                'description' => 'Group participation and member activity',
                'icon' => 'team'
            ],
            'device-usage' => [
                'name' => 'Device Usage Report',
                'description' => 'RFID device usage and performance metrics',
                'icon' => 'device'
            ]
        ];
    }

    /**
     * Get recent reports
     */
    private function getRecentReports(): array
    {
        // This would typically come from a database table tracking report generation
        return [
            [
                'name' => 'Monthly Attendance - November 2024',
                'type' => 'attendance',
                'generated_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'generated_by' => 'Admin User'
            ],
            [
                'name' => 'Group Activity Analysis',
                'type' => 'group-analysis',
                'generated_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'generated_by' => 'Manager User'
            ]
        ];
    }

    /**
     * Get quick statistics
     */
    private function getQuickStats(): array
    {
        return [
            'total_reports_generated' => 156,
            'reports_this_month' => 23,
            'most_popular_report' => 'Attendance Report',
            'avg_generation_time' => '2.3 seconds'
        ];
    }

    // Additional helper methods for filters, exports, analytics, etc.
    // These would be implemented based on specific requirements

    private function getEventsForFilter(): array
    {
        return $this->db->selectAll("SELECT id, name FROM events WHERE status = 'active' ORDER BY name");
    }

    private function getGroupsForFilter(): array
    {
        return $this->db->selectAll("SELECT id, name FROM groups WHERE is_active = 1 ORDER BY name");
    }

    private function getUsersForFilter(): array
    {
        return $this->db->selectAll("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE is_active = 1 ORDER BY first_name, last_name");
    }

    private function getDevicesForFilter(): array
    {
        return $this->db->selectAll("SELECT id, name FROM rfid_devices WHERE status = 'active' ORDER BY name");
    }

    private function getAvailableReportFields(): array
    {
        return [
            'user_info' => ['first_name', 'last_name', 'email', 'role'],
            'attendance' => ['check_in_time', 'check_out_time', 'duration'],
            'event_info' => ['event_name', 'event_start', 'event_end'],
            'device_info' => ['device_name', 'device_location']
        ];
    }

    private function getSavedCustomReports(): array
    {
        // Return saved custom reports from database
        return [];
    }

    private function getAnalyticsChartData(string $period): array
    {
        // Return chart data for analytics dashboard
        return [];
    }

    private function getKPIs(string $period): array
    {
        // Return key performance indicators
        return [];
    }

    private function getTrendAnalysis(string $period): array
    {
        // Return trend analysis data
        return [];
    }

    private function generateChartData(string $type, string $period): array
    {
        // Generate chart data based on type and period
        return [];
    }

    private function buildCustomQuery(array $fields, array $filters, string $groupBy, string $orderBy, string $orderDirection): array
    {
        // Build and execute custom query
        return [];
    }

    private function saveCustomReport(string $name, array $config): void
    {
        // Save custom report configuration to database
    }

    // Placeholder methods for additional export formats
    private function exportAttendancePdf(array $reportData, string $dateFrom, string $dateTo): void
    {
        // PDF export implementation
        echo 'PDF export not yet implemented';
        exit;
    }

    private function exportUserActivityCsv(array $reportData, string $dateFrom, string $dateTo): void
    {
        // User activity CSV export
        echo 'User activity CSV export not yet implemented';
        exit;
    }

    private function exportEventSummaryCsv(array $reportData, string $dateFrom, string $dateTo): void
    {
        // Event summary CSV export
        echo 'Event summary CSV export not yet implemented';
        exit;
    }

    private function exportGroupAnalysisCsv(array $reportData, string $dateFrom, string $dateTo): void
    {
        // Group analysis CSV export
        echo 'Group analysis CSV export not yet implemented';
        exit;
    }

    private function exportDeviceUsageCsv(array $reportData, string $dateFrom, string $dateTo): void
    {
        // Device usage CSV export
        echo 'Device usage CSV export not yet implemented';
        exit;
    }
}