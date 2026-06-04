<?php
$teamId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($teamId <= 0) {
    echo "<div style='color:var(--text3); text-align:center; padding:100px; font-family:sans-serif;'>Invalid team ID. <a href='teams.php' style='color:var(--org); font-weight:700; text-decoration:none;'>&larr; Back to Clubs</a></div>";
    return;
}

// Fetch team details
$team = db_fetch_one("
    SELECT t.*, s.name AS stadium_name, s.capacity AS stadium_capacity, s.city AS stadium_city, s.address AS stadium_address
    FROM teams t
    LEFT JOIN stadiums s ON s.id = t.home_stadium_id
    WHERE t.id = ?
", 'i', [$teamId]);

if (!$team) {
    echo "<div style='color:var(--text3); text-align:center; padding:100px; font-family:sans-serif;'>Team not found. <a href='teams.php' style='color:var(--org); font-weight:700; text-decoration:none;'>&larr; Back to Clubs</a></div>";
    return;
}

// Fetch players
$players = db_fetch_all("
    SELECT p.*, ps.goals, ps.assists, ps.average_rating
    FROM players p
    LEFT JOIN player_statistics ps ON ps.player_id = p.id
    WHERE p.team_id = ? AND p.status = 'active'
    ORDER BY FIELD(p.position, 'goalkeeper', 'defender', 'midfielder', 'forward') ASC, p.jersey_number ASC, p.first_name ASC
", 'i', [$teamId]);

// Group players by position
$groupedPlayers = [
    'goalkeeper' => [],
    'defender' => [],
    'midfielder' => [],
    'forward' => []
];
foreach ($players as $p) {
    $groupedPlayers[strtolower($p['position'])][] = $p;
}

// Fetch team standing
$standing = db_fetch_one("
    SELECT * FROM team_standings WHERE team_id = ?
", 'i', [$teamId]);
?>

<section class="td-section">
  <div class="td-container">
    <!-- Back Button -->
    <a href="teams.php" class="td-back-link">&larr; Back to League Clubs</a>
    
    <!-- Hero Banner Card -->
    <div class="td-hero-banner" style="background: linear-gradient(135deg, <?= $team['primary_color'] ?: 'var(--navy)' ?> 0%, <?= $team['secondary_color'] ?: 'var(--navy-m)' ?> 100%);">
      <div class="td-hero-logo">
        <img src="<?= e($team['logo'] ?: 'https://images.unsplash.com/photo-1551958219-acbc595d9e15?w=160&q=80'); ?>" alt="Club Logo">
      </div>
      <div class="td-hero-info">
        <h1 class="td-hero-name"><?= e($team['name']); ?></h1>
        <div class="td-hero-meta">
          <span>📍 <?= e($team['city'] ?: 'Kigali'); ?></span>
          <span class="td-meta-separator">•</span>
          <span>📅 Founded <?= e($team['founded_year'] ?: 'N/A'); ?></span>
          <span class="td-meta-separator">•</span>
          <span>🏟️ <?= e($team['stadium_name'] ?: 'Home Arena'); ?></span>
        </div>
      </div>
      <div class="td-hero-stats">
        <div class="td-hero-stat-box">
          <div class="td-hstat-val"><?= count($players); ?></div>
          <div class="td-hstat-lbl">Active Players</div>
        </div>
        <?php if ($standing): ?>
          <div class="td-hero-stat-box">
            <div class="td-hstat-val" style="color:var(--org);">#<?= (int) $standing['position']; ?></div>
            <div class="td-hstat-lbl">League Rank</div>
          </div>
          <div class="td-hero-stat-box">
            <div class="td-hstat-val"><?= (int) $standing['points']; ?></div>
            <div class="td-hstat-lbl">Points</div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Main Layout -->
    <div class="td-layout">
      <!-- Sidebar Details -->
      <div class="td-sidebar">
        <!-- Stadium Information -->
        <div class="td-side-card">
          <h3 class="td-side-title">Home Stadium</h3>
          <div class="td-stadium-img">
            <img src="https://images.unsplash.com/photo-1508098682722-e99c43a406b2?w=400&q=80" alt="Stadium">
          </div>
          <div class="td-side-list">
            <div class="td-side-row">
              <span>Name</span>
              <strong><?= e($team['stadium_name'] ?: 'Home Arena'); ?></strong>
            </div>
            <div class="td-side-row">
              <span>Capacity</span>
              <strong><?= $team['stadium_capacity'] ? number_format($team['stadium_capacity']) . ' seats' : 'N/A'; ?></strong>
            </div>
            <div class="td-side-row">
              <span>City</span>
              <strong><?= e($team['stadium_city'] ?: $team['city']); ?></strong>
            </div>
            <div class="td-side-row" style="border:none;">
              <span>Address</span>
              <strong style="font-size:11px;"><?= e($team['stadium_address'] ?: 'Kigali, Rwanda'); ?></strong>
            </div>
          </div>
        </div>

        <!-- Coach & Contacts -->
        <div class="td-side-card">
          <h3 class="td-side-title">Club Directory</h3>
          <div class="td-side-list">
            <div class="td-side-row">
              <span>Head Coach</span>
              <strong>👨‍🏫 <?= e($team['coach_name'] ?: 'N/A'); ?></strong>
            </div>
            <div class="td-side-row">
              <span>Email</span>
              <strong>✉️ <?= e($team['contact_email'] ?: 'N/A'); ?></strong>
            </div>
            <div class="td-side-row" style="border:none;">
              <span>Phone</span>
              <strong>📞 <?= e($team['contact_phone'] ?: 'N/A'); ?></strong>
            </div>
          </div>
        </div>
        
        <?php if ($standing): ?>
          <!-- Standing Record -->
          <div class="td-side-card">
            <h3 class="td-side-title">Season Record</h3>
            <div class="td-record-grid">
              <div class="td-record-box"><span>P</span><strong><?= (int) $standing['matches_played']; ?></strong></div>
              <div class="td-record-box" style="color:#22c55e;"><span>W</span><strong><?= (int) $standing['wins']; ?></strong></div>
              <div class="td-record-box" style="color:#eab308;"><span>D</span><strong><?= (int) $standing['draws']; ?></strong></div>
              <div class="td-record-box" style="color:#ef4444;"><span>L</span><strong><?= (int) $standing['losses']; ?></strong></div>
            </div>
            <div class="td-side-list" style="margin-top:12px;">
              <div class="td-side-row">
                <span>Goals For / Against</span>
                <strong><?= (int) $standing['goals_for']; ?> / <?= (int) $standing['goals_against']; ?></strong>
              </div>
              <div class="td-side-row" style="border:none;">
                <span>Goal Difference</span>
                <strong style="color:<?= $standing['goal_difference'] >= 0 ? '#22c55e' : '#ef4444' ?>;"><?= ($standing['goal_difference'] > 0 ? '+' : '') . (int) $standing['goal_difference']; ?></strong>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Main Roster -->
      <div class="td-main">
        <h2 class="td-squad-title">Active <span>Squad Roster</span></h2>
        
        <?php 
        $positions = [
            'goalkeeper' => ['label' => 'Goalkeepers', 'color' => '#F97316', 'badge' => 'gk-b'],
            'defender' => ['label' => 'Defenders', 'color' => '#1E3A8A', 'badge' => 'def-b'],
            'midfielder' => ['label' => 'Midfielders', 'color' => '#15803d', 'badge' => 'mid-b'],
            'forward' => ['label' => 'Forwards', 'color' => '#dc2626', 'badge' => 'fwd-b']
        ];
        
        foreach ($positions as $posKey => $posInfo):
            $squad = $groupedPlayers[$posKey];
            if (empty($squad)) continue;
        ?>
          <div class="td-pos-group">
            <h3 class="td-pos-title" style="border-left:4px solid <?= $posInfo['color']; ?>;"><?= $posInfo['label']; ?></h3>
            <div class="td-pos-grid">
              <?php foreach ($squad as $p): 
                  $rating = (int) ($p['average_rating'] ?: 75);
                  $pImg = $p['photo_pl'] ?: 'https://images.unsplash.com/photo-1627483297886-49710ae1fc22?w=300&q=80';
              ?>
                <div class="td-player-card" onclick="location.href='players.php?id=<?= (int) $p['id']; ?>'">
                  <div class="td-pc-img-wrap">
                    <img src="<?= e($pImg); ?>" alt="">
                    <div class="td-pc-img-overlay" style="background: linear-gradient(to top, <?= $posInfo['color']; ?>bb, transparent);"></div>
                    <div class="td-pc-jersey">#<?= (int) $p['jersey_number']; ?></div>
                  </div>
                  <div class="td-pc-body">
                    <h4 class="td-pc-name"><?= e($p['first_name'] . ' ' . $p['last_name']); ?></h4>
                    <span class="td-pc-nat">🌐 <?= e($p['nationality'] ?: 'Rwanda'); ?></span>
                    
                    <div class="td-pc-stats">
                      <div class="td-pc-stat-box">⚽ <?= (int) $p['goals']; ?> G</div>
                      <div class="td-pc-stat-box">👟 <?= (int) $p['assists']; ?> A</div>
                    </div>
                    <div class="td-pc-rating">
                      <span class="td-pc-rating-val"><?= $rating; ?></span>
                      <span class="td-pc-rating-lbl">Rating</span>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
        
        <?php if (empty($players)): ?>
          <div style="color:var(--text3); text-align:center; padding:60px; background:#fff; border:1px solid var(--gray-l); border-radius:var(--rl);">
             No active players registered in this squad roster.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<style>
.td-section {
  background: var(--off);
  min-height: 700px;
  padding: 30px 20px;
}
.td-container {
  max-width: 1200px;
  margin: 0 auto;
}
.td-back-link {
  display: inline-block;
  font-size: 12px;
  font-weight: 700;
  color: var(--org);
  text-decoration: none;
  margin-bottom: 20px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  transition: color 0.2s;
}
.td-back-link:hover {
  color: var(--org-d);
}

/* Hero Banner */
.td-hero-banner {
  border-radius: var(--rl);
  padding: 30px;
  display: flex;
  align-items: center;
  gap: 25px;
  color: #fff;
  box-shadow: 0 10px 25px -5px rgba(15,31,75,0.15);
  margin-bottom: 24px;
  position: relative;
  overflow: hidden;
  flex-wrap: wrap;
}
.td-hero-logo {
  width: 90px;
  height: 90px;
  border-radius: 50%;
  background: #fff;
  border: 3px solid rgba(255,255,255,0.25);
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 8px 20px rgba(0,0,0,0.15);
  flex-shrink: 0;
}
.td-hero-logo img {
  width: 80%;
  height: 80%;
  object-fit: contain;
}
.td-hero-info {
  flex: 1;
  min-width: 250px;
}
.td-hero-name {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 36px;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: -0.5px;
  line-height: 1.1;
  margin-bottom: 6px;
}
.td-hero-meta {
  font-size: 12px;
  color: rgba(255,255,255,0.75);
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  font-weight: 500;
}
.td-meta-separator {
  color: rgba(255,255,255,0.3);
}
.td-hero-stats {
  display: flex;
  gap: 12px;
}
.td-hero-stat-box {
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: var(--rm);
  padding: 10px 18px;
  text-align: center;
  min-width: 90px;
}
.td-hstat-val {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 26px;
  font-weight: 900;
  line-height: 1;
}
.td-hstat-lbl {
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  color: rgba(255,255,255,0.45);
  margin-top: 3px;
  letter-spacing: 0.3px;
}

/* Two-column Layout */
.td-layout {
  display: grid;
  grid-template-columns: 320px 1fr;
  gap: 24px;
}

/* Sidebar */
.td-side-card {
  background: #fff;
  border: 1px solid var(--gray-l);
  border-radius: var(--rl);
  padding: 20px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.02);
  margin-bottom: 20px;
}
.td-side-title {
  font-size: 13px;
  font-weight: 800;
  color: var(--navy);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 14px;
}
.td-stadium-img {
  height: 110px;
  border-radius: var(--r);
  overflow: hidden;
  margin-bottom: 14px;
}
.td-stadium-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.td-side-list {
  display: flex;
  flex-direction: column;
}
.td-side-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11.5px;
  padding-bottom: 8px;
  margin-bottom: 8px;
  border-bottom: 1px solid var(--gray-ll);
}
.td-side-row span {
  color: var(--gray);
  font-weight: 500;
}
.td-side-row strong {
  color: var(--text);
  font-weight: 700;
  text-align: right;
  max-width: 170px;
}
.td-record-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
}
.td-record-box {
  background: var(--gray-ll);
  border-radius: var(--r);
  padding: 6px 4px;
  text-align: center;
  display: flex;
  flex-direction: column;
  line-height: 1.1;
}
.td-record-box span {
  font-size: 8px;
  font-weight: 700;
  color: var(--gray);
  margin-bottom: 2px;
}
.td-record-box strong {
  font-size: 13px;
  font-weight: 800;
}

