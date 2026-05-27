<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_BASE', '');
require_once __DIR__ . '/includes/functions.php';

function resolve_dashboard_by_user_type($userType)
{
    return $userType === 'club'
        ? ['panel' => 'team', 'path' => 'team/index.php']
        : ['panel' => 'federation', 'path' => 'federation/index.php'];
}

if (is_logged_in()) {
    $target = resolve_dashboard_by_user_type($_SESSION['user']['user_type'] ?? 'federation');
    $_SESSION['panel'] = $target['panel'];
    redirect_to($target['path']);
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
                'entity_id' => $user['entity_id'],
            ];

            $target = resolve_dashboard_by_user_type($user['user_type']);
            $_SESSION['panel'] = $target['panel'];

            db_execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', 'i', [$user['id']]);
            log_action('login', 'auth', 'users', $user['id']);
            redirect_to($target['path']);
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
  <title>Sign In - <?= e($settings['system_name']); ?></title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
  <main class="signin-shell">
    <section class="signin-left">
      <h1>Sign In</h1>

      <div class="signin-social" aria-hidden="true">
        <span>f</span>
        <span>G</span>
        <span>in</span>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error); ?></div>
      <?php endif; ?>

      <form method="post" class="signin-form">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <a class="signin-help" href="mailto:<?= e($settings['system_email']); ?>">Forgot your password?</a>
        <button class="btn btn-primary btn-full signin-submit" type="submit">Sign In</button>
      </form>
    </section>

    <aside class="signin-right">
      <h2>Forgot Your Password?</h2>
      <p>Enter your email address to reset your password.</p>
      <a class="signin-reset" href="mailto:<?= e($settings['system_email']); ?>">Reset Password</a>
    </aside>
  </main>
</body>
</html>