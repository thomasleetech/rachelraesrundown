<?php
/**
 * Rachel Rae's Rundown — admin/articles.php
 */
session_start();
require_once __DIR__ . '/../config.php';
if (!($_SESSION['rrr_admin'] ?? false)) { header('Location: /admin/'); exit; }

$pdo = getDB();

// ---- Quick single-row actions (MUST be before any output) ----
if (isset($_GET['quick_pub'], $_GET['tok']) && $_GET['tok'] === md5(ADMIN_PASSWORD.(int)$_GET['quick_pub'])) {
    $pdo->prepare("UPDATE articles SET status='live' WHERE id=?")->execute([(int)$_GET['quick_pub']]);
    header('Location: /admin/articles.php?msg=published'); exit;
}
if (isset($_GET['del'], $_GET['tok']) && $_GET['tok'] === md5(ADMIN_PASSWORD.(int)$_GET['del'])) {
    $pdo->prepare("DELETE FROM articles WHERE id=?")->execute([(int)$_GET['del']]);
    header('Location: /admin/articles.php?msg=deleted'); exit;
}

// ---- Bulk actions ----
if ($_POST['action'] ?? null) {
    $ids = array_map('intval', $_POST['ids'] ?? []);
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        match ($_POST['action']) {
            'publish' => $pdo->prepare("UPDATE articles SET status='live' WHERE id IN ($ph)")->execute($ids),
            'draft'   => $pdo->prepare("UPDATE articles SET status='draft' WHERE id IN ($ph)")->execute($ids),
            'archive' => $pdo->prepare("UPDATE articles SET status='archived' WHERE id IN ($ph)")->execute($ids),
            'delete'  => $pdo->prepare("DELETE FROM articles WHERE id IN ($ph)")->execute($ids),
            default   => null,
        };
    }
    header('Location: /admin/articles.php?msg=done');
    exit;
}

