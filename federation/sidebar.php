<?php
$current = $page ?? 'dashboard';
$groups = [
    [
        'title' => 'Overview',
        'icon' => 'dashboard',
        'items' => [
            ['dashboard', 'Dashboard', 'dashboard'],
            ['activity_logs', 'Activity Logs', 'logs'],
            ['reports', 'Reports', 'report'],
        ],
    ],
    [
        'title' => 'Teams & Seasons',
        'icon' => 'team',
        'items' => [
            ['teams', 'Teams Management', 'team'],
            ['team_registrations', 'Team Registrations', 'team'],
            ['stadiums', 'Stadium Management', 'stadium'],
            ['seasons', 'Seasons Management', 'season'],
        ],
    ],
    [
        'title' => 'Users & Roles',
        'icon' => 'users',
        'items' => [
            ['users', 'Users Management', 'users'],
            ['roles_permissions', 'Roles & Permissions', 'settings'],
            ['assign_roles', 'Assign Roles', 'users'],
        ],
    ],
    [
        'title' => 'Approvals',
        'icon' => 'approval',
        'items' => [
            ['player_rankings_approval', 'Ranking Approvals', 'approval'],
            ['player_ratings_approval', 'Ratings Approvals', 'approval'],
            ['player_statistics_approval', 'Statistics Approvals', 'approval'],
            ['match_results_approval', 'Match Results', 'approval'],
            ['match_lineups_approval', 'Match Lineups', 'approval'],
        ],
    ],
    [
        'title' => 'Match Center',
        'icon' => 'season',
        'items' => [
            ['match_officials', 'Match Officials', 'users'],
            ['news', 'News Management', 'news'],
            ['settings', 'Settings', 'settings'],
            ['profile', 'Profile', 'profile'],
        ],
    ],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="<?= e(app_url($settings['logo'])); ?>" alt="logo">
        <div>
            <div class="brand-title">Federation Admin</div>
        </div>
    </div>

    <nav class="sidebar-nav">
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
            <button class="nav-group-title" type="button" data-nav-group>
                <?= icon_svg($group['icon']); ?>
                <span><?= e($group['title']); ?></span>
                <svg class="chev" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6z"/></svg>
            </button>
            <div class="nav-sub">
                <?php foreach ($group['items'] as $item): ?>
                    <a class="nav-item <?= $current === $item[0] ? 'active' : ''; ?>" href="index.php?page=<?= e($item[0]); ?>">
                        <?= icon_svg($item[2]); ?>
                        <span><?= e($item[1]); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
        <a class="nav-item" href="logout.php">
            <?= icon_svg('logout'); ?>
            <span>Logout</span>
        </a>
    </div>
</aside>
