<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid token.');
        redirect_to('index.php?page=team_registrations');
    }

    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'approve') {
        db_execute('UPDATE teams SET is_active = 1, activated_at = NOW(), deactivated_at = NULL WHERE id = ?', 'i', [$id]);
        log_action('team_registration_approved', 'teams', 'teams', $id);
        set_flash('success', 'Team registration approved.');
    }

    if ($action === 'reject') {
        db_execute('UPDATE teams SET is_active = 0, deactivated_at = NOW() WHERE id = ?', 'i', [$id]);
        log_action('team_registration_rejected', 'teams', 'teams', $id);
        set_flash('warning', 'Team registration marked as pending/rejected.');
    }

    redirect_to('index.php?page=team_registrations');
}

$pendingTeams = db_fetch_all('SELECT t.*, s.name AS stadium_name FROM teams t LEFT JOIN stadiums s ON s.id=t.home_stadium_id WHERE t.is_active = 0 ORDER BY t.created_at DESC LIMIT 30');
?>

<div class="card">
    <div class="card-head"><h3>Team Registrations</h3></div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Team</th>
                        <th>Coach</th>
                        <th>Stadium</th>
                        <th>Province</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pendingTeams)): ?>
                        <tr><td colspan="7"><div class="empty-state">No pending team registrations.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($pendingTeams as $team): ?>
                            <tr>
                                <td><?= e($team['name']); ?></td>
                                <td><?= e($team['coach_name'] ?: '-'); ?></td>
                                <td><?= e($team['stadium_name'] ?: '-'); ?></td>
                                <td><?= e($team['city'] ?: '-'); ?></td>
                                <td><?= e(date('d M Y H:i', strtotime($team['created_at']))); ?></td>
                                <td><?= status_badge('pending'); ?></td>
                                <td>
                                    <div class="action-group">
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="id" value="<?= (int) $team['id']; ?>">
                                            <button class="btn btn-secondary btn-sm" type="submit">Approve</button>
                                        </form>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="id" value="<?= (int) $team['id']; ?>">
                                            <button class="btn btn-danger btn-sm" type="submit">Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

