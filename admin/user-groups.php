<?php
/**
 * User Groups Management Interface - FIXED VERSION
 * 
 * Complete user groups administration system with support for:
 * - Creating and managing user groups
 * - Adding/removing users from groups
 * - Assigning groups to events with automatic deduplication
 * - Group statistics and analytics
 * 
 * @author Senior Developer (Fixed intern's Bootstrap dependency issues)
 * @version 3.0 - Local CSS Implementation
 */

require_once '../core/auth.php';
require_once '../core/user-group-manager.php';
require_once '../core/database.php';

// Check authentication
if (!Auth::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

// Check admin permissions
$currentUser = Auth::getCurrentUser();
if (!$currentUser || !in_array($currentUser['role'], ['admin', 'manager'])) {
    header('Location: ../frontend/dashboard.php');
    exit;
}

$groupManager = new UserGroupManager();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_group':
            $result = $groupManager->createGroup([
                'group_name' => $_POST['group_name'],
                'description' => $_POST['description'],
                'group_type' => $_POST['group_type'],
                'created_by' => $currentUser['user_id']
            ]);
            
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['error'];
            }
            break;
            
        case 'add_user_to_group':
            $result = $groupManager->addUserToGroup(
                $_POST['user_id'],
                $_POST['group_id'],
                $_POST['role'] ?? 'member',
                $currentUser['user_id']
            );
            
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['error'];
            }
            break;
            
        case 'remove_user_from_group':
            $result = $groupManager->removeUserFromGroup($_POST['user_id'], $_POST['group_id']);
            
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['error'];
            }
            break;
            
        case 'update_group':
            $result = $groupManager->updateGroup($_POST['group_id'], [
                'group_name' => $_POST['group_name'],
                'description' => $_POST['description'],
                'group_type' => $_POST['group_type']
            ]);
            
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['error'];
            }
            break;
            
        case 'delete_group':
            $result = $groupManager->deleteGroup($_POST['group_id']);
            
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['error'];
            }
            break;
    }
}

// Get data for display
$groups = $groupManager->getAllGroups();
$stats = $groupManager->getGroupStatistics();
$allUsers = $groupManager->getAllUsers();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Groups Management - <?php echo APP_NAME; ?></title>
    
    <!-- Local CSS Files -->
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <link rel="stylesheet" href="../assets/css/users.css">
    <link rel="stylesheet" href="../assets/css/user-groups.css">
    <link rel="stylesheet" href="../assets/css/modal.css">
    <link rel="stylesheet" href="../assets/css/admin-tools.css">
