<?php
$statusCol = pick_status_column('player_ratings') ?: 'status';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid token.');
        redirect_to('index.php?page=player_ratings_approval');
    }

    if (!current_user_can('ratings.approve')) {
        set_flash('danger', 'You do not have permission to approve/reject player ratings.');
        redirect_to('index.php?page=player_ratings_approval');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'approve' && set_status_value('player_ratings', $id, 'approved')) {
        log_action('player_rating_approved', 'approvals', 'player_ratings', $id);
        set_flash('success', 'Player rating approved.');
    } elseif ($action === 'reject' && set_status_value('player_ratings', $id, 'rejected')) {
        log_action('player_rating_rejected', 'approvals', 'player_ratings', $id);
        set_flash('warning', 'Player rating rejected.');
    } else {
        set_flash('danger', 'Unable to process rating request.');
    }

    redirect_to('index.php?page=player_ratings_approval');
}

$rows = db_fetch_all("SELECT pr.*, p.first_name, p.last_name, m.match_date
    FROM player_ratings pr
    LEFT JOIN players p ON p.id = pr.player_id
    LEFT JOIN matches m ON m.id = pr.match_id
    ORDER BY pr.created_at DESC LIMIT 40");
?>

<div class="card">
    <div class="card-head"><h3>Player Ratings Approval</h3></div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Player</th>
                        <th>Match Date</th>
                        <th>Rating</th>
                        <th>Performance</th>
                        <th>Coach Comment</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7"><div class="empty-state">No player ratings submitted.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= e(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: 'Unknown'); ?></td>
                                <td><?= e($r['match_date'] ?: '-'); ?></td>
                                <td><?= e($r['rating']); ?>/100</td>
                                <td><?= e(mb_strimwidth((string) ($r['performance_summary'] ?? '-'), 0, 40, '...')); ?></td>
                                <td><?= e(mb_strimwidth((string) ($r['coach_comment'] ?? '-'), 0, 30, '...')); ?></td>
                                <td><?= status_badge($r[$statusCol] ?? 'pending'); ?></td>
                                <td>
                                    <div class="action-group">
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="id" value="<?= (int) $r['id']; ?>">
                                            <button class="btn btn-secondary btn-sm" type="submit" name="action" value="approve">Approve</button>
                                        </form>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="id" value="<?= (int) $r['id']; ?>">
                                            <button class="btn btn-danger btn-sm" type="submit" name="action" value="reject">Reject</button>
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

