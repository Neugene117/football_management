<?php
require_once __DIR__ . '/auth.php';
$settings = load_system_settings();
$page = $_GET['page'] ?? 'dashboard';

// Handle notification mark-as-read AJAX request
if (isset($_POST['mark_notification_read']) && isset($_POST['notification_id'])) {
    $notifId = (int) $_POST['notification_id'];
    $userId = (int) ($currentUser['id'] ?? 0);
    if ($notifId > 0 && $userId > 0) {
        db_execute(
            'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?',
            'ii',
            [$notifId, $userId]
        );
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// Handle mark all notifications as read
if (isset($_POST['mark_all_notifications_read'])) {
    $userId = (int) ($currentUser['id'] ?? 0);
    if ($userId > 0) {
        db_execute(
            'UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0',
            'i',
            [$userId]
        );
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// Handle dynamic notification polling
if (isset($_GET['notifications_poll'])) {
    $userId = (int) ($currentUser['id'] ?? 0);
    $items = fetch_user_notifications($userId, 8);
    foreach ($items as &$item) {
        $item['id'] = (int) $item['id'];
        $item['is_read'] = (int) $item['is_read'];
        $item['time_ago'] = notif_time_ago($item['created_at'] ?? '');
    }
    unset($item);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'unread_count' => unread_notification_count($userId),
        'notifications' => $items,
    ]);
    exit;
}

// Fetch notifications
$userId = (int) ($currentUser['id'] ?? 0);
$notifications = fetch_user_notifications($userId, 8);
$unreadCount = unread_notification_count($userId);

$routes = [
    'dashboard' => 'dashboard.php',
    'squad' => 'squad.php',
    'players' => 'players.php',
    'lineups' => 'lineups.php',
    'matches' => 'matches.php',
    'results' => 'results.php',
    'news' => 'news.php',
    'notifications' => 'notifications.php',
    'profile' => 'profile.php',
];

$pageFile = $routes[$page] ?? 'dashboard.php';
$fullPagePath = __DIR__ . '/pages/' . $pageFile;
if (!file_exists($fullPagePath)) {
    $page = 'dashboard';
    $fullPagePath = __DIR__ . '/pages/dashboard.php';
}

$accessDeniedPage = null;
if (!current_user_can_page($page)) {
    $accessDeniedPage = $page;
}

$titles = [
    'dashboard' => 'Team Dashboard',
    'squad' => 'Squad Management',
    'players' => 'Players',
    'lineups' => 'Lineup Submissions',
    'matches' => 'Match Schedule',
    'results' => 'Match Results',
    'news' => 'Federation News',
    'profile' => 'My Profile',
];

$notifIconMap = [
    'info' => 'fa-circle-info',
    'success' => 'fa-circle-check',
    'warning' => 'fa-triangle-exclamation',
    'error' => 'fa-circle-xmark',
    'approval' => 'fa-clipboard-check',
    'team' => 'fa-shield-halved',
    'match' => 'fa-futbol',
    'user' => 'fa-user-plus',
];

$notifColorMap = [
    'info' => 'notif-info',
    'success' => 'notif-success',
    'warning' => 'notif-warning',
    'error' => 'notif-error',
    'approval' => 'notif-approval',
    'team' => 'notif-team',
    'match' => 'notif-match',
    'user' => 'notif-user',
];

include __DIR__ . '/header.php';
include __DIR__ . '/sidebar.php';
?>
<div class="main">
    <?php
    $profilePhoto = !empty($currentUser['profile_photo']) ? app_url($currentUser['profile_photo']) : '';
    $profileInitials = strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1));
    ?>
    <header class="topbar">
        <div class="topbar-left">
            <button class="menu-toggle" id="sidebarToggle" type="button" aria-label="Toggle sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="topbar-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" placeholder="Search squad, matches, results..." aria-label="Search">
            </div>
        </div>

        <div class="topbar-right">
            <!-- Notification Bell -->
            <div class="dropdown notif-dropdown" id="notifDropdown">
                <button class="topbar-action-item dropdown-toggle" type="button" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
                    <i class="fa-regular fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="topbar-badge" id="notifBadge"><?= $unreadCount; ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu notif-menu">
                    <div class="notif-header">
                        <h4>Notifications</h4>
                        <?php if ($unreadCount > 0): ?>
                            <button class="notif-mark-all" id="markAllRead" type="button">Mark all read</button>
                        <?php endif; ?>
                    </div>
                    <div class="notif-list" id="notifList">
                        <?php if (empty($notifications)): ?>
                            <div class="notif-empty">
                                <i class="fa-regular fa-bell-slash"></i>
                                <p>No notifications yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $item):
                                $isUnread = (int) $item['is_read'] === 0;
                                $type = $item['type'] ?? 'info';
                                $iconClass = $notifIconMap[$type] ?? 'fa-circle-info';
                                $colorClass = $notifColorMap[$type] ?? 'notif-info';
                            ?>
                                <div class="notif-item <?= $isUnread ? 'unread' : ''; ?> <?= $colorClass; ?>"
                                     data-notif-id="<?= (int) $item['id']; ?>"
                                     role="button" tabindex="0">
                                    <div class="notif-icon-wrap">
                                        <i class="fa-solid <?= e($iconClass); ?>"></i>
                                    </div>
                                    <div class="notif-content">
                                        <p class="notif-title"><?= e($item['title']); ?></p>
                                        <?php if (!empty($item['message'])): ?>
                                            <p class="notif-message"><?= e(mb_strimwidth($item['message'], 0, 80, '...')); ?></p>
                                        <?php endif; ?>
                                        <span class="notif-time">
                                            <i class="fa-regular fa-clock"></i>
                                            <?= e(notif_time_ago($item['created_at'] ?? '')); ?>
                                        </span>
                                    </div>
                                    <?php if ($isUnread): ?>
                                        <span class="notif-dot"></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="notif-footer">
                        <a href="index.php?page=notifications">View all notifications</a>
                    </div>
                </div>
            </div>
            
            <div class="dropdown">
                <button class="profile-btn dropdown-toggle" type="button" aria-haspopup="true" aria-expanded="false">
                    <div class="profile-avatar">
                        <?php if ($profilePhoto): ?>
                            <img src="<?= e($profilePhoto); ?>" alt="profile" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <?php endif; ?>
                        <div class="profile-avatar-placeholder" style="<?= $profilePhoto ? 'display: none;' : ''; ?>">
                            <?= e($profileInitials); ?>
                        </div>
                    </div>
                    <div class="profile-meta">
                        <span class="profile-name"><?= e($currentUser['full_name'] ?? 'User'); ?></span>
                        <span class="profile-role"><?= e(ucfirst($currentUser['user_type'] ?? 'Team Manager')); ?></span>
                    </div>
                    <i class="fa-solid fa-chevron-down profile-chevron"></i>
                </button>
                <div class="dropdown-menu">
                    <div class="dropdown-header">
                        <strong><?= e($currentUser['full_name'] ?? 'User'); ?></strong>
                        <span><?= e($currentUser['email'] ?? ''); ?></span>
                    </div>
                    <hr class="dropdown-divider">
                    <a href="index.php?page=profile"><i class="fa-regular fa-user"></i> My Profile</a>
                    <hr class="dropdown-divider">
                    <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <main class="content <?= $page === 'dashboard' ? 'dashboard-content' : ''; ?>">
        <div class="page-head <?= $page === 'dashboard' ? 'dashboard-head' : ''; ?>">
            <div>
                <h1><?= e($accessDeniedPage ? 'Access Restricted' : ($page === 'dashboard' ? 'Dashboard Overview' : ($titles[$page] ?? 'Team Dashboard'))); ?></h1>
                <p class="muted"><?= e($accessDeniedPage ? 'Your assigned role does not include permission to view this page.' : ($page === 'dashboard' ? "Welcome back, {$currentUser['full_name']}! Here's what's happening today." : 'Operational tools for team managers and coaches.')); ?></p>
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

        <?php if ($accessDeniedPage): ?>
            <div class="card access-denied-card">
                <div class="card-body">
                    <i class="fa-solid fa-lock"></i>
                    <h3>Permission required</h3>
                    <p>You do not have access to <?= e($titles[$accessDeniedPage] ?? 'this page'); ?>. Ask a Federation role administrator to update your role permissions.</p>
                    <a class="btn btn-primary" href="index.php?page=dashboard">Back to Dashboard</a>
                </div>
            </div>
        <?php else: ?>
            <?php include $fullPagePath; ?>
        <?php endif; ?>
    </main>
</div>
<?php include __DIR__ . '/footer.php'; ?>
