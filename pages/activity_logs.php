<?php
$search = trim($_GET['search'] ?? '');
$where = '1=1';
$types = '';
$params = [];

if ($search !== '') {
    $where .= ' AND (u.full_name LIKE ? OR al.action LIKE ? OR al.module LIKE ? OR al.ip_address LIKE ?)';
    $types .= 'ssss';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$offset = 0;
$limitClause = paginate_clause($offset);
$rows = db_fetch_all(
    "SELECT al.*, u.full_name FROM activity_logs al LEFT JOIN users u ON u.id=al.user_id WHERE {$where} ORDER BY al.created_at DESC {$limitClause}",
    $types,
    $params
);
$totalRows = db_fetch_one("SELECT COUNT(*) total FROM activity_logs al LEFT JOIN users u ON u.id=al.user_id WHERE {$where}", $types, $params);
$totalItems = (int) ($totalRows['total'] ?? 0);
?>

<div class="card">
    <div class="card-head"><h3>Activity Logs</h3></div>
    <div class="card-body">
        <div class="toolbar">
            <div class="left">
                <form method="get" class="inline-form">
                    <input type="hidden" name="page" value="activity_logs">
                    <input type="text" name="search" placeholder="Search user/action/module/IP..." value="<?= e($search); ?>">
                    <button class="btn btn-light" type="submit">Search</button>
                </form>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="5"><div class="empty-state">No activity logs found.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= e(date('d M Y H:i:s', strtotime($row['created_at']))); ?></td>
                                <td><?= e($row['full_name'] ?: 'System'); ?></td>
                                <td><?= e($row['action']); ?></td>
                                <td><?= e($row['module'] ?: '-'); ?></td>
                                <td><?= e($row['ip_address'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php render_pagination($totalItems); ?>
    </div>
</div>

