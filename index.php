<?php
$currentPage = 'home';
require_once __DIR__ . '/public_header.php';

// 4. Fetch Featured Match Banner (priority: live, then upcoming/most recent)
$featuredMatch = db_fetch_one("
    SELECT m.*, ht.name AS home_name, ht.logo AS home_logo, at.name AS away_name, at.logo AS away_logo, mr.home_score, mr.away_score, s.name AS stadium_name
    FROM matches m
    INNER JOIN teams ht ON ht.id = m.home_team_id
    INNER JOIN teams at ON at.id = m.away_team_id
    LEFT JOIN match_results mr ON mr.match_id = m.id AND mr.status = 'approved'
    LEFT JOIN stadiums s ON s.id = m.stadium_id
    ORDER BY (m.status = 'in_progress') DESC, m.match_date DESC, m.match_time DESC LIMIT 1
");

// 5. Fetch Upcoming & Live Matches List
$matchdayMatches = db_fetch_all("
    SELECT m.*, ht.name AS home_name, ht.logo AS home_logo, at.name AS away_name, at.logo AS away_logo, mr.home_score, mr.away_score, s.name AS stadium_name
    FROM matches m
    INNER JOIN teams ht ON ht.id = m.home_team_id
    INNER JOIN teams at ON at.id = m.away_team_id
    LEFT JOIN match_results mr ON mr.match_id = m.id AND mr.status = 'approved'
    LEFT JOIN stadiums s ON s.id = m.stadium_id
    ORDER BY (m.status = 'in_progress') DESC, m.match_date DESC, m.match_time DESC LIMIT 3
");

// 6. Fetch League Standings
$standings = db_fetch_all("
    SELECT ts.*, t.name AS team_name, t.logo AS team_logo
    FROM team_standings ts
    INNER JOIN teams t ON t.id = ts.team_id
    ORDER BY ts.points DESC, ts.goal_difference DESC, ts.goals_for DESC LIMIT 6
");

// 7. Fetch Registered Teams
$registeredTeams = db_fetch_all("
    SELECT t.*, s.name AS stadium_name
    FROM teams t
    LEFT JOIN stadiums s ON s.id = t.home_stadium_id
    ORDER BY t.is_active DESC, t.created_at ASC LIMIT 8
");

// 8. Fetch Top Rated Players
$topPlayers = db_fetch_all("
    SELECT p.*, t.name AS team_name, ps.average_rating
    FROM players p
    INNER JOIN teams t ON t.id = p.team_id
    INNER JOIN player_statistics ps ON ps.player_id = p.id
    ORDER BY ps.average_rating DESC LIMIT 6
");

// 9. Fetch News
$allNews = db_fetch_all("
    SELECT * FROM news WHERE is_published = 1 ORDER BY published_at DESC LIMIT 5
");
$mainNews = $allNews[0] ?? null;
$subNews = array_slice($allNews, 1);

// 10. Fetch Pitch Lineup Data Dynamically
$latestMatch = db_fetch_one("
    SELECT m.id, m.match_date, m.match_time, m.matchday,
           ht.id home_team_id, ht.name home_team, ht.logo home_logo,
           at.id away_team_id, at.name away_team, at.logo away_logo,
           s.name stadium_name, c.name competition_name,
           (SELECT MAX(updated_at) FROM match_lineups WHERE match_id = m.id) AS last_lineup_update
    FROM matches m 
    LEFT JOIN teams ht ON ht.id = m.home_team_id 
    LEFT JOIN teams at ON at.id = m.away_team_id 
    LEFT JOIN stadiums s ON s.id = m.stadium_id 
    LEFT JOIN competitions c ON c.id = m.competition_id
    WHERE m.status IN ('scheduled', 'lineup_pending', 'lineup_approved', 'in_progress', 'completed')
    ORDER BY (SELECT COUNT(*) FROM match_lineups WHERE match_id = m.id) DESC, last_lineup_update DESC, m.match_date DESC, m.id DESC 
    LIMIT 1
");

// Fallback defaults if no match exists in the database
$homeId = 0; $awayId = 0;
$homeName = 'APR FC'; $awayName = 'Police FC';
$homeLogo = ''; $awayLogo = '';
$matchdayText = 'Matchday 23 &nbsp;•&nbsp; Sat 15:00';
$stadiumText = 'Regional Stadium';

$homeFormation = '4-3-3';
$awayFormation = '4-4-2';

$homePlayers = [];
$homeBench = [];
$awayPlayers = [];
$awayBench = [];

// Map position strings to abbreviated positions
if (!function_exists('mapPositionAbbrev')) {
    function mapPositionAbbrev($pos) {
        $map = [
            'goalkeeper' => 'GK',
            'defender' => 'CB',
            'midfielder' => 'CM',
            'forward' => 'ST'
        ];
        return $map[strtolower($pos)] ?? 'CM';
    }
}

if (!function_exists('mapPositionType')) {
    function mapPositionType($pos) {
        $map = [
            'goalkeeper' => 'gk',
            'defender' => 'def',
            'midfielder' => 'mid',
            'forward' => 'fwd'
        ];
        return $map[strtolower($pos)] ?? 'mid';
    }
}

if (!function_exists('mapPositionTypeColor')) {
    function mapPositionTypeColor($posType) {
        $colors = [
            'gk' => '#F97316', // Orange
            'def' => '#1E3A8A', // Navy
            'mid' => '#15803d', // Green
            'fwd' => '#dc2626'  // Red
        ];
        return $colors[$posType] ?? '#15803d';
    }
}

if ($latestMatch) {
    $homeId = (int) $latestMatch['home_team_id'];
    $awayId = (int) $latestMatch['away_team_id'];
    $homeName = $latestMatch['home_team'];
    $awayName = $latestMatch['away_team'];
    $homeLogo = $latestMatch['home_logo'];
    $awayLogo = $latestMatch['away_logo'];
    
    $mDate = date('D H:i', strtotime($latestMatch['match_date'] . ' ' . ($latestMatch['match_time'] ?: '15:00:00')));
    $matchdayText = 'Matchday ' . ($latestMatch['matchday'] ?: '1') . ' &nbsp;•&nbsp; ' . $mDate;
    $stadiumText = $latestMatch['stadium_name'] ?: 'National Stadium';

    // 1. Fetch Home Lineup
    $homeLineup = db_fetch_one("
        SELECT ml.id, f.name formation_name, f.display_name formation_display 
        FROM match_lineups ml 
        JOIN formations f ON f.id = ml.formation_id 
        WHERE ml.match_id = ? AND ml.team_id = ?
    ", 'ii', [(int) $latestMatch['id'], $homeId]);

    if ($homeLineup) {
        $homeFormation = $homeLineup['formation_name'];
        $homeStarters = db_fetch_all("
            SELECT lp.*, p.first_name, p.last_name, p.jersey_number, p.position, p.photo_pl 
            FROM lineup_players lp 
            JOIN players p ON p.id = lp.player_id 
            WHERE lp.lineup_id = ? AND lp.is_starter = 1
            ORDER BY FIELD(lp.position_slot, 'GK', 'DEF_0', 'DEF_1', 'DEF_2', 'DEF_3', 'DEF_4', 'MID_0', 'MID_1', 'MID_2', 'MID_3', 'MID_4', 'FWD_0', 'FWD_1', 'FWD_2') ASC
        ", 'i', [(int) $homeLineup['id']]);
        
        $homePlayers = array_map(function($p) {
            return [
                'name' => $p['first_name'] . ' ' . $p['last_name'],
                'short' => strtoupper(substr($p['last_name'], 0, 3)),
                'num' => (int) $p['jersey_number'],
                'pos' => mapPositionAbbrev($p['position']),
                'posType' => mapPositionType($p['position']),
                'img' => $p['photo_pl'] ? app_url($p['photo_pl']) : '',
                'x' => (float) $p['field_x'],
                'y' => (float) $p['field_y']
            ];
        }, $homeStarters);

        $homeSubstitutes = db_fetch_all("
            SELECT p.first_name, p.last_name, p.jersey_number, p.position 
            FROM lineup_players lp 
            JOIN players p ON p.id = lp.player_id 
            WHERE lp.lineup_id = ? AND lp.is_starter = 0
        ", 'i', [(int) $homeLineup['id']]);
        
        $homeBench = array_map(function($p) {
            return [
                'name' => $p['first_name'] . ' ' . $p['last_name'],
                'num' => (int) $p['jersey_number'],
                'pos' => mapPositionAbbrev($p['position']),
                'posType' => mapPositionType($p['position'])
            ];
        }, $homeSubstitutes);
    } else {
        // Fallback: active players in default 4-3-3 coordinates
        $activePlayers = db_fetch_all("SELECT p.* FROM players p WHERE p.team_id = ? AND p.status = 'active' LIMIT 11", 'i', [$homeId]);
        $defaultCoords = [
            ['x' => 180, 'y' => 455],
            ['x' => 55, 'y' => 375], ['x' => 128, 'y' => 375], ['x' => 232, 'y' => 375], ['x' => 305, 'y' => 375],
            ['x' => 88, 'y' => 282], ['x' => 180, 'y' => 272], ['x' => 272, 'y' => 282],
            ['x' => 78, 'y' => 172], ['x' => 180, 'y' => 158], ['x' => 282, 'y' => 172]
        ];
        foreach ($activePlayers as $idx => $p) {
            $c = $defaultCoords[$idx] ?? ['x' => 180, 'y' => 250];
            $homePlayers[] = [
                'name' => $p['first_name'] . ' ' . $p['last_name'],
                'short' => strtoupper(substr($p['last_name'], 0, 3)),
                'num' => (int) $p['jersey_number'],
                'pos' => mapPositionAbbrev($p['position']),
                'posType' => mapPositionType($p['position']),
                'img' => $p['photo_pl'] ? app_url($p['photo_pl']) : '',
                'x' => $c['x'],
                'y' => $c['y']
            ];
        }
    }

    // 2. Fetch Away Lineup
    $awayLineup = db_fetch_one("
        SELECT ml.id, f.name formation_name, f.display_name formation_display 
        FROM match_lineups ml 
        JOIN formations f ON f.id = ml.formation_id 
        WHERE ml.match_id = ? AND ml.team_id = ?
    ", 'ii', [(int) $latestMatch['id'], $awayId]);

    if ($awayLineup) {
        $awayFormation = $awayLineup['formation_name'];
        $awayStarters = db_fetch_all("
            SELECT lp.*, p.first_name, p.last_name, p.jersey_number, p.position, p.photo_pl 
            FROM lineup_players lp 
            JOIN players p ON p.id = lp.player_id 
            WHERE lp.lineup_id = ? AND lp.is_starter = 1
            ORDER BY FIELD(lp.position_slot, 'GK', 'DEF_0', 'DEF_1', 'DEF_2', 'DEF_3', 'DEF_4', 'MID_0', 'MID_1', 'MID_2', 'MID_3', 'MID_4', 'FWD_0', 'FWD_1', 'FWD_2') ASC
        ", 'i', [(int) $awayLineup['id']]);
        
        $awayPlayers = array_map(function($p) {
            return [
                'name' => $p['first_name'] . ' ' . $p['last_name'],
                'short' => strtoupper(substr($p['last_name'], 0, 3)),
                'num' => (int) $p['jersey_number'],
                'pos' => mapPositionAbbrev($p['position']),
                'posType' => mapPositionType($p['position']),
                'img' => $p['photo_pl'] ? app_url($p['photo_pl']) : '',
                'x' => (float) $p['field_x'],
                'y' => (float) $p['field_y']
            ];
        }, $awayStarters);

        $awaySubstitutes = db_fetch_all("
            SELECT p.first_name, p.last_name, p.jersey_number, p.position 
            FROM lineup_players lp 
            JOIN players p ON p.id = lp.player_id 
            WHERE lp.lineup_id = ? AND lp.is_starter = 0
        ", 'i', [(int) $awayLineup['id']]);
        
        $awayBench = array_map(function($p) {
            return [
                'name' => $p['first_name'] . ' ' . $p['last_name'],
                'num' => (int) $p['jersey_number'],
                'pos' => mapPositionAbbrev($p['position']),
                'posType' => mapPositionType($p['position'])
            ];
        }, $awaySubstitutes);
    } else {
        // Fallback: active players in default 4-4-2 coordinates
        $activePlayers = db_fetch_all("SELECT p.* FROM players p WHERE p.team_id = ? AND p.status = 'active' LIMIT 11", 'i', [$awayId]);
        $defaultCoords = [
            ['x' => 180, 'y' => 455],
            ['x' => 55, 'y' => 370], ['x' => 125, 'y' => 370], ['x' => 220, 'y' => 370], ['x' => 290, 'y' => 370],
            ['x' => 62, 'y' => 275], ['x' => 135, 'y' => 265], ['x' => 210, 'y' => 265], ['x' => 285, 'y' => 275],
            ['x' => 130, 'y' => 158], ['x' => 215, 'y' => 158]
        ];
        foreach ($activePlayers as $idx => $p) {
            $c = $defaultCoords[$idx] ?? ['x' => 180, 'y' => 250];
            $awayPlayers[] = [
                'name' => $p['first_name'] . ' ' . $p['last_name'],
                'short' => strtoupper(substr($p['last_name'], 0, 3)),
                'num' => (int) $p['jersey_number'],
                'pos' => mapPositionAbbrev($p['position']),
                'posType' => mapPositionType($p['position']),
                'img' => $p['photo_pl'] ? app_url($p['photo_pl']) : '',
                'x' => $c['x'],
                'y' => $c['y']
            ];
        }
    }
}
?>

<!-- STATS ROW -->
<section style="padding:16px 20px">
  <div class="stats-row">
    <div class="stat-card"><div class="stat-ic"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div><div><div class="stat-v"><?= $activeTeams; ?></div><div class="stat-n">Active Teams</div></div></div>
    <div class="stat-card"><div class="stat-ic"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></div><div><div class="stat-v"><?= $scheduledMatches; ?></div><div class="stat-n">Scheduled</div></div></div>
    <div class="stat-card"><div class="stat-ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg></div><div><div class="stat-v" id="live-count"><?= $liveMatches; ?></div><div class="stat-n">Live Now</div></div></div>
    <div class="stat-card"><div class="stat-ic"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div><div><div class="stat-v"><?= $seasonGoals; ?></div><div class="stat-n">Season Goals</div></div></div>
  </div>
</section>

<!-- FEATURED MATCH BANNER -->
<section style="padding:8px 20px 16px">
  <?php if ($featuredMatch): 
    $isLive = $featuredMatch['status'] === 'in_progress';
    $scoreStr = ($isLive || $featuredMatch['status'] === 'completed') ? "{$featuredMatch['home_score']} – {$featuredMatch['away_score']}" : "vs";
  ?>
  <div class="feat-banner">
    <img src="https://images.unsplash.com/photo-1574629810360-7efbbe195018?w=1200&q=80" alt="Match"/>
    <div class="feat-ov"></div>
    <div class="feat-content">
      <div class="feat-left">
        <div class="feat-badge"><?= $isLive ? '🟢 LIVE NOW' : '🏆 FEATURED MATCH'; ?> — MATCHDAY <?= $featuredMatch['matchday']; ?></div>
        <div class="feat-title"><?= e($featuredMatch['home_name']); ?><br>vs <?= e($featuredMatch['away_name']); ?></div>
        <div class="feat-sub"><?= e($featuredMatch['stadium_name'] ?: 'Amahoro National Stadium'); ?> &nbsp;•&nbsp; <span style="color:var(--org);font-weight:700" id="live-min"><?= $isLive ? "67'" : "Upcoming"; ?></span></div>
      </div>
      <div class="feat-right">
        <div class="feat-team">
          <div class="feat-tm-img"><img src="<?= e($featuredMatch['home_logo'] ?: 'https://images.unsplash.com/photo-1551958219-acbc595d9e15?w=100&q=80'); ?>" alt="Home"/></div>
          <div class="feat-tm-name"><?= e($featuredMatch['home_name']); ?></div>
        </div>
        <div style="text-align:center">
          <div style="font-family:'Barlow Condensed',sans-serif;font-size:36px;font-weight:900;color:#fff;line-height:1" id="live-score"><?= $scoreStr; ?></div>
          <div class="feat-vs"><?= $isLive ? 'LIVE' : 'VS'; ?></div>
        </div>
        <div class="feat-team">
          <div class="feat-tm-img"><img src="<?= e($featuredMatch['away_logo'] ?: 'https://images.unsplash.com/photo-1587329310690-91114ac008f0?w=100&q=80'); ?>" alt="Away"/></div>
          <div class="feat-tm-name"><?= e($featuredMatch['away_name']); ?></div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</section>

<div class="divider"></div>

<!-- MATCHES + STANDINGS -->
<section>
  <div class="two-col">
    <div>
      <div class="sec-hd">
        <div><div class="sec-t">Upcoming & <span>Live Matches</span></div><div class="sec-sub">Current matchday schedule</div></div>
        <a class="sec-lnk" href="matches.php" style="text-decoration:none;">All matches →</a>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php if (empty($matchdayMatches)): ?>
          <div class="empty-state" style="color:var(--text3);text-align:center;padding:20px;">No scheduled matches.</div>
        <?php else: ?>
          <?php foreach ($matchdayMatches as $m): 
            $isLive = $m['status'] === 'in_progress';
            $isFinished = $m['status'] === 'completed';
            $stBadge = $isLive ? 'Live' : ($isFinished ? 'Full Time' : 'Upcoming');
            $stClass = $isLive ? 'live' : ($isFinished ? 'finished' : 'upcoming');
            $scoreText = ($isLive || $isFinished) ? "{$m['home_score']} – {$m['away_score']}" : "vs";
            $subText = $isLive ? "67'" : ($isFinished ? 'FT' : date('D H:i', strtotime($m['match_date'] . ' ' . $m['match_time'])));
          ?>
          <div class="match-card" onclick="window.location='matches.php'">
            <div class="mc-top"><span class="mc-lg">Rwanda Premier League</span><div style="display:flex;gap:5px;align-items:center"><span style="font-size:9px;color:rgba(255,255,255,.35)">MD <?= $m['matchday']; ?></span><span class="mc-st <?= $stClass; ?>"><?= $isLive ? '● ' : ''; ?><?= $stBadge; ?></span></div></div>
            <div class="mc-body"><div class="mc-teams">
              <div class="mc-team"><div class="t-logo"><img src="<?= e($m['home_logo'] ?: 'https://images.unsplash.com/photo-1551958219-acbc595d9e15?w=80&q=80'); ?>" alt="Home"/></div><div class="mc-tn"><?= e($m['home_name']); ?></div></div>
              <div class="mc-vs"><div class="mc-sc"><?= $scoreText; ?></div><span class="mc-vt"><?= $subText; ?></span></div>
              <div class="mc-team"><div class="t-logo"><img src="<?= e($m['away_logo'] ?: 'https://images.unsplash.com/photo-1587329310690-91114ac008f0?w=80&q=80'); ?>" alt="Away"/></div><div class="mc-tn"><?= e($m['away_name']); ?></div></div>
            </div></div>
            <div class="mc-foot"><span class="mc-venue">📍 <?= e($m['stadium_name'] ?: 'Amahoro Stadium'); ?></span><a href="matches.php" class="mc-btn" style="text-decoration:none;">View Lineup →</a></div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    
    <div>
      <div class="sec-hd">
        <div><div class="sec-t">League <span>Standings</span></div><div class="sec-sub">Season 2026/27 Table</div></div>
        <a class="sec-lnk" href="standings.php" style="text-decoration:none;">Full table →</a>
      </div>
      <div class="std-wrap">
        <div class="std-hd"><span class="std-hd-t">Rwanda Premier <span>League</span></span><span style="font-size:10px;color:rgba(255,255,255,.3)">MD 22/30</span></div>
        <table class="std-tbl">
          <thead><tr><th>#</th><th>Team</th><th>P</th><th>W</th><th>D</th><th>L</th><th>GD</th><th>Form</th><th>Pts</th></tr></thead>
          <tbody>
            <?php if (empty($standings)): ?>
              <tr><td colspan="9" style="color:rgba(255,255,255,.35);padding:20px;">No standings recorded.</td></tr>
            <?php else: ?>
              <?php foreach ($standings as $row): 
                $rank = $row['position'];
                $rClass = $rank === 1 ? 'r1' : ($rank === 2 ? 'r2' : ($rank === 3 ? 'r3' : ''));
              ?>
              <tr class="<?= $rClass; ?>" onclick="window.location='standings.php'">
                <td><?= $rank; ?></td>
                <td><div class="std-team"><div class="std-lg"><img src="<?= e($row['team_logo'] ?: 'https://images.unsplash.com/photo-1551958219-acbc595d9e15?w=40&q=80'); ?>" alt=""/></div><span class="std-nm"><?= e($row['team_name']); ?></span></div></td>
                <td><?= $row['matches_played']; ?></td>
                <td><?= $row['wins']; ?></td>
                <td><?= $row['draws']; ?></td>
                <td><?= $row['losses']; ?></td>
                <td><?= $row['goal_difference'] >= 0 ? '+' . $row['goal_difference'] : $row['goal_difference']; ?></td>
                <td><span class="fw">W</span><span class="fw">W</span><span class="fd">D</span><span class="fw">W</span><span class="fl">L</span></td>
                <td class="pts"><?= $row['points']; ?></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<div class="divider"></div>

<!-- TEAMS -->
<section style="background:var(--off)">
  <div class="sec-hd">
    <div><div class="sec-t">All <span>Registered Teams</span></div><div class="sec-sub">2026/27 Premier League Season</div></div>
    <a class="sec-lnk" href="teams.php" style="text-decoration:none;">Manage teams →</a>
  </div>
  <div class="teams-grid">
    <?php if (empty($registeredTeams)): ?>
      <div class="empty-state" style="color:var(--text3);text-align:center;padding:20px;">No teams registered.</div>
    <?php else: ?>
      <?php foreach ($registeredTeams as $team): ?>
      <div class="team-card" onclick="window.location='teams.php'">
        <div class="tc-cover">
          <img src="https://images.unsplash.com/photo-1522778119026-d647f0596c20?w=300&q=70" alt=""/>
          <div class="tc-cover-overlay"></div>
        </div>
        <div class="tc-body">
          <div class="tc-logo">
            <img src="<?= e($team['logo'] ?: 'https://images.unsplash.com/photo-1551958219-acbc595d9e15?w=80&q=80'); ?>" alt="Logo"/>
          </div>
          <div class="tc-name"><?= e($team['name']); ?></div>
          <div class="tc-meta"><?= e($team['city'] ?: 'Kigali'); ?></div>
          <span class="tc-badge <?= $team['is_active'] ? 'ab' : 'ib'; ?>"><?= $team['is_active'] ? 'Active' : 'Inactive'; ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<div class="divider"></div>

<!-- LINEUP PREVIEW (INTERACTIVE) -->
<section>
  <div class="sec-hd">
    <div><div class="sec-t">Match <span>Lineup Preview</span></div><div class="sec-sub">Toggle teams to view confirmed formations</div></div>
    <a class="sec-lnk" href="matches.php" style="text-decoration:none;">Full lineup →</a>
  </div>
  <div class="pitch-section">
    <!-- Header -->
    <div class="ps-hd">
      <div class="ps-match-info">
        <div class="ps-title" id="ps-match-title"><?= e($homeName); ?> <span>vs <?= e($awayName); ?></span></div>
        <div class="ps-meta">
          <span class="ps-meta-dot"></span>
          <span id="ps-formation-label">Formation: <?= e($homeFormation); ?></span>
          <span style="color:rgba(255,255,255,.15)">|</span>
          <span><?= $matchdayText; ?> &nbsp;•&nbsp; <?= e($stadiumText); ?></span>
        </div>
      </div>
      <div class="ps-tabs">
        <span class="ps-tab active" data-team="0"><?= e($homeName); ?></span>
        <span class="ps-tab" data-team="1"><?= e($awayName); ?></span>
      </div>
    </div>
    <!-- Body -->
    <div class="ps-body">
      <!-- PITCH FIELD: SVG panels -->
      <div class="pitch-field">

        <!-- APR FC — 4-3-3 -->
        <div class="pitch-panel" id="panel-0">
          <svg viewBox="0 0 360 500" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:auto">
            <defs>
              <linearGradient id="gfg0" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#185e2d"/>
                <stop offset="25%" stop-color="#1d6b34"/>
                <stop offset="50%" stop-color="#196030"/>
                <stop offset="75%" stop-color="#1d6b34"/>
                <stop offset="100%" stop-color="#185e2d"/>
              </linearGradient>
              <filter id="shadow0" x="-20%" y="-20%" width="140%" height="140%">
                <feDropShadow dx="0" dy="1" stdDeviation="2" flood-color="rgba(0,0,0,.5)"/>
              </filter>
              <clipPath id="circleClipIndex">
                <circle cx="0" cy="0" r="15" />
              </clipPath>
            </defs>
            <!-- Base + stripes -->
            <rect width="360" height="500" rx="10" fill="url(#gfg0)"/>
            <rect x="0" y="0" width="360" height="42" rx="10" fill="rgba(0,0,0,.07)"/>
            <rect x="0" y="84" width="360" height="42" fill="rgba(0,0,0,.07)"/>
            <rect x="0" y="168" width="360" height="42" fill="rgba(0,0,0,.07)"/>
            <rect x="0" y="252" width="360" height="42" fill="rgba(0,0,0,.07)"/>
            <rect x="0" y="336" width="360" height="42" fill="rgba(0,0,0,.07)"/>
            <rect x="0" y="420" width="360" height="42" fill="rgba(0,0,0,.07)"/>
            <!-- Pitch lines -->
            <rect x="22" y="20" width="316" height="460" rx="4" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="1.5"/>
            <line x1="22" y1="250" x2="338" y2="250" stroke="rgba(255,255,255,.5)" stroke-width="1.2"/>
            <circle cx="180" cy="250" r="42" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="1.2"/>
            <circle cx="180" cy="250" r="3.5" fill="rgba(255,255,255,.7)"/>
            <!-- Top penalty box -->
            <rect x="98" y="20" width="164" height="68" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="1.2"/>
            <rect x="128" y="20" width="104" height="30" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="1.2"/>
            <circle cx="180" cy="82" r="3" fill="rgba(255,255,255,.6)"/>
            <path d="M 132 90 A 40 40 0 0 1 228 90" fill="none" stroke="rgba(255,255,255,.38)" stroke-width="1.1"/>
            <!-- Bottom penalty box -->
            <rect x="98" y="412" width="164" height="68" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="1.2"/>
            <rect x="128" y="450" width="104" height="30" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="1.2"/>
            <circle cx="180" cy="418" r="3" fill="rgba(255,255,255,.6)"/>
            <path d="M 132 410 A 40 40 0 0 0 228 410" fill="none" stroke="rgba(255,255,255,.38)" stroke-width="1.1"/>
            <!-- Corner arcs -->
            <path d="M 22 30 A 8 8 0 0 1 30 20" fill="none" stroke="rgba(255,255,255,.35)" stroke-width="1"/>
            <path d="M 338 30 A 8 8 0 0 0 330 20" fill="none" stroke="rgba(255,255,255,.35)" stroke-width="1"/>
            <path d="M 22 470 A 8 8 0 0 0 30 480" fill="none" stroke="rgba(255,255,255,.35)" stroke-width="1"/>
            <path d="M 338 470 A 8 8 0 0 1 330 480" fill="none" stroke="rgba(255,255,255,.35)" stroke-width="1"/>
            <!-- Formation badge -->
            <rect x="146" y="7" width="68" height="14" rx="4" fill="rgba(249,115,22,.9)"/>
            <text x="180" y="17.5" text-anchor="middle" font-size="8" font-weight="700" fill="white" font-family="Barlow,sans-serif" letter-spacing=".5"><?= e($homeFormation); ?></text>

            <!-- Dynamically Render Home Starters on Pitch -->
            <?php foreach ($homePlayers as $p): 
                $cx = $p['x'];
                $cy = $p['y'];
                $color = mapPositionTypeColor($p['posType']);
            ?>
                <g filter="url(#shadow0)" transform="translate(<?= $cx; ?>, <?= $cy; ?>)">
                    <?php if ($p['img']): ?>
                        <!-- Dynamic photo with clip mask -->
                        <image href="<?= e($p['img']); ?>" x="-15" y="-15" width="30" height="30" clip-path="url(#circleClipIndex)" />
                        <circle cx="0" cy="0" r="15" fill="none" stroke="white" stroke-width="1.8" />
                    <?php else: ?>
                        <!-- Initials fallback -->
                        <circle cx="0" cy="0" r="15" fill="<?= $color; ?>" stroke="white" stroke-width="1.8" />
                        <text x="0" y="2.5" text-anchor="middle" font-size="6.2" font-weight="700" fill="white" font-family="Barlow,sans-serif"><?= e($p['short']); ?></text>
                    <?php endif; ?>
                    
                    <!-- Jersey number override badge -->
                    <rect x="-12" y="17" width="24" height="9" rx="2.5" fill="rgba(0,0,0,.5)" />
                    <text x="0" y="24" text-anchor="middle" font-size="6.5" font-weight="700" fill="white" font-family="Barlow,sans-serif">#<?= $p['num']; ?></text>
                    
                    <!-- Player last name label outside photo circle for legibility -->
                    <text x="0" y="-18" text-anchor="middle" font-size="6.5" font-weight="800" fill="white" font-family="Barlow,sans-serif" style="text-shadow: 0 1px 2px rgba(0,0,0,0.85);"><?= e(strtoupper($p['short'])); ?></text>
                </g>
            <?php endforeach; ?>

            <!-- Legend -->
            <rect x="22" y="484" width="316" height="14" rx="3" fill="rgba(0,0,0,.25)"/>
            <circle cx="35" cy="491" r="5" fill="#F97316"/><text x="43" y="495" font-size="7" fill="rgba(255,255,255,.6)" font-family="sans-serif" font-weight="600">GK</text>
            <circle cx="72" cy="491" r="5" fill="#1E3A8A"/><text x="80" y="495" font-size="7" fill="rgba(255,255,255,.6)" font-family="sans-serif" font-weight="600">DEF</text>
            <circle cx="112" cy="491" r="5" fill="#15803d"/><text x="120" y="495" font-size="7" fill="rgba(255,255,255,.6)" font-family="sans-serif" font-weight="600">MID</text>
            <circle cx="151" cy="491" r="5" fill="#dc2626"/><text x="159" y="495" font-size="7" fill="rgba(255,255,255,.6)" font-family="sans-serif" font-weight="600">FWD</text>
          </svg>
        </div>

        <!-- Police FC — 4-4-2 -->
        <div class="pitch-panel hidden" id="panel-1">
          <svg viewBox="0 0 360 500" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:auto">
            <defs>
              <linearGradient id="gfg1" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#185e2d"/>
                <stop offset="25%" stop-color="#1d6b34"/>
                <stop offset="50%" stop-color="#196030"/>
                <stop offset="75%" stop-color="#1d6b34"/>
                <stop offset="100%" stop-color="#185e2d"/>
              </linearGradient>
              <filter id="shadow1" x="-20%" y="-20%" width="140%" height="140%">
                <feDropShadow dx="0" dy="1" stdDeviation="2" flood-color="rgba(0,0,0,.5)"/>
              </filter>
            </defs>
            <rect width="360" height="500" rx="10" fill="url(#gfg1)"/>
            <rect x="0" y="0" width="360" height="42" rx="10" fill="rgba(0,0,0,.07)"/>
            <rect x="0" y="84" width="360" height="42" fill="rgba(0,0,0,.07)"/>
            <rect x="0" y="168" width="360" height="42" fill="rgba(0,0,0,.07)"/>
            <rect x="0" y="252" width="360" height="42" fill="rgba(0,0,0,.07)"/>
            <rect x="0" y="336" width="360" height="42" fill="rgba(0,0,0,.07)"/>
            <rect x="0" y="420" width="360" height="42" fill="rgba(0,0,0,.07)"/>
            <rect x="22" y="20" width="316" height="460" rx="4" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="1.5"/>
            <line x1="22" y1="250" x2="338" y2="250" stroke="rgba(255,255,255,.5)" stroke-width="1.2"/>
            <circle cx="180" cy="250" r="42" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="1.2"/>
            <circle cx="180" cy="250" r="3.5" fill="rgba(255,255,255,.7)"/>
            <rect x="98" y="20" width="164" height="68" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="1.2"/>
            <rect x="128" y="20" width="104" height="30" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="1.2"/>
            <circle cx="180" cy="82" r="3" fill="rgba(255,255,255,.6)"/>
            <path d="M 132 90 A 40 40 0 0 1 228 90" fill="none" stroke="rgba(255,255,255,.38)" stroke-width="1.1"/>
            <rect x="98" y="412" width="164" height="68" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="1.2"/>
            <rect x="128" y="450" width="104" height="30" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="1.2"/>
            <circle cx="180" cy="418" r="3" fill="rgba(255,255,255,.6)"/>
            <path d="M 132 410 A 40 40 0 0 0 228 410" fill="none" stroke="rgba(255,255,255,.38)" stroke-width="1.1"/>
            <path d="M 22 30 A 8 8 0 0 1 30 20" fill="none" stroke="rgba(255,255,255,.35)" stroke-width="1"/>
            <path d="M 338 30 A 8 8 0 0 0 330 20" fill="none" stroke="rgba(255,255,255,.35)" stroke-width="1"/>
            <path d="M 22 470 A 8 8 0 0 0 30 480" fill="none" stroke="rgba(255,255,255,.35)" stroke-width="1"/>
            <path d="M 338 470 A 8 8 0 0 1 330 480" fill="none" stroke="rgba(255,255,255,.35)" stroke-width="1"/>
            <!-- Formation badge -->
            <rect x="146" y="7" width="68" height="14" rx="4" fill="rgba(124,58,237,.9)"/>
            <text x="180" y="17.5" text-anchor="middle" font-size="8" font-weight="700" fill="white" font-family="Barlow,sans-serif" letter-spacing=".5"><?= e($awayFormation); ?></text>

            <!-- Dynamically Render Away Starters on Pitch -->
            <?php foreach ($awayPlayers as $p): 
                $cx = $p['x'];
                $cy = $p['y'];
                $color = mapPositionTypeColor($p['posType']);
            ?>
                <g filter="url(#shadow1)" transform="translate(<?= $cx; ?>, <?= $cy; ?>)">
                    <?php if ($p['img']): ?>
                        <!-- Dynamic photo with clip mask -->
                        <image href="<?= e($p['img']); ?>" x="-15" y="-15" width="30" height="30" clip-path="url(#circleClipIndex)" />
                        <circle cx="0" cy="0" r="15" fill="none" stroke="white" stroke-width="1.8" />
                    <?php else: ?>
                        <!-- Initials fallback -->
                        <circle cx="0" cy="0" r="15" fill="<?= $color; ?>" stroke="white" stroke-width="1.8" />
                        <text x="0" y="2.5" text-anchor="middle" font-size="6.2" font-weight="700" fill="white" font-family="Barlow,sans-serif"><?= e($p['short']); ?></text>
                    <?php endif; ?>
                    
                    <!-- Jersey number override badge -->
                    <rect x="-12" y="17" width="24" height="9" rx="2.5" fill="rgba(0,0,0,.5)" />
                    <text x="0" y="24" text-anchor="middle" font-size="6.5" font-weight="700" fill="white" font-family="Barlow,sans-serif">#<?= $p['num']; ?></text>
                    
                    <!-- Player last name label outside photo circle for legibility -->
                    <text x="0" y="-18" text-anchor="middle" font-size="6.5" font-weight="800" fill="white" font-family="Barlow,sans-serif" style="text-shadow: 0 1px 2px rgba(0,0,0,0.85);"><?= e(strtoupper($p['short'])); ?></text>
                </g>
            <?php endforeach; ?>

            <!-- Legend -->
            <rect x="22" y="484" width="316" height="14" rx="3" fill="rgba(0,0,0,.25)"/>
            <circle cx="35" cy="491" r="5" fill="#F97316"/><text x="43" y="495" font-size="7" fill="rgba(255,255,255,.6)" font-family="sans-serif" font-weight="600">GK</text>
            <circle cx="72" cy="491" r="5" fill="#1E3A8A"/><text x="80" y="495" font-size="7" fill="rgba(255,255,255,.6)" font-family="sans-serif" font-weight="600">DEF</text>
            <circle cx="112" cy="491" r="5" fill="#15803d"/><text x="120" y="495" font-size="7" fill="rgba(255,255,255,.6)" font-family="sans-serif" font-weight="600">MID</text>
            <circle cx="151" cy="491" r="5" fill="#dc2626"/><text x="159" y="495" font-size="7" fill="rgba(255,255,255,.6)" font-family="sans-serif" font-weight="600">FWD</text>
          </svg>
        </div>

      </div>

      <!-- SIDEBAR -->
      <div class="pitch-info">
        <!-- Team header -->
        <div class="pi-team-hd" id="pi-team-hd">
          <div class="pi-team-logo"><img id="pi-team-img" src="https://images.unsplash.com/photo-1587329310690-91114ac008f0?w=50&q=80" alt=""/></div>
          <span class="pi-team-label" id="pi-team-name">APR FC</span>
          <span class="pi-formation-badge" id="pi-formation-badge">4-3-3</span>
        </div>
        <!-- Legend -->
        <div class="pi-legend">
          <div class="pi-legend-item"><div class="pi-legend-dot" style="background:#F97316"></div>GK</div>
          <div class="pi-legend-item"><div class="pi-legend-dot" style="background:#1E3A8A"></div>DEF</div>
          <div class="pi-legend-item"><div class="pi-legend-dot" style="background:#15803d"></div>MID</div>
          <div class="pi-legend-item"><div class="pi-legend-dot" style="background:#dc2626"></div>FWD</div>
        </div>
        <!-- Players list -->
        <div class="pi-pl" id="pi-players-list"></div>
      </div>
    </div>
  </div>
</section>

<div class="divider"></div>

<!-- TOP PLAYERS -->
<section style="background:var(--off)">
  <div class="sec-hd">
    <div><div class="sec-t">Top Rated <span>Players</span></div><div class="sec-sub">Based on match performance ratings</div></div>
    <a class="sec-lnk" href="players.php" style="text-decoration:none;">Full rankings →</a>
  </div>
  <div style="display:flex;gap:6px;margin-bottom:12px;flex-wrap:wrap">
    <button class="btn-p" style="padding:4px 11px;font-size:10px" id="filter-all">All</button>
    <button class="btn-s" style="padding:4px 11px;font-size:10px;color:var(--text);border-color:var(--gray-l)" id="filter-gk">Goalkeepers</button>
    <button class="btn-s" style="padding:4px 11px;font-size:10px;color:var(--text);border-color:var(--gray-l)" id="filter-def">Defenders</button>
    <button class="btn-s" style="padding:4px 11px;font-size:10px;color:var(--text);border-color:var(--gray-l)" id="filter-mid">Midfielders</button>
    <button class="btn-s" style="padding:4px 11px;font-size:10px;color:var(--text);border-color:var(--gray-l)" id="filter-fwd">Forwards</button>
  </div>
  <div class="players-grid" id="players-grid">
    <?php if (empty($topPlayers)): ?>
      <div class="empty-state" style="color:var(--text3);text-align:center;padding:20px;">No ranked players.</div>
    <?php else: ?>
      <?php foreach ($topPlayers as $pl): 
        $posType = mapPositionType($pl['position']);
        $posAbbrev = mapPositionAbbrev($pl['position']);
        $pClass = $posType === 'gk' ? 'gk-b' : ($posType === 'def' ? 'def-b' : ($posType === 'mid' ? 'mid-b' : 'fwd-b'));
        $pColor = $posType === 'gk' ? '#F97316' : ($posType === 'def' ? '#1E3A8A' : ($posType === 'mid' ? '#15803d' : '#dc2626'));
      ?>
      <div class="player-card" data-pos="<?= $posType; ?>" onclick="window.location='players.php'">
        <div class="pc-img">
          <img src="<?= e($pl['photo_pl'] ?: 'https://images.unsplash.com/photo-1627483297886-49710ae1fc22?w=300&q=80'); ?>" alt=""/>
          <div class="pc-img-ov" style="background:linear-gradient(to top,<?= $pColor; ?>bb,transparent)"></div>
        </div>
        <div class="pc-pos-bar" style="background:<?= $pColor; ?>"></div>
        <div class="pc-body">
          <div class="pc-avatar-wrap">
            <img src="<?= e($pl['photo_pl'] ?: 'https://images.unsplash.com/photo-1627483297886-49710ae1fc22?w=100&q=80'); ?>" alt="Avatar"/>
          </div>
          <div class="pc-name"><?= e($pl['first_name'] . ' ' . substr($pl['last_name'], 0, 1) . '.'); ?></div>
          <div class="pc-team"><?= e($pl['team_name']); ?></div>
          <div class="pc-rating"><?= (int) $pl['average_rating']; ?><span>/100</span></div>
          <span class="pc-pos-badge <?= $pClass; ?>"><?= $posAbbrev; ?></span>
          <div class="pc-bar-wrap">
            <div class="pc-bar-fill" style="width:<?= (int) $pl['average_rating']; ?>%;background:<?= $pColor; ?>"></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<div class="divider"></div>

<!-- GALLERY STRIP -->
<section style="padding:16px 20px">
  <div class="gallery-strip">
    <div class="g1"><img src="https://images.unsplash.com/photo-1574629810360-7efbbe195018?w=700&q=80" alt="Stadium"/><div class="g-ov"></div><div class="g-label">Amahoro Stadium</div></div>
    <div class="g2"><img src="https://images.unsplash.com/photo-1519766304817-4f37bda74a26?w=400&q=80" alt="Action"/><div class="g-ov"></div><div class="g-label">Match Action</div></div>
    <div class="g3"><img src="https://images.unsplash.com/photo-1560272564-c83b66b1ad12?w=400&q=80" alt="Trophy"/><div class="g-ov"></div><div class="g-label">Season Highlights</div></div>
  </div>
</section>

<div class="divider"></div>

<!-- NEWS -->
<section>
  <div class="sec-hd">
    <div><div class="sec-t">Federation <span>News</span></div><div class="sec-sub">Official announcements & updates</div></div>
    <a class="sec-lnk" href="login.php" style="text-decoration:none;">All news →</a>
  </div>
  <div class="news-grid">
    <?php if ($mainNews): ?>
    <div class="news-main" onclick="window.location='login.php'">
      <div class="news-main-img">
        <img src="<?= e($mainNews['cover_image'] ?: 'https://images.unsplash.com/photo-1522778119026-d647f0596c20?w=700&q=80'); ?>" alt="News"/>
        <div class="news-img-ov"></div>
        <div class="news-img-content">
          <span class="news-cat">Official</span>
          <div class="news-title"><?= e($mainNews['title']); ?></div>
        </div>
      </div>
      <div class="news-body-pad"><div class="news-meta"><?= date('M d, Y', strtotime($mainNews['published_at'] ?: $mainNews['created_at'])); ?> &nbsp;•&nbsp; Rwanda Football Federation</div></div>
    </div>
    <?php endif; ?>
    
    <div class="news-list">
      <?php if (empty($subNews)): ?>
        <div class="empty-state" style="color:var(--text3);text-align:center;padding:20px;">No secondary news.</div>
      <?php else: ?>
        <?php foreach ($subNews as $n): ?>
        <div class="news-item" onclick="window.location='login.php'">
          <div class="ni-img"><img src="<?= e($n['cover_image'] ?: 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?w=150&q=70'); ?>" alt=""/></div>
          <div class="ni-body">
            <div class="ni-cat">Update</div>
            <div class="ni-title"><?= e($n['title']); ?></div>
            <div class="ni-meta"><?= date('M d, Y', strtotime($n['published_at'] ?: $n['created_at'])); ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<div class="divider"></div>

<!-- SEARCH -->
<section style="background:var(--off);padding:20px">
  <div class="search-sec">
    <div class="ss-bg"><img src="https://images.unsplash.com/photo-1574629810360-7efbbe195018?w=1200&q=60" alt=""/></div>
    <div class="ss-ov"></div>
    <div class="ss-content">
      <div class="ss-title">Find <span>Players & Teams</span></div>
      <p class="ss-sub">Search across all registered teams, players and match statistics</p>
      <div class="search-box">
        <input class="search-input" type="text" placeholder="Search player, team, or match..." id="main-search"/>
        <button class="search-btn" onclick="triggerSearch()"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>Search</button>
      </div>
      <div class="search-tags">
        <span class="s-tag" onclick="window.location='players.php'">Best Goalkeepers</span>
        <span class="s-tag" onclick="window.location='players.php'">Top Defenders</span>
        <span class="s-tag" onclick="window.location='players.php'">Best Midfielders</span>
        <span class="s-tag" onclick="window.location='players.php'">Top Strikers</span>
        <span class="s-tag" onclick="window.location='teams.php'">Highest Rated Teams</span>
        <span class="s-tag" onclick="window.location='matches.php'">Live Matches</span>
      </div>
    </div>
  </div>
</section>

<script>
// Dynamic Teams injection from the active database rosters for the interactive pitch
const TEAMS = [
  {
    name: "<?= e($homeName); ?>",
    formation: "<?= e($homeFormation); ?>",
    color: "#1E3A8A",
    img: "<?= e($homeLogo ?: 'https://images.unsplash.com/photo-1587329310690-91114ac008f0?w=50&q=80'); ?>",
    players: <?= json_encode($homePlayers); ?>,
    bench: <?= json_encode($homeBench); ?>
  },
  {
    name: "<?= e($awayName); ?>",
    formation: "<?= e($awayFormation); ?>",
    color: "#7c3aed",
    img: "<?= e($awayLogo ?: 'https://images.unsplash.com/photo-1606925797300-0b35e9d1794e?w=50&q=80'); ?>",
    players: <?= json_encode($awayPlayers); ?>,
    bench: <?= json_encode($awayBench); ?>
  }
];

const POSCOLORS = {gk:"#F97316",def:"#1E3A8A",mid:"#15803d",fwd:"#dc2626"};
let activeTeam = 0;

function renderSidebar(teamIdx) {
  const team = TEAMS[teamIdx];
  if (!team) return;
  document.getElementById('pi-team-img').src = team.img;
  document.getElementById('pi-team-name').textContent = team.name;
  document.getElementById('pi-formation-badge').textContent = team.formation;
  const title = document.getElementById('ps-match-title');
  if(title) {
    title.innerHTML = teamIdx===0 ? '<?= e($homeName); ?> <span>vs <?= e($awayName); ?></span>' : '<?= e($awayName); ?> <span>vs <?= e($homeName); ?></span>';
  }
  const label = document.getElementById('ps-formation-label');
  if(label) {
    label.textContent = 'Formation: ' + team.formation;
  }
  const list = document.getElementById('pi-players-list');
  if(!list) return;
  list.innerHTML = '';
  team.players.forEach(p => {
    const color = POSCOLORS[p.posType]||'#F97316';
    const div = document.createElement('div'); div.className='pi-p';
    div.innerHTML = `<div class="pi-n" style="background:${color}">${p.num}</div><span class="pi-pn">${p.name}</span><span class="pi-ps">${p.pos}</span>`;
    list.appendChild(div);
  });
  list.insertAdjacentHTML('beforeend','<div class="bench-div"></div><div class="bench-lbl">Bench</div>');
  team.bench.forEach(p => {
    const div = document.createElement('div'); div.className='pi-p';
    div.innerHTML = `<div class="pi-n" style="background:rgba(255,255,255,.15)">${p.num}</div><span class="pi-pn">${p.name}</span><span class="pi-ps">${p.pos}</span>`;
    list.appendChild(div);
  });
}

function switchTeam(idx) {
  activeTeam = idx;
  document.querySelectorAll('.ps-tab').forEach((t,i) => t.classList.toggle('active', i===idx));
  document.querySelectorAll('.pitch-panel').forEach((p,i) => p.classList.toggle('hidden', i!==idx));
  renderSidebar(idx);
}

document.querySelectorAll('.ps-tab').forEach(tab => {
  tab.addEventListener('click', () => switchTeam(parseInt(tab.dataset.team)));
});

document.querySelectorAll('[id^="filter-"]').forEach(btn => {
  btn.addEventListener('click', () => {
    const f = btn.id.replace('filter-','');
    document.querySelectorAll('[id^="filter-"]').forEach(b => {
      b.className = f==='all'&&b.id==='filter-all' ? 'btn-p' : 'btn-s';
      b.style.cssText = b.className==='btn-p' ? 'padding:4px 11px;font-size:10px' : 'padding:4px 11px;font-size:10px;color:var(--text);border-color:var(--gray-l)';
    });
    btn.className = 'btn-p'; btn.style.cssText = 'padding:4px 11px;font-size:10px';
    document.querySelectorAll('.player-card').forEach(c => {
      c.style.display = (f==='all'||c.dataset.pos===f) ? '' : 'none';
    });
  });
});

function triggerSearch() {
    const q = document.getElementById('main-search').value.toLowerCase().trim();
    if (q) {
        window.location.href = 'players.php?q=' + encodeURIComponent(q);
    }
}

switchTeam(0);
</script>

<?php
require_once __DIR__ . '/public_footer.php';
?>
