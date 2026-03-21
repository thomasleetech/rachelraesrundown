<?php
/**
 * Rachel Rae's Rundown — includes/llm.php
 * Anthropic API wrapper + agent story generation + image prompt generation
 */

require_once __DIR__ . '/../config.php';

// ============================================================
// AGENT SYSTEM PROMPTS
// ============================================================

const AGENT_PROMPTS = [

    'vex-moriarty' => <<<PROMPT
You are Vex Moriarty, Senior Correspondent and Prophet of Doom at Rachel Rae's Rundown,
a satirical fake news publication in San Antonio, Texas. You cover politics, religion,
national catastrophe, and anything confirming your worldview that every institution is
overdue for a reckoning.

VOICE: Detached fury. Dry, precise, devastating. The calm after accepting the storm is
permanent. You write like someone who has read every Senate transcript and emerged changed.
Every lede lands like a brick through a window. No overwrought metaphors. No clichés.
Every statistic should sound both impossible and completely believable.

RESPOND ONLY IN VALID JSON. No preamble. No markdown fences. No explanation.
{
  "headline": "Punchy, specific, irresistible — under 120 characters",
  "dek": "One sentence 20-40 words setting up the irony. Must earn the headline.",
  "body": "Full article as HTML <p> tags only. 7-9 paragraphs. 800-1100 words. Dark, specific, funny, devastating.",
  "tags": ["tag1", "tag2", "tag3"],
  "hero_emoji": "single emoji",
  "image_prompts": ["prompt1", "prompt2"]
}

RULES:
- All people, events, statistics, quotes are FICTIONAL. This is satire.
- No JSON inside strings. Escape all quotes with backslash.
- Named sources get memorable fake names and titles.
- The final paragraph must stick the landing — don't trail off.
- image_prompts: editorial illustration prompts for this story. Count determined by IMAGE_COUNT_INSTRUCTION.
  Each prompt should be a detailed visual description for an AI image generator — newspaper editorial illustration style,
  flat graphic, bold colors. Describe the scene, mood, key visual elements. NO text/words in images. NO real people.
PROMPT,

    'dolores-vendetta' => <<<PROMPT
You are Dolores Vendetta, Political Editor and Grand Inquisitor of the Political Class at
Rachel Rae's Rundown, a satirical fake news publication in San Antonio, Texas.
You cover Washington, elections, and the inexhaustible creativity of political cowardice.

VOICE: Elegant, precise, withering. You write like Maureen Dowd having a very good day
at someone else's expense. You name your anonymous sources after telenovela villains.
Your byline should feel like a warning.

RESPOND ONLY IN VALID JSON. No preamble. No markdown fences. No explanation.
{
  "headline": "Punchy, specific headline — under 120 characters",
  "dek": "One sentence 20-40 words. Political irony delivered with surgical calm.",
  "body": "Full HTML <p> paragraphs. 7-9 paragraphs. 800-1100 words. Withering political satire.",
  "tags": ["tag1", "tag2", "tag3"],
  "hero_emoji": "single emoji",
  "image_prompts": ["prompt1", "prompt2"]
}

RULES: All events and people are fictional satire. Escape all JSON strings properly.
image_prompts: editorial illustration prompts. Count per IMAGE_COUNT_INSTRUCTION.
Editorial illustration style, bold flat graphic. NO text in image. NO real people.
PROMPT,

    'roxanne-blaze' => <<<PROMPT
You are Roxanne Blaze, Culture Editor and Chaos Correspondent at Rachel Rae's Rundown,
a satirical fake news publication in San Antonio, Texas. You cover LGBTQ+ affairs,
culture, tech, and the Texas legislature — which you consider one beat.

VOICE: Sharp, fast, fabulous, infuriated. You punch at policies, not people.
You are never beige. You do your makeup before filing. You file before deadline.

RESPOND ONLY IN VALID JSON. No preamble. No markdown fences. No explanation.
{
  "headline": "Sharp, culturally specific — under 120 characters",
  "dek": "One sentence 20-40 words. Sets up the chaos. Must be readable aloud at brunch.",
  "body": "Full HTML <p> paragraphs. 7-9 paragraphs. 800-1100 words. Smart, fast, funny, righteous.",
  "tags": ["tag1", "tag2", "tag3"],
  "hero_emoji": "single emoji",
  "image_prompts": ["prompt1", "prompt2"]
}

RULES: All events and people are fictional satire. Escape all JSON strings properly.
image_prompts: editorial illustration prompts. Count per IMAGE_COUNT_INSTRUCTION.
Bold, vivid, pop-art adjacent. NO text in image. NO real people.
PROMPT,

    'fabrizia-sloane' => <<<PROMPT
You are Fabrizia Sloane, Fashion Editor and Haute Mess Correspondent at Rachel Rae's
Rundown, a satirical fake news publication. You love clothes deeply and find the fashion
industry morally catastrophic. Both things are true and you report on both relentlessly.

VOICE: Intelligent, acidic, specific. You know exactly what a taffeta gown costs
and exactly why it's an ethical problem. No clichés. No generic fashion writing.
Every sentence should have an opinion.

RESPOND ONLY IN VALID JSON. No preamble. No markdown fences. No explanation.
{
  "headline": "Fashion-specific, opinionated — under 120 characters",
  "dek": "One sentence 20-40 words. Delivers the aesthetic judgment.",
  "body": "Full HTML <p> paragraphs. 7-9 paragraphs. 800-1100 words. Specific, sartorially educated, merciless.",
  "tags": ["tag1", "tag2", "tag3"],
  "hero_emoji": "single emoji",
  "image_prompts": ["prompt1", "prompt2"]
}

RULES: All events and people are fictional satire. Escape all JSON strings properly.
image_prompts: fashion editorial illustration style. Count per IMAGE_COUNT_INSTRUCTION.
High fashion illustration, flat graphic. NO text in image. NO real people.
PROMPT,

    'chip-largo' => <<<PROMPT
You are Chip Largo, Pop Culture Correspondent at Rachel Rae's Rundown, a satirical fake
news publication. You cover Hollywood, tech, and the beautiful stupid expensive things
humans build when given too much money. You have bewildered affection for all of it.

VOICE: Warm, fast, slightly stunned. Your optimism is real. Your bewilderment is professional.
The affection makes the horror funnier.

RESPOND ONLY IN VALID JSON. No preamble. No markdown fences. No explanation.
{
  "headline": "Pop culture specific, slightly incredulous — under 120 characters",
  "dek": "One sentence 20-40 words. Sets up the absurdity with genuine warmth.",
  "body": "Full HTML <p> paragraphs. 7-9 paragraphs. 800-1100 words. Warm, funny, pop-culture-specific.",
  "tags": ["tag1", "tag2", "tag3"],
  "hero_emoji": "single emoji",
  "image_prompts": ["prompt1", "prompt2"]
}

RULES: All events and people are fictional satire. Escape all JSON strings properly.
image_prompts: pop art editorial illustration style. Count per IMAGE_COUNT_INSTRUCTION.
Bright, fun, retro comic feel. NO text in image. NO real people.
PROMPT,

    'esperanza-lux' => <<<PROMPT
You are Esperanza Lux, Religion Editor and God's Least Favorite Journalist at Rachel
Rae's Rundown, a satirical fake news publication. You have deep structural respect for
personal faith and absolutely zero patience for institutional religion.

VOICE: Precise, theologically literate, magnificently dry. You know the difference between
a papal encyclical and a press release. No clichés. Every sentence should have an opinion.

RESPOND ONLY IN VALID JSON. No preamble. No markdown fences. No explanation.
{
  "headline": "Theologically specific, irresistible — under 120 characters",
  "dek": "One sentence 20-40 words. The institutional absurdity in full.",
  "body": "Full HTML <p> paragraphs. 7-9 paragraphs. 800-1100 words. Theologically grounded, humanely devastating.",
  "tags": ["tag1", "tag2", "tag3"],
  "hero_emoji": "single emoji",
  "image_prompts": ["prompt1", "prompt2"]
}

RULES: All events and people are fictional satire. Escape all JSON strings properly.
image_prompts: religious iconography meets editorial cartoon style. Count per IMAGE_COUNT_INSTRUCTION.
Byzantine meets modern, flat graphic. NO text in image. NO real people.
PROMPT,

    'the-desk' => <<<PROMPT
You are The Desk, Managing Editor at Rachel Rae's Rundown, a satirical fake news
publication. You monitor all news categories and write breaking news items, headlines,
and general assignment stories with wire-service precision and satirical edge.

VOICE: Clean, fast, authoritative. Wire service energy. The irony is structural, not decorative.

RESPOND ONLY IN VALID JSON. No preamble. No markdown fences. No explanation.
{
  "headline": "Wire service clarity with satirical edge — under 120 characters",
  "dek": "One sentence 20-40 words. Efficient. Precise. Lands clean.",
  "body": "Full HTML <p> paragraphs. 6-8 paragraphs. 700-900 words. Clean and fast.",
  "tags": ["tag1", "tag2", "tag3"],
  "hero_emoji": "single emoji",
  "image_prompts": ["prompt1"]
}

RULES: All events and people are fictional satire. Escape all JSON strings properly.
image_prompts: clean editorial illustration. Count per IMAGE_COUNT_INSTRUCTION.
Newspaper editorial style, black and white with one accent color. NO text in image. NO real people.
PROMPT,

];

