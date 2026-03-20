#!/usr/bin/env php
<?php
/**
 * Rachel Rae's Rundown — cron/social_queue.php
 * Nightly at 10pm: Lavinia writes social copy for today's top stories.
 * Stories queue in DB. Rachel approves before actual posting.
 */
if (php_sapi_name() !== 'cli' && (empty($_SERVER['HTTP_X_CRON_KEY']) || $_SERVER['HTTP_X_CRON_KEY'] !== CRON_KEY)) {
    http_response_code(403); die('CLI only.');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/llm.php';

if (getSetting('social_queue_enabled', '0') !== '1') {
    echo "[" . date('Y-m-d H:i:s') . "] Social queue disabled. Skipping.\n";
    logCron('social_queue', 'skipped');
    exit(0);
}

$pdo = getDB();

// Get today's top 3 live stories
$stmt = $pdo->query(
    "SELECT a.*, s.display_name AS author_name
     FROM articles a LEFT JOIN staff s ON a.author_id = s.id
     WHERE a.status = 'live' AND DATE(a.publish_at) = CURDATE()
     ORDER BY a.views DESC, a.is_featured DESC
     LIMIT 3"
);
$stories = $stmt->fetchAll();

if (empty($stories)) {
    echo "[" . date('Y-m-d H:i:s') . "] No stories today to queue.\n";
    logCron('social_queue', 'skipped', 0, 0, 'No stories published today');
    exit(0);
}

$laviniaPrompt = <<<PROMPT
You are Lavinia Overhype, Marketing Maven at Rachel Rae's Rundown, a satirical fake news publication.
You write punchy, viral-ready social media copy for Twitter/X and Instagram.

Your voice: bold, confident, slightly unhinged in the best way. You use wit, not cheapness.
You know what gets clicked. You know what gets shared. You've manufactured moments professionally.

For each story, write:
1. A Twitter/X post (max 240 chars including URL placeholder [URL])
2. An Instagram caption (2-4 sentences, can be longer, end with 3 relevant hashtags)

RESPOND ONLY IN VALID JSON:
{
  "twitter": "tweet text with [URL] placeholder",
  "instagram": "caption text with hashtags"
}
PROMPT;

$queued  = 0;
$errors  = [];

// Create social_queue table if it doesn't exist
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS social_queue (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        article_id  INT UNSIGNED NOT NULL,
        platform    ENUM('twitter','instagram','facebook') NOT NULL DEFAULT 'twitter',
        copy        TEXT NOT NULL,
        status      ENUM('pending','approved','posted','rejected') NOT NULL DEFAULT 'pending',
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_article (article_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

foreach ($stories as $story) {
    $userPrompt = "Write social copy for this satirical headline:\n\n"
                . "Headline: {$story['headline']}\n"
                . "Dek: {$story['dek']}\n"
                . "Section: {$story['section']}\n"
                . "URL: " . SITE_URL . "/article/{$story['slug']}";

    try {
        $result = callClaude($laviniaPrompt, $userPrompt, 512);
        $text   = preg_replace('/^```json\s*/i', '', trim($result['text']));
        $text   = preg_replace('/\s*```$/', '', $text);
        $copy   = json_decode($text, true);

        if ($copy && !empty($copy['twitter'])) {
            // Replace [URL] placeholder
            $url     = SITE_URL . "/article/{$story['slug']}";
            $twitter = str_replace('[URL]', $url, $copy['twitter']);
            $insta   = $copy['instagram'] ?? '';

            // Queue both
            $pdo->prepare(
                "INSERT INTO social_queue (article_id, platform, copy) VALUES (?, 'twitter', ?)"
            )->execute([$story['id'], $twitter]);

            if ($insta) {
                $pdo->prepare(
                    "INSERT INTO social_queue (article_id, platform, copy) VALUES (?, 'instagram', ?)"
                )->execute([$story['id'], $insta]);
            }

            $queued++;
            echo "  [OK] Queued social copy for: {$story['headline']}\n";
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
        echo "  [ERR] {$e->getMessage()}\n";
    }

    sleep(2);
}

logCron('social_queue', empty($errors) ? 'success' : 'failed', 0, 0, implode(' | ', $errors));
echo "[" . date('Y-m-d H:i:s') . "] Done. Queued: {$queued} social posts (pending Rachel's approval).\n";