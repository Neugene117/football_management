<?php
$totalUsers = (int) db_table_count('users');
$totalAppointments = (int) db_table_count('matches');
$totalPosts = (int) db_table_count('news');
$totalMessages = (int) db_table_count('notifications', 'is_read = 0');

$trendRows = db_fetch_all("SELECT MONTH(created_at) m, COUNT(*) c FROM matches WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 MONTH) GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY YEAR(created_at), MONTH(created_at)");
$trendValues = array_map('intval', array_column($trendRows, 'c'));
if (count($trendValues) < 6) {
    $trendValues = [12, 19, 15, 25, 22, 30];
}

$recentActivities = db_fetch_all('SELECT action, module, created_at, target_type FROM activity_logs ORDER BY created_at DESC LIMIT 4');

if (!function_exists('dashboard_time_ago')) {
    function dashboard_time_ago($datetime)
    {
        $timestamp = strtotime((string) $datetime);
        if (!$timestamp) {
            return 'Just now';
        }

        $diff = max(1, time() - $timestamp);
        if ($diff < 3600) {
            $mins = (int) floor($diff / 60);
            return ($mins > 1 ? $mins : 1) . ' minutes ago';
        }

        if ($diff < 86400) {
            $hours = (int) floor($diff / 3600);
            return $hours . ' hours ago';
        }

        $days = (int) floor($diff / 86400);
        return $days . ' days ago';
    }
}

$activityIconMap = [
    'users' => 'user-add',
    'news' => 'news',
    'teams' => 'team',
    'matches' => 'calendar',
    'approvals' => 'approval',
];

$quickActions = [
    ['index.php?page=news', 'New Blog Post', 'add'],
    ['index.php?page=teams', 'Add Team', 'team'],
    ['index.php?page=reports', 'Upload Reports', 'upload'],
    ['index.php?page=users', 'Add User', 'user-add'],
];
?>

<div class="dashboard-metrics">
    <div class="card dash-stat-card">
        <div class="dash-stat-icon"><?= icon_svg('users'); ?></div>
        <div>
            <div class="dash-stat-value" data-counter="<?= $totalUsers; ?>">0</div>
            <div class="dash-stat-label">Total Users</div>
        </div>
    </div>

    <div class="card dash-stat-card">
        <div class="dash-stat-icon"><?= icon_svg('calendar'); ?></div>
        <div>
            <div class="dash-stat-value" data-counter="<?= $totalAppointments; ?>">0</div>
            <div class="dash-stat-label">Appointments</div>
        </div>
    </div>

    <div class="card dash-stat-card">
        <div class="dash-stat-icon"><?= icon_svg('news'); ?></div>
        <div>
            <div class="dash-stat-value" data-counter="<?= $totalPosts; ?>">0</div>
            <div class="dash-stat-label">Blog Posts</div>
        </div>
    </div>

    <div class="card dash-stat-card">
        <div class="dash-stat-icon"><?= icon_svg('mail'); ?></div>
        <div>
            <div class="dash-stat-value" data-counter="<?= $totalMessages; ?>">0</div>
            <div class="dash-stat-label">Messages</div>
        </div>
    </div>
</div>

<div class="dashboard-grid mt-12">
    <section class="card dash-panel">
        <div class="card-head">
            <h3>Appointment Trends</h3>
            <div class="dash-tools">
                <button type="button" class="dash-tool-btn"><?= icon_svg('refresh'); ?></button>
                <button type="button" class="dash-tool-btn"><?= icon_svg('dots'); ?></button>
            </div>
        </div>
        <div class="card-body chart dash-chart">
            <canvas data-line-chart data-values="<?= e(implode(',', $trendValues)); ?>" data-color="#0d6b4e" data-fill="rgba(13,107,78,0.16)"></canvas>
        </div>
    </section>

    <section class="card dash-panel">
        <div class="card-head">
            <h3>Recent Activities</h3>
            <a class="dash-view-all" href="index.php?page=activity_logs">View All</a>
        </div>
        <div class="card-body dash-activity-list">
            <?php if (empty($recentActivities)): ?>
                <div class="empty-state">No recent activity found.</div>
            <?php else: ?>
                <?php foreach ($recentActivities as $row):
                    $module = (string) ($row['module'] ?? '');
                    $icon = $activityIconMap[$module] ?? 'dashboard';
                    $actionText = ucwords(str_replace('_', ' ', (string) ($row['action'] ?? 'Activity')));
                ?>
                    <div class="dash-activity-item">
                        <span class="dash-activity-icon"><?= icon_svg($icon); ?></span>
                        <div>
                            <p><?= e($actionText); ?></p>
                            <small><?= e(dashboard_time_ago($row['created_at'] ?? '')); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="card dash-panel">
        <div class="card-head">
            <h3>Quick Actions</h3>
        </div>
        <div class="card-body dash-actions-grid">
            <?php foreach ($quickActions as $act): ?>
                <a href="<?= e($act[0]); ?>" class="dash-action-btn">
                    <span><?= icon_svg($act[2]); ?></span>
                    <strong><?= e($act[1]); ?></strong>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</div>