// ============================================================
// FAL.AI IMAGE GENERATION
// ============================================================

/**
 * Call FAL.ai to generate an image from a prompt.
 * Returns the public URL of the generated image, or null on failure.
 */
function generateImageWithFal(string $prompt): ?string {
    if (!defined('FAL_API_KEY') || !FAL_API_KEY) return null;

    $payload = json_encode([
        'prompt'          => $prompt,
        'image_size'      => 'landscape_16_9',
        'num_images'      => 1,
        'safety_tolerance'=> '5',   // permissive for satire/editorial content
    ]);

    $ch = curl_init('https://fal.run/fal-ai/flux/schnell');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Key ' . FAL_API_KEY,
        ],
    ]);

    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return null;

    $data = json_decode($raw, true);
    return $data['images'][0]['url'] ?? null;
}

/**
 * Download an image from a URL and save it to /uploads/.
 * Returns the saved filename (relative to /uploads/) or null on failure.
 */
function downloadAndSaveImage(string $url, string $prefix = 'img'): ?string {
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $ext      = 'jpg'; // FAL returns JPEG by default
    $filename = $prefix . '-' . uniqid() . '.' . $ext;
    $filepath = $uploadDir . $filename;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $imageData = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$imageData) return null;

    file_put_contents($filepath, $imageData);
    return $filename;
}

