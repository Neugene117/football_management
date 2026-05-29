<?php
$permCount = db_table_count('permissions');
if ($permCount === 0) {
    $defaultPerms = [
        ['Manage Teams', 'teams.manage', 'teams'],
        ['Approve Teams', 'teams.approve', 'teams'],
        ['Manage Users', 'users.manage', 'users'],
        ['Manage Roles', 'roles.manage', 'roles'],
        ['Approve Rankings', 'rankings.approve', 'approvals'],
        ['Approve Ratings', 'ratings.approve', 'approvals'],
        ['Approve Statistics', 'statistics.approve', 'approvals'],
        ['Manage Stadiums', 'stadiums.manage', 'stadiums'],
        ['Manage Seasons', 'seasons.manage', 'seasons'],
        ['Approve Match Results', 'results.approve', 'approvals'],
        ['Assign Match Officials', 'officials.manage', 'matches'],
        ['Approve Lineups', 'lineups.approve', 'approvals'],
        ['Manage News', 'news.manage', 'news'],
        ['View Reports', 'reports.view', 'reports'],
    ];

    foreach ($defaultPerms as $p) {
        db_execute('INSERT INTO permissions (name, slug, module) VALUES (?, ?, ?)', 'sss', $p);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid request token.');
        redirect_to('index.php?page=roles_permissions');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_role') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $scope = $_POST['scope'] ?? 'club';
        $description = trim($_POST['description'] ?? '');
        $slug = create_slug($name);

        if ($name === '') {
            set_flash('danger', 'Role name is required.');
            redirect_to('index.php?page=roles_permissions');
        }

        if ($id > 0) {
            db_execute('UPDATE roles SET name=?, slug=?, scope=?, description=?, updated_at=NOW() WHERE id=?', 'ssssi', [$name, $slug, $scope, $description ?: null, $id]);
            log_action('role_updated', 'roles', 'roles', $id);
            set_flash('success', 'Role updated successfully.');
        } else {
            db_execute('INSERT INTO roles (name, slug, scope, description) VALUES (?, ?, ?, ?)', 'ssss', [$name, $slug, $scope, $description ?: null]);
            $roleId = db_last_id();
            log_action('role_created', 'roles', 'roles', $roleId);
            set_flash('success', 'Role created successfully.');
        }

        redirect_to('index.php?page=roles_permissions');
    }

    if ($action === 'delete_role') {
        $id = (int) ($_POST['id'] ?? 0);
        db_execute('DELETE FROM roles WHERE id = ?', 'i', [$id]);
        log_action('role_deleted', 'roles', 'roles', $id);
        set_flash('warning', 'Role deleted.');
        redirect_to('index.php?page=roles_permissions');
    }

    if ($action === 'save_permissions') {
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $permissionIds = $_POST['permission_ids'] ?? [];

        db_execute('DELETE FROM role_permissions WHERE role_id = ?', 'i', [$roleId]);
        foreach ($permissionIds as $pid) {
            db_execute('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)', 'ii', [$roleId, (int) $pid]);
        }

        log_action('role_permissions_updated', 'roles', 'roles', $roleId);
        set_flash('success', 'Permissions updated successfully.');
        redirect_to('index.php?page=roles_permissions&role_id=' . $roleId);
    }
}

$editRole = null;
if (!empty($_GET['edit'])) {
    $editRole = db_fetch_one('SELECT * FROM roles WHERE id = ?', 'i', [(int) $_GET['edit']]);
}

$roles = db_fetch_all('SELECT * FROM roles ORDER BY id DESC');
$permissions = db_fetch_all('SELECT * FROM permissions ORDER BY module ASC, name ASC');
$selectedRoleId = (int) ($_GET['role_id'] ?? ($roles[0]['id'] ?? 0));
$currentPermRows = db_fetch_all('SELECT permission_id FROM role_permissions WHERE role_id = ?', 'i', [$selectedRoleId]);
$currentPerms = array_map(static function ($row) { return (int) $row['permission_id']; }, $currentPermRows);
?>

