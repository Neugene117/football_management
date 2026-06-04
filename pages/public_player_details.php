<?php
$playerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($playerId <= 0) {
    echo "<div style='color:var(--text3); text-align:center; padding:100px; font-family:sans-serif;'>Invalid player ID. <a href='players.php' style='color:var(--org); font-weight:700; text-decoration:none;'>&larr; Back to Directory</a></div>";
    return;
}

// Fetch player details
$player = db_fetch_one("
    SELECT p.*, t.name AS team_name, t.logo AS team_logo, t.city AS team_city
    FROM players p
    INNER JOIN teams t ON t.id = p.team_id
    WHERE p.id = ?
", 'i', [$playerId]);

if (!$player) {
    echo "<div style='color:var(--text3); text-align:center; padding:100px; font-family:sans-serif;'>Player not found. <a href='players.php' style='color:var(--org); font-weight:700; text-decoration:none;'>&larr; Back to Directory</a></div>";
    return;
}

// Fetch statistics per competition
$statsList = db_fetch_all("
    SELECT ps.*, c.name AS competition_name
    FROM player_statistics ps
    LEFT JOIN competitions c ON c.id = ps.competition_id
    WHERE ps.player_id = ? AND ps.statuss = 'approved'
", 'i', [$playerId]);

// Fetch cumulative statistics
$cumulative = db_fetch_one("
    SELECT 
        SUM(matches_played) AS matches_played,
        SUM(matches_started) AS matches_started,
        SUM(minutes_played) AS minutes_played,
        SUM(goals) AS goals,
        SUM(assists) AS assists,
        SUM(yellow_cards) AS yellow_cards,
        SUM(red_cards) AS red_cards,
        SUM(clean_sheets) AS clean_sheets,
        SUM(saves) AS saves,
        AVG(average_rating) AS average_rating
    FROM player_statistics
    WHERE player_id = ? AND statuss = 'approved'
", 'i', [$playerId]);

// Fetch career/played teams
$teamsPlayed = db_fetch_all("
    SELECT DISTINCT t.id, t.name, t.logo, t.city
    FROM (
        SELECT team_id FROM players WHERE id = ?
        UNION
        SELECT ml.team_id 
        FROM lineup_players lp 
        INNER JOIN match_lineups ml ON ml.id = lp.lineup_id 
        WHERE lp.player_id = ?
        UNION
        SELECT team_id FROM match_events WHERE player_id = ?
    ) temp
    INNER JOIN teams t ON t.id = temp.team_id
", 'iii', [$playerId, $playerId, $playerId]);

// Fetch ratings & highlights
$ratingsList = db_fetch_all("
    SELECT pr.*, m.match_date, m.round, ht.name AS home_name, at.name AS away_name, ht.logo AS home_logo, at.logo AS away_logo, mf.file_path AS video_path, mf.original_name AS video_name
    FROM player_ratings pr
    INNER JOIN matches m ON m.id = pr.match_id
    INNER JOIN teams ht ON ht.id = m.home_team_id
    INNER JOIN teams at ON at.id = m.away_team_id
    LEFT JOIN media_files mf ON mf.id = pr.highlight_video_id
    WHERE pr.player_id = ? AND pr.ststuss = 'approved'
    ORDER BY m.match_date DESC
", 'i', [$playerId]);

// Styling & Info Setup
$posColors = [
    'goalkeeper' => '#F97316',
    'defender' => '#1E3A8A',
    'midfielder' => '#15803d',
    'forward' => '#dc2626'
];
$posColor = $posColors[strtolower($player['position'])] ?? '#15803d';

$posLabels = [
    'goalkeeper' => 'Goalkeeper',
    'defender' => 'Defender',
    'midfielder' => 'Midfielder',
    'forward' => 'Forward'
];
$posLabel = $posLabels[strtolower($player['position'])] ?? $player['position'];

$ratingVal = $cumulative['average_rating'] ? round((float)$cumulative['average_rating'], 1) : 75;

// Age Calculation
$birthdate = $player['date_of_birth'];
$ageStr = 'N/A';
if ($birthdate) {
    $diff = date_diff(date_create($birthdate), date_create('today'));
    $ageStr = $diff->y . ' yrs';
}

function pd_format_date($dateStr) {
    if (!$dateStr) return 'N/A';
    return date('d M Y', strtotime($dateStr));
}
?>

<section class="pd-section">
  <div class="pd-container">
    
    <!-- Header Navigation -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
      <a href="players.php" class="pd-back-link">&larr; Back to Directory</a>
      <a href="teams.php?id=<?= (int) $player['team_id']; ?>" class="pd-back-link" style="color:var(--org-l); font-weight:500;">View <?= e($player['team_name']); ?> Squad &rarr;</a>
    </div>

    <!-- Ultimate Player Header Banner -->
    <div class="pd-header-banner">
      <div class="pd-banner-ambient" style="background: radial-gradient(circle at 30% 50%, <?= $posColor; ?>66, transparent 60%);"></div>
      
      <div class="pd-header-content">
        <!-- Player Photo -->
        <div class="pd-header-photo">
          <img src="<?= e($player['photo_pl'] ?: 'https://images.unsplash.com/photo-1627483297886-49710ae1fc22?w=300&q=80'); ?>" alt="Player Photo">
          <div class="pd-photo-jersey" style="background:<?= $posColor; ?>;">#<?= (int) $player['jersey_number']; ?></div>
        </div>

        <!-- Name & Core Info -->
        <div class="pd-header-info">
          <span class="pd-pos-label" style="background: <?= $posColor; ?>;"><?= $posLabel; ?></span>
          <h1 class="pd-player-name"><?= e($player['first_name'] . ' ' . $player['last_name']); ?></h1>
          
          <div class="pd-club-row">
            <div class="pd-club-logo">
              <img src="<?= e($player['team_logo'] ?: 'https://images.unsplash.com/photo-1551958219-acbc595d9e15?w=50&q=80'); ?>" alt="Club Logo">
            </div>
            <span><?= e($player['team_name']); ?> (<?= e($player['team_city']); ?>)</span>
          </div>
          
          <div class="pd-national-badge">
            <span>🌐 <?= e($player['nationality'] ?: 'Rwanda'); ?></span>
          </div>
        </div>

        <!-- Fifa Rating Card Display -->
        <div class="pd-rating-card" style="border-color: <?= $posColor; ?>;">
          <div class="pd-rating-num"><?= $ratingVal; ?></div>
          <div class="pd-rating-text" style="color:var(--org);">OVR RATING</div>
          <div class="pd-rating-bar-wrap">
            <div class="pd-rating-bar-fill" style="width: <?= $ratingVal; ?>%; background: <?= $posColor; ?>;"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content Layout -->
    <div class="pd-grid-layout">
      <!-- Left Column: Personal info & History -->
      <div class="pd-column-left">
        <!-- Biodata Card -->
        <div class="pd-card-dark">
          <h3 class="pd-card-title">Personal Profile</h3>
          <div class="pd-info-list">
            <div class="pd-info-row">
              <span>Date of Birth</span>
              <strong><?= pd_format_date($player['date_of_birth']); ?> (<?= $ageStr; ?>)</strong>
            </div>
            <div class="pd-info-row">
              <span>Nationality</span>
              <strong><?= e($player['nationality'] ?: 'Rwanda'); ?></strong>
            </div>
            <div class="pd-info-row">
              <span>Height</span>
              <strong><?= $player['height_cm'] ? (int) $player['height_cm'] . ' cm' : 'N/A'; ?></strong>
            </div>
            <div class="pd-info-row">
              <span>Weight</span>
              <strong><?= $player['weight_kg'] ? (int) $player['weight_kg'] . ' kg' : 'N/A'; ?></strong>
            </div>
            <div class="pd-info-row">
              <span>Preferred Foot</span>
              <strong style="text-transform: capitalize;"><?= e($player['preferred_foot']); ?></strong>
            </div>
            <div class="pd-info-row">
              <span>Status</span>
              <strong style="text-transform: capitalize; color:<?= $player['status'] === 'active' ? '#4ade80' : '#f87171' ?>;"><?= e($player['status']); ?></strong>
            </div>
            <div class="pd-info-row">
              <span>Market Value</span>
              <strong style="color:var(--org);"><?= $player['market_value'] ? '€' . number_format((float) $player['market_value']) : 'N/A'; ?></strong>
            </div>
            <div class="pd-info-row" style="border:none; padding-bottom:0; margin-bottom:0;">
              <span>Contract Period</span>
              <strong style="font-size:11px;"><?= pd_format_date($player['contract_start']); ?> &nbsp;to&nbsp; <?= pd_format_date($player['contract_end']); ?></strong>
            </div>
          </div>
        </div>

        <!-- Biography -->
        <div class="pd-card-dark">
          <h3 class="pd-card-title">Biography</h3>
          <p class="pd-bio-text">
            <?= nl2br(e($player['biography'] ?: 'No professional biography records submitted for this player yet.')); ?>
          </p>
        </div>

        <!-- Career representation history -->
        <div class="pd-card-dark">
          <h3 class="pd-card-title">Club Career</h3>
          <div class="pd-career-list">
            <?php foreach ($teamsPlayed as $t): ?>
              <div class="pd-career-item" onclick="location.href='teams.php?id=<?= (int) $t['id']; ?>'">
                <div class="pd-career-logo">
                  <img src="<?= e($t['logo'] ?: 'https://images.unsplash.com/photo-1551958219-acbc595d9e15?w=50&q=80'); ?>" alt="">
                </div>
                <div>
                  <div class="pd-career-name"><?= e($t['name']); ?></div>
                  <div class="pd-career-city"><?= e($t['city'] ?: 'Rwanda'); ?></div>
                </div>
                <div class="pd-career-arrow">&rarr;</div>
              </div>
            <?php endforeach; ?>
            <?php if (empty($teamsPlayed)): ?>
              <div style="color:rgba(255,255,255,0.3); font-size:11px;">No historical club representation.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Right Column: Stats & Highlight ratings -->
      <div class="pd-column-right">
        <!-- Cumulative Stats Panel -->
        <div class="pd-card-dark">
          <h3 class="pd-card-title">Season Dashboard</h3>
          <div class="pd-stats-grid">
            <div class="pd-stat-box">
              <div class="pd-stat-num"><?= (int) ($cumulative['matches_played'] ?? 0); ?></div>
              <div class="pd-stat-lbl">Matches</div>
            </div>
            <div class="pd-stat-box">
              <div class="pd-stat-num"><?= (int) ($cumulative['goals'] ?? 0); ?></div>
              <div class="pd-stat-lbl">Goals</div>
            </div>
            <div class="pd-stat-box">
              <div class="pd-stat-num"><?= (int) ($cumulative['assists'] ?? 0); ?></div>
              <div class="pd-stat-lbl">Assists</div>
            </div>
            <div class="pd-stat-box">
              <?php if (strtolower($player['position']) === 'goalkeeper'): ?>
                <div class="pd-stat-num"><?= (int) ($cumulative['saves'] ?? 0); ?></div>
                <div class="pd-stat-lbl">Saves</div>
              <?php else: ?>
                <div class="pd-stat-num"><?= (int) ($cumulative['clean_sheets'] ?? 0); ?></div>
                <div class="pd-stat-lbl">Clean Sheets</div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Sub Stats details -->
          <div class="pd-substats-row">
            <div class="pd-substat">
              <span>Starts</span>
              <strong><?= (int) ($cumulative['matches_started'] ?? 0); ?></strong>
            </div>
            <div class="pd-substat">
              <span>Minutes Played</span>
              <strong><?= number_format((int) ($cumulative['minutes_played'] ?? 0)); ?> min</strong>
            </div>
            <div class="pd-substat">
              <span style="color:#facc15;">Yellow Cards</span>
              <strong style="color:#facc15;"><?= (int) ($cumulative['yellow_cards'] ?? 0); ?></strong>
            </div>
            <div class="pd-substat" style="border:none;">
              <span style="color:#f87171;">Red Cards</span>
              <strong style="color:#f87171;"><?= (int) ($cumulative['red_cards'] ?? 0); ?></strong>
            </div>
          </div>
        </div>

        <!-- Competition List Breakdown -->
        <div class="pd-card-dark">
          <h3 class="pd-card-title">Competition Statistics</h3>
          <table class="pd-stats-table">
            <thead>
              <tr>
                <th>Competition</th>
                <th>Apps</th>
                <th>Goals</th>
                <th>Assists</th>
                <th>Avg Rating</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($statsList as $s): ?>
                <tr>
                  <td style="text-align:left; font-weight:700; color:#fff;"><?= e($s['competition_name'] ?: 'National League'); ?></td>
                  <td><?= (int) $s['matches_played']; ?></td>
                  <td><?= (int) $s['goals']; ?></td>
                  <td><?= (int) $s['assists']; ?></td>
                  <td style="color:var(--org); font-weight:800;"><?= round((float)$s['average_rating'], 1); ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($statsList)): ?>
                <tr>
                  <td colspan="5" style="color:rgba(255,255,255,0.3); padding:16px;">No statistical records approved.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Match Performance Ratings Timeline & Highlights -->
        <div class="pd-card-dark">
          <h3 class="pd-card-title">Match Performance & Highlights</h3>
          <div class="pd-ratings-timeline">
            <?php foreach ($ratingsList as $r): ?>
              <div class="pd-rating-item">
                <div class="pd-rating-item-header">
                  <div class="pd-rating-match">
                    <img class="pd-rating-match-logo" src="<?= e($r['home_logo'] ?: 'https://images.unsplash.com/photo-1551958219-acbc595d9e15?w=30&q=80'); ?>" alt="">
                    <span><?= e($r['home_name']); ?> vs <?= e($r['away_name']); ?></span>
                    <img class="pd-rating-match-logo" src="<?= e($r['away_logo'] ?: 'https://images.unsplash.com/photo-1587329310690-91114ac008f0?w=30&q=80'); ?>" alt="">
                  </div>
                  <div class="pd-rating-badge" style="border-color:<?= $posColor; ?>; color:<?= $posColor; ?>;">
                    <?= (int) $r['rating']; ?>/100 Rating
                  </div>
                </div>
                
                <div class="pd-rating-date"><?= pd_format_date($r['match_date']); ?> &nbsp;•&nbsp; <?= e($r['round']); ?></div>
                
                <div class="pd-rating-summary">
                  <strong>Summary:</strong> <?= e($r['performance_summary'] ?: 'No summary submitted.'); ?>
                </div>
                
                <?php if ($r['coach_comment']): ?>
                  <div class="pd-rating-comment" style="border-left-color: <?= $posColor; ?>;">
                    <strong>Coach's Comment:</strong> <?= e($r['coach_comment']); ?>
                  </div>
                <?php endif; ?>
                
                <?php if ($r['video_path']): ?>
                  <a href="<?= e($r['video_path']); ?>" target="_blank" class="pd-video-link" style="background:<?= $posColor; ?>33; border-color:<?= $posColor; ?>44; color:#fff;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="<?= $posColor; ?>" stroke-width="3" style="display:inline-block; vertical-align:middle; margin-right:4px;"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                    Watch Highlight Clip
                  </a>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            
            <?php if (empty($ratingsList)): ?>
              <div style="color:rgba(255,255,255,0.3); text-align:center; padding:30px; font-size:12px;">
                No match performance highlights or ratings recorded for this player yet.
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<style>
.pd-section {
  background: #090d1a;
  min-height: 800px;
  padding: 36px 20px;
  color: #f1f5f9;
  font-family: 'Barlow', sans-serif;
}
.pd-container {
  max-width: 1200px;
  margin: 0 auto;
}
.pd-back-link {
  display: inline-block;
  font-size: 12px;
  font-weight: 700;
  color: var(--org);
  text-decoration: none;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  transition: color 0.18s;
}
.pd-back-link:hover {
  color: var(--org-d);
}

/* Header Banner Display */
.pd-header-banner {
  background: #111827;
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: var(--rl);
  padding: 30px;
  position: relative;
  overflow: hidden;
  box-shadow: 0 15px 35px -5px rgba(0,0,0,0.3);
  margin-bottom: 24px;
}
.pd-banner-ambient {
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: 1;
}
.pd-header-content {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  gap: 30px;
  flex-wrap: wrap;
}
.pd-header-photo {
  width: 110px;
  height: 110px;
  border-radius: 50%;
  border: 3px solid rgba(255,255,255,0.12);
  overflow: hidden;
  position: relative;
  background: #0f172a;
  flex-shrink: 0;
  box-shadow: 0 10px 25px rgba(0,0,0,0.3);
}
.pd-header-photo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: top;
}
.pd-photo-jersey {
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translate(-50%, 20%);
  color: #fff;
  font-size: 11px;
  font-weight: 900;
  padding: 2px 8px;
  border-radius: 10px;
  border: 1.5px solid #111827;
  box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}
.pd-header-info {
  flex: 1;
  min-width: 250px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
}
.pd-pos-label {
  font-size: 9px;
  font-weight: 800;
  text-transform: uppercase;
  color: #fff;
  padding: 3px 10px;
  border-radius: 30px;
  letter-spacing: 0.5px;
}
.pd-player-name {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 40px;
  font-weight: 900;
  text-transform: uppercase;
  line-height: 1;
  letter-spacing: -1px;
}
.pd-club-row {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: rgba(255,255,255,0.7);
  font-weight: 600;
}
.pd-club-logo {
  width: 20px;
  height: 20px;
  background: #fff;
  border-radius: 50%;
  overflow: hidden;
  padding: 1px;
}
.pd-club-logo img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.pd-national-badge {
  font-size: 11.5px;
  background: rgba(255,255,255,0.06);
  padding: 3px 10px;
  border-radius: 4px;
  border: 1px solid rgba(255,255,255,0.04);
}

/* Fifa Badge Rating Card */
.pd-rating-card {
  background: rgba(255,255,255,0.03);
  border: 2.5px solid rgba(255,255,255,0.1);
  border-radius: var(--rl);
  padding: 14px;
  text-align: center;
  min-width: 120px;
  flex-shrink: 0;
  backdrop-filter: blur(4px);
  box-shadow: inset 0 0 15px rgba(255,255,255,0.02);
}
.pd-rating-num {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 44px;
  font-weight: 900;
  color: #fff;
  line-height: 1;
  text-shadow: 0 0 10px rgba(255,255,255,0.15);
}
.pd-rating-text {
  font-size: 8px;
  font-weight: 800;
  letter-spacing: 1px;
  margin-top: 4px;
  margin-bottom: 8px;
}
.pd-rating-bar-wrap {
  height: 4.5px;
  background: rgba(255,255,255,0.08);
  border-radius: 10px;
  overflow: hidden;
}
.pd-rating-bar-fill {
  height: 100%;
  border-radius: 10px;
}

/* Grid Layout */
.pd-grid-layout {
  display: grid;
  grid-template-columns: 360px 1fr;
  gap: 24px;
}

/* Cards dark */
.pd-card-dark {
  background: #111827;
  border: 1px solid rgba(255,255,255,0.05);
  border-radius: var(--rl);
  padding: 20px;
  margin-bottom: 24px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}
.pd-card-title {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 15px;
  font-weight: 800;
  color: var(--org);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 16px;
}
.pd-info-list {
  display: flex;
  flex-direction: column;
}
.pd-info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  padding-bottom: 8px;
  margin-bottom: 8px;
  border-bottom: 1px solid rgba(255,255,255,0.04);
}
.pd-info-row span {
  color: rgba(255,255,255,0.4);
}
.pd-info-row strong {
  color: #fff;
  font-weight: 700;
  text-align: right;
  max-width: 210px;
}
.pd-bio-text {
  font-size: 12.5px;
  line-height: 1.6;
  color: rgba(255,255,255,0.7);
  font-style: italic;
}

/* Career Timeline */
.pd-career-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.pd-career-item {
  display: flex;
  align-items: center;
  gap: 12px;
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.04);
  border-radius: var(--r);
  padding: 10px;
  cursor: pointer;
  transition: all 0.15s;
}
.pd-career-item:hover {
  background: rgba(255,255,255,0.05);
  border-color: var(--org);
}
.pd-career-logo {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  overflow: hidden;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5px;
}
.pd-career-logo img {
  width: 80%;
  height: 80%;
  object-fit: contain;
}
.pd-career-name {
  font-size: 11px;
  font-weight: 700;
  color: #fff;
}
.pd-career-city {
  font-size: 9px;
  color: rgba(255,255,255,0.4);
}
.pd-career-arrow {
  margin-left: auto;
  font-size: 12px;
  color: rgba(255,255,255,0.25);
  transition: color 0.15s;
}
.pd-career-item:hover .pd-career-arrow {
  color: var(--org);
}

/* Season dashboard stats */
.pd-stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 20px;
}
.pd-stat-box {
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.05);
  border-radius: var(--rm);
  padding: 16px 8px;
  text-align: center;
}
.pd-stat-num {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 28px;
  font-weight: 900;
  color: #fff;
  line-height: 1;
}
.pd-stat-lbl {
  font-size: 9px;
  color: rgba(255,255,255,0.4);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-top: 4px;
}
.pd-substats-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  background: rgba(0,0,0,0.2);
  border-radius: var(--r);
  padding: 10px;
}
.pd-substat {
  text-align: center;
  border-right: 1px solid rgba(255,255,255,0.05);
  display: flex;
  flex-direction: column;
}
.pd-substat span {
  font-size: 8px;
  color: rgba(255,255,255,0.4);
  font-weight: 600;
  text-transform: uppercase;
}
.pd-substat strong {
  font-size: 12px;
  font-weight: 700;
  color: #fff;
  margin-top: 2px;
}

