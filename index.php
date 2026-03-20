<?php
/**
 * Rachel Rae's Rundown — index.php
 * Homepage: pulls latest live articles from DB, renders the newspaper layout.
 * Layout matches the prototype at rachelmoreno.com
 */
require_once __DIR__ . '/config.php';

$pdo = getDB();

// ---- Breaking news ----
$breakingId = getSetting('breaking_story_id');
$breaking   = null;
if ($breakingId) {
    $stmt = $pdo->prepare(
        'SELECT a.*, s.display_name AS author_name
         FROM articles a LEFT JOIN staff s ON a.author_id = s.id
         WHERE a.id = ? AND a.status = "live"'
    );
    $stmt->execute([(int)$breakingId]);
    $breaking = $stmt->fetch();
}

// ---- Hero story (most recent featured or latest live) ----
$hero = $pdo->query(
    'SELECT a.*, s.display_name AS author_name
     FROM articles a LEFT JOIN staff s ON a.author_id = s.id
     WHERE a.status = "live"
     ORDER BY a.is_featured DESC, a.publish_at DESC LIMIT 1'
)->fetch();

$heroId = $hero['id'] ?? 0;

// ---- Sidebar stories (next 3) ----
$sidebarStmt = $pdo->prepare(
    'SELECT a.*, s.display_name AS author_name
     FROM articles a LEFT JOIN staff s ON a.author_id = s.id
     WHERE a.status = "live" AND a.id != ?
     ORDER BY a.publish_at DESC LIMIT 3'
);
$sidebarStmt->execute([$heroId]);
$sidebar = $sidebarStmt->fetchAll();

// ---- Section grids ----
function getSectionArticles(PDO $pdo, string $section, int $limit = 3, int $excludeId = 0): array {
    $stmt = $pdo->prepare(
        'SELECT a.*, s.display_name AS author_name
         FROM articles a LEFT JOIN staff s ON a.author_id = s.id
         WHERE a.status = "live" AND a.section = ? AND a.id != ?
         ORDER BY a.publish_at DESC LIMIT ?'
    );
    $stmt->execute([$section, $excludeId, $limit]);
    return $stmt->fetchAll();
}

$politicsArticles = getSectionArticles($pdo, 'politics', 3, $heroId);
$cultureArticles  = getSectionArticles($pdo, 'culture', 4, $heroId);
$religionArticles = getSectionArticles($pdo, 'religion', 3, $heroId);
$lgbtqArticles    = getSectionArticles($pdo, 'lgbtq', 3, $heroId);

// ---- Opinion articles (latest 3 opinion pieces) ----
$opinionArticles = $pdo->prepare(
    'SELECT a.*, s.display_name AS author_name, s.job_title AS author_role, s.headshot_svg
     FROM articles a LEFT JOIN staff s ON a.author_id = s.id
     WHERE a.status = "live" AND a.section = "opinion" AND a.id != ?
     ORDER BY a.publish_at DESC LIMIT 3'
);
$opinionArticles->execute([$heroId]);
$opinionArticles = $opinionArticles->fetchAll();

// ---- Late-breaking (latest articles not already shown) ----
$usedIds = array_merge([$heroId], array_column($sidebar, 'id'), array_column($politicsArticles, 'id'));
$placeholders = implode(',', array_fill(0, count($usedIds), '?'));
$lbStmt = $pdo->prepare(
    "SELECT a.*, s.display_name AS author_name
     FROM articles a LEFT JOIN staff s ON a.author_id = s.id
     WHERE a.status = 'live' AND a.id NOT IN ($placeholders)
     ORDER BY a.publish_at DESC LIMIT 4"
);
$lbStmt->execute($usedIds);
$lbArticles = $lbStmt->fetchAll();

// ---- Marquee headlines ----
$marqueeArticles = $pdo->query(
    "SELECT headline, slug FROM articles WHERE status = 'live'
     ORDER BY publish_at DESC LIMIT 8"
)->fetchAll();

// ---- Ticker headlines ----
$tickerArticles = $pdo->query(
    'SELECT headline, slug FROM articles WHERE status = "live"
     ORDER BY publish_at DESC LIMIT 15'
)->fetchAll();

$tickerText = implode(' &nbsp;◆&nbsp; ', array_map(
    fn($a) => '<a href="/article/' . e($a['slug']) . '">' . e($a['headline']) . '</a>',
    $tickerArticles
));

// ---- Format publish date ----
function fmtDate(string $dt): string {
    return date('F j, Y', strtotime($dt));
}

// ---- Label color helper ----
function sectionLabelClass(string $section): string {
    return match($section) {
        'politics', 'elections', 'national', 'crime' => 'label-red',
        'lgbtq', 'tech' => 'label-teal',
        'fashion', 'religion' => 'label-gold',
        default => 'label-dark',
    };
}

include __DIR__ . '/includes/header.php';
?>

