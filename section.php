<?php
/**
 * Rachel Rae's Rundown — section.php
 * Displays articles for a given section.
 * URL: /section/{name} → section.php?s={name}
 */
require_once __DIR__ . '/config.php';

$section = preg_replace('/[^a-z0-9_\-]/', '', strtolower($_GET['s'] ?? ''));
if (!$section) { header('Location: /'); exit; }

$pdo    = getDB();
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 12;
$offset = ($page - 1) * $limit;

// Section display name map
$sectionNames = [
    'politics'    => 'Politics & Power',
    'elections'   => 'Elections',
    'national'    => 'National Affairs',
    'world'       => 'World',
    'local'       => 'Local San Antonio',
    'crime'       => 'Crime & Chaos',
    'culture'     => 'Culture & Catastrophe',
    'fashion'     => 'Fashion & Fury',
    'food'        => 'Food & Drink',
    'music'       => 'Music',
    'film'        => 'Film',
    'tech'        => 'Technology',
    'opinion'     => 'Opinion',
    'letters'     => 'Letters to Nobody',
    'astrology'   => 'Astrology (With Violence)',
    'religion'    => 'Religion & The Almighty',
    'megachurch'  => 'Megachurch Watch',
    'miracles'    => 'Miracles & Myths',
    'lgbtq'       => 'Pride & Prejudice',
    'drag'        => 'Drag Dispatch',
    'policy'      => 'Policy Watch',
];

$sectionLabel = $sectionNames[$section] ?? ucfirst($section);
$pageTitle    = $sectionLabel;
$pageDesc     = "All {$sectionLabel} stories from Rachel Rae's Rundown — satirical news from San Antonio.";

// Count total
$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM articles WHERE status = 'live' AND section = ?"
);
$countStmt->execute([$section]);
$total     = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $limit));

// Fetch articles
$stmt = $pdo->prepare(
    "SELECT a.*, s.display_name AS author_name
     FROM articles a
     LEFT JOIN staff s ON a.author_id = s.id
     WHERE a.status = 'live' AND a.section = ?
     ORDER BY a.publish_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute([$section, $limit, $offset]);
$articles = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="wrap" style="padding-top:36px;">

  <div class="sec-head">
    <div class="sec-title"><?= e($sectionLabel) ?></div>
    <div class="sec-rule"></div>
    <div class="label label-dark"><?= $total ?> stories</div>
  </div>

  <?php if (empty($articles)): ?>
    <div style="padding:60px 0;text-align:center;color:var(--warm-gray);">
      <div style="font-size:64px;margin-bottom:16px;">📭</div>
      <p style="font-family:'Cormorant Garamond',serif;font-style:italic;font-size:22px;">
        No stories here yet. The agents are working on it. Probably.
      </p>
    </div>
  <?php else: ?>
    <div class="g3">
      <?php foreach ($articles as $a): ?>
      <div class="card">
        <div class="card-img" style="font-size:44px;"><?= e($a['hero_emoji'] ?? '📰') ?></div>
        <div class="label label-dark"><?= e(ucfirst($a['section'])) ?></div>
        <h3>
          <a href="/article/<?= e($a['slug']) ?>" style="color:inherit;text-decoration:none;">
            <?= e($a['headline']) ?>
          </a>
        </h3>
        <p><?= e($a['dek']) ?></p>
        <div class="byline">
          By <strong><?= e($a['author_name'] ?? 'Staff') ?></strong>
          · <?= date('F j, Y', strtotime($a['publish_at'])) ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;justify-content:center;align-items:center;gap:12px;padding:32px 0;font-family:'DM Mono',monospace;font-size:12px;letter-spacing:0.08em;">
      <?php if ($page > 1): ?>
        <a href="/section/<?= e($section) ?>?page=<?= $page-1 ?>" style="color:var(--teal);text-decoration:none;">← Prev</a>
      <?php endif; ?>
      <span style="color:var(--warm-gray);">Page <?= $page ?> of <?= $totalPages ?></span>
      <?php if ($page < $totalPages): ?>
        <a href="/section/<?= e($section) ?>?page=<?= $page+1 ?>" style="color:var(--teal);text-decoration:none;">Next →</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
