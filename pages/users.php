<?php
$roles = db_fetch_all('SELECT id, name FROM roles ORDER BY name ASC');
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid request token.');
        redirect_to('index.php?page=users');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_user') {
        $id = (int) ($_POST['id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $status = (int) ($_POST['status'] ?? 1);
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $userType = $_POST['user_type'] ?? 'federation';

        if ($fullName === '' || $email === '') {
            set_flash('danger', 'Full name and email are required.');
            redirect_to('index.php?page=users');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'Invalid email format.');
            redirect_to('index.php?page=users');
        }

        [$okUpload, $photoPath] = upload_file('profile_photo', 'uploads/users');
        if (!$okUpload) {
            set_flash('danger', $photoPath);
            redirect_to('index.php?page=users');
        }

        if ($id > 0) {
            $existing = db_fetch_one('SELECT * FROM users WHERE id = ?', 'i', [$id]);
            if (!$existing) {
                set_flash('danger', 'User not found.');
                redirect_to('index.php?page=users');
            }

            $username = $existing['username'];
            $photoToSave = $photoPath ?: $existing['profile_photo'];

            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ok = db_execute('UPDATE users SET email=?, full_name=?, phone=?, user_type=?, is_active=?, profile_photo=?, password_hash=?, updated_at=NOW() WHERE id=?', 'ssssissi', [$email, $fullName, $phone ?: null, $userType, $status, $photoToSave, $hash, $id]);
            } else {
                $ok = db_execute('UPDATE users SET email=?, full_name=?, phone=?, user_type=?, is_active=?, profile_photo=?, updated_at=NOW() WHERE id=?', 'ssssisi', [$email, $fullName, $phone ?: null, $userType, $status, $photoToSave, $id]);
            }

            if ($ok) {
                if ($roleId > 0) {
                    $existsRole = db_fetch_one('SELECT id FROM user_roles WHERE user_id = ? AND role_id = ?', 'ii', [$id, $roleId]);
                    if (!$existsRole) {
                        db_execute('INSERT INTO user_roles (user_id, role_id, granted_by) VALUES (?, ?, ?)', 'iii', [$id, $roleId, (int) (current_user()['id'] ?? 0)]);
                    }
                }

                log_action('user_updated', 'users', 'users', $id);
                set_flash('success', 'User updated successfully.');
            } else {
                set_flash('danger', 'Failed to update user.');
            }
        } else {
            if ($password === '') {
                set_flash('danger', 'Password is required for new users.');
                redirect_to('index.php?page=users');
            }

            $exists = db_fetch_one('SELECT id FROM users WHERE email = ?', 's', [$email]);
            if ($exists) {
                set_flash('danger', 'Email already exists.');
                redirect_to('index.php?page=users');
            }

            $usernameBase = strtolower(preg_replace('/[^a-z0-9]/', '', strstr($email, '@', true) ?: 'user'));
            $username = $usernameBase . rand(10, 99);
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $ok = db_execute('INSERT INTO users (username, email, password_hash, full_name, profile_photo, phone, user_type, entity_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', 'sssssssii', [$username, $email, $hash, $fullName, $photoPath, $phone ?: null, $userType, null, $status]);
            if ($ok) {
                $userId = db_last_id();
                if ($roleId > 0) {
                    db_execute('INSERT INTO user_roles (user_id, role_id, granted_by) VALUES (?, ?, ?)', 'iii', [$userId, $roleId, (int) (current_user()['id'] ?? 0)]);
                }
                log_action('user_created', 'users', 'users', $userId);
                set_flash('success', 'User created successfully.');
            } else {
                set_flash('danger', 'Failed to create user.');
            }
        }

        redirect_to('index.php?page=users');
    }

    if ($action === 'delete_user') {
        $id = (int) ($_POST['id'] ?? 0);
        $ok = db_execute('DELETE FROM users WHERE id = ?', 'i', [$id]);
        if ($ok) {
            log_action('user_deleted', 'users', 'users', $id);
            set_flash('success', 'User deleted successfully.');
        } else {
            set_flash('danger', 'Could not delete user.');
        }
        redirect_to('index.php?page=users');
    }

    if ($action === 'toggle_status') {
        $id = (int) ($_POST['id'] ?? 0);
        $newStatus = (int) ($_POST['new_status'] ?? 0);
        db_execute('UPDATE users SET is_active = ? WHERE id = ?', 'ii', [$newStatus, $id]);
        log_action($newStatus ? 'user_activated' : 'user_suspended', 'users', 'users', $id);
        set_flash('success', $newStatus ? 'User activated.' : 'User suspended.');
        redirect_to('index.php?page=users');
    }
}

