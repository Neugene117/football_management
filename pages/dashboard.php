<?php
$totalTeams = db_table_count('teams');
$totalPlayers = db_table_count('players');
$totalMatches = db_table_count('matches');
$totalStadiums = db_table_count('stadiums');
$activeSeasons = db_table_count('seasons', 'is_active = 1');
$totalOfficials = db_table_count('match_officials');
$totalNews = db_table_count('news');

$pendingApprovals = db_table_count('approvals', 'status = ?', 's', ['pending']);

$prCol = pick_status_column('player_rankings');
if ($prCol) {
    $pendingApprovals += db_table_count('player_rankings', "`{$prCol}` = 'pending'");
}
$psCol = pick_status_column('player_statistics');
if ($psCol) {
    $pendingApprovals += db_table_count('player_statistics', "`{$psCol}` = 'pending'");
}
$rtCol = pick_status_column('player_ratings');
if ($rtCol) {
    $pendingApprovals += db_table_count('player_ratings', "`{$rtCol}` = 'pending'");
}

$recentActivities = db_fetch_all(
    'SELECT al.*, u.full_name FROM activity_logs al LEFT JOIN users u ON u.id = al.user_id ORDER BY al.created_at DESC LIMIT 8'
);

$approvalRequests = db_fetch_all(
    'SELECT a.*, u.full_name FROM approvals a LEFT JOIN users u ON u.id = a.submitted_by ORDER BY a.created_at DESC LIMIT 6'
);

$latestTeams = db_fetch_all('SELECT name, city, founded_year, created_at, is_active FROM teams ORDER BY created_at DESC LIMIT 6');
$federationActions = db_fetch_all("SELECT action, module, created_at FROM activity_logs WHERE module IN ('teams','approvals','users','news') ORDER BY created_at DESC LIMIT 8");
$approvalNotifications = db_fetch_all('SELECT title, created_at, is_read FROM notifications ORDER BY created_at DESC LIMIT 8');

$teamRegRows = db_fetch_all("SELECT DATE_FORMAT(created_at, '%b') as m, COUNT(*) c FROM teams WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY YEAR(created_at), MONTH(created_at)");
$teamRegVals = array_column($teamRegRows, 'c');
if (count($teamRegVals) < 2) {
    $teamRegVals = [2, 4, 3, 6, 5, 7];
}

$matchStats = [];
$matchStatuses = ['scheduled', 'lineup_pending', 'lineup_approved', 'in_progress', 'completed', 'postponed'];
foreach ($matchStatuses as $s) {
    $matchStats[] = db_table_count('matches', 'status = ?', 's', [$s]);
}
if (array_sum($matchStats) === 0) {
    $matchStats = [3, 6, 4, 2, 5, 1];
}

$rankingApprovedVals = [];
if ($prCol) {
    $rows = db_fetch_all("SELECT MONTH(updated_at) m, COUNT(*) c FROM player_rankings WHERE `{$prCol}` = 'approved' AND updated_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY MONTH(updated_at) ORDER BY MONTH(updated_at)");
    $rankingApprovedVals = array_column($rows, 'c');
}
if (count($rankingApprovedVals) < 2) {
    $rankingApprovedVals = [1, 2, 2, 3, 5, 4];
}

$stats = [
    ['Total Teams', $totalTeams, 'team'],
    ['Total Players', $totalPlayers, 'users'],
    ['Total Matches', $totalMatches, 'dashboard'],
    ['Pending Approvals', $pendingApprovals, 'approval'],
    ['Total Stadiums', $totalStadiums, 'stadium'],
    ['Active Seasons', $activeSeasons, 'season'],
    ['Total Officials', $totalOfficials, 'users'],
    ['Total News', $totalNews, 'news'],
];
?>

