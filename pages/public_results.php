<?php
$resultsList = db_fetch_all("
    SELECT mr.*, m.match_date, m.matchday, ht.name AS home_name, ht.logo AS home_logo, at.name AS away_name, at.logo AS away_logo, s.name AS stadium_name
    FROM match_results mr
    INNER JOIN matches m ON m.id = mr.match_id
    INNER JOIN teams ht ON ht.id = m.home_team_id
    INNER JOIN teams at ON at.id = m.away_team_id
    LEFT JOIN stadiums s ON s.id = m.stadium_id
    WHERE mr.status = 'approved'
    ORDER BY m.match_date DESC, m.id DESC
");
?>
<section style="background: var(--off); min-height: 600px; padding: 24px 20px;">
  <div style="max-width: 1000px; margin: 0 auto;">
    
    <!-- Results Filters -->
    <div style="background: #fff; border: 1px solid var(--gray-l); border-radius: var(--rl); padding: 18px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
      <h3 style="font-size: 15px; font-weight: 800; color: var(--navy); text-transform: uppercase;">Official Match Results</h3>
      <div style="position: relative; width: 300px;">
        <input type="text" id="result-search" placeholder="Search result by team or stadium..." style="width: 100%; padding: 10px 14px 10px 36px; border: 1px solid var(--gray-l); border-radius: var(--r); outline: none; font-family: inherit; font-size: 13px;" oninput="filterResults()"/>
        <svg style="position: absolute; left: 12px; top: 12px; width: 14px; height: 14px; color: var(--gray); z-index: 10;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      </div>
    </div>

    <!-- Results List -->
    <div style="display: flex; flex-direction: column; gap: 16px;" id="results-list-container">
      <?php if (empty($resultsList)): ?>
        <div style="color: var(--text3); text-align: center; padding: 40px;">No completed official match results found.</div>
      <?php else: ?>
        <?php foreach ($resultsList as $r): ?>
          <div class="result-row-card" data-search="<?= e(strtolower($r['home_name'] . ' ' . $r['away_name'] . ' ' . $r['stadium_name'])); ?>" style="background: #fff; border: 1px solid var(--gray-l); border-radius: var(--rl); overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.02); padding: 18px; display: flex; flex-direction: column; gap: 14px; transition: border 0.2s;">
            <div style="display: flex; justify-content: space-between; font-size: 10px; font-weight: 700; color: var(--gray); text-transform: uppercase; border-bottom: 1px solid var(--gray-ll); padding-bottom: 8px;">
              <span>📅 <?= date('l, d M Y', strtotime($r['match_date'])); ?> &nbsp;•&nbsp; Matchday <?= (int) $r['matchday']; ?></span>
              <span style="color: var(--org);">🏆 Rwanda Premier League</span>
            </div>
            
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 20px;">
              <!-- Home Team -->
              <div style="display: flex; align-items: center; gap: 14px; flex: 1; justify-content: flex-end;">
                <span style="font-size: 14px; font-weight: 800; color: var(--navy);"><?= e($r['home_name']); ?></span>
                <div style="width: 44px; height: 44px; border-radius: 50%; overflow: hidden; border: 2px solid var(--gray-l); background: #fff; flex-shrink: 0;">
                  <img src="<?= e($r['home_logo'] ?: 'https://images.unsplash.com/photo-1551958219-acbc595d9e15?w=100&q=80'); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;"/>
                </div>
              </div>

              <!-- Score Pill -->
              <div style="background: var(--navy); color: #fff; font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; padding: 6px 20px; border-radius: 30px; letter-spacing: 0.5px; flex-shrink: 0; box-shadow: 0 4px 10px rgba(15,31,75,0.25);">
                <?= (int) $r['home_score']; ?> – <?= (int) $r['away_score']; ?>
              </div>

              <!-- Away Team -->
              <div style="display: flex; align-items: center; gap: 14px; flex: 1;">
                <div style="width: 44px; height: 44px; border-radius: 50%; overflow: hidden; border: 2px solid var(--gray-l); background: #fff; flex-shrink: 0;">
                  <img src="<?= e($r['away_logo'] ?: 'https://images.unsplash.com/photo-1587329310690-91114ac008f0?w=100&q=80'); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;"/>
                </div>
                <span style="font-size: 14px; font-weight: 800; color: var(--navy);"><?= e($r['away_name']); ?></span>
              </div>
            </div>

            <!-- Stats breakdown section -->
            <div style="background: var(--gray-ll); border-radius: var(--r); padding: 10px 14px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; text-align: center; font-size: 11px; font-weight: 700; color: var(--text2);">
              <div> Possession: <strong><?= $r['home_possession_pct'] ?: 50; ?>%</strong> – <strong><?= $r['away_possession_pct'] ?: 50; ?>%</strong></div>
              <div style="border-left: 1px solid var(--gray-l); border-right: 1px solid var(--gray-l);"> Shots: <strong><?= $r['home_shots'] ?: 10; ?></strong> (on target: <strong><?= $r['home_shots_on_target'] ?: 4; ?></strong>) vs <strong><?= $r['away_shots'] ?: 8; ?></strong> (on target: <strong><?= $r['away_shots_on_target'] ?: 3; ?></strong>)</div>
              <div> Corners: <strong><?= $r['home_corners'] ?: 5; ?></strong> – <strong><?= $r['away_corners'] ?: 4; ?></strong> &nbsp;•&nbsp; Fouls: <strong><?= $r['home_fouls'] ?: 12; ?></strong> – <strong><?= $r['away_fouls'] ?: 14; ?></strong></div>
            </div>
            
            <div style="font-size: 10.5px; color: var(--gray); font-weight: 650; display: flex; align-items: center; gap: 4px;">
              <span>📍 Stadium: <strong><?= e($r['stadium_name'] ?: 'National Stadium'); ?></strong></span>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>
