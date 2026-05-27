<?php
$myTeamId = (int) (current_user()['entity_id'] ?? 0);
$teamFilter = $myTeamId > 0 ? ' WHERE id = ' . $myTeamId : '';

$totalPlayers = $myTeamId > 0
    ? db_fetch_one('SELECT COUNT(*) total FROM players WHERE team_id = ?', 'i', [$myTeamId])['total'] ?? 0
    : db_table_count('players');

$totalMatches = $myTeamId > 0
    ? db_fetch_one('SELECT COUNT(*) total FROM matches WHERE home_team_id = ? OR away_team_id = ?', 'ii', [$myTeamId, $myTeamId])['total'] ?? 0
    : db_table_count('matches');

$pendingLineups = $myTeamId > 0
    ? db_fetch_one("SELECT COUNT(*) total FROM match_lineups WHERE team_id = ? AND status IN ('draft','submitted')", 'i', [$myTeamId])['total'] ?? 0
    : db_fetch_one("SELECT COUNT(*) total FROM match_lineups WHERE status IN ('draft','submitted')")['total'] ?? 0;

$upcoming = $myTeamId > 0
    ? db_fetch_all("SELECT m.match_date, ht.name home_team, at.name away_team, m.status FROM matches m LEFT JOIN teams ht ON ht.id=m.home_team_id LEFT JOIN teams at ON at.id=m.away_team_id WHERE (m.home_team_id=? OR m.away_team_id=?) ORDER BY m.match_date ASC LIMIT 6", 'ii', [$myTeamId, $myTeamId])
    : db_fetch_all("SELECT m.match_date, ht.name home_team, at.name away_team, m.status FROM matches m LEFT JOIN teams ht ON ht.id=m.home_team_id LEFT JOIN teams at ON at.id=m.away_team_id ORDER BY m.match_date ASC LIMIT 6");

$latestNews = db_fetch_all('SELECT title, published_at FROM news WHERE is_published = 1 ORDER BY published_at DESC LIMIT 6');
?>

<div class="grid stats-grid">
  <div class="card stat-card"><div class="stat-icon"><?= icon_svg('users'); ?></div><div><div class="stat-value" data-counter="<?= (int) $totalPlayers; ?>">0</div><div class="stat-label">Total Players</div></div></div>
  <div class="card stat-card"><div class="stat-icon"><?= icon_svg('season'); ?></div><div><div class="stat-value" data-counter="<?= (int) $totalMatches; ?>">0</div><div class="stat-label">Scheduled Matches</div></div></div>
  <div class="card stat-card"><div class="stat-icon"><?= icon_svg('approval'); ?></div><div><div class="stat-value" data-counter="<?= (int) $pendingLineups; ?>">0</div><div class="stat-label">Pending Lineups</div></div></div>
  <div class="card stat-card"><div class="stat-icon"><?= icon_svg('news'); ?></div><div><div class="stat-value" data-counter="<?= (int) count($latestNews); ?>">0</div><div class="stat-label">Latest News</div></div></div>
</div>

<div class="two-col mt-12">
  <div class="card">
    <div class="card-head"><h3>Upcoming Matches</h3></div>
    <div class="card-body panel-list">
      <?php if (empty($upcoming)): ?>
        <div class="empty-state">No upcoming matches.</div>
      <?php else: ?>
        <?php foreach ($upcoming as $m): ?>
          <div class="list-item">
            <div>
              <div class="list-title"><?= e(($m['home_team'] ?: 'Home') . ' vs ' . ($m['away_team'] ?: 'Away')); ?></div>
              <div class="small muted"><?= e($m['match_date']); ?></div>
            </div>
            <?= status_badge($m['status']); ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>Federation News Feed</h3></div>
    <div class="card-body panel-list">
      <?php if (empty($latestNews)): ?>
        <div class="empty-state">No news published yet.</div>
      <?php else: ?>
        <?php foreach ($latestNews as $n): ?>
          <div class="list-item">
            <div>
              <div class="list-title"><?= e($n['title']); ?></div>
              <div class="small muted"><?= e($n['published_at'] ?: '-'); ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
