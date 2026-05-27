<?php
$myTeamId = (int) (current_user()['entity_id'] ?? 0);
$rows = $myTeamId > 0
  ? db_fetch_all("SELECT mr.*, m.match_date, ht.name home_team, at.name away_team FROM match_results mr LEFT JOIN matches m ON m.id=mr.match_id LEFT JOIN teams ht ON ht.id=m.home_team_id LEFT JOIN teams at ON at.id=m.away_team_id WHERE m.home_team_id=? OR m.away_team_id=? ORDER BY mr.created_at DESC", 'ii', [$myTeamId, $myTeamId])
  : db_fetch_all("SELECT mr.*, m.match_date, ht.name home_team, at.name away_team FROM match_results mr LEFT JOIN matches m ON m.id=mr.match_id LEFT JOIN teams ht ON ht.id=m.home_team_id LEFT JOIN teams at ON at.id=m.away_team_id ORDER BY mr.created_at DESC LIMIT 100");
?>

<div class="card">
  <div class="card-head"><h3>Match Results</h3></div>
  <div class="card-body">
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Match</th><th>Date</th><th>Score</th><th>Possession</th><th>Status</th></tr></thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="5"><div class="empty-state">No results available.</div></td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= e(($r['home_team'] ?: 'Home') . ' vs ' . ($r['away_team'] ?: 'Away')); ?></td>
                <td><?= e($r['match_date'] ?: '-'); ?></td>
                <td><?= (int) $r['home_score']; ?> - <?= (int) $r['away_score']; ?></td>
                <td><?= e(($r['home_possession_pct'] ?? 0) . '% / ' . ($r['away_possession_pct'] ?? 0) . '%'); ?></td>
                <td><?= status_badge($r['status']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
