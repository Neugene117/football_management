<?php
$currentUserId = (int) (current_user()['id'] ?? 0);
$messageColumn = notification_message_column();
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid request token.');
        redirect_to('index.php?page=notifications');
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            db_execute('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', 'ii', [$id, $currentUserId]);
        }
        redirect_to('index.php?page=notifications');
    }

    if ($action === 'mark_all_read') {
        db_execute('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', 'i', [$currentUserId]);
        redirect_to('index.php?page=notifications');
    }
}

$where = 'user_id = ?';
$types = 'i';
$params = [$currentUserId];

if ($filter === 'unread') {
    $where .= ' AND is_read = 0';
} elseif ($filter === 'read') {
    $where .= ' AND is_read = 1';
}

if ($search !== '') {
    $where .= " AND (title LIKE ? OR {$messageColumn} LIKE ? OR type LIKE ?)";
    $types .= 'sss';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$offset = 0;
$limitClause = paginate_clause($offset);
$notificationsPageRows = db_fetch_all(
    "SELECT id, title, type, {$messageColumn} AS message, is_read, created_at
     FROM notifications
     WHERE {$where}
     ORDER BY created_at DESC {$limitClause}",
    $types,
    $params
);
$totalRows = db_fetch_one("SELECT COUNT(*) total FROM notifications WHERE {$where}", $types, $params);
$totalItems = (int) ($totalRows['total'] ?? 0);
$totalUnread = unread_notification_count($currentUserId);
$totalAll = db_table_count('notifications', 'user_id = ?', 'i', [$currentUserId]);

$notificationIconMap = [
    'info' => 'fa-circle-info',
    'success' => 'fa-circle-check',
    'warning' => 'fa-triangle-exclamation',
    'error' => 'fa-circle-xmark',
    'approval' => 'fa-clipboard-check',
    'team' => 'fa-shield-halved',
    'match' => 'fa-futbol',
    'user' => 'fa-user-plus',
];
?>

<div class="notifications-page">
    <div class="notifications-hero">
        <div>
            <span class="notifications-kicker">Notification Center</span>
            <h2>All Notifications</h2>
            <p>Track every saved system activity, approval, user change, team update, and operational movement in one clear view.</p>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <input type="hidden" name="action" value="mark_all_read">
            <button class="btn btn-primary" type="submit" <?= $totalUnread <= 0 ? 'disabled' : ''; ?>>
                <i class="fa-regular fa-circle-check"></i>
                Mark all read
            </button>
        </form>
    </div>

    <div class="notifications-summary">
        <div class="notifications-summary-item">
            <span>Total</span>
            <strong><?= (int) $totalAll; ?></strong>
        </div>
        <div class="notifications-summary-item unread">
            <span>Unread</span>
            <strong><?= (int) $totalUnread; ?></strong>
        </div>
        <div class="notifications-summary-item">
            <span>Showing</span>
            <strong><?= (int) $totalItems; ?></strong>
        </div>
    </div>

    <div class="notifications-toolbar card">
        <form method="get" class="notifications-filter-form">
            <input type="hidden" name="page" value="notifications">
            <input type="text" name="search" placeholder="Search notifications..." value="<?= e($search); ?>">
            <select name="filter">
                <option value="all" <?= $filter === 'all' ? 'selected' : ''; ?>>All notifications</option>
                <option value="unread" <?= $filter === 'unread' ? 'selected' : ''; ?>>Unread only</option>
                <option value="read" <?= $filter === 'read' ? 'selected' : ''; ?>>Read only</option>
            </select>
            <button class="btn btn-light" type="submit">Apply</button>
        </form>
    </div>

    <div class="notifications-list-card card">
        <?php if (empty($notificationsPageRows)): ?>
            <div class="notif-page-empty">
                <i class="fa-regular fa-bell-slash"></i>
                <h3>No notifications found</h3>
                <p>New user activity notifications will appear here automatically.</p>
            </div>
        <?php else: ?>
            <div class="notifications-feed">
                <?php foreach ($notificationsPageRows as $item):
                    $type = $item['type'] ?: 'info';
                    $iconClass = $notificationIconMap[$type] ?? 'fa-circle-info';
                    $isUnread = (int) $item['is_read'] === 0;
                ?>
                    <article class="notification-row <?= $isUnread ? 'unread' : ''; ?>">
                        <div class="notification-row-icon notif-<?= e($type); ?>">
                            <i class="fa-solid <?= e($iconClass); ?>"></i>
                        </div>
                        <div class="notification-row-body">
                            <div class="notification-row-head">
                                <div>
                                    <h3><?= e($item['title']); ?></h3>
                                    <span><?= e(humanize_activity_text($type)); ?> • <?= e(notif_time_ago($item['created_at'])); ?></span>
                                </div>
                                <?php if ($isUnread): ?>
                                    <span class="notification-status">Unread</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($item['message'])): ?>
                                <p><?= e($item['message']); ?></p>
                            <?php endif; ?>
                            <div class="notification-row-foot">
                                <time><?= e(date('d M Y H:i', strtotime($item['created_at']))); ?></time>
                                <?php if ($isUnread): ?>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="id" value="<?= (int) $item['id']; ?>">
                                        <button class="btn btn-light btn-sm" type="submit">Mark read</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php render_pagination($totalItems); ?>
</div>
