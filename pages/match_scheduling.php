<?php
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid request token.');
        redirect_to('index.php?page=match_scheduling');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_match') {
        if (!current_user_can('matches.schedule')) {
            set_flash('danger', 'You do not have permission to schedule matches.');
            redirect_to('index.php?page=match_scheduling');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $competitionId = (int) ($_POST['competition_id'] ?? 0);
        $homeTeamId = (int) ($_POST['home_team_id'] ?? 0);
        $awayTeamId = (int) ($_POST['away_team_id'] ?? 0);
        $stadiumId = (int) ($_POST['stadium_id'] ?? 0);
        $matchDate = trim($_POST['match_date'] ?? '');
        $matchTime = trim($_POST['match_time'] ?? '');
        $matchday = $_POST['matchday'] !== '' ? (int) $_POST['matchday'] : null;
        $round = trim($_POST['round'] ?? '');
        $status = trim($_POST['status'] ?? 'scheduled');

        if ($competitionId <= 0 || $homeTeamId <= 0 || $awayTeamId <= 0 || $stadiumId <= 0 || $matchDate === '') {
            set_flash('danger', 'Please fill in all required fields.');
            redirect_to('index.php?page=match_scheduling');
        }

        if ($homeTeamId === $awayTeamId) {
            set_flash('danger', 'Home team and Away team must be different.');
            redirect_to('index.php?page=match_scheduling');
        }

        // Fetch federation_id from the selected competition
        $comp = db_fetch_one('SELECT federation_id FROM competitions WHERE id = ?', 'i', [$competitionId]);
        $federationId = $comp ? (int) $comp['federation_id'] : get_default_federation_id();
        $scheduledBy = (int) (current_user()['id'] ?? null);

        if ($id > 0) {
            // Edit match schedule
            $existing = db_fetch_one('SELECT * FROM matches WHERE id = ?', 'i', [$id]);
            if (!$existing) {
                set_flash('danger', 'Match not found.');
                redirect_to('index.php?page=match_scheduling');
            }

            $done = db_execute(
                'UPDATE matches SET competition_id=?, home_team_id=?, away_team_id=?, stadium_id=?, match_date=?, match_time=?, matchday=?, round=?, status=? WHERE id=?',
                'iiiisssssi',
                [$competitionId, $homeTeamId, $awayTeamId, $stadiumId, $matchDate, $matchTime ?: null, $matchday, $round ?: null, $status, $id]
            );

            if ($done) {
                log_action('match_updated', 'matches', 'matches', $id, $existing, [
                    'competition_id' => $competitionId,
                    'home_team_id' => $homeTeamId,
                    'away_team_id' => $awayTeamId,
                    'stadium_id' => $stadiumId,
                    'match_date' => $matchDate,
                    'match_time' => $matchTime,
                    'status' => $status
                ]);
                set_flash('success', 'Match schedule updated successfully.');
            } else {
                set_flash('danger', 'Failed to update match schedule.');
            }
        } else {
            // Schedule new match
            $status = 'scheduled';
            $done = db_execute(
                'INSERT INTO matches (federation_id, competition_id, home_team_id, away_team_id, stadium_id, match_date, match_time, matchday, round, status, scheduled_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'iiiiisssssi',
                [$federationId, $competitionId, $homeTeamId, $awayTeamId, $stadiumId, $matchDate, $matchTime ?: null, $matchday, $round ?: null, $status, $scheduledBy ?: null]
            );

            if ($done) {
                $newId = db_last_id();
                log_action('match_created', 'matches', 'matches', $newId, null, [
                    'competition_id' => $competitionId,
                    'home_team_id' => $homeTeamId,
                    'away_team_id' => $awayTeamId,
                    'stadium_id' => $stadiumId,
                    'match_date' => $matchDate,
                    'match_time' => $matchTime
                ]);

                // Fetch team names to include in notifications
                $homeTeam = db_fetch_one('SELECT name FROM teams WHERE id = ?', 'i', [$homeTeamId]);
                $awayTeam = db_fetch_one('SELECT name FROM teams WHERE id = ?', 'i', [$awayTeamId]);
                $homeName = $homeTeam ? $homeTeam['name'] : 'Home Team';
                $awayName = $awayTeam ? $awayTeam['name'] : 'Away Team';

                // Send notifications to Home Team managers
                $homeUsers = db_fetch_all("SELECT id FROM users WHERE user_type = 'club' AND entity_id = ? AND is_active = 1", 'i', [$homeTeamId]);
                foreach ($homeUsers as $hu) {
                    create_notification(
                        (int) $hu['id'],
                        'match',
                        'New Match Scheduled vs ' . $awayName,
                        'Your match vs ' . $awayName . ' has been scheduled on ' . $matchDate . '. Click to prepare your lineup: index.php?page=lineup_prepare&match_id=' . $newId
                    );
                }

                // Send notifications to Away Team managers
                $awayUsers = db_fetch_all("SELECT id FROM users WHERE user_type = 'club' AND entity_id = ? AND is_active = 1", 'i', [$awayTeamId]);
                foreach ($awayUsers as $au) {
                    create_notification(
                        (int) $au['id'],
                        'match',
                        'New Match Scheduled vs ' . $homeName,
                        'Your match vs ' . $homeName . ' has been scheduled on ' . $matchDate . '. Click to prepare your lineup: index.php?page=lineup_prepare&match_id=' . $newId
                    );
                }

                set_flash('success', 'Match scheduled successfully and teams notified.');
            } else {
                set_flash('danger', 'Failed to schedule match. Check database integrity.');
            }
        }

        redirect_to('index.php?page=match_scheduling');
    }

    if ($action === 'delete_match') {
        if (!current_user_can('matches.delete') && !current_user_can('matches.schedule')) {
            set_flash('danger', 'You do not have permission to delete scheduled matches.');
            redirect_to('index.php?page=match_scheduling');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $old = db_fetch_one('SELECT * FROM matches WHERE id = ?', 'i', [$id]);
        if ($old) {
            $done = db_execute('DELETE FROM matches WHERE id = ?', 'i', [$id]);
            if ($done) {
                log_action('match_deleted', 'matches', 'matches', $id, $old, null);
                set_flash('success', 'Match schedule deleted successfully.');
            } else {
                set_flash('danger', 'Failed to delete match. It might have lineups or results.');
            }
        } else {
            set_flash('danger', 'Match not found.');
        }

        redirect_to('index.php?page=match_scheduling');
    }
}

