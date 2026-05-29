<?php
$matches = db_fetch_all("SELECT m.id, m.match_date, ht.name AS home_team, at.name AS away_team
FROM matches m
LEFT JOIN teams ht ON ht.id=m.home_team_id
LEFT JOIN teams at ON at.id=m.away_team_id
ORDER BY m.match_date DESC LIMIT 100");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid token.');
        redirect_to('index.php?page=match_officials');
    }

    if (!current_user_can('officials.manage')) {
        set_flash('danger', 'You do not have permission to manage match officials.');
        redirect_to('index.php?page=match_officials');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $matchId = (int) ($_POST['match_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $role = $_POST['role'] ?? 'referee';

        if ($matchId <= 0 || $fullName === '') {
            set_flash('danger', 'Match and official name are required.');
            redirect_to('index.php?page=match_officials');
        }

        if ($id > 0) {
            db_execute('UPDATE match_officials SET match_id=?, full_name=?, role=? WHERE id=?', 'issi', [$matchId, $fullName, $role, $id]);
            log_action('official_updated', 'matches', 'match_officials', $id);
            set_flash('success', 'Official updated.');
        } else {
            db_execute('INSERT INTO match_officials (match_id, full_name, role) VALUES (?, ?, ?)', 'iss', [$matchId, $fullName, $role]);
            $newId = db_last_id();
            log_action('official_assigned', 'matches', 'match_officials', $newId);
            set_flash('success', 'Official assigned.');
        }
        redirect_to('index.php?page=match_officials');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        db_execute('DELETE FROM match_officials WHERE id = ?', 'i', [$id]);
        log_action('official_removed', 'matches', 'match_officials', $id);
        set_flash('warning', 'Official removed.');
        redirect_to('index.php?page=match_officials');
    }
}

$editing = null;
if (!empty($_GET['edit'])) {
    $editing = db_fetch_one('SELECT * FROM match_officials WHERE id = ?', 'i', [(int) $_GET['edit']]);
}

$rows = db_fetch_all("SELECT mo.*, m.match_date, ht.name AS home_team, at.name AS away_team
FROM match_officials mo
LEFT JOIN matches m ON m.id=mo.match_id
LEFT JOIN teams ht ON ht.id=m.home_team_id
LEFT JOIN teams at ON at.id=m.away_team_id
ORDER BY mo.created_at DESC");

function official_role_label($role)
{
    $map = [
        'referee' => 'Referee',
        'assistant_1' => 'Assistant Referee 1',
        'assistant_2' => 'Assistant Referee 2 / VAR Official',
        'fourth_official' => 'Match Commissioner (Fourth Official)',
    ];
    return $map[$role] ?? ucfirst($role);
}
?>

<div class="card">
    <div class="card-head">
        <h3>Match Officials</h3>
        <?php if (current_user_can('officials.manage')): ?>
            <button class="btn btn-primary" data-open-modal="#officialModal"><?= icon_svg('add'); ?> Assign Official</button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Match</th>
                        <th>Date</th>
                        <th>Official Name</th>
                        <th>Role</th>
                        <?php if (current_user_can('officials.manage')): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="5"><div class="empty-state">No match officials assigned.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= e(($r['home_team'] ?: 'Home') . ' vs ' . ($r['away_team'] ?: 'Away')); ?></td>
                                <td><?= e($r['match_date'] ?: '-'); ?></td>
                                <td><?= e($r['full_name']); ?></td>
                                <td><?= status_badge(official_role_label($r['role'])); ?></td>
                                <?php if (current_user_can('officials.manage')): ?>
                                    <td>
                                        <div class="action-group">
                                            <a class="btn btn-light btn-sm" href="index.php?page=match_officials&edit=<?= (int) $r['id']; ?>">Edit</a>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $r['id']; ?>">
                                                <button class="btn btn-danger btn-sm" type="submit" data-confirm="Remove this official?">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal <?= $editing ? 'active' : ''; ?>" id="officialModal">
    <div class="modal-content">
        <div class="modal-head">
            <h3><?= $editing ? 'Edit Official Assignment' : 'Assign Match Official'; ?></h3>
            <button type="button" class="btn btn-light btn-sm" data-close-modal>Close</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0); ?>">
                <div class="form-grid">
                    <label>Match
                        <select name="match_id" required>
                            <option value="">Select match</option>
                            <?php foreach ($matches as $m): ?>
                                <option value="<?= (int) $m['id']; ?>" <?= ((int) ($editing['match_id'] ?? 0) === (int) $m['id']) ? 'selected' : ''; ?>><?= e(($m['home_team'] ?: 'Home') . ' vs ' . ($m['away_team'] ?: 'Away') . ' - ' . $m['match_date']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Official Name
                        <input type="text" name="full_name" required value="<?= e($editing['full_name'] ?? ''); ?>">
                    </label>
                    <label>Role
                        <select name="role">
                            <option value="referee" <?= (($editing['role'] ?? '') === 'referee') ? 'selected' : ''; ?>>Referee</option>
                            <option value="assistant_1" <?= (($editing['role'] ?? '') === 'assistant_1') ? 'selected' : ''; ?>>Assistant Referee 1</option>
                            <option value="assistant_2" <?= (($editing['role'] ?? '') === 'assistant_2') ? 'selected' : ''; ?>>Assistant Referee 2 / VAR Official</option>
                            <option value="fourth_official" <?= (($editing['role'] ?? '') === 'fourth_official') ? 'selected' : ''; ?>>Match Commissioner</option>
                        </select>
                    </label>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-light" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Assignment</button>
            </div>
        </form>
    </div>
</div>

