<?php
/**
 * Rachel Rae's Rundown — admin/staff.php
 * Staff / AI Agent management — create, edit, generate bios & headshots.
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/llm.php';
if (!($_SESSION['rrr_admin'] ?? false)) { header('Location: /admin/'); exit; }

$pdo = getDB();
$msg = '';
$msgType = 'ok'; // ok or err

// ---- AJAX: Generate bio ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_bio') {
    header('Content-Type: application/json');
    $staffId = (int)($_POST['staff_id'] ?? 0);
    $context = trim($_POST['context'] ?? '');

    $staff = null;
    if ($staffId) {
        $row = $pdo->prepare('SELECT * FROM staff WHERE id = ?');
        $row->execute([$staffId]);
        $staff = $row->fetch() ?: null;
    }

    // Get existing info if editing
    $name  = $_POST['display_name'] ?? ($staff['display_name'] ?? 'New Agent');
    $title = $_POST['job_title']    ?? ($staff['job_title'] ?? '');
    $tier  = $_POST['tier']         ?? ($staff['tier'] ?? 'staff');

    $systemPrompt = <<<SYS
You are a creative writing assistant for Rachel Rae's Rundown, a satirical fake news publication.
Generate a compelling, funny bio and personality details for an AI staff member.

RESPOND ONLY IN VALID JSON. No preamble. No markdown.
{
  "bio": "2-3 sentences. Funny, specific, character-building. Written in third person.",
  "previously": "1-2 sentences about their fictional career history. Absurd but specific.",
  "hobbies": ["hobby1", "hobby2", "hobby3"],
  "quote": "A memorable one-liner this person would say. In their voice."
}
SYS;

    $userPrompt = "Generate bio details for: {$name}, {$title} (tier: {$tier}).\n";
    if ($context) $userPrompt .= "Additional context: {$context}\n";
    if ($staff && $staff['bio']) $userPrompt .= "Current bio (refresh/improve): {$staff['bio']}\n";

    try {
        $result = callLLM($systemPrompt, $userPrompt, 800);
        $text   = trim($result['text']);
        $text   = preg_replace('/^```json\s*/i', '', $text);
        $text   = preg_replace('/\s*```$/', '', $text);
        $parsed = json_decode($text, true);

        if (!$parsed) {
            echo json_encode(['error' => 'Failed to parse AI response']);
        } else {
            echo json_encode(['success' => true, 'data' => $parsed, 'cost' => $result['cost_usd']]);
        }
    } catch (Throwable $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ---- AJAX: Generate headshot SVG ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_headshot') {
    header('Content-Type: application/json');
    $name  = trim($_POST['display_name'] ?? 'Agent');
    $title = trim($_POST['job_title'] ?? '');
    $bio   = trim($_POST['bio'] ?? '');

    $systemPrompt = <<<SYS
You are a graphic designer for Rachel Rae's Rundown. Generate a stylized SVG portrait/avatar.

RULES:
- Return ONLY valid SVG markup. No preamble, no explanation, no markdown.
- The SVG must use viewBox="0 0 200 200" and be self-contained.
- Use a bold, editorial illustration style — flat shapes, limited palette.
- Use warm tones: golds (#C8960F), burnt orange (#B84A18), teal (#276B61), charcoal (#28241F), cream (#F6F1E9).
- The portrait should capture the character's personality through style (not photorealism).
- Include abstract shapes, patterns, or symbols that hint at their beat/personality.
- NO text elements in the SVG (no names, no labels).
- Keep it under 3000 characters total.
SYS;

    $userPrompt = "Create an SVG portrait for: {$name}";
    if ($title) $userPrompt .= ", {$title}";
    if ($bio) $userPrompt .= ". Personality: " . substr($bio, 0, 200);

    try {
        $result = callLLM($systemPrompt, $userPrompt, 1500);
        $svg = trim($result['text']);
        // Extract just the SVG tag if wrapped in anything
        if (preg_match('/<svg[\s\S]*<\/svg>/i', $svg, $m)) {
            $svg = $m[0];
        }
        echo json_encode(['success' => true, 'svg' => $svg, 'cost' => $result['cost_usd']]);
    } catch (Throwable $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ---- SAVE staff member ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id           = (int)($_POST['id'] ?? 0);
    $slug         = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_POST['slug'] ?? '')));
    $displayName  = trim($_POST['display_name'] ?? '');
    $jobTitle     = trim($_POST['job_title'] ?? '');
    $department   = trim($_POST['department'] ?? '');
    $tier         = in_array($_POST['tier'] ?? '', ['leadership','senior','staff','freelance','intern']) ? $_POST['tier'] : 'staff';
    $bio          = trim($_POST['bio'] ?? '');
    $previously   = trim($_POST['previously'] ?? '');
    $beat         = trim($_POST['beat'] ?? '[]');
    $systemPrompt = trim($_POST['system_prompt'] ?? '');
    $hobbies      = trim($_POST['hobbies'] ?? '[]');
    $quote        = trim($_POST['quote'] ?? '');
    $headshot_svg = trim($_POST['headshot_svg'] ?? '');
    $isActive     = isset($_POST['is_active']) ? 1 : 0;
    $sortOrder    = max(0, (int)($_POST['sort_order'] ?? 0));

    if (!$displayName || !$slug) {
        $msg = 'Name and slug are required.';
        $msgType = 'err';
    } else {
        // Validate JSON fields
        if (!json_decode($beat)) $beat = '["all"]';
        if (!json_decode($hobbies)) $hobbies = '[]';

        if ($id > 0) {
            // Update
            $stmt = $pdo->prepare(
                'UPDATE staff SET slug=?, display_name=?, job_title=?, department=?, tier=?,
                 bio=?, previously=?, beat=?, system_prompt=?, hobbies=?, quote=?,
                 headshot_svg=?, is_active=?, sort_order=? WHERE id=?'
            );
            $stmt->execute([$slug, $displayName, $jobTitle, $department, $tier,
                            $bio, $previously, $beat, $systemPrompt ?: null, $hobbies, $quote,
                            $headshot_svg ?: null, $isActive, $sortOrder, $id]);
            $msg = "Updated {$displayName}.";
        } else {
            // Insert
            $stmt = $pdo->prepare(
                'INSERT INTO staff (slug, display_name, job_title, department, tier,
                 bio, previously, beat, system_prompt, hobbies, quote, headshot_svg, is_active, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$slug, $displayName, $jobTitle, $department, $tier,
                            $bio, $previously, $beat, $systemPrompt ?: null, $hobbies, $quote,
                            $headshot_svg ?: null, $isActive, $sortOrder]);
            $msg = "Created {$displayName}.";
        }
    }
}

