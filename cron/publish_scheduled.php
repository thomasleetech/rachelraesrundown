#!/usr/bin/env php
<?php
/**
 * Rachel Rae's Rundown — cron/publish_scheduled.php
 * Called every hour: 0 * * * * php /path/to/cron/publish_scheduled.php
 *
 * Promotes 'scheduled' articles whose publish_at time has passed to 'live'.
 */

if (php_sapi_name() !== 'cli' && (empty($_SERVER['HTTP_X_CRON_KEY']) || $_SERVER['HTTP_X_CRON_KEY'] !== CRON_KEY)) {
    http_response_code(403); die('CLI only.');
}

require_once __DIR__ . '/../config.php';

$pdo       = getDB();
$published = 0;

echo "[" . date('Y-m-d H:i:s') . "] Checking for scheduled articles to publish...\n";

// Fetch all articles ready to go live
$stmt = $pdo->query(
    "SELECT id, slug, headline, section
     FROM articles
     WHERE status = 'scheduled'
       AND publish_at <= NOW()
     ORDER BY publish_at ASC"
);

$toPublish = $stmt->fetchAll();

if (empty($toPublish)) {
    echo "  [SKIP] No articles ready to publish.\n";
    logCron('publish_scheduled', 'skipped', 0, 0);
    exit(0);
}

$ids = array_column($toPublish, 'id');

// Bulk update to 'live'
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$update       = $pdo->prepare("UPDATE articles SET status = 'live' WHERE id IN ($placeholders)");
$update->execute($ids);
$published    = $update->rowCount();

foreach ($toPublish as $a) {
    echo "  [PUB] #{$a['id']} [{$a['section']}] {$a['headline']}\n";
}

// If one of the newly-published articles should be breaking news, promote it
// (logic: most recent article tagged 'breaking' or highest-priority section)
$breakingStmt = $pdo->query(
    "SELECT id FROM articles
     WHERE status = 'live'
       AND JSON_CONTAINS(tags, '\"breaking\"')
     ORDER BY publish_at DESC
     LIMIT 1"
);
$breakingRow = $breakingStmt->fetch();
if ($breakingRow) {
    setSetting('breaking_story_id', (string) $breakingRow['id']);
    echo "  [BREAK] Set breaking story: #{$breakingRow['id']}\n";
}

logCron('publish_scheduled', 'success', 0, $published);
echo "[" . date('Y-m-d H:i:s') . "] Published: {$published} articles.\n";