<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid token.');
        redirect_to('index.php?page=match_results_approval');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $comment = trim($_POST['comment'] ?? '');
    $adminId = (int) (current_user()['id'] ?? 0);

    if ($action === 'approve') {
        $ok = db_execute('UPDATE match_results SET status = ?, approved_by = ?, approved_at = NOW(), rejection_notes = NULL WHERE id = ?', 'sii', ['approved', $adminId, $id]);
        if ($ok) {
            db_execute('UPDATE approvals SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_notes = ? WHERE item_type = ? AND item_id = ?', 'sissi', ['approved', $adminId, $comment ?: null, 'result', $id]);
            log_action('match_result_approved', 'approvals', 'match_results', $id, null, ['comment' => $comment]);
            set_flash('success', 'Match result approved.');
        }
    }

    if ($action === 'reject') {
        $ok = db_execute('UPDATE match_results SET status = ?, approved_by = ?, approved_at = NOW(), rejection_notes = ? WHERE id = ?', 'sisi', ['rejected', $adminId, $comment ?: null, $id]);
        if ($ok) {
            db_execute('UPDATE approvals SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_notes = ? WHERE item_type = ? AND item_id = ?', 'sissi', ['rejected', $adminId, $comment ?: null, 'result', $id]);
            log_action('match_result_rejected', 'approvals', 'match_results', $id, null, ['comment' => $comment]);
            set_flash('warning', 'Match result rejected.');
        }
    }

    redirect_to('index.php?page=match_results_approval');
}

$rows = db_fetch_all("SELECT mr.*, m.match_date, ht.name AS home_team, at.name AS away_team
FROM match_results mr
LEFT JOIN matches m ON m.id = mr.match_id
LEFT JOIN teams ht ON ht.id = m.home_team_id
LEFT JOIN teams at ON at.id = m.away_team_id
ORDER BY mr.created_at DESC");
?>

<div class="card">
    <div class="card-head"><h3>Match Results Approval</h3></div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Match</th>
                        <th>Date</th>
                        <th>Score</th>
                        <th>Shots</th>
                        <th>Possession</th>
                        <th>Status</th>
                        <th>Comment</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8"><div class="empty-state">No match results submitted.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= e(($r['home_team'] ?: 'Home') . ' vs ' . ($r['away_team'] ?: 'Away')); ?></td>
                                <td><?= e($r['match_date'] ?: '-'); ?></td>
                                <td><?= (int) $r['home_score']; ?> - <?= (int) $r['away_score']; ?></td>
                                <td><?= e(($r['home_shots'] ?? 0) . ' / ' . ($r['away_shots'] ?? 0)); ?></td>
                                <td><?= e(($r['home_possession_pct'] ?? 0) . '% / ' . ($r['away_possession_pct'] ?? 0) . '%'); ?></td>
                                <td><?= status_badge($r['status']); ?></td>
                                <td>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="id" value="<?= (int) $r['id']; ?>">
                                        <input type="text" name="comment" placeholder="approval comment" class="note-input" value="<?= e($r['rejection_notes'] ?? ''); ?>">
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

