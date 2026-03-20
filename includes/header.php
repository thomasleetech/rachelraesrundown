<?php
/**
 * Rachel Rae's Rundown — includes/header.php
 * Shared across all public pages. Outputs everything above <main>.
 * Nav dropdown categories are loaded from the settings table (configurable in admin).
 */
if (!defined('DB_HOST')) require_once __DIR__ . '/../config.php';

$pdo = getDB();

// Ticker: latest 12 headlines
$tickerRows = $pdo->query(
    "SELECT slug, headline FROM articles
     WHERE status = 'live'
     ORDER BY publish_at DESC LIMIT 12"
)->fetchAll();

$tickerHtml = '';
foreach ($tickerRows as $t) {
    $tickerHtml .= '<span><a href="/article/' . e($t['slug']) . '" style="color:inherit;text-decoration:none;">'
                 . e($t['headline']) . '</a></span><span class="bull"> ◆ </span>';
}
if (!$tickerHtml) {
    $tickerHtml = '<span>Welcome to Rachel Rae\'s Rundown — San Antonio\'s most trusted source of complete nonsense</span>';
}

// Current page for active nav state
$currentFile = basename($_SERVER['PHP_SELF']);
$currentSection = $_GET['s'] ?? '';

// Load nav categories from settings (falls back to defaults if not configured)
$navCategoriesJson = getSetting('nav_categories', '');
$navCategories = $navCategoriesJson ? json_decode($navCategoriesJson, true) : null;
if (!is_array($navCategories)) {
    // Default categories matching the prototype
    $navCategories = [
        ['label' => 'News',    'items' => [
            ['label' => 'Politics',       'section' => 'politics'],
            ['label' => 'Elections',      'section' => 'elections'],
            ['label' => 'National',       'section' => 'national'],
            ['label' => 'World',          'section' => 'world'],
            ['label' => 'Local SA',       'section' => 'local'],
            ['label' => 'Crime & Chaos',  'section' => 'crime'],
        ]],
        ['label' => 'Culture', 'items' => [
            ['label' => 'Pop Culture',    'section' => 'culture'],
            ['label' => 'Fashion',        'section' => 'fashion'],
            ['label' => 'Food & Drink',   'section' => 'food'],
            ['label' => 'Music',          'section' => 'music'],
            ['label' => 'Film',           'section' => 'film'],
            ['label' => 'Tech',           'section' => 'tech'],
        ]],
        ['label' => 'Opinion', 'items' => [
            ['label' => 'Hot Takes',              'section' => 'opinion'],
            ['label' => 'Letters to Nobody',      'section' => 'letters'],
            ['label' => 'Astrology (With Violence)', 'section' => 'astrology'],
        ]],
        ['label' => 'Religion', 'items' => [
            ['label' => 'Vatican Updates',   'section' => 'religion'],
            ['label' => 'Megachurch Watch',  'section' => 'megachurch'],
            ['label' => 'Miracles & Myths',  'section' => 'miracles'],
        ]],
        ['label' => 'LGBTQ+', 'items' => [
            ['label' => 'Pride & Prejudice', 'section' => 'lgbtq'],
            ['label' => 'Drag Dispatch',     'section' => 'drag'],
            ['label' => 'Policy Watch',      'section' => 'policy'],
        ]],
        ['label' => 'Cosmic', 'items' => [
            ['label' => 'First Contact',       'section' => 'world'],
            ['label' => 'Prophecy',            'section' => 'religion'],
            ['label' => 'Time Travel',         'section' => 'culture'],
            ['label' => 'Alternate Universes', 'section' => 'culture'],
        ]],
        ['label' => 'Staff', 'items' => [
            ['label' => 'Meet the Team',      'url' => '/staff'],
            ['label' => 'About the Rundown',  'url' => '/about'],
            ['label' => "Publisher's Note",   'url' => '/article/publishers-note'],
            ['label' => 'Submit a Tip',       'url' => '/article/submit-a-tip'],
        ]],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?>Rachel Rae's Rundown</title>
<meta name="description" content="<?= isset($pageDesc) ? e($pageDesc) : "San Antonio's most trusted source of complete nonsense. Satire. All of it." ?>">
<meta property="og:site_name" content="Rachel Rae's Rundown">
<meta property="og:title" content="<?= isset($pageTitle) ? e($pageTitle) : "Rachel Rae's Rundown" ?>">
<meta property="og:description" content="<?= isset($pageDesc) ? e($pageDesc) : 'All the news that\'s unfit to print.' ?>">
<meta property="og:url" content="<?= e(SITE_URL . $_SERVER['REQUEST_URI']) ?>">
<link rel="canonical" href="<?= e(SITE_URL . strtok($_SERVER['REQUEST_URI'],'?')) ?>">
<link rel="alternate" type="application/rss+xml" title="Rachel Rae's Rundown" href="/rss">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400;1,600&family=EB+Garamond:ital,wght@0,400;0,500;0,700;1,400;1,500&family=DM+Mono:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<!-- TICKER -->
<div class="ticker-wrap">
  <div class="ticker-inner"><?= $tickerHtml ?></div>
</div>

<!-- NAV -->
<nav>
  <div class="nav-meta">
    <span>📍 San Antonio, TX · <?= e(SITE_DOMAIN) ?></span>
    <span>☀️ <?= date('l, F j, Y') ?></span>
    <span><a href="/rss" style="color:inherit;">RSS</a> · <a href="/search" style="color:inherit;">Search</a></span>
  </div>
  <div class="nav-links">
    <div class="nav-item"><a href="/" <?= $currentFile==='index.php'?'style="color:var(--burnt-orange);"':'' ?>>Home</a></div>

    <?php foreach ($navCategories as $group): ?>
    <div class="nav-item">
      <a><?= e($group['label']) ?> ▾</a>
      <div class="dropdown">
        <?php foreach ($group['items'] ?? [] as $item): ?>
          <?php
            $href = isset($item['url']) ? $item['url'] : '/section/' . ($item['section'] ?? '');
          ?>
          <a href="<?= e($href) ?>"><?= e($item['label']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <div class="nav-item"><a href="/about">About</a></div>
    <div class="nav-item"><a href="/search">🔍</a></div>
  </div>
</nav>

<!-- MASTHEAD (only on homepage) -->
<?php if ($currentFile === 'index.php'): ?>
<div class="masthead">
  <div class="mast-eyebrow">San Antonio's Only Newspaper That Tells You the Truth About the Lies</div>
  <div class="mast-title">Rachel Rae's <em>Rundown</em></div>
  <div class="mast-tagline">"All the news that's unfit to print — delivered with love and zero remorse."</div>
  <div class="mast-rule">
    <div class="mast-rule-line"></div>
    <div class="mast-rule-diamond">✦</div>
    <div class="mast-rule-line"></div>
  </div>
  <div class="mast-meta">
    <span><?= date('l, F j, Y') ?></span>
    <span><?= e(SITE_DOMAIN) ?></span>
    <span>Price: Your Last Remaining Illusion</span>
  </div>
</div>
<div class="deco-bar"></div>
<?php endif; ?>
