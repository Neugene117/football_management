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
        <button class="menu-toggle" id="sidebarToggle" type="button"><?= icon_svg('dashboard'); ?></button>
        <div class="top-logo">
            <img src="<?= e(app_url($settings['logo'])); ?>" alt="logo">
            <h2>Team Workspace</h2>
        </div>
        <div class="spacer"></div>
        <div class="dropdown">
            <button class="profile-btn dropdown-toggle" type="button">
                <img src="<?= e(app_url($currentUser['profile_photo'] ?: 'assets/images/federation-logo.svg')); ?>" alt="profile">
                <span><?= e($currentUser['full_name']); ?></span>
            </button>
            <div class="dropdown-menu">
                <a href="index.php?page=profile">Profile</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </header>

    <main class="content">
        <div class="page-head">
            <div>
                <h1><?= e($titles[$page] ?? 'Team Dashboard'); ?></h1>
                <p class="muted">Operational tools for team managers and coaches.</p>
            </div>
            <div class="breadcrumbs">
                <a href="index.php?page=dashboard">Home</a><span>/</span><span><?= e($titles[$page] ?? 'Team Dashboard'); ?></span>
            </div>
        </div>

        <?php foreach (get_flashes() as $flash): ?>
            <div class="alert alert-<?= e($flash['type']); ?>"><?= e($flash['message']); ?></div>
        <?php endforeach; ?>

        <?php include $fullPagePath; ?>
    </main>
</div>
<?php include __DIR__ . '/footer.php'; ?>
