<?php
/**
 * Rachel Rae's Rundown — includes/tracking.php
 * Visitor tracking middleware. Call trackVisit() on each page load.
 */

function trackVisit(string $pagePath = '', int $articleId = 0): void {
    try {
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

        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ip = trim(explode(',', $ip)[0]);
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        $path = $pagePath ?: ($_SERVER['REQUEST_URI'] ?? '/');

        // Parse user agent for device/browser/os
        $device  = 'desktop';
        $browser = 'Unknown';
        $os      = 'Unknown';

        $uaLower = strtolower($ua);
        if (preg_match('/mobile|android|iphone|ipad|ipod/i', $ua)) {
            $device = str_contains($uaLower, 'ipad') || str_contains($uaLower, 'tablet') ? 'tablet' : 'mobile';
        } elseif (preg_match('/bot|crawl|spider|slurp|googlebot/i', $ua)) {
            $device = 'bot';
        }

        if (str_contains($uaLower, 'firefox'))       $browser = 'Firefox';
        elseif (str_contains($uaLower, 'edg/'))      $browser = 'Edge';
        elseif (str_contains($uaLower, 'opr/') || str_contains($uaLower, 'opera')) $browser = 'Opera';
        elseif (str_contains($uaLower, 'chrome'))     $browser = 'Chrome';
        elseif (str_contains($uaLower, 'safari'))     $browser = 'Safari';

        if (str_contains($uaLower, 'windows'))        $os = 'Windows';
        elseif (str_contains($uaLower, 'macintosh') || str_contains($uaLower, 'mac os'))  $os = 'macOS';
        elseif (str_contains($uaLower, 'linux'))      $os = 'Linux';
        elseif (str_contains($uaLower, 'android'))    $os = 'Android';
        elseif (str_contains($uaLower, 'iphone') || str_contains($uaLower, 'ipad'))       $os = 'iOS';

        // Geo lookup via free ip-api.com (non-blocking, cached)
        $country = null;
        $city    = null;
        if ($ip && $ip !== '127.0.0.1' && $ip !== '::1' && $device !== 'bot') {
            $geo = @json_decode(@file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,city", false, stream_context_create(['http' => ['timeout' => 2]])), true);
            if (($geo['status'] ?? '') === 'success') {
                $country = $geo['country'] ?? null;
                $city    = $geo['city'] ?? null;
            }
        }

        // Session tracking
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $sessionId = session_id() ?: null;

        $stmt = $pdo->prepare(
            'INSERT INTO visitor_log (ip_address, user_agent, page_path, article_id, referrer, country, city, device_type, browser, os, session_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $ip,
            substr($ua, 0, 2000),
            substr($path, 0, 500),
            $articleId ?: null,
            substr($referrer, 0, 1000),
            $country,
            $city,
            $device,
            $browser,
            $os,
            $sessionId,
        ]);
    } catch (\Throwable $e) {
        // Silently fail — tracking should never break the site
    }
}
