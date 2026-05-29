<?php
$current = $page ?? 'dashboard';
$myTeamId = (int) (current_user()['entity_id'] ?? 0);
$pendingLineups = $myTeamId > 0
    ? (int) (db_fetch_one("SELECT COUNT(*) total FROM match_lineups WHERE team_id = ? AND status IN ('draft','submitted')", 'i', [$myTeamId])['total'] ?? 0)
    : (int) (db_fetch_one("SELECT COUNT(*) total FROM match_lineups WHERE status IN ('draft','submitted')")['total'] ?? 0);

$teamInfo = null;
if ($myTeamId > 0) {
    $teamInfo = db_fetch_one("SELECT * FROM teams WHERE id = ?", 'i', [$myTeamId]);
}
$teamName = $teamInfo['name'] ?? 'My Team';
$teamLogoUrl = !empty($teamInfo['logo']) ? app_url($teamInfo['logo']) : app_url('assets/images/team-logo.svg');

$groups = [
    [
        'title' => 'Overview',
        'icon' => 'fa-gauge-high',
        'items' => [
            ['dashboard', 'Dashboard', 'fa-gauge-high', null],
            ['matches', 'Match Schedule', 'fa-calendar-days', null],
            ['results', 'Match Results', 'fa-flag-checkered', null],
        ],
    ],
    [
        'title' => 'Team Operations',
        'icon' => 'fa-users',
        'items' => [
            ['players', 'Players', 'fa-user-plus', null],
            ['squad', 'Squad Management', 'fa-users', null],
            ['lineups', 'Lineup Submissions', 'fa-clipboard-list', $pendingLineups > 0 ? $pendingLineups : null],
            ['news', 'Federation News', 'fa-newspaper', null],
        ],
    ],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand-block">
        <div class="sidebar-logo">
            <img src="<?= e($teamLogoUrl); ?>" alt="logo" onerror="this.src='<?= e(app_url('assets/images/default-team.png')); ?>'">
        </div>
        <div class="sidebar-brand-info">
            <span class="sidebar-brand-name"><?= e($teamName); ?></span>
            <span class="sidebar-brand-role">Team Manager</span>
        </div>
        <button class="sidebar-close-btn" id="sidebarClose" type="button" aria-label="Close sidebar">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

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

    <div class="sidebar-foot">
        <a class="nav-item <?= $current === 'profile' ? 'active' : ''; ?>" href="index.php?page=profile">
            <i class="fa-solid fa-user nav-fa" aria-hidden="true"></i>
            <span>My Profile</span>
        </a>
        <a class="nav-item" href="logout.php">
            <i class="fa-solid fa-right-from-bracket nav-fa" aria-hidden="true"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>
<div class="mobile-overlay" id="mobileOverlay"></div>
