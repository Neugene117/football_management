<?php
$myTeamId = (int) (current_user()['entity_id'] ?? 0);
$players = $myTeamId > 0
  ? db_fetch_all('SELECT p.*, t.name team_name FROM players p LEFT JOIN teams t ON t.id=p.team_id WHERE p.team_id = ? ORDER BY p.created_at DESC', 'i', [$myTeamId])
  : db_fetch_all('SELECT p.*, t.name team_name FROM players p LEFT JOIN teams t ON t.id=p.team_id ORDER BY p.created_at DESC LIMIT 80');
?>

<div class="card">
  <div class="card-head"><h3>Squad Management</h3></div>
  <div class="card-body">
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Player</th>
            <th>Team</th>
            <th>Position</th>
            <th>Jersey #</th>
            <th>Nationality</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($players)): ?>
            <tr><td colspan="6"><div class="empty-state">No players found.</div></td></tr>
          <?php else: ?>
            <?php foreach ($players as $p): ?>
              <tr>
                <td><?= e(trim($p['first_name'] . ' ' . $p['last_name'])); ?></td>
                <td><?= e($p['team_name'] ?: '-'); ?></td>
                <td><?= e(ucfirst($p['position'])); ?></td>
                <td><?= e($p['jersey_number'] ?: '-'); ?></td>
                <td><?= e($p['nationality'] ?: '-'); ?></td>
                <td><?= status_badge($p['status']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
