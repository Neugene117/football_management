<?php
require_once __DIR__ . '/auth.php';
$settings = load_system_settings();
$page = $_GET['page'] ?? 'dashboard';

$routes = [
    'dashboard' => 'dashboard.php',
    'squad' => 'squad.php',
    'lineups' => 'lineups.php',
    'matches' => 'matches.php',
    'results' => 'results.php',
    'news' => 'news.php',
    'profile' => 'profile.php',
];

$pageFile = $routes[$page] ?? 'dashboard.php';
$fullPagePath = __DIR__ . '/pages/' . $pageFile;
if (!file_exists($fullPagePath)) {
    $page = 'dashboard';
    $fullPagePath = __DIR__ . '/pages/dashboard.php';
}

$titles = [
    'dashboard' => 'Team Dashboard',
    'squad' => 'Squad Management',
    'lineups' => 'Lineup Submissions',
    'matches' => 'Match Schedule',
    'results' => 'Match Results',
    'news' => 'Federation News',
    'profile' => 'My Profile',
];

include __DIR__ . '/header.php';
include __DIR__ . '/sidebar.php';
?>
<div class="main">
    <header class="topbar">
        <button class="menu-toggle" id="sidebarToggle" type="button"><?= icon_svg('menu'); ?></button>
        <div class="top-logo">
            <img src="<?= e(app_url($settings['logo'])); ?>" alt="logo">
            <h2><?= e(strtoupper($settings['system_name'])); ?></h2>
        </div>
        <div class="spacer"></div>
        <div class="dropdown">
            <button class="profile-btn dropdown-toggle" type="button">
                <span class="profile-mini-icon"><?= icon_svg('profile'); ?></span>
                <span class="profile-mini-name"><?= e($currentUser['full_name']); ?></span>
                <?= icon_svg('chevron-down'); ?>
            </button>
            <div class="dropdown-menu">
                <a href="index.php?page=profile">Profile</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </header>

    <main class="content <?= $page === 'dashboard' ? 'dashboard-content' : ''; ?>">
        <div class="page-head <?= $page === 'dashboard' ? 'dashboard-head' : ''; ?>">
            <div>
                <h1><?= e($page === 'dashboard' ? 'Dashboard Overview' : ($titles[$page] ?? 'Team Dashboard')); ?></h1>
                <p class="muted"><?= e($page === 'dashboard' ? "Welcome back, {$currentUser['full_name']}! Here's what's happening today." : 'Operational tools for team managers and coaches.'); ?></p>
            </div>
            <?php if ($page !== 'dashboard'): ?>
                <div class="breadcrumbs">
                    <a href="index.php?page=dashboard">Home</a><span>/</span><span><?= e($titles[$page] ?? 'Team Dashboard'); ?></span>
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
