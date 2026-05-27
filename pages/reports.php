<?php
$reportType = $_GET['report'] ?? 'teams';
$allowedReports = ['teams', 'matches', 'players', 'approvals'];
if (!in_array($reportType, $allowedReports, true)) {
    $reportType = 'teams';
}

if (($_GET['export'] ?? '') === 'csv') {
    $filename = $reportType . '_report_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=' . $filename);

    $out = fopen('php://output', 'w');
    if ($reportType === 'teams') {
        fputcsv($out, ['Team Name', 'Coach', 'City', 'Founded Year', 'Status']);
        $data = db_fetch_all('SELECT name, coach_name, city, founded_year, is_active FROM teams ORDER BY name ASC');
        foreach ($data as $row) {
            fputcsv($out, [$row['name'], $row['coach_name'], $row['city'], $row['founded_year'], (int) $row['is_active'] ? 'Active' : 'Pending']);
        }
    }
    if ($reportType === 'matches') {
        fputcsv($out, ['Date', 'Home Team', 'Away Team', 'Status']);
        $data = db_fetch_all('SELECT m.match_date, ht.name home_team, at.name away_team, m.status FROM matches m LEFT JOIN teams ht ON ht.id=m.home_team_id LEFT JOIN teams at ON at.id=m.away_team_id ORDER BY m.match_date DESC');
        foreach ($data as $row) {
            fputcsv($out, [$row['match_date'], $row['home_team'], $row['away_team'], $row['status']]);
        }
    }
    if ($reportType === 'players') {
        fputcsv($out, ['Player', 'Team', 'Position', 'Nationality', 'Status']);
        $data = db_fetch_all("SELECT CONCAT(p.first_name,' ',p.last_name) player_name, t.name team_name, p.position, p.nationality, p.status FROM players p LEFT JOIN teams t ON t.id=p.team_id ORDER BY p.created_at DESC");
        foreach ($data as $row) {
            fputcsv($out, [$row['player_name'], $row['team_name'], $row['position'], $row['nationality'], $row['status']]);
        }
    }
    if ($reportType === 'approvals') {
        fputcsv($out, ['Item Type', 'Item ID', 'Submitted By', 'Status', 'Reviewed At']);
        $data = db_fetch_all('SELECT a.item_type, a.item_id, u.full_name, a.status, a.reviewed_at FROM approvals a LEFT JOIN users u ON u.id=a.submitted_by ORDER BY a.created_at DESC');
        foreach ($data as $row) {
            fputcsv($out, [$row['item_type'], $row['item_id'], $row['full_name'], $row['status'], $row['reviewed_at']]);
        }
    }
    fclose($out);
    exit;
}

$summary = [
    'teams' => db_table_count('teams'),
    'matches' => db_table_count('matches'),
    'players' => db_table_count('players'),
    'approvals' => db_table_count('approvals'),
];

$tableRows = [];
$tableHeaders = [];

if ($reportType === 'teams') {
    $tableHeaders = ['Team', 'Coach', 'Province', 'Founded', 'Status'];
    $tableRows = db_fetch_all('SELECT name, coach_name, city, founded_year, is_active FROM teams ORDER BY created_at DESC LIMIT 50');
}
if ($reportType === 'matches') {
    $tableHeaders = ['Date', 'Home Team', 'Away Team', 'Status'];
    $tableRows = db_fetch_all('SELECT m.match_date, ht.name AS home_team, at.name AS away_team, m.status FROM matches m LEFT JOIN teams ht ON ht.id=m.home_team_id LEFT JOIN teams at ON at.id=m.away_team_id ORDER BY m.match_date DESC LIMIT 50');
}
if ($reportType === 'players') {
    $tableHeaders = ['Player', 'Team', 'Position', 'Nationality', 'Status'];
    $tableRows = db_fetch_all("SELECT CONCAT(p.first_name,' ',p.last_name) AS player_name, t.name AS team_name, p.position, p.nationality, p.status FROM players p LEFT JOIN teams t ON t.id=p.team_id ORDER BY p.created_at DESC LIMIT 50");
}
if ($reportType === 'approvals') {
    $tableHeaders = ['Item Type', 'Item ID', 'Submitted By', 'Status', 'Reviewed'];
    $tableRows = db_fetch_all('SELECT a.item_type, a.item_id, u.full_name, a.status, a.reviewed_at FROM approvals a LEFT JOIN users u ON u.id=a.submitted_by ORDER BY a.created_at DESC LIMIT 50');
}
?>