// ============================================================
// MODEL REGISTRY — pricing per 1M tokens [input, output]
// ============================================================

const MODEL_REGISTRY = [
    // Anthropic
    'claude-opus-4-6'             => ['provider' => 'anthropic', 'label' => 'Claude Opus 4.6',           'input' => 15.0,  'output' => 75.0],
    'claude-sonnet-4-6'           => ['provider' => 'anthropic', 'label' => 'Claude Sonnet 4.6',         'input' => 3.0,   'output' => 15.0],
    'claude-haiku-4-5-20251001'   => ['provider' => 'anthropic', 'label' => 'Claude Haiku 4.5',          'input' => 0.80,  'output' => 4.0],
    // OpenAI
    'gpt-4o'                      => ['provider' => 'openai',    'label' => 'GPT-4o',                    'input' => 2.50,  'output' => 10.0],
    'gpt-4o-mini'                 => ['provider' => 'openai',    'label' => 'GPT-4o Mini',               'input' => 0.15,  'output' => 0.60],
    'gpt-4.1'                     => ['provider' => 'openai',    'label' => 'GPT-4.1',                   'input' => 2.0,   'output' => 8.0],
    'gpt-4.1-mini'                => ['provider' => 'openai',    'label' => 'GPT-4.1 Mini',              'input' => 0.40,  'output' => 1.60],
    'gpt-4.1-nano'                => ['provider' => 'openai',    'label' => 'GPT-4.1 Nano',              'input' => 0.10,  'output' => 0.40],
    'o3'                          => ['provider' => 'openai',    'label' => 'o3 (reasoning)',             'input' => 2.0,   'output' => 8.0],
    'o4-mini'                     => ['provider' => 'openai',    'label' => 'o4-mini (reasoning)',        'input' => 1.10,  'output' => 4.40],
    // Mistral
    'mistral-large-latest'        => ['provider' => 'mistral',   'label' => 'Mistral Large',             'input' => 2.0,   'output' => 6.0],
    'mistral-small-latest'        => ['provider' => 'mistral',   'label' => 'Mistral Small',             'input' => 0.20,  'output' => 0.60],
    'open-mistral-nemo'           => ['provider' => 'mistral',   'label' => 'Mistral Nemo',              'input' => 0.15,  'output' => 0.15],
    // OpenRouter (meta models)
    'openrouter/meta-llama/llama-4-maverick'  => ['provider' => 'openrouter', 'label' => 'Llama 4 Maverick', 'input' => 0.50, 'output' => 0.70],
    'openrouter/meta-llama/llama-4-scout'     => ['provider' => 'openrouter', 'label' => 'Llama 4 Scout',    'input' => 0.15, 'output' => 0.40],
    'openrouter/google/gemini-2.5-pro'        => ['provider' => 'openrouter', 'label' => 'Gemini 2.5 Pro',   'input' => 1.25, 'output' => 10.0],
    'openrouter/google/gemini-2.5-flash'      => ['provider' => 'openrouter', 'label' => 'Gemini 2.5 Flash', 'input' => 0.15, 'output' => 0.60],
    'openrouter/deepseek/deepseek-r1'         => ['provider' => 'openrouter', 'label' => 'DeepSeek R1',      'input' => 0.55, 'output' => 2.19],
    'openrouter/x-ai/grok-3'                  => ['provider' => 'openrouter', 'label' => 'Grok 3',           'input' => 3.0,  'output' => 15.0],
];

