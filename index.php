<?php
session_start();
define('APP_BASE', '');
require_once __DIR__ . '/includes/functions.php';
$settings = load_system_settings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($settings['system_name']); ?> - Home</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="landing">
  <header class="landing-top">
    <div class="landing-brand">
      <img src="<?= e(app_url($settings['logo'])); ?>" alt="logo">
      <span><?= e($settings['system_name']); ?></span>
    </div>
    <a href="federation/login.php" class="btn btn-primary btn-sm">Login</a>
  </header>

  <main class="landing-main">
    <div class="landing-grid">
      <section class="hero-card">
        <h1>Modern Football Administration Platform</h1>
        <p>Manage federation operations, approvals, team activities, and reporting from one clean and professional dashboard.</p>
        <ul class="hero-list">
          <li><?= icon_svg('approval'); ?> Centralized approval workflows</li>
          <li><?= icon_svg('report'); ?> Real-time performance and reports</li>
          <li><?= icon_svg('users'); ?> Role-based access for federation and teams</li>
        </ul>
        <a href="federation/login.php" class="btn btn-primary">Enter Federation Panel</a>
      </section>

      <aside class="hero-side">
        <h3 class="landing-side-title">Choose Your Workspace</h3>
        <p class="landing-side-text">Both dashboards share one visual system, but each has dedicated navigation and workflows.</p>
        <div class="panel-links">
          <a class="panel-link" href="federation/login.php"><span>Federation Dashboard</span><span>Open</span></a>
          <a class="panel-link" href="team/login.php"><span>Team Dashboard</span><span>Open</span></a>
        </div>
      </aside>
    </div>
  </main>
</body>
</html>