<div class="two-col">
    <div class="card">
        <div class="card-head">
            <h3>Roles Management</h3>
            <button class="btn btn-primary" data-open-modal="#roleModal"><?= icon_svg('add'); ?> Create Role</button>
        </div>
        <div class="card-body">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Scope</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($roles)): ?>
                            <tr><td colspan="4"><div class="empty-state">No roles available.</div></td></tr>
                        <?php else: ?>
                            <?php foreach ($roles as $role): ?>
                                <tr>
                                    <td><?= e($role['name']); ?></td>
                                    <td><?= status_badge($role['scope']); ?></td>
                                    <td><?= e($role['description'] ?: '-'); ?></td>
                                    <td>
                                        <div class="action-group">
                                            <a class="btn btn-light btn-sm" href="index.php?page=roles_permissions&edit=<?= (int) $role['id']; ?>">Edit</a>
                                            <a class="btn btn-secondary btn-sm" href="index.php?page=roles_permissions&role_id=<?= (int) $role['id']; ?>">Permissions</a>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="delete_role">
                                                <input type="hidden" name="id" value="<?= (int) $role['id']; ?>">
                                                <button class="btn btn-danger btn-sm" type="submit" data-confirm="Delete this role?">Delete</button>
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

    <div class="card">
        <div class="card-head"><h3>Assign Permissions</h3></div>
        <div class="card-body">
            <?php if ($selectedRoleId <= 0): ?>
                <div class="empty-state">Create roles first.</div>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="save_permissions">
                    <input type="hidden" name="role_id" value="<?= $selectedRoleId; ?>">
                    <div class="form-grid">
                        <?php foreach ($permissions as $perm): ?>
                            <label class="permission-label">
                                <input type="checkbox" name="permission_ids[]" value="<?= (int) $perm['id']; ?>" class="permission-checkbox" <?= in_array((int) $perm['id'], $currentPerms, true) ? 'checked' : ''; ?>>
                                <?= e($perm['name']); ?>
                                <span class="muted small">(<?= e($perm['module']); ?>)</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-10">
                        <button type="submit" class="btn btn-primary">Save Permissions</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal <?= $editRole ? 'active' : ''; ?>" id="roleModal">
    <div class="modal-content role-editor-modal-content">
        <div class="modal-head">
            <h3><?= $editRole ? 'Edit Role' : 'Create Role'; ?></h3>
            <button type="button" class="btn btn-light btn-sm" data-close-modal>Close</button>
        </div>
        <form method="post">
            <div class="modal-body role-editor-body">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="save_role">
                <input type="hidden" name="id" value="<?= (int) ($editRole['id'] ?? 0); ?>">
                <div class="role-editor-layout">
                    <div class="role-editor-aside">
                        <span class="role-editor-kicker">Access Control</span>
                        <h4><?= $editRole ? 'Update this role' : 'Create a clean role'; ?></h4>
                        <p>Define the role name, scope, and a short description so permissions stay easy to understand.</p>
                    </div>
                    <div class="role-editor-fields">
                        <div class="form-grid">
                            <label>Role Name
                                <input type="text" name="name" required value="<?= e($editRole['name'] ?? ''); ?>">
                            </label>
                            <label>Scope
                                <select name="scope">
                                    <option value="federation" <?= (($editRole['scope'] ?? '') === 'federation') ? 'selected' : ''; ?>>Federation</option>
                                    <option value="club" <?= (($editRole['scope'] ?? '') === 'club') ? 'selected' : ''; ?>>Club</option>
                                    <option value="global" <?= (($editRole['scope'] ?? '') === 'global') ? 'selected' : ''; ?>>Global</option>
                                </select>
                            </label>
                            <label class="full">Description
                                <textarea name="description" rows="4"><?= e($editRole['description'] ?? ''); ?></textarea>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-light" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Role</button>
            </div>
        </form>
    </div>
</div>