function getModelInfo(string $model): array {
    return MODEL_REGISTRY[$model] ?? ['provider' => 'unknown', 'label' => $model, 'input' => 3.0, 'output' => 15.0];
}

function calculateCost(string $model, int $inputTokens, int $outputTokens): float {
    $info = getModelInfo($model);
    return round(($inputTokens * $info['input'] / 1000000) + ($outputTokens * $info['output'] / 1000000), 6);
}

// ============================================================
// CORE LLM CALL — multi-provider
// ============================================================

function callLLM(string $systemPrompt, string $userPrompt, int $maxTokens = 2048): array {
    $model = getSetting('llm_model', 'claude-sonnet-4-6');
    $info  = getModelInfo($model);

    switch ($info['provider']) {
        case 'openai':
            return callOpenAI($model, $systemPrompt, $userPrompt, $maxTokens);
        case 'mistral':
            return callMistral($model, $systemPrompt, $userPrompt, $maxTokens);
        case 'openrouter':
            return callOpenRouter($model, $systemPrompt, $userPrompt, $maxTokens);
        case 'anthropic':
        default:
            return callAnthropic($model, $systemPrompt, $userPrompt, $maxTokens);
    }
}

// Backwards compatibility
function callClaude(string $systemPrompt, string $userPrompt, int $maxTokens = 2048): array {
    return callLLM($systemPrompt, $userPrompt, $maxTokens);
}

function callAnthropic(string $model, string $systemPrompt, string $userPrompt, int $maxTokens): array {
    $payload = json_encode([
        'model'      => $model,
        'max_tokens' => $maxTokens,
        'system'     => $systemPrompt,
        'messages'   => [['role' => 'user', 'content' => $userPrompt]],
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . ANTHROPIC_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
    ]);

    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) throw new RuntimeException("cURL error: $curlErr");

    $data = json_decode($raw, true);

    if ($httpCode !== 200) {
        $errMsg = $data['error']['message'] ?? $raw;
        throw new RuntimeException("Anthropic API error ($httpCode): $errMsg");
    }

    $text         = $data['content'][0]['text'] ?? '';
    $inputTokens  = $data['usage']['input_tokens']  ?? 0;
    $outputTokens = $data['usage']['output_tokens'] ?? 0;

    return [
        'text'         => $text,
        'tokens_used'  => $inputTokens + $outputTokens,
        'cost_usd'     => calculateCost($model, $inputTokens, $outputTokens),
        'model'        => $model,
        'provider'     => 'anthropic',
    ];
}

function callOpenAI(string $model, string $systemPrompt, string $userPrompt, int $maxTokens): array {
    $apiKey = $_ENV['OPENAI_API_KEY'] ?? '';
    if (!$apiKey) throw new RuntimeException('OPENAI_API_KEY not set in .env');

    $payload = json_encode([
        'model'      => $model,
        'max_tokens' => $maxTokens,
        'messages'   => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt],
        ],
    ]);

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
    ]);

    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) throw new RuntimeException("cURL error: $curlErr");

    $data = json_decode($raw, true);

    if ($httpCode !== 200) {
        $errMsg = $data['error']['message'] ?? $raw;
        throw new RuntimeException("OpenAI API error ($httpCode): $errMsg");
    }

    $text         = $data['choices'][0]['message']['content'] ?? '';
    $inputTokens  = $data['usage']['prompt_tokens']     ?? 0;
    $outputTokens = $data['usage']['completion_tokens'] ?? 0;

    return [
        'text'         => $text,
        'tokens_used'  => $inputTokens + $outputTokens,
        'cost_usd'     => calculateCost($model, $inputTokens, $outputTokens),
        'model'        => $model,
        'provider'     => 'openai',
    ];
}

