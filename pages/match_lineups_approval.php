<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid token.');
        redirect_to('index.php?page=match_lineups_approval');
    }

    if (!current_user_can('lineups.approve')) {
        set_flash('danger', 'You do not have permission to approve/reject lineups.');
        redirect_to('index.php?page=match_lineups_approval');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note = trim($_POST['note'] ?? '');
    $adminId = (int) (current_user()['id'] ?? 0);

    if ($action === 'approve') {
        $ok = db_execute('UPDATE match_lineups SET status=?, approved_by=?, approved_at=NOW(), rejection_notes=NULL WHERE id=?', 'sii', ['approved', $adminId, $id]);
        if ($ok) {
            db_execute('UPDATE approvals SET status=?, reviewed_by=?, reviewed_at=NOW(), review_notes=? WHERE item_type=? AND item_id=?', 'sissi', ['approved', $adminId, $note ?: null, 'lineup', $id]);
            log_action('lineup_approved', 'approvals', 'match_lineups', $id, null, ['note' => $note]);
            set_flash('success', 'Lineup approved.');
        }
    }

    if ($action === 'reject') {
        $ok = db_execute('UPDATE match_lineups SET status=?, approved_by=?, approved_at=NOW(), rejection_notes=? WHERE id=?', 'sisi', ['rejected', $adminId, $note ?: null, $id]);
        if ($ok) {
            db_execute('UPDATE approvals SET status=?, reviewed_by=?, reviewed_at=NOW(), review_notes=? WHERE item_type=? AND item_id=?', 'sissi', ['rejected', $adminId, $note ?: null, 'lineup', $id]);
            log_action('lineup_rejected', 'approvals', 'match_lineups', $id, null, ['note' => $note]);
            set_flash('warning', 'Lineup rejected.');
        }
    }

    redirect_to('index.php?page=match_lineups_approval');
}

$rows = db_fetch_all("SELECT ml.*, m.match_date, t.name AS team_name, f.display_name,
(SELECT COUNT(*) FROM lineup_players lp WHERE lp.lineup_id = ml.id) AS player_count,
(SELECT GROUP_CONCAT(CONCAT(p.first_name,' ',p.last_name) SEPARATOR ', ') FROM lineup_players lp2 LEFT JOIN players p ON p.id=lp2.player_id WHERE lp2.lineup_id=ml.id LIMIT 11) AS lineup_players
FROM match_lineups ml
LEFT JOIN matches m ON m.id = ml.match_id
LEFT JOIN teams t ON t.id = ml.team_id
LEFT JOIN formations f ON f.id = ml.formation_id
ORDER BY ml.updated_at DESC");
?>

<div class="card">
    <div class="card-head"><h3>Match Lineups Approval</h3></div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Match Date</th>
                        <th>Team</th>
                        <th>Formation</th>
                        <th>Players</th>
                        <th>Submitted Players</th>
                        <th>Status</th>
                        <th>Note</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8"><div class="empty-state">No lineups submitted.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= e($r['match_date'] ?: '-'); ?></td>
                                <td><?= e($r['team_name'] ?: '-'); ?></td>
                                <td><?= e($r['display_name'] ?: '-'); ?></td>
                                <td><?= (int) $r['player_count']; ?></td>
                                <td class="small text-wrap"><?= e($r['lineup_players'] ?: '-'); ?></td>
                                <td><?= status_badge($r['status']); ?></td>
                                <td>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="id" value="<?= (int) $r['id']; ?>">
                                        <input type="text" name="note" value="<?= e($r['rejection_notes'] ?? ''); ?>" placeholder="review note" class="note-input">
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