/* Table */
.pd-stats-table {
  width: 100%;
  border-collapse: collapse;
}
.pd-stats-table th {
  font-size: 9.5px;
  color: rgba(255,255,255,0.35);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  text-align: center;
  padding: 10px;
  border-bottom: 1.5px solid rgba(255,255,255,0.06);
}
.pd-stats-table th:first-child {
  text-align: left;
}
.pd-stats-table td {
  padding: 10px;
  font-size: 11px;
  color: rgba(255,255,255,0.7);
  text-align: center;
  border-bottom: 1px solid rgba(255,255,255,0.04);
}
.pd-stats-table td:first-child {
  text-align: left;
}
.pd-stats-table tr:hover td {
  background: rgba(255,255,255,0.01);
}

/* Ratings Timeline */
.pd-ratings-timeline {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.pd-rating-item {
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.04);
  border-radius: var(--rm);
  padding: 16px;
}
.pd-rating-item-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}
.pd-rating-match {
  font-size: 12px;
  font-weight: 700;
  color: #fff;
  display: flex;
  align-items: center;
  gap: 8px;
}
.pd-rating-match-logo {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  object-fit: cover;
  background: #fff;
  padding: 1px;
}
.pd-rating-badge {
  border: 1px solid;
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 13px;
  font-weight: 800;
  padding: 2px 8px;
  border-radius: 4px;
  background: rgba(255,255,255,0.02);
}
.pd-rating-date {
  font-size: 9px;
  color: rgba(255,255,255,0.35);
  font-weight: 600;
  margin-top: 4px;
  margin-bottom: 10px;
}
.pd-rating-summary {
  font-size: 11.5px;
  line-height: 1.5;
  color: rgba(255,255,255,0.8);
}
.pd-rating-comment {
  font-size: 11.5px;
  line-height: 1.5;
  color: rgba(255,255,255,0.65);
  background: rgba(0,0,0,0.18);
  padding: 10px;
  border-radius: 4px;
  margin-top: 10px;
  border-left: 3px solid;
}
.pd-video-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 1px solid;
  font-size: 10px;
  font-weight: 700;
  padding: 5px 12px;
  border-radius: 4px;
  text-decoration: none;
  margin-top: 12px;
  transition: opacity 0.15s;
}
.pd-video-link:hover {
  opacity: 0.9;
}

@media (max-width: 900px) {
  .pd-grid-layout {
    grid-template-columns: 1fr;
  }
}
</style>
