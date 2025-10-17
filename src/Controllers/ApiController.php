<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers;

use Exception;

/**
 * API Controller
 * Handles RESTful API endpoints for external integrations and AJAX requests
 */
class ApiController extends BaseController
{
    /**
     * API authentication check
     */
    private function requireApiAuth(): array
    {
        // Check for API key in headers
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
        
        if (!$apiKey) {
            http_response_code(401);
            $this->jsonResponse(['error' => 'API key required']);
            exit;
        }

        // Validate API key (in real implementation, check against database)
        $user = $this->validateApiKey($apiKey);
        if (!$user) {
            http_response_code(401);
            $this->jsonResponse(['error' => 'Invalid API key']);
            exit;
        }

        return $user;
    }

    /**
     * RFID scan endpoint - primary endpoint for RFID devices
     */
    public function rfidScan(): void
    {
        try {
            $user = $this->requireApiAuth();

            // Get scan data
            $rfidCard = trim($_POST['rfid_card'] ?? '');
            $deviceId = (int)($_POST['device_id'] ?? 0);
            $scanTime = $_POST['scan_time'] ?? date('Y-m-d H:i:s');

            if (empty($rfidCard)) {
                $this->jsonResponse(['error' => 'RFID card required'], 400);
                return;
            }

            if ($deviceId <= 0) {
                $this->jsonResponse(['error' => 'Device ID required'], 400);
                return;
            }

            // Find user by RFID card
            $cardUser = $this->db->selectRow(
                "SELECT u.*, urc.card_number 
                 FROM users u
                 INNER JOIN user_rfid_cards urc ON u.id = urc.user_id
                 WHERE urc.card_number = ? AND u.is_active = 1",
                [$rfidCard]
            );

            if (!$cardUser) {
                $this->logWarning('Unknown RFID card scanned', [
                    'card' => $rfidCard,
                    'device_id' => $deviceId
                ]);
                
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Unknown RFID card',
                    'card' => $rfidCard
                ], 404);
                return;
            }

            // Update device heartbeat
            $this->updateDeviceHeartbeat($deviceId);

            // Find active event for this time
            $activeEvent = $this->findActiveEvent();

