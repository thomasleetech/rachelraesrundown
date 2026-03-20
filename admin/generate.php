<?php
/**
 * Rachel Rae's Rundown — admin/generate.php
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/llm.php';
if (!($_SESSION['rrr_admin'] ?? false)) { header('Location: /admin/'); exit; }

$pdo      = getDB();
$agents   = $pdo->query('SELECT id, slug, display_name, tier FROM staff WHERE is_active=1 ORDER BY sort_order')->fetchAll();
// Build sections list from nav categories setting (or use defaults)
$navJson = getSetting('nav_categories', '');
$navCats = $navJson ? json_decode($navJson, true) : null;
$sections = [];
if (is_array($navCats)) {
    foreach ($navCats as $group) {
        foreach ($group['items'] ?? [] as $item) {
            if (!empty($item['section']) && !in_array($item['section'], $sections)) {
                $sections[] = $item['section'];
            }
        }
    }
}
if (!$sections) {
    $sections = ['politics','elections','national','world','local','culture','fashion',
                 'tech','film','music','religion','lgbtq','opinion','crime'];
}

// ---- AJAX endpoint ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    $agentSlug  = preg_replace('/[^a-z0-9\-]/', '', $_POST['agent_slug'] ?? 'the-desk');
    $section    = preg_replace('/[^a-z0-9_\-]/', '', $_POST['section'] ?? 'news');
    $prompt     = trim($_POST['prompt'] ?? '');
    $imageCount = max(0, min(2, (int)($_POST['image_count'] ?? 0)));
    $lengthKey  = array_key_exists($_POST['length'] ?? '', STORY_LENGTHS) ? $_POST['length'] : 'short';
    $darkness   = max(1, min(10, (int)($_POST['darkness'] ?? 10)));

    if (!$prompt || !$agentSlug) {
        echo json_encode(['error' => 'Prompt and agent are required.']);
        exit;
    }
    try {
        $result = generateStory($agentSlug, $prompt, $section, $imageCount, $lengthKey, $darkness);
        $result['preview_token'] = md5(ADMIN_PASSWORD . $result['slug']);
        $result['length_label']  = STORY_LENGTHS[$lengthKey]['label'];
        $result['length_words']  = STORY_LENGTHS[$lengthKey]['words'];
        echo json_encode(['success' => true, 'result' => $result]);
    } catch (Throwable $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
$imageGenOn = getSetting('image_gen_enabled','0') === '1';
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Generate — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@300;400;500&family=Cormorant+Garamond:ital,wght@0,700;1,400&display=swap" rel="stylesheet">
<style>
:root{--bg:#1A1714;--sf:#222018;--bd:#3A3530;--tx:#C2BCB2;--mu:#6A6460;--gd:#C8960F;--te:#276B61;--or:#B84A18;}
*{margin:0;padding:0;box-sizing:border-box;}
body{background:var(--bg);color:var(--tx);font-family:'DM Mono',monospace;font-size:12px;}
header{background:#0E0C0A;border-bottom:1px solid var(--bd);padding:12px 28px;display:flex;align-items:center;justify-content:space-between;}
header h1{font-family:'Cormorant Garamond',serif;color:var(--gd);font-size:24px;}
nav a{color:var(--mu);text-decoration:none;margin-left:20px;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;}
nav a:hover{color:var(--gd);}
.wrap{max-width:860px;margin:0 auto;padding:32px 28px;}
label{display:block;font-size:10px;letter-spacing:0.12em;text-transform:uppercase;color:var(--mu);margin-bottom:5px;margin-top:16px;}
select,textarea{width:100%;background:#0E0C0A;border:1px solid var(--bd);color:var(--tx);font-family:'DM Mono',monospace;font-size:12px;padding:8px 12px;outline:none;resize:vertical;}
.agent-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:8px;margin-top:6px;}
.agent-btn{background:#0E0C0A;border:1px solid var(--bd);padding:10px 12px;cursor:pointer;text-align:left;color:var(--tx);font-family:'DM Mono',monospace;font-size:11px;transition:border-color 0.15s;}
.agent-btn.selected{border-color:var(--gd);color:var(--gd);}
.agent-btn:hover{background:var(--sf);}
.agent-name{font-size:12px;font-weight:500;display:block;}
.agent-role{font-size:9px;color:var(--mu);letter-spacing:0.06em;text-transform:uppercase;margin-top:2px;display:block;}
.len-btn{cursor:pointer;background:#0E0C0A;border:1px solid var(--bd);padding:8px 10px;display:flex;flex-direction:column;gap:3px;transition:border-color 0.15s;}
.prompt-ideas{margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;}
.idea{background:#0E0C0A;border:1px solid var(--bd);padding:4px 10px;font-size:10px;color:var(--mu);cursor:pointer;}
.idea:hover{color:var(--gd);border-color:var(--gd);}
.img-toggle{display:flex;gap:14px;margin-top:6px;}
.img-opt{display:flex;align-items:center;gap:6px;cursor:pointer;}
.img-opt input{accent-color:var(--gd);}
.img-opt span{font-size:11px;color:var(--tx);}
.hint{font-size:10px;color:var(--mu);margin-left:2px;}
/* Darkness */
.darkness-row{display:flex;align-items:center;gap:14px;margin-top:6px;}
.darkness-row input[type=range]{flex:1;cursor:pointer;height:4px;}
.darkness-val{font-size:20px;min-width:28px;text-align:center;font-weight:500;transition:color 0.2s;}
.darkness-labels{display:flex;justify-content:space-between;font-size:9px;color:var(--mu);letter-spacing:0.06em;margin-top:4px;}
.darkness-desc{font-size:10px;color:var(--mu);margin-top:5px;min-height:14px;}
/* Generate button */
#gen-btn{background:var(--gd);color:#28241F;border:none;font-family:'DM Mono',monospace;font-size:12px;
         letter-spacing:0.12em;text-transform:uppercase;padding:12px 32px;cursor:pointer;margin-top:20px;
         transition:opacity 0.2s,background 0.2s;}
