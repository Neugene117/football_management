<section style="background: var(--off); min-height: 600px; padding: 24px 20px;">
  <div style="max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
    
    <!-- Top Goal Scorers -->
    <div style="background: #fff; border: 1px solid var(--gray-l); border-radius: var(--rl); overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.02);">
      <div style="background: var(--navy); color: #fff; padding: 14px 18px; font-family: 'Barlow Condensed', sans-serif; font-size: 15px; font-weight: 800; text-transform: uppercase; display: flex; justify-content: space-between; align-items: center;">
        <span>⚽ Season Top Scorers</span>
        <span style="color: var(--org);">Goals</span>
      </div>
      <div style="padding: 14px; display: flex; flex-direction: column; gap: 8px;">
        <?php 
        $scorers = db_fetch_all("SELECT p.*, t.name team_name, ps.goals FROM players p JOIN teams t ON t.id=p.team_id JOIN player_statistics ps ON ps.player_id=p.id WHERE ps.goals > 0 ORDER BY ps.goals DESC LIMIT 5");
        if (empty($scorers)): ?>
          <div style="color: var(--text3); text-align: center; padding: 20px;">No goals recorded yet.</div>
        <?php else:
          foreach ($scorers as $idx => $s): ?>
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px; border-bottom: 1px solid var(--gray-ll);">
              <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-family: 'Barlow Condensed', sans-serif; font-weight: 900; font-size: 14px; color: var(--gray); width: 14px;"><?= $idx + 1; ?></span>
                <div style="width: 32px; height: 32px; border-radius: 50%; overflow: hidden; border: 1.5px solid var(--gray-l); background: #fff;">
                  <img src="<?= e($s['photo_pl'] ?: 'https://images.unsplash.com/photo-1627483297886-49710ae1fc22?w=80&q=80'); ?>" style="width:100%; height:100%; object-fit:cover; object-position:top;"/>
                </div>
                <div>
                  <strong style="font-size: 12.5px; color: var(--navy);"><?= e($s['first_name'] . ' ' . $s['last_name']); ?></strong>
                  <div style="font-size: 9.5px; color: var(--gray); font-weight: 600;"><?= e($s['team_name']); ?></div>
                </div>
              </div>
              <span style="font-family: 'Barlow Condensed', sans-serif; font-weight: 900; font-size: 18px; color: var(--org);"><?= (int) $s['goals']; ?></span>
            </div>
          <?php endforeach; 
        endif; ?>
      </div>
    </div>

    <!-- Top Assists Providers -->
    <div style="background: #fff; border: 1px solid var(--gray-l); border-radius: var(--rl); overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.02);">
      <div style="background: var(--navy); color: #fff; padding: 14px 18px; font-family: 'Barlow Condensed', sans-serif; font-size: 15px; font-weight: 800; text-transform: uppercase; display: flex; justify-content: space-between; align-items: center;">
        <span>👟 Season Assist Leaders</span>
        <span style="color: #15803d;">Assists</span>
      </div>
      <div style="padding: 14px; display: flex; flex-direction: column; gap: 8px;">
        <?php 
        $assists = db_fetch_all("SELECT p.*, t.name team_name, ps.assists FROM players p JOIN teams t ON t.id=p.team_id JOIN player_statistics ps ON ps.player_id=p.id WHERE ps.assists > 0 ORDER BY ps.assists DESC LIMIT 5");
        if (empty($assists)): ?>
          <div style="color: var(--text3); text-align: center; padding: 20px;">No assists recorded yet.</div>
        <?php else:
          foreach ($assists as $idx => $s): ?>
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px; border-bottom: 1px solid var(--gray-ll);">
              <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-family: 'Barlow Condensed', sans-serif; font-weight: 900; font-size: 14px; color: var(--gray); width: 14px;"><?= $idx + 1; ?></span>
                <div style="width: 32px; height: 32px; border-radius: 50%; overflow: hidden; border: 1.5px solid var(--gray-l); background: #fff;">
                  <img src="<?= e($s['photo_pl'] ?: 'https://images.unsplash.com/photo-1627483297886-49710ae1fc22?w=80&q=80'); ?>" style="width:100%; height:100%; object-fit:cover; object-position:top;"/>
                </div>
                <div>
                  <strong style="font-size: 12.5px; color: var(--navy);"><?= e($s['first_name'] . ' ' . $s['last_name']); ?></strong>
                  <div style="font-size: 9.5px; color: var(--gray); font-weight: 600;"><?= e($s['team_name']); ?></div>
                </div>
              </div>
              <span style="font-family: 'Barlow Condensed', sans-serif; font-weight: 900; font-size: 18px; color: #15803d;"><?= (int) $s['assists']; ?></span>
            </div>
          <?php endforeach; 
        endif; ?>
      </div>
    </div>

    <!-- Highest Rated Players -->
    <div style="background: #fff; border: 1px solid var(--gray-l); border-radius: var(--rl); overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.02);">
      <div style="background: var(--navy); color: #fff; padding: 14px 18px; font-family: 'Barlow Condensed', sans-serif; font-size: 15px; font-weight: 800; text-transform: uppercase; display: flex; justify-content: space-between; align-items: center;">
        <span>⭐ Top Performance Ratings</span>
        <span style="color: #1e3a8a;">Rating</span>
      </div>
      <div style="padding: 14px; display: flex; flex-direction: column; gap: 8px;">
        <?php 
        $ratings = db_fetch_all("SELECT p.*, t.name team_name, ps.average_rating FROM players p JOIN teams t ON t.id=p.team_id JOIN player_statistics ps ON ps.player_id=p.id ORDER BY ps.average_rating DESC LIMIT 5");
        if (empty($ratings)): ?>
          <div style="color: var(--text3); text-align: center; padding: 20px;">No ratings recorded yet.</div>
        <?php else:
          foreach ($ratings as $idx => $s): ?>
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px; border-bottom: 1px solid var(--gray-ll);">
              <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-family: 'Barlow Condensed', sans-serif; font-weight: 900; font-size: 14px; color: var(--gray); width: 14px;"><?= $idx + 1; ?></span>
                <div style="width: 32px; height: 32px; border-radius: 50%; overflow: hidden; border: 1.5px solid var(--gray-l); background: #fff;">
                  <img src="<?= e($s['photo_pl'] ?: 'https://images.unsplash.com/photo-1627483297886-49710ae1fc22?w=80&q=80'); ?>" style="width:100%; height:100%; object-fit:cover; object-position:top;"/>
                </div>
                <div>
                  <strong style="font-size: 12.5px; color: var(--navy);"><?= e($s['first_name'] . ' ' . $s['last_name']); ?></strong>
                  <div style="font-size: 9.5px; color: var(--gray); font-weight: 600;"><?= e($s['team_name']); ?></div>
                </div>
              </div>
              <span style="font-family: 'Barlow Condensed', sans-serif; font-weight: 900; font-size: 18px; color: #1e3a8a;"><?= (int) $s['average_rating']; ?></span>
            </div>
          <?php endforeach; 
        endif; ?>
      </div>
    </div>

  </div>
</section>
