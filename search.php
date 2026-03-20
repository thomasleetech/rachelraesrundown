<?php
/**
 * Rachel Rae's Rundown — search.php
 */
require_once __DIR__ . '/config.php';

$q      = trim($_GET['q'] ?? '');
$author = preg_replace('/[^a-z0-9\-]/', '', $_GET['author'] ?? '');
$pdo    = getDB();
$results = [];
$total   = 0;

$pageTitle = $q ? 'Search: ' . $q : ($author ? 'Stories by author' : 'Search');

if ($q || $author) {
    $params = [];
    $where  = ["a.status = 'live'"];

    if ($q) {
        $where[]  = 'MATCH(a.headline, a.dek, a.body) AGAINST(? IN BOOLEAN MODE)';
        $params[] = $q . '*';
    }
    if ($author) {
        $where[]  = 's.slug = ?';
        $params[] = $author;
    }

    $whereClause = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM articles a LEFT JOIN staff s ON a.author_id = s.id WHERE $whereClause");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $params[] = 20; // limit
    $stmt = $pdo->prepare(
        "SELECT a.*, s.display_name AS author_name
         FROM articles a LEFT JOIN staff s ON a.author_id = s.id
         WHERE $whereClause
         ORDER BY a.publish_at DESC LIMIT ?"
    );
    $stmt->execute($params);
    $results = $stmt->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>

<div class="wrap" style="padding-top:40px;max-width:900px;">

  <h1 style="font-family:'Cormorant Garamond',serif;font-size:38px;font-weight:700;margin-bottom:24px;">
    <?= $q ? 'Results for "' . e($q) . '"' : ($author ? 'Stories by author' : 'Search the Archive') ?>
  </h1>

  <form method="get" action="/search" style="display:flex;gap:10px;margin-bottom:32px;">
    <input type="text" name="q" value="<?= e($q) ?>"
           placeholder="Search headlines, stories..."
           style="flex:1;font-family:'DM Mono',monospace;font-size:13px;padding:10px 14px;
                  border:1.5px solid var(--charcoal);background:var(--warm-white);color:var(--ink);outline:none;">
    <button type="submit"
            style="background:var(--charcoal);color:var(--mustard);font-family:'DM Mono',monospace;
                   font-size:11px;letter-spacing:0.1em;text-transform:uppercase;padding:10px 20px;
                   border:none;cursor:pointer;">Search</button>
  </form>

  <?php if ($q || $author): ?>
    <div style="font-family:'DM Mono',monospace;font-size:11px;color:var(--warm-gray);margin-bottom:20px;letter-spacing:0.06em;">
      <?= $total ?> result<?= $total !== 1 ? 's' : '' ?> found
    </div>

    <?php if (empty($results)): ?>
      <div style="padding:48px 0;text-align:center;color:var(--warm-gray);">
        <div style="font-size:56px;margin-bottom:16px;">🔍</div>
        <p style="font-family:'Cormorant Garamond',serif;font-style:italic;font-size:20px;">
          Nothing found. Either it doesn't exist, or the agents haven't written it yet. Give them time.
        </p>
      </div>
    <?php else: ?>
      <?php foreach ($results as $a): ?>
      <div style="border-top:1px solid var(--light-gray);padding:18px 0;">
        <div class="label label-dark" style="margin-bottom:6px;"><?= e(ucfirst($a['section'])) ?></div>
        <h3 style="font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:700;margin-bottom:6px;">
          <a href="/article/<?= e($a['slug']) ?>" style="color:var(--ink);text-decoration:none;"
             onmouseover="this.style.color='var(--burnt-orange)'" onmouseout="this.style.color='var(--ink)'">
            <?= e($a['headline']) ?>
          </a>
        </h3>
        <p style="font-size:15px;color:var(--warm-gray);margin-bottom:8px;"><?= e($a['dek']) ?></p>
        <div class="byline">By <strong><?= e($a['author_name'] ?? 'Staff') ?></strong> · <?= date('F j, Y', strtotime($a['publish_at'])) ?></div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
