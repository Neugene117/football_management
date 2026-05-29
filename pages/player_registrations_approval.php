<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid security token.');
        redirect_to('index.php?page=player_registrations_approval');
    }

    if (!current_user_can('player_registrations.approve')) {
        set_flash('danger', 'You do not have permission to approve/reject player registrations.');
        redirect_to('index.php?page=player_registrations_approval');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $player = db_fetch_one("SELECT p.*, t.name AS team_name FROM players p LEFT JOIN teams t ON t.id = p.team_id WHERE p.id = ?", 'i', [$id]);

    if ($player) {
        $playerName = trim($player['first_name'] . ' ' . $player['last_name']);
        
        if ($action === 'approve') {
            db_execute("UPDATE players SET status = 'active' WHERE id = ?", 'i', [$id]);
            log_action('player_approved', 'players', 'players', $id, null, ['name' => $playerName]);
            
            // Notify team users of that club
            $teamUsers = db_fetch_all("SELECT id FROM users WHERE user_type = 'club' AND entity_id = ?", 'i', [$player['team_id']]);
            foreach ($teamUsers as $u) {
                create_notification(
                    (int) $u['id'],
                    'success',
                    'Player Registration Approved',
                    "Your registration request for player '{$playerName}' has been approved by the federation."
                );
            }
            
            set_flash('success', "Player registration for '{$playerName}' approved.");
        } elseif ($action === 'reject') {
            // Delete player record so the team can re-register them with correct info if needed
            db_execute("DELETE FROM players WHERE id = ?", 'i', [$id]);
            log_action('player_rejected', 'players', 'players', $id, null, ['name' => $playerName]);
            
            // Notify team users of that club
            $teamUsers = db_fetch_all("SELECT id FROM users WHERE user_type = 'club' AND entity_id = ?", 'i', [$player['team_id']]);
            foreach ($teamUsers as $u) {
                create_notification(
                    (int) $u['id'],
                    'error',
                    'Player Registration Rejected',
                    "Your registration request for player '{$playerName}' was rejected and removed. Please re-register with correct information."
                );
            }
            
            set_flash('warning', "Player registration for '{$playerName}' rejected and removed.");
        }
    } else {
        set_flash('danger', 'Player record not found.');
    }

    redirect_to('index.php?page=player_registrations_approval');
}

// Fetch all players pending approval
$pendingPlayers = db_fetch_all("
    SELECT p.*, t.name AS team_name 
    FROM players p 
    LEFT JOIN teams t ON t.id = p.team_id 
    WHERE p.status = 'inactive' 
    ORDER BY p.created_at DESC 
    LIMIT 50
");
?>

<div class="card">
    <div class="card-head">
        <h3>Pending Player Registrations Approval</h3>
    </div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Player Name</th>
                        <th>Team</th>
                        <th>Position</th>
                        <th>Jersey #</th>
                        <th>Nationality</th>
                        <th>Age / DOB</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pendingPlayers)): ?>
                        <tr><td colspan="8"><div class="empty-state">No pending player registrations to approve.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($pendingPlayers as $p): ?>
                            <tr>
                                <td>
                                    <div class="avatar avatar-sm">
                                        <?php if ($p['photo_pl']): ?>
                                            <img src="<?= e(app_url($p['photo_pl'])); ?>" alt="Photo" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover;">
                                        <?php else: ?>
                                            <div style="width: 38px; height: 38px; border-radius: 50%; background: #edf2f8; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #7b93b0;">
                                                <?= strtoupper(substr($p['first_name'], 0, 1) . substr($p['last_name'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--navy-800);">
                                        <?= e($p['first_name'] . ' ' . $p['last_name']); ?>
                                    </div>
                                    <?php if ($p['market_value']): ?>
                                        <small class="muted">Value: $<?= number_format($p['market_value']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: var(--navy-700);"><?= e($p['team_name'] ?: 'No Team'); ?></span>
                                </td>
                                <td><span class="badge badge-info"><?= e(ucfirst($p['position'])); ?></span></td>
                                <td><strong>#<?= e($p['jersey_number'] ?? '-'); ?></strong></td>
                                <td><?= e($p['nationality'] ?: '-'); ?></td>
                                <td>
                                    <?php if ($p['date_of_birth']): 
                                        $dob = new DateTime($p['date_of_birth']);
                                        $today = new DateTime();
                                        $age = $today->diff($dob)->y;
                                        echo e($age . ' yrs (' . date('d M Y', strtotime($p['date_of_birth'])) . ')');
                                    else: 
                                        echo '-';
                                    endif; ?>
                                </td>
                                <td>
                                    <div class="action-group" style="display: flex; gap: 8px;">
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="id" value="<?= (int) $p['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button class="btn btn-secondary btn-sm" type="submit">Approve</button>
                                        </form>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="id" value="<?= (int) $p['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button class="btn btn-danger btn-sm" type="submit" data-confirm="Are you sure you want to reject and delete this player registration?">Reject</button>
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
