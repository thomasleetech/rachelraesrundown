<?php
/**
 * Rachel Rae's Rundown — admin/cron_runner.php
 * Executes a cron PHP file via CLI and streams output back to the browser.
 * Called by cron_log.php via fetch(). Requires a valid CRON_KEY.
 */
session_start();
require_once __DIR__ . '/../config.php';

// Must be logged in as admin
if (!($_SESSION['rrr_admin'] ?? false)) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

// Must have a CRON_KEY configured
if (!CRON_KEY) {
    http_response_code(503);
    echo 'CRON_KEY not configured. Add it to .env first.';
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$jobFile = $input['job'] ?? '';
$key     = $input['key'] ?? '';

// Validate key
if ($key !== CRON_KEY) {
    http_response_code(403);
    echo 'Invalid key.';
    exit;
}

// Whitelist — only allow known cron files, no path traversal
$allowedJobs = [
    '../cron/generate_stories.php',
    '../cron/publish_scheduled.php',
    '../cron/replenish_queue.php',
    '../cron/social_queue.php',
];

if (!in_array($jobFile, $allowedJobs, true)) {
    http_response_code(400);
    echo 'Unknown job: ' . htmlspecialchars($jobFile);
    exit;
}

$absPath = realpath(__DIR__ . '/' . $jobFile);

if (!$absPath || !file_exists($absPath)) {
    http_response_code(404);
    echo 'Job file not found.';
    exit;
}

// Stream output
header('Content-Type: text/plain; charset=utf-8');
header('X-Accel-Buffering: no'); // Disable nginx buffering
header('Cache-Control: no-cache');

if (ob_get_level()) ob_end_flush();

$phpBin = PHP_BINARY ?: 'php';
$cmd    = escapeshellarg($phpBin) . ' ' . escapeshellarg($absPath) . ' 2>&1';

$proc = popen($cmd, 'r');
if (!$proc) {
    echo "[ERROR] Failed to start process.\n";
    exit;
}

while (!feof($proc)) {
    $line = fgets($proc, 1024);
    if ($line !== false) {
        echo $line;
        flush();
    }
}

pclose($proc);