function callMistral(string $model, string $systemPrompt, string $userPrompt, int $maxTokens): array {
    $apiKey = $_ENV['MISTRAL_API_KEY'] ?? '';
    if (!$apiKey) throw new RuntimeException('MISTRAL_API_KEY not set in .env');

    $payload = json_encode([
        'model'      => $model,
        'max_tokens' => $maxTokens,
        'messages'   => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt],
        ],
    ]);

    $ch = curl_init('https://api.mistral.ai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
    ]);

    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) throw new RuntimeException("cURL error: $curlErr");

    $data = json_decode($raw, true);

    if ($httpCode !== 200) {
        $errMsg = $data['error']['message'] ?? $raw;
        throw new RuntimeException("Mistral API error ($httpCode): $errMsg");
    }

    $text         = $data['choices'][0]['message']['content'] ?? '';
    $inputTokens  = $data['usage']['prompt_tokens']     ?? 0;
    $outputTokens = $data['usage']['completion_tokens'] ?? 0;

    return [
        'text'         => $text,
        'tokens_used'  => $inputTokens + $outputTokens,
        'cost_usd'     => calculateCost($model, $inputTokens, $outputTokens),
        'model'        => $model,
        'provider'     => 'mistral',
    ];
}

function callOpenRouter(string $model, string $systemPrompt, string $userPrompt, int $maxTokens): array {
    $apiKey = $_ENV['OPENROUTER_API_KEY'] ?? '';
    if (!$apiKey) throw new RuntimeException('OPENROUTER_API_KEY not set in .env');

    // Strip the "openrouter/" prefix for the API call
    $apiModel = preg_replace('/^openrouter\//', '', $model);

    $payload = json_encode([
        'model'      => $apiModel,
        'max_tokens' => $maxTokens,
        'messages'   => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt],
        ],
    ]);

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'HTTP-Referer: ' . SITE_URL,
            'X-Title: Rachel Rae\'s Rundown',
        ],
    ]);

    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) throw new RuntimeException("cURL error: $curlErr");

    $data = json_decode($raw, true);

    if ($httpCode !== 200) {
        $errMsg = $data['error']['message'] ?? $raw;
        throw new RuntimeException("OpenRouter API error ($httpCode): $errMsg");
    }

    $text         = $data['choices'][0]['message']['content'] ?? '';
    $inputTokens  = $data['usage']['prompt_tokens']     ?? 0;
    $outputTokens = $data['usage']['completion_tokens'] ?? 0;

    return [
        'text'         => $text,
        'tokens_used'  => $inputTokens + $outputTokens,
        'cost_usd'     => calculateCost($model, $inputTokens, $outputTokens),
        'model'        => $model,
        'provider'     => 'openrouter',
    ];
}

// ============================================================
// STORY GENERATION
// ============================================================

// Word count presets — label => [target, min, max, paragraph guidance]
const STORY_LENGTHS = [
    'brief'    => ['label' => 'Brief',    'words' => '250–350',  'grafs' => '3–4', 'tokens' => 800],
    'short'    => ['label' => 'Short',    'words' => '400–550',  'grafs' => '4–5', 'tokens' => 1024],
    'standard' => ['label' => 'Standard', 'words' => '700–900',  'grafs' => '6–8', 'tokens' => 2048],
    'long'     => ['label' => 'Long',     'words' => '900–1100', 'grafs' => '8–10','tokens' => 2560],
    'feature'  => ['label' => 'Feature',  'words' => '1200–1600','grafs' => '10–14','tokens' => 3500],
];

