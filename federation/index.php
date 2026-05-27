<?php
require_once __DIR__ . '/auth.php';

ensure_default_roles();
$settings = load_system_settings();
$page = $_GET['page'] ?? 'dashboard';

$routes = [
    'dashboard' => 'dashboard.php',
    'teams' => 'teams.php',
    'team_registrations' => 'team_registrations.php',
    'users' => 'users.php',
    'roles_permissions' => 'roles_permissions.php',
    'assign_roles' => 'assign_roles.php',
    'player_rankings_approval' => 'player_rankings_approval.php',
    'player_ratings_approval' => 'player_ratings_approval.php',
    'player_statistics_approval' => 'player_statistics_approval.php',
    'stadiums' => 'stadiums.php',
    'seasons' => 'seasons.php',
    'match_results_approval' => 'match_results_approval.php',
    'match_officials' => 'match_officials.php',
    'match_lineups_approval' => 'match_lineups_approval.php',
    'news' => 'news.php',
    'activity_logs' => 'activity_logs.php',
    'reports' => 'reports.php',
    'settings' => 'settings.php',
    'profile' => 'profile.php',
    'logout' => 'logout.php',
];

$pageFile = $routes[$page] ?? 'dashboard.php';
$fullPagePath = __DIR__ . '/pages/' . $pageFile;
if (!file_exists($fullPagePath)) {
    $page = 'dashboard';
    $fullPagePath = __DIR__ . '/pages/dashboard.php';
}

include __DIR__ . '/header.php';
include __DIR__ . '/sidebar.php';
?>
<div class="main">
    <header class="topbar">
        <button class="menu-toggle" id="sidebarToggle" type="button" aria-label="Toggle sidebar"><?= icon_svg('menu'); ?></button>
        <div class="top-logo">
            <img src="<?= e(app_url($settings['logo'])); ?>" alt="logo">
            <h2><?= e(strtoupper($settings['system_name'])); ?></h2>
        </div>
        <div class="spacer"></div>

        <div class="dropdown">
            <button class="profile-btn dropdown-toggle" type="button">
                <span class="profile-mini-icon"><?= icon_svg('profile'); ?></span>
                <span class="profile-mini-name"><?= e($currentUser['full_name'] ?? 'Admin'); ?></span>
                <?= icon_svg('chevron-down'); ?>
            </button>
            <div class="dropdown-menu">
                <a href="index.php?page=profile">Profile</a>
                <a href="index.php?page=settings">Settings</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </header>

    <main class="content <?= $page === 'dashboard' ? 'dashboard-content' : ''; ?>">
        <div class="page-head <?= $page === 'dashboard' ? 'dashboard-head' : ''; ?>">
            <div>
                <h1><?= e($page === 'dashboard' ? 'Dashboard Overview' : page_title($page)); ?></h1>
                <p class="muted"><?= e($page === 'dashboard' ? "Welcome back, {$currentUser['full_name']}! Here's what's happening today." : 'Manage federation workflows in one place.'); ?></p>
            </div>
            <?php if ($page !== 'dashboard'): ?>
                <div class="breadcrumbs">
                    <a href="index.php?page=dashboard">Home</a>
                    <span>/</span>
                    <span><?= e(page_title($page)); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <?php foreach (get_flashes() as $flash): ?>
            <div class="alert alert-<?= e($flash['type']); ?>"><?= e($flash['message']); ?></div>
        <?php endforeach; ?>

        <?php include $fullPagePath; ?>
    </main>
</div>
<?php include __DIR__ . '/footer.php'; ?>
