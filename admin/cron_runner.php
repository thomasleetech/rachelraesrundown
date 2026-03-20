<?php
/**
 * Rachel Rae's Rundown — admin/cron_runner.php
 * Executes a cron PHP file via CLI and streams output back to the browser.
 * Called by cron_log.php via fetch(). Requires admin session.
 * Falls back to direct PHP include if CLI execution fails.
 */
session_start();
require_once __DIR__ . '/../config.php';

// Must be logged in as admin
if (!($_SESSION['rrr_admin'] ?? false)) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$jobFile = $input['job'] ?? '';

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
header('X-Accel-Buffering: no');
header('Cache-Control: no-cache');

if (ob_get_level()) ob_end_flush();

// Try CLI execution first
$phpBin = PHP_BINARY ?: 'php';
$envVars = '';

// Pass essential env vars to CLI process
if (defined('DB_HOST'))   $envVars .= 'DB_HOST=' . escapeshellarg(DB_HOST) . ' ';
if (defined('DB_NAME'))   $envVars .= 'DB_NAME=' . escapeshellarg(DB_NAME) . ' ';
if (defined('DB_USER'))   $envVars .= 'DB_USER=' . escapeshellarg(DB_USER) . ' ';
if (defined('DB_PASS'))   $envVars .= 'DB_PASS=' . escapeshellarg(DB_PASS) . ' ';
if (defined('ANTHROPIC_API_KEY') && ANTHROPIC_API_KEY) $envVars .= 'ANTHROPIC_API_KEY=' . escapeshellarg(ANTHROPIC_API_KEY) . ' ';
if (defined('FAL_API_KEY') && FAL_API_KEY) $envVars .= 'FAL_API_KEY=' . escapeshellarg(FAL_API_KEY) . ' ';
if (defined('CRON_KEY') && CRON_KEY) $envVars .= 'CRON_KEY=' . escapeshellarg(CRON_KEY) . ' ';

$cmd = $envVars . escapeshellarg($phpBin) . ' ' . escapeshellarg($absPath) . ' 2>&1';

$proc = @popen($cmd, 'r');
if (!$proc) {
    // Fallback: include the file directly (for environments where popen is disabled)
    echo "[INFO] CLI unavailable, running inline...\n";
    ob_start();
    try {
        // Override CLI check by setting the header that cron scripts accept
        $_SERVER['HTTP_X_CRON_KEY'] = CRON_KEY ?: 'admin-manual-run';
        include $absPath;
    } catch (\Throwable $e) {
        echo "\n[ERR] " . $e->getMessage() . "\n";
    }
    $output = ob_get_clean();
    echo $output;
    exit;
}

while (!feof($proc)) {
    $line = fgets($proc, 1024);
    if ($line !== false) {
        echo $line;
        flush();
    }
}

$exitCode = pclose($proc);
if ($exitCode !== 0) {
    echo "\n[WARN] Process exited with code $exitCode\n";
}