<?php if ($breaking): ?>
<div class="breaking-bar">
  <div class="break-pill">⚡ Breaking</div>
  <div class="break-text">
    <a href="/article/<?= e($breaking['slug']) ?>" style="color:inherit;text-decoration:none;">
      <?= e($breaking['headline']) ?>
    </a>
  </div>
</div>
<?php endif; ?>

<!-- MARQUEE -->
<?php if ($marqueeArticles): ?>
<div class="marquee-wrap">
  <div class="marquee-inner">
    <?php foreach ($marqueeArticles as $i => $m): ?>
      <span><?= e($m['headline']) ?></span><?php if ($i < count($marqueeArticles) - 1): ?><span class="sep"> ✦ </span><?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- HERO -->
<?php if ($hero): ?>
<div class="hero-outer">
  <div class="hero-main">
    <div class="label label-red"><?= e(ucfirst($hero['section'])) ?></div>
    <h1 class="hero-headline">
      <a href="/article/<?= e($hero['slug']) ?>" style="color:inherit;text-decoration:none;">
        <?= e($hero['headline']) ?>
      </a>
    </h1>
    <a href="/article/<?= e($hero['slug']) ?>" style="text-decoration:none;display:block;">
      <div class="hero-img-box">
        <?php if ($hero['hero_image']): ?>
          <img src="/uploads/<?= e($hero['hero_image']) ?>" alt="<?= e($hero['headline']) ?>" style="width:100%;height:100%;object-fit:cover;">
        <?php else: ?>
          <div class="img-placeholder">
            <span class="icon"><?= e($hero['hero_emoji'] ?? '📰') ?></span>
          </div>
        <?php endif; ?>
      </div>
    </a>
    <a href="/article/<?= e($hero['slug']) ?>" class="hero-dek-link"><?= e($hero['dek']) ?></a>
    <div class="byline">By <strong><?= e($hero['author_name']) ?></strong> · <?= e(ucfirst($hero['section'])) ?> · <?= fmtDate($hero['publish_at']) ?></div>
  </div>
  <div class="hero-sidebar">
    <?php foreach ($sidebar as $s): ?>
    <a href="/article/<?= e($s['slug']) ?>" class="sidebar-item-link">
      <div class="sidebar-item">
        <div class="label <?= sectionLabelClass($s['section']) ?>"><?= e(ucfirst($s['section'])) ?></div>
        <div class="sidebar-hed"><?= e($s['headline']) ?></div>
        <div class="sidebar-dek"><?= e($s['dek']) ?></div>
        <div class="byline" style="margin-top:8px;">By <strong><?= e($s['author_name']) ?></strong></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- POLITICS & POWER -->
