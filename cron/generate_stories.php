#!/usr/bin/env php
<?php
/**
 * Rachel Rae's Rundown — cron/generate_stories.php
 * Called every 2 hours by crontab: 0 * /2 * * * php /path/to/cron/generate_stories.php
 *
 * Picks topics from the queue, assigns agents, calls the LLM, inserts drafts/scheduled.
 */

// Prevent browser execution
if (php_sapi_name() !== 'cli' && (empty($_SERVER['HTTP_X_CRON_KEY']) || $_SERVER['HTTP_X_CRON_KEY'] !== CRON_KEY)) {
    http_response_code(403);
    die('CLI only.');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/llm.php';

$startTime     = microtime(true);
$storiesTarget = max(1, (int) round((int) getSetting('stories_per_day', '6') / 12));
$generated     = 0;
$totalTokens   = 0;
$totalCost     = 0.0;
$errors        = [];

// Cron defaults for image count and story length (configurable in Admin > Settings)
$cronImageCount = max(0, min(2, (int) getSetting('cron_image_count', '0')));
$cronLengthKey  = array_key_exists(getSetting('cron_story_length', 'standard'), STORY_LENGTHS)
                ? getSetting('cron_story_length', 'standard')
                : 'standard';

echo "[" . date('Y-m-d H:i:s') . "] Starting story generation. Target: {$storiesTarget} stories\n";

$pdo = getDB();

for ($i = 0; $i < $storiesTarget; $i++) {
    // Pull next unused topic from queue (highest priority first)
    $stmt = $pdo->query(
        "SELECT * FROM topic_queue WHERE used = 0 ORDER BY priority DESC, id ASC LIMIT 1"
    );
    $topic = $stmt->fetch();

    if (!$topic) {
        echo "  [SKIP] Topic queue is empty. No stories generated this run.\n";
        logCron('generate_stories', 'skipped', 0, 0, 'Topic queue empty', 0);
        break;
    }

    // Mark topic as used immediately to prevent duplicate generation
    $pdo->prepare("UPDATE topic_queue SET used = 1, used_at = NOW() WHERE id = ?")
        ->execute([$topic['id']]);

    // Pick agent
    $agentSlug = $topic['agent_slug'] ?: pickAgentForSection($topic['section']);

    echo "  [GEN] Section={$topic['section']} Agent={$agentSlug}\n";
    echo "        Prompt: " . substr($topic['prompt'], 0, 80) . "...\n";

    try {
        $result = generateStory($agentSlug, $topic['prompt'], $topic['section'], $cronImageCount, $cronLengthKey);

        $generated++;
        $totalTokens += $result['tokens_used'];
        $totalCost   += $result['cost_usd'];

        echo "  [OK]  Article #{$result['article_id']}: {$result['headline']}\n";
        echo "        Tokens: {$result['tokens_used']} | Cost: \${$result['cost_usd']}\n";

    } catch (Throwable $e) {
        $errMsg = $e->getMessage();
        $errors[] = $errMsg;
        echo "  [ERR] {$errMsg}\n";
    }

    // Polite pause between API calls
    if ($i < $storiesTarget - 1) sleep(3);
}

$elapsed = round(microtime(true) - $startTime, 2);
$status  = empty($errors) ? 'success' : ($generated > 0 ? 'success' : 'failed');
$errLog  = implode(' | ', $errors);

logCron('generate_stories', $status, $generated, 0, $errLog, $totalTokens, $totalCost);

echo "[" . date('Y-m-d H:i:s') . "] Done. Generated: {$generated} | Tokens: {$totalTokens} | Cost: \${$totalCost} | Time: {$elapsed}s\n";