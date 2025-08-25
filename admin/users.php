<?php
require_once '../core/config.php';
require_once '../core/database.php';
require_once '../core/auth.php';

// Check if user is admin
if (!Auth::isLoggedIn() || !Auth::hasRole(['admin'])) {
    http_response_code(403);
    header('Location: ../auth/login.php');
    exit;
}

// Initialize database connection
$db = getDB();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'load_users':
                $page = max(1, intval($_POST['page'] ?? 1));
                $limit = max(10, min(100, intval($_POST['limit'] ?? 25)));
                $offset = ($page - 1) * $limit;
                $search = trim($_POST['search'] ?? '');
                $role = $_POST['role'] ?? '';
                $status = $_POST['status'] ?? '';
                
                $where = ['1=1'];
                $params = [];
                
                if ($search) {
                    $where[] = "(u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
                    $searchParam = "%$search%";
                    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
                }
                
                if ($role) {
                    $where[] = "u.role = ?";
                    $params[] = $role;
                }
                
                if ($status === 'active') {
                    $where[] = "u.is_active = 1";
                } elseif ($status === 'inactive') {
                    $where[] = "u.is_active = 0";
                }
                
                $whereClause = implode(' AND ', $where);
                
                // Get total count
                $stmt = $db->prepare("SELECT COUNT(*) FROM Users u WHERE $whereClause");
                $stmt->execute($params);
                $total = $stmt->fetchColumn();
                
                // Get users
                $stmt = $db->prepare("
                    SELECT u.*, 
                           COUNT(DISTINCT c.checkin_id) as total_checkins,
                           MAX(c.checkin_time) as last_checkin
                    FROM Users u 
                    LEFT JOIN CheckIn c ON u.user_id = c.user_id 
                    WHERE $whereClause 
                    GROUP BY u.user_id 
                    ORDER BY u.created_at DESC 
                    LIMIT ? OFFSET ?
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
                $userData = [
                    'username' => trim($_POST['username'] ?? ''),
                    'email' => trim($_POST['email'] ?? ''),
                    'first_name' => trim($_POST['first_name'] ?? ''),
                    'last_name' => trim($_POST['last_name'] ?? ''),
                    'role' => $_POST['role'] ?? 'user',
                    'department' => trim($_POST['department'] ?? ''),
                    'rfid_tag' => trim($_POST['rfid_tag'] ?? ''),
                    'password' => $_POST['password'] ?? ''
                ];
                
                // Validation
                if (empty($userData['username']) || empty($userData['email']) || empty($userData['first_name']) || empty($userData['password'])) {
                    throw new Exception('Required fields are missing');
                }
                
                if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email address');
                }
                
                if (strlen($userData['password']) < 8) {
                    throw new Exception('Password must be at least 8 characters long');
                }
                
                // Check for duplicates
                $stmt = $db->prepare("SELECT COUNT(*) FROM Users WHERE username = ? OR email = ?");
                $stmt->execute([$userData['username'], $userData['email']]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Username or email already exists');
                }
                
                if ($userData['rfid_tag']) {
                    $stmt = $db->prepare("SELECT COUNT(*) FROM Users WHERE rfid_tag = ?");
                    $stmt->execute([$userData['rfid_tag']]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception('RFID tag already assigned to another user');
                    }
                }
                
                // Create user
                $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("
                    INSERT INTO Users (username, email, password, first_name, last_name, role, department, rfid_tag, is_active, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([
                    $userData['username'],
                    $userData['email'], 
                    $hashedPassword,
                    $userData['first_name'],
                    $userData['last_name'],
                    $userData['role'],
                    $userData['department'],
                    $userData['rfid_tag'] ?: null
                ]);
                
                $userId = $db->lastInsertId();
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO ActivityLog (user_id, action, details, ip_address) 
                    VALUES (?, 'user_created', 'User created by admin', ?)
                ");
                $stmt->execute([
                    Auth::getCurrentUser()['user_id'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                
                echo json_encode(['success' => true, 'user_id' => $userId]);
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
                    $stmt = $db->prepare("SELECT COUNT(*) FROM Users WHERE $field = ? AND user_id != ?");
                    $stmt->execute([$value, $userId]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception(ucfirst($field) . ' already exists');
                    }
                }
                
                // Update user
                $stmt = $db->prepare("UPDATE Users SET $field = ? WHERE user_id = ?");
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
                    FROM Users 
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
                            $stmt = $db->prepare("SELECT COUNT(*) FROM Users WHERE $field = ? AND user_id != ?");
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
                $sql = "UPDATE Users SET " . implode(', ', $updateFields) . " WHERE user_id = ?";
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
                    
                    // Delete from CheckIn table
                    $stmt = $db->prepare("DELETE FROM CheckIn WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    // Delete from EventRegistration table
                    $stmt = $db->prepare("DELETE FROM EventRegistration WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    // Delete from ActivityLog table
                    $stmt = $db->prepare("DELETE FROM ActivityLog WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    // Delete from password_resets table
                    $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    // Delete from user_settings table
                    $stmt = $db->prepare("DELETE FROM user_settings WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    // Delete from AccessLogs table
                    $stmt = $db->prepare("DELETE FROM AccessLogs WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    // Finally delete the user
                    $stmt = $db->prepare("DELETE FROM Users WHERE user_id = ?");
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
                $stmt = $db->prepare("UPDATE Users SET password = ? WHERE user_id = ?");
                $stmt->execute([$hashedPassword, $userId]);
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO ActivityLog (user_id, action, details, ip_address) 
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
                        $stmt = $db->prepare("SELECT COUNT(*) FROM Users WHERE username = ? OR email = ?");
                        $stmt->execute([$userData['username'], $userData['email']]);
                        if ($stmt->fetchColumn() > 0) {
                            continue;
                        }
                        
                        // Create user
                        $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
                        $stmt = $db->prepare("
                            INSERT INTO Users (username, email, password, first_name, last_name, role, department, rfid_tag, is_active, created_at) 
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
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Get statistics for dashboard
$stats = [];
try {
    // Total users
    $stmt = $db->prepare("SELECT COUNT(*) FROM Users WHERE is_active = 1");
    $stmt->execute();
    $stats['total_users'] = $stmt->fetchColumn();
    
    // New users this month
    $stmt = $db->prepare("SELECT COUNT(*) FROM Users WHERE is_active = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
    $stmt->execute();
    $stats['new_users_month'] = $stmt->fetchColumn();
    
    // Users by role
    $stmt = $db->prepare("SELECT role, COUNT(*) as count FROM Users WHERE is_active = 1 GROUP BY role");
    $stmt->execute();
    $stats['users_by_role'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Recent activity
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM ActivityLog 
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
    <title>User Management - Electronic Check-in System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <link rel="stylesheet" href="../assets/css/users.css">
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="admin-header">
        <div class="container">
            <h1>User Management</h1>
            <p>Manage users, roles, and permissions</p>
        </div>
    </div>
    
    <div class="container">
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_users'] ?? 0; ?></div>
                <div class="stat-label">Total Active Users</div>
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
                        <input type="text" id="rfid_tag" name="rfid_tag">
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
                        <div style="color: green;">
                            Successfully imported ${data.imported} users.
                            ${data.errors.length > 0 ? '<br>Errors: ' + data.errors.join('<br>') : ''}
                        </div>
                    `;
                    loadUsers();
                } else {
                    document.getElementById('importResults').innerHTML = `
                        <div style="color: red;">Error: ${data.error}</div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('importResults').innerHTML = `
                    <div style="color: red;">Error importing users</div>
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
                                    <input type="text" id="editRfidTag" name="rfid_tag">
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
        
        function deleteUser(userId) {
            if (confirm('⚠️ WARNING: This will permanently delete the user and all their data.\n\nThis action cannot be undone!\n\nAre you absolutely sure you want to delete this user?')) {
                const formData = new FormData();
                formData.append('action', 'delete_user');
                formData.append('user_id', userId);
                
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
                        alert('✅ User has been permanently deleted');
                    } else {
                        alert('❌ Error deleting user: ' + (data.error || 'Unknown error'));
                        deleteBtn.disabled = false;
                        deleteBtn.textContent = 'Delete';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('❌ Error deleting user: Network or server error');
                    deleteBtn.disabled = false;
                    deleteBtn.textContent = 'Delete';
                });
            }
        }
        
        function toggleUserStatus(userId, newStatus) {
            const action = newStatus == 1 ? 'activate' : 'deactivate';
            if (confirm(`Are you sure you want to ${action} this user?`)) {
                updateUser(userId, 'is_active', newStatus);
            }
        }        function updateUser(userId, field, value) {
            const formData = new FormData();
            formData.append('action', 'update_user');
            formData.append('user_id', userId);
            formData.append('field', field);
            formData.append('value', value);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadUsers();
                } else {
                    alert('Error updating user: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating user');
            });
        }
        
        function exportUsers() {
            window.location.href = '?export=csv';
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Close modals when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });
    </script>
    
    <?php include '../includes/theme_script.php'; ?>
</body>
</html>
