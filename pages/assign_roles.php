<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid request token.');
        redirect_to('index.php?page=assign_roles');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'assign') {
        if (!current_user_can('users.assign_role')) {
            set_flash('danger', 'You do not have permission to assign roles.');
            redirect_to('index.php?page=assign_roles');
        }
        $userId = (int) ($_POST['user_id'] ?? 0);
        $roleId = (int) ($_POST['role_id'] ?? 0);

        if ($userId > 0 && $roleId > 0) {
            $exists = db_fetch_one('SELECT id FROM user_roles WHERE user_id = ? AND role_id = ?', 'ii', [$userId, $roleId]);
            if (!$exists) {
                db_execute('INSERT INTO user_roles (user_id, role_id, granted_by) VALUES (?, ?, ?)', 'iii', [$userId, $roleId, (int) (current_user()['id'] ?? 0)]);
                log_action('role_assigned', 'roles', 'users', $userId, null, ['role_id' => $roleId]);
                set_flash('success', 'Role assigned successfully.');
            } else {
                set_flash('warning', 'User already has this role.');
            }
        }
        redirect_to('index.php?page=assign_roles');
    }

    if ($action === 'remove') {
        if (!current_user_can('users.assign_role')) {
            set_flash('danger', 'You do not have permission to remove role assignments.');
            redirect_to('index.php?page=assign_roles');
        }
        $id = (int) ($_POST['id'] ?? 0);
        db_execute('DELETE FROM user_roles WHERE id = ?', 'i', [$id]);
        log_action('role_removed', 'roles', 'user_roles', $id);
        set_flash('success', 'Role assignment removed.');
        redirect_to('index.php?page=assign_roles');
    }
}

$users = db_fetch_all('SELECT id, full_name, email FROM users ORDER BY full_name ASC');
$roles = db_fetch_all('SELECT id, name FROM roles ORDER BY name ASC');
$assignments = db_fetch_all('SELECT ur.id, u.full_name, u.email, r.name AS role_name, ur.created_at FROM user_roles ur LEFT JOIN users u ON u.id=ur.user_id LEFT JOIN roles r ON r.id=ur.role_id ORDER BY ur.created_at DESC');
?>

<div class="two-col">
    <div class="card">
        <div class="card-head"><h3>Assign Role to User</h3></div>
        <div class="card-body">
            <?php if (!current_user_can('users.assign_role')): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-lock"></i>
                    <p>You do not have permission to assign roles.</p>
                </div>
            <?php else: ?>
                <form method="post" class="form-grid">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="assign">
                    <label>User
                        <select name="user_id" required>
                            <option value="">Select user</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int) $u['id']; ?>"><?= e($u['full_name']); ?> (<?= e($u['email']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Role
                        <select name="role_id" required>
                            <option value="">Select role</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= (int) $r['id']; ?>"><?= e($r['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="full">
                        <button type="submit" class="btn btn-primary">Assign Role</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3>Assigned Roles</h3></div>
        <div class="card-body">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Assigned At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assignments)): ?>
                            <tr><td colspan="5"><div class="empty-state">No role assignments yet.</div></td></tr>
                        <?php else: ?>
                            <?php foreach ($assignments as $a): ?>
                                <tr>
                                    <td><?= e($a['full_name']); ?></td>
                                    <td><?= e($a['email']); ?></td>
                                    <td><?= status_badge($a['role_name']); ?></td>
                                    <td><?= e(date('d M Y H:i', strtotime($a['created_at']))); ?></td>
                                    <td>
                                        <?php if (current_user_can('users.assign_role')): ?>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="id" value="<?= (int) $a['id']; ?>">
                                                <button class="btn btn-danger btn-sm" type="submit" data-confirm="Remove this role assignment?">Remove</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge badge-light"><i class="fa-solid fa-lock"></i> Locked</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

