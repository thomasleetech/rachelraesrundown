<?php
/**
 * Rachel Rae's Rundown — article.php
 * Single article view. URL: /article/{slug}
 * .htaccess rewrites: /article/(.+) → article.php?slug=$1
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/tracking.php';

$slug = preg_replace('/[^a-z0-9\-]/', '', $_GET['slug'] ?? '');

if (!$slug) {
    header('Location: /');
    exit;
}

$pdo  = getDB();
$stmt = $pdo->prepare(
    'SELECT a.*, s.display_name AS author_name, s.slug AS author_slug,
            s.job_title AS author_title
     FROM articles a
     LEFT JOIN staff s ON a.author_id = s.id
     WHERE a.slug = ? AND a.status = "live"
     LIMIT 1'
);
$stmt->execute([$slug]);
$article = $stmt->fetch();

if (!$article) {
    http_response_code(404);
    include __DIR__ . '/includes/header.php';
    echo '<div class="wrap" style="padding:80px 40px;text-align:center;">';
    echo '<h1 style="font-family:\'Cormorant Garamond\',serif;font-size:64px;color:var(--burnt-orange);">404</h1>';
    echo '<p style="font-size:18px;margin:20px 0;">This story has been redacted, retracted, or never existed. Probably all three.</p>';
    echo '<a href="/" style="color:var(--teal);">← Back to the chaos</a>';
    echo '</div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Increment view counter
$pdo->prepare('UPDATE articles SET views = views + 1 WHERE id = ?')
    ->execute([$article['id']]);

// Track visit
trackVisit('/article/' . $slug, (int)$article['id']);

// Reaction counts
$reactionCounts = ['like' => 0, 'dislike' => 0, 'share' => 0];
try {
    foreach (['like', 'dislike', 'share'] as $act) {
        $rs = $pdo->prepare('SELECT COUNT(*) FROM article_reactions WHERE article_id = ? AND action_type = ?');
        $rs->execute([$article['id'], $act]);
        $reactionCounts[$act] = (int)$rs->fetchColumn();
    }
} catch (\Throwable $e) {
    // Table may not exist yet
}

// Check user reaction state
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$ip = trim(explode(',', $ip)[0]);
$userLiked = $userDisliked = false;
try {
    foreach (['like' => 'userLiked', 'dislike' => 'userDisliked'] as $act => $var) {
        $rs = $pdo->prepare('SELECT COUNT(*) FROM article_reactions WHERE article_id = ? AND action_type = ? AND ip_address = ?');
        $rs->execute([$article['id'], $act, $ip]);
        $$var = (int)$rs->fetchColumn() > 0;
    }
} catch (\Throwable $e) {}

// Related articles (same section, not this one)
$related = $pdo->prepare(
    'SELECT a.*, s.display_name AS author_name
     FROM articles a LEFT JOIN staff s ON a.author_id = s.id
     WHERE a.status = "live" AND a.section = ? AND a.id != ?
     ORDER BY a.publish_at DESC LIMIT 3'
);
$related->execute([$article['section'], $article['id']]);
$relatedArticles = $related->fetchAll();

// Tags
$tags = json_decode($article['tags'] ?? '[]', true) ?: [];

// Format date
$pubDate = date('F j, Y', strtotime($article['publish_at']));

include __DIR__ . '/includes/header.php';
?>

<div class="wrap" style="max-width:760px;padding-top:40px;">

  <!-- Breadcrumb -->
  <div style="font-family:'DM Mono',monospace;font-size:10px;letter-spacing:0.12em;color:var(--warm-gray);margin-bottom:20px;text-transform:uppercase;">
    <a href="/" style="color:var(--teal);text-decoration:none;">Home</a>
    <span style="margin:0 8px;">›</span>
    <a href="/section/<?= e($article['section']) ?>" style="color:var(--teal);text-decoration:none;"><?= e(ucfirst($article['section'])) ?></a>
  </div>

  <!-- Article header -->
  <article>
    <div class="label label-red" style="margin-bottom:12px;"><?= e(ucfirst($article['section'])) ?></div>

    <h1 style="font-family:'Cormorant Garamond',serif;font-size:clamp(28px,4vw,48px);font-weight:700;line-height:1.1;color:var(--ink);margin-bottom:14px;">
      <?= e($article['headline']) ?>
    </h1>

    <?php if ($article['dek']): ?>
    <p style="font-family:'EB Garamond',serif;font-style:italic;font-size:20px;line-height:1.55;color:var(--warm-gray);margin-bottom:16px;">
      <?= e($article['dek']) ?>
    </p>
    <?php endif; ?>

    <div class="byline" style="padding-bottom:16px;border-bottom:1px solid var(--light-gray);margin-bottom:24px;">
      By <strong><?= e($article['author_name']) ?></strong>
      <?php if ($article['author_title']): ?>
        <span style="color:var(--warm-gray);"> · <?= e($article['author_title']) ?></span>
      <?php endif; ?>
       · <?= $pubDate ?>
      <?php if ($article['views'] > 10): ?>
        <span style="color:var(--warm-gray);margin-left:12px;"><?= number_format($article['views']) ?> views</span>
      <?php endif; ?>
    </div>

    <!-- Hero image / emoji -->
    <div style="width:100%;background:var(--light-gray);margin-bottom:28px;display:flex;align-items:center;justify-content:center;min-height:220px;border:1px solid var(--light-gray);">
      <?php if ($article['hero_image']): ?>
        <img src="/uploads/<?= e($article['hero_image']) ?>" alt="<?= e($article['headline']) ?>" style="width:100%;object-fit:cover;max-height:400px;">
      <?php else: ?>
        <span style="font-size:88px;opacity:0.35;"><?= e($article['hero_emoji'] ?? '📰') ?></span>
      <?php endif; ?>
    </div>

    <!-- Article body -->
    <div style="font-family:'EB Garamond',serif;font-size:18px;line-height:1.85;color:var(--ink);">
      <?= $article['body'] ?>
    </div>

    <!-- Like / Dislike / Share -->
    <div class="article-actions" data-article-id="<?= $article['id'] ?>">
      <button class="action-btn<?= $userLiked ? ' liked' : '' ?>" onclick="react(<?= $article['id'] ?>, 'like', this)" title="Like">
        <span class="icon">&#x1F44D;</span>
        <span class="count" id="count-like"><?= $reactionCounts['like'] ?></span>
      </button>
      <button class="action-btn<?= $userDisliked ? ' disliked' : '' ?>" onclick="react(<?= $article['id'] ?>, 'dislike', this)" title="Dislike">
        <span class="icon">&#x1F44E;</span>
        <span class="count" id="count-dislike"><?= $reactionCounts['dislike'] ?></span>
      </button>
      <span class="action-spacer"></span>
      <button class="action-btn" onclick="shareArticle(<?= $article['id'] ?>, this)" title="Share">
        <span class="icon">&#x1F517;</span>
        <span class="count" id="count-share"><?= $reactionCounts['share'] ?></span>
        <span class="label-text">Share</span>
      </button>
    </div>

    <!-- Tags -->
    <?php if ($tags): ?>
    <div style="margin-top:24px;padding-top:16px;border-top:1px solid var(--light-gray);display:flex;flex-wrap:wrap;gap:8px;">
      <?php foreach ($tags as $tag): ?>
      <a href="/section/<?= e(strtolower($tag)) ?>"
         style="font-family:'DM Mono',monospace;font-size:10px;letter-spacing:0.1em;text-transform:uppercase;padding:3px 10px;border:1px solid var(--light-gray);color:var(--warm-gray);text-decoration:none;">
        <?= e($tag) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Author box -->
    <div style="margin-top:40px;padding:20px;border:1px solid var(--light-gray);background:var(--warm-white);display:flex;gap:16px;align-items:flex-start;">
      <div style="font-size:40px;flex-shrink:0;">✍️</div>
      <div>
        <div style="font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:700;"><?= e($article['author_name']) ?></div>
        <div style="font-family:'DM Mono',monospace;font-size:10px;letter-spacing:0.1em;color:var(--burnt-orange);text-transform:uppercase;margin-bottom:8px;"><?= e($article['author_title'] ?? '') ?></div>
        <p style="font-size:14px;color:var(--warm-gray);">
          <a href="/staff" style="color:var(--teal);">View full staff profile →</a>
        </p>
      </div>
    </div>

  </article>

  <!-- Disclaimer -->
  <div style="margin-top:24px;padding:14px 18px;background:var(--charcoal);font-family:'DM Mono',monospace;font-size:10px;color:#6A6460;letter-spacing:0.06em;line-height:1.7;">
    ✦ Rachel Rae's Rundown is a satire publication. All articles, events, quotes, and named individuals are entirely fictional or constitute parody. Not intended as factual reporting.
  </div>

</div><!-- end wrap -->

<!-- RELATED ARTICLES -->
<?php if ($relatedArticles): ?>
<div style="background:var(--warm-white);border-top:2px solid var(--charcoal);margin-top:48px;padding:40px;">
  <div style="max-width:1380px;margin:0 auto;">
    <div class="sec-head">
      <div class="sec-title">More from <?= e(ucfirst($article['section'])) ?></div>
      <div class="sec-rule"></div>
    </div>
    <div class="g3">
      <?php foreach ($relatedArticles as $r): ?>
      <div class="card">
        <a href="/article/<?= e($r['slug']) ?>" style="text-decoration:none;display:block;">
          <div class="card-img" style="font-size:40px;"><?= e($r['hero_emoji'] ?? '📰') ?></div>
        </a>
        <h3>
          <a href="/article/<?= e($r['slug']) ?>" style="color:inherit;text-decoration:none;">
            <?= e($r['headline']) ?>
          </a>
        </h3>
        <a href="/article/<?= e($r['slug']) ?>" class="card-dek-link"><?= e($r['dek']) ?></a>
        <div class="byline">By <strong><?= e($r['author_name']) ?></strong></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
async function react(articleId, action, btn) {
  try {
    const resp = await fetch('/api/reaction.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({article_id: articleId, action: action})
    });
    const data = await resp.json();
    if (data.counts) {
      document.getElementById('count-like').textContent = data.counts.like;
      document.getElementById('count-dislike').textContent = data.counts.dislike;
      document.getElementById('count-share').textContent = data.counts.share;
    }
    if (data.user !== undefined) {
      document.querySelectorAll('.action-btn').forEach(b => {
        b.classList.remove('liked', 'disliked');
      });
      if (data.user.like) btn.closest('.article-actions').querySelector('[title="Like"]').classList.add('liked');
      if (data.user.dislike) btn.closest('.article-actions').querySelector('[title="Dislike"]').classList.add('disliked');
    }
  } catch (e) {}
}

async function shareArticle(articleId, btn) {
  const url = window.location.href;
  const title = document.title;
  if (navigator.share) {
    try {
      await navigator.share({title: title, url: url});
    } catch (e) {
      if (e.name === 'AbortError') return;
    }
  } else {
    await navigator.clipboard.writeText(url).catch(() => {});
    btn.querySelector('.label-text').textContent = 'Copied!';
    setTimeout(() => btn.querySelector('.label-text').textContent = 'Share', 2000);
  }
  // Record share action
  fetch('/api/reaction.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({article_id: articleId, action: 'share'})
  }).then(r => r.json()).then(data => {
    if (data.counts) document.getElementById('count-share').textContent = data.counts.share;
  }).catch(() => {});
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