/* Roster Main */
.td-main {
  background: #fff;
  border: 1px solid var(--gray-l);
  border-radius: var(--rl);
  padding: 24px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.02);
}
.td-squad-title {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 22px;
  font-weight: 800;
  color: var(--navy);
  text-transform: uppercase;
  margin-bottom: 20px;
  letter-spacing: -0.3px;
}
.td-squad-title span {
  color: var(--org);
}
.td-pos-group {
  margin-bottom: 24px;
}
.td-pos-title {
  font-size: 13px;
  font-weight: 800;
  color: var(--text);
  text-transform: uppercase;
  padding-left: 10px;
  margin-bottom: 14px;
  letter-spacing: 0.5px;
}
.td-pos-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 14px;
}

/* Player Card */
.td-player-card {
  background: var(--gray-ll);
  border: 1px solid var(--gray-l);
  border-radius: var(--rm);
  overflow: hidden;
  cursor: pointer;
  transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
  display: flex;
  flex-direction: column;
}
.td-player-card:hover {
  transform: translateY(-4px);
  border-color: var(--org);
  box-shadow: 0 8px 18px rgba(249,115,22,0.1);
  background: #fff;
}
.td-pc-img-wrap {
  height: 90px;
  position: relative;
  overflow: hidden;
  background: rgba(0,0,0,0.05);
}
.td-pc-img-wrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: top;
  transition: transform 0.2s;
}
.td-player-card:hover .td-pc-img-wrap img {
  transform: scale(1.04);
}
.td-pc-img-overlay {
  position: absolute;
  inset: 0;
}
.td-pc-jersey {
  position: absolute;
  top: 8px;
  right: 8px;
  background: rgba(0,0,0,0.65);
  color: #fff;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 800;
  border: 1px solid rgba(255,255,255,0.2);
}
.td-pc-body {
  padding: 10px;
  display: flex;
  flex-direction: column;
  flex: 1;
}
.td-pc-name {
  font-size: 12.5px;
  font-weight: 800;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-bottom: 2px;
}
.td-pc-nat {
  font-size: 9px;
  color: var(--gray);
  font-weight: 600;
}
.td-pc-stats {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4px;
  background: rgba(0,0,0,0.03);
  border-radius: 4px;
  padding: 4px;
  font-size: 9px;
  font-weight: 700;
  color: var(--text2);
  margin-top: 8px;
  text-align: center;
}
.td-pc-rating {
  margin-top: 8px;
  display: flex;
  align-items: flex-end;
  gap: 4px;
  justify-content: center;
}
.td-pc-rating-val {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 17px;
  font-weight: 900;
  color: var(--text);
  line-height: 1;
}
.td-pc-rating-lbl {
  font-size: 8px;
  color: var(--text3);
  text-transform: uppercase;
  font-weight: 600;
}

@media (max-width: 900px) {
  .td-layout {
    grid-template-columns: 1fr;
  }
}
</style>
