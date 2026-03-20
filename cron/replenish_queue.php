#!/usr/bin/env php
<?php
/**
 * Rachel Rae's Rundown — cron/replenish_queue.php
 * Runs at 6am daily. If fewer than 15 unused topics remain,
 * The Desk generates a fresh batch of story prompts using current news trends.
 */
if (php_sapi_name() !== 'cli' && (empty($_SERVER['HTTP_X_CRON_KEY']) || $_SERVER['HTTP_X_CRON_KEY'] !== CRON_KEY)) {
    http_response_code(403); die('CLI only.');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/llm.php';

$pdo       = getDB();
$remaining = (int) $pdo->query("SELECT COUNT(*) FROM topic_queue WHERE used = 0")->fetchColumn();
$threshold = 15;

echo "[" . date('Y-m-d H:i:s') . "] Topic queue: {$remaining} prompts remaining.\n";

if ($remaining >= $threshold) {
    echo "  [SKIP] Queue is healthy. No replenishment needed.\n";
    logCron('replenish_queue', 'skipped');
    exit(0);
}

echo "  [GEN] Queue below threshold ({$threshold}). Generating new topics...\n";

$deskPrompt = <<<PROMPT
You are The Desk, Managing Editor at Rachel Rae's Rundown, a satirical fake news publication
in San Antonio, Texas. You assign stories to reporters.

Generate 20 fresh story prompt ideas for our AI writing staff. The prompts should:
- Be specific enough to write a full satirical article from
- Parody real-world news genres (political scandal, religious institution, celebrity culture,
  tech industry, government bureaucracy, alien/paranormal affairs, time travel, fashion, etc.)
- Lean weird, dark, funny, absurd — our readers expect it
- Mix timeless satirical angles with riffs on current cultural moments
- Cover a variety of sections: politics, religion, lgbtq, fashion, culture, tech, local, world

RESPOND ONLY IN VALID JSON — no preamble, no fences:
{
  "topics": [
    {"prompt": "The specific story prompt/angle", "section": "section_name", "agent_slug": "agent-slug-or-null", "priority": 1-10},
    ...
  ]
}

Valid agent slugs: vex-moriarty, dolores-vendetta, roxanne-blaze, fabrizia-sloane,
chip-largo, esperanza-lux, the-desk, ziggy-nullpointer, lavinia-overhype, ptolemy-snark

Valid sections: politics, elections, national, world, local, culture, fashion, tech, film,
music, religion, lgbtq, opinion, crime, science
PROMPT;

$userPrompt = "Today is " . date('F j, Y') . ". Generate 20 fresh satirical story prompts for Rachel Rae's Rundown. "
            . "Make them weird, specific, and excellent. Include a mix of the team's favorite beats: "
            . "politics, religion, LGBTQ+, fashion, AI/tech, aliens, time travel, parallel universes, "
            . "San Antonio local color, and general cosmic chaos.";

try {
    $result = callClaude($deskPrompt, $userPrompt, 2048);
    $text   = preg_replace('/^```json\s*/i', '', trim($result['text']));
    $text   = preg_replace('/\s*```$/', '', $text);
    $data   = json_decode($text, true);

    if (!$data || empty($data['topics'])) {
        throw new RuntimeException("Failed to parse topic JSON. Raw: " . substr($text, 0, 400));
    }

    $inserted = 0;
    $stmt     = $pdo->prepare(
        "INSERT INTO topic_queue (prompt, section, agent_slug, priority) VALUES (?, ?, ?, ?)"
    );

    foreach ($data['topics'] as $t) {
        if (empty($t['prompt'])) continue;
        $stmt->execute([
            trim($t['prompt']),
            preg_replace('/[^a-z0-9_\-]/', '', $t['section'] ?? 'news'),
            ($t['agent_slug'] && $t['agent_slug'] !== 'null') ? $t['agent_slug'] : null,
            max(1, min(10, (int)($t['priority'] ?? 5))),
        ]);
        $inserted++;
    }

    logCron('replenish_queue', 'success', $inserted, 0, '', $result['tokens_used'], $result['cost_usd']);
    echo "  [OK] Added {$inserted} new topics. Tokens: {$result['tokens_used']}. Cost: \${$result['cost_usd']}\n";

} catch (Throwable $e) {
    logCron('replenish_queue', 'failed', 0, 0, $e->getMessage());
    echo "  [ERR] {$e->getMessage()}\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Done.\n";