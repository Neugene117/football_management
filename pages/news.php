<?php
$authors = db_fetch_all('SELECT id, full_name FROM users ORDER BY full_name ASC');
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid token.');
        redirect_to('index.php?page=news');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_news') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $authorId = (int) ($_POST['author_id'] ?? 0);
        $isPublished = (int) ($_POST['is_published'] ?? 0);
        $publishedDate = $_POST['published_at'] ?? null;

        if ($title === '' || $description === '') {
            set_flash('danger', 'Title and description are required.');
            redirect_to('index.php?page=news');
        }

        [$uploadOk, $thumbPath] = upload_file('thumbnail', 'uploads/news');
        if (!$uploadOk) {
            set_flash('danger', $thumbPath);
            redirect_to('index.php?page=news');
        }

        $slug = create_slug($title . '-' . substr((string) time(), -4));

        if ($id > 0) {
            $existing = db_fetch_one('SELECT * FROM news WHERE id = ?', 'i', [$id]);
            $cover = $thumbPath ?: ($existing['cover_image'] ?? null);

            db_execute('UPDATE news SET author_id=?, title=?, slug=?, content=?, cover_image=?, is_published=?, published_at=?, updated_at=NOW() WHERE id=?', 'issssisi', [$authorId ?: null, $title, $slug, $description, $cover, $isPublished, $isPublished ? ($publishedDate ?: date('Y-m-d H:i:s')) : null, $id]);
            log_action('news_updated', 'news', 'news', $id);
            set_flash('success', 'News updated successfully.');
        } else {
            db_execute('INSERT INTO news (author_id, title, slug, content, cover_image, is_published, published_at) VALUES (?, ?, ?, ?, ?, ?, ?)', 'issssis', [$authorId ?: null, $title, $slug, $description, $thumbPath, $isPublished, $isPublished ? ($publishedDate ?: date('Y-m-d H:i:s')) : null]);
            $nid = db_last_id();
            log_action('news_created', 'news', 'news', $nid);
            set_flash('success', 'News created successfully.');
        }

        redirect_to('index.php?page=news');
    }

    if ($action === 'delete_news') {
        $id = (int) ($_POST['id'] ?? 0);
        db_execute('DELETE FROM news WHERE id = ?', 'i', [$id]);
        log_action('news_deleted', 'news', 'news', $id);
        set_flash('warning', 'News deleted.');
        redirect_to('index.php?page=news');
    }

    if ($action === 'publish') {
        $id = (int) ($_POST['id'] ?? 0);
        db_execute('UPDATE news SET is_published = 1, published_at = NOW() WHERE id = ?', 'i', [$id]);
        log_action('news_published', 'news', 'news', $id);
        set_flash('success', 'News published.');
        redirect_to('index.php?page=news');
    }
}

if (!empty($_GET['edit'])) {
    $editing = db_fetch_one('SELECT * FROM news WHERE id = ?', 'i', [(int) $_GET['edit']]);
}

$rows = db_fetch_all('SELECT n.*, u.full_name AS author_name FROM news n LEFT JOIN users u ON u.id = n.author_id ORDER BY n.created_at DESC');
?>

<div class="card">
    <div class="card-head">
        <h3>News Management</h3>
        <button class="btn btn-primary" data-open-modal="#newsModal"><?= icon_svg('add'); ?> Create News</button>
    </div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Thumbnail</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Published Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6"><div class="empty-state">No news available.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><img src="<?= e(app_url($row['cover_image'] ?: 'assets/images/federation-logo.svg')); ?>" alt="thumb" class="media-thumb"></td>
                                <td><?= e($row['title']); ?></td>
                                <td><?= e($row['author_name'] ?: 'Unknown'); ?></td>
                                <td><?= e($row['published_at'] ?: '-'); ?></td>
                                <td><?= status_badge((int) $row['is_published'] === 1 ? 'published' : 'draft'); ?></td>
                                <td>
                                    <div class="action-group">
                                        <a class="btn btn-light btn-sm" href="index.php?page=news&edit=<?= (int) $row['id']; ?>">Edit</a>
                                        <?php if ((int) $row['is_published'] === 0): ?>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="publish">
                                                <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                                <button class="btn btn-secondary btn-sm" type="submit">Publish</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="delete_news">
                                            <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                            <button class="btn btn-danger btn-sm" type="submit" data-confirm="Delete this news article?">Delete</button>
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

<div class="modal <?= $editing ? 'active' : ''; ?>" id="newsModal">
    <div class="modal-content">
        <div class="modal-head">
            <h3><?= $editing ? 'Edit News' : 'Create News'; ?></h3>
            <button type="button" class="btn btn-light btn-sm" data-close-modal>Close</button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="save_news">
                <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0); ?>">
                <div class="form-grid">
                    <label>Title
                        <input type="text" name="title" required value="<?= e($editing['title'] ?? ''); ?>">
                    </label>
                    <label>Author
                        <select name="author_id">
                            <option value="">Select author</option>
                            <?php foreach ($authors as $a): ?>
                                <option value="<?= (int) $a['id']; ?>" <?= ((int) ($editing['author_id'] ?? 0) === (int) $a['id']) ? 'selected' : ''; ?>><?= e($a['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Published Date
                        <input type="datetime-local" name="published_at" value="<?= !empty($editing['published_at']) ? e(date('Y-m-d\TH:i', strtotime($editing['published_at']))) : ''; ?>">
                    </label>
                    <label>Status
                        <select name="is_published">
                            <option value="0" <?= ((int) ($editing['is_published'] ?? 0) === 0) ? 'selected' : ''; ?>>Draft</option>
                            <option value="1" <?= ((int) ($editing['is_published'] ?? 0) === 1) ? 'selected' : ''; ?>>Published</option>
                        </select>
                    </label>
                    <label class="full">Description
                        <textarea name="description" rows="4" required><?= e($editing['content'] ?? ''); ?></textarea>
                    </label>
                    <label class="full">Thumbnail
                        <input type="file" name="thumbnail" accept=".jpg,.jpeg,.png,.webp">
                    </label>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-light" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save News</button>
            </div>
        </form>
    </div>
</div>