#gen-btn:disabled{opacity:0.45;cursor:not-allowed;}
/* Progress */
#progress-wrap{display:none;margin-top:24px;background:#0A0908;border:1px solid var(--bd);border-left:3px solid var(--gd);padding:20px 24px;}
.pb-track{background:#1A1714;height:3px;width:100%;margin-bottom:18px;overflow:hidden;}
.pb-fill{height:3px;background:var(--gd);width:0%;transition:width 0.7s cubic-bezier(0.4,0,0.2,1);}
.stage{font-size:11px;color:#3A3530;margin-bottom:5px;padding-left:18px;position:relative;transition:color 0.3s;}
.stage::before{content:'·';position:absolute;left:0;color:inherit;}
.stage.active{color:var(--gd);}
.stage.active::before{content:'⟳';animation:spin 1s linear infinite;}
.stage.done{color:#5DC490;}
.stage.done::before{content:'✓';}
@keyframes spin{to{transform:rotate(360deg)}}
/* Result */
#result-wrap{display:none;margin-top:24px;}
.result-box{background:#0A1A12;border:1px solid #1A4A28;padding:22px;}
.result-hed{font-family:'Cormorant Garamond',serif;font-size:22px;color:var(--gd);margin-bottom:8px;line-height:1.2;}
.result-meta{color:var(--mu);font-size:11px;margin-bottom:14px;line-height:1.9;}
.img-prompts{margin-top:14px;border-top:1px solid #1A4A28;padding-top:14px;}
.img-prompt-item{background:#081208;border:1px solid #1A3020;padding:10px 14px;margin-bottom:8px;}
.ipl{font-size:9px;letter-spacing:0.14em;text-transform:uppercase;color:var(--te);margin-bottom:4px;}
.ipt{font-size:11px;color:#9AB8A0;line-height:1.6;}
.err-box{background:#1A0808;border:1px solid #4A1818;padding:14px;color:#C07070;margin-top:16px;font-size:11px;line-height:1.6;}
</style>
</head><body>
<header>
  <h1>Generate Story</h1>
  <nav>
    <a href="/admin/">Dashboard</a><a href="/admin/articles.php">Articles</a>
    <a href="/admin/generate.php">Generate</a><a href="/admin/settings.php">Settings</a>
    <a href="/admin/?logout=1">Logout</a>
  </nav>
</header>

<div class="wrap">
  <form id="genform">

    <label>Choose Agent</label>
    <div class="agent-grid">
      <?php foreach ($agents as $a): ?>
      <button type="button" class="agent-btn" onclick="selectAgent('<?=e($a['slug'])?>', this)">
        <span class="agent-name"><?=e($a['display_name'])?></span>
        <span class="agent-role"><?=e($a['tier'])?></span>
      </button>
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="agent_slug" id="agent_slug" value="the-desk">

    <label>Section</label>
    <select name="section">
      <?php foreach ($sections as $s): ?>
      <option value="<?=$s?>"><?=ucfirst($s)?></option>
      <?php endforeach; ?>
    </select>

    <label>Story Length</label>
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:6px;margin-top:6px;">
      <?php foreach (STORY_LENGTHS as $key => $cfg):
        $sel = ($key === 'short'); ?>
      <div class="len-btn" data-key="<?=e($key)?>" onclick="selectLength('<?=e($key)?>', this)"
           style="border-color:<?=$sel?'var(--gd)':'var(--bd)'?>;">
        <span style="font-size:11px;color:<?=$sel?'var(--gd)':'var(--tx)'?>;font-weight:<?=$sel?'500':'400'?>;"><?=e($cfg['label'])?></span>
        <span style="font-size:9px;color:var(--mu);letter-spacing:0.04em;"><?=e($cfg['words'])?> words</span>
      </div>
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="length" id="length-val" value="short">

    <label>Story Prompt / Angle</label>
    <textarea name="prompt" rows="4" placeholder="What should the agent write about? Be specific — the weirder the better."></textarea>

    <div class="prompt-ideas">
      <?php
      $ideas = [
        'God spotted at a Waffle House in Amarillo at 3am',
        'Senator introduces bill requiring all legislation explained via hand puppets',
        'Megachurch pastor blesses private jet; second pastor flown in on another jet for the blessing',
        'Local drag queen defeats Texas AG in foot race; AG claims heels were structurally unfair',
        'Time traveler from 2087 refuses to confirm upcoming events but is visibly relieved about something',
        'H-E-B achieves formal religious status in Texas',
        'AI therapist logs 1M sessions, charges $400 each, offers no useful advice',
        'Aliens confirm they find American truck stops deeply moving',
      ];
      foreach ($ideas as $idea): ?>
      <div class="idea" onclick="document.querySelector('[name=prompt]').value='<?=addslashes($idea)?>'">
        <?=e(substr($idea,0,52))?>…
      </div>
      <?php endforeach; ?>
    </div>

    <label>Darkness / Edge</label>
    <div class="darkness-row">
      <input type="range" name="darkness" id="darkness-slider" min="1" max="10" step="1" value="10"
             oninput="updateDarkness(this.value)">
      <span class="darkness-val" id="darkness-val">10</span>
    </div>
    <div class="darkness-labels">
      <span>☀️ Light &amp; Playful</span>
      <span>Standard Satire</span>
      <span>🔥 Maximum Bleak</span>
    </div>
    <div class="darkness-desc" id="darkness-desc">Maximum darkness. Bleak, savage, unflinching satire. Pull no punches.</div>

    <label>Images for This Story</label>
    <div class="img-toggle">
      <label class="img-opt">
        <input type="radio" name="image_count" value="0" checked>
        <span>Agent decides <span class="hint">(1–2)</span></span>
      </label>
      <label class="img-opt">
        <input type="radio" name="image_count" value="1">
        <span>1 image</span>
      </label>
      <label class="img-opt">
        <input type="radio" name="image_count" value="2">
        <span>2 images</span>
      </label>
    </div>
    <?php if (!$imageGenOn): ?>
    <div style="font-size:10px;color:var(--or);margin-top:5px;">
      ⚠ Image generation is off — prompts saved but no images generated.
      <a href="/admin/settings.php" style="color:var(--or);">Enable in Settings.</a>
    </div>
    <?php endif; ?>

    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
      <button type="button" id="gen-btn" onclick="submitGenerate()">⚡ Generate Now</button>
      <span style="color:var(--mu);font-size:11px;" id="cost-hint">~$0.015–0.025 depending on length</span>
    </div>
  </form>

  <!-- Progress panel -->
  <div id="progress-wrap">
    <div class="pb-track"><div class="pb-fill" id="pb-fill"></div></div>
    <div class="stage" id="s1">Contacting agent</div>
    <div class="stage" id="s2">Agent reviewing the prompt</div>
    <div class="stage" id="s3">Writing the story</div>
    <div class="stage" id="s4">Generating image prompts</div>
    <div class="stage" id="s5">Saving to database</div>
    <?php if ($imageGenOn): ?>
    <div class="stage" id="s6">Generating images via FAL.ai</div>
    <?php endif; ?>
  </div>

  <!-- Result panel -->
  <div id="result-wrap"></div>
</div>

<script>
const darkDescs = {
  1:'Light and playful. Gentle satire, family-friendly absurdity. No edge.',
  2:'Light and playful. Gentle satire, family-friendly absurdity.',
  3:'Mild satire. Funny and sharp but stays broadly accessible.',
  4:'Mild satire. Funny and sharp, light irony.',
  5:'Standard satirical edge. Pointed but not bleak. Smart comedy with bite.',
  6:'Standard satirical edge. Pointed, smart, adult-ish.',
  7:'Dark and biting. Cynical, uncomfortable truths delivered with wit.',
  8:'Dark and biting. Adult themes OK. Moral chaos welcome.',
  9:'Very dark. Bleak, savage, unflinching. Pull almost no punches.',
  10:'Maximum darkness. Bleak, savage, unflinching satire. Pull no punches.',
};

function darkColor(v) {
  // gold (10,150,15) → orange (184,74,24)
  const t = (v - 1) / 9;
  const r = Math.round(200 + t * (184 - 200));
  const g = Math.round(150 + t * (74  - 150));
  const b = Math.round(15);
  return `rgb(${r},${g},${b})`;
}

function updateDarkness(v) {
  v = parseInt(v);
  const col = darkColor(v);
  const el  = document.getElementById('darkness-val');
  el.textContent = v;
  el.style.color = col;
  document.getElementById('darkness-slider').style.accentColor = col;
  document.getElementById('darkness-desc').textContent = darkDescs[v] || '';
}

function selectAgent(slug, btn) {
  document.getElementById('agent_slug').value = slug;
  document.querySelectorAll('.agent-btn').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
}

function selectLength(key, el) {
  document.getElementById('length-val').value = key;
  document.querySelectorAll('.len-btn').forEach(b => {
    const on = b.dataset.key === key;
    b.style.borderColor = on ? 'var(--gd)' : 'var(--bd)';
    const spans = b.querySelectorAll('span');
    spans[0].style.color      = on ? 'var(--gd)' : 'var(--tx)';
    spans[0].style.fontWeight = on ? '500' : '400';
  });
}

// --- Progress ---
const stages = () => Array.from(document.querySelectorAll('.stage'));
let   stageTimers = [];

function startProgress() {
  document.getElementById('progress-wrap').style.display = 'block';
  document.getElementById('result-wrap').style.display   = 'none';
  document.getElementById('result-wrap').innerHTML       = '';
  document.getElementById('pb-fill').style.width         = '0%';
  stages().forEach(s => s.className = 'stage');

  // Staggered activation — most time sits on "Writing the story"
  const delays = [0, 600, 1100, 5500, 6200, 7000];
  const pcts   = [8,   18,   35,   70,   88,   96];
  const ss = stages();

  stageTimers.forEach(clearTimeout);
  stageTimers = [];

  ss.forEach((s, i) => {
    stageTimers.push(setTimeout(() => {
      if (i > 0) ss[i-1].className = 'stage done';
      s.className = 'stage active';
      document.getElementById('pb-fill').style.width = (pcts[i] || 90) + '%';
    }, delays[i] || i * 800));
  });
}

function finishProgress() {
  stageTimers.forEach(clearTimeout);
  stages().forEach(s => s.className = 'stage done');
  document.getElementById('pb-fill').style.width = '100%';
  setTimeout(() => {
    document.getElementById('progress-wrap').style.display = 'none';
  }, 700);
}

// --- AJAX submit ---
async function submitGenerate() {
  const form   = document.getElementById('genform');
  const prompt = form.querySelector('[name=prompt]').value.trim();
  const agent  = document.getElementById('agent_slug').value;

  if (!prompt) { alert('Enter a story prompt first.'); return; }
  if (!agent)  { alert('Select an agent first.'); return; }

  const btn = document.getElementById('gen-btn');
  btn.disabled = true;
  btn.textContent = '⏳ Generating...';
  document.getElementById('cost-hint').textContent = '';

  startProgress();

  try {
    const resp = await fetch('/admin/generate.php', {
      method:  'POST',
      headers: {'X-Requested-With': 'XMLHttpRequest'},
      body:    new FormData(form),
    });
    const json = await resp.json();
    finishProgress();
    json.error ? renderError(json.error) : renderResult(json.result);
  } catch (err) {
    finishProgress();
    renderError('Request failed: ' + err.message);
  } finally {
    btn.disabled    = false;
    btn.textContent = '⚡ Generate Now';
    document.getElementById('cost-hint').textContent = '~$0.015–0.025 depending on length';
  }
}

function renderError(msg) {
  const w = document.getElementById('result-wrap');
  w.style.display = 'block';
  w.innerHTML = `<div class="err-box">⚠ ${esc(msg)}</div>`;
}

function renderResult(r) {
  let imgs = '';
  if (r.image_prompts?.length) {
    imgs = `<div class="img-prompts">
      <div style="font-size:10px;letter-spacing:0.12em;text-transform:uppercase;color:var(--mu);margin-bottom:10px;">
        ${r.image_prompts.length} Image Prompt${r.image_prompts.length > 1 ? 's' : ''} Saved
      </div>`;
    r.image_prompts.forEach(p => {
      imgs += `<div class="img-prompt-item">
        <div class="ipl">${esc(p.type.toUpperCase())} — Media #${p.media_id} ${p.generated ? '✓ Generated' : '⏳ Pending'}</div>
        <div class="ipt">${esc(p.prompt_used)}</div>
      </div>`;
    });
    imgs += '</div>';
  }

  const w = document.getElementById('result-wrap');
  w.style.display = 'block';
  w.innerHTML = `
  <div class="result-box">
    <div style="font-size:10px;letter-spacing:0.14em;text-transform:uppercase;color:var(--te);margin-bottom:10px;">
      ✓ Story Generated — ID #${r.article_id}
    </div>
    <div class="result-hed">${esc(r.headline)}</div>
    <div class="result-meta">
      Tokens: ${Number(r.tokens_used).toLocaleString()} &nbsp;·&nbsp;
      Cost: $${parseFloat(r.cost_usd).toFixed(4)} &nbsp;·&nbsp;
      Length: ${esc(r.length_label)} (${esc(r.length_words)} words) &nbsp;·&nbsp;
      Slug: ${esc(r.slug)}
    </div>
    ${imgs}
    <div style="display:flex;gap:14px;margin-top:14px;">
      <a href="/admin/edit.php?id=${r.article_id}" style="color:var(--gd);font-size:11px;">Edit story →</a>
      <a href="/article/${esc(r.slug)}?preview=${esc(r.preview_token)}" target="_blank" style="color:var(--te);font-size:11px;">Preview →</a>
    </div>
  </div>`;

  w.scrollIntoView({behavior:'smooth', block:'start'});
}

function esc(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Init
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.agent-btn').forEach(b => {
    if (b.getAttribute('onclick')?.includes('the-desk')) b.classList.add('selected');
  });
  selectLength('short', document.querySelector('[data-key="short"]'));
  updateDarkness(10);
});
</script>
</body></html>