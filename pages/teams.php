<?php
$stadiums = db_fetch_all('SELECT id, name FROM stadiums ORDER BY name ASC');
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid request token.');
        redirect_to('index.php?page=teams');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_team') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $coach = trim($_POST['coach_name'] ?? '');
        $stadiumId = (int) ($_POST['home_stadium_id'] ?? 0);
        $foundedYear = (int) ($_POST['founded_year'] ?? 0);
        $province = trim($_POST['province'] ?? '');
        $status = $_POST['status'] ?? 'pending';
        $shortName = strtoupper(substr(trim($_POST['short_name'] ?? ''), 0, 10));

        if ($name === '') {
            set_flash('danger', 'Team name is required.');
            redirect_to('index.php?page=teams');
        }

        [$okUpload, $logoPath] = upload_file('logo', 'uploads/teams');
        if (!$okUpload) {
            set_flash('danger', $logoPath);
            redirect_to('index.php?page=teams');
        }

        $slug = create_slug($name . '-' . substr((string) time(), -4));
        $isActive = ($status === 'approved' || $status === 'active') ? 1 : 0;
        $federationId = get_default_federation_id();
        $stadium = $stadiumId > 0 ? $stadiumId : null;

        if ($id > 0) {
            $existing = db_fetch_one('SELECT * FROM teams WHERE id = ?', 'i', [$id]);
            if (!$existing) {
                set_flash('danger', 'Team not found.');
                redirect_to('index.php?page=teams');
            }

            $currentLogo = $existing['logo'];
            $logoToSave = $logoPath ?: $currentLogo;

            $done = db_execute(
                'UPDATE teams SET home_stadium_id=?, name=?, short_name=?, logo=?, city=?, founded_year=?, coach_name=?, is_active=?, updated_at=NOW() WHERE id=?',
                'issssiisi',
                [$stadium, $name, $shortName ?: null, $logoToSave, $province ?: null, $foundedYear ?: null, $coach ?: null, $isActive, $id]
            );

            if ($done) {
                log_action('team_updated', 'teams', 'teams', $id, $existing, ['name' => $name]);
                set_flash('success', 'Team updated successfully.');
            } else {
                set_flash('danger', 'Failed to update team.');
            }
        } else {
            $done = db_execute(
                'INSERT INTO teams (federation_id, home_stadium_id, name, slug, short_name, logo, city, country, founded_year, coach_name, is_active, activated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'iissssssisis',
                [
                    $federationId,
                    $stadium,
                    $name,
                    $slug,
                    $shortName ?: null,
                    $logoPath,
                    $province ?: null,
                    'Rwanda',
                    $foundedYear ?: null,
                    $coach ?: null,
                    $isActive,
                    $isActive ? date('Y-m-d H:i:s') : null,
                ]
            );

            if ($done) {
                $newId = db_last_id();
                log_action('team_created', 'teams', 'teams', $newId, null, ['name' => $name]);
                set_flash('success', 'Team registered successfully.');
            } else {
                set_flash('danger', 'Failed to register team. Check required FK records.');
            }
        }

        redirect_to('index.php?page=teams');
    }

    if ($action === 'delete_team') {
        $id = (int) ($_POST['id'] ?? 0);
        $old = db_fetch_one('SELECT * FROM teams WHERE id = ?', 'i', [$id]);
        if ($old && db_execute('DELETE FROM teams WHERE id = ?', 'i', [$id])) {
            log_action('team_deleted', 'teams', 'teams', $id, $old, null);
            set_flash('success', 'Team deleted successfully.');
        } else {
            set_flash('danger', 'Failed to delete team.');
        }
        redirect_to('index.php?page=teams');
    }

    if ($action === 'approve_team') {
        $id = (int) ($_POST['id'] ?? 0);
        if (db_execute('UPDATE teams SET is_active = 1, activated_at = NOW(), deactivated_at = NULL WHERE id = ?', 'i', [$id])) {
            log_action('team_approved', 'teams', 'teams', $id);
            set_flash('success', 'Team approved.');
        } else {
            set_flash('danger', 'Failed to approve team.');
        }
        redirect_to('index.php?page=teams');
    }
}

if (!empty($_GET['edit'])) {
    $editing = db_fetch_one('SELECT * FROM teams WHERE id = ?', 'i', [(int) $_GET['edit']]);
}