// ---- Filters ----
$status  = $_GET['status']  ?? '';
$section = $_GET['section'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 30;
$offset  = ($page - 1) * $limit;

$where  = [];
$params = [];
if ($status)  { $where[] = 'a.status = ?';  $params[] = $status; }
if ($section) { $where[] = 'a.section = ?'; $params[] = $section; }
$wClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM articles a $wClause");
$total->execute($params);
$total = (int) $total->fetchColumn();

$params2 = $params; $params2[] = $limit; $params2[] = $offset;
$stmt = $pdo->prepare(
    "SELECT a.*, s.display_name AS author_name
     FROM articles a LEFT JOIN staff s ON a.author_id = s.id
     $wClause ORDER BY a.created_at DESC LIMIT ? OFFSET ?"
);
$stmt->execute($params2);
$articles = $stmt->fetchAll();

$sections = $pdo->query("SELECT DISTINCT section FROM articles ORDER BY section")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Articles — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@300;400;500&family=Cormorant+Garamond:wght@700&display=swap" rel="stylesheet">
<style>
:root{--bg:#1A1714;--sf:#222018;--bd:#3A3530;--tx:#C2BCB2;--mu:#6A6460;--gd:#C8960F;--or:#B84A18;--te:#276B61;}
*{margin:0;padding:0;box-sizing:border-box;}body{background:var(--bg);color:var(--tx);font-family:'DM Mono',monospace;font-size:12px;}
header{background:#0E0C0A;border-bottom:1px solid var(--bd);padding:12px 28px;display:flex;align-items:center;justify-content:space-between;}
header h1{font-family:'Cormorant Garamond',serif;color:var(--gd);font-size:24px;}
nav a{color:var(--mu);text-decoration:none;margin-left:20px;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;}
nav a:hover{color:var(--gd);}
.wrap{max-width:1400px;margin:0 auto;padding:24px 28px;}
.filters{display:flex;gap:10px;margin-bottom:18px;align-items:center;flex-wrap:wrap;}
select,input{background:#0E0C0A;border:1px solid var(--bd);color:var(--tx);font-family:'DM Mono',monospace;font-size:11px;padding:6px 10px;outline:none;}
button,.btn{background:var(--gd);color:#28241F;border:none;font-family:'DM Mono',monospace;font-size:10px;letter-spacing:0.1em;text-transform:uppercase;padding:6px 14px;cursor:pointer;}
.btn-red{background:var(--or);}
table{width:100%;border-collapse:collapse;}
th{font-size:9px;letter-spacing:0.14em;text-transform:uppercase;color:var(--mu);padding:7px 10px;text-align:left;font-weight:400;border-bottom:1px solid var(--bd);}
td{padding:9px 10px;border-bottom:1px solid #252118;vertical-align:top;}
tr:hover td{background:#211F1A;}
.p{display:inline-block;font-size:9px;letter-spacing:0.06em;padding:2px 7px;border-radius:2px;text-transform:uppercase;}
.p-live{background:#0F3020;color:#5DC490;border:1px solid #1A5035;}
.p-draft{background:#2A2010;color:#C8960F;border:1px solid #4A3820;}
.p-scheduled{background:#1A2835;color:#60A0D0;border:1px solid #254060;}
.p-archived{background:#2A2028;color:#907090;border:1px solid #3A2A3A;}
a.lnk{color:var(--te);text-decoration:none;font-size:11px;} a.lnk:hover{color:var(--gd);}
.pg{display:flex;gap:8px;margin-top:16px;align-items:center;}
.pg a{color:var(--te);text-decoration:none;font-size:11px;padding:4px 10px;border:1px solid var(--bd);}
.pg a:hover{background:var(--sf);}
.pg span{color:var(--mu);font-size:11px;}
</style>
</head><body>
<header>
  <h1>Articles</h1>
  <nav>
    <a href="/admin/">Dashboard</a><a href="/admin/articles.php">Articles</a>
    <a href="/admin/generate.php">Generate</a><a href="/admin/staff.php">Staff</a>
    <a href="/admin/settings.php">Settings</a><a href="/admin/cron_log.php">Cron Log</a>
    <a href="/" target="_blank">Site</a><a href="/admin/?logout=1">Logout</a>
  </nav>
</header>
<div class="wrap">
  <?php if ($_GET['msg'] ?? null): ?>
  <div style="background:#0F3020;color:#5DC490;padding:8px 14px;margin-bottom:14px;font-size:11px;">Action completed.</div>
  <?php endif; ?>

  <form method="get" class="filters">
    <select name="status" onchange="this.form.submit()">
      <option value="">All statuses</option>
      <?php foreach (['live','draft','scheduled','archived'] as $s): ?>
      <option value="<?=$s?>" <?=($status===$s?'selected':'')?>><?=ucfirst($s)?></option>
      <?php endforeach; ?>
    </select>
    <select name="section" onchange="this.form.submit()">
      <option value="">All sections</option>
      <?php foreach ($sections as $sec): ?>
      <option value="<?=e($sec)?>" <?=($section===$sec?'selected':'')?>><?=e(ucfirst($sec))?></option>
      <?php endforeach; ?>
    </select>
    <span style="color:var(--mu);"><?= number_format($total) ?> articles</span>
  </form>

  <form method="post">
    <div style="display:flex;gap:8px;margin-bottom:14px;">
      <select name="action">
        <option value="">Bulk action...</option>
        <option value="publish">→ Publish now</option>
        <option value="draft">→ Draft</option>
        <option value="archive">→ Archive</option>
        <option value="delete">✕ Delete</option>
      </select>
      <button type="submit" onclick="return confirm('Apply to selected?')">Apply</button>
      <label style="color:var(--mu);font-size:11px;display:flex;align-items:center;gap:6px;cursor:pointer;">
        <input type="checkbox" onchange="document.querySelectorAll('input[name=\'ids[]\']').forEach(c=>c.checked=this.checked)">
        Select all
      </label>
    </div>

    <table>
      <thead><tr>
        <th></th>
        <th>#</th><th>Headline</th><th>Section</th><th>Author</th>
        <th>Status</th><th>Published</th><th>Views</th><th>Actions</th>
      </tr></thead>
      <tbody>
        <?php foreach ($articles as $a): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?=$a['id']?>"></td>
          <td style="color:var(--mu);"><?=$a['id']?></td>
          <td style="max-width:280px;">
            <a href="/article/<?=e($a['slug'])?>" target="_blank" style="color:var(--tx);text-decoration:none;">
              <?=e(substr($a['headline'],0,80)).(strlen($a['headline'])>80?'…':'')?>
            </a>
          </td>
          <td style="color:var(--mu);"><?=e($a['section'])?></td>
          <td style="color:var(--mu);white-space:nowrap;"><?=e($a['author_name']??'—')?></td>
          <td><span class="p p-<?=$a['status']?>"><?=$a['status']?></span></td>
          <td style="color:var(--mu);white-space:nowrap;"><?=$a['publish_at']?date('M j Y',strtotime($a['publish_at'])):'—'?></td>
          <td><?=number_format($a['views'])?></td>
          <td style="white-space:nowrap;">
            <a class="lnk" href="/admin/edit.php?id=<?=$a['id']?>">Edit</a> ·
            <?php if ($a['status']!=='live'): ?>
            <a class="lnk" href="/admin/articles.php?quick_pub=<?=$a['id']?>&tok=<?=md5(ADMIN_PASSWORD.$a['id'])?>" style="color:#5DC490;">Live</a> ·
            <?php endif; ?>
            <a class="lnk" href="/admin/articles.php?del=<?=$a['id']?>&tok=<?=md5(ADMIN_PASSWORD.$a['id'])?>"
               onclick="return confirm('Delete this?')" style="color:var(--or);">Del</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </form>

  <div class="pg">
    <?php if ($page > 1): ?><a href="?page=<?=$page-1?>&status=<?=e($status)?>&section=<?=e($section)?>">← Prev</a><?php endif; ?>
    <span>Page <?=$page?> · <?=number_format($total)?> total</span>
    <?php if ($total > $offset + $limit): ?><a href="?page=<?=$page+1?>&status=<?=e($status)?>&section=<?=e($section)?>">Next →</a><?php endif; ?>
  </div>
</div>
</body></html>