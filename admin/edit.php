<?php
/**
 * Rachel Rae's Rundown — admin/edit.php
 */
session_start();
require_once __DIR__ . '/../config.php';
if (!($_SESSION['rrr_admin'] ?? false)) { header('Location: /admin/'); exit; }

$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);
$msg = '';

// Load article or blank
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM articles WHERE id = ?');
    $stmt->execute([$id]);
    $article = $stmt->fetch();
    if (!$article) { header('Location: /admin/articles.php'); exit; }
} else {
    $article = ['id'=>0,'slug'=>'','headline'=>'','dek'=>'','body'=>'','section'=>'news',
                'author_id'=>'','status'=>'draft','hero_emoji'=>'📰','publish_at'=>date('Y-m-d\\TH:i'),
                'is_breaking'=>0,'is_featured'=>0,'tags'=>'[]'];
}

// Load staff for author dropdown
$staff = $pdo->query('SELECT id, display_name FROM staff WHERE is_active=1 ORDER BY sort_order')->fetchAll();

// Load media for this article
$mediaRows = [];
if ($id) {
    $mStmt = $pdo->prepare('SELECT * FROM media WHERE article_id = ? ORDER BY type = "hero" DESC, id ASC');
    $mStmt->execute([$id]);
    $mediaRows = $mStmt->fetchAll();
}

// Handle media deletion
if (isset($_GET['del_media'], $_GET['mtok']) && $id &&
    $_GET['mtok'] === md5(ADMIN_PASSWORD . 'media' . (int)$_GET['del_media'])) {
    $mid  = (int)$_GET['del_media'];
    $mRow = $pdo->prepare('SELECT * FROM media WHERE id = ? AND article_id = ?');
    $mRow->execute([$mid, $id]);
    $mRow = $mRow->fetch();
    if ($mRow) {
        // Delete file from disk if it exists
        if ($mRow['filename']) {
            $path = __DIR__ . '/../uploads/' . $mRow['filename'];
            if (file_exists($path)) unlink($path);
        }
        $pdo->prepare('DELETE FROM media WHERE id = ?')->execute([$mid]);
        // If this was the hero image, clear articles.hero_image too
        if ($mRow['type'] === 'hero' && $mRow['filename']) {
            $pdo->prepare("UPDATE articles SET hero_image = NULL WHERE id = ? AND hero_image = ?")
                ->execute([$id, $mRow['filename']]);
        }
    }
    header("Location: /admin/edit.php?id=$id&msg=media_deleted"); exit;
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'slug'        => slugify($_POST['slug'] ?: $_POST['headline']),
        'headline'    => trim($_POST['headline']),
        'dek'         => trim($_POST['dek']),
        'body'        => $_POST['body'],
        'section'     => trim($_POST['section']),
        'author_id'   => (int)$_POST['author_id'] ?: null,
        'status'      => $_POST['status'],
        'hero_emoji'  => trim($_POST['hero_emoji']) ?: '📰',
        'publish_at'  => $_POST['publish_at'] ?: date('Y-m-d H:i:s'),
        'is_breaking' => isset($_POST['is_breaking']) ? 1 : 0,
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'tags'        => json_encode(array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')))),
        'approved_by' => 'rachel',
    ];

    if ($data['headline']) {
        if ($id) {
            $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
            $pdo->prepare("UPDATE articles SET $set WHERE id = ?")->execute([...array_values($data), $id]);
            $msg = '✓ Saved.';
            // Reload article and media
            $stmt = $pdo->prepare('SELECT * FROM articles WHERE id = ?');
            $stmt->execute([$id]);
            $article = $stmt->fetch();
            $mStmt = $pdo->prepare('SELECT * FROM media WHERE article_id = ? ORDER BY type = "hero" DESC, id ASC');
            $mStmt->execute([$id]);
            $mediaRows = $mStmt->fetchAll();
        } else {
            $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
            $vals = implode(', ', array_fill(0, count($data), '?'));
            $pdo->prepare("INSERT INTO articles ($cols) VALUES ($vals)")->execute(array_values($data));
            $id = (int)$pdo->lastInsertId();
            header("Location: /admin/edit.php?id=$id&msg=created"); exit;
        }
    }
}

