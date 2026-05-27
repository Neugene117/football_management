<?php
$myTeamId = (int) (current_user()['entity_id'] ?? 0);

$totalUsers = $myTeamId > 0
    ? (int) (db_fetch_one('SELECT COUNT(*) total FROM players WHERE team_id = ?', 'i', [$myTeamId])['total'] ?? 0)
    : (int) db_table_count('players');

$totalAppointments = $myTeamId > 0
    ? (int) (db_fetch_one('SELECT COUNT(*) total FROM matches WHERE home_team_id = ? OR away_team_id = ?', 'ii', [$myTeamId, $myTeamId])['total'] ?? 0)
    : (int) db_table_count('matches');

$totalPosts = (int) db_table_count('news');
$totalMessages = (int) db_table_count('notifications', 'is_read = 0');

$trendRows = $myTeamId > 0
    ? db_fetch_all("SELECT MONTH(created_at) m, COUNT(*) c FROM match_lineups WHERE team_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 MONTH) GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY YEAR(created_at), MONTH(created_at)", 'i', [$myTeamId])
    : db_fetch_all("SELECT MONTH(created_at) m, COUNT(*) c FROM match_lineups WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 MONTH) GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY YEAR(created_at), MONTH(created_at)");

$trendValues = array_map('intval', array_column($trendRows, 'c'));
if (count($trendValues) < 6) {
    $trendValues = [8, 14, 11, 20, 18, 24];
}

$upcoming = $myTeamId > 0
    ? db_fetch_all("SELECT match_date, status FROM matches WHERE home_team_id = ? OR away_team_id = ? ORDER BY match_date ASC LIMIT 4", 'ii', [$myTeamId, $myTeamId])
    : db_fetch_all("SELECT match_date, status FROM matches ORDER BY match_date ASC LIMIT 4");

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

$quickActions = [
    ['index.php?page=lineups', 'Submit Lineup', 'approval'],
    ['index.php?page=squad', 'Manage Squad', 'users'],
    ['index.php?page=matches', 'View Matches', 'calendar'],
    ['index.php?page=profile', 'Update Profile', 'profile'],
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
            <a class="dash-view-all" href="index.php?page=matches">View All</a>
        </div>
        <div class="card-body dash-activity-list">
            <?php if (empty($upcoming)): ?>
                <div class="empty-state">No recent activity found.</div>
            <?php else: ?>
                <?php foreach ($upcoming as $row): ?>
                    <div class="dash-activity-item">
                        <span class="dash-activity-icon"><?= icon_svg('calendar'); ?></span>
                        <div>
                            <p>Match status: <?= e(ucwords(str_replace('_', ' ', (string) $row['status']))); ?></p>
                            <small><?= e(dashboard_time_ago($row['match_date'] ?? '')); ?></small>
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