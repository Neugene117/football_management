<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_BASE', '../');
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in() && (($_SESSION['panel'] ?? '') === 'team')) {
    redirect_to('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $user = db_fetch_one('SELECT * FROM users WHERE email = ? LIMIT 1', 's', [$email]);
        if ($user && (int) $user['is_active'] === 1 && password_verify($password, $user['password_hash'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'profile_photo' => $user['profile_photo'],
                'user_type' => $user['user_type'],
                'entity_id' => $user['entity_id'],
            ];
            $_SESSION['panel'] = 'team';
            db_execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', 'i', [$user['id']]);
            log_action('team_login', 'auth', 'users', $user['id']);
            redirect_to('index.php');
        }

        $error = 'Invalid credentials or inactive account.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Team Login</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <div class="auth-wrap">
    <section class="auth-left">
      <h1>Team Operations Workspace</h1>
      <p>Access squad management, lineup submissions, match schedules, and performance tracking from one streamlined interface.</p>
      <div class="auth-points">
        <div class="auth-point"><?= icon_svg('users'); ?><span>Maintain player and squad visibility</span></div>
        <div class="auth-point"><?= icon_svg('approval'); ?><span>Submit lineups and monitor approvals</span></div>
        <div class="auth-point"><?= icon_svg('dashboard'); ?><span>Track upcoming matches and outcomes</span></div>
      </div>
    </section>

    <section class="auth-right">
      <div class="auth-card">
        <h2>Team Sign In</h2>
        <p class="muted auth-card-sub">Login to your team dashboard.</p>
        <?php if ($error): ?>
          <div class="alert alert-danger"><?= e($error); ?></div>
        <?php endif; ?>
        <form method="post" class="form-grid single-col">
          <label>Email
            <input type="email" name="email" required>
          </label>
          <label>Password
            <input type="password" name="password" required>
          </label>
          <button type="submit" class="btn btn-primary btn-full">Sign In</button>
          <a href="../index.php" class="btn btn-light btn-full">Back to Home</a>
        </form>
      </div>
    </section>
  </div>
  <script src="../assets/js/script.js"></script>
</body>
</html>

