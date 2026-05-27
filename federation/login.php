<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_BASE', '../');
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in() && (($_SESSION['panel'] ?? '') === 'federation')) {
    redirect_to('index.php');
}

$settings = load_system_settings();
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
            ];
            $_SESSION['panel'] = 'federation';

            db_execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', 'i', [$user['id']]);
            log_action('federation_login', 'auth', 'users', $user['id']);
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
  <title>Federation Login</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <div class="auth-wrap">
    <section class="auth-left">
      <h1>Football Federation Dashboard</h1>
      <p>Welcome to the central control room for approvals, governance, competition operations, and nationwide football administration.</p>
      <div class="auth-points">
        <div class="auth-point"><?= icon_svg('approval'); ?><span>Approve player rankings, statistics, and match results</span></div>
        <div class="auth-point"><?= icon_svg('users'); ?><span>Manage federation users, roles, and permissions</span></div>
        <div class="auth-point"><?= icon_svg('report'); ?><span>Generate official federation reports and logs</span></div>
      </div>
    </section>

    <section class="auth-right">
      <div class="auth-card">
        <h2>Federation Sign In</h2>
        <p class="muted auth-card-sub">Use your federation account to continue.</p>
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

