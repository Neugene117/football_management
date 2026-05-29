<?php
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid request token.');
        redirect_to('index.php?page=competitions');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_competition') {
        if (!current_user_can('competitions.manage')) {
            set_flash('danger', 'You do not have permission to manage competitions.');
            redirect_to('index.php?page=competitions');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $type = trim($_POST['type'] ?? 'league');
        $seasonId = (int) ($_POST['season_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        $description = trim($_POST['description'] ?? '');

        if ($name === '' || $seasonId <= 0) {
            set_flash('danger', 'Competition name and Season are required.');
            redirect_to('index.php?page=competitions');
        }

        [$okUpload, $logoPath] = upload_file('logo', 'uploads/competitions');
        if (!$okUpload) {
            set_flash('danger', $logoPath);
            redirect_to('index.php?page=competitions');
        }

        $slug = create_slug($name);
        $isActive = ($status === 'active') ? 1 : 0;
        $federationId = get_default_federation_id();

        if ($id > 0) {
            if (!current_user_can('competitions.manage')) {
                set_flash('danger', 'You do not have permission to edit competitions.');
                redirect_to('index.php?page=competitions');
            }

            $existing = db_fetch_one('SELECT * FROM competitions WHERE id = ?', 'i', [$id]);
            if (!$existing) {
                set_flash('danger', 'Competition not found.');
                redirect_to('index.php?page=competitions');
            }

            $currentLogo = $existing['logo'];
            $logoToSave = $logoPath ?: $currentLogo;

            $done = db_execute(
                'UPDATE competitions SET season_id=?, name=?, slug=?, type=?, logo=?, description=?, is_active=?, updated_at=NOW() WHERE id=?',
                'isssssii',
                [$seasonId, $name, $slug, $type, $logoToSave, $description ?: null, $isActive, $id]
            );

            if ($done) {
                log_action('competition_updated', 'competitions', 'competitions', $id, $existing, ['name' => $name]);
                set_flash('success', 'Competition updated successfully.');
            } else {
                set_flash('danger', 'Failed to update competition.');
            }
        } else {
            if (!current_user_can('competitions.manage')) {
                set_flash('danger', 'You do not have permission to create competitions.');
                redirect_to('index.php?page=competitions');
            }

            $done = db_execute(
                'INSERT INTO competitions (federation_id, season_id, name, slug, type, logo, description, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                'iisssssi',
                [$federationId, $seasonId, $name, $slug, $type, $logoPath, $description ?: null, $isActive]
            );

            if ($done) {
                $newId = db_last_id();
                log_action('competition_created', 'competitions', 'competitions', $newId, null, ['name' => $name]);
                set_flash('success', 'Competition created successfully.');
            } else {
                set_flash('danger', 'Failed to create competition.');
            }
        }

        redirect_to('index.php?page=competitions');
    }

    if ($action === 'delete_competition') {
        if (!current_user_can('competitions.manage')) {
            set_flash('danger', 'You do not have permission to delete competitions.');
            redirect_to('index.php?page=competitions');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $old = db_fetch_one('SELECT * FROM competitions WHERE id = ?', 'i', [$id]);
        if (!$old) {
            set_flash('danger', 'Competition not found.');
            redirect_to('index.php?page=competitions');
        }

        // Integrity Checks
        $matchCount = db_table_count('matches', 'competition_id = ?', 'i', [$id]);
        $teamEnrollCount = db_table_count('competition_teams', 'competition_id = ?', 'i', [$id]);

        if ($matchCount > 0 || $teamEnrollCount > 0) {
            set_flash('danger', 'Cannot delete competition. It is currently referenced by scheduled matches or enrolled teams.');
            redirect_to('index.php?page=competitions');
        }

        if (db_execute('DELETE FROM competitions WHERE id = ?', 'i', [$id])) {
            log_action('competition_deleted', 'competitions', 'competitions', $id, $old, null);
            set_flash('success', 'Competition deleted successfully.');
        } else {
            set_flash('danger', 'Failed to delete competition.');
        }

        redirect_to('index.php?page=competitions');
    }
}

if (!empty($_GET['edit'])) {
    $editing = db_fetch_one('SELECT * FROM competitions WHERE id = ?', 'i', [(int) $_GET['edit']]);
}

$search = trim($_GET['search'] ?? '');
$where = '1=1';
$types = '';
$params = [];
if ($search !== '') {
    $where .= ' AND (c.name LIKE ? OR c.type LIKE ? OR s.name LIKE ?)';
    $types .= 'sss';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$offset = 0;
$limitClause = paginate_clause($offset);
$competitions = db_fetch_all(
    "SELECT c.*, s.name AS season_name 
     FROM competitions c 
     INNER JOIN seasons s ON s.id = c.season_id 
     WHERE {$where} 
     ORDER BY c.created_at DESC {$limitClause}",
    $types,
    $params
);

$totalCompetitionsRows = db_fetch_one(
    "SELECT COUNT(*) total FROM competitions c INNER JOIN seasons s ON s.id = c.season_id WHERE {$where}",
    $types,
    $params
);
$totalItems = (int) ($totalCompetitionsRows['total'] ?? 0);

$seasons = db_fetch_all('SELECT id, name FROM seasons ORDER BY id DESC');
?>

<div class="card">
    <div class="card-head">
        <h3>Competitions Management</h3>
        <?php if (current_user_can('competitions.manage')): ?>
            <button type="button" class="btn btn-primary" data-open-modal="#competitionModal">
                <?= icon_svg('add'); ?> Add Competition
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="toolbar">
            <div class="left">
                <form method="get" action="index.php" class="inline-form">
                    <input type="hidden" name="page" value="competitions">
                    <input type="text" name="search" placeholder="Search competitions, seasons..." value="<?= e($search); ?>">
                    <button class="btn btn-light" type="submit">Search</button>
                </form>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table" id="competitionsTable">
                <thead>
                    <tr>
                        <th>Logo</th>
                        <th>Competition Name</th>
                        <th>Type</th>
                        <th>Season</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($competitions)): ?>
                        <tr><td colspan="6"><div class="empty-state">No competitions found.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($competitions as $comp): ?>
                            <tr>
                                <td>
                                    <img src="<?= e(app_url($comp['logo'] ?: 'assets/images/federation-logo.svg')); ?>" alt="logo" class="img-sm2">
                                </td>
                                <td>
                                    <span class="text-semibold"><?= e($comp['name']); ?></span>
                                </td>
                                <td>
                                    <span class="text-semibold" style="text-transform: capitalize;"><?= e($comp['type']); ?></span>
                                </td>
                                <td><?= e($comp['season_name']); ?></td>
                                <td><?= status_badge($comp['is_active'] ? 'active' : 'inactive'); ?></td>
                                <td>
                                    <div class="action-group">
                                        <?php if (current_user_can('competitions.manage')): ?>
                                            <a class="btn btn-light btn-sm" href="index.php?page=competitions&edit=<?= (int) $comp['id']; ?>">Edit</a>
                                        <?php endif; ?>
                                        <?php if (current_user_can('competitions.manage')): ?>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="delete_competition">
                                                <input type="hidden" name="id" value="<?= (int) $comp['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Are you sure you want to delete this competition?">Delete</button>
                                            </form>
                                        <?php endif; ?>
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

<div class="modal <?= $editing ? 'active' : ''; ?>" id="competitionModal">
    <div class="modal-content">
        <div class="modal-head">
            <h3><?= $editing ? 'Edit Competition' : 'Add Competition'; ?></h3>
            <button type="button" class="btn btn-light btn-sm" data-close-modal>Close</button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="save_competition">
                <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0); ?>">
                
                <div class="form-grid">
                    <label class="full">Competition Name <span class="text-danger">*</span>
                        <input type="text" name="name" required value="<?= e($editing['name'] ?? ''); ?>" placeholder="e.g. Rwanda Premier League">
                    </label>

                    <label>Type <span class="text-danger">*</span>
                        <select name="type" required>
                            <option value="league" <?= (($editing['type'] ?? '') === 'league') ? 'selected' : ''; ?>>League</option>
                            <option value="cup" <?= (($editing['type'] ?? '') === 'cup') ? 'selected' : ''; ?>>Cup</option>
                            <option value="friendly" <?= (($editing['type'] ?? '') === 'friendly') ? 'selected' : ''; ?>>Friendly</option>
                            <option value="tournament" <?= (($editing['type'] ?? '') === 'tournament') ? 'selected' : ''; ?>>Tournament</option>
                        </select>
                    </label>

                    <label>Season <span class="text-danger">*</span>
                        <select name="season_id" required>
                            <option value="">Select season</option>
                            <?php foreach ($seasons as $s): ?>
                                <option value="<?= (int) $s['id']; ?>" <?= ((int) ($editing['season_id'] ?? 0) === (int) $s['id']) ? 'selected' : ''; ?>><?= e($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>Status
                        <select name="status">
                            <option value="active" <?= (($editing['is_active'] ?? 1) == 1) ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?= (($editing['is_active'] ?? 1) == 0) ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </label>

                    <label>Logo
                        <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp">
                    </label>

                    <label class="full">Description
                        <textarea name="description" rows="3" placeholder="Enter competition description..."><?= e($editing['description'] ?? ''); ?></textarea>
                    </label>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-light" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Competition</button>
            </div>
        </form>
    </div>
</div>
