<?php
declare(strict_types=1);

use RfidCheckin\Controllers\AuthController;
use RfidCheckin\Controllers\DashboardController;
use RfidCheckin\Controllers\HomeController;
use RfidCheckin\Controllers\UserController;
use RfidCheckin\Controllers\EventController;
use RfidCheckin\Controllers\AdminController;
use RfidCheckin\Controllers\GroupController;
use RfidCheckin\Controllers\ApiController;
use RfidCheckin\Controllers\ReportController;
use RfidCheckin\Controllers\NotificationController;

// ==============================================
// PUBLIC ROUTES (No Authentication Required)
// ==============================================

// Homepage
$router->get('/', [HomeController::class, 'index']);

// Authentication routes
$router->get('/login', [AuthController::class, 'showLoginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/forgot-password', [AuthController::class, 'showForgotForm']);
$router->post('/forgot-password', [AuthController::class, 'forgotPassword']);
$router->get('/reset-password', [AuthController::class, 'showResetForm']);
$router->post('/reset-password', [AuthController::class, 'resetPassword']);

// Public API endpoints (for RFID devices)
$router->post('/api/rfid/scan', [ApiController::class, 'rfidScan']);
$router->post('/api/device/heartbeat', [ApiController::class, 'deviceHeartbeat']);
$router->get('/api/device/status', [ApiController::class, 'getDeviceStatus']);

// ==============================================
// AUTHENTICATED USER ROUTES
// ==============================================

$router->group(['middleware' => 'auth'], function($router) {
    
    // Dashboard routes
    $router->get('/', [DashboardController::class, 'index']);
    $router->get('/dashboard', [DashboardController::class, 'index']);
    
    // User profile routes
    $router->get('/profile', [UserController::class, 'profile']);
    $router->post('/profile', [UserController::class, 'updateProfile']);
    $router->get('/profile/password', [UserController::class, 'showChangePasswordForm']);
    $router->post('/profile/password', [UserController::class, 'changePassword']);
    
    // User attendance view
    $router->get('/attendance', [UserController::class, 'attendance']);
    $router->get('/attendance/export', [UserController::class, 'exportAttendance']);
    
    // Event participation
    $router->get('/events', [EventController::class, 'userEvents']);
    $router->get('/events/{id}', [EventController::class, 'show']);
    $router->post('/events/{id}/join', [EventController::class, 'joinEvent']);
    $router->post('/events/{id}/leave', [EventController::class, 'leaveEvent']);
    
    // User notifications
    $router->get('/notifications', [NotificationController::class, 'index']);
    $router->post('/notifications/mark-read', [NotificationController::class, 'markAsRead']);
    $router->post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    $router->post('/notifications/delete', [NotificationController::class, 'delete']);
    $router->get('/api/notifications/unread-count', [NotificationController::class, 'getUnreadCount']);
    $router->get('/api/notifications/recent', [NotificationController::class, 'getRecent']);
    
    // Check-in interface
    $router->get('/checkin', [DashboardController::class, 'checkin']);
    
    // AJAX endpoints for authenticated users
    $router->get('/api/user/stats', [UserController::class, 'getUserStats']);
    $router->get('/api/user/attendance', [ApiController::class, 'getAttendance']);
});

// ==============================================
// MANAGER/ADMIN ROUTES
// ==============================================

$router->group(['middleware' => ['auth', 'role:manager,admin']], function($router) {
    
    // Event management
    $router->get('/events/manage', [EventController::class, 'index']);
    $router->get('/events/create', [EventController::class, 'create']);
    $router->post('/events/create', [EventController::class, 'store']);
    $router->get('/events/{id}/edit', [EventController::class, 'edit']);
    $router->post('/events/{id}/edit', [EventController::class, 'update']);
    $router->post('/events/{id}/delete', [EventController::class, 'delete']);
    $router->get('/events/{id}/attendance', [EventController::class, 'attendance']);
    $router->post('/events/{id}/checkin', [EventController::class, 'manualCheckin']);
    
    // Group management
    $router->get('/groups', [GroupController::class, 'index']);
    $router->get('/groups/create', [GroupController::class, 'create']);
    $router->post('/groups/create', [GroupController::class, 'store']);
    $router->get('/groups/{id}', [GroupController::class, 'show']);
    $router->get('/groups/{id}/edit', [GroupController::class, 'edit']);
    $router->post('/groups/{id}/edit', [GroupController::class, 'update']);
    $router->post('/groups/{id}/delete', [GroupController::class, 'delete']);
    $router->get('/groups/{id}/members', [GroupController::class, 'members']);
    $router->post('/groups/{id}/add-member', [GroupController::class, 'addMember']);
    $router->post('/groups/{id}/remove-member', [GroupController::class, 'removeMember']);
    $router->get('/api/groups/{id}/stats', [GroupController::class, 'getStats']);
    
    // Basic user management
    $router->get('/users', [UserController::class, 'index']);
    $router->get('/users/{id}', [UserController::class, 'show']);
    $router->get('/users/{id}/edit', [UserController::class, 'edit']);
    $router->post('/users/{id}/edit', [UserController::class, 'update']);
    $router->post('/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
    
    // Basic reports
    $router->get('/reports', [ReportController::class, 'index']);
    $router->get('/reports/attendance', [ReportController::class, 'attendance']);
    $router->get('/reports/user-activity', [ReportController::class, 'userActivity']);
    $router->get('/reports/event-summary', [ReportController::class, 'eventSummary']);
    $router->get('/reports/group-analysis', [ReportController::class, 'groupAnalysis']);
    $router->post('/reports/export-attendance-csv', [ReportController::class, 'exportAttendanceCsv']);
    
    // Notification management
    $router->get('/notifications/admin', [NotificationController::class, 'admin']);
    $router->get('/notifications/create', [NotificationController::class, 'create']);
    $router->post('/notifications/create', [NotificationController::class, 'create']);
    $router->post('/notifications/send', [NotificationController::class, 'send']);
    $router->get('/notifications/templates', [NotificationController::class, 'templates']);
    
    // Manual check-in
    $router->post('/api/manual-checkin', [ApiController::class, 'manualCheckin']);
});

// ==============================================
// ADMIN-ONLY ROUTES
// ==============================================

$router->group(['middleware' => ['auth', 'role:admin']], function($router) {
    
    // Admin dashboard
    $router->get('/admin', [AdminController::class, 'index']);
    $router->get('/admin/dashboard', [AdminController::class, 'index']);
    
    // Advanced user management
    $router->get('/admin/users', [AdminController::class, 'users']);
    $router->get('/admin/users/create', [AdminController::class, 'userForm']);
    $router->post('/admin/users/create', [AdminController::class, 'userForm']);
    $router->get('/admin/users/{id}/edit', [AdminController::class, 'userForm']);
    $router->post('/admin/users/{id}/edit', [AdminController::class, 'userForm']);
    $router->post('/admin/users/{id}/delete', [AdminController::class, 'deleteUser']);
    $router->post('/admin/users/{id}/reset-password', [UserController::class, 'resetPassword']);
    
    // System management
    $router->get('/admin/settings', [AdminController::class, 'settings']);
    $router->post('/admin/settings', [AdminController::class, 'updateSettings']);
    $router->get('/admin/logs', [AdminController::class, 'logs']);
    $router->get('/admin/analytics', [AdminController::class, 'analytics']);
    $router->get('/admin/performance', [AdminController::class, 'performance']);
    
    // RFID device management
    $router->get('/admin/rfid', [AdminController::class, 'rfidTest']);
    $router->post('/admin/rfid/test', [AdminController::class, 'testRfidDevice']);
    $router->get('/admin/devices', [AdminController::class, 'devices']);
    
    // Advanced reports
    $router->get('/admin/reports/device-usage', [ReportController::class, 'deviceUsage']);
    $router->get('/admin/reports/custom', [ReportController::class, 'customBuilder']);
    $router->post('/admin/reports/custom/generate', [ReportController::class, 'generateCustomReport']);
    $router->get('/admin/reports/analytics', [ReportController::class, 'analytics']);
    $router->get('/api/reports/chart-data', [ReportController::class, 'getChartData']);
    
    // System maintenance
    $router->post('/admin/maintenance/clear-logs', [AdminController::class, 'clearLogs']);
    $router->post('/admin/maintenance/optimize-database', [AdminController::class, 'optimizeDatabase']);
    $router->get('/admin/backup', [AdminController::class, 'backup']);
    $router->post('/admin/backup/create', [AdminController::class, 'createBackup']);
});

// ==============================================
// API ROUTES FOR EXTERNAL INTEGRATION
// ==============================================

$router->group(['prefix' => 'api', 'middleware' => 'api-auth'], function($router) {
    
    // User API
    $router->get('/users', [ApiController::class, 'getUser']);
    $router->get('/users/{id}', [ApiController::class, 'getUser']);
    
    // Event API
    $router->get('/events', [ApiController::class, 'getEvents']);
    $router->get('/events/{id}', [ApiController::class, 'getEvents']);
    
    // Attendance API
    $router->get('/attendance', [ApiController::class, 'getAttendance']);
    $router->get('/attendance/{userId}', [ApiController::class, 'getAttendance']);
    
    // Statistics API
    $router->get('/stats', [ApiController::class, 'getStats']);
    $router->get('/stats/attendance', [ApiController::class, 'getStats']);
    $router->get('/stats/events', [ApiController::class, 'getStats']);
    $router->get('/stats/users', [ApiController::class, 'getStats']);
});

// ==============================================
// ERROR ROUTES
// ==============================================

$router->get('/404', function() {
    http_response_code(404);
    include __DIR__ . '/../views/404.php';
});

$router->get('/403', function() {
    http_response_code(403);
    include __DIR__ . '/../error-pages/403.html';
});

$router->get('/500', function() {
    http_response_code(500);
    include __DIR__ . '/../views/500.php';
});
