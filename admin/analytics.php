<?php
/**
 * Rachel Rae's Rundown — admin/analytics.php
 * Visitor analytics dashboard.
 */
session_start();
require_once __DIR__ . '/../config.php';
if (!($_SESSION['rrr_admin'] ?? false)) { header('Location: /admin/'); exit; }

$pdo = getDB();

// Ensure table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS visitor_log (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address      VARCHAR(45) DEFAULT NULL,
    user_agent      TEXT DEFAULT NULL,
    page_path       VARCHAR(500) DEFAULT NULL,
    article_id      INT UNSIGNED DEFAULT NULL,
    referrer        VARCHAR(1000) DEFAULT NULL,
    country         VARCHAR(100) DEFAULT NULL,
    city            VARCHAR(200) DEFAULT NULL,
    device_type     VARCHAR(50) DEFAULT NULL,
    browser         VARCHAR(100) DEFAULT NULL,
    os              VARCHAR(100) DEFAULT NULL,
    session_id      VARCHAR(100) DEFAULT NULL,
    visited_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_visited (visited_at),
    INDEX idx_page (page_path(191)),
    INDEX idx_article (article_id),
    INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Stats
$totalVisits    = $pdo->query("SELECT COUNT(*) FROM visitor_log")->fetchColumn();
$todayVisits    = $pdo->query("SELECT COUNT(*) FROM visitor_log WHERE DATE(visited_at) = CURDATE()")->fetchColumn();
$uniqueIPs      = $pdo->query("SELECT COUNT(DISTINCT ip_address) FROM visitor_log")->fetchColumn();
$todayUniqueIPs = $pdo->query("SELECT COUNT(DISTINCT ip_address) FROM visitor_log WHERE DATE(visited_at) = CURDATE()")->fetchColumn();

// Top pages
$topPages = $pdo->query("SELECT page_path, COUNT(*) as hits FROM visitor_log GROUP BY page_path ORDER BY hits DESC LIMIT 15")->fetchAll();

// Top countries
$topCountries = $pdo->query("SELECT COALESCE(country, 'Unknown') as country, COUNT(*) as hits FROM visitor_log GROUP BY country ORDER BY hits DESC LIMIT 10")->fetchAll();

// Top cities
$topCities = $pdo->query("SELECT COALESCE(CONCAT(city, ', ', country), 'Unknown') as location, COUNT(*) as hits FROM visitor_log WHERE city IS NOT NULL GROUP BY city, country ORDER BY hits DESC LIMIT 10")->fetchAll();

// Devices
$devices = $pdo->query("SELECT device_type, COUNT(*) as hits FROM visitor_log GROUP BY device_type ORDER BY hits DESC")->fetchAll();

// Browsers
$browsers = $pdo->query("SELECT browser, COUNT(*) as hits FROM visitor_log GROUP BY browser ORDER BY hits DESC LIMIT 8")->fetchAll();

// OS
$osList = $pdo->query("SELECT os, COUNT(*) as hits FROM visitor_log GROUP BY os ORDER BY hits DESC LIMIT 8")->fetchAll();

// Top referrers
$referrers = $pdo->query("SELECT referrer, COUNT(*) as hits FROM visitor_log WHERE referrer IS NOT NULL AND referrer != '' GROUP BY referrer ORDER BY hits DESC LIMIT 10")->fetchAll();

// Recent visits (last 50)
$recent = $pdo->query("SELECT * FROM visitor_log ORDER BY visited_at DESC LIMIT 50")->fetchAll();

