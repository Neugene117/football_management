<?php
$menuItems = [
    ['dashboard', 'Dashboard', 'dashboard'],
    ['teams', 'Teams Management', 'team'],
    ['team_registrations', 'Team Registrations', 'team'],
    ['users', 'Users Management', 'users'],
    ['roles_permissions', 'Roles & Permissions', 'settings'],
    ['assign_roles', 'Assign Roles', 'users'],
    ['player_rankings_approval', 'Player Rankings Approval', 'approval'],
    ['player_ratings_approval', 'Player Ratings Approval', 'approval'],
    ['player_statistics_approval', 'Player Statistics Approval', 'approval'],
    ['stadiums', 'Stadium Management', 'stadium'],
    ['seasons', 'Seasons Management', 'season'],
    ['match_results_approval', 'Match Results Approval', 'approval'],
    ['match_officials', 'Match Officials', 'users'],
    ['match_lineups_approval', 'Match Lineups Approval', 'approval'],
    ['news', 'News Management', 'news'],
    ['activity_logs', 'Activity Logs', 'logs'],
    ['reports', 'Reports', 'report'],
    ['settings', 'Settings', 'settings'],
    ['profile', 'Profile', 'profile'],
    ['logout', 'Logout', 'logout'],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-title">Administration</div>
    <nav>
        <?php foreach ($menuItems as $item):
            $active = ($page ?? 'dashboard') === $item[0] ? 'active' : '';
        ?>
            <a class="menu-item <?= $active; ?>" href="index.php?page=<?= e($item[0]); ?>">
                <span class="menu-icon"><?= icon_svg($item[2]); ?></span>
                <span><?= e($item[1]); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>