if (!empty($_GET['edit'])) {
    $editing = db_fetch_one('SELECT * FROM users WHERE id = ?', 'i', [(int) $_GET['edit']]);
}

$search = trim($_GET['search'] ?? '');
$where = '1=1';
$types = '';
$params = [];
if ($search !== '') {
    $where .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $types .= 'sss';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$offset = 0;
$limitClause = paginate_clause($offset);
$users = db_fetch_all(
    "SELECT u.*, GROUP_CONCAT(r.name SEPARATOR ', ') AS role_names
     FROM users u
     LEFT JOIN user_roles ur ON ur.user_id = u.id
     LEFT JOIN roles r ON r.id = ur.role_id
     WHERE {$where}
     GROUP BY u.id
     ORDER BY u.created_at DESC {$limitClause}",
    $types,
    $params
);
$totalUsersRows = db_fetch_one("SELECT COUNT(*) total FROM users u WHERE {$where}", $types, $params);
$totalItems = (int) ($totalUsersRows['total'] ?? 0);
?>

<div class="card">
    <div class="card-head">
        <h3>Users Management</h3>
        <button type="button" class="btn btn-primary" data-open-modal="#userModal"><?= icon_svg('add'); ?> Add User</button>
    </div>
    <div class="card-body">
        <div class="toolbar">
            <div class="left">
                <form method="get" action="index.php" class="inline-form">
                    <input type="hidden" name="page" value="users">
                    <input type="text" name="search" placeholder="Search users..." value="<?= e($search); ?>">
                    <button class="btn btn-light" type="submit">Search</button>
                </form>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="8"><div class="empty-state">No users found.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><img src="<?= e(app_url($u['profile_photo'] ?: 'assets/images/federation-logo.svg')); ?>" class="img-xs" alt="photo"></td>
                                <td><?= e($u['full_name']); ?></td>
                                <td><?= e($u['email']); ?></td>
                                <td><?= e($u['phone'] ?: '-'); ?></td>
                                <td><?= e($u['role_names'] ?: '-'); ?></td>
                                <td><?= e(ucfirst($u['user_type'])); ?></td>
                                <td><?= status_badge((int) $u['is_active'] === 1 ? 'active' : 'suspended'); ?></td>
                                <td>
                                    <div class="action-group">
                                        <a class="btn btn-light btn-sm" href="index.php?page=users&edit=<?= (int) $u['id']; ?>">Edit</a>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?= (int) $u['id']; ?>">
                                            <input type="hidden" name="new_status" value="<?= (int) $u['is_active'] === 1 ? 0 : 1; ?>">
                                            <button class="btn btn-secondary btn-sm" type="submit"><?= (int) $u['is_active'] === 1 ? 'Suspend' : 'Activate'; ?></button>
                                        </form>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="id" value="<?= (int) $u['id']; ?>">
                                            <button class="btn btn-danger btn-sm" type="submit" data-confirm="Delete this user?">Delete</button>
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

<div class="modal <?= $editing ? 'active' : ''; ?>" id="userModal">
    <div class="modal-content">
        <div class="modal-head">
            <h3><?= $editing ? 'Edit User' : 'Add User'; ?></h3>
            <button type="button" class="btn btn-light btn-sm" data-close-modal>Close</button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="save_user">
                <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0); ?>">
                <div class="form-grid">
                    <label>Full Name
                        <input type="text" name="full_name" required value="<?= e($editing['full_name'] ?? ''); ?>">
                    </label>
                    <label>Email
                        <input type="email" name="email" required value="<?= e($editing['email'] ?? ''); ?>">
                    </label>
                    <label>Phone
                        <input type="text" name="phone" value="<?= e($editing['phone'] ?? ''); ?>">
                    </label>
                    <label>Password <?= $editing ? '(leave empty to keep)' : ''; ?>
                        <input type="password" name="password" <?= $editing ? '' : 'required'; ?>>
                    </label>
                    <label>Role
                        <select name="role_id">
                            <option value="">Select role</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= (int) $role['id']; ?>"><?= e($role['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>User Type
                        <select name="user_type">
                            <option value="federation" <?= (($editing['user_type'] ?? '') === 'federation') ? 'selected' : ''; ?>>Federation</option>
                            <option value="club" <?= (($editing['user_type'] ?? '') === 'club') ? 'selected' : ''; ?>>Club</option>
                            <option value="admin" <?= (($editing['user_type'] ?? '') === 'admin') ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </label>
                    <label>Status
                        <select name="status">
                            <option value="1" <?= ((int) ($editing['is_active'] ?? 1) === 1) ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?= ((int) ($editing['is_active'] ?? 1) === 0) ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </label>
                    <label>Profile Image
                        <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp">
                    </label>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-light" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save User</button>
            </div>
        </form>
    </div>
</div>

