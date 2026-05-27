<?php
$current = $page ?? 'dashboard';
$groups = [
    [
        'title' => 'Overview',
        'icon' => 'dashboard',
        'items' => [
            ['dashboard', 'Dashboard', 'dashboard'],
            ['matches', 'Match Schedule', 'season'],
            ['results', 'Match Results', 'approval'],
        ],
    ],
    [
        'title' => 'Team Operations',
        'icon' => 'team',
        'items' => [
            ['squad', 'Squad Management', 'users'],
            ['lineups', 'Lineup Submissions', 'approval'],
            ['news', 'Federation News', 'news'],
            ['profile', 'Profile', 'profile'],
        ],
    ],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="<?= e(app_url($settings['logo'])); ?>" alt="logo">
        <div class="brand-title">Team Dashboard</div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($groups as $group):
            $open = false;
            foreach ($group['items'] as $item) {
                if ($item[0] === $current) {
                    $open = true;
                    break;
                }
            }
        ?>
        <div class="nav-group <?= $open ? 'open' : ''; ?>">
            <button class="nav-group-title" data-nav-group type="button">
                <?= icon_svg($group['icon']); ?>
                <span><?= e($group['title']); ?></span>
                <svg class="chev" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6z"/></svg>
            </button>
            <div class="nav-sub">
                <?php foreach ($group['items'] as $item): ?>
                    <a class="nav-item <?= $current === $item[0] ? 'active' : ''; ?>" href="index.php?page=<?= e($item[0]); ?>">
                        <?= icon_svg($item[2]); ?>
                        <span><?= e($item[1]); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
      <a class="nav-item" href="logout.php"><?= icon_svg('logout'); ?><span>Logout</span></a>
    </div>
</aside>