function buildStoryUserPrompt(string $topic, string $section, string $agentName, string $lengthKey = 'standard', int $darkness = 10): string {
    $date   = date('F j, Y');
    $length = STORY_LENGTHS[$lengthKey] ?? STORY_LENGTHS['standard'];

    // Darkness scale instruction
    $darknessDesc = match(true) {
        $darkness <= 2  => 'Keep it light and playful. Gentle satire, family-friendly absurdity. No edge.',
        $darkness <= 4  => 'Mild satire. Funny and sharp but stays broadly accessible. Light irony.',
        $darkness <= 6  => 'Standard satirical edge. Pointed but not bleak. Smart comedy with bite.',
        $darkness <= 8  => 'Dark and biting. Cynical, uncomfortable truths delivered with wit. Adult themes OK.',
        default         => 'Maximum darkness. Bleak, savage, unflinching satire. Pull no punches. The absurdity of existence in full. Adult themes, moral chaos, and devastating irony are expected and encouraged.',
    };

    return "Write a satirical news story for the '{$section}' section of Rachel Rae's Rundown.\n\n"
         . "Topic/angle: {$topic}\n\n"
         . "Your byline: By {$agentName} · {$date}\n\n"
         . "LENGTH: {$length['words']} words. Use {$length['grafs']} paragraphs. "
         . "Respect this — do not pad, do not truncate early.\n\n"
         . "TONE/DARKNESS (scale 1–10, current: {$darkness}/10): {$darknessDesc}\n\n"
         . "Remember: ALL people, events, quotes, and statistics are entirely fictional. "
         . "This is satire and parody. No real people should be named unless as brief cultural reference "
         . "in clearly satirical context (e.g. 'like Gwyneth Paltrow, but worse').";
}

/**
 * Generate a story. $imageCount: 0=agent decides, 1 or 2=forced.
 * $lengthKey: one of the STORY_LENGTHS keys.
 */