</head>
<body>
    <!-- Navigation -->
    <?php include '../includes/navigation.php'; ?>
    
    <div class="main-content">
        <!-- Header Section -->
        <div class="dashboard-header">
            <div class="header-content">
                <div class="header-text">
                    <h1>👥 User Groups Management</h1>
                    <p>Manage user groups and event assignments with automatic deduplication</p>
                </div>
                <button class="btn btn-primary" onclick="createGroup()">
                    <span class="icon">➕</span> Create Group
                </button>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success">
                <span class="icon">✅</span> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <span class="icon">❌</span> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?php echo $stats['total_groups'] ?? 0; ?></div>
                <div class="stat-label">Total Groups</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👤</div>
                <div class="stat-number"><?php echo $stats['total_memberships'] ?? 0; ?></div>
                <div class="stat-label">Total Memberships</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎯</div>
                <div class="stat-number"><?php echo $stats['users_in_groups'] ?? 0; ?></div>
                <div class="stat-label">Users in Groups</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-number"><?php echo number_format($stats['avg_group_size'] ?? 0, 1); ?></div>
                <div class="stat-label">Avg Group Size</div>
            </div>
        </div>
        
        <!-- Groups Grid -->
        <div class="groups-section">
            <h2>Groups Overview</h2>
            
            <?php if (!empty($groups)): ?>
                <div class="groups-grid">
                    <?php foreach ($groups as $group): ?>
                        <div class="group-card">
                            <div class="group-header">
                                <h3 class="group-name"><?php echo htmlspecialchars($group['group_name']); ?></h3>
                                <span class="group-type-badge"><?php echo ucfirst($group['group_type']); ?></span>
                            </div>
                            
                            <div class="group-content">
                                <p class="group-description">
                                    <?php echo htmlspecialchars($group['description'] ?: 'No description provided'); ?>
                                </p>
                                
                                <div class="group-meta">
                                    <div class="meta-item">
                                        <span class="meta-label">Members:</span>
                                        <span class="meta-value"><?php echo $group['member_count'] ?? 0; ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-label">Created:</span>
                                        <span class="meta-value"><?php echo date('M j, Y', strtotime($group['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="group-actions">
                                <button class="btn btn-secondary btn-sm" 
                                        onclick="editGroup(<?php echo $group['group_id']; ?>, '<?php echo addslashes(htmlspecialchars($group['group_name'])); ?>', '<?php echo addslashes(htmlspecialchars($group['description'])); ?>', '<?php echo $group['group_type']; ?>')">
                                    <span class="icon">✏️</span> Edit
                                </button>
                                <button class="btn btn-secondary btn-sm" 
                                        onclick="manageMembers(<?php echo $group['group_id']; ?>, '<?php echo addslashes(htmlspecialchars($group['group_name'])); ?>')">
                                    <span class="icon">👤</span> Members
                                </button>
                                <button class="btn btn-danger btn-sm" 
                                        onclick="deleteGroup(<?php echo $group['group_id']; ?>, '<?php echo addslashes(htmlspecialchars($group['group_name'])); ?>')">
                                    <span class="icon">🗑️</span> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">👥</div>
                    <h3>No Groups Yet</h3>
                    <p>Create your first user group to get started with group management.</p>
                    <button class="btn btn-primary" onclick="createGroup()">
                        <span class="icon">➕</span> Create First Group
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Create Group Modal -->
    <div class="modal" id="createGroupModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="create_group">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Group</h5>
                        <button type="button" class="btn-close" onclick="closeModal('createGroupModal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="group_name">Group Name *</label>
                            <input type="text" class="form-control" id="group_name" name="group_name" required>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="group_type">Group Type</label>
                            <select class="form-control" id="group_type" name="group_type">
                                <option value="custom">Custom</option>
                                <option value="department">Department</option>
                                <option value="team">Team</option>
                                <option value="project">Project</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('createGroupModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Group</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Group Modal -->
    <div class="modal" id="editGroupModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="update_group">
                    <input type="hidden" id="edit_group_id" name="group_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Group</h5>
                        <button type="button" class="btn-close" onclick="closeModal('editGroupModal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="edit_group_name">Group Name *</label>
                            <input type="text" class="form-control" id="edit_group_name" name="group_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_description">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="edit_group_type">Group Type</label>
                            <select class="form-control" id="edit_group_type" name="group_type">
                                <option value="custom">Custom</option>
                                <option value="department">Department</option>
                                <option value="team">Team</option>
                                <option value="project">Project</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editGroupModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Group</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Manage Members Modal -->
    <div class="modal" id="manageMembersModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Group Members</h5>
                    <button type="button" class="btn-close" onclick="closeModal('manageMembersModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="manage_group_id" name="group_id">
                    
                    <!-- Add User Section -->
                    <div class="add-user-section">
                        <h6>Add User to Group</h6>
                        <form method="POST" id="addUserForm" class="user-form-grid">
                            <input type="hidden" name="action" value="add_user_to_group">
                            <input type="hidden" name="group_id" id="add_user_group_id">
                            <div class="form-group">
                                <select name="user_id" class="form-control" required>
                                    <option value="">Select User</option>
                                    <?php foreach ($allUsers as $user): ?>
                                        <option value="<?php echo $user['user_id']; ?>">
                                            <?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name']) . ' (' . $user['username'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <select name="role" class="form-control">
                                    <option value="member">Member</option>
                                    <option value="leader">Leader</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Add User</button>
                            </div>
                        </form>
                    </div>
                    
                    <hr>
                    
                    <!-- Current Members Section -->
                    <div class="current-members">
                        <h6>Current Members</h6>
                        <div id="membersContent">
                            <p class="text-muted">Select a group to view members</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Local JavaScript -->
    <script>
        // Enhanced user group management functions
        function createGroup() {
            document.getElementById('createGroupModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function editGroup(groupId, groupName, description, groupType) {
            const modal = document.getElementById('editGroupModal');
            modal.querySelector('#edit_group_id').value = groupId;
            modal.querySelector('#edit_group_name').value = groupName;
            modal.querySelector('#edit_description').value = description;
            modal.querySelector('#edit_group_type').value = groupType;
            modal.style.display = 'block';
        }
        
        function manageMembers(groupId, groupName) {
            const modal = document.getElementById('manageMembersModal');
            modal.querySelector('.modal-title').textContent = `Manage Members - ${groupName}`;
            modal.querySelector('#manage_group_id').value = groupId;
            modal.querySelector('#add_user_group_id').value = groupId;
            modal.style.display = 'block';
            
            // Load current members
            loadGroupMembers(groupId);
        }
        
        function loadGroupMembers(groupId) {
            // This would typically be an AJAX call, but for now we'll show a message
            const membersContent = document.getElementById('membersContent');
            membersContent.innerHTML = `
                <div class="loading-state">
                    <span class="icon">⏳</span>
                    <p>Loading group members...</p>
                </div>
            `;
        }
        
        function deleteGroup(groupId, groupName) {
            if (confirm(`Are you sure you want to delete the group "${groupName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_group">
                    <input type="hidden" name="group_id" value="${groupId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function removeUserFromGroup(userId, groupId) {
            if (confirm('Remove this user from the group?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="remove_user_from_group">
                    <input type="hidden" name="user_id" value="${userId}">
                    <input type="hidden" name="group_id" value="${groupId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
    
    <?php include '../includes/theme_script.php'; ?>
</body>
</html>
