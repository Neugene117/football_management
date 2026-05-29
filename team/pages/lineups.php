<?php
$myTeamId = (int) (current_user()['entity_id'] ?? 0);
$matches = $myTeamId > 0
  ? db_fetch_all("SELECT m.id, m.match_date, ht.name home_team, at.name away_team FROM matches m LEFT JOIN teams ht ON ht.id=m.home_team_id LEFT JOIN teams at ON at.id=m.away_team_id WHERE m.home_team_id=? OR m.away_team_id=? ORDER BY m.match_date DESC LIMIT 120", 'ii', [$myTeamId, $myTeamId])
  : db_fetch_all("SELECT m.id, m.match_date, ht.name home_team, at.name away_team FROM matches m LEFT JOIN teams ht ON ht.id=m.home_team_id LEFT JOIN teams at ON at.id=m.away_team_id ORDER BY m.match_date DESC LIMIT 120");
$formations = db_fetch_all('SELECT id, display_name FROM formations WHERE is_active = 1 ORDER BY display_name ASC');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_csrf()) {
    set_flash('danger', 'Invalid token.');
    redirect_to('index.php?page=lineups');
  }

  if (!current_user_can('lineups.submit')) {
    set_flash('danger', 'You do not have permission to submit lineups.');
    redirect_to('index.php?page=lineups');
  }

  $matchId = (int) ($_POST['match_id'] ?? 0);
  $formationId = (int) ($_POST['formation_id'] ?? 0);
  $visibility = (int) ($_POST['is_public'] ?? 0);

  if ($matchId <= 0 || $formationId <= 0) {
    set_flash('danger', 'Select match and formation.');
    redirect_to('index.php?page=lineups');
  }

  $teamId = $myTeamId;
  if ($teamId <= 0) {
    $m = db_fetch_one('SELECT home_team_id FROM matches WHERE id = ?', 'i', [$matchId]);
    $teamId = (int) ($m['home_team_id'] ?? 0);
  }

  $ok = db_execute('INSERT INTO match_lineups (match_id, team_id, formation_id, status, submitted_at, submitted_by, is_public) VALUES (?, ?, ?, ?, NOW(), ?, ?)', 'iiisii', [$matchId, $teamId, $formationId, 'submitted', (int) current_user()['id'], $visibility]);

  if ($ok) {
    $lid = db_last_id();
    db_execute('INSERT INTO approvals (item_type, item_id, submitted_by, status) VALUES (?, ?, ?, ?)', 'siis', ['lineup', $lid, (int) current_user()['id'], 'pending']);
    log_action('lineup_submitted', 'lineups', 'match_lineups', $lid);
    set_flash('success', 'Lineup submitted for federation approval.');
  } else {
    set_flash('danger', 'Failed to submit lineup.');
  }

  redirect_to('index.php?page=lineups');
}

$lineups = $myTeamId > 0
  ? db_fetch_all("SELECT ml.*, m.match_date, f.display_name FROM match_lineups ml LEFT JOIN matches m ON m.id=ml.match_id LEFT JOIN formations f ON f.id=ml.formation_id WHERE ml.team_id=? ORDER BY ml.created_at DESC", 'i', [$myTeamId])
  : db_fetch_all("SELECT ml.*, m.match_date, f.display_name, t.name team_name FROM match_lineups ml LEFT JOIN matches m ON m.id=ml.match_id LEFT JOIN formations f ON f.id=ml.formation_id LEFT JOIN teams t ON t.id=ml.team_id ORDER BY ml.created_at DESC LIMIT 80");
?>

<div class="card">
  <div class="card-head">
    <h3>Lineup Submissions</h3>
    <?php if (current_user_can('lineups.submit')): ?>
      <button class="btn btn-primary btn-sm" type="button" data-open-modal="#lineupModal"><?= icon_svg('add'); ?> Submit Lineup</button>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Match Date</th><th>Formation</th><th>Status</th><th>Visibility</th></tr></thead>
        <tbody>
          <?php if (empty($lineups)): ?>
            <tr><td colspan="4"><div class="empty-state">No lineup submissions found.</div></td></tr>
          <?php else: ?>
            <?php foreach ($lineups as $l): ?>
              <tr>
                <td><?= e($l['match_date'] ?: '-'); ?></td>
                <td><?= e($l['display_name'] ?: '-'); ?></td>
                <td><?= status_badge($l['status']); ?></td>
                <td><?= status_badge((int) $l['is_public'] === 1 ? 'public' : 'private'); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal" id="lineupModal">
  <div class="modal-content">
    <div class="modal-head"><h3>Submit Match Lineup</h3><button type="button" class="btn btn-light btn-sm" data-close-modal>Close</button></div>
    <form method="post">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <div class="form-grid">
          <label>Match
            <select name="match_id" required>
              <option value="">Select match</option>
              <?php foreach ($matches as $m): ?>
                <option value="<?= (int) $m['id']; ?>"><?= e(($m['home_team'] ?: 'Home') . ' vs ' . ($m['away_team'] ?: 'Away') . ' - ' . $m['match_date']); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Formation
            <select name="formation_id" required>
              <option value="">Select formation</option>
              <?php foreach ($formations as $f): ?>
                <option value="<?= (int) $f['id']; ?>"><?= e($f['display_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Visibility
            <select name="is_public">
              <option value="0">Private</option>
              <option value="1">Public</option>
            </select>
          </label>
        </div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-light" data-close-modal>Cancel</button><button type="submit" class="btn btn-primary">Submit</button></div>
    </form>
  </div>
</div>
