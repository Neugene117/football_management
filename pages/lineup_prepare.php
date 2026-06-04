<?php
$currentUser = current_user();
if (!$currentUser || !in_array($currentUser['user_type'], ['club', 'team', 'coach'], true)) {
    set_flash('danger', 'Unauthorized access.');
    redirect_to('index.php?page=lineups');
}

$myTeamId = (int) ($currentUser['entity_id'] ?? 0);
$matchId = (int) ($_GET['match_id'] ?? 0);

if ($matchId <= 0) {
    set_flash('danger', 'Invalid match request.');
    redirect_to('index.php?page=lineups');
}

// Fetch match details and ensure manager's team is playing
$match = db_fetch_one("
    SELECT m.*, 
           ht.name home_team, 
           at.name away_team, 
           s.name stadium_name, 
           s.city stadium_city,
           c.name competition_name 
    FROM matches m 
    LEFT JOIN teams ht ON ht.id = m.home_team_id 
    LEFT JOIN teams at ON at.id = m.away_team_id 
    LEFT JOIN stadiums s ON s.id = m.stadium_id 
    LEFT JOIN competitions c ON c.id = m.competition_id 
    WHERE m.id = ?
", 'i', [$matchId]);

if (!$match) {
    set_flash('danger', 'Scheduled match not found.');
    redirect_to('index.php?page=lineups');
}

if ($myTeamId !== (int) $match['home_team_id'] && $myTeamId !== (int) $match['away_team_id']) {
    set_flash('danger', 'You can only prepare lineups for matches scheduled for your club.');
    redirect_to('index.php?page=lineups');
}

// Enforce Match Status constraint: must be scheduled, lineup_approved, or in_progress
if (!in_array($match['status'], ['scheduled', 'lineup_approved', 'in_progress'], true)) {
    set_flash('danger', 'Match preparation is only accessible when the match status is scheduled, lineup_approved or in_progress.');
    redirect_to('index.php?page=lineups');
}

// Fetch active players of the manager's team
$players = db_fetch_all("
    SELECT id, first_name, last_name, jersey_number, position, photo_pl 
    FROM players 
    WHERE team_id = ? AND status = 'active' 
    ORDER BY first_name ASC, last_name ASC
", 'i', [$myTeamId]);

// Fetch active tactical formations
$formations = db_fetch_all("
    SELECT * 
    FROM formations 
    WHERE is_active = 1 
    ORDER BY display_name ASC
");

// Check for existing lineup to pre-populate form
$existingLineup = db_fetch_one("
    SELECT * 
    FROM match_lineups 
    WHERE match_id = ? AND team_id = ?
", 'ii', [$matchId, $myTeamId]);

$existingStarters = [];
$existingSubs = [];
$existingFormationId = 0;

if ($existingLineup) {
    $existingFormationId = (int) $existingLineup['formation_id'];
    $lineupPlayers = db_fetch_all("
        SELECT player_id, is_starter, position_slot 
        FROM lineup_players 
        WHERE lineup_id = ?
    ", 'i', [$existingLineup['id']]);
    
    foreach ($lineupPlayers as $lp) {
        if ((int) $lp['is_starter'] === 1) {
            $existingStarters[$lp['position_slot']] = (int) $lp['player_id'];
        } else {
            $existingSubs[] = (int) $lp['player_id'];
        }
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Security verification failed.');
        redirect_to("index.php?page=lineup_prepare&match_id={$matchId}");
    }

    $formationId = (int) ($_POST['formation_id'] ?? 0);
    $startersInput = $_POST['starters'] ?? []; // Array of slot_name => player_id
    $substitutesInput = $_POST['substitutes'] ?? []; // Array of player_ids

    // Server-side validation
    $errors = [];
    if ($formationId <= 0) {
        $errors[] = 'Please select a tactical formation.';
    }

    $validStarters = [];
    foreach ($startersInput as $slot => $pid) {
        $pid = (int) $pid;
        if ($pid > 0) {
            $validStarters[$slot] = $pid;
        }
    }

    if (count($validStarters) !== 11) {
        $errors[] = 'You must select exactly 11 starting players.';
    }

    $validSubs = [];
    foreach ($substitutesInput as $pid) {
        $pid = (int) $pid;
        if ($pid > 0) {
            $validSubs[] = $pid;
        }
    }

    $subCount = count($validSubs);
    if ($subCount < 7 || $subCount > 12) {
        $errors[] = 'You must select between 7 and 12 substitutes.';
    }

    $totalSquad = count($validStarters) + $subCount;
    if ($totalSquad < 18 || $totalSquad > 23) {
        $errors[] = 'Total squad size must be between 18 and 23 players.';
    }

    // Check for duplicates for this match only
    $allSelected = array_merge(array_values($validStarters), $validSubs);
    if (count($allSelected) !== count(array_unique($allSelected))) {
        $errors[] = 'A player cannot be selected more than once.';
    }

    if (!empty($errors)) {
        set_flash('danger', implode('<br>', $errors));
        redirect_to("index.php?page=lineup_prepare&match_id={$matchId}");
    }

    // Proceed to Save Lineup (Upsert)
    $dbError = false;
    if ($existingLineup) {
        $lineupId = (int) $existingLineup['id'];
        $ok = db_execute("
            UPDATE match_lineups 
            SET formation_id = ?, status = 'approved', submitted_at = NOW(), submitted_by = ?, approved_by = ?, approved_at = NOW() 
            WHERE id = ?
        ", 'iiii', [$formationId, (int) $currentUser['id'], (int) $currentUser['id'], $lineupId]);
        if (!$ok) $dbError = true;
    } else {
        $ok = db_execute("
            INSERT INTO match_lineups (match_id, team_id, formation_id, status, submitted_at, submitted_by, approved_by, approved_at) 
            VALUES (?, ?, ?, 'approved', NOW(), ?, ?, NOW())
        ", 'iiiiii', [$matchId, $myTeamId, $formationId, (int) $currentUser['id'], (int) $currentUser['id']]);
        if ($ok) {
            $lineupId = db_last_id();
        } else {
            $dbError = true;
        }
    }

    if (!$dbError && $lineupId > 0) {
        // Clear previous player entries
        db_execute("DELETE FROM lineup_players WHERE lineup_id = ?", 'i', [$lineupId]);

        // Insert Starters
        // Fetch selected formation info to load coordinate layout mapping
        $chosenFormation = db_fetch_one("SELECT * FROM formations WHERE id = ?", 'i', [$formationId]);
        $defenders = (int) ($chosenFormation['defenders'] ?? 4);
        $midfielders = (int) ($chosenFormation['midfielders'] ?? 3);
        $forwards = (int) ($chosenFormation['forwards'] ?? 3);

        // Helper function in PHP to compute coordinates matching JS logic
        function getPHPCoordinates($slotName, $def, $mid, $fwd) {
            // GK
            if ($slotName === 'GK') {
                return ['x' => 180, 'y' => 455];
            }
            
            // Parse group and index from slot name e.g. DEF_0, MID_2
            $parts = explode('_', $slotName);
            if (count($parts) !== 2) {
                return ['x' => 180, 'y' => 250]; // center field safety
            }
            $group = $parts[0];
            $i = (int) $parts[1];

            if ($group === 'DEF') {
                $x = $def === 1 ? 180 : 50 + ($i * (260 / ($def - 1)));
                $y = 375 - ($def > 3 && ($i === 0 || $i === $def - 1) ? 10 : 0);
                return ['x' => round($x, 1), 'y' => round($y, 1)];
            }
            
            if ($group === 'MID') {
                $x = $mid === 1 ? 180 : 60 + ($i * (240 / ($mid - 1)));
                $y = 280 - ($mid === 3 && $i === 1 ? -15 : ($mid === 5 && $i === 2 ? 15 : 0));
                return ['x' => round($x, 1), 'y' => round($y, 1)];
            }

            if ($group === 'FWD') {
                $x = $fwd === 1 ? 180 : 70 + ($i * (220 / ($fwd - 1)));
                $y = 160 + ($fwd === 3 && ($i === 0 || $i === $fwd - 1) ? 10 : 0);
                return ['x' => round($x, 1), 'y' => round($y, 1)];
            }

            return ['x' => 180, 'y' => 250];
        }

        foreach ($validStarters as $slot => $pid) {
            $coords = getPHPCoordinates($slot, $defenders, $midfielders, $forwards);
            db_execute("
                INSERT INTO lineup_players (lineup_id, player_id, is_starter, position_slot, field_x, field_y) 
                VALUES (?, ?, 1, ?, ?, ?)
            ", 'iisdd', [$lineupId, $pid, $slot, $coords['x'], $coords['y']]);
        }

        // Insert Substitutes
        foreach ($validSubs as $pid) {
            db_execute("
                INSERT INTO lineup_players (lineup_id, player_id, is_starter) 
                VALUES (?, ?, 0)
            ", 'ii', [$lineupId, $pid]);
        }

        // Create or update Approval request
        $existingApproval = db_fetch_one("
            SELECT id FROM approvals 
            WHERE item_type = 'lineup' AND item_id = ?
        ", 'i', [$lineupId]);

        if ($existingApproval) {
            db_execute("
                UPDATE approvals 
                SET status = 'approved', submitted_by = ?, submitted_at = NOW(), approved_by = ?, approved_at = NOW(), rejection_notes = NULL 
                WHERE id = ?
            ", 'iii', [(int) $currentUser['id'], (int) $currentUser['id'], (int) $existingApproval['id']]);
        } else {
            db_execute("
                INSERT INTO approvals (item_type, item_id, submitted_by, status, approved_by, approved_at) 
                VALUES ('lineup', ?, ?, 'approved', ?, NOW())", 'iiii', [$lineupId, (int) $currentUser['id'], (int) $currentUser['id']]);
        }

        // Log the activity
        log_action('lineup_submitted', 'lineups', 'match_lineups', $lineupId);
        set_flash('success', 'Roster and tactical lineup submitted and approved successfully! The dashboard and main page have been updated.');
        redirect_to('index.php?page=lineups');
    } else {
        set_flash('danger', 'Database transaction failed. Please try again.');
        redirect_to("index.php?page=lineup_prepare&match_id={$matchId}");
    }
}

// Convert players to JSON helper safely (retaining photo path)
$playersJson = json_encode(array_map(function($p) {
    return [
        'id' => (int) $p['id'],
        'name' => e($p['first_name'] . ' ' . $p['last_name']),
        'lastName' => e($p['last_name']),
        'num' => (int) $p['jersey_number'],
        'pos' => strtolower($p['position']),
        'photo' => $p['photo_pl'] ? app_url($p['photo_pl']) : ''
    ];
}, $players));

$formationsJson = json_encode(array_map(function($f) {
    return [
        'id' => (int) $f['id'],
        'name' => e($f['name']),
        'def' => (int) $f['defenders'],
        'mid' => (int) $f['midfielders'],
        'fwd' => (int) $f['forwards']
    ];
}, $formations));
?>

<style>
  /* Extra styling for lineup prepare page - premium aesthetics matching redesign */
  @import url('https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800;900&display=swap');
  
  .wb-container {
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      gap: 24px;
      margin-top: 15px;
      font-family: 'Barlow', sans-serif;
  }
  @media (max-width: 992px) {
      .wb-container {
          grid-template-columns: 1fr;
      }
  }

  .match-banner {
      background: linear-gradient(160deg, #1E3A8A 0%, #0F1F4B 100%);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: var(--rl);
      padding: 20px;
      color: #fff;
      margin-bottom: 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
  }
  .banner-details h2 {
      font-size: 20px;
      font-weight: 800;
      margin-bottom: 5px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
  }
  .banner-details h2 span {
      color: var(--org);
  }
  .banner-meta {
      display: flex;
      gap: 15px;
      font-size: 13px;
      color: #94A3B8;
  }
  .banner-meta span {
      display: flex;
      align-items: center;
      gap: 5px;
  }
  .banner-meta span i {
      color: var(--org);
  }

  /* Pitch viewbox wrapper */
  .pitch-wrapper {
      background: var(--navy-800);
      border-radius: var(--rl);
      border: 1px solid rgba(255, 255, 255, 0.06);
      padding: 16px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
  }

  /* Right column panels */
  .tactics-card {
      background: #fff;
      border-radius: var(--rl);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
      border: 1px solid var(--gray-l);
      display: flex;
      flex-direction: column;
      gap: 20px;
      padding: 20px;
  }
  .section-title {
      font-size: 16px;
      font-weight: 750;
      color: #0F1F4B;
      border-bottom: 2px solid var(--gray-ll);
      padding-bottom: 8px;
      margin-bottom: 5px;
      display: flex;
      justify-content: space-between;
      align-items: center;
  }
  
  .form-group {
      display: flex;
      flex-direction: column;
      gap: 6px;
  }
  .form-group label {
      font-size: 12px;
      font-weight: 700;
      color: var(--text2);
      text-transform: uppercase;
      letter-spacing: 0.5px;
  }
  .form-group select {
      background: var(--off);
      border: 1px solid var(--gray-l);
      border-radius: var(--r);
      padding: 10px;
      font-weight: 500;
      color: var(--text);
      transition: border 0.2s, box-shadow 0.2s;
  }
  .form-group select:focus {
      border-color: var(--org);
      box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15);
      outline: none;
  }

  .starters-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
  }
  @media (max-width: 480px) {
      .starters-grid {
          grid-template-columns: 1fr;
      }
  }

  .subs-wrap {
      max-height: 240px;
      overflow-y: auto;
      border: 1px solid var(--gray-l);
      border-radius: var(--r);
      padding: 10px;
      background: var(--off);
  }
  .sub-item-row {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 6px 8px;
      border-bottom: 1px solid var(--gray-ll);
      font-size: 13px;
      cursor: pointer;
      transition: background 0.15s;
  }
  .sub-item-row:last-child {
      border-bottom: none;
  }
  .sub-item-row:hover {
      background: #F1F5F9;
  }
  .sub-item-row input[type="checkbox"] {
      cursor: pointer;
      accent-color: var(--org);
      width: 16px;
      height: 16px;
  }

  /* Live verification checklist styles */
  .validator-box {
      background: #F8FAFC;
      border-radius: var(--r);
      border: 1px solid var(--gray-l);
      padding: 12px 16px;
  }
  .val-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      margin-bottom: 6px;
      font-weight: 600;
  }
  .val-item:last-child {
      margin-bottom: 0;
  }
  .val-item i {
      font-size: 15px;
  }
  .val-success {
      color: #15803D;
  }
  .val-error {
      color: #DC2626;
  }

  .legend-item {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 11px;
      font-weight: 650;
      color: rgba(255, 255, 255, 0.6);
  }

  /* Player select popup modal specific styling */
  .modal.active {
      display: flex !important;
      align-items: center;
      justify-content: center;
      background: rgba(15, 31, 75, 0.5) !important;
      backdrop-filter: blur(6px) !important;
  }
  .player-card-option {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 14px;
      border: 1px solid var(--gray-l);
      border-radius: var(--rm);
      background: #fff;
      cursor: pointer;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  }
  .player-card-option:hover:not(.disabled) {
      border-color: var(--org);
      transform: translateY(-1px);
      box-shadow: 0 4px 10px rgba(249, 115, 22, 0.12);
      background: var(--org-xl);
  }
  .player-card-option.disabled {
      background: var(--gray-ll);
      opacity: 0.55;
      cursor: not-allowed;
      border-color: var(--gray-l);
  }
  .player-card-option.selected {
      border-color: #15803D;
      background: rgba(21, 128, 61, 0.04);
  }
  .player-meta-left {
      display: flex;
      align-items: center;
      gap: 10px;
  }
  .player-meta-left img {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid var(--gray-l);
  }
  .player-meta-left .avatar-ph {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background: #edf2f8;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      font-weight: 700;
      color: #7b93b0;
      border: 2px solid var(--gray-l);
  }
  .player-name-wrap strong {
      display: block;
      font-size: 13.5px;
      color: var(--text);
  }
  .player-name-wrap span {
      font-size: 11.5px;
      color: var(--gray);
  }
  .player-status-badge {
      font-size: 10px;
      font-weight: 700;
      padding: 3px 6px;
      border-radius: 4px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
  }
  .badge-avail {
      background: rgba(21, 128, 61, 0.1);
      color: #15803D;
  }
  .badge-assigned {
      background: rgba(30, 58, 138, 0.1);
      color: #1E3A8A;
  }
  .badge-selected {
      background: rgba(249, 115, 22, 0.1);
      color: var(--org);
  }
</style>

<div class="match-banner">
    <div class="banner-details">
        <h2><?= e($match['home_team']); ?> <span>vs</span> <?= e($match['away_team']); ?></h2>
        <div class="banner-meta">
            <span><i class="fa-solid fa-trophy"></i> <?= e($match['competition_name']); ?></span>
            <span><i class="fa-solid fa-location-dot"></i> <?= e($match['stadium_name']); ?> (<?= e($match['stadium_city']); ?>)</span>
            <span><i class="fa-regular fa-calendar"></i> <?= e($match['match_date']); ?> <?= e($match['match_time'] ? date('H:i', strtotime($match['match_time'])) : ''); ?></span>
        </div>
    </div>
    <a href="index.php?page=lineups" class="btn btn-light btn-sm" style="display: flex; align-items: center; gap: 6px;">
        <i class="fa-solid fa-arrow-left"></i> Back to Lineups
    </a>
</div>

<form method="post" id="lineupPrepForm">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
  
  <div class="wb-container">
      
      <!-- LEFT COLUMN: SVG football pitch visualizer -->
      <div class="pitch-wrapper">
          <svg viewBox="0 0 360 500" xmlns="http://www.w3.org/2000/svg" style="width:100%; height:auto;" id="pitchSvg">
              <defs>
                  <linearGradient id="pitchGrad" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stop-color="#185e2d"/>
                      <stop offset="25%" stop-color="#1d6b34"/>
                      <stop offset="50%" stop-color="#196030"/>
                      <stop offset="75%" stop-color="#1d6b34"/>
                      <stop offset="100%" stop-color="#185e2d"/>
                  </linearGradient>
                  <filter id="nodeShadow" x="-20%" y="-20%" width="140%" height="140%">
                      <feDropShadow dx="0" dy="1" stdDeviation="2" flood-color="rgba(0,0,0,.5)"/>
                  </filter>
                  <!-- Circular clip path mask for rendering dynamic photos -->
                  <clipPath id="circleClip">
                      <circle cx="0" cy="0" r="15" />
                  </clipPath>
              </defs>
              
              <!-- Green grass field and stripes -->
              <rect width="360" height="500" rx="10" fill="url(#pitchGrad)"/>
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
              
              <!-- Penalty areas -->
              <rect x="98" y="20" width="164" height="68" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="1.2"/>
              <rect x="128" y="20" width="104" height="30" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="1.2"/>
              <circle cx="180" cy="82" r="3" fill="rgba(255,255,255,.6)"/>
              <path d="M 132 90 A 40 40 0 0 1 228 90" fill="none" stroke="rgba(255,255,255,.38)" stroke-width="1.1"/>
              
              <rect x="98" y="412" width="164" height="68" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="1.2"/>
              <rect x="128" y="450" width="104" height="30" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="1.2"/>
              <circle cx="180" cy="418" r="3" fill="rgba(255,255,255,.6)"/>
              <path d="M 132 410 A 40 40 0 0 0 228 410" fill="none" stroke="rgba(255,255,255,.38)" stroke-width="1.1"/>
              
              <!-- Corners -->
              <path d="M 22 30 A 8 8 0 0 1 30 20" fill="none" stroke="rgba(255,255,255,.35)" stroke-width="1"/>
              <path d="M 338 30 A 8 8 0 0 0 330 20" fill="none" stroke="rgba(255,255,255,.35)" stroke-width="1"/>
              <path d="M 22 470 A 8 8 0 0 0 30 480" fill="none" stroke="rgba(255,255,255,.35)" stroke-width="1"/>
              <path d="M 338 470 A 8 8 0 0 1 330 480" fill="none" stroke="rgba(255,255,255,.35)" stroke-width="1"/>
              
              <!-- Tactical formation badge -->
              <rect x="146" y="7" width="68" height="14" rx="4" fill="rgba(249,115,22,.9)"/>
              <text x="180" y="17.5" text-anchor="middle" font-size="8" font-weight="700" fill="white" font-family="Barlow,sans-serif" letter-spacing=".5" id="pitchFormationBadge">4-3-3</text>
              
              <!-- Container for dynamic player nodes -->
              <g id="pitchPlayersGroup"></g>
              
              <!-- Legend bottom bar -->
              <rect x="22" y="484" width="316" height="14" rx="3" fill="rgba(0,0,0,.25)"/>
              <g class="legend-item" transform="translate(30, 487)">
                  <circle cx="5" cy="4" r="4.5" fill="#F97316"/>
                  <text x="12" y="7" font-size="6.8" fill="rgba(255,255,255,.7)" font-weight="700">GK</text>
              </g>
              <g class="legend-item" transform="translate(68, 487)">
                  <circle cx="5" cy="4" r="4.5" fill="#1E3A8A"/>
                  <text x="12" y="7" font-size="6.8" fill="rgba(255,255,255,.7)" font-weight="700">DEF</text>
              </g>
              <g class="legend-item" transform="translate(108, 487)">
                  <circle cx="5" cy="4" r="4.5" fill="#15803d"/>
                  <text x="12" y="7" font-size="6.8" fill="rgba(255,255,255,.7)" font-weight="700">MID</text>
              </g>
              <g class="legend-item" transform="translate(148, 487)">
                  <circle cx="5" cy="4" r="4.5" fill="#dc2626"/>
                  <text x="12" y="7" font-size="6.8" fill="rgba(255,255,255,.7)" font-weight="700">FWD</text>
              </g>
          </svg>
      </div>

      <!-- RIGHT COLUMN: Tactics control workbench -->
      <div class="tactics-card">
          <div>
              <div class="section-title">Tactical Formation</div>
              <div class="form-group">
                  <label for="formation_select">Select Match Tactics</label>
                  <select name="formation_id" id="formation_select" required>
                      <option value="">Choose formation...</option>
                      <?php foreach ($formations as $f): ?>
                          <option value="<?= (int) $f['id']; ?>" <?= ((int) $existingFormationId === (int) $f['id']) ? 'selected' : ''; ?>><?= e($f['display_name']); ?> (<?= e($f['name']); ?>)</option>
                      <?php endforeach; ?>
                  </select>
              </div>
          </div>

          <div>
              <div class="section-title">Starting Eleven <span style="font-size: 11px; font-weight: normal; color: var(--gray);" id="startersCounter">0 / 11 selected</span></div>
              <div class="starters-grid" id="startersDropdownsContainer">
                  <!-- JS builds selectors here based on formation chosen -->
              </div>
          </div>

          <div>
              <div class="section-title">Substitutes <span style="font-size: 11px; font-weight: normal; color: var(--gray);" id="subsCounter">0 selected (min 7, max 12)</span></div>
              <div class="subs-wrap" id="subsListContainer">
                  <!-- JS builds checkboxes here -->
              </div>
          </div>

          <div class="validator-box">
              <div class="section-title" style="border-bottom:none; margin-bottom:0; padding-bottom:0; font-size:14px;">Roster Rules Verification</div>
              <div style="margin-top:8px;">
                  <div class="val-item" id="valStarters">
                      <i class="fa-solid fa-circle-xmark val-error"></i>
                      <span>Exactly 11 Starters chosen (Current: <strong id="valStartersCount">0</strong>)</span>
                  </div>
                  <div class="val-item" id="valSubs">
                      <i class="fa-solid fa-circle-xmark val-error"></i>
                      <span>7 – 12 Substitute Bench players chosen (Current: <strong id="valSubsCount">0</strong>)</span>
                  </div>
                  <div class="val-item" id="valTotal">
                      <i class="fa-solid fa-circle-xmark val-error"></i>
                      <span>Total roster size between 18 and 23 players (Current: <strong id="valTotalCount">0</strong>)</span>
                  </div>
              </div>
          </div>

          <div>
              <button type="submit" class="btn btn-primary" id="btnSubmitLineup" style="width:100%; padding: 12px; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;" disabled>
                  <i class="fa-solid fa-paper-plane"></i> Submit Match Lineup
              </button>
          </div>
      </div>

  </div>
</form>

<!-- POPUP MODAL: Interactive eligible player selection when clicking circles on the pitch -->
<div class="modal" id="playerSelectModal" style="display: none;">
  <div class="modal-content" style="max-width: 500px; border-radius: var(--rl); box-shadow: 0 20px 45px rgba(0,0,0,0.3); border:none;">
    <div class="modal-head">
      <h3 id="modalSlotTitle">Select Position Player</h3>
      <button type="button" class="btn btn-light btn-sm" onclick="closePlayerModal()">Close</button>
    </div>
    <div class="modal-body" style="padding-top: 12px;">
      <div style="font-size:12px; color:var(--gray); margin-bottom:12px;">Only eligible <strong id="modalSlotRoleText" style="text-transform:uppercase;"></strong> players are shown for this position.</div>
      <div id="eligiblePlayersGrid" style="display: flex; flex-direction: column; gap: 10px; max-height: 380px; overflow-y: auto; padding-right: 5px;">
        <!-- Dynamic Player rows will be loaded here -->
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inject active roster players and formations from PHP safely
    const players = <?= $playersJson; ?>;
    const formations = <?= $formationsJson; ?>;
    
    // Check for pre-existing inputs from edit mode
    const preExistingStarters = <?= json_encode($existingStarters); ?>;
    const preExistingSubs = <?= json_encode($existingSubs); ?>;
    
    const formationSelect = document.getElementById('formation_select');
    const startersContainer = document.getElementById('startersDropdownsContainer');
    const subsContainer = document.getElementById('subsListContainer');
    const pitchPlayersGroup = document.getElementById('pitchPlayersGroup');
    const pitchFormationBadge = document.getElementById('pitchFormationBadge');
    const btnSubmitLineup = document.getElementById('btnSubmitLineup');
    
    // Live validation element references
    const startersCounter = document.getElementById('startersCounter');
    const subsCounter = document.getElementById('subsCounter');
    const valStarters = document.getElementById('valStarters');
    const valStartersCount = document.getElementById('valStartersCount');
    const valSubs = document.getElementById('valSubs');
    const valSubsCount = document.getElementById('valSubsCount');
    const valTotal = document.getElementById('valTotal');
    const valTotalCount = document.getElementById('valTotalCount');

    // Dynamic slot coordinates mapper
    function getSlotCoordinates(roleName, idx, def, mid, fwd) {
        if (roleName === 'GK') {
            return { x: 180, y: 455, type: 'gk', abbrev: 'GK' };
        }
        
        if (roleName === 'DEF') {
            const x = def === 1 ? 180 : 50 + (idx * (260 / (def - 1)));
            const y = 375 - (def > 3 && (idx === 0 || idx === def - 1) ? 10 : 0);
            const abbrevs = def === 3 ? ['LCB', 'CB', 'RCB'] : (def === 4 ? ['LB', 'LCB', 'RCB', 'RB'] : ['LWB', 'LCB', 'CB', 'RCB', 'RWB']);
            return { x: Math.round(x), y: Math.round(y), type: 'def', abbrev: abbrevs[idx] || 'CB' };
        }
        
        if (roleName === 'MID') {
            const x = mid === 1 ? 180 : 60 + (idx * (240 / (mid - 1)));
            const y = 280 - (mid === 3 && idx === 1 ? -15 : (mid === 5 && idx === 2 ? 15 : 0));
            const abbrevs = mid === 3 ? ['LCM', 'DM', 'RCM'] : (mid === 4 ? ['LM', 'LCM', 'RCM', 'RM'] : ['LM', 'LCM', 'AM', 'RCM', 'RM']);
            return { x: Math.round(x), y: Math.round(y), type: 'mid', abbrev: abbrevs[idx] || 'CM' };
        }
        
        if (roleName === 'FWD') {
            const x = fwd === 1 ? 180 : 70 + (idx * (220 / (fwd - 1)));
            const y = 160 + (fwd === 3 && (idx === 0 || idx === fwd - 1) ? 10 : 0);
            const abbrevs = fwd === 1 ? ['ST'] : (fwd === 2 ? ['LS', 'RS'] : ['LW', 'ST', 'RW']);
            return { x: Math.round(x), y: Math.round(y), type: 'fwd', abbrev: abbrevs[idx] || 'ST' };
        }
        return { x: 180, y: 250, type: 'mid', abbrev: 'CM' };
    }

    // Colors mapping based on position type
    const positionColors = {
        gk: '#F97316', // Orange
        def: '#1E3A8A', // Navy
        mid: '#15803d', // Green
        fwd: '#dc2626'  // Red
    };

    // Tracks current tactical assignment details
    let currentSlots = [];

    // Initializes layout when formation is loaded or changed
    function handleFormationChange() {
        const fid = parseInt(formationSelect.value);
        if (!fid) {
            startersContainer.innerHTML = '';
            pitchPlayersGroup.innerHTML = '';
            pitchFormationBadge.textContent = 'None';
            currentSlots = [];
            validateRoster();
            return;
        }

        const formObj = formations.find(f => f.id === fid);
        if (!formObj) return;

        pitchFormationBadge.textContent = formObj.name;
        startersContainer.innerHTML = '';
        currentSlots = [];

        // Build list of slot roles: 1 GK, D defenders, M midfielders, F forwards
        const slotsDef = [];
        
        // 1. Goalkeeper
        slotsDef.push({ id: 'GK', roleName: 'GK', idx: 0 });
        
        // 2. Defenders
        for (let i = 0; i < formObj.def; i++) {
            slotsDef.push({ id: `DEF_${i}`, roleName: 'DEF', idx: i });
        }
        
        // 3. Midfielders
        for (let i = 0; i < formObj.mid; i++) {
            slotsDef.push({ id: `MID_${i}`, roleName: 'MID', idx: i });
        }
        
        // 4. Forwards
        for (let i = 0; i < formObj.fwd; i++) {
            slotsDef.push({ id: `FWD_${i}`, roleName: 'FWD', idx: i });
        }

        currentSlots = slotsDef.map(s => {
            const coord = getSlotCoordinates(s.roleName, s.idx, formObj.def, formObj.mid, formObj.fwd);
            return {
                id: s.id,
                roleName: s.roleName,
                coord: coord,
                playerId: 0
            };
        });

        // Generate Starting 11 input controls on the right column
        currentSlots.forEach(slot => {
            const wrap = document.createElement('div');
            wrap.className = 'form-group';
            
            const label = document.createElement('label');
            label.textContent = `${slot.coord.abbrev} Position`;
            
            const select = document.createElement('select');
            select.name = `starters[${slot.id}]`;
            select.id = `select_${slot.id}`;
            select.required = true;
            
            const optDefault = document.createElement('option');
            optDefault.value = '';
            optDefault.textContent = `- Select ${slot.coord.abbrev} -`;
            select.appendChild(optDefault);

            // ONLY show players eligible for this specific position group!
            const eligiblePlayers = players.filter(p => {
                const slotType = slot.coord.type;
                const playerPos = p.pos;
                if (slotType === 'gk') return playerPos === 'goalkeeper';
                if (slotType === 'def') return playerPos === 'defender';
                if (slotType === 'mid') return playerPos === 'midfielder';
                if (slotType === 'fwd') return playerPos === 'forward';
                return false;
            });

            eligiblePlayers.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = `${p.name} (#${p.num})`;
                // Pre-populate if we have pre-existing starter values
                if (preExistingStarters[slot.id] && preExistingStarters[slot.id] === p.id) {
                    opt.selected = true;
                    slot.playerId = p.id;
                }
                select.appendChild(opt);
            });

            select.addEventListener('change', function() {
                slot.playerId = parseInt(this.value) || 0;
                syncSelectChoices();
                renderPitchNodes();
                rebuildSubsChecklist();
                validateRoster();
            });

            wrap.appendChild(label);
            wrap.appendChild(select);
            startersContainer.appendChild(wrap);
        });

        syncSelectChoices();
        renderPitchNodes();
        rebuildSubsChecklist();
        validateRoster();
    }

    // Prevent selecting the same player twice in starters
    function syncSelectChoices() {
        const selectedStarters = currentSlots.map(s => s.playerId).filter(id => id > 0);
        
        currentSlots.forEach(slot => {
            const select = document.getElementById(`select_${slot.id}`);
            if (!select) return;
            
            Array.from(select.options).forEach(opt => {
                const val = parseInt(opt.value);
                if (val && val !== slot.playerId) {
                    if (selectedStarters.includes(val)) {
                        opt.disabled = true;
                    } else {
                        opt.disabled = false;
                    }
                }
            });
        });
    }

    // Build the bench / substitute list checklist dynamically
    function rebuildSubsChecklist() {
        const selectedStarters = currentSlots.map(s => s.playerId).filter(id => id > 0);
        
        // Save currently checked subs if any
        const checkedSubs = Array.from(subsContainer.querySelectorAll('input[type="checkbox"]:checked'))
                                 .map(el => parseInt(el.value));

        // Use pre-existing bench entries if this is first loading
        const initialSubs = (checkedSubs.length === 0 && preExistingSubs.length > 0) ? preExistingSubs : checkedSubs;

        subsContainer.innerHTML = '';

        players.forEach(p => {
            // Starters are hidden/excluded from bench choices
            if (selectedStarters.includes(p.id)) return;

            const label = document.createElement('label');
            label.className = 'sub-item-row';
            
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.name = 'substitutes[]';
            checkbox.value = p.id;
            
            if (initialSubs.includes(p.id)) {
                checkbox.checked = true;
            }

            checkbox.addEventListener('change', validateRoster);

            const metaSpan = document.createElement('span');
            metaSpan.style.display = 'flex';
            metaSpan.style.alignItems = 'center';
            metaSpan.style.gap = '8px';
            
            if (p.photo) {
                const img = document.createElement('img');
                img.src = p.photo;
                img.alt = p.lastName;
                img.style.width = '20px';
                img.style.height = '20px';
                img.style.borderRadius = '50%';
                img.style.objectFit = 'cover';
                metaSpan.appendChild(img);
            }

            const nameSpan = document.createElement('span');
            nameSpan.innerHTML = `<strong>${p.name}</strong> <span style="color:#64748B;">#${p.num} (${p.pos.toUpperCase()})</span>`;
            metaSpan.appendChild(nameSpan);

            label.appendChild(checkbox);
            label.appendChild(metaSpan);
            subsContainer.appendChild(label);
        });
    }

    // Render interactive player circles on the green SVG canvas
    function renderPitchNodes() {
        pitchPlayersGroup.innerHTML = '';
        
        currentSlots.forEach(slot => {
            const playerObj = players.find(p => p.id === slot.playerId);
            const cx = slot.coord.x;
            const cy = slot.coord.y;
            const color = positionColors[slot.coord.type] || '#15803d';

            // SVG Group wrapper - translated to cx, cy so we can render photos easily relative to 0,0
            const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            g.setAttribute('transform', `translate(${cx}, ${cy})`);
            g.setAttribute('filter', 'url(#nodeShadow)');
            g.style.cursor = 'pointer';
            g.style.transition = 'all 0.3s ease';

            if (playerObj) {
                // RENDER SELECTED PLAYER STATE
                
                if (playerObj.photo) {
                    // 1. Photo display masked as a circular circle
                    const img = document.createElementNS('http://www.w3.org/2000/svg', 'image');
                    img.setAttribute('href', playerObj.photo);
                    img.setAttribute('x', '-15');
                    img.setAttribute('y', '-15');
                    img.setAttribute('width', '30');
                    img.setAttribute('height', '30');
                    img.setAttribute('clip-path', 'url(#circleClip)');
                    g.appendChild(img);

                    // Circle border overlay
                    const border = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                    border.setAttribute('cx', '0');
                    border.setAttribute('cy', '0');
                    border.setAttribute('r', '15');
                    border.setAttribute('fill', 'none');
                    border.setAttribute('stroke', '#ffffff');
                    border.setAttribute('stroke-width', '1.8');
                    g.appendChild(border);
                    
                    // Name text positioned above the photo circle for supreme clarity
                    const txtName = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                    txtName.setAttribute('x', '0');
                    txtName.setAttribute('y', '-19');
                    txtName.setAttribute('text-anchor', 'middle');
                    txtName.setAttribute('font-size', '6.5');
                    txtName.setAttribute('font-weight', '800');
                    txtName.setAttribute('fill', '#ffffff');
                    txtName.setAttribute('font-family', 'Barlow, sans-serif');
                    txtName.setAttribute('style', 'text-shadow: 0 1px 3px rgba(0,0,0,0.85);');
                    txtName.textContent = playerObj.lastName.toUpperCase();
                    g.appendChild(txtName);
                    
                } else {
                    // 2. Initials fallback rendering (when no profile photo is uploaded)
                    const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                    circle.setAttribute('cx', '0');
                    circle.setAttribute('cy', '0');
                    circle.setAttribute('r', '15');
                    circle.setAttribute('fill', color);
                    circle.setAttribute('stroke', '#ffffff');
                    circle.setAttribute('stroke-width', '1.8');
                    g.appendChild(circle);

                    const txtName = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                    txtName.setAttribute('x', '0');
                    txtName.setAttribute('y', '-4');
                    txtName.setAttribute('text-anchor', 'middle');
                    txtName.setAttribute('font-size', '6.2');
                    txtName.setAttribute('font-weight', '700');
                    txtName.setAttribute('fill', '#ffffff');
                    txtName.setAttribute('font-family', 'Barlow, sans-serif');
                    txtName.textContent = playerObj.lastName.toUpperCase();
                    g.appendChild(txtName);

                    const txtRole = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                    txtRole.setAttribute('x', '0');
                    txtRole.setAttribute('y', '5');
                    txtRole.setAttribute('text-anchor', 'middle');
                    txtRole.setAttribute('font-size', '5.5');
                    txtRole.setAttribute('fill', 'rgba(255, 255, 255, 0.7)');
                    txtRole.setAttribute('font-family', 'sans-serif');
                    txtRole.textContent = slot.coord.abbrev;
                    g.appendChild(txtRole);
                }

                // Dark jersey pill and number under the circle (visible in both photo/fallback modes)
                const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                rect.setAttribute('x', '-12');
                rect.setAttribute('y', '17');
                rect.setAttribute('width', '24');
                rect.setAttribute('height', '9');
                rect.setAttribute('rx', '2.5');
                rect.setAttribute('fill', 'rgba(0,0,0,.6)');
                g.appendChild(rect);

                const txtNum = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                txtNum.setAttribute('x', '0');
                txtNum.setAttribute('y', '24');
                txtNum.setAttribute('text-anchor', 'middle');
                txtNum.setAttribute('font-size', '6.5');
                txtNum.setAttribute('font-weight', '700');
                txtNum.setAttribute('fill', '#ffffff');
                txtNum.setAttribute('font-family', 'Barlow, sans-serif');
                txtNum.textContent = `#${playerObj.num}`;
                g.appendChild(txtNum);
                
            } else {
                // RENDER EMPTY / ASSIGNMENT PLACEHOLDER STATE
                
                const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                circle.setAttribute('cx', '0');
                circle.setAttribute('cy', '0');
                circle.setAttribute('r', '14');
                circle.setAttribute('fill', 'none');
                circle.setAttribute('stroke', 'rgba(255, 255, 255, 0.4)');
                circle.setAttribute('stroke-width', '1.5');
                circle.setAttribute('stroke-dasharray', '3,3');
                g.appendChild(circle);

                const cross = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                cross.setAttribute('x', '0');
                cross.setAttribute('y', '3.5');
                cross.setAttribute('text-anchor', 'middle');
                cross.setAttribute('font-size', '12');
                cross.setAttribute('fill', 'rgba(255, 255, 255, 0.45)');
                cross.setAttribute('font-family', 'sans-serif');
                cross.textContent = '+';
                g.appendChild(cross);

                const txtSlot = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                txtSlot.setAttribute('x', '0');
                txtSlot.setAttribute('y', '23');
                txtSlot.setAttribute('text-anchor', 'middle');
                txtSlot.setAttribute('font-size', '6.5');
                txtSlot.setAttribute('font-weight', '700');
                txtSlot.setAttribute('fill', 'rgba(255, 255, 255, 0.65)');
                txtSlot.setAttribute('font-family', 'Barlow, sans-serif');
                txtSlot.textContent = slot.coord.abbrev;
                g.appendChild(txtSlot);
            }

            // Click node to open the dynamic eligible position popup selector!
            g.addEventListener('click', function() {
                openPlayerModal(slot.id);
            });

            pitchPlayersGroup.appendChild(g);
        });
    }

    // Handles open player modal
    window.openPlayerModal = function(slotId) {
        const slot = currentSlots.find(s => s.id === slotId);
        if (!slot) return;

        const modal = document.getElementById('playerSelectModal');
        const modalSlotTitle = document.getElementById('modalSlotTitle');
        const modalSlotRoleText = document.getElementById('modalSlotRoleText');
        const grid = document.getElementById('eligiblePlayersGrid');

        const roleString = slot.coord.type === 'gk' ? 'goalkeeper' : (slot.coord.type === 'def' ? 'defender' : (slot.coord.type === 'mid' ? 'midfielder' : 'forward'));

        modalSlotTitle.innerHTML = `Assign <span>${slot.coord.abbrev}</span> Position`;
        modalSlotRoleText.textContent = roleString;
        grid.innerHTML = '';

        // Filter active players to show ONLY those eligible for this specific position group!
        const eligiblePlayers = players.filter(p => {
            const slotType = slot.coord.type;
            const playerPos = p.pos;
            if (slotType === 'gk') return playerPos === 'goalkeeper';
            if (slotType === 'def') return playerPos === 'defender';
            if (slotType === 'mid') return playerPos === 'midfielder';
            if (slotType === 'fwd') return playerPos === 'forward';
            return false;
        });

        if (eligiblePlayers.length === 0) {
            grid.innerHTML = `<div class="empty-state" style="padding:20px; text-align:center; color:var(--gray);">No active ${roleString}s available in your squad. Register some in Roster tab first.</div>`;
            modal.classList.add('active');
            modal.style.display = 'flex';
            return;
        }

        // Get currently assigned starters
        const assignedStarters = {};
        currentSlots.forEach(s => {
            if (s.playerId > 0) {
                assignedStarters[s.playerId] = s;
            }
        });

        // Get selected bench subs
        const checkedSubs = Array.from(subsContainer.querySelectorAll('input[type="checkbox"]:checked'))
                                 .map(el => parseInt(el.value));

        eligiblePlayers.forEach(p => {
            const card = document.createElement('div');
            card.className = 'player-card-option';

            const metaLeft = document.createElement('div');
            metaLeft.className = 'player-meta-left';

            if (p.photo) {
                const img = document.createElement('img');
                img.src = p.photo;
                img.alt = p.lastName;
                metaLeft.appendChild(img);
            } else {
                const initials = document.createElement('div');
                initials.className = 'avatar-ph';
                initials.textContent = p.lastName.substring(0, 2).toUpperCase();
                metaLeft.appendChild(initials);
            }

            const nameWrap = document.createElement('div');
            nameWrap.className = 'player-name-wrap';
            nameWrap.innerHTML = `<strong>${p.name}</strong> <span>Jersey #${p.num}</span>`;
            metaLeft.appendChild(nameWrap);

            card.appendChild(metaLeft);

            const statusBadge = document.createElement('span');
            statusBadge.className = 'player-status-badge';

            let isSelectable = true;

            if (slot.playerId === p.id) {
                statusBadge.textContent = 'Assigned Here';
                statusBadge.classList.add('badge-selected');
                card.classList.add('selected');
            } else if (assignedStarters[p.id]) {
                const otherSlot = assignedStarters[p.id];
                statusBadge.textContent = `Starter (${otherSlot.coord.abbrev})`;
                statusBadge.classList.add('badge-assigned');
                card.classList.add('disabled');
                isSelectable = false; // Cannot duplicate player!
            } else if (checkedSubs.includes(p.id)) {
                statusBadge.textContent = 'On Bench';
                statusBadge.classList.add('badge-selected');
            } else {
                statusBadge.textContent = 'Available';
                statusBadge.classList.add('badge-avail');
            }

            card.appendChild(statusBadge);

            if (isSelectable) {
                card.addEventListener('click', function() {
                    const select = document.getElementById(`select_${slot.id}`);
                    if (select) {
                        select.value = p.id;
                        slot.playerId = p.id;
                    }
                    
                    // If they were on the bench, remove them from bench checklist
                    if (checkedSubs.includes(p.id)) {
                        const checkbox = Array.from(subsContainer.querySelectorAll('input[type="checkbox"]'))
                                             .find(el => parseInt(el.value) === p.id);
                        if (checkbox) checkbox.checked = false;
                    }

                    syncSelectChoices();
                    renderPitchNodes();
                    rebuildSubsChecklist();
                    validateRoster();
                    closePlayerModal();
                });
            }

            grid.appendChild(card);
        });

        modal.classList.add('active');
        modal.style.display = 'flex';
    };

    window.closePlayerModal = function() {
        const modal = document.getElementById('playerSelectModal');
        if (modal) {
            modal.classList.remove('active');
            modal.style.display = 'none';
        }
    };

    // Handles live counters and enforces squad limits (11 starters, 7-12 subs, 18-23 total)
    function validateRoster() {
        const starersCount = currentSlots.filter(s => s.playerId > 0).length;
        const subsCount = subsContainer.querySelectorAll('input[type="checkbox"]:checked').length;
        const totalCount = starersCount + subsCount;

        // Update labels
        startersCounter.textContent = `${starersCount} / 11 selected`;
        subsCounter.textContent = `${subsCount} selected (min 7, max 12)`;
        
        valStartersCount.textContent = starersCount;
        valSubsCount.textContent = subsCount;
        valTotalCount.textContent = totalCount;

        let isAllValid = true;

        // 1. Validate Starters (Must be exactly 11)
        if (starersCount === 11) {
            valStarters.className = 'val-item val-success';
            valStarters.querySelector('i').className = 'fa-solid fa-circle-check';
        } else {
            valStarters.className = 'val-item val-error';
            valStarters.querySelector('i').className = 'fa-solid fa-circle-xmark';
            isAllValid = false;
        }

        // 2. Validate Substitutes (Must be between 7 and 12)
        if (subsCount >= 7 && subsCount <= 12) {
            valSubs.className = 'val-item val-success';
            valSubs.querySelector('i').className = 'fa-solid fa-circle-check';
        } else {
            valSubs.className = 'val-item val-error';
            valSubs.querySelector('i').className = 'fa-solid fa-circle-xmark';
            isAllValid = false;
        }

        // 3. Validate Total Squad size (Must be between 18 and 23)
        if (totalCount >= 18 && totalCount <= 23) {
            valTotal.className = 'val-item val-success';
            valTotal.querySelector('i').className = 'fa-solid fa-circle-check';
        } else {
            valTotal.className = 'val-item val-error';
            valTotal.querySelector('i').className = 'fa-solid fa-circle-xmark';
            isAllValid = false;
        }

        // Enable or disable save lineup button
        btnSubmitLineup.disabled = !isAllValid;
    }

    // Attach base triggers
    formationSelect.addEventListener('change', handleFormationChange);
    
    // Automatically trigger initial loading (important for pre-existing setups)
    if (formationSelect.value) {
        handleFormationChange();
    }
});
</script>