$search = trim($_GET['search'] ?? '');
$where = '1=1';
$types = '';
$params = [];
if ($search !== '') {
    $where .= ' AND (t.name LIKE ? OR t.coach_name LIKE ? OR t.city LIKE ?)';
    $types .= 'sss';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$offset = 0;
$limitClause = paginate_clause($offset);
$teams = db_fetch_all(
    "SELECT t.*, s.name AS stadium_name FROM teams t LEFT JOIN stadiums s ON s.id = t.home_stadium_id WHERE {$where} ORDER BY t.created_at DESC {$limitClause}",
    $types,
    $params
);

$totalTeamsRows = db_fetch_one("SELECT COUNT(*) total FROM teams t WHERE {$where}", $types, $params);
$totalItems = (int) ($totalTeamsRows['total'] ?? 0);
?>

<div class="card">
    <div class="card-head">
        <h3>Teams Management</h3>
        <button type="button" class="btn btn-primary" data-open-modal="#teamModal"><?= icon_svg('add'); ?> Register Team</button>
    </div>
    <div class="card-body">
        <div class="toolbar">
            <div class="left">
                <form method="get" action="index.php" class="inline-form">
                    <input type="hidden" name="page" value="teams">
                    <input type="text" name="search" placeholder="Search teams..." value="<?= e($search); ?>">
                    <button class="btn btn-light" type="submit">Search</button>
                </form>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table" id="teamsTable">
                <thead>
                    <tr>
                        <th>Logo</th>
                        <th>Team Name</th>
                        <th>Coach</th>
                        <th>Stadium</th>
                        <th>Founded</th>
                        <th>Province</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($teams)): ?>
                        <tr><td colspan="8"><div class="empty-state">No teams found.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($teams as $team): ?>
                            <tr>
                                <td>
                                    <img src="<?= e(app_url($team['logo'] ?: 'assets/images/federation-logo.svg')); ?>" alt="logo" class="img-sm">
                                </td>
                                <td><?= e($team['name']); ?></td>
                                <td><?= e($team['coach_name'] ?: '-'); ?></td>
                                <td><?= e($team['stadium_name'] ?: '-'); ?></td>
                                <td><?= e($team['founded_year'] ?: '-'); ?></td>
                                <td><?= e($team['city'] ?: '-'); ?></td>
                                <td><?= status_badge((int) $team['is_active'] === 1 ? 'approved' : 'pending'); ?></td>
                                <td>
                                    <div class="action-group">
                                        <a class="btn btn-light btn-sm" href="index.php?page=teams&edit=<?= (int) $team['id']; ?>">Edit</a>
                                        <?php if ((int) $team['is_active'] === 0): ?>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="approve_team">
                                                <input type="hidden" name="id" value="<?= (int) $team['id']; ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm">Approve</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="delete_team">
                                            <input type="hidden" name="id" value="<?= (int) $team['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete this team?">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php render_pagination($totalItems); ?>
    </div>
</div>

<div class="modal <?= $editing ? 'active' : ''; ?>" id="teamModal">
    <div class="modal-content">
        <div class="modal-head">
            <h3><?= $editing ? 'Edit Team' : 'Register Team'; ?></h3>
            <button type="button" class="btn btn-light btn-sm" data-close-modal>Close</button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="save_team">
                <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0); ?>">
                <div class="form-grid">
                    <label>Team Name
                        <input type="text" name="name" required value="<?= e($editing['name'] ?? ''); ?>">
                    </label>
                    <label>Coach
                        <input type="text" name="coach_name" value="<?= e($editing['coach_name'] ?? ''); ?>">
                    </label>
                    <label>Stadium
                        <select name="home_stadium_id">
                            <option value="">Select stadium</option>
                            <?php foreach ($stadiums as $s): ?>
                                <option value="<?= (int) $s['id']; ?>" <?= ((int) ($editing['home_stadium_id'] ?? 0) === (int) $s['id']) ? 'selected' : ''; ?>><?= e($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Founded Year
                        <input type="number" name="founded_year" min="1800" max="<?= date('Y'); ?>" value="<?= e($editing['founded_year'] ?? ''); ?>">
                    </label>
                    <label>Province
                        <input type="text" name="province" value="<?= e($editing['city'] ?? ''); ?>">
                    </label>
                    <label>Short Name
                        <input type="text" name="short_name" maxlength="10" value="<?= e($editing['short_name'] ?? ''); ?>">
                    </label>
                    <label>Status
                        <select name="status">
                            <option value="pending" <?= ((int) ($editing['is_active'] ?? 0) === 0) ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?= ((int) ($editing['is_active'] ?? 0) === 1) ? 'selected' : ''; ?>>Approved</option>
                        </select>
                    </label>
                    <label>Team Logo
                        <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp">
                    </label>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-light" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Team</button>
            </div>
        </form>
    </div>
</div>

