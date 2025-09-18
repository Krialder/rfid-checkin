<?php
/**
 * User Management Administrative Interface
 * 
 * Administrative interface for managing users with CRUD operations,
 * bulk actions, role management, and RFID tag assignment.
 * 
 * @package RfidCheckin\Admin
 * @author Kralder
 * @access Admin Only
 */

// Load configuration and components
require_once '../core/config.php';
require_once '../core/auth.php';

// Initialize components
$container = EnterpriseContainer::getInstance();
$errorHandler = $container->get('errorHandler');
$securityManager = $container->get('securityManager');
$performanceManager = $container->get('performanceManager');
$assetOptimizer = $container->get('assetOptimizer');

// Initialize repositories and services
$userRepository = new UserRepository();
$dataService = new DataService();

// Enforce administrative access control
if (!Auth::isLoggedIn() || !Auth::hasRole(['admin'])) {
    $errorHandler->log('Unauthorized access attempt to admin users page', null, 'WARNING', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    http_response_code(403);
    header('Location: ../auth/login.php');
    exit;
}

// Start performance monitoring
$performanceManager->startTimer('admin_users_page');

// Handle AJAX requests with enterprise security and performance monitoring
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $performanceManager->startTimer('ajax_request');
    
    try {
        // Validate CSRF token
        if (!$securityManager->validateCSRFToken($_POST['_token'] ?? '')) {
            throw new Exception('Invalid security token');
        }
        
        $action = $securityManager->validateInput($_POST['action'] ?? '', 'string');
        
        switch ($action) {
            case 'load_users':
                $page = max(1, intval($_POST['page'] ?? 1));
                $limit = max(10, min(100, intval($_POST['limit'] ?? 25)));
                $search = $securityManager->validateInput($_POST['search'] ?? '', 'string');
                $role = $securityManager->validateInput($_POST['role'] ?? '', 'string');
                $status = $securityManager->validateInput($_POST['status'] ?? '', 'string');
                
                // Build filter array for repository
                $filters = [];
                if ($search) $filters['search'] = $search;
                if ($role) $filters['role'] = $role;
                if ($status === 'active') $filters['status'] = true;
                elseif ($status === 'inactive') $filters['status'] = false;
                
                // Use repository pattern for data access
                $result = $userRepository->getUsersPaginated($filters, $page, $limit);
                
                // Record performance metrics
                $performanceManager->recordMetric('users_loaded', count($result['users']));
                
                echo json_encode([
                    'success' => true,
                    'users' => $result['users'],
                    'total' => $result['total'],
                    'page' => $result['page'],
                    'totalPages' => $result['totalPages'],
                    'limit' => $result['limit']
                ]);
                break;
                ");
                $stmt->execute(array_merge($params, [$limit, $offset]));
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'users' => $users,
                    'pagination' => [
                        'current_page' => $page,
                        'total_pages' => ceil($total / $limit),
                        'total_records' => $total,
                        'per_page' => $limit
                    ]
                ]);
                break;
                
            case 'create_user':
                // Validate and sanitize input using SecurityManager
                $userData = [
                    'username' => $securityManager->validateInput($_POST['username'] ?? '', 'username'),
                    'email' => $securityManager->validateInput($_POST['email'] ?? '', 'email'),
                    'first_name' => $securityManager->validateInput($_POST['first_name'] ?? '', 'name'),
                    'last_name' => $securityManager->validateInput($_POST['last_name'] ?? '', 'name'),
                    'role' => $securityManager->validateInput($_POST['role'] ?? 'user', 'string'),
                    'department' => $securityManager->validateInput($_POST['department'] ?? '', 'string'),
                    'rfid_tag' => $securityManager->validateInput($_POST['rfid_tag'] ?? '', 'string'),
                    'password' => $securityManager->validateInput($_POST['password'] ?? '', 'password')
                ];
                
                // Additional validation
                if (!$userData['username'] || !$userData['email'] || !$userData['first_name'] || !$userData['password']) {
                    throw new Exception('Required fields are missing');
                }
                
                if (strlen($userData['password']) < PASSWORD_MIN_LENGTH) {
                    throw new Exception('Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long');
                }
                
                // Use repository for user creation with enterprise validation
                try {
                    $userId = $userRepository->createUser($userData);
                    
                    // Log activity using enterprise logging
                    $currentUser = Auth::getCurrentUser();
                    $userRepository->logActivity(
                        $currentUser['user_id'], 
                        'user_created', 
                        "User '{$userData['username']}' created by admin"
                    );
                    
                    // Record performance metrics
                    $performanceManager->recordMetric('user_created', 1);
                    
                    echo json_encode(['success' => true, 'user_id' => $userId]);
                    
                } catch (InvalidArgumentException $e) {
                    throw new Exception($e->getMessage());
                }
                break;
                
            case 'update_user':
                $userId = intval($_POST['user_id'] ?? 0);
                $field = $_POST['field'] ?? '';
                $value = $_POST['value'] ?? '';
                
                if (!$userId || !$field) {
                    throw new Exception('Missing required parameters');
                }
                
                $allowedFields = ['username', 'email', 'first_name', 'last_name', 'role', 'department', 'rfid_tag', 'is_active'];
                if (!in_array($field, $allowedFields)) {
                    throw new Exception('Invalid field');
                }
                
                // Additional validation
                if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email address');
                }
                
                if (in_array($field, ['username', 'email', 'rfid_tag']) && $value) {
                    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE $field = ? AND user_id != ?");
                    $stmt->execute([$value, $userId]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception(ucfirst($field) . ' already exists');
                    }
                }
                
                // Update user
                $stmt = $db->prepare("UPDATE users SET $field = ? WHERE user_id = ?");
                $stmt->execute([$value ?: null, $userId]);
                
                echo json_encode(['success' => true]);
                break;
                
            case 'get_user':
                $userId = intval($_POST['user_id'] ?? 0);
                
                if (!$userId) {
                    throw new Exception('User ID required');
                }
                
                $stmt = $db->prepare("
                    SELECT user_id, username, first_name, last_name, email, phone, role, 
                           department, rfid_tag, is_active, created_at, updated_at
                    FROM users 
                    WHERE user_id = ?
                ");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                
                if ($user) {
                    echo json_encode(['success' => true, 'user' => $user]);
                } else {
                    throw new Exception('User not found');
                }
                break;
                
            case 'update_user_multiple':
                $userId = intval($_POST['user_id'] ?? 0);
                
                if (!$userId) {
                    throw new Exception('User ID required');
                }
                
                $allowedFields = ['username', 'email', 'first_name', 'last_name', 'role', 'department', 'phone', 'rfid_tag'];
                $updateFields = [];
                $updateValues = [];
                
                foreach ($allowedFields as $field) {
                    if (isset($_POST[$field])) {
                        $value = trim($_POST[$field]);
                        
                        // Validation
                        if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            throw new Exception('Invalid email address');
                        }
                        
                        if ($field === 'first_name' && empty($value)) {
                            throw new Exception('First name is required');
                        }
                        
                        // Check uniqueness for certain fields
                        if (in_array($field, ['username', 'email', 'rfid_tag']) && !empty($value)) {
                            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE $field = ? AND user_id != ?");
                            $stmt->execute([$value, $userId]);
                            if ($stmt->fetchColumn() > 0) {
                                throw new Exception(ucfirst($field) . ' already exists');
                            }
                        }
                        
                        $updateFields[] = "$field = ?";
                        $updateValues[] = $value ?: null;
                    }
                }
                
                if (empty($updateFields)) {
                    throw new Exception('No fields to update');
                }
                
                // Add user ID for WHERE clause
                $updateValues[] = $userId;
                
                // Update user
                $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE user_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($updateValues);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'User updated successfully']);
                } else {
                    echo json_encode(['success' => true, 'message' => 'No changes made']);
                }
                break;
                
            case 'delete_user':
                $userId = intval($_POST['user_id'] ?? 0);
                
                if (!$userId) {
                    throw new Exception('User ID required');
                }
                
                // Check if user exists and is not current user
                if ($userId === Auth::getCurrentUser()['user_id']) {
                    throw new Exception('Cannot delete your own account');
                }
                
                // Start transaction
                $db->beginTransaction();
                
                try {
                    // Delete related records first to maintain referential integrity
                    // Note: Using correct lowercase table names from database
                    
                    // Delete from checkin table
                    $stmt = $db->prepare("DELETE FROM checkin WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    // Delete from eventregistration table  
                    $stmt = $db->prepare("DELETE FROM eventregistration WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    // Delete from usergroupmemberships table
                    $stmt = $db->prepare("DELETE FROM usergroupmemberships WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    // Delete from activitylog table
                    $stmt = $db->prepare("DELETE FROM activitylog WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    // Delete from password_resets table
                    $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    // Delete from accesslogs table
                    $stmt = $db->prepare("DELETE FROM accesslogs WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    // Finally delete the user
                    $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    // Check if user was actually deleted
                    if ($stmt->rowCount() === 0) {
                        throw new Exception('User not found or could not be deleted');
                    }
                    
                    $db->commit();
                    echo json_encode(['success' => true, 'message' => 'User permanently deleted']);
                    
                } catch (Exception $e) {
                    $db->rollback();
                    throw $e;
                }
                break;
                
            case 'reset_password':
                $userId = intval($_POST['user_id'] ?? 0);
                $newPassword = $_POST['new_password'] ?? '';
                
                if (!$userId || !$newPassword) {
                    throw new Exception('Missing required parameters');
                }
                
                if (strlen($newPassword) < 8) {
                    throw new Exception('Password must be at least 8 characters long');
                }
                
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->execute([$hashedPassword, $userId]);
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activitylog (user_id, action, details, ip_address) 
                    VALUES (?, 'password_reset_admin', 'Password reset by admin', ?)
                ");
                $stmt->execute([
                    Auth::getCurrentUser()['user_id'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                
                echo json_encode(['success' => true]);
                break;
                
            case 'bulk_import':
                if (!isset($_FILES['csv_file'])) {
                    throw new Exception('No file uploaded');
                }
                
                $file = $_FILES['csv_file'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('File upload error');
                }
                
                $handle = fopen($file['tmp_name'], 'r');
                if (!$handle) {
                    throw new Exception('Unable to read file');
                }
                
                $header = fgetcsv($handle);
                $expectedColumns = ['username', 'email', 'first_name', 'last_name', 'role', 'department', 'rfid_tag'];
                
                $imported = 0;
                $errors = [];
                
                while (($row = fgetcsv($handle)) !== false) {
                    try {
                        if (count($row) < count($expectedColumns)) {
                            continue;
                        }
                        
                        $userData = array_combine($expectedColumns, array_slice($row, 0, count($expectedColumns)));
                        $userData = array_map('trim', $userData);
                        
                        // Generate random password
                        $userData['password'] = bin2hex(random_bytes(8));
                        
                        // Basic validation
                        if (empty($userData['username']) || empty($userData['email']) || empty($userData['first_name'])) {
                            continue;
                        }
                        
                        // Check for duplicates
                        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
                        $stmt->execute([$userData['username'], $userData['email']]);
                        if ($stmt->fetchColumn() > 0) {
                            continue;
                        }
                        
                        // Create user
                        $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
                        $stmt = $db->prepare("
                            INSERT INTO users (username, email, password, first_name, last_name, role, department, rfid_tag, is_active, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                        ");
                        $stmt->execute([
                            $userData['username'],
                            $userData['email'],
                            $hashedPassword,
                            $userData['first_name'],
                            $userData['last_name'],
                            $userData['role'] ?: 'user',
                            $userData['department'],
                            $userData['rfid_tag'] ?: null
                        ]);
                        
                        $imported ++;
                    } catch (Exception $e) {
                        $errors[] = "Row " . ($imported + count($errors) + 1) . ": " . $e->getMessage();
                    }
                }
                
                fclose($handle);
                
                echo json_encode([
                    'success' => true,
                    'imported' => $imported,
                    'errors' => $errors
                ]);
                break;
                
            default:
                throw new Exception('Unknown action');
        }
        
    } catch (Exception $e) {
        // Enterprise error handling
        $errorHandler->log('Admin users AJAX error', $e, 'ERROR', [
            'action' => $action ?? 'unknown',
            'user_id' => Auth::getCurrentUser()['user_id'] ?? null
        ]);
        
        // Record error metrics
        $performanceManager->recordMetric('admin_ajax_error', 1);
        
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    } finally {
        $performanceManager->endTimer('ajax_request');
    }
    exit;
}

// Get statistics using enterprise repository pattern
$stats = [];
try {
    $performanceManager->startTimer('load_statistics');
    
    // Use repository for system statistics
    $stats = $userRepository->getSystemStats();
    
    // Record metrics
    $performanceManager->recordMetric('stats_loaded', 1);
    
} catch (Exception $e) {
    $errorHandler->log('Failed to load user statistics', $e, 'ERROR');
    $stats = [
        'total_users' => 0,
        'active_users' => 0,
        'admin_users' => 0,
        'regular_users' => 0,
        'users_with_rfid' => 0,
        'active_last_30_days' => 0
    ];
} finally {
    $performanceManager->endTimer('load_statistics');
}
    $stmt->execute();
    $stats['new_users_month'] = $stmt->fetchColumn();
    
    // Users by role
    $stmt = $db->prepare("SELECT role, COUNT(*) as count FROM users WHERE is_active = 1 GROUP BY role");
    $stmt->execute();
    $stats['users_by_role'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Recent activity
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM activitylog 
        WHERE action IN ('login', 'checkin', 'checkout') 
        AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute();
    $stats['activity_24h'] = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Stats query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise User Management - RFID Check-in System</title>
    
    <!-- Enterprise Asset Optimization -->
    <?php
    echo $assetOptimizer->loadCSS([
        'main.css',
        'navigation.css', 
        'dashboard.css',
        'forms.css',
        'users.css'
    ]);
    ?>
    
    <!-- Security Headers -->
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <meta name="robots" content="noindex, nofollow">
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <div class="container">
                <h1>Enterprise User Management</h1>
                <p>Comprehensive user administration with enterprise security and performance monitoring</p>
                
                <?php if (DEBUG_MODE): ?>
                <div class="debug-info">
                    <small>Enterprise Mode: Repository Pattern | Performance Monitoring Active</small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    
    <div class="container">
        <!-- Enhanced Statistics Cards with Enterprise Data -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-value"><?php echo number_format($stats['total_users'] ?? 0); ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-trend">System-wide</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-value"><?php echo number_format($stats['active_users'] ?? 0); ?></div>
                <div class="stat-label">Active Users</div>
                <div class="stat-trend">Currently enabled</div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-value"><?php echo number_format($stats['users_with_rfid'] ?? 0); ?></div>
                <div class="stat-label">RFID Assigned</div>
                <div class="stat-trend">Hardware integrated</div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-value"><?php echo number_format($stats['active_last_30_days'] ?? 0); ?></div>
                <div class="stat-label">Recent Activity</div>
                <div class="stat-trend">Last 30 days</div>
            </div>
        </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['new_users_month'] ?? 0; ?></div>
                <div class="stat-label">New Users This Month</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['activity_24h'] ?? 0; ?></div>
                <div class="stat-label">Activity Last 24h</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($stats['users_by_role'] ?? []); ?></div>
                <div class="stat-label">Different Roles</div>
            </div>
        </div>
        
        <!-- Toolbar -->
        <div class="toolbar">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search users..." class="form-control">
            </div>
            <div class="filter-group">
                <select id="roleFilter">
                    <option value="">All Roles</option>
                    <option value="admin">Admin</option>
                    <option value="moderator">Moderator</option>
                    <option value="user">User</option>
                </select>
                <select id="statusFilter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="btn-group">
                <button class="btn btn-primary" onclick="openCreateUserModal()">Add User</button>
                <button class="btn btn-secondary" onclick="openImportModal()">Import CSV</button>
                <button class="btn btn-secondary" onclick="exportUsers()">Export</button>
            </div>
        </div>
        
        <!-- Users Table -->
        <div class="users-table-container">
            <div id="loadingIndicator" class="loading">
                Loading users...
            </div>
            <table class="users-table" id="usersTable" style="display: none;">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>RFID Tag</th>
                        <th>Check-ins</th>
                        <th>Last Check-in</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                </tbody>
            </table>
            
            <div class="pagination" id="paginationContainer" style="display: none;">
                <div class="pagination-info" id="paginationInfo"></div>
                <div class="pagination-controls">
                    <button onclick="changePage('prev')" id="prevBtn">Previous</button>
                    <button onclick="changePage('next')" id="nextBtn">Next</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create User Modal -->
    <div class="modal" id="createUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New User</h3>
                <button class="close-btn" onclick="closeModal('createUserModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="createUserForm">
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name">
                    </div>
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role">
                            <option value="user">User</option>
                            <option value="moderator">Moderator</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="department">Department</label>
                        <input type="text" id="department" name="department">
                    </div>
                    <div class="form-group">
                        <label for="rfid_tag">RFID Tag</label>
                        <div class="rfid-input-group">
                            <input type="text" id="rfid_tag" name="rfid_tag" placeholder="Enter RFID tag">
                            <button type="button" class="btn-scan-rfid" 
                                    data-rfid-scan data-rfid-target="rfid_tag">
                                Scan RFID
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required minlength="8">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Import CSV Modal -->
    <div class="modal" id="importModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Import Users from CSV</h3>
                <button class="close-btn" onclick="closeModal('importModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>CSV should contain columns: username, email, first_name, last_name, role, department, rfid_tag</p>
                <form id="importForm">
                    <div class="form-group">
                        <label for="csv_file">CSV File</label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Import Users</button>
                    </div>
                </form>
                <div id="importResults" style="margin-top: 1rem;"></div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/dashboard.js"></script>
    <script src="../assets/js/rfid-scanner.js"></script>
    <script>
        let currentPage = 1;
        let totalPages = 1;
        let searchTimeout;
        
        // Load users on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadUsers();
            
            // Search functionality
            document.getElementById('searchInput').addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    currentPage = 1;
                    loadUsers();
                }, 500);
            });
            
            // Filter functionality
            document.getElementById('roleFilter').addEventListener('change', () => {
                currentPage = 1;
                loadUsers();
            });
            
            document.getElementById('statusFilter').addEventListener('change', () => {
                currentPage = 1;
                loadUsers();
            });
        });
        
        function loadUsers() {
            const loadingIndicator = document.getElementById('loadingIndicator');
            const usersTable = document.getElementById('usersTable');
            const paginationContainer = document.getElementById('paginationContainer');
            
            loadingIndicator.style.display = 'block';
            usersTable.style.display = 'none';
            paginationContainer.style.display = 'none';
            
            const formData = new FormData();
            formData.append('action', 'load_users');
            formData.append('page', currentPage);
            formData.append('search', document.getElementById('searchInput').value);
            formData.append('role', document.getElementById('roleFilter').value);
            formData.append('status', document.getElementById('statusFilter').value);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayUsers(data.users);
                    updatePagination(data.pagination);
                } else {
                    alert('Error loading users: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading users');
            })
            .finally(() => {
                loadingIndicator.style.display = 'none';
                usersTable.style.display = 'table';
                paginationContainer.style.display = 'flex';
            });
        }
        
        function displayUsers(users) {
            const tbody = document.getElementById('usersTableBody');
            tbody.innerHTML = '';
            
            users.forEach(user => {
                const row = document.createElement('tr');
                
                const initials = (user.first_name.charAt(0) + (user.last_name?.charAt(0) || '')).toUpperCase();
                const lastCheckin = user.last_checkin ? new Date(user.last_checkin).toLocaleDateString() : 'Never';
                
                row.innerHTML = `
                    <td>
                        <div class="user-info">
                            <div class="user-avatar">${initials}</div>
                            <div class="user-details">
                                <h4>${escapeHtml(user.first_name)} ${escapeHtml(user.last_name || '')}</h4>
                                <p>${escapeHtml(user.username)} • ${escapeHtml(user.email)}</p>
                            </div>
                        </div>
                    </td>
                    <td><span class="role-badge role-${user.role}">${user.role}</span></td>
                    <td>${escapeHtml(user.department || 'N/A')}</td>
                    <td><span class="status-badge status-${user.is_active == 1 ? 'active' : 'inactive'}">${user.is_active == 1 ? 'Active' : 'Inactive'}</span></td>
                    <td>${escapeHtml(user.rfid_tag || 'Not assigned')}</td>
                    <td>${user.total_checkins || 0}</td>
                    <td>${lastCheckin}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-sm btn-edit" onclick="editUser(${user.user_id})">Edit</button>
                            <button class="btn-sm ${user.is_active == 1 ? 'btn-warning' : 'btn-success'}" 
                                    onclick="toggleUserStatus(${user.user_id}, ${user.is_active == 1 ? 0 : 1})">
                                ${user.is_active == 1 ? 'Deactivate' : 'Activate'}
                            </button>
                            <button class="btn-sm btn-delete" onclick="deleteUser(${user.user_id})">Delete</button>
                        </div>
                    </td>
                `;
                
                tbody.appendChild(row);
            });
        }
        
        function updatePagination(pagination) {
            totalPages = pagination.total_pages;
            currentPage = pagination.current_page;
            
            document.getElementById('paginationInfo').textContent = 
                `Showing ${(currentPage - 1) * pagination.per_page + 1}-${Math.min(currentPage * pagination.per_page, pagination.total_records)} of ${pagination.total_records} users`;
            
            document.getElementById('prevBtn').disabled = currentPage <= 1;
            document.getElementById('nextBtn').disabled = currentPage >= totalPages;
        }
        
        function changePage(direction) {
            if (direction === 'prev' && currentPage > 1) {
                currentPage --;
                loadUsers();
            } else if (direction === 'next' && currentPage < totalPages) {
                currentPage ++;
                loadUsers();
            }
        }
        
        function openCreateUserModal() {
            document.getElementById('createUserModal').style.display = 'block';
        }
        
        function openImportModal() {
            document.getElementById('importModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Create user form submission
        document.getElementById('createUserForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'create_user');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal('createUserModal');
                    this.reset();
                    loadUsers();
                    alert('User created successfully');
                } else {
                    alert('Error creating user: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error creating user');
            });
        });
        
        // Edit user form submission handler
        document.addEventListener('submit', function(e) {
            if (e.target.id === 'editUserForm') {
                e.preventDefault();
                
                const formData = new FormData(e.target);
                formData.append('action', 'update_user_multiple');
                
                // Disable submit button
                const submitBtn = e.target.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Updating...';
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        closeModal('editUserModal');
                        loadUsers();
                        alert(data.message || 'User updated successfully');
                    } else {
                        alert('Error updating user: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating user');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                });
            }
        });
        
        // Import form submission
        document.getElementById('importForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'bulk_import');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('importResults').innerHTML = `
                        <div class="success-message">
                            Successfully imported ${data.imported} users.
                            ${data.errors.length > 0 ? '<br>Errors: ' + data.errors.join('<br>') : ''}
                        </div>
                    `;
                    loadUsers();
                } else {
                    document.getElementById('importResults').innerHTML = `
                        <div class="error-message">Error: ${data.error}</div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('importResults').innerHTML = `
                    <div class="error-message">Error importing users</div>
                `;
            });
        });
        
        function editUser(userId) {
            // Load user data first
            loadUserForEdit(userId);
        }
        
        function loadUserForEdit(userId) {
            fetch('', {
                method: 'POST',
                body: new URLSearchParams({
                    'action': 'get_user',
                    'user_id': userId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showEditModal(data.user);
                } else {
                    alert('Error loading user data: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Fallback to simple role edit
                const newRole = prompt('Enter new role (admin/moderator/user):');
                if (newRole && ['admin', 'moderator', 'user'].includes(newRole)) {
                    updateUser(userId, 'role', newRole);
                }
            });
        }
        
        function showEditModal(user) {
            // Create modal if it doesn't exist
            let modal = document.getElementById('editUserModal');
            if (!modal) {
                modal = createEditModal();
                document.body.appendChild(modal);
            }
            
            // Populate form with user data
            document.getElementById('editUserId').value = user.user_id;
            document.getElementById('editUsername').value = user.username || '';
            document.getElementById('editFirstName').value = user.first_name || '';
            document.getElementById('editLastName').value = user.last_name || '';
            document.getElementById('editEmail').value = user.email || '';
            document.getElementById('editPhone').value = user.phone || '';
            document.getElementById('editRole').value = user.role || 'user';
            document.getElementById('editDepartment').value = user.department || '';
            document.getElementById('editRfidTag').value = user.rfid_tag || '';
            
            // Show modal
            modal.style.display = 'block';
        }
        
        function createEditModal() {
            const modal = document.createElement('div');
            modal.id = 'editUserModal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Edit User</h3>
                        <button class="close-btn" onclick="closeModal('editUserModal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="editUserForm">
                            <input type="hidden" id="editUserId" name="user_id">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="editUsername">Username</label>
                                    <input type="text" id="editUsername" name="username" required>
                                </div>
                                <div class="form-group">
                                    <label for="editEmail">Email</label>
                                    <input type="email" id="editEmail" name="email" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="editFirstName">First Name</label>
                                    <input type="text" id="editFirstName" name="first_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="editLastName">Last Name</label>
                                    <input type="text" id="editLastName" name="last_name">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="editPhone">Phone</label>
                                    <input type="tel" id="editPhone" name="phone">
                                </div>
                                <div class="form-group">
                                    <label for="editRole">Role</label>
                                    <select id="editRole" name="role" required>
                                        <option value="user">User</option>
                                        <option value="moderator">Moderator</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="editDepartment">Department</label>
                                    <input type="text" id="editDepartment" name="department">
                                </div>
                                <div class="form-group">
                                    <label for="editRfidTag">RFID Tag</label>
                                    <div class="rfid-input-group">
                                        <input type="text" id="editRfidTag" name="rfid_tag" placeholder="Enter RFID tag">
                                        <button type="button" class="btn-scan-rfid" 
                                                data-rfid-scan data-rfid-target="editRfidTag">
                                            Scan RFID
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Update User</button>
                                <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            return modal;
        }
        
        // Enhanced deleteUser with enterprise security
        function deleteUser(userId) {
            if (confirm('⚠️ WARNING: This will permanently delete the user and all their data.\n\nThis action cannot be undone!\n\nAre you absolutely sure you want to delete this user?')) {
                const formData = new FormData();
                formData.append('action', 'delete_user');
                formData.append('user_id', userId);
                formData.append('_token', '<?php echo $securityManager->generateCSRFToken(); ?>');
                
                // Show loading state
                const deleteBtn = event.target;
                deleteBtn.disabled = true;
                deleteBtn.textContent = 'Deleting...';
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadUsers();
                        showNotification('✅ User has been permanently deleted', 'success');
                    } else {
                        showNotification('❌ Error deleting user: ' + (data.error || 'Unknown error'), 'error');
                        deleteBtn.disabled = false;
                        deleteBtn.textContent = 'Delete';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('❌ Error deleting user: Network or server error', 'error');
                    deleteBtn.disabled = false;
                    deleteBtn.textContent = 'Delete';
                });
            }
        }
        
        // Enhanced notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                z-index: 10000;
                font-weight: 500;
                max-width: 400px;
                word-wrap: break-word;
                animation: slideIn 0.3s ease-out;
            `;
            
            // Apply type-specific styles
            switch(type) {
                case 'success':
                    notification.style.backgroundColor = '#d4edda';
                    notification.style.color = '#155724';
                    notification.style.border = '1px solid #c3e6cb';
                    break;
                case 'error':
                    notification.style.backgroundColor = '#f8d7da';
                    notification.style.color = '#721c24';
                    notification.style.border = '1px solid #f5c6cb';
                    break;
                default:
                    notification.style.backgroundColor = '#e3f2fd';
                    notification.style.color = '#0d47a1';
                    notification.style.border = '1px solid #bbdefb';
            }
            
            document.body.appendChild(notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-in';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 5000);
        }
        
        // Enhanced updateUser with CSRF protection
        function updateUser(userId, field, value) {
            const formData = new FormData();
            formData.append('action', 'update_user');
            formData.append('user_id', userId);
            formData.append('field', field);
            formData.append('value', value);
            formData.append('_token', '<?php echo $securityManager->generateCSRFToken(); ?>');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadUsers();
                    showNotification('User updated successfully', 'success');
                } else {
                    showNotification('Error updating user: ' + data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error updating user: Network issue', 'error');
            });
        }
        
        function toggleUserStatus(userId, newStatus) {
            const action = newStatus == 1 ? 'activate' : 'deactivate';
            if (confirm(`Are you sure you want to ${action} this user?`)) {
                updateUser(userId, 'is_active', newStatus);
            }
        }
        
        function exportUsers() {
            showNotification('Exporting users...', 'info');
            window.location.href = '?export=csv';
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Enhanced modal management
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                // Clear form if exists
                const form = modal.querySelector('form');
                if (form) {
                    form.reset();
                }
            }
        }
        
        // Close modals when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });
        
        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
    
    <!-- Enterprise Asset Loading -->
    <?php
    echo $assetOptimizer->loadJS(['users.js']);
    include '../includes/theme_script.php';
    
    // Complete performance monitoring
    $performanceManager->endTimer('admin_users_page');
    ?>
</body>
</html>
