<?php
/**
 * Rachel Rae's Rundown — admin/index.php
 * Rachel's command center. Protected by session password.
 */
session_start();
require_once __DIR__ . '/../config.php';

// Simple password auth
if ($_POST['password'] ?? null) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['rrr_admin'] = true;
    } else {
        $loginError = 'Wrong password. Nice try.';
    }
}
if ($_GET['logout'] ?? false) {
    session_destroy();
    header('Location: /admin/');
    exit;
}
if (!($_SESSION['rrr_admin'] ?? false)) { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin — Rachel Rae's Rundown</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Cormorant+Garamond:wght@700&display=swap" rel="stylesheet">
<style>
  body{background:#28241F;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;font-family:'DM Mono',monospace;}
  .box{background:#1A1714;border:1px solid #3A3530;padding:48px;max-width:380px;width:100%;text-align:center;}
  h1{font-family:'Cormorant Garamond',serif;color:#C8960F;font-size:36px;margin-bottom:8px;}
  p{color:#6A6460;font-size:11px;letter-spacing:0.1em;margin-bottom:28px;}
  input[type=password]{width:100%;background:#0E0C0A;border:1px solid #3A3530;color:#F6F1E9;font-family:'DM Mono',monospace;font-size:14px;padding:12px 14px;margin-bottom:14px;outline:none;box-sizing:border-box;}
  button{width:100%;background:#C8960F;color:#28241F;border:none;font-family:'DM Mono',monospace;font-size:12px;letter-spacing:0.1em;text-transform:uppercase;padding:12px;cursor:pointer;}
  .err{color:#A83018;font-size:11px;margin-bottom:12px;}
</style>
</head>
<body>
<div class="box">
  <h1>The Bunker</h1>
  <p>Rachel Rae's Rundown · Admin Access</p>
  <?php if (isset($loginError)): ?><div class="err"><?= e($loginError) ?></div><?php endif; ?>
  <form method="post">
    <input type="password" name="password" placeholder="Password" autofocus>
    <button type="submit">Enter</button>
  </form>
</div>
</body>
</html>
<?php exit; }

// === AUTHENTICATED — DASHBOARD ===
$pdo = getDB();

// Stats
$totalLive      = $pdo->query("SELECT COUNT(*) FROM articles WHERE status = 'live'")->fetchColumn();
$totalDraft     = $pdo->query("SELECT COUNT(*) FROM articles WHERE status = 'draft'")->fetchColumn();
$totalScheduled = $pdo->query("SELECT COUNT(*) FROM articles WHERE status = 'scheduled'")->fetchColumn();
$totalViews     = $pdo->query("SELECT COALESCE(SUM(views),0) FROM articles WHERE status = 'live'")->fetchColumn();
$todayViews     = $pdo->query("SELECT COALESCE(SUM(views),0) FROM articles WHERE DATE(publish_at) = CURDATE()")->fetchColumn();
$totalTokens    = $pdo->query("SELECT COALESCE(SUM(tokens_used),0) FROM cron_log")->fetchColumn();
$totalCost      = $pdo->query("SELECT COALESCE(SUM(cost_usd),0) FROM cron_log")->fetchColumn();
$lastCron       = $pdo->query("SELECT * FROM cron_log ORDER BY ran_at DESC LIMIT 1")->fetch();
$queueRemaining = $pdo->query("SELECT COUNT(*) FROM topic_queue WHERE used = 0")->fetchColumn();

// Recent articles
$recent = $pdo->query(
    "SELECT a.*, s.display_name AS author_name
     FROM articles a LEFT JOIN staff s ON a.author_id = s.id
     ORDER BY a.created_at DESC LIMIT 20"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bunker — Rachel Rae's Rundown Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@300;400;500&family=Cormorant+Garamond:ital,wght@0,700;1,400&family=EB+Garamond:wght@400&display=swap" rel="stylesheet">
<style>
:root{--bg:#1A1714;--surface:#222018;--border:#3A3530;--text:#C2BCB2;--muted:#6A6460;--gold:#C8960F;--orange:#B84A18;--teal:#276B61;--red:#A83018;}
*{margin:0;padding:0;box-sizing:border-box;}
body{background:var(--bg);color:var(--text);font-family:'DM Mono',monospace;font-size:13px;}
header{background:#0E0C0A;border-bottom:1px solid var(--border);padding:14px 32px;display:flex;align-items:center;justify-content:space-between;}
header h1{font-family:'Cormorant Garamond',serif;color:var(--gold);font-size:28px;font-weight:700;}
header nav a{color:var(--muted);text-decoration:none;margin-left:24px;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;}
header nav a:hover{color:var(--gold);}
.wrap{max-width:1300px;margin:0 auto;padding:32px;}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:32px;}
.stat{background:var(--surface);border:1px solid var(--border);padding:16px 18px;}
.stat-label{font-size:9px;letter-spacing:0.16em;text-transform:uppercase;color:var(--muted);margin-bottom:8px;}
.stat-val{font-size:28px;font-weight:500;color:var(--gold);}
.stat-sub{font-size:10px;color:var(--muted);margin-top:4px;}
table{width:100%;border-collapse:collapse;}
thead tr{border-bottom:1px solid var(--border);}
th{font-size:9px;letter-spacing:0.14em;text-transform:uppercase;color:var(--muted);padding:8px 12px;text-align:left;font-weight:400;}
td{padding:10px 12px;border-bottom:1px solid #2A2620;vertical-align:top;}
tr:hover td{background:#252118;}
.pill{display:inline-block;font-size:9px;letter-spacing:0.08em;padding:2px 8px;border-radius:2px;text-transform:uppercase;}
.pill-live{background:#0F3020;color:#5DC490;border:1px solid #1A5035;}
.pill-draft{background:#2A2010;color:#C8960F;border:1px solid #4A3820;}
.pill-scheduled{background:#1A2835;color:#60A0D0;border:1px solid #254060;}
.pill-archived{background:#2A2028;color:#907090;border:1px solid #3A2A3A;}
a.act{color:var(--teal);text-decoration:none;font-size:11px;}
a.act:hover{color:var(--gold);}
.section-head{font-family:'Cormorant Garamond',serif;font-size:22px;color:var(--gold);border-bottom:1px solid var(--border);padding-bottom:10px;margin:32px 0 16px;}
.cron-ok{color:#5DC490;} .cron-fail{color:var(--red);} .cron-skip{color:var(--muted);}
.gen-form{background:var(--surface);border:1px solid var(--border);padding:24px;margin-bottom:32px;}
.gen-form input,.gen-form select,.gen-form textarea{
  background:#0E0C0A;border:1px solid var(--border);color:var(--text);font-family:'DM Mono',monospace;
  font-size:12px;padding:8px 12px;width:100%;margin-bottom:12px;outline:none;
}
.gen-form button{background:var(--gold);color:#28241F;border:none;font-family:'DM Mono',monospace;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;padding:10px 24px;cursor:pointer;}
</style>
</head>
<body>
<header>
  <h1>The Bunker</h1>
  <nav>
    <a href="/admin/">Dashboard</a>
    <a href="/admin/articles.php">Articles</a>
    <a href="/admin/generate.php">Generate</a>
    <a href="/admin/staff.php">Staff</a>
    <a href="/admin/settings.php">Settings</a>
    <a href="/admin/cron_log.php">Cron Log</a>
    <a href="/" target="_blank">View Site</a>
    <a href="/admin/?logout=1">Logout</a>
  </nav>
</header>

<div class="wrap">
  <div class="stats">
    <div class="stat"><div class="stat-label">Live Articles</div><div class="stat-val"><?= number_format($totalLive) ?></div></div>
    <div class="stat"><div class="stat-label">Scheduled</div><div class="stat-val"><?= number_format($totalScheduled) ?></div></div>
    <div class="stat"><div class="stat-label">Drafts</div><div class="stat-val"><?= number_format($totalDraft) ?></div></div>
    <div class="stat"><div class="stat-label">Total Views</div><div class="stat-val"><?= number_format($totalViews) ?></div><div class="stat-sub">Today: <?= number_format($todayViews) ?></div></div>
    <div class="stat"><div class="stat-label">Queue Left</div><div class="stat-val"><?= number_format($queueRemaining) ?></div><div class="stat-sub">Topic prompts</div></div>
    <div class="stat"><div class="stat-label">API Cost Total</div><div class="stat-val">$<?= number_format($totalCost, 2) ?></div><div class="stat-sub"><?= number_format($totalTokens) ?> tokens</div></div>
    <div class="stat"><div class="stat-label">Last Cron</div>
      <div class="stat-val" style="font-size:13px;margin-top:4px;">
        <?php if ($lastCron): ?>
          <span class="cron-<?= $lastCron['status'] ?>"><?= $lastCron['status'] ?></span><br>
          <span style="font-size:11px;color:var(--muted);"><?= date('M j H:i', strtotime($lastCron['ran_at'])) ?></span>
        <?php else: ?>
          <span class="cron-skip">never</span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="section-head">Recent Articles</div>
  <table>
    <thead>
      <tr>
        <th>#</th><th>Headline</th><th>Section</th><th>Author</th><th>Status</th><th>Published</th><th>Views</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($recent as $a): ?>
      <tr>
        <td style="color:var(--muted);"><?= $a['id'] ?></td>
        <td style="max-width:320px;"><a href="/article/<?= e($a['slug']) ?>" target="_blank" style="color:var(--text);text-decoration:none;"><?= e($a['headline']) ?></a></td>
        <td style="color:var(--muted);"><?= e($a['section']) ?></td>
        <td style="color:var(--muted);"><?= e($a['author_name'] ?? '—') ?></td>
        <td><span class="pill pill-<?= $a['status'] ?>"><?= $a['status'] ?></span></td>
        <td style="color:var(--muted);white-space:nowrap;"><?= $a['publish_at'] ? date('M j Y H:i', strtotime($a['publish_at'])) : '—' ?></td>
        <td><?= number_format($a['views']) ?></td>
        <td>
          <a class="act" href="/admin/edit.php?id=<?= $a['id'] ?>">Edit</a> ·
          <?php if ($a['status'] === 'draft' || $a['status'] === 'scheduled'): ?>
            <a class="act" href="/admin/publish.php?id=<?= $a['id'] ?>&tok=<?= md5(ADMIN_PASSWORD . $a['id']) ?>" style="color:#5DC490;">Publish</a> ·
          <?php endif; ?>
          <a class="act" href="/admin/delete.php?id=<?= $a['id'] ?>&tok=<?= md5(ADMIN_PASSWORD . $a['id']) ?>" style="color:var(--red);" onclick="return confirm('Delete this story?')">Delete</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
</body>
</html>