if (!empty($_GET['edit'])) {
    $editing = db_fetch_one('SELECT * FROM matches WHERE id = ?', 'i', [(int) $_GET['edit']]);
}

$search = trim($_GET['search'] ?? '');
$where = '1=1';
$types = '';
$params = [];
if ($search !== '') {
    $where .= ' AND (ht.name LIKE ? OR at.name LIKE ? OR s.name LIKE ? OR c.name LIKE ?)';
    $types .= 'ssss';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$offset = 0;
$limitClause = paginate_clause($offset);
$matches = db_fetch_all(
    "SELECT m.*, c.name AS competition_name, ht.name AS home_team_name, at.name AS away_team_name, s.name AS stadium_name, s.city AS stadium_city
     FROM matches m
     INNER JOIN competitions c ON c.id = m.competition_id
     INNER JOIN teams ht ON ht.id = m.home_team_id
     INNER JOIN teams at ON at.id = m.away_team_id
     LEFT JOIN stadiums s ON s.id = m.stadium_id
     WHERE {$where}
     ORDER BY m.match_date DESC, m.match_time DESC {$limitClause}",
    $types,
    $params
);

$totalMatchesRows = db_fetch_one(
    "SELECT COUNT(*) total FROM matches m
     INNER JOIN competitions c ON c.id = m.competition_id
     INNER JOIN teams ht ON ht.id = m.home_team_id
     INNER JOIN teams at ON at.id = m.away_team_id
     LEFT JOIN stadiums s ON s.id = m.stadium_id
     WHERE {$where}",
    $types,
    $params
);
$totalItems = (int) ($totalMatchesRows['total'] ?? 0);

// Dropdown lists
$competitions = db_fetch_all('SELECT id, name FROM competitions WHERE is_active = 1 ORDER BY name ASC');
$teams = db_fetch_all('SELECT id, name FROM teams WHERE is_active = 1 ORDER BY name ASC');
$stadiums = db_fetch_all('SELECT id, name, city FROM stadiums ORDER BY name ASC');
?>

<div class="card">
    <div class="card-head">
        <h3>Match Schedules</h3>
        <?php if (current_user_can('matches.schedule')): ?>
            <button type="button" class="btn btn-primary" data-open-modal="#matchModal">
                <?= icon_svg('add'); ?> Schedule Match
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="toolbar">
            <div class="left">
                <form method="get" action="index.php" class="inline-form">
                    <input type="hidden" name="page" value="match_scheduling">
                    <input type="text" name="search" placeholder="Search fixtures, teams, stadiums..." value="<?= e($search); ?>">
                    <button class="btn btn-light" type="submit">Search</button>
                </form>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table" id="matchesTable">
                <thead>
                    <tr>
                        <th>Competition</th>
                        <th>Fixture (Home vs Away)</th>
                        <th>Stadium / Venue</th>
                        <th>Date & Time</th>
                        <th>Matchday</th>
                        <th>Round</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($matches)): ?>
                        <tr><td colspan="8"><div class="empty-state">No scheduled matches found.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($matches as $match): ?>
                            <tr>
                                <td>
                                    <span class="text-semibold"><?= e($match['competition_name']); ?></span>
                                </td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <span class="text-semibold"><?= e($match['home_team_name']); ?></span>
                                        <span class="muted text-sm">vs</span>
                                        <span class="text-semibold"><?= e($match['away_team_name']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div><?= e($match['stadium_name'] ?: '-'); ?></div>
                                    <?php if ($match['stadium_city']): ?>
                                        <small class="muted"><i class="fa-solid fa-location-dot" style="font-size:10px; margin-right:3px;"></i><?= e($match['stadium_city']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-semibold"><?= date('d M Y', strtotime($match['match_date'])); ?></div>
                                    <small class="muted"><i class="fa-regular fa-clock" style="font-size:10px; margin-right:3px;"></i><?= $match['match_time'] ? date('H:i', strtotime($match['match_time'])) : '--:--'; ?></small>
                                </td>
                                <td class="text-center"><?= $match['matchday'] ?: '-'; ?></td>
                                <td><?= e($match['round'] ?: '-'); ?></td>
                                <td><?= status_badge($match['status']); ?></td>
                                <td>
                                    <div class="action-group">
                                        <?php if (current_user_can('matches.schedule')): ?>
                                            <a class="btn btn-light btn-sm" href="index.php?page=match_scheduling&edit=<?= (int) $match['id']; ?>">Edit</a>
                                        <?php endif; ?>
                                        <?php if (current_user_can('matches.delete') || current_user_can('matches.schedule')): ?>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="delete_match">
                                                <input type="hidden" name="id" value="<?= (int) $match['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Are you sure you want to cancel and delete this match schedule?">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php render_pagination($totalItems); ?>
    </div>
</div>

<div class="modal <?= $editing ? 'active' : ''; ?>" id="matchModal">
    <div class="modal-content">
        <div class="modal-head">
            <h3><?= $editing ? 'Edit Scheduled Match' : 'Schedule Match'; ?></h3>
            <button type="button" class="btn btn-light btn-sm" data-close-modal>Close</button>
        </div>
        <form method="post" id="matchForm">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="save_match">
                <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0); ?>">
                
                <div class="form-grid">
                    <label class="full">Competition <span class="text-danger">*</span>
                        <select name="competition_id" required>
                            <option value="">Select competition</option>
                            <?php foreach ($competitions as $c): ?>
                                <option value="<?= (int) $c['id']; ?>" <?= ((int) ($editing['competition_id'] ?? 0) === (int) $c['id']) ? 'selected' : ''; ?>><?= e($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>Home Team <span class="text-danger">*</span>
                        <select name="home_team_id" id="home_team_id" required>
                            <option value="">Select home team</option>
                            <?php foreach ($teams as $t): ?>
                                <option value="<?= (int) $t['id']; ?>" <?= ((int) ($editing['home_team_id'] ?? 0) === (int) $t['id']) ? 'selected' : ''; ?>><?= e($t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>Away Team <span class="text-danger">*</span>
                        <select name="away_team_id" id="away_team_id" required>
                            <option value="">Select away team</option>
                            <?php foreach ($teams as $t): ?>
                                <option value="<?= (int) $t['id']; ?>" <?= ((int) ($editing['away_team_id'] ?? 0) === (int) $t['id']) ? 'selected' : ''; ?>><?= e($t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="full">Stadium / Venue <span class="text-danger">*</span>
                        <select name="stadium_id" required>
                            <option value="">Select stadium</option>
                            <?php foreach ($stadiums as $s): ?>
                                <option value="<?= (int) $s['id']; ?>" <?= ((int) ($editing['stadium_id'] ?? 0) === (int) $s['id']) ? 'selected' : ''; ?>><?= e($s['name']); ?> (<?= e($s['city']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>Match Date <span class="text-danger">*</span>
                        <input type="date" name="match_date" required value="<?= e($editing['match_date'] ?? ''); ?>">
                    </label>

                    <label>Match Time
                        <input type="time" name="match_time" value="<?= e($editing['match_time'] ?? ''); ?>">
                    </label>

                    <label>Matchday
                        <input type="number" name="matchday" min="1" value="<?= e($editing['matchday'] ?? ''); ?>" placeholder="e.g. 1">
                    </label>

                    <label>Round
                        <input type="text" name="round" value="<?= e($editing['round'] ?? ''); ?>" placeholder="e.g. Regular Season, Quarter-final">
                    </label>

                    <?php if ($editing): ?>
                    <label class="full">Status
                        <select name="status">
                            <?php $currStatus = $editing['status'] ?? 'scheduled'; ?>
                            <option value="scheduled" <?= ($currStatus === 'scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="lineup_pending" <?= ($currStatus === 'lineup_pending') ? 'selected' : ''; ?>>Lineup Pending</option>
                            <option value="lineup_approved" <?= ($currStatus === 'lineup_approved') ? 'selected' : ''; ?>>Lineup Approved</option>
                            <option value="in_progress" <?= ($currStatus === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?= ($currStatus === 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="postponed" <?= ($currStatus === 'postponed') ? 'selected' : ''; ?>>Postponed</option>
                            <option value="cancelled" <?= ($currStatus === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </label>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-light" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Schedule</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('matchForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const homeSel = document.getElementById('home_team_id');
            const awaySel = document.getElementById('away_team_id');
            if (homeSel && awaySel && homeSel.value && awaySel.value && homeSel.value === awaySel.value) {
                e.preventDefault();
                alert('Validation Error: Home team and Away team must be different.');
            }
        });
    }
});
</script>
