<?php
$current = $page ?? 'dashboard';
$userCount = (int) db_table_count('users');
$pendingApprovals = (int) db_table_count('approvals', 'status = ?', 's', ['pending']);

$groups = [
    [
        'title' => 'Overview',
        'icon' => 'fa-gauge-high',
        'items' => [
            ['dashboard', 'Dashboard', 'fa-gauge-high', null],
            ['reports', 'Reports', 'fa-chart-column', null],
            ['activity_logs', 'Activity Logs', 'fa-clock-rotate-left', null],
        ],
    ],
    [
        'title' => 'People',
        'icon' => 'fa-users',
        'items' => [
            ['users', 'Users', 'fa-users', $userCount > 0 ? $userCount : null],
            ['match_officials', 'Officials', 'fa-user-shield', null],
        ],
    ],
    [
        'title' => 'Competitions',
        'icon' => 'fa-trophy',
        'items' => [
            ['teams', 'Teams', 'fa-shield-halved', null],
            ['team_registrations', 'Registrations', 'fa-clipboard-check', null],
            ['seasons', 'Seasons', 'fa-calendar-days', null],
        ],
    ],
    [
        'title' => 'Infrastructure',
        'icon' => 'fa-location-dot',
        'items' => [
            ['stadiums', 'Stadiums', 'fa-location-dot', null],
            ['news', 'News', 'fa-newspaper', null],
        ],
    ],
    [
        'title' => 'Player Approvals',
        'icon' => 'fa-user-check',
        'items' => [
            ['player_rankings_approval', 'Ranking Approvals', 'fa-list-ol', $pendingApprovals > 0 ? $pendingApprovals : null],
            ['player_ratings_approval', 'Rating Approvals', 'fa-star-half-stroke', null],
            ['player_statistics_approval', 'Statistics Approvals', 'fa-chart-line', null],
        ],
    ],
    [
        'title' => 'Match Approvals',
        'icon' => 'fa-list-check',
        'items' => [
            ['match_results_approval', 'Result Approvals', 'fa-flag-checkered', null],
            ['match_lineups_approval', 'Lineup Approvals', 'fa-clipboard-list', null],
        ],
    ],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-title-block">Admin</div>

    <nav class="sidebar-nav sidebar-group-nav">
        <?php foreach ($groups as $group):
            $open = false;
            foreach ($group['items'] as $item) {
                if ($item[0] === $current) {
                    $open = true;
                    break;
                }
            }
        ?>
            <div class="nav-group <?= $open ? 'open' : ''; ?>">
                <button class="nav-group-title" type="button" data-nav-group title="<?= e($group['title']); ?>">
                    <i class="fa-solid <?= e($group['icon']); ?> nav-group-fa" aria-hidden="true"></i>
                    <span><?= e($group['title']); ?></span>
                    <svg class="chev" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6z"/></svg>
                </button>
                <div class="nav-sub">
                    <?php foreach ($group['items'] as $item): ?>
                        <a class="nav-item <?= $current === $item[0] ? 'active' : ''; ?>" href="index.php?page=<?= e($item[0]); ?>" title="<?= e($item[1]); ?>">
                            <i class="fa-solid <?= e($item[2]); ?> nav-fa" aria-hidden="true"></i>
                            <span><?= e($item[1]); ?></span>
                            <?php if ($item[3] !== null): ?>
                                <span class="nav-badge"><?= (int) $item[3]; ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </nav>

</aside>
