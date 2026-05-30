<?php
$matchesList = db_fetch_all("
    SELECT m.*, ht.name AS home_name, ht.logo AS home_logo, at.name AS away_name, at.logo AS away_logo, s.name AS stadium_name, s.city AS stadium_city, c.name AS competition_name
    FROM matches m
    INNER JOIN teams ht ON ht.id = m.home_team_id
    INNER JOIN teams at ON at.id = m.away_team_id
    LEFT JOIN stadiums s ON s.id = m.stadium_id
    LEFT JOIN competitions c ON c.id = m.competition_id
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
          <div class="match-card" data-status="<?= e($m['status']); ?>" data-search="<?= e(strtolower($m['home_name'] . ' ' . $m['away_name'] . ' ' . $m['stadium_name'] . ' round ' . $m['round'])); ?>" style="background: var(--navy); border-radius: var(--rl); overflow: hidden; transition: transform 0.2s;">
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
