<?php
$matchesList = db_fetch_all("
    SELECT m.*, ht.name AS home_name, ht.logo AS home_logo, at.name AS away_name, at.logo AS away_logo, s.name AS stadium_name, s.city AS stadium_city, c.name AS competition_name
    FROM matches m
    INNER JOIN teams ht ON ht.id = m.home_team_id
    INNER JOIN teams at ON at.id = m.away_team_id
    LEFT JOIN stadiums s ON s.id = m.stadium_id
    LEFT JOIN competitions c ON c.id = m.competition_id
    WHERE m.status IN ('scheduled', 'in_progress', 'completed', 'cancelled')
    ORDER BY m.match_date ASC, m.match_time ASC
");
?>
<section style="background: var(--off); min-height: 600px; padding: 24px 20px;">
  <div style="max-width: 1200px; margin: 0 auto;">
    
    <!-- Filters Bar -->
    <div style="background: #fff; border: 1px solid var(--gray-l); border-radius: var(--rl); padding: 18px; margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap; align-items: center; justify-content: space-between; box-shadow: 0 4px 12px rgba(0,0,0,0.02);">
      <div style="display: flex; gap: 10px; flex: 1; min-width: 280px;">
        <div style="position: relative; flex: 1;">
          <input type="text" id="match-search" placeholder="Search team, round or stadium..." style="width: 100%; padding: 10px 14px 10px 36px; border: 1px solid var(--gray-l); border-radius: var(--r); outline: none; font-family: inherit; font-size: 13px;" oninput="filterMatches()"/>
          <svg style="position: absolute; left: 12px; top: 12px; width: 14px; height: 14px; color: var(--gray); z-index: 10;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        </div>
      </div>
      <div style="display: flex; gap: 8px;">
        <button class="btn-p" style="padding: 8px 16px; font-size: 11px; font-weight: 700;" onclick="setStatusFilter('all')">All Matches</button>
        <button class="btn-s" style="padding: 8px 16px; font-size: 11px; font-weight: 700; color: var(--text); border-color: var(--gray-l);" onclick="setStatusFilter('scheduled')">Scheduled</button>
        <button class="btn-s" style="padding: 8px 16px; font-size: 11px; font-weight: 700; color: var(--text); border-color: var(--gray-l);" onclick="setStatusFilter('completed')">Completed</button>
      </div>
    </div>

    <!-- Matches Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 16px;" id="matches-grid-container">
      <?php if (empty($matchesList)): ?>
        <div style="color: var(--text3); text-align: center; grid-column: 1 / -1; padding: 40px;">No scheduled fixtures found.</div>
      <?php else: ?>
        <?php foreach ($matchesList as $m): 
          $isLive = $m['status'] === 'in_progress';
          $isFinished = $m['status'] === 'completed';
          $isCancelled = $m['status'] === 'cancelled';
          
          $stBadge = $isLive ? 'Live' : ($isFinished ? 'Full Time' : ($isCancelled ? 'Cancelled' : 'Scheduled'));
          $stClass = $isLive ? 'live' : ($isFinished ? 'finished' : ($isCancelled ? 'finished' : 'upcoming'));
          
          // Fetch score if completed or live
          $score = null;
          if ($isFinished || $isLive) {
              $score = db_fetch_one("SELECT home_score, away_score FROM match_results WHERE match_id = ? AND status = 'approved'", 'i', [$m['id']]);
          }
          $scoreText = $score ? "{$score['home_score']} – {$score['away_score']}" : "vs";
          $matchTimeStr = $m['match_time'] ? date('H:i', strtotime($m['match_time'])) : '15:00';
        ?>
          <div class="match-card" onclick="openLineupModal(<?= (int) $m['id']; ?>)" data-status="<?= e($m['status']); ?>" data-search="<?= e(strtolower($m['home_name'] . ' ' . $m['away_name'] . ' ' . $m['stadium_name'] . ' round ' . $m['round'])); ?>" style="background: var(--navy); border-radius: var(--rl); overflow: hidden; transition: transform 0.2s; cursor: pointer;">
            <div class="mc-top" style="background: rgba(255,255,255,0.03); padding: 10px 14px; display: flex; justify-content: space-between; align-items: center;">
              <span class="mc-lg" style="font-size: 9px; font-weight: 800; color: var(--org); letter-spacing: 0.5px; text-transform: uppercase;"><?= e($m['competition_name'] ?: 'Rwanda Premier League'); ?></span>
              <span class="mc-st <?= $stClass; ?>" style="font-size: 8px; font-weight: 800; padding: 2px 6px; border-radius: 3px;"><?= $stBadge; ?></span>
            </div>
            <div class="mc-body" style="padding: 20px 14px;">
              <div class="mc-teams" style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                <div class="mc-team" style="display: flex; flex-direction: column; align-items: center; gap: 8px; flex: 1;">
                  <div class="t-logo" style="width: 46px; height: 46px; border: 2px solid rgba(255,255,255,0.1);"><img src="<?= e($m['home_logo'] ?: 'https://images.unsplash.com/photo-1551958219-acbc595d9e15?w=100&q=80'); ?>" alt=""/></div>
                  <span class="mc-tn" style="font-size: 11px; font-weight: 700; color: #fff; text-align: center;"><?= e($m['home_name']); ?></span>
                </div>
                <div class="mc-vs" style="text-align: center; flex-shrink: 0; min-width: 70px;">
                  <div class="mc-sc" style="font-size: 26px; font-weight: 900; color: #fff;"><?= $scoreText; ?></div>
                  <span style="font-size: 9px; color: rgba(255,255,255,0.35); font-weight: 600; text-transform: uppercase;"><?= date('d M Y', strtotime($m['match_date'])); ?></span>
                </div>
                <div class="mc-team" style="display: flex; flex-direction: column; align-items: center; gap: 8px; flex: 1;">
                  <div class="t-logo" style="width: 46px; height: 46px; border: 2px solid rgba(255,255,255,0.1);"><img src="<?= e($m['away_logo'] ?: 'https://images.unsplash.com/photo-1587329310690-91114ac008f0?w=100&q=80'); ?>" alt=""/></div>
                  <span class="mc-tn" style="font-size: 11px; font-weight: 700; color: #fff; text-align: center;"><?= e($m['away_name']); ?></span>
                </div>
              </div>
            </div>
            <div class="mc-foot" style="padding: 10px 14px; border-top: 1px solid rgba(255,255,255,0.04); display: flex; justify-content: space-between; align-items: center; font-size: 10px; color: rgba(255,255,255,0.4);">
              <span>📍 <?= e($m['stadium_name']); ?> (<?= e($m['stadium_city']); ?>)</span>
              <span style="color: var(--org); font-weight: 700;">🕒 <?= $matchTimeStr; ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- LINEUP MODAL -->
<div id="lineupModal" class="modal-overlay" onclick="closeLineupModal(event)">
  <div class="modal-card" onclick="event.stopPropagation()">
    <!-- Close button -->
    <button class="modal-close-btn" onclick="closeLineupModal(null)">&times;</button>
    
    <!-- Loader View -->
    <div id="modalLoader" class="modal-loader-view">
      <div class="spinner"></div>
      <div style="margin-top: 15px; color: var(--text3); font-size: 13px;">Loading match lineups...</div>
    </div>
    
    <!-- Team Selector View -->
    <div id="teamSelectorView" class="modal-view" style="display: none;">
      <div class="modal-title">Select a <span>Team</span></div>
      <div class="modal-subtitle">Choose a team to preview their lineup configuration</div>
      
      <div class="team-cards-grid">
        <!-- Home Team Card -->
        <div class="team-select-card" id="homeTeamSelectCard">
          <div class="team-select-logo-wrap">
            <img id="homeTeamSelectLogo" src="" alt="Home Logo">
          </div>
          <div class="team-select-name" id="homeTeamSelectName">Home Team</div>
          <button class="btn-p" style="margin-top: 15px; width: 100%; justify-content: center;" id="homeTeamSelectBtn">View Lineup</button>
        </div>
        
        <div class="team-select-vs">VS</div>
        
        <!-- Away Team Card -->
        <div class="team-select-card" id="awayTeamSelectCard">
          <div class="team-select-logo-wrap">
            <img id="awayTeamSelectLogo" src="" alt="Away Logo">
          </div>
          <div class="team-select-name" id="awayTeamSelectName">Away Team</div>
          <button class="btn-s" style="margin-top: 15px; width: 100%; justify-content: center; color:#fff; border-color:rgba(255,255,255,.3)" id="awayTeamSelectBtn">View Lineup</button>
        </div>
      </div>
    </div>
    
    <!-- Lineup Preview View -->
    <div id="lineupPreviewView" class="modal-view" style="display: none;">
      <!-- Back button -->
      <button class="modal-back-btn" onclick="showTeamSelector()">&larr; Back to Teams</button>
      
      <!-- Pitch Section structure (from index.php) -->
      <div class="pitch-section" style="margin-top: 10px;">
        <!-- Header -->
        <div class="ps-hd">
          <div class="ps-match-info">
            <div class="ps-title" id="ps-match-title">Home <span>vs Away</span></div>
            <div class="ps-meta">
              <span class="ps-meta-dot"></span>
              <span id="ps-formation-label">Formation: 4-3-3</span>
              <span style="color:rgba(255,255,255,.15)">|</span>
              <span id="ps-match-details-label">Matchday 1 • Stadium</span>
            </div>
          </div>
          <div class="ps-tabs">
            <span class="ps-tab active" id="tab-home-btn">Home Team</span>
            <span class="ps-tab" id="tab-away-btn">Away Team</span>
          </div>
        </div>
        
        <!-- Body -->
        <div class="ps-body">
          <!-- PITCH FIELD: SVG container dynamically rendered -->
          <div class="pitch-field">
            <div id="pitch-panel-container"></div>
          </div>
          
          <!-- SIDEBAR Roster list -->
          <div class="pitch-info">
            <!-- Team header -->
            <div class="pi-team-hd">
              <div class="pi-team-logo"><img id="pi-team-img" src="" alt=""/></div>
              <span class="pi-team-label" id="pi-team-name">Team Name</span>
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
    </div>
  </div>
</div>

<style>
/* Modal overlay styling */
.modal-overlay {
  position: fixed;
  z-index: 9999;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(15, 23, 42, 0.85);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  padding: 20px;
}
.modal-overlay.active {
  opacity: 1;
  pointer-events: auto;
}

/* Modal Card */
.modal-card {
  background: var(--navy);
  width: 100%;
  max-width: 680px;
  border-radius: var(--rl);
  border: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  position: relative;
  overflow: hidden;
  max-height: 92vh;
  display: flex;
  flex-direction: column;
  transform: scale(0.95);
  transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.modal-overlay.active .modal-card {
  transform: scale(1);
}

/* Close & Back buttons */
.modal-close-btn {
  position: absolute;
  top: 14px;
  right: 18px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: rgba(255, 255, 255, 0.6);
  font-size: 22px;
  font-weight: 500;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: 0.18s;
  z-index: 10;
  line-height: 1;
  padding-bottom: 3px;
}
.modal-close-btn:hover {
  background: rgba(239, 68, 68, 0.2);
  color: #ef4444;
  border-color: rgba(239, 68, 68, 0.3);
}
.modal-back-btn {
  background: none;
  border: none;
  color: var(--org);
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  padding: 8px 12px;
  border-radius: var(--r);
  transition: 0.18s;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.modal-back-btn:hover {
  background: rgba(249, 115, 22, 0.1);
  color: var(--org-d);
}

/* Views & Headers */
.modal-view {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
}
.modal-title {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 24px;
  font-weight: 900;
  color: #fff;
  text-transform: uppercase;
  margin-bottom: 4px;
  letter-spacing: -0.5px;
}
.modal-title span {
  color: var(--org);
}
.modal-subtitle {
  font-size: 11px;
  color: var(--text3);
  margin-bottom: 24px;
}

/* Loader view */
.modal-loader-view {
  height: 250px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.spinner {
  width: 40px;
  height: 40px;
  border: 3.5px solid rgba(255, 255, 255, 0.05);
  border-top-color: var(--org);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Team Selection Screen Cards */
.team-cards-grid {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  gap: 20px;
  margin: 10px 0;
}
.team-select-card {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: var(--rm);
  padding: 24px 20px;
  text-align: center;
  cursor: pointer;
  transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.team-select-card:hover {
  transform: translateY(-4px);
  background: rgba(255, 255, 255, 0.06);
  border-color: var(--org);
  box-shadow: 0 10px 25px -5px rgba(249, 115, 22, 0.15);
}
.team-select-logo-wrap {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.2);
  border: 2px solid rgba(255, 255, 255, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  margin-bottom: 15px;
}
.team-select-logo-wrap img {
  width: 80%;
  height: 80%;
  object-fit: contain;
}
.team-select-name {
  font-size: 13px;
  font-weight: 700;
  color: #fff;
  line-height: 1.3;
}
.team-select-vs {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 26px;
  font-weight: 900;
  color: rgba(255, 255, 255, 0.15);
  font-style: italic;
}

/* Responsiveness for Modal selection */
@media (max-width: 600px) {
  .team-cards-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }
  .team-select-vs {
    padding: 5px 0;
  }
  .modal-card {
    max-height: 95vh;
  }
  .ps-body {
    flex-direction: column;
  }
  .pitch-info {
    width: 100%;
  }
}
</style>

<script>
const POSCOLORS = {gk:"#F97316",def:"#1E3A8A",mid:"#15803d",fwd:"#dc2626"};
let currentLineupData = null;
let activeTeamIdx = 0;

function openLineupModal(matchId) {
    const modal = document.getElementById('lineupModal');
    const loader = document.getElementById('modalLoader');
    const selector = document.getElementById('teamSelectorView');
    const preview = document.getElementById('lineupPreviewView');
    
    // Show modal & loader
    modal.classList.add('active');
    loader.style.display = 'flex';
    selector.style.display = 'none';
    preview.style.display = 'none';
    
    // Fetch data
    fetch('matches.php?action=get_lineup&match_id=' + matchId)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Error fetching lineup data.');
                closeLineupModal(null);
                return;
            }
            
            currentLineupData = data;
            
            // Populating team selector view
            document.getElementById('homeTeamSelectLogo').src = data.match.home_logo || 'https://images.unsplash.com/photo-1551958219-acbc595d9e15?w=100&q=80';
            document.getElementById('homeTeamSelectName').textContent = data.match.home_name;
            document.getElementById('awayTeamSelectLogo').src = data.match.away_logo || 'https://images.unsplash.com/photo-1587329310690-91114ac008f0?w=100&q=80';
            document.getElementById('awayTeamSelectName').textContent = data.match.away_name;
            
            // Set up click events for selector
            document.getElementById('homeTeamSelectBtn').onclick = (e) => { e.stopPropagation(); selectTeamForPreview(0); };
            document.getElementById('homeTeamSelectCard').onclick = () => selectTeamForPreview(0);
            document.getElementById('awayTeamSelectBtn').onclick = (e) => { e.stopPropagation(); selectTeamForPreview(1); };
            document.getElementById('awayTeamSelectCard').onclick = () => selectTeamForPreview(1);
            
            // Render selector
            loader.style.display = 'none';
            selector.style.display = 'block';
        })
        .catch(err => {
            console.error(err);
            alert('An error occurred while loading lineups.');
            closeLineupModal(null);
        });
}

function selectTeamForPreview(teamIdx) {
    activeTeamIdx = teamIdx;
    document.getElementById('teamSelectorView').style.display = 'none';
    document.getElementById('lineupPreviewView').style.display = 'block';
    renderLineupForSelectedTeam();
}

function showTeamSelector() {
    document.getElementById('lineupPreviewView').style.display = 'none';
    document.getElementById('teamSelectorView').style.display = 'block';
}

function closeLineupModal(event) {
    const modal = document.getElementById('lineupModal');
    if (!event || event.target === modal || event.target.classList.contains('modal-close-btn')) {
        modal.classList.remove('active');
        currentLineupData = null;
    }
}

function renderPitchSVG(team, teamIdx) {
    const formation = team.formation;
    const players = team.players;
    const badgeColor = teamIdx === 0 ? 'rgba(249,115,22,.9)' : 'rgba(124,58,237,.9)';
    
    let svgContent = `
      <svg viewBox="0 0 360 500" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:auto">
        <defs>
          <linearGradient id="gfgM" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#185e2d"/>
            <stop offset="25%" stop-color="#1d6b34"/>
            <stop offset="50%" stop-color="#196030"/>
            <stop offset="75%" stop-color="#1d6b34"/>
            <stop offset="100%" stop-color="#185e2d"/>
          </linearGradient>
          <filter id="shadowM" x="-20%" y="-20%" width="140%" height="140%">
            <feDropShadow dx="0" dy="1" stdDeviation="2" flood-color="rgba(0,0,0,.5)"/>
          </filter>
          <clipPath id="circleClipM">
            <circle cx="0" cy="0" r="15" />
          </clipPath>
        </defs>
        <!-- Base + stripes -->
        <rect width="360" height="500" rx="10" fill="url(#gfgM)"/>
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
        <rect x="146" y="7" width="68" height="14" rx="4" fill="${badgeColor}"/>
        <text x="180" y="17.5" text-anchor="middle" font-size="8" font-weight="700" fill="white" font-family="Barlow,sans-serif" letter-spacing=".5">${formation}</text>
    `;
    
    players.forEach(p => {
        const color = POSCOLORS[p.posType] || '#15803d';
        svgContent += `
          <g filter="url(#shadowM)" transform="translate(${p.x}, ${p.y})">
        `;
        if (p.img) {
            svgContent += `
              <image href="${p.img}" x="-15" y="-15" width="30" height="30" clip-path="url(#circleClipM)" />
              <circle cx="0" cy="0" r="15" fill="none" stroke="white" stroke-width="1.8" />
            `;
        } else {
            svgContent += `
              <circle cx="0" cy="0" r="15" fill="${color}" stroke="white" stroke-width="1.8" />
              <text x="0" y="2.5" text-anchor="middle" font-size="6.2" font-weight="700" fill="white" font-family="Barlow,sans-serif">${p.short}</text>
            `;
        }
        svgContent += `
            <rect x="-12" y="17" width="24" height="9" rx="2.5" fill="rgba(0,0,0,.5)" />
            <text x="0" y="24" text-anchor="middle" font-size="6.5" font-weight="700" fill="white" font-family="Barlow,sans-serif">#${p.num}</text>
            <text x="0" y="-18" text-anchor="middle" font-size="6.5" font-weight="800" fill="white" font-family="Barlow,sans-serif" style="text-shadow: 0 1px 2px rgba(0,0,0,0.85);">${p.short}</text>
          </g>
        `;
    });
    
    svgContent += `
        <rect x="22" y="484" width="316" height="14" rx="3" fill="rgba(0,0,0,.25)"/>
        <circle cx="35" cy="491" r="5" fill="#F97316"/><text x="43" y="495" font-size="7" fill="rgba(255,255,255,.6)" font-family="sans-serif" font-weight="600">GK</text>
        <circle cx="72" cy="491" r="5" fill="#1E3A8A"/><text x="80" y="495" font-size="7" fill="rgba(255,255,255,.6)" font-family="sans-serif" font-weight="600">DEF</text>
        <circle cx="112" cy="491" r="5" fill="#15803d"/><text x="120" y="495" font-size="7" fill="rgba(255,255,255,.6)" font-family="sans-serif" font-weight="600">MID</text>
        <circle cx="151" cy="491" r="5" fill="#dc2626"/><text x="159" y="495" font-size="7" fill="rgba(255,255,255,.6)" font-family="sans-serif" font-weight="600">FWD</text>
      </svg>
    `;
    
    document.getElementById('pitch-panel-container').innerHTML = svgContent;
}

function renderLineupForSelectedTeam() {
    if (!currentLineupData) return;
    
    const match = currentLineupData.match;
    const isHome = activeTeamIdx === 0;
    const teamData = isHome ? currentLineupData.home : currentLineupData.away;
    const teamName = isHome ? match.home_name : match.away_name;
    const teamLogo = isHome ? match.home_logo : match.away_logo;
    const opponentName = isHome ? match.away_name : match.home_name;
    
    document.getElementById('ps-match-title').innerHTML = isHome ? 
        `${match.home_name} <span>vs ${match.away_name}</span>` : 
        `${match.away_name} <span>vs ${match.home_name}</span>`;
        
    document.getElementById('ps-formation-label').textContent = 'Formation: ' + teamData.formation;
    document.getElementById('ps-match-details-label').innerHTML = `${match.competition_name}  &nbsp;•&nbsp;  ${match.matchday_text}  &nbsp;•&nbsp;  ${match.stadium_name}`;
    
    const tabHome = document.getElementById('tab-home-btn');
    const tabAway = document.getElementById('tab-away-btn');
    tabHome.textContent = match.home_name;
    tabAway.textContent = match.away_name;
    tabHome.classList.toggle('active', isHome);
    tabAway.classList.toggle('active', !isHome);
    
    // Set up click handlers for tabs
    tabHome.onclick = () => { activeTeamIdx = 0; renderLineupForSelectedTeam(); };
    tabAway.onclick = () => { activeTeamIdx = 1; renderLineupForSelectedTeam(); };
    
    // Render Pitch
    renderPitchSVG(teamData, activeTeamIdx);
    
    // Roster Sidebar
    document.getElementById('pi-team-img').src = teamLogo || (isHome ? 'https://images.unsplash.com/photo-1551958219-acbc595d9e15?w=50&q=80' : 'https://images.unsplash.com/photo-1587329310690-91114ac008f0?w=50&q=80');
    document.getElementById('pi-team-name').textContent = teamName;
    document.getElementById('pi-formation-badge').textContent = teamData.formation;
    
    const list = document.getElementById('pi-players-list');
    list.innerHTML = '';
    
    teamData.players.forEach(p => {
        const color = POSCOLORS[p.posType] || '#15803d';
        const div = document.createElement('div');
        div.className = 'pi-p';
        div.innerHTML = `<div class="pi-n" style="background:${color}">${p.num}</div><span class="pi-pn">${p.name}</span><span class="pi-ps">${p.pos}</span>`;
        list.appendChild(div);
    });
    
    if (teamData.bench && teamData.bench.length > 0) {
        const div = document.createElement('div');
        div.className = 'bench-div';
        list.appendChild(div);
        
        const lbl = document.createElement('div');
        lbl.className = 'bench-lbl';
        lbl.textContent = 'Bench';
        list.appendChild(lbl);
        
        teamData.bench.forEach(p => {
            const div = document.createElement('div');
            div.className = 'pi-p';
            div.innerHTML = `<div class="pi-n" style="background:rgba(255,255,255,.15)">${p.num}</div><span class="pi-pn">${p.name}</span><span class="pi-ps">${p.pos}</span>`;
            list.appendChild(div);
        });
    }
}
</script>
