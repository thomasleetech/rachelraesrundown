<?php
/**
 * Rachel Rae's Rundown — admin/settings.php
 */
session_start();
require_once __DIR__ . '/../config.php';
if (!($_SESSION['rrr_admin'] ?? false)) { header('Location: /admin/'); exit; }
$pdo = getDB();
$msg = '';

// Default nav categories (used if none saved yet)
$defaultNavCategories = [
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle nav categories separately
    if (isset($_POST['nav_categories_json'])) {
        $navJson = trim($_POST['nav_categories_json']);
        $parsed  = json_decode($navJson, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
            setSetting('nav_categories', $navJson);
        }
    }

    $keys = ['site_name','site_tagline','stories_per_day','llm_model','llm_max_tokens',
             'auto_publish','publish_delay_hours','breaking_story_id','maintenance_mode',
             'social_queue_enabled','image_gen_enabled','admin_email',
             'cron_story_length','cron_image_count'];
    foreach ($keys as $k) {
        if (isset($_POST[$k])) setSetting($k, trim($_POST[$k]));
    }
    $msg = '✓ Settings saved.';
}

// Load all settings
$allSettings = $pdo->query("SELECT `key`, `value` FROM settings ORDER BY `key`")->fetchAll(PDO::FETCH_KEY_PAIR);
function sv(array $s, string $k, string $d=''): string { return htmlspecialchars($s[$k]??$d,ENT_QUOTES); }

