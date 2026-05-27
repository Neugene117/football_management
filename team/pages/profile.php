<?php
$user = current_user();
$fullUser = db_fetch_one('SELECT * FROM users WHERE id = ?', 'i', [(int) $user['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_csrf()) {
    set_flash('danger', 'Invalid token.');
    redirect_to('index.php?page=profile');
  }

  $fullName = trim($_POST['full_name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  [$okUpload, $path] = upload_file('profile_photo', 'uploads/users');
  if (!$okUpload) {
    set_flash('danger', $path);
    redirect_to('index.php?page=profile');
  }

  $photo = $path ?: ($fullUser['profile_photo'] ?? null);

  if ($password !== '') {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    db_execute('UPDATE users SET full_name=?, phone=?, email=?, profile_photo=?, password_hash=?, updated_at=NOW() WHERE id=?', 'sssssi', [$fullName, $phone ?: null, $email, $photo, $hash, $fullUser['id']]);
  } else {
    db_execute('UPDATE users SET full_name=?, phone=?, email=?, profile_photo=?, updated_at=NOW() WHERE id=?', 'ssssi', [$fullName, $phone ?: null, $email, $photo, $fullUser['id']]);
  }

  $_SESSION['user']['full_name'] = $fullName;
  $_SESSION['user']['email'] = $email;
  $_SESSION['user']['profile_photo'] = $photo;
  set_flash('success', 'Profile updated.');
  redirect_to('index.php?page=profile');
}
?>

<div class="card">
  <div class="card-head"><h3>My Profile</h3></div>
  <div class="card-body">
    <form method="post" enctype="multipart/form-data" class="form-grid">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
      <label>Full Name
        <input type="text" name="full_name" required value="<?= e($fullUser['full_name'] ?? ''); ?>">
      </label>
      <label>Email
        <input type="email" name="email" required value="<?= e($fullUser['email'] ?? ''); ?>">
      </label>
      <label>Phone
        <input type="text" name="phone" value="<?= e($fullUser['phone'] ?? ''); ?>">
      </label>
      <label>New Password
        <input type="password" name="password" placeholder="Leave blank to keep current">
      </label>
      <label class="full">Profile Image
        <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp">
      </label>
      <div class="full inline-preview">
        <img class="img-lg-round" src="<?= e(app_url($fullUser['profile_photo'] ?: 'assets/images/federation-logo.svg')); ?>" alt="profile">
        <span class="muted">Last login: <?= e($fullUser['last_login_at'] ?: 'Never'); ?></span>
      </div>
      <div class="full"><button class="btn btn-primary" type="submit">Update Profile</button></div>
    </form>
  </div>
</div>