// ---- DELETE staff member ----
if (($_GET['delete'] ?? '') && ($_GET['tok'] ?? '')) {
    $delId = (int)$_GET['delete'];
    if ($_GET['tok'] === md5(ADMIN_PASSWORD . $delId)) {
        $pdo->prepare('DELETE FROM staff WHERE id = ?')->execute([$delId]);
        $msg = 'Staff member deleted.';
    }
}

// ---- Load all staff ----
$allStaff = $pdo->query(
    "SELECT s.*,
            (SELECT COUNT(*) FROM articles a WHERE a.author_id = s.id) AS story_count
     FROM staff s
     ORDER BY FIELD(s.tier,'leadership','senior','staff','freelance','intern'), s.sort_order"
)->fetchAll();

// ---- Editing a specific member? ----
$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editId) {
    foreach ($allStaff as $s) {
        if ($s['id'] === $editId) { $editing = $s; break; }
    }
}

// New staff mode
$isNew = isset($_GET['new']);

$tierLabels = [
    'leadership' => 'Leadership',
    'senior'     => 'Senior Staff',
    'staff'      => 'Staff Writers',
    'freelance'  => 'Freelance',
    'intern'     => 'Interns',
];
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Staff — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@300;400;500&family=Cormorant+Garamond:ital,wght@0,700;1,400&display=swap" rel="stylesheet">
<style>
:root{--bg:#1A1714;--sf:#222018;--bd:#3A3530;--tx:#C2BCB2;--mu:#6A6460;--gd:#C8960F;--or:#B84A18;--te:#276B61;--red:#A83018;}
*{margin:0;padding:0;box-sizing:border-box;}body{background:var(--bg);color:var(--tx);font-family:'DM Mono',monospace;font-size:12px;}
header{background:#0E0C0A;border-bottom:1px solid var(--bd);padding:12px 28px;display:flex;align-items:center;justify-content:space-between;}
header h1{font-family:'Cormorant Garamond',serif;color:var(--gd);font-size:24px;}
nav a{color:var(--mu);text-decoration:none;margin-left:20px;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;}
nav a:hover{color:var(--gd);}
.wrap{max-width:1300px;margin:0 auto;padding:24px 28px;}
.msg{padding:10px 14px;font-size:11px;margin-bottom:16px;border:1px solid;}
.msg-ok{color:#5DC490;border-color:#1A4A28;background:#0A1A12;}
.msg-err{color:#C07070;border-color:#4A1818;background:#1A0808;}

/* Staff grid */
.staff-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;margin-bottom:28px;}
.staff-card{background:var(--sf);border:1px solid var(--bd);overflow:hidden;position:relative;transition:border-color 0.15s;}
.staff-card:hover{border-color:var(--gd);}
.staff-headshot{width:100%;height:120px;background:linear-gradient(135deg,#1A1714,#222018);display:flex;align-items:center;justify-content:center;overflow:hidden;}
.staff-headshot svg{max-width:100%;max-height:100%;}
.staff-initial{font-family:'Cormorant Garamond',serif;font-size:60px;font-weight:700;opacity:0.12;color:var(--gd);user-select:none;}
.staff-info{padding:14px 16px;}
.staff-name{font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:700;color:var(--gd);margin-bottom:2px;}
.staff-title{font-size:10px;letter-spacing:0.08em;text-transform:uppercase;color:var(--mu);margin-bottom:6px;line-height:1.4;}
.staff-meta{font-size:10px;color:var(--mu);margin-bottom:10px;}
.staff-badges{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:10px;}
.badge{font-size:9px;letter-spacing:0.08em;text-transform:uppercase;padding:2px 7px;border:1px solid var(--bd);color:var(--mu);}
.badge-active{border-color:var(--te);color:var(--te);}
.badge-inactive{border-color:var(--red);color:var(--red);}
.staff-actions{display:flex;gap:10px;padding-top:10px;border-top:1px solid var(--bd);}
.staff-actions a{font-size:10px;letter-spacing:0.08em;text-transform:uppercase;text-decoration:none;color:var(--te);}
.staff-actions a:hover{color:var(--gd);}
.staff-actions a.del{color:var(--red);}

/* Tier section headers */
.tier-header{font-family:'Cormorant Garamond',serif;font-size:20px;color:var(--gd);border-bottom:1px solid var(--bd);padding-bottom:8px;margin:28px 0 14px;font-weight:700;}
.tier-header:first-of-type{margin-top:0;}

/* Form */
.edit-form{background:var(--sf);border:1px solid var(--bd);padding:24px;margin-bottom:28px;}
.edit-form h2{font-family:'Cormorant Garamond',serif;color:var(--gd);font-size:22px;margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid var(--bd);}
.field{margin-bottom:14px;}
.field label{display:block;font-size:10px;letter-spacing:0.12em;text-transform:uppercase;color:var(--mu);margin-bottom:4px;}
.field input,.field select,.field textarea{width:100%;background:#0E0C0A;border:1px solid var(--bd);color:var(--tx);font-family:'DM Mono',monospace;font-size:12px;padding:7px 10px;outline:none;}
.field textarea{resize:vertical;min-height:80px;}
.field .hint{font-size:10px;color:var(--mu);margin-top:3px;}
.row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;}
.btn{background:var(--gd);color:#28241F;border:none;font-family:'DM Mono',monospace;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;padding:9px 20px;cursor:pointer;}
.btn:disabled{opacity:0.4;cursor:not-allowed;}
.btn-ghost{background:transparent;border:1px solid var(--bd);color:var(--tx);}
.btn-ghost:hover{border-color:var(--gd);color:var(--gd);}
.btn-ai{background:var(--te);color:#fff;}
.btn-ai:hover{background:#2D7D6F;}

/* SVG preview */
.svg-preview{width:200px;height:200px;background:#0E0C0A;border:1px solid var(--bd);display:flex;align-items:center;justify-content:center;overflow:hidden;margin-top:6px;}
.svg-preview svg{max-width:100%;max-height:100%;}

/* Top bar */
.top-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;}
.top-bar h2{font-family:'Cormorant Garamond',serif;color:var(--gd);font-size:22px;font-weight:700;}
</style>
</head><body>
<header><h1>Staff Management</h1>
  <nav>
    <a href="/admin/">Dashboard</a><a href="/admin/articles.php">Articles</a>
    <a href="/admin/generate.php">Generate</a><a href="/admin/staff.php">Staff</a>
    <a href="/admin/settings.php">Settings</a><a href="/admin/analytics.php">Analytics</a>
    <a href="/admin/cron_log.php">Cron Log</a><a href="/" target="_blank">View Site</a>
    <a href="/admin/?logout=1">Logout</a>
  </nav>
</header>

<div class="wrap">

<?php if ($msg): ?>
<div class="msg msg-<?=$msgType?>"><?=e($msg)?></div>
<?php endif; ?>

<?php if ($editing || $isNew):
    $s = $editing ?: [
        'id'=>0,'slug'=>'','display_name'=>'','job_title'=>'','department'=>'','tier'=>'staff',
        'bio'=>'','previously'=>'','beat'=>'["all"]','system_prompt'=>'','hobbies'=>'[]','quote'=>'',
        'headshot_svg'=>'','is_active'=>1,'sort_order'=>(count($allStaff)+1),
    ];
?>
<!-- ===== EDIT / CREATE FORM ===== -->
<div class="edit-form">
  <h2><?= $editing ? 'Edit: ' . e($s['display_name']) : 'Create New Staff Member' ?></h2>
  <form method="post" id="staff-form">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?=$s['id']?>">

    <div class="row">
      <div class="field">
        <label>Display Name</label>
        <input name="display_name" id="f-name" value="<?=e($s['display_name'])?>" required placeholder="e.g. Vex Moriarty">
      </div>
      <div class="field">
        <label>Slug (URL-safe)</label>
        <input name="slug" id="f-slug" value="<?=e($s['slug'])?>" required placeholder="e.g. vex-moriarty">
      </div>
    </div>

    <div class="row">
      <div class="field">
        <label>Job Title</label>
        <input name="job_title" id="f-title" value="<?=e($s['job_title'])?>" placeholder="e.g. Prophet of Doom & Senior Correspondent">
      </div>
      <div class="field">
        <label>Department</label>
        <input name="department" value="<?=e($s['department'] ?? '')?>" placeholder="e.g. Senior Staff">
      </div>
    </div>

    <div class="row3">
      <div class="field">
        <label>Tier</label>
        <select name="tier" id="f-tier">
          <?php foreach ($tierLabels as $tk => $tl): ?>
          <option value="<?=$tk?>" <?=($s['tier']===$tk?'selected':'')?>><?=e($tl)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Sort Order</label>
        <input type="number" name="sort_order" value="<?=$s['sort_order']?>" min="0" max="100">
      </div>
      <div class="field">
        <label>Status</label>
        <div style="padding-top:7px;">
          <label style="font-size:12px;color:var(--tx);letter-spacing:0;text-transform:none;">
            <input type="checkbox" name="is_active" value="1" <?=($s['is_active']?'checked':'')?>> Active
          </label>
        </div>
      </div>
    </div>

    <div class="field">
      <label>Bio</label>
      <textarea name="bio" id="f-bio" rows="3" placeholder="Agent bio — funny, specific, third-person."><?=e($s['bio'] ?? '')?></textarea>
    </div>

    <div class="field">
      <label>Previously (career history)</label>
      <textarea name="previously" id="f-prev" rows="2" placeholder="Fictional career history."><?=e($s['previously'] ?? '')?></textarea>
    </div>

    <div class="row">
      <div class="field">
        <label>Beat (JSON array of section slugs)</label>
        <input name="beat" id="f-beat" value='<?=e($s['beat'] ?? '["all"]')?>'>
        <div class="hint">e.g. ["politics","elections","national"]</div>
      </div>
      <div class="field">
        <label>Hobbies (JSON array)</label>
        <input name="hobbies" id="f-hobbies" value='<?=e($s['hobbies'] ?? '[]')?>'>
        <div class="hint">e.g. ["doom-scrolling","existential dread","sourdough"]</div>
      </div>
    </div>

    <div class="field">
      <label>Quote</label>
      <input name="quote" id="f-quote" value="<?=e($s['quote'] ?? '')?>" placeholder="A memorable one-liner in their voice.">
    </div>

    <div class="field">
      <label>System Prompt (LLM instructions — leave blank to use default)</label>
      <textarea name="system_prompt" rows="6" placeholder="Custom system prompt for this agent. If blank, uses the default from llm.php."><?=e($s['system_prompt'] ?? '')?></textarea>
      <div class="hint">This overrides the hardcoded prompt in includes/llm.php. Include JSON output format instructions.</div>
    </div>

    <!-- Headshot SVG -->
    <div class="field">
      <label>Headshot SVG</label>
      <div style="display:flex;gap:16px;align-items:flex-start;">
        <div>
          <div class="svg-preview" id="svg-preview">
            <?php if ($s['headshot_svg']): ?>
              <?= $s['headshot_svg'] ?>
            <?php else: ?>
              <span class="staff-initial"><?=mb_strtoupper(mb_substr($s['display_name'] ?: '?', 0, 1))?></span>
            <?php endif; ?>
          </div>
          <button type="button" class="btn btn-ai" style="margin-top:8px;width:200px;" onclick="generateHeadshot()" id="gen-headshot-btn">
            Generate Headshot
          </button>
        </div>
        <div style="flex:1;">
          <textarea name="headshot_svg" id="f-headshot" rows="6" placeholder="Paste SVG markup or use Generate button."><?=e($s['headshot_svg'] ?? '')?></textarea>
          <div class="hint">Raw SVG markup. Use the Generate button to create one via AI, or paste your own.</div>
        </div>
      </div>
    </div>

    <!-- AI Generate buttons -->
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px;padding:14px 0;border-top:1px solid var(--bd);border-bottom:1px solid var(--bd);">
      <span style="font-size:10px;letter-spacing:0.12em;text-transform:uppercase;color:var(--mu);">AI Assist:</span>
      <button type="button" class="btn btn-ai" onclick="generateBio()" id="gen-bio-btn">Generate Bio & Personality</button>
      <input type="text" id="bio-context" placeholder="Optional context for bio generation..." style="flex:1;min-width:200px;background:#0E0C0A;border:1px solid var(--bd);color:var(--tx);font-family:'DM Mono',monospace;font-size:11px;padding:7px 10px;">
      <span id="ai-cost" style="font-size:10px;color:var(--mu);"></span>
    </div>

    <div style="display:flex;gap:10px;">
      <button type="submit" class="btn">Save Staff Member</button>
      <a href="/admin/staff.php" class="btn btn-ghost" style="text-decoration:none;text-align:center;">Cancel</a>
      <?php if ($editing): ?>
      <a href="/staff?slug=<?=e($s['slug'])?>" target="_blank" class="btn btn-ghost" style="text-decoration:none;text-align:center;">View Public Profile</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<script>
// Auto-generate slug from name
document.getElementById('f-name').addEventListener('input', function() {
  const slugField = document.getElementById('f-slug');
  if (!slugField.dataset.manual) {
    slugField.value = this.value.toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/[\s]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
  }
});
document.getElementById('f-slug').addEventListener('input', function() {
  this.dataset.manual = '1';
});
<?php if ($editing): ?>
document.getElementById('f-slug').dataset.manual = '1';
<?php endif; ?>

async function generateBio() {
  const btn = document.getElementById('gen-bio-btn');
  btn.disabled = true;
  btn.textContent = 'Generating...';

  const form = new FormData();
  form.append('action', 'generate_bio');
  form.append('staff_id', '<?=$s['id']?>');
  form.append('display_name', document.getElementById('f-name').value);
  form.append('job_title', document.getElementById('f-title').value);
  form.append('tier', document.getElementById('f-tier').value);
  form.append('context', document.getElementById('bio-context').value);

  try {
    const resp = await fetch('/admin/staff.php', { method: 'POST', body: form });
    const json = await resp.json();
    if (json.error) {
      alert('Error: ' + json.error);
    } else {
      const d = json.data;
      if (d.bio) document.getElementById('f-bio').value = d.bio;
      if (d.previously) document.getElementById('f-prev').value = d.previously;
      if (d.hobbies) document.getElementById('f-hobbies').value = JSON.stringify(d.hobbies);
      if (d.quote) document.getElementById('f-quote').value = d.quote;
      document.getElementById('ai-cost').textContent = 'Cost: $' + parseFloat(json.cost).toFixed(4);
    }
  } catch (err) {
    alert('Request failed: ' + err.message);
  } finally {
    btn.disabled = false;
    btn.textContent = 'Generate Bio & Personality';
  }
}

async function generateHeadshot() {
  const btn = document.getElementById('gen-headshot-btn');
  btn.disabled = true;
  btn.textContent = 'Generating...';

  const form = new FormData();
  form.append('action', 'generate_headshot');
  form.append('display_name', document.getElementById('f-name').value);
  form.append('job_title', document.getElementById('f-title').value);
  form.append('bio', document.getElementById('f-bio').value);

  try {
    const resp = await fetch('/admin/staff.php', { method: 'POST', body: form });
    const json = await resp.json();
    if (json.error) {
      alert('Error: ' + json.error);
    } else {
      document.getElementById('f-headshot').value = json.svg;
      document.getElementById('svg-preview').innerHTML = json.svg;
      const costEl = document.getElementById('ai-cost');
      const prev = costEl.textContent;
      costEl.textContent = (prev ? prev + ' + ' : 'Cost: ') + '$' + parseFloat(json.cost).toFixed(4);
    }
  } catch (err) {
    alert('Request failed: ' + err.message);
  } finally {
    btn.disabled = false;
    btn.textContent = 'Generate Headshot';
  }
}

// Live SVG preview
document.getElementById('f-headshot').addEventListener('input', function() {
  const preview = document.getElementById('svg-preview');
  if (this.value.trim().startsWith('<svg')) {
    preview.innerHTML = this.value;
  }
});
</script>

<?php else: ?>
<!-- ===== STAFF LIST ===== -->
<div class="top-bar">
  <h2>All Staff (<?=count($allStaff)?>)</h2>
  <a href="/admin/staff.php?new=1" class="btn">+ New Staff Member</a>
</div>

<?php
$currentTier = null;
foreach ($allStaff as $s):
    if ($s['tier'] !== $currentTier):
        if ($currentTier !== null) echo '</div>'; // close previous grid
        $currentTier = $s['tier'];
?>
<div class="tier-header"><?=e($tierLabels[$currentTier] ?? $currentTier)?></div>
<div class="staff-grid">
<?php endif; ?>

  <div class="staff-card">
    <div class="staff-headshot">
      <?php if (!empty($s['headshot_svg'])): ?>
        <?= $s['headshot_svg'] ?>
      <?php else: ?>
        <span class="staff-initial"><?=mb_strtoupper(mb_substr($s['display_name'], 0, 1))?></span>
      <?php endif; ?>
    </div>
    <div class="staff-info">
      <div class="staff-name"><?=e($s['display_name'])?></div>
      <div class="staff-title"><?=e($s['job_title'])?></div>
      <div class="staff-badges">
        <span class="badge <?=$s['is_active']?'badge-active':'badge-inactive'?>">
          <?=$s['is_active']?'Active':'Inactive'?>
        </span>
        <?php if ($s['story_count'] > 0): ?>
        <span class="badge"><?=$s['story_count']?> stories</span>
        <?php endif; ?>
        <?php if ($s['system_prompt']): ?>
        <span class="badge" style="border-color:var(--or);color:var(--or);">Custom Prompt</span>
        <?php endif; ?>
      </div>
      <?php if ($s['bio']): ?>
      <div class="staff-meta" style="line-height:1.5;"><?=e(substr($s['bio'], 0, 120))?><?=strlen($s['bio'])>120?'...':''?></div>
      <?php endif; ?>
      <div class="staff-actions">
        <a href="/admin/staff.php?edit=<?=$s['id']?>">Edit</a>
        <a href="/staff?slug=<?=e($s['slug'])?>" target="_blank">View</a>
        <a href="/admin/staff.php?delete=<?=$s['id']?>&tok=<?=md5(ADMIN_PASSWORD . $s['id'])?>" class="del"
           onclick="return confirm('Delete <?=e($s['display_name'])?>? This cannot be undone.')">Delete</a>
      </div>
    </div>
  </div>

<?php endforeach;
if ($currentTier !== null) echo '</div>'; // close last grid
?>

<?php if (empty($allStaff)): ?>
<div style="text-align:center;padding:60px 40px;color:var(--mu);font-family:'Cormorant Garamond',serif;font-style:italic;font-size:18px;">
  No staff yet. <a href="/admin/staff.php?new=1" style="color:var(--gd);">Create your first agent.</a>
</div>
<?php endif; ?>

<?php endif; ?>

</div><!-- /wrap -->
</body></html>