            if (!$activeEvent) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'No active event',
                    'user' => [
                        'name' => $cardUser['first_name'] . ' ' . $cardUser['last_name'],
                        'id' => $cardUser['id']
                    ]
                ]);
                return;
            }

            // Check if user is in assigned groups for this event
            $isAssigned = $this->isUserAssignedToEvent($cardUser['id'], $activeEvent['id']);
            
            if (!$isAssigned) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'User not assigned to current event',
                    'user' => [
                        'name' => $cardUser['first_name'] . ' ' . $cardUser['last_name'],
                        'id' => $cardUser['id']
                    ],
                    'event' => $activeEvent['name']
                ]);
                return;
            }

            // Check for existing attendance today
            $existingAttendance = $this->db->selectRow(
                "SELECT * FROM attendance 
                 WHERE user_id = ? AND event_id = ? AND DATE(check_in_time) = CURDATE()
                 ORDER BY check_in_time DESC LIMIT 1",
                [$cardUser['id'], $activeEvent['id']]
            );

            if ($existingAttendance) {
                if ($existingAttendance['check_out_time'] === null) {
                    // Check out
                    $this->db->execute(
                        "UPDATE attendance SET check_out_time = ?, device_id_out = ? WHERE id = ?",
                        [$scanTime, $deviceId, $existingAttendance['id']]
                    );

                    $action = 'check_out';
                    $message = 'Checked out successfully';
                } else {
                    // Already completed attendance for today
                    $this->jsonResponse([
                        'success' => false,
                        'error' => 'Already completed attendance for today',
                        'user' => [
                            'name' => $cardUser['first_name'] . ' ' . $cardUser['last_name'],
                            'id' => $cardUser['id']
                        ],
                        'last_checkin' => $existingAttendance['check_in_time'],
                        'last_checkout' => $existingAttendance['check_out_time']
                    ]);
                    return;
                }
            } else {
                // Check in
                $this->db->execute(
                    "INSERT INTO attendance (user_id, event_id, check_in_time, device_id_in, created_at) 
                     VALUES (?, ?, ?, ?, NOW())",
                    [$cardUser['id'], $activeEvent['id'], $scanTime, $deviceId]
                );

                $action = 'check_in';
                $message = 'Checked in successfully';
            }

            $this->logInfo('RFID scan processed', [
                'user_id' => $cardUser['id'],
                'event_id' => $activeEvent['id'],
                'device_id' => $deviceId,
                'action' => $action,
                'card' => $rfidCard
            ]);

            $this->jsonResponse([
                'success' => true,
                'action' => $action,
                'message' => $message,
                'user' => [
                    'id' => $cardUser['id'],
                    'name' => $cardUser['first_name'] . ' ' . $cardUser['last_name'],
                    'email' => $cardUser['email']
                ],
                'event' => [
                    'id' => $activeEvent['id'],
                    'name' => $activeEvent['name']
                ],
                'timestamp' => $scanTime
            ]);

        } catch (Exception $e) {
            $this->logError('RFID scan error', $e);
            $this->jsonResponse(['error' => 'Scan processing failed'], 500);
        }
    }

    /**
     * Get attendance data
     */
    public function getAttendance(): void
    {
        try {
            $user = $this->requireApiAuth();

            $eventId = (int)($_GET['event_id'] ?? 0);
            $date = $_GET['date'] ?? date('Y-m-d');
            $userId = (int)($_GET['user_id'] ?? 0);

            $whereConditions = ["DATE(check_in_time) = ?"];
            $params = [$date];

            if ($eventId > 0) {
                $whereConditions[] = "event_id = ?";
                $params[] = $eventId;
            }

            if ($userId > 0) {
                $whereConditions[] = "user_id = ?";
                $params[] = $userId;
            }

            $whereClause = implode(' AND ', $whereConditions);

            $attendance = $this->db->selectAll(
                "SELECT a.*, 
                        u.first_name, u.last_name, u.email,
                        e.name as event_name,
                        TIMESTAMPDIFF(MINUTE, a.check_in_time, COALESCE(a.check_out_time, NOW())) as duration_minutes
                 FROM attendance a
                 INNER JOIN users u ON a.user_id = u.id
                 INNER JOIN events e ON a.event_id = e.id
                 WHERE {$whereClause}
                 ORDER BY a.check_in_time DESC",
                $params
            );

            $this->jsonResponse([
                'success' => true,
                'data' => $attendance,
                'count' => count($attendance),
                'filters' => [
                    'date' => $date,
                    'event_id' => $eventId,
                    'user_id' => $userId
                ]
            ]);

        } catch (Exception $e) {
            $this->logError('Get attendance API error', $e);
            $this->jsonResponse(['error' => 'Unable to retrieve attendance data'], 500);
        }
    }

    /**
     * Get user information
     */
    public function getUser(): void
    {
        try {
            $user = $this->requireApiAuth();

            $userId = (int)($_GET['id'] ?? 0);
            $rfidCard = trim($_GET['rfid_card'] ?? '');

            if ($userId > 0) {
                $userData = $this->db->selectRow(
                    "SELECT u.*, 
                            GROUP_CONCAT(urc.card_number) as rfid_cards
                     FROM users u
                     LEFT JOIN user_rfid_cards urc ON u.id = urc.user_id
                     WHERE u.id = ? AND u.is_active = 1
                     GROUP BY u.id",
                    [$userId]
                );
            } elseif (!empty($rfidCard)) {
                $userData = $this->db->selectRow(
                    "SELECT u.*, urc.card_number as rfid_card
                     FROM users u
                     INNER JOIN user_rfid_cards urc ON u.id = urc.user_id
                     WHERE urc.card_number = ? AND u.is_active = 1",
                    [$rfidCard]
                );
            } else {
                $this->jsonResponse(['error' => 'User ID or RFID card required'], 400);
                return;
            }

            if (!$userData) {
                $this->jsonResponse(['error' => 'User not found'], 404);
                return;
            }

            // Remove sensitive data
            unset($userData['password']);

            // Get user groups
            $userGroups = $this->db->selectAll(
                "SELECT g.id, g.name, g.category
                 FROM groups g
                 INNER JOIN user_groups ug ON g.id = ug.group_id
                 WHERE ug.user_id = ? AND g.is_active = 1",
                [$userData['id']]
            );

            $userData['groups'] = $userGroups;

            $this->jsonResponse([
                'success' => true,
                'data' => $userData
            ]);

        } catch (Exception $e) {
            $this->logError('Get user API error', $e);
            $this->jsonResponse(['error' => 'Unable to retrieve user data'], 500);
        }
    }

    /**
     * Get events data
     */
    public function getEvents(): void
    {
        try {
            $user = $this->requireApiAuth();

            $status = $_GET['status'] ?? 'active';
            $date = $_GET['date'] ?? date('Y-m-d');

            $whereConditions = [];
            $params = [];

            if ($status !== 'all') {
                $whereConditions[] = "status = ?";
                $params[] = $status;
            }

            if ($date === 'today') {
                $whereConditions[] = "DATE(start_date) <= CURDATE() AND DATE(end_date) >= CURDATE()";
            } elseif ($date !== 'all') {
                $whereConditions[] = "DATE(start_date) <= ? AND DATE(end_date) >= ?";
                $params[] = $date;
                $params[] = $date;
            }

            $whereClause = empty($whereConditions) ? '1=1' : implode(' AND ', $whereConditions);

            $events = $this->db->selectAll(
                "SELECT e.*, 
                        u.first_name as creator_first_name,
                        u.last_name as creator_last_name,
                        COUNT(DISTINCT a.user_id) as attendance_count,
                        COUNT(DISTINCT eg.group_id) as assigned_groups
                 FROM events e
                 LEFT JOIN users u ON e.created_by = u.id
                 LEFT JOIN attendance a ON e.id = a.event_id
                 LEFT JOIN event_groups eg ON e.id = eg.event_id
                 WHERE {$whereClause}
                 GROUP BY e.id
                 ORDER BY e.start_date DESC",
                $params
            );

            $this->jsonResponse([
                'success' => true,
                'data' => $events,
                'count' => count($events)
            ]);

        } catch (Exception $e) {
            $this->logError('Get events API error', $e);
            $this->jsonResponse(['error' => 'Unable to retrieve events data'], 500);
        }
    }

    /**
     * Get system statistics
     */
    public function getStats(): void
    {
        try {
            $user = $this->requireApiAuth();

            $period = $_GET['period'] ?? 'today';

            $stats = [];

            switch ($period) {
                case 'today':
                    $stats = $this->getTodayStats();
                    break;
                case 'week':
                    $stats = $this->getWeekStats();
                    break;
                case 'month':
                    $stats = $this->getMonthStats();
                    break;
                default:
                    $stats = $this->getTodayStats();
            }

            $this->jsonResponse([
                'success' => true,
                'data' => $stats,
                'period' => $period,
                'generated_at' => date('Y-m-d H:i:s')
            ]);

        } catch (Exception $e) {
            $this->logError('Get stats API error', $e);
            $this->jsonResponse(['error' => 'Unable to retrieve statistics'], 500);
        }
    }

    /**
     * Device heartbeat endpoint
     */
    public function deviceHeartbeat(): void
    {
        try {
            $user = $this->requireApiAuth();

            $deviceId = (int)($_POST['device_id'] ?? 0);
            $status = $_POST['status'] ?? 'online';
            $firmwareVersion = $_POST['firmware_version'] ?? null;

            if ($deviceId <= 0) {
                $this->jsonResponse(['error' => 'Device ID required'], 400);
                return;
            }

            // Update device status
            $updateData = [
                'last_heartbeat' => date('Y-m-d H:i:s'),
                'status' => $status
            ];

            if ($firmwareVersion) {
                $updateData['firmware_version'] = $firmwareVersion;
            }

            $setClause = [];
            $params = [];
            foreach ($updateData as $field => $value) {
                $setClause[] = "{$field} = ?";
                $params[] = $value;
            }
            $params[] = $deviceId;

            $this->db->execute(
                "UPDATE rfid_devices SET " . implode(', ', $setClause) . " WHERE id = ?",
                $params
            );

            // Get device info
            $device = $this->db->selectRow(
                "SELECT * FROM rfid_devices WHERE id = ?",
                [$deviceId]
            );

            $this->jsonResponse([
                'success' => true,
                'message' => 'Heartbeat received',
                'device' => $device,
                'server_time' => date('Y-m-d H:i:s')
            ]);

        } catch (Exception $e) {
            $this->logError('Device heartbeat error', $e);
            $this->jsonResponse(['error' => 'Heartbeat processing failed'], 500);
        }
    }

    /**
     * Manual check-in endpoint
     */
    public function manualCheckin(): void
    {
        try {
            $user = $this->requireApiAuth();

            $userId = (int)($_POST['user_id'] ?? 0);
            $eventId = (int)($_POST['event_id'] ?? 0);
            $action = $_POST['action'] ?? 'check_in'; // check_in or check_out
            $adminId = $user['id'];

            if ($userId <= 0 || $eventId <= 0) {
                $this->jsonResponse(['error' => 'User ID and Event ID required'], 400);
                return;
            }

            // Verify user and event exist
            $targetUser = $this->db->selectRow("SELECT * FROM users WHERE id = ?", [$userId]);
            $event = $this->db->selectRow("SELECT * FROM events WHERE id = ?", [$eventId]);

            if (!$targetUser || !$event) {
                $this->jsonResponse(['error' => 'User or event not found'], 404);
                return;
            }

            $timestamp = date('Y-m-d H:i:s');

            if ($action === 'check_in') {
                // Check for existing check-in today
                $existing = $this->db->selectRow(
                    "SELECT * FROM attendance WHERE user_id = ? AND event_id = ? AND DATE(check_in_time) = CURDATE()",
                    [$userId, $eventId]
                );

                if ($existing) {
                    $this->jsonResponse(['error' => 'User already checked in today'], 400);
                    return;
                }

                $this->db->execute(
                    "INSERT INTO attendance (user_id, event_id, check_in_time, created_by, created_at) 
                     VALUES (?, ?, ?, ?, NOW())",
                    [$userId, $eventId, $timestamp, $adminId]
                );

                $message = 'Manual check-in successful';

            } else {
                // Find today's check-in to update
                $existing = $this->db->selectRow(
                    "SELECT * FROM attendance WHERE user_id = ? AND event_id = ? AND DATE(check_in_time) = CURDATE() AND check_out_time IS NULL",
                    [$userId, $eventId]
                );

                if (!$existing) {
                    $this->jsonResponse(['error' => 'No active check-in found for today'], 400);
                    return;
                }

                $this->db->execute(
                    "UPDATE attendance SET check_out_time = ?, updated_by = ? WHERE id = ?",
                    [$timestamp, $adminId, $existing['id']]
                );

                $message = 'Manual check-out successful';
            }

            $this->logInfo('Manual attendance action', [
                'target_user_id' => $userId,
                'event_id' => $eventId,
                'action' => $action,
                'admin_id' => $adminId
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => $message,
                'action' => $action,
                'user' => [
                    'id' => $targetUser['id'],
                    'name' => $targetUser['first_name'] . ' ' . $targetUser['last_name']
                ],
                'event' => [
                    'id' => $event['id'],
                    'name' => $event['name']
                ],
                'timestamp' => $timestamp
            ]);

        } catch (Exception $e) {
            $this->logError('Manual check-in error', $e);
            $this->jsonResponse(['error' => 'Manual check-in failed'], 500);
        }
    }

    /**
     * Get device status
     */
    public function getDeviceStatus(): void
    {
        try {
            $user = $this->requireApiAuth();

            $deviceId = (int)($_GET['device_id'] ?? 0);

            if ($deviceId > 0) {
                // Get specific device
                $device = $this->db->selectRow(
                    "SELECT *, 
                            CASE 
                                WHEN last_heartbeat >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 'online'
                                WHEN last_heartbeat >= DATE_SUB(NOW(), INTERVAL 30 MINUTE) THEN 'warning'
                                ELSE 'offline'
                            END as connection_status
                     FROM rfid_devices WHERE id = ?",
                    [$deviceId]
                );

                if (!$device) {
                    $this->jsonResponse(['error' => 'Device not found'], 404);
                    return;
                }

                $this->jsonResponse(['success' => true, 'data' => $device]);
            } else {
                // Get all devices
                $devices = $this->db->selectAll(
                    "SELECT *, 
                            CASE 
                                WHEN last_heartbeat >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 'online'
                                WHEN last_heartbeat >= DATE_SUB(NOW(), INTERVAL 30 MINUTE) THEN 'warning'
                                ELSE 'offline'
                            END as connection_status
                     FROM rfid_devices 
                     WHERE status != 'deleted'
                     ORDER BY name"
                );

                $this->jsonResponse(['success' => true, 'data' => $devices, 'count' => count($devices)]);
            }

        } catch (Exception $e) {
            $this->logError('Get device status error', $e);
            $this->jsonResponse(['error' => 'Unable to retrieve device status'], 500);
        }
    }

    /**
     * Validate API key and return user info
     */
    private function validateApiKey(string $apiKey): ?array
    {
        // In real implementation, this would check against a database table
        // For now, we'll check against a predefined key or session-based auth
        
        if ($this->getCurrentUser()) {
            return $this->getCurrentUser();
        }

        // Check for system API keys (could be stored in config or database)
        $validApiKeys = [
            'system_key_12345' => ['id' => 0, 'role' => 'system'],
            'device_key_67890' => ['id' => 0, 'role' => 'device']
        ];

        return $validApiKeys[$apiKey] ?? null;
    }

    /**
     * Update device heartbeat
     */
    private function updateDeviceHeartbeat(int $deviceId): void
    {
        $this->db->execute(
            "UPDATE rfid_devices SET last_heartbeat = NOW() WHERE id = ?",
            [$deviceId]
        );
    }

    /**
     * Find active event for current time
     */
    private function findActiveEvent(): ?array
    {
        return $this->db->selectRow(
            "SELECT * FROM events 
             WHERE status = 'active' 
             AND start_date <= NOW() 
             AND end_date >= NOW()
             ORDER BY start_date ASC
             LIMIT 1"
        );
    }

    /**
     * Check if user is assigned to event through groups
     */
    private function isUserAssignedToEvent(int $userId, int $eventId): bool
    {
        $count = $this->db->selectValue(
            "SELECT COUNT(*) FROM user_groups ug
             INNER JOIN event_groups eg ON ug.group_id = eg.group_id
             WHERE ug.user_id = ? AND eg.event_id = ?",
            [$userId, $eventId]
        );

        return $count > 0;
    }

    /**
     * Get today's statistics
     */
    private function getTodayStats(): array
    {
        return [
            'attendance' => [
                'total_checkins' => $this->db->selectValue("SELECT COUNT(*) FROM attendance WHERE DATE(check_in_time) = CURDATE()"),
                'active_sessions' => $this->db->selectValue("SELECT COUNT(*) FROM attendance WHERE DATE(check_in_time) = CURDATE() AND check_out_time IS NULL"),
                'completed_sessions' => $this->db->selectValue("SELECT COUNT(*) FROM attendance WHERE DATE(check_in_time) = CURDATE() AND check_out_time IS NOT NULL"),
                'unique_users' => $this->db->selectValue("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE DATE(check_in_time) = CURDATE()")
            ],
            'events' => [
                'active_today' => $this->db->selectValue("SELECT COUNT(*) FROM events WHERE DATE(start_date) <= CURDATE() AND DATE(end_date) >= CURDATE() AND status = 'active'")
            ],
            'devices' => [
                'online' => $this->db->selectValue("SELECT COUNT(*) FROM rfid_devices WHERE last_heartbeat >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)"),
                'total_active' => $this->db->selectValue("SELECT COUNT(*) FROM rfid_devices WHERE status = 'active'")
            ]
        ];
    }

    /**
     * Get week's statistics
     */
    private function getWeekStats(): array
    {
        return [
            'attendance' => [
                'total_checkins' => $this->db->selectValue("SELECT COUNT(*) FROM attendance WHERE check_in_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"),
                'unique_users' => $this->db->selectValue("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE check_in_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"),
                'daily_average' => $this->db->selectValue("SELECT AVG(daily_count) FROM (SELECT COUNT(*) as daily_count FROM attendance WHERE check_in_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY DATE(check_in_time)) as daily_stats")
            ]
        ];
    }

    /**
     * Get month's statistics
     */
    private function getMonthStats(): array
    {
        return [
            'attendance' => [
                'total_checkins' => $this->db->selectValue("SELECT COUNT(*) FROM attendance WHERE MONTH(check_in_time) = MONTH(CURDATE()) AND YEAR(check_in_time) = YEAR(CURDATE())"),
                'unique_users' => $this->db->selectValue("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE MONTH(check_in_time) = MONTH(CURDATE()) AND YEAR(check_in_time) = YEAR(CURDATE())"),
                'events_with_attendance' => $this->db->selectValue("SELECT COUNT(DISTINCT event_id) FROM attendance WHERE MONTH(check_in_time) = MONTH(CURDATE()) AND YEAR(check_in_time) = YEAR(CURDATE())")
            ]
        ];
    }
}