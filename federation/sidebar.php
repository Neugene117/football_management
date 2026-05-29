<?php
$current = $page ?? 'dashboard';
$userCount = (int) db_table_count('users');
$pendingApprovals = (int) db_table_count('approvals', 'status = ?', 's', ['pending']);
$pendingPlayers = (int) db_table_count('players', "status = 'inactive'");

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
            ['roles_permissions', 'Roles & Permissions', 'fa-key', null],
            ['assign_roles', 'Assign Roles', 'fa-user-tag', null],
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
            ['player_registrations_approval', 'Registrations', 'fa-user-plus', $pendingPlayers > 0 ? $pendingPlayers : null],
            ['player_rankings_approval', 'Rankings', 'fa-list-ol', $pendingApprovals > 0 ? $pendingApprovals : null],
            ['player_ratings_approval', 'Ratings', 'fa-star-half-stroke', null],
            ['player_statistics_approval', 'Statistics', 'fa-chart-line', null],
        ],
    ],
    [
        'title' => 'Match Approvals',
        'icon' => 'fa-list-check',
        'items' => [
            ['match_results_approval', 'Results', 'fa-flag-checkered', null],
            ['match_lineups_approval', 'Lineups', 'fa-clipboard-list', null],
        ],
    ],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand-block">
        <div class="sidebar-logo">
            <img src="<?= e(app_url($settings['logo'] ?? 'assets/images/federation-logo.svg')); ?>" alt="logo">
        </div>
        <div class="sidebar-brand-info">
            <span class="sidebar-brand-name"><?= e($settings['system_name'] ?? 'Federation'); ?></span>
            <span class="sidebar-brand-role">Admin Panel</span>
        </div>
        <button class="sidebar-close-btn" id="sidebarClose" type="button" aria-label="Close sidebar">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <nav class="sidebar-nav sidebar-group-nav">
        <?php foreach ($groups as $group):
            $open = false;
            $visibleItems = array_values(array_filter($group['items'], static function ($item) {
                return current_user_can_page($item[0]);
            }));
            if (empty($visibleItems)) {
                continue;
            }
            foreach ($visibleItems as $item) {
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
                    <?php foreach ($visibleItems as $item): ?>
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

    <div class="sidebar-foot">
        <a class="nav-item <?= $current === 'profile' ? 'active' : ''; ?>" href="index.php?page=profile">
            <i class="fa-solid fa-circle-user nav-fa" aria-hidden="true"></i>
            <span>Profile</span>
        </a>
        <?php if (current_user_can_page('settings')): ?>
            <a class="nav-item <?= $current === 'settings' ? 'active' : ''; ?>" href="index.php?page=settings">
                <i class="fa-solid fa-gear nav-fa" aria-hidden="true"></i>
                <span>Settings</span>
            </a>
        <?php endif; ?>
        <a class="nav-item nav-item-logout" href="logout.php">
            <i class="fa-solid fa-right-from-bracket nav-fa" aria-hidden="true"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>
<div class="mobile-overlay" id="mobileOverlay"></div>
