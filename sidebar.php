<?php
$current = $page ?? 'dashboard';

$groups = [
    [
        'title' => 'Overview',
        'icon'  => 'dashboard',
        'items' => [
            ['dashboard', 'Dashboard', 'dashboard'],
        ],
    ],
    [
        'title' => 'People',
        'icon'  => 'users',
        'items' => [
            ['users', 'Users Management', 'users'],
            ['roles_permissions', 'Roles & Permissions', 'settings'],
            ['assign_roles', 'Assign Roles', 'users'],
            ['match_officials', 'Match Officials', 'users'],
        ],
    ],
    [
        'title' => 'Competitions',
        'icon'  => 'team',
        'items' => [
            ['teams', 'Teams Management', 'team'],
            ['team_registrations', 'Team Registrations', 'team'],
            ['seasons', 'Seasons Management', 'season'],
        ],
    ],
    [
        'title' => 'Infrastructure',
        'icon'  => 'stadium',
        'items' => [
            ['stadiums', 'Stadium Management', 'stadium'],
            ['news', 'News Management', 'news'],
        ],
    ],
    [
        'title' => 'Player Approvals',
        'icon'  => 'approval',
        'items' => [
            ['player_rankings_approval', 'Player Rankings Approval', 'approval'],
            ['player_ratings_approval', 'Player Ratings Approval', 'approval'],
            ['player_statistics_approval', 'Player Statistics Approval', 'approval'],
        ],
    ],
    [
        'title' => 'Match Approvals',
        'icon'  => 'approval',
        'items' => [
            ['match_results_approval', 'Match Results Approval', 'approval'],
            ['match_lineups_approval', 'Match Lineups Approval', 'approval'],
        ],
    ],
    [
        'title' => 'System',
        'icon'  => 'settings',
        'items' => [
            ['activity_logs', 'Activity Logs', 'logs'],
            ['reports', 'Reports', 'report'],
            ['settings', 'Settings', 'settings'],
        ],
    ],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-title">Administration</div>
    <nav>
        <?php foreach ($groups as $group):
            $open = false;
            foreach ($group['items'] as $item) {
                if ($item[0] === $current) {
                    $open = true;
                    break;
                }
            }
        ?>
            <div class="menu-group <?= $open ? 'open' : ''; ?>">
                <button class="menu-group-toggle" type="button" data-nav-group>
                    <span class="menu-icon"><?= icon_svg($group['icon']); ?></span>
                    <span><?= e($group['title']); ?></span>
                    <svg class="menu-chev" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6z"/></svg>
                </button>
                <div class="menu-sub">
                    <?php foreach ($group['items'] as $item): ?>
                        <a class="menu-item <?= $current === $item[0] ? 'active' : ''; ?>" href="index.php?page=<?= e($item[0]); ?>">
                            <span class="menu-icon"><?= icon_svg($item[2]); ?></span>
                            <span><?= e($item[1]); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="menu-group-divider"></div>
        <a class="menu-item <?= $current === 'profile' ? 'active' : ''; ?>" href="index.php?page=profile">
            <span class="menu-icon"><?= icon_svg('profile'); ?></span>
            <span>Profile</span>
        </a>
        <a class="menu-item menu-item-logout" href="index.php?page=logout">
            <span class="menu-icon"><?= icon_svg('logout'); ?></span>
            <span>Logout</span>
        </a>
    </nav>
</aside>
