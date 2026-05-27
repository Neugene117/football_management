<?php
$myTeamId = (int) (current_user()['entity_id'] ?? 0);
$rows = $myTeamId > 0
  ? db_fetch_all("SELECT m.*, ht.name home_team, at.name away_team, s.name stadium_name FROM matches m LEFT JOIN teams ht ON ht.id=m.home_team_id LEFT JOIN teams at ON at.id=m.away_team_id LEFT JOIN stadiums s ON s.id=m.stadium_id WHERE m.home_team_id=? OR m.away_team_id=? ORDER BY m.match_date DESC", 'ii', [$myTeamId, $myTeamId])
  : db_fetch_all("SELECT m.*, ht.name home_team, at.name away_team, s.name stadium_name FROM matches m LEFT JOIN teams ht ON ht.id=m.home_team_id LEFT JOIN teams at ON at.id=m.away_team_id LEFT JOIN stadiums s ON s.id=m.stadium_id ORDER BY m.match_date DESC LIMIT 100");
?>

<div class="card">
  <div class="card-head"><h3>Match Schedule</h3></div>
  <div class="card-body">
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Date</th><th>Fixture</th><th>Stadium</th><th>Round</th><th>Status</th></tr></thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="5"><div class="empty-state">No matches found.</div></td></tr>
          <?php else: ?>
            <?php foreach ($rows as $m): ?>
              <tr>
                <td><?= e($m['match_date']); ?> <?= e($m['match_time'] ?: ''); ?></td>
                <td><?= e(($m['home_team'] ?: 'Home') . ' vs ' . ($m['away_team'] ?: 'Away')); ?></td>
                <td><?= e($m['stadium_name'] ?: '-'); ?></td>
                <td><?= e($m['round'] ?: '-'); ?></td>
                <td><?= status_badge($m['status']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
