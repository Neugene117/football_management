<?php
$allRoles = db_fetch_all('SELECT id, name, slug, description FROM roles ORDER BY name ASC');
$masterRolesList = [];
$normalRolesList = [];
foreach ($allRoles as $role) {
    if (in_array($role['slug'], ['federation-role', 'team-role'], true)) {
        $masterRolesList[] = $role;
    } else {
        $normalRolesList[] = $role;
    }
}
$editing = null;
$editingRoleIds = [];

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
        $roleIds = $_POST['role_ids'] ?? [];
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

        // Fetch master role IDs for backend enforcement
        $masterRoleSlugs = ['federation-role', 'team-role'];
        $masterRoles = db_fetch_all("SELECT id FROM roles WHERE slug IN ('federation-role', 'team-role')");
        $masterRoleIds = array_column($masterRoles, 'id');

        $hasMaster = false;
        $selectedMasterId = 0;
        foreach ($roleIds as $rId) {
            if (in_array((int)$rId, $masterRoleIds, true)) {
                $hasMaster = true;
                $selectedMasterId = (int)$rId;
                break;
            }
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
                // Clear existing user roles
                db_execute('DELETE FROM user_roles WHERE user_id = ?', 'i', [$id]);

                if ($hasMaster) {
                    // Only save the master role
                    db_execute('INSERT INTO user_roles (user_id, role_id, granted_by) VALUES (?, ?, ?)', 'iii', [$id, $selectedMasterId, (int) (current_user()['id'] ?? 0)]);
                } else {
                    // Save all normal roles
                    foreach ($roleIds as $rId) {
                        $rId = (int) $rId;
                        if ($rId > 0) {
                            db_execute('INSERT INTO user_roles (user_id, role_id, granted_by) VALUES (?, ?, ?)', 'iii', [$id, $rId, (int) (current_user()['id'] ?? 0)]);
                        }
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

                if ($hasMaster) {
                    db_execute('INSERT INTO user_roles (user_id, role_id, granted_by) VALUES (?, ?, ?)', 'iii', [$userId, $selectedMasterId, (int) (current_user()['id'] ?? 0)]);
                } else {
                    foreach ($roleIds as $rId) {
                        $rId = (int) $rId;
                        if ($rId > 0) {
                            db_execute('INSERT INTO user_roles (user_id, role_id, granted_by) VALUES (?, ?, ?)', 'iii', [$userId, $rId, (int) (current_user()['id'] ?? 0)]);
                        }
                    }
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
    if ($editing) {
        $userRolesList = db_fetch_all('SELECT role_id FROM user_roles WHERE user_id = ?', 'i', [(int) $editing['id']]);
        $editingRoleIds = array_column($userRolesList, 'role_id');
    }
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
    <div class="modal-content user-modal-content">
        <div class="modal-head">
            <h3><?= $editing ? 'Edit User' : 'Add User'; ?></h3>
            <button type="button" class="btn btn-light btn-sm" data-close-modal>Close</button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="save_user">
                <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0); ?>">

                <div class="modal-landscape-layout">
                    <!-- Left Side: Basic Info -->
                    <div class="modal-form-main">
                        <span class="form-section-title">Basic Information</span>
                        <div class="form-grid-inner">
                            <label class="form-label-wrap">Full Name
                                <input type="text" name="full_name" required value="<?= e($editing['full_name'] ?? ''); ?>">
                            </label>
                            <label class="form-label-wrap">Email
                                <input type="email" name="email" required value="<?= e($editing['email'] ?? ''); ?>">
                            </label>
                            <label class="form-label-wrap">Phone
                                <input type="text" name="phone" value="<?= e($editing['phone'] ?? ''); ?>">
                            </label>
                            <label class="form-label-wrap">Password <?= $editing ? '(leave empty to keep)' : ''; ?>
                                <input type="password" name="password" <?= $editing ? '' : 'required'; ?>>
                            </label>
                            <label class="form-label-wrap">User Type
                                <select name="user_type">
                                    <option value="federation" <?= (($editing['user_type'] ?? '') === 'federation') ? 'selected' : ''; ?>>Federation</option>
                                    <option value="club" <?= (($editing['user_type'] ?? '') === 'club') ? 'selected' : ''; ?>>Club</option>
                                    <option value="admin" <?= (($editing['user_type'] ?? '') === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </label>
                            <label class="form-label-wrap">Status
                                <select name="status">
                                    <option value="1" <?= ((int) ($editing['is_active'] ?? 1) === 1) ? 'selected' : ''; ?>>Active</option>
                                    <option value="0" <?= ((int) ($editing['is_active'] ?? 1) === 0) ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </label>
                            <label class="form-label-wrap full-width">Profile Image
                                <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp">
                            </label>
                        </div>
                    </div>

                    <!-- Right Side: Roles -->
                    <div class="modal-form-sidebar">
                        <div class="role-selection-section">
                            <span class="form-section-title">Roles & Access Control</span>

                            <div class="roles-group-wrap">
                                <!-- Master Roles -->
                                <div class="roles-sub-group master-roles-group">
                                    <h5>Master Roles <span class="badge badge-accent">Main / Single Select</span></h5>
                                    <div class="roles-grid">
                                        <?php foreach ($masterRolesList as $role):
                                            $isChecked = in_array((int)$role['id'], $editingRoleIds, true);
                                        ?>
                                            <label class="role-card-item master-role-card <?= $isChecked ? 'active' : ''; ?>">
                                                <input type="checkbox" name="role_ids[]" value="<?= (int)$role['id']; ?>"
                                                       data-is-master="1" class="role-checkbox"
                                                       <?= $isChecked ? 'checked' : ''; ?>>
                                                <div class="role-card-content">
                                                    <div class="role-card-header">
                                                        <i class="fa-solid fa-crown role-crown-icon"></i>
                                                        <span class="role-name"><?= e($role['name']); ?></span>
                                                    </div>
                                                    <span class="role-desc"><?= e($role['description'] ?: 'Full scope access'); ?></span>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Normal Roles -->
                                <div class="roles-sub-group normal-roles-group">
                                    <h5>Normal Roles <span class="badge badge-info">Multi-Select</span></h5>
                                    <div class="roles-grid">
                                        <?php foreach ($normalRolesList as $role):
                                            $isChecked = in_array((int)$role['id'], $editingRoleIds, true);
                                        ?>
                                            <label class="role-card-item normal-role-card <?= $isChecked ? 'active' : ''; ?>">
                                                <input type="checkbox" name="role_ids[]" value="<?= (int)$role['id']; ?>"
                                                       data-is-master="0" class="role-checkbox"
                                                       <?= $isChecked ? 'checked' : ''; ?>>
                                                <div class="role-card-content">
                                                    <div class="role-card-header">
                                                        <span class="role-name"><?= e($role['name']); ?></span>
                                                    </div>
                                                    <span class="role-desc"><?= e($role['description'] ?: 'Standard permissions'); ?></span>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-light" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save User</button>
            </div>
        </form>
    </div>
</div>

