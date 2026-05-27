<?php
$user = current_user();
$fullUser = db_fetch_one('SELECT * FROM users WHERE id = ?', 'i', [(int) ($user['id'] ?? 0)]);

if (!$fullUser) {
    set_flash('danger', 'User profile could not be loaded.');
    redirect_to('index.php?page=dashboard');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid request token.');
        redirect_to('index.php?page=profile');
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($fullName === '' || $email === '') {
        set_flash('danger', 'Full name and email are required.');
        redirect_to('index.php?page=profile');
    }

    [$uploadOk, $imgPath] = upload_file('profile_photo', 'uploads/users');
    if (!$uploadOk) {
        set_flash('danger', $imgPath);
        redirect_to('index.php?page=profile');
    }

    $newPhoto = $imgPath ?: $fullUser['profile_photo'];

    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        db_execute('UPDATE users SET full_name=?, phone=?, email=?, profile_photo=?, password_hash=?, updated_at=NOW() WHERE id=?', 'sssssi', [$fullName, $phone ?: null, $email, $newPhoto, $hash, $fullUser['id']]);
    } else {
        db_execute('UPDATE users SET full_name=?, phone=?, email=?, profile_photo=?, updated_at=NOW() WHERE id=?', 'ssssi', [$fullName, $phone ?: null, $email, $newPhoto, $fullUser['id']]);
    }

    $_SESSION['user']['full_name'] = $fullName;
    $_SESSION['user']['email'] = $email;
    $_SESSION['user']['profile_photo'] = $newPhoto;

    log_action('profile_updated', 'profile', 'users', $fullUser['id']);
    set_flash('success', 'Profile updated successfully.');
    redirect_to('index.php?page=profile');
}
?>

<div class="card">
    <div class="card-head"><h3>My Profile</h3></div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <label>Full Name
                <input type="text" name="full_name" required value="<?= e($fullUser['full_name']); ?>">
            </label>
            <label>Email
                <input type="email" name="email" required value="<?= e($fullUser['email']); ?>">
            </label>
            <label>Phone
                <input type="text" name="phone" value="<?= e($fullUser['phone'] ?? ''); ?>">
            </label>
            <label>User Type
                <input type="text" value="<?= e(ucfirst($fullUser['user_type'])); ?>" disabled>
            </label>
            <label>Password (leave blank to keep)
                <input type="password" name="password">
            </label>
            <label>Profile Image
                <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp">
            </label>
            <div class="full inline-preview">
                <img src="<?= e(app_url($fullUser['profile_photo'] ?: 'assets/images/federation-logo.svg')); ?>" alt="profile" class="img-lg-round">
                <span class="muted">Last login: <?= e($fullUser['last_login_at'] ?: 'Never'); ?></span>
            </div>
            <div class="full">
                <button class="btn btn-primary" type="submit">Update Profile</button>
            </div>
        </form>
    </div>
</div>