$tags      = implode(', ', json_decode($article['tags'] ?? '[]', true) ?: []);
$publishAt = $article['publish_at'] ? date('Y-m-d\\TH:i', strtotime($article['publish_at'])) : date('Y-m-d\\TH:i');
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title><?=$id?'Edit':'New'?> Article — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@300;400;500&family=Cormorant+Garamond:ital,wght@0,700;1,400&family=EB+Garamond:wght@400&display=swap" rel="stylesheet">
<style>
:root{--bg:#1A1714;--sf:#222018;--bd:#3A3530;--tx:#C2BCB2;--mu:#6A6460;--gd:#C8960F;--or:#B84A18;--te:#276B61;}
*{margin:0;padding:0;box-sizing:border-box;}body{background:var(--bg);color:var(--tx);font-family:'DM Mono',monospace;font-size:12px;}
header{background:#0E0C0A;border-bottom:1px solid var(--bd);padding:12px 28px;display:flex;align-items:center;justify-content:space-between;}
header h1{font-family:'Cormorant Garamond',serif;color:var(--gd);font-size:24px;}
nav a{color:var(--mu);text-decoration:none;margin-left:20px;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;}
nav a:hover{color:var(--gd);}
.wrap{max-width:1000px;margin:0 auto;padding:28px;}
.row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:14px;}
.row.full{grid-template-columns:1fr;}
label{display:block;font-size:10px;letter-spacing:0.12em;text-transform:uppercase;color:var(--mu);margin-bottom:5px;}
input,select,textarea{width:100%;background:#0E0C0A;border:1px solid var(--bd);color:var(--tx);font-family:'DM Mono',monospace;font-size:12px;padding:8px 12px;outline:none;resize:vertical;}
textarea.body{font-family:'EB Garamond',Georgia,serif;font-size:15px;line-height:1.7;height:480px;}
.save-bar{position:sticky;top:0;background:#0E0C0A;border-bottom:1px solid var(--bd);padding:10px 28px;display:flex;align-items:center;gap:14px;z-index:100;}
button.save{background:var(--gd);color:#28241F;border:none;font-family:'DM Mono',monospace;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;padding:8px 22px;cursor:pointer;}
.msg{color:#5DC490;font-size:11px;}
.check-row{display:flex;gap:24px;align-items:center;}
.check-row label{display:flex;align-items:center;gap:6px;cursor:pointer;text-transform:none;font-size:12px;color:var(--tx);letter-spacing:0;}
.preview-btn{color:#60A0D0;text-decoration:none;font-size:11px;}
/* Media panel */
.media-panel{background:#0E0C0A;border:1px solid var(--bd);padding:20px;margin-top:28px;}
.media-panel h3{font-family:'Cormorant Garamond',serif;font-size:18px;color:var(--gd);margin-bottom:16px;font-weight:700;}
.media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;}
.media-card{border:1px solid var(--bd);background:var(--sf);overflow:hidden;}
.media-card img{width:100%;height:140px;object-fit:cover;display:block;}
.media-card-placeholder{width:100%;height:140px;background:#1A1714;display:flex;align-items:center;justify-content:center;font-size:11px;color:var(--mu);letter-spacing:0.08em;}
.media-card-info{padding:10px 12px;}
.media-type{font-size:9px;letter-spacing:0.14em;text-transform:uppercase;margin-bottom:4px;}
.media-type-hero{color:var(--gd);}
.media-type-inline{color:var(--te);}
.media-prompt{font-size:10px;color:var(--mu);line-height:1.5;margin-bottom:8px;max-height:52px;overflow:hidden;}
.media-status{font-size:9px;letter-spacing:0.06em;}
.media-status-ok{color:#5DC490;}
.media-status-pending{color:var(--or);}
.media-del{font-size:10px;color:var(--or);text-decoration:none;letter-spacing:0.06em;}
.media-del:hover{text-decoration:underline;}
.no-media{color:var(--mu);font-size:11px;font-style:italic;padding:8px 0;}
</style>
</head><body>
<header>
  <h1><?=$id?'Edit Article #'.$id:'New Article'?></h1>
  <nav>
    <a href="/admin/">Dashboard</a><a href="/admin/articles.php">Articles</a>
    <a href="/admin/generate.php">Generate</a><a href="/admin/settings.php">Settings</a>
    <?php if ($id): ?><a href="/article/<?=e($article['slug'])?>" target="_blank" class="preview-btn">↗ Preview</a><?php endif; ?>
    <a href="/admin/?logout=1">Logout</a>
  </nav>
</header>

<div class="save-bar">
  <button form="editform" type="submit" class="save">Save Article</button>
  <?php if ($msg): ?><span class="msg"><?=e($msg)?></span><?php endif; ?>
  <?php if ($_GET['msg'] ?? null): ?>
    <span class="msg"><?= $_GET['msg'] === 'created' ? '✓ Article created.' : (
                           $_GET['msg'] === 'media_deleted' ? '✓ Image removed.' : '') ?></span>
  <?php endif; ?>
</div>

<div class="wrap">
<form id="editform" method="post">

  <div class="row full">
    <div><label>Headline *</label>
    <input type="text" name="headline" value="<?=e($article['headline'])?>" required
           style="font-family:'Cormorant Garamond',serif;font-size:20px;" placeholder="The headline that will stop the world..."></div>
  </div>

  <div class="row full">
    <div><label>Dek (subheadline)</label>
    <input type="text" name="dek" value="<?=e($article['dek'])?>" placeholder="The sentence that earns the headline..."></div>
  </div>

  <div class="row">
    <div><label>Slug</label>
    <input type="text" name="slug" value="<?=e($article['slug'])?>" placeholder="auto-from-headline"></div>
    <div><label>Hero Emoji</label>
    <input type="text" name="hero_emoji" value="<?=e($article['hero_emoji'])?>" placeholder="📰" style="font-size:24px;"></div>
  </div>

  <div class="row">
    <div><label>Section</label>
    <input type="text" name="section" value="<?=e($article['section'])?>" placeholder="politics, fashion, lgbtq..."></div>
    <div><label>Author</label>
    <select name="author_id">
      <option value="">— Select agent —</option>
      <?php foreach ($staff as $s): ?>
      <option value="<?=$s['id']?>" <?=($article['author_id']==$s['id']?'selected':'')?>><?=e($s['display_name'])?></option>
      <?php endforeach; ?>
    </select></div>
  </div>

  <div class="row">
    <div><label>Status</label>
    <select name="status">
      <?php foreach (['draft','scheduled','live','archived'] as $s): ?>
      <option value="<?=$s?>" <?=($article['status']===$s?'selected':'')?>><?=ucfirst($s)?></option>
      <?php endforeach; ?>
    </select></div>
    <div><label>Publish At</label>
    <input type="datetime-local" name="publish_at" value="<?=e($publishAt)?>"></div>
  </div>

  <div class="row">
    <div><label>Tags (comma-separated)</label>
    <input type="text" name="tags" value="<?=e($tags)?>" placeholder="politics, breaking, satire"></div>
    <div style="padding-top:24px;" class="check-row">
      <label><input type="checkbox" name="is_breaking" <?=($article['is_breaking']?'checked':'')?>> 🔴 Breaking</label>
      <label><input type="checkbox" name="is_featured" <?=($article['is_featured']?'checked':'')?>> ⭐ Featured</label>
    </div>
  </div>

  <div class="row full">
    <div>
      <label>Body (HTML — use &lt;p&gt; tags)</label>
      <textarea name="body" class="body"><?=e($article['body'])?></textarea>
    </div>
  </div>

</form>

<!-- ===== IMAGES PANEL ===== -->
<?php if ($id): ?>
<div class="media-panel">
  <h3>Images</h3>

  <?php if ($mediaRows): ?>
  <div class="media-grid">
    <?php foreach ($mediaRows as $m):
      $hasFile = !empty($m['filename']);
      $delTok  = md5(ADMIN_PASSWORD . 'media' . $m['id']);
    ?>
    <div class="media-card">
      <?php if ($hasFile): ?>
        <img src="/<?=e($m['filepath'])?>" alt="<?=e($m['alt_text'])?>">
      <?php else: ?>
        <div class="media-card-placeholder">PROMPT SAVED<br>NOT YET GENERATED</div>
      <?php endif; ?>
      <div class="media-card-info">
        <div class="media-type media-type-<?=e($m['type'])?>"><?=strtoupper(e($m['type']))?></div>
        <?php if ($m['prompt_used']): ?>
        <div class="media-prompt" title="<?=e($m['prompt_used'])?>">
          <?=e(substr($m['prompt_used'], 0, 120)).(strlen($m['prompt_used'])>120?'…':'')?>
        </div>
        <?php endif; ?>
        <div class="media-status <?=$hasFile?'media-status-ok':'media-status-pending'?>">
          <?=$hasFile ? '✓ Generated · #'.$m['id'] : '⏳ Pending generation · #'.$m['id']?>
        </div>
        <div style="margin-top:6px;">
          <a href="/admin/edit.php?id=<?=$id?>&del_media=<?=$m['id']?>&mtok=<?=$delTok?>"
             class="media-del"
             onclick="return confirm('Remove this image?')">✕ Remove</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php else: ?>
  <div class="no-media">No images for this article. Generate a new story to include AI image prompts.</div>
  <?php endif; ?>

  <?php
  $pendingCount = count(array_filter($mediaRows, fn($m) => empty($m['filename'])));
  if ($pendingCount > 0):
  ?>
  <div style="margin-top:16px;padding:10px 14px;background:#1A1208;border:1px solid #3A2808;font-size:11px;color:#C8960F;">
    <?=$pendingCount?> image<?=$pendingCount>1?'s':''?> pending generation.
    <?php if (getSetting('image_gen_enabled','0') !== '1'): ?>
      Image generation is currently <strong>off</strong> —
      <a href="/admin/settings.php" style="color:var(--or);">enable it in Settings</a> to generate on next story run.
    <?php else: ?>
      Will generate automatically on next cron run.
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

</div><!-- /wrap -->
</body></html>