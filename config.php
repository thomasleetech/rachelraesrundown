<?php
/**
 * Rachel Rae's Rundown — config.php
 * Load env, define constants, helpers
 */

// Load .env file
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}

date_default_timezone_set('America/Chicago');

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'rachel_rae_rundown');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('ANTHROPIC_API_KEY', $_ENV['ANTHROPIC_API_KEY'] ?? '');
define('FAL_API_KEY',       $_ENV['FAL_API_KEY']       ?? '');
define('CRON_KEY',          $_ENV['CRON_KEY']          ?? '');
define('SITE_URL',    $_ENV['SITE_URL']    ?? 'https://rachelmoreno.com');
define('SITE_DOMAIN', parse_url(SITE_URL, PHP_URL_HOST) ?? 'rachelmoreno.com');

// Helper: build a site email address from the live domain
function siteMail(string $prefix): string {
    return $prefix . '@' . SITE_DOMAIN;
}
define('ADMIN_PASSWORD', $_ENV['ADMIN_PASSWORD'] ?? 'changeme_immediately');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');

// PDO singleton
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            if (APP_ENV === 'development') {
                die('DB connection failed: ' . $e->getMessage());
            }
            http_response_code(503);
            die('Service temporarily unavailable.');
        }
    }
    return $pdo;
}

// Settings helper
function getSetting(string $key, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        $stmt = getDB()->prepare('SELECT `value` FROM settings WHERE `key` = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $cache[$key] = $row ? ($row['value'] ?? $default) : $default;
    }
    return $cache[$key];
}

function setSetting(string $key, string $value): void {
    getDB()->prepare(
        'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `value` = ?, updated_at = NOW()'
    )->execute([$key, $value, $value]);
}

// URL slug generator
function slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

// Safe HTML output
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Cron logging
function logCron(string $job, string $status, int $generated = 0, int $published = 0, string $error = '', int $tokens = 0, float $cost = 0.0): void {
    getDB()->prepare(
        'INSERT INTO cron_log (job_name, status, articles_generated, articles_published, error_msg, tokens_used, cost_usd)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([$job, $status, $generated, $published, $error, $tokens, $cost]);
}