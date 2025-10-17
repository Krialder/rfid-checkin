<?php include __DIR__ . '/../shared/header.php'; ?>

<div class="dashboard-header">
    <h1>Welcome, <?= htmlspecialchars($user->getFirstName()) ?>!</h1>
    <p class="subtitle">Track your attendance and improve your punctuality</p>
</div>

<div class="attendance-overview card">
    <div class="card-header">
        <h2 class="card-title">Your Attendance Status</h2>
    </div>
    <div class="card-body">
        <div class="attendance-gauge-container">
            <div class="attendance-gauge">
                <svg width="200" height="100" viewBox="0 0 200 100">
                    <path d="M 20 80 A 60 60 0 0 1 180 80" class="gauge-background" />
                    <path d="M 20 80 A 60 60 0 0 1 180 80" 
                          class="gauge-fill <?= $overallStats->getAbsencePercentage() >= 10 ? 'danger' : ($overallStats->getAbsencePercentage() >= 7.5 ? 'warning' : 'good') ?>"
                          style="stroke-dasharray: <?= $overallStats->getAbsencePercentage() * 1.88 ?> 188" />
                </svg>
                <div class="gauge-text">
                    <div class="gauge-percentage"><?= number_format($overallStats->getAbsencePercentage(), 1) ?>%</div>
                    <div class="gauge-label">Absence Rate</div>
                </div>
            </div>
        </div>
        
        <div class="attendance-details">
            <div class="detail-item">
                <span class="detail-label">Days Present:</span>
                <span class="detail-value"><?= $overallStats->getDaysPresent() ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Days Absent:</span>
                <span class="detail-value"><?= $overallStats->getDaysAbsent() ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Late Arrivals:</span>
                <span class="detail-value"><?= $overallStats->getLateCount() ?></span>
            </div>
        </div>
        
        <?php if ($daysUntilLimit !== null): ?>
        <div class="alert alert-<?= $daysUntilLimit <= 5 ? 'danger' : 'warning' ?>">
            <strong>Warning:</strong> You have <?= $daysUntilLimit ?> days of allowed absence remaining before reaching the 10% limit.
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="insights-section">
    <h2>Your Attendance Insights</h2>
    <div class="insights-grid">
        <?php foreach ($insights as $insight): ?>
        <div class="insight-card <?= $insight->getType() ?>">
            <div class="insight-icon"><?= $insight->getIcon() ?></div>
            <div class="insight-content">
                <h3><?= htmlspecialchars($insight->getTitle()) ?></h3>
                <p><?= htmlspecialchars($insight->getMessage()) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="attendance-calendar card">
    <div class="card-header">
        <h2 class="card-title">Your Attendance Calendar</h2>
    </div>
    <div class="card-body">
        <div id="attendanceCalendar" data-attendance='<?= json_encode($recentAttendance) ?>'></div>
    </div>
</div>

<div class="goals-section card">
    <div class="card-header">
        <h2 class="card-title">Your Goals</h2>
        <button class="btn btn-secondary btn-sm" onclick="setNewGoal()">Set New Goal</button>
    </div>
    <div class="card-body">
        <?php if (!empty($activeGoals)): ?>
            <?php foreach ($activeGoals as $goal): ?>
            <div class="goal-item">
                <div class="goal-header">
                    <h4><?= htmlspecialchars($goal->getTitle()) ?></h4>
                    <span class="goal-progress"><?= $goal->getProgress() ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $goal->getProgress() ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="empty-state">No active goals. Set a goal to improve your attendance!</p>
        <?php endif; ?>
    </div>
</div>

<script src="/assets/js/apprentice-dashboard.js"></script>

<?php include __DIR__ . '/../shared/footer.php'; ?>
