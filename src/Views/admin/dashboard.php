<?php include __DIR__ . '/../shared/header.php'; ?>

<div class="dashboard-header">
    <h1>Admin Dashboard</h1>
    <div class="header-actions">
        <a href="/admin/apprentices/create" class="btn btn-primary">
            <span class="icon">+</span> Register Apprentice
        </a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Apprentices</div>
        <div class="stat-value"><?= $stats['totalApprentices'] ?></div>
        <div class="stat-change">
            <span class="<?= $stats['apprenticeChange'] >= 0 ? 'positive' : 'negative' ?>">
                <?= $stats['apprenticeChange'] >= 0 ? '↑' : '↓' ?> 
                <?= abs($stats['apprenticeChange']) ?>% from last month
            </span>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">Present Today</div>
        <div class="stat-value"><?= $stats['presentToday'] ?></div>
        <div class="stat-subtitle"><?= $stats['attendanceRate'] ?>% attendance rate</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">At Risk (>7.5%)</div>
        <div class="stat-value" style="color: var(--warning-color)"><?= count($atRiskApprentices) ?></div>
        <div class="stat-subtitle">Approaching absence limit</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">Average Check-in Time</div>
        <div class="stat-value"><?= $stats['avgCheckInTime'] ?></div>
        <div class="stat-subtitle"><?= $stats['latePercentage'] ?>% late arrivals</div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">At Risk Apprentices</h2>
            <a href="/admin/apprentices?status=at_risk" class="btn-link">View All</a>
        </div>
        <div class="card-body">
            <?php if (empty($atRiskApprentices)): ?>
                <p class="empty-state">No apprentices at risk</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Year</th>
                            <th>Absence %</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($atRiskApprentices as $apprentice): ?>
                        <tr>
                            <td><?= htmlspecialchars($apprentice->getFullName()) ?></td>
                            <td><?= $apprentice->getYearLevel() ?></td>
                            <td>
                                <span class="absence-badge <?= $apprentice->absencePercentage >= 10 ? 'danger' : 'warning' ?>">
                                    <?= number_format($apprentice->absencePercentage, 1) ?>%
                                </span>
                            </td>
                            <td>
                                <a href="/admin/apprentices/<?= $apprentice->getId() ?>" class="btn btn-sm">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Live Check-ins</h2>
        </div>
        <div class="card-body">
            <div class="live-checkins">
                <?php foreach ($recentCheckIns as $checkIn): ?>
                <div class="check-in-item">
                    <div class="check-in-user"><?= htmlspecialchars($checkIn->userName) ?></div>
                    <div class="check-in-time"><?= $checkIn->time ?></div>
                    <span class="check-in-status <?= $checkIn->isLate ? 'late' : 'on-time' ?>">
                        <?= $checkIn->isLate ? 'Late' : 'On Time' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Auto-refresh live check-ins every 10 seconds
    setInterval(() => {
        fetch('/api/attendance/live')
            .then(res => res.json())
            .then(data => updateLiveCheckIns(data));
    }, 10000);
</script>

<?php include __DIR__ . '/../shared/footer.php'; ?>
