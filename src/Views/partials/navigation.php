<?php
use RfidCheckin\Core\Session;
$session = new Session();
$userRole = $session->getUserRole();
$userName = $session->getUserName();
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand" href="/dashboard">
            <i class="fas fa-id-card me-2"></i>
            RFID Check-in System
        </a>

        <!-- Mobile menu button -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navigation Menu -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <!-- Dashboard -->
                <li class="nav-item">
                    <a class="nav-link" href="/dashboard">
                        <i class="fas fa-tachometer-alt me-1"></i>
                        Dashboard
                    </a>
                </li>

                <!-- Events -->
                <li class="nav-item">
                    <a class="nav-link" href="/events">
                        <i class="fas fa-calendar me-1"></i>
                        Events
                    </a>
                </li>

                <!-- Groups (Manager/Admin) -->
                <?php if (in_array($userRole, ['manager', 'admin'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="/groups">
                        <i class="fas fa-users me-1"></i>
                        Groups
                    </a>
                </li>
                <?php endif; ?>

                <!-- Users (Manager/Admin) -->
                <?php if (in_array($userRole, ['manager', 'admin'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="/users">
                        <i class="fas fa-user-friends me-1"></i>
                        Users
                    </a>
                </li>
                <?php endif; ?>

                <!-- Reports (Manager/Admin) -->
                <?php if (in_array($userRole, ['manager', 'admin'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="/reports">
                        <i class="fas fa-chart-bar me-1"></i>
                        Reports
                    </a>
                </li>
                <?php endif; ?>

                <!-- Admin Panel (Admin only) -->
                <?php if ($userRole === 'admin'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-cog me-1"></i>
                        Admin
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="/admin">
                            <i class="fas fa-tachometer-alt me-1"></i>
                            Admin Dashboard
                        </a></li>
                        <li><a class="dropdown-item" href="/admin/users">
                            <i class="fas fa-users-cog me-1"></i>
                            User Management
                        </a></li>
                        <li><a class="dropdown-item" href="/admin/settings">
                            <i class="fas fa-sliders-h me-1"></i>
                            System Settings
                        </a></li>
                        <li><a class="dropdown-item" href="/admin/rfid">
                            <i class="fas fa-microchip me-1"></i>
                            RFID Devices
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/admin/logs">
                            <i class="fas fa-file-alt me-1"></i>
                            System Logs
                        </a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Right side menu -->
            <ul class="navbar-nav">
                <!-- Notifications -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-bell me-1"></i>
                        <span class="badge bg-danger rounded-pill notification-count" style="font-size: 0.6em;">0</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" style="width: 300px;">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <li><div class="dropdown-item-text text-muted">No new notifications</div></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="/notifications">View All</a></li>
                    </ul>
                </li>

                <!-- User Menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        <?= htmlspecialchars($userName) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/profile">
                            <i class="fas fa-user me-1"></i>
                            My Profile
                        </a></li>
                        <li><a class="dropdown-item" href="/attendance">
                            <i class="fas fa-clock me-1"></i>
                            My Attendance
                        </a></li>
                        <li><a class="dropdown-item" href="/profile/password">
                            <i class="fas fa-key me-1"></i>
                            Change Password
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="/logout" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($session->getCsrfToken()) ?>">
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="fas fa-sign-out-alt me-1"></i>
                                    Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>