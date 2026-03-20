<?php
/**
 * Rachel Rae's Rundown — admin/cron_log.php
 */
session_start();
require_once __DIR__ . '/../config.php';
if (!($_SESSION['rrr_admin'] ?? false)) { header('Location: /admin/'); exit; }

$pdo         = getDB();
$logs        = $pdo->query('SELECT * FROM cron_log ORDER BY ran_at DESC LIMIT 100')->fetchAll();
$totalCost   = $pdo->query('SELECT COALESCE(SUM(cost_usd),0) FROM cron_log')->fetchColumn();
$totalTokens = $pdo->query('SELECT COALESCE(SUM(tokens_used),0) FROM cron_log')->fetchColumn();

$cronJobs = [
    'generate_stories' => [
        'label' => 'Generate Stories',
        'file'  => '../cron/generate_stories.php',
        'desc'  => 'Pulls topics from queue, calls agents, inserts articles.',
        'color' => '#C8960F',
    ],
    'publish_scheduled' => [
        'label' => 'Publish Scheduled',
        'file'  => '../cron/publish_scheduled.php',
        'desc'  => 'Promotes scheduled articles whose publish_at has passed.',
        'color' => '#5DC490',
    ],
    'replenish_queue' => [
        'label' => 'Replenish Queue',
        'file'  => '../cron/replenish_queue.php',
        'desc'  => 'Generates 20 new story prompts via The Desk if queue is low.',
        'color' => '#60A0D0',
    ],
    'social_queue' => [
        'label' => 'Social Queue',
        'file'  => '../cron/social_queue.php',
        'desc'  => 'Writes social copy for today\'s top stories (requires enable in Settings).',
        'color' => '#907090',
    ],
];
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Cron Log — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@300;400;500&family=Cormorant+Garamond:wght@700&display=swap" rel="stylesheet">
<style>
:root{--bg:#1A1714;--sf:#222018;--bd:#3A3530;--tx:#C2BCB2;--mu:#6A6460;--gd:#C8960F;--or:#B84A18;--te:#276B61;}
*{margin:0;padding:0;box-sizing:border-box;}body{background:var(--bg);color:var(--tx);font-family:'DM Mono',monospace;font-size:12px;}
header{background:#0E0C0A;border-bottom:1px solid var(--bd);padding:12px 28px;display:flex;align-items:center;justify-content:space-between;}
header h1{font-family:'Cormorant Garamond',serif;color:var(--gd);font-size:24px;}
nav a{color:var(--mu);text-decoration:none;margin-left:20px;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;}
nav a:hover{color:var(--gd);}
.wrap{max-width:1300px;margin:0 auto;padding:24px 28px;}
.stats{display:flex;gap:20px;margin-bottom:28px;flex-wrap:wrap;}
.stat{background:var(--sf);border:1px solid var(--bd);padding:14px 18px;min-width:140px;}
.stat-l{font-size:9px;letter-spacing:0.14em;text-transform:uppercase;color:var(--mu);margin-bottom:6px;}
.stat-v{font-size:22px;color:var(--gd);}
/* Manual run panel */
.run-panel{margin-bottom:32px;}
.run-panel h2{font-family:'Cormorant Garamond',serif;font-size:22px;color:var(--gd);margin-bottom:14px;font-weight:700;}
.job-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;margin-bottom:20px;}
.job-card{background:var(--sf);border:1px solid var(--bd);padding:16px 18px;}
.job-name{font-size:12px;font-weight:500;margin-bottom:3px;}
.job-desc{font-size:10px;color:var(--mu);line-height:1.5;margin-bottom:12px;}
.run-btn{background:transparent;border:1px solid var(--bd);color:var(--tx);font-family:'DM Mono',monospace;
         font-size:10px;letter-spacing:0.1em;text-transform:uppercase;padding:6px 14px;
         cursor:pointer;transition:border-color 0.15s,color 0.15s;}
.run-btn:hover{border-color:var(--gd);color:var(--gd);}
.run-btn:disabled{opacity:0.4;cursor:not-allowed;}
.run-btn.running{border-color:var(--or);color:var(--or);}
/* Output console */
.console-wrap{display:none;margin-bottom:28px;}
.console-wrap.visible{display:block;}
.console-label{font-size:9px;letter-spacing:0.16em;text-transform:uppercase;color:var(--mu);margin-bottom:6px;display:flex;align-items:center;gap:10px;}
.console-spinner{display:inline-block;width:8px;height:8px;border:1px solid var(--mu);border-top-color:var(--gd);border-radius:50%;animation:spin 0.6s linear infinite;}
.console-spinner.done{display:none;}
@keyframes spin{to{transform:rotate(360deg)}}
.console{background:#0A0908;border:1px solid var(--bd);border-left:3px solid var(--gd);padding:16px 18px;
         font-size:11px;line-height:1.8;color:#9A9080;height:240px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;}
.console .ok{color:#5DC490;}.console .err{color:#C07070;}.console .warn{color:var(--gd);}
/* Log table */
table{width:100%;border-collapse:collapse;}
th{font-size:9px;letter-spacing:0.12em;text-transform:uppercase;color:var(--mu);padding:6px 10px;text-align:left;font-weight:400;border-bottom:1px solid var(--bd);}
td{padding:8px 10px;border-bottom:1px solid #252118;font-size:11px;}
tr:hover td{background:#211F1A;}
.ok{color:#5DC490;}.fail{color:#C07070;}.skipped{color:var(--mu);}
.no-key-warn{background:#1A0E08;border:1px solid #4A2810;padding:12px 16px;font-size:11px;color:#C8960F;margin-bottom:20px;line-height:1.6;}
</style>
</head><body>
<header><h1>Cron Log</h1>
  <nav>
    <a href="/admin/">Dashboard</a><a href="/admin/articles.php">Articles</a>
    <a href="/admin/generate.php">Generate</a><a href="/admin/settings.php">Settings</a>
    <a href="/admin/cron_log.php">Cron Log</a><a href="/admin/?logout=1">Logout</a>
  </nav>
</header>

<div class="wrap">

  <div class="stats">
    <div class="stat"><div class="stat-l">Total Runs</div><div class="stat-v"><?=number_format(count($logs))?></div></div>
    <div class="stat"><div class="stat-l">Total Tokens</div><div class="stat-v"><?=number_format($totalTokens)?></div></div>
    <div class="stat"><div class="stat-l">Total API Cost</div><div class="stat-v">$<?=number_format($totalCost,2)?></div></div>
    <div class="stat"><div class="stat-l">Avg Cost/Run</div><div class="stat-v">$<?=count($logs)?number_format($totalCost/count($logs),4):'0.00'?></div></div>
  </div>

  <!-- Manual run panel -->
  <div class="run-panel">
    <h2>Manual Run</h2>

    <?php if (!CRON_KEY): ?>
    <div class="no-key-warn">
      ⚠ <strong>CRON_KEY not set.</strong> Add <code>CRON_KEY=your_secret_here</code> to your <code>.env</code> file for automated cron runs. Manual runs from this panel still work.
    </div>
    <?php endif; ?>

    <div class="job-grid">
      <?php foreach ($cronJobs as $jobKey => $job): ?>
      <div class="job-card">
        <div class="job-name" style="color:<?=e($job['color'])?>"><?=e($job['label'])?></div>
        <div class="job-desc"><?=e($job['desc'])?></div>
        <button class="run-btn"
                onclick="runJob('<?=e($jobKey)?>', '<?=e($job['file'])?>', this)">
          ▶ Run Now
        </button>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Output console -->
    <div class="console-wrap" id="console-wrap">
      <div class="console-label">
        <span class="console-spinner" id="console-spinner"></span>
        <span id="console-label-text">Output</span>
      </div>
      <div class="console" id="console"></div>
    </div>
  </div>

  <!-- Log table -->
  <h2 style="font-family:'Cormorant Garamond',serif;font-size:22px;color:var(--gd);margin-bottom:14px;font-weight:700;">
    Recent Runs
  </h2>
  <table>
    <thead><tr>
      <th>#</th><th>Job</th><th>Status</th><th>Generated</th><th>Published</th>
      <th>Tokens</th><th>Cost</th><th>Error</th><th>Ran At</th>
    </tr></thead>
    <tbody>
      <?php foreach ($logs as $l): ?>
      <tr>
        <td style="color:var(--mu);"><?=$l['id']?></td>
        <td><?=e($l['job_name'])?></td>
        <td><span class="<?=$l['status']?>"><?=$l['status']?></span></td>
        <td><?=$l['articles_generated']?:'—'?></td>
        <td><?=$l['articles_published']?:'—'?></td>
        <td><?=$l['tokens_used']?number_format($l['tokens_used']):'—'?></td>
        <td><?=$l['cost_usd']>0?'$'.number_format($l['cost_usd'],4):'—'?></td>
        <td style="color:#C07070;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
            title="<?=e($l['error_msg']??'')?>"><?=e(substr($l['error_msg']??'',0,60))?></td>
        <td style="color:var(--mu);white-space:nowrap;"><?=date('M j H:i:s',strtotime($l['ran_at']))?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
async function runJob(jobKey, jobFile, btn) {

  // Lock UI
  document.querySelectorAll('.run-btn').forEach(b => b.disabled = true);
  btn.classList.add('running');
  btn.textContent = '⏳ Running...';

  // Show console
  const wrap    = document.getElementById('console-wrap');
  const console_ = document.getElementById('console');
  const spinner = document.getElementById('console-spinner');
  const label   = document.getElementById('console-label-text');

  wrap.classList.add('visible');
  console_.textContent = '';
  spinner.classList.remove('done');
  label.textContent = 'Running ' + jobKey + '...';
  console_.scrollTop = 0;

  try {
    const resp = await fetch('/admin/cron_runner.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Admin-Session': '1',
      },
      body: JSON.stringify({ job: jobFile }),
    });

    if (!resp.ok) {
      console_.textContent = 'HTTP ' + resp.status + ': ' + await resp.text();
      return;
    }

    // Stream the response line by line
    const reader = resp.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    while (true) {
      const { done, value } = await reader.read();
      if (done) break;
      buffer += decoder.decode(value, { stream: true });
      // Flush complete lines
      const lines = buffer.split('\n');
      buffer = lines.pop();
      for (const line of lines) {
        appendLine(console_, line);
      }
      console_.scrollTop = console_.scrollHeight;
    }
    if (buffer) appendLine(console_, buffer);

    label.textContent = jobKey + ' — completed';

  } catch (err) {
    console_.textContent += '\n[ERROR] ' + err.message;
  } finally {
    spinner.classList.add('done');
    btn.classList.remove('running');
    btn.textContent = '▶ Run Now';
    document.querySelectorAll('.run-btn').forEach(b => b.disabled = false);
    // Reload page after 2s to refresh the log table
    setTimeout(() => location.reload(), 2000);
  }
}

function appendLine(el, line) {
  const span = document.createElement('span');
  if (line.includes('[OK]') || line.includes('[PUB]'))  span.className = 'ok';
  else if (line.includes('[ERR]'))                       span.className = 'err';
  else if (line.includes('[SKIP]') || line.includes('[WARN]')) span.className = 'warn';
  span.textContent = line + '\n';
  el.appendChild(span);
}
</script>
</body></html>