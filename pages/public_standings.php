<?php
$allStandings = db_fetch_all("
    SELECT ts.*, t.name AS team_name, t.logo AS team_logo
    FROM team_standings ts
    INNER JOIN teams t ON t.id = ts.team_id
    ORDER BY ts.points DESC, ts.goal_difference DESC, ts.goals_for DESC
");
?>
<section style="background: var(--off); min-height: 600px; padding: 24px 20px;">
  <div style="max-width: 1000px; margin: 0 auto;">
    <div class="std-wrap" style="box-shadow: 0 8px 30px rgba(0,0,0,0.15);">
      <div class="std-hd" style="padding: 16px 20px;"><span class="std-hd-t">Full League <span>Standings Table</span></span><span style="font-size:11px;color:rgba(255,255,255,0.4)">Premier League Season 2026/27</span></div>
      <table class="std-tbl" style="width: 100%; border-collapse: collapse;">
        <thead>
          <tr>
            <th style="padding: 12px 14px;">Rank</th>
            <th style="padding: 12px 14px; text-align: left;">Team</th>
            <th style="padding: 12px 14px;">Played</th>
            <th style="padding: 12px 14px;">Won</th>
            <th style="padding: 12px 14px;">Drawn</th>
            <th style="padding: 12px 14px;">Lost</th>
            <th style="padding: 12px 14px;">GF</th>
            <th style="padding: 12px 14px;">GA</th>
            <th style="padding: 12px 14px;">GD</th>
            <th style="padding: 12px 14px;">Form</th>
            <th style="padding: 12px 14px;">Points</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($allStandings)): ?>
            <tr><td colspan="11" style="color: rgba(255,255,255,0.4); padding: 40px; text-align: center;">No standings recorded.</td></tr>
          <?php else: ?>
            <?php foreach ($allStandings as $row): 
              $rank = (int) $row['position'];
              $rClass = $rank === 1 ? 'r1' : ($rank === 2 ? 'r2' : ($rank === 3 ? 'r3' : ''));
            ?>
              <tr class="<?= $rClass; ?>" style="border-bottom: 1px solid rgba(255,255,255,0.04);">
                <td style="padding: 12px 14px; font-weight: 800; font-size: 12px;"><?= $rank; ?></td>
                <td style="padding: 12px 14px; text-align: left;">
                  <div class="std-team">
                    <div class="std-lg" style="width: 24px; height: 24px; border: 1.5px solid rgba(255,255,255,0.2);"><img src="<?= e($row['team_logo'] ?: 'https://images.unsplash.com/photo-1551958219-acbc595d9e15?w=40&q=80'); ?>" alt=""/></div>
                    <span class="std-nm" style="font-size: 12.5px; font-weight: 700; color: #fff;"><?= e($row['team_name']); ?></span>
                  </div>
                </td>
                <td style="padding: 12px 14px; font-weight: 600;"><?= $row['matches_played']; ?></td>
                <td style="padding: 12px 14px; color: rgba(255,255,255,0.8);"><?= $row['wins']; ?></td>
                <td style="padding: 12px 14px; color: rgba(255,255,255,0.8);"><?= $row['draws']; ?></td>
                <td style="padding: 12px 14px; color: rgba(255,255,255,0.8);"><?= $row['losses']; ?></td>
                <td style="padding: 12px 14px; color: rgba(255,255,255,0.6);"><?= $row['goals_for']; ?></td>
                <td style="padding: 12px 14px; color: rgba(255,255,255,0.6);"><?= $row['goals_against']; ?></td>
                <td style="padding: 12px 14px; font-weight: 700; color: <?= $row['goal_difference'] >= 0 ? '#4ade80' : '#f87171'; ?>;"><?= $row['goal_difference'] >= 0 ? '+' . $row['goal_difference'] : $row['goal_difference']; ?></td>
                <td style="padding: 12px 14px; white-space: nowrap;">
                  <span class="fw">W</span><span class="fw">W</span><span class="fd">D</span><span class="fw">W</span><span class="fl">L</span>
                </td>
                <td style="padding: 12px 14px;" class="pts"><?= $row['points']; ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
