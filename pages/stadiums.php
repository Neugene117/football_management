<?php
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid token.');
        redirect_to('index.php?page=stadiums');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_stadium') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $capacity = (int) ($_POST['capacity'] ?? 0);
        $province = trim($_POST['province'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($name === '') {
            set_flash('danger', 'Stadium name is required.');
            redirect_to('index.php?page=stadiums');
        }

        if ($id > 0) {
            if (!current_user_can('stadiums.edit')) {
                set_flash('danger', 'You do not have permission to edit stadiums.');
                redirect_to('index.php?page=stadiums');
            }
            $ok = db_execute('UPDATE stadiums SET name=?, city=?, country=?, capacity=?, address=?, updated_at=NOW() WHERE id=?', 'sssisi', [$name, $province ?: null, 'Rwanda', $capacity ?: null, $address ?: null, $id]);
            if ($ok) {
                log_action('stadium_updated', 'stadiums', 'stadiums', $id);
                set_flash('success', 'Stadium updated.');
            }
        } else {
            if (!current_user_can('stadiums.create')) {
                set_flash('danger', 'You do not have permission to create stadiums.');
                redirect_to('index.php?page=stadiums');
            }
            $ok = db_execute('INSERT INTO stadiums (name, city, country, capacity, address) VALUES (?, ?, ?, ?, ?)', 'sssis', [$name, $province ?: null, 'Rwanda', $capacity ?: null, $address ?: null]);
            if ($ok) {
                $id = db_last_id();
                log_action('stadium_created', 'stadiums', 'stadiums', $id);
                set_flash('success', 'Stadium added.');
            }
        }

        if ($ok) {
            [$uploadOk, $imgPath] = upload_file('stadium_image', 'uploads/stadiums');
            if ($uploadOk && $imgPath) {
                $tmp = $_FILES['stadium_image']['tmp_name'];
                $info = @getimagesize($tmp);
                $mime = $_FILES['stadium_image']['type'] ?? 'image/jpeg';
                $orig = $_FILES['stadium_image']['name'] ?? basename($imgPath);
                db_execute(
                    'INSERT INTO media_files (uploaded_by, entity_type, entity_id, file_type, original_name, stored_name, file_path, mime_type, file_size_bytes, width_px, height_px) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    'isisssssiii',
                    [
                        (int) (current_user()['id'] ?? 0),
                        'stadium',
                        $id,
                        'image',
                        $orig,
                        basename($imgPath),
                        $imgPath,
                        $mime,
                        (int) ($_FILES['stadium_image']['size'] ?? 0),
                        (int) ($info[0] ?? 0),
                        (int) ($info[1] ?? 0),
                    ]
                );
            }
        }

        redirect_to('index.php?page=stadiums');
    }

    if ($action === 'delete_stadium') {
        if (!current_user_can('stadiums.delete')) {
            set_flash('danger', 'You do not have permission to delete stadiums.');
            redirect_to('index.php?page=stadiums');
        }
        $id = (int) ($_POST['id'] ?? 0);
        db_execute('DELETE FROM stadiums WHERE id = ?', 'i', [$id]);
        log_action('stadium_deleted', 'stadiums', 'stadiums', $id);
        set_flash('warning', 'Stadium deleted.');
        redirect_to('index.php?page=stadiums');
    }
}

if (!empty($_GET['edit'])) {
    $editing = db_fetch_one('SELECT * FROM stadiums WHERE id = ?', 'i', [(int) $_GET['edit']]);
}

$rows = db_fetch_all("SELECT s.*, mf.file_path AS image_path
    FROM stadiums s
    LEFT JOIN media_files mf ON mf.id = (
        SELECT m2.id FROM media_files m2 WHERE m2.entity_type='stadium' AND m2.entity_id=s.id ORDER BY m2.id DESC LIMIT 1
    )
    ORDER BY s.created_at DESC");
?>

<div class="card">
    <div class="card-head">
        <h3>Stadium Management</h3>
        <?php if (current_user_can('stadiums.create')): ?>
            <button class="btn btn-primary" data-open-modal="#stadiumModal"><?= icon_svg('add'); ?> Add Stadium</button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Stadium Name</th>
                        <th>Capacity</th>
                        <th>Province</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6"><div class="empty-state">No stadiums found.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><img src="<?= e(app_url($row['image_path'] ?: 'assets/images/federation-logo.svg')); ?>" alt="stadium" class="img-sm2"></td>
                                <td><?= e($row['name']); ?></td>
                                <td><?= e(number_format((int) $row['capacity'])); ?></td>
                                <td><?= e($row['city'] ?: '-'); ?></td>
                                <td><?= status_badge('active'); ?></td>
                                <td>
                                    <div class="action-group">
                                        <?php if (current_user_can('stadiums.edit')): ?>
                                            <a class="btn btn-light btn-sm" href="index.php?page=stadiums&edit=<?= (int) $row['id']; ?>">Edit</a>
                                        <?php endif; ?>
                                        <?php if (current_user_can('stadiums.delete')): ?>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="delete_stadium">
                                                <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                                <button class="btn btn-danger btn-sm" type="submit" data-confirm="Delete this stadium?">Delete</button>
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

<div class="modal <?= $editing ? 'active' : ''; ?>" id="stadiumModal">
    <div class="modal-content">
        <div class="modal-head">
            <h3><?= $editing ? 'Edit Stadium' : 'Add Stadium'; ?></h3>
            <button class="btn btn-light btn-sm" type="button" data-close-modal>Close</button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="save_stadium">
                <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0); ?>">
                <div class="form-grid">
                    <label>Stadium Name
                        <input type="text" name="name" required value="<?= e($editing['name'] ?? ''); ?>">
                    </label>
                    <label>Capacity
                        <input type="number" name="capacity" min="0" value="<?= e($editing['capacity'] ?? ''); ?>">
                    </label>
                    <label>Province
                        <input type="text" name="province" value="<?= e($editing['city'] ?? ''); ?>">
                    </label>
                    <label>Status
                        <select name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </label>
                    <label class="full">Address
                        <textarea name="address" rows="2"><?= e($editing['address'] ?? ''); ?></textarea>
                    </label>
                    <label class="full">Image
                        <input type="file" name="stadium_image" accept=".jpg,.jpeg,.png,.webp">
                    </label>
                </div>
            </div>
            <div class="modal-foot">
                <button class="btn btn-light" type="button" data-close-modal>Cancel</button>
                <button class="btn btn-primary" type="submit">Save Stadium</button>
            </div>
        </form>
    </div>
</div>

