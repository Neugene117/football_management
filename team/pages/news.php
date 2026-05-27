<?php
$rows = db_fetch_all('SELECT n.*, u.full_name author_name FROM news n LEFT JOIN users u ON u.id=n.author_id WHERE n.is_published = 1 ORDER BY n.published_at DESC LIMIT 24');
?>

<div class="card">
  <div class="card-head"><h3>Federation News</h3></div>
  <div class="card-body">
    <?php if (empty($rows)): ?>
      <div class="empty-state">No published news.</div>
    <?php else: ?>
      <div class="grid news-grid">
        <?php foreach ($rows as $n): ?>
          <article class="card news-card">
            <div class="card-body">
              <h4 class="news-title"><?= e($n['title']); ?></h4>
              <p class="muted news-excerpt"><?= e(mb_strimwidth(strip_tags((string) $n['content']), 0, 130, '...')); ?></p>
              <div class="small muted">By <?= e($n['author_name'] ?: 'Federation'); ?> • <?= e($n['published_at'] ?: '-'); ?></div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
