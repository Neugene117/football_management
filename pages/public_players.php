<?php
$playersList = db_fetch_all("
    SELECT p.*, t.name AS team_name, t.logo AS team_logo, ps.average_rating, ps.goals, ps.assists
    FROM players p
    INNER JOIN teams t ON t.id = p.team_id
    LEFT JOIN player_statistics ps ON ps.player_id = p.id
    WHERE p.status = 'active'
    ORDER BY p.first_name ASC, p.last_name ASC
");
?>
<section style="background: var(--off); min-height: 600px; padding: 24px 20px;">
  <div style="max-width: 1200px; margin: 0 auto;">
    
    <!-- Players Filters -->
    <div style="background: #fff; border: 1px solid var(--gray-l); border-radius: var(--rl); padding: 18px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); display: flex; gap: 15px; justify-content: space-between; align-items: center; flex-wrap: wrap;">
      <div style="display: flex; gap: 10px; flex: 1; min-width: 300px;">
        <div style="position: relative; flex: 1;">
          <input type="text" id="player-search" placeholder="Search players by name, jersey, team or nationality..." style="width: 100%; padding: 10px 14px 10px 36px; border: 1px solid var(--gray-l); border-radius: var(--r); outline: none; font-family: inherit; font-size: 13px;" oninput="filterPlayers()"/>
          <svg style="position: absolute; left: 12px; top: 12px; width: 14px; height: 14px; color: var(--gray); z-index: 10;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        </div>
      </div>
      <div style="display: flex; gap: 6px;">
        <button class="btn-p" style="padding: 8px 14px; font-size: 11px; font-weight: 700;" onclick="setPlayerPosFilter('all')">All Positions</button>
        <button class="btn-s" style="padding: 8px 14px; font-size: 11px; font-weight: 700; color: var(--text); border-color: var(--gray-l);" onclick="setPlayerPosFilter('goalkeeper')">Goalkeepers</button>
        <button class="btn-s" style="padding: 8px 14px; font-size: 11px; font-weight: 700; color: var(--text); border-color: var(--gray-l);" onclick="setPlayerPosFilter('defender')">Defenders</button>
        <button class="btn-s" style="padding: 8px 14px; font-size: 11px; font-weight: 700; color: var(--text); border-color: var(--gray-l);" onclick="setPlayerPosFilter('midfielder')">Midfielders</button>
        <button class="btn-s" style="padding: 8px 14px; font-size: 11px; font-weight: 700; color: var(--text); border-color: var(--gray-l);" onclick="setPlayerPosFilter('forward')">Forwards</button>
      </div>
    </div>

    <!-- Players Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(172px, 1fr)); gap: 16px;" id="players-grid-container">
      <?php if (empty($playersList)): ?>
        <div style="color: var(--text3); text-align: center; grid-column: 1 / -1; padding: 40px;">No registered active players found.</div>
      <?php else: ?>
        <?php foreach ($playersList as $pl): 
          $posType = mapPositionType($pl['position']);
          $posAbbrev = mapPositionAbbrev($pl['position']);
          $pClass = $posType === 'gk' ? 'gk-b' : ($posType === 'def' ? 'def-b' : ($posType === 'mid' ? 'mid-b' : 'fwd-b'));
          $pColor = $posType === 'gk' ? '#F97316' : ($posType === 'def' ? '#1E3A8A' : ($posType === 'mid' ? '#15803d' : '#dc2626'));
          $rating = (int) ($pl['average_rating'] ?: 75);
        ?>
          <div class="player-card" data-pos="<?= $posType; ?>" data-search="<?= e(strtolower($pl['first_name'] . ' ' . $pl['last_name'] . ' ' . $pl['team_name'] . ' ' . $pl['nationality'] . ' ' . $pl['position'])); ?>" style="background: #fff; border: 1px solid var(--gray-l); border-radius: var(--rl); overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.02); transition: transform 0.2s; text-align: center;">
            <div class="pc-img" style="height: 100px; position: relative; overflow: hidden;">
              <img src="<?= e($pl['photo_pl'] ?: 'https://images.unsplash.com/photo-1627483297886-49710ae1fc22?w=300&q=80'); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover; object-position: top;"/>
              <div class="pc-img-ov" style="background: linear-gradient(to top, <?= $pColor; ?>bb, transparent); position: absolute; inset: 0;"></div>
              <div style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.6); color: #fff; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; border: 1px solid rgba(255,255,255,0.25);">#<?= (int) $pl['jersey_number']; ?></div>
            </div>
            <div class="pc-pos-bar" style="background: <?= $pColor; ?>; height: 3px;"></div>
            <div class="pc-body" style="padding: 12px;">
              <div class="pc-avatar-wrap" style="width: 48px; height: 48px; border-radius: 50%; border: 3px solid #fff; overflow: hidden; margin: -30px auto 6px; box-shadow: 0 3px 8px rgba(0,0,0,0.15); background: #fff;">
                <img src="<?= e($pl['photo_pl'] ?: 'https://images.unsplash.com/photo-1627483297886-49710ae1fc22?w=100&q=80'); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; object-position: top;"/>
              </div>
              <h4 style="font-size: 12.5px; font-weight: 800; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= e($pl['first_name'] . ' ' . $pl['last_name']); ?></h4>
              <div style="font-size: 9.5px; color: var(--gray); font-weight: 700; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.2px;"><?= e($pl['team_name']); ?></div>
              
              <div style="display: flex; align-items: center; justify-content: center; gap: 4px; margin-bottom: 8px;">
                <span class="pc-pos-badge <?= $pClass; ?>" style="font-size: 8px; font-weight: 700; padding: 2px 6px; border-radius: 4px; text-transform: uppercase;"><?= $posAbbrev; ?></span>
                <span style="font-size: 9.5px; color: var(--text2); background: var(--gray-ll); padding: 2px 6px; border-radius: 4px; font-weight: 700;">🌐 <?= e($pl['nationality'] ?: 'Rwanda'); ?></span>
              </div>

              <!-- Stats Bar -->
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px; background: var(--gray-ll); border-radius: 6px; padding: 4px; font-size: 9px; font-weight: 700; color: var(--text2); margin-bottom: 8px;">
                <div style="border-right: 1px solid var(--gray-l);">⚽ <?= (int) $pl['goals']; ?> Goals</div>
                <div>👟 <?= (int) $pl['assists']; ?> Asts</div>
              </div>

              <div class="pc-rating" style="font-family: 'Barlow Condensed', sans-serif; font-size: 20px; font-weight: 900; color: var(--text); line-height: 1;"><?= $rating; ?><span style="font-size: 10px; color: var(--text3); font-family: inherit; font-weight: 500;">/100 RATING</span></div>
              <div class="pc-bar-wrap" style="background: var(--gray-ll); border-radius: 3px; height: 3px; margin-top: 5px; overflow: hidden;">
                <div class="pc-bar-fill" style="width: <?= $rating; ?>%; background: <?= $pColor; ?>; height: 100%;"></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>
