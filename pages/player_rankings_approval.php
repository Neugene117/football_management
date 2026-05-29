<?php
$statusCol = pick_status_column('player_rankings') ?: 'status';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid token.');
        redirect_to('index.php?page=player_rankings_approval');
    }

    if (!current_user_can('rankings.approve')) {
        set_flash('danger', 'You do not have permission to approve/reject player rankings.');
        redirect_to('index.php?page=player_rankings_approval');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note = trim($_POST['note'] ?? '');

    if ($action === 'approve' && set_status_value('player_rankings', $id, 'approved')) {
        log_action('player_ranking_approved', 'approvals', 'player_rankings', $id, null, ['note' => $note]);
        set_flash('success', 'Player ranking approved.');
    } elseif ($action === 'reject' && set_status_value('player_rankings', $id, 'rejected')) {
        log_action('player_ranking_rejected', 'approvals', 'player_rankings', $id, null, ['note' => $note]);
        set_flash('warning', 'Player ranking rejected.');
    } else {
        set_flash('danger', 'Unable to process ranking request.');
    }

    redirect_to('index.php?page=player_rankings_approval');
}

$rows = db_fetch_all("SELECT pr.*, p.first_name, p.last_name, c.name AS competition_name
    FROM player_rankings pr
    LEFT JOIN players p ON p.id = pr.player_id
    LEFT JOIN competitions c ON c.id = pr.competition_id
    ORDER BY pr.updated_at DESC LIMIT 40");
?>

<div class="card">
    <div class="card-head"><h3>Player Rankings Approval</h3></div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Player</th>
                        <th>Competition</th>
                        <th>Position</th>
                        <th>Rank</th>
                        <th>Score</th>
                        <th>Avg Rating</th>
                        <th>Status</th>
                        <th>Note</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="9"><div class="empty-state">No player rankings submitted.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= e(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: 'Unknown'); ?></td>
                                <td><?= e($r['competition_name'] ?: '-'); ?></td>
                                <td><?= e(ucfirst($r['position'])); ?></td>
                                <td><?= e($r['rank_position'] ?? '-'); ?></td>
                                <td><?= e($r['total_score']); ?></td>
                                <td><?= e($r['average_rating'] ?? '-'); ?></td>
                                <td><?= status_badge($r[$statusCol] ?? 'pending'); ?></td>
                                <td>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="id" value="<?= (int) $r['id']; ?>">
                                        <input type="text" name="note" placeholder="note" class="note-input">
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