<div class="grid stats-grid">
    <?php foreach ($stats as $s): ?>
        <div class="card stat-card">
            <div class="stat-icon"><?= icon_svg($s[2]); ?></div>
            <div>
                <div class="stat-value" data-counter="<?= (int) $s[1]; ?>">0</div>
                <div class="stat-label"><?= e($s[0]); ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="two-col mt-12">
    <div class="card">
        <div class="card-head"><h3>Team Registrations Chart</h3></div>
        <div class="card-body chart"><canvas data-line-chart data-values="<?= e(implode(',', $teamRegVals)); ?>"></canvas></div>
    </div>
    <div class="card">
        <div class="card-head"><h3>Match Statistics Chart</h3></div>
        <div class="card-body chart"><canvas data-bar-chart data-values="<?= e(implode(',', $matchStats)); ?>"></canvas></div>
    </div>
</div>

<div class="two-col mt-12">
    <div class="card">
        <div class="card-head"><h3>Player Ranking Approvals</h3></div>
        <div class="card-body chart"><canvas data-line-chart data-values="<?= e(implode(',', $rankingApprovedVals)); ?>"></canvas></div>
    </div>
    <div class="card">
        <div class="card-head"><h3>Match Approval Requests</h3></div>
        <div class="card-body panel-list">
            <?php if (empty($approvalRequests)): ?>
                <div class="empty-state">No approval requests found.</div>
            <?php else: ?>
                <?php foreach ($approvalRequests as $a): ?>
                    <div class="list-item">
                        <div>
                            <div class="list-title"><?= e(ucfirst($a['item_type'])); ?> #<?= (int) $a['item_id']; ?></div>
                            <div class="small muted">By <?= e($a['full_name'] ?: 'Unknown'); ?></div>
                        </div>
                        <?= status_badge($a['status']); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="three-col mt-12">
    <div class="card">
        <div class="card-head"><h3>Recent Activities</h3></div>
        <div class="card-body panel-list">
            <?php if (empty($recentActivities)): ?>
                <div class="empty-state">No recent activities.</div>
            <?php else: ?>
                <?php foreach ($recentActivities as $act): ?>
                    <div class="list-item">
                        <div>
                            <div class="list-title"><?= e($act['action']); ?> <span class="muted">(<?= e($act['module']); ?>)</span></div>
                            <div class="small muted"><?= e($act['full_name'] ?: 'System'); ?> - <?= e(date('d M H:i', strtotime($act['created_at']))); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3>Latest Registered Teams</h3></div>
        <div class="card-body panel-list">
            <?php if (empty($latestTeams)): ?>
                <div class="empty-state">No teams yet.</div>
            <?php else: ?>
                <?php foreach ($latestTeams as $team): ?>
                    <div class="list-item">
                        <div>
                            <div class="list-title"><?= e($team['name']); ?></div>
                            <div class="small muted"><?= e($team['city'] ?: 'N/A'); ?> • Founded <?= e($team['founded_year'] ?: '-'); ?></div>
                        </div>
                        <?= status_badge(((int) $team['is_active'] === 1) ? 'active' : 'pending'); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3>Approval Notifications</h3></div>
        <div class="card-body panel-list">
            <?php if (empty($approvalNotifications)): ?>
                <div class="empty-state">No notifications found.</div>
            <?php else: ?>
                <?php foreach ($approvalNotifications as $n): ?>
                    <div class="list-item">
                        <div>
                            <div class="list-title"><?= e($n['title']); ?></div>
                            <div class="small muted"><?= e(date('d M Y H:i', strtotime($n['created_at']))); ?></div>
                        </div>
                        <?= status_badge((int) $n['is_read'] === 1 ? 'approved' : 'pending'); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card mt-12">
    <div class="card-head"><h3>Recent Federation Actions</h3></div>
    <div class="card-body">
        <?php if (empty($federationActions)): ?>
            <div class="empty-state">No federation actions logged yet.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($federationActions as $fa): ?>
                            <tr>
                                <td><?= e($fa['action']); ?></td>
                                <td><?= e($fa['module']); ?></td>
                                <td><?= e(date('d M Y H:i', strtotime($fa['created_at']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

