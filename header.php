<?php
$settings = $settings ?? load_system_settings();
$user = current_user();
$notifications = fetch_user_notifications((int) ($user['id'] ?? 0), 6);
$unreadCount = unread_notification_count((int) ($user['id'] ?? 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(page_title($page ?? 'dashboard')); ?> - <?= e($settings['system_name']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body data-primary-color="<?= e($settings['primary_color']); ?>" data-secondary-color="<?= e($settings['secondary_color']); ?>">
<div class="spinner-overlay" id="loadingSpinner">
    <div class="spinner"></div>
</div>
<div class="app-shell">
    <header class="topbar">
        <button id="sidebarToggle" class="icon-btn" type="button" aria-label="Toggle menu"><?= icon_svg('dashboard'); ?></button>
        <div class="brand-wrap">
            <img class="brand-logo" src="<?= e($settings['logo']); ?>" alt="Federation logo">
            <div>
                <h2><?= e($settings['system_name']); ?></h2>
                <small>Federation Control Panel</small>
            </div>
        </div>

        <div class="topbar-right">
            <form class="top-search" method="get" action="index.php">
                <input type="hidden" name="page" value="dashboard">
                <span class="search-icon"><?= icon_svg('search'); ?></span>
                <input type="text" name="q" placeholder="Search modules...">
            </form>

            <div class="dropdown" id="notifyDrop">
                <button class="icon-btn has-badge" type="button" data-toggle="dropdown">
                    <?= icon_svg('notification'); ?>
                    <span class="count"><?= $unreadCount; ?></span>
                </button>
                <div class="dropdown-menu dropdown-right">
                    <h4>Notifications</h4>
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state small">No notifications yet.</div>
                    <?php else: ?>
                        <?php foreach ($notifications as $item): ?>
                            <div class="notification-item <?= (int) $item['is_read'] === 0 ? 'unread' : ''; ?>">
                                <strong><?= e($item['title']); ?></strong>
                                <small><?= e(date('d M Y H:i', strtotime($item['created_at']))); ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dropdown" id="profileDrop">
                <button class="profile-btn" type="button" data-toggle="dropdown">
                    <img src="<?= e($user['profile_photo'] ?: 'assets/images/federation-logo.svg'); ?>" alt="Profile">
                    <span><?= e($user['full_name'] ?? 'Admin'); ?></span>
                </button>
                <div class="dropdown-menu dropdown-right">
                    <a href="index.php?page=profile">My Profile</a>
                    <a href="index.php?page=settings">Settings</a>
                    <a href="index.php?page=logout" class="danger-text">Logout</a>
                </div>
            </div>
        </div>
    </header>