<div class="wrap">
  <?php if ($politicsArticles): ?>
  <div class="sec-head">
    <div class="sec-title">Politics &amp; Power</div>
    <div class="sec-rule"></div>
    <div class="label label-red">Washington &amp; Beyond</div>
  </div>
  <div class="g3">
    <?php foreach ($politicsArticles as $a): ?>
    <div class="card">
      <a href="/article/<?= e($a['slug']) ?>" style="text-decoration:none;display:block;">
        <div class="card-img" style="background:#A8301820; font-size:50px;"><?= e($a['hero_emoji'] ?? '🏛️') ?></div>
      </a>
      <div class="label label-red"><?= e(ucfirst($a['section'])) ?></div>
      <h3><a href="/article/<?= e($a['slug']) ?>" style="color:inherit;text-decoration:none;"><?= e($a['headline']) ?></a></h3>
      <a href="/article/<?= e($a['slug']) ?>" class="card-dek-link"><?= e($a['dek']) ?></a>
      <div class="byline">By <strong><?= e($a['author_name']) ?></strong> · <?= fmtDate($a['publish_at']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- OPINION STRIP -->
<?php if ($opinionArticles): ?>
<div class="op-strip">
  <div class="op-strip-head">✦ &nbsp; Opinion &amp; Commentary &nbsp; ✦</div>
  <div class="op-grid">
    <?php foreach ($opinionArticles as $op): ?>
    <div class="op-card">
      <div class="op-meta">
        <div class="op-ava" style="background:var(--burnt-orange);">
          <?php if (!empty($op['headshot_svg'])): ?>
            <?= $op['headshot_svg'] ?>
          <?php endif; ?>
        </div>
        <div>
          <div class="op-name"><?= e($op['author_name'] ?? 'Staff') ?></div>
          <div class="op-role"><?= e($op['author_role'] ?? '') ?></div>
        </div>
      </div>
      <div class="op-hed"><a href="/article/<?= e($op['slug']) ?>" style="color:inherit;text-decoration:none;"><?= e($op['headline']) ?></a></div>
      <a href="/article/<?= e($op['slug']) ?>" class="op-body-link"><?= e($op['dek']) ?></a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- CULTURE & CATASTROPHE -->
<div class="wrap">
  <?php if ($cultureArticles): ?>
  <div class="sec-head" style="margin-top:36px;">
    <div class="sec-title">Culture &amp; Catastrophe</div>
    <div class="sec-rule"></div>
    <div class="label label-dark">Pop Culture · Arts · Tech</div>
  </div>
  <div class="g4">
    <?php foreach ($cultureArticles as $a): ?>
    <div class="card">
      <a href="/article/<?= e($a['slug']) ?>" style="text-decoration:none;display:block;">
        <div class="card-img" style="background:#CC807020; font-size:44px;"><?= e($a['hero_emoji'] ?? '🎭') ?></div>
      </a>
      <div class="label <?= sectionLabelClass($a['section']) ?>"><?= e(ucfirst($a['section'])) ?></div>
      <h3><a href="/article/<?= e($a['slug']) ?>" style="color:inherit;text-decoration:none;"><?= e($a['headline']) ?></a></h3>
      <a href="/article/<?= e($a['slug']) ?>" class="card-dek-link"><?= e($a['dek']) ?></a>
      <div class="byline">By <strong><?= e($a['author_name']) ?></strong> · <?= fmtDate($a['publish_at']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- LATE BREAKING STRIP -->
<?php if ($lbArticles): ?>
<div class="lb-strip">
  <div class="lb-head">✦ &nbsp; Late Breaking &nbsp; ✦</div>
  <div class="lb-grid">
    <?php foreach ($lbArticles as $lb): ?>
    <a href="/article/<?= e($lb['slug']) ?>" style="text-decoration:none;display:block;">
      <div class="lb-hed"><?= e($lb['headline']) ?></div>
      <div class="lb-dek"><?= e($lb['dek']) ?></div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- RELIGION & THE ALMIGHTY -->
<div class="wrap">
  <?php if ($religionArticles): ?>
  <div class="sec-head" style="margin-top:36px;">
    <div class="sec-title">Religion &amp; The Almighty</div>
    <div class="sec-rule"></div>
    <div class="label label-gold">Faith · Doubt · Mercantile Salvation</div>
  </div>
  <div class="g3">
    <?php foreach ($religionArticles as $a): ?>
    <div class="card">
      <a href="/article/<?= e($a['slug']) ?>" style="text-decoration:none;display:block;">
        <div class="card-img" style="background:#27216130; font-size:46px;"><?= e($a['hero_emoji'] ?? '⛪') ?></div>
      </a>
      <div class="label label-gold"><?= e(ucfirst($a['section'])) ?></div>
      <h3><a href="/article/<?= e($a['slug']) ?>" style="color:inherit;text-decoration:none;"><?= e($a['headline']) ?></a></h3>
      <a href="/article/<?= e($a['slug']) ?>" class="card-dek-link"><?= e($a['dek']) ?></a>
      <div class="byline">By <strong><?= e($a['author_name']) ?></strong> · <?= fmtDate($a['publish_at']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- PREMIUM CTA -->
  <div class="premium-cta">
    <div class="premium-cta-inner">
      <div class="premium-cta-badge">✦ PREMIUM ✦</div>
      <h2 class="premium-cta-title">Rachel Rae's <em>Rundown</em> — Premium</h2>
      <p class="premium-cta-text">Unlock more chaos, deeper satire, and exclusive access to our "Letters to Nobody" archive.<br>Cancel anytime. We won't take it personally. <em>(We will take it personally.)</em></p>
      <div class="premium-cta-price">$4.20<span>/mo</span></div>
      <a href="/subscribe" class="premium-cta-btn">Subscribe Now</a>
    </div>
  </div>

  <!-- LGBTQ+ / PRIDE & PREJUDICE -->
  <?php if ($lgbtqArticles): ?>
  <div class="sec-head">
    <div class="sec-title">Pride &amp; Prejudice</div>
    <div class="sec-rule"></div>
    <div class="label label-teal">LGBTQ+ Coverage</div>
  </div>
  <div class="g3">
    <?php foreach ($lgbtqArticles as $a): ?>
    <div class="card">
      <a href="/article/<?= e($a['slug']) ?>" style="text-decoration:none;display:block;">
        <div class="card-img" style="background:#276B6130; font-size:46px;"><?= e($a['hero_emoji'] ?? '🏳️‍🌈') ?></div>
      </a>
      <div class="label label-teal"><?= e(ucfirst($a['section'])) ?></div>
      <h3><a href="/article/<?= e($a['slug']) ?>" style="color:inherit;text-decoration:none;"><?= e($a['headline']) ?></a></h3>
      <a href="/article/<?= e($a['slug']) ?>" class="card-dek-link"><?= e($a['dek']) ?></a>
      <div class="byline">By <strong><?= e($a['author_name']) ?></strong> · <?= fmtDate($a['publish_at']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div><!-- end wrap -->

<?php include __DIR__ . '/includes/footer.php'; ?>
