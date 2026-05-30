<?php
$teamsList = db_fetch_all("
    SELECT t.*, s.name AS stadium_name, s.city AS stadium_city,
           (SELECT COUNT(*) FROM players WHERE team_id = t.id AND status = 'active') AS player_count
    FROM teams t
    LEFT JOIN stadiums s ON s.id = t.home_stadium_id
    ORDER BY t.name ASC
");
?>
<section style="background: var(--off); min-height: 600px; padding: 24px 20px;">
  <div style="max-width: 1200px; margin: 0 auto;">
    
    <!-- Teams Search -->
    <div style="background: #fff; border: 1px solid var(--gray-l); border-radius: var(--rl); padding: 18px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); display: flex; gap: 15px; justify-content: space-between; align-items: center; flex-wrap: wrap;">
      <h3 style="font-size: 15px; font-weight: 800; color: var(--navy); text-transform: uppercase;">Professional League Clubs</h3>
      <div style="position: relative; width: 300px;">
        <input type="text" id="team-search" placeholder="Filter clubs by name or city..." style="width: 100%; padding: 10px 14px 10px 36px; border: 1px solid var(--gray-l); border-radius: var(--r); outline: none; font-family: inherit; font-size: 13px;" oninput="filterTeams()"/>
        <svg style="position: absolute; left: 12px; top: 12px; width: 14px; height: 14px; color: var(--gray); z-index: 10;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      </div>
    </div>

    <!-- Teams Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px;" id="teams-grid-container">
      <?php if (empty($teamsList)): ?>
        <div style="color: var(--text3); text-align: center; grid-column: 1 / -1; padding: 40px;">No registered clubs found.</div>
      <?php else: ?>
        <?php foreach ($teamsList as $team): ?>
          <div class="team-card" data-search="<?= e(strtolower($team['name'] . ' ' . $team['city'] . ' ' . $team['stadium_name'])); ?>" style="background: #fff; border: 1px solid var(--gray-l); border-radius: var(--rl); overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.02); transition: transform 0.2s;">
            <div class="tc-cover" style="height: 70px; background: linear-gradient(135deg, var(--navy) 0%, var(--navy-m) 100%);"></div>
            <div class="tc-body" style="padding: 16px; text-align: center; position: relative;">
              <div class="tc-logo" style="width: 58px; height: 58px; border: 3px solid #fff; border-radius: 50%; overflow: hidden; margin: -44px auto 8px; background: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                <img src="<?= e($team['logo'] ?: 'https://images.unsplash.com/photo-1551958219-acbc595d9e15?w=120&q=80'); ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: cover;"/>
              </div>
              <h4 style="font-size: 14px; font-weight: 800; color: var(--text); margin-bottom: 2px;"><?= e($team['name']); ?></h4>
              <div style="font-size: 11px; color: var(--gray); font-weight: 600; margin-bottom: 10px;">📍 <?= e($team['city'] ?: 'Kigali'); ?></div>
              
              <div style="display: flex; justify-content: center; gap: 10px; margin-bottom: 12px; font-size: 10px; font-weight: 700; color: var(--text2);">
                <span style="background: var(--gray-ll); padding: 4px 8px; border-radius: 4px;">🏟️ <?= e($team['stadium_name'] ?: 'Home Arena'); ?></span>
                <span style="background: var(--org-xl); color: var(--org-d); padding: 4px 8px; border-radius: 4px;">🏃‍♂️ <?= (int) $team['player_count']; ?> Players</span>
              </div>
              <span class="tc-badge <?= $team['is_active'] ? 'ab' : 'ib'; ?>" style="font-size: 9px; font-weight: 700; padding: 2px 8px; border-radius: 4px;"><?= $team['is_active'] ? 'Active' : 'Inactive'; ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>
