<?php
/**
 * Rachel Rae's Rundown — index.php
 * Homepage: pulls latest live articles from DB, renders the newspaper layout.
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

// ---- Sidebar stories (next 3) ----
$sidebarStmt = $pdo->prepare(
    'SELECT a.*, s.display_name AS author_name
     FROM articles a LEFT JOIN staff s ON a.author_id = s.id
     WHERE a.status = "live" AND a.id != ?
     ORDER BY a.publish_at DESC LIMIT 3'
);
$sidebarStmt->execute([$hero['id'] ?? 0]);
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

$politicsArticles = getSectionArticles($pdo, 'politics', 3, $hero['id'] ?? 0);
$cultureArticles  = getSectionArticles($pdo, 'culture', 4, $hero['id'] ?? 0);
$religionArticles = getSectionArticles($pdo, 'religion', 3, $hero['id'] ?? 0);
$lgbtqArticles    = getSectionArticles($pdo, 'lgbtq', 3, $hero['id'] ?? 0);
$lbArticles       = $pdo->query(
    'SELECT a.*, s.display_name AS author_name
     FROM articles a LEFT JOIN staff s ON a.author_id = s.id
     WHERE a.status = "live"
     ORDER BY a.publish_at DESC LIMIT 8 OFFSET 4'
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
    <div class="hero-img-box">
      <?php if ($hero['hero_image']): ?>
        <img src="/uploads/<?= e($hero['hero_image']) ?>" alt="<?= e($hero['headline']) ?>" style="width:100%;height:100%;object-fit:cover;">
      <?php else: ?>
        <div class="img-placeholder">
          <span class="icon"><?= e($hero['hero_emoji'] ?? '📰') ?></span>
        </div>
      <?php endif; ?>
    </div>
    <div class="hero-dek"><?= e($hero['dek']) ?></div>
    <div class="byline">By <strong><?= e($hero['author_name']) ?></strong> · <?= fmtDate($hero['publish_at']) ?></div>
  </div>
  <div class="hero-sidebar">
    <?php foreach ($sidebar as $s): ?>
    <div class="sidebar-item">
      <div class="label label-dark"><?= e(ucfirst($s['section'])) ?></div>
      <a href="/article/<?= e($s['slug']) ?>" style="text-decoration:none;">
        <div class="sidebar-hed"><?= e($s['headline']) ?></div>
      </a>
      <div class="sidebar-dek"><?= e($s['dek']) ?></div>
      <div class="byline" style="margin-top:8px;">By <strong><?= e($s['author_name']) ?></strong></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="wrap">

  <!-- POLITICS -->
  <?php if ($politicsArticles): ?>
  <div class="sec-head">
    <div class="sec-title">Politics &amp; Power</div>
    <div class="sec-rule"></div>
    <div class="label label-red">Washington &amp; Beyond</div>
  </div>
  <div class="g3">
    <?php foreach ($politicsArticles as $a): ?>
    <div class="card">
      <div class="card-img" style="font-size:44px;"><?= e($a['hero_emoji'] ?? '📰') ?></div>
      <div class="label label-red"><?= e(ucfirst($a['section'])) ?></div>
      <h3><a href="/article/<?= e($a['slug']) ?>" style="color:inherit;text-decoration:none;"><?= e($a['headline']) ?></a></h3>
      <p><?= e($a['dek']) ?></p>
      <div class="byline">By <strong><?= e($a['author_name']) ?></strong> · <?= fmtDate($a['publish_at']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- CULTURE -->
  <?php if ($cultureArticles): ?>
  <div class="sec-head">
    <div class="sec-title">Culture &amp; Catastrophe</div>
    <div class="sec-rule"></div>
    <div class="label label-dark">Pop Culture · Arts · Tech</div>
  </div>
  <div class="g4">
    <?php foreach ($cultureArticles as $a): ?>
    <div class="card">
      <div class="card-img" style="font-size:40px;"><?= e($a['hero_emoji'] ?? '📰') ?></div>
      <div class="label label-dark"><?= e(ucfirst($a['section'])) ?></div>
      <h3><a href="/article/<?= e($a['slug']) ?>" style="color:inherit;text-decoration:none;"><?= e($a['headline']) ?></a></h3>
      <p><?= e($a['dek']) ?></p>
      <div class="byline">By <strong><?= e($a['author_name']) ?></strong> · <?= fmtDate($a['publish_at']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div><!-- end wrap -->

<?php include __DIR__ . '/includes/footer.php'; ?>
