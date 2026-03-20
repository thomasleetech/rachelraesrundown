<?php
/**
 * Rachel Rae's Rundown — api/feed.php
 * Returns JSON array of live articles for dynamic homepage loading.
 *
 * GET params:
 *   section  string   filter by section
 *   limit    int      max results (default 12, max 50)
 *   offset   int      pagination offset
 *   q        string   full-text search
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Cache-Control: public, max-age=60');

$section = $_GET['section'] ?? null;
$limit   = min(50, max(1, (int)($_GET['limit'] ?? 12)));
$offset  = max(0, (int)($_GET['offset'] ?? 0));
$q       = trim($_GET['q'] ?? '');

$pdo    = getDB();
$params = ['live'];
$where  = ['a.status = ?'];

if ($section) {
    $where[]  = 'a.section = ?';
    $params[] = $section;
}

if ($q) {
    $where[]  = 'MATCH(a.headline, a.dek, a.body) AGAINST(? IN BOOLEAN MODE)';
    $params[] = $q . '*';
}

$whereClause = implode(' AND ', $where);

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM articles a WHERE $whereClause");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

// Fetch articles (no body in feed — saves bandwidth)
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare(
    "SELECT a.id, a.slug, a.headline, a.dek, a.section, a.hero_emoji,
            a.hero_image, a.is_breaking, a.is_featured, a.views,
            a.tags, a.publish_at,
            s.display_name AS author_name, s.slug AS author_slug
     FROM articles a
     LEFT JOIN staff s ON a.author_id = s.id
     WHERE $whereClause
     ORDER BY a.is_featured DESC, a.publish_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute($params);
$articles = $stmt->fetchAll();

// Format dates and decode JSON fields
foreach ($articles as &$a) {
    $a['publish_date'] = date('F j, Y', strtotime($a['publish_at']));
    $a['tags']         = json_decode($a['tags'] ?? '[]', true) ?: [];
    $a['url']          = '/article/' . $a['slug'];
}

echo json_encode([
    'articles' => $articles,
    'total'    => $total,
    'limit'    => $limit,
    'offset'   => $offset,
    'section'  => $section,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