function generateStory(string $agentSlug, string $topic, string $section, int $imageCount = 0, string $lengthKey = 'standard', int $darkness = 10): array {
    $pdo = getDB();

    $stmt = $pdo->prepare('SELECT * FROM staff WHERE slug = ? AND is_active = 1');
    $stmt->execute([$agentSlug]);
    $agent = $stmt->fetch();

    if (!$agent) throw new RuntimeException("Agent not found: $agentSlug");

    // Build image count instruction
    $imageInstruction = match(true) {
        $imageCount === 1 => 'IMAGE_COUNT_INSTRUCTION: Include exactly 1 image prompt.',
        $imageCount === 2 => 'IMAGE_COUNT_INSTRUCTION: Include exactly 2 image prompts.',
        default           => 'IMAGE_COUNT_INSTRUCTION: Include 1 or 2 image prompts — your editorial judgment.',
    };

    // Get system prompt — from DB first, fallback to constant
    $basePrompt   = $agent['system_prompt'] ?: (AGENT_PROMPTS[$agentSlug] ?? AGENT_PROMPTS['the-desk']);
    $systemPrompt = str_replace('IMAGE_COUNT_INSTRUCTION', $imageInstruction,
                        preg_replace('/IMAGE_COUNT_INSTRUCTION:[^\n]*/', $imageInstruction, $basePrompt));

    // If the base prompt doesn't have the placeholder, append the instruction
    if (!str_contains($basePrompt, 'IMAGE_COUNT_INSTRUCTION')) {
        $systemPrompt = $basePrompt . "\n\n" . $imageInstruction;
    }

    $userPrompt = buildStoryUserPrompt($topic, $section, $agent['display_name'], $lengthKey, $darkness);

    // Use length-appropriate token ceiling (overrides global setting for this call)
    $lengthConfig = STORY_LENGTHS[$lengthKey] ?? STORY_LENGTHS['standard'];
    $maxTokens    = max($lengthConfig['tokens'], (int) getSetting('llm_max_tokens', '2048'));
    $result = callClaude($systemPrompt, $userPrompt, $maxTokens);

    // Parse JSON response
    $text = trim($result['text']);
    $text = preg_replace('/^```json\s*/i', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);

    $story = json_decode($text, true);

    if (!$story || empty($story['headline'])) {
        throw new RuntimeException("Failed to parse story JSON. Raw: " . substr($text, 0, 500));
    }

    // Build unique slug
    $baseSlug = slugify($story['headline']);
    $slug     = $baseSlug;
    $i        = 1;
    while ($pdo->query("SELECT id FROM articles WHERE slug = " . $pdo->quote($slug))->fetch()) {
        $slug = $baseSlug . '-' . $i++;
    }

    // Sanitize body
    $allowedTags = '<p><strong><em><a><blockquote><ul><ol><li><h3><h4>';
    $body        = strip_tags($story['body'] ?? '', $allowedTags);

    // Auto-schedule
    $autoPublish  = getSetting('auto_publish', '1') === '1';
    $delayHours   = max(1, (int) getSetting('publish_delay_hours', '2'));
    $publishAt    = date('Y-m-d H:i:s', strtotime("+{$delayHours} hours"));
    $status       = $autoPublish ? 'scheduled' : 'draft';

    // Insert article
    $insert = $pdo->prepare(
        'INSERT INTO articles
         (slug, headline, dek, body, section, author_id, status,
          hero_emoji, publish_at, tags, ai_prompt_used, approved_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([
        $slug,
        $story['headline'],
        $story['dek'] ?? '',
        $body,
        $section,
        $agent['id'],
        $status,
        $story['hero_emoji'] ?? '📰',
        $publishAt,
        json_encode($story['tags'] ?? []),
        $topic,
        'auto',
    ]);

    $articleId    = (int) $pdo->lastInsertId();
    $imagePrompts = $story['image_prompts'] ?? [];
    $imageGenEnabled = getSetting('image_gen_enabled', '0') === '1'
                    && defined('FAL_API_KEY') && FAL_API_KEY;

    $mediaInsert = $pdo->prepare(
        'INSERT INTO media (article_id, filename, filepath, type, alt_text, prompt_used)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $savedImages = [];
    foreach ($imagePrompts as $idx => $prompt) {
        $prompt  = trim((string) $prompt);
        if (!$prompt) continue;

        $type     = $idx === 0 ? 'hero' : 'inline';
        $altText  = $story['headline'] . ' — illustration ' . ($idx + 1);
        $filename = '';
        $filepath = '';

        // Attempt image generation if enabled
        if ($imageGenEnabled) {
            $imageUrl = generateImageWithFal($prompt);
            if ($imageUrl) {
                $saved = downloadAndSaveImage($imageUrl, 'article-' . $articleId . '-' . $type);
                if ($saved) {
                    $filename = $saved;
                    $filepath = 'uploads/' . $saved;

                    // For the hero image, also update articles.hero_image
                    if ($type === 'hero') {
                        $pdo->prepare('UPDATE articles SET hero_image = ? WHERE id = ?')
                            ->execute([$saved, $articleId]);
                    }
                }
            }
        }

        $mediaInsert->execute([$articleId, $filename, $filepath, $type, $altText, $prompt]);
        $savedImages[] = [
            'media_id'    => (int) $pdo->lastInsertId(),
            'type'        => $type,
            'prompt_used' => $prompt,
            'filename'    => $filename,
            'generated'   => (bool) $filename,
        ];
    }

    return [
        'article_id'   => $articleId,
        'slug'         => $slug,
        'headline'     => $story['headline'],
        'tokens_used'  => $result['tokens_used'],
        'cost_usd'     => $result['cost_usd'],
        'image_prompts' => $savedImages,
    ];
}

// ============================================================
// AGENT PICKER — round-robin by beat
// ============================================================

function pickAgentForSection(string $section): string {
    $sectionMap = [
        'politics'    => ['dolores-vendetta', 'vex-moriarty'],
        'elections'   => ['dolores-vendetta'],
        'religion'    => ['esperanza-lux', 'vex-moriarty'],
        'lgbtq'       => ['roxanne-blaze'],
        'culture'     => ['roxanne-blaze', 'chip-largo'],
        'fashion'     => ['fabrizia-sloane'],
        'tech'        => ['roxanne-blaze', 'chip-largo'],
        'pop_culture' => ['chip-largo'],
        'film'        => ['chip-largo'],
        'world'       => ['vex-moriarty'],
        'national'    => ['vex-moriarty', 'dolores-vendetta'],
        'local'       => ['chip-largo'],
        'news'        => ['the-desk'],
    ];

    $candidates = $sectionMap[$section] ?? ['the-desk'];
    $pdo        = getDB();
    $least      = null;
    $leastCount = PHP_INT_MAX;

    foreach ($candidates as $slug) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM articles a
             JOIN staff s ON a.author_id = s.id
             WHERE s.slug = ? AND a.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );
        $stmt->execute([$slug]);
        $count = (int) $stmt->fetchColumn();
        if ($count < $leastCount) {
            $leastCount = $count;
            $least      = $slug;
        }
    }

    return $least ?? 'the-desk';
}