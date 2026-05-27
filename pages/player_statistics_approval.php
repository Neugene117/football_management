<?php
$statusCol = pick_status_column('player_statistics') ?: 'status';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid token.');
        redirect_to('index.php?page=player_statistics_approval');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $comment = trim($_POST['comment'] ?? '');

    if ($action === 'approve' && set_status_value('player_statistics', $id, 'approved')) {
        log_action('player_statistics_approved', 'approvals', 'player_statistics', $id, null, ['comment' => $comment]);
        set_flash('success', 'Player statistics approved.');
    } elseif ($action === 'reject' && set_status_value('player_statistics', $id, 'rejected')) {
        log_action('player_statistics_rejected', 'approvals', 'player_statistics', $id, null, ['comment' => $comment]);
        set_flash('warning', 'Player statistics rejected.');
    } else {
        set_flash('danger', 'Unable to process request.');
    }

    redirect_to('index.php?page=player_statistics_approval');
}

$rows = db_fetch_all("SELECT ps.*, p.first_name, p.last_name, c.name AS competition_name
    FROM player_statistics ps
    LEFT JOIN players p ON p.id = ps.player_id
    LEFT JOIN competitions c ON c.id = ps.competition_id
    ORDER BY ps.updated_at DESC LIMIT 40");
?>

<div class="card">
    <div class="card-head"><h3>Player Statistics Approval</h3></div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Player</th>
                        <th>Competition</th>
                        <th>Matches</th>
                        <th>Goals</th>
                        <th>Assists</th>
                        <th>Avg Rating</th>
                        <th>Status</th>
                        <th>Comment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="9"><div class="empty-state">No submitted player statistics yet.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= e(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: 'Unknown'); ?></td>
                                <td><?= e($r['competition_name'] ?: '-'); ?></td>
                                <td><?= (int) $r['matches_played']; ?></td>
                                <td><?= (int) $r['goals']; ?></td>
                                <td><?= (int) $r['assists']; ?></td>
                                <td><?= e($r['average_rating'] ?? '-'); ?></td>
                                <td><?= status_badge($r[$statusCol] ?? 'pending'); ?></td>
                                <td>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="id" value="<?= (int) $r['id']; ?>">
                                        <input type="text" name="comment" placeholder="comment" class="note-input">
                                </td>
                                <td>
                                        <button class="btn btn-secondary btn-sm" type="submit" name="action" value="approve">Approve</button>
                                        <button class="btn btn-danger btn-sm" type="submit" name="action" value="reject">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

