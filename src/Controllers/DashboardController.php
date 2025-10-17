<?php

namespace RfidCheckin\Controllers;

use RfidCheckin\Services\DatabaseService;
use RfidCheckin\Services\LoggingService;
use Exception;

/**
 * Dashboard Controller
 * 
 * Handles dashboard functionality:
 * - Main dashboard view
 * - Real-time statistics
 * - Recent activity
 * - Quick actions
 */
class DashboardController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Main dashboard view
     */
    public function index(): void
    {
        try {
            // Check authentication
            $user = $this->requireAuth();
            if (!$user) {
                return;
            }
            
            // Get dashboard data
            $data = [
                'user' => $user,
                'stats' => $this->getStats(),
                'recent_activity' => $this->getRecentActivity(),
                'active_events' => $this->getActiveEvents(),
                'title' => 'Dashboard - RFID Check-in System'
            ];
            
            echo $this->render('dashboard/index', $data);
            
        } catch (Exception $e) {
            $this->logger->error('Dashboard error', [
                'error' => $e->getMessage(),
                'user_id' => $user['id'] ?? null
            ]);
            $this->renderError('Unable to load dashboard');
        }
    }
    
    /**
     * Get dashboard statistics (AJAX)
     */
    public function getStats(): array
    {
        $user = $this->getCurrentUser();
        $today = date('Y-m-d');
        
        try {
            $stats = [
                'total_checkins_today' => $this->db->selectValue(
                    "SELECT COUNT(*) FROM attendance WHERE DATE(check_in_time) = ?",
                    [$today]
                ),
                'active_users' => $this->db->selectValue(
                    "SELECT COUNT(DISTINCT user_id) FROM attendance 
                     WHERE DATE(check_in_time) = ? AND check_out_time IS NULL",
                    [$today]
                ),
                'active_events' => $this->db->selectValue(
                    "SELECT COUNT(*) FROM events 
                     WHERE status = 'active' AND start_date <= CURDATE() 
                     AND (end_date IS NULL OR end_date >= CURDATE())"
                ),
                'total_users' => $this->db->selectValue(
                    "SELECT COUNT(*) FROM users WHERE is_active = 1"
                )
            ];
            
            if ($this->isAjaxRequest()) {
                $this->jsonSuccess($stats);
                return [];
            }
            
            return $stats;
            
        } catch (Exception $e) {
            $this->logger->error('Dashboard stats error', ['error' => $e->getMessage()]);
            
            if ($this->isAjaxRequest()) {
                $this->jsonError('Unable to load statistics');
            }
            
            return [];
        }
    }
    
    /**
     * Get recent activity
     */
    public function getRecentActivity(): array
    {
        try {
            return $this->db->selectAll(
                "SELECT a.*, u.first_name, u.last_name, e.name as event_name
                 FROM attendance a
                 JOIN users u ON a.user_id = u.id
                 LEFT JOIN events e ON a.event_id = e.id
                 ORDER BY a.check_in_time DESC
                 LIMIT 10"
            );
        } catch (Exception $e) {
            $this->logger->error('Recent activity error', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Get active events
     */
    private function getActiveEvents(): array
    {
        try {
            return $this->db->selectAll(
                "SELECT id, name, start_date, start_time, end_date, end_time, 
                        description, location
                 FROM events 
                 WHERE status = 'active' 
                 AND start_date <= CURDATE() 
                 AND (end_date IS NULL OR end_date >= CURDATE())
                 ORDER BY start_date, start_time
                 LIMIT 5"
            );
        } catch (Exception $e) {
            $this->logger->error('Active events error', ['error' => $e->getMessage()]);
            return [];
        }
    }
}