<div class="grid stats-grid">
    <div class="card stat-card"><div class="stat-icon"><?= icon_svg('team'); ?></div><div><div class="stat-value" data-counter="<?= $summary['teams']; ?>">0</div><div class="stat-label">Teams Report Count</div></div></div>
    <div class="card stat-card"><div class="stat-icon"><?= icon_svg('dashboard'); ?></div><div><div class="stat-value" data-counter="<?= $summary['matches']; ?>">0</div><div class="stat-label">Matches Report Count</div></div></div>
    <div class="card stat-card"><div class="stat-icon"><?= icon_svg('users'); ?></div><div><div class="stat-value" data-counter="<?= $summary['players']; ?>">0</div><div class="stat-label">Players Report Count</div></div></div>
    <div class="card stat-card"><div class="stat-icon"><?= icon_svg('approval'); ?></div><div><div class="stat-value" data-counter="<?= $summary['approvals']; ?>">0</div><div class="stat-label">Approvals Report Count</div></div></div>
</div>

<div class="card mt-12">
    <div class="card-head">
        <h3>Reports</h3>
        <div class="action-group">
            <a class="btn btn-light btn-sm" href="index.php?page=reports&report=teams">Teams</a>
            <a class="btn btn-light btn-sm" href="index.php?page=reports&report=matches">Matches</a>
            <a class="btn btn-light btn-sm" href="index.php?page=reports&report=players">Players</a>
            <a class="btn btn-light btn-sm" href="index.php?page=reports&report=approvals">Approvals</a>
            <a class="btn btn-primary btn-sm" href="index.php?page=reports&report=<?= e($reportType); ?>&export=csv">Export CSV</a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <?php foreach ($tableHeaders as $h): ?>
                            <th><?= e($h); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tableRows)): ?>
                        <tr><td colspan="<?= count($tableHeaders); ?>"><div class="empty-state">No data available for this report.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($tableRows as $row): ?>
                            <tr>
                                <?php if ($reportType === 'teams'): ?>
                                    <td><?= e($row['name']); ?></td>
                                    <td><?= e($row['coach_name'] ?: '-'); ?></td>
                                    <td><?= e($row['city'] ?: '-'); ?></td>
                                    <td><?= e($row['founded_year'] ?: '-'); ?></td>
                                    <td><?= status_badge((int) $row['is_active'] ? 'active' : 'pending'); ?></td>
                                <?php elseif ($reportType === 'matches'): ?>
                                    <td><?= e($row['match_date']); ?></td>
                                    <td><?= e($row['home_team'] ?: '-'); ?></td>
                                    <td><?= e($row['away_team'] ?: '-'); ?></td>
                                    <td><?= status_badge($row['status']); ?></td>
                                <?php elseif ($reportType === 'players'): ?>
                                    <td><?= e($row['player_name']); ?></td>
                                    <td><?= e($row['team_name'] ?: '-'); ?></td>
                                    <td><?= e($row['position']); ?></td>
                                    <td><?= e($row['nationality'] ?: '-'); ?></td>
                                    <td><?= status_badge($row['status']); ?></td>
                                <?php else: ?>
                                    <td><?= e($row['item_type']); ?></td>
                                    <td>#<?= (int) $row['item_id']; ?></td>
                                    <td><?= e($row['full_name'] ?: '-'); ?></td>
                                    <td><?= status_badge($row['status']); ?></td>
                                    <td><?= e($row['reviewed_at'] ?: '-'); ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