// Load current nav categories
$navCategoriesRaw = $allSettings['nav_categories'] ?? '';
$navCategories    = $navCategoriesRaw ? json_decode($navCategoriesRaw, true) : null;
if (!is_array($navCategories)) $navCategories = $defaultNavCategories;
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Settings — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@300;400;500&family=Cormorant+Garamond:wght@700&display=swap" rel="stylesheet">
<style>
:root{--bg:#1A1714;--sf:#222018;--bd:#3A3530;--tx:#C2BCB2;--mu:#6A6460;--gd:#C8960F;}
*{margin:0;padding:0;box-sizing:border-box;}body{background:var(--bg);color:var(--tx);font-family:'DM Mono',monospace;font-size:12px;}
header{background:#0E0C0A;border-bottom:1px solid var(--bd);padding:12px 28px;display:flex;align-items:center;justify-content:space-between;}
header h1{font-family:'Cormorant Garamond',serif;color:var(--gd);font-size:24px;}
nav a{color:var(--mu);text-decoration:none;margin-left:20px;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;}
nav a:hover{color:var(--gd);}
.wrap{max-width:760px;margin:0 auto;padding:28px;}
.group{background:var(--sf);border:1px solid var(--bd);padding:20px 24px;margin-bottom:20px;}
.group h2{font-family:'Cormorant Garamond',serif;color:var(--gd);font-size:20px;margin-bottom:16px;padding-bottom:8px;border-bottom:1px solid var(--bd);}
.field{margin-bottom:14px;}
label{display:block;font-size:10px;letter-spacing:0.12em;text-transform:uppercase;color:var(--mu);margin-bottom:4px;}
input,select,textarea{width:100%;background:#0E0C0A;border:1px solid var(--bd);color:var(--tx);font-family:'DM Mono',monospace;font-size:12px;padding:7px 10px;outline:none;}
input[type=checkbox]{width:auto;}
textarea{resize:vertical;min-height:60px;}
button{background:var(--gd);color:#28241F;border:none;font-family:'DM Mono',monospace;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;padding:9px 24px;cursor:pointer;margin-top:8px;}
.msg{color:#5DC490;font-size:11px;margin-bottom:16px;}
.hint{font-size:10px;color:var(--mu);margin-top:3px;}
/* Nav category editor */
.nav-editor{margin-top:10px;}
.nav-group{background:#0E0C0A;border:1px solid var(--bd);padding:14px;margin-bottom:10px;position:relative;}
.nav-group-header{display:flex;align-items:center;gap:10px;margin-bottom:10px;}
.nav-group-header input{flex:1;}
.nav-item-row{display:flex;gap:8px;align-items:center;margin-bottom:6px;padding-left:16px;}
.nav-item-row input{flex:1;}
.btn-sm{background:var(--bd);color:var(--tx);border:none;font-family:'DM Mono',monospace;font-size:10px;padding:4px 10px;cursor:pointer;letter-spacing:0.06em;text-transform:uppercase;}
.btn-sm:hover{background:var(--gd);color:#28241F;}
.btn-danger{background:#6B2A1A;color:#D4A090;}
.btn-danger:hover{background:#A83018;color:#fff;}
</style>
</head><body>
<header><h1>Settings</h1>
  <nav><a href="/admin/">Dashboard</a><a href="/admin/articles.php">Articles</a><a href="/admin/generate.php">Generate</a><a href="/admin/settings.php">Settings</a><a href="/admin/cron_log.php">Cron Log</a><a href="/admin/?logout=1">Logout</a></nav>
</header>
<div class="wrap">
  <?php if ($msg): ?><div class="msg"><?=e($msg)?></div><?php endif; ?>
  <form method="post" id="settings-form">

    <div class="group">
      <h2>Site Identity</h2>
      <div class="field"><label>Site Name</label><input name="site_name" value="<?=sv($allSettings,'site_name',"Rachel Rae's Rundown")?>"></div>
      <div class="field"><label>Tagline</label><input name="site_tagline" value="<?=sv($allSettings,'site_tagline')?>"></div>
      <div class="field"><label>Admin Email</label><input name="admin_email" value="<?=sv($allSettings,'admin_email', siteMail('rachel'))?>"></div>
    </div>

    <div class="group">
      <h2>Navigation Dropdowns</h2>
      <div class="hint" style="margin-bottom:14px;">Configure the navigation bar dropdown menus. Each group becomes a top-level nav item with a dropdown. Items link to /section/&lt;slug&gt; unless a custom URL is specified.</div>
      <div class="nav-editor" id="nav-editor"></div>
      <div style="display:flex;gap:8px;margin-top:8px;">
        <button type="button" class="btn-sm" onclick="addNavGroup()">+ Add Dropdown</button>
        <button type="button" class="btn-sm" onclick="resetNavDefaults()">Reset to Defaults</button>
      </div>
      <input type="hidden" name="nav_categories_json" id="nav-categories-json">
    </div>

    <div class="group">
      <h2>Content Generation</h2>
      <div class="field">
        <label>Stories per day</label>
        <input name="stories_per_day" type="number" min="1" max="50" value="<?=sv($allSettings,'stories_per_day','6')?>">
        <div class="hint">Divided across 12 cron runs (every 2 hrs). 6 = ~0.5 stories/run.</div>
      </div>
      <div class="field">
        <label>LLM Model</label>
        <select name="llm_model">
          <?php foreach (['claude-sonnet-4-6'=>'Claude Sonnet 4.6 (recommended)','claude-opus-4-6'=>'Claude Opus 4.6 (expensive)','claude-haiku-4-5-20251001'=>'Claude Haiku 4.5 (cheap)'] as $v=>$l): ?>
          <option value="<?=$v?>" <?=(sv($allSettings,'llm_model','claude-sonnet-4-6')===$v?'selected':'')?>><?=e($l)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Max tokens per story</label><input name="llm_max_tokens" type="number" min="500" max="4096" value="<?=sv($allSettings,'llm_max_tokens','2048')?>"></div>
      <div class="field">
        <label>Publish delay (hours after generation)</label>
        <input name="publish_delay_hours" type="number" min="0" max="72" value="<?=sv($allSettings,'publish_delay_hours','2')?>">
      </div>
      <div class="field">
        <label><input type="checkbox" name="auto_publish" value="1" <?=(sv($allSettings,'auto_publish','1')==='1'?'checked':'')?>> Auto-publish without review</label>
        <div class="hint">If unchecked, all generated stories go to draft and you approve them manually.</div>
      </div>
    </div>

    <div class="group">
      <h2>Breaking News</h2>
      <div class="field">
        <label>Breaking Story ID (leave blank to clear)</label>
        <input name="breaking_story_id" value="<?=sv($allSettings,'breaking_story_id')?>">
        <div class="hint">Article ID to display in the breaking news bar. Find IDs in the Articles list.</div>
      </div>
    </div>

    <div class="group">
      <h2>Features</h2>
      <div class="field"><label><input type="checkbox" name="social_queue_enabled" value="1" <?=(sv($allSettings,'social_queue_enabled','0')==='1'?'checked':'')?>> Enable social media queue</label></div>
      <div class="field"><label><input type="checkbox" name="image_gen_enabled" value="1" <?=(sv($allSettings,'image_gen_enabled','0')==='1'?'checked':'')?>> Enable GFXBOT-7 image generation</label></div>
      <div class="field"><label><input type="checkbox" name="maintenance_mode" value="1" <?=(sv($allSettings,'maintenance_mode','0')==='1'?'checked':'')?>> Maintenance mode (hides site from public)</label></div>
    </div>

    <div class="group">
      <h2>Cron Defaults</h2>
      <p style="font-size:11px;color:var(--mu);margin-bottom:14px;">Applied to all auto-generated stories. Manual generation on the Generate page can override these per story.</p>
      <div class="field">
        <label>Auto-Generation Story Length</label>
        <select name="cron_story_length">
          <?php foreach (['brief'=>'Brief (250–350 words)','short'=>'Short (400–550 words)','standard'=>'Standard (700–900 words)','long'=>'Long (900–1100 words)','feature'=>'Feature (1200–1600 words)'] as $k=>$l): ?>
          <option value="<?=$k?>" <?=(sv($allSettings,'cron_story_length','standard')===$k?'selected':'')?>><?=e($l)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Auto-Generation Images per Story</label>
        <select name="cron_image_count">
          <option value="0" <?=(sv($allSettings,'cron_image_count','0')==='0'?'selected':'')?>>Agent decides (1-2)</option>
          <option value="1" <?=(sv($allSettings,'cron_image_count','0')==='1'?'selected':'')?>>Always 1</option>
          <option value="2" <?=(sv($allSettings,'cron_image_count','0')==='2'?'selected':'')?>>Always 2</option>
        </select>
      </div>
    </div>

    <button type="submit">Save All Settings</button>
  </form>
</div>

<script>
// Nav category editor
let navData = <?= json_encode($navCategories, JSON_UNESCAPED_UNICODE) ?>;
const defaultNavData = <?= json_encode($defaultNavCategories, JSON_UNESCAPED_UNICODE) ?>;

function renderNavEditor() {
  const container = document.getElementById('nav-editor');
  container.innerHTML = '';
  navData.forEach((group, gi) => {
    const div = document.createElement('div');
    div.className = 'nav-group';
    let itemsHtml = '';
    (group.items || []).forEach((item, ii) => {
      const sectionOrUrl = item.url ? item.url : (item.section || '');
      const isUrl = !!item.url;
      itemsHtml += `<div class="nav-item-row">
        <input type="text" value="${esc(item.label)}" placeholder="Display label" onchange="updateItem(${gi},${ii},'label',this.value)" style="max-width:180px;">
        <input type="text" value="${esc(sectionOrUrl)}" placeholder="${isUrl ? 'URL path' : 'section slug'}" onchange="updateItemTarget(${gi},${ii},this.value)" style="max-width:180px;">
        <button type="button" class="btn-sm btn-danger" onclick="removeItem(${gi},${ii})">×</button>
      </div>`;
    });
    div.innerHTML = `
      <div class="nav-group-header">
        <span style="color:var(--gd);font-size:10px;letter-spacing:0.1em;text-transform:uppercase;">Dropdown ${gi+1}:</span>
        <input type="text" value="${esc(group.label)}" placeholder="Menu label (e.g. News)" onchange="updateGroupLabel(${gi},this.value)">
        <button type="button" class="btn-sm btn-danger" onclick="removeGroup(${gi})">Remove</button>
      </div>
      <div style="display:flex;gap:8px;padding-left:16px;margin-bottom:6px;">
        <span style="font-size:9px;color:var(--mu);flex:1;max-width:180px;">LABEL</span>
        <span style="font-size:9px;color:var(--mu);flex:1;max-width:180px;">SECTION SLUG OR URL</span>
        <span style="width:36px;"></span>
      </div>
      ${itemsHtml}
      <button type="button" class="btn-sm" onclick="addItem(${gi})" style="margin-left:16px;">+ Add Item</button>
    `;
    container.appendChild(div);
  });
  syncJson();
}

function esc(str) {
  const d = document.createElement('div');
  d.textContent = str || '';
  return d.innerHTML.replace(/"/g, '&quot;');
}

function updateGroupLabel(gi, val) { navData[gi].label = val; syncJson(); }
function updateItem(gi, ii, key, val) { navData[gi].items[ii][key] = val; syncJson(); }
function updateItemTarget(gi, ii, val) {
  if (val.startsWith('/')) {
    delete navData[gi].items[ii].section;
    navData[gi].items[ii].url = val;
  } else {
    delete navData[gi].items[ii].url;
    navData[gi].items[ii].section = val;
  }
  syncJson();
}
function removeItem(gi, ii) { navData[gi].items.splice(ii, 1); renderNavEditor(); }
function removeGroup(gi) { navData.splice(gi, 1); renderNavEditor(); }
function addItem(gi) { navData[gi].items.push({label: '', section: ''}); renderNavEditor(); }
function addNavGroup() { navData.push({label: 'New Menu', items: [{label: '', section: ''}]}); renderNavEditor(); }
function resetNavDefaults() { navData = JSON.parse(JSON.stringify(defaultNavData)); renderNavEditor(); }
function syncJson() { document.getElementById('nav-categories-json').value = JSON.stringify(navData); }

renderNavEditor();
</script>
</body></html>