// Visits per day (last 14 days)
$dailyVisits = $pdo->query("SELECT DATE(visited_at) as day, COUNT(*) as hits, COUNT(DISTINCT ip_address) as unique_ips FROM visitor_log WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY DATE(visited_at) ORDER BY day DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Analytics — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@300;400;500&family=Cormorant+Garamond:wght@700&display=swap" rel="stylesheet">
<style>
:root{--bg:#1A1714;--sf:#222018;--bd:#3A3530;--tx:#C2BCB2;--mu:#6A6460;--gd:#C8960F;--te:#276B61;}
*{margin:0;padding:0;box-sizing:border-box;}body{background:var(--bg);color:var(--tx);font-family:'DM Mono',monospace;font-size:12px;}
header{background:#0E0C0A;border-bottom:1px solid var(--bd);padding:12px 28px;display:flex;align-items:center;justify-content:space-between;}
header h1{font-family:'Cormorant Garamond',serif;color:var(--gd);font-size:24px;}
nav a{color:var(--mu);text-decoration:none;margin-left:20px;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;}
nav a:hover{color:var(--gd);}
.wrap{max-width:1400px;margin:0 auto;padding:24px 28px;}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:28px;}
.stat{background:var(--sf);border:1px solid var(--bd);padding:14px 18px;}
.stat-l{font-size:9px;letter-spacing:0.14em;text-transform:uppercase;color:var(--mu);margin-bottom:6px;}
.stat-v{font-size:26px;color:var(--gd);}
.stat-sub{font-size:10px;color:var(--mu);margin-top:2px;}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px;}
@media(max-width:900px){.grid2{grid-template-columns:1fr;}}
.panel{background:var(--sf);border:1px solid var(--bd);padding:18px 20px;}
.panel h3{font-family:'Cormorant Garamond',serif;font-size:18px;color:var(--gd);margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid var(--bd);}
.panel-row{display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #2A2620;font-size:11px;}
.panel-row:last-child{border:none;}
.panel-val{color:var(--gd);font-weight:500;}
table{width:100%;border-collapse:collapse;}
th{font-size:9px;letter-spacing:0.12em;text-transform:uppercase;color:var(--mu);padding:6px 8px;text-align:left;font-weight:400;border-bottom:1px solid var(--bd);}
td{padding:6px 8px;border-bottom:1px solid #252118;font-size:10px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
tr:hover td{background:#211F1A;}
.sec-head{font-family:'Cormorant Garamond',serif;font-size:22px;color:var(--gd);border-bottom:1px solid var(--bd);padding-bottom:10px;margin:24px 0 14px;}
</style>
</head><body>
<header><h1>Analytics</h1>
  <nav>
    <a href="/admin/">Dashboard</a><a href="/admin/articles.php">Articles</a>
    <a href="/admin/generate.php">Generate</a><a href="/admin/settings.php">Settings</a>
    <a href="/admin/analytics.php">Analytics</a><a href="/admin/cron_log.php">Cron Log</a>
    <a href="/admin/?logout=1">Logout</a>
  </nav>
</header>

<div class="wrap">
  <div class="stats">
    <div class="stat"><div class="stat-l">Total Pageviews</div><div class="stat-v"><?= number_format($totalVisits) ?></div></div>
    <div class="stat"><div class="stat-l">Today's Pageviews</div><div class="stat-v"><?= number_format($todayVisits) ?></div></div>
    <div class="stat"><div class="stat-l">Unique Visitors (All Time)</div><div class="stat-v"><?= number_format($uniqueIPs) ?></div></div>
    <div class="stat"><div class="stat-l">Unique Visitors (Today)</div><div class="stat-v"><?= number_format($todayUniqueIPs) ?></div></div>
  </div>

  <!-- Daily Breakdown -->
  <?php if ($dailyVisits): ?>
  <div class="panel" style="margin-bottom:20px;">
    <h3>Last 14 Days</h3>
    <table>
      <thead><tr><th>Date</th><th>Pageviews</th><th>Unique IPs</th></tr></thead>
      <tbody>
        <?php foreach ($dailyVisits as $d): ?>
        <tr>
          <td><?= e($d['day']) ?></td>
          <td style="color:var(--gd);"><?= number_format($d['hits']) ?></td>
          <td><?= number_format($d['unique_ips']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <div class="grid2">
    <!-- Top Pages -->
    <div class="panel">
      <h3>Top Pages</h3>
      <?php foreach ($topPages as $p): ?>
      <div class="panel-row"><span style="max-width:200px;overflow:hidden;text-overflow:ellipsis;"><?= e($p['page_path']) ?></span><span class="panel-val"><?= number_format($p['hits']) ?></span></div>
      <?php endforeach; ?>
    </div>

    <!-- Countries -->
    <div class="panel">
      <h3>Top Countries</h3>
      <?php foreach ($topCountries as $c): ?>
      <div class="panel-row"><span><?= e($c['country']) ?></span><span class="panel-val"><?= number_format($c['hits']) ?></span></div>
      <?php endforeach; ?>
    </div>

    <!-- Cities -->
    <div class="panel">
      <h3>Top Cities</h3>
      <?php foreach ($topCities as $c): ?>
      <div class="panel-row"><span><?= e($c['location']) ?></span><span class="panel-val"><?= number_format($c['hits']) ?></span></div>
      <?php endforeach; ?>
    </div>

    <!-- Devices -->
    <div class="panel">
      <h3>Devices</h3>
      <?php foreach ($devices as $d): ?>
      <div class="panel-row"><span><?= e($d['device_type'] ?: 'Unknown') ?></span><span class="panel-val"><?= number_format($d['hits']) ?></span></div>
      <?php endforeach; ?>
    </div>

    <!-- Browsers -->
    <div class="panel">
      <h3>Browsers</h3>
      <?php foreach ($browsers as $b): ?>
      <div class="panel-row"><span><?= e($b['browser']) ?></span><span class="panel-val"><?= number_format($b['hits']) ?></span></div>
      <?php endforeach; ?>
    </div>

    <!-- OS -->
    <div class="panel">
      <h3>Operating Systems</h3>
      <?php foreach ($osList as $o): ?>
      <div class="panel-row"><span><?= e($o['os']) ?></span><span class="panel-val"><?= number_format($o['hits']) ?></span></div>
      <?php endforeach; ?>
    </div>

    <!-- Top Referrers -->
    <div class="panel">
      <h3>Top Referrers</h3>
      <?php if ($referrers): ?>
        <?php foreach ($referrers as $r): ?>
        <div class="panel-row"><span style="max-width:220px;overflow:hidden;text-overflow:ellipsis;"><?= e($r['referrer']) ?></span><span class="panel-val"><?= number_format($r['hits']) ?></span></div>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="color:var(--mu);font-size:11px;">No referrer data yet.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Visits -->
  <div class="sec-head">Recent Visits</div>
  <div style="overflow-x:auto;">
  <table>
    <thead><tr>
      <th>Time</th><th>IP</th><th>Page</th><th>Country</th><th>City</th>
      <th>Device</th><th>Browser</th><th>OS</th><th>Referrer</th>
    </tr></thead>
    <tbody>
      <?php foreach ($recent as $v): ?>
      <tr>
        <td style="white-space:nowrap;color:var(--mu);"><?= date('M j H:i', strtotime($v['visited_at'])) ?></td>
        <td><?= e($v['ip_address'] ?? '') ?></td>
        <td title="<?= e($v['page_path'] ?? '') ?>"><?= e(substr($v['page_path'] ?? '', 0, 40)) ?></td>
        <td><?= e($v['country'] ?? '—') ?></td>
        <td><?= e($v['city'] ?? '—') ?></td>
        <td><?= e($v['device_type'] ?? '—') ?></td>
        <td><?= e($v['browser'] ?? '—') ?></td>
        <td><?= e($v['os'] ?? '—') ?></td>
        <td title="<?= e($v['referrer'] ?? '') ?>"><?= e(substr($v['referrer'] ?? '', 0, 30)) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
</body></html>
