<?php
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid token.');
        redirect_to('index.php?page=seasons');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_season') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $startDate = $_POST['start_date'] ?? null;
        $endDate = $_POST['end_date'] ?? null;
        $status = $_POST['status'] ?? 'inactive';
        $isActive = $status === 'active' ? 1 : 0;

        if ($name === '') {
            set_flash('danger', 'Season name is required.');
            redirect_to('index.php?page=seasons');
        }

        if ($id > 0) {
            $ok = db_execute('UPDATE seasons SET name=?, start_date=?, end_date=?, is_active=? WHERE id=?', 'sssii', [$name, $startDate ?: null, $endDate ?: null, $isActive, $id]);
            if ($ok) {
                log_action('season_updated', 'seasons', 'seasons', $id);
                set_flash('success', 'Season updated.');
            }
        } else {
            $ok = db_execute('INSERT INTO seasons (name, start_date, end_date, is_active) VALUES (?, ?, ?, ?)', 'sssi', [$name, $startDate ?: null, $endDate ?: null, $isActive]);
            if ($ok) {
                $sid = db_last_id();
                log_action('season_created', 'seasons', 'seasons', $sid);
                set_flash('success', 'Season created.');
            }
        }
        redirect_to('index.php?page=seasons');
    }

    if ($action === 'activate') {
        $id = (int) ($_POST['id'] ?? 0);
        db_execute('UPDATE seasons SET is_active = 0');
        db_execute('UPDATE seasons SET is_active = 1 WHERE id = ?', 'i', [$id]);
        log_action('season_activated', 'seasons', 'seasons', $id);
        set_flash('success', 'Season activated.');
        redirect_to('index.php?page=seasons');
    }

    if ($action === 'close') {
        $id = (int) ($_POST['id'] ?? 0);
        db_execute('UPDATE seasons SET is_active = 0 WHERE id = ?', 'i', [$id]);
        log_action('season_closed', 'seasons', 'seasons', $id);
        set_flash('warning', 'Season closed.');
        redirect_to('index.php?page=seasons');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        db_execute('DELETE FROM seasons WHERE id = ?', 'i', [$id]);
        log_action('season_deleted', 'seasons', 'seasons', $id);
        set_flash('warning', 'Season deleted.');
        redirect_to('index.php?page=seasons');
    }
}

if (!empty($_GET['edit'])) {
    $editing = db_fetch_one('SELECT * FROM seasons WHERE id = ?', 'i', [(int) $_GET['edit']]);
}

$rows = db_fetch_all('SELECT * FROM seasons ORDER BY created_at DESC');
?>

<div class="card">
    <div class="card-head">
        <h3>Seasons Management</h3>
        <button class="btn btn-primary" data-open-modal="#seasonModal"><?= icon_svg('add'); ?> Add Season</button>
    </div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Season Name</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="5"><div class="empty-state">No seasons found.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= e($row['name']); ?></td>
                                <td><?= e($row['start_date'] ?: '-'); ?></td>
                                <td><?= e($row['end_date'] ?: '-'); ?></td>
                                <td><?= status_badge((int) $row['is_active'] === 1 ? 'active' : 'closed'); ?></td>
                                <td>
                                    <div class="action-group">
                                        <a class="btn btn-light btn-sm" href="index.php?page=seasons&edit=<?= (int) $row['id']; ?>">Edit</a>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                            <button class="btn btn-secondary btn-sm" type="submit">Activate</button>
                                        </form>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="close">
                                            <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                            <button class="btn btn-light btn-sm" type="submit">Close</button>
                                        </form>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                            <button class="btn btn-danger btn-sm" type="submit" data-confirm="Delete this season?">Delete</button>
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

<div class="modal <?= $editing ? 'active' : ''; ?>" id="seasonModal">
    <div class="modal-content">
        <div class="modal-head">
            <h3><?= $editing ? 'Edit Season' : 'Add Season'; ?></h3>
            <button type="button" class="btn btn-light btn-sm" data-close-modal>Close</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="save_season">
                <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0); ?>">
                <div class="form-grid">
                    <label>Season Name
                        <input type="text" name="name" required value="<?= e($editing['name'] ?? ''); ?>">
                    </label>
                    <label>Status
                        <select name="status">
                            <option value="active" <?= ((int) ($editing['is_active'] ?? 0) === 1) ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?= ((int) ($editing['is_active'] ?? 0) === 0) ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </label>
                    <label>Start Date
                        <input type="date" name="start_date" value="<?= e($editing['start_date'] ?? ''); ?>">
                    </label>
                    <label>End Date
                        <input type="date" name="end_date" value="<?= e($editing['end_date'] ?? ''); ?>">
                    </label>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-light" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Season</button>
            </div>
        </form>
    </div>
</div>

