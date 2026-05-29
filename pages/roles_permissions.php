<?php
ensure_default_permissions();


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
            if (!current_user_can('roles.edit')) {
                set_flash('danger', 'You do not have permission to edit roles.');
                redirect_to('index.php?page=roles_permissions');
            }
            db_execute('UPDATE roles SET name=?, slug=?, scope=?, description=?, updated_at=NOW() WHERE id=?', 'ssssi', [$name, $slug, $scope, $description ?: null, $id]);
            log_action('role_updated', 'roles', 'roles', $id);
            set_flash('success', 'Role updated successfully.');
        } else {
            if (!current_user_can('roles.create')) {
                set_flash('danger', 'You do not have permission to create roles.');
                redirect_to('index.php?page=roles_permissions');
            }
            db_execute('INSERT INTO roles (name, slug, scope, description) VALUES (?, ?, ?, ?)', 'ssss', [$name, $slug, $scope, $description ?: null]);
            $id = db_last_id();
            log_action('role_created', 'roles', 'roles', $id);
            set_flash('success', 'Role created successfully.');
        }

        redirect_to('index.php?page=roles_permissions&role_id=' . $id);
    }

    if ($action === 'delete_role') {
        if (!current_user_can('roles.delete')) {
            set_flash('danger', 'You do not have permission to delete roles.');
            redirect_to('index.php?page=roles_permissions');
        }
        $id = (int) ($_POST['id'] ?? 0);
        db_execute('DELETE FROM role_permissions WHERE role_id = ?', 'i', [$id]);
        db_execute('DELETE FROM user_roles WHERE role_id = ?', 'i', [$id]);
        db_execute('DELETE FROM roles WHERE id = ?', 'i', [$id]);
        log_action('role_deleted', 'roles', 'roles', $id);
        set_flash('warning', 'Role deleted.');
        redirect_to('index.php?page=roles_permissions');
    }

    if ($action === 'save_permissions') {
        if (!current_user_can('roles.assign_permissions')) {
            set_flash('danger', 'You do not have permission to assign permissions.');
            redirect_to('index.php?page=roles_permissions');
        }
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $permissionIds = $_POST['permission_ids'] ?? [];

        db_execute('DELETE FROM role_permissions WHERE role_id = ?', 'i', [$roleId]);
        foreach ($permissionIds as $permissionId) {
            db_execute('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)', 'ii', [$roleId, (int) $permissionId]);
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

// Fetch all permissions for the assign permissions card
$allPermissions = db_fetch_all('SELECT * FROM permissions ORDER BY module ASC, name ASC');

// Set up pagination for Permissions Library
$totalPermissionsRow = db_fetch_one('SELECT COUNT(*) AS total FROM permissions');
$totalPermissions = (int) ($totalPermissionsRow['total'] ?? 0);

$offset = 0;
$limitClause = paginate_clause($offset);
$paginatedPermissions = db_fetch_all("SELECT * FROM permissions ORDER BY module ASC, name ASC {$limitClause}");

$selectedRoleId = isset($_GET['role_id']) ? (int) $_GET['role_id'] : 0;
$selectedRole = $selectedRoleId > 0 ? db_fetch_one('SELECT * FROM roles WHERE id = ?', 'i', [$selectedRoleId]) : null;
$currentPermRows = db_fetch_all('SELECT permission_id FROM role_permissions WHERE role_id = ?', 'i', [$selectedRoleId]);
$currentPerms = array_map(static function ($row) { return (int) $row['permission_id']; }, $currentPermRows);
$permissionsByModule = [];
foreach ($allPermissions as $permission) {
    $permissionsByModule[$permission['module']][] = $permission;
}

// Count assigned permissions per role for the table
$rolePermCounts = [];
$allRolePerms = db_fetch_all('SELECT role_id, COUNT(*) as cnt FROM role_permissions GROUP BY role_id');
foreach ($allRolePerms as $rp) {
    $rolePermCounts[(int)$rp['role_id']] = (int)$rp['cnt'];
}
?>

<div class="roles-permissions-page">
    <div class="roles-permissions-hero">
        <div>
            <span class="notifications-kicker">Access Control</span>
            <h2>Roles & Permissions</h2>
            <p>Create roles, assign permissions, and control exactly which modules each role can access and manage.</p>
        </div>
        <div class="roles-permissions-actions">
            <button class="btn btn-secondary" data-open-modal="#assignPermissionModal"><i class="fa-solid fa-key"></i> Assign Permission to Role</button>
            <?php if (current_user_can('roles.create')): ?>
                <button class="btn btn-primary" data-open-modal="#roleModal"><?= icon_svg('add'); ?> Create Role</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="notifications-summary">
        <div class="notifications-summary-item">
            <span>Roles</span>
            <strong><?= count($roles); ?></strong>
        </div>
        <div class="notifications-summary-item">
            <span>Permissions</span>
            <strong><?= count($allPermissions); ?></strong>
        </div>
        <div class="notifications-summary-item">
            <span>Modules</span>
            <strong><?= count($permissionsByModule); ?></strong>
        </div>
        <div class="notifications-summary-item unread">
            <span>Selected</span>
            <strong><?= $selectedRole ? e($selectedRole['name']) : '-'; ?></strong>
        </div>
    </div>

    <div class="single-col-grid">
        <div class="card">
            <div class="card-head"><h3>Roles Management</h3></div>
            <div class="card-body">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Role</th>
                                <th>Scope</th>
                                <th>Permissions</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($roles)): ?>
                                <tr><td colspan="5"><div class="empty-state">No roles available.</div></td></tr>
                            <?php else: ?>
                                <?php foreach ($roles as $role): ?>
                                    <tr class="<?= (int) $role['id'] === $selectedRoleId ? 'selected-row' : ''; ?>">
                                        <td><?= e($role['name']); ?></td>
                                        <td><?= status_badge($role['scope']); ?></td>
                                        <td><span class="badge badge-info"><?= $rolePermCounts[(int)$role['id']] ?? 0; ?> assigned</span></td>
                                        <td><?= e($role['description'] ?: '-'); ?></td>
                                        <td>
                                            <div class="action-group">
                                                <?php if (current_user_can('roles.edit')): ?>
                                                    <a class="btn btn-light btn-sm" href="index.php?page=roles_permissions&edit=<?= (int) $role['id']; ?>">Edit</a>
                                                <?php endif; ?>
                                                <a class="btn btn-secondary btn-sm" href="index.php?page=roles_permissions&role_id=<?= (int) $role['id']; ?>"><i class="fa-solid fa-key"></i> Permissions</a>
                                                <?php if (current_user_can('roles.delete')): ?>
                                                    <form method="post">
                                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                        <input type="hidden" name="action" value="delete_role">
                                                        <input type="hidden" name="id" value="<?= (int) $role['id']; ?>">
                                                        <button class="btn btn-danger btn-sm" type="submit" data-confirm="Delete this role?">Delete</button>
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
            </div>
        </div>

        <?php if ($selectedRoleId > 0): ?>
            <div class="card">
                <div class="card-head">
                    <h3><?= $selectedRole ? 'Assign Permissions to <strong>' . e($selectedRole['name']) . '</strong>' : 'Assign Permissions'; ?></h3>
                    <?php if ($selectedRole): ?>
                        <span class="badge badge-info"><?= count($currentPerms); ?> / <?= count($allPermissions); ?> assigned</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($selectedRoleId <= 0): ?>
                        <div class="empty-state">Create roles first, then select a role to assign permissions.</div>
                    <?php else: ?>
                        <form method="post" id="permissionsForm">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                            <input type="hidden" name="action" value="save_permissions">
                            <input type="hidden" name="role_id" value="<?= $selectedRoleId; ?>">

                            <div class="permission-toolbar">
                                <div class="permission-toolbar-left">
                                    <?php if (current_user_can('roles.assign_permissions')): ?>
                                        <button type="button" class="btn btn-light btn-sm" id="selectAllPerms">Select All</button>
                                        <button type="button" class="btn btn-light btn-sm" id="deselectAllPerms">Deselect All</button>
                                    <?php endif; ?>
                                </div>
                                <div class="permission-toolbar-right">
                                    <input type="text" id="permissionSearch" placeholder="Search permissions..." class="permission-search-input">
                                </div>
                            </div>

                            <div class="permission-module-list">
                                <?php foreach ($permissionsByModule as $module => $modulePermissions): ?>
                                    <section class="permission-module-card" data-module="<?= e($module); ?>">
                                        <div class="permission-module-header">
                                            <h4><?= e(humanize_activity_text($module)); ?></h4>
                                            <label class="permission-module-toggle">
                                                <input type="checkbox" class="module-select-all" data-module="<?= e($module); ?>" <?= !current_user_can('roles.assign_permissions') ? 'disabled' : ''; ?>>
                                                <span>All</span>
                                            </label>
                                        </div>
                                        <div class="permission-check-grid">
                                            <?php foreach ($modulePermissions as $permission): ?>
                                                <label class="permission-check-card" data-name="<?= e(strtolower($permission['name'] . ' ' . $permission['slug'])); ?>">
                                                    <input type="checkbox" name="permission_ids[]" value="<?= (int) $permission['id']; ?>" class="permission-checkbox module-<?= e($module); ?>" <?= in_array((int) $permission['id'], $currentPerms, true) ? 'checked' : ''; ?> <?= !current_user_can('roles.assign_permissions') ? 'disabled' : ''; ?>>
                                                    <span>
                                                        <strong><?= e($permission['name']); ?></strong>
                                                        <small><?= e($permission['slug']); ?></small>
                                                        <?php if ($permission['description']): ?>
                                                            <em class="permission-desc"><?= e($permission['description']); ?></em>
                                                        <?php endif; ?>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-10">
                                <?php if (current_user_can('roles.assign_permissions')): ?>
                                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-key"></i> Assign</button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-primary" disabled><i class="fa-solid fa-lock"></i> Assign (Locked)</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($selectedRoleId > 0): ?>
        <div class="card permissions-library-card">
            <div class="card-head"><h3><i class="fa-solid fa-book"></i> Permissions Library</h3></div>
            <div class="card-body">
                <p class="permissions-library-desc">These are all available system permissions. They are automatically managed by the system and cannot be modified manually. Use the role assignment panel above to assign them to roles.</p>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Permission</th>
                                <th>Slug</th>
                                <th>Module</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $permIndex = $offset; foreach ($paginatedPermissions as $permission): $permIndex++; ?>
                                <tr>
                                    <td><?= $permIndex; ?></td>
                                    <td><?= e($permission['name']); ?></td>
                                    <td><span class="code-pill"><?= e($permission['slug']); ?></span></td>
                                    <td><?= status_badge($permission['module']); ?></td>
                                    <td><?= e($permission['description'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php render_pagination($totalPermissions); ?>
            </div>
        </div>
    <?php endif; ?>
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

<style>
.permission-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.permission-toolbar-left {
    display: flex;
    gap: 8px;
}
.permission-toolbar-right {
    flex: 1;
    max-width: 300px;
}
.permission-search-input {
    width: 100%;
    padding: 8px 14px;
    border: 1px solid var(--border, #e0e0e0);
    border-radius: 8px;
    font-size: 0.85rem;
    background: var(--bg-secondary, #f5f5f5);
    transition: border-color 0.2s;
}
.permission-search-input:focus {
    outline: none;
    border-color: var(--primary, #ff7a00);
    box-shadow: 0 0 0 3px rgba(255, 122, 0, 0.1);
}
.permission-module-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.permission-module-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    font-size: 0.8rem;
    color: var(--text-secondary, #666);
    user-select: none;
}
.permission-module-toggle input[type="checkbox"] {
    accent-color: var(--primary, #ff7a00);
}
.permission-desc {
    display: block;
    font-size: 0.72rem;
    color: var(--text-tertiary, #999);
    font-style: italic;
    margin-top: 2px;
}
.permissions-library-desc {
    font-size: 0.85rem;
    color: var(--text-secondary, #666);
    margin-bottom: 16px;
    padding: 10px 14px;
    background: var(--bg-secondary, #f5f5f5);
    border-radius: 8px;
    border-left: 3px solid var(--primary, #ff7a00);
}
.permission-check-card.hidden-by-search {
    display: none;
}
.permission-module-card.hidden-by-search {
    display: none;
}
.modal-permission-check-card:hover {
    border-color: var(--primary, #ff7a00) !important;
    background: var(--bg-hover, #fffcf9) !important;
    box-shadow: 0 2px 6px rgba(255, 122, 0, 0.05);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select All / Deselect All buttons
    var selectAllBtn = document.getElementById('selectAllPerms');
    var deselectAllBtn = document.getElementById('deselectAllPerms');
    var allCheckboxes = document.querySelectorAll('.permission-checkbox');

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            allCheckboxes.forEach(function(cb) {
                if (!cb.closest('.hidden-by-search')) {
                    cb.checked = true;
                }
            });
            updateModuleToggles();
        });
    }

    if (deselectAllBtn) {
        deselectAllBtn.addEventListener('click', function() {
            allCheckboxes.forEach(function(cb) {
                if (!cb.closest('.hidden-by-search')) {
                    cb.checked = false;
                }
            });
            updateModuleToggles();
        });
    }

    // Module-level select all toggles
    var moduleToggles = document.querySelectorAll('.module-select-all');
    moduleToggles.forEach(function(toggle) {
        var moduleName = toggle.getAttribute('data-module');
        var moduleCheckboxes = document.querySelectorAll('.module-' + CSS.escape(moduleName));

        // Set initial state
        var allChecked = Array.from(moduleCheckboxes).every(function(cb) { return cb.checked; });
        toggle.checked = allChecked && moduleCheckboxes.length > 0;

        toggle.addEventListener('change', function() {
            moduleCheckboxes.forEach(function(cb) { cb.checked = toggle.checked; });
        });

        // Update module toggle when individual checkboxes change
        moduleCheckboxes.forEach(function(cb) {
            cb.addEventListener('change', function() {
                toggle.checked = Array.from(moduleCheckboxes).every(function(c) { return c.checked; });
            });
        });
    });

    function updateModuleToggles() {
        moduleToggles.forEach(function(toggle) {
            var moduleName = toggle.getAttribute('data-module');
            var moduleCheckboxes = document.querySelectorAll('.module-' + CSS.escape(moduleName));
            toggle.checked = Array.from(moduleCheckboxes).every(function(c) { return c.checked; });
        });
    }

    // Permission search filter
    var searchInput = document.getElementById('permissionSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var query = this.value.toLowerCase().trim();
            var cards = document.querySelectorAll('.permission-check-card');
            var modules = document.querySelectorAll('.permission-module-card');

            cards.forEach(function(card) {
                var name = card.getAttribute('data-name') || '';
                if (query === '' || name.indexOf(query) !== -1) {
                    card.classList.remove('hidden-by-search');
                } else {
                    card.classList.add('hidden-by-search');
                }
            });

            // Hide empty modules
            modules.forEach(function(moduleSection) {
                var visibleCards = moduleSection.querySelectorAll('.permission-check-card:not(.hidden-by-search)');
                if (visibleCards.length === 0 && query !== '') {
                    moduleSection.classList.add('hidden-by-search');
                } else {
                    moduleSection.classList.remove('hidden-by-search');
                }
            });
        });
    }

    // Modal Select Role & Dynamic Permissions Loading
    var modalRoleSelect = document.getElementById('modalRoleSelect');
    var modalPermissionsContainer = document.getElementById('modalPermissionsContainer');
    var modalAssignBtn = document.getElementById('modalAssignBtn');
    var modalCheckboxes = document.querySelectorAll('.modal-permission-checkbox');

    if (modalRoleSelect) {
        modalRoleSelect.addEventListener('change', function() {
            var roleId = this.value;
            if (!roleId) {
                modalPermissionsContainer.style.display = 'none';
                modalAssignBtn.disabled = true;
                return;
            }

            // Fetch the role's current permissions via AJAX
            fetch('index.php?page=roles_permissions&get_role_permissions=1&role_id=' + roleId)
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data && data.success) {
                        var activeIds = data.permission_ids || [];
                        modalCheckboxes.forEach(function(cb) {
                            cb.checked = activeIds.indexOf(parseInt(cb.value)) !== -1;
                        });
                        updateModalModuleToggles();
                        modalPermissionsContainer.style.display = 'block';
                        modalAssignBtn.disabled = false;
                    }
                })
                .catch(function(err) {
                    console.error('Failed to fetch role permissions:', err);
                });
        });
        if (modalRoleSelect.value) {
            modalRoleSelect.dispatchEvent(new Event('change'));
        }
    }

    // Modal Select All / Deselect All
    var modalSelectAllBtn = document.getElementById('modalSelectAll');
    var modalDeselectAllBtn = document.getElementById('modalDeselectAll');
    if (modalSelectAllBtn) {
        modalSelectAllBtn.addEventListener('click', function() {
            modalCheckboxes.forEach(function(cb) {
                if (!cb.closest('.hidden-by-search')) {
                    cb.checked = true;
                }
            });
            updateModalModuleToggles();
        });
    }
    if (modalDeselectAllBtn) {
        modalDeselectAllBtn.addEventListener('click', function() {
            modalCheckboxes.forEach(function(cb) {
                if (!cb.closest('.hidden-by-search')) {
                    cb.checked = false;
                }
            });
            updateModalModuleToggles();
        });
    }

    // Modal Module Select All Toggles
    var modalModuleToggles = document.querySelectorAll('.modal-module-select-all');
    modalModuleToggles.forEach(function(toggle) {
        var moduleName = toggle.getAttribute('data-module');
        var moduleCheckboxes = document.querySelectorAll('.modal-module-' + CSS.escape(moduleName));

        toggle.addEventListener('change', function() {
            moduleCheckboxes.forEach(function(cb) { cb.checked = toggle.checked; });
        });

        moduleCheckboxes.forEach(function(cb) {
            cb.addEventListener('change', function() {
                toggle.checked = Array.from(moduleCheckboxes).every(function(c) { return c.checked; });
            });
        });
    });

    function updateModalModuleToggles() {
        modalModuleToggles.forEach(function(toggle) {
            var moduleName = toggle.getAttribute('data-module');
            var moduleCheckboxes = document.querySelectorAll('.modal-module-' + CSS.escape(moduleName));
            toggle.checked = Array.from(moduleCheckboxes).every(function(c) { return c.checked; }) && moduleCheckboxes.length > 0;
        });
    }

    // Modal Permission Search Filter
    var modalSearchInput = document.getElementById('modalPermissionSearch');
    if (modalSearchInput) {
        modalSearchInput.addEventListener('input', function() {
            var query = this.value.toLowerCase().trim();
            var cards = document.querySelectorAll('.modal-permission-check-card');
            var modules = document.querySelectorAll('.modal-module-card');

            cards.forEach(function(card) {
                var name = card.getAttribute('data-name') || '';
                if (query === '' || name.indexOf(query) !== -1) {
                    card.classList.remove('hidden-by-search');
                } else {
                    card.classList.add('hidden-by-search');
                }
            });

            modules.forEach(function(moduleSection) {
                var visibleCards = moduleSection.querySelectorAll('.modal-permission-check-card:not(.hidden-by-search)');
                if (visibleCards.length === 0 && query !== '') {
                    moduleSection.classList.add('hidden-by-search');
                } else {
                    moduleSection.classList.remove('hidden-by-search');
                }
            });
        });
    }
});
</script>

<div class="modal" id="assignPermissionModal">
    <div class="modal-content" style="max-width: 760px; width: 95%;">
        <div class="modal-head">
            <h3>Assign Permission to Role</h3>
            <button type="button" class="btn btn-light btn-sm" data-close-modal>Close</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <input type="hidden" name="action" value="save_permissions">
            <div class="modal-body">
                <div class="form-grid" style="margin-bottom: 12px;">
                    <label class="full">Select Role
                        <select name="role_id" id="modalRoleSelect" required>
                            <option value="">-- Choose a Role --</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= (int) $r['id']; ?>" <?= ($selectedRoleId === (int) $r['id']) ? 'selected' : ''; ?>><?= e($r['name']); ?> (<?= e($r['scope']); ?> scope)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div id="modalPermissionsContainer" style="display: none; border-top: 1px solid #edf1f7; padding-top: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 10px; flex-wrap: wrap;">
                        <h4 style="color: var(--navy-800); margin: 0; font-size: 14px; font-weight: 700;">Select Permissions to Assign</h4>
                        <div style="display: flex; gap: 6px;">
                            <button type="button" class="btn btn-light btn-sm" id="modalSelectAll">Select All</button>
                            <button type="button" class="btn btn-light btn-sm" id="modalDeselectAll">Deselect All</button>
                        </div>
                    </div>

                    <div style="margin-bottom: 14px;">
                        <input type="text" id="modalPermissionSearch" placeholder="Search permissions in modal..." class="permission-search-input" style="width: 100%;">
                    </div>

                    <div class="permission-module-list" style="max-height: 360px; overflow-y: auto; padding-right: 4px;">
                        <?php foreach ($permissionsByModule as $module => $modulePermissions): ?>
                            <section class="permission-module-card modal-module-card" data-module="<?= e($module); ?>" style="margin-bottom: 12px; border: 1px solid #e3e9f2; border-radius: 10px; padding: 12px; background: #fbfcff;">
                                <div class="permission-module-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <h4 style="margin: 0; color: var(--navy-800); font-size: 13px; font-weight: 700;"><?= e(humanize_activity_text($module)); ?></h4>
                                    <label class="permission-module-toggle" style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.8rem; cursor: pointer; color: #666; user-select: none;">
                                        <input type="checkbox" class="modal-module-select-all" data-module="<?= e($module); ?>">
                                        <span>All</span>
                                    </label>
                                </div>
                                <div class="permission-check-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(195px, 1fr)); gap: 8px;">
                                    <?php foreach ($modulePermissions as $permission): ?>
                                        <label class="permission-check-card modal-permission-check-card" data-name="<?= e(strtolower($permission['name'] . ' ' . $permission['slug'])); ?>" style="display: flex; align-items: flex-start; gap: 9px; padding: 10px; background: #ffffff; border: 1px solid #e4eaf3; border-radius: 8px; cursor: pointer; transition: all 0.2s;">
                                            <input type="checkbox" name="permission_ids[]" value="<?= (int) $permission['id']; ?>" class="modal-permission-checkbox modal-module-<?= e($module); ?>" style="margin-top: 2px;">
                                            <span>
                                                <strong style="display: block; color: var(--navy-800); font-size: 12.5px; margin-bottom: 2px;"><?= e($permission['name']); ?></strong>
                                                <small style="color: var(--muted); font-size: 11px;"><?= e($permission['slug']); ?></small>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-light" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary" id="modalAssignBtn" disabled>Assign</button>
            </div>
        </form>
    </div>
</div>

<style>
.single-col-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
}
</style>
